FROM php:8.2-cli-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    unzip \
    autoconf \
    g++ \
    make \
    linux-headers \
    bash \
    libzip-dev \
    zip

# Install PHP extensions
RUN docker-php-ext-install -j$(nproc) zip

# PCOV for code coverage (per BUNDLES_STANDARDS_PROMPT §2.1: same image for dev and tests)
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del $PHPIZE_DEPS

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure git safe directory
RUN git config --global --add safe.directory /app

# Set working directory
WORKDIR /app

# Set environment
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="/app/vendor/bin:${PATH}"
ENV XDEBUG_MODE=coverage

