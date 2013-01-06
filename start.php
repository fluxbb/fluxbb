<?php

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Laravel application instance
| which serves as the "glue" for all the components of Laravel, and is
| the IoC container for the system binding all of the various parts.
|
*/

$app = new Illuminate\Foundation\Application;

/*
|--------------------------------------------------------------------------
| Define The Application Path
|--------------------------------------------------------------------------
|
| Here we just defined the path to the application directory. Most likely
| you will never need to change this value as the default setup should
| work perfectly fine for the vast majority of all our applications.
|
*/

$app->instance('path', $appPath = __DIR__);

$app->instance('path.base', __DIR__);

/*
|--------------------------------------------------------------------------
| Detect The Application Environment
|--------------------------------------------------------------------------
|
| Laravel takes a dead simple approach to your application environments
| so you can just specify a machine name or HTTP host that matches a
| given environment, then we will automatically detect it for you.
|
*/

$env = $app->detectEnvironment(array(

	'local' => array('localhost', '*.dev', '*.app'),

));

/*
|--------------------------------------------------------------------------
| Load The Application
|--------------------------------------------------------------------------
|
| Here we will load the Illuminate application. We'll keep this is in a
| separate location so we can isolate the creation of an application
| from the actual running of the application with a given request.
|
*/

require __DIR__.'/vendor/illuminate/foundation/src/start.php';

/*
|--------------------------------------------------------------------------
| Bootstrap FluxBB
|--------------------------------------------------------------------------
|
| Include the FluxBB start file that will setup everything that's needed
| for FluxBB to function in a Laravel context.
|
*/

require __DIR__.'/vendor/fluxbb/core/start.php';

return $app;