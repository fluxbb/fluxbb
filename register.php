<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';


// If we are logged in, we shouldn't be here
if (!$pun_user['is_guest'])
{
	header('Location: index.php');
	exit;
}

// Load the register.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/register.php';

// Load the register.php/profile.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/prof_reg.php';

if ($pun_config['o_regs_allow'] == '0')
	message($lang_register['No new regs']);


// User pressed the cancel button
if (isset($_GET['cancel']))
	redirect('index.php', $lang_register['Reg cancel redirect']);


else if ($pun_config['o_rules'] == '1' && !isset($_GET['agree']) && !isset($_POST['form_sent']))
{
	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_register['Register'], $lang_register['Forum rules']);
	define('PUN_ACTIVE_PAGE', 'register');
	require PUN_ROOT.'header.php';

?>
<div id="rules" class="blockform">
	<div class="hd"><h2><span><?php echo $lang_register['Forum rules'] ?></span></h2></div>
	<div class="box">
		<form method="get" action="register.php">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_register['Rules legend'] ?></legend>
					<div class="infldset">
						<div class="usercontent"><?php echo $pun_config['o_rules_message'] ?></div>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="agree" value="<?php echo $lang_register['Agree'] ?>" /> <input type="submit" name="cancel" value="<?php echo $lang_register['Cancel'] ?>" /></p>
		</form>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}

// Start with a clean slate
$errors = array();

if (isset($_POST['form_sent']))
{
	// Check that someone from this IP didn't register a user within the last hour (DoS prevention)
	$result = $db->query('SELECT 1 FROM '.$db->prefix.'users WHERE registration_ip=\''.get_remote_address().'\' AND registered>'.(time() - 3600)) or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());

	if ($db->num_rows($result))
		message($lang_register['Registration flood']);


	$username = pun_trim($_POST['req_user']);
	$email1 = strtolower(pun_trim($_POST['req_email1']));

	if ($pun_config['o_regs_verify'] == '1')
	{
		$email2 = strtolower(pun_trim($_POST['req_email2']));

		$password1 = random_pass(8);
		$password2 = $password1;
	}
	else
	{
		$password1 = pun_trim($_POST['req_password1']);
		$password2 = pun_trim($_POST['req_password2']);
	}

	// Validate username and passwords
	check_username($username);

	if (pun_strlen($password1) < 4)
		$errors[] = $lang_prof_reg['Pass too short'];
	else if ($password1 != $password2)
		$errors[] = $lang_prof_reg['Pass not match'];

	// Validate email
	require PUN_ROOT.'include/email.php';

	if (!is_valid_email($email1))
		$errors[] = $lang_common['Invalid email'];
	else if ($pun_config['o_regs_verify'] == '1' && $email1 != $email2)
		$errors[] = $lang_register['Email not match'];

	// Check if it's a banned email address
	if (is_banned_email($email1))
	{
		if ($pun_config['p_allow_banned_email'] == '0')
			$errors[] = $lang_prof_reg['Banned email'];

		$banned_email = true; // Used later when we send an alert email
	}
	else
		$banned_email = false;

	// Check if someone else already has registered with that email address
	$dupe_list = array();

	$result = $db->query('SELECT username FROM '.$db->prefix.'users WHERE email=\''.$db->escape($email1).'\'') or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());
	if ($db->num_rows($result))
	{
		if ($pun_config['p_allow_dupe_email'] == '0')
			$errors[] = $lang_prof_reg['Dupe email'];

		while ($cur_dupe = $db->fetch_assoc($result))
			$dupe_list[] = $cur_dupe['username'];
	}

	// Make sure we got a valid language string
	if (isset($_POST['language']))
	{
		$language = preg_replace('%[\.\\\/]%', '', $_POST['language']);
		if (!file_exists(PUN_ROOT.'lang/'.$language.'/common.php'))
			message($lang_common['Bad request']);
	}
	else
		$language = $pun_config['o_default_lang'];

	$timezone = round($_POST['timezone'], 1);

	$dst = isset($_POST['dst']) ? '1' : '0';

	$email_setting = intval($_POST['email_setting']);
	if ($email_setting < 0 || $email_setting > 2)
		$email_setting = $pun_config['o_default_email_setting'];

	// Did everything go according to plan?
	if (empty($errors))
	{
		// Insert the new user into the database. We do this now to get the last inserted ID for later use
		$now = time();

		$intial_group_id = ($pun_config['o_regs_verify'] == '0') ? $pun_config['o_default_user_group'] : PUN_UNVERIFIED;
		$password_hash = pun_hash($password1);

		// Add the user
		$db->query('INSERT INTO '.$db->prefix.'users (username, group_id, password, email, email_setting, timezone, dst, language, style, registered, registration_ip, last_visit) VALUES(\''.$db->escape($username).'\', '.$intial_group_id.', \''.$password_hash.'\', \''.$db->escape($email1).'\', '.$email_setting.', '.$timezone.' , '.$dst.', \''.$db->escape($language).'\', \''.$pun_config['o_default_style'].'\', '.$now.', \''.get_remote_address().'\', '.$now.')') or error('Unable to create user', __FILE__, __LINE__, $db->error());
		$new_uid = $db->insert_id();

		// If the mailing list isn't empty, we may need to send out some alerts
		if ($pun_config['o_mailing_list'] != '')
		{
			// If we previously found out that the email was banned
			if ($banned_email)
			{
				// Load the "banned email register" template
				$mail_tpl = trim(file_get_contents(PUN_ROOT.'lang/'.$pun_user['language'].'/mail_templates/banned_email_register.tpl'));

				// The first row contains the subject
				$first_crlf = strpos($mail_tpl, "\n");
				$mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
				$mail_message = trim(substr($mail_tpl, $first_crlf));

				$mail_message = str_replace('<username>', $username, $mail_message);
				$mail_message = str_replace('<email>', $email1, $mail_message);
				$mail_message = str_replace('<profile_url>', get_base_url().'/profile.php?id='.$new_uid, $mail_message);
				$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message);

				pun_mail($pun_config['o_mailing_list'], $mail_subject, $mail_message);
			}

			// If we previously found out that the email was a dupe
			if (!empty($dupe_list))
			{
				// Load the "dupe email register" template
				$mail_tpl = trim(file_get_contents(PUN_ROOT.'lang/'.$pun_user['language'].'/mail_templates/dupe_email_register.tpl'));

				// The first row contains the subject
				$first_crlf = strpos($mail_tpl, "\n");
				$mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
				$mail_message = trim(substr($mail_tpl, $first_crlf));

				$mail_message = str_replace('<username>', $username, $mail_message);
				$mail_message = str_replace('<dupe_list>', implode(', ', $dupe_list), $mail_message);
				$mail_message = str_replace('<profile_url>', get_base_url().'/profile.php?id='.$new_uid, $mail_message);
				$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message);

				pun_mail($pun_config['o_mailing_list'], $mail_subject, $mail_message);
			}

			// Should we alert people on the admin mailing list that a new user has registered?
			if ($pun_config['o_regs_report'] == '1')
			{
				// Load the "new user" template
				$mail_tpl = trim(file_get_contents(PUN_ROOT.'lang/'.$pun_user['language'].'/mail_templates/new_user.tpl'));

				// The first row contains the subject
				$first_crlf = strpos($mail_tpl, "\n");
				$mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
				$mail_message = trim(substr($mail_tpl, $first_crlf));

				$mail_message = str_replace('<username>', $username, $mail_message);
				$mail_message = str_replace('<base_url>', get_base_url().'/', $mail_message);
				$mail_message = str_replace('<profile_url>', get_base_url().'/profile.php?id='.$new_uid, $mail_message);
				$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message);

				pun_mail($pun_config['o_mailing_list'], $mail_subject, $mail_message);
			}
		}

		// Must the user verify the registration or do we log him/her in right now?
		if ($pun_config['o_regs_verify'] == '1')
		{
			// Load the "welcome" template
			$mail_tpl = trim(file_get_contents(PUN_ROOT.'lang/'.$pun_user['language'].'/mail_templates/welcome.tpl'));

			// The first row contains the subject
			$first_crlf = strpos($mail_tpl, "\n");
			$mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
			$mail_message = trim(substr($mail_tpl, $first_crlf));

			$mail_subject = str_replace('<board_title>', $pun_config['o_board_title'], $mail_subject);
			$mail_message = str_replace('<base_url>', get_base_url().'/', $mail_message);
			$mail_message = str_replace('<username>', $username, $mail_message);
			$mail_message = str_replace('<password>', $password1, $mail_message);
			$mail_message = str_replace('<login_url>', get_base_url().'/login.php', $mail_message);
			$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message);

			pun_mail($email1, $mail_subject, $mail_message);

			message($lang_register['Reg email'].' <a href="mailto:'.$pun_config['o_admin_email'].'">'.$pun_config['o_admin_email'].'</a>.', true);
		}

		// Regenerate the users info cache
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			require PUN_ROOT.'include/cache.php';

		generate_users_info_cache();

		pun_setcookie($new_uid, $password_hash, time() + $pun_config['o_timeout_visit']);

		redirect('index.php', $lang_register['Reg complete']);
	}
}


$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_register['Register']);
$required_fields = array('req_user' => $lang_common['Username'], 'req_password1' => $lang_common['Password'], 'req_password2' => $lang_prof_reg['Confirm pass'], 'req_email1' => $lang_common['Email'], 'req_email2' => $lang_common['Email'].' 2');
$focus_element = array('register', 'req_user');
define('PUN_ACTIVE_PAGE', 'register');
require PUN_ROOT.'header.php';

$timezone = isset($timezone) ? $timezone : $pun_config['o_default_timezone'];
$dst = isset($dst) ? $dst : $pun_config['o_default_dst'];
$email_setting = isset($email_setting) ? $email_setting : $pun_config['o_default_email_setting'];

// If there are errors, we display them
if (!empty($errors))
{

?>
<div id="posterror" class="block">
	<h2><span><?php echo $lang_register['Registration errors'] ?></span></h2>
	<div class="box">
		<div class="inbox error-info">
			<p><?php echo $lang_register['Registration errors info'] ?></p>
			<ul class="error-list">
<?php

	foreach ($errors as $cur_error)
		echo "\t\t\t\t".'<li><strong>'.$cur_error.'</strong></li>'."\n";
?>
			</ul>
		</div>
	</div>
</div>

<?php

}
?>
<div id="regform" class="blockform">
	<h2><span><?php echo $lang_register['Register'] ?></span></h2>
	<div class="box">
		<form id="register" method="post" action="register.php?action=register" onsubmit="this.register.disabled=true;if(process_form(this)){return true;}else{this.register.disabled=false;return false;}">
			<div class="inform">
				<div class="forminfo">
					<h3><?php echo $lang_common['Important information'] ?></h3>
					<p><?php echo $lang_register['Desc 1'] ?></p>
					<p><?php echo $lang_register['Desc 2'] ?></p>
				</div>
				<fieldset>
					<legend><?php echo $lang_register['Username legend'] ?></legend>
					<div class="infldset">
						<input type="hidden" name="form_sent" value="1" />
						<label class="required"><strong><?php echo $lang_common['Username'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br /><input type="text" name="req_user" value="<?php if (isset($_POST['req_user'])) echo pun_htmlspecialchars($_POST['req_user']); ?>" size="25" maxlength="25" /><br /></label>
					</div>
				</fieldset>
			</div>
<?php if ($pun_config['o_regs_verify'] == '0'): ?>			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_register['Pass legend'] ?></legend>
					<div class="infldset">
						<label class="conl required"><strong><?php echo $lang_common['Password'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br /><input type="password" name="req_password1" value="<?php if (isset($_POST['req_password1'])) echo pun_htmlspecialchars($_POST['req_password1']); ?>" size="16" /><br /></label>
						<label class="conl required"><strong><?php echo $lang_prof_reg['Confirm pass'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br /><input type="password" name="req_password2" value="<?php if (isset($_POST['req_password2'])) echo pun_htmlspecialchars($_POST['req_password2']); ?>" size="16" /><br /></label>
						<p class="clearb"><?php echo $lang_register['Pass info'] ?></p>
					</div>
				</fieldset>
			</div>
<?php endif; ?>			<div class="inform">
				<fieldset>
					<legend><?php echo ($pun_config['o_regs_verify'] == '1') ? $lang_prof_reg['Email legend 2'] : $lang_prof_reg['Email legend'] ?></legend>
					<div class="infldset">
<?php if ($pun_config['o_regs_verify'] == '1'): ?>						<p><?php echo $lang_register['Email info'] ?></p>
<?php endif; ?>						<label class="required"><strong><?php echo $lang_common['Email'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br />
						<input type="text" name="req_email1" value="<?php if (isset($_POST['req_email1'])) echo pun_htmlspecialchars($_POST['req_email1']); ?>" size="50" maxlength="80" /><br /></label>
<?php if ($pun_config['o_regs_verify'] == '1'): ?>						<label class="required"><strong><?php echo $lang_register['Confirm email'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br />
						<input type="text" name="req_email2" value="<?php if (isset($_POST['req_email2'])) echo pun_htmlspecialchars($_POST['req_email2']); ?>" size="50" maxlength="80" /><br /></label>
<?php endif; ?>					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_prof_reg['Localisation legend'] ?></legend>
					<div class="infldset">
						<p><?php echo $lang_prof_reg['Time zone info'] ?></p>
						<label><?php echo $lang_prof_reg['Time zone']."\n" ?>
						<br /><select id="time_zone" name="timezone">
							<option value="-12"<?php if ($timezone == -12) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-12:00'] ?></option>
							<option value="-11"<?php if ($timezone == -11) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-11:00'] ?></option>
							<option value="-10"<?php if ($timezone == -10) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-10:00'] ?></option>
							<option value="-9.5"<?php if ($timezone == -9.5) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-09:30'] ?></option>
							<option value="-9"<?php if ($timezone == -9) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-09:00'] ?></option>
							<option value="-8.5"<?php if ($timezone == -8.5) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-08:30'] ?></option>
							<option value="-8"<?php if ($timezone == -8) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-08:00'] ?></option>
							<option value="-7"<?php if ($timezone == -7) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-07:00'] ?></option>
							<option value="-6"<?php if ($timezone == -6) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-06:00'] ?></option>
							<option value="-5"<?php if ($timezone == -5) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-05:00'] ?></option>
							<option value="-4"<?php if ($timezone == -4) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-04:00'] ?></option>
							<option value="-3.5"<?php if ($timezone == -3.5) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-03:30'] ?></option>
							<option value="-3"<?php if ($timezone == -3) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-03:00'] ?></option>
							<option value="-2"<?php if ($timezone == -2) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-02:00'] ?></option>
							<option value="-1"<?php if ($timezone == -1) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC-01:00'] ?></option>
							<option value="0"<?php if ($timezone == 0) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC'] ?></option>
							<option value="1"<?php if ($timezone == 1) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+01:00'] ?></option>
							<option value="2"<?php if ($timezone == 2) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+02:00'] ?></option>
							<option value="3"<?php if ($timezone == 3) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+03:00'] ?></option>
							<option value="3.5"<?php if ($timezone == 3.5) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+03:30'] ?></option>
							<option value="4"<?php if ($timezone == 4) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+04:00'] ?></option>
							<option value="4.5"<?php if ($timezone == 4.5) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+04:30'] ?></option>
							<option value="5"<?php if ($timezone == 5) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+05:00'] ?></option>
							<option value="5.5"<?php if ($timezone == 5.5) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+05:30'] ?></option>
							<option value="5.75"<?php if ($timezone == 5.75) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+05:45'] ?></option>
							<option value="6"<?php if ($timezone == 6) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+06:00'] ?></option>
							<option value="6.5"<?php if ($timezone == 6.5) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+06:30'] ?></option>
							<option value="7"<?php if ($timezone == 7) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+07:00'] ?></option>
							<option value="8"<?php if ($timezone == 8) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+08:00'] ?></option>
							<option value="8.75"<?php if ($timezone == 8.75) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+08:45'] ?></option>
							<option value="9"<?php if ($timezone == 9) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+09:00'] ?></option>
							<option value="9.5"<?php if ($timezone == 9.5) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+09:30'] ?></option>
							<option value="10"<?php if ($timezone == 10) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+10:00'] ?></option>
							<option value="10.5"<?php if ($timezone == 10.5) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+10:30'] ?></option>
							<option value="11"<?php if ($timezone == 11) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+11:00'] ?></option>
							<option value="11.5"<?php if ($timezone == 11.5) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+11:30'] ?></option>
							<option value="12"<?php if ($timezone == 12) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+12:00'] ?></option>
							<option value="12.75"<?php if ($timezone == 12.75) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+12:45'] ?></option>
							<option value="13"<?php if ($timezone == 13) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+13:00'] ?></option>
							<option value="14"<?php if ($timezone == 14) echo ' selected="selected"' ?>><?php echo $lang_prof_reg['UTC+14:00'] ?></option>
						</select>
						<br /></label>
						<div class="rbox">
							<label><input type="checkbox" name="dst" value="1"<?php if ($dst == '1') echo ' checked="checked"' ?> /><?php echo $lang_prof_reg['DST'] ?><br /></label>
						</div>
<?php

		$languages = forum_list_langs();

		// Only display the language selection box if there's more than one language available
		if (count($languages) > 1)
		{

?>
							<label><?php echo $lang_prof_reg['Language'] ?>
							<br /><select name="language">
<?php

			foreach ($languages as $temp)
			{
				if ($pun_config['o_default_lang'] == $temp)
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
					<legend><?php echo $lang_prof_reg['Privacy options legend'] ?></legend>
					<div class="infldset">
						<p><?php echo $lang_prof_reg['Email setting info'] ?></p>
						<div class="rbox">
							<label><input type="radio" name="email_setting" value="0"<?php if ($email_setting == '0') echo ' checked="checked"' ?> /><?php echo $lang_prof_reg['Email setting 1'] ?><br /></label>
							<label><input type="radio" name="email_setting" value="1"<?php if ($email_setting == '1') echo ' checked="checked"' ?> /><?php echo $lang_prof_reg['Email setting 2'] ?><br /></label>
							<label><input type="radio" name="email_setting" value="2"<?php if ($email_setting == '2') echo ' checked="checked"' ?> /><?php echo $lang_prof_reg['Email setting 3'] ?><br /></label>
						</div>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="register" value="<?php echo $lang_register['Register'] ?>" /></p>
		</form>
	</div>
</div>
<?php

require PUN_ROOT.'footer.php';
