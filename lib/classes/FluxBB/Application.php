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

namespace FluxBB;

use Closure,
	Illuminate\Container,
	Illuminate\Http\Request,
	Symfony\Component\HttpFoundation\Response,
	Illuminate\Support\ServiceProvider;

class Application extends Container
{

	protected $booted = false;

	protected $services = array();


	public function __construct()
	{
		$this['request'] = Request::createFromGlobals();
	}

	public function register(ServiceProvider $service)
	{
		$service->register();

		$this->services[] = $service;
	}

	public function run()
	{
		if (!$this->booted)
		{
			$this->boot();
		}

		$response = $this->prepareResponse($this->dispatch($this['request']));

		$response->prepare($this['request'])
			->send();
	}

	protected function prepareResponse($response)
	{
		if ($response instanceof Response)
		{
			return $response;
		}

		return new Response($response);
	}

	protected function dispatch(Request $request)
	{
		return $this['router']->dispatch($request);
	}

	protected function boot()
	{
		foreach ($this->services as $service)
		{
			$service->boot();
		}

		$this->booted = true;
	}

	public function before(Closure $callback)
	{
		return $this['router']->filter('before', $callback);
	}

	public function after(Closure $callback)
	{
		return $this['router']->filter('after', $callback);
	}

	public function close(Closure $callback)
	{
		return $this['router']->filter('close', $callback);
	}

	public function finish(Closure $callback)
	{
		return $this['router']->filter('finish', $callback);
	}

}