{{-- SM2b Phase 3 — Fiche tech LIVE (A2).
     Header + 4 KPIs personnels + card "EN CE MOMENT" + frise chronologique
     + actions rapides (Appeler / WhatsApp / Localiser).
     Hydratée par features/admin/tech-live.js (polling 20s sur
     admin.tech.timeline + admin.dashboard.live pour les KPIs perso).

     Variables Blade :
       - $tech (User) — résolu par route model binding {user}. --}}
<x-admin-layout>
    <x-slot name="title">{{ $tech->name }} — Live</x-slot>

    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/admin/live-dashboard.css') }}?v={{ config('app.version', '1') }}">
        <link rel="stylesheet" href="{{ asset('css/admin/tech-live.css') }}?v={{ config('app.version', '1') }}">
    @endpush

    <div class="live-dashboard" data-tech-live data-tech-id="{{ $tech->id }}">

        {{-- ════ Header tech + retour + statut Live ════ --}}
        <header class="tech-live-head">
            <a href="{{ route('admin.pilotage') }}" class="tech-live-back" aria-label="Retour pilotage">← Retour</a>
            <div class="tech-live-avatar">{{ mb_substr($tech->name, 0, 2) }}</div>
            <div class="tech-live-meta">
                <h1 class="tech-live-name">{{ $tech->name }}</h1>
                <div class="tech-live-substatus">
                    <span class="live-status-pulse" aria-hidden="true"></span>
                    <span data-field="tech-status">Chargement…</span>
                </div>
            </div>
            <div class="tech-live-actions-quick">
                @if($tech->whatsapp_number)
                    <a href="tel:{{ $tech->whatsapp_number }}" class="tech-live-action tech-live-action--call"
                       title="Appeler {{ $tech->name }}">📞</a>
                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $tech->whatsapp_number) }}"
                       target="_blank" rel="noopener"
                       class="tech-live-action tech-live-action--wa"
                       title="WhatsApp">💬</a>
                @endif
                <a href="{{ route('admin.map.live') }}?focus={{ $tech->id }}"
                   class="tech-live-action tech-live-action--map"
                   title="Localiser sur la carte">📍</a>
            </div>
        </header>

        {{-- ════ 4 KPIs personnels ════ --}}
        <section class="tech-live-kpis">
            <div class="tech-live-kpi">
                <div class="tech-live-kpi-value" data-kpi="done">—</div>
                <div class="tech-live-kpi-label">Faites aujourd'hui</div>
            </div>
            <div class="tech-live-kpi">
                <div class="tech-live-kpi-value" data-kpi="in_progress">—</div>
                <div class="tech-live-kpi-label">En cours</div>
            </div>
            <div class="tech-live-kpi">
                <div class="tech-live-kpi-value" data-kpi="remaining">—</div>
                <div class="tech-live-kpi-label">Restant</div>
            </div>
            <div class="tech-live-kpi">
                <div class="tech-live-kpi-value" data-kpi="problems">—</div>
                <div class="tech-live-kpi-label">Signalements</div>
            </div>
        </section>

        {{-- ════ Card "EN CE MOMENT" — orange ════ --}}
        <section class="tech-live-current" data-field="current-card" hidden>
            <div class="tech-live-current-label">EN CE MOMENT</div>
            <div class="tech-live-current-body">
                <span class="tech-live-current-icon" data-field="current-icon">📷</span>
                <div class="tech-live-current-text">
                    <div class="tech-live-current-title" data-field="current-title">—</div>
                    <div class="tech-live-current-sub" data-field="current-sub">—</div>
                </div>
            </div>
        </section>

        {{-- ════ Timeline chronologique ════ --}}
        <section class="tech-live-timeline">
            <h2 class="tech-live-timeline-title">📅 Activité du jour</h2>
            <div class="tech-live-timeline-empty" data-field="timeline-empty">
                <div class="tech-live-timeline-empty-icon" aria-hidden="true">⏳</div>
                <div>Aucune activité enregistrée aujourd'hui.</div>
            </div>
            <ol class="tech-live-timeline-list" data-field="timeline-list" hidden>
                <template data-field="timeline-row-tpl">
                    <li class="tech-live-event">
                        <div class="tech-live-event-dot" data-field="event-dot"></div>
                        <div class="tech-live-event-body">
                            <div class="tech-live-event-head">
                                <strong data-field="event-label">—</strong>
                                <span data-field="event-time">—</span>
                            </div>
                            <div class="tech-live-event-meta">
                                <span data-field="event-subject">—</span>
                                <span data-field="event-location" hidden></span>
                            </div>
                            <div class="tech-live-event-extra" data-field="event-extra" hidden></div>
                        </div>
                    </li>
                </template>
            </ol>
        </section>

    </div>

    @push('scripts')
        <script>
            window.ADMIN_TECH_LIVE_CONFIG = {
                techId: {{ $tech->id }},
                timelineEndpoint: @json(route('admin.tech.timeline', ['user' => $tech->id])),
                dashboardEndpoint: @json(route('admin.dashboard.live')),
                pollMs: 20000,
            };
        </script>
        <script type="module" src="{{ asset('js/admin/tech-live.js') }}?v={{ config('app.version', '1') }}"></script>
    @endpush
</x-admin-layout>
