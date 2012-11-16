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

namespace FluxBB\Routing;

use FluxBB\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Factory;

class Controller
{

	protected $app;


	public function setApplication(Application $app)
	{
		$this->app = $app;

		return $this;
	}

	public function execute($action, $method, $parameters)
	{
		$call = $method.'_'.$action;

		return call_user_func_array(array($this, $call), $parameters);
	}

	public function redirect($route, $parameters = array(), $message = '')
	{
		return new RedirectResponse();
	}

	public function validator($attributes, $rules, $messages = array())
	{
		// TODO: Should these be properly dependency-injected?
		// All the way through the factory...
		return $this->app['validator']->make($attributes, $rules, $messages);
	}

	public function view($name)
	{
		return $this->app['view']->make($name);
	}

	public function input($key = null, $default = null)
	{
		if (is_null($key))
		{
			return $this->app['request']->everything();
		}
		
		return $this->app['request']->input($key, $default);
	}

	public function hasInput($key)
	{
		return trim((string) $this->app['request']->input($key)) !== '';
	}

	public function isGuest()
	{
		return \FluxBB\Auth::isGuest();
	}

	public function isAuthed()
	{
		return \FluxBB\Auth::isAuthed();
	}

}
