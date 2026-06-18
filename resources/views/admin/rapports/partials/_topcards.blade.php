<div id="rpt-topcards">
{{-- ════ RAPPORTS DÉTAILLÉS — RACCOURCIS ════ --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin-bottom:20px">
    {{-- Bug E corrigé : data-route-base est utilisé par rapports-live.js pour
         reconstruire le href avec la query string des filtres courants. --}}
    <a href="{{ route('admin.rapports.campagnes') }}"
       data-route-base="{{ route('admin.rapports.campagnes') }}"
       style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:var(--surface);border:1px solid var(--border);border-left:4px solid #fab80b;border-radius:12px;text-decoration:none;transition:transform .15s,border-color .15s,box-shadow .15s"
       onmouseenter="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 18px rgba(0,0,0,.08)'"
       onmouseleave="this.style.transform='';this.style.boxShadow=''">
        <div style="width:38px;height:38px;border-radius:10px;background:rgba(250,184,11,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fab80b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
        </div>
        <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:700;color:var(--text)">Rapport campagnes</div>
            <div style="font-size:11px;color:var(--text3);margin-top:2px">Performance · motifs d'annulation · top</div>
        </div>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </a>

    {{-- Annulations entreprise : admin/MP only. Pour le commercial, on
         cache le lien (la route est déjà bloquée côté backend, mais évite
         un 403 si l'admin a partagé le lien ou si l'admin a cliqué par
         erreur côté UI commercial).
         Note : la card "Rapport taxes" a été retirée de ce hub — le
         rapport reste accessible depuis le module Taxes lui-même
         (admin/taxes : boutons "📊 Rapport" sur index + historique). --}}
    @if(auth()->user()?->role?->value !== 'commercial')
    <a href="{{ route('admin.rapports.annulations') }}"
       data-route-base="{{ route('admin.rapports.annulations') }}"
       style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:var(--surface);border:1px solid var(--border);border-left:4px solid #ef4444;border-radius:12px;text-decoration:none;transition:transform .15s,border-color .15s,box-shadow .15s"
       onmouseenter="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 18px rgba(0,0,0,.08)'"
       onmouseleave="this.style.transform='';this.style.boxShadow=''">
        <div style="width:38px;height:38px;border-radius:10px;background:rgba(239,68,68,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:700;color:var(--text)">Rapport annulations</div>
            <div style="font-size:11px;color:var(--text3);margin-top:2px">Détail des campagnes annulées</div>
        </div>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </a>

    {{-- 2026-06-18 (feedback patronne) : carte "Rapport taxes" retirée de
         cette page. Le suivi des taxes communales reste accessible
         depuis la sidebar (section Opérations → Taxes communes) pour
         admin + MP — pas besoin de doublonner ici. --}}
    @endif
</div>
</div>