# Build stage for Node.js assets
FROM node:20-alpine AS node-builder

WORKDIR /app
COPY package*.json ./
RUN npm ci
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
    supervisor \
    nginx \
    postgresql-client \
    libpq-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd zip opcache

# Configure PHP
COPY docker/prod/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/prod/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/prod/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Create necessary directories
RUN mkdir -p /var/log/nginx /var/log/supervisor /var/lib/nginx/body /run/nginx /run/php && \
    touch /var/log/nginx/error.log /var/log/nginx/access.log /var/log/supervisor/supervisord.log && \
    chmod -R 777 /var/log /var/lib/nginx /run/nginx /run/php

# Copy application files
COPY . .

# Set up environment
echo "Setting up environment..."

# Create a new .env file from example
cp .env.example .env

# Update database connection settings
sed -i "s#DB_HOST=.*#DB_HOST=${DB_HOST}#" .env
sed -i "s#DB_PORT=.*#DB_PORT=${DB_PORT}#" .env
sed -i "s#DB_DATABASE=.*#DB_DATABASE=${DB_DATABASE}#" .env
sed -i "s#DB_USERNAME=.*#DB_USERNAME=${DB_USERNAME}#" .env
sed -i "s#DB_PASSWORD=.*#DB_PASSWORD=${DB_PASSWORD}#" .env

# Update app settings
sed -i "s#APP_ENV=.*#APP_ENV=${APP_ENV:-production}#" .env
sed -i "s#APP_DEBUG=.*#APP_DEBUG=${APP_DEBUG:-false}#" .env
sed -i "s#APP_URL=.*#APP_URL=${APP_URL}#" .env

# Generate application key if not set
if [ -z "$APP_KEY" ]; then
    echo "Generating application key..."
    php artisan key:generate --force
else
    echo "Using provided application key..."
    sed -i "s#APP_KEY=.*#APP_KEY=${APP_KEY}#" .env
fi

# Set up storage and cache directories
RUN mkdir -p /var/www/storage/logs /var/www/storage/framework/{sessions,views,cache} /var/www/storage/app/public && \
    chmod -R 777 /var/www/storage /var/www/bootstrap/cache && \
    chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Install composer dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy built frontend assets
COPY --from=node-builder /app/public/build public/build/

# Copy configuration files
COPY docker/prod/nginx.conf /etc/nginx/nginx.conf
COPY docker/prod/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create entrypoint script
RUN echo '#!/bin/sh\n\
echo "Waiting for database to be ready..."\n\
max_attempts=30\n\
attempt=1\n\
\n\
while [ $attempt -le $max_attempts ]; do\n\
    if pg_isready -h ${DB_HOST} -p ${DB_PORT} -U ${DB_USERNAME} -d ${DB_DATABASE} > /dev/null 2>&1; then\n\
        echo "Database is ready!"\n\
        break\n\
    fi\n\
    echo "Attempt $attempt/$max_attempts: Database is unavailable - sleeping"\n\
    sleep 2\n\
    attempt=$((attempt + 1))\n\
done\n\
\n\
if [ $attempt -gt $max_attempts ]; then\n\
    echo "Database connection failed after $max_attempts attempts. Continuing anyway..."\n\
fi\n\
\n\
echo "Setting up environment..."\n\
\n\
# Update database connection settings\n\
sed -i "s#DB_HOST=.*#DB_HOST=${DB_HOST}#" .env\n\
sed -i "s#DB_PORT=.*#DB_PORT=${DB_PORT}#" .env\n\
sed -i "s#DB_DATABASE=.*#DB_DATABASE=${DB_DATABASE}#" .env\n\
sed -i "s#DB_USERNAME=.*#DB_USERNAME=${DB_USERNAME}#" .env\n\
sed -i "s#DB_PASSWORD=.*#DB_PASSWORD=${DB_PASSWORD}#" .env\n\
\n\
# Update app settings\n\
sed -i "s#APP_ENV=.*#APP_ENV=${APP_ENV:-production}#" .env\n\
sed -i "s#APP_DEBUG=.*#APP_DEBUG=${APP_DEBUG:-false}#" .env\n\
sed -i "s#APP_URL=.*#APP_URL=${APP_URL}#" .env\n\
\n\
echo "Setting storage permissions..."\n\
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache\n\
chmod -R 775 /var/www/storage /var/www/bootstrap/cache\n\
\n\
# Create symbolic link for public storage\n\
php artisan storage:link || echo "Storage link already exists"\n\
\n\
echo "Running migrations..."\n\
php artisan migrate --force || echo "Migration failed, but continuing startup"\n\
\n\
echo "Optimizing for production..."\n\
php artisan optimize\n\
php artisan view:cache\n\
php artisan route:cache\n\
php artisan config:cache\n\
\n\
echo "Starting supervisor..."\n\
/usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf\n\
' > /usr/local/bin/docker-entrypoint.sh && \
    chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"] 