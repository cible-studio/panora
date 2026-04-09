@extends('client.layout')
@section('title', 'Sécurité du compte')
@section('page-title', '🔒 Sécurité')

@section('content')
<div class="max-w-md mx-auto">
    <div class="bg-[#11131f] border border-white/5 rounded-2xl p-8 text-center">
        <div class="text-6xl mb-4">
            @if(auth('client')->user()?->must_change_password) 🔑 @else 🔒 @endif
        </div>
        <h1 class="text-2xl font-bold text-white mb-2">
            @if(auth('client')->user()?->must_change_password)
                Définir votre mot de passe
            @else
                Changer mon mot de passe
            @endif
        </h1>
        <p class="text-sm text-gray-400 mb-6">
            @if(auth('client')->user()?->must_change_password)
                Bienvenue sur votre espace client ! Pour sécuriser votre compte, veuillez définir un mot de passe personnel.
            @else
                Pour votre sécurité, nous vous recommandons de choisir un mot de passe robuste et unique.
            @endif
        </p>

        @if(session('warning'))
            <div class="bg-yellow-500/10 border border-yellow-500/20 rounded-xl p-3 mb-6 text-yellow-400 text-sm">⚠️ {{ session('warning') }}</div>
        @endif

        <form method="POST" action="{{ route('client.password.update') }}" class="text-left">
            @csrf

            @if(!auth('client')->user()?->must_change_password)
            <div class="mb-4">
                <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Mot de passe actuel</label>
                <input type="password" name="current_password" class="w-full bg-[#1a1d2e] border border-white/5 rounded-lg px-4 py-2 text-white focus:border-[#e8a020] focus:outline-none">
                @error('current_password') <div class="text-red-400 text-xs mt-1">{{ $message }}</div> @enderror
            </div>
            @endif

            <div class="mb-4">
                <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Nouveau mot de passe</label>
                <input type="password" name="password" class="w-full bg-[#1a1d2e] border border-white/5 rounded-lg px-4 py-2 text-white focus:border-[#e8a020] focus:outline-none">
                @error('password') <div class="text-red-400 text-xs mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="mb-6">
                <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Confirmer le mot de passe</label>
                <input type="password" name="password_confirmation" class="w-full bg-[#1a1d2e] border border-white/5 rounded-lg px-4 py-2 text-white focus:border-[#e8a020] focus:outline-none">
            </div>

            <div class="bg-[#1a1d2e] rounded-xl p-4 mb-6">
                <div class="text-xs font-semibold text-[#e8a020] mb-2">✅ Règles de sécurité</div>
                <div class="grid grid-cols-2 gap-2 text-xs text-gray-400">
                    <div class="flex items-center gap-2"><span class="text-[#e8a020]">✓</span> Minimum 8 caractères</div>
                    <div class="flex items-center gap-2"><span class="text-[#e8a020]">✓</span> 1 lettre majuscule</div>
                    <div class="flex items-center gap-2"><span class="text-[#e8a020]">✓</span> 1 lettre minuscule</div>
                    <div class="flex items-center gap-2"><span class="text-[#e8a020]">✓</span> 1 chiffre</div>
                </div>
            </div>

            <div class="flex gap-3">
                @if(!auth('client')->user()?->must_change_password)
                <a href="{{ route('client.profil') }}" class="flex-1 text-center px-4 py-2 bg-[#1a1d2e] border border-white/5 rounded-lg text-sm text-gray-300 hover:text-white transition-all">← Annuler</a>
                @endif
                <button type="submit" class="flex-1 px-4 py-2 bg-[#e8a020] text-[#0a0c15] font-semibold rounded-lg text-sm hover:bg-[#c47a00] transition-all">
                    @if(auth('client')->user()?->must_change_password)
                        ✅ Enregistrer et continuer
                    @else
                        🔒 Mettre à jour
                    @endif
                </button>
            </div>
        </form>

        @if(!auth('client')->user()?->must_change_password)
        <div class="mt-6 pt-6 border-t border-white/5">
            <a href="{{ route('client.dashboard') }}" class="text-sm text-gray-500 hover:text-[#e8a020] transition-all">← Retour au tableau de bord</a>
        </div>
        @endif
    </div>
</div>
@endsection