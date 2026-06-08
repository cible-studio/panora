<x-admin-layout>
<x-slot name="title">Modifier facture {{ $invoice->reference }}</x-slot>

<x-slot:topbarLeft>
    <a href="{{ route('admin.invoices.show', $invoice) }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour à la facture
    </a>
</x-slot:topbarLeft>

<div style="max-width:980px;margin:0 auto">

    <div style="font-size:12px;color:var(--text3);margin-bottom:16px">
        <a href="{{ route('admin.invoices.index') }}" style="color:var(--text3);text-decoration:none">Facturation</a>
        <span style="margin:0 6px">›</span>
        <a href="{{ route('admin.invoices.show', $invoice) }}" style="color:var(--text3);text-decoration:none">{{ $invoice->reference }}</a>
        <span style="margin:0 6px">›</span>
        <span style="color:var(--text)">Modifier</span>
    </div>

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 22px;margin-bottom:18px;display:flex;align-items:center;gap:14px">
        <div style="width:44px;height:44px;border-radius:12px;background:rgba(232,160,32,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:22px">
            ✏️
        </div>
        <div>
            <div style="font-size:16px;font-weight:800;color:var(--text);margin-bottom:3px">Modifier la facture {{ $invoice->reference }}</div>
            <div style="font-size:12px;color:var(--text3);line-height:1.5">
                Les totaux sont recalculés automatiquement après sauvegarde (InvoiceCalculator).
                @if($invoice->isLocked())
                    Facture verrouillée — déverrouille-la d'abord pour modifier.
                @endif
            </div>
        </div>
    </div>

    @include('admin.invoices.partials._form-fne', [
        'action'    => route('admin.invoices.update', $invoice),
        'method'    => 'PUT',
        'reference' => $invoice->reference,
        'invoice'   => $invoice,
    ])

</div>

</x-admin-layout>
