<x-admin-layout>
<x-slot name="title">Modifier — {{ $technicien->name }}</x-slot>

<x-slot:topbarLeft>
    <a href="{{ route('admin.pose-tasks.techniciens.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour aux techniciens
    </a>
</x-slot:topbarLeft>

<div style="max-width:680px;margin:0 auto">

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 22px;margin-bottom:18px;display:flex;align-items:center;gap:14px">
        <div style="width:44px;height:44px;border-radius:12px;background:rgba(232,160,32,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:22px">🔧</div>
        <div>
            <div style="font-size:16px;font-weight:800;color:var(--text);margin-bottom:3px">Modifier {{ $technicien->name }}</div>
            <div style="font-size:12px;color:var(--text3);line-height:1.4">
                Lien public : <code style="background:var(--surface2);padding:2px 6px;border-radius:4px;font-size:11px">{{ url('/tech/' . $technicien->tech_public_token . '/poses') }}</code>
            </div>
        </div>
    </div>

    @if($errors->any())
    <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:10px;padding:14px 16px;margin-bottom:16px">
        @foreach($errors->all() as $error)
            <div style="color:#ef4444;font-size:13px;display:flex;gap:6px;align-items:flex-start;margin-bottom:3px">
                <span>⚠️</span><span>{{ $error }}</span>
            </div>
        @endforeach
    </div>
    @endif

    <div class="card">
        <div class="card-header">
            <div class="card-title">✏️ Informations</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.pose-tasks.techniciens.update', $technicien) }}">
                @csrf
                @method('PUT')

                <div class="mfg">
                    <label>Nom complet <span style="color:var(--red)">*</span></label>
                    <input type="text" name="name" required maxlength="100"
                           value="{{ old('name', $technicien->name) }}">
                </div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>WhatsApp</label>
                        <input type="text" name="whatsapp_number" maxlength="20"
                               value="{{ old('whatsapp_number', $technicien->whatsapp_number) }}"
                               placeholder="07 07 07 07 07">
                    </div>
                    <div class="mfg">
                        <label>Code agent</label>
                        <input type="text" name="agent_code" maxlength="50"
                               value="{{ old('agent_code', $technicien->agent_code) }}">
                    </div>
                </div>

                <div class="mfg">
                    <label>Email</label>
                    <input type="email" name="email" maxlength="100"
                           value="{{ old('email', str_starts_with($technicien->email, 'tech_') ? '' : $technicien->email) }}"
                           placeholder="optionnel">
                </div>

                <div class="mfg">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" name="is_active" value="1" {{ $technicien->is_active ? 'checked' : '' }}>
                        <span>Technicien actif (reçoit des poses)</span>
                    </label>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:14px;border-top:1px solid var(--border)">
                    <a href="{{ route('admin.pose-tasks.techniciens.index') }}" class="btn btn-ghost">Annuler</a>
                    <button type="submit" class="btn btn-primary">✅ Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

</x-admin-layout>
