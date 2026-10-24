# Landing page and community total

The landing page is `pages/index.vue`; Dutch and English content lives in `locales/site.ts`.
GSAP is loaded on the client for the intro, scroll reveals, background parallax and counter.
All motion respects `prefers-reduced-motion` and cleans up on navigation.
GSAP reference: https://gsap.com/docs/v3/GSAP/gsap.matchMedia()/

## Generated artwork

- Final asset: `public/images/amsterdam-dusk.webp` (2056 × 765, WebP).
- Tool: built-in imagegen, original PNG preserved in the generator's output directory.
- Final prompt:

> Use case: photorealistic-natural. Asset type: full-bleed background photograph for VeiligerBuiten, a Dutch neighborhood safety app landing page. Primary request: a beautiful calm Amsterdam canal neighborhood seen from a slightly elevated pedestrian bridge at blue hour, softly lit windows, elegant historic Dutch facades, a few natural cyclists and pedestrians in the far distance, reflections on water. Composition/framing: ultra-wide cinematic landscape, canal recedes through the center-right, left half atmospheric and visually quiet to support overlaid bold dark typography using a pale gradient. Lighting/mood: reassuring, quiet everyday life, pale misty blue sky, soft warm amber streetlights against cool slate blue tones; premium editorial architectural photography, real textures, subtle film grain. Constraints: no emergency, no fire, no police, no disaster, no dramatic danger, no text, no logos, no typography, no phones, no UI. The app helps people feel informed and at ease outdoors. Wide landscape image.

## Community count

1. Laravel stores a daily aggregate in the existing database `cache` table: **976 + `User::count()`**. This is the requested fixed starting total plus accounts, not an independently verified count of unique people.
2. `veiligbuiten:publish-site-statistics` refreshes it at 03:30 Europe/Amsterdam through the existing Laravel scheduler. The first authorized read also refreshes a missing or previous-day snapshot, so a missed scheduled run recovers automatically.
3. `GET /api/v1/internal/site-statistics` requires `Authorization: Bearer <VB_SITE_STATS_TOKEN>`; it returns only `total` and `updated_at`.
4. The Nuxt server reads it privately and serves `GET /api/site/community` on the website's own origin. Nitro caches a successful result for one hour with stale-while-revalidate. Failures never become a fabricated count; the page shows a dash when no real snapshot is available.
5. The web app uses `/api/v1/*` through a same-origin server gateway. The gateway blocks internal routes. The website's public runtime config contains no backend hostname or statistics token.

### Deployment

On the Nuxt server set:

```dotenv
NUXT_API_ORIGIN=http://your-private-backend:8000
NUXT_SITE_STATS_TOKEN=<strong-random-shared-secret>
```

On Laravel set the **same** secret:

```dotenv
VB_SITE_STATS_TOKEN=<strong-random-shared-secret>
```

Use deployment secrets, never `NUXT_PUBLIC_*` for these values. Remove any previously configured `NUXT_PUBLIC_API_BASE` on the website: the public web API base is `/api/v1`. Older `NUXT_PUBLIC_API_BASE_URL` is consumed only during the explicit mobile build and does not override the web runtime key.

Keep the Laravel scheduler running (`php artisan schedule:run` every minute, as for the existing ingestion tasks). No migration is needed: Laravel's database cache tables already exist. Optional initial publication:

```sh
php artisan veiligbuiten:publish-site-statistics
```

Build the web server with `npm run build`; it needs a running Nuxt/Nitro server. A static-only host cannot provide these private endpoints. Build native assets with `npm run build:mobile`, setting `NUXT_PUBLIC_API_BASE_URL` to the native app's HTTPS API address. Native clients necessarily know the API they call; this change keeps that address out of the website's public configuration.

The local frontend and backend `.env` files were paired without printing the generated secret. Production needs its own pair of deployment secrets.

## Validation

- Production Nuxt build passed on Node 22.23.2.
- Complete Laravel suite: 38 tests, 151 assertions passed, including four new statistics tests.
- Chrome checks passed at 320, 375, 390, 768, 1024 and 1440 pixels; no horizontal overflow or JavaScript errors. Dutch/English switch and reduced-motion rendering checked.
- Full chain verified with a separate temporary SQLite database: zero accounts returned 976 through Laravel and Nitro, and rendered 976 in the browser. Unit/feature coverage additionally verifies nonzero counts, daily rollover, the shared-secret check and forced publication.
- Internal gateway requests, including encoded route variants, returned 404. HTML and all built public JavaScript were checked for the private origin and actual local secret. No external requests were made by the landing page.
- Simulated statistics failure rendered a dash, with no fabricated fallback total.

The existing local Laravel `.env` points at MySQL on port 3306, which was unavailable during this work. Its database settings were preserved. The temporary test database is separate from application data; production must connect to the real database before the landing page can display the real account aggregate.
