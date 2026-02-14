FROM php:8.2-cli

# System deps
RUN apt-get update && apt-get install -y \
    git unzip zip sqlite3 libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Workdir
WORKDIR /app

# Copy composer files first (cache)
COPY composer.json composer.lock ./

# Install PHP deps
RUN composer install --no-dev --optimize-autoloader

# Copy rest of the app
COPY . .

# Expose port Render expects
EXPOSE 10000

# Start PHP built-in server
CMD ["php", "-S", "0.0.0.0:10000", "-t", "public"]
