<x-admin-layout>
<x-slot name="title">Message #{{ $message->id }} — {{ $message->from_name }}</x-slot>

<div style="margin-bottom:14px;">
    <a href="{{ route('admin.messages.index') }}" class="btn btn-ghost btn-sm">← Tous les messages</a>
</div>

@if(session('success'))
    <div class="flash flash-success" style="margin-bottom:16px;">✓ {{ session('success') }}</div>
@endif
@if(session('warning'))
    <div class="flash flash-warning" style="margin-bottom:16px;">⚠ {{ session('warning') }}</div>
@endif

@php
    $statusCfg = match($message->status) {
        'new'         => ['c'=>'#f59e0b', 'label'=>'Nouveau'],
        'in_progress' => ['c'=>'#3b82f6', 'label'=>'En cours'],
        'replied'     => ['c'=>'#22c55e', 'label'=>'Répondu'],
        'archived'    => ['c'=>'#6b7280', 'label'=>'Archivé'],
        default       => ['c'=>'#6b7280', 'label'=>$message->status],
    };
@endphp

<div style="display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:flex-start;">
    {{-- Corps du message + réponse --}}
    <div class="card" style="padding:24px;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;padding-bottom:18px;border-bottom:1px solid var(--border);">
            <span style="font-size:28px;">✉️</span>
            <div style="flex:1;">
                <div style="font-size:18px;font-weight:700;color:var(--text);line-height:1.2;">{{ $message->subject }}</div>
                <div style="font-size:11px;color:var(--text3);margin-top:4px;">Reçu {{ $message->created_at->format('d/m/Y à H:i') }} · #{{ $message->id }}</div>
            </div>
            <span style="padding:4px 12px;border-radius:20px;font-size:10px;font-weight:800;background:{{ $statusCfg['c'] }}1a;color:{{ $statusCfg['c'] }};text-transform:uppercase;letter-spacing:.4px;">{{ $statusCfg['label'] }}</span>
        </div>

        {{-- Message original (texte préservé) --}}
        <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:20px;">
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:8px;">Message du client</div>
            <div style="font-size:13px;color:var(--text);line-height:1.6;white-space:pre-wrap;">{{ $message->body }}</div>
        </div>

        {{-- Réponse existante --}}
        @if($message->reply_body)
            <div style="background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.25);border-radius:10px;padding:16px;margin-bottom:20px;">
                <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px;">
                    <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#22c55e;">↩ Réponse envoyée</span>
                    <span style="font-size:10px;color:var(--text3);">{{ $message->replied_at->format('d/m/Y à H:i') }} par {{ $message->replier?->name ?? '—' }}</span>
                </div>
                <div style="font-size:13px;color:var(--text);line-height:1.6;white-space:pre-wrap;">{{ $message->reply_body }}</div>
            </div>
        @endif

        {{-- Formulaire réponse --}}
        @if($message->status !== 'archived')
            <form method="POST" action="{{ route('admin.messages.reply', $message) }}">
                @csrf
                <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:8px;">
                    {{ $message->reply_body ? '✏️ Réécrire la réponse' : '↩ Répondre au client' }}
                    <span style="color:#ef4444;">*</span>
                </label>
                <textarea name="reply_body" rows="6" required minlength="5" maxlength="5000"
                          placeholder="Rédigez votre réponse — elle sera envoyée par email à {{ $message->from_email }}"
                          style="width:100%;padding:12px 14px;background:var(--surface);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text);resize:vertical;outline:none;font-family:inherit;box-sizing:border-box;">{{ old('reply_body', $message->reply_body ?? '') }}</textarea>
                @error('reply_body')<div style="font-size:11px;color:#ef4444;margin-top:6px;">{{ $message->getMessageBag()->first('reply_body') ?? $message }}</div>@enderror
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;">
                    <div style="font-size:11px;color:var(--text3);">
                        💡 La réponse partira à <strong>{{ $message->from_email }}</strong> avec votre signature équipe.
                    </div>
                    <button type="submit" class="btn btn-primary">📤 Envoyer la réponse</button>
                </div>
            </form>
        @else
            <div style="text-align:center;padding:24px;color:var(--text3);font-size:13px;">Ce message est archivé. Aucune réponse possible.</div>
        @endif
    </div>

    {{-- Side panel : client + actions --}}
    <div style="display:flex;flex-direction:column;gap:14px;">
        <div class="card" style="padding:18px;">
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:10px;">Expéditeur</div>
            <div style="font-size:14px;font-weight:700;color:var(--text);">{{ $message->from_name }}</div>
            <div style="font-size:11px;color:var(--text3);font-family:monospace;margin-top:2px;">{{ $message->from_email }}</div>
            @if($message->client)
                <a href="{{ route('admin.clients.show', $message->client) }}" class="btn btn-ghost btn-sm" style="margin-top:12px;width:100%;justify-content:center;">
                    👤 Voir la fiche client →
                </a>
            @endif
        </div>

        <div class="card" style="padding:18px;">
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:10px;">Historique</div>
            <ul style="list-style:none;padding:0;margin:0;font-size:12px;color:var(--text2);display:flex;flex-direction:column;gap:8px;">
                <li>📥 Reçu {{ $message->created_at->format('d/m à H:i') }}</li>
                @if($message->read_at)
                    <li>👁 Lu {{ $message->read_at->format('d/m à H:i') }}{{ $message->reader ? ' par '.$message->reader->name : '' }}</li>
                @endif
                @if($message->replied_at)
                    <li>↩ Répondu {{ $message->replied_at->format('d/m à H:i') }}{{ $message->replier ? ' par '.$message->replier->name : '' }}</li>
                @endif
            </ul>
        </div>

        @if($message->status !== 'archived')
            <form method="POST" action="{{ route('admin.messages.archive', $message) }}">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm" style="width:100%;justify-content:center;"
                        onclick="return confirm('Archiver ce message ? Il restera consultable mais ne pourra plus recevoir de réponse.')">
                    📁 Archiver
                </button>
            </form>
        @endif

        <form method="POST" action="{{ route('admin.messages.destroy', $message) }}">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-ghost btn-sm" style="width:100%;justify-content:center;color:#ef4444;"
                    onclick="return confirm('Supprimer définitivement ce message ? Cette action est irréversible.')">
                🗑 Supprimer
            </button>
        </form>
    </div>
</div>

</x-admin-layout>
