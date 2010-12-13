<?php

/**
 * Copyright (C) 2008-2010 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Tell header.php to use the admin template
define('PUN_ADMIN_CONSOLE', 1);

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/common_admin.php';


if (!$pun_user['is_admmod'])
	message($lang_common['No permission']);

// Load the admin_users.php language file
require PUN_ROOT.'lang/'.$admin_language.'/admin_users.php';

// Show IP statistics for a certain user ID
if (isset($_GET['ip_stats']))
{
	$ip_stats = intval($_GET['ip_stats']);
	if ($ip_stats < 1)
		message($lang_common['Bad request']);

	// Fetch ip count
	$result = $db->query('SELECT poster_ip, MAX(posted) AS last_used FROM '.$db->prefix.'posts WHERE poster_id='.$ip_stats.' GROUP BY poster_ip') or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
	$num_ips = $db->num_rows($result);

	// Determine the ip offset (based on $_GET['p'])
	$num_pages = ceil($num_ips / 50);

	$p = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $num_pages) ? 1 : intval($_GET['p']);
	$start_from = 50 * ($p - 1);

	// Generate paging links
	$paging_links = '<span class="pages-label">'.$lang_common['Pages'].' </span>'.paginate($num_pages, $p, 'admin_users.php?ip_stats='.$ip_stats );

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_common['Admin'], $lang_admin_common['Users'], $lang_admin_users['Results head']);
	define('PUN_ACTIVE_PAGE', 'admin');
	require PUN_ROOT.'header.php';

?>
<div class="linkst">
	<div class="inbox crumbsplus">
		<ul class="crumbs">
			<li><a href="admin_index.php"><?php echo $lang_admin_common['Admin'].' '.$lang_admin_common['Index'] ?></a></li>
			<li><span>»&#160;</span><a href="admin_users.php"><?php echo $lang_admin_common['Users'] ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang_admin_users['Results head'] ?></strong></li>
		</ul>
		<div class="pagepost">
			<p class="pagelink"><?php echo $paging_links ?></p>
		</div>
		<div class="clearer"></div>
	</div>
</div>

<div id="users1" class="blocktable">
	<h2><span><?php echo $lang_admin_users['Results head'] ?></span></h2>
	<div class="box">
		<div class="inbox">
			<table cellspacing="0">
			<thead>
				<tr>
					<th class="tcl" scope="col"><?php echo $lang_admin_users['Results IP address head'] ?></th>
					<th class="tc2" scope="col"><?php echo $lang_admin_users['Results last used head'] ?></th>
					<th class="tc3" scope="col"><?php echo $lang_admin_users['Results times found head'] ?></th>
					<th class="tcr" scope="col"><?php echo $lang_admin_users['Results action head'] ?></th>
				</tr>
			</thead>
			<tbody>
<?php

	$result = $db->query('SELECT poster_ip, MAX(posted) AS last_used, COUNT(id) AS used_times FROM '.$db->prefix.'posts WHERE poster_id='.$ip_stats.' GROUP BY poster_ip ORDER BY last_used DESC LIMIT '.$start_from.', 50') or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
	if ($db->num_rows($result))
	{
		while ($cur_ip = $db->fetch_assoc($result))
		{

?>
				<tr>
					<td class="tcl"><a href="moderate.php?get_host=<?php echo $cur_ip['poster_ip'] ?>"><?php echo $cur_ip['poster_ip'] ?></a></td>
					<td class="tc2"><?php echo format_time($cur_ip['last_used']) ?></td>
					<td class="tc3"><?php echo $cur_ip['used_times'] ?></td>
					<td class="tcr"><a href="admin_users.php?show_users=<?php echo $cur_ip['poster_ip'] ?>"><?php echo $lang_admin_users['Results find more link'] ?></a></td>
				</tr>
<?php

		}
	}
	else
		echo "\t\t\t\t".'<tr><td class="tcl" colspan="4">'.$lang_admin_users['Results no posts found'].'</td></tr>'."\n";

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
			<li><a href="admin_index.php"><?php echo $lang_admin_common['Admin'].' '.$lang_admin_common['Index'] ?></a></li>
			<li><span>»&#160;</span><a href="admin_users.php"><?php echo $lang_admin_common['Users'] ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang_admin_users['Results head'] ?></strong></li>
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

	if (!@preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $ip) && !@preg_match('/^((([0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}:[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){5}:([0-9A-Fa-f]{1,4}:)?[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){4}:([0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){3}:([0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){2}:([0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(([0-9A-Fa-f]{1,4}:){0,5}:((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(::([0-9A-Fa-f]{1,4}:){0,5}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|([0-9A-Fa-f]{1,4}::([0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})|(::([0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){1,7}:))$/', $ip))
		message($lang_admin_users['Bad IP message']);

	// Fetch user count
	$result = $db->query('SELECT DISTINCT poster_id, poster FROM '.$db->prefix.'posts WHERE poster_ip=\''.$db->escape($ip).'\'') or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
	$num_users = $db->num_rows($result);

	// Determine the user offset (based on $_GET['p'])
	$num_pages = ceil($num_users / 50);

	$p = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $num_pages) ? 1 : intval($_GET['p']);
	$start_from = 50 * ($p - 1);

	// Generate paging links
	$paging_links = '<span class="pages-label">'.$lang_common['Pages'].' </span>'.paginate($num_pages, $p, 'admin_users.php?show_users='.$ip);

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_common['Admin'], $lang_admin_common['Users'], $lang_admin_users['Results head']);
	define('PUN_ACTIVE_PAGE', 'admin');
	require PUN_ROOT.'header.php';

?>
<div class="linkst">
	<div class="inbox crumbsplus">
		<ul class="crumbs">
			<li><a href="admin_index.php"><?php echo $lang_admin_common['Admin'].' '.$lang_admin_common['Index'] ?></a></li>
			<li><span>»&#160;</span><a href="admin_users.php"><?php echo $lang_admin_common['Users'] ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang_admin_users['Results head'] ?></strong></li>
		</ul>
		<div class="pagepost">
			<p class="pagelink"><?php echo $paging_links ?></p>
		</div>
		<div class="clearer"></div>
	</div>
</div>

<div id="users2" class="blocktable">
	<h2><span><?php echo $lang_admin_users['Results head'] ?></span></h2>
	<div class="box">
		<div class="inbox">
			<table cellspacing="0">
			<thead>
				<tr>
					<th class="tcl" scope="col"><?php echo $lang_admin_users['Results username head'] ?></th>
					<th class="tc2" scope="col"><?php echo $lang_admin_users['Results e-mail head'] ?></th>
					<th class="tc3" scope="col"><?php echo $lang_admin_users['Results title head'] ?></th>
					<th class="tc4" scope="col"><?php echo $lang_admin_users['Results posts head'] ?></th>
					<th class="tc5" scope="col"><?php echo $lang_admin_users['Results admin note head'] ?></th>
					<th class="tcr" scope="col"><?php echo $lang_admin_users['Results actions head'] ?></th>
				</tr>
			</thead>
			<tbody>
<?php

	$result = $db->query('SELECT DISTINCT poster_id, poster FROM '.$db->prefix.'posts WHERE poster_ip=\''.$db->escape($ip).'\' ORDER BY poster DESC') or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
	$num_posts = $db->num_rows($result);

	if ($num_posts)
	{
		// Loop through users and print out some info
		for ($i = 0; $i < $num_posts; ++$i)
		{
			list($poster_id, $poster) = $db->fetch_row($result);

			$result2 = $db->query('SELECT u.id, u.username, u.email, u.title, u.num_posts, u.admin_note, g.g_id, g.g_user_title FROM '.$db->prefix.'users AS u INNER JOIN '.$db->prefix.'groups AS g ON g.g_id=u.group_id WHERE u.id>1 AND u.id='.$poster_id) or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());

			if (($user_data = $db->fetch_assoc($result2)))
			{
				$user_title = get_title($user_data);

				$actions = '<a href="admin_users.php?ip_stats='.$user_data['id'].'">'.$lang_admin_users['Results view IP link'].'</a> | <a href="search.php?action=show_user_posts&amp;user_id='.$user_data['id'].'">'.$lang_admin_users['Results show posts link'].'</a>';

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
					<td class="tc3"><?php echo $lang_admin_users['Results guest'] ?></td>
					<td class="tc4">&#160;</td>
					<td class="tc5">&#160;</td>
					<td class="tcr">&#160;</td>
				</tr>
<?php

			}
		}
	}
	else
		echo "\t\t\t\t".'<tr><td class="tcl" colspan="6">'.$lang_admin_users['Results no IP found'].'</td></tr>'."\n";

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
			<li><a href="admin_index.php"><?php echo $lang_admin_common['Admin'].' '.$lang_admin_common['Index'] ?></a></li>
			<li><span>»&#160;</span><a href="admin_users.php"><?php echo $lang_admin_common['Users'] ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang_admin_users['Results head'] ?></strong></li>
		</ul>
		<div class="clearer"></div>
	</div>
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
	$registered_after = isset($_GET['registered_after']) ? trim($_GET['registered_after']) : '';
	$registered_before = isset($_GET['registered_before']) ? trim($_GET['registered_before']) : '';
	$order_by = isset($_GET['order_by']) && in_array($_GET['order_by'], array('username', 'email', 'num_posts', 'last_post', 'registered')) ? $_GET['order_by'] : 'username';
	$direction = isset($_GET['direction']) && $_GET['direction'] == 'DESC' ? 'DESC' : 'ASC';
	$user_group = isset($_GET['user_group']) ? intval($_GET['user_group']) : -1;

	$query_str[] = 'order_by='.$order_by;
	$query_str[] = 'direction='.$direction;
	$query_str[] = 'user_group='.$user_group;

	if (preg_match('/[^0-9]/', $posts_greater.$posts_less))
		message($lang_admin_users['Non numeric message']);

	// Try to convert date/time to timestamps
	if ($last_post_after != '')
	{
		$query_str[] = 'last_post_after='.$last_post_after;

		$last_post_after = strtotime($last_post_after);
		if ($last_post_after === false || $last_post_after == -1)
			message($lang_admin_users['Invalid date time message']);

		$conditions[] = 'u.last_post>'.$last_post_after;
	}
	if ($last_post_before != '')
	{
		$query_str[] = 'last_post_before='.$last_post_before;

		$last_post_before = strtotime($last_post_before);
		if ($last_post_before === false || $last_post_before == -1)
			message($lang_admin_users['Invalid date time message']);

		$conditions[] = 'u.last_post<'.$last_post_before;
	}
	if ($registered_after != '')
	{
		$query_str[] = 'registered_after='.$registered_after;

		$registered_after = strtotime($registered_after);
		if ($registered_after === false || $registered_after == -1)
			message($lang_admin_users['Invalid date time message']);

		$conditions[] = 'u.registered>'.$registered_after;
	}
	if ($registered_before != '')
	{
		$query_str[] = 'registered_before='.$registered_before;

		$registered_before = strtotime($registered_before);
		if ($registered_before === false || $registered_before == -1)
			message($lang_admin_users['Invalid date time message']);

		$conditions[] = 'u.registered<'.$registered_before;
	}

	$like_command = ($db_type == 'pgsql') ? 'ILIKE' : 'LIKE';
	foreach ($form as $key => $input)
	{
		if ($input != '' && in_array($key, array('username', 'email', 'title', 'realname', 'url', 'jabber', 'icq', 'msn', 'aim', 'yahoo', 'location', 'signature', 'admin_note')))
		{
			$conditions[] = 'u.'.$db->escape($key).' '.$like_command.' \''.$db->escape(str_replace('*', '%', $input)).'\'';
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
	$result = $db->query('SELECT COUNT(id) FROM '.$db->prefix.'users AS u LEFT JOIN '.$db->prefix.'groups AS g ON g.g_id=u.group_id WHERE u.id>1'.(!empty($conditions) ? ' AND '.implode(' AND ', $conditions) : '')) or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());
	$num_users = $db->result($result);

	// Determine the user offset (based on $_GET['p'])
	$num_pages = ceil($num_users / 50);

	$p = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $num_pages) ? 1 : intval($_GET['p']);
	$start_from = 50 * ($p - 1);

	// Generate paging links
	$paging_links = '<span class="pages-label">'.$lang_common['Pages'].' </span>'.paginate($num_pages, $p, 'admin_users.php?find_user=&amp;'.implode('&amp;', $query_str));

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_common['Admin'], $lang_admin_common['Users'], $lang_admin_users['Results head']);
	define('PUN_ACTIVE_PAGE', 'admin');
	require PUN_ROOT.'header.php';

?>
<div class="linkst">
	<div class="inbox crumbsplus">
		<ul class="crumbs">
			<li><a href="admin_index.php"><?php echo $lang_admin_common['Admin'].' '.$lang_admin_common['Index'] ?></a></li>
			<li><span>»&#160;</span><a href="admin_users.php"><?php echo $lang_admin_common['Users'] ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang_admin_users['Results head'] ?></strong></li>
		</ul>
		<div class="pagepost">
			<p class="pagelink"><?php echo $paging_links ?></p>
		</div>
		<div class="clearer"></div>
	</div>
</div>


<div id="users2" class="blocktable">
	<h2><span><?php echo $lang_admin_users['Results head'] ?></span></h2>
	<div class="box">
		<div class="inbox">
			<table cellspacing="0">
			<thead>
				<tr>
					<th class="tcl" scope="col"><?php echo $lang_admin_users['Results username head'] ?></th>
					<th class="tc2" scope="col"><?php echo $lang_admin_users['Results e-mail head'] ?></th>
					<th class="tc3" scope="col"><?php echo $lang_admin_users['Results title head'] ?></th>
					<th class="tc4" scope="col"><?php echo $lang_admin_users['Results posts head'] ?></th>
					<th class="tc5" scope="col"><?php echo $lang_admin_users['Results admin note head'] ?></th>
					<th class="tcr" scope="col"><?php echo $lang_admin_users['Results actions head'] ?></th>
				</tr>
			</thead>
			<tbody>
<?php

	$result = $db->query('SELECT u.id, u.username, u.email, u.title, u.num_posts, u.admin_note, g.g_id, g.g_user_title FROM '.$db->prefix.'users AS u LEFT JOIN '.$db->prefix.'groups AS g ON g.g_id=u.group_id WHERE u.id>1'.(!empty($conditions) ? ' AND '.implode(' AND ', $conditions) : '').' ORDER BY '.$db->escape($order_by).' '.$db->escape($direction).' LIMIT '.$start_from.', 50') or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());
	if ($db->num_rows($result))
	{
		while ($user_data = $db->fetch_assoc($result))
		{
			$user_title = get_title($user_data);

			// This script is a special case in that we want to display "Not verified" for non-verified users
			if (($user_data['g_id'] == '' || $user_data['g_id'] == PUN_UNVERIFIED) && $user_title != $lang_common['Banned'])
				$user_title = '<span class="warntext">'.$lang_admin_users['Not verified'].'</span>';

			$actions = '<a href="admin_users.php?ip_stats='.$user_data['id'].'">'.$lang_admin_users['Results view IP link'].'</a> | <a href="search.php?action=show_user_posts&amp;user_id='.$user_data['id'].'">'.$lang_admin_users['Results show posts link'].'</a>';

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
	}
	else
		echo "\t\t\t\t".'<tr><td class="tcl" colspan="6">'.$lang_admin_users['No match'].'</td></tr>'."\n";

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
			<li><a href="admin_index.php"><?php echo $lang_admin_common['Admin'].' '.$lang_admin_common['Index'] ?></a></li>
			<li><span>»&#160;</span><a href="admin_users.php"><?php echo $lang_admin_common['Users'] ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang_admin_users['Results head'] ?></strong></li>
		</ul>
		<div class="clearer"></div>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


else
{
	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_common['Admin'], $lang_admin_common['Users']);
	$focus_element = array('find_user', 'form[username]');
	define('PUN_ACTIVE_PAGE', 'admin');
	require PUN_ROOT.'header.php';

	generate_admin_menu('users');

?>
	<div class="blockform">
		<h2><span><?php echo $lang_admin_users['User search head'] ?></span></h2>
		<div class="box">
			<form id="find_user" method="get" action="admin_users.php">
				<p class="submittop"><input type="submit" name="find_user" value="<?php echo $lang_admin_users['Submit search'] ?>" tabindex="1" /></p>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_users['User search subhead'] ?></legend>
						<div class="infldset">
							<p><?php echo $lang_admin_users['User search info'] ?></p>
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang_admin_users['Username label'] ?></th>
									<td><input type="text" name="form[username]" size="25" maxlength="25" tabindex="2" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_users['E-mail address label'] ?></th>
									<td><input type="text" name="form[email]" size="30" maxlength="80" tabindex="3" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_users['Title label'] ?></th>
									<td><input type="text" name="form[title]" size="30" maxlength="50" tabindex="4" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_users['Real name label'] ?></th>
									<td><input type="text" name="form[realname]" size="30" maxlength="40" tabindex="5" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_users['Website label'] ?></th>
									<td><input type="text" name="form[url]" size="35" maxlength="100" tabindex="6" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_users['Jabber label'] ?></th>
									<td><input type="text" name="form[jabber]" size="30" maxlength="75" tabindex="7" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_users['ICQ label'] ?></th>
									<td><input type="text" name="form[icq]" size="12" maxlength="12" tabindex="8" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_users['MSN label'] ?></th>
									<td><input type="text" name="form[msn]" size="30" maxlength="50" tabindex="9" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_users['AOL label'] ?></th>
									<td><input type="text" name="form[aim]" size="20" maxlength="20" tabindex="10" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_users['Yahoo label'] ?></th>
									<td><input type="text" name="form[yahoo]" size="20" maxlength="20" tabindex="11" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_users['Location label'] ?></th>
									<td><input type="text" name="form[location]" size="30" maxlength="30" tabindex="12" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_users['Signature label'] ?></th>
									<td><input type="text" name="form[signature]" size="35" maxlength="512" tabindex="13" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_users['Admin note label'] ?></th>
									<td><input type="text" name="form[admin_note]" size="30" maxlength="30" tabindex="14" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_users['Posts more than label'] ?></th>
									<td><input type="text" name="posts_greater" size="5" maxlength="8" tabindex="15" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_users['Posts less than label'] ?></th>
									<td><input type="text" name="posts_less" size="5" maxlength="8" tabindex="16" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_users['Last post after label'] ?></th>
									<td><input type="text" name="last_post_after" size="24" maxlength="19" tabindex="17" />
									<span><?php echo $lang_admin_users['Date help'] ?></span></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_users['Last post before label'] ?></th>
									<td><input type="text" name="last_post_before" size="24" maxlength="19" tabindex="18" />
									<span><?php echo $lang_admin_users['Date help'] ?></span></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_users['Registered after label'] ?></th>
									<td><input type="text" name="registered_after" size="24" maxlength="19" tabindex="19" />
									<span><?php echo $lang_admin_users['Date help'] ?></span></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_users['Registered before label'] ?></th>
									<td><input type="text" name="registered_before" size="24" maxlength="19" tabindex="20" />
									<span><?php echo $lang_admin_users['Date help'] ?></span></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_users['Order by label'] ?></th>
									<td>
										<select name="order_by" tabindex="21">
											<option value="username" selected="selected"><?php echo $lang_admin_users['Order by username'] ?></option>
											<option value="email"><?php echo $lang_admin_users['Order by e-mail'] ?></option>
											<option value="num_posts"><?php echo $lang_admin_users['Order by posts'] ?></option>
											<option value="last_post"><?php echo $lang_admin_users['Order by last post'] ?></option>
											<option value="registered"><?php echo $lang_admin_users['Order by registered'] ?></option>
										</select>&#160;&#160;&#160;<select name="direction" tabindex="22">
											<option value="ASC" selected="selected"><?php echo $lang_admin_users['Ascending'] ?></option>
											<option value="DESC"><?php echo $lang_admin_users['Descending'] ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_users['User group label'] ?></th>
									<td>
										<select name="user_group" tabindex="23">
											<option value="-1" selected="selected"><?php echo $lang_admin_users['All groups'] ?></option>
											<option value="0"><?php echo $lang_admin_users['Unverified users'] ?></option>
<?php

	$result = $db->query('SELECT g_id, g_title FROM '.$db->prefix.'groups WHERE g_id!='.PUN_GUEST.' ORDER BY g_title') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());

	while ($cur_group = $db->fetch_assoc($result))
		echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";

?>
										</select>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="find_user" value="<?php echo $lang_admin_users['Submit search'] ?>" tabindex="25" /></p>
			</form>
		</div>

		<h2 class="block2"><span><?php echo $lang_admin_users['IP search head'] ?></span></h2>
		<div class="box">
			<form method="get" action="admin_users.php">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_users['IP search subhead'] ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang_admin_users['IP address label'] ?><div><input type="submit" value="<?php echo $lang_admin_users['Find IP address'] ?>" tabindex="26" /></div></th>
									<td><input type="text" name="show_users" size="18" maxlength="15" tabindex="24" />
									<span><?php echo $lang_admin_users['IP address help'] ?></span></td>
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
