@php
    $paid     = $invoice->paidAmount();
    $total    = (float) ($invoice->total_a_payer ?: $invoice->amount_ttc);
    $remaining= $invoice->remainingAmount();
    $payStatus= $invoice->paymentStatus();
    $nextDue  = $invoice->nextDueSchedule();
    $payCfg = match($payStatus) {
        'soldee'    => ['bg' => 'rgba(34,197,94,.12)',  'color' => '#15803d', 'label' => 'Soldée',      'icon' => '✅'],
        'partielle' => ['bg' => 'rgba(245,158,11,.12)', 'color' => '#b45309', 'label' => 'Partielle',   'icon' => '⏳'],
        'annulee'   => ['bg' => 'rgba(107,114,128,.12)','color' => '#4b5563', 'label' => 'Annulée',     'icon' => '🚫'],
        default     => ['bg' => 'rgba(239,68,68,.08)',  'color' => '#b91c1c', 'label' => 'Non payée',   'icon' => '❌'],
    };
@endphp
<tr data-invoice-row="{{ $invoice->id }}" data-status="{{ $invoice->status }}">
    <td>
        <span style="font-family:monospace; color:var(--accent); font-weight:700;">{{ $invoice->reference }}</span>
        <div style="font-size:10px;color:var(--text3);margin-top:2px">{{ $invoice->issued_at->format('d/m/Y') }}</div>
    </td>
    <td>
        @if($invoice->client)
            <a href="{{ route('admin.clients.show', $invoice->client) }}"
               style="color:var(--text);font-weight:700;text-decoration:none;border-bottom:1px dashed var(--border2)"
               title="Voir la fiche client">
                {{ $invoice->client->name }}
            </a>
        @else
            <span style="color:var(--text3)">—</span>
        @endif
        @if($invoice->campaign)
            <div style="font-size:10.5px;color:var(--text3);margin-top:1px">
                📢 <a href="{{ route('admin.campaigns.show', $invoice->campaign) }}" style="color:var(--text3);text-decoration:none">{{ \Illuminate\Support\Str::limit($invoice->campaign->name, 26) }}</a>
            </div>
        @endif
    </td>
    <td style="text-align:right;font-family:ui-monospace,monospace;font-weight:700;color:var(--accent)">{{ number_format($total, 0, ',', ' ') }}</td>
    <td style="text-align:right;font-family:ui-monospace,monospace;color:#16a34a">{{ number_format($paid, 0, ',', ' ') }}</td>
    <td style="text-align:right;font-family:ui-monospace,monospace;color:{{ $remaining > 0 ? '#b91c1c' : 'var(--text3)' }};font-weight:700">{{ number_format($remaining, 0, ',', ' ') }}</td>
    <td>
        <span style="display:inline-flex;align-items:center;gap:4px;background:{{ $payCfg['bg'] }};color:{{ $payCfg['color'] }};padding:3px 8px;border-radius:999px;font-size:10.5px;font-weight:800;white-space:nowrap">
            {{ $payCfg['icon'] }} {{ $payCfg['label'] }}
        </span>
        <div style="margin-top:3px">
            @switch($invoice->status)
                @case('brouillon') <span style="font-size:9.5px;color:var(--text3);text-transform:uppercase;letter-spacing:.3px">📝 Brouillon</span> @break
                @case('envoyee')   <span style="font-size:9.5px;color:#1d4ed8;text-transform:uppercase;letter-spacing:.3px">📤 Envoyée</span> @break
                @case('payee')     <span style="font-size:9.5px;color:#15803d;text-transform:uppercase;letter-spacing:.3px">✅ Marquée payée</span> @break
                @case('annulee')   <span style="font-size:9.5px;color:#b91c1c;text-transform:uppercase;letter-spacing:.3px">🚫 Annulée</span> @break
            @endswitch
        </div>
    </td>
    <td>
        @if($nextDue)
            @php $st = $nextDue->state(); @endphp
            <div style="font-size:11.5px;font-weight:700;color:{{ $st === 'overdue' ? '#b91c1c' : ($st === 'soon' ? '#b45309' : 'var(--text2)') }}">
                {{ $nextDue->due_date->format('d/m/Y') }}
            </div>
            <div style="font-size:10px;color:var(--text3);margin-top:1px">
                {{ $nextDue->label ?? 'Échéance' }} · {{ number_format((float) $nextDue->amount, 0, ',', ' ') }} F
            </div>
            @if($st === 'overdue')
                <span style="display:inline-block;margin-top:2px;background:rgba(239,68,68,.15);color:#b91c1c;padding:1px 6px;border-radius:6px;font-size:9px;font-weight:800">🔴 RELANCER ({{ abs($nextDue->daysUntilDue()) }}j)</span>
            @elseif($st === 'soon')
                <span style="display:inline-block;margin-top:2px;background:rgba(245,158,11,.12);color:#b45309;padding:1px 6px;border-radius:6px;font-size:9px;font-weight:800">⏰ DANS {{ $nextDue->daysUntilDue() }}j</span>
            @endif
        @else
            <span style="color:var(--text3);font-size:11px">—</span>
        @endif
    </td>
    <td>
        <div style="display:flex; gap:6px; flex-wrap:wrap;">
            <a href="{{ route('admin.invoices.show', $invoice) }}" class="btn btn-ghost btn-sm" title="Voir">👁️</a>
            <a href="{{ route('admin.invoices.pdf', $invoice) }}" class="btn btn-ghost btn-sm" title="PDF">📄</a>

            @if($invoice->status === 'brouillon')
                <form method="POST" action="{{ route('admin.invoices.send', $invoice) }}" class="inline-form invoice-action" data-action="send">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-blue btn-sm" title="Marquer envoyée">📤</button>
                </form>
            @endif

            @if(in_array($invoice->status, ['envoyee', 'payee']))
                <form method="POST" action="{{ route('admin.invoices.revert-draft', $invoice) }}" class="inline-form invoice-action" data-action="revert" data-confirm="Rebasculer cette facture en brouillon ? La date de paiement sera effacée.">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-ghost btn-sm" title="Rebasculer en brouillon" style="color:var(--text3);">↩</button>
                </form>
            @endif

            @if(!in_array($invoice->status, ['annulee', 'payee']))
                <form method="POST" action="{{ route('admin.invoices.cancel', $invoice) }}" class="inline-form invoice-action" data-action="cancel" data-confirm="Annuler cette facture ?">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-ghost btn-sm" title="Annuler" style="color:#ef4444;">🚫</button>
                </form>
            @endif

            @if(!$invoice->isLocked())
                <a href="{{ route('admin.invoices.edit', $invoice) }}" class="btn btn-ghost btn-sm" title="Modifier">✏️</a>
            @endif
        </div>
    </td>
</tr>
