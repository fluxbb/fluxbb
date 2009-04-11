<?php
/**
 * Outputs the header used by most forum pages.
 *
 * @copyright Copyright (C) 2008 FluxBB.org, based on code copyright (C) 2002-2008 PunBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package FluxBB
 */


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
		$tpl_path = FORUM_ROOT.'style/'.$forum_user['style'].'/admin.tpl';
	else
		$tpl_path = FORUM_ROOT.'include/template/admin.tpl';
}
else if (FORUM_PAGE == 'help')
{
	if (file_exists(FORUM_ROOT.'style/'.$forum_user['style'].'/help.tpl'))
		$tpl_path = FORUM_ROOT.'style/'.$forum_user['style'].'/help.tpl';
	else
		$tpl_path = FORUM_ROOT.'include/template/help.tpl';
}
else
{
	if (file_exists(FORUM_ROOT.'style/'.$forum_user['style'].'/main.tpl'))
		$tpl_path = FORUM_ROOT.'style/'.$forum_user['style'].'/main.tpl';
	else
		$tpl_path = FORUM_ROOT.'include/template/main.tpl';
}

($hook = get_hook('hd_pre_template_loaded')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

$tpl_main = file_get_contents($tpl_path);

($hook = get_hook('hd_template_loaded')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

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

// Is this a page that we want search index spiders to index?
if (!defined('FORUM_ALLOW_INDEX'))
	$forum_head['robots'] = '<meta name="ROBOTS" content="NOINDEX, FOLLOW" />';
else
	$forum_head['descriptions'] = '<meta name="description" content="'.generate_crumbs(true).' '.$lang_common['Title separator'].' '.forum_htmlencode($forum_config['o_board_desc']).'" />';

// Should we output a MicroID? http://microid.org/
if (strpos(FORUM_PAGE, 'profile') === 0)
	$forum_head['microid'] = '<meta name="microid" content="mailto+http:sha1:'.sha1(sha1('mailto:'.$user['email']).sha1(forum_link($forum_url['user'], $id))).'" />';

$forum_head['title'] = '<title>'.generate_crumbs(true).'</title>';

// Should we output feed links?
if (FORUM_PAGE == 'index')
{
	$forum_head['rss'] = '<link rel="alternate" type="application/rss+xml" href="'.forum_link($forum_url['index_rss']).'" title="RSS" />';
	$forum_head['atom'] = '<link rel="alternate" type="application/atom+xml" href="'.forum_link($forum_url['index_atom']).'" title="ATOM" />';
}
else if (FORUM_PAGE == 'viewforum')
{
	$forum_head['rss'] = '<link rel="alternate" type="application/rss+xml" href="'.forum_link($forum_url['forum_rss'], array($id, $cur_forum['sort_by'] == '1' ? 'posted' : 'last_post')).'" title="RSS" />';
	$forum_head['atom'] = '<link rel="alternate" type="application/atom+xml" href="'.forum_link($forum_url['forum_atom'], array($id, $cur_forum['sort_by'] == '1' ? 'posted' : 'last_post')).'" title="ATOM" />';
}
else if (FORUM_PAGE == 'viewtopic')
{
	$forum_head['rss'] = '<link rel="alternate" type="application/rss+xml" href="'.forum_link($forum_url['topic_rss'], $id).'" title="RSS" />';
	$forum_head['atom'] =  '<link rel="alternate" type="application/atom+xml" href="'.forum_link($forum_url['topic_atom'], $id).'" title="ATOM" />';
}


$forum_head['top'] = '<link rel="top" href="'.$base_url.'" title="'.$lang_common['Forum index'].'" />';

// If there are more than two breadcrumbs, add the "up" link (second last)
if (count($forum_page['crumbs']) > 2)
	$forum_head['up'] = '<link rel="up" href="'.$forum_page['crumbs'][count($forum_page['crumbs']) - 2][1].'" title="'.forum_htmlencode($forum_page['crumbs'][count($forum_page['crumbs']) - 2][0]).'" />';

// If there are other page navigation links (first, next, prev and last)
if (!empty($forum_page['nav']))
	$forum_head['nav'] = implode("\n", $forum_page['nav']);

$forum_head['search'] = '<link rel="search" href="'.forum_link($forum_url['search']).'" title="'.$lang_common['Search'].'" />';
$forum_head['author'] = '<link rel="author" href="'.forum_link($forum_url['users']).'" title="'.$lang_common['User list'].'" />';

ob_start();

// Include stylesheets
require FORUM_ROOT.'style/'.$forum_user['style'].'/'.$forum_user['style'].'.php';

$head_temp = forum_trim(ob_get_contents());
$num_temp = 0;

foreach (explode("\n", $head_temp) as $style_temp)
	$forum_head['style'.$num_temp++] = $style_temp;

ob_end_clean();

$forum_head['commonjs'] = '<script type="text/javascript" src="'.$base_url.'/include/js/common.js"></script>';

($hook = get_hook('hd_head')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

$tpl_main = str_replace('<!-- forum_head -->', implode("\n", $forum_head), $tpl_main);
unset($forum_head);

// END SUBST - <!-- forum_head -->


// START SUBST OF COMMON ELEMENTS
// Setup array of general elements
$gen_elements = array();

// Forum page id and classes
if (!defined('FORUM_PAGE_TYPE'))
{
	if (substr(FORUM_PAGE, 0, 5) == 'admin')
		define('FORUM_PAGE_TYPE', 'admin-page');
	else
	{
		if (!empty($forum_page['page_post']))
			define('FORUM_PAGE_TYPE', 'paged-page');
		else if (!empty($forum_page['main_menu']))
			define('FORUM_PAGE_TYPE', 'menu-page');
		else
			define('FORUM_PAGE_TYPE', 'basic-page');
	}
}

$gen_elements['<!-- forum_page -->'] = 'id="brd-'.FORUM_PAGE.'" class="brd-page '.FORUM_PAGE_TYPE.'"';

// Skip link
$gen_elements['<!-- forum_skip -->'] = '<p id="brd-access"><a href="#brd-main">'.$lang_common['Skip to content'].'</a></p>';

// Forum Title
$gen_elements['<!-- forum_title -->'] = '<p id="brd-title"><a href="'.forum_link($forum_url['index']).'">'.forum_htmlencode($forum_config['o_board_title']).'</a></p>';

// Forum Description
$gen_elements['<!-- forum_desc -->'] = ($forum_config['o_board_desc'] != '') ? '<p id="brd-desc">'.forum_htmlencode($forum_config['o_board_desc']).'</p>' : '';

// Main Navigation
$gen_elements['<!-- forum_navlinks -->'] = '<ul>'."\n\t\t".generate_navlinks()."\n\t".'</ul>';

// Announcement
$gen_elements['<!-- forum_announcement -->'] = ($forum_config['o_announcement'] == '1' && $forum_user['g_read_board'] == '1') ? '<div id="brd-announcement" class="gen-content">'."\n\t".'<div class="user-box">'.($forum_config['o_announcement_heading'] != '' ? "\n\t\t".'<h2 class="msg-head">'.$forum_config['o_announcement_heading'].'</h2>' : '')."\n\t\t".$forum_config['o_announcement_message']."\n\t".'</div>'."\n".'</div>'."\n" : '';

// Maintenance Warning
$gen_elements['<!-- forum_maint -->'] = ($forum_user['g_id'] == FORUM_ADMIN && $forum_config['o_maintenance'] == '1') ? '<p id="maint-alert" class="warn">'.sprintf($lang_common['Maintenance warning'], '<a href="'.forum_link($forum_url['admin_settings_maintenance']).'">'.$lang_common['Maintenance mode'].'</a>').'</p>' : '';

($hook = get_hook('hd_gen_elements')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

$tpl_main = str_replace(array_keys($gen_elements), array_values($gen_elements), $tpl_main);
unset($gen_elements);

// END SUBST OF COMMON ELEMENTS


// START SUBST VISIT ELEMENTS
$visit_elements = array();

if ($forum_user['is_guest'])
	$visit_elements['<!-- forum_welcome -->'] = '<p id="welcome"><span>'.$lang_common['Not logged in'].'</span> <span>'.$lang_common['Login nag'].'</span></p>';
else
	$visit_elements['<!-- forum_welcome -->'] = '<p id="welcome"><span>'.sprintf($lang_common['Logged in as'], '<strong>'.forum_htmlencode($forum_user['username']).'</strong>').'</span> <span>'.sprintf($lang_common['Last visit'], format_time($forum_user['last_visit'])).'</span></p>';

if ($forum_user['g_read_board'] == '1' && $forum_user['g_search'] == '1')
{
	$visit_links = array();

	if (!$forum_user['is_guest'])
		$visit_links['newposts'] = '<span id="visit-new"'.(empty($visit_links) ? ' class="item1"' : '').'><a href="'.forum_link($forum_url['search_new']).'" title="'.$lang_common['New posts title'].'">'.$lang_common['New posts'].'</a></span>';

	$visit_links['recent'] = '<span id="visit-recent"'.(empty($visit_links) ? ' class="item1"' : '').'><a href="'.forum_link($forum_url['search_recent']).'" title="'.$lang_common['Active topics title'].'">'.$lang_common['Active topics'].'</a></span>';
	$visit_links['unanswered'] = '<span id="visit-unanswered"'.(empty($visit_links) ? ' class="item1"' : '').'><a href="'.forum_link($forum_url['search_unanswered']).'" title="'.$lang_common['Unanswered topics title'].'">'.$lang_common['Unanswered topics'].'</a></span>';
}

$visit_elements['<!-- forum_visit -->'] = (!empty($visit_links)) ? '<p id="visit-links" class="options">'.implode(' ', $visit_links).'</p>' : '';

($hook = get_hook('hd_visit_elements')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

$tpl_main = str_replace(array_keys($visit_elements), array_values($visit_elements), $tpl_main);
unset($visit_elements);

// END SUBST VISIT ELEMENTS


// START SUBST - <!-- forum_admod -->
$admod_links = array();

// We only need to run this query for mods/admins if there will actually be reports to look at
if ($forum_user['is_admmod'] && $forum_config['o_report_method'] != 1)
{
	$query = array(
		'SELECT'	=> 'COUNT(r.id)',
		'FROM'		=> 'reports AS r',
		'WHERE'		=> 'r.zapped IS NULL',
	);

	($hook = get_hook('hd_qr_get_unread_reports_count')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result_header = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	if ($forum_db->result($result_header))
		$admod_links['reports'] = '<span id="reports"><a href="'.forum_link($forum_url['admin_reports']).'">'.$lang_common['New reports'].'</a></span>';
}

if ($forum_user['g_id'] == FORUM_ADMIN)
{
	$alert_items = array();

	// Warn the admin that maintenance mode is enabled
	if ($forum_config['o_maintenance'] == '1')
		$alert_items['maintenance'] = '<p id="maint-alert" class="warn">'.$lang_common['Maintenance alert'].'</p>';

	if ($forum_config['o_check_for_updates'] == '1')
	{
		if ($forum_updates['fail'])
			$alert_items['update_fail'] = '<p><strong>'.$lang_common['Updates'].'</strong> '.$lang_common['Updates failed'].'</p>';
		else if (isset($forum_updates['version']) && isset($forum_updates['hotfix']))
			$alert_items['update_version_hotfix'] = '<p><strong>'.$lang_common['Updates'].'</strong> '.sprintf($lang_common['Updates version n hf'], $forum_updates['version']).'</p>';
		else if (isset($forum_updates['version']))
			$alert_items['update_version'] = '<p><strong>'.$lang_common['Updates'].'</strong> '.sprintf($lang_common['Updates version'], $forum_updates['version']).'</p>';
		else if (isset($forum_updates['hotfix']))
			$alert_items['update_hotfix'] = '<p><strong>'.$lang_common['Updates'].'</strong> '.$lang_common['Updates hf'].'</p>';
	}

	// Warn the admin that their version of the database is newer than the version supported by the code
	if ($forum_config['o_database_revision'] > FORUM_DB_REVISION)
		$alert_items['newer_database'] = '<p><strong>'.$lang_common['Database mismatch'].'</strong> '.$lang_common['Database mismatch alert'].'</p>';

	// Warn the admin that the engines used in the database don't correspond with the chosen DB layer
	if (($db_type == 'mysql_innodb' || $db_type == 'mysqli_innodb') && $forum_config['o_database_engine'] != 'InnoDB')
			$alert_items['update_fail'] = '<p><strong>'.$lang_common['Database engine mismatch'].'</strong> '.sprintf($lang_common['Database engine mismatch alert'], 'MyISAM', 'InnoDB', forum_link('misc.php?admin_action=change_engine')).'</p>';
	else if (($db_type == 'mysql' || $db_type == 'mysqli') && $forum_config['o_database_engine'] != 'MyISAM')
			$alert_items['update_fail'] = '<p><strong>'.$lang_common['Database engine mismatch'].'</strong> '.sprintf($lang_common['Database engine mismatch alert'], 'InnoDB', 'MyISAM', forum_link('misc.php?admin_action=change_engine')).'</p>';

	if (!empty($alert_items))
		$admod_links['alert'] = '<span id="alert"><a href="'.forum_link($forum_url['admin_index']).'"><strong>'.$lang_common['New alerts'].'</strong></a></span>';

	($hook = get_hook('hd_alert')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
}

$tpl_main = str_replace('<!-- forum_admod -->', (!empty($admod_links)) ? '<p id="brd-admod">'.implode(' ', $admod_links).'</p>' : '', $tpl_main);

// END SUBST - <!-- forum_admod -->


// MAIN SECTION INTERFACE ELEMENT SUBSTITUTION
$main_elements = array();

// Top breadcrumbs
$main_elements['<!-- forum_crumbs_top -->'] = (FORUM_PAGE != 'index') ? '<div id="brd-crumbs-top" class="crumbs gen-content">'."\n\t".'<p>'.generate_crumbs(false).'</p>'."\n".'</div>' : '';

// Bottom breadcrumbs
$main_elements['<!-- forum_crumbs_end -->'] = (FORUM_PAGE != 'index') ? '<div id="brd-crumbs-end" class="crumbs gen-content">'."\n\t".'<p>'.generate_crumbs(false).'</p>'."\n".'</div>' : '';

// Main section heading
$main_elements['<!-- forum_main_head -->'] =  "\t".'<div class="main-head">'."\n\t".'<h1 class="hn"><span>'.((isset($forum_page['main_head'])) ? $forum_page['main_head'] : forum_htmlencode(end($forum_page['crumbs']))).(isset($forum_page['main_head_pages']) ? ' <small>'.$forum_page['main_head_pages'].'</small>' : '').'</span></h1>'."\n".'</div>';

// Top pagination and post links
$main_elements['<!-- forum_main_pagepost_top -->'] = (!empty($forum_page['page_post'])) ? '<div id="brd-pagepost-top" class="main-pagepost gen-content">'."\n\t".implode("\n\t", $forum_page['page_post'])."\n".'</div>' : '';

// Bottom pagination and postlink
$main_elements['<!-- forum_main_pagepost_end -->'] = (!empty($forum_page['page_post'])) ? '<div id="brd-pagepost-end" class="main-pagepost gen-content">'."\n\t".implode("\n\t", $forum_page['page_post'])."\n".'</div>' : '';

// Main section menu e.g. profile menu
$main_elements['<!-- forum_main_menu -->'] = (!empty($forum_page['main_menu'])) ? '<div class="main-menu gen-content">'."\n\t".'<ul>'."\n\t\t".implode("\n\t\t", $forum_page['main_menu'])."\n\t".'</ul>'."\n".'</div>' : '';

// Main section menu e.g. profile menu
if (substr(FORUM_PAGE, 0, 5) == 'admin' && FORUM_PAGE_TYPE != 'paged')
{
	$main_elements['<!-- forum_admin_menu -->'] = '<div class="admin-menu gen-content">'."\n\t".'<ul>'."\n\t\t".generate_admin_menu(false)."\n\t".'</ul>'."\n".'</div>';

	$forum_page['admin_sub'] = generate_admin_menu(true);
		$main_elements['<!-- forum_admin_submenu -->'] = ($forum_page['admin_sub'] != '') ? '<div class="admin-submenu gen-content">'."\n\t".'<ul>'."\n\t\t".$forum_page['admin_sub']."\n\t".'</ul>'."\n".'</div>' : '';
}

// Main section options bar
$main_elements['<!-- forum_main_options -->'] = (!empty($forum_page['main_options'])) ? '<div class="main-options gen-content">'."\n\t\t".'<h2 class="hn"><span>'.$forum_page['main_options_head'].'</span></h2>'."\n\t\t".'<p class="options">'.implode(' ', $forum_page['main_options']).'</p>'."\n\t".'</div>' : '';

($hook = get_hook('hd_main_elements')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

$tpl_main = str_replace(array_keys($main_elements),  array_values($main_elements), $tpl_main);
unset($main_elements);

// END MAIN SECTION INTERFACE ELEMENT SUBSTITUTION


($hook = get_hook('hd_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

if (!defined('FORUM_HEADER'))
	define('FORUM_HEADER', 1);
