{{-- _modal_report.blade.php — Phase 2 SM1 (rendu pixel-identique).
     Modale de signalement terrain (9 motifs DelayReason) + le <style>
     local qui contient :
       - règles modal #ts-report-modal + animations tsUp / fadeIn
       - règles overlay succès #ts-success (.ts-check / .ts-msg) — couplé
         avec le workflow signalement (toast après envoi)
       - règles .actions .btn min-height génériques mobile-friendly
       - règles .btn-report-sm (bouton report dans hero next-pose)

     L'extraction préserve ces 3 ensembles dans le même <style> pour ne
     pas perturber l'ordre de cascade CSS (les règles plus spécifiques
     restent après les générales — comportement strictement identique).

     Aucune variable Blade explicite — DelayReason::cases() lu dans le partial.
     IDs critiques pour le JS (lignes 2163-2278 du <script>) :
       #ts-report-modal, #ts-report-ref, #ts-report-note, #ts-report-photo,
       #ts-report-photo-label, #ts-report-photo-label-text, #ts-report-cancel,
       #ts-report-send, classes .ts-report-opt + [data-type=]. --}}
{{-- Modal "Signaler un problème" --}}
<div id="ts-report-modal" aria-hidden="true">
    <div class="ts-report-card">
        <h3>⚠️ Tu as un souci ?</h3>
        <p class="ts-report-sub" id="ts-report-ref">Touche le souci. Le bureau sera prévenu tout de suite.</p>
        {{-- 9 motifs centralisés dans App\Enums\DelayReason — Module 3 SLA enrichi --}}
        <div class="ts-report-opts">
            @foreach(\App\Enums\DelayReason::cases() as $motif)
                <button type="button" class="ts-report-opt" data-type="{{ $motif->value }}">{{ $motif->icon() }} {{ $motif->label() }}</button>
            @endforeach
        </div>
        <textarea id="ts-report-note" placeholder="Tu peux écrire des détails (pas obligé)…"></textarea>
        <label class="ts-report-photo-btn" id="ts-report-photo-label">
            <input type="file" id="ts-report-photo" accept="image/*" capture="environment" hidden>
            <span id="ts-report-photo-label-text">📷 Ajouter une photo (pas obligé)</span>
        </label>
        <div class="ts-report-actions">
            <button type="button" class="ts-btn-ghost" id="ts-report-cancel">Annuler</button>
            <button type="button" class="ts-btn-send" id="ts-report-send" disabled>Envoyer au bureau</button>
        </div>
    </div>
</div>

<style>
    /* UX "sans lecture" : actions plus grosses */
    .actions .btn { min-height: 52px; font-size: 16px; }
    .btn-report-sm {
        width:100%; margin-top:8px; min-height:46px;
        background:rgba(217,119,6,.10); color:#b45309;
        border:1px solid rgba(217,119,6,.30); border-radius:12px;
        font-weight:700; cursor:pointer;
    }
    .btn-report-sm:active { transform: translateY(1px); }
    /* Overlay succès */
    #ts-success {
        position:fixed; inset:0; z-index:9999; display:none;
        flex-direction:column; align-items:center; justify-content:center; gap:16px;
        background:rgba(22,163,74,.97); color:#fff;
    }
    #ts-success.show { display:flex; animation:tsFade .2s ease; }
    @keyframes tsFade { from{opacity:0} to{opacity:1} }
    .ts-check svg { width:120px; height:120px; }
    .ts-check circle { stroke:#fff; stroke-width:3; stroke-dasharray:151; stroke-dashoffset:151; animation:tsC .5s ease forwards; }
    .ts-check path { stroke:#fff; stroke-width:4; stroke-linecap:round; stroke-linejoin:round; stroke-dasharray:40; stroke-dashoffset:40; animation:tsK .35s .35s ease forwards; }
    @keyframes tsC { to{stroke-dashoffset:0} }
    @keyframes tsK { to{stroke-dashoffset:0} }
    .ts-msg { font-size:23px; font-weight:800; }
    /* Modal report */
    #ts-report-modal {
        position:fixed; inset:0; z-index:9998; display:none;
        align-items:flex-end; justify-content:center; background:rgba(15,23,42,.55); padding:0;
    }
    #ts-report-modal.show { display:flex; }
    .ts-report-card {
        background:#fff; width:100%; max-width:520px; border-radius:18px 18px 0 0;
        padding:20px 18px calc(18px + env(safe-area-inset-bottom)); animation:tsUp .25s ease;
    }
    @keyframes tsUp { from{transform:translateY(40px);opacity:.5} to{transform:translateY(0);opacity:1} }
    .ts-report-card h3 { font-size:18px; margin:0 0 4px; }
    .ts-report-sub { font-size:13px; color:#475569; margin:0 0 14px; }
    .ts-report-opts { display:flex; flex-direction:column; gap:8px; }
    .ts-report-opt {
        text-align:left; padding:14px; min-height:52px;
        background:#f6f7f9; border:1.5px solid #e8eaee; border-radius:12px;
        font-size:15px; font-weight:600; color:#0f172a; cursor:pointer;
    }
    .ts-report-opt.sel { border-color:#d97706; background:rgba(217,119,6,.10); color:#b45309; }
    #ts-report-note { width:100%; margin-top:10px; min-height:64px; padding:10px 12px; border:1px solid #e8eaee; border-radius:12px; font:inherit; font-size:14px; resize:vertical; }
    .ts-report-photo-btn {
        display:flex; align-items:center; justify-content:center; gap:8px;
        margin-top:10px; min-height:46px; padding:0 14px;
        background:rgba(59,130,246,.08); border:1.5px dashed rgba(59,130,246,.4);
        color:#2563eb; border-radius:12px;
        font-size:13px; font-weight:700; cursor:pointer;
    }
    .ts-report-photo-btn.has-file {
        background:rgba(34,197,94,.08); border-color:rgba(34,197,94,.45); color:#16a34a;
        border-style:solid;
    }
    .ts-report-actions { display:flex; gap:10px; margin-top:14px; }
    .ts-btn-ghost { flex:1; min-height:50px; background:#f1f5f9; border:none; border-radius:12px; font-weight:700; font-size:15px; cursor:pointer; }
    .ts-btn-send { flex:2; min-height:50px; background:#d97706; color:#fff; border:none; border-radius:12px; font-weight:800; font-size:15px; cursor:pointer; }
    .ts-btn-send:disabled { opacity:.5; }
</style>
