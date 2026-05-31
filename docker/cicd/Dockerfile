FROM php:7.4-fpm
# https://github.com/netroby/docker-php-fpm/blob/master/Dockerfile

RUN apt-get update && apt-get install --no-install-recommends -y \
        wkhtmltopdf wget  \
        libpng-dev zlib1g-dev libzip-dev libgmp-dev \
        unzip openjdk-11-jre-headless \
        libssl-dev pkg-config openssl \
        libmemcached-dev \
        nginx supervisor \
        gettext-base \
    && pecl install yaf-3.1.4 \
    && pecl install mongodb-1.19.4 \
    && pecl install memcached \
    && pecl install redis-6.2.0 \
    && docker-php-ext-enable yaf mongodb memcached redis \
    && docker-php-ext-install pcntl bcmath gd zip sockets gmp calendar \
    && rm -rf /var/lib/apt/lists/*

RUN wget 'https://github.com/wkhtmltopdf/wkhtmltopdf/releases/download/0.12.5/wkhtmltox_0.12.5-1.buster_amd64.deb' \
    && apt-get update && apt-get install -y ./wkhtmltox_0.12.5-1.buster_amd64.deb \
    && rm -rf /var/lib/apt/lists/*

# install jsignpdf 2.3.0 (drops bundled log4j 1.x; same CLI as 1.6.4, runs on Java 11)
# Sourced from Maven Central rather than SourceForge so the wget doesn't hit
# the Cloudflare/JS interstitial that SourceForge serves to non-browsers.
RUN wget -O /tmp/jsignpdf.zip \
        'https://repo1.maven.org/maven2/com/github/kwart/jsign/jsignpdf-distribution/2.3.0/jsignpdf-distribution-2.3.0.zip' \
    && unzip /tmp/jsignpdf.zip -d /opt \
    && rm -f /tmp/jsignpdf.zip

WORKDIR /billrun
# Copy over custom configurations
# (Assuming php-fpm.conf and php.ini are still in the old directory. If they are also in cicd, change their source paths too!)
COPY docker/billrun-docker/billrun-php74/php-fpm.conf /usr/local/etc/
COPY docker/billrun-docker/billrun-php74/php.ini /usr/local/etc/php/

# These are now pulling right from your docker/cicd folder
COPY docker/cicd/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
# Nginx config is shipped as a template; entrypoint.sh renders it on container
# start so APPLICATION_MULTITENANT (and any future runtime params) can come
# from env vars rather than being baked into the image.
COPY docker/cicd/nginx.conf.template /etc/nginx/sites-available/default.template
# Re-link Nginx site config if it isn't automatically caught
RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

COPY ./ /billrun

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
