<x-admin-layout>
    <x-slot name="title">Textes des mails clients</x-slot>

    {{--
      Éditeur des textes des mails clients avant échéance (refonte 2026-10-23).
      L'aperçu est rendu par le SERVEUR (route admin.renouvellements.apercu)
      à chaque frappe : aucune copie de la mise en forme en JavaScript.
    --}}
    @include('admin.renouvellements._styles')

    @php
        $groupes = [
            'Séquence · 12 mois et plus' => ['j60' => ['J-60', 'Première annonce'], 'j45' => ['J-45', 'Relance'], 'j30' => ['J-30', 'Date limite'], 'j15' => ['J-15', 'Disponibilité non garantie'], 'j7' => ['J-7', 'Dernier rappel']],
            'Après une réponse' => ['accuse' => ['✓', 'Accusé de réception']],
            'Moins de 12 mois' => ['fin' => ['J-7', 'Fin de campagne']],
        ];
        $champsSimples = array_filter($champs, fn($k) => !in_array($k, ['[Récapitulatif]', '[Boutons]'], true), ARRAY_FILTER_USE_KEY);
    @endphp

    <style>
        .ed-grille { display:grid; grid-template-columns:230px minmax(0,1fr) minmax(0,1fr); gap:16px; align-items:start; }
        @media (max-width: 1280px) { .ed-grille { grid-template-columns:220px minmax(0,1fr); } .ed-apercu { grid-column:1 / -1; } }
        @media (max-width: 860px)  { .ed-grille { grid-template-columns:minmax(0,1fr); } }
        .ed-menu { padding:10px; position:sticky; top:12px; }
        .ed-menu .grp { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.5px; color:var(--text3); margin:12px 8px 6px; }
        .ed-menu .grp:first-child { margin-top:4px; }
        .ed-item { display:flex; align-items:center; gap:10px; padding:9px 10px; border-radius:10px; text-decoration:none; color:var(--text2); font-size:13.5px; font-weight:600; }
        .ed-item:hover { background:var(--surface2); }
        .ed-item.on { background:var(--accent); color:#fff; }
        .ed-item .j { font-weight:800; font-size:12px; width:34px; flex-shrink:0; }
        .ed-item .m { margin-left:auto; width:7px; height:7px; border-radius:50%; background:#fab80b; }
        .ed-carte { padding:18px 20px; }
        .ed-outils { display:flex; flex-wrap:wrap; gap:6px; align-items:center; margin:12px 0 6px; }
        .ed-outil { height:30px; min-width:30px; padding:0 9px; border-radius:8px; border:1px solid var(--border); background:var(--surface); color:var(--text); font-size:12.5px; font-weight:700; cursor:pointer; }
        .ed-outil:hover { border-color:var(--accent); }
        .ed-texte { width:100%; min-height:460px; border:1px solid var(--border); border-radius:12px; background:var(--surface); color:var(--text); font-family:ui-monospace,Consolas,monospace; font-size:13px; line-height:1.6; padding:12px 14px; resize:vertical; }
        .ed-ecran { background:var(--surface2); border-radius:12px; padding:12px; display:flex; justify-content:center; }
        .ed-ecran iframe { width:100%; height:720px; border:0; border-radius:10px; background:#fff; transition:width .2s; }
        .ed-ecran.mobile iframe { width:390px; max-width:100%; }
    </style>

    <div x-data="{ aide: false }">
        <div class="rn-card rn-head" style="margin-bottom:16px;">
            <div>
                <h1 class="rn-title">✏️ Textes des mails clients</h1>
                <div class="rn-sub">Les modifications s'appliquent dès les prochains envois. L'aperçu se met à jour pendant la saisie.</div>
            </div>
            <div class="rn-actions">
                <button type="button" class="rn-btn" @click="aide = !aide">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    Comment écrire
                </button>
                <a href="{{ route('admin.renouvellements.index') }}" class="rn-btn">← Renouvellements</a>
            </div>
        </div>

        <div x-show="aide" x-transition class="rn-card" style="padding:16px 20px;margin-bottom:16px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;font-size:13px;color:var(--text2);line-height:1.6;">
                <div><strong style="color:var(--text);">Paragraphes</strong><br>Une ligne vide sépare deux paragraphes.</div>
                <div><strong style="color:var(--text);">Gras</strong><br><code>**texte**</code>, ou sélectionnez puis bouton <strong>G</strong>.</div>
                <div><strong style="color:var(--text);">Liste</strong><br>Lignes qui commencent par <code>- </code>, ou bouton <strong>• Liste</strong>.</div>
                <div><strong style="color:var(--text);">Informations du client</strong><br>Menu « Insérer un champ » : nom, campagne, dates…</div>
                <div><strong style="color:var(--text);">Encadré et boutons</strong><br><code>[Récapitulatif]</code> et <code>[Boutons]</code>, chacun seul sur sa ligne.</div>
                <div><strong style="color:var(--text);">Ajouté automatiquement</strong><br>La signature, le liseré de la marque et le lien de désinscription.</div>
            </div>
        </div>

        <div class="ed-grille">

            {{-- ═══ LISTE DES MAILS ═══ --}}
            <nav class="rn-card ed-menu">
                @foreach($groupes as $groupe => $items)
                    <div class="grp">{{ $groupe }}</div>
                    @foreach($items as $k => [$j, $lib])
                        <a href="{{ route('admin.renouvellements.modeles', ['cle' => $k, 'campagne' => $campagneId ?: null]) }}" class="ed-item {{ $k === $cle ? 'on' : '' }}">
                            <span class="j">{{ $j }}</span>{{ $lib }}
                            @if(in_array($k, $modifies, true))<span class="m" title="Texte modifié"></span>@endif
                        </a>
                    @endforeach
                @endforeach
            </nav>

            {{-- ═══ ÉDITION ═══ --}}
            <div class="rn-card ed-carte">
                <form method="POST" action="{{ route('admin.renouvellements.modeles.update', $cle) }}">
                    @csrf
                    <input type="hidden" name="campagne" value="{{ $campagneId ?: '' }}" id="champ-campagne-form">

                    <label class="rn-champ" style="margin-top:0;">Objet</label>
                    <input type="text" name="objet" id="objet" value="{{ old('objet', $modele['objet']) }}" maxlength="191" required class="rn-input">

                    <div class="ed-outils">
                        <select class="rn-select" style="height:30px;font-size:12.5px;" onchange="if (this.value) { EDITEUR.inserer(this.value); this.value=''; }">
                            <option value="">+ Insérer un champ…</option>
                            @foreach($champsSimples as $champ => $aideChamp)
                                <option value="{{ $champ }}">{{ trim($champ, '[]') }} — {{ $aideChamp }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="ed-outil" title="Gras" @click="EDITEUR.entourer('**')"><strong>G</strong></button>
                        <button type="button" class="ed-outil" title="Liste" @click="EDITEUR.liste()">• Liste</button>
                        <button type="button" class="ed-outil" title="Encadré de la campagne" @click="EDITEUR.bloc('[Récapitulatif]')">▤ Encadré</button>
                        @if(in_array($cle, ['j60', 'j45', 'j30', 'j15', 'j7'], true))
                            <button type="button" class="ed-outil" title="Boutons de réponse" @click="EDITEUR.bloc('[Boutons]')">▣ Boutons</button>
                        @endif
                    </div>
                    <textarea name="corps" id="corps" maxlength="10000" required class="ed-texte">{{ old('corps', $modele['corps']) }}</textarea>

                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;align-items:center;">
                        <button class="rn-btn primaire">Enregistrer</button>
                        @if($modele['modifie'])
                            <button class="rn-btn" name="retablir" value="1" formnovalidate
                                    onclick="return confirm('Revenir au texte d\'origine ? Le texte modifié sera perdu.')">Rétablir l'original</button>
                        @endif
                        <span class="rn-muted" style="margin-left:auto;">
                            @if($ligne)
                                Modifié le {{ $ligne->updated_at->format('d/m/Y') }}@if($ligne->auteur) par {{ $ligne->auteur->name }}@endif
                            @else
                                Texte d'origine
                            @endif
                        </span>
                    </div>
                </form>
            </div>

            {{-- ═══ APERÇU ═══ --}}
            <div class="rn-card ed-carte ed-apercu" x-data="{ mobile: false }">
                <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px;">
                    <select id="campagne" class="rn-select" style="flex:1;min-width:200px;" title="Campagne utilisée pour l'aperçu">
                        <option value="">Exemple fictif</option>
                        @foreach($campagnes as $c)
                            <option value="{{ $c->id }}" @selected($campagneId === $c->id)>{{ $c->client?->name ?? '—' }} — {{ $c->name }}</option>
                        @endforeach
                    </select>
                    <div class="rn-seg">
                        <a href="#" :class="!mobile && 'on'" @click.prevent="mobile = false">💻 Ordinateur</a>
                        <a href="#" :class="mobile && 'on'" @click.prevent="mobile = true">📱 Mobile</a>
                    </div>
                    <span id="etat-apercu" class="rn-muted"></span>
                </div>
                <div class="ed-ecran" :class="mobile && 'mobile'">
                    <iframe id="apercu" title="Aperçu du mail"></iframe>
                </div>
                <form method="POST" action="{{ route('admin.renouvellements.tester', $cle) }}" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:12px;"
                      onsubmit="document.getElementById('champ-campagne-test').value = document.getElementById('campagne').value; if (!document.getElementById('campagne').value) { alert('Choisissez une campagne réelle pour l\'envoi de test.'); return false; } return confirm('Envoyer ce mail (texte enregistré) aux adresses de test ?');">
                    @csrf
                    <input type="hidden" name="campagne" id="champ-campagne-test">
                    <button class="rn-btn petit">✉️ Envoyer un test</button>
                    <span class="rn-muted" data-tip="Vers la liste Brevo « Tests internes », avec le texte enregistré. Jamais au client.">vers les adresses de test <span class="rn-info">?</span></span>
                </form>
            </div>
        </div>
    </div>

    <script>
    const EDITEUR = {
        minuteur: null,
        champ() { return document.getElementById('corps'); },
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
            donnees.append('corps', this.champ().value);
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
        remplacer(debut, fin, texte, curseur) {
            const t = this.champ();
            t.value = t.value.slice(0, debut) + texte + t.value.slice(fin);
            t.focus();
            t.selectionStart = t.selectionEnd = curseur ?? debut + texte.length;
            this.actualiser();
        },
        inserer(champ) {
            const t = this.champ();
            this.remplacer(t.selectionStart, t.selectionEnd, champ);
        },
        entourer(marque) {
            const t = this.champ(), d = t.selectionStart, f = t.selectionEnd;
            const sel = t.value.slice(d, f) || 'texte';
            this.remplacer(d, f, marque + sel + marque);
        },
        liste() {
            const t = this.champ(), d = t.value.lastIndexOf('\n', t.selectionStart - 1) + 1, f = t.selectionEnd;
            const lignes = t.value.slice(d, f).split('\n').map(l => l.startsWith('- ') ? l : '- ' + l);
            this.remplacer(d, f, lignes.join('\n'));
        },
        bloc(nom) {
            const t = this.champ();
            this.remplacer(t.selectionStart, t.selectionEnd, '\n\n' + nom + '\n\n');
        },
    };
    ['objet', 'corps'].forEach(id => document.getElementById(id).addEventListener('input', () => EDITEUR.actualiser()));
    document.getElementById('campagne').addEventListener('change', (e) => {
        document.getElementById('champ-campagne-form').value = e.target.value;
        EDITEUR.charger();
    });
    EDITEUR.charger();
    </script>
</x-admin-layout>
