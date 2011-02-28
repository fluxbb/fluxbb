<?php

/**
 * Copyright (C) 2008-2011 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;


//
// Load some information about the latest registered users
//
function generate_users_info_cache()
{
	global $db;

	$stats = array();

	$result = $db->query('SELECT COUNT(id)-1 FROM '.$db->prefix.'users WHERE group_id!='.PUN_UNVERIFIED) or error('Unable to fetch total user count', __FILE__, __LINE__, $db->error());
	$stats['total_users'] = $db->result($result);

	$result = $db->query('SELECT id, username FROM '.$db->prefix.'users WHERE group_id!='.PUN_UNVERIFIED.' ORDER BY registered DESC LIMIT 1') or error('Unable to fetch newest registered user', __FILE__, __LINE__, $db->error());
	$stats['last_user'] = $db->fetch_assoc($result);

	// Output users info as PHP code
	$fh = @fopen(FORUM_CACHE_DIR.'cache_users_info.php', 'wb');
	if (!$fh)
		error('Unable to write users info cache file to cache directory. Please make sure PHP has write access to the directory \''.pun_htmlspecialchars(FORUM_CACHE_DIR).'\'', __FILE__, __LINE__);

	fwrite($fh, '<?php'."\n\n".'define(\'PUN_USERS_INFO_LOADED\', 1);'."\n\n".'$stats = '.var_export($stats, true).';'."\n\n".'?>');

	fclose($fh);

	if (function_exists('apc_delete_file'))
		@apc_delete_file(FORUM_CACHE_DIR.'cache_users_info.php');
}


define('FORUM_CACHE_FUNCTIONS_LOADED', true);
