# Handmatig te doen voor de lancering

Stand 27-10-2026, eind van de avond. Dit zijn de stappen die Claude niet mocht of kon doen
(sleutels, wachtwoorden, persoonsgegevens, beleidsverklaringen, bestanden groter dan 10 MB).
Volgorde = de snelste weg naar live.

## 0. Eerst

- [ ] **Chrome:** het tabblad van App Store Connect hangt op een dialoog ("Pagina verlaten?").
  Klik die weg, anders blokkeert de extensie App Store Connect.
- [ ] **Back-up van de Android-uploadsleutel:** `~/Documents/VeiligerBuiten-sleutels/` bevat
  `veiligerbuiten-upload.jks` en `release.properties` (met het wachtwoord). Zet beide in je
  wachtwoordmanager. Kwijt = via Play-support een nieuwe uploadsleutel aanvragen.
- [ ] **Pushen naar GitHub**, zodat Coolify backend en website opnieuw uitrolt (migratie
  `billing_provider`, webhook voor RevenueCat, `app-ads.txt`): `git push`.

## 1. Coolify (backend-env)

- [ ] `REVENUECAT_SECRET_KEY` = geheime sleutel uit RevenueCat → API keys (`sk_…`).
- [ ] `REVENUECAT_WEBHOOK_SECRET` = zelf maken: `openssl rand -hex 32`. Dezelfde waarde zet je in
  RevenueCat bij de webhook (stap 2).
- [ ] `REVENUECAT_ENTITLEMENT=veiligerbuiten_premium`
- [ ] De rest uit `docs/TODO.md`: Stripe (`STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_PRICE_ID`),
  Resend (`MAIL_MAILER=resend`, `RESEND_API_KEY`, `MAIL_FROM_ADDRESS`), push
  (`FCM_SERVICE_ACCOUNT_JSON_BASE64`, `APNS_PRIVATE_KEY`, `PUSH_DELIVERY_DRIVER=fcm`, `APNS_PRODUCTION=true`).
- [ ] Opnieuw deployen.
- [ ] Reviewaccount op productie: maak in de app of op de site `review@veiligerbuiten.nl` aan met
  een sterk wachtwoord, en daarna `php artisan veiligbuiten:gift review@veiligerbuiten.nl --days=365`.

## 2. RevenueCat (project VeiligerBuiten)

- [ ] **App Store-app:** in App Store Connect → Users and Access → Integrations → In-App Purchase
  een sleutel maken en de `.p8` downloaden. In RevenueCat → Apps → New → App Store: bundle-ID
  `nl.veiligerbuiten.app`, de `.p8` uploaden met Key ID en Issuer ID, opslaan.
- [ ] **Play Store-app:** een Google Cloud-serviceaccount met toegang tot de Play Console
  (RevenueCat-handleiding "Google Play service credentials"), JSON uploaden bij Apps → New →
  Google Play Store, package `nl.veiligerbuiten.app`.
- [ ] **Producten:** `nl.veiligerbuiten.app.monthly` (Apple) en `veiligerbuiten_monthly:monthly`
  (Google, na stap 4) importeren, koppelen aan entitlement `veiligerbuiten_premium`, en in offering
  `default` aan het maandpakket hangen (naast het Test Store-product).
- [ ] **Webhook:** Integrations → Webhooks → URL
  `https://api.veiligerbuiten.nl/api/v1/webhooks/revenuecat`, Authorization-header = de waarde van
  `REVENUECAT_WEBHOOK_SECRET`. Stuur een testevent (dat negeert de server netjes).
- [ ] **Publieke SDK-sleutels** (`appl_…` en `goog_…`, API keys) in `frontend/.env`:
  `NUXT_PUBLIC_REVENUECAT_IOS_KEY=…` en `NUXT_PUBLIC_REVENUECAT_ANDROID_KEY=…` (dit zijn geen
  geheimen). Zonder deze sleutels toont de paywall "Betalen lukt nu even niet".

## 3. Nieuwe builds met de RevenueCat-sleutels

Build 1.0 (1) staat al in App Store Connect, maar zonder RevenueCat-sleutel; kopen werkt daarin
niet. Na stap 2 (Node 22 en JDK 21 nodig):

```sh
cd frontend
export PATH=~/.nvm/versions/node/v22.23.2/bin:$PATH
npm run cap:build
# iOS: buildnummer 2 in Xcode (target App → General → Build) of in project.pbxproj (CURRENT_PROJECT_VERSION),
# dan Product → Archive → Distribute App → App Store Connect.
# Android: versionCode 2 in android/app/build.gradle, dan:
cd android && JAVA_HOME=~/Library/Java/JavaVirtualMachines/jbr-21.0.11/Contents/Home ./gradlew bundleRelease
# → android/app/build/outputs/bundle/release/app-release.aab
```

## 4. Google Play Console

Persoonlijk account: **eerst een gesloten test met minstens 12 testers, 14 dagen lang**, daarna
pas productie aanvragen. Vanavond live in Play kan dus niet; vanavond de test starten wel.

- [ ] **Testen → Gesloten test:** track maken, minstens 12 testers (e-maillijst of Google-groep),
  de `.aab` uploaden (Play App Signing accepteren) en de release uitrollen. Upload zelf: het
  bestand is groter dan de 10 MB die Claude kan uploaden.
- [ ] **Inkomsten genereren → Abonnementen** (kan pas na de eerste upload): product-ID
  `veiligerbuiten_monthly`, basisabonnement `monthly`, 1 maand, automatisch verlengen, € 0,99,
  activeren.
- [ ] **App-content** (antwoorden in `docs/store/LISTING.md`): inloggegevens (reviewaccount),
  contentclassificatie, doelgroep, gegevensveiligheid (nu óók "aankoopgeschiedenis"),
  overheidsapps nee, financiële functies geen, gezondheid nee.
- [ ] **Store-vermelding** staat klaar als concept; screenshot 6 (NL en EN) is al vervangen.
  Verzenden met de release.
- [ ] Na 14 dagen: **Toegang tot productie aanvragen**.

## 5. App Store Connect (VeiligerBuiten, iOS 1.0)

Klaar gezet door Claude: abonnementsgroep "VeiligerBuiten Premium" met abonnement
`nl.veiligerbuiten.app.monthly` (1 maand, € 0,99, naam en beschrijving NL/EN, reviewnotitie),
build 1.0 (1) geüpload.

- [ ] **Small Business Program** aanvragen (developer.apple.com → Programs), voor 15% in plaats van 30%.
- [ ] Versie 1.0 → **Build** kiezen (build 2, met de RevenueCat-sleutel).
- [ ] Versie 1.0 → **In-App Purchases and Subscriptions** → "Premium maandelijks" toevoegen, zodat
  het abonnement met de versie mee ter review gaat.
- [ ] Abonnement → **Review Information → Screenshot**: een screenshot van de paywall uit TestFlight.
- [ ] **Screenshot 6** vervangen door `docs/store/assets/iphone63-nl/6-instellingen.png`
  (en `iphone63-en` bij de Engelse taal; voor 6,9 inch `iphone-nl`/`iphone-en`). De oude toont "Temp".
- [ ] **App Review Information:** reviewnotities vervangen door de nieuwe tekst uit
  `docs/store/LISTING.md` (de oude zegt "no purchases"), Sign-In Information met het
  reviewaccount, en je contactgegevens (naam, telefoon, e-mail).
- [ ] **App Privacy:** "Aankoopgeschiedenis" toevoegen (gekoppeld, geen tracking,
  app-functionaliteit), daarna **Publish**.
- [ ] Content Rights, DSA-handelaarsstatus en leeftijdsclassificatie controleren.
- [ ] **Add for Review → Submit.** Releaseoptie staat op "automatisch na goedkeuring".

## 6. Daarna

- [ ] **AdMob:** zodra de apps in de winkels staan, bij beide VeiligerBuiten-apps "Store toevoegen"
  (anders blijft de advertentieweergave beperkt). `https://veiligerbuiten.nl/app-ads.txt`
  moet na de push bereikbaar zijn.
- [ ] **Winkellinks op de site** (Coolify, frontend): `NUXT_PUBLIC_APP_STORE_URL=https://apps.apple.com/app/id6826950469`
  zodra de app live is, en `NUXT_PUBLIC_PLAY_STORE_URL=https://play.google.com/store/apps/details?id=nl.veiligerbuiten.app`
  na de productierelease.
- [ ] **Mail ontvangen** op `hallo@veiligerbuiten.nl` (MX of doorsturen bij Mijndomein) en SPF van
  het hoofddomein laten kloppen.
- [ ] **Push testen** op een echte iPhone en Android.
- [ ] **Kopen testen**: sandbox-account (Apple) en licentietester (Google) die kopen, opzeggen en
  herstellen; controleren dat `/me` `provider: app_store` / `play_store` geeft.
- [ ] GitHub-repo privé maken? (Staat nu openbaar.)
