{{--
    Lot 12.4 — Badge statut unifié pour l'espace client.

    Convention 4 couleurs (cohérente sur toutes les pages client) :
      🟠 ATTENTE (orange #fab80b) : en_attente, planifie, option
      🟢 ACTIF   (vert  #22c55e) : actif, confirme, pose
      🔵 TERMINÉ (bleu  #3b82f6) : termine
      🔴 ARRÊT   (rouge #ef4444) : annule, refuse

    Le statut admin "occupe" est traité comme actif côté client (le client
    ne voit pas la nuance "occupé internement" vs "actif").

    Utilisation :
      @include('client.partials._status-badge', ['status' => $obj->status->value])
      @include('client.partials._status-badge', ['status' => 'actif', 'size' => 'sm'])

    Variables :
      $status (required) — valeur enum brute
      $size   (optional) — 'sm' (default) ou 'lg'
--}}
@php
    $statusKey = match($status ?? null) {
        'en_attente', 'planifie', 'option' => 'attente',
        'actif', 'confirme', 'pose', 'occupe' => 'actif',
        'termine'                            => 'termine',
        'annule', 'refuse', 'rejete'         => 'arret',
        default                              => 'autre',
    };

    $palette = [
        'attente' => ['bg'=>'rgba(250,184,11,.12)',  'color'=>'#c2570d', 'bd'=>'rgba(250,184,11,.3)'],
        'actif'   => ['bg'=>'rgba(34,197,94,.12)',   'color'=>'#15803d', 'bd'=>'rgba(34,197,94,.3)'],
        'termine' => ['bg'=>'rgba(59,130,246,.12)',  'color'=>'#1d4ed8', 'bd'=>'rgba(59,130,246,.3)'],
        'arret'   => ['bg'=>'rgba(239,68,68,.12)',   'color'=>'#b91c1c', 'bd'=>'rgba(239,68,68,.3)'],
        'autre'   => ['bg'=>'rgba(148,163,184,.1)',  'color'=>'#64748b', 'bd'=>'rgba(148,163,184,.25)'],
    ];

    $labels = [
        'en_attente' => 'En attente',
        'planifie'   => 'Planifiée',
        'option'     => 'Option',
        'actif'      => 'Active',
        'confirme'   => 'Confirmée',
        'pose'       => 'En pose',
        'occupe'     => 'Active',
        'termine'    => 'Terminée',
        'annule'     => 'Annulée',
        'refuse'     => 'Refusée',
        'rejete'     => 'Refusée',
    ];
    $label = $labels[$status ?? ''] ?? ucfirst($status ?? '—');
    $cfg = $palette[$statusKey];

    $sizeStyle = ($size ?? 'sm') === 'lg'
        ? 'padding:5px 14px;font-size:12px;'
        : 'padding:3px 10px;font-size:10.5px;';
@endphp
<span style="display:inline-block;{{ $sizeStyle }}border-radius:14px;background:{{ $cfg['bg'] }};color:{{ $cfg['color'] }};border:1px solid {{ $cfg['bd'] }};font-weight:700;letter-spacing:.3px;">
    {{ $label }}
</span>
