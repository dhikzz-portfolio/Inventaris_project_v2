# =============================================================
# Stage 1: Build Frontend Assets (Vite + Tailwind CSS)
# =============================================================
FROM node:22-alpine AS frontend-builder

WORKDIR /app

# Cache package manifests for fast layer reuse
COPY package.json package-lock.json ./
RUN npm ci --no-audit --prefer-offline

# Copy application files required by Vite and Tailwind CSS v4
COPY resources ./resources
COPY public ./public
COPY vite.config.js postcss.config.js ./

# Compile production assets to /app/public/build
RUN npm run build

# =============================================================
# Stage 2: Install PHP Composer Dependencies
# =============================================================
FROM composer:2 AS composer-builder

WORKDIR /app

# Cache composer definitions
COPY composer.json composer.lock ./

# Install production dependencies without running scripts
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --no-autoloader \
    --ignore-platform-reqs

# Copy application files required for PSR-4 autoload classmap
COPY app/ ./app/
COPY database/ ./database/

# Dump optimized autoloader without triggering artisan scripts
RUN composer dump-autoload --optimize --no-dev --no-scripts

# =============================================================
# Stage 3: Production Runtime (PHP 8.3-FPM + Nginx on Alpine)
# =============================================================
FROM php:8.3-fpm-alpine

WORKDIR /var/www/html

# Install runtime libraries and compile PHP extensions
RUN apk add --no-cache \
        nginx \
        curl \
        tzdata \
        libpng \
        libjpeg-turbo \
        freetype \
        libzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        linux-headers \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql gd zip pcntl bcmath opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear ~/.pearrc

# Composer binary for artisan / runtime usage
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy application source code
COPY . /var/www/html

# Copy vendor from composer-builder stage
COPY --from=composer-builder /app/vendor /var/www/html/vendor

# Copy compiled frontend assets from frontend-builder stage
COPY --from=frontend-builder /app/public/build /var/www/html/public/build

# Copy server and runtime configurations
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

# Configure permissions, strip CRLF if any, pre-discover packages, and publish Livewire assets
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh \
    && sed -i 's/user nginx;/user www-data;/' /etc/nginx/nginx.conf \
    && mkdir -p /var/lib/nginx/tmp/client_body \
                /var/lib/nginx/tmp/fastcgi \
                /var/lib/nginx/tmp/proxy \
                /var/lib/nginx/tmp/scgi \
                /var/lib/nginx/tmp/uwsgi \
                /var/log/nginx \
    && chown -R www-data:www-data /var/lib/nginx /var/log/nginx \
    && chmod -R 775 /var/lib/nginx /var/log/nginx \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && php artisan package:discover --ansi \
    && php artisan livewire:publish --assets

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
