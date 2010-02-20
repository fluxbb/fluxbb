<?php

/**
 * Copyright (C) 2008-2010 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Tell header.php to use the admin template
define('PUN_ADMIN_CONSOLE', 1);

define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/common_admin.php';


if ($pun_user['g_id'] != PUN_ADMIN && ($pun_user['g_moderator'] != '1' || $pun_user['g_mod_ban_users'] == '0'))
	message($lang_common['No permission']);

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
			$add_ban = intval($_GET['add_ban']);
			if ($add_ban < 2)
				message($lang_common['Bad request']);

			$user_id = $add_ban;

			$result = $db->query('SELECT group_id, username, email FROM '.$db->prefix.'users WHERE id='.$user_id) or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());
			if ($db->num_rows($result))
				list($group_id, $ban_user, $ban_email) = $db->fetch_row($result);
			else
				message('No user by that ID registered.');
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

		// Make sure we're not banning an admin
		if (isset($group_id) && $group_id == PUN_ADMIN)
			message(sprintf($lang_admin_bans['User is admin message'], pun_htmlspecialchars($ban_user)));

		// If we have a $user_id, we can try to find the last known IP of that user
		if (isset($user_id))
		{
			$result = $db->query('SELECT poster_ip FROM '.$db->prefix.'posts WHERE poster_id='.$user_id.' ORDER BY posted DESC LIMIT 1') or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
			$ban_ip = ($db->num_rows($result)) ? $db->result($result) : '';
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

		$ban_expire = ($ban_expire != '') ? date('Y-m-d', $ban_expire) : '';

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
										<span><?php echo $lang_admin_bans['IP help'] ?><?php if ($ban_user != '' && isset($user_id)) printf($lang_admin_bans['IP help link'], '<a href="admin_users.php?ip_stats='.$user_id.'">'.$lang_admin_common['here'].'</a>') ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_bans['E-mail label'] ?></th>
									<td>
										<input type="text" name="ban_email" size="40" maxlength="80" value="<?php if (isset($ban_email)) echo strtolower($ban_email); ?>" tabindex="3" />
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
	$ban_ip = trim($_POST['ban_ip']);
	$ban_email = strtolower(trim($_POST['ban_email']));
	$ban_message = pun_trim($_POST['ban_message']);
	$ban_expire = trim($_POST['ban_expire']);

	if ($ban_user == '' && $ban_ip == '' && $ban_email == '')
		message($lang_admin_bans['Must enter message']);
	else if (strtolower($ban_user) == 'guest')
		message($lang_admin_bans['Cannot ban guest message']);

	// Validate IP/IP range (it's overkill, I know)
	if ($ban_ip != '')
	{
		$ban_ip = preg_replace('/[\s]{2,}/', ' ', $ban_ip);
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

					if ($c > 3 || preg_match('/[^0-9]/', $octets[$c]) || intval($octets[$c]) > 255)
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
		if (!preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/', $ban_email))
			message($lang_admin_bans['Invalid e-mail message']);
	}

	if ($ban_expire != '' && $ban_expire != 'Never')
	{
		$ban_expire = strtotime($ban_expire);

		if ($ban_expire == -1 || $ban_expire <= time())
			message($lang_admin_bans['Invalid date message']);
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

		<h2 class="block2"><span><?php echo $lang_admin_bans['Existing bans head'] ?></span></h2>
		<div class="box">
			<div class="fakeform">
<?php

$result = $db->query('SELECT b.id, b.username, b.ip, b.email, b.message, b.expire, b.ban_creator, u.username AS ban_creator_username FROM '.$db->prefix.'bans AS b LEFT JOIN '.$db->prefix.'users AS u ON b.ban_creator=u.id ORDER BY b.id') or error('Unable to fetch ban list', __FILE__, __LINE__, $db->error());
if ($db->num_rows($result))
{
	while ($cur_ban = $db->fetch_assoc($result))
	{
		$expire = format_time($cur_ban['expire'], true);

?>
				<div class="inform">
					<fieldset>
						<legend><?php printf($lang_admin_bans['Existing ban subhead'], $expire) ?></legend>
						<div class="infldset">
							<table cellspacing="0">
<?php if ($cur_ban['username'] != ''): ?>								<tr>
									<th><?php echo $lang_admin_bans['Username label'] ?></th>
									<td><?php echo pun_htmlspecialchars($cur_ban['username']) ?></td>
								</tr>
<?php endif; ?><?php if ($cur_ban['email'] != ''): ?>								<tr>
									<th><?php echo $lang_admin_bans['E-mail label'] ?></th>
									<td><?php echo $cur_ban['email'] ?></td>
								</tr>
<?php endif; ?><?php if ($cur_ban['ip'] != ''): ?>								<tr>
									<th><?php echo $lang_admin_bans['IP label'] ?></th>
									<td><?php echo $cur_ban['ip'] ?></td>
								</tr>
<?php endif; ?><?php if ($cur_ban['message'] != ''): ?>								<tr>
									<th><?php echo $lang_admin_bans['Reason label'] ?></th>
									<td><?php echo pun_htmlspecialchars($cur_ban['message']) ?></td>
								</tr>
<?php endif; ?><?php if (!empty($cur_ban['ban_creator_username'])): ?>								<tr>
									<th><?php echo $lang_admin_bans['Banned by label'] ?></th>
									<td><a href="profile.php?id=<?php echo $cur_ban['ban_creator'] ?>"><?php echo pun_htmlspecialchars($cur_ban['ban_creator_username']) ?></a></td>
								</tr>
<?php endif; ?>							</table>
							<p class="linkactions"><a href="admin_bans.php?edit_ban=<?php echo $cur_ban['id'] ?>"><?php echo $lang_admin_common['Edit'] ?></a> | <a href="admin_bans.php?del_ban=<?php echo $cur_ban['id'] ?>"><?php echo $lang_admin_common['Remove'] ?></a></p>
						</div>
					</fieldset>
				</div>
<?php

	}
}
else

{

?>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_bans['Ban expires'] ?></legend>
						<div class="infldset">
							<p><?php echo $lang_admin_bans['No bans in list'] ?></p>
						</div>
					</fieldset>
				</div>
<?php

}

?>
			</div>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

require PUN_ROOT.'footer.php';
