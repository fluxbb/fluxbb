<?php
/***********************************************************************

  Copyright (C) 2002-2008  PunBB.org

  This file is part of PunBB.

  PunBB is free software; you can redistribute it and/or modify it
  under the terms of the GNU General Public License as published
  by the Free Software Foundation; either version 2 of the License,
  or (at your option) any later version.

  PunBB is distributed in the hope that it will be useful, but
  WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston,
  MA  02111-1307  USA

************************************************************************/


if (!defined('PUN_ROOT'))
	define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';

($hook = get_hook('rg_start')) ? eval($hook) : null;

// If we are logged in, we shouldn't be here
if (!$pun_user['is_guest'])
{
	header('Location: '.pun_link($pun_url['index']));
	exit;
}

// Load the profile.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/profile.php';

if ($pun_config['o_regs_allow'] == '0')
	message($lang_profile['No new regs']);

$errors = array();


// User pressed the cancel button
if (isset($_GET['cancel']))
	redirect(pun_link($pun_url['index']), $lang_profile['Reg cancel redirect']);

// User pressed agree but failed to tick checkbox
else if (isset($_GET['agree']) && !isset($_GET['req_agreement']))
	redirect(pun_link($pun_url['index']), $lang_profile['Reg cancel redirect']);

// Show the rules
else if ($pun_config['o_rules'] == '1' && !isset($_GET['agree']) && !isset($_POST['form_sent']))
{
	// Setup breadcrumbs
	$pun_page['crumbs'] = array(
		array($pun_config['o_board_title'], pun_link($pun_url['index'])),
		array($lang_common['Register'], pun_link($pun_url['register'])),
		$lang_common['Rules']
	);

	($hook = get_hook('rg_rules_pre_header_load')) ? eval($hook) : null;

	define('PUN_PAGE', 'rules');
	require PUN_ROOT.'header.php';

	$pun_page['set_count'] = $pun_page['fld_count'] = 0;

?>
<div id="pun-main" class="main">

	<h1><span><?php echo end($pun_page['crumbs']) ?></span></h1>

	<div class="main-head">
		<h2><span><?php echo $lang_common['Forum rules'].'. '.$lang_profile['Agree to rules'] ?></span></h2>
	</div>

	<div class="main-content frm">
		<div class="userbox">
			<?php echo $pun_config['o_rules_message'] ?>
		</div>
		<form class="frm-form" method="get" accept-charset="utf-8" action="<?php echo $base_url ?>/register.php">
			<fieldset class="frm-set set<?php echo ++$pun_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_common['Required information'] ?></strong></legend>
				<div class="checkbox radbox">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>"><span class="fld-label"><?php echo $lang_profile['Agreement'] ?></span><br /><input type="checkbox" id="fld<?php echo $pun_page['fld_count'] ?>" name="req_agreement" value="1" /> <?php echo $lang_profile['Agreement label'] ?></label>
				</div>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="agree" value="<?php echo $lang_profile['Agree'] ?>" /></span>
				<span class="cancel"><input type="submit" name="cancel" value="<?php echo $lang_common['Cancel'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

	require PUN_ROOT.'footer.php';
}

else if (isset($_POST['form_sent']))
{
	($hook = get_hook('rg_register_form_submitted')) ? eval($hook) : null;

	// Check that someone from this IP didn't register a user within the last hour (DoS prevention)
	$query = array(
		'SELECT'	=> '1',
		'FROM'		=> 'users AS u',
		'WHERE'		=> 'u.registration_ip=\''.get_remote_address().'\' AND u.registered>'.(time() - 3600)
	);

	($hook = get_hook('rg_qr_check_register_flood')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	if ($pun_db->num_rows($result))
		$errors[] = $lang_profile['Registration flood'];

	// Did everything go according to plan so far?
	if (empty($errors))
	{
		$username = trim($_POST['req_username']);
		$email1 = strtolower(trim($_POST['req_email1']));

		if ($pun_config['o_regs_verify'] == '1')
		{
			$email2 = strtolower(trim($_POST['req_email2']));
	
			$password1 = random_key(8, true);
			$password2 = $password1;
		}
		else
		{
			$password1 = trim($_POST['req_password1']);
			$password2 = trim($_POST['req_password2']);
		}

		// Validate the username
		$errors = array_merge($errors, validate_username($username));

		// ... and the password
		if (pun_strlen($password1) < 4)
			$errors[] = $lang_profile['Pass too short'];
		else if ($password1 != $password2)
			$errors[] = $lang_profile['Pass not match'];

		// ... and the e-mail address
		require PUN_ROOT.'include/email.php';

		if (!is_valid_email($email1))
			$errors[] = $lang_common['Invalid e-mail'];
		else if ($pun_config['o_regs_verify'] == '1' && $email1 != $email2)
			$errors[] = $lang_profile['E-mail not match'];

		// Check if it's a banned e-mail address
		$banned_email = is_banned_email($email1);
		if ($banned_email && $pun_config['p_allow_banned_email'] == '0')
			$errors[] = $lang_profile['Banned e-mail'];

		// Check if someone else already has registered with that e-mail address
		$dupe_list = array();

		$query = array(
			'SELECT'	=> 'u.username',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'u.email=\''.$email1.'\''
		);

		($hook = get_hook('rg_qr_check_email_dupe')) ? eval($hook) : null;
		$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
		if ($pun_db->num_rows($result) && empty($errors))
		{
			if ($pun_config['p_allow_dupe_email'] == '0')
				$errors[] = $lang_profile['Dupe e-mail'];
	
			while ($cur_dupe = $pun_db->fetch_assoc($result))
				$dupe_list[] = $cur_dupe['username'];
		}

		// Did everything go according to plan so far?
		if (empty($errors))
		{
			// Make sure we got a valid language string
			if (isset($_POST['language']))
			{
				$language = preg_replace('#[\.\\\/]#', '', $_POST['language']);
				if (!file_exists(PUN_ROOT.'lang/'.$language.'/common.php'))
					message($lang_common['Bad request']);
			}
			else
				$language = $pun_config['o_default_lang'];

			$save_pass = (!isset($_POST['save_pass']) || $_POST['save_pass'] != '1') ? 0 : 1;
			$email_setting = intval($_POST['email_setting']);
			if ($email_setting < 0 || $email_setting > 2) $email_setting = 1;
			$initial_group_id = ($pun_config['o_regs_verify'] == '0') ? $pun_config['o_default_user_group'] : PUN_UNVERIFIED;
			$salt = random_key(12);
			$password_hash = sha1($salt.sha1($password1));

			// Insert the new user into the database. We do this now to get the last inserted id for later use.
			$user_info = array(
				'username'				=>	$username,
				'group_id'				=>	$initial_group_id,
				'salt'					=>	$salt,
				'password'				=>	$password1,
				'password_hash'			=>	$password_hash,
				'email'					=>	$email1,
				'email_setting'			=>	$email_setting,
				'save_pass'				=>	$save_pass,
				'timezone'				=>	$_POST['timezone'],
				'dst'					=>	isset($_POST['dst']) ? '1' : '0',
				'language'				=>	$language,
				'style'					=>	$pun_config['o_default_style'],
				'registered'			=>	time(),
				'registration_ip'		=>	get_remote_address(),
				'activate_key'			=>	($pun_config['o_regs_verify'] == '1') ? '\''.random_key(8, true).'\'' : 'NULL',
				'require_verification'	=>	($pun_config['o_regs_verify'] == '1'),
				'notify_admins'			=>	($pun_config['o_regs_report'] == '1')
			);

			($hook = get_hook('rg_register_pre_add_user')) ? eval($hook) : null;
			add_user($user_info, $new_uid);

			// If we previously found out that the e-mail was banned
			if ($banned_email && $pun_config['o_mailing_list'] != '')
			{
				($hook = get_hook('rg_register_banned_email')) ? eval($hook) : null;

				$mail_subject = 'Alert - Banned e-mail detected';
				$mail_message = 'User \''.$username.'\' registered with banned e-mail address: '.$email1."\n\n".'User profile: '.pun_link($pun_url['user'], $new_uid)."\n\n".'-- '."\n".'Forum Mailer'."\n".'(Do not reply to this message)';

				pun_mail($pun_config['o_mailing_list'], $mail_subject, $mail_message);
			}

			// If we previously found out that the e-mail was a dupe
			if (!empty($dupe_list) && $pun_config['o_mailing_list'] != '')
			{
				($hook = get_hook('rg_register_dupe_email')) ? eval($hook) : null;

				$mail_subject = 'Alert - Duplicate e-mail detected';
				$mail_message = 'User \''.$username.'\' registered with an e-mail address that also belongs to: '.implode(', ', $dupe_list)."\n\n".'User profile: '.pun_link($pun_url['user'], $new_uid)."\n\n".'-- '."\n".'Forum Mailer'."\n".'(Do not reply to this message)';

				pun_mail($pun_config['o_mailing_list'], $mail_subject, $mail_message);
			}

			($hook = get_hook('rg_register_pre_login_redirect')) ? eval($hook) : null;

			// Must the user verify the registration or do we log him/her in right now?
			if ($pun_config['o_regs_verify'] == '1')
				message(sprintf($lang_profile['Reg e-mail'], '<a href="mailto:'.$pun_config['o_admin_email'].'">'.$pun_config['o_admin_email'].'</a>'));

			pun_setcookie($cookie_name, base64_encode($new_uid.'|'.$password_hash), ($save_pass != '0') ? time() + 31536000 : 0);

			redirect(pun_link($pun_url['index']), $lang_profile['Reg complete']);
		}
	}
}

// Setup form
$pun_page['set_count'] = $pun_page['fld_count'] = 0;
$pun_page['form_action'] = $base_url.'/register.php?action=register';

// Setup form information
$pun_page['frm_info'][] = '<p>'.$lang_profile['Register intro'].'</p>';
if ($pun_config['o_regs_verify'] != '0')
	$pun_page['frm_info'][] = '<p class="warn">'.$lang_profile['E-mail info'].'</p>';

// Setup breadcrumbs
$pun_page['crumbs'] = array(
	array($pun_config['o_board_title'], pun_link($pun_url['index'])),
	$lang_common['Register']
);

($hook = get_hook('rg_register_pre_header_load')) ? eval($hook) : null;

define('PUN_PAGE', 'register');
require PUN_ROOT.'header.php';

?>
<div id="pun-main" class="main">

	<h1><span><?php echo end($pun_page['crumbs']) ?></span></h1>

	<div class="main-head">
		<h2><span><?php printf($lang_profile['Register at'], pun_htmlencode($pun_config['o_board_title'])) ?></span></h2>
	</div>

	<div class="main-content frm">
		<div class="frm-info">
			<?php echo implode("\n\t\t\t\t", $pun_page['frm_info'])."\n" ?>
		</div>
<?php

	// If there were any errors, show them
	if (!empty($errors))
	{
		$pun_page['errors'] = array();
		while (list(, $cur_error) = each($errors))
			$pun_page['errors'][] = '<li class="warn"><span>'.$cur_error.'</span></li>';

		($hook = get_hook('rg_pre_register_errors')) ? eval($hook) : null;

?>
		<div class="frm-error">
			<h3 class="warn"><?php echo $lang_profile['Register errors'] ?></h3>
			<ul>
				<?php echo implode("\n\t\t\t\t\t", $pun_page['errors'])."\n" ?>
			</ul>
		</div>
<?php

	}

?>
		<div id="req-msg" class="frm-warn">
			<p class="important"><?php printf($lang_common['Required warn'], '<em class="req-text">'.$lang_common['Required'].'</em>') ?></p>
		</div>
		<form class="frm-form" id="afocus" method="post" accept-charset="utf-8" action="<?php echo $pun_page['form_action'] ?>">
			<div class="hidden">
				<input type="hidden" name="form_sent" value="1" />
			</div>
<?php ($hook = get_hook('rg_register_pre_req_info_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set<?php echo ++$pun_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_common['Required information'] ?></strong></legend>
				<div class="frm-fld text required">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Username'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $pun_page['fld_count'] ?>" name="req_username" value="<?php echo(isset($_POST['req_username']) ? pun_htmlencode($_POST['req_username']) : '') ?>" size="35" maxlength="25" /></span><br />
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
						<span class="fld-help"><?php echo $lang_profile['Username help'] ?></span>
					</label>
				</div>
<?php if ($pun_config['o_regs_verify'] == '0'): ?>				<div class="frm-fld text required">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Password'] ?></span><br />
						<span class="fld-input"><input type="password" id="fld<?php echo $pun_page['fld_count'] ?>" name="req_password1" size="35" /></span><br />
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
						<span class="fld-help"><?php echo $lang_profile['Password help'] ?></span>
					</label>
				</div>
				<div class="frm-fld text required">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Confirm password'] ?></span><br />
						<span class="fld-input"><input type="password" id="fld<?php echo $pun_page['fld_count'] ?>" name="req_password2" size="35" /></span><br />
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
						<span class="fld-help"><?php echo $lang_profile['Confirm password help'] ?></span>
					</label>
				</div>
<?php endif; ($hook = get_hook('rg_register_pre_email_field')) ? eval($hook) : null; ?>				<div class="frm-fld text required">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['E-mail'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $pun_page['fld_count'] ?>" name="req_email1" value="<?php echo(isset($_POST['req_email1']) ? pun_htmlencode($_POST['req_email1']) : '') ?>" size="35" maxlength="80" /></span><br />
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
						<span class="fld-help"><?php echo $lang_profile['E-mail help'] ?></span>
					</label>
				</div>
<?php if ($pun_config['o_regs_verify'] == '1'): ?>				<div class="frm-fld text required">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Confirm e-mail'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $pun_page['fld_count'] ?>" name="req_email2" value="<?php echo(isset($_POST['req_email2']) ? pun_htmlencode($_POST['req_email2']) : '') ?>" size="35" maxlength="80" /></span><br />
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
						<span class="fld-help"><?php echo $lang_profile['Confirm e-mail help'] ?></span>
					</label>
				</div>
<?php endif; ($hook = get_hook('rg_register_req_info_end')) ? eval($hook) : null; ?>			</fieldset>
<?php ($hook = get_hook('rg_register_post_req_info_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set<?php echo ++$pun_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_profile['Local legend'] ?></strong></legend>
<?php

		$languages = array();
		$d = dir(PUN_ROOT.'lang');
		while (($entry = $d->read()) !== false)
		{
			if ($entry != '.' && $entry != '..' && is_dir(PUN_ROOT.'lang/'.$entry) && file_exists(PUN_ROOT.'lang/'.$entry.'/common.php'))
				$languages[] = $entry;
		}
		$d->close();

		// Only display the language selection box if there's more than one language available
		if (count($languages) > 1)
		{
			natcasesort($languages);

?>
				<div class="frm-fld select">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Language'] ?></span><br />
						<span class="fld-input"><select id="fld<?php echo $pun_page['fld_count'] ?>" name="language">
<?php

			$select_lang = isset($_POST['language']) ? $_POST['language'] : $pun_config['o_default_lang'];

			while (list(, $temp) = @each($languages))
			{
				if ($select_lang == $temp)
					echo "\t\t\t\t\t\t".'<option value="'.$temp.'" selected="selected">'.$temp.'</option>'."\n";
				else
					echo "\t\t\t\t\t\t".'<option value="'.$temp.'">'.$temp.'</option>'."\n";
			}

?>
						</select></span>
					</label>
				</div>
<?php

		}

		$select_timezone = isset($_POST['timezone']) ? $_POST['timezone'] : $pun_config['o_default_timezone'];

?>
				<div class="frm-fld select">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Timezone'] ?></span><br />
						<span class="fld-input"><select id="fld<?php echo $pun_page['fld_count'] ?>" name="timezone">
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
					</label>
				</div>
				<div class="checkbox radbox">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>"><span class="fld-label"><?php echo $lang_profile['Adjust for DST'] ?></span><br /><input type="checkbox" id="fld<?php echo $pun_page['fld_count'] ?>" name="dst" value="<?php echo(isset($_POST['dst']) ? 'checked="checked"' : '') ?>"/> <?php echo $lang_profile['DST label'] ?></label>
				</div>
<?php ($hook = get_hook('rg_register_local_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php ($hook = get_hook('rg_register_post_local_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set<?php echo ++$pun_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_profile['Other settings'] ?></strong></legend>
				<fieldset class="frm-group">
					<legend><span><?php echo $lang_profile['E-mail settings'] ?></span></legend>
					<div class="radbox"><label for="fld<?php echo ++$pun_page['fld_count'] ?>"><input type="radio" id="fld<?php echo $pun_page['fld_count'] ?>" name="email_setting" value="0" <?php echo((isset($_POST['email_setting']) && intval($_POST['email_setting']) == 0) ? 'checked="checked"' : '') ?>/> <?php echo $lang_profile['E-mail setting 1'] ?></label></div>
					<div class="radbox"><label for="fld<?php echo ++$pun_page['fld_count'] ?>"><input type="radio" id="fld<?php echo $pun_page['fld_count'] ?>" name="email_setting" value="1" <?php echo((!isset($_POST['email_setting']) || intval($_POST['email_setting']) == 1) ? 'checked="checked"' : '') ?>/> <?php echo $lang_profile['E-mail setting 2'] ?></label></div>
					<div class="radbox"><label for="fld<?php echo ++$pun_page['fld_count'] ?>"><input type="radio" id="fld<?php echo $pun_page['fld_count'] ?>" name="email_setting" value="2" <?php echo((isset($_POST['email_setting']) && intval($_POST['email_setting']) == 2) ? 'checked="checked"' : '') ?>/> <?php echo $lang_profile['E-mail setting 3'] ?></label></div>
				</fieldset>
				<div class="checkbox radbox">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>"><span class="fld-label"><?php echo $lang_profile['Persistent login'] ?></span><br /><input type="checkbox" id="fld<?php echo $pun_page['fld_count'] ?>" name="save_pass" value="1" <?php echo((!isset($_POST['register']) || isset($_POST['save_pass'])) ? 'checked="checked"' : '') ?>/> <?php echo $lang_profile['Save user/pass'] ?></label>
				</div>
<?php ($hook = get_hook('rg_register_other_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php ($hook = get_hook('rg_register_post_other_fieldset')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="register" value="<?php echo $lang_common['Register'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

($hook = get_hook('rg_end')) ? eval($hook) : null;

require PUN_ROOT.'footer.php';
