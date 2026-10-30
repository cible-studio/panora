<?php

namespace App\Mail\Concerns;

/**
 * Marque un mail COMMERCIAL ou de FACTURATION envoyé au client
 * (2026-10-30, demande de la direction).
 *
 * Un mailable qui porte cette interface reçoit automatiquement la copie
 * de `config('mails.copie_commerciale')`, quel que soit le chemin
 * d'envoi — NotificationMailer, Mail::to()->send(), file d'attente.
 * Le branchement est un écouteur de MessageSending (AppServiceProvider) :
 * c'est le seul point par lequel passent tous les envois.
 *
 * NE PAS la poser sur :
 *   · les mails internes au personnel (alertes, attributions, digests) ;
 *   · les mails de suivi d'exécution (preuve d'affichage, décapage,
 *     maintenance, enquête de satisfaction) ;
 *   · les mails techniques (mot de passe, invitation d'un utilisateur
 *     client, réponse à un message reçu).
 *
 * Un mailable qui fixe déjà une copie dans son envelope() la conserve :
 * l'écouteur ajoute, il ne remplace pas (cf. MailClientCampagne, qui
 * porte la copie de fin de campagne).
 */
interface MailCommercial
{
}
