# VeiligerBuiten — livegang-checklist

Deze checklist is de actuele bron voor een publieke release. Een vinkje betekent
dat het aantoonbaar is getest in de productie-achtige omgeving, niet alleen op
localhost.

## 1. Wat al in de code zit

- [x] Account met abonnement, installatie per toestel, locatievalidatie en dagquotum van 3 meldingen (CAPTCHA vervallen).
- [x] Maximaal 3 nieuwe meldingen per uur en 10 per dag per installatie.
- [x] Tekstregels voor meldingen: geen cijfers, links, e-mailadressen, promotie,
  privacygevoelige inhoud of bekende scheldwoorden.
- [x] Duplicate-detectie, bevestigen/oplossen met één stem per installatie en
  trust-/moderatiebasis voor gevoelige categorieën.
- [x] Incident- en bronlifecycle: alleen actuele incidenten zijn publiek;
  verlopen incidenten worden gearchiveerd. Nieuwe gebruikersmeldingen verlopen
  uiterlijk na 24 uur.
- [x] NDW-import, KNMI-, Luchtmeetnet-, politie- en RWS-adapters, bronstatus en
  opslag van genormaliseerde bronrecords. Niet iedere adapter staat al aan.
- [x] Alertselectie op betrouwbaarheid, ernst, afstand, voorkeuren en dubbele
  bezorging. Oude exacte installatielocaties worden na 24 uur gewist.
- [x] Native registratie van Android/iOS-push-tokens en openen van het juiste
  incident na een tik op een notificatie.
- [x] Android FCM-serververzending, zodra Firebase is gekoppeld.

## 2. Eerst: domeinen, hosting en database

- [ ] Kies en configureer `www.veiligerbuiten.nl` voor de Nuxt-webapp en
  `api.veiligerbuiten.nl` voor Laravel. Maak DNS-records en HTTPS-certificaten.
- [ ] Host de Nuxt-output als statische site/CDN en de Laravel API op een
  afzonderlijke HTTPS-host. De API mag nooit alleen op `localhost` staan.
- [ ] Provision een productiondatabase. PostgreSQL + PostGIS is aanbevolen voor
  latere geografische analyse; MySQL 8 werkt voor de huidige MVP ook.
- [ ] Provision Redis voor cache en rate limits. Maak automatische, versleutelde
  databaseback-ups en test minimaal één herstelprocedure.
- [ ] Maak een secret store; commit nooit `.env`, Firebase- of Apple-sleutels.

### Backend productievariabelen

- [ ] Zet minimaal: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://api.veiligerbuiten.nl`,
  `FRONTEND_URL=https://www.veiligerbuiten.nl`, databasegegevens en
  `CACHE_STORE=redis`.
- [ ] Draai één keer `php artisan key:generate --force`, vervolgens
  `php artisan migrate --force`, `php artisan config:cache` en
  `php artisan route:cache`.
- [ ] Configureer precies één schedulerproces:

  ```cron
  * * * * * cd /var/www/veiligbuiten/backend && php artisan schedule:run >> /var/log/veiligbuiten-scheduler.log 2>&1
  ```

  Gebruik bij meerdere backendinstances één aangewezen schedulerinstance.
- [ ] Controleer na deploy `https://api.veiligerbuiten.nl/api/v1/health` en
  `https://api.veiligerbuiten.nl/api/v1/sources/status`.
- [ ] Voeg foutmonitoring, uptime-monitoring en alerts toe voor een falende
  scheduler, een oude bronrun, 5xx-fouten en mislukte pushbezorging.

## 3. Brondata activeren

- [ ] **NDW:** valideer de actuele publieke feed in productie. Geen sleutel
  nodig. Controleer in bronstatus een verse run na maximaal vijf minuten.
- [ ] **KNMI:** maak een gratis Open Data API-key, zet alleen
  `KNMI_API_KEY` als backend-secret en controleer de eerste uur-run.
- [ ] **Luchtmeetnet:** zet `LUCHTMEETNET_ENABLED=true`, laat de
  stationcatalogus opbouwen en controleer drempels voordat signalen publiek
  verschijnen. Deze bron is keyloos.
- [ ] **Politieberichten:** zet `POLITIE_NEWS_ENABLED=true` alleen na een
  juridische/redactionele controle. Toon ze als officiële, recente berichten —
  nooit als realtime meldkamerinformatie. Deze adapter gebruikt geen key.
- [ ] **Rijkswaterstaat:** laat uit totdat de actuele endpoint, licentie en
  drempels zijn gevalideerd; `RWS_WATER_OBSERVATIONS_URL` is nu bewust leeg.
- [ ] **P2000/NL-Alert:** niet inschakelen zonder schriftelijk toegestane,
  gelicentieerde provider/feed.
- [ ] Test per geactiveerde bron: nieuw signaal, update, verdwijnen uit de
  bron en automatische archivering.

## 4. Pushmeldingen

### Android — nog door jou te koppelen

- [ ] Maak een Firebase-project en voeg Android-app `nl.veiligerbuiten.app` toe.
- [ ] Download `google-services.json` naar `frontend/android/app/` en houd het
  buiten Git.
- [ ] Maak een server service-account met minimale Firebase Cloud Messaging
  rechten. Base64-encode het JSON-bestand en zet de waarde als
  `FCM_SERVICE_ACCOUNT_JSON_BASE64` in de backend-secretstore.
- [ ] Zet pas dan `PUSH_DELIVERY_DRIVER=fcm`.
- [ ] Bouw/sync de Android-app opnieuw en test op een echt toestel: toestemming,
  ontvangst op achtergrond, tik/deeplink, voorkeuren uit, afstandsgrens en
  dubbele update van hetzelfde incident.

### iOS — nog extern én server-side open

- [ ] Apple Developer Program, App ID `nl.veiligerbuiten.app` en de capability
  **Push Notifications** activeren.
- [ ] Maak een APNs `.p8` authentication key en bewaar Key ID, Team ID en de
  key uitsluitend als secrets.
- [ ] Voeg vóór publieke iOS-release een echte APNs-provider toe aan de backend
  of kies een provider die APNs beheert. De app registreert al tokens en
  deeplinkt al, maar de server bezorgt nu nog niet naar APNs.
- [ ] Test op fysiek iPhone-toestel met productie-APNs, niet alleen simulator.

## 5. Web- en Capacitor-build

- [ ] Web: bouw de Nuxt-output en deploy `frontend/.output/public` naar de
  gekozen static host.
- [ ] Mobiel: gebruik bij iedere release een absolute API-URL, geen localhost:

  ```sh
  cd frontend
  export NUXT_PUBLIC_API_BASE_URL=https://api.veiligerbuiten.nl/api/v1
  npm ci
  npm run generate
  npx cap sync
  ```

- [ ] Open daarna Android Studio/Xcode, verhoog versiecode/buildnummer en test
  op fysieke toestellen. Een Capacitor-build met `/api/v1` als URL kan de live
  API niet bereiken.
- [ ] Controleer CORS vanaf de webdomain; `FRONTEND_URL` moet exact de live
  origin zijn. Native Capacitor-verzoeken gebruiken de native HTTP-client.

## 6. Veiligheid, privacy en operatie — verplicht vóór publiek

- [ ] Laat een DPIA, privacyverklaring, verwerkingsregister en bewaartermijnen
  beoordelen. Leg vooral uit: anonieme installatie-ID, tijdelijk laatste
  locatiepunt, meldinhoud, push-token en brondata.
- [ ] Richt een echte moderatie- en incident-escalatierotatie in. Gevoelige
  gebruikersmeldingen mogen niet onbeheerd blijven liggen.
- [ ] Schrijf een misbruik-, takedown-, datalek- en supportproces met eigenaar
  en responstijd.
- [ ] Doe security review/pentest, dependency-audit, loadtest en hersteltest.
- [ ] Stel limieten in voor reverse proxy/WAF, database-connecties, scheduler
  runtime en bronverzoeken. Monitor logs zonder persoonsdata onnodig te bewaren.
- [ ] Verifieer alle bronlicenties/voorwaarden vóór publiek gebruik, vooral
  P2000, politie-inhoud en kaarttiles.

## 7. Play Store en App Store

- [ ] Maak Play Console- en App Store Connect-records, support-e-mailadres,
  privacy policy URL, screenshots, beschrijving, categorie en content rating.
- [ ] Vul Google Play Data safety en Apple App Privacy feitelijk in voor locatie,
  anonieme installatiegegevens en notificaties.
- [ ] Maak Android upload-keystore en beveilig deze buiten Git; test eerst via
  Google Play Internal testing.
- [ ] Configureer iOS signing/provisioning en test eerst via TestFlight.
- [ ] Controleer toestemmingsteksten, offline/foutmeldingen, kaartproviders,
  toetsenbord, contrast, screenreaders en weigeren van locatie/notificaties.

## 8. Go/no-go

Ga pas publiek als alle relevante vakjes hierboven af zijn, `/health` groen is,
bronstatus vers is, push op echte toestellen werkt (of bewust uit staat), en een
verantwoordelijke bereikbaar is voor moderatie en incidenten. Start met een
begrensde pilot; VeiligerBuiten mag nooit als vervanging voor 112 worden
gepositioneerd.
