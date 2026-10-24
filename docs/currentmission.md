# Current mission: handmatige taken

> Dingen die jij (de developer) zelf moet regelen. De code staat klaar en
> wacht alleen op accounts, sleutels en instellingen.

Het model: **€ 0,99 per maand via RevenueCat, geen proefperiode, geen advertenties.**

## 1. Backend `.env`

```env
APP_KEY=            # php artisan key:generate (lokaal is hij nu leeg: AI-tokens versleutelen dan niet)
APP_URL=https://api.veiligbuiten.nl   # bepaalt ook de MCP-URL die gebruikers zien
VB_BILLING_BYPASS=false               # alleen lokaal op true om zonder abonnement te testen
```

## 2. RevenueCat (abonnement)

1. Maak in App Store Connect en Google Play Console een maandabonnement van € 0,99 aan, **zonder proefperiode**.
2. Maak in RevenueCat een project, koppel beide winkels en maak het entitlement `premium` met dat product.
3. Zet de standaard-offering met een `monthly`-package.
4. Webhook: `https://api.veiligbuiten.nl/api/v1/webhooks/revenuecat`, met een Authorization-header die je zelf kiest.

```env
# backend/.env
REVENUECAT_SECRET_KEY=sk_...           # REST-sleutel (geheim)
REVENUECAT_WEBHOOK_SECRET=...           # zelfde waarde als de Authorization-header in RevenueCat
REVENUECAT_ENTITLEMENT=premium

# bij het bouwen van de app (publieke SDK-sleutels)
NUXT_PUBLIC_REVENUECAT_IOS_KEY=appl_...
NUXT_PUBLIC_REVENUECAT_ANDROID_KEY=goog_...
```

De app gebruikt het account-id als RevenueCat-gebruikers-id, zodat de webhook de aankoop aan het juiste account koppelt.

## 3. Push: iOS (APNs, direct)

1. Apple Developer → Keys → nieuwe key met **Apple Push Notifications service**. Download de `.p8`.
2. Zet in Xcode bij target App de capabilities **Push Notifications**, **Background Modes** (Background fetch, Background processing, Remote notifications) en **Time Sensitive Notifications** aan. `App.entitlements` en `Info.plist` staan al goed.

```env
APNS_KEY_ID=XXXXXXXXXX
APNS_TEAM_ID=XXXXXXXXXX
APNS_PRIVATE_KEY=...        # inhoud van de .p8 (PEM of base64), nooit een pad in de repo
APNS_BUNDLE_ID=nl.veiligbuiten.app
APNS_PRODUCTION=true        # false voor builds uit Xcode
PUSH_DELIVERY_DRIVER=live   # 'log' schrijft pushes alleen naar het log
```

## 4. Push: Android (Firebase Cloud Messaging)

1. Firebase-project, Android-app `nl.veiligbuiten.app`, download `google-services.json` naar `frontend/android/app/`.
2. Project settings → Service accounts → nieuwe private key (JSON).

```env
FCM_SERVICE_ACCOUNT_JSON_BASE64=...   # base64 van dat JSON-bestand
```

De app maakt zelf drie kanalen: `vb_danger` (gevaar, met geluid), `vb_warn` (opletten) en `vb_info` (stil).

## 5. Winkellinks op de site

```env
NUXT_PUBLIC_APP_STORE_URL=https://apps.apple.com/...
NUXT_PUBLIC_PLAY_STORE_URL=https://play.google.com/store/apps/details?id=nl.veiligbuiten.app
```

Zolang ze leeg zijn, springen de knoppen naar de prijs-sectie.

## 6. Bronnen

| Bron | Staat | Wat nodig |
|---|---|---|
| P2000 (Zwaailicht) | aan | niets; elke 2 min met ETag |
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
| RevenueCat + producten in beide winkels | ⬜ |
| RevenueCat-sleutels in `.env` en bij de app-build | ⬜ |
| APNs-key en Xcode-capabilities | ⬜ |
| Firebase + `google-services.json` | ⬜ |
| Winkellinks | ⬜ |
| KNMI-sleutel, Politie en Luchtmeetnet aanzetten | ⬜ |
| NS-sleutel aanvragen | ⬜ |
| Privacy en voorwaarden laten nalezen | ⬜ |
