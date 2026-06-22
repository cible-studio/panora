{{-- Drawer "Voir le détail" client+relances — refonte 2026-06-18.
     Retourné en HTML partiel via /admin/finance/relances/{relance}/detail.

     Affiche :
       - En-tête client (nom, contact)
       - Dette actuelle (snapshot live : total dû + nb factures ouvertes)
       - Liste verticale scrollable de TOUTES les relances du client
         dans l'ordre chronologique descendant. La relance demandée
         (cliquée depuis l'historique) est mise en évidence avec une
         bordure accent + ancre auto-scroll.

     Variables :
       $relance        → la relance demandée (mise en évidence)
       $clientRelances → toutes les relances du client (Collection)
       $totalDu        → dette actuelle du client (float, FCFA)
       $facturesOpen   → nb factures avec reste à payer
--}}
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
    $client = $relance->client;
@endphp

{{-- ════ En-tête CLIENT + DETTE ACTUELLE ════ --}}
<div class="rd-client-head" style="margin:-18px -18px 16px;padding:16px 18px;background:linear-gradient(135deg,rgba(232,160,32,.10),rgba(245,158,11,.04));border-bottom:1px solid var(--border)">
    <div style="display:flex;align-items:flex-start;gap:12px;flex-wrap:wrap">
        <div style="width:42px;height:42px;border-radius:10px;background:rgba(232,160,32,.18);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">👤</div>
        <div style="flex:1;min-width:180px">
            <div style="font-size:11px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.4px">Client</div>
            <div style="font-size:15px;font-weight:800;color:var(--text);margin-top:2px">
                @if($client)
                    <a href="{{ route('admin.clients.show', $client) }}" class="rd-link" style="text-decoration:none">{{ $client->name }}</a>
                @else
                    <span class="rd-muted">—</span>
                @endif
            </div>
            @if($client && ($client->phone || $client->email))
                <div style="font-size:11.5px;color:var(--text3);margin-top:4px;display:flex;gap:10px;flex-wrap:wrap">
                    @if($client->phone)<span>📞 {{ $client->phone }}</span>@endif
                    @if($client->email)<span>✉ {{ $client->email }}</span>@endif
                </div>
            @endif
        </div>
        <div style="text-align:right;min-width:140px">
            <div style="font-size:11px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.4px">Dette actuelle</div>
            @if($totalDu > 0)
                <div style="font-size:18px;font-weight:800;color:#b45309;font-family:ui-monospace,monospace;margin-top:2px">{{ $fmt($totalDu) }} <span style="font-size:10px;font-family:inherit;color:var(--text3)">FCFA</span></div>
                <div style="font-size:10.5px;color:var(--text3);margin-top:2px">{{ $facturesOpen }} facture(s) ouverte(s)</div>
                @if(($brouillonsCount ?? 0) > 0)
                    <div style="font-size:10.5px;color:#6b7280;margin-top:3px;font-style:italic" title="Facture(s) en cours de saisie, pas encore envoyée(s)">
                        📝 + {{ $brouillonsCount }} brouillon{{ $brouillonsCount > 1 ? 's' : '' }} ({{ $fmt($brouillonsTotal) }})
                    </div>
                @endif
            @elseif(($brouillonsCount ?? 0) > 0)
                {{-- Hotfix 2026-06-22 : alignement avec la liste — un client
                     avec brouillon en cours n'est pas "soldé" même s'il
                     n'a aucune facture ENVOYÉE non payée. --}}
                <div style="font-size:14px;font-weight:800;color:#6b7280;margin-top:4px" title="Facture(s) en cours de saisie, pas encore envoyée(s)">
                    📝 {{ $brouillonsCount }} brouillon{{ $brouillonsCount > 1 ? 's' : '' }}
                </div>
                <div style="font-size:10.5px;color:var(--text3);margin-top:2px;font-family:ui-monospace,monospace">{{ $fmt($brouillonsTotal) }} FCFA en cours</div>
            @else
                <div style="font-size:14px;font-weight:800;color:#15803d;margin-top:4px">✓ Soldé</div>
                <div style="font-size:10.5px;color:var(--text3);margin-top:2px">aucune facture ouverte</div>
            @endif
        </div>
    </div>
</div>

{{-- ════ Compteur ════ --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;gap:10px;flex-wrap:wrap">
    <div style="font-size:13px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:6px">
        📋 Toutes les relances
        <span style="background:rgba(232,160,32,.18);color:#9a3412;padding:2px 9px;border-radius:999px;font-size:11.5px;font-weight:800;font-family:ui-monospace,monospace">×{{ $clientRelances->count() }}</span>
    </div>
    @if($client)
        <div style="display:flex;gap:6px;flex-wrap:wrap">
            {{-- 2026-06-19 — PDF dédié à ce client : route existante
                 finance.relances.export.pdf filtrée par client_id. --}}
            @if(\Illuminate\Support\Facades\Route::has('admin.finance.relances.export.pdf'))
                <a href="{{ route('admin.finance.relances.export.pdf', ['client_id' => $client->id]) }}"
                   class="btn btn-ghost btn-sm" target="_blank"
                   style="font-size:11px;font-weight:700;background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.25);color:#b91c1c"
                   title="Télécharger en PDF tout l'historique des relances de {{ $client->name }}">
                    📄 PDF relances
                </a>
            @endif
            <button type="button" class="btn btn-primary btn-sm"
                    style="font-size:11px;font-weight:700"
                    onclick="relancesOpenModal({{ $client->id }})"
                    title="Enregistrer une nouvelle relance pour ce client">
                📞 Nouvelle relance
            </button>
        </div>
    @endif
</div>

{{-- ════ LISTE SCROLLABLE des relances ════ --}}
<div class="rd-relances-list" id="rd-relances-list">
@foreach($clientRelances as $r)
    @php
        $oCfg = $outcomeCfg[$r->outcome] ?? null;
        $isCurrent = $r->id === $relance->id;
    @endphp
    <div class="rd-relance-card {{ $isCurrent ? 'is-current' : '' }}" @if($isCurrent) id="rd-current-relance" @endif>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap">
            <div style="display:flex;align-items:center;gap:8px">
                <span class="rd-meta-label">Relance #{{ $r->id }}</span>
                @if($isCurrent)
                    <span style="font-size:10px;background:var(--accent);color:#fff;padding:1px 7px;border-radius:999px;font-weight:800;letter-spacing:.3px">SÉLECTIONNÉE</span>
                @endif
            </div>
            <span class="rd-meta-date">{{ $r->relance_date->format('d/m/Y') }}</span>
        </div>

        @if($oCfg)
            <div class="rd-outcome" style="background:{{ $oCfg['bg'] }};color:{{ $oCfg['c'] }};margin-top:6px">{{ $oCfg['l'] }}</div>
        @else
            <div class="rd-outcome" style="background:var(--surface2);color:var(--text3);font-style:italic;margin-top:6px">— Résultat non renseigné</div>
        @endif

        <div class="rd-relance-grid">
            <div>
                <div class="rd-field-label">Canal</div>
                <div class="rd-field-value">{{ \App\Services\ReminderService::canalLabel($r->canal) }}</div>
            </div>
            <div>
                <div class="rd-field-label">Auteur</div>
                <div class="rd-field-value">{{ $r->user?->name ?? '—' }}</div>
            </div>
            <div>
                <div class="rd-field-label">Enregistrée</div>
                <div class="rd-field-value rd-muted">{{ $r->created_at?->format('d/m/Y H:i') ?? '—' }}</div>
            </div>
            <div>
                <div class="rd-field-label">Facture concernée</div>
                <div class="rd-field-value">
                    @if($r->invoice)
                        <a href="{{ route('admin.invoices.show', $r->invoice) }}" class="rd-link" style="font-family:monospace;font-weight:700">{{ $r->invoice->reference }}</a>
                        @if($r->schedule)
                            <div style="font-size:10.5px;color:var(--text3);margin-top:2px;font-weight:500">
                                🎯 Échéance {{ $r->schedule->due_date?->format('d/m/Y') ?? '—' }} · {{ $fmt($r->schedule->amount) }} FCFA
                            </div>
                        @endif
                    @else
                        <span class="rd-muted" style="font-style:italic;font-size:11.5px">— Relance globale</span>
                    @endif
                </div>
            </div>
        </div>

        @if($r->note)
            <div class="rd-mini-block">
                <div class="rd-mini-block-title">📝 Note</div>
                <div class="rd-text">{{ $r->note }}</div>
            </div>
        @endif

        @if($r->suite_donnee)
            <div class="rd-mini-block" style="background:rgba(59,130,246,.06);border-color:rgba(59,130,246,.20)">
                <div class="rd-mini-block-title" style="color:#1d4ed8">💬 Suite à donner</div>
                <div class="rd-text">{{ $r->suite_donnee }}</div>
            </div>
        @endif
    </div>
@endforeach
</div>

<style>
.rd-relances-list { display:flex; flex-direction:column; gap:12px; }
.rd-relance-card {
    padding:12px 14px;
    background:var(--surface, #fff);
    border:1px solid var(--border);
    border-radius:10px;
    transition:border-color .15s, box-shadow .15s;
}
.rd-relance-card.is-current {
    border-color:var(--accent);
    box-shadow:0 0 0 3px rgba(232,160,32,.15);
    background:rgba(232,160,32,.04);
}
.rd-relance-grid {
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:8px 14px;
    margin-top:10px;
}
.rd-relance-grid > div { min-width:0; }
.rd-mini-block {
    margin-top:8px;
    padding:8px 10px;
    background:var(--surface2, #f8fafc);
    border:1px solid var(--border);
    border-radius:8px;
}
.rd-mini-block-title {
    font-size:10px;
    font-weight:800;
    text-transform:uppercase;
    color:var(--text2);
    letter-spacing:.4px;
    margin-bottom:4px;
}
</style>

<script>
// Auto-scroll vers la relance sélectionnée si elle n'est pas la 1re.
// On utilise requestAnimationFrame pour laisser le drawer se peindre avant.
requestAnimationFrame(() => {
    const cur = document.getElementById('rd-current-relance');
    if (cur && cur.previousElementSibling) {
        cur.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
</script>
