<?php

return [

    // Maximaal aantal meldingen dat een account per kalenderdag (Europe/Amsterdam) mag plaatsen.
    'reports_per_day' => (int) env('VB_REPORTS_PER_DAY', 3),
    'timezone' => 'Europe/Amsterdam',

    // De app synchroniseert ieder uur alles binnen deze straal en controleert daarna lokaal.
    'sync' => [
        'radius_km' => 30,
        'interval_seconds' => 3600,
    ],

    // Toegestane achtergrondcontroles (minuten) die een gebruiker kan kiezen.
    'check_intervals' => [15, 30, 60],

    /*
     * Ernst en bereik. Een stip en het meldingsbereik groeien met het aantal
     * bevestigingen, maar logaritmisch en begrensd: nooit absurd groot.
     */
    'danger_scale' => [
        'base_radius_m' => ['danger' => 1500, 'warn' => 750, 'info' => 300],
        'max_radius_factor' => 3.0,
        'official_weight' => 5,
        'max_dot_scale' => 2.0,
    ],

    'places' => [
        'max_per_user' => 10,
        'min_radius_m' => 100,
        'max_radius_m' => 5000,
    ],

    // Antispam voor pushberichten per gebruiker.
    'alerts' => [
        'warn_per_hour' => 4,
        'info_digest_minutes' => 60,
    ],

    // Lokaal ontwikkelen zonder RevenueCat: alle accounts gelden als betalend.
    'billing_bypass' => (bool) env('VB_BILLING_BYPASS', false),

];
