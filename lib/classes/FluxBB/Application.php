<?php

namespace FluxBB;

use Closure,
	Illuminate\Container,
	Illuminate\Http\Request,
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
		$service->register($this);

		$this->services[] = $service;
	}

	public function run()
	{
		$response = $this->dispatch($this['request']);

		$response->prepare($this['request'])
			->send();
	}

	protected function dispatch(Request $request)
	{
		if (!$this->booted)
		{
			$this->boot();
		}

		return $this['router']->dispatch($request);
	}

	protected function boot()
	{
		foreach ($this->services as $service)
		{
			$service->boot($this);
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