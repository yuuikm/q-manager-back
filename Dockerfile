FROM php:8.4.12-cli

# Install only essential packages
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    libonig-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    ghostscript \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions (much faster than compiling)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy the entire application first
COPY . .

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Create required storage directories
RUN mkdir -p storage/app/public/documents storage/app/public/previews && \
    chown -R www-data:www-data storage

# Set proper permissions
RUN chown -R www-data:www-data /var/www

# Change current user to www
USER www-data

# Expose port 8000
EXPOSE 8000

# Start Laravel development server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
