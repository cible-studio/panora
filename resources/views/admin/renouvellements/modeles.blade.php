<x-admin-layout>
    <x-slot name="title">Textes des mails clients</x-slot>

    {{--
      Éditeur des textes des mails clients avant échéance (2026-10-22).
      L'aperçu est rendu par le SERVEUR (route admin.renouvellements.apercu)
      à chaque frappe : aucune copie de la mise en forme en JavaScript.
    --}}
    @php
        $card  = 'background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 20px;margin-bottom:16px;';
        $muted = 'font-size:12px;color:var(--text3);';
    @endphp

    <div style="{{ $card }}display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
        <div style="margin-right:auto;">
            <div style="font-size:18px;font-weight:800;color:var(--text);">✏️ Textes des mails clients</div>
            <div style="{{ $muted }}margin-top:4px;">Séquence de renouvellement (campagnes de 12 mois ou plus) et mail de fin des autres campagnes. L'aperçu se met à jour pendant la saisie.</div>
        </div>
        <a href="{{ route('admin.renouvellements.index') }}" class="btn btn-ghost btn-sm">← Renouvellements</a>
    </div>

    {{-- Onglets --}}
    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;">
        @foreach($etapes as $k => $lib)
            <a href="{{ route('admin.renouvellements.modeles', ['cle' => $k, 'campagne' => $campagneId ?: null]) }}"
               style="padding:7px 12px;border-radius:10px;font-size:12.5px;font-weight:700;text-decoration:none;{{ $k === $cle ? 'background:var(--accent);color:#fff;' : 'background:var(--surface);color:var(--text2);border:1px solid var(--border);' }}">{{ $lib }}</a>
        @endforeach
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:16px;align-items:start;">

        {{-- ═══ ÉDITION ═══ --}}
        <div style="{{ $card }}margin:0;">
            <form method="POST" action="{{ route('admin.renouvellements.modeles.update', $cle) }}" id="form-modele">
                @csrf
                <input type="hidden" name="campagne" value="{{ $campagneId ?: '' }}" id="champ-campagne-form">

                <label style="font-size:12px;font-weight:800;color:var(--text2);">Objet</label>
                <input type="text" name="objet" id="objet" value="{{ old('objet', $modele['objet']) }}" maxlength="255" required
                       class="filter-input" style="width:100%;height:38px;margin:4px 0 12px;">

                <label style="font-size:12px;font-weight:800;color:var(--text2);">Texte</label>
                <textarea name="corps" id="corps" rows="22" maxlength="10000" required class="filter-input"
                          style="width:100%;margin-top:4px;font-family:ui-monospace,Consolas,monospace;font-size:13px;line-height:1.55;">{{ old('corps', $modele['corps']) }}</textarea>

                <div style="{{ $muted }}margin:8px 0 6px;line-height:1.6;">
                    Une ligne vide = nouveau paragraphe · <code>**texte**</code> = gras · lignes commençant par <code>- </code> = liste.
                    La signature et le lien de désinscription sont ajoutés automatiquement. Cliquez sur un champ pour l'insérer :
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:5px;">
                    @foreach($champs as $champ => $aide)
                        <button type="button" class="btn btn-ghost btn-sm" title="{{ $aide }}" style="font-size:11.5px;padding:3px 8px;"
                                onclick="MODELES.inserer(@js($champ))">{{ $champ }}</button>
                    @endforeach
                </div>

                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:16px;align-items:center;">
                    <button class="btn btn-primary">Enregistrer</button>
                    @if($modele['modifie'])
                        <button class="btn btn-ghost" name="retablir" value="1" formnovalidate
                                onclick="return confirm('Revenir au texte d\'origine ? Le texte modifié sera perdu.')">Rétablir le texte d'origine</button>
                    @endif
                    <span style="{{ $muted }}">
                        @if($ligne)
                            Modifié le {{ $ligne->updated_at->format('d/m/Y à H\hi') }}@if($ligne->auteur) par {{ $ligne->auteur->name }}@endif.
                        @else
                            Texte d'origine.
                        @endif
                    </span>
                </div>
            </form>
        </div>

        {{-- ═══ APERÇU ═══ --}}
        <div style="{{ $card }}margin:0;position:sticky;top:12px;">
            <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px;">
                <label style="font-size:12px;font-weight:800;color:var(--text2);">Aperçu avec</label>
                <select id="campagne" class="filter-select" style="height:34px;flex:1;min-width:200px;">
                    <option value="">Exemple fictif (Société Exemple)</option>
                    @foreach($campagnes as $c)
                        <option value="{{ $c->id }}" @selected($campagneId === $c->id)>{{ $c->client?->name ?? '—' }} — {{ $c->name }} (fin {{ $c->end_date->format('d/m/Y') }})</option>
                    @endforeach
                </select>
                <span id="etat-apercu" style="{{ $muted }}min-width:70px;"></span>
            </div>
            <iframe id="apercu" title="Aperçu du mail" style="width:100%;height:720px;border:1px solid var(--border);border-radius:10px;background:#fff;"></iframe>

            <form method="POST" action="{{ route('admin.renouvellements.tester', $cle) }}" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:12px;"
                  onsubmit="document.getElementById('champ-campagne-test').value = document.getElementById('campagne').value; if (!document.getElementById('campagne').value) { alert('Choisissez une campagne réelle pour l\'envoi de test.'); return false; } return confirm('Envoyer ce mail (texte enregistré) aux adresses de test ?');">
                @csrf
                <input type="hidden" name="campagne" id="champ-campagne-test">
                <button class="btn btn-ghost btn-sm">✉️ Envoyer un test</button>
                <span style="{{ $muted }}">Part vers la liste Brevo « Tests internes », avec le texte <strong>enregistré</strong>. Jamais au client.</span>
            </form>
        </div>
    </div>

    <script>
    const MODELES = {
        minuteur: null,
        actualiser() {
            clearTimeout(this.minuteur);
            this.minuteur = setTimeout(() => this.charger(), 350);
        },
        async charger() {
            const etat = document.getElementById('etat-apercu');
            etat.textContent = 'Mise à jour…';
            const donnees = new FormData();
            donnees.append('_token', @js(csrf_token()));
            donnees.append('cle', @js($cle));
            donnees.append('objet', document.getElementById('objet').value);
            donnees.append('corps', document.getElementById('corps').value);
            donnees.append('campagne', document.getElementById('campagne').value);
            try {
                const r = await fetch(@js(route('admin.renouvellements.apercu')), { method: 'POST', body: donnees, headers: { 'Accept': 'text/html' } });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                document.getElementById('apercu').srcdoc = await r.text();
                etat.textContent = '';
            } catch (e) {
                etat.textContent = 'Aperçu indisponible';
            }
        },
        inserer(champ) {
            const t = document.getElementById('corps');
            const debut = t.selectionStart ?? t.value.length, fin = t.selectionEnd ?? t.value.length;
            t.value = t.value.slice(0, debut) + champ + t.value.slice(fin);
            t.focus();
            t.selectionStart = t.selectionEnd = debut + champ.length;
            this.actualiser();
        },
    };
    ['objet', 'corps'].forEach(id => document.getElementById(id).addEventListener('input', () => MODELES.actualiser()));
    document.getElementById('campagne').addEventListener('change', (e) => {
        document.getElementById('champ-campagne-form').value = e.target.value;
        MODELES.charger();
    });
    MODELES.charger();
    </script>
</x-admin-layout>
