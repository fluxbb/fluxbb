<?php

return array(

	/*
	|--------------------------------------------------------------------------
	| Default View Driver
	|--------------------------------------------------------------------------
	|
	| This option controls the default view driver that will get used for the
	| application. Laravel comes with a few great engines ranging from the
	| simple PHP engine to the powerful Blade engine. Use what you like!
	|
	| Supported: "php", "blade"
	|
	*/

	'driver' => 'blade',

	/*
	|--------------------------------------------------------------------------
	| View Storage Paths
	|--------------------------------------------------------------------------
	|
	| Most templating systems load templates from disk. Here you may specify
	| an array of paths that should be checked for your views. Of course
	| the usual Laravel view path has already been registered for you.
	|
	*/

	'paths' => array(

		__DIR__.'/../views',

	),

	/*
	|--------------------------------------------------------------------------
	| Compiled View Cache Path
	|--------------------------------------------------------------------------
	|
	| Some view engines cache a compiled version of their templates, such as
	| Twig and Blade. Here you may control where those caches are stored.
	| A sensible default has already been setup for your applications.
	|
	*/

	'cache' => __DIR__.'/../storage/views',

	/*
	|--------------------------------------------------------------------------
	| Pagination View
	|--------------------------------------------------------------------------
	|
	| This view will be used to render the pagination link output, and can
	| be easily customized here to show any view you like. A clean view
	| compatible with Twitter's Bootstrap is given to you by default.
	|
	*/

	'pagination' => 'pagination::slider',

);
