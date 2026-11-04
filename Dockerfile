# Render-compatible Dockerfile for Green Valley SRMS
# Uses Nginx + PHP-FPM for proper file serving (php -S has routing issues with subdirectories)
# Render sets the $PORT environment variable at runtime

FROM php:8.3-fpm

# Set working directory
WORKDIR /var/www/html

# Install system dependencies: Nginx, PostgreSQL PHP extension, and other libs
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    curl \
    zip \
    unzip \
    nginx \
    supervisor \
    && docker-php-ext-install pdo_pgsql mbstring xml zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files (excluding local .env and dev files)
COPY . .

# Remove local .env if present (Render uses environment variables)
RUN rm -f .env

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod +x /var/www/html/start-php-fpm.sh

# Configure Nginx to serve PHP files
RUN rm -f /etc/nginx/sites-enabled/default && \
    echo 'server { \
        listen 8080; \
        server_name _; \
        root /var/www/html; \
        index index.php index.html; \
        \
        location / { \
            try_files $uri $uri/ /index.php?$query_string; \
        } \
        \
        location ~ \.php$ { \
            fastcgi_pass 127.0.0.1:9000; \
            fastcgi_index index.php; \
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; \
            include fastcgi_params; \
        } \
        \
        location ~ /\. { \
            deny all; \
        } \
    }' > /etc/nginx/sites-enabled/default

# Configure supervisord to run both Nginx and PHP-FPM
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Default port (Render overrides $PORT at runtime)
ENV PORT=8080
EXPOSE 8080

# Start supervisord to manage both Nginx and PHP-FPM
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
