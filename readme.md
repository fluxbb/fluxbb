# FluxBB 2.0

FluxBB is a fast, light, user-friendly forum application for your website.

## Installation

### On your local webserver

The development version of FluxBB 2.0 has to be installed using Composer.
To install FluxBB, run the following commands from your command line:

    git clone git://github.com/fluxbb/fluxbb.git
    git checkout 2.0
    curl -s https://getcomposer.org/installer | php
    php composer.phar install
    php artisan asset:publish fluxbb/core

After that, you can browse to the public/ folder in your favorite browser.
FluxBB will then ask you to install it. :)
