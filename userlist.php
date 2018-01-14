<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';


if ($pun_user['g_read_board'] == '0')
	message($lang_common['No view'], false, '403 Forbidden');
else if ($pun_user['g_view_users'] == '0')
	message($lang_common['No permission'], false, '403 Forbidden');

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
$like_command = ($db_type == 'pgsql') ? 'ILIKE' : 'LIKE';

if ($username != '')
	$where_sql[] = 'u.username '.$like_command.' \''.$db->escape(str_replace(array('*', '_',), array('%', '\\_'), $username)).'\'';
if ($show_group > -1)
	$where_sql[] = 'u.group_id='.$show_group;

// Fetch user count
$result = $db->query('SELECT COUNT(id) FROM '.$db->prefix.'users AS u WHERE u.id>1 AND u.group_id!='.PUN_UNVERIFIED.(!empty($where_sql) ? ' AND '.implode(' AND ', $where_sql) : '')) or error('Unable to fetch user list count', __FILE__, __LINE__, $db->error());
$num_users = $db->result($result);

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

$result = $db->query('SELECT g_id, g_title FROM '.$db->prefix.'groups WHERE g_id!='.PUN_GUEST.' ORDER BY g_id') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());

while ($cur_group = $db->fetch_assoc($result))
{
	if ($cur_group['g_id'] == $show_group)
		echo "\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
	else
		echo "\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
}

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
			<table>
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
$result = $db->query('SELECT u.id FROM '.$db->prefix.'users AS u WHERE u.id>1 AND u.group_id!='.PUN_UNVERIFIED.(!empty($where_sql) ? ' AND '.implode(' AND ', $where_sql) : '').' ORDER BY '.$sort_by.' '.$sort_dir.', u.id ASC LIMIT '.$start_from.', 50') or error('Unable to fetch user IDs', __FILE__, __LINE__, $db->error());

if ($db->num_rows($result))
{
	$user_ids = array();
	for ($i = 0;$cur_user_id = $db->result($result, $i);$i++)
		$user_ids[] = $cur_user_id;

	// Grab the users
	$result = $db->query('SELECT u.id, u.username, u.title, u.num_posts, u.registered, g.g_id, g.g_user_title FROM '.$db->prefix.'users AS u LEFT JOIN '.$db->prefix.'groups AS g ON g.g_id=u.group_id WHERE u.id IN('.implode(',', $user_ids).') ORDER BY '.$sort_by.' '.$sort_dir.', u.id ASC') or error('Unable to fetch user list', __FILE__, __LINE__, $db->error());

	while ($user_data = $db->fetch_assoc($result))
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
