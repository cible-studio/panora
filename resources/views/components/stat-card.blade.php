@props([
    'value'   => 0,
    'label'   => '',
    'icon'    => '📊',
    'color'   => 'orange',
    'href'    => null,
    'active'  => false,
    'filter'  => null,
    'filterValue' => null,
    'sub'     => null,
    'kpiKey'  => null,   // expose data-kpi-value pour update AJAX
])

@php
    $classes = 'stat-card stat-card-' . $color . ($active ? ' active' : '');
    $extra = '';
    if ($filter) {
        $extra .= ' data-filter="' . e($filter) . '"';
        $extra .= ' data-value="' . e($filterValue ?? '') . '"';
    }
    if ($kpiKey) {
        $extra .= ' data-kpi="' . e($kpiKey) . '"';
    }
@endphp

@if($href)
    <a href="{{ $href }}" class="{{ $classes }}" {!! $extra !!} {{ $attributes }}>
        <div class="stat-icon">{{ $icon }}</div>
        <div class="stat-value" @if($kpiKey) data-kpi-value="{{ $kpiKey }}" @endif>{{ $value }}</div>
        <div class="stat-label">{{ $label }}</div>
        @if($sub)<div class="stat-sub">{{ $sub }}</div>@endif
    </a>
@else
    <div class="{{ $classes }}" {!! $extra !!} {{ $attributes }}>
        <div class="stat-icon">{{ $icon }}</div>
        <div class="stat-value" @if($kpiKey) data-kpi-value="{{ $kpiKey }}" @endif>{{ $value }}</div>
        <div class="stat-label">{{ $label }}</div>
        @if($sub)<div class="stat-sub">{{ $sub }}</div>@endif
    </div>
@endif
