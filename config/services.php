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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'clearbit' => [
        'key' => env('CLEARBIT_API_KEY'),
    ],

    'facebook' => [
        'client_id' => env('FB_CLIENT_ID'),
        'client_secret' => env('FB_CLIENT_SECRET'),
        'redirect' => env('FB_REDIRECT_URI'),
    ],

    'google' => [
        'enabled' => env('GOOGLE_ENABLED', true),
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
    ],

    'zoom' => [
        'enabled' => env('ZOOM_INTEGRATION_ENABLED', false),
        'client_id' => env('ZOOM_CLIENT_ID'),
        'client_secret' => env('ZOOM_CLIENT_SECRET'),
        'account_id' => env('ZOOM_ACCOUNT_ID'),
        'webhook_secret' => env('ZOOM_WEBHOOK_SECRET'),
    ],

    'teams' => [
        'enabled' => env('TEAMS_ENABLED', false),
        'client_id' => env('TEAMS_CLIENT_ID'),
        'client_secret' => env('TEAMS_CLIENT_SECRET'),
        'tenant_id' => env('TEAMS_TENANT_ID'),
        'redirect_uri' => env('TEAMS_REDIRECT_URI'),
    ],

    'outlook' => [
        'enabled' => env('OUTLOOK_ENABLED', false),
        'client_id' => env('OUTLOOK_CLIENT_ID'),
        'client_secret' => env('OUTLOOK_CLIENT_SECRET'),
        'tenant_id' => env('OUTLOOK_TENANT_ID'),
        'redirect_uri' => env('OUTLOOK_REDIRECT_URI'),
    ],

];
