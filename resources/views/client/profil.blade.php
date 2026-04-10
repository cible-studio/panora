@extends('client.layout')
@section('title', 'Mon profil')
@section('page-title', '👤 Mon profil')

@section('content')
@php $client = auth('client')->user(); @endphp

<div class="bg-[#11131f] border border-white/5 rounded-2xl p-6 mb-6">
    <div class="flex flex-wrap items-center gap-6">
        <div class="flex flex-col items-center gap-3">
            <div class="w-20 h-20 rounded-full bg-gradient-to-r from-[#e8a020] to-[#fbbf24] flex items-center justify-center text-3xl font-bold text-[#0a0c15]">
                {{ strtoupper(mb_substr($client->name, 0, 1)) }}
            </div>
            <div class="bg-[#e8a020]/10 border border-[#e8a020]/20 rounded-full px-3 py-1 text-xs text-[#e8a020]">⭐ Client Premium</div>
        </div>
        <div class="flex-1">
            <h1 class="text-2xl font-bold text-white mb-1">{{ $client->name }}</h1>
            @if($client->ncc)<div class="font-mono text-sm text-gray-500 mb-2">NCC : {{ $client->ncc }}</div>@endif
            <div class="flex flex-wrap gap-4 text-sm text-gray-400">
                <span>📧 {{ $client->email }}</span>
                @if($client->phone)<span>📞 {{ $client->phone }}</span>@endif
            </div>
        </div>
        <div>
            <a href="{{ route('client.password.change') }}" class="px-5 py-2 bg-[#1a1d2e] border border-white/5 rounded-lg text-sm text-gray-300 hover:text-white transition-all">🔑 Changer le mot de passe</a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-[#11131f] border border-white/5 rounded-xl p-4 text-center">
        <div class="text-2xl mb-2">📅</div>
        <div class="text-xl font-bold text-[#e8a020]">{{ $client->created_at->format('d/m/Y') }}</div>
        <div class="text-xs text-gray-500 mt-1">Membre depuis</div>
    </div>
    <div class="bg-[#11131f] border border-white/5 rounded-xl p-4 text-center">
        <div class="text-2xl mb-2">🔐</div>
        <div class="text-sm font-semibold {{ $client->password_changed_at ? 'text-green-400' : 'text-yellow-400' }}">
            {{ $client->password_changed_at ? '✅ Mot de passe sécurisé' : '⚠️ À sécuriser' }}
        </div>
        <div class="text-xs text-gray-500 mt-1">Sécurité</div>
    </div>
    <div class="bg-[#11131f] border border-white/5 rounded-xl p-4 text-center">
        <div class="text-2xl mb-2">📊</div>
        <div class="text-sm text-gray-300">{{ $client->last_login_at ? 'Dernière connexion ' . $client->last_login_at->diffForHumans() : 'Première connexion' }}</div>
        <div class="text-xs text-gray-500 mt-1">Activité</div>
    </div>
</div>

<div class="space-y-6">
    <div class="bg-[#11131f] border border-white/5 rounded-xl overflow-hidden">
        <div class="px-6 py-4 bg-white/5 border-b border-white/5">
            <h2 class="font-semibold text-white">ℹ️ Informations générales</h2>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <div class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Nom / Raison sociale</div>
                <div class="text-white">{{ $client->name }}</div>
                <div class="text-xs text-gray-500 mt-1">Contactez votre commercial pour modifier</div>
            </div>
            <div>
                <div class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Email professionnel</div>
                <div class="text-white">{{ $client->email }}</div>
                <div class="text-xs text-gray-500 mt-1">Non modifiable en ligne</div>
            </div>
            @if($client->ncc)
            <div>
                <div class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">NCC (Numéro Client)</div>
                <div class="text-white font-mono">{{ $client->ncc }}</div>
            </div>
            @endif
        </div>
    </div>

    <div class="bg-[#11131f] border border-white/5 rounded-xl overflow-hidden">
        <div class="px-6 py-4 bg-white/5 border-b border-white/5">
            <h2 class="font-semibold text-white">📍 Coordonnées</h2>
        </div>
        <div class="p-6">
            <form method="POST" action="{{ route('client.profil.update') }}">
                @csrf @method('PATCH')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Téléphone</label>
                        <input type="tel" name="phone" class="w-full bg-[#1a1d2e] border border-white/5 rounded-lg px-4 py-2 text-white focus:border-[#e8a020] focus:outline-none" value="{{ old('phone', $client->phone) }}" placeholder="+225 XX XX XX XX">
                        @error('phone')<div class="text-red-400 text-xs mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Personne de contact</label>
                        <input type="text" name="contact_name" class="w-full bg-[#1a1d2e] border border-white/5 rounded-lg px-4 py-2 text-white focus:border-[#e8a020] focus:outline-none" value="{{ old('contact_name', $client->contact_name) }}" placeholder="Nom du référent">
                        @error('contact_name')<div class="text-red-400 text-xs mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Adresse</label>
                        <input type="text" name="address" class="w-full bg-[#1a1d2e] border border-white/5 rounded-lg px-4 py-2 text-white focus:border-[#e8a020] focus:outline-none" value="{{ old('address', $client->address) }}" placeholder="Adresse complète">
                        @error('address')<div class="text-red-400 text-xs mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="flex items-center gap-3 mt-6">
                    <button type="submit" class="px-5 py-2 bg-[#e8a020] text-[#0a0c15] font-semibold rounded-lg text-sm hover:bg-[#c47a00] transition-all">💾 Enregistrer</button>
                    <span class="text-xs text-gray-500">Seuls les champs de contact sont modifiables</span>
                </div>
            </form>
        </div>
    </div>

    <div class="bg-[#11131f] border border-white/5 rounded-xl overflow-hidden">
        <div class="px-6 py-4 bg-white/5 border-b border-white/5">
            <h2 class="font-semibold text-white">🔒 Sécurité du compte</h2>
        </div>
        <div class="divide-y divide-white/5">
            <div class="flex flex-wrap justify-between items-center p-5 gap-3">
                <div>
                    <div class="font-semibold text-white">Mot de passe</div>
                    @if($client->password_changed_at)
                        <div class="text-sm text-green-400 mt-1">✅ Modifié le {{ $client->password_changed_at->format('d/m/Y') }}</div>
                    @else
                        <div class="text-sm text-yellow-400 mt-1">⚠️ Jamais modifié — action recommandée</div>
                    @endif
                </div>
                <a href="{{ route('client.password.change') }}" class="px-4 py-2 bg-[#1a1d2e] border border-white/5 rounded-lg text-sm text-gray-300 hover:text-white transition-all">Modifier →</a>
            </div>
            @if($client->last_login_at)
            <div class="p-5">
                <div class="font-semibold text-white mb-1">Dernière activité</div>
                <div class="text-sm text-gray-400">
                    Connexion le {{ $client->last_login_at->format('d/m/Y à H:i') }}
                    @if($client->last_login_ip) · depuis {{ $client->last_login_ip }} @endif
                </div>
            </div>
            @endif
            <div class="p-5 bg-[#e8a020]/5">
                <div class="flex gap-3">
                    <span class="text-xl">💡</span>
                    <div class="text-sm text-gray-400">
                        <strong class="text-[#e8a020]">Conseil de sécurité</strong><br>
                        Utilisez un mot de passe unique pour votre espace client et ne le partagez jamais.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection