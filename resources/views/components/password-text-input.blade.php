@props([
    'disabled' => false,
])

@php
    // ID stable indispensable pour relier le bouton œil à l'input.
    // Si l'appelant n'en passe pas, on en génère un unique pour ne pas
    // qu'un toggle révèle accidentellement le password d'un autre champ.
    $pwdId = $attributes->get('id') ?: 'pwdtxt_' . \Illuminate\Support\Str::random(6);
@endphp

<div class="relative">
    <input
        @disabled($disabled)
        type="password"
        id="{{ $pwdId }}"
        {{ $attributes->except(['id', 'type'])->merge([
            'class' => 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm pr-10',
        ]) }}
    >
    <button type="button"
            tabindex="-1"
            aria-label="Afficher ou masquer le mot de passe"
            title="Afficher / masquer"
            data-pwd-target="{{ $pwdId }}"
            onclick="(function(b){var i=document.getElementById(b.dataset.pwdTarget);if(!i)return;var s=i.type==='password';i.type=s?'text':'password';b.style.opacity=s?'1':'0.5';b.textContent=s?'🙈':'👁️';})(this)"
            class="absolute inset-y-0 right-2 flex items-center text-sm cursor-pointer bg-transparent border-0 px-2"
            style="opacity:0.5;">
        👁️
    </button>
</div>
