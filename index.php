<?php

require 'vendor/autoload.php';

use FluxBB\Application;
use Illuminate\Filesystem;
use Illuminate\Config\FileLoader;
use Illuminate\Config\Repository as ConfigRepository;

$app = new Application;

set_fluxbb($app);

$app['path'] = __DIR__;
$app['path.config'] = __DIR__.'/config/';
$app['path.lang'] = __DIR__.'/lang/';
$app['path.view'] = __DIR__.'/views/';
$app['path.cache'] = __DIR__.'/cache/';

$app['auth.model'] = 'FluxBB\\Models\\User';

$app['config.loader'] = $app->share(function($app)
{
	return new FileLoader(new Filesystem, $app['path.config']);
});

$app['config'] = $app->share(function($app)
{
	return new ConfigRepository($app['config.loader'], 'production');
});

$app->register(new FluxBB\Services\AuthService);
$app->register(new FluxBB\Services\CacheService);
$app->register(new FluxBB\Services\ConfigService);
$app->register(new FluxBB\Services\CookieService);
$app->register(new FluxBB\Services\DatabaseService);
$app->register(new FluxBB\Services\EncrypterService);
$app->register(new FluxBB\Services\EventService);
$app->register(new FluxBB\Services\FilesystemService);
$app->register(new FluxBB\Services\HashService);
$app->register(new FluxBB\Services\PaginationService);
$app->register(new FluxBB\Services\RoutingService);
$app->register(new FluxBB\Services\SessionService);
$app->register(new FluxBB\Services\TranslationService);
$app->register(new FluxBB\Services\ValidationService);
$app->register(new FluxBB\Services\ViewService);

Illuminate\Support\Facade::setFacadeApplication($app);
FluxBB\Models\Base::setCacheStore($app['cache']);


include __DIR__.'/lib/helpers/validators.php';



$app->run();

