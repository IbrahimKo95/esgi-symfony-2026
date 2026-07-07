FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    postgresql-dev \
    git \
    unzip \
    curl \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    supervisor \
    nodejs \
    npm

RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    opcache \
    gd \
    zip \
    mbstring \
    intl

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && apk del .build-deps

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

ENV APP_ENV=prod \
    APP_SECRET=buildsecret \
    DATABASE_URL="postgresql://dummy:dummy@localhost/dummy?serverVersion=16" \
    MESSENGER_TRANSPORT_DSN=doctrine://default \
    MAILER_DSN=null://null \
    COMPOSER_ALLOW_SUPERUSER=1

# --no-scripts évite symfony-cmd (CLI Symfony non dispo ici)
# COMPOSER_ALLOW_SUPERUSER=1 permet aux plugins de tourner en root (génère autoload_runtime.php)
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts \
    && composer dump-autoload --optimize --no-dev

RUN php bin/console assets:install --env=prod
RUN php bin/console importmap:install --env=prod

COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.prod.sh /entrypoint.prod.sh
RUN chmod +x /entrypoint.prod.sh

EXPOSE 8080

ENTRYPOINT ["/entrypoint.prod.sh"]
