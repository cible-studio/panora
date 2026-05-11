<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp — notifications techniciens (poses OOH)
    |--------------------------------------------------------------------------
    | Provider : "callmebot" (gratuit, MVP) ou "twilio" (prod payant).
    */
    'whatsapp' => [
        // PROD : "twilio" (provider officiel, scalable, supporte WhatsApp Business
        // approuvé Meta). "callmebot" reste disponible mais déconseillé (1 clé = 1
        // destinataire — inutilisable au-delà du dev/test individuel).
        'provider' => env('WHATSAPP_PROVIDER', 'twilio'),
        'enabled'  => (bool) env('WHATSAPP_ENABLED', true),
    ],

    'callmebot' => [
        'api_key' => env('CALLMEBOT_API_KEY'),
    ],

    'twilio' => [
        'sid'            => env('TWILIO_SID'),
        'token'          => env('TWILIO_AUTH_TOKEN'),
        'whatsapp_from'  => env('TWILIO_WHATSAPP_FROM'), // ex: +14155238886 (sandbox)

        // ── Templates approuvés Meta (production) ───────────────────────
        // En prod approuvé, WhatsApp REFUSE les messages free-form vers un
        // destinataire qui n'a pas écrit en premier dans les 24h. Il faut
        // utiliser un Content Template approuvé. Le ContentSid est fourni
        // par Twilio après approval. Renseigne uniquement quand tu as
        // soumis et fait approuver le template "pose_assignment_fr".
        'content_sid_pose_assignment' => env('TWILIO_CONTENT_SID_POSE_ASSIGNMENT'),
    ],

];
