FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        apache2 \
        libapache2-mod-php \
        mysql-server \
        php-cli \
        php-common \
        php-gd \
        php-mysql \
        supervisor \
        ca-certificates \
    && a2enmod php* \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# The web root is configured as /var/www/html/public so project files
# outside public/ are not directly served by Apache.
COPY . /var/www/html/
COPY docker/apache-ai-image-detector.conf /etc/apache2/sites-available/000-default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/ai-image-detector.conf

RUN mkdir -p /var/log/supervisor /var/run/mysqld \
    && chown -R mysql:mysql /var/run/mysqld \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R a=rX /var/www/html \
    && a2dissite 000-default.conf >/dev/null 2>&1 || true

EXPOSE 80

VOLUME ["/var/lib/mysql"]

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
