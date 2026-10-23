{{-- Styles des écrans Renouvellements (2026-10-23). Variables du thème admin : clair et sombre. --}}
<style>
    .rn-card { background:var(--surface); border:1px solid var(--border); border-radius:16px; }
    .rn-head { display:flex; flex-wrap:wrap; gap:12px; align-items:center; padding:18px 22px; }
    .rn-title { font-size:20px; font-weight:800; color:var(--text); margin:0; display:flex; align-items:center; gap:10px; }
    .rn-sub { font-size:13px; color:var(--text3); margin-top:3px; }
    .rn-actions { margin-left:auto; display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .rn-btn { display:inline-flex; align-items:center; gap:7px; height:36px; padding:0 14px; border-radius:10px; border:1px solid var(--border); background:var(--surface); color:var(--text); font-size:13px; font-weight:700; text-decoration:none; cursor:pointer; transition:background .15s, border-color .15s; white-space:nowrap; }
    .rn-btn:hover { border-color:var(--accent); }
    .rn-btn.primaire { background:var(--accent); border-color:var(--accent); color:#fff; }
    .rn-btn.vert { background:#166534; border-color:#166534; color:#fff; }
    .rn-btn.petit { height:30px; padding:0 10px; font-size:12px; border-radius:8px; }
    .rn-btn svg { width:16px; height:16px; }
    .rn-chip { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; white-space:nowrap; line-height:1.3; }
    .rn-chip.test { background:#fef3c7; color:#92400e; }
    .rn-chip.prod { background:#dcfce7; color:#166534; }
    .rn-muted { color:var(--text3); font-size:12px; }

    .rn-tabs { display:flex; gap:2px; border-bottom:1px solid var(--border); margin:18px 0 16px; overflow-x:auto; }
    .rn-tab { padding:10px 16px; font-size:14px; font-weight:700; color:var(--text3); text-decoration:none; border-bottom:2px solid transparent; margin-bottom:-1px; white-space:nowrap; }
    .rn-tab:hover { color:var(--text); }
    .rn-tab.on { color:var(--text); border-bottom-color:var(--accent); }
    .rn-tab .n { margin-left:7px; background:var(--surface2); color:var(--text2); border-radius:999px; padding:1px 8px; font-size:11.5px; }

    .rn-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(175px,1fr)); gap:12px; margin-bottom:16px; }
    .rn-kpi { display:block; text-decoration:none; padding:14px 16px; border-radius:14px; background:var(--surface); border:1px solid var(--border); transition:border-color .15s, transform .15s; }
    .rn-kpi:hover { border-color:var(--accent); transform:translateY(-1px); }
    .rn-kpi.on { outline:2px solid var(--accent); outline-offset:-1px; }
    .rn-kpi .lib { font-size:12.5px; font-weight:700; color:var(--text2); display:flex; align-items:center; gap:7px; }
    .rn-kpi .pt { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
    .rn-kpi .val { font-size:28px; font-weight:800; color:var(--text); margin-top:8px; line-height:1; }
    .rn-kpi .ca { font-size:12.5px; color:var(--text2); margin-top:6px; font-weight:600; }
    .rn-kpi .ca small { color:var(--text3); font-weight:500; }

    .rn-bar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:12px; }
    .rn-seg { display:inline-flex; background:var(--surface2); border-radius:10px; padding:3px; }
    .rn-seg a { padding:6px 12px; border-radius:8px; font-size:12.5px; font-weight:700; color:var(--text2); text-decoration:none; }
    .rn-seg a.on { background:var(--surface); color:var(--text); box-shadow:0 1px 3px rgba(0,0,0,.15); }
    .rn-search { position:relative; flex:1; min-width:200px; max-width:360px; }
    .rn-search input { width:100%; height:36px; padding:0 12px 0 34px; border-radius:10px; border:1px solid var(--border); background:var(--surface); color:var(--text); font-size:13px; }
    .rn-search svg { position:absolute; left:11px; top:10px; width:16px; height:16px; color:var(--text3); }
    .rn-select { height:36px; border-radius:10px; border:1px solid var(--border); background:var(--surface); color:var(--text); font-size:13px; padding:0 10px; }

    .rn-table { width:100%; border-collapse:collapse; font-size:13.5px; }
    .rn-table th { text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:var(--text3); padding:11px 14px; background:var(--surface2); font-weight:700; }
    .rn-table td { padding:13px 14px; border-top:1px solid var(--border); vertical-align:middle; color:var(--text); }
    .rn-row { cursor:pointer; }
    .rn-row:hover td { background:var(--surface2); }
    .rn-client { font-weight:800; }
    .rn-camp { font-size:12.5px; color:var(--text3); margin-top:2px; }
    .rn-j { display:inline-block; margin-top:3px; font-size:11.5px; font-weight:800; padding:1px 7px; border-radius:6px; background:var(--surface2); color:var(--text2); }
    .rn-j.chaud { background:#fee2e2; color:#991b1b; }
    .rn-j.tiede { background:#fef3c7; color:#92400e; }
    .rn-action { font-size:13px; font-weight:700; }
    .rn-action.urgent { color:#dc2626; } .rn-action.alerte { color:#d97706; } .rn-action.ok { color:#16a34a; }
    .rn-action.info { color:var(--text2); font-weight:600; } .rn-action.neutre { color:var(--text3); font-weight:600; }
    .rn-chevron { color:var(--text3); font-size:18px; text-align:right; }

    /* Frise des 5 mails (J-60 → J-7). */
    .rn-seq { display:flex; gap:4px; align-items:center; }
    .rn-seq i { display:block; width:22px; height:8px; border-radius:4px; box-sizing:border-box; }
    .rn-e-envoye { background:#3aa835; } .rn-e-aujourdhui { background:#fab80b; }
    .rn-e-prevu { border:1.5px solid var(--text3); opacity:.55; } .rn-e-echec { background:#e20613; }
    .rn-e-exclu { background:#9ca3af; } .rn-e-arrete { border:1.5px dashed var(--border); }
    .rn-e-saute { background:var(--border); opacity:.5; }

    /* Info-bulles. */
    [data-tip] { position:relative; }
    [data-tip]:hover::after { content:attr(data-tip); position:absolute; bottom:calc(100% + 8px); left:50%; transform:translateX(-50%); background:#111; color:#fff; font-size:12px; font-weight:500; line-height:1.4; padding:7px 10px; border-radius:8px; white-space:normal; width:max-content; max-width:260px; z-index:50; pointer-events:none; text-transform:none; letter-spacing:0; }
    .rn-info { display:inline-flex; width:17px; height:17px; border-radius:50%; align-items:center; justify-content:center; font-size:11px; font-weight:800; background:var(--surface2); color:var(--text3); cursor:help; }

    /* Panneaux latéraux (fiche dossier, guide). */
    .rn-voile { position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9990; }
    .rn-panneau { position:fixed; top:0; right:0; height:100%; width:min(540px,100%); background:var(--surface); z-index:9991; overflow-y:auto; box-shadow:-12px 0 40px rgba(0,0,0,.25); }
    .rn-panneau header { position:sticky; top:0; z-index:2; background:var(--surface); padding:18px 22px; border-bottom:1px solid var(--border); display:flex; gap:12px; align-items:flex-start; }
    .rn-fermer { margin-left:auto; width:34px; height:34px; border-radius:10px; border:1px solid var(--border); background:var(--surface); color:var(--text2); font-size:20px; cursor:pointer; flex-shrink:0; }
    .rn-section { padding:16px 22px; border-bottom:1px solid var(--border); }
    .rn-section h4 { font-size:11.5px; text-transform:uppercase; letter-spacing:.6px; color:var(--text3); margin:0 0 12px; font-weight:800; }
    .rn-grille { display:grid; grid-template-columns:1fr 1fr; gap:12px 16px; }
    .rn-grille .lbl { font-size:11.5px; color:var(--text3); }
    .rn-grille .v { font-size:14px; font-weight:700; color:var(--text); margin-top:2px; word-break:break-word; }
    .rn-frise { list-style:none; margin:0; padding:0; }
    .rn-frise li { display:flex; gap:12px; align-items:flex-start; padding:7px 0; }
    .rn-frise .pt { width:12px; height:12px; border-radius:50%; margin-top:4px; flex-shrink:0; box-sizing:border-box; }
    .rn-frise .et { font-weight:800; font-size:13px; width:44px; flex-shrink:0; color:var(--text); }
    .rn-frise .tx { font-size:13px; color:var(--text2); flex:1; }
    .rn-alerte { border-radius:12px; padding:10px 12px; font-size:13px; line-height:1.5; }
    .rn-alerte.jaune { background:#fef3c7; color:#92400e; }
    .rn-alerte.bleu { background:#e0f2fe; color:#075985; }
    .rn-champ { display:block; font-size:12px; font-weight:700; color:var(--text2); margin:10px 0 5px; }
    .rn-input { width:100%; border:1px solid var(--border); border-radius:10px; background:var(--surface); color:var(--text); font-size:13.5px; padding:8px 11px; }
    .rn-histo { border-left:2px solid var(--border); padding-left:12px; }
    .rn-histo div { font-size:12.5px; margin-bottom:10px; color:var(--text); }
    .rn-vide { padding:44px 20px; text-align:center; color:var(--text3); font-size:14px; line-height:1.7; }
    .rn-vide .ic { font-size:34px; margin-bottom:8px; }
    .rn-guide h3 { font-size:15px; font-weight:800; color:var(--text); margin:0 0 8px; display:flex; gap:8px; align-items:center; }
    .rn-guide p, .rn-guide li { font-size:13.5px; color:var(--text2); line-height:1.6; margin:0 0 6px; }
    .rn-guide ul { padding-left:18px; margin:0; }
    @media (max-width: 760px) { .rn-hide-sm { display:none; } .rn-grille { grid-template-columns:1fr; } }
</style>
