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

use FluxBB\Schema;
use Illuminate\Database\Migrations\Migration;

class Topics extends Migration
{

	public function up()
	{
		Schema::table('topics', function($table)
		{
			$table->create();

			$table->increments('id');
			$table->string('poster', 200)->default('');
			$table->string('subject', 255)->default('');
			$table->integer('posted')->unsigned()->default(0);
			$table->integer('first_post_id')->unsigned()->default(0);
			$table->integer('last_post')->unsigned()->default(0);
			$table->integer('last_post_id')->unsigned()->default(0);
			$table->string('last_poster', 200)->nullable();
			$table->integer('num_views')->unsigned()->default(0);
			$table->integer('num_replies')->unsigned()->default(0);
			$table->boolean('closed')->default(false);
			$table->boolean('sticky')->default(false);
			$table->integer('moved_to')->unsigned()->nullable();
			$table->integer('forum_id')->unsigned()->default(0);

			$table->index('forum_id');
			$table->index('moved_to');
			$table->index('last_post');
			$table->index('first_post_id');
		});
	}

	public function down()
	{
		Schema::drop('topics');
	}

}