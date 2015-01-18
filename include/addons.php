<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

$flux_addons = array();
$flux_addons_loaded = false;

function flux_addons_load()
{
	global $flux_addons, $flux_addons_loaded;

	foreach (glob(PUN_ROOT.'addons/*.php') as $addon_file)
	{
		$addon_name = 'addon_'.basename($addon_file, '.php');
		include $addon_file;

		$flux_addons[] = new $addon_name;
	}

	$flux_addons_loaded = true;
}

function flux_hook($name)
{
	global $flux_addons, $flux_addons_loaded;

	if (!$flux_addons_loaded)
		flux_addons_load();

	// Give every plugin the chance to run some code specific for this hook
	foreach ($flux_addons as $addon)
	{
		$hook = 'hook_'.$name;
		$addon->$hook();
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
	function hook_register_validate()
	{ }

	function hook_register_pre_submit()
	{ }
}
