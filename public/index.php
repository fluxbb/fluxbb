<?php
/**
 * Illuminate - A PHP Framework For The Bright.
 *
 * @package  Illuminate
 * @version  1.0.0
 * @author   Taylor Otwell <taylorotwell@gmail.com>
 */

define('ILLUMINATE_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Register The Composer Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader
| for our application. We just need to utilize it! We'll require it
| into the script here so that we don't have to worry about the
| loading of any our classes manually. Feels great to relax.
|
*/

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Turn On The Lights
|--------------------------------------------------------------------------
|
| We need to illuminate PHP development, so let's turn on the lights.
| This bootstrap the framework and gets it ready for use, then it
| will load up the application so that we can run it and send
| the responses back to the browser and delight our users.
|
*/

$app = require_once __DIR__.'/../shine.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can simple call the run method,
| which will execute the request and send the response back to
| the client's browser allowing them to enjoy the creative
| this wonderful applications we have created for them.
|
*/

$app->run();