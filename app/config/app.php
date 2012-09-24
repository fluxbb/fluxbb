<?php

return array(

	/*
	|--------------------------------------------------------------------------
	| Application Debug Mode
	|--------------------------------------------------------------------------
	|
	| When your application is in debug mode, detailed error messages with
	| stack traces will be shown on every error that occurs within your
	| application. If disabled, a simple generic error page is shown.
	|
	*/

	'debug' => true,

	/*
	|--------------------------------------------------------------------------
	| Application Locale Configuration
	|--------------------------------------------------------------------------
	|
	| The application locale determines the default locale that will be used
	| by the translation service provider. You are free to set this value
	| to any of the locales which will be supported by the application.
	|
	*/

	'locales' => array('en'),

	'locale' => 'en',

	'fallback_locale' => 'en',

	'locale_path' => __DIR__.'/../lang',

	/*
	|--------------------------------------------------------------------------
	| Encryption Key
	|--------------------------------------------------------------------------
	|
	| This key is used by the Illuminate encrypter service and should be set
	| to a random, long string, otherwise your encrypted values will not
	| be safe. Be sure to change this before deploying your websites!
	|
	*/

	'key' => 'YourSecretKey!',

	/*
	|--------------------------------------------------------------------------
	| Class Aliases
	|--------------------------------------------------------------------------
	|
	| This array of class aliases will be registered when this application
	| is started. However, feel free to register as many as you wish as
	| the aliases are "lazy" loaded so they don't hinder performance.
	|
	*/

	'aliases' => array(

		'Controller' => 'Illuminate\Routing\Controller',
		'Eloquent'   => 'Illuminate\Database\Eloquent\Model',

	),

	/*
	|--------------------------------------------------------------------------
	| Autoloaded Service Providers
	|--------------------------------------------------------------------------
	|
	| The service providers listed here will be automatically loaded on each
	| request to your application. Feel free to add your own services to
	| this array to grant expanded functionality to your applications.
	|
	*/

	'providers' => array(

		'Illuminate\Foundation\Providers\AliasServiceProvider',
		'Illuminate\Foundation\Providers\AuthServiceProvider',
		'Illuminate\Foundation\Providers\CacheServiceProvider',
		'Illuminate\Foundation\Providers\CookieServiceProvider',
		'Illuminate\Foundation\Providers\DatabaseServiceProvider',
		'Illuminate\Foundation\Providers\EncrypterServiceProvider',
		'Illuminate\Foundation\Providers\EventsServiceProvider',
		'Illuminate\Foundation\Providers\FilesystemServiceProvider',
		'Illuminate\Foundation\Providers\HashServiceProvider',
		'Illuminate\Foundation\Providers\PaginationServiceProvider',
		'Illuminate\Foundation\Providers\RedisServiceProvider',
		'Illuminate\Foundation\Providers\SessionServiceProvider',
		'Illuminate\Foundation\Providers\TranslationServiceProvider',
		'Illuminate\Foundation\Providers\ValidatorServiceProvider',
		'Illuminate\Foundation\Providers\ViewServiceProvider',

	),

);
