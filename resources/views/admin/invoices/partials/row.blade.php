<tr data-invoice-row="{{ $invoice->id }}" data-status="{{ $invoice->status }}">
    <td>
        <span style="font-family:monospace; color:var(--accent); font-weight:700;">{{ $invoice->reference }}</span>
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
    </td>
    <td style="font-size:12px;">
        @if($invoice->campaign)
            <a href="{{ route('admin.campaigns.show', $invoice->campaign) }}"
               style="color:var(--text2);text-decoration:none;border-bottom:1px dashed var(--border2)"
               title="Voir la campagne">
                {{ $invoice->campaign->name }}
            </a>
        @else
            <span style="color:var(--text3)">—</span>
        @endif
    </td>
    <td>{{ number_format($invoice->amount, 0, ',', ' ') }} FCFA</td>
    <td>{{ rtrim(rtrim(number_format($invoice->tva, 2, ',', ''), '0'), ',') }}%</td>
    <td style="color:var(--accent); font-weight:700;">{{ number_format($invoice->amount_ttc, 0, ',', ' ') }} FCFA</td>
    <td style="font-size:12px;">
        {{ $invoice->issued_at->format('d/m/Y') }}
        @if($invoice->paid_at)
            <div style="font-size:10px;color:var(--green);margin-top:2px;">Payée le {{ $invoice->paid_at->format('d/m/Y') }}</div>
        @endif
    </td>
    <td>
        @switch($invoice->status)
            @case('brouillon') <span class="badge badge-gray">📝 Brouillon</span> @break
            @case('envoyee')   <span class="badge badge-blue">📤 Envoyée</span> @break
            @case('payee')     <span class="badge badge-green">✅ Payée</span> @break
            @case('annulee')   <span class="badge badge-red">🚫 Annulée</span> @break
        @endswitch
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
                <form method="POST" action="{{ route('admin.invoices.pay', $invoice) }}" class="inline-form invoice-action" data-action="pay" data-confirm="Marquer cette facture comme payée maintenant ?">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-success btn-sm" title="Marquer payée directement">✓</button>
                </form>
            @endif

            @if($invoice->status === 'envoyee')
                <form method="POST" action="{{ route('admin.invoices.pay', $invoice) }}" class="inline-form invoice-action" data-action="pay" data-confirm="Marquer cette facture comme payée ?">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-success btn-sm" title="Marquer payée">✓ Payée</button>
                </form>
            @endif

            @if(in_array($invoice->status, ['envoyee', 'payee']))
                <form method="POST" action="{{ route('admin.invoices.revert-draft', $invoice) }}" class="inline-form invoice-action" data-action="revert" data-confirm="Rebasculer cette facture en brouillon ? La date de paiement sera effacée.">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-ghost btn-sm" title="Rebasculer en brouillon" style="color:var(--text3);">↩</button>
                </form>
            @endif

            @if(!in_array($invoice->status, ['annulee', 'payee']))
                <form method="POST" action="{{ route('admin.invoices.cancel', $invoice) }}" class="inline-form invoice-action" data-action="cancel" data-confirm="Annuler cette facture ? Cette action est traçable mais ne supprime pas la facture.">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-ghost btn-sm" title="Annuler" style="color:#ef4444;">🚫</button>
                </form>
            @endif

            <a href="{{ route('admin.invoices.edit', $invoice) }}" class="btn btn-ghost btn-sm" title="Modifier">✏️</a>
        </div>
    </td>
</tr>
