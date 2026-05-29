FROM php:8.5-fpm
# https://github.com/netroby/docker-php-fpm/blob/master/Dockerfile

RUN apt-get update && apt-get install --no-install-recommends -y \
        wget  \
        libpng-dev zlib1g-dev libzip-dev libgmp-dev \
        unzip openjdk-21-jre-headless \
        libssl-dev pkg-config openssl \
        libmemcached-dev \
        nginx supervisor \
        gettext-base \
    && pecl install yaf \
    && pecl install mongodb-2.3.1 \
    && pecl install memcached \
    && pecl install redis \
    && docker-php-ext-enable yaf mongodb memcached redis \
    && docker-php-ext-install pcntl bcmath gd zip sockets gmp calendar \
    && rm -rf /var/lib/apt/lists/*

RUN apt-get update \
    && wget 'https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-3/wkhtmltox_0.12.6.1-3.bookworm_amd64.deb' \
    && apt-get install -y ./wkhtmltox_0.12.6.1-3.bookworm_amd64.deb \
    && rm wkhtmltox_0.12.6.1-3.bookworm_amd64.deb \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /billrun
# Copy over custom configurations
# (Assuming php-fpm.conf and php.ini are still in the old directory. If they are also in cicd, change their source paths too!)
COPY docker/billrun-docker/billrun-php85/php-fpm.conf /usr/local/etc/
COPY docker/billrun-docker/billrun-php85/php.ini /usr/local/etc/php/

# These are now pulling right from your docker/cicd folder
COPY docker/cicd/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
# Nginx config is shipped as a template; entrypoint.sh renders it on container
# start so APPLICATION_MULTITENANT (and any future runtime params) can come
# from env vars rather than being baked into the image.
COPY docker/cicd/nginx.conf.template /etc/nginx/sites-available/default.template
# Re-link Nginx site config if it isn't automatically caught
RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

COPY ./ /billrun

# Install Composer and project PHP dependencies (vendor/ is gitignored, so it is
# not part of the build context and must be generated at image build time).
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
    && rm /usr/local/bin/composer

RUN mkdir -p /billrun/logs && touch /billrun/logs/debug.log && chmod 777 /billrun/logs/debug.log
RUN mkdir -p /billrun/logs/container && touch /billrun/logs/container/debug.log && chmod 777 /billrun/logs/container/debug.log

COPY docker/cicd/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose HTTP web server port instead of FastCGI port.
# Port 8074 is chosen to align with the BillRun ecosystem convention
EXPOSE 8074

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Command tells Supervisor to launch and run everything in the foreground
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
