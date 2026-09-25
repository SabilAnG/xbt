<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    /*
     * DHL Express MyDHL API — dipakai halaman /tracking untuk menyegarkan
     * status kiriman. Kosongkan username/password untuk mematikan fiturnya:
     * halaman tetap bekerja dan menyajikan apa yang sudah tersimpan.
     */
    'dhl' => [
        'username' => env('DHL_USERNAME'),
        'password' => env('DHL_PASSWORD'),

        // "test" atau "production". Kredensial sandbox TIDAK berlaku di
        // produksi, dan sebaliknya.
        'environment' => env('DHL_ENVIRONMENT', 'test'),

        'account_number' => env('DHL_ACCOUNT_NUMBER'),

        // Hanya perlu di mesin yang PHP-nya tanpa bundel CA — gejalanya
        // "unable to get local issuer certificate" di tiap panggilan.
        'ca_bundle' => env('DHL_CA_BUNDLE'),

        // Berapa lama status dari DHL dianggap masih segar. Status kiriman
        // tidak berubah semenit sekali, sementara orang yang menunggu paket
        // menekan tombol lacak berkali-kali.
        'menit_segar' => env('DHL_TRACKING_CACHE_MINUTES', 15),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
