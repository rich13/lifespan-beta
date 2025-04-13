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
    client_max_body_size 100M; \
    keepalive_timeout 65; \
    sendfile on; \
    tcp_nopush on; \
    tcp_nodelay on; \
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
        fastcgi_read_timeout 300; \
        fastcgi_send_timeout 300; \
        fastcgi_connect_timeout 300; \
    } \
    location /health { \
        access_log off; \
        add_header Content-Type application/json; \
        return 200 '"'"'{"status":"healthy","timestamp":"$time_iso8601"}'"'"'; \
    } \
}' > /etc/nginx/conf.d/default.conf

# Configure PHP-FPM
RUN echo '[global]\n\
error_log = /proc/self/fd/2\n\
log_level = notice\n\
\n\
[www]\n\
access.log = /proc/self/fd/2\n\
clear_env = no\n\
catch_workers_output = yes\n\
decorate_workers_output = no\n\
\n\
pm = dynamic\n\
pm.max_children = 5\n\
pm.start_servers = 2\n\
pm.min_spare_servers = 1\n\
pm.max_spare_servers = 3\n\
\n\
php_admin_value[error_log] = /proc/self/fd/2\n\
php_admin_flag[log_errors] = on\n\
\n\
request_terminate_timeout = 300\n\
request_slowlog_timeout = 300\n\
slowlog = /proc/self/fd/2\n\
' > /usr/local/etc/php-fpm.d/www.conf

# Configure Supervisor
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
RUN mkdir -p /var/log/supervisor

# Create start script
RUN echo '#!/bin/bash\n\
supervisord -c /etc/supervisor/conf.d/supervisord.conf' > /usr/local/bin/start.sh && \
    chmod +x /usr/local/bin/start.sh

# Expose ports
EXPOSE 80 5173

# Start the application
CMD ["/usr/local/bin/start.sh"] 