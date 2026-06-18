{{-- Drawer "Voir le détail" d'une relance — Bloc 3 Famille D (2026-06-18).
     Retourné en HTML partiel via /admin/finance/relances/{relance}/detail.
     Le wrapper drawer (overlay + slide-in) vit dans _relance_detail_drawer.blade.php. --}}
@php
    $fmt = fn($n) => number_format((float) $n, 0, ',', ' ');
    $outcomeCfg = [
        'promesse_paiement' => ['bg' => 'rgba(34,197,94,.12)',  'c' => '#15803d', 'l' => '✅ Promesse de paiement'],
        'paiement_recu'     => ['bg' => 'rgba(34,197,94,.18)',  'c' => '#15803d', 'l' => '💰 Paiement reçu'],
        'a_relancer'        => ['bg' => 'rgba(245,158,11,.14)', 'c' => '#b45309', 'l' => '🔁 À relancer'],
        'sans_reponse'      => ['bg' => 'rgba(107,114,128,.12)','c' => '#4b5563', 'l' => '📵 Sans réponse'],
        'desaccord'         => ['bg' => 'rgba(239,68,68,.10)',  'c' => '#b91c1c', 'l' => '⚠ Désaccord'],
        'autre'             => ['bg' => 'rgba(99,102,241,.10)', 'c' => '#4338ca', 'l' => '📝 Autre'],
    ];
    $oCfg = $outcomeCfg[$relance->outcome] ?? null;
@endphp

<div class="rd-section">
    <div class="rd-meta">
        <span class="rd-meta-label">Relance #{{ $relance->id }}</span>
        <span class="rd-meta-date">{{ $relance->relance_date->format('d/m/Y') }}</span>
    </div>

    @if($oCfg)
        <div class="rd-outcome" style="background:{{ $oCfg['bg'] }};color:{{ $oCfg['c'] }}">
            {{ $oCfg['l'] }}
        </div>
    @else
        <div class="rd-outcome" style="background:var(--surface2);color:var(--text3);font-style:italic">— Résultat non renseigné</div>
    @endif
</div>

{{-- Bloc Client / Facture --}}
<div class="rd-grid">
    <div class="rd-field">
        <div class="rd-field-label">Client</div>
        <div class="rd-field-value">
            @if($relance->client)
                <a href="{{ route('admin.clients.show', $relance->client) }}" class="rd-link">{{ $relance->client->name }}</a>
            @else
                <span class="rd-muted">—</span>
            @endif
        </div>
    </div>

    <div class="rd-field">
        <div class="rd-field-label">Canal</div>
        <div class="rd-field-value">{{ \App\Services\ReminderService::canalLabel($relance->canal) }}</div>
    </div>

    <div class="rd-field">
        <div class="rd-field-label">Auteur</div>
        <div class="rd-field-value">{{ $relance->user?->name ?? '—' }}</div>
    </div>

    <div class="rd-field">
        <div class="rd-field-label">Enregistrée</div>
        <div class="rd-field-value rd-muted">{{ $relance->created_at?->format('d/m/Y H:i') ?? '—' }}</div>
    </div>
</div>

{{-- Facture liée (si présente) --}}
@if($relance->invoice)
    @php $inv = $relance->invoice; @endphp
    <div class="rd-block">
        <div class="rd-block-title">📄 Facture concernée</div>
        <div class="rd-grid">
            <div class="rd-field">
                <div class="rd-field-label">Référence</div>
                <div class="rd-field-value">
                    <a href="{{ route('admin.invoices.show', $inv) }}" class="rd-link" style="font-family:monospace;font-weight:700">{{ $inv->reference }}</a>
                </div>
            </div>
            <div class="rd-field">
                <div class="rd-field-label">Émise le</div>
                <div class="rd-field-value">{{ $inv->issued_at?->format('d/m/Y') ?? '—' }}</div>
            </div>
            <div class="rd-field">
                <div class="rd-field-label">Total</div>
                <div class="rd-field-value">{{ $fmt($inv->total_a_payer) }} FCFA</div>
            </div>
            <div class="rd-field">
                <div class="rd-field-label">Reste à payer</div>
                <div class="rd-field-value" style="font-weight:800;color:{{ $inv->remainingAmount() > 0 ? '#ef4444' : '#15803d' }}">
                    {{ $fmt($inv->remainingAmount()) }} FCFA
                </div>
            </div>
        </div>

        @if($relance->schedule)
            <div style="margin-top:10px;padding:10px 12px;background:var(--surface2);border-radius:8px;font-size:12px;color:var(--text2)">
                🎯 Cible : échéance du
                <strong>{{ $relance->schedule->due_date?->format('d/m/Y') ?? '—' }}</strong>
                — montant : <strong>{{ $fmt($relance->schedule->amount) }} FCFA</strong>
                @if($relance->schedule->paid_at)
                    <span style="color:#15803d;margin-left:6px">✓ payée le {{ \Carbon\Carbon::parse($relance->schedule->paid_at)->format('d/m/Y') }}</span>
                @endif
            </div>
        @endif
    </div>
@else
    <div class="rd-block">
        <div class="rd-block-title">📄 Facture concernée</div>
        <div class="rd-muted" style="font-style:italic">Relance globale — non rattachée à une facture précise.</div>
    </div>
@endif

{{-- Note (observations) --}}
<div class="rd-block">
    <div class="rd-block-title">📝 Note (observations)</div>
    @if($relance->note)
        <div class="rd-text">{{ $relance->note }}</div>
    @else
        <div class="rd-muted" style="font-style:italic">Aucune note.</div>
    @endif
</div>

{{-- Suite à donner --}}
<div class="rd-block">
    <div class="rd-block-title">💬 Suite à donner</div>
    @if($relance->suite_donnee)
        <div class="rd-text">{{ $relance->suite_donnee }}</div>
    @else
        <div class="rd-muted" style="font-style:italic">—</div>
    @endif
</div>
