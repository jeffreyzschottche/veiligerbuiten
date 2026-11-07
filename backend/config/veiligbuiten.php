<?php

return [

    // Server-to-server aggregate; never put this secret in public runtime config.
    'site_stats_token' => env('VB_SITE_STATS_TOKEN', ''),

    // Een login-token dat zo lang niet gebruikt is, werkt niet meer (en wordt opgeruimd). De app en de
    // achtergrondsync gebruiken het token steeds, dus wie de app gebruikt, wordt nooit uitgelogd.
    'token_idle_days' => (int) env('VB_TOKEN_IDLE_DAYS', 90),

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
        // Telefoons vragen standaard elk kwartier; bij gevaar in de buurt niet vaker dan de ondergrens.
        'interval_seconds' => 900,
        'snap_degrees' => 0.05,
        // Ondergrens tussen automatische syncs; ophogen bij drukte (load shedding).
        'min_interval_seconds' => (int) env('VB_SYNC_MIN_INTERVAL', 900),
        // Met de app open ververst hij elke minuut. Meestal een 304 zonder databasequery. Ophogen bij drukte.
        'foreground_min_interval_seconds' => (int) env('VB_SYNC_FOREGROUND_MIN_INTERVAL', 60),
        // Hoe ver terug een delta mag gaan; ouder = volledige sync.
        'delta_window_hours' => 6,
        'response_cache_seconds' => 600,
        // Gebieden waar telefoons de laatste uren om vroegen; na elke verandering zet de server hun antwoord klaar.
        'warm_hours' => 3,
        'warm_max_cells' => 400,
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
     *
     * Gebruikersmeldingen die een ander bevestigde (de melder zelf telt als 1) volgen
     * niet het ritme per soort: die blijven tot 3 mensen 'niet meer aanwezig' zeggen,
     * of tot er confirmed_idle_minutes lang geen nieuwe bevestiging kwam.
     */
    'lifecycle' => [
        'categories' => [
            'geweld' => ['ttl' => 90, 'extend' => 45, 'idle' => 90, 'max' => 360, 'half_life' => 45],
            // Een persoon die lastigvalt verplaatst zich: kort zichtbaar, snel minder zeker.
            'intimidatie' => ['ttl' => 60, 'extend' => 30, 'idle' => 60, 'max' => 240, 'half_life' => 30],
            'onveilig' => ['ttl' => 120, 'extend' => 60, 'idle' => 120, 'max' => 480, 'half_life' => 60],
            'medisch' => ['ttl' => 120, 'extend' => 60, 'idle' => 120, 'max' => 480, 'half_life' => 60],
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
        'confirmed_idle_minutes' => 720,
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

    /*
     * Uitknoppen voor bronnen die we niet als open data gebruiken, maar dankzij toestemming
     * of zonder duidelijke licentie voor commercieel gebruik. Zet er een op false in de .env
     * en herstart: dan haalt de server niets meer op, verdwijnen bestaande meldingen binnen
     * een minuut van de kaart en uit de pushwachtrij, en staat de bron in de app als
     * uitgeschakeld. Een nieuwe bron zonder open licentie komt hier ook bij, met dezelfde
     * naam als in 'scrape.sources' en als incidentbron. Open overheidsdata (Politie, NDW,
     * KNMI, CBS) staat hier bewust niet in. Zie docs/bronnen-en-licenties.md.
     */
    'source_switches' => [
        'p2000_zwaailicht' => (bool) env('ZWAAILICHT_ENABLED', true),
        'p2000_sirenes' => (bool) env('SIRENES_ENABLED', true),
        'nl_alert' => (bool) env('NL_ALERT_ENABLED', true),
    ],

    /*
     * Ophaalplanner (veiligbuiten:scrape, elke minuut via de scheduler). Per bron een interval
     * in minuten tussen min en max: verandert er iets, dan terug naar min; blijft het gelijk,
     * dan steeds ×1,5 ruimer tot max. Bij een fout steeds 2× langer wachten, tot backoff_max.
     * 's Nachts (night) is het interval minstens min × factor, behalve bij 'night' => false.
     * Twee rijen (lanes), zodat een trage bron de snelle P2000/NL-Alert nooit ophoudt.
     */
    'scrape' => [
        'night' => ['from' => 1, 'to' => 6, 'factor' => 2],
        'backoff_max' => 60,
        'jitter' => 0.1,
        'sources' => [
            // NL-Alert: elke 60 minuten, ook 's nachts (keuze van 2 nov. 2026). Let op: een NL-Alert duurt vaak maar een uur.
            'nl_alert' => ['lane' => 'fast', 'command' => 'veiligbuiten:ingest-nl-alert', 'min' => 60, 'max' => 60, 'night' => false],
            // Zwaailicht staat aan: de eigenaar gaf in november 2026 per mail toestemming, mits zichtbare bronlink per melding. Hoogstens 1× per 6 minuten (min is een harde ondergrens).
            // Zwaailicht, Sirenes en NL-Alert hebben een uitknop in 'source_switches'; de planner slaat ze dan over.
            'p2000_zwaailicht' => ['lane' => 'fast', 'command' => 'veiligbuiten:ingest-p2000', 'args' => ['--feed' => 'zwaailicht'], 'min' => 6, 'max' => 12],
            'p2000_sirenes' => ['lane' => 'fast', 'command' => 'veiligbuiten:ingest-p2000', 'args' => ['--feed' => 'sirenes'], 'min' => 2, 'max' => 8],
            'ndw' => ['lane' => 'slow', 'command' => 'veiligbuiten:ingest-ndw', 'min' => 5, 'max' => 15],
            'politie' => ['lane' => 'slow', 'command' => 'veiligbuiten:ingest-police-news', 'min' => 15, 'max' => 30, 'enabled' => (bool) env('POLITIE_NEWS_ENABLED', true)],
            'knmi_quakes' => ['lane' => 'slow', 'command' => 'veiligbuiten:ingest-knmi-quakes', 'min' => 10, 'max' => 30],
            // Bronnen die uit staan (geen sleutel of niet ingeschakeld) worden helemaal overgeslagen.
            'knmi' => ['lane' => 'slow', 'command' => 'veiligbuiten:ingest-knmi', 'min' => 60, 'max' => 120, 'enabled' => (bool) env('KNMI_API_KEY')],
            'luchtmeetnet' => ['lane' => 'slow', 'command' => 'veiligbuiten:ingest-luchtmeetnet', 'min' => 60, 'max' => 120, 'enabled' => (bool) env('LUCHTMEETNET_ENABLED', false)],
            'rws' => ['lane' => 'slow', 'command' => 'veiligbuiten:ingest-rws-water', 'min' => 60, 'max' => 180, 'enabled' => (bool) env('RWS_WATER_OBSERVATIONS_URL')],
        ],
    ],

    // Buurtcijfers van de politie (via CBS): buurten met opvallend veel geweld en straatroof per km².
    'neighbourhood_stats' => [
        'months' => 12,
        // CBS-code => [naam, gewicht]. Straatroof en openlijk geweld raken voorbijgangers het meest.
        'types' => [
            '1.4.6' => ['straatroof', 3],
            '1.4.3' => ['openlijk_geweld', 2],
            '1.4.4' => ['bedreiging', 1],
            '1.4.5' => ['mishandeling', 1],
        ],
        // Minstens zoveel misdrijven in de periode (kleine aantallen zeggen weinig)...
        'min_count' => 25,
        // ...en per km² minstens zoveel keer het landelijke gemiddelde.
        'min_ratio' => 40,
        // Hoogstens zoveel buurten per sync-gebied, de opvallendste eerst.
        'per_area' => 40,
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
            'verkeer' => 800, 'blokkade' => 500, 'drukte' => 600, 'dier' => 400, 'anders' => 800, 'medisch' => 500,
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

    // "Bron aanvragen" in de app gaat naar dit adres; de gebruiker staat als reply-to.
    'source_requests' => [
        'mail_to' => env('SOURCE_REQUEST_MAIL') ?: env('MAIL_FROM_ADDRESS', 'hallo@veiligerbuiten.nl'),
    ],

    'places' => [
        'max_per_user' => 10,
        'min_radius_m' => 100,
        'max_radius_m' => 5000,
        // Kleuren voor de cirkel op de kaart. Geen rood, oranje of blauw: die betekenen op de kaart gevaar, opletten en jij. De app vertaalt de naam naar een kleur; zo kan het palet later mee met een donker thema.
        'colors' => ['ink', 'violet', 'pink', 'teal', 'green', 'brown'],
    ],

    // Antispam voor pushberichten per gebruiker.
    'alerts' => [
        'warn_per_hour' => 4,
        'info_digest_minutes' => 60,
    ],

    // Lokaal ontwikkelen zonder Stripe: alle accounts gelden als betalend.
    'billing_bypass' => (bool) env('VB_BILLING_BYPASS', false),

];
