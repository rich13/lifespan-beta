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

# Copy Bootstrap Icons fonts to public directory
RUN mkdir -p public/fonts
RUN if [ -d "node_modules/bootstrap-icons/font/fonts/" ]; then \
        cp -r node_modules/bootstrap-icons/font/fonts/* public/fonts/; \
    fi

# Also create a special build directory for static files
RUN mkdir -p public/build/fonts
RUN if [ -d "node_modules/bootstrap-icons/font/fonts/" ]; then \
        cp -r node_modules/bootstrap-icons/font/fonts/* public/build/fonts/; \
    fi

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
    nginx \
    supervisor \
    libzip-dev \
    postgresql-client

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application files
COPY . .

# Copy built frontend assets
COPY --from=node-builder /app/public/build public/build/
# Copy font files
COPY --from=node-builder /app/public/fonts public/fonts/

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Create required directories with proper permissions
RUN mkdir -p /var/www/storage/logs \
    /var/www/storage/framework/{sessions,views,cache} \
    /var/www/storage/app/public \
    /var/www/storage/app/imports \
    /var/www/bootstrap/cache \
    /var/log/supervisor \
    /var/log/nginx \
    /var/run/nginx \
    /var/run/php \
    /etc/supervisor/conf.d

# Create log files with correct permissions
RUN touch /var/www/storage/logs/laravel.log && \
    chmod 664 /var/www/storage/logs/laravel.log && \
    chown www-data:www-data /var/www/storage/logs/laravel.log

# Copy YAML files to imports directory - try multiple sources
# First try from storage/app/imports (local dev environment)
RUN if [ -d "storage/app/imports" ] && [ "$(ls -A storage/app/imports)" ]; then \
    cp -r storage/app/imports/*.yaml /var/www/storage/app/imports/ || true; \
fi

# Then try from yaml-samples directory (Git repository samples)
RUN if [ -d "yaml-samples" ] && [ "$(ls -A yaml-samples)" ]; then \
    cp -r yaml-samples/*.yaml /var/www/storage/app/imports/ || true; \
fi

# Copy configuration files
COPY docker/prod/nginx.conf /etc/nginx/nginx.conf
COPY docker/prod/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/prod/php-fpm/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/prod/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/prod/health-check.sh /usr/local/bin/health-check.sh
COPY docker/prod/set-db-config.php /usr/local/bin/set-db-config.php

# Make scripts executable
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/health-check.sh

# Set appropriate permissions for supervisor directories
RUN mkdir -p /var/log/supervisor && \
    chmod -R 755 /var/log/supervisor && \
    chmod -R 755 /etc/supervisor/conf.d && \
    touch /var/log/supervisor/supervisord.log && \
    chmod 664 /var/log/supervisor/supervisord.log

# Set permissions for the application
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    /var/log/nginx /var/run/nginx /var/run/php

# Expose port
EXPOSE 8080

# Set entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"] 