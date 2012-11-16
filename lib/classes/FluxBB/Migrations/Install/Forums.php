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

class Forums
{

	public function up()
	{
		Schema::table('forums', function($table)
		{
			$table->create();

			$table->increments('id');
			$table->string('forum_name', 80);
			$table->text('forum_desc')->nullable();
			$table->string('redirect_url', 100)->nullable();
			$table->integer('num_topics')->unsigned()->default(0);
			$table->integer('num_posts')->unsigned()->default(0);
			$table->integer('last_post')->unsigned()->nullable();
			$table->integer('last_post_id')->unsigned()->nullable();
			$table->string('last_poster', 200)->nullable();
			$table->integer('sort_by')->unsigned()->default(0);
			$table->integer('disp_position')->default(0);
			$table->integer('cat_id')->unsigned();
		});
	}

	public function down()
	{
		Schema::drop('forums');
	}

}