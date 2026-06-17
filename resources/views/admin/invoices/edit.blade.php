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

<x-slot name="topbarActions">
    <a href="{{ route('admin.invoices.show', $invoice) }}" class="btn btn-ghost btn-sm">👁️ Voir</a>
    <a href="{{ route('admin.invoices.pdf', $invoice) }}" class="btn btn-ghost btn-sm">📄 PDF</a>
</x-slot>

<div style="max-width:1240px;margin:0 auto">

    {{-- Breadcrumb --}}
    <div style="font-size:12px;color:var(--text3);margin-bottom:12px">
        <a href="{{ route('admin.invoices.index') }}" style="color:var(--text3);text-decoration:none">Facturation</a>
        <span style="margin:0 6px">›</span>
        <a href="{{ route('admin.invoices.show', $invoice) }}" style="color:var(--text3);text-decoration:none">{{ $invoice->reference }}</a>
        <span style="margin:0 6px">›</span>
        <span style="color:var(--text)">Modifier</span>
    </div>

    {{-- ═══ HERO HEADER ÉDITION ═══
         Layout : icône + référence + métadonnées (date émission, client,
         statut). Si verrouillée → bandeau d'avertissement explicite.
    ════════════════════════════════════════════════════════════════ --}}
    @php
        // Couvre les 9 statuts du cycle de vie (cahier §3). Avant : 4 sur 9
        // → UnhandledMatchError 500 dès que la facture passait en
        // generee/validee/partiellement_payee/en_retard/litige et qu'on
        // ouvrait /edit. Label centralisé via Invoice::statusLabel().
        $statusPalette = [
            'brouillon'           => ['bg' => 'rgba(107,114,128,.10)', 'color' => '#4b5563', 'icon' => '📝'],
            'generee'             => ['bg' => 'rgba(232,160,32,.10)',  'color' => '#a16207', 'icon' => '📋'],
            'validee'             => ['bg' => 'rgba(99,102,241,.10)',  'color' => '#4338ca', 'icon' => '🔒'],
            'envoyee'             => ['bg' => 'rgba(59,130,246,.10)',  'color' => '#1d4ed8', 'icon' => '📤'],
            'partiellement_payee' => ['bg' => 'rgba(245,158,11,.10)',  'color' => '#b45309', 'icon' => '⏳'],
            'payee'               => ['bg' => 'rgba(34,197,94,.10)',   'color' => '#15803d', 'icon' => '✅'],
            'en_retard'           => ['bg' => 'rgba(239,68,68,.10)',   'color' => '#b91c1c', 'icon' => '⚠️'],
            'litige'              => ['bg' => 'rgba(244,63,94,.10)',   'color' => '#9f1239', 'icon' => '⚖️'],
            'annulee'             => ['bg' => 'rgba(107,114,128,.10)', 'color' => '#374151', 'icon' => '🚫'],
        ];
        $statusCfg = $statusPalette[$invoice->status] ?? ['bg' => 'rgba(107,114,128,.10)', 'color' => '#4b5563', 'icon' => '📝'];
        $statusCfg['label'] = $statusCfg['icon'] . ' ' . \App\Models\Invoice::statusLabel($invoice->status);
    @endphp
    <div style="background:linear-gradient(135deg,rgba(232,160,32,.06) 0%,rgba(180,83,9,.04) 100%);border:1px solid var(--border);border-radius:16px;padding:20px 26px;margin-bottom:14px;display:flex;align-items:center;gap:18px;flex-wrap:wrap">
        <div style="width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,var(--accent),var(--accent-dark));display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 6px 16px rgba(232,160,32,.25);font-size:22px">
            ✏️
        </div>
        <div style="flex:1;min-width:240px">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <div style="font-size:18px;font-weight:800;color:var(--text);letter-spacing:-.2px">Modifier {{ $invoice->reference }}</div>
                <span style="background:{{ $statusCfg['bg'] }};color:{{ $statusCfg['color'] }};padding:3px 10px;border-radius:999px;font-size:10.5px;font-weight:800;letter-spacing:.3px">{{ $statusCfg['label'] }}</span>
            </div>
            <div style="font-size:12.5px;color:var(--text3);margin-top:3px;line-height:1.5">
                Émise le <strong style="color:var(--text2)">{{ $invoice->issued_at->format('d/m/Y') }}</strong>
                @if($invoice->client) · Client <strong style="color:var(--text2)">{{ $invoice->client->name }}</strong>@endif
                @if($invoice->campaign) · Campagne <strong style="color:var(--text2)">{{ \Illuminate\Support\Str::limit($invoice->campaign->name, 40) }}</strong>@endif
                · Total actuel <strong style="color:var(--accent)">{{ number_format((float) ($invoice->total_a_payer ?: $invoice->amount_ttc), 0, ',', ' ') }} FCFA</strong>
            </div>
        </div>
    </div>

    {{-- Bandeau verrou si facture verrouillée (envoyée/payée) --}}
    @if($invoice->isLocked())
        <div style="background:linear-gradient(180deg,#fef9c3 0%,#fef3c7 100%);border:1.5px solid #f59e0b;border-radius:14px;padding:14px 18px;margin-bottom:18px;display:flex;align-items:flex-start;gap:14px">
            <div style="width:38px;height:38px;border-radius:10px;background:#f59e0b;display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;flex-shrink:0;font-weight:800">🔒</div>
            <div style="flex:1;line-height:1.55">
                <div style="font-size:14px;font-weight:800;color:#78350f;margin-bottom:2px">
                    Facture verrouillée le {{ $invoice->locked_at->format('d/m/Y à H:i') }}
                    @if($invoice->lockedBy) par {{ $invoice->lockedBy->name }}@endif
                </div>
                <div style="font-size:12.5px;color:#92400e">
                    Une facture envoyée au client est immuable. Pour la modifier, retourne sur la fiche détail et clique <strong>↩ Rebasculer en brouillon</strong>. L'action est tracée dans les logs.
                </div>
            </div>
            <a href="{{ route('admin.invoices.show', $invoice) }}" class="btn btn-ghost btn-sm" style="flex-shrink:0;background:rgba(255,255,255,.6);font-weight:700">Retour à la fiche</a>
        </div>
    @endif

    @include('admin.invoices.partials._form-fne', [
        'action'    => route('admin.invoices.update', $invoice),
        'method'    => 'PUT',
        'reference' => $invoice->reference,
        'invoice'   => $invoice,
    ])

</div>

</x-admin-layout>
