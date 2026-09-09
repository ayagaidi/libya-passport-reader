FROM swaggerapi/swagger-ui:v5.32.15 AS swagger-ui

FROM php:8.3-cli-bookworm

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        tesseract-ocr \
        tesseract-ocr-ara \
        poppler-utils \
        imagemagick \
        python3 \
        python3-venv \
        python3-pip \
        libgl1 \
        libglib2.0-0 \
    && docker-php-ext-install intl zip opcache \
    && rm -rf /var/lib/apt/lists/*

# Allow high-resolution passport images/PDFs while keeping an application-level cap.
RUN printf '%s\n' \
        'upload_max_filesize=32M' \
        'post_max_size=40M' \
        'memory_limit=256M' \
        'max_execution_time=120' \
        'max_input_time=120' \
        > /usr/local/etc/php/conf.d/99-passport-uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --no-scripts

# Bundle Swagger UI static assets from the official Swagger Docker image.
COPY --from=swagger-ui /usr/share/nginx/html /app/public/docs

COPY . .

RUN mkdir -p \
        storage/app/private/passport-tmp \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --no-interaction \
    && python3 -m venv /opt/passport-vision \
    && /opt/passport-vision/bin/pip install --no-cache-dir --upgrade pip \
    && /opt/passport-vision/bin/pip install --no-cache-dir -r requirements-vision.txt

EXPOSE 8080

CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"]
