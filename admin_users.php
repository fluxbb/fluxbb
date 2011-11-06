<?php

/**
 * Copyright (C) 2008-2011 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Tell header.php to use the admin template
define('PUN_ADMIN_CONSOLE', 1);

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/common_admin.php';


if (!$pun_user['is_admmod'])
	message($lang->t('No permission'));

// Load the admin_users.php language file
$lang->load('admin_users');

// Show IP statistics for a certain user ID
if (isset($_GET['ip_stats']))
{
	$ip_stats = intval($_GET['ip_stats']);
	if ($ip_stats < 1)
		message($lang->t('Bad request'));

	// Fetch ip count: TODO: This query is horrible - why do we fetch all the data just to count it? We should use something like
	// SELECT COUNT(DISTINCT poster_ip) FROM posts WHERE poster_id = :poster_id - though PostgreSQL doesn't seem to support that :(
	$query = $db->select(array('poster_ip' => 'p.poster_ip', 'last_used' => 'MAX(p.posted) AS last_used'), 'posts AS p');
	$query->where = 'p.poster_id = :poster_id';
	$query->group = array('poster_ip' => 'p.poster_ip');

	$params = array(':poster_id' => $ip_stats);

	$result = $query->run($params);
	$num_ips = count($result);
	unset ($result, $query, $params);

	// Determine the ip offset (based on $_GET['p'])
	$num_pages = ceil($num_ips / 50);

	$p = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $num_pages) ? 1 : intval($_GET['p']);
	$start_from = 50 * ($p - 1);

	// Generate paging links
	$paging_links = '<span class="pages-label">'.$lang->t('Pages').' </span>'.paginate($num_pages, $p, 'admin_users.php?ip_stats='.$ip_stats );

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Admin'), $lang->t('Users'), $lang->t('Results head'));
	define('PUN_ACTIVE_PAGE', 'admin');
	require PUN_ROOT.'header.php';

?>
<div class="linkst">
	<div class="inbox crumbsplus">
		<ul class="crumbs">
			<li><a href="admin_index.php"><?php echo $lang->t('Admin').' '.$lang->t('Index') ?></a></li>
			<li><span>»&#160;</span><a href="admin_users.php"><?php echo $lang->t('Users') ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang->t('Results head') ?></strong></li>
		</ul>
		<div class="pagepost">
			<p class="pagelink"><?php echo $paging_links ?></p>
		</div>
		<div class="clearer"></div>
	</div>
</div>

<div id="users1" class="blocktable">
	<h2><span><?php echo $lang->t('Results head') ?></span></h2>
	<div class="box">
		<div class="inbox">
			<table cellspacing="0">
			<thead>
				<tr>
					<th class="tcl" scope="col"><?php echo $lang->t('Results IP address head') ?></th>
					<th class="tc2" scope="col"><?php echo $lang->t('Results last used head') ?></th>
					<th class="tc3" scope="col"><?php echo $lang->t('Results times found head') ?></th>
					<th class="tcr" scope="col"><?php echo $lang->t('Results action head') ?></th>
				</tr>
			</thead>
			<tbody>
<?php

	$query = $db->select(array('poster_ip' => 'p.poster_ip', 'last_used' => 'MAX(p.posted) AS last_used', 'used_times' => 'COUNT(p.id) AS used_times'), 'posts AS p');
	$query->where = 'p.poster_id = :poster_id';
	$query->group = array('poster_ip' => 'p.poster_ip');
	$query->order = array('last_used' => 'last_used DESC');
	$query->offset = $start_from;
	$query->limit = 50;

	$params = array(':poster_id' => $ip_stats);

	$result = $query->run($params);
	if (!empty($result))
	{
		foreach ($result as $cur_ip)
		{

?>
				<tr>
					<td class="tcl"><a href="moderate.php?get_host=<?php echo $cur_ip['poster_ip'] ?>"><?php echo $cur_ip['poster_ip'] ?></a></td>
					<td class="tc2"><?php echo format_time($cur_ip['last_used']) ?></td>
					<td class="tc3"><?php echo $cur_ip['used_times'] ?></td>
					<td class="tcr"><a href="admin_users.php?show_users=<?php echo $cur_ip['poster_ip'] ?>"><?php echo $lang->t('Results find more link') ?></a></td>
				</tr>
<?php

		}
	}
	else
		echo "\t\t\t\t".'<tr><td class="tcl" colspan="4">'.$lang->t('Results no posts found').'</td></tr>'."\n";

	unset ($result, $query, $params);

?>
			</tbody>
			</table>
		</div>
	</div>
</div>

<div class="linksb">
	<div class="inbox crumbsplus">
		<div class="pagepost">
			<p class="pagelink"><?php echo $paging_links ?></p>
		</div>
		<ul class="crumbs">
			<li><a href="admin_index.php"><?php echo $lang->t('Admin').' '.$lang->t('Index') ?></a></li>
			<li><span>»&#160;</span><a href="admin_users.php"><?php echo $lang->t('Users') ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang->t('Results head') ?></strong></li>
		</ul>
		<div class="clearer"></div>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


if (isset($_GET['show_users']))
{
	$ip = trim($_GET['show_users']);

	if (!@preg_match('%^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$%', $ip) && !@preg_match('%^((([0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}:[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){5}:([0-9A-Fa-f]{1,4}:)?[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){4}:([0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){3}:([0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){2}:([0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(([0-9A-Fa-f]{1,4}:){0,5}:((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(::([0-9A-Fa-f]{1,4}:){0,5}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|([0-9A-Fa-f]{1,4}::([0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})|(::([0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){1,7}:))$%', $ip))
		message($lang->t('Bad IP message'));

	// Fetch user count: TODO: Again, we really shouldn't fetch all the data just to count it...
	$query = $db->select(array('poster_id' => 'p.poster_id', 'poster' => 'p.poster'), 'posts AS p', true);
	$query->where = 'poster_ip = :poster_ip';

	$params = array(':poster_ip' => $ip);
	$result = $query->run($params);

	$num_users = count($result);
	unset ($result, $query, $params);

	// Determine the user offset (based on $_GET['p'])
	$num_pages = ceil($num_users / 50);

	$p = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $num_pages) ? 1 : intval($_GET['p']);
	$start_from = 50 * ($p - 1);

	// Generate paging links
	$paging_links = '<span class="pages-label">'.$lang->t('Pages').' </span>'.paginate($num_pages, $p, 'admin_users.php?show_users='.$ip);

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Admin'), $lang->t('Users'), $lang->t('Results head'));
	define('PUN_ACTIVE_PAGE', 'admin');
	require PUN_ROOT.'header.php';

?>
<div class="linkst">
	<div class="inbox crumbsplus">
		<ul class="crumbs">
			<li><a href="admin_index.php"><?php echo $lang->t('Admin').' '.$lang->t('Index') ?></a></li>
			<li><span>»&#160;</span><a href="admin_users.php"><?php echo $lang->t('Users') ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang->t('Results head') ?></strong></li>
		</ul>
		<div class="pagepost">
			<p class="pagelink"><?php echo $paging_links ?></p>
		</div>
		<div class="clearer"></div>
	</div>
</div>

<div id="users2" class="blocktable">
	<h2><span><?php echo $lang->t('Results head') ?></span></h2>
	<div class="box">
		<div class="inbox">
			<table cellspacing="0">
			<thead>
				<tr>
					<th class="tcl" scope="col"><?php echo $lang->t('Results username head') ?></th>
					<th class="tc2" scope="col"><?php echo $lang->t('Results e-mail head') ?></th>
					<th class="tc3" scope="col"><?php echo $lang->t('Results title head') ?></th>
					<th class="tc4" scope="col"><?php echo $lang->t('Results posts head') ?></th>
					<th class="tc5" scope="col"><?php echo $lang->t('Results admin note head') ?></th>
					<th class="tcr" scope="col"><?php echo $lang->t('Results actions head') ?></th>
				</tr>
			</thead>
			<tbody>
<?php

	$query = $db->select(array('poster_id' => 'p.poster_id', 'poster' => 'p.poster'), 'posts AS p', true);
	$query->where = 'p.poster_ip = :poster_ip';
	$query->order = array('poster' => 'p.poster DESC');

	$params = array(':poster_ip' => $ip);

	$result = $query->run($params);
	$num_posts = count($result);
	unset ($query, $params);

	if ($num_posts)
	{
		$query = $db->select(array('id' => 'u.id', 'username' => 'u.username', 'email' => 'u.email', 'title' => 'u.title', 'num_posts' => 'u.num_posts', 'admin_note' => 'u.admin_note', 'g_id' => 'g.g_id', 'g_user_title' => 'g.g_user_title'), 'users AS u');
		$query->innerJoin('g', 'groups AS g', 'g.g_id = u.group_id');
		$query->where = 'u.id > 1 AND u.id = :poster_id';

		// Loop through users and print out some info
		foreach ($result as $cur_post)
		{
			$poster_id = $cur_post['poster_id'];
			$poster = $cur_post['poster'];

			// TODO: Do we really need a query within a query here...?
			$params = array(':poster_id' => $poster_id);
			$result_user = $query->run($params);

			if (!empty($result_user))
			{
				$user_data = $result_user[0];

				$user_title = get_title($user_data);

				$actions = '<a href="admin_users.php?ip_stats='.$user_data['id'].'">'.$lang->t('Results view IP link').'</a> | <a href="search.php?action=show_user_posts&amp;user_id='.$user_data['id'].'">'.$lang->t('Results show posts link').'</a>';

?>
				<tr>
					<td class="tcl"><?php echo '<a href="profile.php?id='.$user_data['id'].'">'.pun_htmlspecialchars($user_data['username']).'</a>' ?></td>
					<td class="tc2"><a href="mailto:<?php echo $user_data['email'] ?>"><?php echo $user_data['email'] ?></a></td>
					<td class="tc3"><?php echo $user_title ?></td>
					<td class="tc4"><?php echo forum_number_format($user_data['num_posts']) ?></td>
					<td class="tc5"><?php echo ($user_data['admin_note'] != '') ? pun_htmlspecialchars($user_data['admin_note']) : '&#160;' ?></td>
					<td class="tcr"><?php echo $actions ?></td>
				</tr>
<?php

			}
			else
			{

?>
				<tr>
					<td class="tcl"><?php echo pun_htmlspecialchars($poster) ?></td>
					<td class="tc2">&#160;</td>
					<td class="tc3"><?php echo $lang->t('Results guest') ?></td>
					<td class="tc4">&#160;</td>
					<td class="tc5">&#160;</td>
					<td class="tcr">&#160;</td>
				</tr>
<?php

			}
		}

		unset ($query, $params, $result_user);
	}
	else
		echo "\t\t\t\t".'<tr><td class="tcl" colspan="6">'.$lang->t('Results no IP found').'</td></tr>'."\n";

	unset ($result);

?>
			</tbody>
			</table>
		</div>
	</div>
</div>

<div class="linksb">
	<div class="inbox crumbsplus">
		<div class="pagepost">
			<p class="pagelink"><?php echo $paging_links ?></p>
		</div>
		<ul class="crumbs">
			<li><a href="admin_index.php"><?php echo $lang->t('Admin').' '.$lang->t('Index') ?></a></li>
			<li><span>»&#160;</span><a href="admin_users.php"><?php echo $lang->t('Users') ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang->t('Results head') ?></strong></li>
		</ul>
		<div class="clearer"></div>
	</div>
</div>
<?php
	require PUN_ROOT.'footer.php';
}


// Move multiple users to other user groups
else if (isset($_POST['move_users']) || isset($_POST['move_users_comply']))
{
	if ($pun_user['g_id'] > PUN_ADMIN)
		message($lang->t('No permission'));

	confirm_referrer('admin_users.php');

	if (isset($_POST['users']))
	{
		$user_ids = is_array($_POST['users']) ? array_keys($_POST['users']) : explode(',', $_POST['users']);
		$user_ids = array_map('intval', $user_ids);

		// Delete invalid IDs
		$user_ids = array_diff($user_ids, array(0, 1));
	}
	else
		$user_ids = array();

	if (empty($user_ids))
		message($lang->t('No users selected'));

	// Are we trying to batch move any admins?
	$query = $db->select(array('count' => 'COUNT(u.*) AS count'), 'users AS u');
	$query->where = 'u.id IN :user_ids AND u.group_id = :group_id';

	$params = array(':user_ids' => $user_ids, ':group_id' => PUN_ADMIN);

	$result = $query->run($params);
	if ($result[0]['count'] > 0)
		message($lang->t('No move admins message'));

	unset($query, $params, $result);

	// Fetch all user groups
	$query = $db->select(array('g_id' => 'g.g_id', 'g_title' => 'g.g_title'), 'groups AS g');
	$query->where = 'g.g_id NOT IN :group_ids';
	$query->order = array('g_title' => 'g.g_title ASC');

	$params = array(':group_ids' => array(PUN_GUEST, PUN_ADMIN));

	$result = $query->run($params);
	$all_groups = array();
	foreach ($result as $row)
		$all_groups[$row['g_id']] = $row['g_title'];
	unset($query, $params, $result);

	if (isset($_POST['move_users_comply']))
	{
		$new_group = isset($_POST['new_group']) && isset($all_groups[$_POST['new_group']]) ? $_POST['new_group'] : message($lang->t('Invalid group message'));

		// Is the new group a moderator group?
		$query = $db->select(array('g_moderator' => 'g.g_moderator'), 'groups AS g');
		$query->where = 'g.g_id = :group_id';

		$params = array(':group_id' => $new_group);

		$result = $query->run($params);
		$new_group_mod = $result[0]['g_moderator'];
		unset ($result, $query, $params);

		// Fetch user groups
		$user_groups = array();
		$query = $db->select(array('id' => 'u.id', 'group_id' => 'u.group_id'), 'users AS u');
		$query->where = 'u.id IN :user_ids';

		$params = array(':user_ids' => $user_ids);

		$result = $query->run($params);
		foreach ($result as $cur_user)
		{
			if (!isset($user_groups[$cur_user['group_id']]))
				$user_groups[$cur_user['group_id']] = array();

			$user_groups[$cur_user['group_id']][] = $cur_user['id'];
		}

		unset($query, $params, $result);

		// Are any users moderators?
		$group_ids = array_keys($user_groups);
		$query = $db->select(array('g_id' => 'g.g_id', 'g_moderator' => 'g.g_moderator'), 'groups AS g');
		$query->where = 'g.g_id IN :group_ids';

		$params = array(':group_ids' => $group_ids);

		$result = $query->run($params);
		foreach ($result as $cur_group)
		{
			if ($cur_group['g_moderator'] == '0')
				unset($user_groups[$cur_group['g_id']]);
		}
		unset($query, $params, $result);

		if (!empty($user_groups) && $new_group != PUN_ADMIN && $new_group_mod != '1')
		{
			// Fetch forum list and clean up their moderator list
			$query = $db->select(array('id' => 'f.id', 'moderators' => 'f.moderators'), 'forums AS f');
			$params = array();

			$result = $query->run($params);
			unset ($query, $params);

			$update_query = $db->update(array('moderators' => ':moderators'), 'forums');
			$update_query->where = 'id = :forum_id';

			foreach ($result as $cur_forum)
			{
				$cur_moderators = ($cur_forum['moderators'] != '') ? unserialize($cur_forum['moderators']) : array();

				foreach ($user_groups as $group_users)
					$cur_moderators = array_diff($cur_moderators, $group_users);

				$params = array(':moderators' => empty($cur_moderators) ? null : serialize($cur_moderators), ':forum_id' => $cur_forum['id']);

				$update_query->run($params);
				unset ($params);
			}

			unset ($result, $update_query);
		}

		// Change user group
		$query = $db->update(array('group_id' => ':group_id'), 'users');
		$query->where = 'id IN :uids';

		$params = array(':group_id' => $new_group, ':uids' => $user_ids);

		$query->run($params);
		unset ($query, $params);

		redirect('admin_users.php', $lang->t('Users move redirect'));
	}

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Admin'), $lang->t('Users'), $lang->t('Move users'));
	define('PUN_ACTIVE_PAGE', 'admin');
	require PUN_ROOT.'header.php';

	generate_admin_menu('users');

?>
	<div class="blockform">
		<h2><span><?php echo $lang->t('Move users') ?></span></h2>
		<div class="box">
			<form name="confirm_move_users" method="post" action="admin_users.php">
				<input type="hidden" name="users" value="<?php echo implode(',', $user_ids) ?>" />
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Move users subhead') ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang->t('New group label') ?></th>
									<td>
										<select name="new_group" tabindex="1">
<?php foreach ($all_groups as $gid => $group) : ?>											<option value="<?php echo $gid ?>"><?php echo pun_htmlspecialchars($group) ?></option>
<?php endforeach; ?>
										</select>
										<span><?php echo $lang->t('New group help') ?></span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="move_users_comply" value="<?php echo $lang->t('Save') ?>" tabindex="2" /></p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


// Delete multiple users
else if (isset($_POST['delete_users']) || isset($_POST['delete_users_comply']))
{
	if ($pun_user['g_id'] > PUN_ADMIN)
		message($lang->t('No permission'));

	confirm_referrer('admin_users.php');

	if (isset($_POST['users']))
	{
		$user_ids = is_array($_POST['users']) ? array_keys($_POST['users']) : explode(',', $_POST['users']);
		$user_ids = array_map('intval', $user_ids);

		// Delete invalid IDs
		$user_ids = array_diff($user_ids, array(0, 1));
	}
	else
		$user_ids = array();

	if (empty($user_ids))
		message($lang->t('No users selected'));

	// Are we trying to delete any admins?
	$query = $db->select(array('count' => 'COUNT(u.*) AS count'), 'users AS u');
	$query->where = 'u.id IN :user_ids AND group_id = :group_id';

	$params = array(':user_ids' => $user_ids, ':group_id' => PUN_ADMIN);

	$result = $query->run($params);
	if ($result[0]['count'] > 0)
		message($lang->t('No delete admins message'));
	unset($query, $params, $result);

	if (isset($_POST['delete_users_comply']))
	{
		// Fetch user groups
		$query = $db->select(array('id' => 'u.id', 'group_id' => 'u.group_id'), 'users AS u');
		$query->where = 'u.id IN :user_ids';

		$params = array(':user_ids' => $user_ids);

		$result = $query->run($params);

		$user_groups = array();
		foreach ($result as $cur_user)
		{
			if (!isset($user_groups[$cur_user['group_id']]))
				$user_groups[$cur_user['group_id']] = array();

			$user_groups[$cur_user['group_id']][] = $cur_user['id'];
		}

		unset($query, $params, $result);

		// Are any users moderators?
		$group_ids = array_keys($user_groups);
		$query = $db->select(array('g_id' => 'g.g_id', 'g_moderator' => 'g.g_moderator'), 'groups AS g');
		$query->where = 'g.g_id IN :group_ids';

		$params = array(':group_ids' => $group_ids);

		$result = $query->run($params);
		foreach ($result as $cur_group)
		{
			if ($cur_group['g_moderator'] == '0')
				unset($user_groups[$cur_group['g_id']]);
		}

		unset($query, $params, $result);

		// Fetch forum list and clean up their moderator list
		$query = $db->select(array('id' => 'f.id', 'moderators' => 'f.moderators'), 'forums AS f');
		$params = array();

		$result = $query->run($params);
		unset ($query, $params);

		$update_query = $db->update(array('moderators' => ':moderators'), 'forums');
		$update_query->where = 'id = :forum_id';

		foreach ($result as $cur_forum)
		{
			$cur_moderators = ($cur_forum['moderators'] != '') ? unserialize($cur_forum['moderators']) : array();

			foreach ($user_groups as $group_users)
				$cur_moderators = array_diff($cur_moderators, $group_users);

			$params = array(':moderators' => empty($cur_moderators) ? null : serialize ($cur_moderators), ':forum_id' => $cur_forum['id']);

			$update_query->run($params);
			unset ($params);
		}

		unset ($result, $update_query);

		// Delete any subscriptions
		$query = $db->delete('topic_subscriptions');
		$query->where = 'user_id IN :uids';

		$params = array(':uids' => $user_ids);

		$query->run($params);
		unset ($query, $params);

		$query = $db->delete('forum_subscriptions');
		$query->where = 'user_id IN :uids';

		$params = array(':uids' => $user_ids);

		$query->run($params);
		unset ($query, $params);

		// Remove them from the online list (if they happen to be logged in)
		$query = $db->delete('online');
		$query->where = 'user_id IN :uids';

		$params = array(':uids' => $user_ids);

		$query->run($params);
		unset ($query, $params);

		// Should we delete all posts made by these users?
		if (isset($_POST['delete_posts']))
		{
			require PUN_ROOT.'include/search_idx.php';
			@set_time_limit(0);

			// Find all posts made by this user
			$query = $db->select(array('id' => 'p.id', 'topic_id' => 'p.topic_id', 'forum_id' => 't.forum_id'), 'posts AS p');
			$query->innerJoin('t', 'topics AS t', 't.id = p.topic_id');
			$query->innerJoin('f', 'forums AS f', 'f.id = t.forum_id');
			$query->where = 'p.poster_id IN :uids';

			$params = array(':uids' => $user_ids);
			$result = $query->run($params);

			unset ($query, $params);

			if (!empty($result))
			{
				$query = $db->select(array('id' => 'p.id'), 'posts AS p');
				$query->where = 'p.topic_id = :topic_id';
				$query->order = array('posted' => 'p.posted');
				$query->limit = '1';

				foreach ($result as $cur_post)
				{
					// Determine whether this post is the "topic post" or not
					$params = array(':topic_id' => $cur_post['topic_id']);
					$result2 = $query->run($params);

					if (isset($result2[0]['id']) && $result2[0]['id'] == $cur_post['id'])
						delete_topic($cur_post['topic_id']);
					else
						delete_post($cur_post['id'], $cur_post['topic_id']);

					update_forum($cur_post['forum_id']);
				}

				unset ($query, $params, $result2);
			}

			unset ($result);
		}
		else
		{
			// Set all their posts to guest
			$query = $db->update(array('poster_id' => '1'), 'posts');
			$query->where = 'poster_id IN :uids';

			$params = array(':uids' => $user_ids);

			$query->run($params);
			unset ($query, $params);
		}

		// Delete the users
		$query = $db->delete('users');
		$query->where = 'id IN :uids';

		$params = array(':uids' => $user_ids);

		$query->run($params);
		unset ($query, $params);

		// Delete user avatars
		foreach ($user_ids as $user_id)
			delete_avatar($user_id);

		// Regenerate the users info cache
		$cache->delete('boardstats');

		redirect('admin_users.php', $lang->t('Users delete redirect'));
	}

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Admin'), $lang->t('Users'), $lang->t('Delete users'));
	define('PUN_ACTIVE_PAGE', 'admin');
	require PUN_ROOT.'header.php';

	generate_admin_menu('users');

?>
	<div class="blockform">
		<h2><span><?php echo $lang->t('Delete users') ?></span></h2>
		<div class="box">
			<form name="confirm_del_users" method="post" action="admin_users.php">
				<input type="hidden" name="users" value="<?php echo implode(',', $user_ids) ?>" />
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Confirm delete legend') ?></legend>
						<div class="infldset">
							<p><?php echo $lang->t('Confirm delete info') ?></p>
							<div class="rbox">
								<label><input type="checkbox" name="delete_posts" value="1" checked="checked" /><?php echo $lang->t('Delete posts') ?><br /></label>
							</div>
							<p class="warntext"><strong><?php echo $lang->t('Delete warning') ?></strong></p>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="delete_users_comply" value="<?php echo $lang->t('Delete') ?>" /> <a href="javascript:history.go(-1)"><?php echo $lang->t('Go back') ?></a></p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


// Ban multiple users
else if (isset($_POST['ban_users']) || isset($_POST['ban_users_comply']))
{
	if ($pun_user['g_id'] != PUN_ADMIN && ($pun_user['g_moderator'] != '1' || $pun_user['g_mod_ban_users'] == '0'))
		message($lang->t('No permission'));

	confirm_referrer('admin_users.php');

	if (isset($_POST['users']))
	{
		$user_ids = is_array($_POST['users']) ? array_keys($_POST['users']) : explode(',', $_POST['users']);
		$user_ids = array_map('intval', $user_ids);

		// Delete invalid IDs
		$user_ids = array_diff($user_ids, array(0, 1));
	}
	else
		$user_ids = array();

	if (empty($user_ids))
		message($lang->t('No users selected'));

	// Are we trying to ban any admins?
	$query = $db->select(array('count' => 'COUNT(u.*) AS count'), 'users AS u');
	$query->where = 'u.id IN :user_ids AND u.group_id = :group_id';

	$params = array(':user_ids' => $user_ids, ':group_id' => PUN_ADMIN);

	$result = $query->run($params);
	if ($result[0]['count'])
		message($lang->t('No ban admins message'));

	unset($query, $params, $result);

	// Also, we cannot ban moderators
	$query = $db->select(array('count' => 'COUNT(u.*) AS count'), 'users AS u');
	$query->innerJoin('g', 'groups AS g', 'u.group_id = g.g_id');
	$query->where = 'g.g_moderator = 1 AND u.id IN :user_ids';

	$params = array(':user_ids' => $user_ids);

	$result = $query->run($params);
	if ($result[0]['count'])
		message($lang->t('No ban mods message'));

	unset($query, $params, $result);

	if (isset($_POST['ban_users_comply']))
	{
		$ban_message = pun_trim($_POST['ban_message']);
		$ban_expire = pun_trim($_POST['ban_expire']);
		$ban_the_ip = isset($_POST['ban_the_ip']) ? intval($_POST['ban_the_ip']) : 0;

		if ($ban_expire != '' && $ban_expire != 'Never')
		{
			$ban_expire = strtotime($ban_expire.' GMT');

			if ($ban_expire == -1 || !$ban_expire)
				message($lang->t('Invalid date message').' '.$lang->t('Invalid date reasons'));

			$diff = ($pun_user['timezone'] + $pun_user['dst']) * 3600;
			$ban_expire -= $diff;

			if ($ban_expire <= time())
				message($lang->t('Invalid date message').' '.$lang->t('Invalid date reasons'));
		}
		else
			$ban_expire = 'NULL';

		$ban_message = ($ban_message != '') ? $db->quote($ban_message) : 'NULL';

		// Fetch user information
		$user_info = array();
		$query = $db->select(array('id' => 'u.id', 'username' => 'u.username', 'email' => 'u.email', 'registration_ip' => 'u.registration_ip'), 'users AS u');
		$query->where = 'u.id IN :user_ids';

		$params = array(':user_ids' => $user_ids);

		$result = $query->run($params);
		foreach ($result as $cur_user)
			$user_info[$cur_user['id']] = array('username' => $cur_user['username'], 'email' => $cur_user['email'], 'ip' => $cur_user['registration_ip']);

		unset($query, $params, $result);

		// Overwrite the registration IP with one from the last post (if it exists)
		if ($ban_the_ip != 0)
		{
			$result = $db->query('SELECT p.poster_id, p.poster_ip FROM '.$db->prefix.'posts AS p INNER JOIN (SELECT MAX(id) AS id FROM '.$db->prefix.'posts WHERE poster_id IN ('.implode(',', $user_ids).') GROUP BY poster_id) AS i ON p.id=i.id') or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
			while ($cur_address = $db->fetch_assoc($result))
				$user_info[$cur_address['poster_id']]['ip'] = $cur_address['poster_ip'];
		}

		// And insert the bans!
		foreach ($user_ids as $user_id)
		{
			$ban_username = $user_info[$user_id]['username'];
			$ban_email = $user_info[$user_id]['email'];
			$ban_ip = ($ban_the_ip != 0) ? $user_info[$user_id]['ip'] : NULL;

			$query = $db->insert(array('username' => ':username', 'ip' => ':ip', 'email' => ':email', 'message' => ':message', 'expire' => ':expire', 'ban_creator' => ':user_id'), 'bans');
			$params = array(':username' => $ban_username, ':ip' => $ban_ip, ':email' => $ban_email, ':message' => $ban_message, ':expire' => $ban_expire, ':user_id' => $pun_user['id']);
			$query->run($params);
			unset($query, $params);
		}

		// Regenerate the bans cache
		$cache->delete('bans');

		redirect('admin_users.php', $lang->t('Users banned redirect'));
	}

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Admin'), $lang->t('Bans'));
	$focus_element = array('bans2', 'ban_message');
	define('PUN_ACTIVE_PAGE', 'admin');
	require PUN_ROOT.'header.php';

	generate_admin_menu('users');

?>
	<div class="blockform">
		<h2><span><?php echo $lang->t('Ban users') ?></span></h2>
		<div class="box">
			<form id="bans2" name="confirm_ban_users" method="post" action="admin_users.php">
				<input type="hidden" name="users" value="<?php echo implode(',', $user_ids) ?>" />
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Message expiry subhead') ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang->t('Ban message label') ?></th>
									<td>
										<input type="text" name="ban_message" size="50" maxlength="255" tabindex="1" />
										<span><?php echo $lang->t('Ban message help') ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Expire date label') ?></th>
									<td>
										<input type="text" name="ban_expire" size="17" maxlength="10" tabindex="2" />
										<span><?php echo $lang->t('Expire date help') ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Ban IP label') ?></th>
									<td>
										<input type="radio" name="ban_the_ip" tabindex="3" value="1" checked="checked" />&#160;<strong><?php echo $lang->t('Yes') ?></strong>&#160;&#160;&#160;<input type="radio" name="ban_the_ip" tabindex="4" value="0" checked="checked" />&#160;<strong><?php echo $lang->t('No') ?></strong>
										<span><?php echo $lang->t('Ban IP help') ?></span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="ban_users_comply" value="<?php echo $lang->t('Save') ?>" tabindex="3" /></p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


else if (isset($_GET['find_user']))
{
	$form = isset($_GET['form']) ? $_GET['form'] : array();

	// trim() all elements in $form
	$form = array_map('pun_trim', $form);
	$conditions = $query_str = array();

	$posts_greater = isset($_GET['posts_greater']) ? trim($_GET['posts_greater']) : '';
	$posts_less = isset($_GET['posts_less']) ? trim($_GET['posts_less']) : '';
	$last_post_after = isset($_GET['last_post_after']) ? trim($_GET['last_post_after']) : '';
	$last_post_before = isset($_GET['last_post_before']) ? trim($_GET['last_post_before']) : '';
	$last_visit_after = isset($_GET['last_visit_after']) ? trim($_GET['last_visit_after']) : '';
	$last_visit_before = isset($_GET['last_visit_before']) ? trim($_GET['last_visit_before']) : '';
	$registered_after = isset($_GET['registered_after']) ? trim($_GET['registered_after']) : '';
	$registered_before = isset($_GET['registered_before']) ? trim($_GET['registered_before']) : '';
	$order_by = isset($_GET['order_by']) && in_array($_GET['order_by'], array('username', 'email', 'num_posts', 'last_post', 'last_visit', 'registered')) ? $_GET['order_by'] : 'username';
	$direction = isset($_GET['direction']) && $_GET['direction'] == 'DESC' ? 'DESC' : 'ASC';
	$user_group = isset($_GET['user_group']) ? intval($_GET['user_group']) : -1;

	$query_str[] = 'order_by='.$order_by;
	$query_str[] = 'direction='.$direction;
	$query_str[] = 'user_group='.$user_group;

	if (preg_match('%[^0-9]%', $posts_greater.$posts_less))
		message($lang->t('Non numeric message'));

	// Try to convert date/time to timestamps
	if ($last_post_after != '')
	{
		$query_str[] = 'last_post_after='.$last_post_after;

		$last_post_after = strtotime($last_post_after);
		if ($last_post_after === false || $last_post_after == -1)
			message($lang->t('Invalid date time message'));

		$conditions[] = 'u.last_post>'.$last_post_after;
	}
	if ($last_post_before != '')
	{
		$query_str[] = 'last_post_before='.$last_post_before;

		$last_post_before = strtotime($last_post_before);
		if ($last_post_before === false || $last_post_before == -1)
			message($lang->t('Invalid date time message'));

		$conditions[] = 'u.last_post<'.$last_post_before;
	}
	if ($last_visit_after != '')
	{
		$query_str[] = 'last_visit_after='.$last_visit_after;

		$last_visit_after = strtotime($last_visit_after);
		if ($last_visit_after === false || $last_visit_after == -1)
			message($lang->t('Invalid date time message'));

		$conditions[] = 'u.last_visit>'.$last_visit_after;
	}
	if ($last_visit_before != '')
	{
		$query_str[] = 'last_visit_before='.$last_visit_before;

		$last_visit_before = strtotime($last_visit_before);
		if ($last_visit_before === false || $last_visit_before == -1)
			message($lang->t('Invalid date time message'));

		$conditions[] = 'u.last_visit<'.$last_visit_before;
	}
	if ($registered_after != '')
	{
		$query_str[] = 'registered_after='.$registered_after;

		$registered_after = strtotime($registered_after);
		if ($registered_after === false || $registered_after == -1)
			message($lang->t('Invalid date time message'));

		$conditions[] = 'u.registered>'.$registered_after;
	}
	if ($registered_before != '')
	{
		$query_str[] = 'registered_before='.$registered_before;

		$registered_before = strtotime($registered_before);
		if ($registered_before === false || $registered_before == -1)
			message($lang->t('Invalid date time message'));

		$conditions[] = 'u.registered<'.$registered_before;
	}

	$like_command = ($db_type == 'pgsql') ? 'ILIKE' : 'LIKE';
	foreach ($form as $key => $input)
	{
		if ($input != '' && in_array($key, array('username', 'email', 'title', 'realname', 'url', 'jabber', 'icq', 'msn', 'aim', 'yahoo', 'location', 'signature', 'admin_note')))
		{
			$conditions[] = 'u.'.$key.' '.$like_command.' '.$db->quote(str_replace('*', '%', $input));
			$query_str[] = 'form%5B'.$key.'%5D='.urlencode($input);
		}
	}

	if ($posts_greater != '')
	{
		$query_str[] = 'posts_greater='.$posts_greater;
		$conditions[] = 'u.num_posts>'.$posts_greater;
	}
	if ($posts_less != '')
	{
		$query_str[] = 'posts_less='.$posts_less;
		$conditions[] = 'u.num_posts<'.$posts_less;
	}

	if ($user_group > -1)
		$conditions[] = 'u.group_id='.$user_group;

	// Fetch user count
	$query = $db->select(array('count' => 'COUNT(u.id) AS num_users'), 'users AS u ');
	$query->leftJoin('g', 'groups AS g', 'g.g_id = u.group_id');
	$query->where = 'u.id > 1'.(!empty($conditions) ? ' AND '.implode(' AND ', $conditions) : '');

	$result = $query->run();

	$num_users = $result[0]['num_users'];

	// Determine the user offset (based on $_GET['p'])
	$num_pages = ceil($num_users / 50);

	$p = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $num_pages) ? 1 : intval($_GET['p']);
	$start_from = 50 * ($p - 1);

	// Generate paging links
	$paging_links = '<span class="pages-label">'.$lang->t('Pages').' </span>'.paginate($num_pages, $p, 'admin_users.php?find_user=&amp;'.implode('&amp;', $query_str));

	// Some helper variables for permissions
	$can_delete = $can_move = $pun_user['g_id'] == PUN_ADMIN;
	$can_ban = $pun_user['g_id'] == PUN_ADMIN || ($pun_user['g_moderator'] == '1' && $pun_user['g_mod_ban_users'] == '1');
	$can_action = ($can_delete || $can_ban || $can_move) && $num_users > 0;

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Admin'), $lang->t('Users'), $lang->t('Results head'));
	$page_head = array('js' => '<script type="text/javascript" src="common.js"></script>');
	define('PUN_ACTIVE_PAGE', 'admin');
	require PUN_ROOT.'header.php';

?>
<div class="linkst">
	<div class="inbox crumbsplus">
		<ul class="crumbs">
			<li><a href="admin_index.php"><?php echo $lang->t('Admin').' '.$lang->t('Index') ?></a></li>
			<li><span>»&#160;</span><a href="admin_users.php"><?php echo $lang->t('Users') ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang->t('Results head') ?></strong></li>
		</ul>
		<div class="pagepost">
			<p class="pagelink"><?php echo $paging_links ?></p>
		</div>
		<div class="clearer"></div>
	</div>
</div>


<form id="search-users-form" action="admin_users.php" method="post">
<div id="users2" class="blocktable">
	<h2><span><?php echo $lang->t('Results head') ?></span></h2>
	<div class="box">
		<div class="inbox">
			<table cellspacing="0">
			<thead>
				<tr>
					<th class="tcl" scope="col"><?php echo $lang->t('Results username head') ?></th>
					<th class="tc2" scope="col"><?php echo $lang->t('Results e-mail head') ?></th>
					<th class="tc3" scope="col"><?php echo $lang->t('Results title head') ?></th>
					<th class="tc4" scope="col"><?php echo $lang->t('Results posts head') ?></th>
					<th class="tc5" scope="col"><?php echo $lang->t('Results admin note head') ?></th>
					<th class="tcr" scope="col"><?php echo $lang->t('Results actions head') ?></th>
<?php if ($can_action): ?>					<th class="tcmod" scope="col"><?php echo $lang->t('Select') ?></th>
<?php endif; ?>
				</tr>
			</thead>
			<tbody>
<?php

	$query = $db->select(array('id' => 'u.id', 'username' => 'u.username', 'email' => 'u.email', 'title' => 'u.title', 'num_posts' => 'u.num_posts', 'admin_note' => 'u.admin_note', 'g_id' => 'g.g_id', 'g_user_title' => 'g.g_user_title'), 'users AS u');
	$query->leftJoin('g', 'groups AS g', 'g.g_id = u.group_id');
	$query->where = 'u.id > 1'.(!empty($conditions) ? ' AND '.implode(' AND ', $conditions) : '');
	$query->order = array('order' => $order_by.' '.$direction);
	$query->limit = '50';
	$query->offset = $start_from;

	$result = $query->run();

	if (!empty($result))
	{
		foreach ($result as $user_data)
		{
			$user_title = get_title($user_data);

			// This script is a special case in that we want to display "Not verified" for non-verified users
			if (($user_data['g_id'] == '' || $user_data['g_id'] == PUN_UNVERIFIED) && $user_title != $lang->t('Banned'))
				$user_title = '<span class="warntext">'.$lang->t('Not verified').'</span>';

			$actions = '<a href="admin_users.php?ip_stats='.$user_data['id'].'">'.$lang->t('Results view IP link').'</a> | <a href="search.php?action=show_user_posts&amp;user_id='.$user_data['id'].'">'.$lang->t('Results show posts link').'</a>';

?>
				<tr>
					<td class="tcl"><?php echo '<a href="profile.php?id='.$user_data['id'].'">'.pun_htmlspecialchars($user_data['username']).'</a>' ?></td>
					<td class="tc2"><a href="mailto:<?php echo $user_data['email'] ?>"><?php echo $user_data['email'] ?></a></td>
					<td class="tc3"><?php echo $user_title ?></td>
					<td class="tc4"><?php echo forum_number_format($user_data['num_posts']) ?></td>
					<td class="tc5"><?php echo ($user_data['admin_note'] != '') ? pun_htmlspecialchars($user_data['admin_note']) : '&#160;' ?></td>
					<td class="tcr"><?php echo $actions ?></td>
<?php if ($can_action): ?>					<td class="tcmod"><input type="checkbox" name="users[<?php echo $user_data['id'] ?>]" value="1" /></td>
<?php endif; ?>
				</tr>
<?php

		}
	}
	else
		echo "\t\t\t\t".'<tr><td class="tcl" colspan="6">'.$lang->t('No match').'</td></tr>'."\n";

	unset ($result, $query);

?>
			</tbody>
			</table>
		</div>
	</div>
</div>

<div class="linksb">
	<div class="inbox crumbsplus">
		<div class="pagepost">
			<p class="pagelink"><?php echo $paging_links ?></p>
<?php if ($can_action): ?>			<p class="conr modbuttons"><a href="#" onclick="return select_checkboxes('search-users-form', this, '<?php echo $lang->t('Unselect all') ?>')"><?php echo $lang->t('Select all') ?></a> <?php if ($can_ban) : ?><input type="submit" name="ban_users" value="<?php echo $lang->t('Ban') ?>" /><?php endif; if ($can_delete) : ?><input type="submit" name="delete_users" value="<?php echo $lang->t('Delete') ?>" /><?php endif; if ($can_move) : ?><input type="submit" name="move_users" value="<?php echo $lang->t('Change group') ?>" /><?php endif; ?></p>
<?php endif; ?>
		</div>
		<ul class="crumbs">
			<li><a href="admin_index.php"><?php echo $lang->t('Admin').' '.$lang->t('Index') ?></a></li>
			<li><span>»&#160;</span><a href="admin_users.php"><?php echo $lang->t('Users') ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang->t('Results head') ?></strong></li>
		</ul>
		<div class="clearer"></div>
	</div>
</div>
</form>
<?php

	require PUN_ROOT.'footer.php';
}


else
{
	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Admin'), $lang->t('Users'));
	$focus_element = array('find_user', 'form[username]');
	define('PUN_ACTIVE_PAGE', 'admin');
	require PUN_ROOT.'header.php';

	generate_admin_menu('users');

?>
	<div class="blockform">
		<h2><span><?php echo $lang->t('User search head') ?></span></h2>
		<div class="box">
			<form id="find_user" method="get" action="admin_users.php">
				<p class="submittop"><input type="submit" name="find_user" value="<?php echo $lang->t('Submit search') ?>" tabindex="1" /></p>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('User search subhead') ?></legend>
						<div class="infldset">
							<p><?php echo $lang->t('User search info') ?></p>
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang->t('Username label') ?></th>
									<td><input type="text" name="form[username]" size="25" maxlength="25" tabindex="2" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('E-mail address label') ?></th>
									<td><input type="text" name="form[email]" size="30" maxlength="80" tabindex="3" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Title label') ?></th>
									<td><input type="text" name="form[title]" size="30" maxlength="50" tabindex="4" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Real name label') ?></th>
									<td><input type="text" name="form[realname]" size="30" maxlength="40" tabindex="5" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Website label') ?></th>
									<td><input type="text" name="form[url]" size="35" maxlength="100" tabindex="6" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Jabber label') ?></th>
									<td><input type="text" name="form[jabber]" size="30" maxlength="75" tabindex="7" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('ICQ label') ?></th>
									<td><input type="text" name="form[icq]" size="12" maxlength="12" tabindex="8" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('MSN label') ?></th>
									<td><input type="text" name="form[msn]" size="30" maxlength="50" tabindex="9" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('AOL label') ?></th>
									<td><input type="text" name="form[aim]" size="20" maxlength="20" tabindex="10" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Yahoo label') ?></th>
									<td><input type="text" name="form[yahoo]" size="20" maxlength="20" tabindex="11" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Location label') ?></th>
									<td><input type="text" name="form[location]" size="30" maxlength="30" tabindex="12" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Signature label') ?></th>
									<td><input type="text" name="form[signature]" size="35" maxlength="512" tabindex="13" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Admin note label') ?></th>
									<td><input type="text" name="form[admin_note]" size="30" maxlength="30" tabindex="14" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Posts more than label') ?></th>
									<td><input type="text" name="posts_greater" size="5" maxlength="8" tabindex="15" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Posts less than label') ?></th>
									<td><input type="text" name="posts_less" size="5" maxlength="8" tabindex="16" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Last post after label') ?></th>
									<td><input type="text" name="last_post_after" size="24" maxlength="19" tabindex="17" />
									<span><?php echo $lang->t('Date help') ?></span></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Last post before label') ?></th>
									<td><input type="text" name="last_post_before" size="24" maxlength="19" tabindex="18" />
									<span><?php echo $lang->t('Date help') ?></span></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Last visit after label') ?></th>
									<td><input type="text" name="last_visit_after" size="24" maxlength="19" tabindex="17" />
									<span><?php echo $lang->t('Date help') ?></span></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Last visit before label') ?></th>
									<td><input type="text" name="last_visit_before" size="24" maxlength="19" tabindex="18" />
									<span><?php echo $lang->t('Date help') ?></span></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Registered after label') ?></th>
									<td><input type="text" name="registered_after" size="24" maxlength="19" tabindex="19" />
									<span><?php echo $lang->t('Date help') ?></span></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Registered before label') ?></th>
									<td><input type="text" name="registered_before" size="24" maxlength="19" tabindex="20" />
									<span><?php echo $lang->t('Date help') ?></span></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Order by label') ?></th>
									<td>
										<select name="order_by" tabindex="21">
											<option value="username" selected="selected"><?php echo $lang->t('Order by username') ?></option>
											<option value="email"><?php echo $lang->t('Order by e-mail') ?></option>
											<option value="num_posts"><?php echo $lang->t('Order by posts') ?></option>
											<option value="last_post"><?php echo $lang->t('Order by last post') ?></option>
											<option value="last_visit"><?php echo $lang->t('Order by last visit') ?></option>
											<option value="registered"><?php echo $lang->t('Order by registered') ?></option>
										</select>&#160;&#160;&#160;<select name="direction" tabindex="22">
											<option value="ASC" selected="selected"><?php echo $lang->t('Ascending') ?></option>
											<option value="DESC"><?php echo $lang->t('Descending') ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('User group label') ?></th>
									<td>
										<select name="user_group" tabindex="23">
											<option value="-1" selected="selected"><?php echo $lang->t('All groups') ?></option>
											<option value="0"><?php echo $lang->t('Unverified users') ?></option>
<?php

	$query = $db->select(array('g_id' => 'g.g_id', 'g_title' => 'g.g_title'), 'groups AS g');
	$query->where = 'g.g_id != :group_guest';
	$query->order = array('g_title' => 'g.g_title DESC');

	$params = array(':group_guest' => PUN_GUEST);

	$result = $query->run($params);
	foreach ($result as $cur_group)
		echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";

	unset ($result, $query, $params);

?>
										</select>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="find_user" value="<?php echo $lang->t('Submit search') ?>" tabindex="25" /></p>
			</form>
		</div>

		<h2 class="block2"><span><?php echo $lang->t('IP search head') ?></span></h2>
		<div class="box">
			<form method="get" action="admin_users.php">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('IP search subhead') ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang->t('IP address label') ?><div><input type="submit" value="<?php echo $lang->t('Find IP address') ?>" tabindex="26" /></div></th>
									<td><input type="text" name="show_users" size="18" maxlength="15" tabindex="24" />
									<span><?php echo $lang->t('IP address help') ?></span></td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}
