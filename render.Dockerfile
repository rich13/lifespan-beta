# Build stage for Node.js assets
FROM node:20-alpine AS node-builder

WORKDIR /app
COPY package*.json ./
RUN npm install && npm ci
COPY resources/ resources/
COPY vite.config.js ./
COPY tailwind.config.js ./
COPY postcss.config.js ./
RUN npm run build

# PHP stage
FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    libzip-dev \
    postgresql-client \
    nginx \
    procps \
    lsof \
    supervisor

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Node.js with specific version for stability
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs && \
    npm install -g npm@latest

# Install PHP extensions
RUN docker-php-ext-configure zip && \
    docker-php-ext-install pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application files first
COPY . /var/www

# Create required directories
RUN mkdir -p /var/www/storage/logs \
    /var/www/storage/framework/{sessions,views,cache,testing,cache/data} \
    /var/www/storage/app/public \
    /var/www/bootstrap/cache

# Install dependencies
RUN composer install --no-interaction --no-dev --optimize-autoloader

# Set permissions and make directories executable
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache /var/www/resources && \
    chmod -R 775 /var/www/storage /var/www/bootstrap/cache && \
    chmod -R 775 /var/www/resources/views

# Copy and verify configuration files
COPY docker/prod/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh && \
    test -f /usr/local/bin/entrypoint.sh || exit 1

# Configure PHP
COPY docker/prod/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/prod/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/prod/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Copy and verify other configuration files
COPY docker/prod/nginx.conf /etc/nginx/nginx.conf
COPY docker/prod/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
RUN chmod 644 /etc/nginx/nginx.conf /etc/supervisor/conf.d/supervisord.conf && \
    test -f /etc/nginx/nginx.conf || exit 1 && \
    test -f /etc/supervisor/conf.d/supervisord.conf || exit 1

# Copy built frontend assets and verify
COPY --from=node-builder /app/public/build public/build/
RUN test -d public/build || exit 1

# Set entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Start PHP-FPM
CMD ["php-fpm"] 