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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Simulasi Desain: pasang otomatis di foto, baca referensi, render foto-realistis.
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'vision_model' => env('OPENAI_VISION_MODEL', 'gpt-5.5'),
        'image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-2'),
        'image_quality' => env('OPENAI_IMAGE_QUALITY', 'medium'),
    ],

    // Foto → model 3D (GLB) untuk tampilan 360° knalpot & motor.
    'tripo' => [
        'key' => env('TRIPO_API_KEY'),
        'model' => env('TRIPO_MODEL', 'v3.1-20260211'),
        'texture_quality' => env('TRIPO_TEXTURE_QUALITY', 'standard'),
    ],

];
