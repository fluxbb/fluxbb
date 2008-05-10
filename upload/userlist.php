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


if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', './');
require FORUM_ROOT.'include/common.php';

($hook = get_hook('ul_start')) ? eval($hook) : null;

if ($forum_user['g_read_board'] == '0')
	message($lang_common['No view']);
else if ($forum_user['g_view_users'] == '0')
	message($lang_common['No permission']);

// Load the userlist.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/userlist.php';

// Load the search.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/search.php';


// Miscellaneous setup
$forum_page['show_post_count'] = ($forum_config['o_show_post_count'] == '1' || $forum_user['is_admmod']) ? true : false;
$forum_page['username'] = (isset($_GET['username']) && $_GET['username'] != '-' && $forum_user['g_search_users'] == '1') ? $_GET['username'] : '';
$forum_page['show_group'] = (!isset($_GET['show_group']) || intval($_GET['show_group']) < -1 && intval($_GET['show_group']) > 2) ? -1 : intval($_GET['show_group']);
$forum_page['sort_by'] = (!isset($_GET['sort_by']) || $_GET['sort_by'] != 'username' && $_GET['sort_by'] != 'registered' && ($_GET['sort_by'] != 'num_posts' || !$forum_page['show_post_count'])) ? 'username' : $_GET['sort_by'];
$forum_page['sort_dir'] = (!isset($_GET['sort_dir']) || $_GET['sort_dir'] != 'ASC' && $_GET['sort_dir'] != 'DESC') ? 'ASC' : strtoupper($_GET['sort_dir']);


// Create any SQL for the WHERE clause
$where_sql = array();
$like_command = ($db_type == 'pgsql') ? 'ILIKE' : 'LIKE';

if ($forum_user['g_search_users'] == '1' && $forum_page['username'] != '')
	$where_sql[] = 'u.username '.$like_command.' \''.$forum_db->escape(str_replace('*', '%', $forum_page['username'])).'\'';
if ($forum_page['show_group'] > -1)
	$where_sql[] = 'u.group_id='.$forum_page['show_group'];


// Fetch user count
$query = array(
	'SELECT'	=> 'COUNT(u.id)',
	'FROM'		=> 'users AS u',
	'WHERE'		=> 'u.id>1'
);

if (!empty($where_sql))
	$query['WHERE'] .= ' AND '.implode(' AND ', $where_sql);

($hook = get_hook('ul_qr_get_user_count')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
$forum_page['num_users'] = $forum_db->result($result);

// Determine the user offset (based on $_GET['p'])
$forum_page['num_pages'] = ceil($forum_page['num_users'] / 50);
$forum_page['page'] = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $forum_page['num_pages']) ? 1 : $_GET['p'];
$forum_page['start_from'] = 50 * ($forum_page['page'] - 1);
$forum_page['finish_at'] = min(($forum_page['start_from'] + 50), ($forum_page['num_users']));

// Generate paging links
$forum_page['page_post'] = '<div class="paging"><span class="pages">'.$lang_common['Pages'].'</span> '.paginate($forum_page['num_pages'], $forum_page['page'], $forum_url['users_browse'], $lang_common['Page separator'], array($forum_page['show_group'], $forum_page['sort_by'], strtoupper($forum_page['sort_dir']), ($forum_page['username'] != '') ? urlencode($forum_page['username']) : '-')).'</div>';

// Navigation links for header and page numbering for title/meta description
if ($forum_page['page'] < $forum_page['num_pages'])
{
	$forum_page['nav']['last'] = '<link rel="last" href="'.forum_sublink($forum_url['users_browse'], $forum_url['page'], $forum_page['num_pages'], array($forum_page['show_group'], $forum_page['sort_by'], strtoupper($forum_page['sort_dir']), ($forum_page['username'] != '') ? urlencode($forum_page['username']) : '-')).'" title="'.$lang_common['Page'].' '.$forum_page['num_pages'].'" />';
	$forum_page['nav']['next'] = '<link rel="next" href="'.forum_sublink($forum_url['users_browse'], $forum_url['page'], ($forum_page['page'] + 1), array($forum_page['show_group'], $forum_page['sort_by'], strtoupper($forum_page['sort_dir']), ($forum_page['username'] != '') ? urlencode($forum_page['username']) : '-')).'" title="'.$lang_common['Page'].' '.($forum_page['page'] + 1).'" />';
}
if ($forum_page['page'] > 1)
{
	$forum_page['nav']['prev'] = '<link rel="prev" href="'.forum_sublink($forum_url['users_browse'], $forum_url['page'], ($forum_page['page'] - 1), array($forum_page['show_group'], $forum_page['sort_by'], strtoupper($forum_page['sort_dir']), ($forum_page['username'] != '') ? urlencode($forum_page['username']) : '-')).'" title="'.$lang_common['Page'].' '.($forum_page['page'] - 1).'" />';
	$forum_page['nav']['first'] = '<link rel="first" href="'.forum_link($forum_url['users_browse'], array($forum_page['show_group'], $forum_page['sort_by'], strtoupper($forum_page['sort_dir']), ($forum_page['username'] != '') ? urlencode($forum_page['username']) : '-')).'" title="'.$lang_common['Page'].' 1" />';
}

$forum_page['main_foot_options'] = array(
	'<a href="'.forum_link($forum_url['users']).'"><span>'.$lang_ul['Perform new search'].'</span></a>'
);

// Generate page information
if (($forum_user['g_search_users'] == '1' && $forum_page['username'] != '') || ($forum_page['show_group'] > -1))
	$forum_page['main_info'] = (($forum_page['num_pages'] > 1) ? '<span>'.sprintf($lang_common['Page number'], $forum_page['page'], $forum_page['num_pages']).' </span>'.sprintf($lang_common['Paged info'], $lang_ul['Users found'], $forum_page['start_from'] + 1, $forum_page['finish_at'], $forum_page['num_users']) : sprintf($lang_common['Page info'], $lang_ul['Users found'], $forum_page['num_users']));
else
	$forum_page['main_info'] = (($forum_page['num_pages'] > 1) ? '<span>'.sprintf($lang_common['Page number'], $forum_page['page'], $forum_page['num_pages']).' </span>'.sprintf($lang_common['Paged info'], $lang_ul['Users'], $forum_page['start_from'] + 1, $forum_page['finish_at'], $forum_page['num_users'], $forum_page['page']) : sprintf($lang_common['Page info'], $lang_ul['Users'], $forum_page['num_users']));

// Setup form
$forum_page['set_count'] = $forum_page['fld_count'] = 0;
$forum_page['form_action'] = $base_url.'/userlist.php';

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])), $lang_common['User list']
);

($hook = get_hook('ul_pre_header_load')) ? eval($hook) : null;

define('FORUM_ALLOW_INDEX', 1);
define('FORUM_PAGE', 'userlist');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

?>
<div id="brd-main" class="main paged">

	<h1><span><?php echo end($forum_page['crumbs']) ?></span></h1>

	<div class="paged-head">
		<?php echo $forum_page['page_post']."\n" ?>
	</div>

	<div class="main-head">
		<h2><span><?php echo $forum_page['main_info'] ?></span></h2>
	</div>

	<div class="main-content frm">
		<form class="frm-form" id="afocus" method="get" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_ul['User find legend'] ?></strong></legend>
<?php if ($forum_user['g_search_users'] == '1'): ?>				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_ul['Search for username'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="username" value="<?php echo forum_htmlencode($forum_page['username']) ?>" size="35" maxlength="25" /></span><br />
						<span class="fld-help"><?php echo $lang_ul['Username help'] ?></span>
					</label>
				</div>
<?php endif; ?>				<div class="frm-fld select">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_ul['User group'] ?></span><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="show_group">
						<option value="-1"<?php if ($forum_page['show_group'] == -1) echo ' selected="selected"' ?>><?php echo $lang_ul['All users'] ?></option>
<?php

// Get the list of user groups (excluding the guest group)
$query = array(
	'SELECT'	=> 'g.g_id, g.g_title',
	'FROM'		=> 'groups AS g',
	'WHERE'		=> 'g.g_id!='.FORUM_GUEST,
	'ORDER BY'	=> 'g.g_id'
);

($hook = get_hook('ul_qr_get_groups')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

while ($cur_group = $forum_db->fetch_assoc($result))
{
	if ($cur_group['g_id'] == $forum_page['show_group'])
		echo "\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.forum_htmlencode($cur_group['g_title']).'</option>'."\n";
	else
		echo "\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.forum_htmlencode($cur_group['g_title']).'</option>'."\n";
}

?>
						</select></span>
					</label>
				</div>
				<div class="frm-fld select">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_search['Sort by'] ?></span><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="sort_by">
						<option value="username"<?php if ($forum_page['sort_by'] == 'username') echo ' selected="selected"' ?>><?php echo $lang_common['Username'] ?></option>
						<option value="registered"<?php if ($forum_page['sort_by'] == 'registered') echo ' selected="selected"' ?>><?php echo $lang_common['Registered'] ?></option>
<?php if ($forum_page['show_post_count']): ?>						<option value="num_posts"<?php if ($forum_page['sort_by'] == 'num_posts') echo ' selected="selected"' ?>><?php echo $lang_ul['No of posts'] ?></option>
<?php endif; ($hook = get_hook('ul_new_sort_by')) ? eval($hook) : null; ?>						</select></span>
					</label>
				</div>
				<fieldset class="frm-group">
					<legend><span><?php echo $lang_search['Sort order'] ?></span></legend>
					<div class="radbox frm-yesno"> <label for="fld<?php echo ++$forum_page['fld_count'] ?>"><input type="radio" id="fld<?php echo $forum_page['fld_count'] ?>" name="sort_dir" value="ASC"<?php if ($forum_page['sort_dir'] == 'ASC') echo ' checked="checked"' ?> /> <?php echo $lang_search['Ascending'] ?></label> <label for="fld<?php echo ++$forum_page['fld_count'] ?>"><input type="radio" id="fld<?php echo $forum_page['fld_count'] ?>" name="sort_dir" value="DESC"<?php if ($forum_page['sort_dir'] == 'DESC') echo ' checked="checked"' ?> /> <?php echo $lang_search['Descending'] ?></label></div>
				</fieldset>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="search" value="<?php echo $lang_search['Submit search'] ?>" accesskey="s" title="<?php echo $lang_common['Submit title'] ?>" /></span>
			</div>
		</form>
		<div class="frm-form">
<?php

// Grab the users
$query = array(
	'SELECT'	=> 'u.id, u.username, u.title, u.num_posts, u.registered, g.g_id, g.g_user_title',
	'FROM'		=> 'users AS u',
	'JOINS'		=> array(
		array(
			'LEFT JOIN'		=> 'groups AS g',
			'ON'			=> 'g.g_id=u.group_id'
		)
	),
	'WHERE'		=> 'u.id>1',
	'ORDER BY'	=> $forum_page['sort_by'].' '.$forum_page['sort_dir'].', u.id ASC',
	'LIMIT'		=> $forum_page['start_from'].', 50'
);

if (!empty($where_sql))
	$query['WHERE'] .= ' AND '.implode(' AND ', $where_sql);

($hook = get_hook('ul_qr_get_users')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
$forum_page['item_count'] = 0;

if ($forum_db->num_rows($result))
{

?>
			<table cellspacing="0" summary="<?php echo $lang_ul['Table summary'] ?>">
				<thead>
					<tr>
<?php ($hook = get_hook('ul_table_header_begin')) ? eval($hook) : null; ?>
						<th class="tcl" scope="col"><?php echo $lang_common['Username'] ?></th>
<?php ($hook = get_hook('ul_table_header_after_username')) ? eval($hook) : null; ?>
						<th class="tc2" scope="col"><?php echo $lang_common['Title'] ?></th>
<?php if ($forum_page['show_post_count']): ?>						<th class="tc3" scope="col"><?php echo $lang_common['Posts'] ?></th>
<?php endif; ($hook = get_hook('ul_table_header_after_num_posts')) ? eval($hook) : null; ?>						<th class="tcr" scope="col"><?php echo $lang_common['Registered'] ?></th>
<?php ($hook = get_hook('ul_table_header_after_registered')) ? eval($hook) : null; ?>
					</tr>
				</thead>
				<tbody>
<?php

	while ($user_data = $forum_db->fetch_assoc($result))
	{
		++$forum_page['item_count'];

?>
					<tr class="<?php echo ($forum_page['item_count'] % 2 != 0) ? 'odd' : 'even' ?>">
<?php ($hook = get_hook('ul_table_contents_begin')) ? eval($hook) : null; ?>
						<td class="tcl"><a href="<?php echo forum_link($forum_url['user'], $user_data['id']) ?>"><?php echo forum_htmlencode($user_data['username']) ?></a></td>
<?php ($hook = get_hook('ul_table_contents_after_username')) ? eval($hook) : null; ?>
						<td class="tc2"><?php echo get_title($user_data) ?></td>
<?php if ($forum_page['show_post_count']): ?>						<td class="tc3"><?php echo $user_data['num_posts'] ?></td>
<?php endif; ($hook = get_hook('ul_table_contents_after_num_posts')) ? eval($hook) : null; ?>						<td class="tcr"><?php echo format_time($user_data['registered'], true) ?></td>
<?php ($hook = get_hook('ul_table_contents_after_registered')) ? eval($hook) : null; ?>
					</tr>
<?php

	}

?>
				</tbody>
			</table>
<?php

}
else
{

?>
			<div class="frm-info">
				<p><strong><?php echo $lang_ul['No users found'] ?></strong></p>
			</div>
<?php

}

?>
		</div>
	</div>

	<div class="main-foot">
		<p class="h2"><strong><?php echo $forum_page['main_info'] ?></strong></p>
<?php if (!empty($forum_page['main_foot_options'])) echo "\t\t\t".'<p class="main-options">'.implode(' ', $forum_page['main_foot_options']).'</p>'."\n" ?>
	</div>

	<div class="paged-foot">
		<?php echo $forum_page['page_post']."\n" ?>
	</div>

</div>
<?php

($hook = get_hook('ul_end')) ? eval($hook) : null;

$tpl_temp = trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
