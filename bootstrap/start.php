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

$basePath = __DIR__ . '/../';
$configPath = __DIR__ . '/../config';
$app->instance('path', $basePath);
$app->instance('path.config', $configPath);

$app->instance('config', $config = new Repository(
    new FileLoader(new Filesystem, $configPath),
    'local'
));


/*
 * Register all service providers.
 */
$app->register('Illuminate\Cache\CacheServiceProvider');
$app->register('Illuminate\Cookie\CookieServiceProvider');
$app->register('Illuminate\Filesystem\FilesystemServiceProvider');
$app->register('Illuminate\Hashing\HashServiceProvider');
$app->register('Illuminate\Mail\MailServiceProvider');
$app->register('Illuminate\Session\SessionServiceProvider');
$app->register('Illuminate\Translation\TranslationServiceProvider');
$app->register('Illuminate\Validation\ValidationServiceProvider');
$app->register('Illuminate\View\ViewServiceProvider');
$app->register('FluxBB\Auth\AuthServiceProvider');
$app->register('FluxBB\Core\CoreServiceProvider');
$app->register('FluxBB\Database\DatabaseServiceProvider');
$app->register('FluxBB\Server\ServiceProvider');
$app->register('FluxBB\Validator\ValidationServiceProvider');
$app->register('FluxBB\Web\ServiceProvider');


/*
 * Other setup.
 */
$app->singleton('FluxBB\Auth\AuthenticatorInterface', 'FluxBB\Integration\Laravel\Authenticator');


return $app;
