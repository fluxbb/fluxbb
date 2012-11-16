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

namespace FluxBB\Tasks;

use FluxBB\Models\Config,
	Laravel\Str;

class Update extends Base
{
	
	public function run($arguments = array())
	{
		$cur_version = $this->cur_version();
		$target_version = isset($arguments[0]) ? $arguments[0] : FLUXBB_VERSION;

		if (version_compare($cur_version, $target_version, '='))
		{
			$this->log('Already up-to-date.');
		}
		else
		{
			$this->migrate($cur_version, $target_version);

			$this->log('Updating database...');
			$this->update_version($target_version);
			$this->log('Done.');
		}
	}

	public function up($arguments = array())
	{
		$version = $this->cur_version();
		$migration = $arguments[0];

		$this->run_migration($version, $migration, 'up');
	}

	public function down($arguments = array())
	{
		$version = $this->cur_version();
		$migration = $arguments[0];

		$this->run_migration($version, $migration, 'down');
	}

	protected function migrate($from, $to)
	{
		$direction = version_compare($from, $to, '<') ? 'up' : 'down';

		$run_versions = array();

		$files = new FilesystemIterator($this->path());
		foreach ($files as $file)
		{
			$version = basename($file->getFileName());

			if ($this->version_between($version, $from, $to))
			{
				$run_versions[] = $version;
			}
		}

		// Sort the versions by name and then run their migrations in the correct order
		usort($run_versions, 'version_compare');
		if ($direction == 'down')
		{
			$run_versions = array_reverse($run_versions);
		}

		foreach ($run_versions as $run_version)
		{
			$this->{$direction.'_version'}($run_version);
		}
	}

	protected function version_between($version, $start, $end)
	{
		if (version_compare($start, $end, '>'))
		{
			$temp = $start;
			$start = $end;
			$end = $temp;
		}

		return version_compare($start, $version, '<') && version_compare($version, $end, '<=');
	}

	protected function up_version($version)
	{
		$this->log('Update to v'.$version.'...');

		$this->foreach_migration($version, 'up');
	}

	protected function down_version($version)
	{
		$this->log('Rollback from v'.$version.'...');

		$this->foreach_migration($version, 'down');
	}

	protected function foreach_migration($version, $method)
	{
		foreach (new FilesystemIterator($this->path().$version) as $file)
		{
			$cur_migration = basename($file->getFileName(), '.php');

			$this->run_migration($version, $cur_migration, $method);
		}
	}

	protected function run_migration($version, $migration, $method)
	{
		$file = $this->path().$version.DS.$migration.'.php';

		$this->log('Migrate '.$migration.'...');

		$class = 'FluxBB_Update_'.Str::classify($migration);
		include_once $file;

		$instance = new $class;
		$instance->$method();
	}

	protected function cur_version()
	{
		return Config::get('o_cur_version');
	}

	protected function update_version($new_version)
	{
		Config::set('o_cur_version', $new_version);
		Config::save();
	}

	protected function path()
	{
		return Bundle::path('fluxbb').'migrations'.DS.'update'.DS;
	}

}
