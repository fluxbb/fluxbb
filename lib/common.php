<?php
/**
 * FluxBB - fast, light, user-friendly PHP forum software
 * Copyright (C) 2008-2012 FluxBB.org
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public license for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category	FluxBB
 * @package		Core
 * @copyright	Copyright (c) 2008-2012 FluxBB (http://fluxbb.org)
 * @license		http://www.gnu.org/licenses/gpl.html	GNU General Public License
 */

// If the database config cannot be found, FluxBB needs to be installed.
if (!FluxBB\Core::isInstalled())
{
	header('Location: install.php');
	exit;
}


// Initialize paths and config
use Illuminate\Filesystem;
use Illuminate\Config\FileLoader;
use Illuminate\Config\Repository as ConfigRepository;

$app['path.config'] = $app['path'].'config/';
$app['path.lang'] = $app['path'].'lang/';
$app['path.view'] = $app['path'].'views/';
$app['path.cache'] = $app['path'].'cache/';

$app['config.loader'] = $app->share(function($app)
{
	return new FileLoader(new Filesystem, $app['path.config']);
});

$app['config'] = $app->share(function($app)
{
	return new ConfigRepository($app['config.loader'], 'production');
});

$app['auth.model'] = 'FluxBB\Models\User';


// TODO: Set up extensions


// Set up the service providers
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

// TODO: Hook for provider manipulation

foreach ($services as $service)
{
	$app->register(new $service($app));
}

Illuminate\Support\Facade::setFacadeApplication($app);
FluxBB\Models\Base::setCacheStore($app['cache']);

