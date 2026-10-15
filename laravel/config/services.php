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

    'brevo' => [
        'key' => env('BREVO_API_KEY'),
        'endpoint' => 'https://api.brevo.com/v3/smtp/email',
        'from_email' => env('MAIL_FROM_EMAIL', 'gradtrack.sjnhs@gmail.com'),
        'from_name' => env('MAIL_FROM_NAME', 'San Josef NHS Graduate Tracking'),
        'always_log' => (bool) env('MAIL_ALWAYS_LOG', true),
        // Shows the reset code on screen when email delivery fails (local testing only).
        // Only 1/true/yes/on enable it (as in legacy); "no", "off", etc. stay off.
        'show_code_on_failure' => in_array(strtolower(trim((string) env('MAIL_SHOW_RESET_LINK_ON_FAILURE', ''))), ['1', 'true', 'yes', 'on'], true),
    ],

    'railway' => [
        'detected' => filled(env('RAILWAY_ENVIRONMENT')) || filled(env('RAILWAY_PROJECT_ID')) || filled(env('RAILWAY_SERVICE_NAME')),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
