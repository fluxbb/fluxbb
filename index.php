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


$kernel = new FluxBB\Web\Dispatcher(
    $app,
    $app->make('FluxBB\Web\Router'),
    new FluxBB\Web\ControllerFactory($app)
);

$session = $app->make('Symfony\Component\HttpFoundation\Session\SessionInterface');
$cookies = $app->make('Illuminate\Contracts\Cookie\QueueingFactory');

$kernel = new FluxBB\Web\SessionKernel($kernel, $session);
$kernel = new FluxBB\Web\CookieKernel($kernel, $cookies);

$response = $kernel->handle($request);
$response->send();
