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
    libzip-dev

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd zip

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy the entire application
COPY . .

# Install dependencies (including dev dependencies for build)
RUN composer install --no-interaction --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Generate application key if not exists
RUN if [ ! -f .env ]; then cp .env.example .env; fi && \
    php artisan key:generate

# Optimize Laravel (without view cache)
RUN php artisan config:cache && \
    php artisan route:cache

# Remove dev dependencies and optimize for production
RUN composer install --no-dev --optimize-autoloader

# Copy and set up the startup script
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Expose port 9000
EXPOSE 9000

# Start the application using our startup script
CMD ["/usr/local/bin/start.sh"] 