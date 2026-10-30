<x-admin-layout>
    <x-slot name="title">Diffusion des disponibilités</x-slot>

    @php
        $mois = [1=>'janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
        $jours = ['dimanche','lundi','mardi','mercredi','jeudi','vendredi','samedi'];
        $fmtJour = fn($d) => $jours[$d->dayOfWeek] . ' ' . $d->day . ' ' . $mois[$d->month] . ' ' . $d->year;
        $card = 'background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px 22px;margin-bottom:18px;';
        $h2 = 'margin:0 0 14px;font-size:15px;font-weight:800;color:var(--text);';
        $muted = 'font-size:12px;color:var(--text3);';
        $statutCouleurs = [
            'envoye'   => ['#dcfce7', '#166534', 'Envoyé'],
            'echec'    => ['#fee2e2', '#991b1b', 'Échec'],
            'en_cours' => ['#fef3c7', '#92400e', 'En cours'],
            'prepare'  => ['#e0e7ff', '#3730a3', 'PDF en préparation'],
            'demande'  => ['#f3e8ff', '#6b21a8', 'Demandé — en file d\'attente'],
        ];
    @endphp

    {{-- ═══ BANDEAU D'ÉTAT ═══ --}}
    <div style="{{ $card }}display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
        <div style="font-size:18px;font-weight:800;color:var(--text);margin-right:auto;">📨 Diffusion des disponibilités</div>

        @if($modeTest)
            <span style="padding:5px 12px;border-radius:999px;background:#fef3c7;color:#92400e;font-size:12px;font-weight:700;"
                  title="DIFFUSION_MODE=test : tous les envois partent vers la liste Brevo « Tests internes ».">
                🧪 Mode test — aucun client ne reçoit
            </span>
        @else
            <span style="padding:5px 12px;border-radius:999px;background:#dcfce7;color:#166534;font-size:12px;font-weight:700;">
                🟢 Production — les clients reçoivent
            </span>
        @endif

        <span style="padding:5px 12px;border-radius:999px;background:{{ $autoActif ? '#dcfce7' : 'var(--surface2)' }};color:{{ $autoActif ? '#166534' : 'var(--text2)' }};font-size:12px;font-weight:700;"
              title="DIFFUSION_AUTO dans le .env">
            {{ $autoActif ? '⏰ Envoi automatique activé' : '⏸ Envoi automatique désactivé' }}
        </span>
    </div>

    @unless($brevoConfigure)
        <div class="flash flash-error" style="margin-bottom:18px;">
            ✕ Brevo n'est pas encore configuré : renseignez <code>BREVO_API_KEY</code> et <code>BREVO_SENDER_EMAIL</code> dans le <code>.env</code>
            (guide : <code>docs/DIFFUSION_DISPONIBILITES.md</code>).
        </div>
    @endunless

    @if($musulmanesManquantes)
        <div style="{{ $card }}border-color:#fde68a;background:#fffbeb;">
            <strong style="color:#92400e;">⚠ Fêtes musulmanes à saisir pour {{ implode(' et ', $musulmanesManquantes) }}</strong>
            <div style="font-size:13px;color:#92400e;margin-top:6px;line-height:1.5;">
                Tabaski, Aïd el-Fitr, Maouloud et lendemain de la Nuit du Destin sont fixés chaque année par décret :
                Panora ne peut pas les calculer. Sans eux, un envoi peut tomber un jour férié.
                Ajoutez-les dans la section « Jours fériés » plus bas dès que les dates sont publiées.
            </div>
        </div>
    @endif

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px;">

        {{-- ═══ PROCHAIN ENVOI ═══ --}}
        <div style="{{ $card }}margin-bottom:0;">
            <h2 style="{{ $h2 }}">⏰ Prochain envoi automatique</h2>
            <div style="font-size:22px;font-weight:800;color:var(--accent);">
                {{ ucfirst($fmtJour($prochain['date_envoi'])) }} à {{ config('diffusion.heure_envoi') }}
            </div>
            @if($envoiDecale)
                <div style="{{ $muted }}margin-top:4px;">
                    Décalé : le premier jour de la fenêtre tombe un week-end ou un jour férié.
                </div>
            @endif
            <div style="margin-top:14px;font-size:14px;color:var(--text2);line-height:1.7;">
                {{-- Le catalogue part AVANT sa période (2026-10-30), pour que
                     le client ait ses disponibilités à temps. --}}
                Fenêtre d'envoi : <strong>{{ $prochaineFenetre }}</strong><br>
                Période présentée : <strong>{{ $prochainePeriode }}</strong><br>
                Destinataires : <strong>{{ $modeTest ? 'liste « Tests internes »' : $nbDestinataires . ' client(s)' }}</strong>
                @if($modeTest)<span style="{{ $muted }}"> ({{ $nbDestinataires }} client(s) en production)</span>@endif
                <br>
                Confirmation envoyée à : <span style="{{ $muted }}">{{ $confirmation ? implode(', ', $confirmation) : '— aucune adresse —' }}</span>
            </div>
            @unless($autoActif)
                <div style="{{ $muted }}margin-top:10px;font-style:italic;">
                    L'automatique est désactivé : rien ne partira seul tant que <code>DIFFUSION_AUTO=true</code> n'est pas posé.
                </div>
            @endunless
        </div>

        {{-- ═══ ENVOI MANUEL ═══ --}}
        <div style="{{ $card }}margin-bottom:0;">
            <h2 style="{{ $h2 }}">🚀 Envoi manuel</h2>
            <p style="font-size:13px;color:var(--text2);line-height:1.6;margin:0 0 16px;">
                À utiliser si l'envoi automatique n'est pas parti, ou pour un cas particulier.
                <strong>N'annule pas</strong> l'envoi automatique suivant. Les catalogues PDF
                (Abidjan et intérieur du pays) prennent quelques minutes à générer : l'envoi part
                dans les minutes qui suivent, et la confirmation arrive par mail. Vous pouvez
                fermer la page.
            </p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button type="button" class="btn btn-primary" onclick="DIFFUSION.ouvrir(false)" @disabled(!$brevoConfigure)>
                    Envoyer maintenant…
                </button>
                <button type="button" class="btn btn-ghost" onclick="DIFFUSION.ouvrir(true)" @disabled(!$brevoConfigure)
                        title="Envoie le vrai mail à la liste Brevo « Tests internes » uniquement.">
                    🧪 Envoyer un test
                </button>
            </div>
        </div>

        {{-- ═══ CONFIGURATION BREVO ═══ --}}
        <div style="{{ $card }}margin-bottom:0;">
            <h2 style="{{ $h2 }}">⚙️ Configuration Brevo</h2>
            @php
                $lignes = [
                    ['Clé API',              $brevoConfigure, $brevoConfigure ? 'renseignée' : 'manquante (BREVO_API_KEY)'],
                    ['Expéditeur',           (bool) $config['expediteur'], $config['expediteur'] ?: 'manquant (BREVO_SENDER_EMAIL)'],
                    ['Liste clients',        (bool) $config['liste_clients'], $config['liste_clients'] ? '#' . $config['liste_clients'] : 'manquante (BREVO_LIST_CLIENTS)'],
                    ['Liste Tests internes', (bool) $config['liste_tests'], $config['liste_tests'] ? '#' . $config['liste_tests'] : 'manquante (BREVO_LIST_TESTS)'],
                    ['Modèle de mail',       true, $config['modele'] ? 'modèle Brevo #' . $config['modele'] : 'gabarit Panora par défaut'],
                    ['Page de désinscription', true, $config['desinscription'] ? 'personnalisée' : 'page Brevo par défaut'],
                    ['Retour d\'information (webhook)', $config['webhook'], $config['webhook'] ? 'jeton configuré' : 'manquant (BREVO_WEBHOOK_TOKEN)'],
                ];
            @endphp
            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                @foreach($lignes as [$libelle, $ok, $valeur])
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:7px 0;color:var(--text2);">{{ $ok ? '✅' : '❌' }} {{ $libelle }}</td>
                        <td style="padding:7px 0;text-align:right;color:var(--text);font-weight:600;">{{ $valeur }}</td>
                    </tr>
                @endforeach
            </table>
            <div style="margin-top:14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <button type="button" class="btn btn-ghost btn-sm" id="btn-tester" onclick="DIFFUSION.testerConnexion()" @disabled(!$brevoConfigure)>
                    Tester la connexion
                </button>
                <span id="resultat-connexion" style="font-size:12px;"></span>
            </div>
        </div>
    </div>

    {{-- ═══ JOURNAL ═══ (filtres et mois : 2026-10-28) --}}
    @php $je = $journalEnvois; @endphp
    <div id="journal" style="{{ $card }}margin-top:18px;scroll-margin-top:84px;">
        <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;">
            <h2 style="{{ $h2 }}margin:0;">📋 Journal des envois</h2>
            @if($je['moisDispos']->count() > 1)
                <form method="GET" action="{{ url()->current() }}#journal" style="margin:0;">
                    @if($je['filtre'] !== 'tous')<input type="hidden" name="journal" value="{{ $je['filtre'] }}">@endif
                    <select name="mois" class="filter-input" style="height:34px;" onchange="this.form.submit()" aria-label="Mois">
                        <option value="">Tous les mois</option>
                        @foreach($je['moisDispos'] as $m)
                            <option value="{{ $m }}" @selected($je['mois'] === $m)>{{ ucfirst(\Illuminate\Support\Carbon::createFromFormat('Y-m-d', $m . '-01')->locale('fr')->translatedFormat('F Y')) }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;">
            @foreach(\App\Models\DiffusionEnvoi::FILTRES as $cle => $lib)
                @php $actif = $je['filtre'] === $cle; $n = $je['compteurs'][$cle]; @endphp
                <a href="{{ request()->fullUrlWithQuery(['journal' => $cle === 'tous' ? null : $cle, 'page' => null]) }}#journal"
                   style="display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:999px;font-size:12px;font-weight:700;text-decoration:none;
                          border:1px solid {{ $actif ? 'var(--accent)' : 'var(--border)' }};
                          background:{{ $actif ? 'var(--accent)' : 'transparent' }};color:{{ $actif ? '#14100a' : 'var(--text2)' }};">
                    {{ $lib }}
                    <span style="font-weight:600;{{ $cle === 'echecs' && $n > 0 && !$actif ? 'color:#ef4444;' : 'opacity:.75;' }}">{{ number_format($n, 0, ',', ' ') }}</span>
                </a>
            @endforeach
        </div>

        @if($envois->isEmpty())
            <div style="{{ $muted }}padding:20px 0;text-align:center;">
                {{ $je['compteurs']['tous'] === 0 && !$je['mois'] ? "Aucun envoi pour l'instant." : 'Aucun envoi pour ce choix.' }}
            </div>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;font-size:13px;border-collapse:collapse;min-width:720px;">
                    <thead>
                        <tr style="text-align:left;color:var(--text3);font-size:11px;text-transform:uppercase;letter-spacing:.5px;">
                            <th style="padding:8px 6px;">Date</th>
                            <th style="padding:8px 6px;">Période</th>
                            <th style="padding:8px 6px;">Type</th>
                            <th style="padding:8px 6px;">Statut</th>
                            <th style="padding:8px 6px;text-align:right;">Panneaux</th>
                            <th style="padding:8px 6px;text-align:right;">Destinataires</th>
                            <th style="padding:8px 6px;">PDF</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($envois as $e)
                            @php [$bg, $fg, $lib] = $statutCouleurs[$e->statut] ?? ['var(--surface2)', 'var(--text2)', $e->statut]; @endphp
                            <tr style="border-top:1px solid var(--border);vertical-align:top;">
                                <td style="padding:9px 6px;white-space:nowrap;">{{ ($e->envoye_at ?? $e->created_at)->format('d/m/Y H\hi') }}</td>
                                <td style="padding:9px 6px;">{{ \App\Services\Diffusion\DiffusionCalendrier::libellePeriode($e->periode_debut, $e->periode_fin) }}</td>
                                <td style="padding:9px 6px;">
                                    {{ ucfirst($e->libelleMode()) }}
                                    @if($e->auteur)<div style="{{ $muted }}">{{ $e->auteur->name }}</div>@endif
                                </td>
                                <td style="padding:9px 6px;">
                                    <span style="padding:3px 9px;border-radius:999px;background:{{ $bg }};color:{{ $fg }};font-size:11px;font-weight:700;">{{ $lib }}</span>
                                    @if($e->erreur)
                                        @if(mb_strlen($e->erreur) <= 70)
                                            <div style="font-size:11px;color:#991b1b;margin-top:4px;max-width:340px;">{{ $e->erreur }}</div>
                                        @else
                                            {{-- Message long replié : la ligne reste compacte. --}}
                                            <details style="font-size:11px;color:#991b1b;margin-top:4px;max-width:340px;">
                                                <summary style="cursor:pointer;">{{ \Illuminate\Support\Str::limit($e->erreur, 60) }}</summary>
                                                <div style="margin-top:4px;line-height:1.45;">{{ $e->erreur }}</div>
                                            </details>
                                        @endif
                                    @endif
                                </td>
                                <td style="padding:9px 6px;text-align:right;">
                                    {{ $e->nb_panneaux }}
                                    {{-- Répartition par zone (2026-10-29). Absente des envois
                                         antérieurs : leurs compteurs de zone valent 0. --}}
                                    @if($e->nb_panneaux_abidjan || $e->nb_panneaux_interieur)
                                        <div style="{{ $muted }}white-space:nowrap;">{{ $e->nb_panneaux_abidjan }} ABJ · {{ $e->nb_panneaux_interieur }} int.</div>
                                    @endif
                                </td>
                                <td style="padding:9px 6px;text-align:right;">{{ $e->nb_destinataires }}</td>
                                <td style="padding:9px 6px;white-space:nowrap;">
                                    @php
                                        $catalogues = array_values(array_filter([
                                            ['Complet',  $e->lienPdf()],
                                            ['Abidjan',  $e->lienPdfZone(\App\Models\DiffusionEnvoi::ZONE_ABIDJAN)],
                                            ['Intérieur', $e->lienPdfZone(\App\Models\DiffusionEnvoi::ZONE_INTERIEUR)],
                                        ], fn ($c) => $c[1] !== null));
                                    @endphp
                                    @forelse($catalogues as $c)
                                        <a href="{{ $c[1] }}" target="_blank" rel="noopener" style="color:var(--accent);">{{ $c[0] }}</a>@unless($loop->last)<br>@endunless
                                    @empty
                                        —
                                    @endforelse
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="margin-top:12px;">{{ $envois->links() }}</div>
        @endif
    </div>

    {{-- ═══ FIN DE CAMPAGNE (J-7) ═══ --}}
    @include('admin.diffusion._fin-campagne')

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:18px;">

        {{-- ═══ JOURS FÉRIÉS ═══ --}}
        <div style="{{ $card }}">
            <h2 style="{{ $h2 }}">📅 Jours fériés</h2>
            <p style="{{ $muted }}margin:-6px 0 14px;line-height:1.5;">
                Aucun envoi ces jours-là : il passe au premier jour ouvrable suivant.
                Les fêtes fixes et chrétiennes sont ajoutées automatiquement ; les fêtes musulmanes sont à saisir chaque année.
            </p>

            <form method="POST" action="{{ route('admin.diffusion-dispos.jours-feries.store') }}"
                  style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
                @csrf
                <input type="date" name="date" required class="filter-input" style="height:36px;">
                <input type="text" name="libelle" required maxlength="120" placeholder="Ex. : Tabaski" class="filter-input" style="height:36px;flex:1;min-width:140px;">
                <select name="source" class="filter-select" style="height:36px;">
                    <option value="musulman">Fête musulmane</option>
                    <option value="manuel">Autre</option>
                </select>
                <button class="btn btn-primary btn-sm" style="height:36px;">Ajouter</button>
            </form>

            <div style="max-height:360px;overflow-y:auto;">
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    @forelse($joursFeries as $jf)
                        <tr style="border-top:1px solid var(--border);{{ $jf->date->isPast() && !$jf->date->isToday() ? 'opacity:.5;' : '' }}">
                            <td style="padding:7px 4px;white-space:nowrap;">{{ ucfirst($fmtJour($jf->date)) }}</td>
                            <td style="padding:7px 4px;">
                                {{ $jf->libelle }}
                                @if($jf->source === 'musulman')<span style="{{ $muted }}">· saisi</span>@endif
                            </td>
                            <td style="padding:7px 4px;text-align:right;">
                                <form method="POST" action="{{ route('admin.diffusion-dispos.jours-feries.destroy', $jf) }}"
                                      onsubmit="return confirm('Retirer « {{ addslashes($jf->libelle) }} » des jours fériés ?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-sm" title="Retirer">✕</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td style="{{ $muted }}padding:10px 0;">Aucun jour férié enregistré.</td></tr>
                    @endforelse
                </table>
            </div>
        </div>

        {{-- ═══ ADRESSES EXCLUES ═══ --}}
        <div style="{{ $card }}">
            <h2 style="{{ $h2 }}">🚫 Adresses exclues</h2>
            <p style="{{ $muted }}margin:-6px 0 14px;line-height:1.5;">
                Désinscriptions (lien des mails Brevo ou des mails de fin de campagne), adresses invalides et signalements de spam remontés par Brevo.
                Une adresse exclue ne reçoit plus ni les disponibilités ni le mail de fin de campagne.
                Vous pouvez aussi exclure une adresse à la main (client qui l'a demandé par téléphone).
            </p>

            <form method="POST" action="{{ route('admin.diffusion-dispos.exclusions.store') }}"
                  style="display:flex;gap:8px;margin-bottom:14px;">
                @csrf
                <input type="email" name="email" required maxlength="191" placeholder="adresse@client.ci" class="filter-input" style="height:36px;flex:1;">
                <button class="btn btn-ghost btn-sm" style="height:36px;">Exclure</button>
            </form>

            {{-- Recherche (2026-10-28) : seules les 50 plus récentes sont affichées. --}}
            @php $xi = $exclusionsInfo; @endphp
            @if($xi['total'] > 0)
                <form method="GET" action="{{ url()->current() }}" style="display:flex;gap:8px;margin-bottom:8px;">
                    <input type="search" name="exclu" value="{{ $xi['recherche'] }}" maxlength="191" placeholder="Chercher une adresse exclue…" class="filter-input" style="height:34px;flex:1;">
                    @if($xi['recherche'] !== '')<a href="{{ url()->current() }}" class="btn btn-ghost btn-sm" style="height:34px;">Effacer</a>@endif
                </form>
                <div style="{{ $muted }}margin-bottom:6px;">
                    @if($xi['recherche'] !== '')
                        {{ $xi['trouvees'] }} résultat{{ $xi['trouvees'] > 1 ? 's' : '' }} pour « {{ $xi['recherche'] }} »{{ $xi['trouvees'] > 50 ? ' · les 50 plus récents' : '' }}
                    @else
                        {{ number_format($xi['total'], 0, ',', ' ') }} adresse{{ $xi['total'] > 1 ? 's' : '' }} exclue{{ $xi['total'] > 1 ? 's' : '' }}{{ $xi['total'] > 50 ? ' · les 50 plus récentes (cherchez une adresse pour les autres)' : '' }}
                    @endif
                </div>
            @endif

            <div style="max-height:360px;overflow-y:auto;">
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    @forelse($exclusions as $x)
                        <tr style="border-top:1px solid var(--border);">
                            <td style="padding:7px 4px;">
                                {{ $x->email }}
                                <div style="{{ $muted }}">{{ $x->libelleMotif() }} · {{ $x->survenu_at?->format('d/m/Y') }}</div>
                            </td>
                            <td style="padding:7px 4px;text-align:right;">
                                <form method="POST" action="{{ route('admin.diffusion-dispos.exclusions.destroy', $x) }}"
                                      onsubmit="return confirm('Réintégrer {{ $x->email }} dans les prochains envois ?{{ $x->motif === 'desinscription' && $x->source === 'brevo' ? ' Ce client s\'est désinscrit lui-même : il faudra aussi le réautoriser dans Brevo.' : '' }}')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-sm">Réintégrer</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td style="{{ $muted }}padding:10px 0;">{{ $xi['recherche'] !== '' ? 'Aucune adresse ne correspond.' : 'Aucune adresse exclue.' }}</td></tr>
                    @endforelse
                </table>
            </div>
        </div>
    </div>

    {{-- ═══ MODAL DE CONFIRMATION ═══ --}}
    <div id="modal-diffusion" onclick="DIFFUSION.fermer()"
         style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.55);align-items:center;justify-content:center;padding:16px;">
        <form method="POST" action="{{ route('admin.diffusion-dispos.envoyer') }}" onclick="event.stopPropagation()"
              onsubmit="return DIFFUSION.soumettre(this)"
              style="background:var(--surface);border:1px solid var(--border);border-radius:14px;width:100%;max-width:500px;overflow:hidden;box-shadow:0 20px 50px rgba(0,0,0,.35);">
            @csrf
            <input type="hidden" name="test" id="champ-test" value="0">

            <div style="padding:16px 20px;border-bottom:1px solid var(--border);background:var(--surface2);font-weight:800;" id="modal-titre">
                Confirmer l'envoi
            </div>

            <div style="padding:20px;font-size:14px;line-height:1.6;color:var(--text2);" id="modal-corps">
                Chargement…
            </div>

            <div style="padding:0 20px 16px;">
                <label style="display:flex;gap:8px;align-items:flex-start;font-size:13px;cursor:pointer;">
                    <input type="checkbox" name="confirmation" value="1" required style="margin-top:3px;">
                    <span id="libelle-confirmation">Je confirme l'envoi.</span>
                </label>
                <div id="bloc-texte" style="display:none;margin-top:12px;">
                    <label style="font-size:12px;font-weight:700;color:#991b1b;">Un envoi a eu lieu il y a moins de <span id="delai"></span> h. Tapez ENVOYER pour confirmer :</label>
                    <input type="text" name="confirmation_texte" autocomplete="off" class="filter-input" style="width:100%;margin-top:6px;height:36px;">
                </div>
            </div>

            <div style="padding:12px 20px;border-top:1px solid var(--border);background:var(--surface2);display:flex;justify-content:flex-end;gap:8px;">
                <button type="button" class="btn btn-ghost" onclick="DIFFUSION.fermer()">Annuler</button>
                <button type="submit" class="btn btn-primary" id="btn-envoyer">Envoyer</button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
    window.DIFFUSION = (function () {
        const modal = () => document.getElementById('modal-diffusion');
        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

        return {
            async ouvrir(test) {
                document.getElementById('champ-test').value = test ? '1' : '0';
                document.getElementById('modal-titre').textContent = test ? '🧪 Envoyer un test' : '🚀 Envoyer les disponibilités maintenant';
                document.getElementById('modal-corps').textContent = 'Chargement…';
                document.getElementById('bloc-texte').style.display = 'none';
                modal().style.display = 'flex';

                try {
                    const r = await fetch(@js(route('admin.diffusion-dispos.apercu')), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    const a = await r.json();

                    let html = '';
                    if (a.dernier_envoi && !test) {
                        html += `<div style="padding:10px 12px;border-radius:10px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;margin-bottom:14px;">
                            ⚠ Un envoi a déjà été fait le <strong>${esc(a.dernier_envoi.date)}</strong>
                            (${esc(a.dernier_envoi.mode)}) à <strong>${esc(a.dernier_envoi.nb)}</strong> destinataire(s),
                            pour la période ${esc(a.dernier_envoi.periode)}.</div>`;
                    }
                    const qui = (test || a.mode_test)
                        ? 'la liste Brevo <strong>« Tests internes »</strong> (aucun client)'
                        : `<strong>${esc(a.nb_destinataires)} client(s)</strong>`;
                    html += `Vous allez envoyer les disponibilités <strong>${esc(a.periode)}</strong> à ${qui}.`;
                    document.getElementById('modal-corps').innerHTML = html;

                    document.getElementById('libelle-confirmation').textContent = test
                        ? 'Je confirme l\'envoi de test.'
                        : 'Je confirme l\'envoi. Il n\'annule pas l\'envoi automatique suivant.';

                    if (a.envoi_recent && !test) {
                        document.getElementById('delai').textContent = a.delai_heures;
                        document.getElementById('bloc-texte').style.display = 'block';
                    }
                } catch (e) {
                    document.getElementById('modal-corps').textContent = 'Impossible de charger le récapitulatif : ' + e.message;
                }
            },
            fermer() { modal().style.display = 'none'; },
            soumettre(form) {
                // Verrou anti double clic : la génération du PDF prend 1 à 2 min.
                const b = document.getElementById('btn-envoyer');
                b.disabled = true;
                b.textContent = 'Enregistrement…';
                return true;
            },
            async testerConnexion() {
                const out = document.getElementById('resultat-connexion');
                out.textContent = 'Vérification…';
                out.style.color = 'var(--text3)';
                try {
                    const r = await fetch(@js(route('admin.diffusion-dispos.tester-connexion')), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                        },
                        credentials: 'same-origin',
                    });
                    const d = await r.json();
                    if (d.ok) {
                        out.style.color = '#166534';
                        out.textContent = '✅ Connecté — compte ' + (d.email || '') + (d.plan ? ' · offre ' + d.plan : '');
                    } else {
                        out.style.color = '#991b1b';
                        out.textContent = '❌ ' + (d.erreur || 'Connexion refusée.');
                    }
                } catch (e) {
                    out.style.color = '#991b1b';
                    out.textContent = '❌ ' + e.message;
                }
            },
        };
    })();

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && document.getElementById('modal-diffusion').style.display === 'flex') DIFFUSION.fermer();
    });
    </script>
    @endpush
</x-admin-layout>
