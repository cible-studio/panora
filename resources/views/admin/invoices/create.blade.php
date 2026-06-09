<x-admin-layout>
<x-slot name="title">Nouvelle facture FNE</x-slot>

<x-slot:topbarLeft>
    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('admin.invoices.index') }}"
       class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour
    </a>
</x-slot:topbarLeft>

<div style="max-width:1240px;margin:0 auto">

    {{-- Breadcrumb compact --}}
    <div style="font-size:12px;color:var(--text3);margin-bottom:12px">
        <a href="{{ route('admin.invoices.index') }}" style="color:var(--text3);text-decoration:none">Facturation</a>
        <span style="margin:0 6px">›</span>
        <span style="color:var(--text)">Nouvelle facture FNE</span>
    </div>

    {{-- ═══ HERO HEADER ═══
         Gradient doré/vert subtil + info clé (référence pré-générée +
         astuce "génère depuis campagne pour gagner du temps"). --}}
    <div style="background:linear-gradient(135deg,rgba(232,160,32,.08) 0%,rgba(58,168,53,.05) 100%);border:1px solid var(--border);border-radius:16px;padding:20px 26px;margin-bottom:18px;display:flex;align-items:center;gap:18px;flex-wrap:wrap">
        <div style="width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,#16a34a,#22c55e);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 6px 16px rgba(34,197,94,.25)">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="12" y1="11" x2="12" y2="17"/>
                <line x1="9" y1="14" x2="15" y2="14"/>
            </svg>
        </div>
        <div style="flex:1;min-width:240px">
            <div style="font-size:18px;font-weight:800;color:var(--text);letter-spacing:-.2px">Nouvelle facture FNE</div>
            <div style="font-size:12.5px;color:var(--text3);margin-top:3px;line-height:1.5">
                Conforme FNE — TVA, TSP, ODP, TM. Référence proposée :
                <strong style="color:var(--accent-dark);font-family:ui-monospace,monospace">{{ $reference }}</strong>
            </div>
        </div>
        <div style="background:rgba(59,130,246,.06);border:1px solid rgba(59,130,246,.20);border-radius:10px;padding:10px 14px;font-size:11.5px;color:#1d4ed8;line-height:1.45;max-width:340px">
            💡 <strong>Astuce</strong> — Pour facturer une campagne existante, ouvre sa fiche et clique « ⚡ Générer la facture FNE ». Tout est pré-rempli.
        </div>
    </div>

    @include('admin.invoices.partials._form-fne', [
        'action'    => route('admin.invoices.store'),
        'method'    => 'POST',
        'reference' => $reference,
        'invoice'   => null,
    ])

</div>

</x-admin-layout>
