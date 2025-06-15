RUN apt-get update && apt-get install -y \
    # ... existing packages ...
    logrotate \
    && rm -rf /var/lib/apt/lists/*

# Copy logrotate configuration
COPY docker/logrotate.conf /etc/logrotate.d/laravel 