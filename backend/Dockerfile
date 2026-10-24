# FrankenPHP: PHP met een ingebouwde, snelle webserver (Caddy). Vervangt `php artisan serve`:
# veel verzoeken tegelijk, compressie en HTTP/2. TLS doet Coolify (Traefik) ervoor.
FROM dunglas/frankenphp:1-php8.3-alpine

RUN install-php-extensions pdo_pgsql pdo_mysql zip opcache redis pcntl \
  && apk add --no-cache supervisor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-veiligerbuiten.ini
COPY docker/supervisord.conf /etc/supervisord.conf

WORKDIR /app
# Eerst alleen de dependencies: die laag blijft gecachet zolang composer.lock niet verandert.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --optimize --no-dev \
  # Niet als root: breekt iemand via PHP in, dan mag hij alleen in storage en de cache schrijven.
  && chown -R www-data:www-data storage bootstrap/cache /data/caddy /config/caddy

# Web, scheduler en queue in één container (zie docker/supervisord.conf); per container uit te zetten.
# Logs naar stderr, zodat Coolify ze toont en er in de container niets volloopt.
ENV RUN_SCHEDULER=true RUN_QUEUE=true LOG_CHANNEL=stderr

USER www-data
EXPOSE 8000
# Coolify zet pas verkeer over als de nieuwe versie gezond is (database en cache bereikbaar).
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
  CMD wget -qO /dev/null http://127.0.0.1:8000/api/v1/health || exit 1
CMD ["sh", "/app/docker/start.sh"]
