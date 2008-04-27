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

($hook = get_hook('ft_forum_main_end')) ? eval($hook) : null;

$tpl_temp = trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->


// START SUBST - <!-- forum_stats -->
if (FORUM_PAGE == 'index')
{
	ob_start();

	// Collect some statistics from the database
	$query = array(
		'SELECT'	=> 'COUNT(u.id)-1',
		'FROM'		=> 'users AS u'
	);

	($hook = get_hook('ft_qr_get_user_count')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$stats['total_users'] = $forum_db->result($result);

	$query = array(
		'SELECT'	=> 'u.id, u.username',
		'FROM'		=> 'users AS u',
		'ORDER BY'	=> 'u.registered DESC',
		'LIMIT'		=> '1'
	);

	($hook = get_hook('ft_qr_get_newest_user')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$stats['last_user'] = $forum_db->fetch_assoc($result);

	$query = array(
		'SELECT'	=> 'SUM(f.num_topics), SUM(f.num_posts)',
		'FROM'		=> 'forums AS f'
	);

	($hook = get_hook('ft_qr_get_post_stats')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	list($stats['total_topics'], $stats['total_posts']) = $forum_db->fetch_row($result);

	$stats_list[] = '<li class="st-users"><span>'.$lang_index['No of users'].':</span> <strong>'. $stats['total_users'].'</strong></li>';
	$stats_list[] = '<li class="st-users"><span>'.$lang_index['Newest user'].':</span> <strong>'.($forum_user['g_view_users'] == '1' ? '<a href="'.forum_link($forum_url['user'], $stats['last_user']['id']).'">'.forum_htmlencode($stats['last_user']['username']).'</a>' : forum_htmlencode($stats['last_user']['username'])).'</strong></li>';
	$stats_list[] = '<li class="st-activity"><span>'.$lang_index['No of topics'].':</span> <strong>'.intval($stats['total_topics']).'</strong></li>';
	$stats_list[] = '<li class="st-activity"><span>'.$lang_index['No of posts'].':</span> <strong>'.intval($stats['total_posts']).'</strong></li>';

?>
<div id="brd-info" class="main">
	<div class="main-head">
		<h2><span><?php echo $lang_index['Forum information'] ?></span></h2>
	</div>
	<div class="main-content">
		<div id="stats">
			<h3><?php echo $lang_index['Statistics'] ?></h3>
			<ul>
				<?php echo implode("\n\t\t\t\t", $stats_list)."\n" ?>
			</ul>
		</div>
<?php

	($hook = get_hook('ft_pre_users_online')) ? eval($hook) : null;

	if ($forum_config['o_users_online'] == '1')
	{
		// Fetch users online info and generate strings for output
		$query = array(
			'SELECT'	=> 'o.user_id, o.ident',
			'FROM'		=> 'online AS o',
			'WHERE'		=> 'o.idle=0',
			'ORDER BY'	=> 'o.ident'
		);

		($hook = get_hook('ft_qr_get_online_info')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		$num_guests = 0;
		$users = array();

		while ($forum_user_online = $forum_db->fetch_assoc($result))
		{
			($hook = get_hook('ft_add_online_user_loop')) ? eval($hook) : null;

			if ($forum_user_online['user_id'] > 1)
				$users[] = ($forum_user['g_view_users'] == '1') ? '<a href="'.forum_link($forum_url['user'], $forum_user_online['user_id']).'">'.forum_htmlencode($forum_user_online['ident']).'</a>' : forum_htmlencode($forum_user_online['ident']);
			else
				++$num_guests;
		}

?>
		<div id="onlinelist">
			<h3><?php printf($lang_index['Online'], $num_guests, count($users)) ?></h3>
<?php

		// If there are registered users logged in, list them
		if (count($users) > 0)
			echo "\t\t\t".'<p>'.implode(', ', $users).'</p>'."\n";

?>
		</div>
<?php

	}

	($hook = get_hook('ft_post_users_online')) ? eval($hook) : null;

?>
	</div>
</div>
<?php

	$tpl_temp = trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_stats -->', $tpl_temp, $tpl_main);
	ob_end_clean();
}
// END SUBST - <!-- forum_stats -->


// START SUBST - <!-- forum_about -->
ob_start();

?>
<div id="brd-about">
<?php

// Display the "Jump to" drop list
if ($forum_user['g_read_board'] == '1' && $forum_config['o_quickjump'] == '1')
{
	// Load cached quickjump
	if (file_exists(FORUM_CACHE_DIR.'cache_quickjump_'.$forum_user['g_id'].'.php'))
		include FORUM_CACHE_DIR.'cache_quickjump_'.$forum_user['g_id'].'.php';

	if (!defined('FORUM_QJ_LOADED'))
	{
		require_once FORUM_ROOT.'include/cache.php';
		generate_quickjump_cache($forum_user['g_id']);
		require FORUM_CACHE_DIR.'cache_quickjump_'.$forum_user['g_id'].'.php';
	}
}


// End the transaction
$forum_db->end_transaction();

?>
	<p id="copyright">Powered by <strong><a href="http://fluxbb.org/">FluxBB</a><?php if ($forum_config['o_show_version'] == '1') echo ' '.$forum_config['o_cur_version']; ?></strong></p>
<?php

($hook = get_hook('ft_about_info_extra')) ? eval($hook) : null;

// Display debug info (if enabled/defined)
if (defined('FORUM_DEBUG'))
{
	// Calculate script generation time
	list($usec, $sec) = explode(' ', microtime());
	$time_diff = sprintf('%.3f', ((float)$usec + (float)$sec) - $forum_start);
	echo "\t".'<p id="querytime">[ Generated in '.$time_diff.' seconds, '.$forum_db->get_num_queries().' queries executed ]</p>'."\n";
}
echo '</div>'."\n";

$tpl_temp = trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_about -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_about -->


// START SUBST - <!-- forum_debug -->
if (defined('FORUM_SHOW_QUERIES'))
	$tpl_main = str_replace('<!-- forum_debug -->', get_saved_queries(), $tpl_main);
// END SUBST - <!-- forum_debug -->


// Last call!
($hook = get_hook('ft_end')) ? eval($hook) : null;


// Close the db connection (and free up any result data)
$forum_db->close();

// Spit out the page
exit($tpl_main);
