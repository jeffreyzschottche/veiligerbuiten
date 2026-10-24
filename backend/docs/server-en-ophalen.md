# Server: ophalen, verwerken en klaarzetten

## Het idee in één plaatje

```
bronnen ──(server haalt op, tempo per bron)──▶ server ──(verwerkt + zet klaar)──▶ telefoons (elke 15 min een ping)
                                                                                    └─ verwerken zelf: afstand, filters, melding
```

1. **De server haalt op.** De ophaalplanner (`veiligbuiten:scrape`) kijkt elke minuut welke bron aan de beurt is.
2. **De server verwerkt:**
   - dubbele meldingen samenvoegen;
   - niveau en bereik bepalen;
   - verlopen meldingen opruimen.
3. **De server zet klaar.** Veranderde er iets, dan worden voor de gebieden waar telefoons de afgelopen 3 uur om vroegen de volledige lijst en de delta alvast gemaakt.
4. **De telefoon vraagt elk kwartier:** "is er iets veranderd sinds mijn vorige keer?"
   - Nee: een lege 304. Dat kost de server geen enkele databasequery.
   - Ja: alleen de veranderingen, kant-en-klaar uit de cache.
5. **De telefoon verwerkt zelf** wat dichtbij is, past filters toe en beslist over een melding. De locatie van de gebruiker gaat niet naar de server.

## Tempo per bron

Instellingen staan in `config/veiligbuiten.php` → `scrape`.

| Bron | Snelst | Rustigst | Opmerking |
|---|---|---|---|
| NL-Alert | 2 min | 2 min | Altijd, ook 's nachts: dit kan levens redden |
| P2000 (Zwaailicht, Sirenes, Alarmeringen) | 2 min | 6–8 min | |
| P2000-monitor | 3 min | 10 min | |
| NDW | 5 min | 15 min | |
| Politie | 15 min | 30 min | |
| KNMI-aardbevingen | 10 min | 30 min | |

Zo past het tempo zich aan:
- **Er veranderde iets:** terug naar het snelste interval.
- **Er veranderde niets:** het interval wordt steeds ×1,5 ruimer, tot het rustigste.
- **Een fout:** 2× zo lang wachten, tot hoogstens 60 minuten.
- **'s Nachts (01:00–06:00):** minstens 2× het snelste interval.
- Er zit ±10% willekeur op, zodat we niet op vaste tijden aankloppen.

Overzicht per bron: `php artisan veiligbuiten:scrape --status`. Alles nu ophalen: `php artisan veiligbuiten:scrape --force`.

## Worden we niet geblokkeerd?

We gedragen ons als een nette, herkenbare bezoeker, en dat is de beste bescherming:

- **Herkenbaar.** Elke aanvraag noemt wie we zijn, met contactadres: `VeiligerBuiten/1.0 (+https://veiligerbuiten.nl; hallo@veiligerbuiten.nl)`. Een beheerder die iets wil, mailt ons in plaats van ons te blokkeren.
- **"Niets nieuws?"-vragen.** We sturen ETag/Last-Modified mee. Is er niets veranderd, dan antwoordt de bron met een lege 304. Zwaailicht en NDW ondersteunen dat (getest; bij NDW scheelt het elke 5 minuten een download van 21 KB). Sirenes, Alarmeringen en de monitor doen het niet; die zijn klein (enkele tientallen KB) en worden rustiger gevraagd als er niets verandert.
- **Eén aanvraag per bron per keer.** Nooit tegelijk en nooit vaker dan het tempo hierboven. Zwaailicht vraagt hoogstens 1× per minuut; wij doen het hoogstens 1× per 2 minuten.
- **Stoppen als een bron dat vraagt.** Bij 429 (te veel), 503 (overbelast) of 403 (geweigerd) gaat er naar die host niets meer tot de pauze voorbij is. Die pauze komt uit Retry-After; anders is hij 15, 5 of 60 minuten.
- **Geen trucs.** Geen wisselende IP-adressen en geen nep-browser. Wie ons wil weren, kan dat, en dan weten we dat via het overzicht en de logs.
- **Eén bron eruit = de rest werkt.** P2000 komt uit vier bronnen. Valt er één weg, dan merkt de app dat nauwelijks.

Het echte risico is niet techniek maar **toestemming**: zie `bronnen-en-licenties.md`.

## Webserver en image

De image gebruikt **FrankenPHP**: PHP met een ingebouwde webserver (Caddy) in één programma, in plaats van `php artisan serve` (de ontwikkelserver van PHP).
- Veel verzoeken tegelijk, met compressie en HTTP/2. HTTPS regelt Coolify ervoor.
- **Niet als root:** alles draait als `www-data`.
- **OPcache staat vast ingesteld:** de code verandert in een container nooit (`docker/php.ini`).
- **Bij het opstarten:** `migrate --isolated` en `optimize`.
- **Logs gaan naar stderr**, dus naar Coolify.
- **`HEALTHCHECK` op `/api/v1/health`:** Coolify zet pas verkeer over als de nieuwe versie gezond is.

Later kan er nog snelheid bij met Octane (worker-modus); dat vraagt een extra pakket en is nu niet nodig.

## Meldingen in de Zschot CMS

`GET /api/v1/ops/status` (met `Authorization: Bearer <OPS_TOKEN>`) geeft:
- de status van database, ophalen, bronnen, pushberichten en fouten;
- een paar cijfers;
- de nieuwe gebeurtenissen sinds `?since=`.

Welke gebeurtenissen:
- **Betalingen:** nieuwe abonnee, verlenging, opzegging, betaalprobleem, verlopen.
- **Betaalfouten:** een betaling zonder gekoppeld account en een geweigerde RevenueCat-webhook. Die tellen als fout.
- **Nieuwe accounts.**
- **Bronnen:** een bron die 3× op rij faalt, en een melding zodra hij weer werkt.
- **Onverwachte fouten in de app**, met de foutsoort, het bestand en de regel. Dezelfde fout telt een uur lang op in plaats van te herhalen.

Er gaan geen persoonsgegevens mee, alleen accountnummers. Gebeurtenissen blijven 30 dagen bewaard.

In de CMS: project → **Koppelingen** → **App-status (eigen app)**. Vul daar de URL en de token in. De CMS haalt dit elke 5 minuten op. Nieuwe gebeurtenissen komen in de activiteiten, en de tab **Monitoring** toont de status. Omdat de CMS ophaalt, werkt het ook als de CMS alleen lokaal draait, en mis je niets als hij een tijd uit stond.

## Moet ik zelf een cron instellen?

Ja, **één keer, één regel**. Laravel heeft een eigen planner (`routes/console.php`) die bijhoudt wat wanneer moet. De server hoeft alleen elke minuut "kijk even" te zeggen. Kies één van deze drie:

**1. Docker of Coolify (de standaard).** De backend-image regelt het zelf. `docker/start.sh` werkt de database bij en start via supervisor drie processen in dezelfde container:
- de API;
- de scheduler (`schedule:work`);
- de queue-worker.

Crasht er één, dan start supervisor hem opnieuw. Je hoeft niets in te stellen. Draai je later meerdere backend-containers, zet dan in alle containers op één na `RUN_SCHEDULER=false`, anders haalt elke container zelf op. Met `RUN_QUEUE=false` zet je de queue-worker uit.

Gebruik dan **niet ook** de "Scheduled Tasks" van Coolify; dan draait alles dubbel.

**2. Een gewone server (VPS) zonder Docker, met crontab.** Open `crontab -e` en zet er deze regel in, met jouw pad:

```
* * * * * cd /var/www/veiligerbuiten/backend && php artisan schedule:run >> /dev/null 2>&1
```

**3. Laravel Forge, Ploi of Laravel Cloud.** Daar heet het "Scheduler": één vinkje aanzetten.

Controleren of het loopt: `php artisan schedule:list`, en na een paar minuten `php artisan veiligbuiten:scrape --status`.

Daarnaast nodig in productie:
- de **queue-worker** voor pushberichten (staat ook in `docker-compose.yml`);
- **Redis als cache** (`CACHE_STORE=redis`). De klaargezette antwoorden, ETags en de planner gebruiken de cache. Met de standaard databasecache werkt het ook, maar minder snel.

## Hoeveel kan één server aan?

Een ruwe schatting, niet gemeten:

- **Aantal pings.** 10.000 gebruikers die elk kwartier pingen geeft ongeveer 11 verzoeken per seconde.
- **Wat een ping kost.** De meeste zijn een 304 zonder databasequery. De rest is een klaargezet antwoord uit de cache (gzip, meestal enkele KB).
- **Conclusie.** Dat gaat prima op één kleine server met php-fpm en Redis.

Wordt het toch te druk, verhoog dan `VB_SYNC_MIN_INTERVAL` (bijvoorbeeld naar 1800). De app volgt dat bij de volgende ping en vraagt dan minder vaak.
