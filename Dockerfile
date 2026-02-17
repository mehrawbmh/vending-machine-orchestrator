FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    libpq-dev \
    libsqlite3-dev \
    unzip \
    git \
    curl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install pdo_pgsql pdo_sqlite pcntl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock* ./

RUN composer install --no-scripts --no-autoloader --prefer-dist

COPY . .

RUN composer dump-autoload --optimize

RUN php -r "file_exists('.env') || copy('.env.example', '.env');"

RUN mkdir -p database storage/logs storage/framework/sessions storage/framework/views storage/framework/cache/data \
    && touch database/database.sqlite \
    && chmod -R 775 storage database

EXPOSE 9000

CMD ["php-fpm"]
