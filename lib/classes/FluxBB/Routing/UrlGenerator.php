<?php

namespace FluxBB\Routing;

class UrlGenerator
{

	protected $route;

	protected $baseUrl;


	public function __construct(array $routes, $baseUrl)
	{
		$this->routes = $routes;
		$this->baseUrl = rtrim($baseUrl, '/').'/';
	}

	public function toRoute($route, $parameters = array())
	{
		$url = $this->routes[$route]['url'];

		$url = preg_replace_callback('/\{([^\}]+)\}/', function($matches) use ($parameters)
		{
			return $parameters[$matches[1]];
		}, $url);

		return $this->to($url);
	}

	public function to($url)
	{
		return $this->baseUrl.$url;
	}

}