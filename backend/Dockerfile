FROM php:8.3-cli-alpine
RUN apk add --no-cache postgresql-dev libzip-dev oniguruma-dev $PHPIZE_DEPS \
  && docker-php-ext-install pdo_pgsql mbstring zip opcache \
  && pecl install redis && docker-php-ext-enable redis
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
COPY . .
RUN chown -R www-data:www-data storage bootstrap/cache
EXPOSE 8000
CMD ["sh", "-c", "php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000"]
