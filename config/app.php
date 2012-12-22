<?php

return array(

	'debug' => true,
	'timezone' => 'UTC',

	'locale' => 'en',
	'fallback_locale' => 'en',

	'key' => 'ASecretFluxBBKey',

	'providers' => array(

		'Illuminate\Foundation\Providers\ArtisanServiceProvider',
		'Illuminate\Auth\AuthServiceProvider',
		'Illuminate\Cache\CacheServiceProvider',
		'Illuminate\Foundation\Providers\ComposerServiceProvider',
		'Illuminate\Routing\ControllerServiceProvider',
		'Illuminate\CookieServiceProvider',
		'Illuminate\Database\DatabaseServiceProvider',
		'Illuminate\EncryptionServiceProvider',
		'Illuminate\Events\EventServiceProvider',
		'Illuminate\FilesystemServiceProvider',
		'Illuminate\Hashing\HashServiceProvider',
		'Illuminate\Log\LogServiceProvider',
		'Illuminate\Mail\MailServiceProvider',
		'Illuminate\Database\MigrationServiceProvider',
		'Illuminate\Pagination\PaginationServiceProvider',
		'Illuminate\Foundation\Providers\PublisherServiceProvider',
		'Illuminate\Redis\RedisServiceProvider',
		'Illuminate\Database\SeedServiceProvider',
		'Illuminate\Session\SessionServiceProvider',
		'Illuminate\Translation\TranslationServiceProvider',
		'Illuminate\Validation\ValidationServiceProvider',
		'Illuminate\View\ViewServiceProvider',
		'FluxBB\Core\Providers\RouteServiceProvider',
		'FluxBB\Installer\Providers\RouteServiceProvider',

	),

	'aliases' => array(

		'App'        => 'Illuminate\Support\Facades\App',
		'Artisan'    => 'Illuminate\Support\Facades\Artisan',
		'Auth'       => 'Illuminate\Support\Facades\Auth',
		'Cache'      => 'Illuminate\Support\Facades\Cache',
		'Config'     => 'Illuminate\Support\Facades\Config',
		'Controller' => 'Illuminate\Routing\Controllers\Controller',
		'Cookie'     => 'Illuminate\Support\Facades\Cookie',
		'Crypt'      => 'Illuminate\Support\Facades\Crypt',
		'DB'         => 'Illuminate\Support\Facades\DB',
		'Eloquent'   => 'Illuminate\Database\Eloquent\Model',
		'Event'      => 'Illuminate\Support\Facades\Event',
		'File'       => 'Illuminate\Support\Facades\File',
		'Hash'       => 'Illuminate\Support\Facades\Hash',
		'Input'      => 'Illuminate\Support\Facades\Input',
		'Lang'       => 'Illuminate\Support\Facades\Lang',
		'Log'        => 'Illuminate\Support\Facades\Log',
		'Mail'       => 'Illuminate\Support\Facades\Mail',
		'Paginator'  => 'Illuminate\Support\Facades\Paginator',
		'Redirect'   => 'Illuminate\Support\Facades\Redirect',
		'Redis'      => 'Illuminate\Support\Facades\Redis',
		'Request'    => 'Illuminate\Support\Facades\Request',
		'Response'   => 'Illuminate\Support\Facades\Response',
		'Route'      => 'Illuminate\Support\Facades\Route',
		'Schema'     => 'Illuminate\Support\Facades\Schema',
		'Session'    => 'Illuminate\Support\Facades\Session',
		'URL'        => 'Illuminate\Support\Facades\URL',
		'Validator'  => 'Illuminate\Support\Facades\Validator',
		'View'       => 'Illuminate\Support\Facades\View',

	),

);