<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;


/**
 * Class flux_addon_manager
 *
 * This class is responsible for loading the addons and storing their hook listeners.
 */
class flux_addon_manager
{
	var $hooks = array();

	var $loaded = false;

	function load()
	{
		$this->loaded = true;

		$d = dir(PUN_ROOT.'addons');
		if (!$d) return;

		while (($addon_file = $d->read()) !== false)
		{
			if (!is_dir(PUN_ROOT.'addons/'.$addon_file) && preg_match('%(\w+)\.php$%', $addon_file))
			{
				$addon_name = 'addon_'.substr($addon_file, 0, -4);

				include PUN_ROOT.'addons/'.$addon_file;
				$addon = new $addon_name;

				$addon->register($this);
			}
		}
		$d->close();
	}

	function bind($hook, $callback)
	{
		if (!isset($this->hooks[$hook]))
			$this->hooks[$hook] = array();

		if (is_callable($callback))
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
 * Subclasses should implement the register method which will be called so that they have a chance to register possible
 * listeners for all hooks.
 */
class flux_addon
{
	function register($manager)
	{ }
}
