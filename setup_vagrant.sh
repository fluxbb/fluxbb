#!/bin/sh

# Install all needed packages
apt-get update
apt-get install -y php5 php5-cli php5-mcrypt php-pear curl git sqlite

# Rehash the console
hash -r

# Install PHPUnit
pear upgrade-all
pear config-set auto_discover 1
pear install -f --alldeps pear.phpunit.de/PHPUnit

# Install Composer
curl -s https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer.phar