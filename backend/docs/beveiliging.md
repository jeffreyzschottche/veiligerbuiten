# Beveiliging

Review van 24 oktober 2026. Wat hieronder als "opgelost" staat, heeft een test in `tests/Feature/SecurityTest.php`.

## Opgelost

| Risico | Wat er kon gebeuren | Oplossing |
|---|---|---|
| Kwetsbare dependencies | Laravel TokenGuard-authenticatie-omzeiling (critical), Guzzle host-bypass (high), Capacitor Android/iOS (critical), Vue SSR-XSS | `composer update` en `npm audit fix` binnen de bestaande versiegrenzen. `composer audit` meldt nu niets meer. |
| SSRF via webhook | Een gebruiker vult `https://169.254.169.254/...` of een intern adres in; de server doet daar een POST naartoe en de testknop verraadt wat er bereikbaar is. | `SafeUrl`: alleen publieke IP's, bij opslaan én bij elke verzending opnieuw gecontroleerd. Het IP wordt vastgepind (geen DNS-rebinding), redirects staan uit en de testknop geeft geen foutdetails terug. |
| Stemmen manipuleren | Eén betalend account maakt nieuwe installaties aan (10 per minuut) en bevestigt een melding tientallen keren ("gevaar" met een groot bereik), of stemt een echte melding weg. | Eén stem per account per melding (`incident_votes.user_id`, uniek), alleen met een eigen of nog niet gekoppelde installatie. Wie iets meldt, kan het niet ook nog bevestigen of nogmaals melden. |
| Accounts opsporen | "Wachtwoord vergeten" gaf voor onbekende adressen een andere melding. | Het antwoord is nu altijd hetzelfde. |
| Credential stuffing | Login was alleen per IP beperkt. | Ook per e-mailadres: 5 pogingen per kwartier. |
| Gestolen token | Een token bleef geldig na een wachtwoordreset. Met alleen een token kon je het e-mailadres wijzigen en zo het account overnemen. | Een reset logt alle toestellen uit, een wachtwoordwijziging alle andere. Een nieuw e-mailadres vraagt het huidige wachtwoord en moet opnieuw bevestigd worden; dat laatste werkte eerder ook niet. |
| Script via bronlink | Een externe feed kon een `javascript:`-link aanleveren, die de app als link toonde. | De server bewaart alleen http(s)-links en de app controleert het nog een keer. |
| Geheimen in de Docker-image | Er was geen `.dockerignore`, dus `COPY . .` nam `.env` en `.env.docker` (met sleutels), logs en de lokale database mee. | `.dockerignore` voor backend en frontend. |

## Review van 7 november 2026

Tests in `tests/Feature/SecurityHardeningTest.php`.

| Risico | Wat er kon gebeuren | Oplossing |
|---|---|---|
| Eén rate limit voor iedereen | Laravel vertrouwde Traefik niet en zag dus bij elk verzoek hetzelfde IP. Bovendien deelden alle routes per IP of account één teller. Tien anonieme verzoeken per minuut (van wie dan ook) blokkeerden inloggen, registreren en het registreren van pushtokens voor alle gebruikers. | `config/trustedproxy.php` vertrouwt alleen privé-netwerken (Traefik); `ThrottlePerRoute` geeft elke route een eigen teller, per account of per IP. |
| Beheerstatus rood maken | 20 nep-webhookverzoeken per uur zetten de status in de CMS op "failed". | Afgewezen webhooks worden als waarschuwing gelogd en tellen niet als fout. |
| MCP-batches | Eén verzoek met duizenden tool-aanroepen omzeilde de rate limit; een lijst als argument gaf een 500. | Maximaal 10 berichten per batch; argumenten worden op type gecontroleerd. |
| Toestel losmaken | Wie een installatietoken kende, kon het toestel van een ander account ontkoppelen of overnemen. | Alleen de eigenaar (of iedereen zolang het toestel van niemand is). Uitloggen ontkoppelt het toestel ook op de server. |
| Tokens verliepen nooit | Een gelekt token bleef voor altijd geldig. | Een token dat 90 dagen niet gebruikt is, werkt niet meer en wordt 's nachts opgeruimd (`VB_TOKEN_IDLE_DAYS`). |
| Ongeremde statusendpoints | `/health` en `/sources/status` deden bij elk verzoek databasewerk. | Throttle, en de bronstatus wordt een minuut gecachet. |
| Headers | Geen HSTS op de API. | nginx stuurt HSTS, `nosniff`, `X-Frame-Options` en `Referrer-Policy`. |
| Symfony-advisories | O.a. trusted proxies en header-injectie in mail. | `composer update "symfony/*"`. |

## Nog te doen (keuzes voor de livegang)

1. **Productieserver.** De Dockerfile start `php artisan serve`. Dat is PHP's ontwikkelserver: hij verwerkt één verzoek tegelijk en heeft geen TLS. Gebruik php-fpm met nginx (of FrankenPHP/Octane) achter HTTPS. Zet `APP_DEBUG=false`; met debug aan lekken foutpagina's SQL en paden. De `scheduler`-container staat nu in `docker-compose.yml` (zie `docs/server-en-ophalen.md`).
2. **Tokens:** verlopen nu na 90 dagen zonder gebruik (zie hierboven). Een harde maximale levensduur vraagt stil verversen in de app.
8. **RevenueCat-ID's zijn raadbaar.** De app logt bij RevenueCat in met het account-ID (1, 2, 3, …). Met de publieke SDK-sleutel uit de app kan iedereen zo de abonnementsstatus van elk account opvragen. Oplossing: een willekeurige ID (UUID) per account, met een app-update en een migratie van bestaande abonnees.
3. **Opslag op het toestel.** Het login-token staat in Capacitor Preferences en in de KV-opslag van de achtergrondrunner, en die zijn niet versleuteld. Overweeg Keychain/Keystore via een secure-storage-plugin.
4. **MCP-token in de URL** (`/mcp/vb_…`). Het komt in de logs van de webserver en de proxy terecht; maskeer het daar.
5. **Beveiligingsheaders** voor de website (HSTS, CSP, X-Frame-Options), in te stellen op de reverse proxy. De API heeft ze nu (`docker/nginx.conf`).
6. **Resterende npm-meldingen.** MapLibre (alleen de popup-sanitiser, die de app niet gebruikt; de oplossing is de volgende hoofdversie) en `node-forge` (alleen in de lokale dev-server). Neem ze mee bij de volgende grote update.
7. **Stripe-webhooks:**
   - Ze tellen alleen met een geldige `Stripe-Signature` (HMAC, hoogstens 5 minuten oud).
   - Ze kunnen in de verkeerde volgorde binnenkomen. Daarom gebruiken we een event alleen als seintje en halen we het abonnement zelf opnieuw op bij Stripe.
   - Een checkout-sessie telt alleen voor het account dat hem startte (`client_reference_id`).
