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

namespace FluxBB\Models;

use Illuminate\Auth\UserInterface,
	FluxBB\Auth,
	Hash;

class User extends Base implements UserInterface
{

	protected $table = 'users';

	const GUEST = 1;


	public function group()
	{
		return $this->belongsTo('FluxBB\\Models\\Group');
	}

	public function bans()
	{
		return $this->hasMany('FluxBB\\Models\\Ban');
	}

	public function posts()
	{
		return $this->hasMany('FluxBB\\Models\\Post', 'poster_id');
	}

	public function sessions()
	{
		return $this->hasMany('FluxBB\\Models\\Session');
	}


	public static function current()
	{
		static $current = null;

		if (Auth::guest())
		{
			if (!isset($current))
			{
				$current = static::find(static::GUEST);
			}

			return $current;
		}

		// We already have the logged in user's object
		return Auth::user();
	}

	public function guest()
	{
		return $this->id == static::GUEST;
	}

	public function isMember()
	{
		return !$this->guest();
	}

	// TODO: Better name
	public function isAdmMod()
	{
		// TODO: Is this even necessary or is a better check for is_moderator() (that returns true for admins, too) better?
		return $this->isAdmin() || $this->isModerator();
	}

	public function isAdmin()
	{
		return $this->group_id == Group::ADMIN;
	}

	public function isModerator()
	{
		return $this->group->g_moderator == 1;
	}

	public function title()
	{
		static $ban_list;

		// If not already built in a previous call, build an array of lowercase banned usernames
		if (empty($ban_list))
		{
			$ban_list = array();

			// FIXME: Retrieve $bans (former $pun_bans)
			$bans = array();
			foreach ($bans as $cur_ban)
			{
				$ban_list[] = strtolower($cur_ban['username']);
			}
		}

		// If the user has a custom title
		if ($this->title != '')
		{
			return $this->title;
		}
		// If the user is banned
		else if (in_array(strtolower($this->username), $ban_list))
		{
			return trans('Banned');
		}
		// If the user group has a default user title
		else if ($this->group->g_user_title != '')
		{
			return $this->group->g_user_title;
		}
		// If the user is a guest
		else if ($this->guest())
		{
			return trans('Guest');
		}
		// If nothing else helps, we assign the default
		else
		{
			return trans('Member');
		}
	}

	public function getAvatarFile()
	{
		// TODO: We might want to cache this result
		$filetypes = array('jpg', 'gif', 'png');

		foreach ($filetypes as $cur_type)
		{
			// FIXME: Prepend base path for upload dir
			$path = '/'.$this->id.'.'.$cur_type;

			if (file_exists($path))
			{
				return $path;
			}
		}

		return '';
	}

	public function hasAvatar()
	{
		return (bool) $this->getAvatarFile();
	}

	public function hasSignature()
	{
		return !empty($this->signature);
	}

	public function signature()
	{
		// TODO: Actually parse this, but somewhere else (as that's presentation code)
		// see fluxbb\Post::message()
		return $this->signature;
	}

	public function isOnline()
	{
		return isset($this->sessions);
	}

	public function hasUrl()
	{
		return !empty($this->url);
	}

	public function hasLocation()
	{
		return !empty($this->location);
	}

	public function hasAdminNote()
	{
		return !empty($this->admin_note);
	}

	public function canViewUsers()
	{
		return $this->group->g_view_users == 1;
	}

	public function dispTopics()
	{
		return $this->disp_topics ?: Config::get('o_disp_topics_default');
	}

	public function dispPosts()
	{
		return $this->disp_posts ?: Config::get('o_disp_posts_default');
	}

	protected function setPassword($password)
	{
		return Hash::make($password);
		// TODO: Maybe reset some attributes like confirmation code here?
	}


	public function getAuthIdentifier()
	{
		return $this->getKey();
	}

	public function getAuthPassword()
	{
		return $this->password;
	}

}
