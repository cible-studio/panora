{{-- _drawer_tech_preferences.blade.php — SM2c Phase 4 finitions.
     Drawer Préférences accessible par tap LONG sur le header T1 OU
     un bouton data-action="open-prefs" (à exposer plus tard).
     Stockage local uniquement (localStorage), pas de sync serveur. --}}
<div id="sm2c-prefs-overlay" class="sm2c-prefs-overlay" hidden aria-hidden="true">
    <aside class="sm2c-prefs-drawer" role="dialog" aria-modal="true">
        <header class="sm2c-prefs-head">
            <h2 class="sm2c-prefs-title">⚙️ Préférences</h2>
            <button type="button" class="sm2c-b3-close" data-action="close-prefs" aria-label="Fermer">✕</button>
        </header>

        <div class="sm2c-prefs-list">
            <div class="sm2c-prefs-row">
                <div class="sm2c-prefs-info">
                    <div class="sm2c-prefs-label">🔠 Texte plus gros</div>
                    <div class="sm2c-prefs-desc">Augmente la taille du texte pour une meilleure lisibilité.</div>
                </div>
                <button type="button" class="sm2c-prefs-toggle" data-pref-key="sm2c_pref_large_text" aria-label="Activer texte plus gros"></button>
            </div>

            <div class="sm2c-prefs-row">
                <div class="sm2c-prefs-info">
                    <div class="sm2c-prefs-label">🔋 Économie batterie</div>
                    <div class="sm2c-prefs-desc">Désactive les animations pour économiser la batterie sur Android Go.</div>
                </div>
                <button type="button" class="sm2c-prefs-toggle" data-pref-key="sm2c_pref_low_power" aria-label="Activer économie batterie"></button>
            </div>

            <div class="sm2c-prefs-row">
                <div class="sm2c-prefs-info">
                    <div class="sm2c-prefs-label">🚀 Sauter la confirmation "Y aller"</div>
                    <div class="sm2c-prefs-desc">Ouvre Google Maps directement sans passer par la mini-carte T7.</div>
                </div>
                <button type="button" class="sm2c-prefs-toggle" data-pref-key="sm2c_pref_no_t7_confirm" aria-label="Sauter la confirmation Y aller"></button>
            </div>

            <div class="sm2c-prefs-row" style="border-top:1px dashed var(--border);padding-top:14px">
                <div class="sm2c-prefs-info">
                    <div class="sm2c-prefs-label" style="font-weight:600;font-size:11.5px;color:var(--text3)">
                        💡 Astuce : maintiens appuyé en haut de l'écran pour revenir ici à tout moment.
                    </div>
                </div>
            </div>
        </div>
    </aside>
</div>
