# Current mission: handmatige taken

> Dingen die jij (de developer) zelf moet regelen. De code staat klaar en
> wacht alleen op accounts, sleutels en instellingen.

Het model: **€ 0,99 per maand via Stripe, geen proefperiode.** Registreren en betalen gebeurt op veiligerbuiten.nl (`/aanmelden`); de app logt alleen in.

## 1. Backend `.env`

```env
APP_KEY=            # php artisan key:generate (lokaal is hij nu leeg: AI-tokens versleutelen dan niet)
APP_URL=https://api.veiligerbuiten.nl   # bepaalt ook de MCP-URL die gebruikers zien
VB_BILLING_BYPASS=false               # alleen lokaal op true om zonder abonnement te testen
```

## 2. Stripe (abonnement)

1. **Product:** Stripe → Productcatalogus → product "VeiligerBuiten", prijs € 0,99, **terugkerend, maandelijks**. Kopieer de prijs-id (`price_…`).
2. **Betaalmethoden:** Instellingen → Betaalmethoden: zet iDEAL en kaarten aan. iDEAL loopt bij een abonnement daarna automatisch door als SEPA-incasso.
3. **Klantportaal:** Instellingen → Billing → Klantportaal: zet opzeggen (aan het einde van de periode), betaalmethode wijzigen en facturen aan.
4. **Webhook:** Ontwikkelaars → Webhooks → endpoint `https://api.veiligerbuiten.nl/api/v1/webhooks/stripe` met deze events:
   - `checkout.session.completed`
   - `customer.subscription.created`, `customer.subscription.updated`, `customer.subscription.deleted`
   - `invoice.paid`

   Kopieer het ondertekeningsgeheim (`whsec_…`).
5. **Sleutels in de backend:**

```env
STRIPE_SECRET=sk_live_...          # of een beperkte sleutel (rk_live_…) met schrijfrechten op Checkout, Customer portal, Customers en Subscriptions
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PRICE_ID=price_...
FRONTEND_URL=https://veiligerbuiten.nl   # Stripe stuurt bezoekers hierheen terug
```

Hoe het loopt:
- Iemand maakt op `/aanmelden` een account en gaat naar de betaalpagina van Stripe.
- Na het betalen vraagt de website de server meteen naar de betaling, zodat er niet op de webhook gewacht hoeft te worden.
- Daarna logt de klant in de app in.
- Opzeggen en de betaalmethode wijzigen gaan via `/account` (de beheerpagina van Stripe).
- Bij het verwijderen van een account wordt de klant ook bij Stripe verwijderd; dat stopt het abonnement direct.

**Testaccount voor Apple en Google:** de reviewers moeten kunnen inloggen.
- Maak op de site een account aan en geef het gratis toegang: `php artisan veiligbuiten:gift review@veiligerbuiten.nl --days=365`.
- Zet dat e-mailadres en wachtwoord bij de reviewnotities.
- Leg daar ook uit dat accounts op de website worden aangemaakt.

## 3. Push: iOS (APNs, direct)

1. Apple Developer → Keys → nieuwe key met **Apple Push Notifications service**. Download de `.p8`.
2. Zet in Xcode bij target App de capabilities **Push Notifications**, **Background Modes** (Background fetch, Background processing, Remote notifications) en **Time Sensitive Notifications** aan. `App.entitlements` en `Info.plist` staan al goed.

```env
APNS_KEY_ID=XXXXXXXXXX
APNS_TEAM_ID=XXXXXXXXXX
APNS_PRIVATE_KEY=...        # inhoud van de .p8 (PEM of base64), nooit een pad in de repo
APNS_BUNDLE_ID=nl.veiligerbuiten.app
APNS_PRODUCTION=true        # false voor builds uit Xcode
PUSH_DELIVERY_DRIVER=live   # 'log' schrijft pushes alleen naar het log
```

## 4. Push: Android (Firebase Cloud Messaging)

1. Firebase-project, Android-app `nl.veiligerbuiten.app`, download `google-services.json` naar `frontend/android/app/`.
2. Project settings → Service accounts → nieuwe private key (JSON).

```env
FCM_SERVICE_ACCOUNT_JSON_BASE64=...   # base64 van dat JSON-bestand
```

De app maakt zelf drie kanalen: `vb_danger` (gevaar, met geluid), `vb_warn` (opletten) en `vb_info` (stil).

## 5. Winkellinks op de site

```env
NUXT_PUBLIC_APP_STORE_URL=https://apps.apple.com/...
NUXT_PUBLIC_PLAY_STORE_URL=https://play.google.com/store/apps/details?id=nl.veiligerbuiten.app
```

Zolang ze leeg zijn, springen de knoppen naar de prijs-sectie.

## 6. Bronnen

| Bron | Staat | Wat nodig |
|---|---|---|
| P2000 (Zwaailicht) | aan | niets; elke 2 min met ETag |
| P2000 (Sirenes, Alarmeringen.nl, P2000-monitor) | aan | niets; elke 2 min met ETag, locaties via PDOK, samengevoegd met Zwaailicht |
| Politie Open Data | aan | niets; elk kwartier |
| NL-Alert | aan | niets; feed van public-warning.app (geen officieel gedocumenteerde API, houd hem in de gaten) |
| KNMI aardbevingen | aan | niets |
| NDW | aan | niets |
| KNMI weerwaarschuwingen | uit | `KNMI_API_KEY` (gratis, developer.dataplatform.knmi.nl) |
| Politie.nl nieuws | uit | `POLITIE_NEWS_ENABLED=true` |
| Luchtmeetnet | uit | `LUCHTMEETNET_ENABLED=true` |
| Rijkswaterstaat | uit | `RWS_WATER_OBSERVATIONS_URL` na controle van het actuele contract |
| NS storingen | niet gebouwd | gratis sleutel op apiportal.ns.nl; daarna kan het gebouwd en getest worden |

## 7. Juridisch

`/privacy` en `/voorwaarden` beschrijven wat de app echt doet. Laat ze nalezen voordat je publiceert.

## Checklist

| Taak | Status |
|---|---|
| `APP_KEY` en `APP_URL` | ⬜ |
| Stripe: prijs, iDEAL, klantportaal, webhook | ⬜ |
| Stripe-sleutels en `FRONTEND_URL` in `.env` | ⬜ |
| Testaccount met `veiligbuiten:gift` voor de app-review | ⬜ |
| APNs-key en Xcode-capabilities | ⬜ |
| Firebase + `google-services.json` | ⬜ |
| Winkellinks | ⬜ |
| KNMI-sleutel, Politie en Luchtmeetnet aanzetten | ⬜ |
| NS-sleutel aanvragen | ⬜ |
| Privacy en voorwaarden laten nalezen | ⬜ |
