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

class Forum extends Base
{

	protected $table = 'forums';


	public function topics()
	{
		return $this->hasMany('FluxBB\\Models\\Topic');
	}

	public function subscriptions()
	{
		return $this->hasMany('FluxBB\\Models\\ForumSubscription');
	}

	public function subscription()
	{
		return $this->hasOne('FluxBB\\Models\\ForumSubscription')
			->where('user_id', '=', User::current()->id);
	}

	public function perms()
	{
		// TODO: has_one() with group condition?
		return $this->hasMany('FluxBB\\Models\\ForumPerms');
			/*->where('group_id', '=', User::current()->id)
			->whereNull('read_forum')
			->orWhere('read_forum', '=', '1');*/
	}


	public static function ids()
	{
		return static::getCacheStore()->remember('FluxBB.forum_ids', 7 * 24 * 60, function() {
			return Forum::lists('id');
		});
	}

	public static function allForGroup($group_id)
	{
		$ids = ForumPerms::forumsForGroup($group_id);

		return empty($ids) ? array() : static::whereIn('id', $ids)->get();
	}

	public function numTopics()
	{
		return $this->redirect_url == '' ? $this->num_topics : '-';
	}

	public function numPosts()
	{
		return $this->redirect_url == '' ? $this->num_posts : '-';
	}

	public function isUserSubscribed()
	{
		return \FluxBB\Auth::check() && !is_null($this->subscription);
	}

	public function moderators()
	{
		return $this->moderators != '' ? unserialize($this->moderators) : array();
	}

	public function isModerator()
	{
		return User::current()->isModerator() && array_key_exists(User::current()->username, $this->moderators());
	}

	public function isAdmMod()
	{
		return User::current()->isAdmin() || $this->isModerator();
	}

	public function sortColumn()
	{
		switch ($this->sort_by)
		{
			case 0:
				return 'last_post';
			case 1:
				return 'posted';
			case 2:
				return 'subject';
			default:
				return 'last_post';
		}
	}

	public function sortDirection()
	{
		switch ($this->sort_by)
		{
			case 0:
				return 'DESC';
			case 1:
				return 'DESC';
			case 2:
				return 'ASC';
			default:
				return 'DESC';
		}
	}

	public function subscribe($subscribe = true)
	{
		// To subscribe or not to subscribe, that ...
		if (!Config::enabled('o_forum_subscriptions') || !FluxBB\Auth::check())
		{
			return false;
		}

		if ($subscribe && !$this->isUserSubscribed())
		{
			$this->subscription()->insert(array('user_id' => User::current()->id));
		}
		else if (!$subscribe && $this->isUserSubscribed())
		{
			$this->subscription()->delete();
		}
	}

	public function unsubscribe()
	{
		return $this->subscribe(false);
	}
	
}
