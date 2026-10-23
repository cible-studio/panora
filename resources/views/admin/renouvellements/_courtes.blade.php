{{-- Onglet « Autres campagnes · mail J-7 » : campagnes de moins de 12 mois (2026-10-23). Données : RelanceFinCampagneService::prochaines. --}}
<div class="rn-bar">
    <form method="GET" action="{{ route('admin.renouvellements.index') }}" class="rn-search">
        <input type="hidden" name="onglet" value="courtes">
        @if($horizon)<input type="hidden" name="horizon" value="{{ $horizon }}">@endif
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="search" name="q" value="{{ $q }}" placeholder="Client, campagne…">
    </form>
    <div class="rn-seg" data-tip="Échéance de la campagne">
        @foreach([0 => 'Toutes', 30 => '≤ 30 j', 60 => '≤ 60 j', 90 => '≤ 90 j'] as $h => $libH)
            <a href="{{ $lien(['onglet' => 'courtes', 'horizon' => $h]) }}" class="{{ $horizon === $h ? 'on' : '' }}">{{ $libH }}</a>
        @endforeach
    </div>
    <span class="rn-muted" style="margin-left:auto;" data-tip="Un seul mail, 7 jours avant la fin, sans boutons : le client répond directement à son commercial. Pas de mail si le client a une autre campagne qui continue après, ou planifiée.">Un mail 7 jours avant la fin <span class="rn-info">?</span></span>
</div>

<div class="rn-card" style="overflow:hidden;">
    @if($courtes->isEmpty())
        <div class="rn-vide"><div class="ic">📭</div>Aucune campagne de moins de 12 mois en cours dans cette sélection.</div>
    @else
        <div style="overflow-x:auto;">
        <table class="rn-table">
            <thead>
                <tr>
                    <th>Client · campagne</th>
                    <th>Échéance</th>
                    <th>Mail J-7</th>
                    <th class="rn-hide-sm">Destinataire</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
            @foreach($courtes as $p)
                @php
                    $c = $p['campagne']; $a = $p['analyse']; $j = $p['journal'];
                    $jours = (int) today()->diffInDays($c->end_date->copy()->startOfDay(), false);
                    [$bg, $fg, $lib, $tip] = match (true) {
                        $j?->statut === 'envoye' => ['#dcfce7', '#166534', 'Envoyé le ' . $j->envoye_at?->format('d/m'), $j->motif ?: 'Envoyé au client.'],
                        $j?->statut === 'echec'  => ['#fee2e2', '#991b1b', 'À retenter', $j->motif],
                        $j?->statut === 'exclu'  => ['var(--surface2)', 'var(--text2)', 'Non envoyé', $j->motif],
                        $a['statut'] === 'exclu' => ['var(--surface2)', 'var(--text2)', 'Ne partira pas', $a['motif']],
                        $p['date_mail']->gte(today()) => ['#e0f2fe', '#075985', 'Prévu le ' . $p['date_mail']->format('d/m'), 'Part à 10 h ce jour-là.' . ($a['statut'] === 'echec' ? ' Attention : ' . $a['motif'] : '')],
                        default => ['var(--surface2)', 'var(--text3)', 'Date passée', 'La campagne a été créée ou prolongée après la date du mail.'],
                    };
                @endphp
                <tr>
                    <td>
                        <div class="rn-client">{{ $c->client?->name ?? '—' }}</div>
                        <div class="rn-camp"><a href="{{ route('admin.campaigns.show', $c) }}" style="color:inherit;">{{ $c->name }}</a></div>
                    </td>
                    <td style="white-space:nowrap;">{{ $c->end_date->format('d/m/Y') }}<br><span class="rn-j {{ $jours <= 7 ? 'chaud' : '' }}">J-{{ $jours }}</span></td>
                    <td><span class="rn-chip" style="background:{{ $bg }};color:{{ $fg }};" data-tip="{{ $tip }}">{{ $lib }}</span></td>
                    <td class="rn-hide-sm">
                        {{ $a['destinataire'] ?: '—' }}
                        @if($a['repondre_a'])<div class="rn-muted">réponse à {{ $a['repondre_a'] }}</div>@endif
                    </td>
                    <td style="text-align:right;white-space:nowrap;">
                        <a href="{{ route('admin.renouvellements.apercu-mail', [$c, 'fin']) }}" target="_blank" rel="noopener" class="rn-btn petit">👁 Aperçu</a>
                        @if($estAdmin)
                            <form method="POST" action="{{ route('admin.diffusion-dispos.fin-campagne.tester', $c) }}" style="display:inline;"
                                  onsubmit="return confirm('Envoyer ce mail aux adresses de test (pas au client) ?')">
                                @csrf
                                <button class="rn-btn petit">✉️ Test</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>
