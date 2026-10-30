{{--
    Mise à la corbeille d'un devis (2026-10-29) : fenêtre de confirmation.
    Bouton déclencheur : onclick="ouvrirCorbeilleDevis(this)" avec
    data-url (route admin.quotes.destroy), data-ref, data-titre, data-client.
--}}
<div id="devis-corbeille-modal" onclick="if (event.target === this) fermerCorbeilleDevis()"
     style="display:none;position:fixed;inset:0;z-index:10020;background:rgba(15,23,42,.45);backdrop-filter:blur(3px);align-items:center;justify-content:center;padding:16px">
    <div role="dialog" aria-modal="true" aria-labelledby="devis-corbeille-titre"
         style="background:var(--surface);border:1px solid var(--border);border-radius:14px;max-width:440px;width:100%;box-shadow:0 20px 50px rgba(0,0,0,.25);overflow:hidden">
        <div style="padding:20px 22px 6px;display:flex;gap:12px;align-items:flex-start">
            <div style="width:40px;height:40px;border-radius:10px;background:rgba(239,68,68,.12);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">🗑</div>
            <div style="min-width:0">
                <div id="devis-corbeille-titre" style="font-size:16px;font-weight:800;color:var(--text)">Mettre ce devis à la corbeille ?</div>
                <div style="margin-top:6px;font-size:13px;color:var(--text2);line-height:1.5">
                    <strong data-ref></strong> · <span data-titre></span><br>
                    <span data-client style="color:var(--text3)"></span>
                </div>
            </div>
        </div>
        <div style="padding:10px 22px 0;font-size:12.5px;color:var(--text3);line-height:1.5">
            Il disparaît des listes. L'admin peut le restaurer depuis la corbeille ; son numéro n'est jamais réattribué.
        </div>
        <form method="POST" id="devis-corbeille-form" style="padding:18px 22px 20px;display:flex;gap:10px;justify-content:flex-end">
            @csrf
            @method('DELETE')
            <button type="button" class="btn btn-ghost btn-sm" onclick="fermerCorbeilleDevis()">Annuler</button>
            <button type="submit" class="btn btn-sm" style="background:#ef4444;border-color:#ef4444;color:#fff">Mettre à la corbeille</button>
        </form>
    </div>
</div>
<script>
function ouvrirCorbeilleDevis(bouton) {
    var fenetre = document.getElementById('devis-corbeille-modal');
    // Attachée à la page entière : aucun bloc parent ne peut la masquer ou la décaler.
    if (fenetre.parentNode !== document.body) document.body.appendChild(fenetre);
    document.getElementById('devis-corbeille-form').action = bouton.dataset.url;
    fenetre.querySelector('[data-ref]').textContent = bouton.dataset.ref || '';
    fenetre.querySelector('[data-titre]').textContent = bouton.dataset.titre || '';
    fenetre.querySelector('[data-client]').textContent = bouton.dataset.client || '';
    fenetre.style.display = 'flex';
}
function fermerCorbeilleDevis() {
    document.getElementById('devis-corbeille-modal').style.display = 'none';
}
document.addEventListener('keydown', function (e) { if (e.key === 'Escape') fermerCorbeilleDevis(); });
</script>
