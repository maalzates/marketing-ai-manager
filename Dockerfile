FROM php:8.4-fpm

# System packages needed to build the PHP extensions below.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        zip \
        curl \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        zip

# pcov is the coverage driver: line coverage only, and fast enough to run the
# whole suite. xdebug is not installed — nothing here needs step debugging.
RUN pecl install redis pcov && docker-php-ext-enable redis pcov

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# php-fpm runs as www-data; match it to the host user so bind-mounted files
# stay writable from both sides during development.
ARG UID=1000
ARG GID=1000
RUN groupmod -o -g "${GID}" www-data && usermod -o -u "${UID}" -g "${GID}" www-data

WORKDIR /var/www/html

CMD ["php-fpm"]
