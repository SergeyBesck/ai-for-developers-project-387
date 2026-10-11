# syntax=docker/dockerfile:1

# pdo_sqlite и mbstring (требование riverline/multipart-parser из цепочки
# league/openapi-psr7-validator) входят в официальный образ php:8.3-cli.
FROM php:8.3-cli

# unzip — чтобы Composer распаковывал дистрибутивы пакетов на этапе сборки.
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*

# Composer нужен только на этапе сборки.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Сначала зависимости — слой кэшируется, пока не меняются composer.json/composer.lock.
# generated/, openapi.json и public/assets/sdk.gen.js уже в репозитории, генерация не нужна.
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-progress

COPY openapi.json ./
COPY src ./src
COPY generated ./generated
COPY public ./public

# SQLite по умолчанию лежит в /app/var — каталог создан заранее и открыт
# на запись нерутовому пользователю, схема создаётся на старте приложения.
RUN mkdir -p /app/var \
    && chown www-data:www-data /app/var

ENV CALENDAR_DB_PATH=/app/var/calendar.sqlite

EXPOSE 8000

USER www-data

# PORT подставляется через оболочку: docker run -e PORT=9000 -p 9000:9000 <образ>.
# exec передаёт сигналы остановки в PHP-сервер, а не в оболочку.
CMD ["sh", "-c", "exec php -S 0.0.0.0:${PORT:-8000} -t public public/router.php"]
