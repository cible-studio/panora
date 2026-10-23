{{-- Onglet « Journal des envois » : tous les mails clients avant échéance (2026-10-23). --}}
<div class="rn-bar">
    <span class="rn-muted">Tous les mails partis (ou non) vers les clients. Le détail (ouvert, lien cliqué) est dans <strong style="color:var(--text);">Brevo → Transactionnel → Journal</strong>, filtré par tag.</span>
</div>

<div class="rn-card" style="overflow:hidden;">
    @if(!$journal || $journal->isEmpty())
        <div class="rn-vide"><div class="ic">📭</div>Aucun mail pour l'instant.</div>
    @else
        <div style="overflow-x:auto;">
        <table class="rn-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Client · campagne</th>
                    <th>Mail</th>
                    <th>Statut</th>
                    <th class="rn-hide-sm">Destinataire</th>
                </tr>
            </thead>
            <tbody>
            @foreach($journal as $m)
                @php [$bg, $fg, $lib] = $m->badge(); @endphp
                <tr>
                    <td style="white-space:nowrap;">{{ ($m->envoye_at ?? $m->updated_at)->format('d/m/Y H\hi') }}</td>
                    <td>
                        <div class="rn-client">{{ $m->client?->name ?? '—' }}</div>
                        <div class="rn-camp">{{ $m->campaign?->name ?? "Campagne #{$m->campaign_id}" }} · fin {{ $m->fin_campagne->format('d/m/Y') }}</div>
                    </td>
                    <td>
                        {{ $m->libelleEtape() }}
                        @if($m->mode === 'test')<span class="rn-chip test" style="margin-left:4px;padding:1px 7px;font-size:11px;" data-tip="Envoyé en test (adresses de test), pas au client.">test</span>@endif
                    </td>
                    <td><span class="rn-chip" style="background:{{ $bg }};color:{{ $fg }};" @if($m->motif) data-tip="{{ $m->motif }}" @endif>{{ $lib }}</span></td>
                    <td class="rn-hide-sm">{{ $m->destinataire ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        <div style="padding:12px 14px;">{{ $journal->links() }}</div>
    @endif
</div>
