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


if (!defined('PUN_ROOT'))
	define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';

($hook = get_hook('ul_start')) ? eval($hook) : null;

if ($pun_user['g_read_board'] == '0')
	message($lang_common['No view']);
else if ($pun_user['g_view_users'] == '0')
	message($lang_common['No permission']);

// Load the userlist.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/userlist.php';

// Load the search.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/search.php';


// Miscellaneous setup
$pun_page['show_post_count'] = ($pun_config['o_show_post_count'] == '1' || $pun_user['is_admmod']) ? true : false;
$pun_page['username'] = (isset($_GET['username']) && $_GET['username'] != '-' && $pun_user['g_search_users'] == '1') ? $_GET['username'] : '';
$pun_page['show_group'] = (!isset($_GET['show_group']) || intval($_GET['show_group']) < -1 && intval($_GET['show_group']) > 2) ? -1 : intval($_GET['show_group']);
$pun_page['sort_by'] = (!isset($_GET['sort_by']) || $_GET['sort_by'] != 'username' && $_GET['sort_by'] != 'registered' && ($_GET['sort_by'] != 'num_posts' || !$pun_page['show_post_count'])) ? 'username' : $_GET['sort_by'];
$pun_page['sort_dir'] = (!isset($_GET['sort_dir']) || $_GET['sort_dir'] != 'ASC' && $_GET['sort_dir'] != 'DESC') ? 'ASC' : strtoupper($_GET['sort_dir']);


// Create any SQL for the WHERE clause
$where_sql = array();
$like_command = ($db_type == 'pgsql') ? 'ILIKE' : 'LIKE';

if ($pun_user['g_search_users'] == '1' && $pun_page['username'] != '')
	$where_sql[] = 'u.username '.$like_command.' \''.$pun_db->escape(str_replace('*', '%', $pun_page['username'])).'\'';
if ($pun_page['show_group'] > -1)
	$where_sql[] = 'u.group_id='.$pun_page['show_group'];


// Fetch user count
$query = array(
	'SELECT'	=> 'COUNT(u.id)',
	'FROM'		=> 'users AS u',
	'WHERE'		=> 'u.id>1'
);

if (!empty($where_sql))
	$query['WHERE'] .= ' AND '.implode(' AND ', $where_sql);

($hook = get_hook('ul_qr_get_user_count')) ? eval($hook) : null;
$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
$pun_page['num_users'] = $pun_db->result($result);

// Determine the user offset (based on $_GET['p'])
$pun_page['num_pages'] = ceil($pun_page['num_users'] / 50);
$pun_page['page'] = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $pun_page['num_pages']) ? 1 : $_GET['p'];
$pun_page['start_from'] = 50 * ($pun_page['page'] - 1);
$pun_page['finish_at'] = min(($pun_page['start_from'] + 50), ($pun_page['num_users']));

// Generate paging links
$pun_page['page_post'] = '<div class="paging"><span class="pages">'.$lang_common['Pages'].'</span> '.paginate($pun_page['num_pages'], $pun_page['page'], $pun_url['users_browse'], $lang_common['Page separator'], array($pun_page['show_group'], $pun_page['sort_by'], strtoupper($pun_page['sort_dir']), ($pun_page['username'] != '') ? urlencode($pun_page['username']) : '-')).'</div>';

// Navigation links for header and page numbering for title/meta description
if ($pun_page['page'] < $pun_page['num_pages'])
{
	$pun_page['nav'][] = '<link rel="last" href="'.pun_sublink($pun_url['users_browse'], $pun_url['page'], $pun_page['num_pages'], array($pun_page['show_group'], $pun_page['sort_by'], strtoupper($pun_page['sort_dir']), ($pun_page['username'] != '') ? urlencode($pun_page['username']) : '-')).'" title="'.$lang_common['Page'].' '.$pun_page['num_pages'].'" />';
	$pun_page['nav'][] = '<link rel="next" href="'.pun_sublink($pun_url['users_browse'], $pun_url['page'], ($pun_page['page'] + 1), array($pun_page['show_group'], $pun_page['sort_by'], strtoupper($pun_page['sort_dir']), ($pun_page['username'] != '') ? urlencode($pun_page['username']) : '-')).'" title="'.$lang_common['Page'].' '.($pun_page['page'] + 1).'" />';
}
if ($pun_page['page'] > 1)
{
	$pun_page['nav'][] = '<link rel="prev" href="'.pun_sublink($pun_url['users_browse'], $pun_url['page'], ($pun_page['page'] - 1), array($pun_page['show_group'], $pun_page['sort_by'], strtoupper($pun_page['sort_dir']), ($pun_page['username'] != '') ? urlencode($pun_page['username']) : '-')).'" title="'.$lang_common['Page'].' '.($pun_page['page'] - 1).'" />';
	$pun_page['nav'][] = '<link rel="first" href="'.pun_link($pun_url['users_browse'], array($pun_page['show_group'], $pun_page['sort_by'], strtoupper($pun_page['sort_dir']), ($pun_page['username'] != '') ? urlencode($pun_page['username']) : '-')).'" title="'.$lang_common['Page'].' 1" />';
}

$pun_page['main_foot_options'] = array(
	'<a href="'.pun_link($pun_url['users']).'"><span>'.$lang_ul['Perform new search'].'</span></a>'
);

// Generate page information
if (($pun_user['g_search_users'] == '1' && $pun_page['username'] != '') || ($pun_page['show_group'] > -1))
	$pun_page['main_info'] = (($pun_page['num_pages'] > 1) ? '<span>'.sprintf($lang_common['Page number'], $pun_page['page'], $pun_page['num_pages']).' </span>'.sprintf($lang_common['Paged info'], $lang_ul['Users found'], $pun_page['start_from'] + 1, $pun_page['finish_at'], $pun_page['num_users']) : sprintf($lang_common['Page info'], $lang_ul['Users found'], $pun_page['num_users']));
else
	$pun_page['main_info'] = (($pun_page['num_pages'] > 1) ? '<span>'.sprintf($lang_common['Page number'], $pun_page['page'], $pun_page['num_pages']).' </span>'.sprintf($lang_common['Paged info'], $lang_ul['Users'], $pun_page['start_from'] + 1, $pun_page['finish_at'], $pun_page['num_users'], $pun_page['page']) : sprintf($lang_common['Page info'], $lang_ul['Users'], $pun_page['num_users']));

// Setup form
$pun_page['set_count'] = $pun_page['fld_count'] = 0;
$pun_page['form_action'] = $base_url.'/userlist.php';

// Setup breadcrumbs
$pun_page['crumbs'] = array(
	array($pun_config['o_board_title'], pun_link($pun_url['index'])), $lang_common['User list']
);

($hook = get_hook('ul_pre_header_load')) ? eval($hook) : null;

define('PUN_ALLOW_INDEX', 1);
define('PUN_PAGE', 'userlist');
require PUN_ROOT.'header.php';

?>
<div id="pun-main" class="main paged">

	<h1><span><?php echo end($pun_page['crumbs']) ?></span></h1>

	<div class="paged-head">
		<?php echo $pun_page['page_post']."\n" ?>
	</div>

	<div class="main-head">
		<h2><span><?php echo $pun_page['main_info'] ?></span></h2>
	</div>

	<div class="main-content frm">
		<form class="frm-form" id="afocus" method="get" accept-charset="utf-8" action="<?php echo $pun_page['form_action'] ?>">
			<fieldset class="frm-set set<?php echo ++$pun_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_ul['User find legend'] ?></strong></legend>
<?php if ($pun_user['g_search_users'] == '1'): ?>				<div class="frm-fld text">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_ul['Search for username'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $pun_page['fld_count'] ?>" name="username" value="<?php echo pun_htmlencode($pun_page['username']) ?>" size="35" maxlength="25" /></span><br />
						<span class="fld-help"><?php echo $lang_ul['Username help'] ?></span>
					</label>
				</div>
<?php endif; ?>				<div class="frm-fld select">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_ul['User group'] ?></span><br />
						<span class="fld-input"><select id="fld<?php echo $pun_page['fld_count'] ?>" name="show_group">
						<option value="-1"<?php if ($pun_page['show_group'] == -1) echo ' selected="selected"' ?>><?php echo $lang_ul['All users'] ?></option>
<?php

// Get the list of user groups (excluding the guest group)
$query = array(
	'SELECT'	=> 'g.g_id, g.g_title',
	'FROM'		=> 'groups AS g',
	'WHERE'		=> 'g.g_id!='.PUN_GUEST,
	'ORDER BY'	=> 'g.g_id'
);

($hook = get_hook('ul_qr_get_groups')) ? eval($hook) : null;
$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);

while ($cur_group = $pun_db->fetch_assoc($result))
{
	if ($cur_group['g_id'] == $pun_page['show_group'])
		echo "\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.pun_htmlencode($cur_group['g_title']).'</option>'."\n";
	else
		echo "\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.pun_htmlencode($cur_group['g_title']).'</option>'."\n";
}

?>
						</select></span>
					</label>
				</div>
				<div class="frm-fld select">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_search['Sort by'] ?></span><br />
						<span class="fld-input"><select id="fld<?php echo $pun_page['fld_count'] ?>" name="sort_by">
						<option value="username"<?php if ($pun_page['sort_by'] == 'username') echo ' selected="selected"' ?>><?php echo $lang_common['Username'] ?></option>
						<option value="registered"<?php if ($pun_page['sort_by'] == 'registered') echo ' selected="selected"' ?>><?php echo $lang_common['Registered'] ?></option>
<?php if ($pun_page['show_post_count']): ?>						<option value="num_posts"<?php if ($pun_page['sort_by'] == 'num_posts') echo ' selected="selected"' ?>><?php echo $lang_ul['No of posts'] ?></option>
<?php endif; ($hook = get_hook('ul_new_sort_by')) ? eval($hook) : null; ?>						</select></span>
					</label>
				</div>
				<fieldset class="frm-group">
					<legend><span><?php echo $lang_search['Sort order'] ?></span></legend>
					<div class="radbox frm-yesno"> <label for="fld<?php echo ++$pun_page['fld_count'] ?>"><input type="radio" id="fld<?php echo $pun_page['fld_count'] ?>" name="sort_dir" value="ASC"<?php if ($pun_page['sort_dir'] == 'ASC') echo ' checked="checked"' ?> /> <?php echo $lang_search['Ascending'] ?></label> <label for="fld<?php echo ++$pun_page['fld_count'] ?>"><input type="radio" id="fld<?php echo $pun_page['fld_count'] ?>" name="sort_dir" value="DESC"<?php if ($pun_page['sort_dir'] == 'DESC') echo ' checked="checked"' ?> /> <?php echo $lang_search['Descending'] ?></label></div>
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
	'ORDER BY'	=> $pun_page['sort_by'].' '.$pun_page['sort_dir'],
	'LIMIT'		=> $pun_page['start_from'].', 50'
);

if (!empty($where_sql))
	$query['WHERE'] .= ' AND '.implode(' AND ', $where_sql);

($hook = get_hook('ul_qr_get_users')) ? eval($hook) : null;
$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
$pun_page['item_count'] = 0;

if ($pun_db->num_rows($result))
{

?>
			<table cellspacing="0" summary="<?php echo $lang_ul['Table summary'] ?>">
				<thead>
					<tr>
<?php ($hook = get_hook('ul_table_header_begin')) ? eval($hook) : null; ?>
						<th class="tcl" scope="col"><?php echo $lang_common['Username'] ?></th>
<?php ($hook = get_hook('ul_table_header_after_username')) ? eval($hook) : null; ?>
						<th class="tc2" scope="col"><?php echo $lang_common['Title'] ?></th>
<?php if ($pun_page['show_post_count']): ?>						<th class="tc3" scope="col"><?php echo $lang_common['Posts'] ?></th>
<?php endif; ($hook = get_hook('ul_table_header_after_num_posts')) ? eval($hook) : null; ?>						<th class="tcr" scope="col"><?php echo $lang_common['Registered'] ?></th>
<?php ($hook = get_hook('ul_table_header_after_registered')) ? eval($hook) : null; ?>
					</tr>
				</thead>
				<tbody>
<?php

	while ($user_data = $pun_db->fetch_assoc($result))
	{
		++$pun_page['item_count'];

?>
					<tr class="<?php echo ($pun_page['item_count'] % 2 != 0) ? 'odd' : 'even' ?>">
<?php ($hook = get_hook('ul_table_contents_begin')) ? eval($hook) : null; ?>
						<td class="tcl"><a href="<?php echo pun_link($pun_url['user'], $user_data['id']) ?>"><?php echo pun_htmlencode($user_data['username']) ?></a></td>
<?php ($hook = get_hook('ul_table_contents_after_username')) ? eval($hook) : null; ?>
						<td class="tc2"><?php echo get_title($user_data) ?></td>
<?php if ($pun_page['show_post_count']): ?>						<td class="tc3"><?php echo $user_data['num_posts'] ?></td>
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
		<p class="h2"><strong><?php echo $pun_page['main_info'] ?></strong></p>
<?php if (!empty($pun_page['main_foot_options'])) echo "\t\t\t".'<p class="main-options">'.implode(' ', $pun_page['main_foot_options']).'</p>'."\n" ?>
	</div>

	<div class="paged-foot">
		<?php echo $pun_page['page_post']."\n" ?>
	</div>

</div>

<div id="pun-crumbs-foot">
	<p class="crumbs"><?php echo generate_crumbs(false) ?></p>
</div>
<?php

($hook = get_hook('ul_end')) ? eval($hook) : null;

require PUN_ROOT.'footer.php';
