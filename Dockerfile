ARG TARGETARCH
ARG SUPERCRONIC_VERSION=v0.2.29

FROM php:8.5-fpm-alpine AS base

ENV TZ=Asia/Seoul

RUN apk add --no-cache tzdata libcurl \
    && cp /usr/share/zoneinfo/${TZ} /etc/localtime \
    && echo "${TZ}" > /etc/timezone \
    && apk add --no-cache --virtual .build-deps curl-dev unzip \
    && docker-php-ext-install curl \
    && apk del .build-deps

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY app/composer.json app/composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY app/ .

COPY --chmod=+x entrypoint.sh /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]

FROM base AS web
CMD ["php-fpm"]

# Supercronic 다운로드 (amd64 버전)
FROM scratch AS supercronic-amd64
ARG SUPERCRONIC_VERSION
ADD --checksum=sha256:87625cd179eff21226f0be6f2f47dd357037064598e6c1f9ffcbd0335d402bbd \
    https://github.com/aptible/supercronic/releases/download/${SUPERCRONIC_VERSION}/supercronic-linux-amd64 \
    /supercronic

# Supercronic 다운로드 (arm64 버전)
FROM scratch AS supercronic-arm64
ARG SUPERCRONIC_VERSION
ADD --checksum=sha256:063799a43c1eac082d83ac59a43a6896b50d69aa1f533c2cc6a5376cb2bfff89 \
    https://github.com/aptible/supercronic/releases/download/${SUPERCRONIC_VERSION}/supercronic-linux-arm64 \
    /supercronic

# 타겟 아키텍처에 해당하는 Supercronic 선택
FROM supercronic-${TARGETARCH} AS supercronic

FROM base AS scheduler

# Supercronic 바이너리 복사
COPY --chmod=+x --from=supercronic /supercronic /usr/local/bin/supercronic

# www-data로 실행하여 생성 파일 소유권을 웹 컨테이너와 통일
# 볼륨 chown은 웹 컨테이너 entrypoint에서 보장
USER www-data
ENTRYPOINT []

CMD ["supercronic", "/app/scheduling/crontab"]