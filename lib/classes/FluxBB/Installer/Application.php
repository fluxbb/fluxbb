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

namespace FluxBB\Installer;

use Illuminate\Http\Request;

class Application extends \FluxBB\Application
{

	protected function dispatch(Request $request)
	{
		$step = $this['request']->query('step', 'start');
		$valid = array('start', 'database', 'config', 'admin', 'run', 'success');

		if (!in_array($step, $valid))
		{
			$step = 'start';
		}

		return $this['view']->make('install.'.$step);
	}

	protected function createDatabase(array $config)
	{
		$factory = new ConnectionFactory;
		$connection = $factory->make($config);

		return $connection;
	}

	protected function remember($key, $value)
	{
		$this['session']->put('fluxbb.install.'.$key, $value);
	}

	protected function has($key)
	{
		return $this['session']->has('fluxbb.install.'.$key);
	}

	protected function retrieve($key)
	{
		return $this['session']->get('fluxbb.install.'.$key);
	}

}