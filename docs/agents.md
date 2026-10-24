# Veilig Buiten - Agent Documentation

> Dit document is bedoeld voor AI coding agents die aan dit project werken.
> **Lees ook: [roadmap.md](./roadmap.md)** voor openstaande taken.

## Project Overview

**Veilig Buiten** is een Nederlandse veiligheidsapp die real-time incidenten toont op een kaart. Gebruikers kunnen incidenten melden, bevestigen en als opgelost markeren. De app combineert crowd-sourced meldingen met officiële databronnen (P2000, KNMI, NDW, etc.).

## Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 12, PHP 8.2+, PostgreSQL + PostGIS |
| Frontend | Nuxt 4, Vue 3.5, MapLibre GL + OpenFreeMap, Capacitor 8 (Background Runner, RevenueCat) |
| Mobile | Capacitor 8 (iOS + Android) |
| Auth | Laravel Sanctum (token-based) |
| Queue | Redis + Laravel Queues |

## Directory Structure

```
/backend
├── app/
│   ├── Console/Commands/   # Ingestion, expiry, alerts
│   ├── Http/Controllers/   # API endpoints
│   ├── Models/             # Eloquent models
│   ├── Services/           # Business logic
│   └── Support/            # GeoUtils, CategoryConfig
├── routes/
│   ├── api.php             # API routes (v1 prefix, rate limited)
│   └── console.php         # Scheduled tasks

/frontend
├── components/             # Vue components
├── composables/            # useApi, useGeo, useIncidents, etc.
├── pages/                  # File-based routing
├── stores/                 # Pinia (auth)
└── types/                  # TypeScript definitions

/docs                       # This documentation
```

## Core Concepts

### Incidents

| Field | Description |
|-------|-------------|
| `category` | brand, geweld, onveilig, verkeer, blokkade, drukte, weer, stoffen, dier, anders |
| `status` | gemeld → bevestigd → officieel → opgelost/verlopen |
| `visibility` | public, private, archived |
| `confidence` | 0.0-1.0 (officieel=1, user=0.2 + 0.2 per bevestiging) |
| `severity` | 1-5 (bepaalt alert level) |
| `source` | 'user' of externe bron (ndw, knmi, p2000_zwaailicht, etc.) |

### Confidence & Voting

```
User report:     20% base
+1 bevestiging:  +20% (max 90%)
3+ bevestigingen: status → 'bevestigd'
3x 'opgelost':   incident gearchiveerd
Officiële bron:  100% confidence
```

### External Data Sources

| Command | Source | Schedule |
|---------|--------|----------|
| `ingest-p2000` | Zwaailicht.nl RSS | Every minute |
| `ingest-knmi` | KNMI API | Hourly |
| `ingest-ndw` | NDW verkeersdata | Every 5 min |
| `ingest-luchtmeetnet` | Luchtkwaliteit | Hourly |
| `ingest-rws-water` | Rijkswaterstaat | Hourly |

## Key Services

### `IncidentEngine`
- `findDuplicate()` - Zoekt bestaand incident binnen 200m
- `updateConfidence()` - Herberekent status na vote
- `castVote()` - Verwerkt present/resolved votes

### `P2000FeedService`
- Fetcht Zwaailicht.nl Atom feed
- Detecteert category/subtype uit tekst (violence, fire indicators)
- Extraheert geo coordinates

### `AlertEngine`
- Bepaalt alert level (direct/warning/info) op basis van severity
- Filtert op afstand (5km/3km/1km)
- Respecteert user preferences

## Utilities

### Backend: `GeoUtils` (app/Support/GeoUtils.php)
```php
GeoUtils::distanceMeters($lat1, $lon1, $lat2, $lon2);
GeoUtils::boundingBoxDeltas($radiusKm);
GeoUtils::isInNetherlands($lat, $lon);
```

### Backend: `CategoryConfig` (app/Support/CategoryConfig.php)
```php
CategoryConfig::get('brand');      // Full config
CategoryConfig::color('geweld');   // '#dc2626'
CategoryConfig::icon('verkeer');   // 'car'
```

### Frontend: `useGeo` (composables/useGeo.ts)
```ts
const { distanceMeters, formatDistance, isInNetherlands } = useGeo()
```

## API Endpoints

### Public
```
GET  /api/v1/categories             # Category config
GET  /api/v1/incidents              # List (lat, lon, radius_km)
GET  /api/v1/incidents.geojson      # GeoJSON format
GET  /api/v1/incidents/{id}         # Single incident
POST /api/v1/incidents/{id}/confirm # Vote present
POST /api/v1/incidents/{id}/resolve # Vote resolved
POST /api/v1/reports                # New incident
```

### Auth
```
POST /api/v1/register
POST /api/v1/login
POST /api/v1/forgot-password
POST /api/v1/reset-password
GET  /api/v1/email/verify/{id}/{hash}
```

### Authenticated
```
GET  /api/v1/me
POST /api/v1/logout
POST /api/v1/email/resend
```

### Installation (device)
```
POST /api/v1/installations
PUT  /api/v1/installations/preferences
PUT  /api/v1/installations/push-token
PUT  /api/v1/installations/location
```

## Rate Limits

Alle endpoints hebben rate limiting:
- Auth: 10/min
- Incidents read: 60/min
- Votes: 15-30/min
- Reports: 6/min
- Email resend: 3/min

## Development

```bash
# Backend
cd backend && composer install && php artisan serve

# Frontend
cd frontend && npm install && npm run dev

# Test P2000
php artisan veiligbuiten:ingest-p2000 --dry-run
```

## Code Conventions

1. **GeoUtils** voor distance calculations (niet zelf Haversine)
2. **CategoryConfig** voor category metadata
3. Services bevatten business logic, controllers zijn dun
4. Nederlands in user-facing content, Engels in code

## Common Pitfalls

1. **Coordinate order**: GeoRSS = `lat lon`, GeoJSON = `[lon, lat]`
2. **Timezone**: UTC in database
3. **PostGIS**: Check migratie `enable_postgis`
