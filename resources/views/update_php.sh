#!/bin/bash

# Define PHP version to keep
PHP_VERSION="8.4"

# Install PHP 8.4
sudo apt install -y php$PHP_VERSION php$PHP_VERSION-cli php$PHP_VERSION-fpm php$PHP_VERSION-mysql php$PHP_VERSION-curl php$PHP_VERSION-xml php$PHP_VERSION-mbstring

# Symlink PHP 8.4 binaries
sudo update-alternatives --set php /usr/bin/php$PHP_VERSION
sudo update-alternatives --set phpize /usr/bin/phpize$PHP_VERSION
sudo update-alternatives --set php-config /usr/bin/php-config$PHP_VERSION

# Verify installation
php -v
