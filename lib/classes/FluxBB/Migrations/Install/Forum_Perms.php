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

namespace FluxBB\Migrations\Install;

use Schema;

class Forum_Perms
{

	public function up()
	{
		Schema::table('forum_perms', function($table)
		{
			$table->create();

			$table->integer('group_id')->unsigned();
			$table->integer('forum_id')->unsigned();
			$table->boolean('read_forum')->default(true);
			$table->boolean('post_replies')->default(true);
			$table->boolean('post_topics')->default(true);

			$table->primary(array('group_id', 'forum_id'));
		});
	}

	public function down()
	{
		Schema::drop('forum_perms');
	}

}