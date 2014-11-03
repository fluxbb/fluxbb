<?php
/**
 * FluxBB - fast, light-weight, user-friendly, extensible forum software.
 *
 * @package FluxBB
 * @author  Franz Liedke <franz@fluxbb.org>
 */

$app = require __DIR__.'/bootstrap/start.php';

$app->register('FluxBB\Installer\Web\RouteServiceProvider');

$app->instance('request', $request = Symfony\Component\HttpFoundation\Request::createFromGlobals());
$app->bind('FluxBB\Web\UrlGeneratorInterface', 'FluxBB\Web\UrlGenerator');
$app->boot();


$dispatcher = new FluxBB\Web\Dispatcher(
    $app->make('FluxBB\Web\Router'),
    new FluxBB\Web\ControllerFactory($app)
);

$dispatcher = new FluxBB\Web\SessionWrapper($dispatcher, $queue = $app->make('Illuminate\Contracts\Cookie\QueueingFactory'));

$response = $dispatcher->handle($request);
$response->send();
