{{--

  USAGE dans show.blade.php — ajouter où tu veux les actions :
  @include('admin.reservations.partials.proposition-actions', ['reservation' => $reservation])

  Ce partial gère :
  - Bouton "Envoyer proposition" (si en_attente + email client)
  - Statut de la proposition (envoyée, vue, expirée)
  - Bouton "Renvoyer" / "Réinitialiser"
  - Lien de copie du lien client
--}}

{{--
  COMPOSANT PROFESSIONNEL : Proposition commerciale
  Usage : @include('admin.reservations.partials.proposition-actions', ['reservation' => $reservation])
--}}

@if($reservation->status->value === 'en_attente')
<div class="proposition-card" id="proposition-card-{{ $reservation->id }}">
    
    {{-- En-tête --}}
    <div class="proposition-header">
        <div class="proposition-title">
            <div class="proposition-icon">📋</div>
            <div>
                <h3>Proposition Commerciale</h3>
                <p>Envoyez un lien sécurisé au client pour qu'il confirme ou refuse</p>
            </div>
        </div>
        
        @if($reservation->proposition_token)
            @include('admin.reservations.partials.proposition-badge', ['reservation' => $reservation])
        @endif
    </div>

    {{-- Informations actives --}}
    @if($reservation->proposition_token && !$reservation->proposition_expires_at?->isPast())
    <div class="proposition-info">
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Envoyée le</span>
                <strong class="info-value">{{ $reservation->proposition_sent_at?->format('d/m/Y à H:i') ?? '—' }}</strong>
            </div>
            @if($reservation->proposition_viewed_at)
            <div class="info-item">
                <span class="info-label">Vue le</span>
                <strong class="info-value viewed">{{ $reservation->proposition_viewed_at->format('d/m/Y à H:i') }}</strong>
            </div>
            @endif
            @if($reservation->proposition_expires_at)
            <div class="info-item">
                <span class="info-label">Expire le</span>
                <strong class="info-value {{ $reservation->proposition_expires_at->diffInHours() < 24 ? 'expiring-soon' : '' }}">
                    {{ $reservation->proposition_expires_at->format('d/m/Y') }}
                    <span class="info-sub">({{ $reservation->proposition_expires_at->diffForHumans() }})</span>
                </strong>
            </div>
            @endif
        </div>

        {{-- Lien client --}}
        @if($reservation->proposition_slug)
        @php
            $propUrl = route('proposition.show', [
                $reservation->reference,
                $reservation->proposition_slug
            ]);
        @endphp
        <div class="client-link-container">
            <span class="info-label">🔗 Lien client</span>
            <div class="client-link-actions">
                <input type="text" 
                       value="{{ $propUrl }}" 
                       readonly 
                       id="prop-link-{{ $reservation->id }}" 
                       class="client-link-input">
                <button type="button" 
                        class="btn-copy" 
                        onclick="PropositionActions.copyLink('prop-link-{{ $reservation->id }}', this)">
                    📋 Copier
                </button>
                <a href="{{ $propUrl }}" 
                   target="_blank" 
                   class="btn-view">
                    👁️ Voir
                </a>
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- Alerte email manquant --}}
    @if(empty($reservation->client?->email))
    <div class="alert-warning">
        <span>⚠️</span>
        <div>
            <strong>Email client manquant</strong>
            <p>Le client <strong>{{ $reservation->client?->name }}</strong> n'a pas d'adresse email enregistrée.</p>
        </div>
        <a href="{{ route('admin.clients.edit', $reservation->client_id) }}" class="alert-link">
            Mettre à jour →
        </a>
    </div>
    @endif

    {{-- Actions --}}
    <div class="proposition-actions">
        @if(!empty($reservation->client?->email))
            @if($reservation->proposition_sent_at)
                <button type="button" 
                        class="btn-primary btn-resend"
                        onclick="PropositionActions.confirmResend({{ $reservation->id }})">
                    📧 Renvoyer la proposition
                </button>
            @else
                <button type="button" 
                        class="btn-primary"
                        onclick="PropositionActions.confirmSend({{ $reservation->id }})">
                    📧 Envoyer la proposition
                </button>
            @endif
        @endif

        @if($reservation->proposition_token)
            <button type="button" 
                    class="btn-secondary"
                    onclick="PropositionActions.confirmReset({{ $reservation->id }})">
                🔄 Réinitialiser le lien
            </button>
        @endif

        @if(!empty($reservation->client?->email))
            <span class="client-email">→ {{ $reservation->client->email }}</span>
        @endif
    </div>
</div>

{{-- Styles du composant --}}
<style>
.proposition-card {
    background: rgba(232, 160, 32, 0.04);
    border: 1px solid rgba(232, 160, 32, 0.15);
    border-radius: 16px;
    padding: 20px 24px;
    margin: 20px 0;
    transition: all 0.2s ease;
}

.proposition-card:hover {
    border-color: rgba(232, 160, 32, 0.3);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.proposition-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    flex-wrap: wrap;
    gap: 8px;
}

.proposition-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.proposition-icon {
    font-size: 24px;
    background: rgba(232, 160, 32, 0.1);
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
}

.proposition-title h3 {
    font-size: 15px;
    font-weight: 700;
    color: #e8a020;
    margin: 0;
}

.proposition-title p {
    font-size: 12px;
    color: #64748b;
    margin: 2px 0 0;
}

.proposition-info {
    background: rgba(0, 0, 0, 0.15);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16px;
}

.info-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 16px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.info-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
}

.info-value {
    font-size: 13px;
    color: #e2e8f0;
}

.info-value.viewed {
    color: #86efac;
}

.info-value.expiring-soon {
    color: #fca5a5;
}

.info-sub {
    font-size: 11px;
    opacity: 0.7;
    margin-left: 4px;
}

.client-link-container {
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    padding-top: 14px;
    margin-top: 4px;
}

.client-link-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
}

.client-link-input {
    flex: 1;
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 11px;
    font-family: monospace;
    color: #94a3b8;
    min-width: 0;
    transition: all 0.2s;
}

.client-link-input:focus {
    outline: none;
    border-color: #e8a020;
    color: #e2e8f0;
}

.btn-copy, .btn-view {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    white-space: nowrap;
}

.btn-copy {
    background: rgba(232, 160, 32, 0.1);
    border: 1px solid rgba(232, 160, 32, 0.2);
    color: #e8a020;
}

.btn-copy:hover {
    background: rgba(232, 160, 32, 0.2);
    border-color: #e8a020;
}

.btn-view {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.2);
    color: #93c5fd;
}

.btn-view:hover {
    background: rgba(59, 130, 246, 0.2);
    border-color: #3b82f6;
}

.alert-warning {
    background: rgba(239, 68, 68, 0.08);
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 12px;
}

.alert-warning span {
    font-size: 18px;
}

.alert-warning p {
    margin: 0;
    color: #fca5a5;
}

.alert-link {
    color: #fca5a5;
    text-decoration: underline;
    margin-left: auto;
    white-space: nowrap;
}

.proposition-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}

.btn-primary, .btn-secondary {
    font-weight: 700;
    font-size: 13px;
    padding: 10px 24px;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: #e8a020;
    color: #0b0e17;
}

.btn-primary:hover {
    background: #f0b33a;
    transform: translateY(-1px);
}

.btn-primary:active {
    transform: translateY(0);
}

.btn-secondary {
    background: transparent;
    color: #94a3b8;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.2);
    color: #e2e8f0;
}

.client-email {
    font-size: 11px;
    color: #64748b;
    margin-left: auto;
}

/* Animation */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.btn-primary:disabled,
.btn-secondary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}
</style>

<script>
// ════════════════════════════════════════════════════════════════
// PROPOSITION ACTIONS - Système professionnel de modales
// ════════════════════════════════════════════════════════════════
window.PropositionActions = {
    csrf: document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
    
    // ── Confirmer l'envoi ─────────────────────────────────────
    confirmSend(reservationId) {
        this._showModal({
            title: '📧 Envoyer la proposition',
            message: 'Un email sera envoyé au client avec un lien sécurisé pour consulter et valider la proposition.',
            details: 'Le lien sera valable 7 jours et sera accessible uniquement au client.',
            type: 'confirm',
            confirmText: 'Envoyer',
            confirmClass: 'btn-primary',
            onConfirm: () => this._sendProposition(reservationId, 'send')
        });
    },

    // ── Confirmer le renvoi ───────────────────────────────────
    confirmResend(reservationId) {
        this._showModal({
            title: '🔄 Renvoyer la proposition',
            message: 'Une nouvelle proposition sera envoyée au client.',
            details: '⚠️ Le lien actuel sera révoqué et un nouveau sera généré.',
            type: 'warning',
            confirmText: 'Renvoyer',
            confirmClass: 'btn-warning',
            onConfirm: () => this._sendProposition(reservationId, 'resend')
        });
    },

    // ── Confirmer la réinitialisation ─────────────────────────
    confirmReset(reservationId) {
        this._showModal({
            title: '🔄 Réinitialiser le lien',
            message: 'Êtes-vous sûr de vouloir réinitialiser le lien de proposition ?',
            details: '⚠️ Le lien actuel ne fonctionnera plus. Un nouveau sera généré, mais l\'email ne sera pas renvoyé automatiquement.',
            type: 'danger',
            confirmText: 'Réinitialiser',
            confirmClass: 'btn-danger',
            onConfirm: () => this._resetProposition(reservationId)
        });
    },

    // ── Envoi de la proposition ───────────────────────────────
    async _sendProposition(reservationId, action) {
        this._setLoading(true);
        
        try {
            const response = await fetch(`/admin/reservations/${reservationId}/proposition/${action === 'send' ? 'envoyer' : 'renvoyer'}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();

            if (data.success) {
                this._showToast(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                this._showToast(data.message || 'Une erreur est survenue.', 'error');
            }
        } catch (error) {
            console.error('Erreur:', error);
            this._showToast('Erreur de connexion. Veuillez réessayer.', 'error');
        } finally {
            this._setLoading(false);
        }
    },

    // ── Réinitialisation ──────────────────────────────────────
    async _resetProposition(reservationId) {
        this._setLoading(true);
        
        try {
            const response = await fetch(`/admin/reservations/${reservationId}/proposition/reinitialiser`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();

            if (data.success) {
                this._showToast(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                this._showToast(data.message || 'Une erreur est survenue.', 'error');
            }
        } catch (error) {
            console.error('Erreur:', error);
            this._showToast('Erreur de connexion. Veuillez réessayer.', 'error');
        } finally {
            this._setLoading(false);
        }
    },

    // ── Copier le lien ────────────────────────────────────────
    copyLink(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input) return;

        const originalText = btn.innerHTML;
        
        navigator.clipboard.writeText(input.value).then(() => {
            btn.innerHTML = '✅ Copié !';
            btn.classList.add('copied');
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.classList.remove('copied');
            }, 2000);
        }).catch(() => {
            input.select();
            document.execCommand('copy');
            btn.innerHTML = '✅ Copié !';
            setTimeout(() => btn.innerHTML = originalText, 2000);
        });
    },

    // ── Affichage modal (système unifié) ──────────────────────
    _showModal(options) {
        // Supprimer modal existante
        const existingModal = document.getElementById('proposition-modal');
        if (existingModal) existingModal.remove();

        const modal = document.createElement('div');
        modal.id = 'proposition-modal';
        modal.className = 'proposition-modal';
        modal.innerHTML = `
            <div class="proposition-modal-overlay"></div>
            <div class="proposition-modal-container ${options.type}">
                <div class="proposition-modal-header">
                    <div class="proposition-modal-icon">${this._getIcon(options.type)}</div>
                    <h3 class="proposition-modal-title">${options.title}</h3>
                    <button class="proposition-modal-close" onclick="PropositionActions._closeModal()">✕</button>
                </div>
                <div class="proposition-modal-body">
                    <p class="proposition-modal-message">${options.message}</p>
                    ${options.details ? `<p class="proposition-modal-details">${options.details}</p>` : ''}
                </div>
                <div class="proposition-modal-footer">
                    <button class="proposition-modal-btn proposition-modal-btn-cancel" onclick="PropositionActions._closeModal()">
                        Annuler
                    </button>
                    <button class="proposition-modal-btn ${options.confirmClass}" id="proposition-modal-confirm">
                        ${options.confirmText}
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        document.body.style.overflow = 'hidden';
        
        // Animation d'entrée
        setTimeout(() => modal.classList.add('active'), 10);

        // Gestionnaire de confirmation
        const confirmBtn = document.getElementById('proposition-modal-confirm');
        confirmBtn.onclick = () => {
            this._closeModal();
            if (options.onConfirm) options.onConfirm();
        };

        // Fermer avec Escape
        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                this._closeModal();
                document.removeEventListener('keydown', handleEscape);
            }
        };
        document.addEventListener('keydown', handleEscape);
    },

    _closeModal() {
        const modal = document.getElementById('proposition-modal');
        if (modal) {
            modal.classList.remove('active');
            setTimeout(() => {
                modal.remove();
                document.body.style.overflow = '';
            }, 300);
        }
    },

    _getIcon(type) {
        switch(type) {
            case 'confirm': return '📧';
            case 'warning': return '⚠️';
            case 'danger': return '⚠️';
            default: return '📋';
        }
    },

    _setLoading(isLoading) {
        const buttons = document.querySelectorAll('.btn-primary, .btn-secondary');
        buttons.forEach(btn => {
            btn.disabled = isLoading;
        });
    },

    // ── Toast notification ────────────────────────────────────
    _showToast(message, type) {
        const colors = { 
            success: '#22c55e', 
            error: '#ef4444', 
            warning: '#f97316' 
        };
        
        const toast = document.createElement('div');
        toast.className = `proposition-toast ${type}`;
        toast.innerHTML = `
            <div class="toast-icon">${type === 'success' ? '✅' : type === 'error' ? '❌' : '⚠️'}</div>
            <div class="toast-message">${message}</div>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }
};

// Styles modals et toasts
(function addGlobalStyles() {
    if (document.getElementById('proposition-global-styles')) return;
    
    const style = document.createElement('style');
    style.id = 'proposition-global-styles';
    style.textContent = `
        /* Modal styles */
        .proposition-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            visibility: hidden;
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .proposition-modal.active {
            visibility: visible;
            opacity: 1;
        }
        
        .proposition-modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
        }
        
        .proposition-modal-container {
            position: relative;
            background: var(--surface, #1e293b);
            border-radius: 20px;
            width: 90%;
            max-width: 480px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            transform: scale(0.95);
            transition: transform 0.3s ease;
            border: 1px solid var(--border, #334155);
        }
        
        .proposition-modal.active .proposition-modal-container {
            transform: scale(1);
        }
        
        .proposition-modal-container.confirm { border-top: 3px solid #3b82f6; }
        .proposition-modal-container.warning { border-top: 3px solid #f97316; }
        .proposition-modal-container.danger { border-top: 3px solid #ef4444; }
        
        .proposition-modal-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border, #334155);
        }
        
        .proposition-modal-icon {
            font-size: 28px;
        }
        
        .proposition-modal-title {
            flex: 1;
            font-size: 16px;
            font-weight: 700;
            margin: 0;
            color: var(--text, #e2e8f0);
        }
        
        .proposition-modal-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: var(--text3, #64748b);
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .proposition-modal-close:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text, #e2e8f0);
        }
        
        .proposition-modal-body {
            padding: 24px;
        }
        
        .proposition-modal-message {
            font-size: 14px;
            margin: 0 0 8px;
            color: var(--text, #e2e8f0);
            line-height: 1.5;
        }
        
        .proposition-modal-details {
            font-size: 12px;
            color: var(--text3, #64748b);
            margin: 0;
            background: rgba(0, 0, 0, 0.2);
            padding: 10px 12px;
            border-radius: 8px;
        }
        
        .proposition-modal-footer {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding: 16px 24px 24px;
        }
        
        .proposition-modal-btn {
            padding: 10px 24px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }
        
        .proposition-modal-btn-cancel {
            background: var(--surface2, #0f172a);
            color: var(--text2, #94a3b8);
            border: 1px solid var(--border, #334155);
        }
        
        .proposition-modal-btn-cancel:hover {
            background: var(--surface3, #1e293b);
            color: var(--text, #e2e8f0);
        }
        
        .btn-primary {
            background: #e8a020;
            color: #0b0e17;
        }
        
        .btn-primary:hover {
            background: #f0b33a;
        }
        
        .btn-warning {
            background: #f97316;
            color: #fff;
        }
        
        .btn-warning:hover {
            background: #fb923c;
        }
        
        .btn-danger {
            background: #ef4444;
            color: #fff;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        /* Toast styles */
        .proposition-toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 100000;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            background: var(--surface, #1e293b);
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            transform: translateX(400px);
            transition: transform 0.3s ease;
            border: 1px solid var(--border, #334155);
            max-width: 380px;
        }
        
        .proposition-toast.show {
            transform: translateX(0);
        }
        
        .proposition-toast.success { border-left: 3px solid #22c55e; }
        .proposition-toast.error { border-left: 3px solid #ef4444; }
        .proposition-toast.warning { border-left: 3px solid #f97316; }
        
        .toast-icon { font-size: 18px; }
        .toast-message { font-size: 13px; color: var(--text, #e2e8f0); line-height: 1.4; }
        
        .btn-copy.copied {
            background: #22c55e;
            color: #fff;
            border-color: #22c55e;
        }
    `;
    document.head.appendChild(style);
})();
</script>
@endif