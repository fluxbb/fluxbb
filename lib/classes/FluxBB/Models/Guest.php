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

use Auth,
	Hash;

class Guest extends User
{

	public function __construct($attributes = array())
	{
		parent::__construct($attributes);

		$this->group_id = 1;
	}

	public function isGuest()
	{
		return true;
	}

	public function isAdmin()
	{
		return false;
	}

	public function isModerator()
	{
		return false;
	}

	public function title()
	{
		return t('Guest');
	}

	public function getAvatarFile()
	{
		return '';
	}

	public function hasAvatar()
	{
		return false;
	}

	public function hasSignature()
	{
		return false;
	}

	public function isOnline()
	{
		return false;
	}

	public function hasUrl()
	{
		return false;
	}

	public function hasLocation()
	{
		return false;
	}

	public function hasAdminNote()
	{
		return false;
	}

	public function canViewUsers()
	{
		return $this->group->g_view_users == 1;
	}

	public function dispTopics()
	{
		return Config::get('o_disp_topics_default');
	}

	public function dispPosts()
	{
		return Config::get('o_disp_posts_default');
	}

}
