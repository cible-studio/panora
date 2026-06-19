{{-- SM2b Phase 2 — Dashboard admin LIVE (A1).
     Vue principale : header + KPIs + bandeau events + liste techs.
     Hydratée par features/admin/live-dashboard.js (polling 20s sur
     admin.dashboard.live). --}}
<x-admin-layout>
    <x-slot name="title">Pilotage terrain</x-slot>

    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/admin/live-dashboard.css') }}?v={{ config('app.version', '1') }}">
        <link rel="stylesheet" href="{{ asset('css/admin/pige-validate.css') }}?v={{ config('app.version', '1') }}">
    @endpush

    <div class="live-dashboard" data-live-root>
        @include('admin.dashboard.partials._live_header')
        @include('admin.dashboard.partials._live_kpis')
        @include('admin.dashboard.partials._live_event_banner')
        @include('admin.dashboard.partials._live_techs_list')
    </div>

    @include('admin.dashboard.partials._modal_validate_photo')

    @push('scripts')
        <script>
            window.ADMIN_DASHBOARD_CONFIG = {
                endpoint: @json(route('admin.dashboard.live')),
                techDetailUrlTpl: @json(route('admin.pilotage.tech', ['user' => '__USER__'])),
                pollMs: 20000,
            };
            window.PIGE_VALIDATE_DETAIL_TPL = @json(route('admin.piges.detail-json', ['pige' => '__PIGE__']));
        </script>
        <script type="module" src="{{ asset('js/admin/live-dashboard.js') }}?v={{ config('app.version', '1') }}"></script>
        <script src="{{ asset('js/admin/pige-validate.js') }}?v={{ config('app.version', '1') }}"></script>
    @endpush
</x-admin-layout>
