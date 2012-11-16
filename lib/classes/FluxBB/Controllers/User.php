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

use FluxBB\Models\User as u;
use FluxBB\Routing\Controller;

class User extends Controller
{

	public function get_profile($id, $action = 'essentials')
	{
		$user = u::find($id);

		if ($user === NULL)
		{
			return \Event::first('404');
		}
		
		else if (u::current()->id == $id || u::current()->isAdmin())
		{
			return $this->view('user.profile.'.$action)
				->with('user', $user);
		}
		
		else
		{
			return $this->view('user.profile.view')
				->with('user', $user);
		}
	
	}
	
	public function put_profile($id, $action = 'essentials')
	{
		$user = u::find($id);
		// TODO: Add validation. This can probably wait until we restructure the profile.
		if ($action == 'essentials')
		{
			$user->username = \Input::get('username', $user->username);
			$user->email = \Input::get('email', $user->email);
			$user->timezone = \Input::get('timezone', $user->timezone);
			$user->dst = \Input::get('dst', $user->dst);
			$user->time_format = \Input::get('time_format');
			$user->date_format = \Input::get('date_format');
			$user->admin_note = \Input::get('admin_note', $user->admin_note);
		}
		
		else if ($action == 'messaging')
		{
			$user->jabber = \Input::get('jabber');
			$user->icq = \Input::get('icq');
			$user->msn = \Input::get('msn');
			$user->aim = \Input::get('aim');
			$user->yahoo = \Input::get('yahoo');
		}
		
		else if ($action == 'personal')
		{
			$user->realname = \Input::get('realname');
			$user->title = \Input::get('title');
			$user->location = \Input::get('location');
			$user->url = \Input::get('url');
		}
		
		else if ($action == 'personality')
		{
			$user->signature = \Input::get('signature');
		}
		
		else if ($action == 'display')
		{
		//This will give an error if not everything is set -> need to set defaults in database!
			$user->style = \Input::get('style');
			$user->show_smilies = \Input::get('show_smilies');
			$user->show_sig = \Input::get('show_sig');
			$user->show_avatars = \Input::get('show_avatars');
			$user->show_img = \Input::get('show_img');
			$user->disp_topics = \Input::get('disp_topics', $user->disp_topics);
			$user->disp_posts = \Input::get('disp_posts', $user->disp_posts);
		}
		
		else //if action == privacy
		{
			//TODO
		}
		
		$user->save();
		return $this->view('user.profile.'.$action)
				->with('user', $user)
				->with('admin', u::current()->isAdmin());
	}

	public function get_list()
	{
		$users = u::paginate(20);

		return $this->view('user.list')
			->with('users', $users);
	}

}