{{-- Logo Panora + mention "opéré par {operator}".
     Utilisable dans tous les PDFs (data-URI injectée par AppServiceProvider). --}}
@if(!empty($logoPanoraLight))
    <img src="{{ $logoPanoraLight }}" alt="Panora" style="height:30px;display:block;margin-bottom:4px;">
@else
    <div class="logo">Panora</div>
@endif
<div class="logo-sub" style="font-size:9px;color:#8a90a2;margin-top:2px;">
    opéré par <strong style="color:#fff;">{{ $operatorName ?? 'CIBLE CI' }}</strong>
</div>
