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
    | Vital Sign API (Raspberry Pi Gateway)
    |--------------------------------------------------------------------------
    |
    | Configuration for the Vital Sign API V1 used by the Raspberry Pi
    | gateway (comennc5). The passphrase must match the API_PASSPHRASE
    | configured in the Python client's config.py.
    |
    */
    'vital_sign_api' => [
        'passphrase' => env('VITAL_SIGN_API_PASSPHRASE', 'qmedno1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | ADT HL7 Listener
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to the ADT HL7 listener service.
    | In Docker deployments, ADT_HOST should be the Docker service name (e.g., 'adt').
    | For local development, use '127.0.0.1' or leave empty to auto-detect.
    |
    */
    'adt' => [
        'host' => env('ADT_HOST', null),
        'port' => env('ADT_PORT', 3000),
    ],

];
