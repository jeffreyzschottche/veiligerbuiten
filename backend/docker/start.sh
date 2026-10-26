#!/bin/sh
# Bij elke (her)start: wachten op de database, database bijwerken, caches opbouwen, en dan alle processen via supervisor.
set -e

# Start de database tegelijk op (bijvoorbeeld na een herstart van de server), dan even wachten.
attempt=1
until php artisan db:show --no-interaction > /dev/null 2>&1; do
  if [ "$attempt" -ge 30 ]; then
    echo "Database na 30 pogingen nog niet bereikbaar."
    php artisan db:show --no-interaction
    exit 1
  fi
  echo "Wachten op de database (${attempt}/30)..."
  attempt=$((attempt + 1))
  sleep 2
done

# Zonder --isolated: dat slot staat in de cache-tabel, en die maakt de eerste migratie pas aan.
# Er draait één backend-container, dus twee migraties tegelijk komen niet voor.
php artisan migrate --force
# Config, routes, events en views gecachet: minder werk per verzoek.
php artisan optimize
# Artisan draaide hierboven als root; de webserver, scheduler en queue (www-data) moeten in storage kunnen schrijven.
chown -R www-data:www-data storage bootstrap/cache
exec supervisord -c /etc/supervisord.conf
