ARG PROJECT_NAME

FROM ${PROJECT_NAME}_php-base

ARG PHP_VERSION

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        apache2 \
        php${PHP_VERSION}-fpm \
        runit \
    # Configure Apache + PHP-FPM
    && mkdir -p /run/php \
    && a2enconf php${PHP_VERSION}-fpm \
    && a2enmod proxy \
    && a2enmod proxy_fcgi \
    && a2enmod rewrite \
    && a2enmod headers \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

COPY etc/. /etc/
COPY php-configuration /etc/php/${PHP_VERSION}

RUN phpenmod app-default \
    && phpenmod app-fpm

EXPOSE 80

CMD ["runsvdir", "-P", "/etc/service"]
