<?php

/**
 * Copyright (C) 2008-2011 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';


if ($pun_user['g_read_board'] == '0')
	message($lang_common['No view']);
else if ($pun_user['g_view_users'] == '0')
	message($lang_common['No permission']);

// Load the userlist.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/userlist.php';

// Load the search.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/search.php';


// Determine if we are allowed to view post counts
$show_post_count = ($pun_config['o_show_post_count'] == '1' || $pun_user['is_admmod']) ? true : false;

$username = isset($_GET['username']) && $pun_user['g_search_users'] == '1' ? pun_trim($_GET['username']) : '';
$show_group = isset($_GET['show_group']) ? intval($_GET['show_group']) : -1;
$sort_by = isset($_GET['sort_by']) && (in_array($_GET['sort_by'], array('username', 'registered')) || ($_GET['sort_by'] == 'num_posts' && $show_post_count)) ? $_GET['sort_by'] : 'username';
$sort_dir = isset($_GET['sort_dir']) && $_GET['sort_dir'] == 'DESC' ? 'DESC' : 'ASC';

// Create any SQL for the WHERE clause
$where_sql = array();
$where_params = array();

if (!empty($username))
{
	$where_sql[] = 'u.username LIKE :username';
	$where_params[':username'] = str_replace('*', '%', $username);
}

if ($show_group > -1)
{
	$where_sql[] = 'u.group_id = :group_id';
	$where_params[':group_id'] = $show_group;
}

// Fetch user count
$query = $db->select(array('num_users' => 'COUNT(u.id) AS num_users'), 'users AS u');
$query->where = 'u.id > 1 AND u.group_id != :group_unverified';

$params = array(':group_unverified' => PUN_UNVERIFIED);

if (!empty($where_sql))
	$query->where .= ' AND '.implode(' AND ', $where_sql);

if (!empty($where_params))
	$params = array_merge($params, $where_params);

$result = $query->run($params);
$num_users = $result[0]['num_users'];

unset ($result, $query, $params);

// Determine the user offset (based on $_GET['p'])
$num_pages = ceil($num_users / 50);

$p = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $num_pages) ? 1 : intval($_GET['p']);
$start_from = 50 * ($p - 1);

$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_common['User list']);
if ($pun_user['g_search_users'] == '1')
	$focus_element = array('userlist', 'username');

// Generate paging links
$paging_links = '<span class="pages-label">'.$lang_common['Pages'].' </span>'.paginate($num_pages, $p, 'userlist.php?username='.urlencode($username).'&amp;show_group='.$show_group.'&amp;sort_by='.$sort_by.'&amp;sort_dir='.$sort_dir);


define('PUN_ALLOW_INDEX', 1);
define('PUN_ACTIVE_PAGE', 'userlist');
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
<?php if ($pun_user['g_search_users'] == '1'): ?>						<label class="conl"><?php echo $lang_common['Username'] ?><br /><input type="text" name="username" value="<?php echo pun_htmlspecialchars($username) ?>" size="25" maxlength="25" /><br /></label>
<?php endif; ?>						<label class="conl"><?php echo $lang_ul['User group']."\n" ?>
						<br /><select name="show_group">
							<option value="-1"<?php if ($show_group == -1) echo ' selected="selected"' ?>><?php echo $lang_ul['All users'] ?></option>
<?php

$query = $db->select(array('g_id' => 'g.g_id', 'g_title' => 'g.g_title'), 'groups AS g');
$query->where = 'g.g_id != :group_guest';
$query->order = array('g_id' => 'g.g_id DESC');

$params = array(':group_guest' => PUN_GUEST);

$result = $query->run($params);
foreach ($result as $cur_group)
{
	if ($cur_group['g_id'] == $show_group)
		echo "\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
	else
		echo "\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
}

unset ($result, $query, $params);

?>
						</select>
						<br /></label>
						<label class="conl"><?php echo $lang_search['Sort by']."\n" ?>
						<br /><select name="sort_by">
							<option value="username"<?php if ($sort_by == 'username') echo ' selected="selected"' ?>><?php echo $lang_common['Username'] ?></option>
							<option value="registered"<?php if ($sort_by == 'registered') echo ' selected="selected"' ?>><?php echo $lang_common['Registered'] ?></option>
<?php if ($show_post_count): ?>							<option value="num_posts"<?php if ($sort_by == 'num_posts') echo ' selected="selected"' ?>><?php echo $lang_ul['No of posts'] ?></option>
<?php endif; ?>						</select>
						<br /></label>
						<label class="conl"><?php echo $lang_search['Sort order']."\n" ?>
						<br /><select name="sort_dir">
							<option value="ASC"<?php if ($sort_dir == 'ASC') echo ' selected="selected"' ?>><?php echo $lang_search['Ascending'] ?></option>
							<option value="DESC"<?php if ($sort_dir == 'DESC') echo ' selected="selected"' ?>><?php echo $lang_search['Descending'] ?></option>
						</select>
						<br /></label>
						<p class="clearb"><?php echo ($pun_user['g_search_users'] == '1' ? $lang_ul['User search info'].' ' : '').$lang_ul['User sort info']; ?></p>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="search" value="<?php echo $lang_common['Submit'] ?>" accesskey="s" /></p>
		</form>
	</div>
</div>

<div class="linkst">
	<div class="inbox">
		<p class="pagelink"><?php echo $paging_links ?></p>
		<div class="clearer"></div>
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
<?php if ($show_post_count): ?>					<th class="tc3" scope="col"><?php echo $lang_common['Posts'] ?></th>
<?php endif; ?>					<th class="tcr" scope="col"><?php echo $lang_common['Registered'] ?></th>
				</tr>
			</thead>
			<tbody>
<?php

// Retrieve a list of user IDs, LIMIT is (really) expensive so we only fetch the IDs here then later fetch the remaining data
$query = $db->select(array('id' => 'u.id'), 'users AS u');
$query->where = 'u.id > 1 AND u.group_id != :group_unverified';
$query->order = array('sort' => 'u.'.$sort_by.' '.$sort_dir, 'uid' => 'u.id ASC');
$query->limit = 50;
$query->offset = $start_from;

$params = array(':group_unverified' => PUN_UNVERIFIED);

if (!empty($where_sql))
	$query->where .= ' AND '.implode(' AND ', $where_sql);

if (!empty($where_params))
	$params = array_merge($params, $where_params);

$user_ids = $query->run($params);
unset ($query, $params);

if (!empty($user_ids))
{
	// Translate from a 3d array into 2d array: $user_ids[0]['id'] -> $user_ids[0]
	foreach ($user_ids as $key => $value)
		$user_ids[$key] = $value['id'];

	// Grab the users
	$query = $db->select(array('uid' => 'u.id', 'username' => 'u.username', 'title' => 'u.title', 'num_posts' => 'u.num_posts', 'registered' => 'u.registered', 'g_id' => 'g.g_id', 'g_user_title' => 'g.g_user_title'), 'users AS u');

	$query->InnerJoin('g', 'groups AS g', 'g.g_id = u.group_id');

	$query->where = 'u.id IN :uids';
	$query->order = array('sort' => 'u.'.$sort_by.' '.$sort_dir, 'uid' => 'u.id ASC');

	$params = array(':uids' => $user_ids);

	$result = $query->run($params);
	foreach ($result as $user_data)
	{
		$user_title_field = get_title($user_data);

?>
				<tr>
					<td class="tcl"><?php echo '<a href="profile.php?id='.$user_data['id'].'">'.pun_htmlspecialchars($user_data['username']).'</a>' ?></td>
					<td class="tc2"><?php echo $user_title_field ?></td>
<?php if ($show_post_count): ?>					<td class="tc3"><?php echo forum_number_format($user_data['num_posts']) ?></td>
<?php endif; ?>
					<td class="tcr"><?php echo format_time($user_data['registered'], true) ?></td>
				</tr>
<?php

	}

	unset ($result, $query, $params);
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
		<div class="clearer"></div>
	</div>
</div>
<?php

require PUN_ROOT.'footer.php';
