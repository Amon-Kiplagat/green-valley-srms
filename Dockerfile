# Render-compatible Dockerfile for Green Valley SRMS
# Uses a single PHP container with built-in web server
# Render sets the $PORT environment variable at runtime

FROM php:8.3-cli

# Set working directory
WORKDIR /var/www/html

# Install system dependencies and PostgreSQL PHP extension
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    curl \
    zip \
    unzip \
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
RUN chown -R www-data:www-data /var/www/html

# Default port (Render overrides $PORT at runtime)
ENV PORT=8080
EXPOSE 8080

# Start PHP built-in web server on the port Render provides
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT}"]
