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


if (isset($_GET['action']))
	define('FORUM_QUIET_VISIT', 1);

if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', './');
require FORUM_ROOT.'include/common.php';

($hook = get_hook('li_start')) ? eval($hook) : null;

// Load the login.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/login.php';


$action = isset($_GET['action']) ? $_GET['action'] : null;
$errors = array();

// Login
if (isset($_POST['form_sent']) && $action == 'in')
{
	$form_username = forum_trim($_POST['req_username']);
	$form_password = forum_trim($_POST['req_password']);
	$save_pass = isset($_POST['save_pass']);

	($hook = get_hook('li_login_form_submitted')) ? eval($hook) : null;

	// Get user info matching login attempt
	$query = array(
		'SELECT'	=> 'u.id, u.group_id, u.password, u.salt',
		'FROM'		=> 'users AS u'
	);

	if ($db_type == 'mysql' || $db_type == 'mysqli')
		$query['WHERE'] = 'username=\''.$forum_db->escape($form_username).'\'';
	else
		$query['WHERE'] = 'LOWER(username)=LOWER(\''.$forum_db->escape($form_username).'\')';

	($hook = get_hook('li_qr_get_login_data')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	list($user_id, $group_id, $db_password_hash, $salt) = $forum_db->fetch_row($result);

	$authorized = false;
	if (!empty($db_password_hash))
	{
		$sha1_in_db = (strlen($db_password_hash) == 40) ? true : false;
		$form_password_hash = forum_hash($form_password, $salt);

		if ($sha1_in_db && $db_password_hash == $form_password_hash)
			$authorized = true;
		else if ((!$sha1_in_db && $db_password_hash == md5($form_password)) || ($sha1_in_db && $db_password_hash == sha1($form_password)))
		{
			$authorized = true;

			$salt = random_key(12);
			$form_password_hash = forum_hash($form_password, $salt);

			// There's an old MD5 hash or an unsalted SHA1 hash in the database, so we replace it
			// with a randomly generated salt and a new, salted SHA1 hash
			$query = array(
				'UPDATE'	=> 'users',
				'SET'		=> 'password=\''.$form_password_hash.'\', salt=\''.$forum_db->escape($salt).'\'',
				'WHERE'		=> 'id='.$user_id
			);

			($hook = get_hook('li_qr_update_user_hash')) ? eval($hook) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);
		}
	}

	($hook = get_hook('li_login_pre_auth_message')) ? eval($hook) : null;

	if (!$authorized)
		$errors[] = sprintf($lang_login['Wrong user/pass']);

	// Did everything go according to plan?
	if (empty($errors))
	{
		// Update the status if this is the first time the user logged in
		if ($group_id == FORUM_UNVERIFIED)
		{
			$query = array(
				'UPDATE'	=> 'users',
				'SET'		=> 'group_id='.$forum_config['o_default_user_group'],
				'WHERE'		=> 'id='.$user_id
			);

			($hook = get_hook('li_qr_update_user_group')) ? eval($hook) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);
		}

		// Remove this user's guest entry from the online list
		$query = array(
			'DELETE'	=> 'online',
			'WHERE'		=> 'ident=\''.$forum_db->escape(get_remote_address()).'\''
		);

		($hook = get_hook('li_qr_delete_online_user')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		$expire = ($save_pass) ? time() + 1209600 : time() + $forum_config['o_timeout_visit'];
		forum_setcookie($cookie_name, base64_encode($user_id.'|'.$form_password_hash.'|'.$expire.'|'.sha1($salt.$form_password_hash.forum_hash($expire, $salt))), $expire);

		redirect(forum_htmlencode($_POST['redirect_url']).((substr_count($_POST['redirect_url'], '?') == 1) ? '&amp;' : '?').'login=1', $lang_login['Login redirect']);
	}
}


// Logout
else if ($action == 'out')
{
	if ($forum_user['is_guest'] || !isset($_GET['id']) || $_GET['id'] != $forum_user['id'])
	{
		header('Location: '.forum_link($forum_url['index']));
		exit;
	}

	// We validate the CSRF token. If it's set in POST and we're at this point, the token is valid.
	// If it's in GET, we need to make sure it's valid.
	if (!isset($_POST['csrf_token']) && (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== generate_form_token('logout'.$forum_user['id'])))
		csrf_confirm_form();

	($hook = get_hook('li_logout_selected')) ? eval($hook) : null;

	// Remove user from "users online" list.
	$query = array(
		'DELETE'	=> 'online',
		'WHERE'		=> 'user_id='.$forum_user['id']
	);

	($hook = get_hook('li_qr_delete_online_user2')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	// Update last_visit (make sure there's something to update it with)
	if (isset($forum_user['logged']))
	{
		$query = array(
			'UPDATE'	=> 'users',
			'SET'		=> 'last_visit='.$forum_user['logged'],
			'WHERE'		=> 'id='.$forum_user['id']
		);

		($hook = get_hook('li_qr_update_last_visit')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);
	}

	$expire = time() + 1209600;
	forum_setcookie($cookie_name, base64_encode('1|'.random_key(8, false, true).'|'.$expire.'|'.random_key(8, false, true)), $expire);

	// Reset tracked topics
	set_tracked_topics(null);

	($hook = get_hook('li_logout_pre_redirect')) ? eval($hook) : null;

	redirect(forum_link($forum_url['index']), $lang_login['Logout redirect']);
}


// New password
else if ($action == 'forget' || $action == 'forget_2')
{
	if (!$forum_user['is_guest'])
		header('Location: '.forum_link($forum_url['index']));

	($hook = get_hook('li_forgot_pass_selected')) ? eval($hook) : null;

	if (isset($_POST['form_sent']))
	{
		require FORUM_ROOT.'include/email.php';

		// Validate the email-address
		$email = strtolower(forum_trim($_POST['req_email']));
		if (!is_valid_email($email))
			$errors[] = $lang_common['Invalid e-mail'];

		// Did everything go according to plan?
		if (empty($errors))
		{
			// Fetch user matching $email
			$query = array(
				'SELECT'	=> 'u.id, u.username, u.salt, u.last_email_sent',
				'FROM'		=> 'users AS u',
				'WHERE'		=> 'u.email=\''.$forum_db->escape($email).'\''
			);

			($hook = get_hook('li_qr_get_user_data')) ? eval($hook) : null;
			$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
			if ($forum_db->num_rows($result))
			{
				($hook = get_hook('li_forgot_pass_pre_email')) ? eval($hook) : null;

				// Load the "activate password" template
				$mail_tpl = forum_trim(file_get_contents(FORUM_ROOT.'lang/'.$forum_user['language'].'/mail_templates/activate_password.tpl'));

				// The first row contains the subject
				$first_crlf = strpos($mail_tpl, "\n");
				$mail_subject = forum_trim(substr($mail_tpl, 8, $first_crlf-8));
				$mail_message = forum_trim(substr($mail_tpl, $first_crlf));

				// Do the generic replacements first (they apply to all e-mails sent out here)
				$mail_message = str_replace('<base_url>', $base_url.'/', $mail_message);
				$mail_message = str_replace('<board_mailer>', sprintf($lang_common['Forum mailer'], $forum_config['o_board_title']), $mail_message);

				// Loop through users we found
				while ($cur_hit = $forum_db->fetch_assoc($result))
				{
					$forgot_pass_timeout = 3600;

					($hook = get_hook('li_forgot_pass_pre_flood_check')) ? eval($hook) : null;

					if ($cur_hit['last_email_sent'] != '' && (time() - $cur_hit['last_email_sent']) < $forgot_pass_timeout && (time() - $cur_hit['last_email_sent']) >= 0)
						message(sprintf($lang_login['Email flood'], $forgot_pass_timeout));

					// Generate a new password activation key
					$new_password_key = random_key(8, true);

					$query = array(
						'UPDATE'	=> 'users',
						'SET'		=> 'activate_key=\''.$new_password_key.'\', last_email_sent = '.time(),
						'WHERE'		=> 'id='.$cur_hit['id']
					);

					($hook = get_hook('li_qr_set_activate_key')) ? eval($hook) : null;
					$forum_db->query_build($query) or error(__FILE__, __LINE__);

					// Do the user specific replacements to the template
					$cur_mail_message = str_replace('<username>', $cur_hit['username'], $mail_message);
					$cur_mail_message = str_replace('<activation_url>', str_replace('&amp;', '&', forum_link($forum_url['change_password_key'], array($cur_hit['id'], $new_password_key))), $cur_mail_message);

					forum_mail($email, $mail_subject, $cur_mail_message);
				}

				message(sprintf($lang_login['Forget mail'], '<a href="mailto:'.$forum_config['o_admin_email'].'">'.$forum_config['o_admin_email'].'</a>'));
			}
			else
				$errors[] = sprintf($lang_login['No e-mail match'], forum_htmlencode($email));
		}
	}

	// Setup form
	$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;
	$forum_page['form_action'] = $base_url.'/login.php?action=forget_2';

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		$lang_login['New password request']
	);

	($hook = get_hook('li_forgot_pass_pre_header_load')) ? eval($hook) : null;

	define ('FORUM_PAGE', 'reqpass');
	define ('FORUM_PAGE_TYPE', 'basic');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

	($hook = get_hook('li_forgot_pass_output_start')) ? eval($hook) : null;

?>
	<div class="main-content main-frm">
		<div class="content-box">
			<p class="important"><?php echo $lang_login['New password info'] ?></p>
		</div>
<?php

	// If there were any errors, show them
	if (!empty($errors))
	{
		$forum_page['errors'] = array();
		while (list(, $cur_error) = each($errors))
			$forum_page['errors'][] = '<li class="warn"><span>'.$cur_error.'</span></li>';

		($hook = get_hook('li_pre_new_password_errors')) ? eval($hook) : null;

?>
		<div class="content-box error-box">
			<h2 class="warn"><?php echo $lang_login['New password errors'] ?></h2>
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
		<form id="afocus" class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
			<div class="hidden">
				<input type="hidden" name="form_sent" value="1" />
			</div>
<?php ($hook = get_hook('li_forgot_pass_pre_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_common['Required information'] ?></strong></legend>
				<div class="frm-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="frm-box text required">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><em><?php echo $lang_common['Reqmark'] ?></em> <?php echo $lang_login['E-mail address'] ?></span><small><?php echo $lang_login['E-mail address help'] ?></small></label><br />
						<span class="fld-input"><input id="fld<?php echo $forum_page['fld_count'] ?>" type="text" name="req_email" value="<?php echo isset($_POST['req_email']) ? forum_htmlencode($_POST['req_email']) : '' ?>" size="35" maxlength="80" /></span>
					</div>
				</div>
			</fieldset>
<?php ($hook = get_hook('li_forgot_pass_post_fieldset')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="request_pass" value="<?php echo $lang_common['Submit'] ?>" /></span>
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

if (!$forum_user['is_guest'])
	header('Location: '.forum_link($forum_url['index']));

// Setup form
$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;
$forum_page['form_action'] = $base_url.'/login.php?action=in';

$forum_page['hidden_fields'] = array(
	'<input type="hidden" name="form_sent" value="1" />',
	'<input type="hidden" name="redirect_url" value="'.forum_htmlencode($forum_user['prev_url']).'" />'
);

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	sprintf($lang_login['Login info'], $forum_config['o_board_title'])
);

($hook = get_hook('li_login_pre_header_load')) ? eval($hook) : null;

define('FORUM_PAGE', 'login');
define('FORUM_PAGE_TYPE', 'basic');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('li_login_output_start')) ? eval($hook) : null;

?>
	<div class="main-content main-frm">
		<div class="content-head">
			<p class="hn"><?php printf($lang_login['Login options'], '<a href="'.forum_link($forum_url['register']).'">'.$lang_login['register'].'</a>', '<a href="'.forum_link($forum_url['request_password']).'">'.$lang_login['Obtain pass'].'</a>') ?></p>
		</div>
<?php

	// If there were any errors, show them
	if (!empty($errors))
	{
		$forum_page['errors'] = array();
		while (list(, $cur_error) = each($errors))
			$forum_page['errors'][] = '<li class="warn"><span>'.$cur_error.'</span></li>';

		($hook = get_hook('li_pre_login_errors')) ? eval($hook) : null;

?>
		<div class="content-box error-box">
			<h2 class="warn"><?php echo $lang_login['Login errors'] ?></h2>
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
		<form id="afocus" class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
			</div>
<?php ($hook = get_hook('li_login_pre_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_login['Login legend'] ?></strong></legend>
				<div class="frm-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="frm-box text required">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><em><?php echo $lang_common['Reqmark'] ?></em> <?php echo $lang_login['Username'] ?></span></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_username" value="<?php echo isset($_POST['req_username']) ? forum_htmlencode($_POST['req_username']) : '' ?>" size="35" maxlength="25" /></span>
					</div>
				</div>
				<div class="frm-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="frm-box text required">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><em><?php echo $lang_common['Reqmark'] ?></em> <?php echo $lang_login['Password'] ?></span></label><br />
						<span class="fld-input"><input type="password" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_password" value="<?php echo isset($_POST['req_password']) ? ($_POST['req_password']) : '' ?>" size="35" /></span>
					</div>
				</div>
			</fieldset>
<?php $forum_page['item_count'] = 0; ?>
			<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_login['Login options legend'] ?></strong></legend>
				<div class="frm-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="frm-box checkbox">
						<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="save_pass" value="1" /></span>
						<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_login['Remember me'] ?></span> <?php echo $lang_login['Persistent login'] ?></label>
					</div>
				</div>
			</fieldset>
<?php ($hook = get_hook('li_login_post_fieldset')) ? eval($hook) : null; ?>
			<div class="frm-buttons frm-options">
				<span class="submit"><input type="submit" name="login" value="<?php echo $lang_common['Login'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

($hook = get_hook('li_end')) ? eval($hook) : null;

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
