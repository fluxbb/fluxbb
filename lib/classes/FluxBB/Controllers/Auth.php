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

namespace FluxBB\Controllers;

use FluxBB\Models\Config,
	FluxBB\Models\Group,
	FluxBB\Models\User,
	FluxBB\Routing\Controller;

class Auth extends Controller
{

	public function __construct()
	{
		//$this->filter('before', 'only_guests')->only(array('login', 'remember'));
		//$this->filter('before', 'only_members')->only('logout');
	}
	
	public function get_logout()
	{
		\FluxBB\Auth::logout();
		return $this->redirect('index')->with('message', t('login.message_logout'));
	}
	
	public function get_login()
	{
		return $this->view('auth.login');
	}

	public function post_login()
	{
		$login_data = array(
			'username'	=> $this->input('req_username'),
			'password'	=> $this->input('req_password'),
		);

		if (\FluxBB\Auth::attempt($login_data, $this->hasInput('save_pass')))
		{
			// Make sure last_visit data is properly updated
			//\Session::sweep();
			// TODO: Implement this!

			if ($this->app['session']->has('redirect_url'))
			{
				$redirectUrl = $this->app['session']->has('redirect_url');
			}
			else
			{
				$redirectUrl = route('index');
			}

			// FIXME: Redirect to $redirectUrl
			return $this->redirect('index')
				->with('message', 'You were successfully logged in.');
		}
		else
		{
			$errors = new \Illuminate\Validation\MessageBag;
			$errors->add('login', 'Invalid username / password combination.');

			return $this->redirect('login')
				->withInput($this->input())
				->with('errors', $errors);
		}
	}

	public function get_register()
	{
		return $this->view('auth.register');
	}

	public function post_register()
	{
        $rules = array(
			'user'		=> 'Required|Between:2,25|UsernameNotGuest|NoIp|UsernameNotReserved|NoBBcode|NotCensored|Unique:users,username|UsernameNotBanned',
		);
		
		// If email confirmation is enabled
		if (Config::enabled('o_regs_verify'))
		{
			$rules['email'] = 'Required|Email|Confirmed|Unique:users,email|EmailNotBanned';
		}
		else
		{
			$rules['password'] = 'Required|Min:4|Confirmed';
			$rules['email'] = 'Required|Email|Unique:users,email';
		}

		// Agree to forum rules
		if (Config::enabled('o_rules'))
		{
			$rules['rules'] = 'Accepted';
		}

		$validation = $this->validator($this->input(), $rules);
		if ($validation->fails())
		{
			return $this->redirect('register')
				->withInput($this->input())
				->with('errors', $validation->getMessages());
		}

		$user_data = array(
			'username'			=> $this->input('user'),
			'group_id'			=> Config::enabled('o_regs_verify') ? Group::UNVERIFIED : Config::get('o_default_user_group'),
			'password'			=> $this->input('password'),
			'email'				=> $this->input('email'),
			'email_setting'		=> Config::get('o_default_email_setting'),
			'timezone'			=> Config::get('o_default_timezone'),
			'dst'				=> Config::get('o_default_dst'),
			'language'			=> Config::get('o_default_lang'),
			'style'				=> Config::get('o_default_style'),
			'registered'		=> $this->app['request']->server('REQUEST_TIME', time()),
			'registration_ip'	=> $this->app['request']->getClientIp(),
			'last_visit'		=> $this->app['request']->server('REQUEST_TIME', time()),
		);
		$user = User::create($user_data);
	
		return $this->redirect('index')
			->with('message', t('register.reg_complete'));
	}

}
