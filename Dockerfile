FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        apache2 \
        libapache2-mod-php \
        mysql-server \
        php-cli \
        php-common \
        php-curl \
        php-gd \
        php-mysql \
        supervisor \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

RUN printf '%s\n' \
    'file_uploads=On' \
    'upload_max_filesize=6M' \
    'post_max_size=7M' \
    'max_file_uploads=1' \
    'memory_limit=256M' \
    'max_execution_time=30' \
    > /etc/php/8.3/apache2/conf.d/99-ai-image-detector.ini

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
    && chmod 0755 /var/www/html/docker/entrypoint.sh

EXPOSE 80

VOLUME ["/var/lib/mysql"]

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
