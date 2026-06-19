{{-- SM2b Phase 6 — Vue équipe LIVE (A5). Cards membres + stats globales.
     Variables :
       - $team (PoseTeam) résolu par route model binding {poseTeam}. --}}
<x-admin-layout>
    <x-slot name="title">Équipe {{ $team->name }} — Live</x-slot>

    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/admin/live-dashboard.css') }}?v={{ config('app.version', '1') }}">
        <link rel="stylesheet" href="{{ asset('css/admin/team-live.css') }}?v={{ config('app.version', '1') }}">
    @endpush

    <div class="live-dashboard" data-team-live data-team-id="{{ $team->id }}">

        <header class="team-live-head" style="background:{{ $team->colorBgHex() }};border-color:{{ $team->colorHex() }}">
            <a href="{{ route('admin.pilotage') }}" class="tech-live-back">← Retour pilotage</a>
            <div class="team-live-icon" style="background:{{ $team->colorHex() }}">👥</div>
            <div class="team-live-meta">
                <h1 class="team-live-name">{{ $team->name }}</h1>
                <div class="team-live-sub">
                    {{ $team->members->count() }} technicien(s)
                    @if($team->leader_user_id)
                        · Chef : {{ optional($team->members->firstWhere('id', $team->leader_user_id))->name ?? '—' }}
                    @endif
                </div>
            </div>
        </header>

        <section class="team-live-stats">
            <div class="team-live-stat"><div class="team-live-stat-value" data-stat="done">0</div><div class="team-live-stat-label">Faites</div></div>
            <div class="team-live-stat"><div class="team-live-stat-value" data-stat="total">0</div><div class="team-live-stat-label">Total</div></div>
            <div class="team-live-stat"><div class="team-live-stat-value" data-stat="online">0</div><div class="team-live-stat-label">En ligne</div></div>
            <div class="team-live-stat"><div class="team-live-stat-value" data-stat="rate">—</div><div class="team-live-stat-label">Pose/h moy.</div></div>
        </section>

        <h2 class="team-live-section-title">Membres</h2>
        <section class="team-live-grid" data-field="members-grid">
            @foreach($team->members->where('role.value', 'technique')->sortBy('name') as $member)
                <article class="team-live-card" data-member-id="{{ $member->id }}">
                    <header class="team-live-card-head">
                        <div class="team-live-card-avatar" style="background:{{ $team->colorHex() }}">
                            {{ mb_substr($member->name, 0, 2) }}
                        </div>
                        <div class="team-live-card-info">
                            <div class="team-live-card-name">{{ $member->name }}</div>
                            <div class="team-live-card-status" data-field="member-status">Chargement…</div>
                        </div>
                    </header>
                    <div class="team-live-card-progress">
                        <div class="team-live-card-progress-text" data-field="member-progress">0/0</div>
                        <div class="team-live-card-progress-bar">
                            <div class="team-live-card-progress-fill" data-field="member-progress-fill" style="width:0%"></div>
                        </div>
                    </div>
                    <a href="{{ route('admin.pilotage.tech', $member->id) }}" class="team-live-card-cta">
                        Voir la fiche →
                    </a>
                </article>
            @endforeach
        </section>
    </div>

    @push('scripts')
        <script>
            window.ADMIN_TEAM_LIVE_CONFIG = {
                teamId: {{ $team->id }},
                memberIds: @json($team->members->where('role.value', 'technique')->pluck('id')->values()),
                dashboardEndpoint: @json(route('admin.dashboard.live')),
                pollMs: 20000,
            };
        </script>
        <script src="{{ asset('js/admin/team-live.js') }}?v={{ config('app.version', '1') }}"></script>
    @endpush
</x-admin-layout>
