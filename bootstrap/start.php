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
$app = new FluxBB\Core\Application();
Illuminate\Support\Facades\Facade::setFacadeApplication($app);

function trans($id, $parameters = [])
{
    global $app;
    return $app->make('translator')->trans($id, $parameters);
}

$basePath = __DIR__ . '/../';
$configPath = __DIR__ . '/../config';
$langPath = __DIR__ . '/../lang';
$cachePath = __DIR__ . '/../cache';
$app->instance('path', $basePath);
$app->instance('path.config', $configPath);
$app->instance('path.lang', $langPath);
$app->instance('path.cache', $cachePath);

$app->instance('config', new Repository(
    new FileLoader(new Filesystem, $configPath),
    'local'
));

$app->alias('config', 'Illuminate\Contracts\Config\Repository');
$app->alias('cookie', 'Illuminate\Contracts\Cookie\QueueingFactory');
$app->alias('events', 'Illuminate\Contracts\Events\Dispatcher');
$app->alias('hash', 'Illuminate\Contracts\Hashing\Hasher');
$app->alias('mailer', 'Illuminate\Contracts\Mail\Mailer');
$app->alias('view', 'Illuminate\Contracts\View\Factory');

/*
 * Register all service providers.
 */
$app->register('Illuminate\Cookie\CookieServiceProvider');
$app->register('Illuminate\Hashing\HashServiceProvider');
$app->register('Illuminate\Translation\TranslationServiceProvider');
$app->register('Illuminate\View\ViewServiceProvider');
$app->register('FluxBB\Auth\AuthServiceProvider');
$app->register('FluxBB\Cache\CacheServiceProvider');
$app->register('FluxBB\Core\CoreServiceProvider');
$app->register('FluxBB\Core\FilesystemServiceProvider');
$app->register('FluxBB\Database\DatabaseServiceProvider');
$app->register('FluxBB\Mail\MailServiceProvider');
$app->register('FluxBB\Server\ServerServiceProvider');
$app->register('FluxBB\Validation\ValidationServiceProvider');
$app->register('FluxBB\Web\Assets\AssetsServiceProvider');
$app->register('FluxBB\Web\WebServiceProvider');
$app->register('FluxBB\Web\SessionServiceProvider');
$app->register('FluxBB\Installer\InstallerServiceProvider');


/*
 * Other setup.
 */
$app->singleton('FluxBB\Auth\AuthenticatorInterface', 'FluxBB\Integration\Laravel\Authenticator');


return $app;
