{{-- Fiche dossier d'une campagne annuelle, en panneau latéral (2026-10-23). Variable : $l (ligne de RenouvellementService::lignes). --}}
@php
    $c = $l['campagne']; $d = $l['dossier'];
    [$libS, $bgS, $fgS] = \App\Models\Renouvellement::STATUTS[$l['statut']];
    $etapesLib = ['j60' => 'J-60', 'j45' => 'J-45', 'j30' => 'J-30', 'j15' => 'J-15', 'j7' => 'J-7'];
    $couleurs = ['envoye' => '#3aa835', 'aujourdhui' => '#fab80b', 'echec' => '#e20613', 'exclu' => '#9ca3af'];
    $choixStatuts = $peutConfirmer ? \App\Models\Renouvellement::STATUTS : array_diff_key(\App\Models\Renouvellement::STATUTS, ['confirme' => 1]);
    $contact = $l['contact'] ?? null;
    $prochaineEtape = $l['prochain_mail'][0] ?? 'j60';
@endphp
<template x-if="dossier === {{ $c->id }}">
<div>
    <div class="rn-voile" @click="dossier = null" x-transition.opacity></div>
    <aside class="rn-panneau" role="dialog" aria-label="Dossier {{ $c->client?->name }}">
        <header>
            <div style="min-width:0;">
                <div style="font-size:18px;font-weight:800;color:var(--text);">{{ $c->client?->name ?? '—' }}</div>
                <a href="{{ route('admin.campaigns.show', $c) }}" style="font-size:13px;color:var(--accent);font-weight:600;">{{ $c->name }} ↗</a>
                <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;">
                    <span class="rn-chip" style="background:{{ $bgS }};color:{{ $fgS }};">{{ $libS }}</span>
                    <span class="rn-chip" style="background:var(--surface2);color:var(--text2);">{{ $l['jours'] > 0 ? 'Fin dans ' . $l['jours'] . ' j' : 'Terminée' }}</span>
                </div>
            </div>
            <button type="button" class="rn-fermer" @click="dossier = null" aria-label="Fermer">×</button>
        </header>

        {{-- Prochaine action --}}
        <div class="rn-section" style="display:flex;align-items:center;gap:10px;">
            <span class="rn-muted">Prochaine action</span>
            <span class="rn-action {{ $l['action'][1] }}" style="font-size:14px;">{{ $l['action'][0] }}</span>
        </div>

        @if($l['autre_planifiee'] && !in_array($l['statut'], ['confirme', 'refus'], true))
            <div class="rn-section"><div class="rn-alerte jaune">⚠️ Le client a une autre campagne planifiée : <strong>{{ $l['autre_planifiee'] }}</strong>. Si c'est le renouvellement, mettez le statut à jour pour arrêter les relances.</div></div>
        @endif

        {{-- Réponse du client --}}
        @if($d?->reponse_at)
            <div class="rn-section">
                <h4>Réponse du client</h4>
                <div class="rn-alerte bleu">
                    <strong>{{ \App\Services\Diffusion\ModelesMail::CHOIX[$d->reponse_choix] ?? $d->reponse_choix }}</strong>
                    <span style="opacity:.8;"> · le {{ $d->reponse_at->format('d/m/Y à H\hi') }}</span>
                    @if($d->reponse_message)<div style="margin-top:6px;font-style:italic;">« {{ $d->reponse_message }} »</div>@endif
                </div>
            </div>
        @endif

        {{-- Infos --}}
        <div class="rn-section">
            <h4>Campagne et contact</h4>
            <div class="rn-grille">
                <div><div class="lbl">Échéance</div><div class="v">{{ $c->end_date->format('d/m/Y') }}</div></div>
                <div><div class="lbl">Dispositif</div><div class="v">{{ $l['panneaux'] }} panneau{{ $l['panneaux'] > 1 ? 'x' : '' }}</div></div>
                <div><div class="lbl">Montant HT</div><div class="v">{{ $l['montant'] > 0 ? number_format($l['montant'], 0, ',', ' ') . ' FCFA' : '—' }}</div></div>
                <div><div class="lbl">Commercial</div><div class="v">{{ $l['commercial']?->name ?? '—' }}</div></div>
                <div><div class="lbl">Interlocuteur</div><div class="v">{{ $contact['contact'] ?? '—' }}</div></div>
                <div>
                    <div class="lbl">Contact</div>
                    <div class="v" style="font-weight:600;">
                        @if($l['telephone'])<a href="tel:{{ preg_replace('/[^0-9+]/', '', $l['telephone']) }}" style="color:var(--accent);">📞 {{ $l['telephone'] }}</a><br>@endif
                        @if(!empty($contact['email']))<a href="mailto:{{ $contact['email'] }}" style="color:var(--accent);font-size:13px;">{{ $contact['email'] }}</a>@endif
                        @if(!$l['telephone'] && empty($contact['email']))—@endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Mails au client --}}
        <div class="rn-section">
            <h4>Mails au client</h4>
            <ul class="rn-frise">
                @foreach($l['sequence'] as $e)
                    <li>
                        <span class="pt rn-e-{{ $e['etat'] }}" style="{{ isset($couleurs[$e['etat']]) ? 'background:' . $couleurs[$e['etat']] . ';' : '' }}"></span>
                        <span class="et">{{ $etapesLib[$e['etape']] }}</span>
                        <span class="tx">{{ $e['info'] }}</span>
                        <a href="{{ route('admin.renouvellements.apercu-mail', [$c, $e['etape']]) }}" target="_blank" rel="noopener" data-tip="Voir le mail" style="color:var(--text3);text-decoration:none;">👁</a>
                    </li>
                @endforeach
                @if($l['accuse'])
                    <li>
                        <span class="pt" style="background:{{ $l['accuse']->statut === 'envoye' ? '#3aa835' : '#e20613' }};"></span>
                        <span class="et">Accusé</span>
                        <span class="tx">{{ $l['accuse']->statut === 'envoye' ? 'Accusé de réception envoyé le ' . $l['accuse']->envoye_at?->format('d/m/Y') : 'Accusé non envoyé : ' . $l['accuse']->motif }}</span>
                    </li>
                @endif
            </ul>
            <div class="rn-muted" style="margin-top:8px;">Suivi détaillé (ouvert, cliqué) : Brevo → Transactionnel → Journal.</div>
        </div>

        {{-- Actions --}}
        <div class="rn-section">
            <h4>Mettre à jour</h4>
            <form method="POST" action="{{ route('admin.renouvellements.statut', $c) }}">
                @csrf
                <div class="rn-grille">
                    <div>
                        <label class="rn-champ" style="margin-top:0;">Statut</label>
                        <select name="statut" class="rn-input">
                            @foreach($choixStatuts as $k => [$libC])
                                <option value="{{ $k }}" @selected($l['statut'] === $k)>{{ $libC }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="rn-champ" style="margin-top:0;">Rappeler le</label>
                        <input type="date" name="rappel_le" value="{{ $d?->rappel_le?->toDateString() }}" class="rn-input">
                    </div>
                </div>
                <label class="rn-champ">Note d'appel</label>
                <textarea name="note" rows="3" maxlength="2000" class="rn-input" placeholder="Joint, décision, points à préparer…"></textarea>
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;">
                    <button class="rn-btn primaire">Enregistrer</button>
                    <span class="rn-muted" style="align-self:center;">Dès que le statut n'est plus « En attente », les relances s'arrêtent.</span>
                </div>
            </form>

            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;padding-top:14px;border-top:1px dashed var(--border);">
                @if($peutConfirmer && $l['statut'] !== 'confirme')
                    <form method="POST" action="{{ route('admin.renouvellements.confirmer', $c) }}"
                          onsubmit="return confirm('Confirmer le renouvellement ? Les panneaux ne seront plus proposés dans le catalogue des disponibilités.')">
                        @csrf
                        <button class="rn-btn vert">✓ Confirmer le renouvellement</button>
                    </form>
                @endif
                <a href="{{ route('admin.campaigns.show', $c) }}#renouveler" class="rn-btn" data-tip="Ouvre la fiche campagne : crée la campagne suivante avec les mêmes panneaux. Le dossier passe alors en « Renouvellement confirmé ».">🔁 Renouveler la campagne</a>
                @if($estAdmin && $l['statut'] === 'en_attente')
                    <a href="{{ route('admin.renouvellements.modeles', ['cle' => $prochaineEtape, 'campagne' => $c->id]) }}" class="rn-btn" data-tip="Ouvre l'éditeur avec cette campagne pour l'aperçu et l'envoi de test.">✉️ Tester un mail</a>
                @endif
            </div>
        </div>

        {{-- Historique --}}
        <div class="rn-section" style="border-bottom:0;">
            <h4>Historique</h4>
            @if($d && $d->historique)
                <div class="rn-histo">
                    @foreach(array_reverse($d->historique) as $h)
                        <div>
                            <span class="rn-muted">{{ \Carbon\Carbon::parse($h['date'])->format('d/m/Y H\hi') }}@isset($h['auteur']) · {{ $h['auteur'] }}@endisset</span><br>
                            {{ $h['action'] }}@isset($h['texte'])<br><em style="color:var(--text2);">{{ $h['texte'] }}</em>@endisset
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rn-muted">Rien pour l'instant.</div>
            @endif
        </div>
    </aside>
</div>
</template>
