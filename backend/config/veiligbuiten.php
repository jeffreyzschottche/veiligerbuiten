<?php

return [

    // Server-to-server aggregate; never put this secret in public runtime config.
    'site_stats_token' => env('VB_SITE_STATS_TOKEN', ''),

    // Maximaal aantal meldingen dat een account per kalenderdag (Europe/Amsterdam) mag plaatsen.
    'reports_per_day' => (int) env('VB_REPORTS_PER_DAY', 3),
    'timezone' => 'Europe/Amsterdam',

    /*
     * De app synchroniseert alles binnen deze straal en controleert daarna lokaal.
     * Het middelpunt wordt op een raster van ~5 km gelegd: gebruikers in dezelfde
     * buurt krijgen hetzelfde antwoord, dat de server één keer opbouwt en cachet.
     * Met een cursor (since) en ETag stuurt de server alleen wat veranderde, of
     * een lege 304 als er niets veranderde.
     */
    'sync' => [
        'radius_km' => 30,
        'interval_seconds' => 3600,
        'snap_degrees' => 0.05,
        // Ondergrens tussen automatische syncs; ophogen bij drukte (load shedding).
        'min_interval_seconds' => (int) env('VB_SYNC_MIN_INTERVAL', 900),
        // Hoe ver terug een delta mag gaan; ouder = volledige sync.
        'delta_window_hours' => 6,
        'response_cache_seconds' => 600,
        'max_items' => 500,
    ],

    /*
     * Levenscyclus van een melding. Elke categorie heeft een eigen ritme:
     *  - ttl: zo lang leeft een nieuwe melding zonder verdere interactie;
     *  - extend: zoveel extra tijd geeft elke bevestiging (vanaf nu, niet opgeteld);
     *  - idle: zonder enige activiteit (bevestiging, update) zo lang → verlopen;
     *  - max: absolute bovengrens sinds de eerste waarneming;
     *  - half_life: in zoveel minuten halveert de 'versheid' (stip vervaagt, bereik krimpt).
     * Een 'niet meer aanwezig'-stem halveert de resterende tijd.
     */
    'lifecycle' => [
        'categories' => [
            'geweld' => ['ttl' => 90, 'extend' => 45, 'idle' => 90, 'max' => 360, 'half_life' => 45],
            // Een persoon die lastigvalt verplaatst zich: kort zichtbaar, snel minder zeker.
            'intimidatie' => ['ttl' => 60, 'extend' => 30, 'idle' => 60, 'max' => 240, 'half_life' => 30],
            'onveilig' => ['ttl' => 120, 'extend' => 60, 'idle' => 120, 'max' => 480, 'half_life' => 60],
            'brand' => ['ttl' => 180, 'extend' => 90, 'idle' => 180, 'max' => 720, 'half_life' => 90],
            'stoffen' => ['ttl' => 240, 'extend' => 90, 'idle' => 240, 'max' => 720, 'half_life' => 120],
            'verkeer' => ['ttl' => 120, 'extend' => 60, 'idle' => 120, 'max' => 480, 'half_life' => 60],
            'blokkade' => ['ttl' => 360, 'extend' => 180, 'idle' => 480, 'max' => 2880, 'half_life' => 240],
            'drukte' => ['ttl' => 180, 'extend' => 60, 'idle' => 180, 'max' => 720, 'half_life' => 90],
            'weer' => ['ttl' => 360, 'extend' => 120, 'idle' => 360, 'max' => 1440, 'half_life' => 240],
            'dier' => ['ttl' => 240, 'extend' => 120, 'idle' => 360, 'max' => 2880, 'half_life' => 180],
            'anders' => ['ttl' => 120, 'extend' => 60, 'idle' => 120, 'max' => 480, 'half_life' => 60],
        ],
        // Officiële bronnen leven zolang de feed ze toont; dit is de vangnet-limiet.
        'official_max_hours' => 24,
        'min_remaining_after_resolve_vote' => 10,
    ],

    // Opruimen, zodat de database klein blijft.
    'retention' => [
        'archived_incident_days' => 45,
        'source_record_missing_days' => 3,
        'ingestion_run_days' => 14,
        'alert_delivery_days' => 30,
    ],

    /*
     * Gevaarlijke plekken: waar de afgelopen dagen herhaaldelijk iets ernstigs
     * gebeurde (ongevallen, geweld). Alleen officiële of bevestigde meldingen,
     * verspreid over meerdere dagen, zodat één drukke avond geen 'plek' maakt.
     */
    'hotspots' => [
        'window_days' => 30,
        'cell_m' => 250,
        'min_incidents' => 3,
        'min_days' => 3,
        'half_life_days' => 10,
        'categories' => ['verkeer', 'geweld', 'onveilig', 'intimidatie'],
    ],

    // Toegestane achtergrondcontroles (minuten) die een gebruiker kan kiezen.
    'check_intervals' => [15, 30, 60],

    /*
     * Ernst en bereik. Een stip en het meldingsbereik groeien met het aantal
     * bevestigingen, maar logaritmisch en begrensd: nooit absurd groot.
     */
    'danger_scale' => [
        // Bereik bij 'gevaar' per categorie; 'opletten' en 'goed om te weten' krijgen een deel daarvan.
        'category_radius_m' => [
            'stoffen' => 1500, 'weer' => 3000, 'brand' => 1000, 'geweld' => 600, 'onveilig' => 500, 'intimidatie' => 400,
            'verkeer' => 800, 'blokkade' => 500, 'drukte' => 600, 'dier' => 400, 'anders' => 800,
        ],
        'level_factor' => ['danger' => 1.0, 'warn' => 0.6, 'info' => 0.3],
        'max_radius_factor' => 3.0,
        'official_weight' => 5,
        'max_dot_scale' => 2.0,
        // Hoeveel het bereik mag krimpen als een melding veroudert.
        'min_freshness_factor' => 0.6,
        // Meerdere onafhankelijke signalen binnen deze straal versterken elkaar.
        'cluster_radius_m' => 500,
        'cluster_window_minutes' => 90,
        'max_area_radius_m' => 65000,
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
