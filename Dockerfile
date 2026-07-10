FROM php:8.4-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libpq-dev \
    libsqlite3-dev \
    && docker-php-ext-install pdo_pgsql pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-interaction

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative

EXPOSE 8000

CMD ["sh", "-c", "php bin/console doctrine:migrations:migrate --no-interaction && php -S 0.0.0.0:8000 -t public public/router.php"]
