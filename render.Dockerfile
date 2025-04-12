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

# Copy application files
COPY . /var/www

# Create required directories
RUN mkdir -p /var/www/storage/logs \
    /var/www/storage/framework/{sessions,views,cache} \
    /var/www/storage/app/public \
    /var/www/bootstrap/cache

# Install dependencies
RUN composer install --no-interaction --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Copy entrypoint script
COPY docker/prod/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Set entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Start PHP-FPM
CMD ["php-fpm"]

# Configure PHP
COPY docker/prod/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/prod/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/prod/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Copy configuration files
COPY docker/prod/nginx.conf /etc/nginx/nginx.conf
COPY docker/prod/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy built frontend assets
COPY --from=node-builder /app/public/build public/build/ 