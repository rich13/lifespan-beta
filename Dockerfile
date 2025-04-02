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
    nginx

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd zip

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy the entire application
COPY . .

# Install dependencies (including dev dependencies for build)
RUN composer install --no-interaction --optimize-autoloader && \
    npm install

# Set permissions
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Generate application key if not exists
RUN if [ ! -f .env ]; then cp .env.example .env; fi && \
    php artisan key:generate

# Optimize Laravel (without view cache)
RUN php artisan config:cache

# Configure Nginx
RUN echo 'server { \
    listen 80; \
    index index.php index.html; \
    server_name localhost; \
    error_log  /var/log/nginx/error.log; \
    access_log /var/log/nginx/access.log; \
    root /var/www/public; \
    location / { \
        try_files $uri $uri/ /index.php?$query_string; \
    } \
    location ~ \.php$ { \
        try_files $uri =404; \
        fastcgi_split_path_info ^(.+\.php)(/.+)$; \
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock; \
        fastcgi_index index.php; \
        include fastcgi_params; \
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; \
        fastcgi_param PATH_INFO $fastcgi_path_info; \
    } \
}' > /etc/nginx/conf.d/default.conf

# Create start script
RUN echo '#!/bin/bash\nphp-fpm -D\nnginx -g "daemon off;"' > /usr/local/bin/start.sh && \
    chmod +x /usr/local/bin/start.sh

# Expose port 80
EXPOSE 80

# Start the application
CMD ["/usr/local/bin/start.sh"] 