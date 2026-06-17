<x-admin-layout>
<x-slot name="title">Nouvelle équipe</x-slot>

<x-slot:topbarLeft>
    <a href="{{ route('admin.teams.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Équipes
    </a>
</x-slot:topbarLeft>

<div style="max-width:680px;margin:0 auto">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:24px 28px">
        <div style="font-size:18px;font-weight:800;color:var(--text);margin-bottom:4px">Nouvelle équipe de pose</div>
        <div style="font-size:12px;color:var(--text3);margin-bottom:20px">Crée une équipe pour regrouper plusieurs techniciens terrain.</div>

        @if(session('error'))
            <div style="padding:10px 14px;background:rgba(239,68,68,.10);border-left:4px solid #dc2626;border-radius:8px;margin-bottom:14px;font-size:13px;color:#b91c1c">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.teams.store') }}" class="teams-form">
            @csrf
            @include('admin.teams._form', ['team' => new \App\Models\PoseTeam()])
            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:18px;padding-top:14px;border-top:1px solid var(--border)">
                <a href="{{ route('admin.teams.index') }}" class="btn btn-ghost">Annuler</a>
                <button type="submit" class="btn btn-primary">Créer l'équipe</button>
            </div>
        </form>
    </div>
</div>

<style>
.teams-form .fne-field { margin-bottom: 14px; }
.teams-form label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--text2); margin-bottom: 6px; }
.teams-form label .req { color: #ef4444; }
.teams-form input[type="text"], .teams-form select, .teams-form textarea {
    width: 100%; padding: 8px 10px; height: 38px;
    border: 1px solid var(--border); border-radius: 8px;
    font-size: 13px; background: var(--surface); color: var(--text);
    font-family: inherit; outline: none; box-sizing: border-box;
}
.teams-form textarea { height: auto; min-height: 60px; resize: vertical; line-height: 1.5; }
.teams-form select { cursor: pointer; padding-right: 28px; -webkit-appearance:none; appearance:none;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
    background-repeat: no-repeat; background-position: right 8px center;
}
.teams-form input:focus, .teams-form select:focus, .teams-form textarea:focus {
    border-color: var(--accent); box-shadow: 0 0 0 3px rgba(232, 160, 32, .15);
}
</style>

</x-admin-layout>
