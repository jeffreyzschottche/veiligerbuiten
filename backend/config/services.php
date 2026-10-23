<?php

return [

    'knmi' => ['key' => env('KNMI_API_KEY')],
    'ndw' => ['url' => env('NDW_OPEN_DATA_URL', 'https://opendata.ndw.nu/veiligheidsgerelateerde_berichten_srti.xml.gz')],
    'luchtmeetnet' => [
        'enabled' => env('LUCHTMEETNET_ENABLED', false),
        'url' => env('LUCHTMEETNET_URL', 'https://api.luchtmeetnet.nl/open_api'),
        // Empty = all catalogued Dutch stations; source calls are deliberately rate-spaced.
        'stations' => json_decode(env('LUCHTMEETNET_STATIONS', '[]'), true, flags: JSON_THROW_ON_ERROR),
        'request_spacing_ms' => (int) env('LUCHTMEETNET_REQUEST_SPACING_MS', 3100),
        // Publish only above deliberately conservative values; tune with domain experts before production.
        'thresholds' => ['PM10' => 50, 'PM2.5' => 25, 'NO2' => 200, 'O3' => 180],
        'max_measurement_age_minutes' => (int) env('LUCHTMEETNET_MAX_MEASUREMENT_AGE_MINUTES', 90),
    ],
    'rws' => ['water_observations_url' => env('RWS_WATER_OBSERVATIONS_URL')],
    'politie' => [
        'enabled' => env('POLITIE_NEWS_ENABLED', false),
        'news_url' => env('POLITIE_NEWS_URL', 'https://api.politie.nl/v4/nieuws'),
        'nationwide' => env('POLITIE_NEWS_NATIONWIDE', true),
        // Add coverage centres as the product expands; each is queried with the official 25 km radius.
        'coverage_areas' => json_decode(env('POLITIE_NEWS_AREAS', '[{"lat":52.3000,"lng":4.6900}]'), true, flags: JSON_THROW_ON_ERROR),
    ],
    'source_health' => [
        'ndw' => ['label' => 'NDW', 'enabled' => true, 'maximum_age_minutes' => 20],
        'knmi' => ['label' => 'KNMI', 'enabled' => (bool) env('KNMI_API_KEY'), 'maximum_age_minutes' => 180],
        'luchtmeetnet' => ['label' => 'Luchtmeetnet', 'enabled' => env('LUCHTMEETNET_ENABLED', false), 'maximum_age_minutes' => 180],
        'politie' => ['label' => 'Politie', 'enabled' => env('POLITIE_NEWS_ENABLED', false), 'maximum_age_minutes' => 180],
        'rijkswaterstaat' => ['label' => 'Rijkswaterstaat', 'enabled' => (bool) env('RWS_WATER_OBSERVATIONS_URL'), 'maximum_age_minutes' => 180],
    ],
    'push' => [
        // Use `log` locally. Production Android delivery uses Firebase HTTP v1 when configured.
        'driver' => env('PUSH_DELIVERY_DRIVER', 'log'),
        'fcm_service_account_json' => env('FCM_SERVICE_ACCOUNT_JSON_BASE64'),
    ],

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

];
