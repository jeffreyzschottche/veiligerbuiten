# Website + web-app (Nuxt SSR) voor Coolify. De mobiele apps bouwen apart (npm run build:mobile).
FROM node:22-alpine AS deps
WORKDIR /app
COPY package.json package-lock.json ./
# Zonder scripts: `nuxt prepare` (postinstall) heeft de broncode nodig en draait in de build-stap.
RUN npm ci --include=dev --ignore-scripts

FROM node:22-alpine AS build
WORKDIR /app
COPY --from=deps /app/node_modules ./node_modules
COPY . .
ENV NODE_ENV=production NUXT_TELEMETRY_DISABLED=1
RUN npm run postinstall && npm run build

FROM node:22-alpine AS runner
WORKDIR /app
# NUXT_API_ORIGIN, NUXT_SITE_STATS_TOKEN en NUXT_PUBLIC_* zet je in Coolify; Nuxt leest ze bij het starten.
ENV NODE_ENV=production NUXT_TELEMETRY_DISABLED=1 HOST=0.0.0.0 PORT=3000 NITRO_HOST=0.0.0.0 NITRO_PORT=3000
COPY --from=build --chown=node:node /app/.output ./.output
USER node
EXPOSE 3000
# Coolify zet pas verkeer over als de nieuwe versie de homepage serveert.
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
  CMD wget -qO /dev/null http://127.0.0.1:3000/ || exit 1
CMD ["node", ".output/server/index.mjs"]
