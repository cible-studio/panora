{{-- Logo CIBLE (régie) + mention "opéré par Panora".
     2026-06-18 (feedback patronne) : le logo CIBLE devient le branding par
     défaut sur tous les PDFs partagés (rapport réseau, taxes, piges,
     sélection, panneaux). Fallback Panora si CIBLE absent (cohérent avec
     le comportement antérieur). Variables injectées par AppServiceProvider. --}}
@if(!empty($logoCibleLight))
    <img src="{{ $logoCibleLight }}" alt="CIBLE CI" style="height:30px;display:block;margin-bottom:4px;">
@elseif(!empty($logoPanoraLight))
    <img src="{{ $logoPanoraLight }}" alt="Panora" style="height:30px;display:block;margin-bottom:4px;">
@else
    <div class="logo">{{ $operatorName ?? 'CIBLE CI' }}</div>
@endif
<div class="logo-sub" style="font-size:9px;color:#8a90a2;margin-top:2px;">
    {{ $operatorName ?? 'CIBLE CI' }} <span style="color:#6b7280;">· opéré par <strong style="color:#fff;">Panora</strong></span>
</div>
