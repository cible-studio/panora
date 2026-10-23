{{-- Guide « Comment ça marche » en panneau latéral (2026-10-23). Remplace les blocs de texte de l'écran. --}}
<template x-if="guide">
<div>
    <div class="rn-voile" @click="guide = false"></div>
    <aside class="rn-panneau rn-guide" role="dialog" aria-label="Guide des renouvellements">
        <header>
            <div>
                <div style="font-size:18px;font-weight:800;color:var(--text);">Comment ça marche</div>
                <div class="rn-muted">Renouvellements et mails de fin de campagne</div>
            </div>
            <button type="button" class="rn-fermer" @click="guide = false" aria-label="Fermer">×</button>
        </header>

        <div class="rn-section">
            <h3>📅 Le calendrier</h3>
            <p>Pour chaque campagne de <strong>12 mois ou plus</strong>, Panora écrit au client à 10 h, tant qu'il n'a pas répondu :</p>
            <div style="display:flex;justify-content:space-between;gap:6px;margin:12px 0 4px;">
                @foreach(['J-60' => 'Annonce', 'J-45' => 'Relance', 'J-30' => 'Date limite', 'J-15' => 'Dernier rappel', 'J-7' => 'Avant la fin'] as $j => $lib)
                    <div style="flex:1;text-align:center;">
                        <div style="height:6px;border-radius:3px;background:#3aa835;margin-bottom:6px;"></div>
                        <div style="font-weight:800;font-size:13px;color:var(--text);">{{ $j }}</div>
                        <div class="rn-muted">{{ $lib }}</div>
                    </div>
                @endforeach
            </div>
            <p class="rn-muted" style="margin-top:8px;">Les campagnes plus courtes reçoivent un seul mail, 7 jours avant la fin (onglet « Autres campagnes »).</p>
        </div>

        <div class="rn-section">
            <h3>👆 Quand le client répond</h3>
            <ul>
                <li>Il clique sur un bouton du mail : <em>renouveler, ajuster, ne pas renouveler, être rappelé</em>, puis confirme.</li>
                <li>Il reçoit un accusé de réception ; son commercial est prévenu par mail, avec commercial@ en copie.</li>
                <li>Les relances s'arrêtent aussitôt. Sa réponse est une intention, pas un engagement.</li>
            </ul>
        </div>

        <div class="rn-section">
            <h3>🧑‍💼 Qui fait quoi</h3>
            <ul>
                <li><strong>Commercial</strong> : reçoit chaque matin ses clients à appeler (sans réponse dès J-25, rappels prévus) et les réponses. Après un appel, il ouvre le dossier et note le résultat (statut, note, date de rappel).</li>
                <li><strong>MP</strong> : confirme le renouvellement. Les panneaux sortent alors du catalogue des disponibilités.</li>
                <li><strong>Renouveler la campagne</strong> (fiche campagne) : crée la campagne suivante avec les mêmes panneaux ; le dossier passe en « confirmé ».</li>
                <li><strong>Admin</strong> : modifie les textes des mails, avec aperçu immédiat.</li>
            </ul>
        </div>

        <div class="rn-section">
            <h3>📊 Les compteurs</h3>
            <ul>
                <li><strong>Sans réponse</strong> : échéance dans 60 jours ou moins. <em>À risque</em> : leur CA à 30 jours ou moins.</li>
                <li><strong>En discussion</strong> : veut renouveler, ajuster, ou être rappelé.</li>
                <li><strong>Confirmés</strong> : CA sécurisé. <strong>Ne renouvellent pas</strong> : CA perdu.</li>
                <li>Cliquez sur un compteur pour filtrer la liste.</li>
            </ul>
        </div>

        <div class="rn-section">
            <h3>✉️ Suivre les mails</h3>
            <ul>
                <li>Ici : la frise de chaque dossier et l'onglet « Journal des envois ».</li>
                <li>Dans Brevo : <strong>Transactionnel → Journal</strong> (reçu, ouvert, lien cliqué), tags <code>renouvellement-j60</code> … <code>renouvellement-j7</code>, <code>fin-campagne</code>.</li>
            </ul>
        </div>

        <div class="rn-section" style="border-bottom:0;">
            <h3>🧪 Mode test</h3>
            <p>Tant que <code>RELANCE_FIN_MODE</code> n'est pas sur <code>production</code>, tous ces mails partent vers la liste Brevo « Tests internes » et les boutons n'enregistrent rien. Aucun client ne reçoit quoi que ce soit.</p>
        </div>
    </aside>
</div>
</template>
