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

use Illuminate\Support\MessageBag;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Environment;
use Illuminate\View\FileViewFinder;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;

class ViewService extends ServiceProvider
{

	public function register()
	{
		$this->app['view'] = $this->app->share(function($app)
		{
			$resolver = new EngineResolver;
			$resolver->register('blade', function() use ($app)
			{
				$cache = $app['path.cache'].'views/';
				$compiler = new BladeCompiler($app['files'], $cache);

				return new CompilerEngine($compiler, $app['files']);
			});

			$paths = array($app['path.view']);
			$finder = new FileViewFinder($app['files'], $paths);

			$environment = new Environment($resolver, $finder, $app['events']);

			if (isset($app['session']) and $app['session']->has('errors'))
			{
				$environment->share('errors', $app['session']->get('errors'));
			}
			else
			{
				$environment->share('errors', new MessageBag);
			}

			$environment->setContainer($app);
			$environment->share('app', $app);

			return $environment;
		});
	}

}
