<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Tell header.php to use the admin template
define('PUN_ADMIN_CONSOLE', 1);

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/common_admin.php';


if (!$pun_user['is_admmod'])
	message($lang_common['No permission']);

// The plugin to load should be supplied via GET
$plugin = isset($_GET['plugin']) ? $_GET['plugin'] : '';
if (!preg_match('%^AM?P_(\w*?)\.php$%i', $plugin))
	message($lang_common['Bad request']);

// AP_ == Admins only, AMP_ == admins and moderators
$prefix = substr($plugin, 0, strpos($plugin, '_'));
if ($pun_user['g_moderator'] == '1' && $prefix == 'AP')
	message($lang_common['No permission']);

// Make sure the file actually exists
if (!file_exists(PUN_ROOT.'plugins/'.$plugin))
	message(sprintf($lang_admin_common['No plugin message'], $plugin));

// Construct REQUEST_URI if it isn't set
if (!isset($_SERVER['REQUEST_URI']))
	$_SERVER['REQUEST_URI'] = (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '').'?'.(isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '');

$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_common['Admin'], str_replace('_', ' ', substr($plugin, strpos($plugin, '_') + 1, -4)));
define('PUN_ACTIVE_PAGE', 'admin');
require PUN_ROOT.'header.php';

// Attempt to load the plugin. We don't use @ here to supress error messages,
// because if we did and a parse error occurred in the plugin, we would only
// get the "blank page of death"
include PUN_ROOT.'plugins/'.$plugin;
if (!defined('PUN_PLUGIN_LOADED'))
	message(sprintf($lang_admin_common['Plugin failed message'], $plugin));

// Output the clearer div
?>
	<div class="clearer"></div>
</div>
<?php

require PUN_ROOT.'footer.php';
