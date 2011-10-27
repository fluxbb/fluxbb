<?php

/**
 * Copyright (C) 2008-2011 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';

// Include UTF-8 function
require PUN_ROOT.'modules/utf8/functions/substr_replace.php';
require PUN_ROOT.'modules/utf8/functions/strcasecmp.php';

$action = isset($_GET['action']) ? $_GET['action'] : null;
$section = isset($_GET['section']) ? $_GET['section'] : null;
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id < 2)
	message($lang->t('Bad request'));

if ($action != 'change_pass' || !isset($_GET['key']))
{
	if ($pun_user['g_read_board'] == '0')
		message($lang->t('No view'));
	else if ($pun_user['g_view_users'] == '0' && ($pun_user['is_guest'] || $pun_user['id'] != $id))
		message($lang->t('No permission'));
}

// Load the profile.php/register.php language file
$lang->load('prof_reg');

// Load the profile.php language file
$lang->load('profile');


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

		// Fetch user information
		$query = $db->select(array('users' => 'u.*'), 'users AS u');
		$query->where = 'u.id = :id';

		$params = array(':id' => $id);

		$result = $query->run($params);
		$cur_user = $result[0];
		unset($query, $params, $result);

		if ($key == '' || $key != $cur_user['activate_key'])
			message($lang->t('Pass key bad').' <a href="mailto:'.$pun_config['o_admin_email'].'">'.$pun_config['o_admin_email'].'</a>.');
		else
		{
			$query = $db->update(array('password' => ':password', 'activate_string' => ':activate_string', 'activate_key' => ':activate_key'), 'users');
			$query->where = 'id = :user_id';

			$params = array(':user_id' => $id, ':password' => $cur_user['activate_string'], ':activate_string' => NULL, ':activate_key' => NULL);

			if (!empty($cur_user['salt']))
			{
				$query->fields['salt'] = ':salt';
				$params[':salt'] = NULL;
			}

			$query->run($params);
			unset($query, $params);

			message($lang->t('Pass updated'), true);
		}
	}

	// Make sure we are allowed to change this users password
	if ($pun_user['id'] != $id)
	{
		if (!$pun_user['is_admmod']) // A regular user trying to change another users password?
			message($lang->t('No permission'));
		else if ($pun_user['g_moderator'] == '1') // A moderator trying to change a users password?
		{
			$query = $db->select(array('group_id' => 'u.group_id', 'g_moderator' => 'g.g_moderator'), 'users AS u');

			$query->InnerJoin('g', 'groups AS g', 'g.g_id = u.group_id');

			$query->where = 'u.id = :user_id';

			$params = array(':user_id' => $id);

			$result = $query->run($params);
			if (empty($result))
				message($lang->t('Bad request'));

			$group_id = $result[0]['group_id'];
			$is_moderator = $result[0]['g_moderator'];

			unset($query, $params, $result);

			if ($pun_user['g_mod_edit_users'] == '0' || $pun_user['g_mod_change_passwords'] == '0' || $group_id == PUN_ADMIN || $is_moderator == '1')
				message($lang->t('No permission'));
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
			message($lang->t('Pass not match'));
		if (pun_strlen($new_password1) < 4)
			message($lang->t('Pass too short'));

		$query = $db->select(array('users' => 'u.*'), 'users AS u');
		$query->where = 'u.id = :user_id';

		$params = array(':user_id' => $id);

		$result = $query->run($params);
		$cur_user = $result[0];
		unset($query, $params, $result);

		$authorized = false;

		if (!empty($cur_user['password']))
		{
			$old_password_hash = pun_hash($old_password);

			if ($cur_user['password'] == $old_password_hash || $pun_user['is_admmod'])
				$authorized = true;
		}

		if (!$authorized)
			message($lang->t('Wrong pass'));

		$new_password_hash = pun_hash($new_password1);

		$query = $db->update(array('password' => ':password'), 'users');
		$query->where = 'id = :user_id';

		$params = array(':user_id' => $id, ':password' => $new_password_hash);

		if (!empty($cur_user['salt']))
		{
			$query->fields['salt'] = ':salt';
			$params[':salt'] = NULL;
		}

		$query->run($params);
		unset($query, $params);

		if ($pun_user['id'] == $id)
			pun_setcookie($pun_user['id'], $new_password_hash, time() + $pun_config['o_timeout_visit']);

		redirect('profile.php?section=essentials&amp;id='.$id, $lang->t('Pass updated redirect'));
	}

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Profile'), $lang->t('Change pass'));
	$required_fields = array('req_old_password' => $lang->t('Old pass'), 'req_new_password1' => $lang->t('New pass'), 'req_new_password2' => $lang->t('Confirm new pass'));
	$focus_element = array('change_pass', ((!$pun_user['is_admmod']) ? 'req_old_password' : 'req_new_password1'));
	define('PUN_ACTIVE_PAGE', 'profile');
	require PUN_ROOT.'header.php';

?>
<div class="blockform">
	<h2><span><?php echo $lang->t('Change pass') ?></span></h2>
	<div class="box">
		<form id="change_pass" method="post" action="profile.php?action=change_pass&amp;id=<?php echo $id ?>" onsubmit="return process_form(this)">
			<div class="inform">
				<input type="hidden" name="form_sent" value="1" />
				<fieldset>
					<legend><?php echo $lang->t('Change pass legend') ?></legend>
					<div class="infldset">
<?php if (!$pun_user['is_admmod']): ?>						<label class="required"><strong><?php echo $lang->t('Old pass') ?> <span><?php echo $lang->t('Required') ?></span></strong><br />
						<input type="password" name="req_old_password" size="16" /><br /></label>
<?php endif; ?>						<label class="conl required"><strong><?php echo $lang->t('New pass') ?> <span><?php echo $lang->t('Required') ?></span></strong><br />
						<input type="password" name="req_new_password1" size="16" /><br /></label>
						<label class="conl required"><strong><?php echo $lang->t('Confirm new pass') ?> <span><?php echo $lang->t('Required') ?></span></strong><br />
						<input type="password" name="req_new_password2" size="16" /><br /></label>
						<p class="clearb"><?php echo $lang->t('Pass info') ?></p>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="update" value="<?php echo $lang->t('Submit') ?>" /> <a href="javascript:history.go(-1)"><?php echo $lang->t('Go back') ?></a></p>
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
			message($lang->t('No permission'));
		else if ($pun_user['g_moderator'] == '1') // A moderator trying to change a users email?
		{
			$query = $db->select(array('group_id' => 'u.group_id', 'g_moderator' => 'g.g_moderator'), 'users AS u');

			$query->InnerJoin('g', 'groups AS g', 'g.g_id = u.group_id');

			$query->where = 'u.id = :user_id';

			$params = array(':user_id' => $id);

			$result = $query->run($params);
			if (empty($result))
				message($lang->t('Bad request'));

			$group_id = $result[0]['group_id'];
			$is_moderator = $result[0]['g_moderator'];

			unset($query, $params, $result);

			if ($pun_user['g_mod_edit_users'] == '0' || $group_id == PUN_ADMIN || $is_moderator == '1')
				message($lang->t('No permission'));
		}
	}

	if (isset($_GET['key']))
	{
		$key = $_GET['key'];

		$query = $db->select(array('activate_string' => 'u.activate_string', 'activate_key' => 'u.activate_key'), 'users AS u');
		$query->where = 'u.id = :user_id';

		$params = array(':user_id' => $id);

		$result = $query->run($params);

		$new_email = $result[0]['activate_string'];
		$new_email_key = $result[0]['activate_key'];

		unset($query, $params, $result);

		if ($key == '' || $key != $new_email_key)
			message($lang->t('Email key bad').' <a href="mailto:'.$pun_config['o_admin_email'].'">'.$pun_config['o_admin_email'].'</a>.');
		else
		{
			$query = $db->update(array('email' => 'activate_string', 'activate_string' => ':activate_string', 'activate_key' => ':activate_key'), 'users');
			$query->where = 'id = :user_id';

			$params = array(':activate_string' => NULL, ':activate_key' => NULL, ':user_id' => $id);

			$query->run($params);
			unset($query, $params);

			message($lang->t('Email updated'), true);
		}
	}
	else if (isset($_POST['form_sent']))
	{
		if (pun_hash($_POST['req_password']) !== $pun_user['password'])
			message($lang->t('Wrong pass'));

		require PUN_ROOT.'include/email.php';

		// Validate the email address
		$new_email = strtolower(trim($_POST['req_new_email']));
		if (!is_valid_email($new_email))
			message($lang->t('Invalid email'));

		// Check if it's a banned email address
		if (is_banned_email($new_email))
		{
			if ($pun_config['p_allow_banned_email'] == '0')
				message($lang->t('Banned email'));
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
		$query = $db->select(array('id' => 'u.id', 'username' => 'u.username'), 'users AS u');
		$query->where = 'u.email = :email';

		$params = array(':email' => $new_email);

		$result = $query->run($params);
		if (!empty($result))
		{
			if ($pun_config['p_allow_dupe_email'] == '0')
				message($lang->t('Dupe email'));
			else if ($pun_config['o_mailing_list'] != '')
			{
				$dupe_list = array();
				foreach ($result as $cur_dupe)
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
		unset($query, $params, $result);

		$new_email_key = random_pass(8);

		$query = $db->update(array('activate_string' => ':activate_string', 'activate_key' => ':activate_key'), 'users');
		$query->where = 'id = :id';

		$params = array(':activate_string' => $new_email, ':activate_key' => $new_email_key, ':id' => $id);

		$query->run($params);
		unset($query, $params);

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

		message($lang->t('Activate email sent').' <a href="mailto:'.$pun_config['o_admin_email'].'">'.$pun_config['o_admin_email'].'</a>.', true);
	}

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Profile'), $lang->t('Change email'));
	$required_fields = array('req_new_email' => $lang->t('New email'), 'req_password' => $lang->t('Password'));
	$focus_element = array('change_email', 'req_new_email');
	define('PUN_ACTIVE_PAGE', 'profile');
	require PUN_ROOT.'header.php';

?>
<div class="blockform">
	<h2><span><?php echo $lang->t('Change email') ?></span></h2>
	<div class="box">
		<form id="change_email" method="post" action="profile.php?action=change_email&amp;id=<?php echo $id ?>" id="change_email" onsubmit="return process_form(this)">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang->t('Email legend') ?></legend>
					<div class="infldset">
						<input type="hidden" name="form_sent" value="1" />
						<label class="required"><strong><?php echo $lang->t('New email') ?> <span><?php echo $lang->t('Required') ?></span></strong><br /><input type="text" name="req_new_email" size="50" maxlength="80" /><br /></label>
						<label class="required"><strong><?php echo $lang->t('Password') ?> <span><?php echo $lang->t('Required') ?></span></strong><br /><input type="password" name="req_password" size="16" /><br /></label>
						<p><?php echo $lang->t('Email instructions') ?></p>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="new_email" value="<?php echo $lang->t('Submit') ?>" /> <a href="javascript:history.go(-1)"><?php echo $lang->t('Go back') ?></a></p>
		</form>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


else if ($action == 'upload_avatar' || $action == 'upload_avatar2')
{
	if ($pun_config['o_avatars'] == '0')
		message($lang->t('Avatars disabled'));

	if ($pun_user['id'] != $id && !$pun_user['is_admmod'])
		message($lang->t('No permission'));

	if (isset($_POST['form_sent']))
	{
		if (!isset($_FILES['req_file']))
			message($lang->t('No file'));

		$uploaded_file = $_FILES['req_file'];

		// Make sure the upload went smooth
		if (isset($uploaded_file['error']))
		{
			switch ($uploaded_file['error'])
			{
				case 1: // UPLOAD_ERR_INI_SIZE
				case 2: // UPLOAD_ERR_FORM_SIZE
					message($lang->t('Too large ini'));
					break;

				case 3: // UPLOAD_ERR_PARTIAL
					message($lang->t('Partial upload'));
					break;

				case 4: // UPLOAD_ERR_NO_FILE
					message($lang->t('No file'));
					break;

				case 6: // UPLOAD_ERR_NO_TMP_DIR
					message($lang->t('No tmp directory'));
					break;

				default:
					// No error occured, but was something actually uploaded?
					if ($uploaded_file['size'] == 0)
						message($lang->t('No file'));
					break;
			}
		}

		if (is_uploaded_file($uploaded_file['tmp_name']))
		{
			// Preliminary file check, adequate in most cases
			$allowed_types = array('image/gif', 'image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png');
			if (!in_array($uploaded_file['type'], $allowed_types))
				message($lang->t('Bad type'));

			// Make sure the file isn't too big
			if ($uploaded_file['size'] > $pun_config['o_avatars_size'])
				message($lang->t('Too large').' '.forum_number_format($pun_config['o_avatars_size']).' '.$lang->t('bytes').'.');

			// Move the file to the avatar directory. We do this before checking the width/height to circumvent open_basedir restrictions
			if (!@move_uploaded_file($uploaded_file['tmp_name'], PUN_ROOT.$pun_config['o_avatars_dir'].'/'.$id.'.tmp'))
				message($lang->t('Move failed').' <a href="mailto:'.$pun_config['o_admin_email'].'">'.$pun_config['o_admin_email'].'</a>.');

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
				message($lang->t('Bad type'));
			}

			// Now check the width/height
			if (empty($width) || empty($height) || $width > $pun_config['o_avatars_width'] || $height > $pun_config['o_avatars_height'])
			{
				@unlink(PUN_ROOT.$pun_config['o_avatars_dir'].'/'.$id.'.tmp');
				message($lang->t('Too wide or high').' '.$pun_config['o_avatars_width'].'x'.$pun_config['o_avatars_height'].' '.$lang->t('pixels').'.');
			}

			// Delete any old avatars and put the new one in place
			delete_avatar($id);
			@rename(PUN_ROOT.$pun_config['o_avatars_dir'].'/'.$id.'.tmp', PUN_ROOT.$pun_config['o_avatars_dir'].'/'.$id.$extension);
			@chmod(PUN_ROOT.$pun_config['o_avatars_dir'].'/'.$id.$extension, 0644);
		}
		else
			message($lang->t('Unknown failure'));

		redirect('profile.php?section=personality&amp;id='.$id, $lang->t('Avatar upload redirect'));
	}

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Profile'), $lang->t('Upload avatar'));
	$required_fields = array('req_file' => $lang->t('File'));
	$focus_element = array('upload_avatar', 'req_file');
	define('PUN_ACTIVE_PAGE', 'profile');
	require PUN_ROOT.'header.php';

?>
<div class="blockform">
	<h2><span><?php echo $lang->t('Upload avatar') ?></span></h2>
	<div class="box">
		<form id="upload_avatar" method="post" enctype="multipart/form-data" action="profile.php?action=upload_avatar2&amp;id=<?php echo $id ?>" onsubmit="return process_form(this)">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang->t('Upload avatar legend') ?></legend>
					<div class="infldset">
						<input type="hidden" name="form_sent" value="1" />
						<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $pun_config['o_avatars_size'] ?>" />
						<label class="required"><strong><?php echo $lang->t('File') ?> <span><?php echo $lang->t('Required') ?></span></strong><br /><input name="req_file" type="file" size="40" /><br /></label>
						<p><?php echo $lang->t('Avatar desc').' '.$pun_config['o_avatars_width'].' x '.$pun_config['o_avatars_height'].' '.$lang->t('pixels').' '.$lang->t('and').' '.forum_number_format($pun_config['o_avatars_size']).' '.$lang->t('bytes').' ('.file_size($pun_config['o_avatars_size']).').' ?></p>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="upload" value="<?php echo $lang->t('Upload') ?>" /> <a href="javascript:history.go(-1)"><?php echo $lang->t('Go back') ?></a></p>
		</form>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


else if ($action == 'delete_avatar')
{
	if ($pun_user['id'] != $id && !$pun_user['is_admmod'])
		message($lang->t('No permission'));

	confirm_referrer('profile.php');

	delete_avatar($id);

	redirect('profile.php?section=personality&amp;id='.$id, $lang->t('Avatar deleted redirect'));
}


else if (isset($_POST['update_group_membership']))
{
	if ($pun_user['g_id'] > PUN_ADMIN)
		message($lang->t('No permission'));

	confirm_referrer('profile.php');

	$new_group_id = intval($_POST['group_id']);

	$query = $db->update(array('group_id' => ':group_id'), 'users');
	$query->where = 'id = :id';

	$params = array(':group_id' => $new_group_id, ':id' => $id);

	$query->run($params);
	unset($query, $params);

	// Regenerate the users info cache
	$cache->delete('boardstats');

	$query = $db->select(array('g_moderator' => 'g.g_moderator'), 'groups AS g');
	$query->where = 'g.g_id = :group_id';

	$params = array(':group_id' => $new_group_id);

	$result = $query->run($params);
	$new_group_mod = $result[0];
	unset($query, $params, $result);

	// If the user was a moderator or an administrator, we remove him/her from the moderator list in all forums as well
	if ($new_group_id != PUN_ADMIN && $new_group_mod != '1')
	{
		$query = $db->select(array('fid' => 'f.id', 'moderators' => 'f.moderators'), 'forums AS f');
		$result = $db->query($query);
		unset($query);

		$update_query = $db->update(array('moderators' => ':moderators'), 'forums');
		$update_query->where = 'id = :forum_id';

		foreach ($result as $cur_forum)
		{
			$cur_moderators = ($cur_forum['moderators'] != '') ? unserialize($cur_forum['moderators']) : array();

			if (in_array($id, $cur_moderators))
			{
				$username = array_search($id, $cur_moderators);
				unset($cur_moderators[$username]);
				$cur_moderators = (!empty($cur_moderators)) ? serialize($cur_moderators) : NULL;

				$params = array(':moderators' => $cur_moderators, ':forum_id' => $cur_forum['id']);

				$update_query->run($params);
				unset($params);
			}
		}

		unset($result, $update_query);
	}

	redirect('profile.php?section=admin&amp;id='.$id, $lang->t('Group membership redirect'));
}


else if (isset($_POST['update_forums']))
{
	if ($pun_user['g_id'] > PUN_ADMIN)
		message($lang->t('No permission'));

	confirm_referrer('profile.php');

	// Get the username of the user we are processing
	$query = $db->select(array('username' => 'u.username'), 'users AS u');
	$query->where = 'u.id = :id';

	$params = array(':id' => $id);

	$result = $query->run($params);
	$username = $result[0]['username'];
	unset($query, $params, $result);

	$moderator_in = (isset($_POST['moderator_in'])) ? array_keys($_POST['moderator_in']) : array();

	// Loop through all forums
	$query = $db->select(array('fid' => 'f.id', 'moderators' => 'f.moderators'), 'forums AS f');
	$result = $db->query($query);
	unset($query);

	$update_query = $db->update(array('moderators' => ':moderators'), 'forums');
	$update_query->where = 'id = :forum_id';

	foreach ($result as $cur_forum)
	{
		$cur_moderators = ($cur_forum['moderators'] != '') ? unserialize($cur_forum['moderators']) : array();
		// If the user should have moderator access (and he/she doesn't already have it)
		if (in_array($cur_forum['id'], $moderator_in) && !in_array($id, $cur_moderators))
		{
			$cur_moderators[$username] = $id;
			uksort($cur_moderators, 'utf8_strcasecmp');

			$params = array(':moderators' => serialize($cur_moderators), ':forum_id' => $cur_forum['id']);

			$update_query->run($params);
			unset($params);
		}
		// If the user shouldn't have moderator access (and he/she already has it)
		else if (!in_array($cur_forum['id'], $moderator_in) && in_array($id, $cur_moderators))
		{
			unset($cur_moderators[$username]);
			$cur_moderators = (!empty($cur_moderators)) ? serialize($cur_moderators) : NULL;

			$params = array(':moderators' => $cur_moderators, ':forum_id' => $cur_forum['id']);

			$update_query->run($params);
			unset($params);
		}
	}

	unset($result, $update_query);

	redirect('profile.php?section=admin&amp;id='.$id, $lang->t('Update forums redirect'));
}


else if (isset($_POST['ban']))
{
	if ($pun_user['g_id'] != PUN_ADMIN && ($pun_user['g_moderator'] != '1' || $pun_user['g_mod_ban_users'] == '0'))
		message($lang->t('No permission'));

	// Get the username of the user we are banning
	$result = $db->query('SELECT username FROM '.$db->prefix.'users WHERE id='.$id) or error('Unable to fetch username', __FILE__, __LINE__, $db->error());
	$username = $db->result($result);

	// Check whether user is already banned
	$result = $db->query('SELECT id FROM '.$db->prefix.'bans WHERE username = \''.$db->escape($username).'\' ORDER BY expire IS NULL DESC, expire DESC LIMIT 1') or error('Unable to fetch ban ID', __FILE__, __LINE__, $db->error());
	if ($db->num_rows($result))
	{
		$ban_id = $db->result($result);
		redirect('admin_bans.php?edit_ban='.$ban_id.'&amp;exists', $lang->t('Ban redirect'));
	}
	else
		redirect('admin_bans.php?add_ban='.$id, $lang->t('Ban redirect'));
}


else if (isset($_POST['delete_user']) || isset($_POST['delete_user_comply']))
{
	if ($pun_user['g_id'] > PUN_ADMIN)
		message($lang->t('No permission'));

	confirm_referrer('profile.php');

	// Get the username and group of the user we are deleting
	$query = $db->select(array('group_id' => 'u.group_id', 'username' => 'u.username'), 'users AS u');
	$query->where = 'u.id = :id';

	$params = array(':id' => $id);

	$result = $query->run($params);

	$group_id = $result[0]['group_id'];
	$username = $result[0]['username'];

	unset($query, $params, $result);

	if ($group_id == PUN_ADMIN)
		message($lang->t('No delete admin message'));

	if (isset($_POST['delete_user_comply']))
	{
		// If the user is a moderator or an administrator, we remove him/her from the moderator list in all forums as well
		$query = $db->select(array('g_moderator' => 'g.g_moderator'), 'groups AS g');
		$query->where = 'g.g_id = :g_id';

		$params = array(':g_id' => $group_id);

		$result = $query->run($params);
		$group_mod = $result[0]['g_moderator'];
		unset($query, $params, $result);

		if ($group_id == PUN_ADMIN || $group_mod == '1')
		{
			$query = $db->select(array('fid' => 'f.id', 'moderators' => 'f.moderators'), 'forums AS f');
			$result = $db->query($query);
			unset($query);

			$update_query = $db->update(array('moderators' => ':moderators'), 'forums');
			$update_query->where = 'id = :forum_id';

			foreach ($result as $cur_forum)
			{
				$cur_moderators = ($cur_forum['moderators'] != '') ? unserialize($cur_forum['moderators']) : array();

				if (in_array($id, $cur_moderators))
				{
					unset($cur_moderators[$username]);
					$cur_moderators = (!empty($cur_moderators)) ? serialize($cur_moderators) : NULL;

					$params = array(':moderators' => $cur_moderators, ':forum_id' => $cur_forum['id']);

					$update_query->run($params);
					unset($params);
				}
			}

			unset($result, $update_query);
		}

		// Delete any subscriptions and remove him/her from the online list (if they happen to be logged in)

		// Delete topic subscriptions
		$query = $db->delete('topic_subscriptions');
		$query->where = 'user_id = :user_id';
		$params = array(':user_id' => $id);

		$query->run($params);
		unset($query, $params);

		// Delete forum subscriptions
		$query = $db->delete('forum_subscriptions');
		$query->where = 'user_id = :user_id';
		$params = array(':user_id' => $id);

		$query->run($params);
		unset($query, $params);

		// Delete online entry
		$query = $db->delete('online');
		$query->where = 'user_id = :user_id';
		$params = array(':user_id' => $id);

		$query->run($params);
		unset($query, $params);

		// Should we delete all posts made by this user?
		if (isset($_POST['delete_posts']))
		{
			require PUN_ROOT.'include/search_idx.php';
			@set_time_limit(0);

			// Find all posts made by this user
			$query = $db->select(array('pid' => 'p.id', 'topic_id' => 'p.topic_id', 'forum_id' => 't.forum_id'), 'posts AS p');

			$query->InnerJoin('t', 'topics AS t', 't.id = p.topic_id');

			$query->InnerJoin('f', 'forums AS f', 'f.id = t.forum_id');

			$query->where = 'p.poster_id = :id';

			$params = array(':id' => $id);

			$result = $query->run($params);
			unset($query, $params);

			if (!empty($result))
			{
				// Determine whether this post is the "topic post" or not
				$query = $db->select(array('pid' => 'p.id'), 'posts AS p');
				$query->where = 'p.topic_id = :topic_id';
				$query->order = array('posted' => 'p.posted ASC');
				$query->limit = 1;

				foreach ($result as $cur_post)
				{
					$params = array(':topic_id' => $cur_post['topic_id']);
					$result2 = $query->run($params);

					if ($result2[0]['id'] == $cur_post['id'])
						delete_topic($cur_post['topic_id']);
					else
						delete_post($cur_post['id'], $cur_post['topic_id']);

					unset($params, $result2);

					update_forum($cur_post['forum_id']);
				}

				unset ($select_query);
			}

			unset($result);
		}
		else
		{
			// Set all his/her posts to guest
			$query = $db->update(array('poster_id' => '1'), 'posts');
			$query->where = 'poster_id = :id';

			$params = array(':id' => $id);

			$query->run($params);
			unset($query, $params);
		}

		// Delete the user
		$query = $db->delete('users');
		$query->where = 'id = :id';

		$params = array(':id' => $id);

		$query->run($params);
		unset($query, $params);

		// Delete user avatar
		delete_avatar($id);

		// Regenerate the users info cache
		$cache->delete('boardstats');

		redirect('index.php', $lang->t('User delete redirect'));
	}

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Profile'), $lang->t('Confirm delete user'));
	define('PUN_ACTIVE_PAGE', 'profile');
	require PUN_ROOT.'header.php';

?>
<div class="blockform">
	<h2><span><?php echo $lang->t('Confirm delete user') ?></span></h2>
	<div class="box">
		<form id="confirm_del_user" method="post" action="profile.php?id=<?php echo $id ?>">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang->t('Confirm delete legend') ?></legend>
					<div class="infldset">
						<p><?php echo $lang->t('Confirmation info').' <strong>'.pun_htmlspecialchars($username).'</strong>.' ?></p>
						<div class="rbox">
							<label><input type="checkbox" name="delete_posts" value="1" checked="checked" /><?php echo $lang->t('Delete posts') ?><br /></label>
						</div>
						<p class="warntext"><strong><?php echo $lang->t('Delete warning') ?></strong></p>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="delete_user_comply" value="<?php echo $lang->t('Delete') ?>" /> <a href="javascript:history.go(-1)"><?php echo $lang->t('Go back') ?></a></p>
		</form>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


else if (isset($_POST['form_sent']))
{
	// Fetch the user group of the user we are editing
	$query = $db->select(array('username' => 'u.username', 'group_id' => 'u.group_id', 'g_moderator' => 'g.g_moderator'), 'users AS u');

	$query->InnerJoin('g', 'groups AS g', 'g.g_id = u.group_id');

	$query->where = 'u.id = :id';

	$params = array(':id' => $id);

	$result = $query->run($params);
	if (empty($result))
		message($lang->t('Bad request'));

	$old_username = $result[0]['username'];
	$group_id = $result[0]['group_id'];
	$is_moderator = $result[0]['g_moderator'];

	unset($query, $params, $result);

	if ($pun_user['id'] != $id &&																	// If we arent the user (i.e. editing your own profile)
		(!$pun_user['is_admmod'] ||																	// and we are not an admin or mod
		($pun_user['g_id'] != PUN_ADMIN &&															// or we aren't an admin and ...
		($pun_user['g_mod_edit_users'] == '0' ||													// mods aren't allowed to edit users
		$group_id == PUN_ADMIN ||																	// or the user is an admin
		$is_moderator))))																			// or the user is another mod
		message($lang->t('No permission'));

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
				if (!Flux_Lang::languageExists($form['language']))
					message($lang->t('Bad request'));
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
						$lang->load('register');

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
				$form['email'] = strtolower(trim($_POST['req_email']));
				if (!is_valid_email($form['email']))
					message($lang->t('Invalid email'));
			}

			break;
		}

		case 'personal':
		{
			$form = array(
				'realname'		=> pun_trim($_POST['form']['realname']),
				'url'			=> pun_trim($_POST['form']['url']),
				'location'		=> pun_trim($_POST['form']['location']),
			);

			// Add http:// if the URL doesn't contain it already (while allowing https://, too)
			if ($form['url'] != '')
			{
				$url = url_valid($form['url']);

				if ($url === false)
					message($lang->t('Invalid website URL'));

				$form['url'] = $url['url'];
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
					$forbidden = array('member', 'moderator', 'administrator', 'banned', 'guest', utf8_strtolower($lang->t('Member')), utf8_strtolower($lang->t('Moderator')), utf8_strtolower($lang->t('Administrator')), utf8_strtolower($lang->t('Banned')), utf8_strtolower($lang->t('Guest')));

					if (in_array(utf8_strtolower($form['title']), $forbidden))
						message($lang->t('Forbidden title'));
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
				message($lang->t('Bad ICQ'));

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
					message(sprintf($lang->t('Sig too long'), $pun_config['p_sig_length'], pun_strlen($form['signature']) - $pun_config['p_sig_length']));
				else if (substr_count($form['signature'], "\n") > ($pun_config['p_sig_lines']-1))
					message(sprintf($lang->t('Sig too many lines'), $pun_config['p_sig_lines']));
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
					message($lang->t('Bad request'));
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
			message($lang->t('Bad request'));
	}


	// NULL for empty values
	$temp = array();
	foreach ($form as $key => $input)
	{
		$value = ($input !== '') ? $input : NULL;

		$temp[$key] = $value;
	}

	if (empty($temp))
		message($lang->t('Bad request'));


	$query = $db->update(array(), 'users');
	$query->where = 'id = :id';

	$params = array(':id' => $id);

	foreach ($temp as $key => $value)
	{
		$query->values[$key] = ':'.$key;
		$params[':'.$key] = $value;
	}

	$query->run($params);
	unset($query, $params);

	// If we changed the username we have to update some stuff
	if ($username_updated)
	{
		// Update all posts by this user
		$query = $db->update(array('poster' => ':poster'), 'posts');
		$query->where = 'poster_id = :poster_id';

		$params = array(':poster' => $form['username'], ':poster_id' => $id);

		$query->run($params);
		unset($query, $params);

		// Update all posts edited by this user
		$query = $db->update(array('edited_by' => ':poster'), 'posts');
		$query->where = 'edited_by = :old_username';

		$params = array(':poster' => $form['username'], ':old_username' => $old_username);

		$query->run($params);
		unset($query, $params);

		// Update all topic by this user
		$query = $db->update(array('poster' => ':poster'), 'topics');
		$query->where = 'poster = :old_username';

		$params = array(':poster' => $form['username'], ':old_username' => $old_username);

		$query->run($params);
		unset($query, $params);

		// Update all topics with a last post by this user
		$query = $db->update(array('last_poster' => ':poster'), 'topics');
		$query->where = 'poster = :old_username';

		$params = array(':poster' => $form['username'], ':old_username' => $old_username);

		$query->run($params);
		unset($query, $params);

		// Update all forums with a last post by this user
		$query = $db->update(array('last_poster' => ':poster'), 'forums');
		$query->where = 'last_poster = :old_username';

		$params = array(':poster' => $form['username'], ':old_username' => $old_username);

		$query->run($params);
		unset($query, $params);

		// Update all online table entries about this user
		$query = $db->update(array('ident' => ':username'), 'online');
		$query->where = 'ident = :old_username';

		$params = array(':username' => $form['username'], ':old_username' => $old_username);

		$query->run($params);
		unset($query, $params);

		// If the user is a moderator or an administrator we have to update the moderator lists
		$query = $db->select(array('group_id' => 'u.group_id'), 'users AS u');
		$query->where = 'u.id = :id';

		$params = array(':id' => $id);

		$result = $query->run($params);
		$group_id = $result[0];
		unset($query, $params, $result);

		$query = $db->select(array('g_moderator' => 'g.g_moderator'), 'groups AS g');
		$query->where = 'g.g_id = :g_id';

		$params = array(':g_id' => $group_id);

		$result = $query->run($params);
		$group_mod = $result[0];
		unset($query, $params, $result);

		if ($group_id == PUN_ADMIN || $group_mod == '1')
		{
			$query = $db->select(array('fid' => 'f.id', 'moderators' => 'f.moderators'), 'forums AS f');
			$result = $db->query($query);
			unset($query);

			$update_query = $db->update(array('moderators' => ':moderators'), 'forums');
			$update_query->where = 'id = :forum_id';

			foreach ($result as $cur_forum)
			{
				$cur_moderators = ($cur_forum['moderators'] != '') ? unserialize($cur_forum['moderators']) : array();

				if (in_array($id, $cur_moderators))
				{
					unset($cur_moderators[$old_username]);
					$cur_moderators[$form['username']] = $id;
					uksort($cur_moderators, 'utf8_strcasecmp');

					$params = array(':moderators' => serialize($cur_moderators), ':forum_id' => $cur_forum['id']);
					$update_query->run($params);
					unset($params);
				}
			}

			unset($result, $update_query);
		}

		// Regenerate the users info cache
		$cache->delete('boardstats');
	}

	redirect('profile.php?section='.$section.'&amp;id='.$id, $lang->t('Profile redirect'));
}

$query = $db->select(array('user' => 'u.*', 'group' => 'g.*'), 'users AS u');

$query->LeftJoin('g', 'groups AS g', 'g.g_id = u.group_id');

$query->where = 'u.id = :user_id';

$params = array(':user_id' => $id);

$result = $query->run($params);
if (empty($result))
	message($lang->t('Bad request'));

$user = $result[0];
unset ($result, $query, $params);

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

	$user_personal[] = '<dt>'.$lang->t('Username').'</dt>';
	$user_personal[] = '<dd>'.pun_htmlspecialchars($user['username']).'</dd>';

	$user_title_field = get_title($user);
	$user_personal[] = '<dt>'.$lang->t('Title').'</dt>';
	$user_personal[] = '<dd>'.(($pun_config['o_censoring'] == '1') ? censor_words($user_title_field) : $user_title_field).'</dd>';

	if ($user['realname'] != '')
	{
		$user_personal[] = '<dt>'.$lang->t('Realname').'</dt>';
		$user_personal[] = '<dd>'.pun_htmlspecialchars(($pun_config['o_censoring'] == '1') ? censor_words($user['realname']) : $user['realname']).'</dd>';
	}

	if ($user['location'] != '')
	{
		$user_personal[] = '<dt>'.$lang->t('Location').'</dt>';
		$user_personal[] = '<dd>'.pun_htmlspecialchars(($pun_config['o_censoring'] == '1') ? censor_words($user['location']) : $user['location']).'</dd>';
	}

	if ($user['url'] != '')
	{
		$user['url'] = pun_htmlspecialchars(($pun_config['o_censoring'] == '1') ? censor_words($user['url']) : $user['url']);
		$user_personal[] = '<dt>'.$lang->t('Website').'</dt>';
		$user_personal[] = '<dd><span class="website"><a href="'.$user['url'].'">'.$user['url'].'</a></span></dd>';
	}

	if ($user['email_setting'] == '0' && !$pun_user['is_guest'] && $pun_user['g_send_email'] == '1')
		$email_field = '<a href="mailto:'.$user['email'].'">'.$user['email'].'</a>';
	else if ($user['email_setting'] == '1' && !$pun_user['is_guest'] && $pun_user['g_send_email'] == '1')
		$email_field = '<a href="misc.php?email='.$id.'">'.$lang->t('Send email').'</a>';
	else
		$email_field = '';
	if ($email_field != '')
	{
		$user_personal[] = '<dt>'.$lang->t('Email').'</dt>';
		$user_personal[] = '<dd><span class="email">'.$email_field.'</span></dd>';
	}

	$user_messaging = array();

	if ($user['jabber'] != '')
	{
		$user_messaging[] = '<dt>'.$lang->t('Jabber').'</dt>';
		$user_messaging[] = '<dd>'.pun_htmlspecialchars(($pun_config['o_censoring'] == '1') ? censor_words($user['jabber']) : $user['jabber']).'</dd>';
	}

	if ($user['icq'] != '')
	{
		$user_messaging[] = '<dt>'.$lang->t('ICQ').'</dt>';
		$user_messaging[] = '<dd>'.$user['icq'].'</dd>';
	}

	if ($user['msn'] != '')
	{
		$user_messaging[] = '<dt>'.$lang->t('MSN').'</dt>';
		$user_messaging[] = '<dd>'.pun_htmlspecialchars(($pun_config['o_censoring'] == '1') ? censor_words($user['msn']) : $user['msn']).'</dd>';
	}

	if ($user['aim'] != '')
	{
		$user_messaging[] = '<dt>'.$lang->t('AOL IM').'</dt>';
		$user_messaging[] = '<dd>'.pun_htmlspecialchars(($pun_config['o_censoring'] == '1') ? censor_words($user['aim']) : $user['aim']).'</dd>';
	}

	if ($user['yahoo'] != '')
	{
		$user_messaging[] = '<dt>'.$lang->t('Yahoo').'</dt>';
		$user_messaging[] = '<dd>'.pun_htmlspecialchars(($pun_config['o_censoring'] == '1') ? censor_words($user['yahoo']) : $user['yahoo']).'</dd>';
	}

	$user_personality = array();

	if ($pun_config['o_avatars'] == '1')
	{
		$avatar_field = generate_avatar_markup($id);
		if ($avatar_field != '')
		{
			$user_personality[] = '<dt>'.$lang->t('Avatar').'</dt>';
			$user_personality[] = '<dd>'.$avatar_field.'</dd>';
		}
	}

	if ($pun_config['o_signatures'] == '1')
	{
		if (isset($parsed_signature))
		{
			$user_personality[] = '<dt>'.$lang->t('Signature').'</dt>';
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
			$quick_searches[] = '<a href="search.php?action=show_user_topics&amp;user_id='.$id.'">'.$lang->t('Show topics').'</a>';
			$quick_searches[] = '<a href="search.php?action=show_user_posts&amp;user_id='.$id.'">'.$lang->t('Show posts').'</a>';
		}
		if ($pun_user['is_admmod'] && $pun_config['o_topic_subscriptions'] == '1')
			$quick_searches[] = '<a href="search.php?action=show_subscriptions&amp;user_id='.$id.'">'.$lang->t('Show subscriptions').'</a>';

		if (!empty($quick_searches))
			$posts_field .= (($posts_field != '') ? ' - ' : '').implode(' - ', $quick_searches);
	}
	if ($posts_field != '')
	{
		$user_activity[] = '<dt>'.$lang->t('Posts').'</dt>';
		$user_activity[] = '<dd>'.$posts_field.'</dd>';
	}

	if ($user['num_posts'] > 0)
	{
		$user_activity[] = '<dt>'.$lang->t('Last post').'</dt>';
		$user_activity[] = '<dd>'.$last_post.'</dd>';
	}

	$user_activity[] = '<dt>'.$lang->t('Registered').'</dt>';
	$user_activity[] = '<dd>'.format_time($user['registered'], true).'</dd>';

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), sprintf($lang->t('Users profile'), pun_htmlspecialchars($user['username'])));
	define('PUN_ALLOW_INDEX', 1);
	define('PUN_ACTIVE_PAGE', 'index');
	require PUN_ROOT.'header.php';

?>
<div id="viewprofile" class="block">
	<h2><span><?php echo $lang->t('Profile') ?></span></h2>
	<div class="box">
		<div class="fakeform">
			<div class="inform">
				<fieldset>
				<legend><?php echo $lang->t('Section personal') ?></legend>
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
				<legend><?php echo $lang->t('Section messaging') ?></legend>
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
				<legend><?php echo $lang->t('Section personality') ?></legend>
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
				<legend><?php echo $lang->t('User activity') ?></legend>
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
				$username_field = '<label class="required"><strong>'.$lang->t('Username').' <span>'.$lang->t('Required').'</span></strong><br /><input type="text" name="req_username" value="'.pun_htmlspecialchars($user['username']).'" size="25" maxlength="25" /><br /></label>'."\n";
			else
				$username_field = '<p>'.sprintf($lang->t('Username info'), pun_htmlspecialchars($user['username'])).'</p>'."\n";

			$email_field = '<label class="required"><strong>'.$lang->t('Email').' <span>'.$lang->t('Required').'</span></strong><br /><input type="text" name="req_email" value="'.$user['email'].'" size="40" maxlength="80" /><br /></label><p><span class="email"><a href="misc.php?email='.$id.'">'.$lang->t('Send email').'</a></span></p>'."\n";
		}
		else
		{
			$username_field = '<p>'.$lang->t('Username').': '.pun_htmlspecialchars($user['username']).'</p>'."\n";

			if ($pun_config['o_regs_verify'] == '1')
				$email_field = '<p>'.sprintf($lang->t('Email info'), $user['email'].' - <a href="profile.php?action=change_email&amp;id='.$id.'">'.$lang->t('Change email').'</a>').'</p>'."\n";
			else
				$email_field = '<label class="required"><strong>'.$lang->t('Email').' <span>'.$lang->t('Required').'</span></strong><br /><input type="text" name="req_email" value="'.$user['email'].'" size="40" maxlength="80" /><br /></label>'."\n";
		}

		$posts_field = '';
		$posts_actions = array();

		if ($pun_user['g_id'] == PUN_ADMIN)
			$posts_field .= '<label>'.$lang->t('Posts').'<br /><input type="text" name="num_posts" value="'.$user['num_posts'].'" size="8" maxlength="8" /><br /></label>';
		else if ($pun_config['o_show_post_count'] == '1' || $pun_user['is_admmod'])
			$posts_actions[] = sprintf($lang->t('Posts info'), forum_number_format($user['num_posts']));

		if ($pun_user['g_search'] == '1' || $pun_user['g_id'] == PUN_ADMIN)
		{
			$posts_actions[] = '<a href="search.php?action=show_user_topics&amp;user_id='.$id.'">'.$lang->t('Show topics').'</a>';
			$posts_actions[] = '<a href="search.php?action=show_user_posts&amp;user_id='.$id.'">'.$lang->t('Show posts').'</a>';

			if ($pun_config['o_topic_subscriptions'] == '1')
				$posts_actions[] = '<a href="search.php?action=show_subscriptions&amp;user_id='.$id.'">'.$lang->t('Show subscriptions').'</a>';
		}

		$posts_field .= (!empty($posts_actions) ? '<p class="actions">'.implode(' - ', $posts_actions).'</p>' : '')."\n";


		$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Profile'), $lang->t('Section essentials'));
		$required_fields = array('req_username' => $lang->t('Username'), 'req_email' => $lang->t('Email'));
		define('PUN_ACTIVE_PAGE', 'profile');
		require PUN_ROOT.'header.php';

		generate_profile_menu('essentials');

?>
	<div class="blockform">
		<h2><span><?php echo pun_htmlspecialchars($user['username']).' - '.$lang->t('Section essentials') ?></span></h2>
		<div class="box">
			<form id="profile1" method="post" action="profile.php?section=essentials&amp;id=<?php echo $id ?>" onsubmit="return process_form(this)">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Username and pass legend') ?></legend>
						<div class="infldset">
							<input type="hidden" name="form_sent" value="1" />
							<?php echo $username_field ?>
<?php if ($pun_user['id'] == $id || $pun_user['g_id'] == PUN_ADMIN || ($user['g_moderator'] == '0' && $pun_user['g_mod_change_passwords'] == '1')): ?>							<p class="actions"><span><a href="profile.php?action=change_pass&amp;id=<?php echo $id ?>"><?php echo $lang->t('Change pass') ?></a></span></p>
<?php endif; ?>						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Email legend') ?></legend>
						<div class="infldset">
							<?php echo $email_field ?>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Localisation legend') ?></legend>
						<div class="infldset">
							<p><?php echo $lang->t('Time zone info') ?></p>
							<label><?php echo $lang->t('Time zone')."\n" ?>
							<br /><select name="form[timezone]">
								<option value="-12"<?php if ($user['timezone'] == -12) echo ' selected="selected"' ?>><?php echo $lang->t('UTC-12:00') ?></option>
								<option value="-11"<?php if ($user['timezone'] == -11) echo ' selected="selected"' ?>><?php echo $lang->t('UTC-11:00') ?></option>
								<option value="-10"<?php if ($user['timezone'] == -10) echo ' selected="selected"' ?>><?php echo $lang->t('UTC-10:00') ?></option>
								<option value="-9.5"<?php if ($user['timezone'] == -9.5) echo ' selected="selected"' ?>><?php echo $lang->t('UTC-09:30') ?></option>
								<option value="-9"<?php if ($user['timezone'] == -9) echo ' selected="selected"' ?>><?php echo $lang->t('UTC-09:00') ?></option>
								<option value="-8.5"<?php if ($user['timezone'] == -8.5) echo ' selected="selected"' ?>><?php echo $lang->t('UTC-08:30') ?></option>
								<option value="-8"<?php if ($user['timezone'] == -8) echo ' selected="selected"' ?>><?php echo $lang->t('UTC-08:00') ?></option>
								<option value="-7"<?php if ($user['timezone'] == -7) echo ' selected="selected"' ?>><?php echo $lang->t('UTC-07:00') ?></option>
								<option value="-6"<?php if ($user['timezone'] == -6) echo ' selected="selected"' ?>><?php echo $lang->t('UTC-06:00') ?></option>
								<option value="-5"<?php if ($user['timezone'] == -5) echo ' selected="selected"' ?>><?php echo $lang->t('UTC-05:00') ?></option>
								<option value="-4"<?php if ($user['timezone'] == -4) echo ' selected="selected"' ?>><?php echo $lang->t('UTC-04:00') ?></option>
								<option value="-3.5"<?php if ($user['timezone'] == -3.5) echo ' selected="selected"' ?>><?php echo $lang->t('UTC-03:30') ?></option>
								<option value="-3"<?php if ($user['timezone'] == -3) echo ' selected="selected"' ?>><?php echo $lang->t('UTC-03:00') ?></option>
								<option value="-2"<?php if ($user['timezone'] == -2) echo ' selected="selected"' ?>><?php echo $lang->t('UTC-02:00') ?></option>
								<option value="-1"<?php if ($user['timezone'] == -1) echo ' selected="selected"' ?>><?php echo $lang->t('UTC-01:00') ?></option>
								<option value="0"<?php if ($user['timezone'] == 0) echo ' selected="selected"' ?>><?php echo $lang->t('UTC') ?></option>
								<option value="1"<?php if ($user['timezone'] == 1) echo ' selected="selected"' ?>><?php echo $lang->t('UTC+01:00') ?></option>
								<option value="2"<?php if ($user['timezone'] == 2) echo ' selected="selected"' ?>><?php echo $lang->t('UTC+02:00') ?></option>
								<option value="3"<?php if ($user['timezone'] == 3) echo ' selected="selected"' ?>><?php echo $lang->t('UTC+03:00') ?></option>
								<option value="3.5"<?php if ($user['timezone'] == 3.5) echo ' selected="selected"' ?>><?php echo $lang->t('UTC+03:30') ?></option>
								<option value="4"<?php if ($user['timezone'] == 4) echo ' selected="selected"' ?>><?php echo $lang->t('UTC+04:00') ?></option>
								<option value="4.5"<?php if ($user['timezone'] == 4.5) echo ' selected="selected"' ?>><?php echo $lang->t('UTC+04:30') ?></option>
								<option value="5"<?php if ($user['timezone'] == 5) echo ' selected="selected"' ?>><?php echo $lang->t('UTC+05:00') ?></option>
								<option value="5.5"<?php if ($user['timezone'] == 5.5) echo ' selected="selected"' ?>><?php echo $lang->t('UTC+05:30') ?></option>
								<option value="5.75"<?php if ($user['timezone'] == 5.75) echo ' selected="selected"' ?>><?php echo $lang->t('UTC+05:45') ?></option>
								<option value="6"<?php if ($user['timezone'] == 6) echo ' selected="selected"' ?>><?php echo $lang->t('UTC+06:00') ?></option>
								<option value="6.5"<?php if ($user['timezone'] == 6.5) echo ' selected="selected"' ?>><?php echo $lang->t('UTC+06:30') ?></option>
								<option value="7"<?php if ($user['timezone'] == 7) echo ' selected="selected"' ?>><?php echo $lang->t('UTC+07:00') ?></option>
								<option value="8"<?php if ($user['timezone'] == 8) echo ' selected="selected"' ?>><?php echo $lang->t('UTC+08:00') ?></option>
								<option value="8.75"<?php if ($user['timezone'] == 8.75) echo ' selected="selected"' ?>><?php echo $lang->t('UTC+08:45') ?></option>
								<option value="9"<?php if ($user['timezone'] == 9) echo ' selected="selected"' ?>><?php echo $lang->t('UTC+09:00') ?></option>
								<option value="9.5"<?php if ($user['timezone'] == 9.5) echo ' selected="selected"' ?>><?php echo $lang->t('UTC+09:30') ?></option>
								<option value="10"<?php if ($user['timezone'] == 10) echo ' selected="selected"' ?>><?php echo $lang->t('UTC+10:00') ?></option>
								<option value="10.5"<?php if ($user['timezone'] == 10.5) echo ' selected="selected"' ?>><?php echo $lang->t('UTC+10:30') ?></option>
								<option value="11"<?php if ($user['timezone'] == 11) echo ' selected="selected"' ?>><?php echo $lang->t('UTC+11:00') ?></option>
								<option value="11.5"<?php if ($user['timezone'] == 11.5) echo ' selected="selected"' ?>><?php echo $lang->t('UTC+11:30') ?></option>
								<option value="12"<?php if ($user['timezone'] == 12) echo ' selected="selected"' ?>><?php echo $lang->t('UTC+12:00') ?></option>
								<option value="12.75"<?php if ($user['timezone'] == 12.75) echo ' selected="selected"' ?>><?php echo $lang->t('UTC+12:45') ?></option>
								<option value="13"<?php if ($user['timezone'] == 13) echo ' selected="selected"' ?>><?php echo $lang->t('UTC+13:00') ?></option>
								<option value="14"<?php if ($user['timezone'] == 14) echo ' selected="selected"' ?>><?php echo $lang->t('UTC+14:00') ?></option>
							</select>
							<br /></label>
							<div class="rbox">
								<label><input type="checkbox" name="form[dst]" value="1"<?php if ($user['dst'] == '1') echo ' checked="checked"' ?> /><?php echo $lang->t('DST') ?><br /></label>
							</div>
							<label><?php echo $lang->t('Time format') ?>

							<br /><select name="form[time_format]">
<?php
								foreach (array_unique($forum_time_formats) as $key => $time_format)
								{
									echo "\t\t\t\t\t\t\t\t".'<option value="'.$key.'"';
									if ($user['time_format'] == $key)
										echo ' selected="selected"';
									echo '>'. format_time(time(), false, null, $time_format, true, true);
									if ($key == 0)
										echo ' ('.$lang->t('Default').')';
									echo "</option>\n";
								}
								?>
							</select>
							<br /></label>
							<label><?php echo $lang->t('Date format') ?>

							<br /><select name="form[date_format]">
<?php
								foreach (array_unique($forum_date_formats) as $key => $date_format)
								{
									echo "\t\t\t\t\t\t\t\t".'<option value="'.$key.'"';
									if ($user['date_format'] == $key)
										echo ' selected="selected"';
									echo '>'. format_time(time(), true, $date_format, null, false, true);
									if ($key == 0)
										echo ' ('.$lang->t('Default').')';
									echo "</option>\n";
								}
								?>
							</select>
							<br /></label>

<?php

		$languages = Flux_Lang::getLanguageList();

		// Only display the language selection box if there's more than one language available
		if (count($languages) > 1)
		{

?>
							<label><?php echo $lang->t('Language') ?>
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
						<legend><?php echo $lang->t('User activity') ?></legend>
						<div class="infldset">
							<p><?php printf($lang->t('Registered info'), format_time($user['registered'], true).(($pun_user['is_admmod']) ? ' (<a href="moderate.php?get_host='.pun_htmlspecialchars($user['registration_ip']).'">'.pun_htmlspecialchars($user['registration_ip']).'</a>)' : '')) ?></p>
							<p><?php printf($lang->t('Last post info'), $last_post) ?></p>
							<p><?php printf($lang->t('Last visit info'), format_time($user['last_visit'])) ?></p>
							<?php echo $posts_field ?>
<?php if ($pun_user['is_admmod']): ?>							<label><?php echo $lang->t('Admin note') ?><br />
							<input id="admin_note" type="text" name="admin_note" value="<?php echo pun_htmlspecialchars($user['admin_note']) ?>" size="30" maxlength="30" /><br /></label>
<?php endif; ?>						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="update" value="<?php echo $lang->t('Submit') ?>" /> <?php echo $lang->t('Instructions') ?></p>
			</form>
		</div>
	</div>
<?php

	}
	else if ($section == 'personal')
	{
		if ($pun_user['g_set_title'] == '1')
			$title_field = '<label>'.$lang->t('Title').' <em>('.$lang->t('Leave blank').')</em><br /><input type="text" name="title" value="'.pun_htmlspecialchars($user['title']).'" size="30" maxlength="50" /><br /></label>'."\n";

		$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Profile'), $lang->t('Section personal'));
		define('PUN_ACTIVE_PAGE', 'profile');
		require PUN_ROOT.'header.php';

		generate_profile_menu('personal');

?>
	<div class="blockform">
		<h2><span><?php echo pun_htmlspecialchars($user['username']).' - '.$lang->t('Section personal') ?></span></h2>
		<div class="box">
			<form id="profile2" method="post" action="profile.php?section=personal&amp;id=<?php echo $id ?>">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Personal details legend') ?></legend>
						<div class="infldset">
							<input type="hidden" name="form_sent" value="1" />
							<label><?php echo $lang->t('Realname') ?><br /><input type="text" name="form[realname]" value="<?php echo pun_htmlspecialchars($user['realname']) ?>" size="40" maxlength="40" /><br /></label>
<?php if (isset($title_field)): ?>							<?php echo $title_field ?>
<?php endif; ?>							<label><?php echo $lang->t('Location') ?><br /><input type="text" name="form[location]" value="<?php echo pun_htmlspecialchars($user['location']) ?>" size="30" maxlength="30" /><br /></label>
							<label><?php echo $lang->t('Website') ?><br /><input type="text" name="form[url]" value="<?php echo pun_htmlspecialchars($user['url']) ?>" size="50" maxlength="80" /><br /></label>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="update" value="<?php echo $lang->t('Submit') ?>" /> <?php echo $lang->t('Instructions') ?></p>
			</form>
		</div>
	</div>
<?php

	}
	else if ($section == 'messaging')
	{

		$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Profile'), $lang->t('Section messaging'));
		define('PUN_ACTIVE_PAGE', 'profile');
		require PUN_ROOT.'header.php';

		generate_profile_menu('messaging');

?>
	<div class="blockform">
		<h2><span><?php echo pun_htmlspecialchars($user['username']).' - '.$lang->t('Section messaging') ?></span></h2>
		<div class="box">
			<form id="profile3" method="post" action="profile.php?section=messaging&amp;id=<?php echo $id ?>">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Contact details legend') ?></legend>
						<div class="infldset">
							<input type="hidden" name="form_sent" value="1" />
							<label><?php echo $lang->t('Jabber') ?><br /><input id="jabber" type="text" name="form[jabber]" value="<?php echo pun_htmlspecialchars($user['jabber']) ?>" size="40" maxlength="75" /><br /></label>
							<label><?php echo $lang->t('ICQ') ?><br /><input id="icq" type="text" name="form[icq]" value="<?php echo $user['icq'] ?>" size="12" maxlength="12" /><br /></label>
							<label><?php echo $lang->t('MSN') ?><br /><input id="msn" type="text" name="form[msn]" value="<?php echo pun_htmlspecialchars($user['msn']) ?>" size="40" maxlength="50" /><br /></label>
							<label><?php echo $lang->t('AOL IM') ?><br /><input id="aim" type="text" name="form[aim]" value="<?php echo pun_htmlspecialchars($user['aim']) ?>" size="20" maxlength="30" /><br /></label>
							<label><?php echo $lang->t('Yahoo') ?><br /><input id="yahoo" type="text" name="form[yahoo]" value="<?php echo pun_htmlspecialchars($user['yahoo']) ?>" size="20" maxlength="30" /><br /></label>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="update" value="<?php echo $lang->t('Submit') ?>" /> <?php echo $lang->t('Instructions') ?></p>
			</form>
		</div>
	</div>
<?php

	}
	else if ($section == 'personality')
	{
		if ($pun_config['o_avatars'] == '0' && $pun_config['o_signatures'] == '0')
			message($lang->t('Bad request'));

		$avatar_field = '<span><a href="profile.php?action=upload_avatar&amp;id='.$id.'">'.$lang->t('Change avatar').'</a></span>';

		$user_avatar = generate_avatar_markup($id);
		if ($user_avatar)
			$avatar_field .= ' <span><a href="profile.php?action=delete_avatar&amp;id='.$id.'">'.$lang->t('Delete avatar').'</a></span>';
		else
			$avatar_field = '<span><a href="profile.php?action=upload_avatar&amp;id='.$id.'">'.$lang->t('Upload avatar').'</a></span>';

		if ($user['signature'] != '')
			$signature_preview = '<p>'.$lang->t('Sig preview').'</p>'."\n\t\t\t\t\t\t\t".'<div class="postsignature postmsg">'."\n\t\t\t\t\t\t\t\t".'<hr />'."\n\t\t\t\t\t\t\t\t".$parsed_signature."\n\t\t\t\t\t\t\t".'</div>'."\n";
		else
			$signature_preview = '<p>'.$lang->t('No sig').'</p>'."\n";

		$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Profile'), $lang->t('Section personality'));
		define('PUN_ACTIVE_PAGE', 'profile');
		require PUN_ROOT.'header.php';

		generate_profile_menu('personality');


?>
	<div class="blockform">
		<h2><span><?php echo pun_htmlspecialchars($user['username']).' - '.$lang->t('Section personality') ?></span></h2>
		<div class="box">
			<form id="profile4" method="post" action="profile.php?section=personality&amp;id=<?php echo $id ?>">
				<div><input type="hidden" name="form_sent" value="1" /></div>
<?php if ($pun_config['o_avatars'] == '1'): ?>				<div class="inform">
					<fieldset id="profileavatar">
						<legend><?php echo $lang->t('Avatar legend') ?></legend>
						<div class="infldset">
<?php if ($user_avatar): ?>							<div class="useravatar"><?php echo $user_avatar ?></div>
<?php endif; ?>							<p><?php echo $lang->t('Avatar info') ?></p>
							<p class="clearb actions"><?php echo $avatar_field ?></p>
						</div>
					</fieldset>
				</div>
<?php endif; if ($pun_config['o_signatures'] == '1'): ?>				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Signature legend') ?></legend>
						<div class="infldset">
							<p><?php echo $lang->t('Signature info') ?></p>
							<div class="txtarea">
								<label><?php printf($lang->t('Sig max size'), forum_number_format($pun_config['p_sig_length']), $pun_config['p_sig_lines']) ?><br />
								<textarea name="signature" rows="4" cols="65"><?php echo pun_htmlspecialchars($user['signature']) ?></textarea><br /></label>
							</div>
							<ul class="bblinks">
								<li><span><a href="help.php#bbcode" onclick="window.open(this.href); return false;"><?php echo $lang->t('BBCode') ?></a> <?php echo ($pun_config['p_sig_bbcode'] == '1') ? $lang->t('on') : $lang->t('off'); ?></span></li>
								<li><span><a href="help.php#img" onclick="window.open(this.href); return false;"><?php echo $lang->t('img tag') ?></a> <?php echo ($pun_config['p_sig_bbcode'] == '1' && $pun_config['p_sig_img_tag'] == '1') ? $lang->t('on') : $lang->t('off'); ?></span></li>
								<li><span><a href="help.php#smilies" onclick="window.open(this.href); return false;"><?php echo $lang->t('Smilies') ?></a> <?php echo ($pun_config['o_smilies_sig'] == '1') ? $lang->t('on') : $lang->t('off'); ?></span></li>
							</ul>
							<?php echo $signature_preview ?>
						</div>
					</fieldset>
				</div>
<?php endif; ?>				<p class="buttons"><input type="submit" name="update" value="<?php echo $lang->t('Submit') ?>" /> <?php echo $lang->t('Instructions') ?></p>
			</form>
		</div>
	</div>
<?php

	}
	else if ($section == 'display')
	{
		$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Profile'), $lang->t('Section display'));
		define('PUN_ACTIVE_PAGE', 'profile');
		require PUN_ROOT.'header.php';

		generate_profile_menu('display');

?>
	<div class="blockform">
		<h2><span><?php echo pun_htmlspecialchars($user['username']).' - '.$lang->t('Section display') ?></span></h2>
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
						<legend><?php echo $lang->t('Style legend') ?></legend>
						<div class="infldset">
							<label><?php echo $lang->t('Styles') ?><br />
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
						<legend><?php echo $lang->t('Post display legend') ?></legend>
						<div class="infldset">
							<p><?php echo $lang->t('Post display info') ?></p>
							<div class="rbox">
<?php if ($pun_config['o_smilies'] == '1' || $pun_config['o_smilies_sig'] == '1'): ?>								<label><input type="checkbox" name="form[show_smilies]" value="1"<?php if ($user['show_smilies'] == '1') echo ' checked="checked"' ?> /><?php echo $lang->t('Show smilies') ?><br /></label>
<?php endif; if ($pun_config['o_signatures'] == '1'): ?>								<label><input type="checkbox" name="form[show_sig]" value="1"<?php if ($user['show_sig'] == '1') echo ' checked="checked"' ?> /><?php echo $lang->t('Show sigs') ?><br /></label>
<?php endif; if ($pun_config['o_avatars'] == '1'): ?>								<label><input type="checkbox" name="form[show_avatars]" value="1"<?php if ($user['show_avatars'] == '1') echo ' checked="checked"' ?> /><?php echo $lang->t('Show avatars') ?><br /></label>
<?php endif; if ($pun_config['p_message_bbcode'] == '1' && $pun_config['p_message_img_tag'] == '1'): ?>								<label><input type="checkbox" name="form[show_img]" value="1"<?php if ($user['show_img'] == '1') echo ' checked="checked"' ?> /><?php echo $lang->t('Show images') ?><br /></label>
<?php endif; if ($pun_config['o_signatures'] == '1' && $pun_config['p_sig_bbcode'] == '1' && $pun_config['p_sig_img_tag'] == '1'): ?>								<label><input type="checkbox" name="form[show_img_sig]" value="1"<?php if ($user['show_img_sig'] == '1') echo ' checked="checked"' ?> /><?php echo $lang->t('Show images sigs') ?><br /></label>
<?php endif; ?>
							</div>
						</div>
					</fieldset>
				</div>
<?php endif; ?>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Pagination legend') ?></legend>
						<div class="infldset">
							<label class="conl"><?php echo $lang->t('Topics per page') ?><br /><input type="text" name="form[disp_topics]" value="<?php echo $user['disp_topics'] ?>" size="6" maxlength="3" /><br /></label>
							<label class="conl"><?php echo $lang->t('Posts per page') ?><br /><input type="text" name="form[disp_posts]" value="<?php echo $user['disp_posts'] ?>" size="6" maxlength="3" /><br /></label>
							<p class="clearb"><?php echo $lang->t('Paginate info') ?> <?php echo $lang->t('Leave blank') ?></p>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="update" value="<?php echo $lang->t('Submit') ?>" /> <?php echo $lang->t('Instructions') ?></p>
			</form>
		</div>
	</div>
<?php

	}
	else if ($section == 'privacy')
	{
		$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Profile'), $lang->t('Section privacy'));
		define('PUN_ACTIVE_PAGE', 'profile');
		require PUN_ROOT.'header.php';

		generate_profile_menu('privacy');

?>
	<div class="blockform">
		<h2><span><?php echo pun_htmlspecialchars($user['username']).' - '.$lang->t('Section privacy') ?></span></h2>
		<div class="box">
			<form id="profile6" method="post" action="profile.php?section=privacy&amp;id=<?php echo $id ?>">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Privacy options legend') ?></legend>
						<div class="infldset">
							<input type="hidden" name="form_sent" value="1" />
							<p><?php echo $lang->t('Email setting info') ?></p>
							<div class="rbox">
								<label><input type="radio" name="form[email_setting]" value="0"<?php if ($user['email_setting'] == '0') echo ' checked="checked"' ?> /><?php echo $lang->t('Email setting 1') ?><br /></label>
								<label><input type="radio" name="form[email_setting]" value="1"<?php if ($user['email_setting'] == '1') echo ' checked="checked"' ?> /><?php echo $lang->t('Email setting 2') ?><br /></label>
								<label><input type="radio" name="form[email_setting]" value="2"<?php if ($user['email_setting'] == '2') echo ' checked="checked"' ?> /><?php echo $lang->t('Email setting 3') ?><br /></label>
							</div>
						</div>
					</fieldset>
				</div>
<?php if ($pun_config['o_forum_subscriptions'] == '1' || $pun_config['o_topic_subscriptions'] == '1'): ?>				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Subscription legend') ?></legend>
						<div class="infldset">
							<div class="rbox">
								<label><input type="checkbox" name="form[notify_with_post]" value="1"<?php if ($user['notify_with_post'] == '1') echo ' checked="checked"' ?> /><?php echo $lang->t('Notify full') ?><br /></label>
<?php if ($pun_config['o_topic_subscriptions'] == '1'): ?>								<label><input type="checkbox" name="form[auto_notify]" value="1"<?php if ($user['auto_notify'] == '1') echo ' checked="checked"' ?> /><?php echo $lang->t('Auto notify full') ?><br /></label>
<?php endif; ?>
							</div>
						</div>
					</fieldset>
				</div>
<?php endif; ?>				<p class="buttons"><input type="submit" name="update" value="<?php echo $lang->t('Submit') ?>" /> <?php echo $lang->t('Instructions') ?></p>
			</form>
		</div>
	</div>
<?php

	}
	else if ($section == 'admin')
	{
		if (!$pun_user['is_admmod'] || ($pun_user['g_moderator'] == '1' && $pun_user['g_mod_ban_users'] == '0'))
			message($lang->t('Bad request'));

		$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Profile'), $lang->t('Section admin'));
		define('PUN_ACTIVE_PAGE', 'profile');
		require PUN_ROOT.'header.php';

		generate_profile_menu('admin');

?>
	<div class="blockform">
		<h2><span><?php echo pun_htmlspecialchars($user['username']).' - '.$lang->t('Section admin') ?></span></h2>
		<div class="box">
			<form id="profile7" method="post" action="profile.php?section=admin&amp;id=<?php echo $id ?>">
				<div class="inform">
				<input type="hidden" name="form_sent" value="1" />
					<fieldset>
<?php

		if ($pun_user['g_moderator'] == '1')
		{

?>
						<legend><?php echo $lang->t('Delete ban legend') ?></legend>
						<div class="infldset">
							<p><input type="submit" name="ban" value="<?php echo $lang->t('Ban user') ?>" /></p>
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
						<legend><?php echo $lang->t('Group membership legend') ?></legend>
						<div class="infldset">
							<select id="group_id" name="group_id">
<?php

				$query = $db->select(array('g_id' => 'g.g_id', 'g_title' => 'g.g_title'), 'groups AS g');
				$query->where = 'g.g_id != :g_id';
				$query->order = array('g_title' => 'g.g_title ASC');

				$params = array(':g_id' => PUN_GUEST);

				$result = $query->run($params);

				foreach ($result as $cur_group)
				{
					if ($cur_group['g_id'] == $user['g_id'] || ($cur_group['g_id'] == $pun_config['o_default_user_group'] && $user['g_id'] == ''))
						echo "\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
					else
						echo "\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
				}
				unset($query, $params, $result);

?>
							</select>
							<input type="submit" name="update_group_membership" value="<?php echo $lang->t('Save') ?>" />
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
<?php

			}

?>
						<legend><?php echo $lang->t('Delete ban legend') ?></legend>
						<div class="infldset">
							<input type="submit" name="delete_user" value="<?php echo $lang->t('Delete user') ?>" /> <input type="submit" name="ban" value="<?php echo $lang->t('Ban user') ?>" />
						</div>
					</fieldset>
				</div>
<?php

			if ($user['g_moderator'] == '1' || $user['g_id'] == PUN_ADMIN)
			{

?>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Set mods legend') ?></legend>
						<div class="infldset">
							<p><?php echo $lang->t('Moderator in info') ?></p>
<?php

				$query = $db->select(array('cid' => 'c.id AS cid', 'cat_name' => 'c.cat_name', 'fid' => 'f.id AS fid', 'forum_name' => 'f.forum_name', 'moderators' => 'f.moderators'), 'categories AS c');

				$query->InnerJoin('f', 'forums AS f', 'c.id = f.cat_id');

				$query->where = 'f.redirect_url IS NULL';
				$query->order = array('cposition' => 'c.disp_position DESC', 'cid' => 'c.id DESC', 'fposition' => 'f.disp_position');

				$params = array();

				$result = $query->run($params);

				$cur_category = 0;
				foreach ($result as $cur_forum)
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

				unset ($result, $query, $params);

?>
								</div>
							</div>
							<br class="clearb" /><input type="submit" name="update_forums" value="<?php echo $lang->t('Update forums') ?>" />
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
		message($lang->t('Bad request'));

?>
	<div class="clearer"></div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}
