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

## Nog te doen (keuzes voor de livegang)

1. **Productieserver.** De Dockerfile start `php artisan serve`. Dat is PHP's ontwikkelserver: hij verwerkt één verzoek tegelijk en heeft geen TLS. Gebruik php-fpm met nginx (of FrankenPHP/Octane) achter HTTPS. Zet `APP_DEBUG=false`; met debug aan lekken foutpagina's SQL en paden. Voeg ook een `scheduler`-container toe (`php artisan schedule:work`), anders draaien imports en opruimen niet.
2. **Tokens verlopen nooit** (`sanctum.expiration = null`). Overweeg een vervaltermijn met stil verversen in de app.
3. **Opslag op het toestel.** Het login-token staat in Capacitor Preferences en in de KV-opslag van de achtergrondrunner, en die zijn niet versleuteld. Overweeg Keychain/Keystore via een secure-storage-plugin.
4. **MCP-token in de URL** (`/mcp/vb_…`). Het komt in de logs van de webserver en de proxy terecht; maskeer het daar.
5. **Beveiligingsheaders** voor de website (HSTS, CSP, X-Frame-Options), in te stellen op de reverse proxy.
6. **Resterende npm-meldingen.** MapLibre (alleen de popup-sanitiser, die de app niet gebruikt; de oplossing is de volgende hoofdversie) en `node-forge` (alleen in de lokale dev-server). Neem ze mee bij de volgende grote update.
7. **RevenueCat-webhooks** kunnen in de verkeerde volgorde binnenkomen. Bij twijfel volgt een `refresh` via de API.
