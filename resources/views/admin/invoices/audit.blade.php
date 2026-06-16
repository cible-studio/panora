<x-admin-layout>
<x-slot name="title">Audit {{ $invoice->reference }}</x-slot>

<x-slot:topbarLeft>
    <a href="{{ route('admin.invoices.show', $invoice) }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Retour à la facture
    </a>
</x-slot:topbarLeft>

@php
    $fmt = fn($n) => is_numeric($n) ? number_format((float) $n, 0, ',', ' ') : (string) $n;

    // Fusionne TOUTES les sources en une timeline unique chronologique.
    // Pour chaque modif, on calcule un diff HUMAIN (cf. InvoiceDiffHumanizer)
    // au lieu d'afficher le JSON brut illisible.
    $events = collect();

    foreach ($invoice->statusHistory as $h) {
        $events->push([
            'at'    => $h->created_at,
            'type'  => 'status',
            'icon'  => '🔄',
            'title' => 'Changement de statut',
            'summary' => \App\Models\Invoice::statusLabel($h->from_status) . ' → ' . \App\Models\Invoice::statusLabel($h->to_status),
            'user'  => $h->user?->name,
            'auto'  => $h->auto,
            'reason'=> $h->reason,
            'diff'  => null,
        ]);
    }

    foreach ($invoice->audits as $a) {
        $lines = \App\Services\InvoiceDiffHumanizer::humanize(
            (array) ($a->old_values ?? []),
            (array) ($a->new_values ?? []),
            'invoice'
        );
        // Le changement de statut est déjà couvert par statusHistory →
        // on retire la ligne 'status' pour éviter le doublon.
        $lines = array_values(array_filter($lines, fn($l) => $l['field'] !== 'status'));
        if (empty($lines)) {
            continue; // saut silencieux si aucun champ humain affichable
        }
        $events->push([
            'at'    => $a->created_at,
            'type'  => 'invoice_change',
            'icon'  => '📝',
            'title' => match($a->event) {
                'created' => 'Création de la facture',
                'deleted' => 'Suppression de la facture',
                default   => 'Modification de la facture',
            },
            'summary' => count($lines) === 1
                ? "1 champ modifié"
                : count($lines) . ' champs modifiés',
            'lines' => $lines,
            'user'  => $a->user?->name ?? 'Système',
            'auto'  => false,
            'reason'=> null,
            'diff_raw'  => ['old' => $a->old_values, 'new' => $a->new_values],
        ]);
    }

    foreach ($invoice->payments as $p) {
        foreach ($p->audits as $a) {
            $events->push([
                'at'    => $a->created_at,
                'type'  => 'payment',
                'icon'  => '💸',
                'title' => match($a->event) {
                    'created' => 'Versement enregistré',
                    'deleted' => 'Versement supprimé',
                    default   => 'Versement modifié',
                },
                'summary' => $fmt($p->montant) . ' FCFA · ' . ($p->mode ?? '—'),
                'user'  => $a->user?->name ?? 'Système',
                'auto'  => false,
                'reason'=> $p->note,
                'diff_raw'  => ['old' => $a->old_values, 'new' => $a->new_values],
            ]);
        }
    }

    foreach ($invoice->schedules as $s) {
        foreach ($s->audits as $a) {
            $events->push([
                'at'    => $a->created_at,
                'type'  => 'schedule',
                'icon'  => '📅',
                'title' => match($a->event) {
                    'created' => 'Échéance créée',
                    'deleted' => 'Échéance supprimée',
                    default   => 'Échéance modifiée',
                },
                'summary' => ($s->label ?? 'Jalon') . ' · ' . $fmt($s->amount) . ' FCFA · échue le ' . $s->due_date?->format('d/m/Y'),
                'user'  => $a->user?->name ?? 'Système',
                'auto'  => false,
                'reason'=> null,
                'diff_raw'  => ['old' => $a->old_values, 'new' => $a->new_values],
            ]);
        }
    }

    $events = $events->sortByDesc('at')->values();
@endphp

<div style="max-width:980px;margin:0 auto">

    <div style="font-size:12px;color:var(--text3);margin-bottom:12px">
        <a href="{{ route('admin.invoices.index') }}" style="color:var(--text3);text-decoration:none">Facturation</a>
        <span style="margin:0 6px">›</span>
        <a href="{{ route('admin.invoices.show', $invoice) }}" style="color:var(--text3);text-decoration:none">{{ $invoice->reference }}</a>
        <span style="margin:0 6px">›</span>
        <span style="color:var(--text)">Audit</span>
    </div>

    <div style="background:linear-gradient(135deg,rgba(59,130,246,.06) 0%,rgba(99,102,241,.05) 100%);border:1px solid var(--border);border-radius:14px;padding:18px 22px;margin-bottom:14px;display:flex;align-items:center;gap:14px">
        <div style="width:44px;height:44px;border-radius:12px;background:rgba(59,130,246,.14);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:20px">📋</div>
        <div>
            <div style="font-size:16px;font-weight:800;color:var(--text)">Audit complet · {{ $invoice->reference }}</div>
            <div style="font-size:12px;color:var(--text3);margin-top:2px;line-height:1.5">
                Historique chronologique en français — chaque ligne explique ce qui a changé.
                <strong>{{ $events->count() }}</strong> événement{{ $events->count() > 1 ? 's' : '' }}.
            </div>
        </div>
    </div>

    {{-- Timeline humaine --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 22px">
        @if($events->isEmpty())
            <div style="padding:30px;text-align:center;color:var(--text3)">Aucun événement d'audit enregistré.</div>
        @else
            <div style="position:relative;padding-left:22px">
                <div style="position:absolute;top:6px;bottom:6px;left:9px;width:2px;background:var(--border)"></div>
                @foreach($events as $ev)
                    <div style="position:relative;padding:12px 14px 14px;margin-bottom:10px;background:var(--surface2);border-radius:10px">
                        <div style="position:absolute;left:-21px;top:14px;width:20px;height:20px;border-radius:50%;background:var(--surface);border:2px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:11px">{{ $ev['icon'] }}</div>

                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap">
                            <div style="flex:1;min-width:200px">
                                <div style="font-size:13px;font-weight:700;color:var(--text)">{{ $ev['title'] }}</div>
                                @if(!empty($ev['summary']))
                                    <div style="font-size:12px;color:var(--text2);margin-top:2px;font-weight:500">{{ $ev['summary'] }}</div>
                                @endif
                                <div style="font-size:11px;color:var(--text3);margin-top:4px">
                                    {{ $ev['at']?->format('d/m/Y à H:i') }} ·
                                    @if($ev['auto'])
                                        <span style="background:rgba(99,102,241,.12);color:#4338ca;padding:1px 6px;border-radius:6px;font-size:9.5px;font-weight:700">SYSTÈME</span>
                                    @else
                                        par <strong>{{ $ev['user'] ?? 'Inconnu' }}</strong>
                                    @endif
                                </div>
                                @if(!empty($ev['reason']))
                                    <div style="font-size:11.5px;color:var(--text2);margin-top:6px;padding:6px 10px;background:rgba(232,160,32,.06);border-left:3px solid var(--accent);border-radius:5px">
                                        💬 {{ $ev['reason'] }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Lignes humaines (modif facture) --}}
                        @if(!empty($ev['lines']))
                            <div style="margin-top:10px;padding:10px 12px;background:var(--surface);border:1px solid var(--border);border-radius:8px">
                                @foreach($ev['lines'] as $line)
                                    <div style="font-size:12px;line-height:1.6;color:var(--text2);padding:3px 0;{{ !$loop->last ? 'border-bottom:1px dashed var(--border)' : '' }}">
                                        <strong style="color:var(--text);font-weight:600">{{ $line['label'] }}</strong> :
                                        <span style="color:#b91c1c;text-decoration:line-through">{{ $line['before'] }}</span>
                                        →
                                        <span style="color:#15803d;font-weight:600">{{ $line['after'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Détails techniques repliés (diff JSON brut) — pour devs uniquement --}}
                        @if(!empty($ev['diff_raw']) && (!empty($ev['diff_raw']['old']) || !empty($ev['diff_raw']['new'])))
                            <details style="margin-top:8px">
                                <summary style="cursor:pointer;font-size:10.5px;color:var(--text3);font-weight:600;user-select:none">⚙ Détails techniques (JSON brut)</summary>
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px;font-size:10.5px">
                                    <div style="padding:8px 10px;background:rgba(239,68,68,.04);border:1px solid rgba(239,68,68,.15);border-radius:7px">
                                        <div style="font-weight:700;color:#b91c1c;margin-bottom:4px;font-size:10px">AVANT</div>
                                        <pre style="margin:0;font-family:ui-monospace,monospace;font-size:10px;white-space:pre-wrap;color:var(--text3);max-height:200px;overflow:auto">{{ json_encode($ev['diff_raw']['old'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </div>
                                    <div style="padding:8px 10px;background:rgba(34,197,94,.04);border:1px solid rgba(34,197,94,.15);border-radius:7px">
                                        <div style="font-weight:700;color:#15803d;margin-bottom:4px;font-size:10px">APRÈS</div>
                                        <pre style="margin:0;font-family:ui-monospace,monospace;font-size:10px;white-space:pre-wrap;color:var(--text3);max-height:200px;overflow:auto">{{ json_encode($ev['diff_raw']['new'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </div>
                                </div>
                            </details>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>

</x-admin-layout>
