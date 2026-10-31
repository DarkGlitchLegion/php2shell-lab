ARG PHP_BASE_IMAGE=php:8.5.11-apache-bookworm
ARG LAB_RUNTIME_EXPECTED_VERSION=8.5.11
ARG TARGETARCH

FROM debian:bookworm-slim AS ttyd-fetch
ARG TARGETARCH
RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl \
    && case "${TARGETARCH}" in \
        amd64) TTYD_ASSET=ttyd.x86_64 ;; \
        arm64) TTYD_ASSET=ttyd.aarch64 ;; \
        arm) TTYD_ASSET=ttyd.armhf ;; \
        *) echo "Unsupported Docker architecture: ${TARGETARCH}" >&2; exit 1 ;; \
    esac \
    && curl --retry 5 --retry-all-errors --connect-timeout 15 --max-time 180 -fsSL "https://github.com/tsl0922/ttyd/releases/download/1.7.7/${TTYD_ASSET}" -o /usr/local/bin/ttyd \
    && case "${TARGETARCH}" in \
        amd64) echo '8a217c968aba172e0dbf3f34447218dc015bc4d5e59bf51db2f2cd12b7be4f55  /usr/local/bin/ttyd' | sha256sum -c - ;; \
        *) curl --retry 5 --retry-all-errors --connect-timeout 15 --max-time 30 -fsSL https://github.com/tsl0922/ttyd/releases/download/1.7.7/SHA256SUMS -o /tmp/ttyd.SHA256SUMS \
            && grep "  ${TTYD_ASSET}$" /tmp/ttyd.SHA256SUMS | sed "s#  ${TTYD_ASSET}#  /usr/local/bin/ttyd#" | sha256sum -c - ;; \
    esac \
    && chmod 0755 /usr/local/bin/ttyd \
    && rm -f /tmp/ttyd.SHA256SUMS \
    && rm -rf /var/lib/apt/lists/*

FROM ${PHP_BASE_IMAGE}
ARG LAB_RUNTIME_EXPECTED_VERSION

ENV LAB_RUNTIME_EXPECTED_VERSION=${LAB_RUNTIME_EXPECTED_VERSION} \
    APACHE_RUN_DIR=/var/run/apache2 \
    APACHE_LOCK_DIR=/var/lock/apache2 \
    APACHE_LOG_DIR=/var/log/apache2 \
    APACHE_PID_FILE=/var/run/apache2/apache2.pid \
    APACHE_RUN_USER=www-data \
    APACHE_RUN_GROUP=www-data \
    HISTFILE=/tmp/php2shell-shell-history \
    HISTSIZE=500 \
    HISTFILESIZE=1000

RUN sed -i 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf \
    && printf '%s\n' \
        'expose_php=Off' \
        'display_errors=Off' \
        'log_errors=On' \
        'error_log=/var/log/apache2/php-error.log' \
        'max_execution_time=10' \
        'max_input_time=10' \
        'post_max_size=8M' \
        'upload_max_filesize=4M' \
        'session.upload_progress.enabled=0' \
        > /usr/local/etc/php/conf.d/php2shell-lab.ini \
    && mkdir -p /var/www/html /var/run/apache2 /var/lock/apache2 /var/log/apache2 \
    && chown -R www-data:www-data /var/www/html /var/run/apache2 /var/lock/apache2 /var/log/apache2

COPY docker/runtime/entrypoint.sh /usr/local/bin/php2shell-entrypoint
COPY docker/runtime/runtime-info.php /var/www/html/runtime-info.php
COPY --from=ttyd-fetch /usr/local/bin/ttyd /usr/local/bin/ttyd
RUN chmod 0555 /usr/local/bin/php2shell-entrypoint \
    && chmod 0444 /var/www/html/runtime-info.php

USER www-data
WORKDIR /var/www/html
EXPOSE 8080 7681
ENTRYPOINT ["/usr/local/bin/php2shell-entrypoint"]
