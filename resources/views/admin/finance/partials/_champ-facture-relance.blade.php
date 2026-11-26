{{--
    Champ « Facture » (facultatif) du formulaire « Enregistrer une relance »
    — 2026-11-26, point 14 : avant, une relance n'était jamais rattachée à
    une facture (la colonne Facture de l'historique restait vide).

    Paramètres :
      $facturesRelance : factures relançables (Invoice::STATUTS_RECLAMABLES
                         et litige), id, reference, client_id
      $champClient     : id du <select> client du même formulaire ; la liste
                         ne montre que les factures du client choisi.
--}}
@php $idChamp = 'facture-relance-' . $champClient; @endphp
<div class="fne-field">
    <label for="{{ $idChamp }}">Facture <span class="opt">— optionnel</span></label>
    <select name="invoice_id" id="{{ $idChamp }}" data-champ-client="{{ $champClient }}">
        <option value="">— Aucune facture précise —</option>
        @foreach($facturesRelance as $f)
            <option value="{{ $f->id }}" data-client="{{ $f->client_id }}">
                {{ $f->reference ?? ('#' . $f->id) }} — reste {{ number_format($f->remainingAmount(), 0, ',', ' ') }} FCFA
            </option>
        @endforeach
    </select>
</div>
@once
<script>
// Ne garde que les factures du client choisi dans le même formulaire.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('select[data-champ-client]').forEach(function (facture) {
        const client = document.getElementById(facture.dataset.champClient);
        if (!client) return;
        const filtrer = function () {
            facture.querySelectorAll('option[data-client]').forEach(function (o) {
                const visible = client.value !== '' && o.dataset.client === client.value;
                o.hidden = !visible;
                o.disabled = !visible;
            });
            const choisie = facture.selectedOptions[0];
            if (choisie && choisie.disabled) facture.value = '';
        };
        client.addEventListener('change', filtrer);
        // Les boutons « Relancer » posent le client par script, sans événement.
        facture.addEventListener('focus', filtrer);
        facture.addEventListener('mousedown', filtrer);
        filtrer();
    });
});
</script>
@endonce
