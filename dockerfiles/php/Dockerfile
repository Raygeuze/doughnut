FROM php:8.4-fpm

ARG uid
ARG username

RUN apt-get update \
    && apt-get install -y curl git zip unzip \
    && docker-php-ext-install exif pdo pdo_mysql \
    && curl -fsSL https://deb.nodesource.com/setup_current.x | bash - \
    && apt-get install -y nodejs \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN useradd -G www-data,root -u ${uid} -d /home/${username} ${username} \
    && mkdir -p /home/${username}/.composer \
    && chown -R ${username}:${username} /home/${username}
