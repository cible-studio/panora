<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Commune;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Support\Facades\DB;

/**
 * Construit une facture FNE pré-remplie depuis une campagne existante.
 *
 * Logique :
 *   1. Récupère client, panneaux (internes + externes), durée
 *   2. Pour chaque panneau, crée UNE ligne :
 *      - PU = unit_price du pivot reservation_panels (prix négocié)
 *        ou panel.monthly_rate (catalogue) en fallback
 *      - dimension_m2 = panel.format.surface_m2
 *      - duree_mois = campaign.billableMonths()
 *      - commune = panel.commune
 *      - tarif ODP/TM résolu via Commune::ratesAt(year-12-31)
 *        (historisation pour cohérence sur recalcul futur)
 *   3. Génère une référence FAC-YYYY-NNN unique
 *   4. Persiste invoice + lignes, recalcule les agrégats via
 *      InvoiceCalculator
 *
 * La facture créée est en statut 'brouillon' — l'admin peut ajuster
 * avant l'envoi. Aucun verrou tant que pas en 'envoyee'.
 */
class InvoiceFromCampaignBuilder
{
    public function __construct(
        protected InvoiceCalculator $calculator,
    ) {}

    /**
     * @param Campaign $campaign
     * @param array{
     *   issued_at?: string|\DateTimeInterface,
     *   remise_pct?: float,
     *   services_impression?: float,
     *   services_pose_depose?: float,
     *   notes_client?: string
     * } $opts
     */
    public function build(Campaign $campaign, array $opts = []): Invoice
    {
        $campaign->loadMissing([
            'client', 'reservation',
            'panels.commune', 'panels.format',
            'externalPanels.commune', 'externalPanels.format',
        ]);

        // ── Garde 1 : la campagne doit avoir au moins 1 panneau ──
        // Sinon on créerait une facture sans aucune ligne → total = 0,
        // pas de sens métier. L'admin doit d'abord attacher des panneaux.
        $totalPanneaux = $campaign->panels->count() + $campaign->externalPanels->count();
        if ($totalPanneaux === 0) {
            throw new \DomainException(
                "Campagne « {$campaign->name} » sans panneau — ajoute au moins un panneau avant de facturer."
            );
        }

        // ── Garde 2 : client requis sur la campagne ──
        if (!$campaign->client_id) {
            throw new \DomainException(
                "Campagne « {$campaign->name} » sans client — impossible de facturer."
            );
        }

        $issuedAt = isset($opts['issued_at'])
            ? \Carbon\Carbon::parse($opts['issued_at'])
            : now();
        $year = (int) $issuedAt->year;

        // Durée facturable canonique CIBLE (cf. Campaign::billableMonths)
        $dureeMois = (float) $campaign->billableMonths();

        // ── Garde 3 : signalement panneaux incomplets ──
        // Si un panneau n'a pas de format (m²) ou pas de commune, sa
        // ligne aura ODP=0 et/ou montant_ht=0. On crée la ligne quand
        // même (l'admin pourra corriger), mais on log un warning pour
        // qu'il sache.
        $missing = [];
        foreach ($campaign->panels as $p) {
            if (!$p->format) $missing[] = "panneau {$p->reference} sans format (m²)";
            if (!$p->commune) $missing[] = "panneau {$p->reference} sans commune (ODP)";
        }
        foreach ($campaign->externalPanels as $p) {
            if (!$p->format) $missing[] = "panneau externe #{$p->id} sans format";
            if (!$p->commune) $missing[] = "panneau externe #{$p->id} sans commune";
        }
        if (!empty($missing)) {
            \Illuminate\Support\Facades\Log::warning('invoice.from_campaign.missing_data', [
                'campaign_id' => $campaign->id,
                'gaps'        => $missing,
            ]);
        }

        // Récupération unit_prices pivot (prix négociés) en une seule query
        $negotiated = [];
        if ($campaign->reservation_id) {
            $rows = DB::table('reservation_panels')
                ->where('reservation_id', $campaign->reservation_id)
                ->get(['panel_id', 'external_panel_id', 'unit_price', 'source']);
            foreach ($rows as $r) {
                if ($r->panel_id) {
                    $negotiated['int_' . $r->panel_id] = (float) $r->unit_price;
                }
                if ($r->external_panel_id) {
                    $negotiated['ext_' . $r->external_panel_id] = (float) $r->unit_price;
                }
            }
        }

        return DB::transaction(function () use ($campaign, $opts, $issuedAt, $year, $dureeMois, $negotiated) {
            // ─── Création facture brouillon ──────────────────────
            // commercial_user_id = COMMERCIAL responsable du compte client
            //   (priorité campaign.commercial_user_id, fallback campaign.user_id)
            // created_by         = utilisateur qui clique "Générer la facture"
            //   (peut être un admin différent du commercial titulaire)
            // → distinct dans le schéma pour permettre les rapports "par
            //   commercial" (cf. § 1 et § 11 du cahier des charges).
            $commercialUserId = $campaign->commercial_user_id ?? $campaign->user_id;
            $createdBy        = auth()->id() ?? $commercialUserId;

            $invoice = Invoice::create([
                'reference'           => $this->generateReference($year),
                'client_id'           => $campaign->client_id,
                'campaign_id'         => $campaign->id,
                'commercial_user_id'  => $commercialUserId,
                'created_by'          => $createdBy,
                'issued_at'           => $issuedAt->toDateString(),
                'status'              => 'brouillon',
                'tva'                 => $this->calculator->tvaRate(),
                'remise_pct'          => (float) ($opts['remise_pct'] ?? 0),
                'services_impression' => (int) round((float) ($opts['services_impression'] ?? 0)),
                'services_pose_depose'=> (int) round((float) ($opts['services_pose_depose'] ?? 0)),
                'campaign_year'       => $year,
                'notes_client'        => $opts['notes_client'] ?? config('billing.payment_terms_default'),
            ]);

            // ─── Lignes panneaux internes ────────────────────────
            // Axes multi-pieds : les pieds d'un même axe donnent UNE seule
            // ligne, portée par la ligne de l'axe (choix du 2026-11-12).
            // Prix = somme des prix HT des pieds réservés ; ODP et TM à 0,
            // saisies à la main par le comptable (cf. grouperParAxe).
            $orderIdx = 0;
            foreach ($this->grouperParAxe($campaign->panels, $negotiated) as $groupe) {
                $this->createLineForPanel(
                    invoice: $invoice,
                    panel: $groupe['porteur'],
                    pivotKey: 'int_' . $groupe['porteur']->id,
                    negotiated: $negotiated,
                    dureeMois: $dureeMois,
                    year: $year,
                    orderIdx: $orderIdx++,
                    campaign: $campaign,
                    puForce: $groupe['pu'],
                    quantite: 1,
                    complement: $groupe['complement'],
                    taxesALaMain: $groupe['taxes_a_la_main'],
                );
            }

            // ─── Lignes panneaux externes ────────────────────────
            // Modèle distinct (ExternalPanel) mais structure proche.
            // On reproduit la même logique pour homogénéité facture.
            foreach ($campaign->externalPanels as $ext) {
                $this->createLineForPanel(
                    invoice: $invoice,
                    panel: $ext,
                    pivotKey: 'ext_' . $ext->id,
                    negotiated: $negotiated,
                    dureeMois: $dureeMois,
                    year: $year,
                    orderIdx: $orderIdx++,
                    isExternal: true,
                    campaign: $campaign,
                );
            }

            // ─── Recalcul agrégats facture ───────────────────────
            return $this->calculator->recalculateAndPersist($invoice);
        });
    }

    /**
     * Crée une InvoiceLine pour un panneau donné (interne ou externe).
     * Le snapshot commune_name + dimension_m2 garantit que la facture
     * reste lisible si la fiche panneau est modifiée plus tard.
     */
    protected function createLineForPanel(
        Invoice $invoice,
        $panel,
        string $pivotKey,
        array $negotiated,
        float $dureeMois,
        int $year,
        int $orderIdx,
        bool $isExternal = false,
        ?Campaign $campaign = null,
        ?float $puForce = null,
        int $quantite = 1,
        ?string $complement = null,
        bool $taxesALaMain = false,
    ): InvoiceLine {
        // $puForce : prix déjà résolu par le regroupement des axes (le prix
        // négocié vit sur les pieds, pas sur la ligne de l'axe qui porte
        // la facture).
        $puHt = $puForce
            ?? $negotiated[$pivotKey]
            ?? (float) ($panel->monthly_rate ?? 0);

        $m2 = $panel->format?->surface_m2 ?? 0;
        $commune = $panel->commune;

        // Résolution tarif historisé (fin d'année concernée pour
        // capturer le tarif valide TOUTE l'année — ratesAt accepte
        // une date entre effective_from et effective_to).
        $rates = $commune?->ratesAt("{$year}-12-31") ?? ['odp' => 0, 'tm' => 1000];

        // Désignation lisible : "ABG-001A — Abengourou rte gare UTI 4×3m"
        $ref  = $panel->reference ?? ($isExternal ? 'EXT-' . $panel->id : 'PAN-' . $panel->id);
        $name = $panel->name ?? '—';
        $dim  = $panel->format?->dimensions_label ?? '';
        $designation = trim("{$ref} — {$name}" . ($dim ? " {$dim}" : '') . ($complement ? " — {$complement}" : ''));

        $line = new InvoiceLine([
            'panel_id'              => $isExternal ? null : $panel->id,
            'commune_id'            => $commune?->id,
            'designation'           => $designation,
            'snapshot_commune_name' => $commune?->name,
            'dimension_m2'          => $m2,
            'pu_ht_mensuel'         => $puHt,
            'quantite'              => $quantite,
            'duree_mois'            => $dureeMois,
            // TX-9 (2026-07-29) — Dates campagne propagées pour calcul auto
            // TM (mois anniversaire) + ODP (trimestres × forfait ×3).
            'campaign_start'        => $campaign?->start_date,
            'campaign_end'          => $campaign?->end_date,
            'odp_rate_applique'     => (float) $rates['odp'],
            'tm_rate_applique'      => (float) $rates['tm'],
            'order_index'           => $orderIdx,
        ]);

        // Axe Lumipub (2026-11-12) : ODP et TM jamais calculées, 0 par
        // défaut, le comptable saisit le montant sur la facture.
        if ($taxesALaMain) {
            $line->odp_amount_override = 0;
            $line->tm_amount_override  = 0;
        }

        // Pré-calcul des montants ligne via le calculateur
        $calc = $this->calculator->calculateLine($line->toArray());
        $line->forceFill($calc);

        $invoice->lines()->save($line);
        return $line;
    }

    /**
     * ═══ AXES MULTI-PIEDS — LA BASCULE (2026-11-11) ═══
     *
     * ⚠ PLUS LUE depuis le 2026-11-12. La règle validée par l'utilisateur
     * la remplace : une ligne par axe, quantité 1, prix = SOMME des pieds
     * réservés, ODP et TM à 0 saisies à la main (cf. grouperParAxe). La
     * question « 11 ODP ou 1 ? » ne se pose donc plus. Constante conservée
     * pour ne rien casser chez d'éventuels lecteurs ; ne pas la réactiver.
     *
     * Historique — **false = phase 1.** Un axe donne UNE ligne de facture, quantité 1,
     * au prix d'UN pied — exactement ce que produisait Panora avant le
     * découpage. L'ODP et la TM sont donc inchangées au franc près, et le
     * découpage ne tranche pas à la place de la direction la question
     * « un axe de 11 pieds, c'est 11 ODP ou 1 ? ».
     *
     * **true = phase 2**, à n'activer qu'une fois cette question tranchée
     * par écrit. La quantité devient le nombre de pieds réservés : le loyer
     * devient juste, MAIS l'ODP et la TM sont multipliées par le même
     * facteur, car `InvoiceCalculator::calculateLine()` applique `quantite`
     * aux trois calculs :
     *
     *     LOYER = pu_ht_mensuel × quantite × duree_mois
     *     ODP   = odp_rate × surface × quantite × mois_ODP
     *     TM    = tm_rate  × surface × quantite × mois_TM
     *
     * Cf. docs/AXES_MULTI_PIEDS.md § 4 et docs/A_TESTER.md § 18.
     */
    public const FACTURER_AU_PIED = false;

    /**
     * Regroupe les pieds d'un même axe en une seule ligne de facture.
     *
     * Clé de groupe : `axe_id ?? id`. Un panneau normal est donc seul dans
     * son groupe, et la ligne d'axe elle-même (présente sur les contrats
     * signés avant le découpage) tombe dans le même groupe que ses pieds.
     *
     * Le **porteur** du groupe est la ligne de l'axe : c'est elle qui donne
     * `panel_id`, la référence, la surface et la commune de la ligne.
     *
     * **Prix (règle validée le 2026-11-12)** : la SOMME des prix HT des
     * pieds réservés — le prix de chaque pied au contrat (négocié par la
     * direction ; 0 = pied offert), à défaut son tarif catalogue. Une ligne
     * d'axe d'un ancien contrat (l'axe entier) garde son propre prix.
     * **ODP et TM** : jamais calculées pour un axe — `taxes_a_la_main`,
     * la ligne naît à 0 et le comptable saisit les montants.
     *
     * @param  iterable<\App\Models\Panel> $panels
     * @param  array<string, float>        $negotiated
     * @return list<array{porteur: \App\Models\Panel, pieds: int, pu: float, complement: ?string, taxes_a_la_main: bool}>
     */
    public function grouperParAxe($panels, array $negotiated): array
    {
        $groupes = [];

        foreach ($panels as $panel) {
            $cle = $panel->axe_id ?? $panel->id;

            $groupes[$cle] ??= ['porteur' => null, 'membres' => []];
            $groupes[$cle]['membres'][] = $panel;

            // La ligne d'axe (axe_id null) porte le groupe si elle est là.
            if ($panel->axe_id === null) {
                $groupes[$cle]['porteur'] = $panel;
            }
        }

        // Prix d'un membre : celui du contrat, sinon le tarif catalogue.
        $prixDe = fn ($p) => (float) ($negotiated['int_' . $p->id] ?? $p->monthly_rate ?? 0);

        // Les axes dont seuls les pieds sont au contrat : charger la ligne
        // parente, avec ce dont createLineForPanel a besoin.
        $aCharger = array_keys(array_filter($groupes, fn ($g) => $g['porteur'] === null));
        if (! empty($aCharger)) {
            $axes = \App\Models\Panel::with(['format', 'commune'])
                ->whereIn('id', $aCharger)
                ->get()
                ->keyBy('id');

            foreach ($aCharger as $cle) {
                // Garde-fou : un axe introuvable (supprimé) ne doit pas
                // faire disparaître la ligne — on retombe sur le pied.
                $groupes[$cle]['porteur'] = $axes->get($cle) ?? $groupes[$cle]['membres'][0];
            }
        }

        $resultat = [];
        foreach ($groupes as $g) {
            $porteur = $g['porteur'];

            // Les pieds du groupe, sans compter la ligne d'axe elle-même.
            $pieds = array_values(array_filter($g['membres'], fn ($p) => $p->axe_id !== null));

            // La ligne d'axe est-elle elle-même au contrat (axe entier) ?
            // Soit dans la campagne ; soit — ancien contrat dont la campagne
            // a été découpée en pieds (axes:decouper-campagnes, 2026-11-13) —
            // sur la réservation, au prix du contrat, sans prix sur ses pieds.
            // La ligne reste alors celle d'avant le découpage, au franc près.
            $axeEntier = collect($g['membres'])->contains(fn ($p) => $p->axe_id === null && $p->taxesSaisiesALaMain())
                || ($pieds && $porteur->axe_id === null && isset($negotiated['int_' . $porteur->id])
                    && ! collect($pieds)->contains(fn ($p) => isset($negotiated['int_' . $p->id])));

            if ($pieds && ! $axeEntier) {
                $pu = array_sum(array_map($prixDe, $pieds));
                $complement = count($pieds) . (count($pieds) > 1 ? ' pieds' : ' pied');
            } else {
                // Panneau ordinaire, ou axe entier d'un ancien contrat.
                $pu = $prixDe($porteur);
                $complement = null;
            }

            $resultat[] = [
                'porteur'         => $porteur,
                'pieds'           => max(1, count($pieds)),
                'pu'              => $pu,
                'complement'      => $complement,
                'taxes_a_la_main' => $porteur->taxesSaisiesALaMain() || $pieds !== [],
            ];
        }

        return $resultat;
    }

    /**
     * Numérotation FAC-YYYY-NNN avec compteur séquentiel sur l'année.
     * Tente jusqu'à 5 fois en cas de collision (très rare).
     */
    protected function generateReference(int $year): string
    {
        $format = config('billing.reference_format', 'FAC-%s-%03d');
        for ($i = 0; $i < 5; $i++) {
            $count = Invoice::whereYear('created_at', $year)->count();
            $ref = sprintf($format, $year, $count + 1 + $i);
            if (!Invoice::where('reference', $ref)->exists()) {
                return $ref;
            }
        }
        // Fallback paranoïaque
        return sprintf($format, $year, time() % 1000);
    }
}
