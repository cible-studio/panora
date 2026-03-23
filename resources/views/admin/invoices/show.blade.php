<x-admin-layout>
<x-slot name="title">{{ $invoice->reference }}</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.invoices.pdf', $invoice) }}" class="btn btn-ghost btn-sm">
        📄 Export PDF
    </a>
    @if($invoice->status === 'brouillon')
    <form method="POST" action="{{ route('admin.invoices.send', $invoice) }}">
        @csrf
        @method('PATCH')
        <button type="submit" class="btn btn-blue btn-sm">📤 Envoyer</button>
    </form>
    @endif
    @if($invoice->status === 'envoyee')
    <form method="POST" action="{{ route('admin.invoices.pay', $invoice) }}">
        @csrf
        @method('PATCH')
        <button type="submit" class="btn btn-success btn-sm">✅ Marquer payée</button>
    </form>
    @endif
    <a href="{{ route('admin.invoices.edit', $invoice) }}" class="btn btn-ghost btn-sm">
        ✏️ Modifier
    </a>
</x-slot>

<div style="display:grid; grid-template-columns:1fr 280px; gap:20px;">

    {{-- COLONNE GAUCHE --}}
    <div>
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">{{ $invoice->reference }}</div>
                    <div style="font-size:12px; color:var(--text3); margin-top:3px;">
                        Émise le {{ $invoice->issued_at->format('d/m/Y') }}
                        par {{ $invoice->creator->name }}
                    </div>
                </div>
                @if($invoice->status === 'brouillon')
                    <span class="badge badge-gray" style="font-size:13px; padding:5px 14px;">Brouillon</span>
                @elseif($invoice->status === 'envoyee')
                    <span class="badge badge-blue" style="font-size:13px; padding:5px 14px;">Envoyée</span>
                @elseif($invoice->status === 'payee')
                    <span class="badge badge-green" style="font-size:13px; padding:5px 14px;">Payée ✓</span>
                @else
                    <span class="badge badge-red" style="font-size:13px; padding:5px 14px;">Annulée</span>
                @endif
            </div>
            <div class="card-body">
                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px;">
                    <div>
                        <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">CLIENT</div>
                        <div style="font-weight:600;">{{ $invoice->client->name }}</div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">CAMPAGNE</div>
                        <div style="font-weight:600;">{{ $invoice->campaign?->name ?? '—' }}</div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">DATE ÉMISSION</div>
                        <div style="font-weight:600;">{{ $invoice->issued_at->format('d/m/Y') }}</div>
                    </div>
                </div>

                {{-- MONTANTS --}}
                <div style="margin-top:20px; padding:16px; background:var(--surface2);
                            border-radius:10px; border:1px solid var(--border);">
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                        <span style="color:var(--text3);">Montant HT</span>
                        <span style="font-weight:600;">
                            {{ number_format($invoice->amount, 0, ',', ' ') }} FCFA
                        </span>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                        <span style="color:var(--text3);">TVA ({{ $invoice->tva }}%)</span>
                        <span style="font-weight:600;">
                            {{ number_format($invoice->amount * $invoice->tva / 100, 0, ',', ' ') }} FCFA
                        </span>
                    </div>
                    <div style="display:flex; justify-content:space-between;
                                padding-top:10px; border-top:1px solid var(--border);">
                        <span style="font-weight:700; font-size:14px;">TOTAL TTC</span>
                        <span style="font-weight:800; font-size:18px; color:var(--accent);">
                            {{ number_format($invoice->amount_ttc, 0, ',', ' ') }} FCFA
                        </span>
                    </div>
                </div>

                @if($invoice->paid_at)
                <div style="margin-top:16px; padding:12px; background:rgba(34,197,94,.1);
                            border:1px solid rgba(34,197,94,.3); border-radius:8px;">
                    <div style="color:var(--green); font-weight:600; font-size:12px;">
                        ✅ Payée le {{ $invoice->paid_at->format('d/m/Y') }}
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- COLONNE DROITE --}}
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">🏢 Client</div>
            </div>
            <div class="card-body">
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <div>
                        <div style="font-size:11px; color:var(--text3);">NOM</div>
                        <div style="font-weight:600;">{{ $invoice->client->name }}</div>
                    </div>
                    @if($invoice->client->email)
                    <div>
                        <div style="font-size:11px; color:var(--text3);">EMAIL</div>
                        <div>{{ $invoice->client->email }}</div>
                    </div>
                    @endif
                    @if($invoice->client->phone)
                    <div>
                        <div style="font-size:11px; color:var(--text3);">TÉLÉPHONE</div>
                        <div>{{ $invoice->client->phone }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>

</x-admin-layout>
