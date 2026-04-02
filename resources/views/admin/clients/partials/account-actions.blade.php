@php $hasAccount = $client->hasAccount(); @endphp
 
<div style="background:rgba(59,130,246,0.04);border:1px solid rgba(59,130,246,0.15);border-radius:12px;padding:18px 20px;margin:20px 0">
  <div style="font-size:13px;font-weight:600;color:#93c5fd;margin-bottom:4px">🏢 Espace Client</div>
  <div style="font-size:12px;color:#64748b;margin-bottom:14px">Gérer l'accès de ce client à son espace personnel.</div>
 
  @if($hasAccount)
    {{-- Compte actif --}}
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;font-size:12px;color:#94a3b8">
      <span style="width:8px;height:8px;border-radius:50%;background:#22c55e;display:inline-block"></span>
      Compte actif
      @if($client->last_login_at)
        · Dernière connexion {{ $client->last_login_at->diffForHumans() }}
      @endif
      @if($client->must_change_password)
        · <span style="color:#fde68a">⚠️ Doit changer son MDP</span>
      @endif
    </div>
 
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <form method="POST" action="{{ route('admin.clients.account.reset', $client) }}"
            onsubmit="return confirm('Réinitialiser le mot de passe de {{ $client->name }} ?')">
        @csrf
        <button type="submit" style="background:rgba(59,130,246,0.1);color:#93c5fd;border:1px solid rgba(59,130,246,0.25);border-radius:8px;padding:8px 16px;font-size:12px;cursor:pointer">
          🔑 Réinitialiser le MDP
        </button>
      </form>
 
      <form method="DELETE" action="{{ route('admin.clients.account.revoke', $client) }}"
            onsubmit="return confirm('Révoquer l\'accès espace client de {{ $client->name }} ?')">
        @csrf
        @method('DELETE')
        <button type="submit" style="background:rgba(239,68,68,0.08);color:#fca5a5;border:1px solid rgba(239,68,68,0.2);border-radius:8px;padding:8px 16px;font-size:12px;cursor:pointer">
          🚫 Révoquer l'accès
        </button>
      </form>
    </div>
  @else
    {{-- Pas de compte --}}
    <div style="font-size:12px;color:#64748b;margin-bottom:14px">
      @if(empty($client->email))
        ⚠️ Ajoutez un email au client pour créer son compte.
      @else
        Ce client n'a pas encore d'accès espace client.
      @endif
    </div>
 
    @if(!empty($client->email))
    <form method="POST" action="{{ route('admin.clients.account.create', $client) }}"
          onsubmit="return confirm('Créer un compte espace client pour {{ $client->name }} ({{ $client->email }}) ?')">
      @csrf
      <button type="submit" style="background:#e8a020;color:#0a0d14;border:none;border-radius:8px;padding:9px 20px;font-size:13px;font-weight:700;cursor:pointer">
        ✅ Créer l'accès espace client
      </button>
    </form>
    @endif
  @endif
</div>