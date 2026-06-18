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
      data-perf-filters
      style="margin-bottom:16px{{ $isCompact ? ';padding:10px 14px' : '' }}">
    <div style="display:flex;gap:{{ $isCompact ? '10px' : '14px' }};align-items:flex-end;flex-wrap:wrap;width:100%">
        {{-- Hidden fields à propager (ex : user_id sur les pages show) --}}
        @foreach($extraHidden as $name => $value)
            @if($value !== null && $value !== '')
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endif
        @endforeach

        @php
            // 2026-06-18 (feedback patronne) : on calcule les VRAIES dates
            // du/au correspondant au preset sélectionné, et on les pousse
            // dans les inputs date au lieu de les laisser vides. L'utilisateur
            // voit donc tout de suite "Ce mois = 01/06/2026 → 30/06/2026"
            // au lieu de jj/mm/aaaa génériques. Côté serveur, resolvePeriod
            // priorise from/to s'ils sont remplis ; mais comme on les
            // resoumet avec le preset, on RETIRE les hidden from/to du submit
            // change-preset (via JS) — sinon le serveur ignorerait le preset.
            $fromVal = request('from', optional($from)->format('Y-m-d'));
            $toVal   = request('to',   optional($to)->format('Y-m-d'));
            $hasAnyFilter = $usingCustomRange || $currentPreset !== null;
        @endphp

        <div class="fne-field" style="min-width:{{ $isCompact ? '140px' : '170px' }}">
            <label>Période rapide</label>
            <select name="preset" data-perf-preset>
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
                   data-perf-from
                   value="{{ $fromVal }}">
        </div>
        <div class="fne-field" style="min-width:{{ $isCompact ? '130px' : '140px' }}">
            <label>Au</label>
            <input type="date" name="to"
                   data-perf-to
                   value="{{ $toVal }}">
        </div>

        {{-- Bouton "Réinitialiser" toujours visible, désactivé si aucun
             filtre actif. Fallback CSS pour la sémantique disabled. --}}
        @if($resetRoute)
            <a href="{{ $resetRoute }}"
               class="btn btn-ghost btn-sm"
               style="height:38px;display:inline-flex;align-items:center;gap:5px;{{ $hasAnyFilter ? '' : 'opacity:.45;pointer-events:none' }}"
               title="Revenir aux dates par défaut">↺ Réinitialiser</a>
        @endif
    </div>
</form>

{{-- JS perf-filters — calcule les vraies dates au change preset + auto-submit.
     Une seule fois grâce à @once même si le partial est inclus 2 fois sur
     la page (ex. pages show qui ré-incluent le filtre). --}}
@once
<script>
(function () {
    // Calcule [from, to] (YYYY-MM-DD) à partir d'un preset.
    // Doit rester aligné avec CommercialPerformanceController::resolvePeriod
    // côté serveur — sinon le filtre affiché ≠ filtre appliqué.
    function presetRange(preset) {
        var now = new Date();
        var fmt = function (d) {
            var y = d.getFullYear();
            var m = String(d.getMonth() + 1).padStart(2, '0');
            var dd = String(d.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + dd;
        };
        var from, to;
        switch (preset) {
            case 'today':
                from = to = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                break;
            case 'week': {
                var dow = (now.getDay() + 6) % 7; // 0 = lundi
                from = new Date(now.getFullYear(), now.getMonth(), now.getDate() - dow);
                to   = new Date(from.getFullYear(), from.getMonth(), from.getDate() + 6);
                break;
            }
            case 'month':
                from = new Date(now.getFullYear(), now.getMonth(), 1);
                to   = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                break;
            case 'quarter': {
                var q = Math.floor(now.getMonth() / 3);
                from = new Date(now.getFullYear(), q * 3, 1);
                to   = new Date(now.getFullYear(), q * 3 + 3, 0);
                break;
            }
            case 'year':
                from = new Date(now.getFullYear(), 0, 1);
                to   = new Date(now.getFullYear(), 11, 31);
                break;
            case 'all':
                from = new Date(2020, 0, 1);
                to   = now;
                break;
            default:
                return null;
        }
        return { from: fmt(from), to: fmt(to) };
    }

    document.querySelectorAll('form[data-perf-filters]').forEach(function (form) {
        var preset = form.querySelector('[data-perf-preset]');
        var from   = form.querySelector('[data-perf-from]');
        var to     = form.querySelector('[data-perf-to]');

        if (preset) {
            preset.addEventListener('change', function () {
                var r = presetRange(preset.value);
                if (r) {
                    from.value = r.from;
                    to.value   = r.to;
                }
                form.submit();
            });
        }
        // Saisie manuelle d'une date → on retire le preset pour basculer en custom.
        [from, to].forEach(function (input) {
            if (!input) return;
            input.addEventListener('change', function () {
                if (preset) preset.value = '';
                form.submit();
            });
        });
    });
})();
</script>
@endonce
