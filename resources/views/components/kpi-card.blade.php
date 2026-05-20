{{--
    Composant KPI Card — design unique pour toute l'application.
    Réplique exacte du design KPI de la page Rapports.

    Props :
      - color      (string)         : couleur accent (hex ou var()) — ex "#22c55e" ou "var(--accent)"
      - value      (string|int)     : grande valeur affichée (ex "2%", "356", "12.8M")
      - label      (string)         : libellé en majuscules (ex "TAUX D'OCCUPATION")
      - sub        (string|null)    : sous-titre discret (ex "8 panneaux occupés")
      - icon       (string|null)    : SVG HTML brut (ex '<svg ...>...</svg>') — sera teint en `color`
      - href       (string|null)    : si fourni, la carte devient un lien cliquable
      - onclick    (string|null)    : si fourni (sans href), la carte devient un button cliquable

    Usage :
      <x-kpi-card
          color="#22c55e"
          value="356"
          label="Panneaux disponibles"
          sub="sur 364 au total"
          :icon="$icon"
          href="{{ route('admin.panels.index', ['status'=>'libre']) }}"
      />
--}}

@props([
    'color' => 'var(--accent)',
    'value' => '',
    'label' => '',
    'sub' => null,
    'icon' => null,
    'href' => null,
    'onclick' => null,
])

@php
    $isLink   = $href !== null;
    $isButton = !$isLink && $onclick !== null;
    $tag      = $isLink ? 'a' : ($isButton ? 'button' : 'div');
    $clickable = $isLink || $isButton;
@endphp

<{{ $tag }}
    @if($isLink) href="{{ $href }}"
    @elseif($isButton) type="button" onclick="{{ $onclick }}"
    @endif
    class="kpi-card"
    style="--kpi-color: {{ $color }};"
    @if($clickable)
        onmouseenter="this.style.borderColor='{{ $color }}';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,.12)'"
        onmouseleave="this.style.borderColor='';this.style.transform='';this.style.boxShadow=''"
    @endif
>
    <div class="kpi-card__top-bar" style="background:{{ $color }}"></div>

    @if($icon)
        <div class="kpi-card__icon" style="color:{{ $color }}">{!! $icon !!}</div>
    @endif

    <div class="kpi-card__value" style="color:{{ $color }}">{{ $value }}</div>
    <div class="kpi-card__label">{{ $label }}</div>
    @if($sub)
        <div class="kpi-card__sub">{{ $sub }}</div>
    @endif

    @if($clickable)
        <div class="kpi-card__arrow" style="color:{{ $color }}">→</div>
    @endif
</{{ $tag }}>
