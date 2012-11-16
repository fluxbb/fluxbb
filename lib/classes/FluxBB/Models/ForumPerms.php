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

class ForumPerms extends Base
{

	protected $table = 'forum_perms';


	public function forum()
	{
		return $this->belongsTo('FluxBB\\Models\\Forum', 'forum_id');
	}

	public function group()
	{
		return $this->belongsTo('FluxBB\\Models\\Group', 'group_id');
	}


	public static function forumsForGroup($group_id)
	{
		return static::getCacheStore()->remember('fluxbb.forums_for_group.'.$group_id, 7 * 24 * 60, function() use($group_id) {
			$disallowed = ForumPerms::where('group_id', '=', $group_id)->where('read_forum', '=', 0)->lists('forum_id');
			$all_forum_ids = Forum::ids();
			return array_diff($all_forum_ids, $disallowed);
		});
	}

}
