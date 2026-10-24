# Externe koppelingen en secrets

Gebruik nooit secrets in `frontend/.env`, in de mobiele app of in Git. Bewaar
serversleutels alleen in de secretstore van de backend/hostingomgeving.

| Koppeling | Nodig voor | Sleutel nodig? | Huidige status |
| --- | --- | --- | --- |
| OpenFreeMap + OpenStreetMap | De kaart | Nee | Actief via de publieke Liberty-style. Voor groot verkeer later eigen tile-hosting plannen. |
| NDW Open Data SRTI | Verkeers- en veiligheidsberichten | Nee | Feed-URL staat als `NDW_OPEN_DATA_URL` in de backend. `veiligbuiten:ingest-ndw` normaliseert actuele puntmeldingen naar officiële verkeer-/blokkade-incidenten. |
| KNMI Open Data | Weerwaarschuwingen | Ja, gratis API-key | Vul `KNMI_API_KEY` alleen in de backend in. |
| RevenueCat | Abonnement € 0,99 per maand | Ja | Melden en kaartdata vereisen een actief abonnement; max. 3 meldingen per dag per account (`ReportQuota`). De oude CAPTCHA is vervallen. |
| Firebase Cloud Messaging | Android pushmeldingen | Ja | Vereist voor push. Firebase-project, Android-app `nl.veiligerbuiten.app`, `google-services.json` in Android en service-account JSON als backend-secret. |
| Apple Push Notification service | iOS pushmeldingen | Ja | Vereist voor iOS-push. Apple Developer Program, Push Notifications capability, APNs `.p8` key, Key ID en Team ID. |
| Google Play Console | Android publiceren | Geen API-key voor handmatige release | Vereist een Play Console-account en upload-keystore. Alleen CI-upload gebruikt later een service-account. |
| Apple Developer + App Store Connect | iOS/TestFlight publiceren | Geen algemene API-key voor handmatige release | Vereist Apple Developer-account, signing en App Store Connect. CI-upload kan later een App Store Connect API-key gebruiken. |
| P2000 via Zwaailicht | Brandweer/politie/ambulance | Nee | Actief: de publieke Atom-feed elke 2 minuten, met ETag en herkenbare User-Agent. Ambulance-ritten tellen als 'goed om te weten'. |
| NL-Alert | Overheidswaarschuwingen met gebied | Nee | Actief via public-warning.app (geen officieel gedocumenteerde API). |
| KNMI aardbevingen | Bevingen vanaf M1,5 in NL | Nee | Actief via de FDSN-eventservice. |
| Resend/Postmark | E-mail voor de oude account-reset/verify-routes | Alleen als die routes blijven | Optioneel. De kernapp is accountloos; verwijder deze starter-routes of configureer `RESEND_API_KEY`/`POSTMARK_API_KEY`. |
| Sentry | Foutmonitoring | Ja, DSN | Sterk aanbevolen voor productie, maar nog niet geïmplementeerd. |

## Backend-secrets voor productie

Minimaal:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.jouwdomein.nl
FRONTEND_URL=https://jouwdomein.nl
DB_CONNECTION=pgsql
DB_HOST=...
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
REDIS_HOST=...
KNMI_API_KEY=...
PUSH_DELIVERY_DRIVER=log
# Pas na Firebase-configuratie op fcm zetten.
FCM_SERVICE_ACCOUNT_JSON_BASE64=...
```

De frontend krijgt alleen een publieke API-URL:

```dotenv
NUXT_PUBLIC_API_BASE_URL=https://api.jouwdomein.nl/api/v1
```

Voor de gewone Nuxt-webapp mag deze waarde leeg blijven tijdens lokale
ontwikkeling: Nitro proxyt dan `/api` naar Laravel. Voor `npm run generate`
en elke Capacitor-build is de absolute **HTTPS** URL verplicht, omdat een
telefoon geen toegang heeft tot jouw lokale proxy of `localhost`.

## Push: wat is gebouwd en wat moet je zelf koppelen

De app registreert op een native toestel een push-token aan de anonieme
installatie. De backend selecteert alleen relevante, bevestigde incidenten,
maakt een bezorgrecord aan, voorkomt dubbele alerts en registreert daarna ook
wanneer iemand op de notificatie tikt.

### Android (Firebase Cloud Messaging)

1. Maak een Firebase-project en voeg Android-package `nl.veiligerbuiten.app` toe.
2. Plaats het gedownloade `google-services.json` in `frontend/android/app/`.
3. Maak in Google Cloud een service-account met Firebase Cloud Messaging-rechten.
4. Base64-encode het volledige JSON-bestand en zet het uitsluitend als
   `FCM_SERVICE_ACCOUNT_JSON_BASE64` in de backend-secretstore.
5. Zet `PUSH_DELIVERY_DRIVER=fcm` in de productieomgeving.
6. Bouw de native app opnieuw met `npm run cap:build` en test op een fysiek
   Android-toestel.

Zonder die credentials gebruikt de backend bewust de `log`-driver: alertkeuze
en bezorgregistratie werken dan wel, maar er wordt niets naar Firebase gestuurd.

### iOS (APNs)

iOS registreert al een native token en kan een getikte notificatie naar het
juiste incident deeplinken. Daadwerkelijke bezorging wacht bewust op een
APNs-provider: daarvoor zijn een Apple Developer Program-lidmaatschap, de Push
Notifications-capability, een APNs `.p8`-key, Key ID, Team ID en Bundle ID
nodig. Voeg deze server-side provider pas met die secrets toe; de app doet niet
alsof iOS-push al live is.
