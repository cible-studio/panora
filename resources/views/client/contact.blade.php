@extends('client.layout')
@section('title', 'Contacter la régie')
@section('page-title', 'Contacter la régie')

@section('content')

{{-- ══ RETOUR ══ --}}
<a href="{{ route('client.dashboard') }}"
   style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--text3);text-decoration:none;padding:6px 14px;border:1px solid var(--border);border-radius:8px;background:var(--surface);transition:all .15s;margin-bottom:18px;"
   onmouseover="this.style.color='var(--text)';this.style.borderColor='var(--border2)';this.style.background='var(--surface2)'"
   onmouseout="this.style.color='var(--text3)';this.style.borderColor='var(--border)';this.style.background='var(--surface)'">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    Tableau de bord
</a>

{{-- ══ SUCCÈS ══ --}}
@if(session('contact_success'))
<div style="background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);border-radius:12px;padding:14px 18px;margin-bottom:20px;font-size:13px;color:#22c55e;display:flex;align-items:center;gap:10px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
    {{ session('contact_success') }}
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ══ COLONNE GAUCHE : contacts + infos ══ --}}
    <div class="lg:col-span-1 space-y-4">

        {{-- Vos interlocuteurs --}}
        @if($interlocutors->isNotEmpty())
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;">
            <div style="padding:14px 18px;border-bottom:1px solid var(--border);">
                <div style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;">Vos interlocuteurs</div>
            </div>
            <div>
                @foreach($interlocutors as $i => $user)
                @php
                    $initials = collect(explode(' ', $user->name))
                        ->map(fn($w) => strtoupper($w[0] ?? ''))->filter()->take(2)->implode('');
                @endphp
                <div style="padding:14px 18px;{{ !$loop->last ? 'border-bottom:1px solid var(--border)' : '' }};display:flex;align-items:center;gap:12px;">
                    <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#e20613,#fab80b);display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:#fff;flex-shrink:0;">
                        {{ $initials }}
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:700;color:var(--text);">{{ $user->name }}</div>
                        <div style="font-size:11px;color:var(--text3);margin-top:1px;">{{ $user->role?->label() ?? '—' }}</div>
                        <div style="margin-top:6px;display:flex;flex-direction:column;gap:4px;">
                            <a href="mailto:{{ $user->email }}"
                               style="display:inline-flex;align-items:center;gap:5px;font-size:11px;color:var(--text2);text-decoration:none;transition:color .15s;"
                               onmouseover="this.style.color='#e20613'" onmouseout="this.style.color='var(--text2)'">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                {{ $user->email }}
                            </a>
                            @if($user->whatsapp_number)
                            <a href="https://wa.me/{{ preg_replace('/\D/', '', $user->whatsapp_number) }}" target="_blank" rel="noopener"
                               style="display:inline-flex;align-items:center;gap:5px;font-size:11px;color:var(--text2);text-decoration:none;transition:color .15s;"
                               onmouseover="this.style.color='#22c55e'" onmouseout="this.style.color='var(--text2)'">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                                {{ $user->whatsapp_number }}
                            </a>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Infos régie --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;">
            <div style="padding:14px 18px;border-bottom:1px solid var(--border);">
                <div style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;">CIBLE CI</div>
            </div>
            <div style="padding:16px 18px;display:flex;flex-direction:column;gap:12px;">
                <div style="display:flex;align-items:flex-start;gap:10px;font-size:12px;color:var(--text2);">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#e20613" stroke-width="2" style="flex-shrink:0;margin-top:1px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span>Cocody Riviera Palmeraie,<br>Abidjan, Côte d'Ivoire</span>
                </div>
                <a href="mailto:contact@cible-ci.com"
                   style="display:flex;align-items:center;gap:10px;font-size:12px;color:var(--text2);text-decoration:none;transition:color .15s;"
                   onmouseover="this.style.color='#e20613'" onmouseout="this.style.color='var(--text2)'">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#e20613" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    contact@cible-ci.com
                </a>
                <div style="font-size:11px;color:var(--text3);padding-top:8px;border-top:1px solid var(--border);line-height:1.6;">
                    Lun – Ven · 8h – 18h<br>
                    Réponse sous 24h ouvrées
                </div>
            </div>
        </div>
    </div>

    {{-- ══ COLONNE DROITE : formulaire ══ --}}
    <div class="lg:col-span-2">
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:24px;">
            <h2 style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:4px;">Envoyer un message</h2>
            <p style="font-size:13px;color:var(--text3);margin-bottom:20px;">Notre équipe vous répondra dans les meilleurs délais.</p>

            @if($errors->any())
            <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:12px;color:#ef4444;">
                @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
            </div>
            @endif

            <form method="POST" action="{{ route('client.contact.send') }}">
                @csrf

                {{-- Infos expéditeur (lecture seule) --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Société</label>
                        <div style="padding:9px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:9px;font-size:13px;color:var(--text2);">
                            {{ $client->company ?? $client->name }}
                        </div>
                    </div>
                    <div>
                        <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Email de réponse</label>
                        <div style="padding:9px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:9px;font-size:13px;color:var(--text2);">
                            {{ $client->email }}
                        </div>
                    </div>
                </div>

                {{-- Objet --}}
                <div style="margin-bottom:16px;">
                    <label for="subject" style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Objet *</label>
                    <input type="text" id="subject" name="subject" value="{{ old('subject') }}"
                           placeholder="Ex : Question sur ma campagne, Demande de devis…"
                           style="width:100%;padding:9px 14px;background:var(--surface2);border:1px solid {{ $errors->has('subject') ? '#ef4444' : 'var(--border2)' }};border-radius:9px;font-size:13px;color:var(--text);outline:none;transition:border-color .15s;"
                           onfocus="this.style.borderColor='#e20613'" onblur="this.style.borderColor='var(--border2)'"
                           maxlength="150" required>
                </div>

                {{-- Message --}}
                <div style="margin-bottom:20px;">
                    <label for="message" style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Message *</label>
                    <textarea id="message" name="message" rows="7"
                              placeholder="Décrivez votre demande en détail…"
                              style="width:100%;padding:10px 14px;background:var(--surface2);border:1px solid {{ $errors->has('message') ? '#ef4444' : 'var(--border2)' }};border-radius:9px;font-size:13px;color:var(--text);outline:none;resize:vertical;font-family:inherit;transition:border-color .15s;"
                              onfocus="this.style.borderColor='#e20613'" onblur="this.style.borderColor='var(--border2)'"
                              maxlength="3000" required>{{ old('message') }}</textarea>
                    <div style="text-align:right;font-size:10px;color:var(--text3);margin-top:4px;">
                        <span id="msg-counter">0</span> / 3000
                    </div>
                </div>

                <button type="submit"
                        style="display:inline-flex;align-items:center;gap:8px;padding:11px 28px;background:#e20613;color:#fff;font-weight:700;border-radius:10px;font-size:14px;border:none;cursor:pointer;transition:opacity .15s;"
                        onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    Envoyer le message
                </button>
            </form>
        </div>
    </div>
</div>

<script>
const msgArea = document.getElementById('message');
const counter = document.getElementById('msg-counter');
if (msgArea && counter) {
    function updateCounter() { counter.textContent = msgArea.value.length; }
    msgArea.addEventListener('input', updateCounter);
    updateCounter();
}
</script>

@endsection
