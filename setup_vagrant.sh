#!/bin/sh

# Install all needed packages
apt-get -qq update
DEBIAN_FRONTEND=noninteractive apt-get install -qq -y libapache2-mod-php5 mysql-server php5-cli php5-mysql php5-mcrypt php-pear curl git sqlite

# Rehash the console
hash -r

# Install PHPUnit
pear upgrade-all
pear config-set auto_discover 1
pear install -f --alldeps pear.phpunit.de/PHPUnit

# Restart Apache
apache2ctl graceful

# Install Composer
curl -s https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer.phar

# Make some directories writable
chmod --recursive a+rw /var/www/public/packages
chmod --recursive a+rw /var/www/app/config/packages
chmod --recursive a+rw /var/www/app/storage

cd /var/www
composer.phar install

# Set up a dataase for FluxBB
echo "CREATE DATABASE IF NOT EXISTS fluxbb" | mysql
echo "GRANT ALL PRIVILEGES ON fluxbb.* TO 'fluxbb'@'localhost' IDENTIFIED BY 'password'" | mysql
