<x-admin-layout>
    <x-slot name="title">Renouvellements</x-slot>

    {{-- Renouvellement des campagnes annuelles (2026-10-22). Cf. RenouvellementService. --}}
    @php
        $card  = 'background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 20px;margin-bottom:16px;';
        $muted = 'font-size:12px;color:var(--text3);';
        $pastille = 'display:inline-block;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700;white-space:nowrap;';
        $fcfa  = fn($n) => number_format((int) $n, 0, ',', ' ') . ' FCFA';
        $etapes = \App\Models\RelanceFinCampagne::ETAPES;
        $statuts = \App\Models\Renouvellement::STATUTS;
        $choisis = $peutConfirmer ? $statuts : array_diff_key($statuts, ['confirme' => 1]);
        $lien = fn(array $p) => route('admin.renouvellements.index', array_filter(array_merge(['horizon' => $horizon, 'statut' => $statut], $p), fn($v) => $v !== null && $v !== ''));
    @endphp

    {{-- ═══ EN-TÊTE ═══ --}}
    <div style="{{ $card }}display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
        <div style="margin-right:auto;">
            <div style="font-size:18px;font-weight:800;color:var(--text);">🔁 Renouvellements des campagnes annuelles</div>
            <div style="{{ $muted }}margin-top:4px;line-height:1.5;">
                Campagnes de 12 mois ou plus. Mails automatiques à J-60, J-45, J-30 (date limite), J-15 et J-7, tant que le client n'a pas répondu.
                Suivi des mails : <strong>Brevo → Transactionnel → Journal</strong>, tags <code>renouvellement-j60</code>… <code>renouvellement-j7</code>.
            </div>
        </div>
        @if($modeTest)
            <span style="{{ $pastille }}background:#fef3c7;color:#92400e;" title="RELANCE_FIN_MODE=test">🧪 Mode test — aucun client ne reçoit</span>
        @else
            <span style="{{ $pastille }}background:#dcfce7;color:#166534;">🟢 Production — les clients reçoivent</span>
        @endif
        @if($estAdmin)
            <a href="{{ route('admin.renouvellements.modeles') }}" class="btn btn-ghost btn-sm">✏️ Textes des mails</a>
        @endif
    </div>

    {{-- ═══ COMPTEURS ═══ --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:16px;">
        @foreach([
            ['Échéance ≤ 30 j', $compteurs['30']['nb'], $fcfa($compteurs['30']['montant']), '#e20613'],
            ['Échéance ≤ 60 j', $compteurs['60']['nb'], $fcfa($compteurs['60']['montant']), '#fab80b'],
            ['Échéance ≤ 90 j', $compteurs['90']['nb'], $fcfa($compteurs['90']['montant']), '#3f7fc0'],
            ['Sans réponse (≤ J-25)', $compteurs['sans_reponse'], 'à appeler', '#81358a'],
            ['Veulent renouveler', $compteurs['renouvellent'], 'dont confirmés par le MP', '#3aa835'],
            ['À confirmer (MP)', $compteurs['a_confirmer'], 'renouveler ou ajuster', '#3aa835'],
            ['Ne renouvellent pas', $compteurs['refus'], 'emplacements libérés', '#e20613'],
        ] as [$lib, $nb, $sous, $coul])
            <div style="{{ $card }}margin:0;padding:14px 16px;border-top:3px solid {{ $coul }};">
                <div style="{{ $muted }}text-transform:uppercase;letter-spacing:.4px;font-weight:700;">{{ $lib }}</div>
                <div style="font-size:24px;font-weight:800;color:var(--text);margin-top:4px;">{{ $nb }}</div>
                <div style="{{ $muted }}">{{ $sous }}</div>
            </div>
        @endforeach
    </div>

    {{-- ═══ FILTRES ═══ --}}
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:12px;">
        <span style="{{ $muted }}font-weight:700;">Échéance :</span>
        @foreach([30 => '30 jours', 60 => '60 jours', 90 => '90 jours', 0 => 'Toutes'] as $h => $libH)
            <a href="{{ $lien(['horizon' => $h]) }}" title="{{ $h ? "Campagnes annuelles qui se terminent dans les {$h} jours" : 'Toutes les campagnes annuelles en cours' }}" style="{{ $pastille }}text-decoration:none;{{ $horizon === $h ? 'background:var(--accent);color:#fff;' : 'background:var(--surface2);color:var(--text2);' }}">{{ $libH }}</a>
        @endforeach
        <span style="{{ $muted }}font-weight:700;margin-left:12px;">Statut :</span>
        <a href="{{ route('admin.renouvellements.index', ['horizon' => $horizon]) }}" style="{{ $pastille }}text-decoration:none;{{ !$statut ? 'background:var(--accent);color:#fff;' : 'background:var(--surface2);color:var(--text2);' }}">Tous</a>
        @foreach($statuts as $cle => [$lib, $bg, $fg])
            <a href="{{ $lien(['statut' => $cle]) }}" style="{{ $pastille }}text-decoration:none;{{ $statut === $cle ? "background:{$bg};color:{$fg};outline:2px solid {$fg};" : "background:{$bg};color:{$fg};opacity:.75;" }}">{{ $lib }}</a>
        @endforeach
    </div>

    {{-- ═══ LISTE ═══ --}}
    <div style="{{ $card }}padding:0;overflow:hidden;">
        @if($lignes->isEmpty())
            <div style="{{ $muted }}padding:30px;text-align:center;line-height:1.7;">
                @if($statut)
                    Aucune campagne annuelle avec ce statut dans cette sélection.
                @elseif($prochaine)
                    Aucune campagne annuelle ne se termine dans les {{ $horizon }} prochains jours.<br>
                    Prochaine échéance : <strong style="color:var(--text);">{{ $prochaine->client?->name }} — {{ $prochaine->name }}</strong>,
                    le {{ $prochaine->end_date->format('d/m/Y') }}.
                    Son premier mail (J-60) partira le {{ $prochaine->end_date->copy()->subDays(60)->format('d/m/Y') }}.<br>
                    <a href="{{ $lien(['horizon' => 0]) }}" style="color:var(--accent);font-weight:700;">Voir toutes les campagnes annuelles en cours</a>
                @else
                    Aucune campagne annuelle (12 mois ou plus) en cours.
                @endif
            </div>
        @else
        <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:980px;">
            <thead>
                <tr style="text-align:left;color:var(--text3);font-size:11px;text-transform:uppercase;letter-spacing:.5px;background:var(--surface2);">
                    <th style="padding:10px 12px;">Client · campagne</th>
                    <th style="padding:10px 12px;">Échéance</th>
                    <th style="padding:10px 12px;">Statut</th>
                    <th style="padding:10px 12px;">Mails</th>
                    <th style="padding:10px 12px;">Commercial · contact</th>
                    <th style="padding:10px 12px;text-align:right;">Suivi</th>
                </tr>
            </thead>
            <tbody>
            @foreach($lignes as $l)
                @php
                    $c = $l['campagne']; $d = $l['dossier'];
                    [$lib, $bg, $fg] = $statuts[$l['statut']];
                    $urgent = $l['statut'] === 'en_attente' && $l['jours'] > 0 && $l['jours'] <= 25;
                @endphp
                <tr style="border-top:1px solid var(--border);vertical-align:top;{{ $urgent ? 'background:rgba(226,6,19,.035);' : '' }}">
                    <td style="padding:12px;">
                        <strong>{{ $c->client?->name ?? '—' }}</strong>
                        <div><a href="{{ route('admin.campaigns.show', $c) }}" style="color:var(--accent);">{{ $c->name }}</a></div>
                        <div style="{{ $muted }}">{{ $l['panneaux'] }} panneau{{ $l['panneaux'] > 1 ? 'x' : '' }}@if($l['montant'] > 0) · {{ $fcfa($l['montant']) }}@endif</div>
                        @if($l['autre_planifiee'])
                            <div style="font-size:11px;color:#92400e;margin-top:4px;" title="Le renouvellement est peut-être déjà réservé : vérifiez, puis mettez le statut à jour.">⚠ Autre campagne planifiée : {{ $l['autre_planifiee'] }}</div>
                        @endif
                    </td>
                    <td style="padding:12px;white-space:nowrap;">
                        <strong>{{ $c->end_date->format('d/m/Y') }}</strong>
                        <div style="font-size:12px;font-weight:700;color:{{ $l['jours'] <= 0 ? 'var(--text3)' : ($l['jours'] <= 15 ? '#991b1b' : ($l['jours'] <= 30 ? '#92400e' : 'var(--text2)')) }};">
                            {{ $l['jours'] > 0 ? 'J-' . $l['jours'] : 'Terminée' }}
                        </div>
                    </td>
                    <td style="padding:12px;max-width:260px;">
                        <span style="{{ $pastille }}background:{{ $bg }};color:{{ $fg }};">{{ $lib }}</span>
                        @if($d?->reponse_at)
                            <div style="{{ $muted }}margin-top:4px;">Réponse du {{ $d->reponse_at->format('d/m/Y') }}</div>
                        @endif
                        @if($d?->reponse_message)
                            <div style="font-size:12px;color:var(--text2);margin-top:4px;font-style:italic;">« {{ \Illuminate\Support\Str::limit($d->reponse_message, 160) }} »</div>
                        @endif
                        @if($d?->confirme_at)
                            <div style="{{ $muted }}margin-top:4px;">Confirmé le {{ $d->confirme_at->format('d/m/Y') }}@if($d->confirmateur) par {{ $d->confirmateur->name }}@endif</div>
                        @endif
                    </td>
                    <td style="padding:12px;white-space:nowrap;">
                        @if($l['dernier_mail'])
                            <div>✉️ {{ str_replace('Renouvellement ', '', $etapes[$l['dernier_mail']->etape] ?? $l['dernier_mail']->etape) }} <span style="{{ $muted }}">le {{ $l['dernier_mail']->envoye_at?->format('d/m') }}</span></div>
                        @else
                            <div style="{{ $muted }}">Aucun mail envoyé</div>
                        @endif
                        @if($l['prochain_mail'])
                            <div style="{{ $muted }}">Prochain : {{ str_replace('Renouvellement ', '', $etapes[$l['prochain_mail'][0]]) }} le {{ $l['prochain_mail'][1]->format('d/m') }}</div>
                        @elseif($l['statut'] !== 'en_attente')
                            <div style="{{ $muted }}">Relances arrêtées</div>
                        @endif
                    </td>
                    <td style="padding:12px;">
                        {{ $l['commercial']?->name ?? '—' }}
                        @if($l['telephone'])
                            <div><a href="tel:{{ preg_replace('/[^0-9+]/', '', $l['telephone']) }}" style="color:var(--accent);font-weight:700;">📞 {{ $l['telephone'] }}</a></div>
                        @endif
                    </td>
                    <td style="padding:12px;text-align:right;min-width:230px;">
                        <details>
                            <summary class="btn btn-ghost btn-sm" style="list-style:none;cursor:pointer;display:inline-block;">Noter un appel / changer</summary>
                            <form method="POST" action="{{ route('admin.renouvellements.statut', $c) }}" style="text-align:left;margin-top:8px;display:grid;gap:6px;">
                                @csrf
                                <select name="statut" class="filter-select" style="height:34px;">
                                    @foreach($choisis as $k => [$libS])
                                        <option value="{{ $k }}" @selected($l['statut'] === $k)>{{ $libS }}</option>
                                    @endforeach
                                </select>
                                <textarea name="note" rows="2" maxlength="2000" class="filter-input" placeholder="Note d'appel (facultatif) : joint, rappeler le…, décision…" style="width:100%;"></textarea>
                                <button class="btn btn-primary btn-sm">Enregistrer</button>
                                <div style="{{ $muted }}">Si le statut n'est plus « En attente », les relances par mail s'arrêtent.</div>
                            </form>
                        </details>
                        @if($peutConfirmer && $l['statut'] !== 'confirme')
                            <form method="POST" action="{{ route('admin.renouvellements.confirmer', $c) }}" style="margin-top:6px;"
                                  onsubmit="return confirm('Confirmer le renouvellement de « {{ addslashes($c->name) }} » ? Ses panneaux ne seront plus proposés dans le catalogue des disponibilités.')">
                                @csrf
                                <button class="btn btn-sm" style="background:#166534;color:#fff;">✓ Confirmer le renouvellement</button>
                            </form>
                        @endif
                        @if($d && $d->historique)
                            <details style="margin-top:6px;text-align:left;">
                                <summary style="{{ $muted }}cursor:pointer;text-align:right;">Historique ({{ count($d->historique) }})</summary>
                                <div style="margin-top:6px;border-left:2px solid var(--border);padding-left:10px;">
                                    @foreach(array_reverse($d->historique) as $h)
                                        <div style="font-size:12px;margin-bottom:6px;">
                                            <span style="{{ $muted }}">{{ \Carbon\Carbon::parse($h['date'])->format('d/m H\hi') }}@isset($h['auteur']) · {{ $h['auteur'] }}@endisset</span><br>
                                            {{ $h['action'] }}@isset($h['texte'])<br><em style="color:var(--text2);">{{ $h['texte'] }}</em>@endisset
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>

    <p style="{{ $muted }}line-height:1.6;">
        Chaque matin (jours ouvrés), chaque commercial reçoit par mail ses clients à appeler — sans réponse dès J-25 — et les réponses reçues ;
        les MP reçoivent les renouvellements à confirmer. Un renouvellement confirmé retire ses panneaux du catalogue des disponibilités.
    </p>
</x-admin-layout>
