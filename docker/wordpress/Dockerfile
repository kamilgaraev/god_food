FROM wordpress:7.1.3-php8.3-apache@sha256:f3e6835fa7d4a5b786615c24b67ec70816e06ebd517d31276bef423e49dfd126

RUN php -r 'exit(PHP_VERSION_ID >= 80336 && PHP_VERSION_ID < 80400 ? 0 : 1);' \
    && a2enmod expires headers
