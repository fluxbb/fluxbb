<?php

/**
 * Copyright (C) 2008-2011 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

if (isset($_GET['action']))
	define('PUN_QUIET_VISIT', 1);

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';


// Load the login.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/login.php';

$action = isset($_GET['action']) ? $_GET['action'] : null;

if (isset($_POST['form_sent']) && $action == 'in')
{
	$form_username = pun_trim($_POST['req_username']);
	$form_password = pun_trim($_POST['req_password']);
	$save_pass = isset($_POST['save_pass']);

	$query = new SelectQuery(array('id' => 'u.id', 'group_id' => 'u.group_id', 'password' => 'u.password'), 'users AS u');
	$query->where = 'u.username LIKE :username';

	$params = array(':username' => $form_username);

	$result = $db->query($query, $params);
	unset ($query, $params);

	// START TEMP PASSWORD UPDATING
	// TODO: Once the extension system is in place this should be split into an extension that is automatically installed
	// when performing an upgrade, but not in a clean install.

	// TODO: the upgrade script must convert non-empty salt columns into $13$saltpassword in the password column
	$needs_updated = false;

	// If it is 40 hex characters (sha1) - (1.2 PHP >= 4.3.0) or (1.4)
	if (preg_match('%^[a-zA-Z0-9]{40}$%', $result[0]['password']))
	{
		// The password is valid, update it to use the new password type
		if ($result[0]['password'] == sha1($form_password))
			$needs_updated = true;
		// The password is invalid
		else
			unset ($result);
	}
	// If it is 40 hex characters (sha1), with a salt - 1.3-legacy
	else if (preg_match('%^$13$(?<salt>[\033-\126]{12})(?<password>[a-zA-Z0-9]{40})$%', $result[0]['password'], $matches))
	{
		// The password is valid, update it to use the new password type
		if ($matches['password'] == sha1($matches['salt'].sha1($form_password)))
			$needs_updated = true;
		// The password is invalid
		else
			unset ($result);
	}
	// If it is 32 hex characters (md5) - 1.2 PHP < 4.3.0
	else if (preg_match('%^[a-zA-Z0-9]{32}$%', $result[0]['password']))
	{
		// the password is valid, update it to use the new password type
		if ($result[0]['password'] == md5($form_password))
			$needs_updated = true;
		// The password is invalid
		else
			unset ($result);
	}

	// If the password was old-style and valid, update it to the correct style
	if ($needs_updated)
	{
		// Hash their password into the correct style
		$result[0]['password'] = PasswordHash::hash($form_password);

		$query = new UpdateQuery(array('password' => ':password'), 'users');
		$query->where = 'id = :user_id';

		$params = array(':password' => $result[0]['password'], ':user_id' => $result[0]['id']);

		$db->query($query, $params);
		unset ($query, $params);
	}

	// END TEMP PASSWORD UPDATING

	if (empty($result) || !PasswordHash::validate($form_password, $result[0]['password']))
		message($lang_login['Wrong user/pass'].' <a href="login.php?action=forget">'.$lang_login['Forgotten pass'].'</a>');

	$cur_user = $result[0];
	unset ($result);

	// Update the status if this is the first time the user logged in
	if ($cur_user['group_id'] == PUN_UNVERIFIED)
	{
		$query = new UpdateQuery(array('group_id' => ':group_id'), 'users');
		$query->where = 'id = :id';
		$params = array(':group_id' => $pun_config['o_default_user_group'], ':id' => $cur_user['id']);
		$db->query($query, $params);
		unset($query, $params);

		// Regenerate the users info cache
		$cache->delete('boardstats');
	}

	// Update this users session to the correct user ID
	$query = new UpdateQuery(array('user_id' => ':user_id'), 'sessions');
	$query->where = 'id = :session_id';

	$params = array(':user_id' => $cur_user['id'], ':session_id' => $pun_user['session_id']);

	$db->query($query, $params);
	unset ($query, $params);

	// Reset tracked topics
	set_tracked_topics(null);

	redirect(htmlspecialchars($_POST['redirect_url']), $lang_login['Login redirect']);
}


else if ($action == 'out')
{
	if ($pun_user['is_guest'] || !isset($_GET['id']) || $_GET['id'] != $pun_user['id'] || !isset($_GET['csrf_token']) || $_GET['csrf_token'] != sha1($pun_user['id'].sha1(get_remote_address())))
	{
		header('Location: index.php');
		exit;
	}

	// Update this users session to be a guest
	$query = new UpdateQuery(array('user_id' => '1'), 'sessions');
	$query->where = 'id = :session_id';

	$params = array(':session_id' => $pun_user['session_id']);

	$db->query($query, $params);
	unset($query, $params);

	redirect('index.php', $lang_login['Logout redirect']);
}


else if ($action == 'forget' || $action == 'forget_2')
{
	if (!$pun_user['is_guest'])
		header('Location: index.php');

	if (isset($_POST['form_sent']))
	{
		// Start with a clean slate
		$errors = array();

		require PUN_ROOT.'include/email.php';

		// Validate the email address
		$email = strtolower(trim($_POST['req_email']));
		if (!is_valid_email($email))
			$errors[] = $lang_common['Invalid email'];

		// Did everything go according to plan?
		if (empty($errors))
		{
			$query = new SelectQuery(array('id' => 'u.id', 'username' => 'u.username', 'last_email_sent' => 'u.last_email_sent'), 'users AS u');
			$query->where = 'u.email = :email';

			$params = array(':email' => $email);

			$result = $db->query($query, $params);
			unset($query, $params);

			if (!empty($result))
			{
				// Load the "activate password" template
				$mail_tpl = trim(file_get_contents(PUN_ROOT.'lang/'.$pun_user['language'].'/mail_templates/activate_password.tpl'));

				// The first row contains the subject
				$first_crlf = strpos($mail_tpl, "\n");
				$mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
				$mail_message = trim(substr($mail_tpl, $first_crlf));

				// Do the generic replacements first (they apply to all emails sent out here)
				$mail_message = str_replace('<base_url>', get_base_url().'/', $mail_message);
				$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'].' '.$lang_common['Mailer'], $mail_message);

				// Loop through users we found
				foreach ($result as $cur_hit)
				{
					if ($cur_hit['last_email_sent'] != '' && (time() - $cur_hit['last_email_sent']) < 3600 && (time() - $cur_hit['last_email_sent']) >= 0)
						message($lang_login['Email flood'], true);

					// Generate a new password and a new password activation code
					$new_password = random_pass(8);
					$new_password_key = random_pass(8);

					$query = new UpdateQuery(array('activate_string' => ':activate_string', 'activate_key' => ':activate_key', 'last_email_sent' => ':last_email_sent'), 'users');
					$query->where = 'id = :id';

					$params = array(':activate_string' => PasswordHash::hash($new_password), ':activate_key' => $new_password_key, ':last_email_sent' => time(), ':id' => $cur_hit['id']);

					$db->query($query, $params);
					unset($params);

					// Do the user specific replacements to the template
					$cur_mail_message = str_replace('<username>', $cur_hit['username'], $mail_message);
					$cur_mail_message = str_replace('<activation_url>', get_base_url().'/profile.php?id='.$cur_hit['id'].'&action=change_pass&key='.$new_password_key, $cur_mail_message);
					$cur_mail_message = str_replace('<new_password>', $new_password, $cur_mail_message);

					pun_mail($email, $mail_subject, $cur_mail_message);
				}
				unset($result);

				message($lang_login['Forget mail'].' <a href="mailto:'.$pun_config['o_admin_email'].'">'.$pun_config['o_admin_email'].'</a>.', true);
			}
			else
				$errors[] = $lang_login['No email match'].' '.htmlspecialchars($email).'.';
			}
		}

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_login['Request pass']);
	$required_fields = array('req_email' => $lang_common['Email']);
	$focus_element = array('request_pass', 'req_email');
	define ('PUN_ACTIVE_PAGE', 'login');
	require PUN_ROOT.'header.php';

// If there are errors, we display them
if (!empty($errors))
{

?>
<div id="posterror" class="block">
	<h2><span><?php echo $lang_login['New password errors'] ?></span></h2>
	<div class="box">
		<div class="inbox error-info">
			<p><?php echo $lang_login['New passworderrors info'] ?></p>
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
<div class="blockform">
	<h2><span><?php echo $lang_login['Request pass'] ?></span></h2>
	<div class="box">
		<form id="request_pass" method="post" action="login.php?action=forget_2" onsubmit="this.request_pass.disabled=true;if(process_form(this)){return true;}else{this.request_pass.disabled=false;return false;}">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_login['Request pass legend'] ?></legend>
					<div class="infldset">
						<input type="hidden" name="form_sent" value="1" />
						<label class="required"><strong><?php echo $lang_common['Email'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br /><input id="req_email" type="text" name="req_email" size="50" maxlength="80" /><br /></label>
						<p><?php echo $lang_login['Request pass info'] ?></p>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="request_pass" value="<?php echo $lang_common['Submit'] ?>" /><?php if (empty($errors)): ?> <a href="javascript:history.go(-1)"><?php echo $lang_common['Go back'] ?></a><?php endif; ?></p>
		</form>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


if (!$pun_user['is_guest'])
	header('Location: index.php');

// Try to determine if the data in HTTP_REFERER is valid (if not, we redirect to index.php after login)
if (!empty($_SERVER['HTTP_REFERER']))
{
	$referrer = parse_url($_SERVER['HTTP_REFERER']);
	// Remove www subdomain if it exists
	if (strpos($referrer['host'], 'www.') === 0)
		$referrer['host'] = substr($referrer['host'], 4);

	// Make sure the path component exists
	if (!isset($referrer['path']))
		$referrer['path'] = '';

	$valid = parse_url(get_base_url());
	// Remove www subdomain if it exists
	if (strpos($valid['host'], 'www.') === 0)
		$valid['host'] = substr($valid['host'], 4);

	// Make sure the path component exists
	if (!isset($valid['path']))
		$valid['path'] = '';

	if ($referrer['host'] == $valid['host'] && preg_match('#^'.preg_quote($valid['path']).'/(.*?)\.php#i', $referrer['path']))
		$redirect_url = $_SERVER['HTTP_REFERER'];
}

if (!isset($redirect_url))
	$redirect_url = 'index.php';

$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_common['Login']);
$required_fields = array('req_username' => $lang_common['Username'], 'req_password' => $lang_common['Password']);
$focus_element = array('login', 'req_username');
define('PUN_ACTIVE_PAGE', 'login');
require PUN_ROOT.'header.php';

?>
<div class="blockform">
	<h2><span><?php echo $lang_common['Login'] ?></span></h2>
	<div class="box">
		<form id="login" method="post" action="login.php?action=in" onsubmit="return process_form(this)">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_login['Login legend'] ?></legend>
					<div class="infldset">
						<input type="hidden" name="form_sent" value="1" />
						<input type="hidden" name="redirect_url" value="<?php echo pun_htmlspecialchars($redirect_url) ?>" />
						<label class="conl required"><strong><?php echo $lang_common['Username'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br /><input type="text" name="req_username" size="25" maxlength="25" tabindex="1" /><br /></label>
						<label class="conl required"><strong><?php echo $lang_common['Password'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br /><input type="password" name="req_password" size="25" tabindex="2" /><br /></label>

						<div class="rbox clearb">
							<label><input type="checkbox" name="save_pass" value="1" tabindex="3" /><?php echo $lang_login['Remember me'] ?><br /></label>
						</div>

						<p class="clearb"><?php echo $lang_login['Login info'] ?></p>
						<p class="actions"><span><a href="register.php" tabindex="4"><?php echo $lang_login['Not registered'] ?></a></span> <span><a href="login.php?action=forget" tabindex="5"><?php echo $lang_login['Forgotten pass'] ?></a></span></p>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="login" value="<?php echo $lang_common['Login'] ?>" tabindex="3" /></p>
		</form>
	</div>
</div>
<?php

require PUN_ROOT.'footer.php';
