<?php
/**
 * FluxBB - fast, light-weight, user-friendly, extensible forum software.
 *
 * @package FluxBB
 * @author  Franz Liedke <franz@fluxbb.org>
 */

use Illuminate\Config\FileLoader;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;

require __DIR__.'/vendor/autoload.php';


$app = new Illuminate\Foundation\Application(__DIR__);
Illuminate\Support\Facades\Facade::setFacadeApplication($app);

$app->instance('config', $config = new Repository(
	new FileLoader(new Filesystem, __DIR__.'/config'), 'production'
));

$app->register('Illuminate\Filesystem\FilesystemServiceProvider');
$app->register('Illuminate\Translation\TranslationServiceProvider');
$app->register('Illuminate\View\ViewServiceProvider');
$app->register('Illuminate\Database\DatabaseServiceProvider');
$app->register('FluxBB\Core\CoreServiceProvider');
$app->register('FluxBB\Server\ServiceProvider');
$app->register('FluxBB\Web\ServiceProvider');

$app->bind('FluxBB\Web\UrlGeneratorInterface', 'FluxBB\Web\UrlGenerator');

$app->boot();


$dispatcher = new FluxBB\Web\Dispatcher(
	$app->make('FluxBB\Web\Router'),
	new FluxBB\Web\ControllerFactory($app),
	$app
);

$app->instance('request', $request = Symfony\Component\HttpFoundation\Request::createFromGlobals());

$response = $dispatcher->handle($request);
$response->send();
