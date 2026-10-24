# Productie-readiness

## Verplichte configuratie

- PostgreSQL met PostGIS is aanbevolen voor toekomstige geografische analyse;
  MySQL 8 werkt voor de huidige MVP ook. Gebruik Redis voor cache en rate limits.
- Stel `APP_ENV=production`, `APP_DEBUG=false`, `DB_CONNECTION=pgsql`, `QUEUE_CONNECTION=redis` en `CACHE_STORE=redis` in.
- Bewaar `KNMI_API_KEY`, `FCM_*`, APNs-credentials en broncredentials uitsluitend in de secret manager van de hoster. Ook `STRIPE_*` en `APNS_PRIVATE_KEY` horen daar; `APP_KEY` moet gezet zijn (AI-tokens worden ermee versleuteld).
- De app gebruikt MapLibre met OpenFreeMap als kosteloze basemap. Plan voor groei een eigen OpenMapTiles/PMTiles-hosting in; zo blijft de kaartstack vendor-onafhankelijk.

## Release gates

1. DPIA, privacyverklaring, verwerkingsregister, bewaartermijnen en datalek-runbook zijn goedgekeurd.
2. Er is een contractuele/licentiecontrole uitgevoerd voor iedere bron, vooral P2000/hulpdienstinformatie.
3. Push is getest op iOS en Android met een echt FCM/APNs-project.
4. Load-, security- en onafhankelijke pentest zijn zonder blocker afgerond.
5. Start met één pilotgebied en een bemande moderatie- en incident-escalatierotatie.

## Operatie

- Monitor queue-lag, connector-fouten, ingest-volume, publicatievertraging en push-delivery.
- Houd bronpayloads afgeschermd; publiceer alleen genormaliseerde, noodzakelijke incidentinformatie.
- Verwijder precieze gebruikerslocatie zodra de locatievalidatie en deduplicatie afgerond zijn; bewaar geen locatiehistorie.

## Pilot-checklist

- Registreer een gratis KNMI Open Data-key en FCM/APNs-project; zet ze uitsluitend in secrets.
- Configureer de scheduler (`php artisan schedule:work` of iedere minuut `php artisan schedule:run`). De huidige bron- en pushcommands verwerken hun eigen databasewachtrij; een aparte Laravel queue-worker is daarvoor nu niet vereist.
- Draai `php artisan migrate --force`, activeer de KNMI/NDW-ingest en controleer bronmonitoring voordat er publieke kaarten worden getoond.
- Laat vóór publieke release een DPIA en onafhankelijke penetratietest uitvoeren.
