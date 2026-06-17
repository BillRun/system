from php:8.5-fpm
# https://github.com/netroby/docker-php-fpm/blob/master/Dockerfile

RUN apt-get update && apt-get install -y \
        wget  \
		libpng-dev zlib1g-dev libzip-dev libgmp-dev  \
                libssl-dev unzip \
                openjdk-21-jre-headless \
                python3-pip libpango-1.0-0 libpangoft2-1.0-0 shared-mime-info \
    && pecl install yaf \
    && pecl install mongodb-2.3.1 \
    && pecl install xdebug \
    && docker-php-ext-enable yaf mongodb xdebug \
    && docker-php-ext-install pcntl bcmath gd zip sockets gmp \
    && pip3 install --break-system-packages 'weasyprint>=62'

RUN  wget 'https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-3/wkhtmltox_0.12.6.1-3.bookworm_amd64.deb' \
	&& apt-get install -y ./wkhtmltox_0.12.6.1-3.bookworm_amd64.deb

RUN docker-php-ext-configure calendar && docker-php-ext-install calendar

RUN docker-php-ext-configure pcntl --enable-pcntl \
    && docker-php-ext-install pcntl

RUN wget -O /tmp/jsignpdf.zip \
        'https://repo1.maven.org/maven2/com/github/kwart/jsign/jsignpdf-distribution/2.3.0/jsignpdf-distribution-2.3.0.zip' \
    && unzip /tmp/jsignpdf.zip -d /opt \
    && rm -f /tmp/jsignpdf.zip
COPY php-fpm.conf /usr/local/etc/
COPY php.ini /usr/local/etc/php/
COPY xdebug.ini /usr/local/etc/php/conf.d/

WORKDIR /billrun

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY composer.json /var/www/html/
COPY composer.lock /var/www/html/

RUN composer install -d /var/www/html/  # in production we need to add --no-dev
RUN ln -sf behat/gherkin/i18n.php /var/www/html/vendor/i18n.php

COPY enable_client_plugin.sh /enable_client_plugin.sh
RUN chmod ug+x /enable_client_plugin.sh
ENTRYPOINT ["/enable_client_plugin.sh"]

CMD ["php-fpm"]
