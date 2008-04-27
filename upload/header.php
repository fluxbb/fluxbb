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

// Send no-cache headers
header('Expires: Thu, 21 Jul 1977 07:30:00 GMT');	// When yours truly first set eyes on this world! :)
header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');		// For HTTP/1.0 compability

// Send the Content-type header in case the web server is setup to send something else
header('Content-type: text/html; charset=utf-8');

// Load the main template
if (substr(FORUM_PAGE, 0, 5) == 'admin')
{
	if (file_exists(FORUM_ROOT.'style/'.$forum_user['style'].'/admin.tpl'))
		$tpl_main = file_get_contents(FORUM_ROOT.'style/'.$forum_user['style'].'/admin.tpl');
	else
		$tpl_main = file_get_contents(FORUM_ROOT.'include/template/admin.tpl');
}
else if (FORUM_PAGE == 'help')
	$tpl_main = file_get_contents(FORUM_ROOT.'include/template/help.tpl');
else
{
	if (file_exists(FORUM_ROOT.'style/'.$forum_user['style'].'/main.tpl'))
		$tpl_main = file_get_contents(FORUM_ROOT.'style/'.$forum_user['style'].'/main.tpl');
	else
		$tpl_main = file_get_contents(FORUM_ROOT.'include/template/main.tpl');
}

($hook = get_hook('hd_template_loaded')) ? eval($hook) : null;


// START SUBST - <!-- forum_include "*" -->
while (preg_match('#<!-- ?forum_include "([^/\\\\]*?)" ?-->#', $tpl_main, $cur_include))
{
	if (!file_exists(FORUM_ROOT.'include/user/'.$cur_include[1]))
		error('Unable to process user include &lt;!-- forum_include "'.forum_htmlencode($cur_include[1]).'" --&gt; from template main.tpl. There is no such file in folder /include/user/', __FILE__, __LINE__);

	ob_start();
	include FORUM_ROOT.'include/user/'.$cur_include[1];
	$tpl_temp = ob_get_contents();
	$tpl_main = str_replace($cur_include[0], $tpl_temp, $tpl_main);
	ob_end_clean();
}
// END SUBST - <!-- forum_include "*" -->


// START SUBST - <!-- forum_local -->
$tpl_main = str_replace('<!-- forum_local -->', 'xml:lang="'.$lang_common['lang_identifier'].'" lang="'.$lang_common['lang_identifier'].'" dir="'.$lang_common['lang_direction'].'"', $tpl_main);
// END SUBST - <!-- forum_local -->


// START SUBST - <!-- forum_head -->
ob_start();

// Is this a page that we want search index spiders to index?
if (!defined('FORUM_ALLOW_INDEX'))
	echo '<meta name="ROBOTS" content="NOINDEX, FOLLOW" />'."\n";
else
	echo '<meta name="description" content="'.generate_crumbs(true).' '.$lang_common['Title separator'].' '.forum_htmlencode($forum_config['o_board_desc']).'" />'."\n";

// Should we output a MicroID? http://microid.org/
if (strpos(FORUM_PAGE, 'profile') === 0)
	echo '<meta name="microid" content="mailto+http:sha1:'.sha1(sha1('mailto:'.$user['email']).sha1(forum_link($forum_url['user'], $id))).'" />'."\n";

?>
<title><?php echo generate_crumbs(true) ?></title>
<?php

// Should we output feed links?
if (FORUM_PAGE == 'viewtopic')
{
	echo '<link rel="alternate" type="application/rss+xml" href="'.forum_link($forum_url['topic_rss'], $id).'" title="'.$lang_common['RSS Feed'].'" />'."\n";
	echo '<link rel="alternate" type="application/atom+xml" href="'.forum_link($forum_url['topic_atom'], $id).'" title="'.$lang_common['ATOM Feed'].'" />'."\n";
}
else if (FORUM_PAGE == 'viewforum')
{
	echo '<link rel="alternate" type="application/rss+xml" href="'.forum_link($forum_url['forum_rss'], $id).'" title="RSS" />'."\n";
	echo '<link rel="alternate" type="application/atom+xml" href="'.forum_link($forum_url['forum_atom'], $id).'" title="ATOM" />'."\n";
}

?>
<link rel="top" href="<?php echo $base_url ?>" title="<?php echo $lang_common['Forum index'] ?>" />
<?php

// If there are more than two breadcrumbs, add the "up" link (second last)
if (count($forum_page['crumbs']) > 2)
	echo '<link rel="up" href="'.$forum_page['crumbs'][count($forum_page['crumbs']) - 2][1].'" title="'.forum_htmlencode($forum_page['crumbs'][count($forum_page['crumbs']) - 2][0]).'" />'."\n";

// If there are other page navigation links (first, next, prev and last)
if (!empty($forum_page['nav']))
	echo implode("\n", $forum_page['nav'])."\n";

?>
<link rel="search" href="<?php echo forum_link($forum_url['search']) ?>" title="<?php echo $lang_common['Search'] ?>" />
<link rel="author" href="<?php echo forum_link($forum_url['users']) ?>" title="<?php echo $lang_common['User list'] ?>" />
<?php

// Include stylesheets
	require FORUM_ROOT.'style/'.$forum_user['style'].'/'.$forum_user['style'].'.php';

?>
<script type="text/javascript" src="<?php echo $base_url ?>/include/js/common.js"></script>

<?php

($hook = get_hook('hd_'.FORUM_PAGE.'_head')) ? eval($hook) : null;

($hook = get_hook('hd_head')) ? eval($hook) : null;

$tpl_temp = trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_head -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_head -->


// START SUBST - <!-- forum_page -->
$tpl_main = str_replace('<!-- forum_page -->', 'id="brd-'.FORUM_PAGE.'"', $tpl_main);
// END SUBST - <!-- forum_page -->


// START SUBST - <!-- forum_skip -->
$tpl_main = str_replace('<!-- forum_skip -->', '<div id="brd-access"><a href="#brd-main">'.$lang_common['Skip to content'].'</a></div>'."\n", $tpl_main);
// END SUBST - <!-- forum_skip -->

// START SUBST - <!-- forum_title -->
$tpl_main = str_replace('<!-- forum_title -->', '<div id="brd-title">'."\n\t".'<div><strong>'.forum_htmlencode($forum_config['o_board_title']).'</strong></div>'."\n".'</div>'."\n", $tpl_main);
// END SUBST - <!-- forum_title -->


// START SUBST - <!-- forum_desc -->
if ($forum_config['o_board_desc'] != '')
	$tpl_main = str_replace('<!-- forum_desc -->', '<div id="brd-desc">'."\n\t".'<p>'.forum_htmlencode($forum_config['o_board_desc']).'</p>'."\n".'</div>'."\n", $tpl_main);
// END SUBST - <!-- forum_desc -->


// START SUBST - <!-- forum_navlinks -->
$tpl_main = str_replace('<!-- forum_navlinks -->', '<div id="brd-navlinks">'."\n\t".'<ul>'."\n\t\t".generate_navlinks()."\n\t".'</ul>'."\n".'</div>'."\n", $tpl_main);
// END SUBST - <!-- forum_navlinks -->


// START SUBST - <!-- forum_crumbs -->
$tpl_main = str_replace('<!-- forum_crumbs -->', '<div id="brd-crumbs-head">'."\n\t".'<p class="crumbs">'.generate_crumbs(false).'</p>'."\n".'</div>'."\n", $tpl_main);
// END SUBST - <!-- forum_crumbs -->


// START SUBST - <!-- forum_visit -->
ob_start();

if ($forum_user['is_guest'])
{
	$visit_msg = array(
		'<span id="vs-logged">'.$lang_common['Not logged in'].'</span>',
		'<span id="vs-message">'.$lang_common['Login nag'].'</span>'
	);
}
else
{
	$visit_msg = array(
		'<span id="vs-logged">'.sprintf($lang_common['Logged in as'], '<strong>'.forum_htmlencode($forum_user['username']).'</strong>').'</span>',
		'<span id="vs-message">'.sprintf($lang_common['Last visit'], '<strong>'.format_time($forum_user['last_visit']).'</strong>').'</span>'
	);

	$visit_links = array();
	if ($forum_user['g_search'] == '1')
		$visit_links[] = '<li id="vs-searchnew"><a href="'.forum_link($forum_url['search_new']).'" title="'.$lang_common['New posts info'].'">'.$lang_common['New posts'].'</a></li>';

	$visit_links[] = '<li id="vs-markread"><a href="'.forum_link($forum_url['mark_read'], generate_form_token('markread'.$forum_user['id'])).'">'.$lang_common['Mark all as read'].'</a></li>';

	if ($forum_user['is_admmod'])
	{
		$query = array(
			'SELECT'	=> 'COUNT(r.id)',
			'FROM'		=> 'reports AS r',
			'WHERE'		=> 'r.zapped IS NULL',
		);

		($hook = get_hook('hd_qr_get_unread_reports_count')) ? eval($hook) : null;
		$result_header = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		if ($forum_db->result($result_header))
			$visit_links[] = '<li id="vs-reports"><a href="'.forum_link($forum_url['admin_reports']).'"><strong>'.$lang_common['New reports'].'</strong></a></li>';
	}
}

($hook = get_hook('hd_visit')) ? eval($hook) : null;

?>
<div id="brd-visit">
<?php if (!$forum_user['is_guest']): ?>	<ul>
		<?php echo implode("\n\t\t", $visit_links)."\n" ?>
	</ul>
<?php endif; ?>	<p>
		<?php echo implode("\n\t\t", $visit_msg)."\n" ?>
	</p>
</div>
<?php

$tpl_temp = ob_get_contents();
$tpl_main = str_replace('<!-- forum_visit -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_visit -->


// START SUBST - <!-- forum_alert -->
$alert_items = array();

if ($forum_user['g_id'] == FORUM_ADMIN)
{
	if ($forum_config['o_check_for_updates'] == '1')
	{
		if ($forum_updates['fail'])
			$alert_items[] = '<p id="updates-alert"'.(empty($alert_items) ? ' class="first-alert"' : '').'><strong>'.$lang_common['Updates'].'</strong> <span>'.$lang_common['Updates failed'].'</span></p>';
		else if (isset($forum_updates['version']) && isset($forum_updates['hotfix']))
			$alert_items[] = '<p id="updates-alert"'.(empty($alert_items) ? ' class="first-alert"' : '').'><strong>'.$lang_common['Updates'].'</strong> <span>'.sprintf($lang_common['Updates version n hf'], $forum_updates['version']).'</span></p>';
		else if (isset($forum_updates['version']))
			$alert_items[] = '<p id="updates-alert"'.(empty($alert_items) ? ' class="first-alert"' : '').'><strong>'.$lang_common['Updates'].'</strong> <span>'.sprintf($lang_common['Updates version'], $forum_updates['version']).'</span></p>';
		else if (isset($forum_updates['hotfix']))
			$alert_items[] = '<p id="updates-alert"'.(empty($alert_items) ? ' class="first-alert"' : '').'><strong>'.$lang_common['Updates'].'</strong> <span>'.$lang_common['Updates hf'].'</span></p>';
	}

	// Warn the admin that maintenance mode is enabled
	if ($forum_config['o_maintenance'] == '1')
		$alert_items[] = '<p id="maint-alert"'.(empty($alert_items) ? ' class="first-alert"' : '').'><strong>'.$lang_common['Maintenance mode'].'</strong> <span>'.$lang_common['Maintenance alert'].'</span></p>';

	// Warn the admin that the install script is accessible
	if (file_exists(FORUM_ROOT.'install.php'))
		$alert_items[] = '<p id="install-script-exists-alert"'.(empty($alert_items) ? ' class="first-alert"' : '').'><strong>'.$lang_common['Install script'].'</strong> <span>'.$lang_common['Install script alert'].'</span></p>';

	// Warn the admin that the database update script is accessible
	if (file_exists(FORUM_ROOT.'db_update.php'))
		$alert_items[] = '<p id="update-script-exists-alert"'.(empty($alert_items) ? ' class="first-alert"' : '').'><strong>'.$lang_common['Update script'].'</strong> <span>'.$lang_common['Update script alert'].'</span></p>';

	// Warn the admin that the script to disable maintenance mode is accessible
	if (file_exists(FORUM_ROOT.'turn_off_maintenance_mode.php'))
		$alert_items[] = '<p id="disable-maint-script-exists-alert"'.(empty($alert_items) ? ' class="first-alert"' : '').'><strong>'.$lang_common['Maint script'].'</strong> <span>'.$lang_common['Maint script alert'].'</span></p>';
}

($hook = get_hook('hd_alert')) ? eval($hook) : null;

if (!empty($alert_items))
{
	ob_start();

?>
<div id="brd-alert">
	<h1 class="warn"><strong><?php echo $lang_common['Attention'] ?></strong></h1>
	<div>
		<?php echo implode("\n\t\t", $alert_items)."\n" ?>
	</div>
</div>
<?php

	$tpl_temp = ob_get_contents();
	$tpl_main = str_replace('<!-- forum_alert -->', $tpl_temp, $tpl_main);
	ob_end_clean();
}
// END SUBST - <!-- forum_alert -->


// START SUBST - <!-- forum_announcement -->
if ($forum_config['o_announcement'] == '1')
	$tpl_main = str_replace('<!-- forum_announcement -->', '<div id="brd-announcement">'."\n\t".'<div class="userbox">'.($forum_config['o_announcement_heading'] != '' ? "\n\t\t".'<h1 class="msg-head">'.$forum_config['o_announcement_heading'].'</h1>' : '')."\n\t\t".$forum_config['o_announcement_message']."\n\t".'</div>'."\n".'</div>'."\n", $tpl_main);
// END SUBST - <!-- forum_announcement -->


// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('hd_end')) ? eval($hook) : null;

define('FORUM_HEADER', 1);
