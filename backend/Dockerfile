FROM php:8.3-cli-alpine
RUN apk add --no-cache supervisor postgresql-dev libzip-dev oniguruma-dev $PHPIZE_DEPS \
  && docker-php-ext-install pdo_pgsql mbstring zip opcache \
  && pecl install redis && docker-php-ext-enable redis
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
COPY . .
RUN chown -R www-data:www-data storage bootstrap/cache
COPY docker/supervisord.conf /etc/supervisord.conf
# Web, scheduler en queue in één container (zie docker/supervisord.conf); per container uit te zetten.
ENV WEB_WORKERS=4 RUN_SCHEDULER=true RUN_QUEUE=true
EXPOSE 8000
CMD ["sh", "/app/docker/start.sh"]
