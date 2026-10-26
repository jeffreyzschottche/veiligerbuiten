# PHP-FPM + nginx, zoals Spot And Find. TLS doet Coolify (Traefik) ervoor.
FROM php:8.3-fpm-alpine

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql redis opcache pcntl zip intl bcmath \
  && apk add --no-cache nginx supervisor \
  && mkdir -p /run/nginx \
  # Coolify geeft de instellingen als omgevingsvariabelen; PHP-FPM moet ze doorgeven aan Laravel.
  && printf '[www]\nclear_env = no\n' > /usr/local/etc/php-fpm.d/zz-env.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-veiligerbuiten.ini
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf

WORKDIR /var/www/html
# Eerst alleen de dependencies: die laag blijft gecachet zolang composer.lock niet verandert.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --optimize --no-dev \
  && chown -R www-data:www-data storage bootstrap/cache

# Web, scheduler en queue in één container (zie docker/supervisord.conf); per container uit te zetten.
# Logs naar stderr, zodat Coolify ze toont en er in de container niets volloopt.
ENV RUN_SCHEDULER=true RUN_QUEUE=true LOG_CHANNEL=stderr

EXPOSE 8000
# Coolify zet pas verkeer over als de nieuwe versie gezond is (database en cache bereikbaar).
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
  CMD wget -qO /dev/null http://127.0.0.1:8000/api/v1/health || exit 1
CMD ["sh", "/var/www/html/docker/start.sh"]
