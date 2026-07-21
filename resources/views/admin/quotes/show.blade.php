<x-admin-layout title="Devis {{ $quote->reference }}">
    <x-slot:topbarLeft>
        <a href="{{ route('admin.quotes.index') }}" class="btn btn-ghost btn-sm">← Retour aux devis</a>
    </x-slot:topbarLeft>
    <x-slot:topbarActions>
        <a href="{{ route('admin.quotes.pdf', $quote) }}" class="btn btn-ghost btn-sm">📄 <span class="btn-label">Télécharger PDF</span></a>
        @can('update', $quote)
            <a href="{{ route('admin.quotes.edit', $quote) }}" class="btn btn-ghost btn-sm">✏️ <span class="btn-label">Modifier</span></a>
        @endcan
        @can('send', $quote)
            <form method="POST" action="{{ route('admin.quotes.send', $quote) }}" style="display:inline" onsubmit="return confirm('Envoyer ce devis au client ?')">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">📤 Envoyer au client</button>
            </form>
        @endcan
        @can('duplicate', $quote)
            <form method="POST" action="{{ route('admin.quotes.duplicate', $quote) }}" style="display:inline">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm" title="Dupliquer">📋 <span class="btn-label">Dupliquer</span></button>
            </form>
        @endcan
    </x-slot:topbarActions>

    @php $st = $quote->status->uiConfig(); @endphp

    {{-- ═══════════════════ EN-TÊTE ═══════════════════ --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:22px;margin-bottom:18px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap">
            <div>
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px">
                    <span style="font-family:monospace;font-size:18px;font-weight:800;color:var(--accent)">{{ $quote->reference }}</span>
                    <span style="background:{{ $st['bg'] }};color:{{ $st['color'] }};border:1px solid {{ $st['border'] }};padding:4px 12px;border-radius:14px;font-size:12px;font-weight:700">
                        {{ $st['icon'] }} {{ $quote->status->label() }}
                    </span>
                    @if($quote->version > 1)
                        <span style="background:rgba(139,92,246,.10);color:#6d28d9;padding:3px 8px;border-radius:12px;font-size:11px;font-weight:700">v{{ $quote->version }}</span>
                    @endif
                </div>
                <h1 style="font-size:22px;font-weight:800;color:var(--text);margin-bottom:8px">{{ $quote->title }}</h1>
                <div style="font-size:13px;color:var(--text3)">
                    Créé par <strong>{{ $quote->creator?->name ?? '—' }}</strong> le {{ $quote->created_at->format('d/m/Y H:i') }}
                    · Commercial : <strong>{{ $quote->commercial?->name ?? '—' }}</strong>
                </div>
            </div>
            <div style="text-align:right">
                <div style="font-size:32px;font-weight:800;color:var(--text)">{{ number_format($quote->total_a_payer, 0, ',', ' ') }} <span style="font-size:14px;color:var(--text3)">FCFA</span></div>
                <div style="font-size:12px;color:var(--text3)">Total à payer TTC + taxes</div>
                @if($quote->status === \App\Enums\QuoteStatus::ENVOYE && $quote->expires_at)
                    @php $daysLeft = (int) now()->diffInDays($quote->expires_at, false); @endphp
                    <div style="margin-top:10px;padding:8px 12px;background:{{ $daysLeft < 3 ? 'rgba(180,83,9,.10)' : 'rgba(59,130,246,.08)' }};border-radius:8px;font-size:12px">
                        <strong>Expire le {{ $quote->expires_at->format('d/m/Y') }}</strong>
                        · @if($daysLeft < 0) ⌛ {{ abs($daysLeft) }}j dépassé @else J-{{ $daysLeft }} @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px">
        <div>
            {{-- ═══════════════════ LIGNES ═══════════════════ --}}
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:16px">
                <div style="padding:12px 16px;border-bottom:1px solid var(--border);font-weight:700">🪧 Panneaux proposés ({{ $quote->lines->count() }})</div>
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding:10px;font-size:11px;color:var(--text3);background:var(--surface2)">Désignation</th>
                            <th style="text-align:left;padding:10px;font-size:11px;color:var(--text3);background:var(--surface2)">Commune</th>
                            <th style="text-align:right;padding:10px;font-size:11px;color:var(--text3);background:var(--surface2)">m²</th>
                            <th style="text-align:right;padding:10px;font-size:11px;color:var(--text3);background:var(--surface2)">PU</th>
                            <th style="text-align:center;padding:10px;font-size:11px;color:var(--text3);background:var(--surface2)">Qté</th>
                            <th style="text-align:center;padding:10px;font-size:11px;color:var(--text3);background:var(--surface2)">Mois</th>
                            <th style="text-align:right;padding:10px;font-size:11px;color:var(--text3);background:var(--surface2)">Montant HT</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($quote->lines as $line)
                            <tr>
                                <td style="padding:10px">{{ $line->designation }}</td>
                                <td style="padding:10px;color:var(--text2);font-size:12.5px">{{ $line->snapshot_commune_name ?? '—' }}</td>
                                <td style="padding:10px;text-align:right">{{ number_format($line->dimension_m2, 2, ',', '') }}</td>
                                <td style="padding:10px;text-align:right">{{ number_format($line->pu_ht_mensuel, 0, ',', ' ') }}</td>
                                <td style="padding:10px;text-align:center">{{ $line->quantite }}</td>
                                <td style="padding:10px;text-align:center">{{ number_format($line->duree_mois, 1, ',', '') }}</td>
                                <td style="padding:10px;text-align:right;font-weight:700">{{ number_format($line->montant_ht_ligne, 0, ',', ' ') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($quote->services->count() > 0)
                <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:16px">
                    <div style="padding:12px 16px;border-bottom:1px solid var(--border);font-weight:700">🔧 Services annexes</div>
                    <table style="width:100%;border-collapse:collapse">
                        @foreach($quote->services as $svc)
                            <tr>
                                <td style="padding:10px">{{ $svc->label }}</td>
                                <td style="padding:10px;text-align:right;font-weight:700">{{ number_format($svc->prix_ht, 0, ',', ' ') }} FCFA</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endif

            @if($quote->notes_client)
                <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:16px">
                    <div style="font-weight:700;margin-bottom:6px">💬 Message client</div>
                    <div style="color:var(--text2);white-space:pre-wrap">{{ $quote->notes_client }}</div>
                </div>
            @endif

            @if($quote->notes_internes)
                <div style="background:#fef7e6;border:1px solid #f59e0b;border-radius:12px;padding:16px;margin-bottom:16px">
                    <div style="font-weight:700;margin-bottom:6px;color:#78350f">🔒 Notes internes (non envoyées au client)</div>
                    <div style="color:#78350f;white-space:pre-wrap">{{ $quote->notes_internes }}</div>
                </div>
            @endif
        </div>

        <div>
            {{-- Bloc client --}}
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:16px">
                <div style="font-weight:700;margin-bottom:10px;color:var(--text3);font-size:11px;text-transform:uppercase;letter-spacing:.5px">Client</div>
                <div style="font-size:15px;font-weight:700">{{ $quote->client?->name ?? '—' }}</div>
                @if($quote->client?->email)
                    <div style="font-size:12.5px;color:var(--text3);margin-top:4px">📧 {{ $quote->client->email }}</div>
                @endif
                @if($quote->client?->phone)
                    <div style="font-size:12.5px;color:var(--text3)">📞 {{ $quote->client->phone }}</div>
                @endif
                @if($quote->client?->ncc)
                    <div style="font-size:12.5px;color:var(--text3);margin-top:4px">NCC : {{ $quote->client->ncc }}</div>
                @endif
            </div>

            {{-- Bloc totaux --}}
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:16px">
                <div style="font-weight:700;margin-bottom:10px;color:var(--text3);font-size:11px;text-transform:uppercase;letter-spacing:.5px">Totaux</div>
                <table style="width:100%;font-size:13px">
                    <tr><td style="padding:4px 0;color:var(--text2)">Total HT panneaux</td><td style="text-align:right">{{ number_format($quote->amount, 0, ',', ' ') }}</td></tr>
                    @if($quote->remise_pct > 0)
                        <tr><td style="padding:4px 0;color:var(--text2)">Remise ({{ number_format($quote->remise_pct, 2, ',', '') }}%)</td><td style="text-align:right;color:#dc2626">- {{ number_format($quote->amount - $quote->net_ht, 0, ',', ' ') }}</td></tr>
                        <tr><td style="padding:4px 0;color:var(--text2)">Net HT panneaux</td><td style="text-align:right">{{ number_format($quote->net_ht, 0, ',', ' ') }}</td></tr>
                    @endif
                    @if($quote->services_ht_total > 0)
                        <tr><td style="padding:4px 0;color:var(--text2)">Services HT</td><td style="text-align:right">{{ number_format($quote->services_ht_total, 0, ',', ' ') }}</td></tr>
                    @endif
                    <tr><td style="padding:4px 0;color:var(--text2)">TVA ({{ (int) $quote->tva }}%)</td><td style="text-align:right">{{ number_format($quote->tva_amount + (int) round($quote->services_ht_total * ((float) $quote->tva / 100)), 0, ',', ' ') }}</td></tr>
                    <tr><td style="padding:4px 0;color:var(--text2)">TSP</td><td style="text-align:right">{{ number_format($quote->tsp_amount, 0, ',', ' ') }}</td></tr>
                    <tr><td style="padding:4px 0;color:var(--text2)">TM</td><td style="text-align:right">{{ number_format($quote->tm_total, 0, ',', ' ') }}</td></tr>
                    <tr><td style="padding:4px 0;color:var(--text2)">ODP</td><td style="text-align:right">{{ number_format($quote->odp_total, 0, ',', ' ') }}</td></tr>
                    <tr style="border-top:2px solid var(--border);background:var(--accent);color:#fff">
                        <td style="padding:8px 6px;font-weight:800">TOTAL À PAYER</td>
                        <td style="padding:8px 6px;text-align:right;font-weight:800;font-size:16px">{{ number_format($quote->total_a_payer, 0, ',', ' ') }}</td>
                    </tr>
                </table>
            </div>

            {{-- Bloc conversion --}}
            @if($quote->convertedReservation)
                <div style="background:#f0f9f0;border:1px solid #86e186;border-radius:12px;padding:16px">
                    <div style="font-weight:700;color:#15803d;margin-bottom:6px">✅ Converti en réservation</div>
                    <a href="{{ route('admin.reservations.show', $quote->convertedReservation) }}" style="color:#15803d;text-decoration:underline">Voir la réservation {{ $quote->convertedReservation->reference ?? '#' . $quote->convertedReservation->id }}</a>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
