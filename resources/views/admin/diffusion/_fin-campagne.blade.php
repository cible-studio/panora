{{--
  Mail au client « Votre campagne se termine le … » (J-7) — 2026-10-21.
  Inclus dans admin/diffusion/index. Données : $finCampagne
  (DiffusionDisponibilitesController::index, RelanceFinCampagneService).
--}}
@php
    $fc = $finCampagne;
    $fmt = fn($d) => \App\Mail\CampaignEndingSoonMail::dateEnLettres($d);
    $pastille = 'padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700;white-space:nowrap;';
@endphp

<div style="{{ $card }}margin-top:18px;">
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:10px;">
        <h2 style="{{ $h2 }}margin:0;margin-right:auto;">⏳ Fin de campagne — mail au client (J-{{ $fc['jours'] }})</h2>
        @if($fc['modeTest'])
            <span style="{{ $pastille }}background:#fef3c7;color:#92400e;" title="RELANCE_FIN_MODE=test">
                🧪 Mode test — aucun client ne reçoit
            </span>
        @else
            <span style="{{ $pastille }}background:#dcfce7;color:#166534;">🟢 Production — les clients reçoivent</span>
        @endif
    </div>

    <p style="{{ $muted }}margin:0 0 14px;line-height:1.6;">
        Chaque jour à 10 h, Panora écrit au client dont la campagne se termine dans {{ $fc['jours'] }} jours,
        pour lui proposer une reconduction. Un seul mail par campagne.
        Destinataire : l'interlocuteur principal du client. Réponse : au commercial de la campagne.
        Copie : {{ implode(', ', $fc['copie']) ?: '—' }}.
        Pas de mail si le client a une autre campagne planifiée ou qui continue après, ni si son adresse est exclue.
        <br>Envoyé par Brevo uniquement. Suivi de chaque mail (délivré, ouvert, cliqué) :
        <strong>Brevo → Transactionnel → Journal</strong>, filtre sur le tag <code>fin-campagne</code>.
        @if($fc['modeTest'])
            <br><strong style="color:#92400e;">En mode test, ces mails partent vers la liste Brevo « Tests internes ».</strong>
            Pour écrire aux clients : <code>RELANCE_FIN_MODE=production</code> dans le <code>.env</code>.
        @endif
    </p>

    {{-- ── Campagnes qui se terminent dans les 14 jours ── --}}
    <div style="font-size:12px;font-weight:800;color:var(--text2);text-transform:uppercase;letter-spacing:.5px;margin:4px 0 8px;">
        Campagnes qui se terminent dans les 14 jours
    </div>
    @if($fc['prochaines']->isEmpty())
        <div style="{{ $muted }}padding:12px 0;">Aucune campagne active ne se termine dans les 14 jours.</div>
    @else
        <div style="overflow-x:auto;">
            <table style="width:100%;font-size:13px;border-collapse:collapse;min-width:760px;">
                <thead>
                    <tr style="text-align:left;color:var(--text3);font-size:11px;text-transform:uppercase;letter-spacing:.5px;">
                        <th style="padding:8px 6px;">Campagne</th>
                        <th style="padding:8px 6px;">Fin</th>
                        <th style="padding:8px 6px;">Mail</th>
                        <th style="padding:8px 6px;">Destinataire</th>
                        <th style="padding:8px 6px;text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($fc['prochaines'] as $p)
                        @php
                            $c = $p['campagne']; $a = $p['analyse']; $j = $p['journal'];
                            $datePassee = $p['date_mail']->lt(today());
                        @endphp
                        <tr style="border-top:1px solid var(--border);vertical-align:top;">
                            <td style="padding:9px 6px;">
                                <strong>{{ $c->name }}</strong>
                                <div style="{{ $muted }}">{{ $c->client?->name ?? '—' }}</div>
                            </td>
                            <td style="padding:9px 6px;white-space:nowrap;">{{ $c->end_date->format('d/m/Y') }}</td>
                            <td style="padding:9px 6px;">
                                @if($j)
                                    @php [$bg, $fg, $lib] = $j->badge(); @endphp
                                    <span style="{{ $pastille }}background:{{ $bg }};color:{{ $fg }};">{{ $lib }}</span>
                                    <div style="{{ $muted }}margin-top:3px;">{{ ($j->envoye_at ?? $j->updated_at)->format('d/m H\hi') }}</div>
                                @elseif($datePassee)
                                    <span style="{{ $pastille }}background:var(--surface2);color:var(--text2);"
                                          title="La campagne a été créée ou prolongée après la date du mail.">Date passée</span>
                                @else
                                    <span style="{{ $pastille }}background:#e0e7ff;color:#3730a3;">Prévu le {{ $p['date_mail']->format('d/m') }}</span>
                                @endif
                            </td>
                            <td style="padding:9px 6px;max-width:320px;">
                                @if($j && $j->statut !== 'envoye')
                                    <div style="font-size:12px;color:{{ $j->statut === 'echec' ? '#991b1b' : 'var(--text2)' }};">{{ $j->motif }}</div>
                                @elseif(!$j && $a['statut'])
                                    <div style="font-size:12px;color:{{ $a['statut'] === 'echec' ? '#991b1b' : 'var(--text2)' }};">{{ $a['motif'] }}</div>
                                @else
                                    {{ $a['destinataire'] }}
                                    <div style="{{ $muted }}">réponse à {{ $a['repondre_a'] ?? '—' }}</div>
                                @endif
                            </td>
                            <td style="padding:9px 6px;text-align:right;white-space:nowrap;">
                                <a href="{{ route('admin.diffusion-dispos.fin-campagne.apercu', $c) }}" target="_blank" rel="noopener"
                                   class="btn btn-ghost btn-sm">Aperçu</a>
                                <form method="POST" action="{{ route('admin.diffusion-dispos.fin-campagne.tester', $c) }}" style="display:inline;"
                                      onsubmit="return confirm('Envoyer ce mail aux adresses de test (pas au client) ?')">
                                    @csrf
                                    <button class="btn btn-ghost btn-sm">Envoyer un test</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ── Derniers mails ── --}}
    <div style="font-size:12px;font-weight:800;color:var(--text2);text-transform:uppercase;letter-spacing:.5px;margin:20px 0 8px;">
        Derniers mails
    </div>
    @if($fc['journal']->isEmpty())
        <div style="{{ $muted }}padding:12px 0;">Aucun mail pour l'instant.</div>
    @else
        <div style="overflow-x:auto;">
            <table style="width:100%;font-size:13px;border-collapse:collapse;min-width:760px;">
                <thead>
                    <tr style="text-align:left;color:var(--text3);font-size:11px;text-transform:uppercase;letter-spacing:.5px;">
                        <th style="padding:8px 6px;">Date</th>
                        <th style="padding:8px 6px;">Campagne</th>
                        <th style="padding:8px 6px;">Mode</th>
                        <th style="padding:8px 6px;">Statut</th>
                        <th style="padding:8px 6px;">Destinataire</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($fc['journal'] as $l)
                        @php [$bg, $fg, $lib] = $l->badge(); @endphp
                        <tr style="border-top:1px solid var(--border);vertical-align:top;">
                            <td style="padding:9px 6px;white-space:nowrap;">{{ ($l->envoye_at ?? $l->updated_at)->format('d/m/Y H\hi') }}</td>
                            <td style="padding:9px 6px;">
                                {{ $l->campaign?->name ?? "Campagne #{$l->campaign_id}" }}
                                <div style="{{ $muted }}">{{ $l->client?->name ?? '—' }} · fin le {{ $fmt($l->fin_campagne) }}</div>
                            </td>
                            <td style="padding:9px 6px;">{{ $l->mode === 'production' ? 'Production' : 'Test' }}</td>
                            <td style="padding:9px 6px;">
                                <span style="{{ $pastille }}background:{{ $bg }};color:{{ $fg }};">{{ $lib }}</span>
                                @if($l->canal)<div style="{{ $muted }}margin-top:3px;">via {{ $l->canal === 'smtp' ? 'mail de secours' : 'Brevo' }}</div>@endif
                            </td>
                            <td style="padding:9px 6px;max-width:360px;">
                                {{ $l->destinataire ?? '—' }}
                                @if($l->motif)<div style="font-size:12px;color:{{ $l->statut === 'echec' ? '#991b1b' : 'var(--text3)' }};margin-top:3px;">{{ $l->motif }}</div>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
