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

use FluxBB\Auth;

class Topic extends Base
{

	protected $table = 'topics';

	public function posts()
	{
		return $this->hasMany('FluxBB\\Models\\Post');
	}

	public function forum()
	{
		return $this->belongsTo('FluxBB\\Models\\Forum');
	}

	public function subscription()
	{
		return $this->hasOne('FluxBB\\Models\\TopicSubscription');
	//		->where('user_id', '=', User::current()->id);
	}

	public function numReplies()
	{
		return is_null($this->moved_to) ? $this->num_replies : '-';
	}

	public function numViews()
	{
		return is_null($this->moved_to) ? $this->num_views : '-';
	}

	public function isUserSubscribed()
	{
		return Auth::check() && !is_null($this->subscription);
	}

	public function wasMoved()
	{
		return !is_null($this->moved_to);
	}

	public function subscribe($subscribe = true)
	{
		// To subscribe or not to subscribe, that ...
		if (!Config::enabled('o_topic_subscriptions') || !Auth::check())
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
