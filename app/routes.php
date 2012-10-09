<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for the application.
| It's a breeze. Just tell Illuminate the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/

/*
Route::get('/', function()
{
	return View::make('hello');
});
*/

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

App::error(function(NotFoundHttpException $e) {
	return View::make('error.404');
});
