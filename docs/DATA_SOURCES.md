# Externe databronnen

VeiligBuiten importeert alleen actuele brondata via gedocumenteerde feeds of API's. Er wordt niet vanaf HTML-pagina's gescrapet.

## Actief

- **NDW** — elke vijf minuten via de publieke DATEX II SRTI-feed. Dit is de primaire automatische verkeersbron.
- **Gebruikersmeldingen** — direct na de lokale veiligheids- en moderatiecontroles.

## Configureerbaar

- **KNMI** — `KNMI_API_KEY` is een gratis server-side key. De import draait ieder uur en gebruikt alleen de dataset `waarschuwingen_nederland_48h`.
- **Luchtmeetnet** — publiek, zonder key. Actief voor station Oude Meer-Aalsmeerderdijk (`NL49565`); de bron wordt ieder uur ververst. Routine-metingen zijn geen incidenten, alleen officiële hoge waarden verschijnen op de kaart.
- **Politie.nl** — publiek, zonder key. Zet `POLITIE_NEWS_ENABLED=true` om recente, geolocatie-gekoppelde politieberichten landelijk te importeren. De kaart filtert deze daarna lokaal. Dit is een officieel politiebericht, geen melding vanuit de meldkamer en dus nooit als “live politie-incident” geformuleerd.
- **Rijkswaterstaat** — bewust uit totdat de actuele WaterWebservices/WADAR endpoint- en drempelconfiguratie is getest. Gebruik nooit een oude klassieke WaterWebservices URL.
- **CBS Politie Open Data** — uitsluitend toekomstige B2B-analyse; nooit een live kaart- of pushbron.

## Productiescheduler

Laat in productie iedere minuut Laravel Scheduler draaien:

```sh
* * * * * cd /pad/naar/veiligbuiten/backend && php artisan schedule:run >> /dev/null 2>&1
```

Bronimporten hebben een rij in `source_ingestion_runs`. Ruwe, genormaliseerde bronrecords staan in `source_records`; feeditems worden pas opgelost wanneer ze twee opeenvolgende controles ontbreken. Externe incidenten kunnen nooit langer dan 24 uur zichtbaar blijven zonder een actuele herbevestiging.
