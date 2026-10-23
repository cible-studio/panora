{{--
  Encart « Mails clients avant échéance » de l'écran Diffusion dispos
  (2026-10-23). Le suivi détaillé (mail J-7, renouvellements, journal) est
  sur l'écran Renouvellements ; ici, un résumé et les liens.
  Données : $finCampagne (DiffusionDisponibilitesController::index).
--}}
@php
    $fc = $finCampagne;
    $envoyes7j = $fc['journal']->filter(fn($l) => $l->statut === 'envoye' && ($l->envoye_at ?? $l->updated_at)->gte(now()->subDays(7)))->count();
    $aRetenter = $fc['journal']->where('statut', 'echec')->count();
    $mini = 'flex:1;min-width:150px;background:var(--surface2);border-radius:12px;padding:12px 14px;';
@endphp

<div style="{{ $card }}margin-top:18px;">
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:14px;">
        <h2 style="{{ $h2 }}margin:0;">✉️ Mails clients avant échéance</h2>
        @if($fc['modeTest'])
            <span style="padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:700;background:#fef3c7;color:#92400e;"
                  title="RELANCE_FIN_MODE=test : ces mails partent vers la liste Brevo « Tests internes ».">🧪 Mode test</span>
        @endif
        <span style="{{ $muted }}" title="Campagnes de 12 mois ou plus : séquence J-60 → J-7 avec boutons de réponse. Autres campagnes : un mail 7 jours avant la fin.">ⓘ Séquence de renouvellement et mail J-7</span>
        <a href="{{ route('admin.renouvellements.index') }}" class="btn btn-primary btn-sm" style="margin-left:auto;">Ouvrir les renouvellements →</a>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:10px;">
        <a href="{{ route('admin.renouvellements.index', ['onglet' => 'courtes', 'horizon' => 30]) }}" style="{{ $mini }}text-decoration:none;">
            <div style="{{ $muted }}font-weight:700;">Mails J-7 à venir (14 j)</div>
            <div style="font-size:22px;font-weight:800;color:var(--text);margin-top:4px;">{{ $fc['prochaines']->count() }}</div>
        </a>
        <a href="{{ route('admin.renouvellements.index', ['onglet' => 'journal']) }}" style="{{ $mini }}text-decoration:none;">
            <div style="{{ $muted }}font-weight:700;">Envoyés (7 derniers jours)</div>
            <div style="font-size:22px;font-weight:800;color:var(--text);margin-top:4px;">{{ $envoyes7j }}</div>
        </a>
        <a href="{{ route('admin.renouvellements.index', ['onglet' => 'journal']) }}" style="{{ $mini }}text-decoration:none;">
            <div style="{{ $muted }}font-weight:700;">À retenter</div>
            <div style="font-size:22px;font-weight:800;color:{{ $aRetenter ? '#991b1b' : 'var(--text)' }};margin-top:4px;">{{ $aRetenter }}</div>
        </a>
        <a href="{{ route('admin.renouvellements.modeles') }}" style="{{ $mini }}text-decoration:none;">
            <div style="{{ $muted }}font-weight:700;">Textes des mails</div>
            <div style="font-size:14px;font-weight:700;color:var(--accent);margin-top:8px;">✏️ Modifier</div>
        </a>
    </div>
</div>
