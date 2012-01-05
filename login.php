<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

if (isset($_GET['action']))
	define('PUN_QUIET_VISIT', 1);

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';


// Load the login.php language file
$lang->load('login');

$action = isset($_GET['action']) ? $_GET['action'] : null;

if (isset($_POST['form_sent']) && $action == 'in')
{
	$form_username = pun_trim($_POST['req_username']);
	$form_password = pun_trim($_POST['req_password']);
	$save_pass = isset($_POST['save_pass']);

	$query = $db->select(array('user' => 'u.*'), 'users AS u');
	$query->where = 'LOWER(u.username) = LOWER(:username)';

	$params = array(':username' => $form_username);

	$result = $query->run($params);
	unset ($query, $params);

	$authorized = false;
	if (!empty($result))
	{
		$cur_user = $result[0];
		$form_password_hash = pun_hash($form_password); // Will result in a SHA-1 hash

		// If there is a salt in the database we have upgraded from 1.3-legacy though havent yet logged in
		if (!empty($cur_user['salt']))
		{
			if (sha1($cur_user['salt'].sha1($form_password)) == $cur_user['password']) // 1.3 used sha1(salt.sha1(pass))
			{
				$authorized = true;

				$query = $db->update(array('password' => ':password', 'salt' => ':salt'), 'users');
				$query->where = 'id = :id';
				$params = array(':password' => $form_password_hash, ':salt' => NULL, ':id' => $cur_user['id']);
				$query->run($params);
				unset($query, $params);
			}
		}
		// If the length isn't 40 then the password isn't using sha1, so it must be md5 from 1.2
		else if (strlen($cur_user['password']) != 40)
		{
			if (md5($form_password) == $cur_user['password'])
			{
				$authorized = true;

				$query = $db->update(array('password' => ':password'), 'users');
				$query->where = 'id = :id';
				$params = array(':password' => $form_password_hash, ':id' => $cur_user['id']);
				$query->run($params);
				unset($query, $params);
			}
		}
		// Otherwise we should have a normal sha1 password
		else
			$authorized = ($cur_user['password'] == $form_password_hash);
	}

	if (!$authorized)
		message($lang->t('Wrong user/pass').' <a href="login.php?action=forget">'.$lang->t('Forgotten pass').'</a>');

	// Update the status if this is the first time the user logged in
	if ($cur_user['group_id'] == PUN_UNVERIFIED)
	{
		$query = $db->update(array('group_id' => ':group_id'), 'users');
		$query->where = 'id = :id';
		$params = array(':group_id' => $pun_config['o_default_user_group'], ':id' => $cur_user['id']);
		$query->run($params);
		unset($query, $params);

		// Regenerate the users info cache
		$cache->delete('boardstats');
	}

	// Remove this users guest entry from the online list
	$query = $db->delete('online');
	$query->where = 'ident = :ident';

	$params = array(':ident' => get_remote_address());

	$query->run($params);
	unset ($query, $params);

	$expire = ($save_pass == '1') ? time() + 1209600 : time() + $pun_config['o_timeout_visit'];
	pun_setcookie($cur_user['id'], $form_password_hash, $expire);

	// Reset tracked topics
//	set_tracked_topics(null);

	redirect(htmlspecialchars($_POST['redirect_url']), $lang->t('Login redirect'));
}


else if ($action == 'out')
{
	if ($pun_user['is_guest'] || !isset($_GET['id']) || $_GET['id'] != $pun_user['id'] || !isset($_GET['csrf_token']) || $_GET['csrf_token'] != pun_hash($pun_user['id'].pun_hash(get_remote_address())))
	{
		header('Location: index.php');
		exit;
	}

	// Remove user from "users online" list
	$query = $db->delete('online');
	$query->where = 'user_id = :user_id';

	$params = array(':user_id' => $pun_user['id']);

	$query->run($params);
	unset($query, $params);

	// Update last_visit (make sure there's something to update it with)
	if (isset($pun_user['logged']))
	{
		$query = $db->update(array('last_visit' => ':last_visit'), 'users');
		$query->where = 'id = :id';

		$params = array(':last_visit' => $pun_user['logged'], ':id' => $pun_user['id']);

		$query->run($params);
		unset($query, $params);
	}

	pun_setcookie(1, pun_hash(uniqid(rand(), true)), time() + 31536000);

	redirect('index.php', $lang->t('Logout redirect'));
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
			$errors[] = $lang->t('Invalid email');

		// Did everything go according to plan?
		if (empty($errors))
		{
			$query = $db->select(array('id' => 'u.id', 'username' => 'u.username', 'last_email_sent' => 'u.last_email_sent'), 'users AS u');
			$query->where = 'u.email = :email';

			$params = array(':email' => $email);

			$result = $query->run($params);
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
				$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message);

				// Loop through users we found
				foreach ($result as $cur_hit)
				{
					if ($cur_hit['last_email_sent'] != '' && (time() - $cur_hit['last_email_sent']) < 3600 && (time() - $cur_hit['last_email_sent']) >= 0)
						message($lang->t('Email flood'), true);

					// Generate a new password and a new password activation code
					$new_password = random_pass(8);
					$new_password_key = random_pass(8);

					$query = $db->update(array('activate_string' => ':activate_string', 'activate_key' => ':activate_key', 'last_email_sent' => ':last_email_sent'), 'users');
					$query->where = 'id = :id';

					$params = array(':activate_string' => pun_hash($new_password), ':activate_key' => $new_password_key, ':last_email_sent' => time(), ':id' => $cur_hit['id']);

					$query->run($params);
					unset($params);

					// Do the user specific replacements to the template
					$cur_mail_message = str_replace('<username>', $cur_hit['username'], $mail_message);
					$cur_mail_message = str_replace('<activation_url>', get_base_url().'/profile.php?id='.$cur_hit['id'].'&action=change_pass&key='.$new_password_key, $cur_mail_message);
					$cur_mail_message = str_replace('<new_password>', $new_password, $cur_mail_message);

					pun_mail($email, $mail_subject, $cur_mail_message);
				}
				unset($result);

				message($lang->t('Forget mail').' <a href="mailto:'.$pun_config['o_admin_email'].'">'.$pun_config['o_admin_email'].'</a>.', true);
			}
			else
				$errors[] = $lang->t('No email match').' '.htmlspecialchars($email).'.';
			}
		}

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Request pass'));
	$required_fields = array('req_email' => $lang->t('Email'));
	$focus_element = array('request_pass', 'req_email');
	define ('PUN_ACTIVE_PAGE', 'login');
	require PUN_ROOT.'header.php';

// If there are errors, we display them
if (!empty($errors))
{

?>
<div id="posterror" class="block">
	<h2><span><?php echo $lang->t('New password errors') ?></span></h2>
	<div class="box">
		<div class="inbox error-info">
			<p><?php echo $lang->t('New password errors info') ?></p>
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
	<h2><span><?php echo $lang->t('Request pass') ?></span></h2>
	<div class="box">
		<form id="request_pass" method="post" action="login.php?action=forget_2" onsubmit="this.request_pass.disabled=true;if(process_form(this)){return true;}else{this.request_pass.disabled=false;return false;}">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang->t('Request pass legend') ?></legend>
					<div class="infldset">
						<input type="hidden" name="form_sent" value="1" />
						<label class="required"><strong><?php echo $lang->t('Email') ?> <span><?php echo $lang->t('Required') ?></span></strong><br /><input id="req_email" type="text" name="req_email" size="50" maxlength="80" /><br /></label>
						<p><?php echo $lang->t('Request pass info') ?></p>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="request_pass" value="<?php echo $lang->t('Submit') ?>" /><?php if (empty($errors)): ?> <a href="javascript:history.go(-1)"><?php echo $lang->t('Go back') ?></a><?php endif; ?></p>
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

	if ($referrer['host'] == $valid['host'] && preg_match('%^'.preg_quote($valid['path'], '%').'/(.*?)\.php%i', $referrer['path']))
		$redirect_url = $_SERVER['HTTP_REFERER'];
}

if (!isset($redirect_url))
	$redirect_url = 'index.php';

$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Login'));
$required_fields = array('req_username' => $lang->t('Username'), 'req_password' => $lang->t('Password'));
$focus_element = array('login', 'req_username');
define('PUN_ACTIVE_PAGE', 'login');
require PUN_ROOT.'header.php';

?>
<div class="blockform">
	<h2><span><?php echo $lang->t('Login') ?></span></h2>
	<div class="box">
		<form id="login" method="post" action="login.php?action=in" onsubmit="return process_form(this)">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang->t('Login legend') ?></legend>
					<div class="infldset">
						<input type="hidden" name="form_sent" value="1" />
						<input type="hidden" name="redirect_url" value="<?php echo pun_htmlspecialchars($redirect_url) ?>" />
						<label class="conl required"><strong><?php echo $lang->t('Username') ?> <span><?php echo $lang->t('Required') ?></span></strong><br /><input type="text" name="req_username" size="25" maxlength="25" tabindex="1" /><br /></label>
						<label class="conl required"><strong><?php echo $lang->t('Password') ?> <span><?php echo $lang->t('Required') ?></span></strong><br /><input type="password" name="req_password" size="25" tabindex="2" /><br /></label>

						<div class="rbox clearb">
							<label><input type="checkbox" name="save_pass" value="1" tabindex="3" /><?php echo $lang->t('Remember me') ?><br /></label>
						</div>

						<p class="clearb"><?php echo $lang->t('Login info') ?></p>
						<p class="actions"><span><a href="register.php" tabindex="5"><?php echo $lang->t('Not registered') ?></a></span> <span><a href="login.php?action=forget" tabindex="6"><?php echo $lang->t('Forgotten pass') ?></a></span></p>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="login" value="<?php echo $lang->t('Login') ?>" tabindex="4" /></p>
		</form>
	</div>
</div>
<?php

require PUN_ROOT.'footer.php';
