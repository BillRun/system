from php:7.4-fpm
# https://github.com/netroby/docker-php-fpm/blob/master/Dockerfile

RUN apt-get update && apt-get install -y \
        wkhtmltopdf wget  \
		libpng-dev zlib1g-dev libzip-dev \
    && pecl install yaf-3.1.4 \
    && pecl install mongodb-1.11.1 \
    && pecl install xdebug-3.1.6 \
    && docker-php-ext-enable yaf mongodb xdebug \
    && docker-php-ext-install pcntl bcmath gd zip sockets


RUN  wget 'https://github.com/wkhtmltopdf/wkhtmltopdf/releases/download/0.12.5/wkhtmltox_0.12.5-1.buster_amd64.deb' \
	&& apt install -y ./wkhtmltox_0.12.5-1.buster_amd64.deb

COPY php-fpm.conf /usr/local/etc/
COPY php.ini /usr/local/etc/php/
COPY xdebug.ini /usr/local/etc/php/conf.d/
CMD ["php-fpm"]
