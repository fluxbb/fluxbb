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

$services = array(
	'FluxBB\Services\AuthService',
	'FluxBB\Services\CacheService',
	'FluxBB\Services\ConfigService',
	'Illuminate\CookieServiceProvider',
	'FluxBB\Services\DatabaseService',
	'Illuminate\EncryptionServiceProvider',
	'Illuminate\Events\EventServiceProvider',
	'Illuminate\FilesystemServiceProvider',
	'Illuminate\Hashing\HashServiceProvider',
	'Illuminate\Pagination\PaginationServiceProvider',
	'FluxBB\Services\RoutingService',
	'FluxBB\Services\SessionService',
	'Illuminate\Translation\TranslationServiceProvider',
	'Illuminate\Validation\ValidationServiceProvider',
	'FluxBB\Services\ViewService',
);

foreach ($services as $service)
{
	$app->register(new $service($app));
}

Illuminate\Support\Facade::setFacadeApplication($app);
FluxBB\Models\Base::setCacheStore($app['cache']);


include __DIR__.'/lib/helpers/validators.php';



$app->run();

