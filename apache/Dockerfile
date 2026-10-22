FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        apache2 \
        libapache2-mod-php \
        php \
        php-curl \
        php-mysql \
        php-fileinfo \
        php-mbstring \
        ca-certificates && \
    a2enmod rewrite && \
    rm -rf /var/lib/apt/lists/*

COPY app/public/ /var/www/html/

RUN mkdir -p /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html && \
    chmod 750 /var/www/html/uploads

EXPOSE 80

CMD ["apachectl", "-D", "FOREGROUND"]

