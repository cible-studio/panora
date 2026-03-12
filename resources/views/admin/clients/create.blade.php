<x-admin-layout title="Nouveau client">

    <div class="mb-6">
        <a href="{{ route('admin.clients.index') }}"
           class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Retour à la liste
        </a>
    </div>

    <div class="max-w-2xl">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-6">Informations du client</h2>

            <form method="POST" action="{{ route('admin.clients.store') }}" class="space-y-5">
                @csrf

                {{-- Nom --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Nom de l'entreprise <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" value="{{ old('name') }}"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 @error('name') border-red-400 @enderror"
                           placeholder="Ex : Orange Côte d'Ivoire"/>
                    @error('name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Secteur --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Secteur d'activité</label>
                    <input type="text" name="sector" value="{{ old('sector') }}"
                           list="sectors-list"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400"
                           placeholder="Ex : Télécommunications"/>
                    <datalist id="sectors-list">
                        @foreach($sectors as $sector)
                            <option value="{{ $sector }}">
                        @endforeach
                    </datalist>
                </div>

                {{-- Contact --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nom du contact</label>
                    <input type="text" name="contact_name" value="{{ old('contact_name') }}"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400"
                           placeholder="Ex : Koné Ibrahim"/>
                </div>

                {{-- Email + Téléphone --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 @error('email') border-red-400 @enderror"
                               placeholder="contact@entreprise.ci"/>
                        @error('email')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
                        <input type="text" name="phone" value="{{ old('phone') }}"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400"
                               placeholder="+225 07 00 00 00 00"/>
                    </div>
                </div>

                {{-- Adresse --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Adresse</label>
                    <textarea name="address" rows="3"
                              class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400"
                              placeholder="Ex : Plateau, Abidjan">{{ old('address') }}</textarea>
                </div>

                {{-- Boutons --}}
                <div class="flex items-center justify-end space-x-3 pt-2">
                    <a href="{{ route('admin.clients.index') }}"
                       class="px-4 py-2.5 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                        Annuler
                    </a>
                    <button type="submit"
                            class="px-6 py-2.5 text-sm text-white bg-orange-500 rounded-lg hover:bg-orange-600 transition font-medium">
                        Créer le client
                    </button>
                </div>

            </form>
        </div>
    </div>

</x-admin-layout>