# 1. Use PHP with Apache
FROM php:8.2-apache

# 2. Install system dependencies for Composer and Google Libraries
RUN apt-get update && apt-get install -y \
    unzip \
    libzip-dev \
    git \
    && docker-php-ext-install mysqli zip

# 3. Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Set the working directory
WORKDIR /var/www/html

# 5. Copy your project files into the container
COPY . /var/www/html/

# 6. Run composer install to create the 'vendor' folder
# We use --no-dev to keep it small and --optimize-autoloader for speed
RUN composer install --no-dev --optimize-autoloader --no-interaction

# 7. Enable Apache rewrite (helpful for clean URLs)
RUN a2enmod rewrite

# 8. Set correct permissions so Apache can read your files
RUN chown -R www-data:www-data /var/www/html