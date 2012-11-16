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

use FluxBB\Database as DB;
use Illuminate\Cache\Store as CacheStore;

class ConfigRepository
{

	protected $table = 'config';

	protected $cache;


	protected $loaded = false;

	protected $data = array();

	protected $original = array();


	public function __construct(CacheStore $cache)
	{
		$this->cache = $cache;
	}

	protected function loadData()
	{
		if ($this->loaded)
		{
			return;
		}
		
		$this->data = $this->original = $this->cache->remember('fluxbb.config', 24 * 60, function()
		{
			$data = DB::table('config')->get();
			$cache = array();

			foreach ($data as $row)
			{
				$cache[$row->conf_name] = $row->conf_value;
			}

			return $cache;
		});

		$this->loaded = true;
	}

	public function get($key, $default = null)
	{
		$this->loadData();

		if (array_key_exists($key, $this->data))
		{
			return $this->data[$key];
		}

		return $default;
	}

	public function enabled($key)
	{
		return $this->get($key, 0) == 1;
	}

	public function disabled($key)
	{
		return $this->get($key, 0) == 0;
	}

	public function set($key, $value)
	{
		$this->loadData();

		$this->data[$key] = $value;
	}

	public function delete($key)
	{
		$this->loadData();

		unset($this->data[$key]);
	}

	public function save()
	{
		// New and changed keys
		$changed = array_diff_assoc($this->data, $this->original);

		$insert_values = array();
		foreach ($changed as $name => $value)
		{
			if (!array_key_exists($name, $this->original))
			{
				$insert_values[] = array(
					'conf_name'		=> $name,
					'conf_value'	=> $value,
				);

				unset($changed[$name]);
			}
		}

		if (!empty($insert_values))
		{
			DB::table('config')->insert($insert_values);
		}

		foreach ($changed as $name => $value)
		{
			DB::table('config')->where('conf_name', '=', $name)->update(array('conf_value' => $value));
		}

		// Deleted keys
		$deleted_keys = array_keys(array_diff_key($this->original, $this->data));
		if (!empty($deleted_keys))
		{
			DB::table('config')->whereIn('conf_name', $deleted_keys)->delete();
		}

		// No need to cache old values anymore
		$this->original = $this->data;

		// Delete the cache so that it will be regenerated on the next request
		$this->cache->forget('fluxbb.config');
	}

}
