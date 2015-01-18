<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

$flux_addons = new flux_addon_manager();


class flux_addon_manager
{
	var $hooks = array();

	var $loaded = false;

	function load()
	{
		$this->loaded = true;

		foreach (glob(PUN_ROOT.'addons/*.php') as $addon_file)
		{
			$addon_name = 'addon_'.basename($addon_file, '.php');

			include $addon_file;
			$addon = new $addon_name;

			$addon->register($this);
		}
	}

	function bind($hook, $callback)
	{
		if (!isset($this->hooks[$hook]))
			$this->hooks[$hook] = array();

		$this->hooks[$hook][] = $callback;
	}

	function hook($name)
	{
		if (!$this->loaded)
			$this->load();

		$callbacks = isset($this->hooks[$name]) ? $this->hooks[$name] : array();

		// Execute every registered callback for this hook
		foreach ($callbacks as $callback)
		{
			list($addon, $method) = $callback;
			$addon->$method();
		}
	}
}


/**
 * Class flux_addon
 *
 * This class can be extended to provide addon functionality.
 * This way, subclasses do not have to worry about implementing functions for all possible hooks.
 */
class flux_addon
{
	function register($manager)
	{ }
}
