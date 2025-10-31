FROM php:8.2-cli

# Install composer
COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . /app

RUN composer install --prefer-dist --no-progress --no-suggest