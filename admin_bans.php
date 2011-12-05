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


if ($pun_user['g_id'] != PUN_ADMIN && ($pun_user['g_moderator'] != '1' || $pun_user['g_mod_ban_users'] == '0'))
	message($lang->t('No permission'));

// Load the admin_bans.php language file
$lang->load('admin_bans');

// Add/edit a ban (stage 1)
if (isset($_REQUEST['add_ban']) || isset($_GET['edit_ban']))
{
	if (isset($_GET['add_ban']) || isset($_POST['add_ban']))
	{
		// If the ID of the user to ban was provided through GET (a link from profile.php)
		if (isset($_GET['add_ban']))
		{
			$user_id = intval($_GET['add_ban']);
			if ($user_id < 2)
				message($lang->t('Bad request'));

			$query = $db->select(array('group_id' => 'u.group_id', 'username' => 'u.username', 'email' => 'u.email'), 'users AS u');
			$query->where = 'id = :user_id';

			$params = array(':user_id' => $user_id);

			$result = $query->run($params);
			if (empty($result))
				message($lang->t('No user ID message'));

			$group_id = $result[0]['group_id'];
			$ban_user = $result[0]['username'];
			$ban_email = $result[0]['email'];

			unset ($result, $query, $params);
		}
		else // Otherwise the username is in POST
		{
			$ban_user = pun_trim($_POST['new_ban_user']);

			if (!empty($ban_user))
			{
				$query = $db->select(array('id' => 'u.id', 'group_id' => 'u.group_id', 'username' => 'u.username', 'email' => 'u.email'), 'users AS u');
				$query->where = 'username = :ban_user AND id > 1';

				$params = array(':ban_user' => $ban_user);

				$result = $query->run($params);
				if (empty($result))
					message($lang->t('No user message'));

				$user_id = $result[0]['id'];
				$group_id = $result[0]['group_id'];
				$ban_user = $result[0]['username'];
				$ban_email = $result[0]['email'];

				unset ($result, $query, $params);
			}
		}

		// Make sure we're not banning an admin or moderator
		if (isset($group_id))
		{
			if ($group_id == PUN_ADMIN)
				message($lang->t('User is admin message', pun_htmlspecialchars($ban_user)));

			$query = $db->select(array('g_moderator' => 'g.g_moderator'), 'groups AS g');
			$query->where = 'g.g_id = :group_id';

			$params = array(':group_id' => $group_id);

			$result = $query->run($params);
			$is_moderator_group = $result[0]['g_moderator'];
			unset ($result, $query, $params);

			if ($is_moderator_group)
				message($lang->t('User is mod message', pun_htmlspecialchars($ban_user)));
		}

		// If we have a $user_id, we can try to find the last known IP of that user
		if (isset($user_id))
		{
			$ban_ip = '';

			$query = $db->select(array('poster_ip' => 'p.poster_ip'), 'posts AS p');
			$query->where = 'p.poster_id = :user_id';
			$query->order = array('posted' => 'p.posted DESC');
			$query->limit = 1;

			$params = array(':user_id' => $user_id);

			$result = $query->run($params);
			if (!empty($result))
				$ban_ip = $result[0]['poster_ip'];

			unset ($result, $query, $params);

			if (empty($ban_ip))
			{
				$query = $db->select(array('registration_ip' => 'u.registration_ip'), 'users AS u');
				$query->where = 'u.id = :user_id';

				$params = array(':user_id' => $user_id);

				$result = $query->run($params);
				if (!empty($result))
					$ban_ip = $result[0]['registration_ip'];

				unset ($result, $query, $params);
			}
		}

		$mode = 'add';
	}
	else // We are editing a ban
	{
		$ban_id = intval($_GET['edit_ban']);
		if ($ban_id < 1)
			message($lang->t('Bad request'));

		$query = $db->select(array('username' => 'b.username', 'ip' => 'b.ip', 'email' => 'b.email', 'message' => 'b.message', 'expire' => 'b.expire'), 'bans AS b');
		$query->where = 'b.id = :ban_id';

		$params = array(':ban_id' => $ban_id);

		$result = $query->run($params);
		if (empty($result))
			message($lang->t('Bad request'));

		$ban_user = $result[0]['username'];
		$ban_ip = $result[0]['ip'];
		$ban_email = $result[0]['email'];
		$ban_message = $result[0]['message'];
		$ban_expire = $result[0]['expire'];

		unset ($result, $query, $params);

		$diff = ($pun_user['timezone'] + $pun_user['dst']) * 3600;
		$ban_expire = ($ban_expire != '') ? gmdate('Y-m-d', $ban_expire + $diff) : '';

		$mode = 'edit';
	}

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Admin'), $lang->t('Bans'));
	$focus_element = array('bans2', 'ban_user');
	define('PUN_ACTIVE_PAGE', 'admin');
	require PUN_ROOT.'header.php';

	generate_admin_menu('bans');

?>
	<div class="blockform">
		<h2><span><?php echo $lang->t('Ban advanced head') ?></span></h2>
		<div class="box">
			<form id="bans2" method="post" action="admin_bans.php">
				<div class="inform">
				<input type="hidden" name="mode" value="<?php echo $mode ?>" />
<?php if ($mode == 'edit'): ?>				<input type="hidden" name="ban_id" value="<?php echo $ban_id ?>" />
<?php endif; ?>				<fieldset>
						<legend><?php echo $lang->t('Ban advanced subhead') ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang->t('Username label') ?></th>
									<td>
										<input type="text" name="ban_user" size="25" maxlength="25" value="<?php if (isset($ban_user)) echo pun_htmlspecialchars($ban_user); ?>" tabindex="1" />
										<span><?php echo $lang->t('Username help') ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('IP label') ?></th>
									<td>
										<input type="text" name="ban_ip" size="45" maxlength="255" value="<?php if (isset($ban_ip)) echo $ban_ip; ?>" tabindex="2" />
										<span><?php echo $lang->t('IP help') ?><?php if ($ban_user != '' && isset($user_id)) echo ' '.$lang->t('IP help link', '<a href="admin_users.php?ip_stats='.$user_id.'">'.$lang->t('here').'</a>') ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Email label') ?></th>
									<td>
										<input type="text" name="ban_email" size="40" maxlength="80" value="<?php if (isset($ban_email)) echo $ban_email; ?>" tabindex="3" />
										<span><?php echo $lang->t('Email help') ?></span>
									</td>
								</tr>
							</table>
							<p class="topspace"><strong class="warntext"><?php echo $lang->t('Ban IP range info') ?></strong></p>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Message expiry subhead') ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang->t('Ban message label') ?></th>
									<td>
										<input type="text" name="ban_message" size="50" maxlength="255" value="<?php if (isset($ban_message)) echo pun_htmlspecialchars($ban_message); ?>" tabindex="4" />
										<span><?php echo $lang->t('Ban message help') ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Expire date label') ?></th>
									<td>
										<input type="text" name="ban_expire" size="17" maxlength="10" value="<?php if (isset($ban_expire)) echo $ban_expire; ?>" tabindex="5" />
										<span><?php echo $lang->t('Expire date help') ?></span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="add_edit_ban" value="<?php echo $lang->t('Save') ?>" tabindex="6" /></p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}

// Add/edit a ban (stage 2)
else if (isset($_POST['add_edit_ban']))
{
	confirm_referrer('admin_bans.php');

	$ban_user = pun_trim($_POST['ban_user']);
	$ban_ip = trim($_POST['ban_ip']);
	$ban_email = strtolower(trim($_POST['ban_email']));
	$ban_message = pun_trim($_POST['ban_message']);
	$ban_expire = trim($_POST['ban_expire']);

	if ($ban_user == '' && $ban_ip == '' && $ban_email == '')
		message($lang->t('Must enter message'));
	else if (strtolower($ban_user) == 'guest')
		message($lang->t('Cannot ban guest message'));

	// Make sure we're not banning an admin or moderator
	if (!empty($ban_user))
	{
		$query = $db->select(array('group_id' => 'u.group_id', 'g_moderator' => 'g.g_moderator'), 'users AS u');

		$query->innerJoin('g', 'groups AS g', 'g.g_id = u.group_id');

		$query->where = 'u.username = :ban_user AND u.id > 1';

		$params = array(':ban_user' => $ban_user);

		$result = $query->run($params);
		if (!empty($result))
		{
			if ($result[0]['group_id'] == PUN_ADMIN)
				message($lang->t('User is admin message', pun_htmlspecialchars($ban_user)));

			if ($result[0]['g_moderator'])
				message($lang->t('User is mod message', pun_htmlspecialchars($ban_user)));
		}

		unset ($result, $query, $params);
	}

	// Validate IP/IP range (it's overkill, I know)
	if ($ban_ip != '')
	{
		$ban_ip = preg_replace('%\s{2,}%S', ' ', $ban_ip);
		$addresses = explode(' ', $ban_ip);
		$addresses = array_map('pun_trim', $addresses);

		for ($i = 0; $i < count($addresses); ++$i)
		{
			if (strpos($addresses[$i], ':') !== false)
			{
				$octets = explode(':', $addresses[$i]);

				for ($c = 0; $c < count($octets); ++$c)
				{
					$octets[$c] = ltrim($octets[$c], "0");

					if ($c > 7 || (!empty($octets[$c]) && !ctype_xdigit($octets[$c])) || intval($octets[$c], 16) > 65535)
						message($lang->t('Invalid IP message'));
				}

				$cur_address = implode(':', $octets);
				$addresses[$i] = $cur_address;
			}
			else
			{
				$octets = explode('.', $addresses[$i]);

				for ($c = 0; $c < count($octets); ++$c)
				{
					$octets[$c] = (strlen($octets[$c]) > 1) ? ltrim($octets[$c], "0") : $octets[$c];

					if ($c > 3 || preg_match('%[^0-9]%', $octets[$c]) || intval($octets[$c]) > 255)
						message($lang->t('Invalid IP message'));
				}

				$cur_address = implode('.', $octets);
				$addresses[$i] = $cur_address;
			}
		}

		$ban_ip = implode(' ', $addresses);
	}

	require PUN_ROOT.'include/email.php';
	if ($ban_email != '' && !is_valid_email($ban_email))
	{
		if (!preg_match('%^[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$%', $ban_email))
			message($lang->t('Invalid email message'));
	}

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
		$ban_expire = null;

	$fields = array('username' => ':username', 'ip' => ':ip', 'email' => ':email', 'message' => ':message', 'expire' => ':expire');

	$params = array(
		':username'	=> empty($ban_user) ? null : $ban_user,
		':ip'		=> empty($ban_ip) ? null : $ban_ip,
		':email'	=> empty($ban_email) ? null : $ban_email,
		':message'	=> empty($ban_message) ? null : $ban_message,
		':expire'	=> $ban_expire,
	);

	if ($_POST['mode'] == 'add')
	{
		$query = $db->insert($fields, 'bans');
		$query->values['ban_creator'] = ':user_id';

		$params[':user_id'] = $pun_user['id'];

		$query->run($params);
		unset ($query, $params);
	}
	else
	{
		$query = $db->update($fields, 'bans');
		$query->where = 'id = :ban_id';

		$params[':ban_id'] = intval($_POST['ban_id']);

		$query->run($params);
		unset ($query, $params);
	}

	// Regenerate the bans cache
	$cache->delete('bans');

	if ($_POST['mode'] == 'edit')
		redirect('admin_bans.php', $lang->t('Ban edited redirect'));
	else
		redirect('admin_bans.php', $lang->t('Ban added redirect'));
}

// Remove a ban
else if (isset($_GET['del_ban']))
{
	confirm_referrer('admin_bans.php');

	$ban_id = intval($_GET['del_ban']);
	if ($ban_id < 1)
		message($lang->t('Bad request'));

	$query = $db->delete('bans');
	$query->where = 'id = :ban_id';

	$params = array(':ban_id' => $ban_id);

	$query->run($params);
	unset ($query, $params);

	// Regenerate the bans cache
	$cache->delete('bans');

	redirect('admin_bans.php', $lang->t('Ban removed redirect'));
}

// Find bans
else if (isset($_GET['find_ban']))
{
	$form = isset($_GET['form']) ? $_GET['form'] : array();

	// trim() all elements in $form
	$form = array_map('pun_trim', $form);
	$conditions = $query_str = array();

	$expire_after = isset($_GET['expire_after']) ? trim($_GET['expire_after']) : '';
	$expire_before = isset($_GET['expire_before']) ? trim($_GET['expire_before']) : '';
	$order_by = isset($_GET['order_by']) && in_array($_GET['order_by'], array('username', 'ip', 'email', 'expire')) ? 'b.'.$_GET['order_by'] : 'b.username';
	$direction = isset($_GET['direction']) && $_GET['direction'] == 'DESC' ? 'DESC' : 'ASC';

	$query_str[] = 'order_by='.$order_by;
	$query_str[] = 'direction='.$direction;

	// Try to convert date/time to timestamps
	if ($expire_after != '')
	{
		$query_str[] = 'expire_after='.$expire_after;

		$expire_after = strtotime($expire_after);
		if ($expire_after === false || $expire_after == -1)
			message($lang->t('Invalid date message'));

		$conditions[] = 'b.expire>'.$expire_after;
	}
	if ($expire_before != '')
	{
		$query_str[] = 'expire_before='.$expire_before;

		$expire_before = strtotime($expire_before);
		if ($expire_before === false || $expire_before == -1)
			message($lang->t('Invalid date message'));

		$conditions[] = 'b.expire<'.$expire_before;
	}

	$like_command = ($db_type == 'pgsql') ? 'ILIKE' : 'LIKE';
	foreach ($form as $key => $input)
	{
		if ($input != '' && in_array($key, array('username', 'ip', 'email', 'message')))
		{
			$conditions[] = 'b.'.$key.' '.$like_command.' '.$db->quote(str_replace('*', '%', $input));
			$query_str[] = 'form%5B'.$key.'%5D='.urlencode($input);
		}
	}

	// Fetch ban count
	$query = $db->select(array('count' => 'COUNT(b.id) AS num_bans'), 'bans as b');
	$query->where = 'b.id > 0'.(!empty($conditions) ? ' AND '.implode(' AND ', $conditions) : '');

	$result = $query->run();

	$num_bans = $result[0]['num_bans'];

	// Determine the ban offset (based on $_GET['p'])
	$num_pages = ceil($num_bans / 50);

	$p = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $num_pages) ? 1 : intval($_GET['p']);
	$start_from = 50 * ($p - 1);

	// Generate paging links
	$paging_links = '<span class="pages-label">'.$lang->t('Pages').' </span>'.paginate($num_pages, $p, 'admin_bans.php?find_ban=&amp;'.implode('&amp;', $query_str));

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Admin'), $lang->t('Bans'), $lang->t('Results head'));
	define('PUN_ACTIVE_PAGE', 'admin');
	require PUN_ROOT.'header.php';

?>
<div class="linkst">
	<div class="inbox crumbsplus">
		<ul class="crumbs">
			<li><a href="admin_index.php"><?php echo $lang->t('Admin').' '.$lang->t('Index') ?></a></li>
			<li><span>»&#160;</span><a href="admin_bans.php"><?php echo $lang->t('Bans') ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang->t('Results head') ?></strong></li>
		</ul>
		<div class="pagepost">
			<p class="pagelink"><?php echo $paging_links ?></p>
		</div>
		<div class="clearer"></div>
	</div>
</div>


<div id="bans1" class="blocktable">
	<h2><span><?php echo $lang->t('Results head') ?></span></h2>
	<div class="box">
		<div class="inbox">
			<table cellspacing="0">
			<thead>
				<tr>
					<th class="tcl" scope="col"><?php echo $lang->t('Results username head') ?></th>
					<th class="tc2" scope="col"><?php echo $lang->t('Results email head') ?></th>
					<th class="tc3" scope="col"><?php echo $lang->t('Results IP address head') ?></th>
					<th class="tc4" scope="col"><?php echo $lang->t('Results expire head') ?></th>
					<th class="tc5" scope="col"><?php echo $lang->t('Results message head') ?></th>
					<th class="tc6" scope="col"><?php echo $lang->t('Results banned by head') ?></th>
					<th class="tcr" scope="col"><?php echo $lang->t('Results actions head') ?></th>
				</tr>
			</thead>
			<tbody>
<?php

	$query = $db->select(array('id' => 'b.id', 'username' => 'b.username', 'ip' => 'b.ip', 'email' => 'b.email', 'message' => 'b.message', 'expire' => 'b.expire', 'ban_creator' => 'b.ban_creator', 'ban_creator_username' => 'u.username AS ban_creator_username'), 'bans AS b');
	$query->leftJoin('u', 'users AS u', 'b.ban_creator = u.id');
	$query->where = 'b.id > 0'.(!empty($conditions) ? ' AND '.implode(' AND ', $conditions) : '');
	$query->order = array('order' => $order_by.' '.$direction);
	$query->limit = '50';
	$query->offset = $start_from;

	$result = $query->run();

	if (!empty($result))
	{
		foreach ($result as $ban_data)
		{

			$actions = '<a href="admin_bans.php?edit_ban='.$ban_data['id'].'">'.$lang->t('Edit').'</a> | <a href="admin_bans.php?del_ban='.$ban_data['id'].'">'.$lang->t('Remove').'</a>';
			$expire = format_time($ban_data['expire'], true);

?>
				<tr>
					<td class="tcl"><?php echo ($ban_data['username'] != '') ? pun_htmlspecialchars($ban_data['username']) : '&#160;' ?></td>
					<td class="tc2"><?php echo ($ban_data['email'] != '') ? $ban_data['email'] : '&#160;' ?></td>
					<td class="tc3"><?php echo ($ban_data['ip'] != '') ? $ban_data['ip'] : '&#160;' ?></td>
					<td class="tc4"><?php echo $expire ?></td>
					<td class="tc5"><?php echo ($ban_data['message'] != '') ? pun_htmlspecialchars($ban_data['message']) : '&#160;' ?></td>
					<td class="tc6"><?php echo ($ban_data['ban_creator_username'] != '') ? '<a href="profile.php?id='.$ban_data['ban_creator'].'">'.pun_htmlspecialchars($ban_data['ban_creator_username']).'</a>' : $lang->t('Unknown') ?></td>
					<td class="tcr"><?php echo $actions ?></td>
				</tr>
<?php

		}
	}
	else
		echo "\t\t\t\t".'<tr><td class="tcl" colspan="7">'.$lang->t('No match').'</td></tr>'."\n";

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
		</div>
		<ul class="crumbs">
			<li><a href="admin_index.php"><?php echo $lang->t('Admin').' '.$lang->t('Index') ?></a></li>
			<li><span>»&#160;</span><a href="admin_bans.php"><?php echo $lang->t('Bans') ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang->t('Results head') ?></strong></li>
		</ul>
		<div class="clearer"></div>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}

$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Admin'), $lang->t('Bans'));
$focus_element = array('bans', 'new_ban_user');
define('PUN_ACTIVE_PAGE', 'admin');
require PUN_ROOT.'header.php';

generate_admin_menu('bans');

?>
	<div class="blockform">
		<h2><span><?php echo $lang->t('New ban head') ?></span></h2>
		<div class="box">
			<form id="bans" method="post" action="admin_bans.php?action=more">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Add ban subhead') ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang->t('Username label') ?><div><input type="submit" name="add_ban" value="<?php echo $lang->t('Add') ?>" tabindex="2" /></div></th>
									<td>
										<input type="text" name="new_ban_user" size="25" maxlength="25" tabindex="1" />
										<span><?php echo $lang->t('Username advanced help') ?></span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
			</form>
		</div>

		<h2 class="block2"><span><?php echo $lang->t('Ban search head') ?></span></h2>
		<div class="box">
			<form id="find_band" method="get" action="admin_bans.php">
				<p class="submittop"><input type="submit" name="find_ban" value="<?php echo $lang->t('Submit search') ?>" tabindex="3" /></p>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Ban search subhead') ?></legend>
						<div class="infldset">
							<p><?php echo $lang->t('Ban search info') ?></p>
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang->t('Username label') ?></th>
									<td><input type="text" name="form[username]" size="25" maxlength="25" tabindex="4" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('IP label') ?></th>
									<td><input type="text" name="form[ip]" size="30" maxlength="255" tabindex="5" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Email label') ?></th>
									<td><input type="text" name="form[email]" size="30" maxlength="80" tabindex="6" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Message label') ?></th>
									<td><input type="text" name="form[message]" size="30" maxlength="255" tabindex="7" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Expire after label') ?></th>
									<td><input type="text" name="expire_after" size="10" maxlength="10" tabindex="8" />
									<span><?php echo $lang->t('Date help') ?></span></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Expire before label') ?></th>
									<td><input type="text" name="expire_before" size="10" maxlength="10" tabindex="9" />
									<span><?php echo $lang->t('Date help') ?></span></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Order by label') ?></th>
									<td>
										<select name="order_by" tabindex="10">
											<option value="username" selected="selected"><?php echo $lang->t('Order by username') ?></option>
											<option value="ip"><?php echo $lang->t('Order by ip') ?></option>
											<option value="email"><?php echo $lang->t('Order by email') ?></option>
											<option value="expire"><?php echo $lang->t('Order by expire') ?></option>
										</select>&#160;&#160;&#160;<select name="direction" tabindex="11">
											<option value="ASC" selected="selected"><?php echo $lang->t('Ascending') ?></option>
											<option value="DESC"><?php echo $lang->t('Descending') ?></option>
										</select>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="find_ban" value="<?php echo $lang->t('Submit search') ?>" tabindex="12" /></p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

require PUN_ROOT.'footer.php';
