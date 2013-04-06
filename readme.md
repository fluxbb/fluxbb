# FluxBB 2.0

FluxBB is a fast, light, user-friendly forum application for your website.

## Installation

### Using Vagrant

The preferred method of getting FluxBB up for development is by using [Vagrant](http://www.vagrantup.com/). This allows you to work in a preconfigured VM without having to make changes to your computer such as installing PHP and a webserver.

To install FluxBB using this method:

 1. Download and install [Virtualbox](https://www.virtualbox.org/) which is required for using Vagrant.
 2. Download and install [Vagrant](https://www.vagrantup.com/).
 3. Use Git to clone this repository: `git clone git://github.com/fluxbb/fluxbb2.git`.
 4. Run `vagrant up` from the command line. This will configure the VM for you. This can take a few minutes when you run it for the first time.

You will now be able to access your FluxBB installation at http://localhost:2008/public/index.php.

A MySQL database will be created for you. To use this during the installation of FluxBB, type in:
 * Database host: *localhost*
 * Database name: *fluxbb*
 * Database user: *fluxbb*
 * Database password: *password*

### On your local webserver

To install FluxBB, run the following commands from your command line:

    git clone git://github.com/fluxbb/fluxbb2.git
    curl -s https://getcomposer.org/installer | php
    php composer.phar install

After that, you can browse to the public/ folder in your favorite browser. FluxBB will then ask you to install it. :)
