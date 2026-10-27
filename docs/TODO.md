# To-do lancering VeiligerBuiten

Bijgewerkt op 27-10-2026. De website draait op Coolify (veiligerbuiten.nl), de API op
api.veiligerbuiten.nl (MySQL, cache/queue/sessies via de database).

**Alle handmatige stappen voor de lancering, op volgorde: [HANDMATIG.md](HANDMATIG.md).**

## 1. Push koppelen

- [ ] In Coolify bij de backend de sleutels als base64 invullen:
  ```sh
  base64 -i ~/Downloads/veiligerbuiten-firebase-adminsdk-fbsvc-ffd127481f.json | pbcopy   # FCM_SERVICE_ACCOUNT_JSON_BASE64
  base64 -i ~/Downloads/AuthKey_ARJ994W782.p8 | pbcopy                                     # APNS_PRIVATE_KEY
  ```
- [ ] `PUSH_DELIVERY_DRIVER=fcm`, `APNS_PRODUCTION=true`, en opnieuw deployen.
- [ ] Testen op een echte iPhone en Android-telefoon: plek opslaan, melding in de buurt, pushbericht komt binnen.

## 2. RevenueCat terug in de app

Waarom: het abonnement is € 0,99 per maand. Stripe kost per betaling 1,5% + € 0,25 ≈ € 0,26
(27%), Apple en Google via het Small Business Program 15% (€ 0,15). In de app betalen gaat dus
via de stores; de website houdt Stripe.

- [x] RevenueCat terug: pakket `@revenuecat/purchases-capacitor`, `Paywall.vue` (kopen en herstellen),
  `useBilling.ts`, backend `StoreBilling` met webhook `/webhooks/revenuecat`.
- [x] Samen met Stripe: één abonnement per account via `billing_provider`; een einde bij het ene
  kanaal overschrijft een lopend abonnement bij het andere niet, Stripe-checkout is geblokkeerd
  zolang er een winkelabonnement loopt, en dubbel betalen komt als waarschuwing in het logboek.
- [x] Registreren in de app weer toegestaan.
- [x] Teksten in de app, `docs/store/LISTING.md`, privacy en voorwaarden bijgewerkt.
- [ ] Backend-env in Coolify: RevenueCat-sleutel en webhookgeheim (zie HANDMATIG.md).
- [ ] RevenueCat: App Store- en Play-app koppelen, producten, webhook, publieke sleutels in de build.

## 3. App Store uitbrengen

- [x] Abonnement aangemaakt: `nl.veiligerbuiten.app.monthly` in groep "VeiligerBuiten Premium".
- [ ] Small Business Program aanvragen.
- [ ] Abonnement koppelen in RevenueCat (app-specifiek gedeeld geheim / In-App Purchase-sleutel).
- [x] Echte AdMob-ID's in de app, plus `app-ads.txt` op de site.
- [x] Build 1.0 (1) tegen `https://api.veiligerbuiten.nl/api/v1` geüpload (nog zonder RevenueCat-sleutel).
- [ ] Build 2 met de RevenueCat-sleutel.
- [ ] Zelf in ASC: App Privacy publiceren, Content Rights, trader-status (DSA), contactgegevens.
- [ ] Reviewaccount op productie: `php artisan veiligbuiten:gift review@veiligerbuiten.nl --days=365`,
  en de login invullen bij Sign-In Information.
- [ ] Build kiezen, abonnement meesturen met de versie, Add for Review.

## 4. Play Store uitbrengen

- [ ] Abonnement aanmaken in de Play Console (€ 0,99/maand), servicefee van 15% staat standaard aan.
- [ ] Koppelen in RevenueCat (serviceaccount met toegang tot de Play Console).
- [ ] Zelf onder App-content: alle verklaringen (antwoorden in `docs/store/LISTING.md`) en app-toegang
  met het reviewaccount.
- [x] Uploadsleutel gemaakt (`~/Documents/VeiligerBuiten-sleutels`) en `.aab` gebouwd.
- [ ] Gesloten test (12 testers, 14 dagen; verplicht voor een persoonlijk account), daarna productie.

## 5. Stripe koppelen (website)

- [x] Backend verwerkt alleen abonnementen met de ingestelde `STRIPE_PRICE_ID`, zodat events van andere apps in hetzelfde Stripe-account worden genegeerd.
- [x] Eén webregistratie via `/aanmelden`, direct gevolgd door Stripe Checkout. Weblogin via `/account`; de mobiele app logt alleen in.
- [ ] Product van € 0,99/maand aanmaken in Stripe.
- [ ] Webhook naar `https://api.veiligerbuiten.nl/api/v1/webhooks/stripe`.
- [ ] `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_PRICE_ID` in Coolify.
- [ ] Testbetaling op de website en opzeggen via het klantportaal.

## 6. Mail via Resend

De code is klaar; activeren met `MAIL_MAILER=resend` en de mailinstellingen hieronder.
Zie [MAIL.md](MAIL.md) voor activeren en testen.

- [x] Domein `veiligerbuiten.nl` toevoegen in Resend en hun DNS-records bij Mijndomein zetten
  (DKIM, en SPF/MX voor het `send.`-subdomein).
- [ ] SPF van het hoofddomein laten kloppen: DMARC staat op reject, dus mail die niet in SPF/DKIM
  past wordt geweigerd.
- [x] Resend PHP-pakket toegevoegd aan de backend en lockfile.
- [x] Welkomstmail met bevestigingsknop, nieuwe bevestigingsmail en wachtwoordreset in VeiligerBuiten-stijl, NL/EN, HTML en tekstversie.
- [x] Veilige, tijdelijke bevestigingslinks en eenmalige resetlinks getest; mails gebruiken de bestaande wachtrij.
- [ ] In Coolify: `MAIL_MAILER=resend`, `RESEND_API_KEY=…`, `MAIL_FROM_ADDRESS=hallo@veiligerbuiten.nl`.
- [ ] Testen: wachtwoord vergeten op de website, mail komt aan en niet in spam.
- [ ] Mail óntvangen op `hallo@veiligerbuiten.nl`: MX-records (bijvoorbeeld mailforwarding bij
  Mijndomein naar je eigen inbox). Dat adres staat als contact in de stores.

## Los
- [x] Screenshot 6 opnieuw gemaakt met de demonaam "Sanne" en vervangen in Play (NL/EN).
- [ ] Screenshot 6 ook vervangen in App Store Connect.
- [ ] GitHub-repo staat op openbaar: privé maken?
