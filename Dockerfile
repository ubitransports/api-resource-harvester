FROM composer:2

WORKDIR /app

COPY . /app

RUN composer install --prefer-dist --no-progress --no-suggest
