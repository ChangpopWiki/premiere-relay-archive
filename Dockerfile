FROM php:8.3-cli AS base

RUN apt-get update && apt-get install -y \
        libcurl4-openssl-dev \
        unzip \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY app/composer.json app/composer.lock ./
RUN composer install --no-dev --optimize-autoloader

COPY app/ .

FROM base AS web
CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app"]

FROM base AS scheduler
ARG TARGETARCH
RUN curl -fsSL https://github.com/aptible/supercronic/releases/latest/download/supercronic-linux-${TARGETARCH} \
    -o /usr/local/bin/supercronic \
    && chmod +x /usr/local/bin/supercronic
CMD ["supercronic", "/app/scheduling/crontab"]