{{--
    Form partagé create/edit Équipe — variables attendues :
      $team         = PoseTeam (nouveau ou existant)
      $colors       = ['slug' => '#hex', …]
      $eligibleLeaders = Collection<User> (non leaders ailleurs OU leader courant)
      $techniciensLibres = Collection<User> (sans équipe)
--}}
@php
    $isEdit = isset($team) && $team->exists;
    $currentColor = old('color_slug', $team->color_slug ?? 'indigo');
@endphp

<div class="fne-field">
    <label>Nom de l'équipe <span class="req">*</span></label>
    <input type="text" name="name" value="{{ old('name', $team->name ?? '') }}" required minlength="2" maxlength="80" autofocus>
    @error('name') <span style="color:#dc2626;font-size:11px">{{ $message }}</span> @enderror
</div>

<div class="fne-field">
    <label>Couleur <span class="req">*</span></label>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        @foreach($colors as $slug => $hex)
            <label style="cursor:pointer;flex:0 0 auto">
                <input type="radio" name="color_slug" value="{{ $slug }}" {{ $currentColor === $slug ? 'checked' : '' }} style="display:none" class="js-color-radio">
                <span class="js-color-swatch" data-slug="{{ $slug }}"
                      style="display:block;width:36px;height:36px;border-radius:10px;background:{{ $hex }};border:3px solid transparent;transition:border .15s"
                      title="{{ ucfirst($slug) }}"></span>
            </label>
        @endforeach
    </div>
    <div style="font-size:11px;color:var(--text3);margin-top:6px">8 couleurs prédéfinies, cohérentes avec le design system Panora.</div>
</div>

<div class="fne-field">
    <label>Leader (optionnel)</label>
    <select name="leader_user_id">
        <option value="">— Aucun —</option>
        @foreach($eligibleLeaders ?? [] as $u)
            <option value="{{ $u->id }}" {{ (int)old('leader_user_id', $team->leader_user_id ?? 0) === $u->id ? 'selected' : '' }}>
                {{ $u->name }} ({{ $u->agent_code }})
            </option>
        @endforeach
    </select>
    <div style="font-size:11px;color:var(--text3);margin-top:6px">
        ℹ️ Un technicien ne peut être leader que d'<strong>une seule équipe</strong> à la fois.
    </div>
</div>

@if(!$isEdit && isset($techniciens) && $techniciens->isNotEmpty())
    <div class="fne-field">
        <label>Membres initiaux (optionnel)</label>
        <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 12px;max-height:200px;overflow-y:auto">
            @foreach($techniciens as $u)
                <label style="display:flex;align-items:center;gap:8px;padding:5px 0;font-size:13px;cursor:pointer">
                    <input type="checkbox" name="member_ids[]" value="{{ $u->id }}">
                    {{ $u->name }} <span style="color:var(--text3);font-family:monospace;font-size:11px">({{ $u->agent_code }})</span>
                </label>
            @endforeach
        </div>
        <div style="font-size:11px;color:var(--text3);margin-top:6px">
            Sélectionne les techniciens à rattacher. Tu pourras en ajouter plus tard depuis la liste.
        </div>
    </div>
@endif

<div class="fne-field">
    <label>Description (optionnel)</label>
    <textarea name="description" rows="2" maxlength="500" placeholder="Ex : équipe basée à Cocody, intervient sur le Sud d'Abidjan…">{{ old('description', $team->description ?? '') }}</textarea>
</div>

@if($isEdit)
    <div class="fne-field">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $team->is_active) ? 'checked' : '' }}>
            <span>Équipe active</span>
        </label>
        <div style="font-size:11px;color:var(--text3);margin-top:4px;margin-left:24px">
            Décoche pour masquer l'équipe des sélecteurs sans la supprimer (utile en cas de pause temporaire).
        </div>
    </div>
@endif

<script>
// Highlight de la couleur sélectionnée (radio masqué + swatch border)
(function () {
    const refresh = () => {
        document.querySelectorAll('.js-color-swatch').forEach(s => {
            const radio = s.previousElementSibling || s.parentElement.querySelector('input[name="color_slug"]');
            const isChecked = radio?.checked;
            s.style.border = isChecked ? '3px solid var(--text)' : '3px solid transparent';
            s.style.transform = isChecked ? 'scale(1.1)' : '';
        });
    };
    document.querySelectorAll('.js-color-radio').forEach(r => r.addEventListener('change', refresh));
    refresh();
})();
</script>
