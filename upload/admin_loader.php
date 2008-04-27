<?php
/***********************************************************************

  Copyright (C) 2002-2005  Rickard Andersson (rickard@punbb.org)

  This file is part of PunBB.

  PunBB is free software; you can redistribute it and/or modify it
  under the terms of the GNU General Public License as published
  by the Free Software Foundation; either version 2 of the License,
  or (at your option) any later version.

  PunBB is distributed in the hope that it will be useful, but
  WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston,
  MA  02111-1307  USA

************************************************************************/


// Tell header.php to use the admin template
define('PUN_ADMIN_CONSOLE', 1);

define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/common_admin.php';


if ($pun_user['g_id'] > PUN_MOD)
	message($lang_common['No permission']);


// The plugin to load should be supplied via GET
$plugin = isset($_GET['plugin']) ? $_GET['plugin'] : '';
if (!@preg_match('/^AM?P_(\w*?)\.php$/i', $plugin))
	message($lang_common['Bad request']);

// AP_ == Admins only, AMP_ == admins and moderators
$prefix = substr($plugin, 0, strpos($plugin, '_'));
if ($pun_user['g_id'] == PUN_MOD && $prefix == 'AP')
	message($lang_common['No permission']);

// Make sure the file actually exists
if (!file_exists(PUN_ROOT.'plugins/'.$plugin))
	message('There is no plugin called \''.$plugin.'\' in the plugin directory.');

// Construct REQUEST_URI if it isn't set
if (!isset($_SERVER['REQUEST_URI']))
	$_SERVER['REQUEST_URI'] = (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '').'?'.(isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '');

$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / Admin / '.$plugin;
require PUN_ROOT.'header.php';

// Attempt to load the plugin. We don't use @ here to supress error messages,
// because if we did and a parse error occurred in the plugin, we would only
// get the "blank page of death".
include PUN_ROOT.'plugins/'.$plugin;
if (!defined('PUN_PLUGIN_LOADED'))
	message('Loading of the plugin \''.$plugin.'\' failed.');

// Output the clearer div
?>
	<div class="clearer"></div>
</div>
<?php

require PUN_ROOT.'footer.php';
