# ---- Build Stage ----
FROM composer:2 AS builder

WORKDIR /build

# Copy composer files first for better caching
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy application source
COPY src/ src/
COPY public/ public/
COPY aes/ aes/
COPY tests/ tests/
COPY docker/ docker/
COPY resources/ resources/
COPY tools/ tools/

# Copy remaining config files
COPY phpunit.xml.dist phpstan.neon .php-cs-fixer.dist.php configuration.php ./

# Runtime stage
FROM php:8.4-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        zip \
        bcmath \
        opcache \
        gd \
        curl \
        xml

# Enable Apache modules
RUN a2enmod rewrite
RUN a2enmod headers

# Copy from builder
COPY --from=builder /build/vendor /var/www/html/vendor
COPY --from=builder /build/src /var/www/html/src
COPY --from=builder /build/public /var/www/html/public
COPY --from=builder /build/aes /var/www/html/aes
COPY --from=builder /build/docker /var/www/html/docker
COPY --from=builder /build/resources /var/www/html/resources
COPY --from=builder /build/tools /var/www/html/tools

# Copy runtime files
COPY composer.json ./
COPY configuration.php ./
COPY phpunit.xml.dist ./
COPY phpstan.neon ./
COPY .php-cs-fixer.dist.php ./

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/public/uploads 2>/dev/null || true

# The credential-recovery tokens live here, outside the document root.
# The directory must be writable by www-data; the tokens themselves are
# written with mode 0644 so that the administrator can read them.
RUN mkdir -p /var/www/html/storage/reset-tokens/pending \
             /var/www/html/storage/reset-tokens/admin \
    && chown -R www-data:www-data /var/www/html/storage \
    && chmod -R 777 /var/www/html/storage

# Copy Apache config
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# The entrypoint prepares the writable directories (the bind mount hides
# the permissions set in the image).
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]

# Health check
HEALTHCHECK --interval=30s --timeout=3s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

EXPOSE 80

CMD ["apache2-foreground"]
