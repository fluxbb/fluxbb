<?php
/**
 * FluxBB - fast, light-weight, user-friendly, extensible forum software.
 *
 * @package FluxBB
 * @author  Franz Liedke <franz@fluxbb.org>
 */

$app = require __DIR__.'/bootstrap/start.php';

$app->bind('FluxBB\Web\UrlGeneratorInterface', 'FluxBB\Web\UrlGenerator');
$app->boot();


$dispatcher = new FluxBB\Web\Dispatcher(
	$app->make('FluxBB\Web\Router'),
	new FluxBB\Web\ControllerFactory($app)
);

$app->instance('request', $request = Symfony\Component\HttpFoundation\Request::createFromGlobals());

$response = $dispatcher->handle($request);
$response->send();
