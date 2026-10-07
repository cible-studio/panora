@php
    $title     = 'Tester la restauration des sauvegardes';
    $preheader = "Rappel {$trimestre} : vérifier qu'une sauvegarde de Panora se restaure bien (environ 30 minutes).";
    $probleme  = collect($copies)->contains(fn ($c) => ! $c['joignable'] || $c['nombre'] === 0);
    $libelles  = ['backups' => 'Serveur (volume Coolify)', 'backups-s3' => 'Hors serveur (Hetzner Object Storage)'];
@endphp

<x-mail.layout :title="$title" :preheader="$preheader"
               footerNote="Rappel automatique envoyé le 1er de chaque trimestre. Procédure complète : docs/SAUVEGARDES.md (section 4).">

    <span class="pill pill-info">Rappel trimestriel · {{ $trimestre }}</span>

    <h1>Tester la restauration des sauvegardes</h1>

    <p>
        Panora est sauvegardé chaque nuit, mais une sauvegarde n'est utile que si l'on sait la
        <strong>restaurer</strong>. Une fois par trimestre, on vérifie qu'une copie récente
        redonne bien toutes les données. Comptez environ <strong>30 minutes</strong>, à faire
        de préférence avant le <strong>{{ $echeance }}</strong>.
    </p>

    <h2>État des sauvegardes aujourd'hui</h2>
    <div class="info">
        @foreach($copies as $c)
            <div class="info-row">
                <div class="lbl">{{ $libelles[$c['disque']] ?? $c['disque'] }}</div>
                <div class="val">
                    @if(! $c['joignable'])
                        <span class="pill pill-danger" style="margin:0">Injoignable</span>
                    @elseif($c['nombre'] === 0)
                        <span class="pill pill-danger" style="margin:0">Aucune copie</span>
                    @else
                        {{ $c['nombre'] }} copie{{ $c['nombre'] > 1 ? 's' : '' }} · dernière le {{ $c['derniere'] }} · {{ $c['taille_mo'] >= 1024 ? number_format($c['taille_mo'] / 1024, 1, ',', ' ') . ' Go' : number_format($c['taille_mo'], 1, ',', ' ') . ' Mo' }}
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if($probleme)
        <div class="alert alert-danger">
            <strong>Une destination de sauvegarde pose problème.</strong> Réglez ce point avant le test :
            voir le tableau « Dépannage » de docs/SAUVEGARDES.md.
        </div>
    @endif

    <h2>Le test, étape par étape</h2>
    <ul class="steps">
        <li><strong>Télécharger la dernière copie</strong> depuis la console Hetzner → Object Storage →
            <code>panora-sauvegardes-cible</code> → dossier <code>panora/</code> (fichier <code>.zip</code> le plus récent).</li>
        <li><strong>L'ouvrir</strong> avec le mot de passe d'archive (<code>BACKUP_ARCHIVE_PASSWORD</code>, rangé dans le coffre de mots de passe) s'il est défini.</li>
        <li><strong>Restaurer la base sur le staging uniquement</strong> — jamais sur la production :
            importer <code>db-dumps/mysql-panora.sql</code> dans la base du staging.</li>
        <li><strong>Vérifier sur le staging</strong> : les dernières factures, les clients récents et quelques photos de panneaux sont bien là.</li>
        <li><strong>Noter le résultat</strong> (date, copie utilisée, OK ou problème) et répondre à ce mail pour garder une trace.</li>
    </ul>

    <div class="alert alert-warning">
        Le staging sera remplacé par les données de production le temps du test : prévenez l'équipe
        qui l'utilise. Ne jamais restaurer directement sur <strong>panora-cible.com</strong>.
    </div>

</x-mail.layout>
