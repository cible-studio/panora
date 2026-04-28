{{-- resources/views/admin/invoices/partials/table-rows.blade.php --}}
@forelse($invoices as $invoice)
<tr>
    <td>
        <span style="font-family:monospace; color:var(--accent); font-weight:700;">{{ $invoice->reference }}</span>
    </td>
    <td><strong>{{ $invoice->client->name }}</strong></td>
    <td style="font-size:12px; color:var(--text3);">{{ $invoice->campaign?->name ?? '—' }}</td>
    <td>{{ number_format($invoice->amount, 0, ',', ' ') }} FCFA</td>
    <td>{{ $invoice->tva }}%</td>
    <td style="color:var(--accent); font-weight:700;">{{ number_format($invoice->amount_ttc, 0, ',', ' ') }} FCFA</td>
    <td style="font-size:12px;">{{ $invoice->issued_at->format('d/m/Y') }}</td>
    <td>
        @if($invoice->status === 'brouillon')
            <span class="badge badge-gray">Brouillon</span>
        @elseif($invoice->status === 'envoyee')
            <span class="badge badge-blue">Envoyée</span>
        @elseif($invoice->status === 'payee')
            <span class="badge badge-green">Payée ✓</span>
        @else
            <span class="badge badge-red">Annulée</span>
        @endif
    </td>
    <td>
        <div style="display:flex; gap:6px;">
            <a href="{{ route('admin.invoices.show', $invoice) }}" class="btn btn-ghost btn-sm" title="Voir">👁️</a>
            <a href="{{ route('admin.invoices.pdf', $invoice) }}" class="btn btn-ghost btn-sm" title="PDF">📄</a>
            @if($invoice->status === 'brouillon')
            <form method="POST" action="{{ route('admin.invoices.send', $invoice) }}" class="inline-form">
                @csrf @method('PATCH')
                <button class="btn btn-blue btn-sm" title="Envoyer">📤</button>
            </form>
            @endif
            @if($invoice->status === 'envoyee')
            <form method="POST" action="{{ route('admin.invoices.pay', $invoice) }}" class="inline-form">
                @csrf @method('PATCH')
                <button class="btn btn-success btn-sm" title="Marquer payée">✓</button>
            </form>
            @endif
            <a href="{{ route('admin.invoices.edit', $invoice) }}" class="btn btn-ghost btn-sm" title="Modifier">✏️</a>
        </div>
    </td>
</tr>
@empty
<tr>
    <td colspan="9" style="text-align:center; color:var(--text3); padding:32px;">Aucune facture</td>
</tr>
@endforelse