<x-admin-layout>
<x-slot name="title">Nouvelle facture</x-slot>

<x-slot:topbarLeft>
    <a href="{{ route('admin.invoices.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour aux factures
    </a>
</x-slot:topbarLeft>

<div style="max-width:980px;margin:0 auto">

    <div style="font-size:12px;color:var(--text3);margin-bottom:16px">
        <a href="{{ route('admin.invoices.index') }}" style="color:var(--text3);text-decoration:none">Facturation</a>
        <span style="margin:0 6px">›</span>
        <span style="color:var(--text)">Nouvelle facture FNE</span>
    </div>

    {{-- Intro + raccourci génération depuis campagne --}}
    <div style="background:linear-gradient(135deg,rgba(58,168,53,.05),rgba(232,160,32,.05));border:1px solid var(--border);border-radius:14px;padding:18px 22px;margin-bottom:18px">
        <div style="display:flex;align-items:flex-start;gap:14px">
            <div style="width:44px;height:44px;border-radius:12px;background:rgba(58,168,53,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#3aa835" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <div style="flex:1">
                <div style="font-size:16px;font-weight:800;color:var(--text);margin-bottom:3px">Créer une facture FNE</div>
                <div style="font-size:12.5px;color:var(--text3);line-height:1.55">
                    Facture conforme FNE (TOTAL HT → TVA → TOTAL TTC → Autres taxes TSP/TM/ODP → Services TTC → TOTAL À PAYER).
                    Les tarifs ODP de chaque commune sont historisés (cf. tableau 2025).
                    <br><strong>💡 Astuce :</strong> pour une campagne existante, va directement sur sa fiche et clique « ⚡ Générer la facture FNE » — c'est plus rapide.
                </div>
            </div>
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
