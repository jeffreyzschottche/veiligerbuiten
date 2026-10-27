# Accountmails via Resend

Het domein is op 27 oktober 2026 geverifieerd in Resend. De backend gebruikt Laravel's
Resend-driver met `resend/resend-php`.

## Activeren in Coolify

Zet bij de **backend** als runtimevariabelen:

```dotenv
MAIL_MAILER=resend
RESEND_API_KEY=re_JOUW_SLEUTEL
MAIL_FROM_ADDRESS=hallo@veiligerbuiten.nl
MAIL_FROM_NAME="VeiligerBuiten"
APP_URL=https://api.veiligerbuiten.nl
FRONTEND_URL=https://veiligerbuiten.nl
```

De API-key heeft Sending access voor `veiligerbuiten.nl`. Bewaar hem uitsluitend in
de backend. Installeer het Composer-pakket niet handmatig in de container: de
Docker-build doet dit via de bijgewerkte lockfile.

Deploy backend en frontend. De backend verstuurt mails via de bestaande queue-worker
(`RUN_QUEUE=true`). `QUEUE_CONNECTION=database` is de standaard; een al werkende
Redis-queue kan ook. Gebruik een vaste `APP_KEY`: de queued mails worden versleuteld
om resetlinks in jobgegevens af te schermen. Verander een bestaande APP_KEY niet.

## Welke mails?

- Bij registratie: één welkomstmail met bevestigingsknop, uitleg over de kaart,
  plekken, meldingen en het abonnement. Registratie start daarna Stripe Checkout.
- Bij opnieuw aanvragen of een gewijzigd e-mailadres: een bevestigingsmail.
- Bij wachtwoord vergeten: een tijdelijke resetlink. Na een geslaagde reset vervallen
  alle accounttokens en kan de link niet worden hergebruikt.

De taal is die van het account. Alle mails hebben HTML en een tekstversie. Het logo
zit als inline PNG in de mail en hoeft niet extern opgehaald te worden.

Bevestigingslinks zijn 60 minuten geldig en openen `/verify-email` op de website.
De website stuurt alleen de toegestane verificatieparameters door naar de eigen API.
De API controleert de handtekening, vervaldatum en de hash van het huidige e-mailadres.
Verificatie werkt zonder login. Een al gebruikte, nog geldige bevestigingslink geeft
opnieuw een bevestiging. E-mailbevestiging en een betaald abonnement zijn aparte zaken.

## Controleren na deploy

1. Registreer een nieuw account met een eigen, bereikbare mailbox. Controleer de
   welkomstmail en klik op de bevestigingsknop. Je hoort de bevestiging op de website
   te zien; de melding om je e-mail te bevestigen verdwijnt op `/account`.
2. Vraag via `/forgot-password` een resetlink aan. Kies een nieuw wachtwoord en log
   daarmee in. Dezelfde resetlink mag daarna niet opnieuw werken.
3. Controleer de verzending en eventuele fouten in Resend. Als er niets verstuurd
   wordt, controleer de backendlogs, queue-worker en `php artisan queue:failed`.
4. Controleer ook de taal van een Engelstalig account en de spammap.

Ontvangst op `hallo@veiligerbuiten.nl` vraagt daarnaast een mailbox of forwarding bij
je mailprovider. Het verifiëren van een verzenddomein maakt geen inbox aan.
