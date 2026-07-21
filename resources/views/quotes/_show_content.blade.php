{{--
    Partial contenu d'un devis (utilisé par la vue client authentifiée
    ET la vue publique via token). Ne contient AUCUN wrapper de layout —
    à inclure depuis un layout parent.

    Variables :
      $quote     : Quote (chargé avec client, commercial, lines, services)
      $isPublic  : bool — true si vue publique (formulaires POST vers routes publiques)
--}}
@php $st = $quote->status->uiConfig(); @endphp

<style>
    .qw-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:22px; margin-bottom:16px; }
    .qw-badge { display:inline-block; padding:4px 12px; border-radius:14px; font-size:12px; font-weight:700; }
    .qw-btn { display:inline-flex; align-items:center; gap:8px; padding:12px 22px; border-radius:8px; font-weight:700; font-size:14px; border:none; cursor:pointer; text-decoration:none; }
    .qw-btn-accept { background:#15803d; color:#fff; }
    .qw-btn-accept:hover { background:#166534; }
    .qw-btn-refuse { background:#fff; color:#991b1b; border:1.5px solid #fca5a5; }
    .qw-btn-refuse:hover { background:#fef2f2; }
    .qw-btn-modif { background:#fff; color:#6d28d9; border:1.5px solid #c4b5fd; }
    .qw-btn-modif:hover { background:#f5f3ff; }
    .qw-btn-ghost { background:#fff; color:#475569; border:1.5px solid #cbd5e1; }
    .qw-btn-ghost:hover { background:#f8fafc; }
    .qw-table { width:100%; border-collapse:collapse; margin-top:10px; }
    .qw-table th { text-align:left; padding:8px 10px; background:#f8fafc; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; }
    .qw-table td { padding:8px 10px; border-bottom:1px solid #f1f5f9; font-size:13px; }
    .qw-totals td { padding:6px 10px; font-size:13.5px; }
    .qw-totals td.lbl { color:#475569; }
    .qw-totals td.val { text-align:right; font-weight:700; }
    .qw-totals tr.grand td { background:#8b5cf6; color:#fff; font-size:16px; padding:12px; font-weight:800; }
    @media (max-width: 720px) { .qw-card { padding:16px; } .qw-btn { width:100%; justify-content:center; } }
</style>

<div style="max-width:960px;margin:0 auto;padding:24px">

    {{-- ═══════════════════ EN-TÊTE ═══════════════════ --}}
    <div class="qw-card" style="border-top:4px solid #8b5cf6">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap">
            <div>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                    <span style="font-family:monospace;font-size:14px;color:#8b5cf6;font-weight:800">{{ $quote->reference }}</span>
                    <span class="qw-badge" style="background:{{ $st['bg'] }};color:{{ $st['color'] }}">{{ $st['icon'] }} {{ $quote->status->label() }}</span>
                </div>
                <h1 style="font-size:24px;font-weight:800;color:#0f172a;margin-bottom:8px">{{ $quote->title }}</h1>
                <div style="font-size:13px;color:#64748b">
                    Émis par <strong>{{ $quote->commercial?->name ?? 'CIBLE' }}</strong>
                    · Client : <strong>{{ $quote->client?->name }}</strong>
                    @if($quote->sent_at)
                        · Envoyé le <strong>{{ $quote->sent_at->format('d/m/Y') }}</strong>
                    @endif
                </div>
            </div>
            <div style="text-align:right">
                <div style="font-size:28px;font-weight:800;color:#0f172a">
                    {{ number_format($quote->total_a_payer, 0, ',', ' ') }} <span style="font-size:13px;color:#94a3b8">FCFA</span>
                </div>
                @if($quote->expires_at && $quote->status === \App\Enums\QuoteStatus::ENVOYE)
                    @php $daysLeft = (int) now()->diffInDays($quote->expires_at, false); @endphp
                    <div style="margin-top:8px;padding:6px 12px;border-radius:8px;font-size:12px;font-weight:600;
                                background:{{ $daysLeft < 3 ? '#fef3c7' : '#dbeafe' }};
                                color:{{ $daysLeft < 3 ? '#78350f' : '#1e40af' }}">
                        @if($daysLeft < 0)
                            ⌛ Expiré depuis {{ abs($daysLeft) }} jour(s)
                        @else
                            Valide jusqu'au {{ $quote->expires_at->format('d/m/Y') }} — J-{{ $daysLeft }}
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ═══════════════════ DÉCISION ═══════════════════ --}}
    @if($quote->status === \App\Enums\QuoteStatus::ENVOYE && !$quote->isExpired())
        <div class="qw-card" style="border-top:4px solid #f59e0b;background:#fffbeb">
            <div style="font-size:15px;font-weight:700;color:#78350f;margin-bottom:6px">🎯 Décision attendue</div>
            <p style="font-size:14px;color:#78350f;margin-bottom:14px">
                Consultez le détail ci-dessous puis choisissez :
            </p>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <button type="button" onclick="document.getElementById('qw-accept-form').style.display='block';document.getElementById('qw-refuse-form').style.display='none';document.getElementById('qw-modif-form').style.display='none'" class="qw-btn qw-btn-accept">
                    ✅ Accepter le devis
                </button>
                <button type="button" onclick="document.getElementById('qw-refuse-form').style.display='block';document.getElementById('qw-accept-form').style.display='none';document.getElementById('qw-modif-form').style.display='none'" class="qw-btn qw-btn-refuse">
                    ❌ Refuser
                </button>
                <button type="button" onclick="document.getElementById('qw-modif-form').style.display='block';document.getElementById('qw-accept-form').style.display='none';document.getElementById('qw-refuse-form').style.display='none'" class="qw-btn qw-btn-modif">
                    🔁 Demander une modification
                </button>
            </div>

            <div id="qw-accept-form" style="display:none;margin-top:16px;padding:16px;background:#fff;border-radius:8px;border:1px solid #86efac">
                <div style="font-weight:700;color:#166534;margin-bottom:8px">Confirmer l'acceptation ?</div>
                <p style="font-size:13px;color:#166534;margin-bottom:12px">
                    En acceptant, une réservation ferme sera créée automatiquement pour vous.
                    Votre commercial vous contactera pour la suite.
                </p>
                <form method="POST" action="{{ $isPublic ? route('public.quote.accept', $quote->public_token) : route('client.devis.accept', $quote) }}">
                    @csrf
                    <button type="submit" class="qw-btn qw-btn-accept">✅ Oui, j'accepte le devis</button>
                    <button type="button" onclick="this.closest('#qw-accept-form').style.display='none'" class="qw-btn qw-btn-ghost">Annuler</button>
                </form>
            </div>

            <div id="qw-refuse-form" style="display:none;margin-top:16px;padding:16px;background:#fff;border-radius:8px;border:1px solid #fca5a5">
                <div style="font-weight:700;color:#991b1b;margin-bottom:8px">Refuser ce devis</div>
                <form method="POST" action="{{ $isPublic ? route('public.quote.refuse', $quote->public_token) : route('client.devis.refuse', $quote) }}">
                    @csrf
                    <label style="font-size:12px;color:#475569;display:block;margin-bottom:4px">Motif (optionnel, pour aider le commercial)</label>
                    <textarea name="reason" rows="3" maxlength="1000" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;margin-bottom:12px" placeholder="ex. Prix trop élevé · Périodes ne conviennent pas · Projet reporté..."></textarea>
                    <button type="submit" class="qw-btn qw-btn-refuse">❌ Confirmer le refus</button>
                    <button type="button" onclick="this.closest('#qw-refuse-form').style.display='none'" class="qw-btn qw-btn-ghost">Annuler</button>
                </form>
            </div>

            @if(!$isPublic)
                <div id="qw-modif-form" style="display:none;margin-top:16px;padding:16px;background:#fff;border-radius:8px;border:1px solid #c4b5fd">
                    <div style="font-weight:700;color:#6d28d9;margin-bottom:8px">Demander une modification</div>
                    <form method="POST" action="{{ route('client.devis.request-modification', $quote) }}">
                        @csrf
                        <label style="font-size:12px;color:#475569;display:block;margin-bottom:4px">Précisez ce que vous souhaitez ajuster *</label>
                        <textarea name="reason" required rows="4" maxlength="1000" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;margin-bottom:12px" placeholder="ex. J'aimerais retirer les panneaux de Yopougon, ajouter 2 panneaux Cocody, et négocier le prix de X..."></textarea>
                        <button type="submit" class="qw-btn qw-btn-modif">🔁 Envoyer ma demande</button>
                        <button type="button" onclick="this.closest('#qw-modif-form').style.display='none'" class="qw-btn qw-btn-ghost">Annuler</button>
                    </form>
                </div>
            @endif
        </div>
    @endif

    {{-- ═══════════════════ MESSAGE COMMERCIAL ═══════════════════ --}}
    @if($quote->notes_client)
        <div class="qw-card" style="border-left:4px solid #8b5cf6">
            <div style="font-weight:700;color:#0f172a;margin-bottom:6px">💬 Message de votre commercial</div>
            <div style="color:#334155;white-space:pre-wrap;font-size:14px">{{ $quote->notes_client }}</div>
        </div>
    @endif

    {{-- ═══════════════════ LIGNES ═══════════════════ --}}
    <div class="qw-card">
        <div style="font-size:15px;font-weight:800;margin-bottom:10px">🪧 Panneaux proposés</div>
        <div style="overflow-x:auto">
            <table class="qw-table">
                <thead>
                    <tr>
                        <th>Désignation</th>
                        <th>Commune</th>
                        <th style="text-align:right">m²</th>
                        <th style="text-align:right">PU HT/mois</th>
                        <th style="text-align:center">Qté</th>
                        <th style="text-align:center">Mois</th>
                        <th style="text-align:right">Montant HT</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($quote->lines as $line)
                        <tr>
                            <td>{{ $line->designation }}</td>
                            <td style="color:#64748b">{{ $line->snapshot_commune_name ?? '—' }}</td>
                            <td style="text-align:right">{{ number_format($line->dimension_m2, 2, ',', '') }}</td>
                            <td style="text-align:right">{{ number_format($line->pu_ht_mensuel, 0, ',', ' ') }}</td>
                            <td style="text-align:center">{{ $line->quantite }}</td>
                            <td style="text-align:center">{{ number_format($line->duree_mois, 1, ',', '') }}</td>
                            <td style="text-align:right;font-weight:700">{{ number_format($line->montant_ht_ligne, 0, ',', ' ') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($quote->services->count() > 0)
        <div class="qw-card">
            <div style="font-size:15px;font-weight:800;margin-bottom:10px">🔧 Services annexes</div>
            <table class="qw-table">
                @foreach($quote->services as $svc)
                    <tr>
                        <td>{{ $svc->label }}</td>
                        <td style="text-align:right;font-weight:700">{{ number_format($svc->prix_ht, 0, ',', ' ') }} FCFA</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    {{-- ═══════════════════ TOTAUX ═══════════════════ --}}
    <div class="qw-card">
        <div style="font-size:15px;font-weight:800;margin-bottom:10px">💰 Totaux</div>
        <table class="qw-totals" style="width:100%">
            <tr><td class="lbl">Total HT panneaux</td><td class="val">{{ number_format($quote->amount, 0, ',', ' ') }}</td></tr>
            @if($quote->remise_pct > 0)
                <tr><td class="lbl">Remise ({{ number_format($quote->remise_pct, 2, ',', '') }}%)</td><td class="val" style="color:#dc2626">- {{ number_format($quote->amount - $quote->net_ht, 0, ',', ' ') }}</td></tr>
            @endif
            @if($quote->services_ht_total > 0)
                <tr><td class="lbl">Services HT</td><td class="val">{{ number_format($quote->services_ht_total, 0, ',', ' ') }}</td></tr>
            @endif
            <tr><td class="lbl">TVA ({{ (int) $quote->tva }}%)</td><td class="val">{{ number_format($quote->tva_amount + (int) round($quote->services_ht_total * ((float) $quote->tva / 100)), 0, ',', ' ') }}</td></tr>
            <tr><td class="lbl">Autres taxes (ODP, TM, TSP)</td><td class="val">{{ number_format($quote->tsp_amount + $quote->tm_total + $quote->odp_total, 0, ',', ' ') }}</td></tr>
            <tr class="grand"><td>TOTAL À PAYER</td><td class="val" style="text-align:right">{{ number_format($quote->total_a_payer, 0, ',', ' ') }} FCFA</td></tr>
        </table>
    </div>

    {{-- ═══════════════════ ACTIONS SECONDAIRES ═══════════════════ --}}
    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:20px">
        <a href="{{ $isPublic ? route('public.quote.pdf', $quote->public_token) : route('client.devis.pdf', $quote) }}" class="qw-btn qw-btn-ghost">📄 Télécharger le PDF</a>
        @if(!$isPublic)
            <a href="{{ route('client.devis.index') }}" class="qw-btn qw-btn-ghost">← Retour à mes devis</a>
        @endif
    </div>

    {{-- ═══════════════════ MENTION LÉGALE ═══════════════════ --}}
    <div style="margin-top:24px;padding:14px;background:#f8fafc;border-radius:8px;font-size:12px;color:#64748b;line-height:1.55">
        <strong style="color:#78350f">⚠️ Devis non contractuel :</strong>
        {{ config('billing.quote_legal_mention') }}
    </div>
</div>
