{{--
    Une ligne de facturation FNE.
    Variables : $i (index, string ou int), $l (array), $communes, $rateDate.

    Utilisé deux fois :
      - Boucle PHP de rendu initial (avec $i numérique)
      - Template HTML cloné par le JS pour ajouter une ligne (avec $i = '__IDX__')
--}}
<div class="fne-line" data-idx="{{ $i }}">
    <div class="fne-line-num">{{ is_numeric($i) ? $i + 1 : 1 }}</div>

    <div>
        <select name="lines[{{ $i }}][designation_picker]" class="line-designation"></select>
        <input type="hidden" name="lines[{{ $i }}][designation]" class="line-designation-value"
               value="{{ $l['designation'] ?? '' }}">
    </div>

    <div>
        <select name="lines[{{ $i }}][commune_id]" class="line-commune" required>
            <option value=""></option>
            @foreach($communes as $c)
                <option value="{{ $c->id }}"
                        data-odp="{{ $c->ratesAt($rateDate)['odp'] }}"
                        data-tm="{{ $c->ratesAt($rateDate)['tm'] }}"
                        {{ (string) ($l['commune_id'] ?? '') === (string) $c->id ? 'selected' : '' }}>
                    {{ $c->name }}
                </option>
            @endforeach
        </select>
    </div>

    <input type="number" name="lines[{{ $i }}][dimension_m2]"  class="line-m2 line-num-input"   required min="0"   step="0.01" value="{{ $l['dimension_m2']  ?? 0 }}">
    {{-- step="1" — FCFA entier, accepte tout prix (y.c. non arrondi à 1000). --}}
    <input type="number" name="lines[{{ $i }}][pu_ht_mensuel]" class="line-pu line-num-input"   required min="0"   step="1" value="{{ $l['pu_ht_mensuel'] ?? 0 }}">
    <input type="number" name="lines[{{ $i }}][quantite]"      class="line-qte line-num-input"  required min="1"   step="1"    value="{{ $l['quantite']      ?? 1 }}">
    <input type="number" name="lines[{{ $i }}][duree_mois]"    class="line-mois line-num-input" required min="0.5" step="0.5"  value="{{ $l['duree_mois']    ?? 1 }}">

    <div class="fne-line-total">0 FCFA</div>

    <button type="button" class="fne-btn-remove fne-line-remove" title="Supprimer cette ligne" aria-label="Supprimer cette ligne">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="3 6 5 6 21 6"/>
            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
            <path d="M10 11v6M14 11v6"/>
            <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
        </svg>
    </button>
</div>
