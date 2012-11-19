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

use Illuminate\Database\ConnectionResolver,
	Illuminate\Database\Connectors\ConnectionFactory,
	Illuminate\Database\Eloquent\Model,
	Illuminate\Support\ServiceProvider;

class DatabaseService extends ServiceProvider
{

	public function register($app)
	{
		$app['db.factory'] = $app->share(function()
		{
			return new ConnectionFactory;
		});

		$app['db.connection'] = $app->share(function($app)
		{
			$connection = $app['db.factory']->make($app['config']['database.connection']);

			$connection->setFetchMode(\PDO::FETCH_CLASS);
			$connection->setEventDispatcher($app['events']);
			$connection->setPaginator(function() use ($app)
			{
				return $app['paginator'];
			});

			return $connection;
		});

		$app['db.resolver'] = $app->share(function($app)
		{
			$resolver = new ConnectionResolver;
			$resolver->addConnection('default', $app['db.connection']);
			$resolver->setDefaultConnection('default');

			return $resolver;
		});

		$this->registerEloquent($app);
	}

	public function registerEloquent($app)
	{
		Model::setConnectionResolver($app['db.resolver']);
	}

}
