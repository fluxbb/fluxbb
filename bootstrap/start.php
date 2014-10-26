<?php

use Illuminate\Config\FileLoader;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;


/*
 * Load the Composer autoloader.
 */
require __DIR__.'/../vendor/autoload.php';


/*
 * Create the main application.
 */
$app = new Illuminate\Foundation\Application(__DIR__.'/../');
Illuminate\Support\Facades\Facade::setFacadeApplication($app);

$app->instance('config', $config = new Repository(
	new FileLoader(new Filesystem, __DIR__.'/../config'), 'production'
));


/*
 * Register all service providers.
 */
$app->register('Illuminate\Auth\AuthServiceProvider');
$app->register('Illuminate\Cache\CacheServiceProvider');
$app->register('Illuminate\Cookie\CookieServiceProvider');
$app->register('Illuminate\Database\DatabaseServiceProvider');
$app->register('Illuminate\Filesystem\FilesystemServiceProvider');
$app->register('Illuminate\Hashing\HashServiceProvider');
$app->register('Illuminate\Session\SessionServiceProvider');
$app->register('Illuminate\Translation\TranslationServiceProvider');
$app->register('Illuminate\Validation\ValidationServiceProvider');
$app->register('Illuminate\View\ViewServiceProvider');
$app->register('FluxBB\Core\CoreServiceProvider');
$app->register('FluxBB\Server\ServiceProvider');
$app->register('FluxBB\Web\ServiceProvider');


return $app;
