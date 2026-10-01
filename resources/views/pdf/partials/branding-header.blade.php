{{-- Logo CIBLE (régie) + mention "opéré par Panora".
     2026-06-18 (feedback patronne) : le logo CIBLE devient le branding par
     défaut sur tous les PDFs partagés (rapport réseau, taxes, piges,
     sélection, panneaux). Fallback Panora si CIBLE absent (cohérent avec
     le comportement antérieur). Variables injectées par AppServiceProvider.
     2026-10-01 — couleurs issues de la charte ($charte, config/charte.php).
     Les PDF actifs utilisent désormais pdf.partials.charte-header ; ce
     partiel ne sert plus qu'aux vues pdf/* apparemment inutilisées (cf.
     docs/TECHNICAL_DEBT.md), d'où un changement limité aux couleurs. --}}
@php($chB = $charte ?? \App\Support\PdfCharte::data())
@if(!empty($logoCibleLight))
    <img src="{{ $logoCibleLight }}" alt="CIBLE CI" style="height:30px;display:block;margin-bottom:4px;">
@elseif(!empty($logoPanoraLight))
    <img src="{{ $logoPanoraLight }}" alt="Panora" style="height:30px;display:block;margin-bottom:4px;">
@else
    <div class="logo">{{ $operatorName ?? 'CIBLE CI' }}</div>
@endif
<div class="logo-sub" style="font-size:9px;color:{{ $chB['texte_pale'] }};margin-top:2px;">
    {{ $operatorName ?? 'CIBLE CI' }} <span style="color:{{ $chB['texte_pale'] }};">· opéré par <strong style="color:{{ $chB['blanc'] }};">Panora</strong></span>
</div>
