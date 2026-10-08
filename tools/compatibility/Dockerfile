ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-cli-alpine
RUN apk add --no-cache git unzip python3 postgresql-dev icu-dev \
    && docker-php-ext-install -j2 pdo_pgsql pdo_mysql intl
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
