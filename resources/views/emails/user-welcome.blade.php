@php
    $title = match ($context) {
        'activated'   => 'Compte activé',
        'reactivated' => 'Compte réactivé',
        default       => 'Bienvenue',
    };
    $intro = match ($context) {
        'activated'   => 'Votre compte vient d\'être <strong>activé</strong> par un administrateur. Vous pouvez désormais vous connecter à la plateforme.',
        'reactivated' => 'Votre compte a été <strong>réactivé</strong>. Bon retour parmi nous !',
        default       => 'Un compte vient d\'être créé pour vous sur la plateforme <strong>CIBLE CI</strong>. Voici vos informations de connexion.',
    };
    $badgeClass = match ($context) {
        'activated', 'reactivated' => 'badge badge-success',
        default                    => 'badge badge-info',
    };
    $badgeLabel = match ($context) {
        'activated'   => '✅ Compte activé',
        'reactivated' => '🔓 Compte réactivé',
        default       => '👋 Nouveau compte',
    };
    $roleLabel = match ($user->role?->value ?? 'user') {
        'admin'        => '👑 Administrateur',
        'commercial'   => '💼 Commercial',
        'mediaplanner' => '📺 Mediaplanner',
        'comptable'    => '🧾 Comptable',
        default        => '👤 Utilisateur',
    };
@endphp

<x-mail.layout :title="$title">

    <span class="{{ $badgeClass }}">{{ $badgeLabel }}</span>

    <h1>Bonjour {{ $user->name }},</h1>
    <p>{!! $intro !!}</p>

    <div class="info-box">
        <div class="info-box-row">
            <div class="lbl">Nom</div>
            <div class="val">{{ $user->name }}</div>
        </div>
        <div class="info-box-row">
            <div class="lbl">Email de connexion</div>
            <div class="val"><span class="code">{{ $user->email }}</span></div>
        </div>
        <div class="info-box-row">
            <div class="lbl">Rôle</div>
            <div class="val">{{ $roleLabel }}</div>
        </div>
        @if($temporaryPassword)
            <div class="info-box-row">
                <div class="lbl">Mot de passe temporaire</div>
                <div class="val"><span class="code" style="background:rgba(232,160,32,0.15);">{{ $temporaryPassword }}</span></div>
            </div>
        @endif
    </div>

    @if($temporaryPassword)
        <p style="font-size:13px;color:#fbbf24;background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.2);padding:10px 14px;border-radius:8px;">
            🔐 <strong>Important</strong> : ce mot de passe est temporaire. Vous serez invité à le changer à votre première connexion.
        </p>
    @endif

    <div class="cta-wrap">
        <a href="{{ $loginUrl }}" class="cta-btn">Se connecter à CIBLE CI →</a>
        <div class="cta-sub">Plateforme de gestion · Régie Publicitaire OOH</div>
    </div>

    <p style="font-size:12px;color:#64748b;margin-top:24px;">
        Si vous n'attendiez pas cet email, ignorez-le simplement — aucune action n'est requise.
        Pour toute question, contactez votre administrateur.
    </p>

    <x-slot:footerNote>
        Email envoyé suite à la {{ $context === 'activated' ? 'activation' : ($context === 'reactivated' ? 'réactivation' : 'création') }} de votre compte.
    </x-slot:footerNote>

</x-mail.layout>
