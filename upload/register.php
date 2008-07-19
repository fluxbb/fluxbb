<?php
/***********************************************************************

  Copyright (C) 2008  FluxBB.org

  Based on code copyright (C) 2002-2008  PunBB.org

  This file is part of FluxBB.

  FluxBB is free software; you can redistribute it and/or modify it
  under the terms of the GNU General Public License as published
  by the Free Software Foundation; either version 2 of the License,
  or (at your option) any later version.

  FluxBB is distributed in the hope that it will be useful, but
  WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston,
  MA  02111-1307  USA

************************************************************************/


if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', './');
require FORUM_ROOT.'include/common.php';

($hook = get_hook('rg_start')) ? eval($hook) : null;

// If we are logged in, we shouldn't be here
if (!$forum_user['is_guest'])
{
	header('Location: '.forum_link($forum_url['index']));
	exit;
}

// Load the profile.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/profile.php';

if ($forum_config['o_regs_allow'] == '0')
	message($lang_profile['No new regs']);

$errors = array();


// User pressed the cancel button
if (isset($_GET['cancel']))
	redirect(forum_link($forum_url['index']), $lang_profile['Reg cancel redirect']);

// User pressed agree but failed to tick checkbox
else if (isset($_GET['agree']) && !isset($_GET['req_agreement']))
	redirect(forum_link($forum_url['index']), $lang_profile['Reg cancel redirect']);

// Show the rules
else if ($forum_config['o_rules'] == '1' && !isset($_GET['agree']) && !isset($_POST['form_sent']))
{

	// Setup form
	$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_common['Register'], forum_link($forum_url['register'])),
		$lang_common['Rules']
	);

	($hook = get_hook('rg_rules_pre_header_load')) ? eval($hook) : null;

	define('FORUM_PAGE', 'rules');
	define('FORUM_PAGE_TYPE', 'basic');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

	($hook = get_hook('rg_rules_output_start')) ? eval($hook) : null;

	$forum_page['set_count'] = $forum_page['fld_count'] = 0;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_profile['Reg rules head'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<div class="ct-box user-box">
			<?php echo $forum_config['o_rules_message'] ?>
		</div>
		<form class="frm-form" method="get" accept-charset="utf-8" action="<?php echo $base_url ?>/register.php">
			<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_common['Required information'] ?></strong></legend>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box checkbox">
						<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="req_agreement" value="1" /></span>
						<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_profile['Agreement'] ?></span> <?php echo $lang_profile['Agreement label'] ?></label>
					</div>
				</div>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="agree" value="<?php echo $lang_profile['Agree'] ?>" /></span>
				<span class="cancel"><input type="submit" name="cancel" value="<?php echo $lang_common['Cancel'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

	$tpl_temp = forum_trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}

else if (isset($_POST['form_sent']))
{
	($hook = get_hook('rg_register_form_submitted')) ? eval($hook) : null;

	// Check that someone from this IP didn't register a user within the last hour (DoS prevention)
	$query = array(
		'SELECT'	=> '1',
		'FROM'		=> 'users AS u',
		'WHERE'		=> 'u.registration_ip=\''.$forum_db->escape(get_remote_address()).'\' AND u.registered>'.(time() - 3600)
	);

	($hook = get_hook('rg_qr_check_register_flood')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	if ($forum_db->num_rows($result))
		$errors[] = $lang_profile['Registration flood'];

	// Did everything go according to plan so far?
	if (empty($errors))
	{
		$username = forum_trim($_POST['req_username']);
		$email1 = strtolower(forum_trim($_POST['req_email1']));

		if ($forum_config['o_regs_verify'] == '1')
		{
			$email2 = strtolower(forum_trim($_POST['req_email2']));

			$password1 = random_key(8, true);
			$password2 = $password1;
		}
		else
		{
			$password1 = forum_trim($_POST['req_password1']);
			$password2 = forum_trim($_POST['req_password2']);
		}

		// Validate the username
		$errors = array_merge($errors, validate_username($username));

		// ... and the password
		if (utf8_strlen($password1) < 4)
			$errors[] = $lang_profile['Pass too short'];
		else if ($password1 != $password2)
			$errors[] = $lang_profile['Pass not match'];

		// ... and the e-mail address
		if (!defined('FORUM_EMAIL_FUNCTIONS_LOADED'))
			require FORUM_ROOT.'include/email.php';

		if (!is_valid_email($email1))
			$errors[] = $lang_common['Invalid e-mail'];
		else if ($forum_config['o_regs_verify'] == '1' && $email1 != $email2)
			$errors[] = $lang_profile['E-mail not match'];

		// Check if it's a banned e-mail address
		$banned_email = is_banned_email($email1);
		if ($banned_email && $forum_config['p_allow_banned_email'] == '0')
			$errors[] = $lang_profile['Banned e-mail'];

		// Check if someone else already has registered with that e-mail address
		$dupe_list = array();

		$query = array(
			'SELECT'	=> 'u.username',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'u.email=\''.$forum_db->escape($email1).'\''
		);

		($hook = get_hook('rg_qr_check_email_dupe')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		if ($forum_db->num_rows($result) && empty($errors))
		{
			if ($forum_config['p_allow_dupe_email'] == '0')
				$errors[] = $lang_profile['Dupe e-mail'];

			while ($cur_dupe = $forum_db->fetch_assoc($result))
				$dupe_list[] = $cur_dupe['username'];
		}

		// Did everything go according to plan so far?
		if (empty($errors))
		{
			// Make sure we got a valid language string
			if (isset($_POST['language']))
			{
				$language = preg_replace('#[\.\\\/]#', '', $_POST['language']);
				if (!file_exists(FORUM_ROOT.'lang/'.$language.'/common.php'))
					message($lang_common['Bad request']);
			}
			else
				$language = $forum_config['o_default_lang'];

			$initial_group_id = ($forum_config['o_regs_verify'] == '0') ? $forum_config['o_default_user_group'] : FORUM_UNVERIFIED;
			$salt = random_key(12);
			$password_hash = forum_hash($password1, $salt);

			// Insert the new user into the database. We do this now to get the last inserted id for later use.
			$user_info = array(
				'username'				=>	$username,
				'group_id'				=>	$initial_group_id,
				'salt'					=>	$salt,
				'password'				=>	$password1,
				'password_hash'			=>	$password_hash,
				'email'					=>	$email1,
				'email_setting'			=>	$forum_config['o_default_email_setting'],
				'timezone'				=>	$_POST['timezone'],
				'dst'					=>	isset($_POST['dst']) ? '1' : '0',
				'language'				=>	$language,
				'style'					=>	$forum_config['o_default_style'],
				'registered'			=>	time(),
				'registration_ip'		=>	get_remote_address(),
				'activate_key'			=>	($forum_config['o_regs_verify'] == '1') ? '\''.random_key(8, true).'\'' : 'NULL',
				'require_verification'	=>	($forum_config['o_regs_verify'] == '1'),
				'notify_admins'			=>	($forum_config['o_regs_report'] == '1')
			);

			($hook = get_hook('rg_register_pre_add_user')) ? eval($hook) : null;
			add_user($user_info, $new_uid);

			// If we previously found out that the e-mail was banned
			if ($banned_email && $forum_config['o_mailing_list'] != '')
			{
				($hook = get_hook('rg_register_banned_email')) ? eval($hook) : null;

				$mail_subject = 'Alert - Banned e-mail detected';
				$mail_message = 'User \''.$username.'\' registered with banned e-mail address: '.$email1."\n\n".'User profile: '.forum_link($forum_url['user'], $new_uid)."\n\n".'-- '."\n".'Forum Mailer'."\n".'(Do not reply to this message)';

				forum_mail($forum_config['o_mailing_list'], $mail_subject, $mail_message);
			}

			// If we previously found out that the e-mail was a dupe
			if (!empty($dupe_list) && $forum_config['o_mailing_list'] != '')
			{
				($hook = get_hook('rg_register_dupe_email')) ? eval($hook) : null;

				$mail_subject = 'Alert - Duplicate e-mail detected';
				$mail_message = 'User \''.$username.'\' registered with an e-mail address that also belongs to: '.implode(', ', $dupe_list)."\n\n".'User profile: '.forum_link($forum_url['user'], $new_uid)."\n\n".'-- '."\n".'Forum Mailer'."\n".'(Do not reply to this message)';

				forum_mail($forum_config['o_mailing_list'], $mail_subject, $mail_message);
			}

			($hook = get_hook('rg_register_pre_login_redirect')) ? eval($hook) : null;

			// Must the user verify the registration or do we log him/her in right now?
			if ($forum_config['o_regs_verify'] == '1')
				message(sprintf($lang_profile['Reg e-mail'], '<a href="mailto:'.$forum_config['o_admin_email'].'">'.$forum_config['o_admin_email'].'</a>'));

			$expire = time() + $forum_config['o_timeout_visit'];

			forum_setcookie($cookie_name, base64_encode($new_uid.'|'.$password_hash.'|'.$expire.'|'.sha1($salt.$password_hash.forum_hash($expire, $salt))), $expire);

			redirect(forum_link($forum_url['index']), $lang_profile['Reg complete']);
		}
	}
}

// Setup form
$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;
$forum_page['form_action'] = $base_url.'/register.php?action=register';

// Setup form information
$forum_page['frm_info']['intro'] = '<p>'.$lang_profile['Register intro'].'</p>';
if ($forum_config['o_regs_verify'] != '0')
	$forum_page['frm_info']['email'] = '<p class="warn">'.$lang_profile['Reg e-mail info'].'</p>';

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	sprintf($lang_profile['Register at'], $forum_config['o_board_title'])
);

($hook = get_hook('rg_register_pre_header_load')) ? eval($hook) : null;

define('FORUM_PAGE', 'register');
define('FORUM_PAGE_TYPE', 'basic');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('rg_register_output_start')) ? eval($hook) : null;

?>
	<div class="main-content main-frm">
		<div class="ct-box">
			<?php echo implode("\n\t\t\t", $forum_page['frm_info'])."\n" ?>
		</div>
<?php

	// If there were any errors, show them
	if (!empty($errors))
	{
		$forum_page['errors'] = array();
		while (list(, $cur_error) = each($errors))
			$forum_page['errors'][] = '<li class="warn"><span>'.$cur_error.'</span></li>';

		($hook = get_hook('rg_pre_register_errors')) ? eval($hook) : null;

?>
		<div class="ct-box error-box">
			<h2 class="warn"><?php echo $lang_profile['Register errors'] ?></h2>
			<ul>
				<?php echo implode("\n\t\t\t\t", $forum_page['errors'])."\n" ?>
			</ul>
		</div>
<?php

	}

?>
		<div id="req-msg" class="req-warn">
			<p class="important"><?php printf($lang_common['Required warn'], '<em>'.$lang_common['Reqmark'].'</em>') ?></p>
		</div>
		<form class="frm-form" id="afocus" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
			<div class="hidden">
				<input type="hidden" name="form_sent" value="1" />
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token($forum_page['form_action']) ?>" />
			</div>
<?php ($hook = get_hook('rg_register_pre_req_info_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_common['Required information'] ?></strong></legend>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text required">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><em><?php echo $lang_common['Reqmark'] ?></em> <?php echo $lang_profile['Username'] ?></span> <small><?php echo $lang_profile['Username help'] ?></small></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_username" value="<?php echo(isset($_POST['req_username']) ? forum_htmlencode($_POST['req_username']) : '') ?>" size="35" maxlength="25" /></span>
					</div>
				</div>
<?php if ($forum_config['o_regs_verify'] == '0'): ?>				<div class="sf-set group-item<?php echo ++$forum_page['group_count'] ?>">
					<div class="sf-box text required">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><em><?php echo $lang_common['Reqmark'] ?></em> <?php echo $lang_profile['Password'] ?></span> <small><?php echo $lang_profile['Password help'] ?></small></label><br />
						<span class="fld-input"><input type="password" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_password1" size="35" /></span>
					</div>
				</div>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text required">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><em><?php echo $lang_common['Reqmark'] ?></em> <?php echo $lang_profile['Confirm password'] ?></span> <small><?php echo $lang_profile['Confirm password help'] ?></small></label><br />
						<span class="fld-input"><input type="password" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_password2" size="35" /></span>
					</div>
				</div>
<?php endif; ($hook = get_hook('rg_register_pre_email_field')) ? eval($hook) : null; ?>				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text required">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><em><?php echo $lang_common['Reqmark'] ?></em> <?php echo $lang_profile['E-mail'] ?></span> <small><?php echo $lang_profile['E-mail help'] ?></small></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_email1" value="<?php echo(isset($_POST['req_email1']) ? forum_htmlencode($_POST['req_email1']) : '') ?>" size="35" maxlength="80" /></span>
					</div>
				</div>
<?php if ($forum_config['o_regs_verify'] == '1'): ?>				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text required">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><em><?php echo $lang_common['Reqmark'] ?></em> <?php echo $lang_profile['Confirm e-mail'] ?></span> <small><?php echo $lang_profile['Confirm e-mail help'] ?></small></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_email2" value="<?php echo(isset($_POST['req_email2']) ? forum_htmlencode($_POST['req_email2']) : '') ?>" size="35" maxlength="80" /></span>
					</div>
				</div>
<?php endif; ($hook = get_hook('rg_register_req_info_end')) ? eval($hook) : null; ?>		</fieldset>
<?php $forum_page['item_count'] = 0; ?>
<?php ($hook = get_hook('rg_register_post_req_info_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-group group-item<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_profile['Optional legend'] ?></strong></legend>
<?php

		$languages = array();
		$d = dir(FORUM_ROOT.'lang');
		while (($entry = $d->read()) !== false)
		{
			if ($entry != '.' && $entry != '..' && is_dir(FORUM_ROOT.'lang/'.$entry) && file_exists(FORUM_ROOT.'lang/'.$entry.'/common.php'))
				$languages[] = $entry;
		}
		$d->close();

		// Only display the language selection box if there's more than one language available
		if (count($languages) > 1)
		{
			natcasesort($languages);

?>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box select">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_profile['Language'] ?></span></label><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="language">
<?php

			$select_lang = isset($_POST['language']) ? $_POST['language'] : $forum_config['o_default_lang'];

			while (list(, $temp) = @each($languages))
			{
				if ($select_lang == $temp)
					echo "\t\t\t\t\t\t".'<option value="'.$temp.'" selected="selected">'.$temp.'</option>'."\n";
				else
					echo "\t\t\t\t\t\t".'<option value="'.$temp.'">'.$temp.'</option>'."\n";
			}

?>
						</select></span>
					</div>
				</div>
<?php

		}

		$select_timezone = isset($_POST['timezone']) ? $_POST['timezone'] : $forum_config['o_default_timezone'];

?>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box select">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_profile['Timezone'] ?></span></label><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="timezone">
						<option value="-12"<?php if ($select_timezone == -12) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-12:00'] ?></option>
						<option value="-11"<?php if ($select_timezone == -11) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-11:00'] ?></option>
						<option value="-10"<?php if ($select_timezone == -10) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-10:00'] ?></option>
						<option value="-9.5"<?php if ($select_timezone == -9.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-09:30'] ?></option>
						<option value="-9"<?php if ($select_timezone == -9) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-09:00'] ?></option>
						<option value="-8"<?php if ($select_timezone == -8) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-08:00'] ?></option>
						<option value="-7"<?php if ($select_timezone == -7) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-07:00'] ?></option>
						<option value="-6"<?php if ($select_timezone == -6) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-06:00'] ?></option>
						<option value="-5"<?php if ($select_timezone == -5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-05:00'] ?></option>
						<option value="-4"<?php if ($select_timezone == -4) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-04:00'] ?></option>
						<option value="-3.5"<?php if ($select_timezone == -3.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-03:30'] ?></option>
						<option value="-3"<?php if ($select_timezone == -3) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-03:00'] ?></option>
						<option value="-2"<?php if ($select_timezone == -2) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-02:00'] ?></option>
						<option value="-1"<?php if ($select_timezone == -1) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-01:00'] ?></option>
						<option value="0"<?php if ($select_timezone == 0) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC'] ?></option>
						<option value="1"<?php if ($select_timezone == 1) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+01:00'] ?></option>
						<option value="2"<?php if ($select_timezone == 2) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+02:00'] ?></option>
						<option value="3"<?php if ($select_timezone == 3) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+03:00'] ?></option>
						<option value="3.5"<?php if ($select_timezone == 3.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+03:30'] ?></option>
						<option value="4"<?php if ($select_timezone == 4) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+04:00'] ?></option>
						<option value="4.5"<?php if ($select_timezone == 4.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+04:30'] ?></option>
						<option value="5"<?php if ($select_timezone == 5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+05:00'] ?></option>
						<option value="5.5"<?php if ($select_timezone == 5.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+05:30'] ?></option>
						<option value="5.75"<?php if ($select_timezone == 5.75) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+05:45'] ?></option>
						<option value="6"<?php if ($select_timezone == 6) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+06:00'] ?></option>
						<option value="6.5"<?php if ($select_timezone == 6.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+06:30'] ?></option>
						<option value="7"<?php if ($select_timezone == 7) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+07:00'] ?></option>
						<option value="8"<?php if ($select_timezone == 8) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+08:00'] ?></option>
						<option value="8.75"<?php if ($select_timezone == 8.75) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+08:45'] ?></option>
						<option value="9"<?php if ($select_timezone == 9) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+09:00'] ?></option>
						<option value="9.5"<?php if ($select_timezone == 9.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+09:30'] ?></option>
						<option value="10"<?php if ($select_timezone == 10) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+10:00'] ?></option>
						<option value="10.5"<?php if ($select_timezone == 10.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+10:30'] ?></option>
						<option value="11"<?php if ($select_timezone == 11) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+11:00'] ?></option>
						<option value="11.5"<?php if ($select_timezone == 11.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+11:30'] ?></option>
						<option value="12"<?php if ($select_timezone == 12) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+12:00'] ?></option>
						<option value="12.75"<?php if ($select_timezone == 12.75) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+12:45'] ?></option>
						<option value="13"<?php if ($select_timezone == 13) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+13:00'] ?></option>
						<option value="14"<?php if ($select_timezone == 14) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+14:00'] ?></option>
						</select></span>
					</div>
				</div>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box checkbox">
						<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="dst" <?php echo(isset($_POST['dst']) ? 'checked="checked" ' : '') ?>/></span>
						<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_profile['Adjust for DST'] ?></span> <?php echo $lang_profile['DST label'] ?></label>
					</div>
				</div>
<?php ($hook = get_hook('rg_register_optional_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php ($hook = get_hook('rg_register_post_optional_fieldset')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="register" value="<?php echo $lang_profile['Register'] ?>" /></span>
			</div>
		</form>
	</div>

<?php

($hook = get_hook('rg_end')) ? eval($hook) : null;

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
