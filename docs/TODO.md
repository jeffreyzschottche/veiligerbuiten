# To-do lancering VeiligerBuiten

Bijgewerkt op 27-10-2026. De website draait op Coolify (veiligerbuiten.nl), de API op
api.veiligerbuiten.nl (MySQL, cache/queue/sessies via de database).

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

- [ ] RevenueCat-code terughalen uit de eigen geschiedenis (vóór commit `6fc0c76`):
  pakket `@revenuecat/purchases-capacitor`, `Paywall.vue`, `useBilling.ts`, backend-webhook.
- [ ] Laten samenwerken met Stripe: één abonnement per account, `billing_provider` bijhouden,
  een Stripe- en store-abonnement mogen elkaar niet overschrijven of dubbel laten betalen.
- [ ] Registreren in de app weer toestaan (Apple eist dat bij aankopen in de app).
- [ ] Teksten in de app, `docs/store/LISTING.md`, privacy en voorwaarden bijwerken
  (aankopen via Apple/Google, aankoopgeschiedenis in de privacylabels).
- [ ] Backend-env in Coolify: RevenueCat-webhooksleutel en API-sleutel.

## 3. App Store uitbrengen

- [ ] Abonnement aanmaken in App Store Connect (€ 0,99/maand), Small Business Program aanvragen.
- [ ] Abonnement koppelen in RevenueCat (app-specifiek gedeeld geheim / In-App Purchase-sleutel).
- [ ] Echte AdMob-ID's in de app (nu nog de test-ID's van Google).
- [ ] Mobiele build tegen `https://api.veiligerbuiten.nl/api/v1`, archiveren in Xcode en uploaden.
- [ ] Zelf in ASC: App Privacy publiceren, Content Rights, trader-status (DSA), contactgegevens.
- [ ] Reviewaccount op productie: `php artisan veiligbuiten:gift review@veiligerbuiten.nl --days=365`,
  en de login invullen bij Sign-In Information.
- [ ] Build kiezen, abonnement meesturen met de versie, Add for Review.

## 4. Play Store uitbrengen

- [ ] Abonnement aanmaken in de Play Console (€ 0,99/maand), servicefee van 15% staat standaard aan.
- [ ] Koppelen in RevenueCat (serviceaccount met toegang tot de Play Console).
- [ ] Zelf onder App-content: alle verklaringen (antwoorden in `docs/store/LISTING.md`) en app-toegang
  met het reviewaccount.
- [ ] `.aab` bouwen, uploaden en een productierelease starten.

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
- [ ] Screenshot 6 toont "Temp · temp@mail.com": opnieuw maken met een demonaam.
- [ ] GitHub-repo staat op openbaar: privé maken?
