FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive \
    HF_HOME=/var/cache/huggingface \
    HF_HUB_DISABLE_TELEMETRY=1 \
    TOKENIZERS_PARALLELISM=false \
    ENABLE_LOCAL_CLASSIFIER=true \
    SMAUG_MODEL_ID=abacusai/Smaug-AI-Image-Detector \
    SMAUG_NUM_THREADS=4

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
        python3 \
        python3-pip \
        python3-venv \
        supervisor \
        ca-certificates \
        curl \
    && rm -rf /var/lib/apt/lists/*

# Keep the detector in an isolated Python virtual environment. Install the
# official CPU-only PyTorch wheels: CUDA is optional and not required.
RUN python3 -m venv /opt/ai-detector-venv \
    && /opt/ai-detector-venv/bin/pip install --no-cache-dir \
        --index-url https://download.pytorch.org/whl/cpu \
        --extra-index-url https://pypi.org/simple \
        torch==2.7.1+cpu torchvision==0.22.1+cpu \
    && /opt/ai-detector-venv/bin/pip install --no-cache-dir \
        -r /var/www/html/requirements-local-detector.txt

RUN printf '%s\\n' \
    'file_uploads=On' \
    'upload_max_filesize=6M' \
    'post_max_size=7M' \
    'max_file_uploads=1' \
    'memory_limit=512M' \
    'max_execution_time=300' \
    > /etc/php/8.3/apache2/conf.d/99-ai-image-detector.ini

WORKDIR /var/www/html

# The web root is configured as /var/www/html/public so project files
# outside public/ are not directly served by Apache.
COPY . /var/www/html/
COPY docker/apache-ai-image-detector.conf /etc/apache2/sites-available/000-default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/ai-image-detector.conf

RUN mkdir -p /var/log/supervisor /var/run/mysqld /var/cache/huggingface \
    && chown -R mysql:mysql /var/run/mysqld \
    && chown -R www-data:www-data /var/www/html /var/cache/huggingface \
    && chmod -R a=rX /var/www/html \
    && chmod 0755 /var/www/html/docker/entrypoint.sh \
    && chmod 0755 /var/www/html/scripts/smaug_detect.py

EXPOSE 80

# The named volumes preserve database rows and downloaded model artifacts
# across container replacement; uploaded image files are never placed here.
VOLUME ["/var/lib/mysql", "/var/cache/huggingface"]

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
