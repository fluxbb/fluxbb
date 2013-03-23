#!/bin/sh

apt-get update
apt-get install -y php5 php5-cli php5-mcrypt php-pear curl git sqlite

hash -r

pear upgrade-all
pear config-set auto_discover 1
pear install -f --alldeps pear.phpunit.de/PHPUnit

curl -s https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer.phar