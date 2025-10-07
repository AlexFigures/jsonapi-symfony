FROM php:8.4-cli-alpine

# Установка системных зависимостей
RUN apk add --no-cache \
    postgresql-dev \
    mysql-dev \
    sqlite-dev \
    autoconf \
    g++ \
    make \
    git \
    unzip \
    linux-headers

# Установка PHP расширений
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pdo_mysql

# Установка Xdebug для coverage
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Установка Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Настройка Xdebug
RUN echo "xdebug.mode=coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

WORKDIR /app

CMD ["php", "-v"]

