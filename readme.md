# FluxBB 2.0

FluxBB is a fast, light, user-friendly forum application for your website.

## Installation

### Download

Download the zip archive from [GitHub](https://github.com/fluxbb/fluxbb/archive/2.0.zip) or clone the repository using
Git:

    git clone -b 2.0 git://github.com/fluxbb/fluxbb.git

### Install dependencies

When using the development version of FluxBB 2.0, you need to install dependencies using
[Composer](https://getcomposer.org/). In the directory where you downloaded FluxBB, run the following commands from
your command line:

    curl -s https://getcomposer.org/installer | php
    php composer.phar install

### Run the installer

Finally, to configure your forum and prepare a database, run the following in your command line:

    php fluxbb install

This will guide you through the installation process by asking for a few parameters.
