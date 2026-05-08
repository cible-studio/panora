@props([
    // Active la complexité visuelle (toggle œil). Désactivable au cas par cas
    // (ex: champ "current_password" sur formulaire de suppression de compte
    // où l'utilisateur ne doit pas pouvoir révéler ce qu'il tape).
    'toggle' => true,
])

@php
    // ID stable nécessaire pour relier le bouton œil à l'input. Si l'appelant
    // n'en fournit pas, on en génère un unique pour éviter qu'un toggle ne
    // dévoile par erreur un autre champ password de la même page.
    $pwdId       = $attributes->get('id') ?: 'pwd_' . \Illuminate\Support\Str::random(6);
    $hasError    = $errors->has($attributes->get('name'));
    $callerStyle = trim((string) $attributes->get('style'));
    // On retire 'style' du merge pour reconstruire un style fusionné qui
    // garantit l'espace réservé au bouton œil (padding-right).
    $inputAttrs  = $attributes->except(['style', 'id', 'class']);
    $finalStyle  = ($toggle ? 'padding-right:40px;' : '') . $callerStyle;
    $finalClass  = trim(($attributes->get('class') ?? '') . ($hasError ? ' is-error error' : ''));
@endphp

<div style="position:relative;display:block;">
    <input
        type="password"
        id="{{ $pwdId }}"
        @class([$finalClass => $finalClass !== ''])
        @if($finalStyle) style="{{ $finalStyle }}" @endif
        {{ $inputAttrs }}
    >

    @if($toggle)
        <button type="button"
                aria-label="Afficher ou masquer le mot de passe"
                title="Afficher / masquer"
                tabindex="-1"
                data-pwd-target="{{ $pwdId }}"
                onclick="(function(b){var i=document.getElementById(b.dataset.pwdTarget);if(!i)return;var s=i.type==='password';i.type=s?'text':'password';b.style.opacity=s?'1':'0.45';b.textContent=s?'🙈':'👁️';})(this)"
                style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text3);font-size:14px;padding:0;line-height:1;opacity:0.45;">
            👁️
        </button>
    @endif
</div>
