FROM php:7.4-fpm
# https://github.com/netroby/docker-php-fpm/blob/master/Dockerfile

RUN apt-get update && apt-get install --no-install-recommends -y \
        wkhtmltopdf wget  \
		libpng-dev zlib1g-dev libzip-dev libgmp-dev \
                unzip openjdk-11-jre-headless \
                libssl-dev pkg-config openssl \
                libmemcached-dev \
    && pecl install yaf-3.1.4 \
    && pecl install mongodb-1.15.3 \
    && pecl install xdebug-3.1.6 \
    && pecl install memcached \
    && pecl install redis-6.2.0 \
    && docker-php-ext-enable yaf mongodb xdebug memcached redis \
    && docker-php-ext-install pcntl bcmath gd zip sockets gmp calendar

RUN  wget 'https://github.com/wkhtmltopdf/wkhtmltopdf/releases/download/0.12.5/wkhtmltox_0.12.5-1.buster_amd64.deb' \
	&& apt install -y ./wkhtmltox_0.12.5-1.buster_amd64.deb

# install jsignpdf
RUN wget -O /tmp/jsignpdf.zip https://github.com/JSignPdf/JSignPdfBin/archive/refs/tags/1.0.1.zip
RUN unzip /tmp/jsignpdf.zip -d /tmp
RUN mv /tmp/JSignPdfBin-1.0.1/bin/jsignpdf-1.6.4/ /opt
RUN rm -rf /tmp/*

COPY php-fpm.conf /usr/local/etc/
COPY php.ini /usr/local/etc/php/
COPY xdebug.ini /usr/local/etc/php/conf.d/
COPY --from=composer:2.6.6 /usr/bin/composer /usr/bin/composer
COPY enable_client_plugin.sh /enable_client_plugin.sh
RUN chmod ug+x /enable_client_plugin.sh
ENTRYPOINT ["/enable_client_plugin.sh"]
ARG EXTRA_PACKAGES=""
RUN if [ -n "${EXTRA_PACKAGES}" ]; then \
      apt-get install --no-install-recommends -y ${EXTRA_PACKAGES}; \
    fi
WORKDIR /billrun

CMD ["php-fpm"]
