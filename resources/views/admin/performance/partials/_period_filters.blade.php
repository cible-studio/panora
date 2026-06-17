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

<form method="GET"
      @if($actionRoute) action="{{ $actionRoute }}" @endif
      class="perf-filter-card"
      style="margin-bottom:16px{{ $isCompact ? ';padding:10px 14px' : '' }}">
    <div style="display:flex;gap:{{ $isCompact ? '10px' : '14px' }};align-items:flex-end;flex-wrap:wrap">
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
