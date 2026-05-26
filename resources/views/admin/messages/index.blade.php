<x-admin-layout>
<x-slot name="title">Messages clients</x-slot>

{{-- ══ KPI cards (pattern unifié) ══════════════════════════════════ --}}
@php
    $kpis = [
        [
            'key'=>'all', 'label'=>'Tous', 'sub'=>'historique complet',
            'color'=>'var(--accent)', 'value'=>$counts['all'],
            'url'=>route('admin.messages.index'),
            'active'=>!request('status'),
            'svg'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
        ],
        [
            'key'=>'new', 'label'=>'Nouveaux', 'sub'=>'à traiter',
            'color'=>'#f59e0b', 'value'=>$counts['new'],
            'url'=>route('admin.messages.index', ['status'=>'new']),
            'active'=>request('status')==='new',
            'svg'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
        ],
        [
            'key'=>'in_progress', 'label'=>'En cours', 'sub'=>'lus, non répondus',
            'color'=>'#3b82f6', 'value'=>$counts['in_progress'],
            'url'=>route('admin.messages.index', ['status'=>'in_progress']),
            'active'=>request('status')==='in_progress',
            'svg'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
        ],
        [
            'key'=>'replied', 'label'=>'Répondus', 'sub'=>'traités',
            'color'=>'#22c55e', 'value'=>$counts['replied'],
            'url'=>route('admin.messages.index', ['status'=>'replied']),
            'active'=>request('status')==='replied',
            'svg'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
        ],
        [
            'key'=>'archived', 'label'=>'Archivés', 'sub'=>'classés',
            'color'=>'#6b7280', 'value'=>$counts['archived'],
            'url'=>route('admin.messages.index', ['status'=>'archived']),
            'active'=>request('status')==='archived',
            'svg'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8M1 3h22v5H1zM10 12h4"/></svg>',
        ],
    ];
@endphp

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:20px;">
    @foreach($kpis as $k)
        <a href="{{ $k['url'] }}"
           style="background:var(--surface);border:1px solid {{ $k['active'] ? $k['color'] : 'var(--border)' }};border-left:3px solid {{ $k['color'] }};border-radius:12px;padding:16px;text-decoration:none;color:inherit;transition:transform .15s;display:block;{{ $k['active'] ? 'box-shadow:0 0 0 1px '.$k['color'].'33;' : '' }}"
           onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
            <div style="color:{{ $k['color'] }};margin-bottom:8px;">{!! $k['svg'] !!}</div>
            <div style="font-size:24px;font-weight:800;color:{{ $k['color'] }};line-height:1;">{{ $k['value'] }}</div>
            <div style="font-size:11px;font-weight:600;color:var(--text2);margin-top:6px;text-transform:uppercase;letter-spacing:.4px;">{{ $k['label'] }}</div>
            <div style="font-size:10px;color:var(--text3);margin-top:2px;">{{ $k['sub'] }}</div>
        </a>
    @endforeach
</div>

{{-- ══ Recherche ════════════════════════════════════════════════════ --}}
<form method="GET" action="{{ route('admin.messages.index') }}" style="margin-bottom:16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    @if(request('status'))
        <input type="hidden" name="status" value="{{ request('status') }}">
    @endif
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Rechercher objet, expéditeur, contenu…"
           style="flex:1;min-width:240px;height:38px;padding:0 14px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text);outline:none;">
    <button type="submit" class="btn btn-primary btn-sm">🔍 Rechercher</button>
    @if(request('search') || request('status'))
        <a href="{{ route('admin.messages.index') }}" class="btn btn-ghost btn-sm">↺ Réinitialiser</a>
    @endif
</form>

{{-- ══ Liste ════════════════════════════════════════════════════════ --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">Messages reçus</span>
        <span style="font-size:11px;color:var(--text3);">{{ $messages->total() }} résultat(s)</span>
    </div>

    @if($messages->isEmpty())
        <div style="text-align:center;padding:80px 20px;color:var(--text3);">
            <div style="font-size:56px;margin-bottom:16px;opacity:.6;">📭</div>
            <div style="font-size:17px;font-weight:700;color:var(--text2);margin-bottom:6px;">Aucun message</div>
            <div style="font-size:13px;">
                {{ request('search') || request('status') ? 'Aucun message ne correspond à ce filtre.' : 'Les messages envoyés depuis le formulaire « Contacter la régie » apparaîtront ici.' }}
            </div>
        </div>
    @else
        <div>
            @foreach($messages as $msg)
                @php
                    $statusCfg = match($msg->status) {
                        'new'         => ['c'=>'#f59e0b', 'bg'=>'rgba(245,158,11,.08)', 'label'=>'Nouveau'],
                        'in_progress' => ['c'=>'#3b82f6', 'bg'=>'rgba(59,130,246,.08)', 'label'=>'En cours'],
                        'replied'     => ['c'=>'#22c55e', 'bg'=>'rgba(34,197,94,.08)',  'label'=>'Répondu'],
                        'archived'    => ['c'=>'#6b7280', 'bg'=>'rgba(107,114,128,.08)', 'label'=>'Archivé'],
                        default       => ['c'=>'#6b7280', 'bg'=>'transparent',          'label'=>$msg->status],
                    };
                @endphp
                <a href="{{ route('admin.messages.show', $msg) }}"
                   style="display:flex;align-items:flex-start;gap:14px;padding:16px 20px;border-bottom:1px solid var(--border);text-decoration:none;color:inherit;transition:background .12s;{{ $msg->status === 'new' ? 'background:'.$statusCfg['bg'].';border-left:3px solid '.$statusCfg['c'].';' : '' }}"
                   onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background='{{ $msg->status === 'new' ? $statusCfg['bg'] : 'transparent' }}'">
                    <div style="width:42px;height:42px;border-radius:10px;background:{{ $statusCfg['bg'] }};color:{{ $statusCfg['c'] }};display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:18px;">✉️</div>
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap;">
                            <strong style="font-size:13px;color:var(--text);">{{ $msg->from_name }}</strong>
                            <span style="font-size:11px;color:var(--text3);">·</span>
                            <span style="font-size:11px;color:var(--text3);font-family:monospace;">{{ $msg->from_email }}</span>
                            <span style="padding:2px 8px;border-radius:20px;font-size:9px;font-weight:800;background:{{ $statusCfg['c'] }}1a;color:{{ $statusCfg['c'] }};text-transform:uppercase;letter-spacing:.3px;">{{ $statusCfg['label'] }}</span>
                        </div>
                        <div style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:4px;">{{ $msg->subject }}</div>
                        <div style="font-size:12px;color:var(--text2);line-height:1.45;{{ $msg->status === 'new' ? '' : 'opacity:.75;' }}">{{ \Illuminate\Support\Str::limit($msg->body, 160) }}</div>
                        <div style="font-size:10px;color:var(--text3);margin-top:6px;">⏱ {{ $msg->created_at->diffForHumans() }} · #{{ $msg->id }}</div>
                    </div>
                </a>
            @endforeach
        </div>

        @if($messages->hasPages())
            <div style="padding:14px 18px;border-top:1px solid var(--border);background:var(--surface2);">
                {{ $messages->links() }}
            </div>
        @endif
    @endif
</div>

</x-admin-layout>
