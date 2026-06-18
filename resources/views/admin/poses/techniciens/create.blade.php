<x-admin-layout>
<x-slot name="title">Nouveau technicien</x-slot>

<x-slot:topbarLeft>
    <a href="{{ route('admin.pose-tasks.techniciens.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour aux techniciens
    </a>
</x-slot:topbarLeft>

<div style="max-width:680px;margin:0 auto">

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 22px;margin-bottom:18px;display:flex;align-items:flex-start;gap:14px">
        <div style="width:44px;height:44px;border-radius:12px;background:rgba(232,160,32,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:22px">🔧</div>
        <div>
            <div style="font-size:16px;font-weight:800;color:var(--text);margin-bottom:3px">Ajouter un technicien</div>
            <div style="font-size:12.5px;color:var(--text3);line-height:1.55">
                Le technicien recevra ses tâches de pose par WhatsApp et accédera à son espace via un lien unique <code style="background:var(--surface2);padding:1px 5px;border-radius:4px;font-size:11px">/tech/{token}</code>.
                <strong>Aucun mot de passe nécessaire</strong> : il ne se connecte pas à Panora.
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
            <div class="card-title">➕ Nouveau technicien</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.pose-tasks.techniciens.store') }}">
                @csrf

                <div class="mfg">
                    <label>Nom complet <span style="color:var(--red)">*</span></label>
                    <input type="text" name="name" required maxlength="100"
                           value="{{ old('name') }}"
                           placeholder="Ex : KOUASSI Jean"
                           class="{{ $errors->has('name') ? 'error' : '' }}">
                </div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>WhatsApp <span style="font-size:11px;color:var(--text3);font-weight:400">— recommandé</span></label>
                        <input type="text" name="whatsapp_number" maxlength="20"
                               value="{{ old('whatsapp_number') }}"
                               placeholder="07 07 07 07 07"
                               class="{{ $errors->has('whatsapp_number') ? 'error' : '' }}">
                        <div style="font-size:11px;color:var(--text3);margin-top:4px">
                            Format CI : 10 chiffres. Sera converti automatiquement en +225 international.
                        </div>
                    </div>
                    <div class="mfg">
                        <label>Code agent <span style="font-size:11px;color:var(--text3);font-weight:400">— optionnel</span></label>
                        <input type="text" name="agent_code" maxlength="50"
                               value="{{ old('agent_code') }}"
                               placeholder="Ex : TECH-001"
                               class="{{ $errors->has('agent_code') ? 'error' : '' }}">
                    </div>
                </div>

                <div class="mfg">
                    <label>Email <span style="font-size:11px;color:var(--text3);font-weight:400">— optionnel</span></label>
                    <input type="email" name="email" maxlength="100"
                           value="{{ old('email') }}"
                           placeholder="kouassi@example.com"
                           class="{{ $errors->has('email') ? 'error' : '' }}">
                    <div style="font-size:11px;color:var(--text3);margin-top:4px">
                        Si vide, un email auto sera généré (jamais utilisé puisque le tech ne se connecte pas à l'app).
                    </div>
                </div>

                {{-- 2026-06-18 (feedback patronne) : sélecteur d'équipe à la
                     création. Bouton "+ Nouvelle équipe" avec smart-back pour
                     revenir ici après save. --}}
                <div class="mfg">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:5px">
                        <label style="margin:0">Équipe <span style="font-size:11px;color:var(--text3);font-weight:400">— optionnel</span></label>
                        <a href="{{ route('admin.teams.create', ['back' => 'posetasks']) }}"
                           style="background:none;border:1px dashed var(--accent);color:var(--accent);font-size:11px;font-weight:700;padding:1px 9px;border-radius:8px;text-decoration:none"
                           title="Créer une nouvelle équipe (puis retour sur ce formulaire)">+ Nouvelle équipe</a>
                    </div>
                    <select name="pose_team_id" class="{{ $errors->has('pose_team_id') ? 'error' : '' }}">
                        <option value="">— Aucune équipe —</option>
                        @foreach(($teams ?? collect()) as $team)
                            <option value="{{ $team->id }}" {{ (int) old('pose_team_id') === (int) $team->id ? 'selected' : '' }}>{{ $team->name }}</option>
                        @endforeach
                    </select>
                    <div style="font-size:11px;color:var(--text3);margin-top:4px">
                        L'équipe sera reportée automatiquement sur les nouvelles tâches de pose assignées à ce technicien.
                    </div>
                </div>

                <div style="background:rgba(59,130,246,.06);border:1px solid rgba(59,130,246,.2);border-radius:10px;padding:12px 14px;font-size:12.5px;color:var(--text2);line-height:1.5;margin-bottom:16px">
                    💡 <strong>Après création :</strong> un lien unique sera généré pour le technicien.
                    Vous pourrez le copier depuis la liste pour l'envoyer en WhatsApp/SMS.
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:14px;border-top:1px solid var(--border)">
                    <a href="{{ route('admin.pose-tasks.techniciens.index') }}" class="btn btn-ghost">Annuler</a>
                    <button type="submit" class="btn btn-primary">✅ Créer le technicien</button>
                </div>
            </form>
        </div>
    </div>
</div>

</x-admin-layout>
