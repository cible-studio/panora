{{--

  USAGE dans show.blade.php — ajouter où tu veux les actions :
  @include('admin.reservations.partials.proposition-actions', ['reservation' => $reservation])

  Ce partial gère :
  - Bouton "Envoyer proposition" (si en_attente + email client)
  - Statut de la proposition (envoyée, vue, expirée)
  - Bouton "Renvoyer" / "Réinitialiser"
  - Lien de copie du lien client
--}}

@if($reservation->status->value === 'en_attente')
<div style="background:rgba(232,160,32,0.04);border:1px solid rgba(232,160,32,0.15);border-radius:12px;padding:20px 24px;margin:20px 0;">

    {{-- Titre --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px">
        <div>
            <div style="font-size:13px;font-weight:600;color:#e8a020;display:flex;align-items:center;gap:6px">
                📋 Proposition Commerciale
            </div>
            <div style="font-size:12px;color:#64748b;margin-top:2px">
                Envoyez un lien sécurisé au client pour qu'il confirme ou refuse.
            </div>
        </div>

        @if($reservation->proposition_token)
            @if($reservation->proposition_expires_at?->isPast())
                <span style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);color:#fca5a5;border-radius:20px;padding:3px 12px;font-size:11px;font-weight:600">⏰ Expirée</span>
            @elseif($reservation->proposition_viewed_at)
                <span style="background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.25);color:#86efac;border-radius:20px;padding:3px 12px;font-size:11px;font-weight:600">👁️ Vue par le client</span>
            @else
                <span style="background:rgba(59,130,246,0.1);border:1px solid rgba(59,130,246,0.25);color:#93c5fd;border-radius:20px;padding:3px 12px;font-size:11px;font-weight:600">📤 Envoyée · En attente</span>
            @endif
        @endif
    </div>

    {{-- Infos si proposition active --}}
    @if($reservation->proposition_token && !$reservation->proposition_expires_at?->isPast())
    <div style="background:rgba(0,0,0,0.15);border-radius:8px;padding:14px 16px;margin-bottom:16px;font-size:12px;color:#94a3b8;display:flex;flex-wrap:wrap;gap:16px">
        <div>
            <span style="color:#64748b">Envoyée le</span>
            <strong style="color:#e2e8f0;margin-left:6px">{{ $reservation->proposition_sent_at?->format('d/m/Y à H:i') ?? '—' }}</strong>
        </div>
        @if($reservation->proposition_viewed_at)
        <div>
            <span style="color:#64748b">Vue le</span>
            <strong style="color:#86efac;margin-left:6px">{{ $reservation->proposition_viewed_at->format('d/m/Y à H:i') }}</strong>
        </div>
        @endif
        @if($reservation->proposition_expires_at)
        <div>
            <span style="color:#64748b">Expire le</span>
            <strong style="color:{{ $reservation->proposition_expires_at->diffInHours() < 24 ? '#fca5a5' : '#e2e8f0' }};margin-left:6px">
                {{ $reservation->proposition_expires_at->format('d/m/Y') }}
                ({{ $reservation->proposition_expires_at->diffForHumans() }})
            </strong>
        </div>
        @endif

        {{-- Lien client — nouvelle URL lisible --}}
        @if($reservation->proposition_slug)
        @php
            $propUrl = route('proposition.show', [
                $reservation->reference,
                $reservation->proposition_slug
            ]);
        @endphp
        <div style="width:100%;margin-top:4px">
            <span style="color:#64748b">Lien client</span>
            <div style="display:flex;align-items:center;gap:8px;margin-top:4px">
                <input type="text"
                       value="{{ $propUrl }}"
                       readonly
                       id="prop-link-{{ $reservation->id }}"
                       style="flex:1;background:rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.08);border-radius:6px;padding:6px 10px;font-size:11px;font-family:monospace;color:#94a3b8;min-width:0">
                <button type="button"
                        onclick="copyLink('prop-link-{{ $reservation->id }}', this)"
                        style="background:rgba(232,160,32,0.1);border:1px solid rgba(232,160,32,0.2);color:#e8a020;border-radius:6px;padding:6px 12px;font-size:11px;cursor:pointer;white-space:nowrap">
                    📋 Copier
                </button>
                <a href="{{ $propUrl }}" target="_blank"
                   style="background:rgba(59,130,246,0.1);border:1px solid rgba(59,130,246,0.2);color:#93c5fd;border-radius:6px;padding:6px 12px;font-size:11px;text-decoration:none;white-space:nowrap">
                    👁️ Voir
                </a>
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- Alerte pas d'email --}}
    @if(empty($reservation->client?->email))
    <div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:12px;color:#fca5a5;display:flex;align-items:center;gap:8px">
        ⚠️ Le client <strong>{{ $reservation->client?->name }}</strong> n'a pas d'email.
        <a href="{{ route('admin.clients.edit', $reservation->client_id) }}" style="color:#fca5a5;text-decoration:underline;margin-left:4px">Mettre à jour →</a>
    </div>
    @endif

    {{-- Actions --}}
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        @if(!empty($reservation->client?->email))
        <form method="POST"
              action="{{ route('admin.reservations.proposition.envoyer', $reservation) }}"
              onsubmit="return confirm('{{ $reservation->proposition_sent_at ? 'Renvoyer la proposition ?' : 'Envoyer la proposition ?' }}')">
            @csrf
            <button type="submit"
                    style="background:#e8a020;color:#0b0e17;font-weight:700;font-size:13px;padding:9px 20px;border-radius:8px;border:none;cursor:pointer">
                📧 {{ $reservation->proposition_sent_at ? 'Renvoyer' : 'Envoyer la proposition' }}
            </button>
        </form>
        @endif

        @if($reservation->proposition_token)
        <form method="POST"
              action="{{ route('admin.reservations.proposition.reinitialiser', $reservation) }}"
              onsubmit="return confirm('Réinitialiser ? Le lien client ne fonctionnera plus.')">
            @csrf
            <button type="submit"
                    style="background:transparent;color:#94a3b8;font-size:12px;padding:9px 16px;border-radius:8px;border:1px solid rgba(255,255,255,0.1);cursor:pointer">
                🔄 Réinitialiser
            </button>
        </form>
        @endif

        @if(!empty($reservation->client?->email))
        <span style="font-size:11px;color:#64748b">→ {{ $reservation->client->email }}</span>
        @endif
    </div>
</div>
@endif

<script>
function copyLink(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    navigator.clipboard.writeText(input.value).then(() => {
        const orig = btn.textContent;
        btn.textContent = '✅ Copié !';
        btn.style.color = '#86efac';
        setTimeout(() => { btn.textContent = orig; btn.style.color = '#e8a020'; }, 2000);
    }).catch(() => { input.select(); document.execCommand('copy'); });
}
</script>