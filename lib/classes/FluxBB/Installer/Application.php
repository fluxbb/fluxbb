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

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class Application extends \FluxBB\Application
{

	protected $step = '';

	protected $validation;

	protected $beforeFilters = array();

	protected $afterFilters = array();


	protected function prepareResponse($response)
	{
		if (!($response instanceof Response))
		{
			$response = new Response($response);
		}

		$this->runAfterFilters($this['request'], $response);

		return $response;
	}

	protected function dispatch(Request $request)
	{
		$this->runBeforeFilters($this['request']);

		$method = strtolower($request->getMethod());
		$this->step = $request->query('step', 'start');

		$action = $method.'_'.$this->step;

		return $this->$action();
	}

	public function before(Closure $callback)
	{
		$this->beforeFilters[] = $callback;
	}

	public function after(Closure $callback)
	{
		$this->afterFilters[] = $callback;
	}

	public function close(Closure $callback)
	{
		$this->afterFilters[] = $callback;
	}

	protected function runBeforeFilters(Request $request)
	{
		foreach ($this->beforeFilters as $filter)
		{
			call_user_func($filter, $request);
		}
	}

	protected function runAfterFilters(Request $request, Response $response)
	{
		foreach ($this->afterFilters as $filter)
		{
			call_user_func($filter, $request, $response);
		}
	}

	public function get_start()
	{
		return $this['view']->make('install.start');
	}

	public function post_start()
	{
		$rules = array(
			// TODO: Verify language being valid
			'language'	=> 'Required',
		);

		// TODO: Set bundle (for localization)
		if (!$this->validate($rules))
		{
			return $this->redirectBack();
		}

		$this->remember('language', $this->getInput('language'));

		return $this->redirectTo('database');
	}

	public function get_database()
	{
		return $this['view']->make('install.database');
	}

	public function post_database()
	{
		$rules = array(
			'db_host'	=> 'Required',
			'db_name'	=> 'Required',
			'db_user'	=> 'Required',
		);

		if (!$this->validate($rules))
		{
			return $this->redirectBack();
		}

		$db_conf = array(
			'driver'	=> 'mysql', // FIXME
			'host'		=> $this->getInput('db_host'),
			'database'	=> $this->getInput('db_name'),
			'username'	=> $this->getInput('db_user'),
			'password'	=> $this->getInput('db_pass'),
			'charset'	=> 'utf8',
			'collation'	=> 'utf8_unicode_ci',
			'prefix'	=> '',
		);

		$this->remember('db_conf', $db_conf);

		return $this->redirectTo('admin');
	}

	public function get_admin()
	{
		return $this['view']->make('install.admin');
	}

	public function post_admin()
	{
		$rules = array(
			'username'	=> 'Required|Between:2,25|UsernameNotGuest|NoIp|UsernameNotReserved|NoBBcode',
			'email'		=> 'Required|Email',
			'password'	=> 'Required|Min:4|Confirmed',
		);

		if (!$this->validate($rules))
		{
			return $this->redirectBack();
		}

		$user_info = array(
			'username'	=> $this->getInput('username'),
			'email'		=> $this->getInput('email'),
			'password'	=> $this->getInput('password'),
		);

		$this->remember('admin', $user_info);

		return $this->redirectTo('config');
	}

	public function get_config()
	{
		return $this['view']->make('install.config');
	}

	public function post_config()
	{
		$rules = array(
			'title'			=> 'Required',
			'description'	=> 'Required',
		);

		if (!$this->validate($rules))
		{
			return $this->redirectBack();
		}

		$board_info = array(
			'title'			=> $this->getInput('title'),
			'description'	=> $this->getInput('description'),
		);

		$this->remember('board', $board_info);

		return $this->redirectTo('run');
	}

	public function get_run()
	{
		return $this['view']->make('install.run');
	}

	public function post_run()
	{
		$installer = new Installer($this);

		$db = $this->retrieve('db_conf');

		// Tell the database to use this connection
		$config = $this['config'];
		$config['database.connection'] = $db;
		$this['config'] = $config;

		Model::setConnectionResolver($this['db']);

		$installer->writeDatabaseConfig($db);
		$installer->createDatabaseTables();
		$installer->createUserGroups();

		$board = $this->retrieve('board');
		$installer->setBoardInfo($board);

		$admin = $this->retrieve('admin');
		$installer->createAdminUser($admin);

		return $this->redirectTo('success');
		// TODO: Dump errors
	}

	public function get_success()
	{
		return $this['view']->make('install.success');
	}

	public function __call($method, $arguments)
	{
		// TODO: return 404
		echo '404';
		exit;
	}

	protected function getInput($key, $default = null)
	{
		return $this['request']->input($key, $default);
	}

	protected function validate(array $rules)
	{
		include_once $this['path'].'/lib/helpers/validators.php';

		$this->validation = $this['validator']->make($this['request']->all(), $rules);
		return $this->validation->passes();
	}

	protected function redirectTo($step)
	{
		return new RedirectResponse($this['request']->url().'?step='.$step);
	}

	protected function redirectBack()
	{
		return $this->redirectTo($this->step)
			->withInput()
			->withErrors($this->validation);
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