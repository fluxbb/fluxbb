<?php

namespace FluxBB\Routing;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Router
{

	protected $route;

	protected $parameters = array();

	protected $routes = array();

	protected $controllerResolver = null;

	protected $filters = array();


	public function __construct(array $routes, $resolver)
	{
		$this->routes = $routes;
		$this->controllerResolver = $resolver;
	}

	public function dispatch(Request $request)
	{
		$response = $this->applyFilters('before', array($request));

		if (!is_null($response))
		{
			return $this->wrapResponse($response);
		}

		$this->findRoute($request);

		$output = $this->runAction($this->route, $this->parameters);
		// TODO: This is also run on the final output in the application - not necessary
		$response = $this->wrapResponse($output);

		$this->applyFilters('after', array($request, $response));

		return $response;
	}

	protected function findRoute(Request $request)
	{
		$request_uri = ltrim($request->getPathInfo(), '/');

		foreach ($this->routes as $route)
		{
			$pattern = $this->createPattern($route['url']);
			if (preg_match($pattern, $request_uri, $matches))
			{
				$this->parameters = array();
				foreach ($matches as $key => $match)
				{
					if (!is_int($key))
					{
						$this->parameters[$key] = $match;
					}
				}
				
				$this->route = $route['action'];
				return;
			}
		}

		// If we got here, we have a problem
		echo '404';
		exit;
	}

	protected function createPattern($route)
	{
		// Parse placeholders into named groups
		$route = preg_replace('%\{([^\}]+)\}%', '(?P<$1>[^/]+)', $route);

		return '%^'.$route.'/?$%i';
	}

	protected function runAction($route, $parameters)
	{
		list($controller, $action) = explode('@', $route);

		$method = strtolower($_SERVER['REQUEST_METHOD']);

		$controller = $this->resolveController($controller);
		return $controller->execute($action, $method, $parameters);
	}

	protected function resolveController($controller)
	{
		return $this->controllerResolver->make($controller);
	}

	protected function wrapResponse($response)
	{
		if ($response instanceof Response)
		{
			return $response;
		}

		return new Response($response);
	}

	public function filter($type, Closure $callback)
	{
		$this->filters[$type][] = $callback;
	}

	// FIXME: Apply all filter types. Take care of arguments. And response handling..
	protected function applyFilters($type, array $arguments = array())
	{
		if (isset($this->filters[$type]))
		{
			foreach ($this->filters[$type] as $filter)
			{
				$filterResponse = call_user_func_array($filter, $arguments);

				if (!is_null($filterResponse))
				{
					return $filterResponse;
				}
			}
		}
	}

}