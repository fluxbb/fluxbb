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

namespace FluxBB\Services;

use FluxBB\Session\Store,
	Illuminate\Support\ServiceProvider;

class SessionService extends ServiceProvider
{

	public function boot()
	{
		$app = $this->app;

		$app->before(function($request) use ($app)
		{
			$app['session']->start($app['cookie']);
		});

		$app->close(function($request, $response) use ($app)
		{
			$app['session']->finish($response, $app['cookie']);
		});
	}

	public function register()
	{
		$this->registerSessionDriver();

		//$this->registerSessionFilter($app);
	}

	protected function registerSessionDriver()
	{
		$this->app['session'] = $this->app->share(function($app)
		{
			// TODO: Fix table name
			$driver = new Store($app['db.connection'], $app['encrypter'], 'sessions');

			$driver->setRequest($app['request']);
			$driver->setLifetime($app['config']['session.lifetime']);
			$driver->setSweepLottery($app['config']['session.lottery']);

			return $driver;
		});
	}

}
