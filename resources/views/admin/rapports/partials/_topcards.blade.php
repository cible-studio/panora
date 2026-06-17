<div id="rpt-topcards">
{{-- ════ RAPPORTS DÉTAILLÉS — RACCOURCIS ════ --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin-bottom:20px">
    <a href="{{ route('admin.rapports.campagnes') }}"
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

    {{-- Annulations entreprise + Taxes communales : admin/MP only.
         Pour le commercial, on cache les liens (les routes sont déjà
         bloquées côté backend, mais évite un 403 si l'admin a partagé
         le lien ou si l'admin a cliqué par erreur côté UI commercial). --}}
    @if(auth()->user()?->role?->value !== 'commercial')
    <a href="{{ route('admin.rapports.annulations') }}"
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

    <a href="{{ route('admin.rapports.taxes') }}"
       style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:var(--surface);border:1px solid var(--border);border-left:4px solid #8b5cf6;border-radius:12px;text-decoration:none;transition:transform .15s,border-color .15s,box-shadow .15s"
       onmouseenter="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 18px rgba(0,0,0,.08)'"
       onmouseleave="this.style.transform='';this.style.boxShadow=''">
        <div style="width:38px;height:38px;border-radius:10px;background:rgba(139,92,246,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:700;color:var(--text)">Rapport taxes</div>
            <div style="font-size:11px;color:var(--text3);margin-top:2px">Suivi des taxes communales</div>
        </div>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
    @endif
</div>
</div>