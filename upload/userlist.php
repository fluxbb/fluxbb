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


define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';


if ($pun_user['g_read_board'] == '0')
	message($lang_common['No view']);


// Load the userlist.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/userlist.php';

// Load the search.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/search.php';


// Determine if we are allowed to view post counts
$show_post_count = ($pun_config['o_show_post_count'] == '1' || $pun_user['g_id'] < PUN_GUEST) ? true : false;

$username = (isset($_GET['username']) && $pun_user['g_search_users'] == '1') ? pun_trim($_GET['username']) : '';
$show_group = (!isset($_GET['show_group']) || intval($_GET['show_group']) < -1 && intval($_GET['show_group']) > 2) ? -1 : intval($_GET['show_group']);
$sort_by = (!isset($_GET['sort_by']) || $_GET['sort_by'] != 'username' && $_GET['sort_by'] != 'registered' && ($_GET['sort_by'] != 'num_posts' || !$show_post_count)) ? 'username' : $_GET['sort_by'];
$sort_dir = (!isset($_GET['sort_dir']) || $_GET['sort_dir'] != 'ASC' && $_GET['sort_dir'] != 'DESC') ? 'ASC' : strtoupper($_GET['sort_dir']);


$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / '.$lang_common['User list'];
if ($pun_user['g_search_users'] == '1')
	$focus_element = array('userlist', 'username');

define('PUN_ALLOW_INDEX', 1);
require PUN_ROOT.'header.php';

?>
<div class="blockform">
	<h2><span><?php echo $lang_search['User search'] ?></span></h2>
	<div class="box">
	<form id="userlist" method="get" action="userlist.php">
		<div class="inform">
			<fieldset>
				<legend><?php echo $lang_ul['User find legend'] ?></legend>
				<div class="infldset">
<?php if ($pun_user['g_search_users'] == '1'): ?>					<label class="conl"><?php echo $lang_common['Username'] ?><br /><input type="text" name="username" value="<?php echo pun_htmlspecialchars($username) ?>" size="25" maxlength="25" /><br /></label>
<?php endif; ?>					<label class="conl"><?php echo $lang_ul['User group']."\n" ?>
					<br /><select name="show_group">
						<option value="-1"<?php if ($show_group == -1) echo ' selected="selected"' ?>><?php echo $lang_ul['All users'] ?></option>
<?php

$result = $db->query('SELECT g_id, g_title FROM '.$db->prefix.'groups WHERE g_id!='.PUN_GUEST.' ORDER BY g_id') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());

while ($cur_group = $db->fetch_assoc($result))
{
	if ($cur_group['g_id'] == $show_group)
		echo "\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
	else
		echo "\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
}

?>
					</select>
					<br /></label>
					<label class="conl"><?php echo $lang_search['Sort by']."\n" ?>
					<br /><select name="sort_by">
						<option value="username"<?php if ($sort_by == 'username') echo ' selected="selected"' ?>><?php echo $lang_common['Username'] ?></option>
						<option value="registered"<?php if ($sort_by == 'registered') echo ' selected="selected"' ?>><?php echo $lang_common['Registered'] ?></option>
<?php if ($show_post_count): ?>						<option value="num_posts"<?php if ($sort_by == 'num_posts') echo ' selected="selected"' ?>><?php echo $lang_ul['No of posts'] ?></option>
<?php endif; ?>					</select>
					<br /></label>
					<label class="conl"><?php echo $lang_search['Sort order']."\n" ?>
					<br /><select name="sort_dir">
						<option value="ASC"<?php if ($sort_dir == 'ASC') echo ' selected="selected"' ?>><?php echo $lang_search['Ascending'] ?></option>
						<option value="DESC"<?php if ($sort_dir == 'DESC') echo ' selected="selected"' ?>><?php echo $lang_search['Descending'] ?></option>
					</select>
					<br /></label>
					<p class="clearb"><?php echo $lang_ul['User search info'] ?></p>
				</div>
			</fieldset>
		</div>
		<p><input type="submit" name="search" value="<?php echo $lang_common['Submit'] ?>" accesskey="s" /></p>
	</form>
	</div>
</div>
<?php


// Create any SQL for the WHERE clause
$where_sql = array();
$like_command = ($db_type == 'pgsql') ? 'ILIKE' : 'LIKE';

if ($pun_user['g_search_users'] == '1' && $username != '')
	$where_sql[] = 'u.username '.$like_command.' \''.$db->escape(str_replace('*', '%', $username)).'\'';
if ($show_group > -1)
	$where_sql[] = 'u.group_id='.$show_group;

// Fetch user count
$result = $db->query('SELECT COUNT(id) FROM '.$db->prefix.'users AS u WHERE u.id>1'.(!empty($where_sql) ? ' AND '.implode(' AND ', $where_sql) : '')) or error('Unable to fetch user list count', __FILE__, __LINE__, $db->error());
$num_users = $db->result($result);


// Determine the user offset (based on $_GET['p'])
$num_pages = ceil($num_users / 50);

$p = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $num_pages) ? 1 : $_GET['p'];
$start_from = 50 * ($p - 1);

// Generate paging links
$paging_links = $lang_common['Pages'].': '.paginate($num_pages, $p, 'userlist.php?username='.urlencode($username).'&amp;show_group='.$show_group.'&amp;sort_by='.$sort_by.'&amp;sort_dir='.strtoupper($sort_dir));


?>
<div class="linkst">
	<div class="inbox">
		<p class="pagelink"><?php echo $paging_links ?></p>
	</div>
</div>

<div id="users1" class="blocktable">
	<h2><span><?php echo $lang_common['User list'] ?></span></h2>
	<div class="box">
		<div class="inbox">
		<table cellspacing="0">
		<thead>
			<tr>
				<th class="tcl" scope="col"><?php echo $lang_common['Username'] ?></th>
				<th class="tc2" scope="col"><?php echo $lang_common['Title'] ?></th>
<?php if ($show_post_count): ?>				<th class="tc3" scope="col"><?php echo $lang_common['Posts'] ?></th>
<?php endif; ?>				<th class="tcr" scope="col"><?php echo $lang_common['Registered'] ?></th>
			</tr>
		</thead>
		<tbody>
<?php

// Grab the users
$result = $db->query('SELECT u.id, u.username, u.title, u.num_posts, u.registered, g.g_id, g.g_user_title FROM '.$db->prefix.'users AS u LEFT JOIN '.$db->prefix.'groups AS g ON g.g_id=u.group_id WHERE u.id>1'.(!empty($where_sql) ? ' AND '.implode(' AND ', $where_sql) : '').' ORDER BY '.$sort_by.' '.$sort_dir.', u.id ASC LIMIT '.$start_from.', 50') or error('Unable to fetch user list', __FILE__, __LINE__, $db->error());
if ($db->num_rows($result))
{
	while ($user_data = $db->fetch_assoc($result))
	{
		$user_title_field = get_title($user_data);

?>
				<tr>
					<td class="tcl"><?php echo '<a href="profile.php?id='.$user_data['id'].'">'.pun_htmlspecialchars($user_data['username']).'</a>' ?></td>
					<td class="tc2"><?php echo $user_title_field ?></td>
<?php if ($show_post_count): ?>					<td class="tc3"><?php echo $user_data['num_posts'] ?></td>
<?php endif; ?>
					<td class="tcr"><?php echo format_time($user_data['registered'], true) ?></td>
				</tr>
<?php

	}
}
else
	echo "\t\t\t".'<tr>'."\n\t\t\t\t\t".'<td class="tcl" colspan="'.(($show_post_count) ? 4 : 3).'">'.$lang_search['No hits'].'</td></tr>'."\n";

?>
			</tbody>
			</table>
		</div>
	</div>
</div>

<div class="linksb">
	<div class="inbox">
		<p class="pagelink"><?php echo $paging_links ?></p>
	</div>
</div>
<?php

require PUN_ROOT.'footer.php';
