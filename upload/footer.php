<?php
/***********************************************************************

  Copyright (C) 2002-2008  PunBB.org

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


// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

($hook = get_hook('ft_pun_main_end')) ? eval($hook) : null;

$tpl_temp = trim(ob_get_contents());
$tpl_main = str_replace('<!-- pun_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- pun_main -->


// START SUBST - <!-- pun_stats -->
if (PUN_PAGE == 'index')
{
	ob_start();

	// Collect some statistics from the database
	$query = array(
		'SELECT'	=> 'COUNT(u.id)-1',
		'FROM'		=> 'users AS u'
	);

	($hook = get_hook('ft_qr_get_user_count')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	$stats['total_users'] = $pun_db->result($result);

	$query = array(
		'SELECT'	=> 'u.id, u.username',
		'FROM'		=> 'users AS u',
		'ORDER BY'	=> 'u.registered DESC',
		'LIMIT'		=> '1'
	);

	($hook = get_hook('ft_qr_get_newest_user')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	$stats['last_user'] = $pun_db->fetch_assoc($result);

	$query = array(
		'SELECT'	=> 'SUM(f.num_topics), SUM(f.num_posts)',
		'FROM'		=> 'forums AS f'
	);

	($hook = get_hook('ft_qr_get_post_stats')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	list($stats['total_topics'], $stats['total_posts']) = $pun_db->fetch_row($result);

	$stats_list[] = '<li class="st-users"><span>'.$lang_index['No of users'].':</span> <strong>'. $stats['total_users'].'</strong></li>';
	$stats_list[] = '<li class="st-users"><span>'.$lang_index['Newest user'].':</span> <strong>'.($pun_user['g_view_users'] == '1' ? '<a href="'.pun_link($pun_url['user'], $stats['last_user']['id']).'">'.pun_htmlencode($stats['last_user']['username']).'</a>' : pun_htmlencode($stats['last_user']['username'])).'</strong></li>';
	$stats_list[] = '<li class="st-activity"><span>'.$lang_index['No of topics'].':</span> <strong>'.intval($stats['total_topics']).'</strong></li>';
	$stats_list[] = '<li class="st-activity"><span>'.$lang_index['No of posts'].':</span> <strong>'.intval($stats['total_posts']).'</strong></li>';

?>
<div id="pun-info" class="main">
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

	if ($pun_config['o_users_online'] == '1')
	{
		// Fetch users online info and generate strings for output
		$query = array(
			'SELECT'	=> 'o.user_id, o.ident',
			'FROM'		=> 'online AS o',
			'WHERE'		=> 'o.idle=0',
			'ORDER BY'	=> 'o.ident'
		);

		($hook = get_hook('ft_qr_get_online_info')) ? eval($hook) : null;
		$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
		$num_guests = 0;
		$users = array();

		while ($pun_user_online = $pun_db->fetch_assoc($result))
		{
			($hook = get_hook('ft_add_online_user_loop')) ? eval($hook) : null;

			if ($pun_user_online['user_id'] > 1)
				$users[] = ($pun_user['g_view_users'] == '1') ? '<a href="'.pun_link($pun_url['user'], $pun_user_online['user_id']).'">'.pun_htmlencode($pun_user_online['ident']).'</a>' : pun_htmlencode($pun_user_online['ident']);
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
	$tpl_main = str_replace('<!-- pun_stats -->', $tpl_temp, $tpl_main);
	ob_end_clean();
}
// END SUBST - <!-- pun_stats -->


// START SUBST - <!-- pun_about -->
ob_start();

?>
<div id="pun-about">
<?php

// Display the "Jump to" drop list
if ($pun_user['g_read_board'] == '1' && $pun_config['o_quickjump'] == '1')
{
	// Load cached quickjump
	if (file_exists(PUN_CACHE_DIR.'cache_quickjump_'.$pun_user['g_id'].'.php'))
		include PUN_CACHE_DIR.'cache_quickjump_'.$pun_user['g_id'].'.php';

	if (!defined('PUN_QJ_LOADED'))
	{
		require_once PUN_ROOT.'include/cache.php';
		generate_quickjump_cache($pun_user['g_id']);
		require PUN_CACHE_DIR.'cache_quickjump_'.$pun_user['g_id'].'.php';
	}
}


// End the transaction
$pun_db->end_transaction();

?>
	<p id="copyright">Powered by <strong><a href="http://punbb.org/">PunBB</a><?php if ($pun_config['o_show_version'] == '1') echo ' '.$pun_config['o_cur_version']; ?></strong></p>
<?php

($hook = get_hook('ft_about_info_extra')) ? eval($hook) : null;

// Display debug info (if enabled/defined)
if (defined('PUN_DEBUG'))
{
	// Calculate script generation time
	list($usec, $sec) = explode(' ', microtime());
	$time_diff = sprintf('%.3f', ((float)$usec + (float)$sec) - $pun_start);
	echo "\t".'<p id="querytime">[ Generated in '.$time_diff.' seconds, '.$pun_db->get_num_queries().' queries executed ]</p>'."\n";
}
echo '</div>'."\n";

$tpl_temp = trim(ob_get_contents());
$tpl_main = str_replace('<!-- pun_about -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- pun_about -->


// START SUBST - <!-- pun_debug -->
if (defined('PUN_SHOW_QUERIES'))
	$tpl_main = str_replace('<!-- pun_debug -->', get_saved_queries(), $tpl_main);
// END SUBST - <!-- pun_debug -->


// START SUBST - <!-- pun_include "*" -->
while (preg_match('#<!-- ?pun_include "([^/\\\\]*?)" ?-->#', $tpl_main, $cur_include))
{
	if (!file_exists(PUN_ROOT.'include/user/'.$cur_include[1]))
		error('Unable to process user include &lt;!-- pun_include "'.pun_htmlencode($cur_include[1]).'" --&gt; from template main.tpl. There is no such file in folder /include/user/', __FILE__, __LINE__);

	ob_start();
	include PUN_ROOT.'include/user/'.$cur_include[1];
	$tpl_temp = ob_get_contents();
	$tpl_main = str_replace($cur_include[0], $tpl_temp, $tpl_main);
	ob_end_clean();
}
// END SUBST - <!-- pun_include "*" -->


// Last call!
($hook = get_hook('ft_end')) ? eval($hook) : null;


// Close the db connection (and free up any result data)
$pun_db->close();

// Spit out the page
exit($tpl_main);
