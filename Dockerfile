FROM php:8.3-fpm AS base

ENV TZ=Asia/Seoul

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

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]

FROM base AS web
CMD ["php-fpm"]

FROM base AS scheduler
ARG TARGETARCH
RUN curl -fsSL https://github.com/aptible/supercronic/releases/latest/download/supercronic-linux-${TARGETARCH} \
    -o /usr/local/bin/supercronic \
    && chmod +x /usr/local/bin/supercronic
CMD ["supercronic", "/app/scheduling/crontab"]