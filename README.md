# Veiliger Buiten — Nuxt 4 + Laravel + Capacitor

Een volledige starterkit met Laravel (Sanctum) API, Nuxt 4-frontend en Capacitor voor iOS en Android. Inclusief registratie, login, e-mailverificatie en wachtwoord-resetflows.

## Project structuur

```
project-root/
├── backend/   # Laravel API
└── frontend/  # Nuxt 4 + Capacitor app
```

## Benodigdheden

- PHP 8.2+
- Composer
- Node.js 22+
- npm
- MySQL of een andere ondersteunde database

## Backend setup

```bash
cd backend
composer install
cp .env.example .env   # vul waarden in
php artisan key:generate
php artisan migrate
php artisan serve
```

Belangrijke `.env` variabelen:

```
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:3000
SANCTUM_STATEFUL_DOMAINS=localhost:3000
DB_* (database instellingen)
MAIL_* (Mailtrap of Mailhog)
```

Handige artisan commando's:

- `php artisan migrate:fresh --seed` - reset database
- `php artisan queue:work` - voor email queueing (optioneel)

## Frontend setup

```bash
cd frontend
npm install
cp .env.example .env  # indien gewenst
npm run dev
```

`frontend/.env`:

```
NUXT_PUBLIC_API_BASE_URL=http://localhost:8000/api/v1
```

## Mobiele app (Capacitor)

De mobiele builds gebruiken de statisch gegenereerde Nuxt-app uit `.output/public`.

```bash
cd frontend
npm install
npm run cap:build
npx cap add android  # eenmalig, vereist Android Studio
npx cap add ios      # eenmalig, vereist macOS en Xcode
```

Open daarna het gewenste native project met `npm run cap:android` of `npm run cap:ios`.

Voor een echte mobiele build moet `NUXT_PUBLIC_API_BASE_URL` een via HTTPS bereikbare API-URL zijn; `localhost` verwijst op een telefoon naar de telefoon zelf.

## Development workflow

1. Start backend: `cd backend && php artisan serve`
2. Start frontend: `cd frontend && npm run dev`
3. Gebruik Mailtrap/Mailhog voor mails (reset / verify links).

## API endpoints

Alle routes bevinden zich onder `/api/v1`.

## VeiligerBuiten MVP

De startpagina is de kaart-first VeiligerBuiten-MVP. Zonder actieve backend toont zij bewust gelabelde voorbeeldsituaties, zodat de interface direct bruikbaar is. Zodra Laravel draait en de migratie is uitgevoerd, haalt de app live incidenten op via de API.

```bash
# Terminal 1
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve

# Terminal 2 (Node 22)
cd frontend
npm install
npm run dev
```

Voor een echte locatie in de browser moet je locatietoegang toestaan. Nieuwe meldingen vallen tijdelijk terug op de lokale kaart wanneer de API niet bereikbaar is.

### Incidenten-API

| Methode | Endpoint | Beschrijving |
| ------- | -------- | ------------ |
| GET | `/incidents?latitude=52.37&longitude=4.90` | Actuele incidenten in de omgeving |
| POST | `/incidents` | Nieuwe melding plaatsen |
| POST | `/incidents/{incident}/confirm` | Situatie bevestigen |
| POST | `/incidents/{incident}/resolve` | Melden dat de situatie voorbij is |

| Methode | Endpoint | Authenticatie | Beschrijving |
| ------- | -------- | ------------- | ------------ |
| POST    | `/register` | Nee | Registratie, retourneert token + user |
| POST    | `/login` | Nee | Login |
| POST    | `/forgot-password` | Nee | Verstuurt reset email |
| POST    | `/reset-password` | Nee | Stelt nieuw wachtwoord in |
| GET     | `/email/verify/{id}/{hash}` | Gesigneerd | Verifieert email |
| POST    | `/logout` | Sanctum token | Logout en revoke token |
| GET     | `/me` | Sanctum token | Huidige gebruiker |
| POST    | `/email/resend` | Sanctum token | Resend verificatie mail |

## Testing checklist

- Registratie flow (inclusief verificatie mail)
- Login / logout
- Forgot + reset password
- Resend verificatie vanuit dashboard
- API direct testen met tools zoals Insomnia/Postman

Veel succes!
