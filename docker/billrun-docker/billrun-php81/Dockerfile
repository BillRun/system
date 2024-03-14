from php:8.1-fpm
# https://github.com/netroby/docker-php-fpm/blob/master/Dockerfile

RUN apt-get update && apt-get install -y \
        wkhtmltopdf wget  \
		libpng-dev zlib1g-dev libzip-dev libgmp-dev  \
                libssl-dev unzip \ 
                openjdk-17-jre-headless \
    && pecl install yaf \
    && pecl install mongodb \
    && pecl install xdebug \
    && docker-php-ext-enable yaf mongodb xdebug \
    && docker-php-ext-install pcntl bcmath gd zip sockets gmp

RUN  wget 'https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-3/wkhtmltox_0.12.6.1-3.bookworm_amd64.deb' \
	&& apt-get install -y ./wkhtmltox_0.12.6.1-3.bookworm_amd64.deb

RUN docker-php-ext-configure calendar && docker-php-ext-install calendar

RUN docker-php-ext-configure pcntl --enable-pcntl \
    && docker-php-ext-install pcntl

## install jsignpdf
RUN wget -O /tmp/jsignpdf.zip https://github.com/JSignPdf/JSignPdfBin/archive/refs/tags/1.0.1.zip
RUN unzip /tmp/jsignpdf.zip -d /tmp
RUN mv /tmp/JSignPdfBin-1.0.1/bin/jsignpdf-1.6.4/ /opt
RUN rm -rf /tmp/*

COPY php-fpm.conf /usr/local/etc/
COPY php.ini /usr/local/etc/php/
COPY xdebug.ini /usr/local/etc/php/conf.d/

WORKDIR /billrun

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY composer.json /var/www/html/

RUN composer install -d /var/www/html/  # in production we need to add --no-dev

CMD ["php-fpm"]
