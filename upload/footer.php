<?php
/***********************************************************************

  Copyright (C) 2008  FluxBB.org

  Based on code copyright (C) 2002-2008  PunBB.org

  This file is part of FluxBB.

  FluxBB is free software; you can redistribute it and/or modify it
  under the terms of the GNU General Public License as published
  by the Free Software Foundation; either version 2 of the License,
  or (at your option) any later version.

  FluxBB is distributed in the hope that it will be useful, but
  WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston,
  MA  02111-1307  USA

************************************************************************/


// Make sure no one attempts to run this script "directly"
if (!defined('FORUM'))
	exit;

// START SUBST - <!-- forum_about -->
ob_start();

($hook = get_hook('ft_about_output_start')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
<div id="brd-about" class="gen-content">
<?php

// Display the "Jump to" drop list
if ($forum_user['g_read_board'] == '1' && $forum_config['o_quickjump'] == '1')
{
	// Load cached quickjump
	if (file_exists(FORUM_CACHE_DIR.'cache_quickjump_'.$forum_user['g_id'].'.php'))
		include FORUM_CACHE_DIR.'cache_quickjump_'.$forum_user['g_id'].'.php';

	if (!defined('FORUM_QJ_LOADED'))
	{
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			require FORUM_ROOT.'include/cache.php';

		generate_quickjump_cache($forum_user['g_id']);
		require FORUM_CACHE_DIR.'cache_quickjump_'.$forum_user['g_id'].'.php';
	}
}


// End the transaction
$forum_db->end_transaction();

?>
	<p id="copyright">Powered by <strong><a href="http://fluxbb.org/">FluxBB</a><?php if ($forum_config['o_show_version'] == '1') echo ' '.$forum_config['o_cur_version']; ?></strong></p>
</div>
<?php

($hook = get_hook('ft_about_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_about -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_about -->


// START SUBST - <!-- forum_debug -->
if (defined('FORUM_DEBUG') || defined('FORUM_SHOW_QUERIES'))
{
	ob_start();

	($hook = get_hook('ft_debug_output_start')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

	// Display debug info (if enabled/defined)
	if (defined('FORUM_DEBUG'))
	{
		// Calculate script generation time
		list($usec, $sec) = explode(' ', microtime());
		$time_diff = forum_number_format(((float)$usec + (float)$sec) - $forum_start, 3);
		echo '<p id="querytime">[ Generated in '.$time_diff.' seconds, '.forum_number_format($forum_db->get_num_queries()).' queries executed ]</p>'."\n";
	}

	if (defined('FORUM_SHOW_QUERIES'))
		echo get_saved_queries();

	($hook = get_hook('ft_debug_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

	$tpl_temp = forum_trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_debug -->', $tpl_temp, $tpl_main);
	ob_end_clean();
}
// END SUBST - <!-- forum_debug -->

// Last call!
($hook = get_hook('ft_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;


// Close the db connection (and free up any result data)
$forum_db->close();

// Spit out the page
exit($tpl_main);
