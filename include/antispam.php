<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

$flux_antispam = array();
$flux_antispam_loaded = false;

function flux_antispam_load()
{
	global $flux_antispam, $flux_antispam_loaded;

	foreach (glob(PUN_ROOT.'antispam/*.php') as $plugin_file)
	{
		$plugin_name = 'antispam_'.basename($plugin_file, '.php');
		include $plugin_file;

		$flux_antispam[] = new $plugin_name;
	}

	$flux_antispam_loaded = true;
}

function flux_antispam_hook($name)
{
	global $flux_antispam, $flux_antispam_loaded;

	if (!$flux_antispam_loaded)
		flux_antispam_load();

	// Give every plugin the chance to run some code specific for this hook
	foreach ($flux_antispam as $plugin)
		$plugin->$name();
}
