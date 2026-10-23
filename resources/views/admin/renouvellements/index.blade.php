<x-admin-layout>
    <x-slot name="title">Renouvellements</x-slot>

    {{--
      Renouvellements (refonte 2026-10-23) : trois onglets — campagnes
      annuelles (séquence J-60 → J-7), autres campagnes (mail J-7 simple),
      journal des envois. Les explications sont dans le guide (panneau) et
      les info-bulles, pas affichées en bloc.
      Cf. RenouvellementController::index, RenouvellementService.
    --}}
    @include('admin.renouvellements._styles')

    @php
        $base = array_filter(['horizon' => $horizon ?: null, 'q' => $q ?: null, 'commercial' => $commercialId], fn($v) => $v !== null && $v !== '');
        $lien = fn(array $p = []) => route('admin.renouvellements.index', array_filter(array_merge($base, $p), fn($v) => $v !== null && $v !== '' && $v !== 0));
    @endphp

    <div x-data="{ dossier: {{ $ouvrir ?: 'null' }}, guide: false }" @keydown.escape.window="dossier = null; guide = false">

        {{-- ═══ EN-TÊTE ═══ --}}
        <div class="rn-card rn-head">
            <div>
                <h1 class="rn-title">🔁 Renouvellements</h1>
                <div class="rn-sub">Fins de campagne et intentions de renouvellement de vos clients.</div>
            </div>
            <div class="rn-actions">
                @if($modeTest)
                    <span class="rn-chip test" data-tip="Mode test : les mails partent uniquement vers la liste Brevo « Tests internes ». Aucun client ne reçoit rien. Passage en production : RELANCE_FIN_MODE=production.">🧪 Mode test</span>
                @else
                    <span class="rn-chip prod" data-tip="Les mails partent aux clients.">● Production</span>
                @endif
                <button type="button" class="rn-btn" @click="guide = true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    Guide
                </button>
                @if($estAdmin)
                    <a href="{{ route('admin.renouvellements.modeles') }}" class="rn-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                        Textes des mails
                    </a>
                @endif
            </div>
        </div>

        {{-- ═══ ONGLETS ═══ --}}
        <nav class="rn-tabs">
            <a href="{{ $lien(['onglet' => null]) }}" class="rn-tab {{ $onglet === 'annuelles' ? 'on' : '' }}">Campagnes annuelles<span class="n">{{ $nbAnnuelles }}</span></a>
            <a href="{{ $lien(['onglet' => 'courtes']) }}" class="rn-tab {{ $onglet === 'courtes' ? 'on' : '' }}"
               data-tip="Campagnes de moins de 12 mois : un seul mail, 7 jours avant la fin.">Autres campagnes · mail J-7<span class="n">{{ $courtes->count() }}</span></a>
            <a href="{{ $lien(['onglet' => 'journal']) }}" class="rn-tab {{ $onglet === 'journal' ? 'on' : '' }}">Journal des envois</a>
        </nav>

        @if($onglet === 'annuelles')
            @include('admin.renouvellements._annuelles')
        @elseif($onglet === 'courtes')
            @include('admin.renouvellements._courtes')
        @else
            @include('admin.renouvellements._journal')
        @endif

        {{-- ═══ FICHES DOSSIER (panneau latéral) ═══ --}}
        @if($onglet === 'annuelles')
            @foreach($lignes as $l)
                @include('admin.renouvellements._dossier', ['l' => $l])
            @endforeach
        @endif

        {{-- ═══ GUIDE ═══ --}}
        @include('admin.renouvellements._guide')
    </div>
</x-admin-layout>
