<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';

// Include UTF-8 function
require PUN_ROOT.'include/utf8/substr_replace.php';
require PUN_ROOT.'include/utf8/ucwords.php'; // utf8_ucwords needs utf8_substr_replace
require PUN_ROOT.'include/utf8/strcasecmp.php';

$action = isset($_GET['action']) ? $_GET['action'] : null;
$section = isset($_GET['section']) ? $_GET['section'] : null;
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id < 2)
	message($lang_common['Bad request'], false, '404 Not Found');

if ($action != 'change_pass' || !isset($_GET['key']))
{
	if ($pun_user['g_read_board'] == '0')
		message($lang_common['No view'], false, '403 Forbidden');
	else if ($pun_user['g_view_users'] == '0' && ($pun_user['is_guest'] || $pun_user['id'] != $id))
		message($lang_common['No permission'], false, '403 Forbidden');
}

// Load the profile.php/register.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/prof_reg.php';

// Load the profile.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/profile.php';


if ($action == 'change_pass')
{
	if (isset($_GET['key']))
	{
		// If the user is already logged in we shouldn't be here :)
		if (!$pun_user['is_guest'])
		{
			header('Location: index.php');
			exit;
		}

		$key = $_GET['key'];

		$result = $db->query('SELECT * FROM '.$db->prefix.'users WHERE id='.$id) or error('Unable to fetch new password', __FILE__, __LINE__, $db->error());
		$cur_user = $db->fetch_assoc($result);

		if ($key == '' || $key != $cur_user['activate_key'])
			message($lang_profile['Pass key bad'].' <a href="mailto:'.$pun_config['o_admin_email'].'">'.$pun_config['o_admin_email'].'</a>.');
		else
		{
			$db->query('UPDATE '.$db->prefix.'users SET password=\''.$cur_user['activate_string'].'\', activate_string=NULL, activate_key=NULL'.(!empty($cur_user['salt']) ? ', salt=NULL' : '').' WHERE id='.$id) or error('Unable to update password', __FILE__, __LINE__, $db->error());

			message($lang_profile['Pass updated'], true);
		}
	}

	// Make sure we are allowed to change this users password
	if ($pun_user['id'] != $id)
	{
		if (!$pun_user['is_admmod']) // A regular user trying to change another users password?
			message($lang_common['No permission'], false, '403 Forbidden');
		else if ($pun_user['g_moderator'] == '1') // A moderator trying to change a users password?
		{
			$result = $db->query('SELECT u.group_id, g.g_moderator FROM '.$db->prefix.'users AS u INNER JOIN '.$db->prefix.'groups AS g ON (g.g_id=u.group_id) WHERE u.id='.$id) or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());
			if (!$db->num_rows($result))
				message($lang_common['Bad request'], false, '404 Not Found');

			list($group_id, $is_moderator) = $db->fetch_row($result);

			if ($pun_user['g_mod_edit_users'] == '0' || $pun_user['g_mod_change_passwords'] == '0' || $group_id == PUN_ADMIN || $is_moderator == '1')
				message($lang_common['No permission'], false, '403 Forbidden');
		}
	}

	if (isset($_POST['form_sent']))
	{
		if ($pun_user['is_admmod'])
			confirm_referrer('profile.php');

		$old_password = isset($_POST['req_old_password']) ? pun_trim($_POST['req_old_password']) : '';
		$new_password1 = pun_trim($_POST['req_new_password1']);
		$new_password2 = pun_trim($_POST['req_new_password2']);

		if ($new_password1 != $new_password2)
			message($lang_prof_reg['Pass not match']);
		if (pun_strlen($new_password1) < 4)
			message($lang_prof_reg['Pass too short']);

		$result = $db->query('SELECT * FROM '.$db->prefix.'users WHERE id='.$id) or error('Unable to fetch password', __FILE__, __LINE__, $db->error());
		$cur_user = $db->fetch_assoc($result);

		$authorized = false;

		if (!empty($cur_user['password']))
		{
			$old_password_hash = pun_hash($old_password);

			if ($cur_user['password'] == $old_password_hash || $pun_user['is_admmod'])
				$authorized = true;
		}

		if (!$authorized)
			message($lang_profile['Wrong pass']);

		$new_password_hash = pun_hash($new_password1);

		$db->query('UPDATE '.$db->prefix.'users SET password=\''.$new_password_hash.'\''.(!empty($cur_user['salt']) ? ', salt=NULL' : '').' WHERE id='.$id) or error('Unable to update password', __FILE__, __LINE__, $db->error());

		if ($pun_user['id'] == $id)
			pun_setcookie($pun_user['id'], $new_password_hash, time() + $pun_config['o_timeout_visit']);

		redirect('profile.php?section=essentials&amp;id='.$id, $lang_profile['Pass updated redirect']);
	}

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_common['Profile'], $lang_profile['Change pass']);
	$required_fields = array('req_old_password' => $lang_profile['Old pass'], 'req_new_password1' => $lang_profile['New pass'], 'req_new_password2' => $lang_profile['Confirm new pass']);
	$focus_element = array('change_pass', ((!$pun_user['is_admmod']) ? 'req_old_password' : 'req_new_password1'));
	define('PUN_ACTIVE_PAGE', 'profile');
	require PUN_ROOT.'header.php';

?>
<div class="blockform">
	<h2><span><?php echo $lang_profile['Change pass'] ?></span></h2>
	<div class="box">
		<form id="change_pass" method="post" action="profile.php?action=change_pass&amp;id=<?php echo $id ?>" onsubmit="return process_form(this)">
			<div class="inform">
				<input type="hidden" name="form_sent" value="1" />
				<fieldset>
					<legend><?php echo $lang_profile['Change pass legend'] ?></legend>
					<div class="infldset">
<?php if (!$pun_user['is_admmod']): ?>						<label class="required"><strong><?php echo $lang_profile['Old pass'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br />
						<input type="password" name="req_old_password" size="16" /><br /></label>
<?php endif; ?>						<label class="conl required"><strong><?php echo $lang_profile['New pass'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br />
						<input type="password" name="req_new_password1" size="16" /><br /></label>
						<label class="conl required"><strong><?php echo $lang_profile['Confirm new pass'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br />
						<input type="password" name="req_new_password2" size="16" /><br /></label>
						<p class="clearb"><?php echo $lang_profile['Pass info'] ?></p>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="update" value="<?php echo $lang_common['Submit'] ?>" /> <a href="javascript:history.go(-1)"><?php echo $lang_common['Go back'] ?></a></p>
		</form>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


else if ($action == 'change_email')
{
	// Make sure we are allowed to change this users email
	if ($pun_user['id'] != $id)
	{
		if (!$pun_user['is_admmod']) // A regular user trying to change another users email?
			message($lang_common['No permission'], false, '403 Forbidden');
		else if ($pun_user['g_moderator'] == '1') // A moderator trying to change a users email?
		{
			$result = $db->query('SELECT u.group_id, g.g_moderator FROM '.$db->prefix.'users AS u INNER JOIN '.$db->prefix.'groups AS g ON (g.g_id=u.group_id) WHERE u.id='.$id) or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());
			if (!$db->num_rows($result))
				message($lang_common['Bad request'], false, '404 Not Found');

			list($group_id, $is_moderator) = $db->fetch_row($result);

			if ($pun_user['g_mod_edit_users'] == '0' || $group_id == PUN_ADMIN || $is_moderator == '1')
				message($lang_common['No permission'], false, '403 Forbidden');
		}
	}

	if (isset($_GET['key']))
	{
		$key = $_GET['key'];

		$result = $db->query('SELECT activate_string, activate_key FROM '.$db->prefix.'users WHERE id='.$id) or error('Unable to fetch activation data', __FILE__, __LINE__, $db->error());
		list($new_email, $new_email_key) = $db->fetch_row($result);

		if ($key == '' || $key != $new_email_key)
			message($lang_profile['Email key bad'].' <a href="mailto:'.$pun_config['o_admin_email'].'">'.$pun_config['o_admin_email'].'</a>.');
		else
		{
			$db->query('UPDATE '.$db->prefix.'users SET email=activate_string, activate_string=NULL, activate_key=NULL WHERE id='.$id) or error('Unable to update email address', __FILE__, __LINE__, $db->error());

			message($lang_profile['Email updated'], true);
		}
	}
	else if (isset($_POST['form_sent']))
	{
		if (pun_hash($_POST['req_password']) !== $pun_user['password'])
			message($lang_profile['Wrong pass']);

		require PUN_ROOT.'include/email.php';

		// Validate the email address
		$new_email = strtolower(pun_trim($_POST['req_new_email']));
		if (!is_valid_email($new_email))
			message($lang_common['Invalid email']);

		// Check if it's a banned email address
		if (is_banned_email($new_email))
		{
			if ($pun_config['p_allow_banned_email'] == '0')
				message($lang_prof_reg['Banned email']);
			else if ($pun_config['o_mailing_list'] != '')
			{
				// Load the "banned email change" template
				$mail_tpl = trim(file_get_contents(PUN_ROOT.'lang/'.$pun_user['language'].'/mail_templates/banned_email_change.tpl'));

				// The first row contains the subject
				$first_crlf = strpos($mail_tpl, "\n");
				$mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
				$mail_message = trim(substr($mail_tpl, $first_crlf));

				$mail_message = str_replace('<username>', $pun_user['username'], $mail_message);
				$mail_message = str_replace('<email>', $new_email, $mail_message);
				$mail_message = str_replace('<profile_url>', get_base_url().'/profile.php?id='.$id, $mail_message);
				$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message);

				pun_mail($pun_config['o_mailing_list'], $mail_subject, $mail_message);
			}
		}

		// Check if someone else already has registered with that email address
		$result = $db->query('SELECT id, username FROM '.$db->prefix.'users WHERE email=\''.$db->escape($new_email).'\'') or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());
		if ($db->num_rows($result))
		{
			if ($pun_config['p_allow_dupe_email'] == '0')
				message($lang_prof_reg['Dupe email']);
			else if ($pun_config['o_mailing_list'] != '')
			{
				while ($cur_dupe = $db->fetch_assoc($result))
					$dupe_list[] = $cur_dupe['username'];

				// Load the "dupe email change" template
				$mail_tpl = trim(file_get_contents(PUN_ROOT.'lang/'.$pun_user['language'].'/mail_templates/dupe_email_change.tpl'));

				// The first row contains the subject
				$first_crlf = strpos($mail_tpl, "\n");
				$mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
				$mail_message = trim(substr($mail_tpl, $first_crlf));

				$mail_message = str_replace('<username>', $pun_user['username'], $mail_message);
				$mail_message = str_replace('<dupe_list>', implode(', ', $dupe_list), $mail_message);
				$mail_message = str_replace('<profile_url>', get_base_url().'/profile.php?id='.$id, $mail_message);
				$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message);

				pun_mail($pun_config['o_mailing_list'], $mail_subject, $mail_message);
			}
		}


		$new_email_key = random_pass(8);

		$db->query('UPDATE '.$db->prefix.'users SET activate_string=\''.$db->escape($new_email).'\', activate_key=\''.$new_email_key.'\' WHERE id='.$id) or error('Unable to update activation data', __FILE__, __LINE__, $db->error());

		// Load the "activate email" template
		$mail_tpl = trim(file_get_contents(PUN_ROOT.'lang/'.$pun_user['language'].'/mail_templates/activate_email.tpl'));

		// The first row contains the subject
		$first_crlf = strpos($mail_tpl, "\n");
		$mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
		$mail_message = trim(substr($mail_tpl, $first_crlf));

		$mail_message = str_replace('<username>', $pun_user['username'], $mail_message);
		$mail_message = str_replace('<base_url>', get_base_url(), $mail_message);
		$mail_message = str_replace('<activation_url>', get_base_url().'/profile.php?action=change_email&id='.$id.'&key='.$new_email_key, $mail_message);
		$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message);

		pun_mail($new_email, $mail_subject, $mail_message);

		message($lang_profile['Activate email sent'].' <a href="mailto:'.$pun_config['o_admin_email'].'">'.$pun_config['o_admin_email'].'</a>.', true);
	}

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_common['Profile'], $lang_profile['Change email']);
	$required_fields = array('req_new_email' => $lang_profile['New email'], 'req_password' => $lang_common['Password']);
	$focus_element = array('change_email', 'req_new_email');
	define('PUN_ACTIVE_PAGE', 'profile');
	require PUN_ROOT.'header.php';

?>
<div class="blockform">
	<h2><span><?php echo $lang_profile['Change email'] ?></span></h2>
	<div class="box">
		<form id="change_email" method="post" action="profile.php?action=change_email&amp;id=<?php echo $id ?>" id="change_email" onsubmit="return process_form(this)">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_profile['Email legend'] ?></legend>
					<div class="infldset">
						<input type="hidden" name="form_sent" value="1" />
						<label class="required"><strong><?php echo $lang_profile['New email'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br /><input type="text" name="req_new_email" size="50" maxlength="80" /><br /></label>
						<label class="required"><strong><?php echo $lang_common['Password'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br /><input type="password" name="req_password" size="16" /><br /></label>
						<p><?php echo $lang_profile['Email instructions'] ?></p>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="new_email" value="<?php echo $lang_common['Submit'] ?>" /> <a href="javascript:history.go(-1)"><?php echo $lang_common['Go back'] ?></a></p>
		</form>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


else if ($action == 'upload_avatar' || $action == 'upload_avatar2')
{
	if ($pun_config['o_avatars'] == '0')
		message($lang_profile['Avatars disabled']);

	if ($pun_user['id'] != $id && !$pun_user['is_admmod'])
		message($lang_common['No permission'], false, '403 Forbidden');

	if (isset($_POST['form_sent']))
	{
		if (!isset($_FILES['req_file']))
			message($lang_profile['No file']);

		$uploaded_file = $_FILES['req_file'];

		// Make sure the upload went smooth
		if (isset($uploaded_file['error']))
		{
			switch ($uploaded_file['error'])
			{
				case 1: // UPLOAD_ERR_INI_SIZE
				case 2: // UPLOAD_ERR_FORM_SIZE
					message($lang_profile['Too large ini']);
					break;

				case 3: // UPLOAD_ERR_PARTIAL
					message($lang_profile['Partial upload']);
					break;

				case 4: // UPLOAD_ERR_NO_FILE
					message($lang_profile['No file']);
					break;

				case 6: // UPLOAD_ERR_NO_TMP_DIR
					message($lang_profile['No tmp directory']);
					break;

				default:
					// No error occured, but was something actually uploaded?
					if ($uploaded_file['size'] == 0)
						message($lang_profile['No file']);
					break;
			}
		}

		if (is_uploaded_file($uploaded_file['tmp_name']))
		{
			// Preliminary file check, adequate in most cases
			$allowed_types = array('image/gif', 'image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png');
			if (!in_array($uploaded_file['type'], $allowed_types))
				message($lang_profile['Bad type']);

			// Make sure the file isn't too big
			if ($uploaded_file['size'] > $pun_config['o_avatars_size'])
				message($lang_profile['Too large'].' '.forum_number_format($pun_config['o_avatars_size']).' '.$lang_profile['bytes'].'.');

			// Move the file to the avatar directory. We do this before checking the width/height to circumvent open_basedir restrictions
			if (!@move_uploaded_file($uploaded_file['tmp_name'], PUN_ROOT.$pun_config['o_avatars_dir'].'/'.$id.'.tmp'))
				message($lang_profile['Move failed'].' <a href="mailto:'.$pun_config['o_admin_email'].'">'.$pun_config['o_admin_email'].'</a>.');

			list($width, $height, $type,) = @getimagesize(PUN_ROOT.$pun_config['o_avatars_dir'].'/'.$id.'.tmp');

			// Determine type
			if ($type == IMAGETYPE_GIF)
				$extension = '.gif';
			else if ($type == IMAGETYPE_JPEG)
				$extension = '.jpg';
			else if ($type == IMAGETYPE_PNG)
				$extension = '.png';
			else
			{
				// Invalid type
				@unlink(PUN_ROOT.$pun_config['o_avatars_dir'].'/'.$id.'.tmp');
				message($lang_profile['Bad type']);
			}

			// Now check the width/height
			if (empty($width) || empty($height) || $width > $pun_config['o_avatars_width'] || $height > $pun_config['o_avatars_height'])
			{
				@unlink(PUN_ROOT.$pun_config['o_avatars_dir'].'/'.$id.'.tmp');
				message($lang_profile['Too wide or high'].' '.$pun_config['o_avatars_width'].'x'.$pun_config['o_avatars_height'].' '.$lang_profile['pixels'].'.');
			}

			// Delete any old avatars and put the new one in place
			delete_avatar($id);
			@rename(PUN_ROOT.$pun_config['o_avatars_dir'].'/'.$id.'.tmp', PUN_ROOT.$pun_config['o_avatars_dir'].'/'.$id.$extension);
			@chmod(PUN_ROOT.$pun_config['o_avatars_dir'].'/'.$id.$extension, 0644);
		}
		else
			message($lang_profile['Unknown failure']);

		redirect('profile.php?section=personality&amp;id='.$id, $lang_profile['Avatar upload redirect']);
	}

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_common['Profile'], $lang_profile['Upload avatar']);
	$required_fields = array('req_file' => $lang_profile['File']);
	$focus_element = array('upload_avatar', 'req_file');
	define('PUN_ACTIVE_PAGE', 'profile');
	require PUN_ROOT.'header.php';

?>
<div class="blockform">
	<h2><span><?php echo $lang_profile['Upload avatar'] ?></span></h2>
	<div class="box">
		<form id="upload_avatar" method="post" enctype="multipart/form-data" action="profile.php?action=upload_avatar2&amp;id=<?php echo $id ?>" onsubmit="return process_form(this)">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_profile['Upload avatar legend'] ?></legend>
					<div class="infldset">
						<input type="hidden" name="form_sent" value="1" />
						<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $pun_config['o_avatars_size'] ?>" />
						<label class="required"><strong><?php echo $lang_profile['File'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br /><input name="req_file" type="file" size="40" /><br /></label>
						<p><?php echo $lang_profile['Avatar desc'].' '.$pun_config['o_avatars_width'].' x '.$pun_config['o_avatars_height'].' '.$lang_profile['pixels'].' '.$lang_common['and'].' '.forum_number_format($pun_config['o_avatars_size']).' '.$lang_profile['bytes'].' ('.file_size($pun_config['o_avatars_size']).').' ?></p>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="upload" value="<?php echo $lang_profile['Upload'] ?>" /> <a href="javascript:history.go(-1)"><?php echo $lang_common['Go back'] ?></a></p>
		</form>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


else if ($action == 'delete_avatar')
{
	if ($pun_user['id'] != $id && !$pun_user['is_admmod'])
		message($lang_common['No permission'], false, '403 Forbidden');

	confirm_referrer('profile.php');

	delete_avatar($id);

	redirect('profile.php?section=personality&amp;id='.$id, $lang_profile['Avatar deleted redirect']);
}


else if (isset($_POST['update_group_membership']))
{
	if ($pun_user['g_id'] > PUN_ADMIN)
		message($lang_common['No permission'], false, '403 Forbidden');

	confirm_referrer('profile.php');

	$new_group_id = intval($_POST['group_id']);

	$db->query('UPDATE '.$db->prefix.'users SET group_id='.$new_group_id.' WHERE id='.$id) or error('Unable to change user group', __FILE__, __LINE__, $db->error());

	// Regenerate the users info cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';

	generate_users_info_cache();

	$result = $db->query('SELECT g_moderator FROM '.$db->prefix.'groups WHERE g_id='.$new_group_id) or error('Unable to fetch group', __FILE__, __LINE__, $db->error());
	$new_group_mod = $db->result($result);

	// If the user was a moderator or an administrator, we remove him/her from the moderator list in all forums as well
	if ($new_group_id != PUN_ADMIN && $new_group_mod != '1')
	{
		$result = $db->query('SELECT id, moderators FROM '.$db->prefix.'forums') or error('Unable to fetch forum list', __FILE__, __LINE__, $db->error());

		while ($cur_forum = $db->fetch_assoc($result))
		{
			$cur_moderators = ($cur_forum['moderators'] != '') ? unserialize($cur_forum['moderators']) : array();

			if (in_array($id, $cur_moderators))
			{
				$username = array_search($id, $cur_moderators);
				unset($cur_moderators[$username]);
				$cur_moderators = (!empty($cur_moderators)) ? '\''.$db->escape(serialize($cur_moderators)).'\'' : 'NULL';

				$db->query('UPDATE '.$db->prefix.'forums SET moderators='.$cur_moderators.' WHERE id='.$cur_forum['id']) or error('Unable to update forum', __FILE__, __LINE__, $db->error());
			}
		}
	}

	redirect('profile.php?section=admin&amp;id='.$id, $lang_profile['Group membership redirect']);
}


else if (isset($_POST['update_forums']))
{
	if ($pun_user['g_id'] > PUN_ADMIN)
		message($lang_common['No permission'], false, '403 Forbidden');

	confirm_referrer('profile.php');

	// Get the username of the user we are processing
	$result = $db->query('SELECT username FROM '.$db->prefix.'users WHERE id='.$id) or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());
	$username = $db->result($result);

	$moderator_in = (isset($_POST['moderator_in'])) ? array_keys($_POST['moderator_in']) : array();

	// Loop through all forums
	$result = $db->query('SELECT id, moderators FROM '.$db->prefix.'forums') or error('Unable to fetch forum list', __FILE__, __LINE__, $db->error());

	while ($cur_forum = $db->fetch_assoc($result))
	{
		$cur_moderators = ($cur_forum['moderators'] != '') ? unserialize($cur_forum['moderators']) : array();
		// If the user should have moderator access (and he/she doesn't already have it)
		if (in_array($cur_forum['id'], $moderator_in) && !in_array($id, $cur_moderators))
		{
			$cur_moderators[$username] = $id;
			uksort($cur_moderators, 'utf8_strcasecmp');

			$db->query('UPDATE '.$db->prefix.'forums SET moderators=\''.$db->escape(serialize($cur_moderators)).'\' WHERE id='.$cur_forum['id']) or error('Unable to update forum', __FILE__, __LINE__, $db->error());
		}
		// If the user shouldn't have moderator access (and he/she already has it)
		else if (!in_array($cur_forum['id'], $moderator_in) && in_array($id, $cur_moderators))
		{
			unset($cur_moderators[$username]);
			$cur_moderators = (!empty($cur_moderators)) ? '\''.$db->escape(serialize($cur_moderators)).'\'' : 'NULL';

			$db->query('UPDATE '.$db->prefix.'forums SET moderators='.$cur_moderators.' WHERE id='.$cur_forum['id']) or error('Unable to update forum', __FILE__, __LINE__, $db->error());
		}
	}

	redirect('profile.php?section=admin&amp;id='.$id, $lang_profile['Update forums redirect']);
}


else if (isset($_POST['ban']))
{
	if ($pun_user['g_id'] != PUN_ADMIN && ($pun_user['g_moderator'] != '1' || $pun_user['g_mod_ban_users'] == '0'))
		message($lang_common['No permission'], false, '403 Forbidden');

	// Get the username of the user we are banning
	$result = $db->query('SELECT username FROM '.$db->prefix.'users WHERE id='.$id) or error('Unable to fetch username', __FILE__, __LINE__, $db->error());
	$username = $db->result($result);

	// Check whether user is already banned
	$result = $db->query('SELECT id FROM '.$db->prefix.'bans WHERE username = \''.$db->escape($username).'\' ORDER BY expire IS NULL DESC, expire DESC LIMIT 1') or error('Unable to fetch ban ID', __FILE__, __LINE__, $db->error());
	if ($db->num_rows($result))
	{
		$ban_id = $db->result($result);
		redirect('admin_bans.php?edit_ban='.$ban_id.'&amp;exists', $lang_profile['Ban redirect']);
	}
	else
		redirect('admin_bans.php?add_ban='.$id, $lang_profile['Ban redirect']);
}


else if (isset($_POST['delete_user']) || isset($_POST['delete_user_comply']))
{
	if ($pun_user['g_id'] > PUN_ADMIN)
		message($lang_common['No permission'], false, '403 Forbidden');

	confirm_referrer('profile.php');

	// Get the username and group of the user we are deleting
	$result = $db->query('SELECT group_id, username FROM '.$db->prefix.'users WHERE id='.$id) or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());
	list($group_id, $username) = $db->fetch_row($result);

	if ($group_id == PUN_ADMIN)
		message($lang_profile['No delete admin message']);

	if (isset($_POST['delete_user_comply']))
	{
		// If the user is a moderator or an administrator, we remove him/her from the moderator list in all forums as well
		$result = $db->query('SELECT g_moderator FROM '.$db->prefix.'groups WHERE g_id='.$group_id) or error('Unable to fetch group', __FILE__, __LINE__, $db->error());
		$group_mod = $db->result($result);

		if ($group_id == PUN_ADMIN || $group_mod == '1')
		{
			$result = $db->query('SELECT id, moderators FROM '.$db->prefix.'forums') or error('Unable to fetch forum list', __FILE__, __LINE__, $db->error());

			while ($cur_forum = $db->fetch_assoc($result))
			{
				$cur_moderators = ($cur_forum['moderators'] != '') ? unserialize($cur_forum['moderators']) : array();

				if (in_array($id, $cur_moderators))
				{
					unset($cur_moderators[$username]);
					$cur_moderators = (!empty($cur_moderators)) ? '\''.$db->escape(serialize($cur_moderators)).'\'' : 'NULL';

					$db->query('UPDATE '.$db->prefix.'forums SET moderators='.$cur_moderators.' WHERE id='.$cur_forum['id']) or error('Unable to update forum', __FILE__, __LINE__, $db->error());
				}
			}
		}

		// Delete any subscriptions
		$db->query('DELETE FROM '.$db->prefix.'topic_subscriptions WHERE user_id='.$id) or error('Unable to delete topic subscriptions', __FILE__, __LINE__, $db->error());
		$db->query('DELETE FROM '.$db->prefix.'forum_subscriptions WHERE user_id='.$id) or error('Unable to delete forum subscriptions', __FILE__, __LINE__, $db->error());

		// Remove him/her from the online list (if they happen to be logged in)
		$db->query('DELETE FROM '.$db->prefix.'online WHERE user_id='.$id) or error('Unable to remove user from online list', __FILE__, __LINE__, $db->error());

		// Should we delete all posts made by this user?
		if (isset($_POST['delete_posts']))
		{
			require PUN_ROOT.'include/search_idx.php';
			@set_time_limit(0);

			// Find all posts made by this user
			$result = $db->query('SELECT p.id, p.topic_id, t.forum_id FROM '.$db->prefix.'posts AS p INNER JOIN '.$db->prefix.'topics AS t ON t.id=p.topic_id INNER JOIN '.$db->prefix.'forums AS f ON f.id=t.forum_id WHERE p.poster_id='.$id) or error('Unable to fetch posts', __FILE__, __LINE__, $db->error());
			if ($db->num_rows($result))
			{
				while ($cur_post = $db->fetch_assoc($result))
				{
					// Determine whether this post is the "topic post" or not
					$result2 = $db->query('SELECT id FROM '.$db->prefix.'posts WHERE topic_id='.$cur_post['topic_id'].' ORDER BY posted LIMIT 1') or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());

					if ($db->result($result2) == $cur_post['id'])
						delete_topic($cur_post['topic_id']);
					else
						delete_post($cur_post['id'], $cur_post['topic_id']);

					update_forum($cur_post['forum_id']);
				}
			}
		}
		else
			// Set all his/her posts to guest
			$db->query('UPDATE '.$db->prefix.'posts SET poster_id=1 WHERE poster_id='.$id) or error('Unable to update posts', __FILE__, __LINE__, $db->error());

		// Delete the user
		$db->query('DELETE FROM '.$db->prefix.'users WHERE id='.$id) or error('Unable to delete user', __FILE__, __LINE__, $db->error());

		// Delete user avatar
		delete_avatar($id);

		// Regenerate the users info cache
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			require PUN_ROOT.'include/cache.php';

		generate_users_info_cache();

		redirect('index.php', $lang_profile['User delete redirect']);
	}

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_common['Profile'], $lang_profile['Confirm delete user']);
	define('PUN_ACTIVE_PAGE', 'profile');
	require PUN_ROOT.'header.php';

?>
<div class="blockform">
	<h2><span><?php echo $lang_profile['Confirm delete user'] ?></span></h2>
	<div class="box">
		<form id="confirm_del_user" method="post" action="profile.php?id=<?php echo $id ?>">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_profile['Confirm delete legend'] ?></legend>
					<div class="infldset">
						<p><?php echo $lang_profile['Confirmation info'].' <strong>'.pun_htmlspecialchars($username).'</strong>.' ?></p>
						<div class="rbox">
							<label><input type="checkbox" name="delete_posts" value="1" checked="checked" /><?php echo $lang_profile['Delete posts'] ?><br /></label>
						</div>
						<p class="warntext"><strong><?php echo $lang_profile['Delete warning'] ?></strong></p>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="delete_user_comply" value="<?php echo $lang_profile['Delete'] ?>" /> <a href="javascript:history.go(-1)"><?php echo $lang_common['Go back'] ?></a></p>
		</form>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


else if (isset($_POST['form_sent']))
{
	// Fetch the user group of the user we are editing
	$result = $db->query('SELECT u.username, u.group_id, g.g_moderator FROM '.$db->prefix.'users AS u INNER JOIN '.$db->prefix.'groups AS g ON (g.g_id=u.group_id) WHERE u.id='.$id) or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());
	if (!$db->num_rows($result))
		message($lang_common['Bad request'], false, '404 Not Found');

	list($old_username, $group_id, $is_moderator) = $db->fetch_row($result);

	if ($pun_user['id'] != $id &&																	// If we arent the user (i.e. editing your own profile)
		(!$pun_user['is_admmod'] ||																	// and we are not an admin or mod
		($pun_user['g_id'] != PUN_ADMIN &&															// or we aren't an admin and ...
		($pun_user['g_mod_edit_users'] == '0' ||													// mods aren't allowed to edit users
		$group_id == PUN_ADMIN ||																	// or the user is an admin
		$is_moderator))))																			// or the user is another mod
		message($lang_common['No permission'], false, '403 Forbidden');

	if ($pun_user['is_admmod'])
		confirm_referrer('profile.php');

	$username_updated = false;

	// Validate input depending on section
	switch ($section)
	{
		case 'essentials':
		{
			$form = array(
				'timezone'		=> floatval($_POST['form']['timezone']),
				'dst'			=> isset($_POST['form']['dst']) ? '1' : '0',
				'time_format'	=> intval($_POST['form']['time_format']),
				'date_format'	=> intval($_POST['form']['date_format']),
			);

			// Make sure we got a valid language string
			if (isset($_POST['form']['language']))
			{
				$languages = forum_list_langs();
				$form['language'] = pun_trim($_POST['form']['language']);
				if (!in_array($form['language'], $languages))
					message($lang_common['Bad request'], false, '404 Not Found');
			}

			if ($pun_user['is_admmod'])
			{
				$form['admin_note'] = pun_trim($_POST['admin_note']);

				// Are we allowed to change usernames?
				if ($pun_user['g_id'] == PUN_ADMIN || ($pun_user['g_moderator'] == '1' && $pun_user['g_mod_rename_users'] == '1'))
				{
					$form['username'] = pun_trim($_POST['req_username']);

					if ($form['username'] != $old_username)
					{
						// Check username
						require PUN_ROOT.'lang/'.$pun_user['language'].'/register.php';

						$errors = array();
						check_username($form['username'], $id);
						if (!empty($errors))
							message($errors[0]);

						$username_updated = true;
					}
				}

				// We only allow administrators to update the post count
				if ($pun_user['g_id'] == PUN_ADMIN)
					$form['num_posts'] = intval($_POST['num_posts']);
			}

			if ($pun_config['o_regs_verify'] == '0' || $pun_user['is_admmod'])
			{
				require PUN_ROOT.'include/email.php';

				// Validate the email address
				$form['email'] = strtolower(pun_trim($_POST['req_email']));
				if (!is_valid_email($form['email']))
					message($lang_common['Invalid email']);
			}

			break;
		}

		case 'personal':
		{
			$form = array(
				'realname'		=> isset($_POST['form']['realname']) ? pun_trim($_POST['form']['realname']) : '',
				'url'			=> isset($_POST['form']['url']) ? pun_trim($_POST['form']['url']) : '',
				'location'		=> isset($_POST['form']['location']) ? pun_trim($_POST['form']['location']) : '',
			);

			// Add http:// if the URL doesn't contain it already (while allowing https://, too)
			if ($pun_user['g_post_links'] == '1')
			{
				if ($form['url'] != '')
				{
					$url = url_valid($form['url']);

					if ($url === false)
						message($lang_profile['Invalid website URL']);

					$form['url'] = $url['url'];
				}
			}
			else
			{
				if (!empty($form['url']))
					message($lang_profile['Website not allowed']);

				$form['url'] = '';
			}

			if ($pun_user['g_id'] == PUN_ADMIN)
				$form['title'] = pun_trim($_POST['title']);
			else if ($pun_user['g_set_title'] == '1')
			{
				$form['title'] = pun_trim($_POST['title']);

				if ($form['title'] != '')
				{
					// A list of words that the title may not contain
					// If the language is English, there will be some duplicates, but it's not the end of the world
					$forbidden = array('member', 'moderator', 'administrator', 'banned', 'guest', utf8_strtolower($lang_common['Member']), utf8_strtolower($lang_common['Moderator']), utf8_strtolower($lang_common['Administrator']), utf8_strtolower($lang_common['Banned']), utf8_strtolower($lang_common['Guest']));

					if (in_array(utf8_strtolower($form['title']), $forbidden))
						message($lang_profile['Forbidden title']);
				}
			}

			break;
		}

		case 'messaging':
		{
			$form = array(
				'jabber'		=> pun_trim($_POST['form']['jabber']),
				'icq'			=> pun_trim($_POST['form']['icq']),
				'msn'			=> pun_trim($_POST['form']['msn']),
				'aim'			=> pun_trim($_POST['form']['aim']),
				'yahoo'			=> pun_trim($_POST['form']['yahoo']),
			);

			// If the ICQ UIN contains anything other than digits it's invalid
			if (preg_match('%[^0-9]%', $form['icq']))
				message($lang_prof_reg['Bad ICQ']);

			break;
		}

		case 'personality':
		{
			$form = array();

			// Clean up signature from POST
			if ($pun_config['o_signatures'] == '1')
			{
				$form['signature'] = pun_linebreaks(pun_trim($_POST['signature']));

				// Validate signature
				if (pun_strlen($form['signature']) > $pun_config['p_sig_length'])
					message(sprintf($lang_prof_reg['Sig too long'], $pun_config['p_sig_length'], pun_strlen($form['signature']) - $pun_config['p_sig_length']));
				else if (substr_count($form['signature'], "\n") > ($pun_config['p_sig_lines']-1))
					message(sprintf($lang_prof_reg['Sig too many lines'], $pun_config['p_sig_lines']));
				else if ($form['signature'] && $pun_config['p_sig_all_caps'] == '0' && is_all_uppercase($form['signature']) && !$pun_user['is_admmod'])
					$form['signature'] = utf8_ucwords(utf8_strtolower($form['signature']));

				// Validate BBCode syntax
				if ($pun_config['p_sig_bbcode'] == '1')
				{
					require PUN_ROOT.'include/parser.php';

					$errors = array();

					$form['signature'] = preparse_bbcode($form['signature'], $errors, true);

					if(count($errors) > 0)
						message('<ul><li>'.implode('</li><li>', $errors).'</li></ul>');
				}
			}

			break;
		}

		case 'display':
		{
			$form = array(
				'disp_topics'		=> pun_trim($_POST['form']['disp_topics']),
				'disp_posts'		=> pun_trim($_POST['form']['disp_posts']),
				'show_smilies'		=> isset($_POST['form']['show_smilies']) ? '1' : '0',
				'show_img'			=> isset($_POST['form']['show_img']) ? '1' : '0',
				'show_img_sig'		=> isset($_POST['form']['show_img_sig']) ? '1' : '0',
				'show_avatars'		=> isset($_POST['form']['show_avatars']) ? '1' : '0',
				'show_sig'			=> isset($_POST['form']['show_sig']) ? '1' : '0',
			);

			if ($form['disp_topics'] != '')
			{
				$form['disp_topics'] = intval($form['disp_topics']);
				if ($form['disp_topics'] < 3)
					$form['disp_topics'] = 3;
				else if ($form['disp_topics'] > 75)
					$form['disp_topics'] = 75;
			}

			if ($form['disp_posts'] != '')
			{
				$form['disp_posts'] = intval($form['disp_posts']);
				if ($form['disp_posts'] < 3)
					$form['disp_posts'] = 3;
				else if ($form['disp_posts'] > 75)
					$form['disp_posts'] = 75;
			}

			// Make sure we got a valid style string
			if (isset($_POST['form']['style']))
			{
				$styles = forum_list_styles();
				$form['style'] = pun_trim($_POST['form']['style']);
				if (!in_array($form['style'], $styles))
					message($lang_common['Bad request'], false, '404 Not Found');
			}

			break;
		}

		case 'privacy':
		{
			$form = array(
				'email_setting'			=> intval($_POST['form']['email_setting']),
				'notify_with_post'		=> isset($_POST['form']['notify_with_post']) ? '1' : '0',
				'auto_notify'			=> isset($_POST['form']['auto_notify']) ? '1' : '0',
			);

			if ($form['email_setting'] < 0 || $form['email_setting'] > 2)
				$form['email_setting'] = $pun_config['o_default_email_setting'];

			break;
		}

		default:
			message($lang_common['Bad request']);
	}


	// Single quotes around non-empty values and NULL for empty values
	$temp = array();
	foreach ($form as $key => $input)
	{
		$value = ($input !== '') ? '\''.$db->escape($input).'\'' : 'NULL';

		$temp[] = $key.'='.$value;
	}

	if (empty($temp))
		message($lang_common['Bad request']);


	$db->query('UPDATE '.$db->prefix.'users SET '.implode(',', $temp).' WHERE id='.$id) or error('Unable to update profile', __FILE__, __LINE__, $db->error());

	// If we changed the username we have to update some stuff
	if ($username_updated)
	{
		$db->query('UPDATE '.$db->prefix.'bans SET username=\''.$db->escape($form['username']).'\' WHERE username=\''.$db->escape($old_username).'\'') or error('Unable to update bans', __FILE__, __LINE__, $db->error());
		// If any bans were updated, we will need to know because the cache will need to be regenerated.
		if ($db->affected_rows() > 0)
			$bans_updated = true;
		$db->query('UPDATE '.$db->prefix.'posts SET poster=\''.$db->escape($form['username']).'\' WHERE poster_id='.$id) or error('Unable to update posts', __FILE__, __LINE__, $db->error());
		$db->query('UPDATE '.$db->prefix.'posts SET edited_by=\''.$db->escape($form['username']).'\' WHERE edited_by=\''.$db->escape($old_username).'\'') or error('Unable to update posts', __FILE__, __LINE__, $db->error());
		$db->query('UPDATE '.$db->prefix.'topics SET poster=\''.$db->escape($form['username']).'\' WHERE poster=\''.$db->escape($old_username).'\'') or error('Unable to update topics', __FILE__, __LINE__, $db->error());
		$db->query('UPDATE '.$db->prefix.'topics SET last_poster=\''.$db->escape($form['username']).'\' WHERE last_poster=\''.$db->escape($old_username).'\'') or error('Unable to update topics', __FILE__, __LINE__, $db->error());
		$db->query('UPDATE '.$db->prefix.'forums SET last_poster=\''.$db->escape($form['username']).'\' WHERE last_poster=\''.$db->escape($old_username).'\'') or error('Unable to update forums', __FILE__, __LINE__, $db->error());
		$db->query('UPDATE '.$db->prefix.'online SET ident=\''.$db->escape($form['username']).'\' WHERE ident=\''.$db->escape($old_username).'\'') or error('Unable to update online list', __FILE__, __LINE__, $db->error());

		// If the user is a moderator or an administrator we have to update the moderator lists
		$result = $db->query('SELECT group_id FROM '.$db->prefix.'users WHERE id='.$id) or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());
		$group_id = $db->result($result);

		$result = $db->query('SELECT g_moderator FROM '.$db->prefix.'groups WHERE g_id='.$group_id) or error('Unable to fetch group', __FILE__, __LINE__, $db->error());
		$group_mod = $db->result($result);

		if ($group_id == PUN_ADMIN || $group_mod == '1')
		{
			$result = $db->query('SELECT id, moderators FROM '.$db->prefix.'forums') or error('Unable to fetch forum list', __FILE__, __LINE__, $db->error());

			while ($cur_forum = $db->fetch_assoc($result))
			{
				$cur_moderators = ($cur_forum['moderators'] != '') ? unserialize($cur_forum['moderators']) : array();

				if (in_array($id, $cur_moderators))
				{
					unset($cur_moderators[$old_username]);
					$cur_moderators[$form['username']] = $id;
					uksort($cur_moderators, 'utf8_strcasecmp');

					$db->query('UPDATE '.$db->prefix.'forums SET moderators=\''.$db->escape(serialize($cur_moderators)).'\' WHERE id='.$cur_forum['id']) or error('Unable to update forum', __FILE__, __LINE__, $db->error());
				}
			}
		}

		// Regenerate the users info cache
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			require PUN_ROOT.'include/cache.php';

		generate_users_info_cache();

		// Check if the bans table was updated and regenerate the bans cache when needed
		if (isset($bans_updated))
			generate_bans_cache();
	}

	redirect('profile.php?section='.$section.'&amp;id='.$id, $lang_profile['Profile redirect']);
}


$result = $db->query('SELECT u.username, u.email, u.title, u.realname, u.url, u.jabber, u.icq, u.msn, u.aim, u.yahoo, u.location, u.signature, u.disp_topics, u.disp_posts, u.email_setting, u.notify_with_post, u.auto_notify, u.show_smilies, u.show_img, u.show_img_sig, u.show_avatars, u.show_sig, u.timezone, u.dst, u.language, u.style, u.num_posts, u.last_post, u.registered, u.registration_ip, u.admin_note, u.date_format, u.time_format, u.last_visit, g.g_id, g.g_user_title, g.g_moderator FROM '.$db->prefix.'users AS u LEFT JOIN '.$db->prefix.'groups AS g ON g.g_id=u.group_id WHERE u.id='.$id) or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());
if (!$db->num_rows($result))
	message($lang_common['Bad request'], false, '404 Not Found');

$user = $db->fetch_assoc($result);

$last_post = format_time($user['last_post']);

if ($user['signature'] != '')
{
	require PUN_ROOT.'include/parser.php';
	$parsed_signature = parse_signature($user['signature']);
}


// View or edit?
if ($pun_user['id'] != $id &&																	// If we arent the user (i.e. editing your own profile)
	(!$pun_user['is_admmod'] ||																	// and we are not an admin or mod
	($pun_user['g_id'] != PUN_ADMIN &&															// or we aren't an admin and ...
	($pun_user['g_mod_edit_users'] == '0' ||													// mods aren't allowed to edit users
	$user['g_id'] == PUN_ADMIN ||																// or the user is an admin
	$user['g_moderator'] == '1'))))																// or the user is another mod
{
	$user_personal = array();

	$user_personal[] = '<dt>'.$lang_common['Username'].'</dt>';
	$user_personal[] = '<dd>'.pun_htmlspecialchars($user['username']).'</dd>';

	$user_title_field = get_title($user);
	$user_personal[] = '<dt>'.$lang_common['Title'].'</dt>';
	$user_personal[] = '<dd>'.(($pun_config['o_censoring'] == '1') ? censor_words($user_title_field) : $user_title_field).'</dd>';

	if ($user['realname'] != '')
	{
		$user_personal[] = '<dt>'.$lang_profile['Realname'].'</dt>';
		$user_personal[] = '<dd>'.pun_htmlspecialchars(($pun_config['o_censoring'] == '1') ? censor_words($user['realname']) : $user['realname']).'</dd>';
	}

	if ($user['location'] != '')
	{
		$user_personal[] = '<dt>'.$lang_profile['Location'].'</dt>';
		$user_personal[] = '<dd>'.pun_htmlspecialchars(($pun_config['o_censoring'] == '1') ? censor_words($user['location']) : $user['location']).'</dd>';
	}

	if ($user['url'] != '')
	{
		$user['url'] = pun_htmlspecialchars(($pun_config['o_censoring'] == '1') ? censor_words($user['url']) : $user['url']);
		$user_personal[] = '<dt>'.$lang_profile['Website'].'</dt>';
		$user_personal[] = '<dd><span class="website"><a href="'.$user['url'].'">'.$user['url'].'</a></span></dd>';
	}

	if ($user['email_setting'] == '0' && !$pun_user['is_guest'] && $pun_user['g_send_email'] == '1')
		$email_field = '<a href="mailto:'.$user['email'].'">'.$user['email'].'</a>';
	else if ($user['email_setting'] == '1' && !$pun_user['is_guest'] && $pun_user['g_send_email'] == '1')
		$email_field = '<a href="misc.php?email='.$id.'">'.$lang_common['Send email'].'</a>';
	else
		$email_field = '';
	if ($email_field != '')
	{
		$user_personal[] = '<dt>'.$lang_common['Email'].'</dt>';
		$user_personal[] = '<dd><span class="email">'.$email_field.'</span></dd>';
	}

	$user_messaging = array();

	if ($user['jabber'] != '')
	{
		$user_messaging[] = '<dt>'.$lang_profile['Jabber'].'</dt>';
		$user_messaging[] = '<dd>'.pun_htmlspecialchars(($pun_config['o_censoring'] == '1') ? censor_words($user['jabber']) : $user['jabber']).'</dd>';
	}

	if ($user['icq'] != '')
	{
		$user_messaging[] = '<dt>'.$lang_profile['ICQ'].'</dt>';
		$user_messaging[] = '<dd>'.$user['icq'].'</dd>';
	}

	if ($user['msn'] != '')
	{
		$user_messaging[] = '<dt>'.$lang_profile['MSN'].'</dt>';
		$user_messaging[] = '<dd>'.pun_htmlspecialchars(($pun_config['o_censoring'] == '1') ? censor_words($user['msn']) : $user['msn']).'</dd>';
	}

	if ($user['aim'] != '')
	{
		$user_messaging[] = '<dt>'.$lang_profile['AOL IM'].'</dt>';
		$user_messaging[] = '<dd>'.pun_htmlspecialchars(($pun_config['o_censoring'] == '1') ? censor_words($user['aim']) : $user['aim']).'</dd>';
	}

	if ($user['yahoo'] != '')
	{
		$user_messaging[] = '<dt>'.$lang_profile['Yahoo'].'</dt>';
		$user_messaging[] = '<dd>'.pun_htmlspecialchars(($pun_config['o_censoring'] == '1') ? censor_words($user['yahoo']) : $user['yahoo']).'</dd>';
	}

	$user_personality = array();

	if ($pun_config['o_avatars'] == '1')
	{
		$avatar_field = generate_avatar_markup($id);
		if ($avatar_field != '')
		{
			$user_personality[] = '<dt>'.$lang_profile['Avatar'].'</dt>';
			$user_personality[] = '<dd>'.$avatar_field.'</dd>';
		}
	}

	if ($pun_config['o_signatures'] == '1')
	{
		if (isset($parsed_signature))
		{
			$user_personality[] = '<dt>'.$lang_profile['Signature'].'</dt>';
			$user_personality[] = '<dd><div class="postsignature postmsg">'.$parsed_signature.'</div></dd>';
		}
	}

	$user_activity = array();

	$posts_field = '';
	if ($pun_config['o_show_post_count'] == '1' || $pun_user['is_admmod'])
		$posts_field = forum_number_format($user['num_posts']);
	if ($pun_user['g_search'] == '1')
	{
		$quick_searches = array();
		if ($user['num_posts'] > 0)
		{
			$quick_searches[] = '<a href="search.php?action=show_user_topics&amp;user_id='.$id.'">'.$lang_profile['Show topics'].'</a>';
			$quick_searches[] = '<a href="search.php?action=show_user_posts&amp;user_id='.$id.'">'.$lang_profile['Show posts'].'</a>';
		}
		if ($pun_user['is_admmod'] && $pun_config['o_topic_subscriptions'] == '1')
			$quick_searches[] = '<a href="search.php?action=show_subscriptions&amp;user_id='.$id.'">'.$lang_profile['Show subscriptions'].'</a>';

		if (!empty($quick_searches))
			$posts_field .= (($posts_field != '') ? ' - ' : '').implode(' - ', $quick_searches);
	}
	if ($posts_field != '')
	{
		$user_activity[] = '<dt>'.$lang_common['Posts'].'</dt>';
		$user_activity[] = '<dd>'.$posts_field.'</dd>';
	}

	if ($user['num_posts'] > 0)
	{
		$user_activity[] = '<dt>'.$lang_common['Last post'].'</dt>';
		$user_activity[] = '<dd>'.$last_post.'</dd>';
	}

	$user_activity[] = '<dt>'.$lang_common['Registered'].'</dt>';
	$user_activity[] = '<dd>'.format_time($user['registered'], true).'</dd>';

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), sprintf($lang_profile['Users profile'], pun_htmlspecialchars($user['username'])));
	define('PUN_ALLOW_INDEX', 1);
	define('PUN_ACTIVE_PAGE', 'index');
	require PUN_ROOT.'header.php';

?>
<div id="viewprofile" class="block">
	<h2><span><?php echo $lang_common['Profile'] ?></span></h2>
	<div class="box">
		<div class="fakeform">
			<div class="inform">
				<fieldset>
				<legend><?php echo $lang_profile['Section personal'] ?></legend>
					<div class="infldset">
						<dl>
							<?php echo implode("\n\t\t\t\t\t\t\t", $user_personal)."\n" ?>
						</dl>
						<div class="clearer"></div>
					</div>
				</fieldset>
			</div>
<?php if (!empty($user_messaging)): ?>			<div class="inform">
				<fieldset>
				<legend><?php echo $lang_profile['Section messaging'] ?></legend>
					<div class="infldset">
						<dl>
							<?php echo implode("\n\t\t\t\t\t\t\t", $user_messaging)."\n" ?>
						</dl>
						<div class="clearer"></div>
					</div>
				</fieldset>
			</div>
<?php endif; if (!empty($user_personality)): ?>			<div class="inform">
				<fieldset>
				<legend><?php echo $lang_profile['Section personality'] ?></legend>
					<div class="infldset">
						<dl>
							<?php echo implode("\n\t\t\t\t\t\t\t", $user_personality)."\n" ?>
						</dl>
						<div class="clearer"></div>
					</div>
				</fieldset>
			</div>
<?php endif; ?>			<div class="inform">
				<fieldset>
				<legend><?php echo $lang_profile['User activity'] ?></legend>
					<div class="infldset">
						<dl>
							<?php echo implode("\n\t\t\t\t\t\t\t", $user_activity)."\n" ?>
						</dl>
						<div class="clearer"></div>
					</div>
				</fieldset>
			</div>
		</div>
	</div>
</div>

<?php

	require PUN_ROOT.'footer.php';
}
else
{
	if (!$section || $section == 'essentials')
	{
		if ($pun_user['is_admmod'])
		{
			if ($pun_user['g_id'] == PUN_ADMIN || $pun_user['g_mod_rename_users'] == '1')
				$username_field = '<label class="required"><strong>'.$lang_common['Username'].' <span>'.$lang_common['Required'].'</span></strong><br /><input type="text" name="req_username" value="'.pun_htmlspecialchars($user['username']).'" size="25" maxlength="25" /><br /></label>'."\n";
			else
				$username_field = '<p>'.sprintf($lang_profile['Username info'], pun_htmlspecialchars($user['username'])).'</p>'."\n";

			$email_field = '<label class="required"><strong>'.$lang_common['Email'].' <span>'.$lang_common['Required'].'</span></strong><br /><input type="text" name="req_email" value="'.$user['email'].'" size="40" maxlength="80" /><br /></label><p><span class="email"><a href="misc.php?email='.$id.'">'.$lang_common['Send email'].'</a></span></p>'."\n";
		}
		else
		{
			$username_field = '<p>'.$lang_common['Username'].': '.pun_htmlspecialchars($user['username']).'</p>'."\n";

			if ($pun_config['o_regs_verify'] == '1')
				$email_field = '<p>'.sprintf($lang_profile['Email info'], $user['email'].' - <a href="profile.php?action=change_email&amp;id='.$id.'">'.$lang_profile['Change email'].'</a>').'</p>'."\n";
			else
				$email_field = '<label class="required"><strong>'.$lang_common['Email'].' <span>'.$lang_common['Required'].'</span></strong><br /><input type="text" name="req_email" value="'.$user['email'].'" size="40" maxlength="80" /><br /></label>'."\n";
		}

		$posts_field = '';
		$posts_actions = array();

		if ($pun_user['g_id'] == PUN_ADMIN)
			$posts_field .= '<label>'.$lang_common['Posts'].'<br /><input type="text" name="num_posts" value="'.$user['num_posts'].'" size="8" maxlength="8" /><br /></label>';
		else if ($pun_config['o_show_post_count'] == '1' || $pun_user['is_admmod'])
			$posts_actions[] = sprintf($lang_profile['Posts info'], forum_number_format($user['num_posts']));

		if ($pun_user['g_search'] == '1' || $pun_user['g_id'] == PUN_ADMIN)
		{
			$posts_actions[] = '<a href="search.php?action=show_user_topics&amp;user_id='.$id.'">'.$lang_profile['Show topics'].'</a>';
			$posts_actions[] = '<a href="search.php?action=show_user_posts&amp;user_id='.$id.'">'.$lang_profile['Show posts'].'</a>';

			if ($pun_config['o_topic_subscriptions'] == '1')
				$posts_actions[] = '<a href="search.php?action=show_subscriptions&amp;user_id='.$id.'">'.$lang_profile['Show subscriptions'].'</a>';
		}

		$posts_field .= (!empty($posts_actions) ? '<p class="actions">'.implode(' - ', $posts_actions).'</p>' : '')."\n";


		$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_common['Profile'], $lang_profile['Section essentials']);
		$required_fields = array('req_username' => $lang_common['Username'], 'req_email' => $lang_common['Email']);
		define('PUN_ACTIVE_PAGE', 'profile');
		require PUN_ROOT.'header.php';

		generate_profile_menu('essentials');

?>
	<div class="blockform">
		<h2><span><?php echo pun_htmlspecialchars($user['username']).' - '.$lang_profile['Section essentials'] ?></span></h2>
		<div class="box">
			<form id="profile1" method="post" action="profile.php?section=essentials&amp;id=<?php echo $id ?>" onsubmit="return process_form(this)">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_profile['Username and pass legend'] ?></legend>
						<div class="infldset">
							<input type="hidden" name="form_sent" value="1" />
							<?php echo $username_field ?>
<?php if ($pun_user['id'] == $id || $pun_user['g_id'] == PUN_ADMIN || ($user['g_moderator'] == '0' && $pun_user['g_mod_change_passwords'] == '1')): ?>							<p class="actions"><span><a href="profile.php?action=change_pass&amp;id=<?php echo $id ?>"><?php echo $lang_profile['Change pass'] ?></a></span></p>
<?php endif; ?>						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_prof_reg['Email legend'] ?></legend>
						<div class="infldset">
							<?php echo $email_field ?>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_prof_reg['Localisation legend'] ?></legend>
						<div class="infldset">
							<p><?php echo $lang_prof_reg['Time zone info'] ?></p>
							<label><?php echo $lang_prof_reg['Time zone']."\n" ?>
							<br /><select name="form[timezone]">
								<option value="-12"<?php if ($user['timezone'] == -12) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-12:00'] ?></option>
								<option value="-11"<?php if ($user['timezone'] == -11) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-11:00'] ?></option>
								<option value="-10"<?php if ($user['timezone'] == -10) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-10:00'] ?></option>
								<option value="-9.5"<?php if ($user['timezone'] == -9.5) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-09:30'] ?></option>
								<option value="-9"<?php if ($user['timezone'] == -9) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-09:00'] ?></option>
								<option value="-8.5"<?php if ($user['timezone'] == -8.5) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-08:30'] ?></option>
								<option value="-8"<?php if ($user['timezone'] == -8) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-08:00'] ?></option>
								<option value="-7"<?php if ($user['timezone'] == -7) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-07:00'] ?></option>
								<option value="-6"<?php if ($user['timezone'] == -6) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-06:00'] ?></option>
								<option value="-5"<?php if ($user['timezone'] == -5) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-05:00'] ?></option>
								<option value="-4"<?php if ($user['timezone'] == -4) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-04:00'] ?></option>
								<option value="-3.5"<?php if ($user['timezone'] == -3.5) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-03:30'] ?></option>
								<option value="-3"<?php if ($user['timezone'] == -3) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-03:00'] ?></option>
								<option value="-2"<?php if ($user['timezone'] == -2) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-02:00'] ?></option>
								<option value="-1"<?php if ($user['timezone'] == -1) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-01:00'] ?></option>
								<option value="0"<?php if ($user['timezone'] == 0) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC'] ?></option>
								<option value="1"<?php if ($user['timezone'] == 1) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+01:00'] ?></option>
								<option value="2"<?php if ($user['timezone'] == 2) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+02:00'] ?></option>
								<option value="3"<?php if ($user['timezone'] == 3) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+03:00'] ?></option>
								<option value="3.5"<?php if ($user['timezone'] == 3.5) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+03:30'] ?></option>
								<option value="4"<?php if ($user['timezone'] == 4) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+04:00'] ?></option>
								<option value="4.5"<?php if ($user['timezone'] == 4.5) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+04:30'] ?></option>
								<option value="5"<?php if ($user['timezone'] == 5) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+05:00'] ?></option>
								<option value="5.5"<?php if ($user['timezone'] == 5.5) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+05:30'] ?></option>
								<option value="5.75"<?php if ($user['timezone'] == 5.75) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+05:45'] ?></option>
								<option value="6"<?php if ($user['timezone'] == 6) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+06:00'] ?></option>
								<option value="6.5"<?php if ($user['timezone'] == 6.5) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+06:30'] ?></option>
								<option value="7"<?php if ($user['timezone'] == 7) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+07:00'] ?></option>
								<option value="8"<?php if ($user['timezone'] == 8) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+08:00'] ?></option>
								<option value="8.75"<?php if ($user['timezone'] == 8.75) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+08:45'] ?></option>
								<option value="9"<?php if ($user['timezone'] == 9) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+09:00'] ?></option>
								<option value="9.5"<?php if ($user['timezone'] == 9.5) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+09:30'] ?></option>
								<option value="10"<?php if ($user['timezone'] == 10) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+10:00'] ?></option>
								<option value="10.5"<?php if ($user['timezone'] == 10.5) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+10:30'] ?></option>
								<option value="11"<?php if ($user['timezone'] == 11) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+11:00'] ?></option>
								<option value="11.5"<?php if ($user['timezone'] == 11.5) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+11:30'] ?></option>
								<option value="12"<?php if ($user['timezone'] == 12) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+12:00'] ?></option>
								<option value="12.75"<?php if ($user['timezone'] == 12.75) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+12:45'] ?></option>
								<option value="13"<?php if ($user['timezone'] == 13) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+13:00'] ?></option>
								<option value="14"<?php if ($user['timezone'] == 14) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+14:00'] ?></option>
							</select>
							<br /></label>
							<div class="rbox">
								<label><input type="checkbox" name="form[dst]" value="1"<?php if ($user['dst'] == '1') echo ' checked="checked"' ?> /><?php echo $lang_prof_reg['DST'] ?><br /></label>
							</div>
							<label><?php echo $lang_prof_reg['Time format'] ?>

							<br /><select name="form[time_format]">
<?php
								foreach (array_unique($forum_time_formats) as $key => $time_format)
								{
									echo "\t\t\t\t\t\t\t\t".'<option value="'.$key.'"';
									if ($user['time_format'] == $key)
										echo ' selected="selected"';
									echo '>'. format_time(time(), false, null, $time_format, true, true);
									if ($key == 0)
										echo ' ('.$lang_prof_reg['Default'].')';
									echo "</option>\n";
								}
								?>
							</select>
							<br /></label>
							<label><?php echo $lang_prof_reg['Date format'] ?>

							<br /><select name="form[date_format]">
<?php
								foreach (array_unique($forum_date_formats) as $key => $date_format)
								{
									echo "\t\t\t\t\t\t\t\t".'<option value="'.$key.'"';
									if ($user['date_format'] == $key)
										echo ' selected="selected"';
									echo '>'. format_time(time(), true, $date_format, null, false, true);
									if ($key == 0)
										echo ' ('.$lang_prof_reg['Default'].')';
									echo "</option>\n";
								}
								?>
							</select>
							<br /></label>

<?php

		$languages = forum_list_langs();

		// Only display the language selection box if there's more than one language available
		if (count($languages) > 1)
		{

?>
							<label><?php echo $lang_prof_reg['Language'] ?>
							<br /><select name="form[language]">
<?php

			foreach ($languages as $temp)
			{
				if ($user['language'] == $temp)
					echo "\t\t\t\t\t\t\t\t".'<option value="'.$temp.'" selected="selected">'.$temp.'</option>'."\n";
				else
					echo "\t\t\t\t\t\t\t\t".'<option value="'.$temp.'">'.$temp.'</option>'."\n";
			}

?>
							</select>
							<br /></label>
<?php

		}

?>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_profile['User activity'] ?></legend>
						<div class="infldset">
							<p><?php printf($lang_profile['Registered info'], format_time($user['registered'], true).(($pun_user['is_admmod']) ? ' (<a href="moderate.php?get_host='.pun_htmlspecialchars($user['registration_ip']).'">'.pun_htmlspecialchars($user['registration_ip']).'</a>)' : '')) ?></p>
							<p><?php printf($lang_profile['Last post info'], $last_post) ?></p>
							<p><?php printf($lang_profile['Last visit info'], format_time($user['last_visit'])) ?></p>
							<?php echo $posts_field ?>
<?php if ($pun_user['is_admmod']): ?>							<label><?php echo $lang_profile['Admin note'] ?><br />
							<input id="admin_note" type="text" name="admin_note" value="<?php echo pun_htmlspecialchars($user['admin_note']) ?>" size="30" maxlength="30" /><br /></label>
<?php endif; ?>						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="update" value="<?php echo $lang_common['Submit'] ?>" /> <?php echo $lang_profile['Instructions'] ?></p>
			</form>
		</div>
	</div>
<?php

	}
	else if ($section == 'personal')
	{
		if ($pun_user['g_set_title'] == '1')
			$title_field = '<label>'.$lang_common['Title'].' <em>('.$lang_profile['Leave blank'].')</em><br /><input type="text" name="title" value="'.pun_htmlspecialchars($user['title']).'" size="30" maxlength="50" /><br /></label>'."\n";

		$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_common['Profile'], $lang_profile['Section personal']);
		define('PUN_ACTIVE_PAGE', 'profile');
		require PUN_ROOT.'header.php';

		generate_profile_menu('personal');

?>
	<div class="blockform">
		<h2><span><?php echo pun_htmlspecialchars($user['username']).' - '.$lang_profile['Section personal'] ?></span></h2>
		<div class="box">
			<form id="profile2" method="post" action="profile.php?section=personal&amp;id=<?php echo $id ?>">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_profile['Personal details legend'] ?></legend>
						<div class="infldset">
							<input type="hidden" name="form_sent" value="1" />
							<label><?php echo $lang_profile['Realname'] ?><br /><input type="text" name="form[realname]" value="<?php echo pun_htmlspecialchars($user['realname']) ?>" size="40" maxlength="40" /><br /></label>
<?php if (isset($title_field)): ?>							<?php echo $title_field ?>
<?php endif; ?>							<label><?php echo $lang_profile['Location'] ?><br /><input type="text" name="form[location]" value="<?php echo pun_htmlspecialchars($user['location']) ?>" size="30" maxlength="30" /><br /></label>
<?php if ($pun_user['g_post_links'] == '1' || $pun_user['g_id'] == PUN_ADMIN) : ?>							<label><?php echo $lang_profile['Website'] ?><br /><input type="text" name="form[url]" value="<?php echo pun_htmlspecialchars($user['url']) ?>" size="50" maxlength="80" /><br /></label>
<?php endif; ?>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="update" value="<?php echo $lang_common['Submit'] ?>" /> <?php echo $lang_profile['Instructions'] ?></p>
			</form>
		</div>
	</div>
<?php

	}
	else if ($section == 'messaging')
	{

		$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_common['Profile'], $lang_profile['Section messaging']);
		define('PUN_ACTIVE_PAGE', 'profile');
		require PUN_ROOT.'header.php';

		generate_profile_menu('messaging');

?>
	<div class="blockform">
		<h2><span><?php echo pun_htmlspecialchars($user['username']).' - '.$lang_profile['Section messaging'] ?></span></h2>
		<div class="box">
			<form id="profile3" method="post" action="profile.php?section=messaging&amp;id=<?php echo $id ?>">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_profile['Contact details legend'] ?></legend>
						<div class="infldset">
							<input type="hidden" name="form_sent" value="1" />
							<label><?php echo $lang_profile['Jabber'] ?><br /><input id="jabber" type="text" name="form[jabber]" value="<?php echo pun_htmlspecialchars($user['jabber']) ?>" size="40" maxlength="75" /><br /></label>
							<label><?php echo $lang_profile['ICQ'] ?><br /><input id="icq" type="text" name="form[icq]" value="<?php echo $user['icq'] ?>" size="12" maxlength="12" /><br /></label>
							<label><?php echo $lang_profile['MSN'] ?><br /><input id="msn" type="text" name="form[msn]" value="<?php echo pun_htmlspecialchars($user['msn']) ?>" size="40" maxlength="50" /><br /></label>
							<label><?php echo $lang_profile['AOL IM'] ?><br /><input id="aim" type="text" name="form[aim]" value="<?php echo pun_htmlspecialchars($user['aim']) ?>" size="20" maxlength="30" /><br /></label>
							<label><?php echo $lang_profile['Yahoo'] ?><br /><input id="yahoo" type="text" name="form[yahoo]" value="<?php echo pun_htmlspecialchars($user['yahoo']) ?>" size="20" maxlength="30" /><br /></label>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="update" value="<?php echo $lang_common['Submit'] ?>" /> <?php echo $lang_profile['Instructions'] ?></p>
			</form>
		</div>
	</div>
<?php

	}
	else if ($section == 'personality')
	{
		if ($pun_config['o_avatars'] == '0' && $pun_config['o_signatures'] == '0')
			message($lang_common['Bad request']);

		$avatar_field = '<span><a href="profile.php?action=upload_avatar&amp;id='.$id.'">'.$lang_profile['Change avatar'].'</a></span>';

		$user_avatar = generate_avatar_markup($id);
		if ($user_avatar)
			$avatar_field .= ' <span><a href="profile.php?action=delete_avatar&amp;id='.$id.'">'.$lang_profile['Delete avatar'].'</a></span>';
		else
			$avatar_field = '<span><a href="profile.php?action=upload_avatar&amp;id='.$id.'">'.$lang_profile['Upload avatar'].'</a></span>';

		if ($user['signature'] != '')
			$signature_preview = '<p>'.$lang_profile['Sig preview'].'</p>'."\n\t\t\t\t\t\t\t".'<div class="postsignature postmsg">'."\n\t\t\t\t\t\t\t\t".'<hr />'."\n\t\t\t\t\t\t\t\t".$parsed_signature."\n\t\t\t\t\t\t\t".'</div>'."\n";
		else
			$signature_preview = '<p>'.$lang_profile['No sig'].'</p>'."\n";

		$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_common['Profile'], $lang_profile['Section personality']);
		define('PUN_ACTIVE_PAGE', 'profile');
		require PUN_ROOT.'header.php';

		generate_profile_menu('personality');


?>
	<div class="blockform">
		<h2><span><?php echo pun_htmlspecialchars($user['username']).' - '.$lang_profile['Section personality'] ?></span></h2>
		<div class="box">
			<form id="profile4" method="post" action="profile.php?section=personality&amp;id=<?php echo $id ?>">
				<div><input type="hidden" name="form_sent" value="1" /></div>
<?php if ($pun_config['o_avatars'] == '1'): ?>				<div class="inform">
					<fieldset id="profileavatar">
						<legend><?php echo $lang_profile['Avatar legend'] ?></legend>
						<div class="infldset">
<?php if ($user_avatar): ?>							<div class="useravatar"><?php echo $user_avatar ?></div>
<?php endif; ?>							<p><?php echo $lang_profile['Avatar info'] ?></p>
							<p class="clearb actions"><?php echo $avatar_field ?></p>
						</div>
					</fieldset>
				</div>
<?php endif; if ($pun_config['o_signatures'] == '1'): ?>				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_profile['Signature legend'] ?></legend>
						<div class="infldset">
							<p><?php echo $lang_profile['Signature info'] ?></p>
							<div class="txtarea">
								<label><?php printf($lang_profile['Sig max size'], forum_number_format($pun_config['p_sig_length']), $pun_config['p_sig_lines']) ?><br />
								<textarea name="signature" rows="4" cols="65"><?php echo pun_htmlspecialchars($user['signature']) ?></textarea><br /></label>
							</div>
							<ul class="bblinks">
								<li><span><a href="help.php#bbcode" onclick="window.open(this.href); return false;"><?php echo $lang_common['BBCode'] ?></a> <?php echo ($pun_config['p_sig_bbcode'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
								<li><span><a href="help.php#url" onclick="window.open(this.href); return false;"><?php echo $lang_common['url tag'] ?></a> <?php echo ($pun_config['p_sig_bbcode'] == '1' && $pun_user['g_post_links'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span>
								<li><span><a href="help.php#img" onclick="window.open(this.href); return false;"><?php echo $lang_common['img tag'] ?></a> <?php echo ($pun_config['p_sig_bbcode'] == '1' && $pun_config['p_sig_img_tag'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
								<li><span><a href="help.php#smilies" onclick="window.open(this.href); return false;"><?php echo $lang_common['Smilies'] ?></a> <?php echo ($pun_config['o_smilies_sig'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
							</ul>
							<?php echo $signature_preview ?>
						</div>
					</fieldset>
				</div>
<?php endif; ?>				<p class="buttons"><input type="submit" name="update" value="<?php echo $lang_common['Submit'] ?>" /> <?php echo $lang_profile['Instructions'] ?></p>
			</form>
		</div>
	</div>
<?php

	}
	else if ($section == 'display')
	{
		$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_common['Profile'], $lang_profile['Section display']);
		define('PUN_ACTIVE_PAGE', 'profile');
		require PUN_ROOT.'header.php';

		generate_profile_menu('display');

?>
	<div class="blockform">
		<h2><span><?php echo pun_htmlspecialchars($user['username']).' - '.$lang_profile['Section display'] ?></span></h2>
		<div class="box">
			<form id="profile5" method="post" action="profile.php?section=display&amp;id=<?php echo $id ?>">
				<div><input type="hidden" name="form_sent" value="1" /></div>
<?php

		$styles = forum_list_styles();

		// Only display the style selection box if there's more than one style available
		if (count($styles) == 1)
			echo "\t\t\t".'<div><input type="hidden" name="form[style]" value="'.$styles[0].'" /></div>'."\n";
		else if (count($styles) > 1)
		{

?>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_profile['Style legend'] ?></legend>
						<div class="infldset">
							<label><?php echo $lang_profile['Styles'] ?><br />
							<select name="form[style]">
<?php

			foreach ($styles as $temp)
			{
				if ($user['style'] == $temp)
					echo "\t\t\t\t\t\t\t\t".'<option value="'.$temp.'" selected="selected">'.str_replace('_', ' ', $temp).'</option>'."\n";
				else
					echo "\t\t\t\t\t\t\t\t".'<option value="'.$temp.'">'.str_replace('_', ' ', $temp).'</option>'."\n";
			}

?>
							</select>
							<br /></label>
						</div>
					</fieldset>
				</div>
<?php

		}

?>
<?php if ($pun_config['o_smilies'] == '1' || $pun_config['o_smilies_sig'] == '1' || $pun_config['o_signatures'] == '1' || $pun_config['o_avatars'] == '1' || ($pun_config['p_message_bbcode'] == '1' && $pun_config['p_message_img_tag'] == '1')): ?>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_profile['Post display legend'] ?></legend>
						<div class="infldset">
							<p><?php echo $lang_profile['Post display info'] ?></p>
							<div class="rbox">
<?php if ($pun_config['o_smilies'] == '1' || $pun_config['o_smilies_sig'] == '1'): ?>								<label><input type="checkbox" name="form[show_smilies]" value="1"<?php if ($user['show_smilies'] == '1') echo ' checked="checked"' ?> /><?php echo $lang_profile['Show smilies'] ?><br /></label>
<?php endif; if ($pun_config['o_signatures'] == '1'): ?>								<label><input type="checkbox" name="form[show_sig]" value="1"<?php if ($user['show_sig'] == '1') echo ' checked="checked"' ?> /><?php echo $lang_profile['Show sigs'] ?><br /></label>
<?php endif; if ($pun_config['o_avatars'] == '1'): ?>								<label><input type="checkbox" name="form[show_avatars]" value="1"<?php if ($user['show_avatars'] == '1') echo ' checked="checked"' ?> /><?php echo $lang_profile['Show avatars'] ?><br /></label>
<?php endif; if ($pun_config['p_message_bbcode'] == '1' && $pun_config['p_message_img_tag'] == '1'): ?>								<label><input type="checkbox" name="form[show_img]" value="1"<?php if ($user['show_img'] == '1') echo ' checked="checked"' ?> /><?php echo $lang_profile['Show images'] ?><br /></label>
<?php endif; if ($pun_config['o_signatures'] == '1' && $pun_config['p_sig_bbcode'] == '1' && $pun_config['p_sig_img_tag'] == '1'): ?>								<label><input type="checkbox" name="form[show_img_sig]" value="1"<?php if ($user['show_img_sig'] == '1') echo ' checked="checked"' ?> /><?php echo $lang_profile['Show images sigs'] ?><br /></label>
<?php endif; ?>
							</div>
						</div>
					</fieldset>
				</div>
<?php endif; ?>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_profile['Pagination legend'] ?></legend>
						<div class="infldset">
							<label class="conl"><?php echo $lang_profile['Topics per page'] ?><br /><input type="text" name="form[disp_topics]" value="<?php echo $user['disp_topics'] ?>" size="6" maxlength="3" /><br /></label>
							<label class="conl"><?php echo $lang_profile['Posts per page'] ?><br /><input type="text" name="form[disp_posts]" value="<?php echo $user['disp_posts'] ?>" size="6" maxlength="3" /><br /></label>
							<p class="clearb"><?php echo $lang_profile['Paginate info'] ?> <?php echo $lang_profile['Leave blank'] ?></p>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="update" value="<?php echo $lang_common['Submit'] ?>" /> <?php echo $lang_profile['Instructions'] ?></p>
			</form>
		</div>
	</div>
<?php

	}
	else if ($section == 'privacy')
	{
		$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_common['Profile'], $lang_profile['Section privacy']);
		define('PUN_ACTIVE_PAGE', 'profile');
		require PUN_ROOT.'header.php';

		generate_profile_menu('privacy');

?>
	<div class="blockform">
		<h2><span><?php echo pun_htmlspecialchars($user['username']).' - '.$lang_profile['Section privacy'] ?></span></h2>
		<div class="box">
			<form id="profile6" method="post" action="profile.php?section=privacy&amp;id=<?php echo $id ?>">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_prof_reg['Privacy options legend'] ?></legend>
						<div class="infldset">
							<input type="hidden" name="form_sent" value="1" />
							<p><?php echo $lang_prof_reg['Email setting info'] ?></p>
							<div class="rbox">
								<label><input type="radio" name="form[email_setting]" value="0"<?php if ($user['email_setting'] == '0') echo ' checked="checked"' ?> /><?php echo $lang_prof_reg['Email setting 1'] ?><br /></label>
								<label><input type="radio" name="form[email_setting]" value="1"<?php if ($user['email_setting'] == '1') echo ' checked="checked"' ?> /><?php echo $lang_prof_reg['Email setting 2'] ?><br /></label>
								<label><input type="radio" name="form[email_setting]" value="2"<?php if ($user['email_setting'] == '2') echo ' checked="checked"' ?> /><?php echo $lang_prof_reg['Email setting 3'] ?><br /></label>
							</div>
						</div>
					</fieldset>
				</div>
<?php if ($pun_config['o_forum_subscriptions'] == '1' || $pun_config['o_topic_subscriptions'] == '1'): ?>				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_profile['Subscription legend'] ?></legend>
						<div class="infldset">
							<div class="rbox">
								<label><input type="checkbox" name="form[notify_with_post]" value="1"<?php if ($user['notify_with_post'] == '1') echo ' checked="checked"' ?> /><?php echo $lang_profile['Notify full'] ?><br /></label>
<?php if ($pun_config['o_topic_subscriptions'] == '1'): ?>								<label><input type="checkbox" name="form[auto_notify]" value="1"<?php if ($user['auto_notify'] == '1') echo ' checked="checked"' ?> /><?php echo $lang_profile['Auto notify full'] ?><br /></label>
<?php endif; ?>
							</div>
						</div>
					</fieldset>
				</div>
<?php endif; ?>				<p class="buttons"><input type="submit" name="update" value="<?php echo $lang_common['Submit'] ?>" /> <?php echo $lang_profile['Instructions'] ?></p>
			</form>
		</div>
	</div>
<?php

	}
	else if ($section == 'admin')
	{
		if (!$pun_user['is_admmod'] || ($pun_user['g_moderator'] == '1' && $pun_user['g_mod_ban_users'] == '0'))
			message($lang_common['Bad request'], false, '403 Forbidden');

		$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_common['Profile'], $lang_profile['Section admin']);
		define('PUN_ACTIVE_PAGE', 'profile');
		require PUN_ROOT.'header.php';

		generate_profile_menu('admin');

?>
	<div class="blockform">
		<h2><span><?php echo pun_htmlspecialchars($user['username']).' - '.$lang_profile['Section admin'] ?></span></h2>
		<div class="box">
			<form id="profile7" method="post" action="profile.php?section=admin&amp;id=<?php echo $id ?>">
				<div class="inform">
				<input type="hidden" name="form_sent" value="1" />
					<fieldset>
<?php

		if ($pun_user['g_moderator'] == '1')
		{

?>
						<legend><?php echo $lang_profile['Delete ban legend'] ?></legend>
						<div class="infldset">
							<p><input type="submit" name="ban" value="<?php echo $lang_profile['Ban user'] ?>" /></p>
						</div>
					</fieldset>
				</div>
<?php

		}
		else
		{
			if ($pun_user['id'] != $id)
			{

?>
						<legend><?php echo $lang_profile['Group membership legend'] ?></legend>
						<div class="infldset">
							<select id="group_id" name="group_id">
<?php

				$result = $db->query('SELECT g_id, g_title FROM '.$db->prefix.'groups WHERE g_id!='.PUN_GUEST.' ORDER BY g_title') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());

				while ($cur_group = $db->fetch_assoc($result))
				{
					if ($cur_group['g_id'] == $user['g_id'] || ($cur_group['g_id'] == $pun_config['o_default_user_group'] && $user['g_id'] == ''))
						echo "\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
					else
						echo "\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
				}

?>
							</select>
							<input type="submit" name="update_group_membership" value="<?php echo $lang_profile['Save'] ?>" />
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
<?php

			}

?>
						<legend><?php echo $lang_profile['Delete ban legend'] ?></legend>
						<div class="infldset">
							<input type="submit" name="delete_user" value="<?php echo $lang_profile['Delete user'] ?>" /> <input type="submit" name="ban" value="<?php echo $lang_profile['Ban user'] ?>" />
						</div>
					</fieldset>
				</div>
<?php

			if ($user['g_moderator'] == '1' || $user['g_id'] == PUN_ADMIN)
			{

?>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_profile['Set mods legend'] ?></legend>
						<div class="infldset">
							<p><?php echo $lang_profile['Moderator in info'] ?></p>
<?php

				$result = $db->query('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.moderators FROM '.$db->prefix.'categories AS c INNER JOIN '.$db->prefix.'forums AS f ON c.id=f.cat_id WHERE f.redirect_url IS NULL ORDER BY c.disp_position, c.id, f.disp_position') or error('Unable to fetch category/forum list', __FILE__, __LINE__, $db->error());

				$cur_category = 0;
				while ($cur_forum = $db->fetch_assoc($result))
				{
					if ($cur_forum['cid'] != $cur_category) // A new category since last iteration?
					{
						if ($cur_category)
							echo "\n\t\t\t\t\t\t\t\t".'</div>';

						if ($cur_category != 0)
							echo "\n\t\t\t\t\t\t\t".'</div>'."\n";

						echo "\t\t\t\t\t\t\t".'<div class="conl">'."\n\t\t\t\t\t\t\t\t".'<p><strong>'.$cur_forum['cat_name'].'</strong></p>'."\n\t\t\t\t\t\t\t\t".'<div class="rbox">';
						$cur_category = $cur_forum['cid'];
					}

					$moderators = ($cur_forum['moderators'] != '') ? unserialize($cur_forum['moderators']) : array();

					echo "\n\t\t\t\t\t\t\t\t\t".'<label><input type="checkbox" name="moderator_in['.$cur_forum['fid'].']" value="1"'.((in_array($id, $moderators)) ? ' checked="checked"' : '').' />'.pun_htmlspecialchars($cur_forum['forum_name']).'<br /></label>'."\n";
				}

?>
								</div>
							</div>
							<br class="clearb" /><input type="submit" name="update_forums" value="<?php echo $lang_profile['Update forums'] ?>" />
						</div>
					</fieldset>
				</div>
<?php

			}
		}

?>
			</form>
		</div>
	</div>
<?php

	}
	else
		message($lang_common['Bad request']);

?>
	<div class="clearer"></div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}
