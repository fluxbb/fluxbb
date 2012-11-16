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

class Category extends Base
{

	protected $table = 'categories';


	public function forums()
	{
		return $this->hasMany('FluxBB\\Models\\Forum', 'cat_id');
			//->orderBy('disp_position', 'ASC');
	}


	public static function all($columns = array())
	{
		return static::getCacheStore()->remember('fluxbb.categories', 7 * 24 * 60, function() {
			$all = array();
			$categories = Category::orderBy('disp_position', 'ASC')
				->orderBy('id', 'ASC')
				->get();

			foreach ($categories as $category)
			{
				$all[$category->id] = $category;
			}
			return $all;
		});
	}

	public static function allForGroup($group_id)
	{
		$categories = static::all();

		$forums = Forum::allForGroup($group_id);
		
		/*usort($forums, function($forum1, $forum2) {
			if ($forum1->cat_id == $forum2->cat_id)
			{
				// Same category: forum's disp_position value decides
				return $forum1->disp_position - $forum2->disp_position;
			}
			else
			{
				// ...else the categories' disp_position values are compared
				return $categories[$forum1->cat_id]->disp_position - $categories[$forum2->cat_id]->disp_position;
			}
		});*/ // TODO: Handle sorting!
		
		// FIXME: Yuck!!!
		$forums_by_cat = array();
		foreach ($forums as $forum)
		{
			if (!isset($forums_by_cat[$forum->cat_id]))
			{
				$forums_by_cat[$forum->cat_id] = array(
					'category'	=> $categories[$forum->cat_id],
					'forums'	=> array(),
				);
			}

			$forums_by_cat[$forum->cat_id]['forums'][] = $forum;
		}

		return $forums_by_cat;
	}

}
