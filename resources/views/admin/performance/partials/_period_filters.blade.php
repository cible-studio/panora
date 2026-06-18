{{--
    Filtres de période partagés — Performance Commerciale / Technicien.
    Mission « Ajustements feedback patronne » — Bloc 2 Famille A (2026-06-17).

    Source unique de vérité pour les 4 pages Performance :
      - /admin/performance/commerciaux        (index)
      - /admin/performance/commerciaux/{user} (show)
      - /admin/performance/techniciens        (index)
      - /admin/performance/techniciens/{user} (show)

    Si demain on ajoute "15 derniers jours" : 1 seul fichier à modifier.

    Paramètres @include :
      action_route  : string  — URL GET cible (route() courante par défaut)
      from          : Carbon  — date de début résolue par le controller
      to            : Carbon  — date de fin idem
      preset        : ?string — preset courant (today|week|month|quarter|year|all|null)
      reset_route   : ?string — URL du bouton "↺ Réinitialiser" (route()->name actuel sans params)
      extra_hidden  : array   — champs hidden à propager (ex : ['user_id' => 5])
      compact       : bool    — true = version dense (pages show), false = version standard

    Côté controller, supporte aussi : today, week (cf. resolvePeriod).
--}}
@php
    $actionRoute  = $action_route ?? null;
    $resetRoute   = $reset_route ?? null;
    $extraHidden  = $extra_hidden ?? [];
    $isCompact    = (bool) ($compact ?? false);
    $usingCustomRange = request()->filled('from') || request()->filled('to');
    $currentPreset    = $preset ?? null;
@endphp

@once
{{-- CSS scoped au partial — harmonise visuellement select / input[type=date]
     pour qu'ils aient la même hauteur, le même padding, la même typo.
     2026-06-18 : feedback patronne sur l'affichage de la page show. --}}
<style>
.perf-filter-card { display:flex; align-items:center; }
.perf-filter-card .fne-field { display:flex; flex-direction:column; gap:4px; }
.perf-filter-card .fne-field label {
    display:block;
    font-size:10px;
    font-weight:800;
    color:var(--text3);
    text-transform:uppercase;
    letter-spacing:.5px;
    margin:0;
}
.perf-filter-card .fne-field select,
.perf-filter-card .fne-field input[type="date"] {
    height:38px;
    box-sizing:border-box;
    padding:0 12px;
    border:1px solid var(--border);
    border-radius:8px;
    background:var(--surface, #fff);
    color:var(--text);
    font-size:13px;
    font-family:inherit;
    line-height:1;
    outline:none;
    transition:border-color .12s, box-shadow .12s;
    width:100%;
}
.perf-filter-card .fne-field select {
    padding-right:30px;
    cursor:pointer;
    -webkit-appearance:none;
    appearance:none;
    background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
    background-repeat:no-repeat;
    background-position:right 10px center;
    background-size:12px;
}
.perf-filter-card .fne-field input[type="date"] {
    font-variant-numeric:tabular-nums;
}
.perf-filter-card .fne-field select:focus,
.perf-filter-card .fne-field input[type="date"]:focus {
    border-color:var(--accent, #e8a020);
    box-shadow:0 0 0 3px rgba(232,160,32,.18);
}
.perf-filter-card .fne-field input[type="date"]::-webkit-calendar-picker-indicator {
    cursor:pointer; opacity:.6; transition:opacity .12s;
}
.perf-filter-card .fne-field input[type="date"]:hover::-webkit-calendar-picker-indicator { opacity:1; }
</style>
@endonce

<form method="GET"
      @if($actionRoute) action="{{ $actionRoute }}" @endif
      class="perf-filter-card"
      style="margin-bottom:16px{{ $isCompact ? ';padding:10px 14px' : '' }}">
    <div style="display:flex;gap:{{ $isCompact ? '10px' : '14px' }};align-items:flex-end;flex-wrap:wrap;width:100%">
        {{-- Hidden fields à propager (ex : user_id sur les pages show) --}}
        @foreach($extraHidden as $name => $value)
            @if($value !== null && $value !== '')
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endif
        @endforeach

        <div class="fne-field" style="min-width:{{ $isCompact ? '140px' : '170px' }}">
            <label>Période rapide</label>
            <select name="preset"
                    onchange="this.form.querySelector('[name=from]').value='';this.form.querySelector('[name=to]').value='';this.form.submit()">
                <option value=""        {{ $usingCustomRange ? 'selected' : '' }} disabled hidden>— Personnalisée —</option>
                <option value="today"   {{ !$usingCustomRange && $currentPreset === 'today'   ? 'selected' : '' }}>Aujourd'hui</option>
                <option value="week"    {{ !$usingCustomRange && $currentPreset === 'week'    ? 'selected' : '' }}>Cette semaine</option>
                <option value="month"   {{ !$usingCustomRange && $currentPreset === 'month'   ? 'selected' : '' }}>Ce mois</option>
                <option value="quarter" {{ !$usingCustomRange && $currentPreset === 'quarter' ? 'selected' : '' }}>Ce trimestre</option>
                <option value="year"    {{ !$usingCustomRange && ($currentPreset === 'year' || $currentPreset === null) ? 'selected' : '' }}>Cette année</option>
                <option value="all"     {{ !$usingCustomRange && $currentPreset === 'all'     ? 'selected' : '' }}>Tout</option>
            </select>
        </div>
        <div class="fne-field" style="min-width:{{ $isCompact ? '130px' : '140px' }}">
            <label>Du</label>
            <input type="date" name="from"
                   value="{{ request('from', $usingCustomRange ? optional($from)->format('Y-m-d') : '') }}"
                   onchange="this.form.querySelector('[name=preset]').value='';this.form.submit()">
        </div>
        <div class="fne-field" style="min-width:{{ $isCompact ? '130px' : '140px' }}">
            <label>Au</label>
            <input type="date" name="to"
                   value="{{ request('to', $usingCustomRange ? optional($to)->format('Y-m-d') : '') }}"
                   onchange="this.form.querySelector('[name=preset]').value='';this.form.submit()">
        </div>

        @if($usingCustomRange && $resetRoute)
            <a href="{{ $resetRoute }}"
               class="btn btn-ghost btn-sm"
               style="height:38px;display:inline-flex;align-items:center"
               title="Revenir aux périodes rapides">↺ Réinitialiser</a>
        @endif
    </div>
</form>
