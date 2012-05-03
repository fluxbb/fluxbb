<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Tell header.php to use the admin template
define('PUN_ADMIN_CONSOLE', 1);

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/common_admin.php';


if ($pun_user['g_id'] != PUN_ADMIN && ($pun_user['g_moderator'] != '1' || $pun_user['g_mod_ban_users'] == '0'))
	message($lang_common['No permission'], false, '403 Forbidden');

// Load the admin_bans.php language file
require PUN_ROOT.'lang/'.$admin_language.'/admin_bans.php';

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
				message($lang_common['Bad request']);

			$result = $db->query('SELECT group_id, username, email FROM '.$db->prefix.'users WHERE id='.$user_id) or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());
			if ($db->num_rows($result))
				list($group_id, $ban_user, $ban_email) = $db->fetch_row($result);
			else
				message($lang_admin_bans['No user ID message']);
		}
		else // Otherwise the username is in POST
		{
			$ban_user = pun_trim($_POST['new_ban_user']);

			if ($ban_user != '')
			{
				$result = $db->query('SELECT id, group_id, username, email FROM '.$db->prefix.'users WHERE username=\''.$db->escape($ban_user).'\' AND id>1') or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());
				if ($db->num_rows($result))
					list($user_id, $group_id, $ban_user, $ban_email) = $db->fetch_row($result);
				else
					message($lang_admin_bans['No user message']);
			}
		}

		// Make sure we're not banning an admin or moderator
		if (isset($group_id))
		{
			if ($group_id == PUN_ADMIN)
				message(sprintf($lang_admin_bans['User is admin message'], pun_htmlspecialchars($ban_user)));

			$result = $db->query('SELECT g_moderator FROM '.$db->prefix.'groups WHERE g_id='.$group_id) or error('Unable to fetch group info', __FILE__, __LINE__, $db->error());
			$is_moderator_group = $db->result($result);

			if ($is_moderator_group)
				message(sprintf($lang_admin_bans['User is mod message'], pun_htmlspecialchars($ban_user)));
		}

		// If we have a $user_id, we can try to find the last known IP of that user
		if (isset($user_id))
		{
			$result = $db->query('SELECT poster_ip FROM '.$db->prefix.'posts WHERE poster_id='.$user_id.' ORDER BY posted DESC LIMIT 1') or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
			$ban_ip = ($db->num_rows($result)) ? $db->result($result) : '';

			if ($ban_ip == '')
			{
				$result = $db->query('SELECT registration_ip FROM '.$db->prefix.'users WHERE id='.$user_id) or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());
				$ban_ip = ($db->num_rows($result)) ? $db->result($result) : '';
			}
		}

		$mode = 'add';
	}
	else // We are editing a ban
	{
		$ban_id = intval($_GET['edit_ban']);
		if ($ban_id < 1)
			message($lang_common['Bad request']);

		$result = $db->query('SELECT username, ip, email, message, expire FROM '.$db->prefix.'bans WHERE id='.$ban_id) or error('Unable to fetch ban info', __FILE__, __LINE__, $db->error());
		if ($db->num_rows($result))
			list($ban_user, $ban_ip, $ban_email, $ban_message, $ban_expire) = $db->fetch_row($result);
		else
			message($lang_common['Bad request']);

		$diff = ($pun_user['timezone'] + $pun_user['dst']) * 3600;
		$ban_expire = ($ban_expire != '') ? gmdate('Y-m-d', $ban_expire + $diff) : '';

		$mode = 'edit';
	}

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_common['Admin'], $lang_admin_common['Bans']);
	$focus_element = array('bans2', 'ban_user');
	define('PUN_ACTIVE_PAGE', 'admin');
	require PUN_ROOT.'header.php';

	generate_admin_menu('bans');

?>
	<div class="blockform">
		<h2><span><?php echo $lang_admin_bans['Ban advanced head'] ?></span></h2>
		<div class="box">
			<form id="bans2" method="post" action="admin_bans.php">
				<div class="inform">
				<input type="hidden" name="mode" value="<?php echo $mode ?>" />
<?php if ($mode == 'edit'): ?>				<input type="hidden" name="ban_id" value="<?php echo $ban_id ?>" />
<?php endif; ?>				<fieldset>
						<legend><?php echo $lang_admin_bans['Ban advanced subhead'] ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang_admin_bans['Username label'] ?></th>
									<td>
										<input type="text" name="ban_user" size="25" maxlength="25" value="<?php if (isset($ban_user)) echo pun_htmlspecialchars($ban_user); ?>" tabindex="1" />
										<span><?php echo $lang_admin_bans['Username help'] ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_bans['IP label'] ?></th>
									<td>
										<input type="text" name="ban_ip" size="45" maxlength="255" value="<?php if (isset($ban_ip)) echo $ban_ip; ?>" tabindex="2" />
										<span><?php echo $lang_admin_bans['IP help'] ?><?php if ($ban_user != '' && isset($user_id)) printf(' '.$lang_admin_bans['IP help link'], '<a href="admin_users.php?ip_stats='.$user_id.'">'.$lang_admin_common['here'].'</a>') ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_bans['E-mail label'] ?></th>
									<td>
										<input type="text" name="ban_email" size="40" maxlength="80" value="<?php if (isset($ban_email)) echo $ban_email; ?>" tabindex="3" />
										<span><?php echo $lang_admin_bans['E-mail help'] ?></span>
									</td>
								</tr>
							</table>
							<p class="topspace"><strong class="warntext"><?php echo $lang_admin_bans['Ban IP range info'] ?></strong></p>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_bans['Message expiry subhead'] ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang_admin_bans['Ban message label'] ?></th>
									<td>
										<input type="text" name="ban_message" size="50" maxlength="255" value="<?php if (isset($ban_message)) echo pun_htmlspecialchars($ban_message); ?>" tabindex="4" />
										<span><?php echo $lang_admin_bans['Ban message help'] ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_bans['Expire date label'] ?></th>
									<td>
										<input type="text" name="ban_expire" size="17" maxlength="10" value="<?php if (isset($ban_expire)) echo $ban_expire; ?>" tabindex="5" />
										<span><?php echo $lang_admin_bans['Expire date help'] ?></span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="add_edit_ban" value="<?php echo $lang_admin_common['Save'] ?>" tabindex="6" /></p>
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
	$ban_ip = pun_trim($_POST['ban_ip']);
	$ban_email = strtolower(pun_trim($_POST['ban_email']));
	$ban_message = pun_trim($_POST['ban_message']);
	$ban_expire = pun_trim($_POST['ban_expire']);

	if ($ban_user == '' && $ban_ip == '' && $ban_email == '')
		message($lang_admin_bans['Must enter message']);
	else if (strtolower($ban_user) == 'guest')
		message($lang_admin_bans['Cannot ban guest message']);

	// Make sure we're not banning an admin or moderator
	if (!empty($ban_user))
	{
		$result = $db->query('SELECT group_id FROM '.$db->prefix.'users WHERE username=\''.$db->escape($ban_user).'\' AND id>1') or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());
		if ($db->num_rows($result))
		{
			$group_id = $db->result($result);

			if ($group_id == PUN_ADMIN)
				message(sprintf($lang_admin_bans['User is admin message'], pun_htmlspecialchars($ban_user)));

			$result = $db->query('SELECT g_moderator FROM '.$db->prefix.'groups WHERE g_id='.$group_id) or error('Unable to fetch group info', __FILE__, __LINE__, $db->error());
			$is_moderator_group = $db->result($result);

			if ($is_moderator_group)
				message(sprintf($lang_admin_bans['User is mod message'], pun_htmlspecialchars($ban_user)));
		}
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
						message($lang_admin_bans['Invalid IP message']);
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
						message($lang_admin_bans['Invalid IP message']);
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
			message($lang_admin_bans['Invalid e-mail message']);
	}

	if ($ban_expire != '' && $ban_expire != 'Never')
	{
		$ban_expire = strtotime($ban_expire.' GMT');

		if ($ban_expire == -1 || !$ban_expire)
			message($lang_admin_bans['Invalid date message'].' '.$lang_admin_bans['Invalid date reasons']);

		$diff = ($pun_user['timezone'] + $pun_user['dst']) * 3600;
		$ban_expire -= $diff;

		if ($ban_expire <= time())
			message($lang_admin_bans['Invalid date message'].' '.$lang_admin_bans['Invalid date reasons']);
	}
	else
		$ban_expire = 'NULL';

	$ban_user = ($ban_user != '') ? '\''.$db->escape($ban_user).'\'' : 'NULL';
	$ban_ip = ($ban_ip != '') ? '\''.$db->escape($ban_ip).'\'' : 'NULL';
	$ban_email = ($ban_email != '') ? '\''.$db->escape($ban_email).'\'' : 'NULL';
	$ban_message = ($ban_message != '') ? '\''.$db->escape($ban_message).'\'' : 'NULL';

	if ($_POST['mode'] == 'add')
		$db->query('INSERT INTO '.$db->prefix.'bans (username, ip, email, message, expire, ban_creator) VALUES('.$ban_user.', '.$ban_ip.', '.$ban_email.', '.$ban_message.', '.$ban_expire.', '.$pun_user['id'].')') or error('Unable to add ban', __FILE__, __LINE__, $db->error());
	else
		$db->query('UPDATE '.$db->prefix.'bans SET username='.$ban_user.', ip='.$ban_ip.', email='.$ban_email.', message='.$ban_message.', expire='.$ban_expire.' WHERE id='.intval($_POST['ban_id'])) or error('Unable to update ban', __FILE__, __LINE__, $db->error());

	// Regenerate the bans cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';

	generate_bans_cache();

	if ($_POST['mode'] == 'edit')
		redirect('admin_bans.php', $lang_admin_bans['Ban edited redirect']);
	else
		redirect('admin_bans.php', $lang_admin_bans['Ban added redirect']);
}

// Remove a ban
else if (isset($_GET['del_ban']))
{
	confirm_referrer('admin_bans.php');

	$ban_id = intval($_GET['del_ban']);
	if ($ban_id < 1)
		message($lang_common['Bad request']);

	$db->query('DELETE FROM '.$db->prefix.'bans WHERE id='.$ban_id) or error('Unable to delete ban', __FILE__, __LINE__, $db->error());

	// Regenerate the bans cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';

	generate_bans_cache();

	redirect('admin_bans.php', $lang_admin_bans['Ban removed redirect']);
}

// Find bans
else if (isset($_GET['find_ban']))
{
	$form = isset($_GET['form']) ? $_GET['form'] : array();

	// trim() all elements in $form
	$form = array_map('pun_trim', $form);
	$conditions = $query_str = array();

	$expire_after = isset($_GET['expire_after']) ? pun_trim($_GET['expire_after']) : '';
	$expire_before = isset($_GET['expire_before']) ? pun_trim($_GET['expire_before']) : '';
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
			message($lang_admin_bans['Invalid date message']);

		$conditions[] = 'b.expire>'.$expire_after;
	}
	if ($expire_before != '')
	{
		$query_str[] = 'expire_before='.$expire_before;

		$expire_before = strtotime($expire_before);
		if ($expire_before === false || $expire_before == -1)
			message($lang_admin_bans['Invalid date message']);

		$conditions[] = 'b.expire<'.$expire_before;
	}

	$like_command = ($db_type == 'pgsql') ? 'ILIKE' : 'LIKE';
	foreach ($form as $key => $input)
	{
		if ($input != '' && in_array($key, array('username', 'ip', 'email', 'message')))
		{
			$conditions[] = 'b.'.$db->escape($key).' '.$like_command.' \''.$db->escape(str_replace('*', '%', $input)).'\'';
			$query_str[] = 'form%5B'.$key.'%5D='.urlencode($input);
		}
	}

	// Fetch ban count
	$result = $db->query('SELECT COUNT(id) FROM '.$db->prefix.'bans as b WHERE b.id>0'.(!empty($conditions) ? ' AND '.implode(' AND ', $conditions) : '')) or error('Unable to fetch ban list', __FILE__, __LINE__, $db->error());
	$num_bans = $db->result($result);

	// Determine the ban offset (based on $_GET['p'])
	$num_pages = ceil($num_bans / 50);

	$p = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $num_pages) ? 1 : intval($_GET['p']);
	$start_from = 50 * ($p - 1);

	// Generate paging links
	$paging_links = '<span class="pages-label">'.$lang_common['Pages'].' </span>'.paginate($num_pages, $p, 'admin_bans.php?find_ban=&amp;'.implode('&amp;', $query_str));

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_common['Admin'], $lang_admin_common['Bans'], $lang_admin_bans['Results head']);
	define('PUN_ACTIVE_PAGE', 'admin');
	require PUN_ROOT.'header.php';

?>
<div class="linkst">
	<div class="inbox crumbsplus">
		<ul class="crumbs">
			<li><a href="admin_index.php"><?php echo $lang_admin_common['Admin'].' '.$lang_admin_common['Index'] ?></a></li>
			<li><span>»&#160;</span><a href="admin_bans.php"><?php echo $lang_admin_common['Bans'] ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang_admin_bans['Results head'] ?></strong></li>
		</ul>
		<div class="pagepost">
			<p class="pagelink"><?php echo $paging_links ?></p>
		</div>
		<div class="clearer"></div>
	</div>
</div>


<div id="bans1" class="blocktable">
	<h2><span><?php echo $lang_admin_bans['Results head'] ?></span></h2>
	<div class="box">
		<div class="inbox">
			<table cellspacing="0">
			<thead>
				<tr>
					<th class="tcl" scope="col"><?php echo $lang_admin_bans['Results username head'] ?></th>
					<th class="tc2" scope="col"><?php echo $lang_admin_bans['Results e-mail head'] ?></th>
					<th class="tc3" scope="col"><?php echo $lang_admin_bans['Results IP address head'] ?></th>
					<th class="tc4" scope="col"><?php echo $lang_admin_bans['Results expire head'] ?></th>
					<th class="tc5" scope="col"><?php echo $lang_admin_bans['Results message head'] ?></th>
					<th class="tc6" scope="col"><?php echo $lang_admin_bans['Results banned by head'] ?></th>
					<th class="tcr" scope="col"><?php echo $lang_admin_bans['Results actions head'] ?></th>
				</tr>
			</thead>
			<tbody>
<?php

	$result = $db->query('SELECT b.id, b.username, b.ip, b.email, b.message, b.expire, b.ban_creator, u.username AS ban_creator_username FROM '.$db->prefix.'bans AS b LEFT JOIN '.$db->prefix.'users AS u ON b.ban_creator=u.id WHERE b.id>0'.(!empty($conditions) ? ' AND '.implode(' AND ', $conditions) : '').' ORDER BY '.$db->escape($order_by).' '.$db->escape($direction).' LIMIT '.$start_from.', 50') or error('Unable to fetch ban list', __FILE__, __LINE__, $db->error());
	if ($db->num_rows($result))
	{
		while ($ban_data = $db->fetch_assoc($result))
		{

			$actions = '<a href="admin_bans.php?edit_ban='.$ban_data['id'].'">'.$lang_admin_common['Edit'].'</a> | <a href="admin_bans.php?del_ban='.$ban_data['id'].'">'.$lang_admin_common['Remove'].'</a>';
			$expire = format_time($ban_data['expire'], true);

?>
				<tr>
					<td class="tcl"><?php echo ($ban_data['username'] != '') ? pun_htmlspecialchars($ban_data['username']) : '&#160;' ?></td>
					<td class="tc2"><?php echo ($ban_data['email'] != '') ? $ban_data['email'] : '&#160;' ?></td>
					<td class="tc3"><?php echo ($ban_data['ip'] != '') ? $ban_data['ip'] : '&#160;' ?></td>
					<td class="tc4"><?php echo $expire ?></td>
					<td class="tc5"><?php echo ($ban_data['message'] != '') ? pun_htmlspecialchars($ban_data['message']) : '&#160;' ?></td>
					<td class="tc6"><?php echo ($ban_data['ban_creator_username'] != '') ? '<a href="profile.php?id='.$ban_data['ban_creator'].'">'.pun_htmlspecialchars($ban_data['ban_creator_username']).'</a>' : $lang_admin_bans['Unknown'] ?></td>
					<td class="tcr"><?php echo $actions ?></td>
				</tr>
<?php

		}
	}
	else
		echo "\t\t\t\t".'<tr><td class="tcl" colspan="7">'.$lang_admin_bans['No match'].'</td></tr>'."\n";

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
			<li><span>»&#160;</span><a href="admin_bans.php"><?php echo $lang_admin_common['Bans'] ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang_admin_bans['Results head'] ?></strong></li>
		</ul>
		<div class="clearer"></div>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}

$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_common['Admin'], $lang_admin_common['Bans']);
$focus_element = array('bans', 'new_ban_user');
define('PUN_ACTIVE_PAGE', 'admin');
require PUN_ROOT.'header.php';

generate_admin_menu('bans');

?>
	<div class="blockform">
		<h2><span><?php echo $lang_admin_bans['New ban head'] ?></span></h2>
		<div class="box">
			<form id="bans" method="post" action="admin_bans.php?action=more">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_bans['Add ban subhead'] ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang_admin_bans['Username label'] ?><div><input type="submit" name="add_ban" value="<?php echo $lang_admin_common['Add'] ?>" tabindex="2" /></div></th>
									<td>
										<input type="text" name="new_ban_user" size="25" maxlength="25" tabindex="1" />
										<span><?php echo $lang_admin_bans['Username advanced help'] ?></span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
			</form>
		</div>

		<h2 class="block2"><span><?php echo $lang_admin_bans['Ban search head'] ?></span></h2>
		<div class="box">
			<form id="find_band" method="get" action="admin_bans.php">
				<p class="submittop"><input type="submit" name="find_ban" value="<?php echo $lang_admin_bans['Submit search'] ?>" tabindex="3" /></p>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_bans['Ban search subhead'] ?></legend>
						<div class="infldset">
							<p><?php echo $lang_admin_bans['Ban search info'] ?></p>
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang_admin_bans['Username label'] ?></th>
									<td><input type="text" name="form[username]" size="25" maxlength="25" tabindex="4" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_bans['IP label'] ?></th>
									<td><input type="text" name="form[ip]" size="30" maxlength="255" tabindex="5" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_bans['E-mail label'] ?></th>
									<td><input type="text" name="form[email]" size="30" maxlength="80" tabindex="6" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_bans['Message label'] ?></th>
									<td><input type="text" name="form[message]" size="30" maxlength="255" tabindex="7" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_bans['Expire after label'] ?></th>
									<td><input type="text" name="expire_after" size="10" maxlength="10" tabindex="8" />
									<span><?php echo $lang_admin_bans['Date help'] ?></span></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_bans['Expire before label'] ?></th>
									<td><input type="text" name="expire_before" size="10" maxlength="10" tabindex="9" />
									<span><?php echo $lang_admin_bans['Date help'] ?></span></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_bans['Order by label'] ?></th>
									<td>
										<select name="order_by" tabindex="10">
											<option value="username" selected="selected"><?php echo $lang_admin_bans['Order by username'] ?></option>
											<option value="ip"><?php echo $lang_admin_bans['Order by ip'] ?></option>
											<option value="email"><?php echo $lang_admin_bans['Order by e-mail'] ?></option>
											<option value="expire"><?php echo $lang_admin_bans['Order by expire'] ?></option>
										</select>&#160;&#160;&#160;<select name="direction" tabindex="11">
											<option value="ASC" selected="selected"><?php echo $lang_admin_bans['Ascending'] ?></option>
											<option value="DESC"><?php echo $lang_admin_bans['Descending'] ?></option>
										</select>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="find_ban" value="<?php echo $lang_admin_bans['Submit search'] ?>" tabindex="12" /></p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

require PUN_ROOT.'footer.php';
