{{-- ═══════════════════════════════════════════════════════════════════
     Champ « Vague » des rechanges (2026-11-04) — partagé par les trois
     écrans de rechange : fiche campagne, fiche pose, action groupée de la
     liste des poses. Règles : App\Services\Vagues\VagueService.

     Envoie `vague` (« nouvelle », « en_cours » ou l'id d'une vague) et
     `vague_nom` (obligatoire pour une nouvelle vague, proposé « Vague N »).
     Masqué pour une retouche : elle reste dans la vague de la pose réparée.

     Paramètres :
       $prefixe      (string)    — préfixe des id HTML (plusieurs champs par page)
       $campaignId   (?int)      — campagne : ses vagues sont proposées
       $kindSelector (string)    — sélecteur CSS du select « type » (rechange/retouche)
       $avecEnCours  (?bool)     — option « Continuer la vague en cours »
                                   (liste des poses : plusieurs campagnes possibles)
     ═══════════════════════════════════════════════════════════════════ --}}
@php
    $serviceVagues = app(\App\Services\Vagues\VagueService::class);
    $vaguesChamp   = $serviceVagues->deLaCampagne($campaignId ?? null)->sortByDesc('numero');
    $nomChamp      = !empty($campaignId) ? $serviceVagues->nomPropose($campaignId) : '';
    $styleChamp    = 'width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:13px;color:var(--text);box-sizing:border-box';
@endphp
@if(\App\Services\Vagues\VagueService::disponible())
<div id="{{ $prefixe }}-vague-bloc" style="margin-bottom:14px">
    <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text2);margin-bottom:6px">
        Vague
        <span title="Nouveau message du client = nouvelle vague. Ses poses et ses piges se retrouveront sous ce nom (fiche campagne, page Piges, PDF)."
              style="cursor:help;color:var(--text3);font-weight:400">ⓘ</span>
    </label>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <select name="vague" id="{{ $prefixe }}-vague" style="{{ $styleChamp }}">
            <option value="nouvelle" selected>🌊 Nouvelle vague</option>
            @foreach($vaguesChamp as $vagueChamp)
                <option value="{{ $vagueChamp->id }}">Continuer : {{ $vagueChamp->libelle() }}</option>
            @endforeach
            @if($avecEnCours ?? false)
                <option value="en_cours">Continuer la vague en cours</option>
            @endif
        </select>
        <input type="text" name="vague_nom" id="{{ $prefixe }}-vague-nom" value="{{ $nomChamp }}" maxlength="150"
               placeholder="Nom, ex. « Phase 2 »" style="{{ $styleChamp }}">
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var bloc = document.getElementById(@json($prefixe . '-vague-bloc'));
    var choix = document.getElementById(@json($prefixe . '-vague'));
    var nom = document.getElementById(@json($prefixe . '-vague-nom'));
    var type = document.querySelector(@json($kindSelector));
    if (!bloc || !choix || !nom) return;

    function maj() {
        var retouche = !!type && type.value === 'retouche';
        var nouvelle = choix.value === 'nouvelle';
        bloc.style.display = retouche ? 'none' : '';
        nom.style.display = nouvelle ? '' : 'none';
        nom.required = nouvelle && !retouche;
    }
    choix.addEventListener('change', maj);
    if (type) type.addEventListener('change', maj);
    maj();
});
</script>
@endif
