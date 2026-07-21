@extends('client.layout', ['pageTitle' => 'Mes devis'])

@section('content')
<div style="max-width:1180px;margin:0 auto;padding:24px">
    <h1 style="font-size:26px;font-weight:800;margin-bottom:20px">📋 Vos devis</h1>

    @if(session('success')) <div style="padding:14px;background:#dcfce7;border:1px solid #86efac;border-radius:8px;color:#166534;margin-bottom:14px">{{ session('success') }}</div> @endif
    @if(session('warning')) <div style="padding:14px;background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;color:#78350f;margin-bottom:14px">{{ session('warning') }}</div> @endif
    @if(session('info')) <div style="padding:14px;background:#dbeafe;border:1px solid #93c5fd;border-radius:8px;color:#1e40af;margin-bottom:14px">{{ session('info') }}</div> @endif

    @forelse($quotes as $quote)
        @php $st = $quote->status->uiConfig(); @endphp
        <a href="{{ route('client.devis.show', $quote) }}" style="display:block;text-decoration:none;color:inherit;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:18px 20px;margin-bottom:12px;transition:box-shadow .15s,transform .15s"
           onmouseover="this.style.boxShadow='0 8px 24px -8px rgba(0,0,0,.12)';this.style.transform='translateY(-2px)'"
           onmouseout="this.style.boxShadow='';this.style.transform=''">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap">
                <div style="flex:1;min-width:240px">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                        <span style="font-family:monospace;font-size:13px;color:#8b5cf6;font-weight:700">{{ $quote->reference }}</span>
                        <span style="background:{{ $st['bg'] }};color:{{ $st['color'] }};padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700">
                            {{ $st['icon'] }} {{ $quote->status->label() }}
                        </span>
                    </div>
                    <div style="font-size:16px;font-weight:700;color:#0f172a;margin-bottom:4px">{{ $quote->title }}</div>
                    @if($quote->period_start && $quote->period_end)
                        <div style="font-size:12.5px;color:#64748b">📅 Période : {{ $quote->period_start->format('d/m/Y') }} → {{ $quote->period_end->format('d/m/Y') }}</div>
                    @endif
                    <div style="font-size:12.5px;color:#64748b;margin-top:2px">👤 Votre commercial : {{ $quote->commercial?->name ?? '—' }}</div>
                </div>
                <div style="text-align:right">
                    <div style="font-size:22px;font-weight:800;color:#0f172a">{{ number_format($quote->total_a_payer, 0, ',', ' ') }} <span style="font-size:12px;color:#94a3b8">FCFA</span></div>
                    @if($quote->expires_at && $quote->status === \App\Enums\QuoteStatus::ENVOYE)
                        @php $daysLeft = (int) now()->diffInDays($quote->expires_at, false); @endphp
                        <div style="font-size:11px;color:{{ $daysLeft < 3 ? '#b45309' : '#64748b' }};margin-top:4px;font-weight:600">
                            @if($daysLeft < 0) ⌛ Expiré @elseif($daysLeft === 0) ⚠️ Expire aujourd'hui @else ⌛ Expire dans {{ $daysLeft }} jour(s) @endif
                        </div>
                    @endif
                </div>
            </div>
        </a>
    @empty
        <div style="text-align:center;padding:60px;background:#fff;border:1px dashed #cbd5e1;border-radius:12px;color:#64748b">
            <div style="font-size:48px;margin-bottom:12px">📭</div>
            <div style="font-size:15px">Aucun devis pour le moment.</div>
            <div style="font-size:13px;margin-top:8px">Votre commercial vous en enverra dès qu'un projet démarre.</div>
        </div>
    @endforelse

    <div style="margin-top:18px">{{ $quotes->links() }}</div>
</div>
@endsection
