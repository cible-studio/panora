@extends('client.layout')
@section('title', 'Proposition ' . $reservation->reference)
@section('page-title', '📄 Détail de la proposition')

@section('content')

<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
    <a href="{{ route('client.dashboard') }}" class="hover:text-[#e8a020] transition-colors">Accueil</a>
    <span>›</span>
    <a href="{{ route('client.propositions') }}" class="hover:text-[#e8a020] transition-colors">Propositions</a>
    <span>›</span>
    <span class="text-gray-400">{{ $reservation->reference }}</span>
</div>

<!-- Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-white mb-1">Proposition {{ $reservation->reference }}</h1>
        <p class="text-sm text-gray-500">Envoyée le {{ $reservation->proposition_sent_at?->format('d/m/Y à H:i') ?? '—' }}</p>
    </div>
    @php
        $statusConfig = match($reservation->status->value) {
            'en_attente' => ['class' => 'bg-amber-500/20 text-amber-400 border-amber-500/30', 'icon' => '⏳', 'text' => 'En attente de réponse'],
            'confirme' => ['class' => 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30', 'icon' => '✅', 'text' => 'Proposition acceptée'],
            'refuse' => ['class' => 'bg-red-500/20 text-red-400 border-red-500/30', 'icon' => '❌', 'text' => 'Proposition refusée'],
            default => ['class' => 'bg-gray-500/20 text-gray-400 border-gray-500/30', 'icon' => 'ℹ️', 'text' => ucfirst($reservation->status->value)],
        };
    @endphp
    <span class="inline-block px-4 py-2 rounded-full text-sm font-medium border {{ $statusConfig['class'] }} w-fit">
        {{ $statusConfig['icon'] }} {{ $statusConfig['text'] }}
    </span>
</div>

{{-- Alertes --}}
@if($joursRestants < 0)
    <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm">
        ⏰ Cette proposition est expirée — vous ne pouvez plus y répondre.
    </div>
@elseif($joursRestants <= 3)
    <div class="mb-6 p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 text-sm">
        ⚠️ Action urgente — plus que {{ max(0, $joursRestants) }} jour(s) pour répondre.
    </div>
@elseif($joursRestants <= 7)
    <div class="mb-6 p-4 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-400 text-sm">
        ℹ️ Plus que {{ $joursRestants }} jour(s) pour répondre.
    </div>
@endif

{{-- Période --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
    <div class="bg-[#11131f] rounded-xl border border-white/5 p-4 text-center">
        <div class="text-3xl mb-2">📅</div>
        <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Date de début</div>
        <div class="text-base font-semibold text-white">{{ $reservation->start_date->format('d/m/Y') }}</div>
    </div>
    <div class="bg-[#11131f] rounded-xl border border-white/5 p-4 text-center">
        <div class="text-3xl mb-2">📅</div>
        <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Date de fin</div>
        <div class="text-base font-semibold text-white">{{ $reservation->end_date->format('d/m/Y') }}</div>
    </div>
    <div class="bg-[#11131f] rounded-xl border border-white/5 p-4 text-center">
        <div class="text-3xl mb-2">⏱️</div>
        <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Durée</div>
        <div class="text-base font-semibold text-white">{{ round($months) }} mois</div>
    </div>
    <div class="bg-[#11131f] rounded-xl border border-white/5 p-4 text-center">
        <div class="text-3xl mb-2">🪧</div>
        <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Panneaux</div>
        <div class="text-base font-semibold text-white">{{ count($panels) }} emplacement(s)</div>
    </div>
</div>

{{-- Emplacements --}}
<h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
    <span>📍</span> Emplacements sélectionnés
    <span class="text-sm text-gray-500 font-normal">({{ count($panels) }})</span>
</h2>

<div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-8">
    @foreach($panels as $index => $panel)
    <div class="bg-[#11131f] rounded-xl border border-white/5 overflow-hidden hover:border-[#e8a020]/30 transition-all group relative">
        <div class="absolute top-3 left-3 bg-[#e8a020] text-[#0a0c15] w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold z-10 shadow-lg">
            {{ $index + 1 }}
        </div>
        
        <!-- Image avec bouton zoom -->
        <div class="relative cursor-pointer group/image" onclick="openPanelModal({{ $index }})">
            @if($panel['photo_url'])
                <img src="{{ $panel['photo_url'] }}" class="w-full h-44 object-cover transition-transform group-hover/image:scale-105" alt="{{ $panel['reference'] }}" loading="lazy">
                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover/image:opacity-100 transition-opacity flex items-center justify-center">
                    <span class="bg-[#e8a020] text-[#0a0c15] px-3 py-1.5 rounded-full text-xs font-semibold flex items-center gap-1">
                        🔍 Voir en détail
                    </span>
                </div>
            @else
                <div class="w-full h-44 bg-gradient-to-br from-[#1a1d2e] to-[#11131f] flex items-center justify-center text-5xl text-gray-600">
                    🪧
                </div>
            @endif
        </div>
        
        <div class="p-4">
            <div class="inline-block font-mono text-xs font-bold text-[#e8a020] bg-[#e8a020]/10 px-2 py-1 rounded mb-2">
                {{ $panel['reference'] }}
            </div>
            <div class="text-white font-semibold text-sm mb-3">{{ $panel['name'] }}</div>
            <div class="border-t border-white/10 pt-3 space-y-2">
                <div class="flex justify-between items-center text-xs">
                    <span class="text-gray-500">📍 Commune</span>
                    <span class="text-gray-300 font-medium">{{ $panel['commune'] }}</span>
                </div>
                @if(!empty($panel['zone']) && $panel['zone'] !== '—')
                <div class="flex justify-between items-center text-xs">
                    <span class="text-gray-500">🗺️ Zone</span>
                    <span class="text-gray-300 font-medium">{{ $panel['zone'] }}</span>
                </div>
                @endif
                @if(!empty($panel['format']))
                <div class="flex justify-between items-center text-xs">
                    <span class="text-gray-500">📐 Format</span>
                    <span class="text-gray-300 font-medium">{{ $panel['format'] }}</span>
                </div>
                @endif
                @if(!empty($panel['dimensions']))
                <div class="flex justify-between items-center text-xs">
                    <span class="text-gray-500">📏 Dimensions</span>
                    <span class="text-gray-300 font-medium">{{ $panel['dimensions'] }}</span>
                </div>
                @endif
                <div class="flex justify-between items-center text-xs">
                    <span class="text-gray-500">💡 Éclairage</span>
                    <span class="text-gray-300 font-medium">{{ $panel['is_lit'] ? 'LED Éclairé' : 'Non éclairé' }}</span>
                </div>
                <div class="flex justify-between items-center text-sm pt-2 border-t border-dashed border-white/10 mt-2">
                    <span class="text-gray-400 font-medium">💰 Tarif mensuel</span>
                    <span class="text-[#e8a020] font-bold">{{ number_format($panel['monthly_rate'], 0, ',', ' ') }} FCFA</span>
                </div>
            </div>
            
            <!-- Bouton voir détails -->
            <button onclick="openPanelModal({{ $index }})" class="mt-3 w-full text-center text-xs text-gray-500 hover:text-[#e8a020] transition-colors py-1 border-t border-white/5 pt-2">
                🔍 Voir tous les détails
            </button>
        </div>
    </div>
    @endforeach
</div>

{{-- Total --}}
@php $total = collect($panels)->sum('total'); @endphp
@if($total > 0)
<div class="bg-gradient-to-r from-[#e8a020]/10 to-transparent border border-[#e8a020]/30 rounded-2xl p-6 mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
        <div>
            <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Montant total estimé (HT)</div>
            <div class="text-3xl font-extrabold text-[#e8a020]">{{ number_format($total, 0, ',', ' ') }} <span class="text-sm font-normal text-gray-500">FCFA</span></div>
            <div class="text-xs text-gray-500 mt-1">Pour {{ round($months) }} mois de campagne</div>
        </div>
        <div class="bg-[#11131f] px-4 py-2 rounded-full text-sm font-semibold text-gray-300 border border-white/10">
            📊 {{ count($panels) }} emplacement(s)
        </div>
    </div>
    <div class="text-xs text-gray-500 pt-4 border-t border-[#e8a020]/20">
        💡 Devis définitif établi lors de la confirmation. Les tarifs sont nets hors taxes et frais techniques.
    </div>
</div>
@endif

{{-- Actions --}}
@if($joursRestants >= 0 && $reservation->status->value === 'en_attente')
<div class="bg-[#11131f] border border-white/10 rounded-2xl p-8 text-center">
    <h3 class="text-xl font-bold text-white mb-3">Quelle est votre décision ?</h3>
    <p class="text-sm text-gray-400 max-w-lg mx-auto mb-6 leading-relaxed">
        En confirmant cette proposition, les panneaux vous seront attribués immédiatement 
        et une campagne sera créée automatiquement dans votre espace.
    </p>
    <div class="flex flex-col sm:flex-row gap-4 justify-center mb-5">
        <button type="button" class="bg-[#e8a020] text-[#0a0c15] font-semibold rounded-xl px-8 py-3 hover:bg-[#c47a00] transition-all cursor-pointer" onclick="openConfirmModal()">
            ✅ Accepter la proposition
        </button>
        <button type="button" class="bg-red-500/20 text-red-400 font-semibold rounded-xl px-8 py-3 border border-red-500/30 hover:bg-red-500/30 transition-all cursor-pointer" onclick="openRefuseModal()">
            ✗ Refuser
        </button>
    </div>
    <div class="text-xs text-gray-600">
        🔒 Réponse sécurisée · CIBLE CI · Abidjan
    </div>
</div>
@endif

{{-- MODAL DE VISUALISATION DÉTAILLÉE DU PANNEAU --}}
<div id="modal-panel" class="fixed inset-0 bg-black/90 backdrop-blur-md flex items-center justify-center z-50 hidden" onclick="if(event.target===this) closePanelModal()">
    <div class="bg-[#11131f] border border-white/10 rounded-2xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-[#11131f] border-b border-white/10 px-6 py-4 flex justify-between items-center">
            <h3 class="text-xl font-bold text-white" id="modal-panel-title">Détail du panneau</h3>
            <button class="text-gray-500 hover:text-gray-300 transition-colors text-2xl" onclick="closePanelModal()">✕</button>
        </div>
        
        <div class="p-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Colonne gauche : Images -->
                <div>
                    <!-- Image principale -->
                    <div class="rounded-xl overflow-hidden border border-white/10 mb-4">
                        <img id="modal-main-image" src="" alt="Panneau" class="w-full object-cover max-h-80">
                    </div>
                    
                    <!-- Miniatures (si plusieurs photos) -->
                    <div id="modal-thumbnails" class="flex gap-2 overflow-x-auto pb-2">
                        <!-- Les miniatures seront ajoutées dynamiquement -->
                    </div>
                    
                    <!-- Indicateur de chargement -->
                    <div id="modal-no-image" class="hidden text-center py-8 bg-[#1a1d2e] rounded-xl">
                        <div class="text-6xl mb-2">🪧</div>
                        <p class="text-gray-500 text-sm">Aucune photo disponible pour ce panneau</p>
                    </div>
                </div>
                
                <!-- Colonne droite : Informations détaillées -->
                <div>
                    <div class="mb-4">
                        <div class="inline-block font-mono text-xs font-bold text-[#e8a020] bg-[#e8a020]/10 px-2 py-1 rounded mb-2" id="modal-ref"></div>
                        <h4 class="text-white font-bold text-lg mb-2" id="modal-name"></h4>
                    </div>
                    
                    <div class="space-y-3">
                        <div class="bg-[#1a1d2e] rounded-xl p-3">
                            <div class="text-xs text-gray-500 uppercase tracking-wider mb-2">📍 Localisation</div>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-400">Commune</span>
                                    <span class="text-white font-medium" id="modal-commune">—</span>
                                </div>
                                <div class="flex justify-between text-sm" id="modal-zone-row">
                                    <span class="text-gray-400">Zone</span>
                                    <span class="text-white font-medium" id="modal-zone">—</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-[#1a1d2e] rounded-xl p-3">
                            <div class="text-xs text-gray-500 uppercase tracking-wider mb-2">📐 Caractéristiques</div>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm" id="modal-format-row">
                                    <span class="text-gray-400">Format</span>
                                    <span class="text-white font-medium" id="modal-format">—</span>
                                </div>
                                <div class="flex justify-between text-sm" id="modal-dimensions-row">
                                    <span class="text-gray-400">Dimensions</span>
                                    <span class="text-white font-medium" id="modal-dimensions">—</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-400">Éclairage</span>
                                    <span class="text-white font-medium" id="modal-lit">—</span>
                                </div>
                                <div class="flex justify-between text-sm" id="modal-orientation-row">
                                    <span class="text-gray-400">Orientation</span>
                                    <span class="text-white font-medium" id="modal-orientation">—</span>
                                </div>
                                <div class="flex justify-between text-sm" id="modal-height-row">
                                    <span class="text-gray-400">Hauteur</span>
                                    <span class="text-white font-medium" id="modal-height">—</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-[#1a1d2e] rounded-xl p-3">
                            <div class="text-xs text-gray-500 uppercase tracking-wider mb-2">📊 Trafic</div>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm" id="modal-traffic-row">
                                    <span class="text-gray-400">Trafic journalier</span>
                                    <span class="text-white font-medium" id="modal-traffic">—</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-r from-[#e8a020]/10 to-transparent rounded-xl p-4 border border-[#e8a020]/30">
                            <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">💰 Tarification</div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-400">Tarif mensuel</span>
                                <span class="text-2xl font-bold text-[#e8a020]" id="modal-price">0 FCFA</span>
                            </div>
                            <div class="text-xs text-gray-500 mt-2">
                                * Tarif net hors taxes
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Confirmation --}}
<div id="modal-confirm" class="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-50 hidden" onclick="if(event.target===this) closeConfirmModal()">
    <div class="bg-[#11131f] border border-white/10 rounded-2xl max-w-md w-full mx-4 relative">
        <button class="absolute top-4 right-4 text-gray-500 hover:text-gray-300 transition-colors text-xl" onclick="closeConfirmModal()">✕</button>
        <div class="p-6 text-center">
            <div class="text-6xl mb-4">✅</div>
            <h3 class="text-xl font-bold text-white mb-2">Confirmer la proposition</h3>
            <p class="text-sm text-gray-400 mb-4">
                Souhaitez-vous confirmer cette proposition ?<br>
                Les panneaux vous seront attribués et une campagne sera créée.
            </p>
            <div class="bg-amber-500/10 border border-amber-500/20 rounded-xl p-3 mb-6">
                <p class="text-xs text-amber-400">🔒 Cette action est définitive — elle déclenche la création de votre campagne.</p>
            </div>
            <form method="POST" action="{{ route('proposition.confirmer', $token) }}" id="form-confirm">
                @csrf
                <div class="flex gap-3 justify-center">
                    <button type="button" class="px-6 py-2 rounded-xl bg-[#1a1d2e] text-gray-400 hover:bg-[#252a3f] transition-colors" onclick="closeConfirmModal()">Annuler</button>
                    <button type="submit" class="px-6 py-2 rounded-xl bg-[#e8a020] text-[#0a0c15] font-semibold hover:bg-[#c47a00] transition-colors" onclick="this.disabled=true;this.textContent='En cours…';this.closest('form').submit()">
                        Confirmer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Refus --}}
<div id="modal-refus" class="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-50 hidden" onclick="if(event.target===this) closeRefuseModal()">
    <div class="bg-[#11131f] border border-white/10 rounded-2xl max-w-md w-full mx-4 relative">
        <button class="absolute top-4 right-4 text-gray-500 hover:text-gray-300 transition-colors text-xl" onclick="closeRefuseModal()">✕</button>
        <div class="p-6">
            <div class="text-center mb-4">
                <div class="text-6xl mb-4">👋</div>
                <h3 class="text-xl font-bold text-white mb-2">Refuser la proposition</h3>
                <p class="text-sm text-gray-400">
                    Un motif aide notre équipe à mieux vous proposer des emplacements adaptés à vos besoins.
                </p>
            </div>
            <form method="POST" action="{{ route('proposition.refuser', $token) }}">
                @csrf
                <textarea name="motif" rows="3" class="w-full bg-[#1a1d2e] border border-white/10 rounded-xl p-3 text-white text-sm placeholder-gray-500 focus:outline-none focus:border-[#e8a020] transition-colors resize-vertical mb-5" placeholder="Motif optionnel — ex: budget, zones non souhaitées, période inadaptée..."></textarea>
                <div class="flex gap-3 justify-center">
                    <button type="button" class="px-6 py-2 rounded-xl bg-[#1a1d2e] text-gray-400 hover:bg-[#252a3f] transition-colors" onclick="closeRefuseModal()">Annuler</button>
                    <button type="submit" class="px-6 py-2 rounded-xl bg-red-500/20 text-red-400 font-semibold border border-red-500/30 hover:bg-red-500/30 transition-colors">
                        Confirmer le refus
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Données des panneaux passées depuis le contrôleur
const panelsData = @json($panels);

// Variables pour le carrousel
let currentPanelIndex = 0;
let currentPhotoIndex = 0;
let currentPhotos = [];

function openPanelModal(index) {
    currentPanelIndex = index;
    const panel = panelsData[currentPanelIndex];
    
    if (!panel) return;
    
    // Récupérer les photos (si disponibles)
    currentPhotos = panel.photos || [];
    currentPhotoIndex = 0;
    
    // Mettre à jour le titre
    document.getElementById('modal-panel-title').innerHTML = `📋 ${panel.reference}`;
    
    // Informations de base
    document.getElementById('modal-ref').innerHTML = panel.reference;
    document.getElementById('modal-name').innerHTML = panel.name;
    document.getElementById('modal-commune').innerHTML = panel.commune || '—';
    
    // Zone
    const zoneRow = document.getElementById('modal-zone-row');
    const zoneEl = document.getElementById('modal-zone');
    if (panel.zone && panel.zone !== '—') {
        zoneRow.style.display = 'flex';
        zoneEl.innerHTML = panel.zone;
    } else {
        zoneRow.style.display = 'none';
    }
    
    // Format
    const formatRow = document.getElementById('modal-format-row');
    const formatEl = document.getElementById('modal-format');
    if (panel.format && panel.format !== '—') {
        formatRow.style.display = 'flex';
        formatEl.innerHTML = panel.format;
    } else {
        formatRow.style.display = 'none';
    }
    
    // Dimensions
    const dimensionsRow = document.getElementById('modal-dimensions-row');
    const dimensionsEl = document.getElementById('modal-dimensions');
    if (panel.dimensions && panel.dimensions !== '—') {
        dimensionsRow.style.display = 'flex';
        dimensionsEl.innerHTML = panel.dimensions;
    } else {
        dimensionsRow.style.display = 'none';
    }
    
    // Orientation
    const orientationRow = document.getElementById('modal-orientation-row');
    const orientationEl = document.getElementById('modal-orientation');
    if (panel.orientation && panel.orientation !== '—') {
        orientationRow.style.display = 'flex';
        orientationEl.innerHTML = panel.orientation;
    } else {
        orientationRow.style.display = 'none';
    }
    
    // Hauteur
    const heightRow = document.getElementById('modal-height-row');
    const heightEl = document.getElementById('modal-height');
    if (panel.height && panel.height !== '—') {
        heightRow.style.display = 'flex';
        heightEl.innerHTML = panel.height;
    } else {
        heightRow.style.display = 'none';
    }
    
    // Trafic
    const trafficRow = document.getElementById('modal-traffic-row');
    const trafficEl = document.getElementById('modal-traffic');
    if (panel.daily_traffic) {
        trafficRow.style.display = 'flex';
        trafficEl.innerHTML = panel.daily_traffic.toLocaleString() + ' véhicules/jour';
    } else {
        trafficRow.style.display = 'none';
    }
    
    // Éclairage
    document.getElementById('modal-lit').innerHTML = panel.is_lit ? '💡 LED Éclairé' : '🌙 Non éclairé';
    
    // Prix
    document.getElementById('modal-price').innerHTML = new Intl.NumberFormat('fr-FR').format(panel.monthly_rate) + ' FCFA';
    
    // Gérer les images
    updateModalImage();
    
    // Afficher le modal
    document.getElementById('modal-panel').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function updateModalImage() {
    const mainImage = document.getElementById('modal-main-image');
    const thumbnailsContainer = document.getElementById('modal-thumbnails');
    const noImageDiv = document.getElementById('modal-no-image');
    
    if (currentPhotos.length > 0 && currentPhotos[currentPhotoIndex]) {
        // Afficher l'image principale
        mainImage.src = currentPhotos[currentPhotoIndex].url;
        mainImage.classList.remove('hidden');
        noImageDiv.classList.add('hidden');
        
        // Générer les miniatures
        thumbnailsContainer.innerHTML = '';
        currentPhotos.forEach((photo, idx) => {
            const thumb = document.createElement('button');
            thumb.className = `w-16 h-16 rounded-lg overflow-hidden border-2 transition-all ${idx === currentPhotoIndex ? 'border-[#e8a020]' : 'border-white/20 hover:border-white/50'}`;
            thumb.onclick = (e) => {
                e.stopPropagation();
                currentPhotoIndex = idx;
                updateModalImage();
            };
            thumb.innerHTML = `<img src="${photo.url}" class="w-full h-full object-cover" alt="Miniature">`;
            thumbnailsContainer.appendChild(thumb);
        });
    } else {
        // Pas d'image
        mainImage.classList.add('hidden');
        noImageDiv.classList.remove('hidden');
        thumbnailsContainer.innerHTML = '';
    }
}

function closePanelModal() {
    document.getElementById('modal-panel').classList.add('hidden');
    document.body.style.overflow = '';
}

function openConfirmModal() {
    document.getElementById('modal-confirm').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeConfirmModal() {
    document.getElementById('modal-confirm').classList.add('hidden');
    document.body.style.overflow = '';
}

function openRefuseModal() {
    document.getElementById('modal-refus').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeRefuseModal() {
    document.getElementById('modal-refus').classList.add('hidden');
    document.body.style.overflow = '';
}

// Navigation au clavier
document.addEventListener('keydown', function(e) {
    if (document.getElementById('modal-panel').classList.contains('hidden')) {
        if (e.key === 'Escape') {
            closeConfirmModal();
            closeRefuseModal();
        }
    } else {
        if (e.key === 'Escape') {
            closePanelModal();
        } else if (e.key === 'ArrowLeft') {
            if (currentPhotos.length > 0) {
                currentPhotoIndex = (currentPhotoIndex - 1 + currentPhotos.length) % currentPhotos.length;
                updateModalImage();
            }
        } else if (e.key === 'ArrowRight') {
            if (currentPhotos.length > 0) {
                currentPhotoIndex = (currentPhotoIndex + 1) % currentPhotos.length;
                updateModalImage();
            }
        }
    }
});
</script>

@if($reservation->status->value === 'confirme')
    <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm">
        ✅ Vous avez confirmé cette proposition. Merci pour votre confiance !
    </div>
@elseif(in_array($reservation->status->value, ['annule', 'refuse']))
    <div class="mb-6 p-4 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-400 text-sm">
        ℹ️ Cette proposition a été refusée ou annulée.
    </div>
@endif

@endsection