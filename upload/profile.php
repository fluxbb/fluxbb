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

($hook = get_hook('pf_start')) ? eval($hook) : null;

$action = isset($_GET['action']) ? $_GET['action'] : null;
$section = isset($_GET['section']) ? $_GET['section'] : 'about';	// Default to section "about"
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id < 2)
	message($lang_common['Bad request']);

$errors = array();

if ($action != 'change_pass' || !isset($_GET['key']))
{
	if ($forum_user['g_read_board'] == '0')
		message($lang_common['No view']);
	else if ($forum_user['g_view_users'] == '0' && ($forum_user['is_guest'] || $forum_user['id'] != $id))
		message($lang_common['No permission']);
}

// Load the profile.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/profile.php';


// Fetch info about the user whose profile we're viewing
$query = array(
	'SELECT'	=> 'u.*, g.g_id, g.g_user_title, g.g_moderator',
	'FROM'		=> 'users AS u',
	'JOINS'		=> array(
		array(
			'LEFT JOIN'	=> 'groups AS g',
			'ON'		=> 'g.g_id=u.group_id'
		)
	),
	'WHERE'		=> 'u.id='.$id
);

($hook = get_hook('pf_qr_get_user_info')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
if (!$forum_db->num_rows($result))
	message($lang_common['Bad request']);

$user = $forum_db->fetch_assoc($result);


if ($action == 'change_pass')
{
	($hook = get_hook('pf_change_pass_selected')) ? eval($hook) : null;

	// User pressed the cancel button
	if (isset($_POST['cancel']))
		redirect(forum_link($forum_url['profile_about'], $id), $lang_common['Cancel redirect']);

	if (isset($_GET['key']))
	{
		// If the user is already logged in we shouldn't be here :)
		if (!$forum_user['is_guest'])
			message($lang_profile['Pass logout']);

		($hook = get_hook('pf_change_pass_key_supplied')) ? eval($hook) : null;

		$key = $_GET['key'];

		if ($key == '' || $key != $user['activate_key'])
			message(sprintf($lang_profile['Pass key bad'], '<a href="mailto:'.$forum_config['o_admin_email'].'">'.$forum_config['o_admin_email'].'</a>'));
		else
		{
			if (isset($_POST['form_sent']))
			{
				($hook = get_hook('pf_change_pass_key_form_submitted')) ? eval($hook) : null;

				$new_password1 = trim($_POST['req_new_password1']);
				$new_password2 = trim($_POST['req_new_password2']);

				if (forum_strlen($new_password1) < 4)
					$errors[] = $lang_profile['Pass too short'];
				else if ($new_password1 != $new_password2)
					$errors[] = $lang_profile['Pass not match'];

				// Did everything go according to plan?
				if (empty($errors))
				{
					$new_password_hash = sha1($user['salt'].sha1($new_password1));

					$query = array(
						'UPDATE'	=> 'users',
						'SET'		=> 'password=\''.$new_password_hash.'\', activate_key=NULL',
						'WHERE'		=> 'id='.$id
					);

					($hook = get_hook('pf_qr_update_password')) ? eval($hook) : null;
					$forum_db->query_build($query) or error(__FILE__, __LINE__);

					redirect(forum_link($forum_url['index']), $lang_profile['Pass updated']);
				}
			}

			// Setup form
			$forum_page['set_count'] = $forum_page['fld_count'] = 0;
			$forum_page['form_action'] = forum_link($forum_url['change_password_key'], array($id, $key));

			// Setup breadcrumbs
			$forum_page['crumbs'] = array(
				array($forum_config['o_board_title'], forum_link($forum_url['index'])),
				array(sprintf($lang_profile['Users profile'], $user['username'], $lang_profile['Section about']), forum_link($forum_url['profile_about'], $id)),
				$lang_profile['Change password']
			);

			($hook = get_hook('pf_change_pass_key_pre_header_load')) ? eval($hook) : null;

			define('FORUM_PAGE', 'profile-changepass');
			require FORUM_ROOT.'header.php';

			// START SUBST - <!-- forum_main -->
			ob_start();

?>
<div id="brd-main" class="main">

	<h1><span><?php printf($lang_profile['Users profile'], forum_htmlencode($user['username'])) ?></span></h1>

	<div class="main-head">
		<h2><span><?php echo $lang_profile['Change password'] ?></span></h2>
	</div>

	<div class="main-content frm">
<?php

			// If there were any errors, show them
			if (!empty($errors))
			{
				$forum_page['errors'] = array();
				while (list(, $cur_error) = each($errors))
					$forum_page['errors'][] = '<li class="warn"><span>'.$cur_error.'</span></li>';

				($hook = get_hook('pf_pre_change_pass_key_errors')) ? eval($hook) : null;

?>
		<div class="frm-error">
			<h3 class="warn"><?php echo $lang_profile['Change pass errors'] ?></h3>
			<ul>
				<?php echo implode("\n\t\t\t\t\t", $forum_page['errors'])."\n" ?>
			</ul>
		</div>
<?php

			}

?>
		<div id="req-msg" class="frm-warn">
			<p class="important"><?php printf($lang_common['Required warn'], '<em class="req-text">'.$lang_common['Required'].'</em>') ?></p>
		</div>
		<form id="afocus" class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
			<div class="hidden">
				<input type="hidden" name="form_sent" value="1" />
			</div>
<?php ($hook = get_hook('pf_change_pass_key_pre_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_common['Required information'] ?></strong></legend>
				<div class="frm-fld text required">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['New password'] ?></span><br />
						<span class="fld-input"><input type="password" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_new_password1" size="35" value="<?php echo(isset($_POST['req_new_password1']) ? ($_POST['req_new_password1']) : ''); ?>"/></span><br />
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
						<span class="fld-help"><?php echo $lang_profile['Password help'] ?></span>
					</label>
				</div>
				<div class="frm-fld text required">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Confirm new password'] ?></span><br />
						<span class="fld-input"><input type="password" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_new_password2" size="35" value="<?php echo(isset($_POST['req_new_password2']) ? ($_POST['req_new_password2']) : ''); ?>"/></span><br />
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
						<span class="fld-help"><?php echo $lang_profile['Confirm password help'] ?></span>
					</label>
				</div>
			</fieldset>
<?php ($hook = get_hook('pf_change_pass_key_post_fieldset')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="update" value="<?php echo $lang_common['Submit'] ?>" /></span>
				<span class="cancel"><input type="submit" name="cancel" value="<?php echo $lang_common['Cancel'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

			$tpl_temp = trim(ob_get_contents());
			$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
			ob_end_clean();
			// END SUBST - <!-- forum_main -->

			require FORUM_ROOT.'footer.php';
		}
	}

	// Make sure we are allowed to change this user's password
	if ($forum_user['id'] != $id &&
		$forum_user['g_id'] != FORUM_ADMIN &&
		($forum_user['g_moderator'] != '1' || $forum_user['g_mod_edit_users'] == '0' || $forum_user['g_mod_change_passwords'] == '0' || $user['g_id'] == FORUM_ADMIN || $user['g_moderator'] == '1'))
		message($lang_common['No permission']);

	if (isset($_POST['form_sent']))
	{
		($hook = get_hook('pf_change_pass_normal_form_submitted')) ? eval($hook) : null;

		$old_password = isset($_POST['req_old_password']) ? trim($_POST['req_old_password']) : '';
		$new_password1 = trim($_POST['req_new_password1']);
		$new_password2 = trim($_POST['req_new_password2']);

		if (forum_strlen($new_password1) < 4)
			$errors[] = $lang_profile['Pass too short'];
		else if ($new_password1 != $new_password2)
			$errors[] = $lang_profile['Pass not match'];

		$authorized = false;
		if (!empty($user['password']))
		{
			$old_password_hash = sha1($user['salt'].sha1($old_password));

			if (($user['password'] == $old_password_hash) || $forum_user['is_admmod'])
				$authorized = true;
		}

		if (!$authorized)
			$errors[] = $lang_profile['Wrong old password'];

		// Did everything go according to plan?
		if (empty($errors))
		{
			$new_password_hash = sha1($user['salt'].sha1($new_password1));

			$query = array(
				'UPDATE'	=> 'users',
				'SET'		=> 'password=\''.$new_password_hash.'\'',
				'WHERE'		=> 'id='.$id
			);

			($hook = get_hook('pf_qr_update_password2')) ? eval($hook) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);

			if ($forum_user['id'] == $id)
			{
				$expire = ($user['save_pass'] == '1') ? time() + 31536000 : 0;
				forum_setcookie($cookie_name, base64_encode($forum_user['id'].'|'.$new_password_hash), $expire);
			}

			redirect(forum_link($forum_url['profile_about'], $id), $lang_profile['Pass updated redirect']);
		}
	}

	// Setup form
	$forum_page['set_count'] = $forum_page['fld_count'] = 0;
	$forum_page['form_action'] = forum_link($forum_url['change_password'], $id);

	$forum_page['hidden_fields']['form_sent'] = '<input type="hidden" name="form_sent" value="1" />';
	if ($forum_user['is_admmod'])
		$forum_page['hidden_fields']['csrf_token'] = '<input type="hidden" name="csrf_token" value="'.generate_form_token($forum_page['form_action']).'" />';

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array(sprintf($lang_profile['Users profile'], $user['username'], $lang_profile['Section about']), forum_link($forum_url['profile_about'], $id)),
		$lang_profile['Change password']
	);

	($hook = get_hook('pf_change_pass_normal_pre_header_load')) ? eval($hook) : null;

	define('FORUM_PAGE', 'profile-changepass');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

?>
<div id="brd-main" class="main sectioned">

	<h1><span><?php printf($lang_profile['Users profile'], forum_htmlencode($user['username'])) ?></span></h1>

	<div class="main-head">
		<h2><span><?php echo $lang_profile['Change password'] ?></span></h2>
	</div>

	<div class="main-content frm">
<?php

	// If there were any errors, show them
	if (!empty($errors))
	{
		$forum_page['errors'] = array();
		while (list(, $cur_error) = each($errors))
			$forum_page['errors'][] = '<li class="warn"><span>'.$cur_error.'</span></li>';

		($hook = get_hook('pf_pre_change_pass_errors')) ? eval($hook) : null;

?>
		<div class="frm-error">
			<h3 class="warn"><?php echo $lang_profile['Change pass errors'] ?></h3>
			<ul>
				<?php echo implode("\n\t\t\t\t\t", $forum_page['errors'])."\n" ?>
			</ul>
		</div>
<?php

	}

?>
		<div id="req-msg" class="frm-warn">
			<p class="important"><?php printf($lang_common['Required warn'], '<em class="req-text">'.$lang_common['Required'].'</em>') ?></p>
		</div>
		<form id="afocus" class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action']  ?>">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
			</div>
<?php ($hook = get_hook('pf_change_pass_normal_pre_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_common['Required information'] ?></strong></legend>
<?php if (!$forum_user['is_admmod']): ?>					<div class="frm-fld text required">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Old password'] ?></span><br />
						<span class="fld-input"><input type="password" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_old_password" size="35" value="<?php echo(isset($_POST['req_old_password']) ? ($_POST['req_old_password']) : ''); ?>"/></span><br />
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
						<span class="fld-help"><?php echo $lang_profile['Old password help'] ?></span>
					</label>
				</div>
<?php endif; ?>				<div class="frm-fld text required">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['New password'] ?></span><br />
						<span class="fld-input"><input type="password" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_new_password1" size="35" value="<?php echo(isset($_POST['req_new_password1']) ? ($_POST['req_new_password1']) : ''); ?>"/></span><br />
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
						<span class="fld-help"><?php echo $lang_profile['Password help'] ?></span>
					</label>
				</div>
				<div class="frm-fld text required">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Confirm new password'] ?></span><br />
						<span class="fld-input"><input type="password" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_new_password2" size="35" value="<?php echo(isset($_POST['req_new_password2']) ? ($_POST['req_new_password2']) : ''); ?>"/></span><br />
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
						<span class="fld-help"><?php echo $lang_profile['Confirm password help'] ?></span>
					</label>
				</div>
			</fieldset>
<?php ($hook = get_hook('pf_change_pass_normal_post_fieldset')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="update" value="<?php echo $lang_common['Submit'] ?>" /></span>
				<span class="cancel"><input type="submit" name="cancel" value="<?php echo $lang_common['Cancel'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

	$tpl_temp = trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}


else if ($action == 'change_email')
{
	// Make sure we are allowed to change this user's e-mail
	if ($forum_user['id'] != $id &&
		$forum_user['g_id'] != FORUM_ADMIN &&
		($forum_user['g_moderator'] != '1' || $forum_user['g_mod_edit_users'] == '0' || $user['g_id'] == FORUM_ADMIN || $user['g_moderator'] == '1'))
		message($lang_common['No permission']);

	($hook = get_hook('pf_change_email_selected')) ? eval($hook) : null;

	// User pressed the cancel button
	if (isset($_POST['cancel']))
		redirect(forum_link($forum_url['profile_about'], $id), $lang_common['Cancel redirect']);

	if (isset($_GET['key']))
	{
		$key = $_GET['key'];

		($hook = get_hook('pf_change_email_key_supplied')) ? eval($hook) : null;

		if ($key == '' || $key != $user['activate_key'])
			message(sprintf($lang_profile['E-mail key bad'], '<a href="mailto:'.$forum_config['o_admin_email'].'">'.$forum_config['o_admin_email'].'</a>'));
		else
		{
			$query = array(
				'UPDATE'	=> 'users',
				'SET'		=> 'email=activate_string, activate_string=NULL, activate_key=NULL',
				'WHERE'		=> 'id='.$id
			);

			($hook = get_hook('pf_qr_update_email')) ? eval($hook) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);

			message($lang_profile['E-mail updated']);
		}
	}
	else if (isset($_POST['form_sent']))
	{
		($hook = get_hook('pf_change_email_normal_form_submitted')) ? eval($hook) : null;

		if (sha1($forum_user['salt'].sha1($_POST['req_password'])) !== $forum_user['password'])
			$errors[] = $lang_profile['Wrong password'];

		require FORUM_ROOT.'include/email.php';

		// Validate the email-address
		$new_email = strtolower(trim($_POST['req_new_email']));
		if (!is_valid_email($new_email))
			$errors[] = $lang_common['Invalid e-mail'];

		// Check it it's a banned e-mail address
		if (is_banned_email($new_email))
		{
			($hook = get_hook('pf_change_email_normal_banned_email')) ? eval($hook) : null;

			if ($forum_config['p_allow_banned_email'] == '0')
				$errors[] = $lang_profile['Banned e-mail'];
			else if ($forum_config['o_mailing_list'] != '')
			{
				$mail_subject = 'Alert - Banned e-mail detected';
				$mail_message = 'User \''.$forum_user['username'].'\' changed to banned e-mail address: '.$new_email."\n\n".'User profile: '.forum_link($forum_url['user'], $id)."\n\n".'-- '."\n".'Forum Mailer'."\n".'(Do not reply to this message)';

				forum_mail($forum_config['o_mailing_list'], $mail_subject, $mail_message);
			}
		}

		// Check if someone else already has registered with that e-mail address
		$query = array(
			'SELECT'	=> 'u.id, u.username',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'u.email=\''.$forum_db->escape($new_email).'\''
		);

		($hook = get_hook('pf_qr_check_email_dupe')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		if ($forum_db->num_rows($result))
		{
			($hook = get_hook('pf_change_email_normal_dupe_email')) ? eval($hook) : null;

			if ($forum_config['p_allow_dupe_email'] == '0')
				$errors[] = $lang_profile['Dupe e-mail'];
			else if (($forum_config['o_mailing_list'] != '') && empty($errors))
			{
				while ($cur_dupe = $forum_db->fetch_assoc($result))
					$dupe_list[] = $cur_dupe['username'];

				$mail_subject = 'Alert - Duplicate e-mail detected';
				$mail_message = 'User \''.$forum_user['username'].'\' changed to an e-mail address that also belongs to: '.implode(', ', $dupe_list)."\n\n".'User profile: '.forum_link($forum_url['user'], $id)."\n\n".'-- '."\n".'Forum Mailer'."\n".'(Do not reply to this message)';

				forum_mail($forum_config['o_mailing_list'], $mail_subject, $mail_message);
			}
		}

		// Did everything go according to plan?
		if (empty($errors))
		{
			$new_email_key = random_key(8, true);

			// Save new e-mail and activation key
			$query = array(
				'UPDATE'	=> 'users',
				'SET'		=> 'activate_string=\''.$forum_db->escape($new_email).'\', activate_key=\''.$new_email_key.'\'',
				'WHERE'		=> 'id='.$id
			);

			($hook = get_hook('pf_qr_update_email_activation')) ? eval($hook) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);

			// Load the "activate e-mail" template
			$mail_tpl = trim(file_get_contents(FORUM_ROOT.'lang/'.$forum_user['language'].'/mail_templates/activate_email.tpl'));

			// The first row contains the subject
			$first_crlf = strpos($mail_tpl, "\n");
			$mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
			$mail_message = trim(substr($mail_tpl, $first_crlf));

			$mail_message = str_replace('<username>', $forum_user['username'], $mail_message);
			$mail_message = str_replace('<base_url>', $base_url.'/', $mail_message);
			$mail_message = str_replace('<activation_url>', str_replace('&amp;', '&', forum_link($forum_url['change_email_key'], array($id, $new_email_key))), $mail_message);
			$mail_message = str_replace('<board_mailer>', sprintf($lang_common['Forum mailer'], $forum_config['o_board_title']), $mail_message);

			($hook = get_hook('pf_change_email_normal_pre_activation_email_sent')) ? eval($hook) : null;

			forum_mail($new_email, $mail_subject, $mail_message);

			message(sprintf($lang_profile['Activate e-mail sent'], '<a href="mailto:'.$forum_config['o_admin_email'].'">'.$forum_config['o_admin_email'].'</a>'));
		}
	}

	// Setup form
	$forum_page['set_count'] = $forum_page['fld_count'] = 0;
	$forum_page['form_action'] = forum_link($forum_url['change_email'], $id);

	$forum_page['hidden_fields']['form_sent'] = '<input type="hidden" name="form_sent" value="1" />';
	if ($forum_user['is_admmod'])
		$forum_page['hidden_fields']['csrf_token'] = '<input type="hidden" name="csrf_token" value="'.generate_form_token($forum_page['form_action']).'" />';

	// Setup form information
	$forum_page['frm_info'] = '<p class="important"><span>'.$lang_profile['E-mail info'].'</span></p>';

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array(sprintf($lang_profile['Users profile'], $user['username'], $lang_profile['Section about']), forum_link($forum_url['profile_about'], $id)),
		$lang_profile['Change e-mail']
	);

	($hook = get_hook('pf_change_email_normal_pre_header_load')) ? eval($hook) : null;

	define('FORUM_PAGE', 'profile-changemail');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

?>
<div id="brd-main" class="main">

	<h1><span><?php printf($lang_profile['Users profile'], forum_htmlencode($user['username'])) ?></span></h1>

	<div class="main-head">
		<h2><span><?php echo $lang_profile['Change e-mail'] ?></span></h2>
	</div>

	<div class="main-content frm">
		<div class="frm-info">
			<?php echo $forum_page['frm_info']."\n" ?>
		</div>
<?php

	// If there were any errors, show them
	if (!empty($errors))
	{
		$forum_page['errors'] = array();
		while (list(, $cur_error) = each($errors))
			$forum_page['errors'][] = '<li class="warn"><span>'.$cur_error.'</span></li>';

		($hook = get_hook('pf_pre_change_email_errors')) ? eval($hook) : null;

?>
		<div class="frm-error">
			<h3 class="warn"><?php echo $lang_profile['Change e-mail errors'] ?></h3>
			<ul>
				<?php echo implode("\n\t\t\t\t\t", $forum_page['errors'])."\n" ?>
			</ul>
		</div>
<?php

	}

?>
		<div id="req-msg" class="frm-warn">
			<p class="important"><?php printf($lang_common['Required warn'], '<em class="req-text">'.$lang_common['Required'].'</em>') ?></p>
		</div>
		<form id="afocus" class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
			</div>
<?php ($hook = get_hook('pf_change_email_normal_pre_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_common['Required information'] ?></strong></legend>
				<div class="frm-fld text required">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['New e-mail'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_new_email" size="50" maxlength="80" value="<?php echo(isset($_POST['req_new_email']) ? forum_htmlencode($_POST['req_new_email']) : ''); ?>"/></span>
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
					</label>
				</div>
				<div class="frm-fld text required">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Password'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_password" size="25" value="<?php echo(isset($_POST['req_password']) ? ($_POST['req_password']) : ''); ?>"/></span>
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
					</label>
				</div>
			</fieldset>
<?php ($hook = get_hook('pf_change_email_normal_post_fieldset')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="update" value="<?php echo $lang_common['Submit'] ?>" /></span>
				<span class="cancel"><input type="submit" name="cancel" value="<?php echo $lang_common['Cancel'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

	$tpl_temp = trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}

else if ($action == 'delete_user' || isset($_POST['delete_user_comply']) || isset($_POST['cancel']))
{
	// User pressed the cancel button
	if (isset($_POST['cancel']))
		redirect(forum_link($forum_url['profile_admin'], $id), $lang_common['Cancel redirect']);

	($hook = get_hook('pf_delete_user_selected')) ? eval($hook) : null;

	if ($forum_user['g_id'] != FORUM_ADMIN)
		message($lang_common['No permission']);

	if ($user['g_id'] == FORUM_ADMIN)
		message($lang_profile['Cannot delete admin']);

	if (isset($_POST['delete_user_comply']))
	{
		($hook = get_hook('pf_delete_user_form_submitted')) ? eval($hook) : null;

		delete_user($id);

		redirect(forum_link($forum_url['index']), $lang_profile['User delete redirect']);
	}

	// Setup form
	$forum_page['set_count'] = $forum_page['fld_count'] = 0;
	$forum_page['form_action'] = forum_link($forum_url['delete_user'], $id);

	// Setup form information
	$forum_page['frm_info'] = array(
		'<li class="warn"><span>'.$lang_profile['Delete warning'].'</span></li>',
		'<li class="warn"><span>'.$lang_profile['Delete posts info'].'</span></li>'
	);

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array(sprintf($lang_profile['Users profile'], $user['username'], $lang_profile['Section admin']), forum_link($forum_url['profile_admin'], $id)),
		$lang_profile['Delete user']
	);

	($hook = get_hook('pf_delete_user_pre_header_load')) ? eval($hook) : null;

	define('FORUM_PAGE', 'dialogue');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

?>
<div id="brd-main" class="main">

	<h1><span><?php printf($lang_profile['Users profile'], forum_htmlencode($user['username'])) ?></span></h1>

	<div class="main-head">
		<h2><span><?php echo $lang_common['Delete'].' '.forum_htmlencode($user['username']) ?></span></h2>
	</div>

	<div class="main-content frm">
		<div class="frm-info">
			<ul>
				<?php echo implode("\n\t\t\t\t\t", $forum_page['frm_info'])."\n" ?>
			</ul>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token($forum_page['form_action']) ?>" />
			</div>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_common['Required information'] ?></strong></legend>
				<div class="checkbox radbox">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span class="fld-label"><?php echo $lang_profile['Delete posts'] ?></span><br /><input type="checkbox" id="fld<?php echo $forum_page['fld_count'] ?>" name="delete_posts" value="1" checked="checked" /> <?php printf($lang_profile['Delete posts label'], forum_htmlencode($user['username'])) ?></label>
				</div>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="delete_user_comply" value="<?php echo $lang_common['Submit'] ?>" /></span>
				<span class="cancel"><input type="submit" name="cancel" value="<?php echo $lang_common['Cancel'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

	$tpl_temp = trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}


else if ($action == 'delete_avatar')
{
	// Make sure we are allowed to delete this user's avatar
	if ($forum_user['id'] != $id &&
		$forum_user['g_id'] != FORUM_ADMIN &&
		($forum_user['g_moderator'] != '1' || $forum_user['g_mod_edit_users'] == '0' || $user['g_id'] == FORUM_ADMIN || $user['g_moderator'] == '1'))
		message($lang_common['No permission']);

	// We validate the CSRF token. If it's set in POST and we're at this point, the token is valid.
	// If it's in GET, we need to make sure it's valid.
	if (!isset($_POST['csrf_token']) && (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== generate_form_token('delete_avatar'.$id.$forum_user['id'])))
		csrf_confirm_form();

	($hook = get_hook('pf_delete_avatar_selected')) ? eval($hook) : null;

	if (file_exists($forum_config['o_avatars_dir'].'/'.$id.'.jpg'))
		@unlink($forum_config['o_avatars_dir'].'/'.$id.'.jpg');
	if (file_exists($forum_config['o_avatars_dir'].'/'.$id.'.png'))
		@unlink($forum_config['o_avatars_dir'].'/'.$id.'.png');
	if (file_exists($forum_config['o_avatars_dir'].'/'.$id.'.gif'))
		@unlink($forum_config['o_avatars_dir'].'/'.$id.'.gif');

	redirect(forum_link($forum_url['profile_avatar'], $id), $lang_profile['Avatar deleted redirect']);
}


else if (isset($_POST['update_group_membership']))
{
	if ($forum_user['g_id'] != FORUM_ADMIN)
		message($lang_common['No permission']);

	($hook = get_hook('pf_change_group_form_submitted')) ? eval($hook) : null;

	$new_group_id = intval($_POST['group_id']);

	$query = array(
		'UPDATE'	=> 'users',
		'SET'		=> 'group_id='.$new_group_id,
		'WHERE'		=> 'id='.$id
	);

	($hook = get_hook('pf_qr_update_group')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	$query = array(
		'SELECT'	=> 'g.g_moderator',
		'FROM'		=> 'groups AS g',
		'WHERE'		=> 'g.g_id='.$new_group_id
	);

	($hook = get_hook('pf_qr_check_new_group_mod')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$new_group_mod = $forum_db->result($result);

	// If the user was a moderator or an administrator (and no longer is), we remove him/her from the moderator list in all forums
	if (($user['g_id'] == FORUM_ADMIN || $user['g_moderator'] == '1') && $new_group_id != FORUM_ADMIN && $new_group_mod != '1')
		clean_forum_moderators();

	redirect(forum_link($forum_url['profile_admin'], $id), $lang_profile['Group membership redirect']);
}


else if (isset($_POST['update_forums']))
{
	if ($forum_user['g_id'] != FORUM_ADMIN)
		message($lang_common['No permission']);

	($hook = get_hook('pf_forum_moderators_form_submitted')) ? eval($hook) : null;

	$moderator_in = (isset($_POST['moderator_in'])) ? array_keys($_POST['moderator_in']) : array();

	// Loop through all forums
	$query = array(
		'SELECT'	=> 'f.id, f.moderators',
		'FROM'		=> 'forums AS f'
	);

	($hook = get_hook('pf_qr_get_all_forum_mods')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	while ($cur_forum = $forum_db->fetch_assoc($result))
	{
		$cur_moderators = ($cur_forum['moderators'] != '') ? unserialize($cur_forum['moderators']) : array();

		// If the user should have moderator access (and he/she doesn't already have it)
		if (in_array($cur_forum['id'], $moderator_in) && !in_array($id, $cur_moderators))
		{
			$cur_moderators[$user['username']] = $id;
			ksort($cur_moderators);
		}
		// If the user shouldn't have moderator access (and he/she already has it)
		else if (!in_array($cur_forum['id'], $moderator_in) && in_array($id, $cur_moderators))
			unset($cur_moderators[$user['username']]);

		$cur_moderators = (!empty($cur_moderators)) ? '\''.$forum_db->escape(serialize($cur_moderators)).'\'' : 'NULL';

		$query = array(
			'UPDATE'	=> 'forums',
			'SET'		=> 'moderators='.$cur_moderators,
			'WHERE'		=> 'id='.$cur_forum['id']
		);

		($hook = get_hook('pf_qr_update_forum_moderators')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);
	}

	redirect(forum_link($forum_url['profile_admin'], $id), $lang_profile['Update forums redirect']);
}


else if (isset($_POST['ban']))
{
	if ($forum_user['g_id'] != FORUM_ADMIN && ($forum_user['g_moderator'] != '1' || $forum_user['g_mod_ban_users'] == '0'))
		message($lang_common['No permission']);

	($hook = get_hook('pf_ban_user_selected')) ? eval($hook) : null;

	redirect(forum_link($forum_url['admin_bans']).'?add_ban='.$id, $lang_profile['Ban redirect']);
}


else if (isset($_POST['form_sent']))
{
	// Make sure we are allowed to edit this user's profile
	if ($forum_user['id'] != $id &&
		$forum_user['g_id'] != FORUM_ADMIN &&
		($forum_user['g_moderator'] != '1' || $forum_user['g_mod_edit_users'] == '0' || $user['g_id'] == FORUM_ADMIN || $user['g_moderator'] == '1'))
		message($lang_common['No permission']);

	($hook = get_hook('pf_change_details_form_submitted')) ? eval($hook) : null;

	// Extract allowed elements from $_POST['form']
	function extract_elements($allowed_elements)
	{
		$form = array();

		while (list($key, $value) = @each($_POST['form']))
		{
			if (in_array($key, $allowed_elements))
				$form[$key] = $value;
		}

		return $form;
	}

	$username_updated = false;

	// Validate input depending on section
	switch ($section)
	{
		case 'identity':
		{
			$form = extract_elements(array('realname', 'url', 'location', 'jabber', 'icq', 'msn', 'aim', 'yahoo'));

			($hook = get_hook('pf_change_details_identity_validation')) ? eval($hook) : null;

			if ($forum_user['is_admmod'])
			{
				// Are we allowed to change usernames?
				if ($forum_user['g_id'] == FORUM_ADMIN || ($forum_user['g_moderator'] == '1' && $forum_user['g_mod_rename_users'] == '1'))
				{
					$form['username'] = trim($_POST['req_username']);
					$old_username = trim($_POST['old_username']);

					// Validate the new username
					$errors = array_merge($errors, validate_username($form['username'], $id));

					if ($form['username'] != $old_username)
						$username_updated = true;
				}

				// We only allow administrators to update the post count
				if ($forum_user['g_id'] == FORUM_ADMIN)
					$form['num_posts'] = intval($_POST['num_posts']);
			}

			if ($forum_config['o_regs_verify'] == '0' || $forum_user['is_admmod'])
			{
				require FORUM_ROOT.'include/email.php';

				// Validate the email-address
				$form['email'] = strtolower(trim($_POST['req_email']));
				if (!is_valid_email($form['email']))
					$errors[] = $lang_common['Invalid e-mail'];
			}

			if ($forum_user['is_admmod'])
				$form['admin_note'] = trim($_POST['admin_note']);

			if ($forum_user['g_id'] == FORUM_ADMIN)
				$form['title'] = trim($_POST['title']);
			else if ($forum_user['g_set_title'] == '1')
			{
				$form['title'] = trim($_POST['title']);

				if ($form['title'] != '')
				{
					// A list of words that the title may not contain
					// If the language is English, there will be some duplicates, but it's not the end of the world
					$forbidden = array('Member', 'Moderator', 'Administrator', 'Banned', 'Guest', $lang_common['Member'], $lang_common['Moderator'], $lang_common['Administrator'], $lang_common['Banned'], $lang_common['Guest']);

					if (in_array($form['title'], $forbidden))
						$errors[] = $lang_profile['Forbidden title'];
				}
			}

			// Add http:// if the URL doesn't contain it already
			if ($form['url'] != '' && strpos(strtolower($form['url']), 'http://') !== 0)
				$form['url'] = 'http://'.$form['url'];

			// If the ICQ UIN contains anything other than digits it's invalid
			if ($form['icq'] != '' && !ctype_digit($form['icq']))
				$errors[] = $lang_profile['Bad ICQ'];

			break;
		}

		case 'settings':
		{
			$form = extract_elements(array('dst', 'timezone', 'language', 'email_setting', 'save_pass', 'notify_with_post', 'auto_notify', 'time_format', 'date_format', 'disp_topics', 'disp_posts', 'show_smilies', 'show_img', 'show_img_sig', 'show_avatars', 'show_sig', 'style'));

			($hook = get_hook('pf_change_details_settings_validation')) ? eval($hook) : null;

			$form['dst'] = (isset($form['dst'])) ? 1 : 0;
			$form['time_format'] = (isset($form['time_format'])) ? intval($form['time_format']) : 0;
			$form['date_format'] = (isset($form['date_format'])) ? intval($form['date_format']) : 0;

			$form['email_setting'] = intval($form['email_setting']);
			if ($form['email_setting'] < 0 && $form['email_setting'] > 2) $form['email_setting'] = 1;

			if (!isset($form['save_pass']) || $form['save_pass'] != '1') $form['save_pass'] = '0';

			if ($forum_config['o_subscriptions'] == '1')
			{
				if (!isset($form['notify_with_post']) || $form['notify_with_post'] != '1') $form['notify_with_post'] = '0';
				if (!isset($form['auto_notify']) || $form['auto_notify'] != '1') $form['auto_notify'] = '0';
			}

			// If the save_pass setting has changed, we need to set a new cookie with the appropriate expire date
			if ($forum_user['id'] == $id && $form['save_pass'] != $forum_user['save_pass'])
				forum_setcookie($cookie_name, base64_encode($id.'|'.$user['password']), ($form['save_pass'] == '1') ? time() + 31536000 : 0);

			// Make sure we got a valid language string
			if (isset($form['language']))
			{
				$form['language'] = preg_replace('#[\.\\\/]#', '', $form['language']);
				if (!file_exists(FORUM_ROOT.'lang/'.$form['language'].'/common.php'))
					message($lang_common['Bad request']);
			}

			if ($form['disp_topics'] != '' && intval($form['disp_topics']) < 3) $form['disp_topics'] = 3;
			if ($form['disp_topics'] != '' && intval($form['disp_topics']) > 75) $form['disp_topics'] = 75;
			if ($form['disp_posts'] != '' && intval($form['disp_posts']) < 3) $form['disp_posts'] = 3;
			if ($form['disp_posts'] != '' && intval($form['disp_posts']) > 75) $form['disp_posts'] = 75;

			if (!isset($form['show_smilies']) || $form['show_smilies'] != '1') $form['show_smilies'] = '0';
			if (!isset($form['show_img']) || $form['show_img'] != '1') $form['show_img'] = '0';
			if (!isset($form['show_img_sig']) || $form['show_img_sig'] != '1') $form['show_img_sig'] = '0';
			if (!isset($form['show_avatars']) || $form['show_avatars'] != '1') $form['show_avatars'] = '0';
			if (!isset($form['show_sig']) || $form['show_sig'] != '1') $form['show_sig'] = '0';

			// Make sure we got a valid style string
			if (isset($form['style']))
			{
				$form['style'] = preg_replace('#[\.\\\/]#', '', $form['style']);
				if (!file_exists(FORUM_ROOT.'style/'.$form['style'].'/'.$form['style'].'.php'))
					message($lang_common['Bad request']);
			}
			break;
		}

		case 'signature':
		{
			if ($forum_config['o_signatures'] == '0')
				message($lang_profile['Signatures disabled']);

			($hook = get_hook('pf_change_details_signature_validation')) ? eval($hook) : null;

			// Clean up signature from POST
			$form['signature'] = forum_linebreaks(trim($_POST['signature']));

			// Validate signature
			if (forum_strlen($form['signature']) > $forum_config['p_sig_length'])
				$errors[] = sprintf($lang_profile['Sig too long'], $forum_config['p_sig_length']);
			if (substr_count($form['signature'], "\n") > ($forum_config['p_sig_lines'] - 1))
				$errors[] = sprintf($lang_profile['Sig too many lines'], $forum_config['p_sig_lines']);

			if ($form['signature'] != '' && $forum_config['p_sig_all_caps'] == '0' && strtoupper($form['signature']) == $form['signature'] && !$forum_user['is_admmod'])
				$form['signature'] = ucwords(strtolower($form['signature']));

			// Validate BBCode syntax
			if ($forum_config['p_sig_bbcode'] == '1' && strpos($form['signature'], '[') !== false && strpos($form['signature'], ']') !== false)
			{
				require FORUM_ROOT.'include/parser.php';
				$form['signature'] = preparse_bbcode($form['signature'], $errors, true);
			}

			break;
		}

		case 'avatar':
		{
			if ($forum_config['o_avatars'] == '0')
				message($lang_profile['Avatars disabled']);

			($hook = get_hook('pf_change_details_avatar_validation')) ? eval($hook) : null;

			if (!isset($_FILES['req_file']))
			{
				$errors[] = $lang_profile['No file'];
				break;
			}
			else
				$uploaded_file = $_FILES['req_file'];

			// Make sure the upload went smooth
			if (isset($uploaded_file['error']) && empty($errors))
			{
				switch ($uploaded_file['error'])
				{
					case 1:	// UPLOAD_ERR_INI_SIZE
					case 2:	// UPLOAD_ERR_FORM_SIZE
						$errors[] = $lang_profile['Too large ini'];
						break;

					case 3:	// UPLOAD_ERR_PARTIAL
						$errors[] = $lang_profile['Partial upload'];
						break;

					case 4:	// UPLOAD_ERR_NO_FILE
						$errors[] = $lang_profile['No file'];
						break;

					case 6:	// UPLOAD_ERR_NO_TMP_DIR
						$errors[] = $lang_profile['No tmp directory'];
						break;

					default:
						// No error occured, but was something actually uploaded?
						if ($uploaded_file['size'] == 0)
							$errors[] = $lang_profile['No file'];
						break;
				}
			}

			if (is_uploaded_file($uploaded_file['tmp_name']) && empty($errors))
			{
				$allowed_types = array('image/gif', 'image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png');
				if (!in_array($uploaded_file['type'], $allowed_types))
					$errors[] = $lang_profile['Bad type'];
				else
				{
					// Make sure the file isn't too big
					if ($uploaded_file['size'] > $forum_config['o_avatars_size'])
						$errors[] = sprintf($lang_profile['Too large'], $forum_config['o_avatars_size']);
				}

				if (empty($errors))
				{
					// Determine type
					$extensions = null;
					if ($uploaded_file['type'] == 'image/gif')
						$extensions = array('.gif', '.jpg', '.png');
					else if ($uploaded_file['type'] == 'image/jpeg' || $uploaded_file['type'] == 'image/pjpeg')
						$extensions = array('.jpg', '.gif', '.png');
					else
						$extensions = array('.png', '.gif', '.jpg');

					// Move the file to the avatar directory. We do this before checking the width/height to circumvent open_basedir restrictions.
					if (!@move_uploaded_file($uploaded_file['tmp_name'], $forum_config['o_avatars_dir'].'/'.$id.'.tmp'))
						$errors[] = sprintf($lang_profile['Move failed'], '<a href="mailto:'.$forum_config['o_admin_email'].'">'.$forum_config['o_admin_email'].'</a>');

					if (empty($errors))
					{
						// Now check the width/height
						list($width, $height, $type,) = getimagesize($forum_config['o_avatars_dir'].'/'.$id.'.tmp');
						if (empty($width) || empty($height) || $width > $forum_config['o_avatars_width'] || $height > $forum_config['o_avatars_height'])
						{
							@unlink($forum_config['o_avatars_dir'].'/'.$id.'.tmp');
							$errors[] = sprintf($lang_profile['Too wide or high'], $forum_config['o_avatars_width'], $forum_config['o_avatars_height']);
						}
						else if ($type == 1 && $uploaded_file['type'] != 'image/gif')	// Prevent dodgy uploads
						{
							@unlink($forum_config['o_avatars_dir'].'/'.$id.'.tmp');
							$errors[] = $lang_profile['Bad type'];
						}

						if (empty($errors))
						{
							// Delete any old avatars
							if (file_exists($forum_config['o_avatars_dir'].'/'.$id.$extensions[0]))
								@unlink($forum_config['o_avatars_dir'].'/'.$id.$extensions[0]);
							if (file_exists($forum_config['o_avatars_dir'].'/'.$id.$extensions[1]))
								@unlink($forum_config['o_avatars_dir'].'/'.$id.$extensions[1]);
							if (file_exists($forum_config['o_avatars_dir'].'/'.$id.$extensions[2]))
								@unlink($forum_config['o_avatars_dir'].'/'.$id.$extensions[2]);

							// Put the new avatar in its place
							@rename($forum_config['o_avatars_dir'].'/'.$id.'.tmp', $forum_config['o_avatars_dir'].'/'.$id.$extensions[0]);
							@chmod($forum_config['o_avatars_dir'].'/'.$id.$extensions[0], 0644);
						}
					}
				}
			}
			else if (empty($errors))
				$errors[] = $lang_profile['Unknown failure'];

			break;
		}

		default:
		{
			($hook = get_hook('pf_change_details_new_section_validation')) ? eval($hook) : null;
			break;
		}
	}

	// All sections apart from avatar potentially affect the database
	if (($section != 'avatar') && empty($errors))
	{
		($hook = get_hook('pf_change_details_database_validation')) ? eval($hook) : null;

		// Singlequotes around non-empty values and NULL for empty values
		$temp = array();
		while (list($key, $input) = @each($form))
		{
			$value = ($input !== '') ? '\''.$forum_db->escape($input).'\'' : 'NULL';

			$temp[] = $key.'='.$value;
		}

		// Make sure we have something to update
		if (empty($temp))
			message($lang_common['Bad request']);

		// Run the update
		$query = array(
			'UPDATE'	=> 'users',
			'SET'		=> implode(',', $temp),
			'WHERE'		=> 'id='.$id
		);

		($hook = get_hook('pf_qr_update_user')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// If we changed the username we have to update some stuff
		if ($username_updated)
		{
			($hook = get_hook('pf_change_details_username_changed')) ? eval($hook) : null;

			$query = array(
				'UPDATE'	=> 'posts',
				'SET'		=> 'poster=\''.$forum_db->escape($form['username']).'\'',
				'WHERE'		=> 'poster_id='.$id
			);

			($hook = get_hook('pf_qr_update_username1')) ? eval($hook) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);

			$query = array(
				'UPDATE'	=> 'topics',
				'SET'		=> 'poster=\''.$forum_db->escape($form['username']).'\'',
				'WHERE'		=> 'poster=\''.$forum_db->escape($old_username).'\''
			);

			($hook = get_hook('pf_qr_update_username2')) ? eval($hook) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);

			$query = array(
				'UPDATE'	=> 'topics',
				'SET'		=> 'last_poster=\''.$forum_db->escape($form['username']).'\'',
				'WHERE'		=> 'last_poster=\''.$forum_db->escape($old_username).'\''
			);

			($hook = get_hook('pf_qr_update_username3')) ? eval($hook) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);

			$query = array(
				'UPDATE'	=> 'forums',
				'SET'		=> 'last_poster=\''.$forum_db->escape($form['username']).'\'',
				'WHERE'		=> 'last_poster=\''.$forum_db->escape($old_username).'\''
			);

			($hook = get_hook('pf_qr_update_username4')) ? eval($hook) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);

			$query = array(
				'UPDATE'	=> 'online',
				'SET'		=> 'ident=\''.$forum_db->escape($form['username']).'\'',
				'WHERE'		=> 'ident=\''.$forum_db->escape($old_username).'\''
			);

			($hook = get_hook('pf_qr_update_username5')) ? eval($hook) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);

			// If the user is a moderator or an administrator we have to update the moderator lists and bans cache
			if ($user['g_id'] == FORUM_ADMIN || $user['g_moderator'] == '1')
			{
				$query = array(
					'SELECT'	=> 'f.id, f.moderators',
					'FROM'		=> 'forums AS f'
				);

				($hook = get_hook('pf_qr_get_all_forum_mods2')) ? eval($hook) : null;
				$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
				while ($cur_forum = $forum_db->fetch_assoc($result))
				{
					$cur_moderators = ($cur_forum['moderators'] != '') ? unserialize($cur_forum['moderators']) : array();

					if (in_array($id, $cur_moderators))
					{
						unset($cur_moderators[$old_username]);
						$cur_moderators[$form['username']] = $id;
						ksort($cur_moderators);

						$query = array(
							'UPDATE'	=> 'forums',
							'SET'		=> 'moderators=\''.$forum_db->escape(serialize($cur_moderators)).'\'',
							'WHERE'		=> 'id='.$cur_forum['id']
						);

						($hook = get_hook('pf_qr_update_forum_moderators2')) ? eval($hook) : null;
						$forum_db->query_build($query) or error(__FILE__, __LINE__);
					}
				}

				// Regenerate the bans cache
				require_once FORUM_ROOT.'include/cache.php';
				generate_bans_cache();
			}
		}

		redirect(forum_link($forum_url['profile_'.$section], $id), $lang_profile['Profile redirect']);
	}
}

($hook = get_hook('pf_new_action')) ? eval($hook) : null;


if ($user['signature'] != '')
{
	require_once FORUM_ROOT.'include/parser.php';
	$parsed_signature = parse_signature($user['signature']);
}


// View or edit?
if ($forum_user['id'] != $id &&
	$forum_user['g_id'] != FORUM_ADMIN &&
	($forum_user['g_moderator'] != '1' || $forum_user['g_mod_edit_users'] == '0' || $user['g_id'] == FORUM_ADMIN || $user['g_moderator'] == '1'))
{
	($hook = get_hook('pf_view_details_selected')) ? eval($hook) : null;

	// Setup user identification
	$forum_page['user_ident'] = array();

	if ($forum_config['o_avatars'] == '1')
	{
		if ($forum_page['img_size'] = @getimagesize($forum_config['o_avatars_dir'].'/'.$id.'.gif'))
			$forum_page['avatar_format'] = 'gif';
		else if ($forum_page['img_size'] = @getimagesize($forum_config['o_avatars_dir'].'/'.$id.'.jpg'))
			$forum_page['avatar_format'] = 'jpg';
		else if ($forum_page['img_size'] = @getimagesize($forum_config['o_avatars_dir'].'/'.$id.'.png'))
			$forum_page['avatar_format'] = 'png';
		else
			$forum_page['avatar_format'] = '';

		if ($forum_page['avatar_format'] != '')
			$forum_page['user_ident']['avatar'] = '<img src="'.$base_url.'/'.$forum_config['o_avatars_dir'].'/'.$id.'.'.$forum_page['avatar_format'].'" '.$forum_page['img_size'][3].' alt="'.$lang_profile['Avatar'].'" />';
	}

	$forum_page['user_ident']['username'] = '<strong class="username'.(($user['realname'] =='') ? ' fn nickname' : ' nickname').'">'.forum_htmlencode($user['username']).'</strong>';

	// Setup user information
	$forum_page['user_info'] = array(
		'<li class="title"><span><strong>'.$lang_profile['Title'].'</strong> '.get_title($user).'</span></li>',
		'<li><span><strong>'.$lang_profile['From'].'</strong> '.(($user['location'] !='') ? forum_htmlencode(($forum_config['o_censoring'] == '1') ? censor_words($user['location']) : $user['location']) : $lang_profile['Unknown']).'</span></li>',
		'<li><span><strong>'.$lang_profile['Registered'].'</strong> '.format_time($user['registered'], true).'</span></li>'
	);

	if ($forum_config['o_show_post_count'] == '1' || $forum_user['is_admmod'])
		$forum_page['user_info']['posts'] = '<li><span><strong>'.$lang_profile['Posts'].'</strong> '.$user['num_posts'].'</span></li>';


	// Setup user actions
	$forum_page['user_actions'] = array();

	if ($user['email_setting'] != '2' && !$forum_user['is_guest'] && $forum_user['g_send_email'] == '1')
		$forum_page['user_actions']['email'] =  '<li><a href="'.forum_link($forum_url['email'], $id).'">'.$lang_common['Send forum e-mail'].'</a></li>';

	if ($forum_user['g_search'] == '1')
	{
		$forum_page['user_actions']['search_posts'] = '<li><a href="'.forum_link($forum_url['search_user_posts'], $id).'">'.$lang_profile['Show posts'].'</a></li>';
		$forum_page['user_actions']['search_topics'] = '<li><a href="'.forum_link($forum_url['search_user_topics'], $id).'">'.$lang_profile['Show topics'].'</a></li>';
	}

	// Setup user data
	$forum_page['user_data'] = array(
		'<li><span'.(($user['realname'] !='') ? ' class="fn"' : '').'><strong>'.$lang_profile['Realname'].'</strong> '.(($user['realname'] !='') ? forum_htmlencode(($forum_config['o_censoring'] == '1') ? censor_words($user['realname']) : $user['realname']) : $lang_profile['Unknown']).'</span></li>',
		'<li><span><strong>'.$lang_profile['Last post'].'</strong> '.format_time($user['last_post']).'</span></li>'
	);

	if ($user['email_setting'] == '0' && !$forum_user['is_guest'] && $forum_user['g_send_email'] == '1')
		$forum_page['user_data']['email'] = '<li><strong>'.$lang_profile['E-mail'].'</strong> <span><a href="mailto:'.$user['email'].'" class="email">'.($forum_config['o_censoring'] == '1' ? censor_words($user['email']) : $user['email']).'</a></span></li>';
	else
		$forum_page['user_data']['email'] = '<li><strong>'.$lang_profile['E-mail'].'</strong> <span>'.$lang_profile['Private'].'</span></li>';

	if ($user['url'] != '')
	{
		if ($forum_config['o_censoring'] == '1')
			$user['url'] = censor_words($user['url']);

		$user['url'] = forum_htmlencode($user['url']);
		$forum_page['url'] = '<a href="'.$user['url'].'" class="external url" rel="me">'.$user['url'].'</a>';
	}
	else
		$forum_page['url'] = $lang_profile['Unknown'];

	array_push(
		$forum_page['user_data'],
		'<li><span><strong>'.$lang_profile['Website'].'</strong> '.$forum_page['url'].'</span></li>',
		'<li><span><strong>'.$lang_profile['Jabber'].'</strong> '.(($user['jabber'] !='') ? forum_htmlencode(($forum_config['o_censoring'] == '1') ? censor_words($user['jabber']) : $user['jabber']) : $lang_profile['Unknown']).'</span></li>',
		'<li><span><strong>'.$lang_profile['ICQ'].'</strong> '.(($user['icq'] !='') ? forum_htmlencode($user['icq']) : $lang_profile['Unknown']).'</span></li>',
		'<li><span><strong>'.$lang_profile['MSN'].'</strong> '.(($user['msn'] !='') ? forum_htmlencode(($forum_config['o_censoring'] == '1') ? censor_words($user['msn']) : $user['msn']) : $lang_profile['Unknown']).'</span></li>',
		'<li><span><strong>'.$lang_profile['AOL IM'].'</strong> '.(($user['aim'] !='') ? forum_htmlencode(($forum_config['o_censoring'] == '1') ? censor_words($user['aim']) : $user['aim']) : $lang_profile['Unknown']).'</span></li>',
		'<li><span><strong>'.$lang_profile['Yahoo'].'</strong> '.(($user['yahoo'] !='') ? forum_htmlencode(($forum_config['o_censoring'] == '1') ? censor_words($user['yahoo']) : $user['yahoo']) : $lang_profile['Unknown']).'</span></li>'
	);

	if ($forum_config['o_signatures'] == '1' && isset($parsed_signature))
		$forum_page['sig_demo'] = $parsed_signature;

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		sprintf($lang_profile['Users profile'], $user['username'])
	);

	($hook = get_hook('pf_view_details_pre_header_load')) ? eval($hook) : null;

	define('FORUM_ALLOW_INDEX', 1);
	define('FORUM_PAGE', 'profile');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

?>
<div id="brd-main" class="main">

	<h1><span><?php echo end($forum_page['crumbs']) ?></span></h1>

	<div class="main-head">
		<h2><span><?php printf($lang_profile['About settings'], forum_htmlencode($user['username'])) ?></span></h2>
	</div>

	<div class="main-content frm">
		<div class="profile vcard">
			<h3><?php echo $lang_profile['User information'] ?></h3>
			<div class="user">
				<h4 class="user-ident"><?php echo implode(' ', $forum_page['user_ident']) ?></h4>
				<ul class="user-info">
					<?php echo implode("\n\t\t\t\t\t\t", $forum_page['user_info'])."\n" ?>
				</ul>
			</div>
<?php ($hook = get_hook('pf_view_details_pre_user_data')) ? eval($hook) : null; ?>
			<ul class="user-data">
				<?php echo implode("\n\t\t\t\t\t\t", $forum_page['user_data'])."\n" ?>
			</ul>
			<h3><?php echo $lang_profile['User actions'] ?></h3>
<?php if (!empty($forum_page['user_actions'])): ?>			<ul class="user-actions">
				<?php echo implode("\n\t\t\t\t", $forum_page['user_actions'])."\n" ?>
			</ul>
<?php endif; if (isset($forum_page['sig_demo'])): ?>			<h3><?php echo $lang_profile['Preview signature'] ?></h3>
			<div class="sig-demo">
				<?php echo $forum_page['sig_demo']."\n" ?>
			</div>
<?php endif; ?>		</div>
<?php ($hook = get_hook('pf_view_details_end')) ? eval($hook) : null; ?>
	</div>

</div>
<?php

	$tpl_temp = trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}


else
{
	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		sprintf($lang_profile['Users profile'], $user['username'])
	);

	if ($section == 'about')
	{
		// Setup user identification
		$forum_page['user_ident'] = array();

		if ($forum_config['o_avatars'] == '1')
		{
			if ($forum_page['img_size'] = @getimagesize($forum_config['o_avatars_dir'].'/'.$id.'.gif'))
				$forum_page['avatar_format'] = 'gif';
			else if ($forum_page['img_size'] = @getimagesize($forum_config['o_avatars_dir'].'/'.$id.'.jpg'))
				$forum_page['avatar_format'] = 'jpg';
			else if ($forum_page['img_size'] = @getimagesize($forum_config['o_avatars_dir'].'/'.$id.'.png'))
				$forum_page['avatar_format'] = 'png';
			else
				$forum_page['avatar_format'] = '';

			if ($forum_page['avatar_format'] != '')
				$forum_page['user_ident']['avatar'] = '<img src="'.$base_url.'/'.$forum_config['o_avatars_dir'].'/'.$id.'.'.$forum_page['avatar_format'].'" '.$forum_page['img_size'][3].' alt="'.$lang_profile['Avatar'].'" />';
		}

		$forum_page['user_ident']['username'] = '<strong class="username'.(($user['realname'] =='') ? ' fn nickname' :  ' nickname').'">'.forum_htmlencode($user['username']).'</strong>';

		// Setup user information
		$forum_page['user_info'] = array(
			'<li class="title"><span><strong>'.$lang_profile['Title'].'</strong> '.get_title($user).'</span></li>',
			'<li><span><strong>'.$lang_profile['From'].'</strong> '.(($user['location'] !='') ? forum_htmlencode(($forum_config['o_censoring'] == '1') ? censor_words($user['location']) : $user['location']) : $lang_profile['Unknown']).'</span></li>',
			'<li><span><strong>'.$lang_profile['Registered'].'</strong> '.format_time($user['registered'], true).'</span></li>'
 		);

 		if ($forum_config['o_show_post_count'] == '1' || $forum_user['is_admmod'])
			$forum_page['user_info']['posts'] = '<li><span><strong>'.$lang_profile['Posts'].'</strong> '.$user['num_posts'].'</span></li>';

		if ($forum_user['is_admmod'])
			$forum_page['user_info']['ip']= '<li><span><strong>'.$lang_profile['IP'].'</strong> <a href="'.forum_link($forum_url['get_host'], forum_htmlencode($user['registration_ip'])).'">'.forum_htmlencode($user['registration_ip']).'</a></span></li>';

		if ($forum_user['is_admmod'] && $user['admin_note'] != '')
				$forum_page['user_info']['note'] = '<li><span><strong>'.$lang_profile['Note'].'</strong> '.forum_htmlencode($user['admin_note']).'</span></li>';


		// Setup user actions
		$forum_page['user_actions'] = array();

		if ($forum_user['id'] == $id || $forum_user['g_id'] == FORUM_ADMIN || ($forum_user['g_moderator'] == '1' && $forum_user['g_mod_change_passwords'] == '1'))
			$forum_page['user_actions']['change_password'] = '<li><a href="'.forum_link($forum_url['change_password'], $id).'">'.$lang_profile['Change password'].'</a></li>';

		if (!$forum_user['is_admmod'] && $forum_config['o_regs_verify'] == '1')
			$forum_page['user_actions']['change_email'] = '<li><a href="'.forum_link($forum_url['change_email'], $id).'">'.$lang_profile['Change e-mail'].'</a></li>';

		if (($user['email_setting'] != '2' || $forum_user['is_admmod']) && $forum_user['g_send_email'] == '1')
			$forum_page['user_actions']['email'] = '<li><a href="'.forum_link($forum_url['email'], $id).'">'.$lang_profile['Send forum e-mail'].'</a></li>';

		if ($forum_user['g_search'] == '1' || $forum_user['is_admmod'])
		{
			$forum_page['user_actions']['search_posts'] = '<li><a href="'.forum_link($forum_url['search_user_posts'], $id).'">'.$lang_profile['Show posts'].'</a></li>';
			$forum_page['user_actions']['search_topics'] = '<li><a href="'.forum_link($forum_url['search_user_topics'], $id).'">'.$lang_profile['Show topics'].'</a></li>';
		}


		// Setup user data
		$forum_page['user_data'] = array(
			'<li><strong>'.$lang_profile['Realname'].'</strong> <span'.(($user['realname'] !='') ? ' class="fn"' : '').'>'.(($user['realname'] !='') ? forum_htmlencode(($forum_config['o_censoring'] == '1') ? censor_words($user['realname']) : $user['realname']) : $lang_profile['Unknown']).'</span></li>',
			'<li><strong>'.$lang_profile['Last post'].'</strong> <span>'.format_time($user['last_post']).'</span></li>'
		);

		if (($user['email_setting'] == '0' && !$forum_user['is_guest']) && $forum_user['g_send_email'] == '1')
			$forum_page['user_data']['email'] = '<li><strong>'.$lang_profile['E-mail'].'</strong> <span><a href="mailto:'.$user['email'].'" class="email">'.($forum_config['o_censoring'] == '1' ? censor_words($user['email']) : $user['email']).'</a></span></li>';
		else
			$forum_page['user_data']['email'] = '<li><strong>'.$lang_profile['E-mail'].'</strong> <span>'.$lang_profile['Private'].'</span></li>';

		if ($user['url'] != '')
		{
			$user['url'] = forum_htmlencode($user['url']);

			if ($forum_config['o_censoring'] == '1')
				$user['url'] = censor_words($user['url']);

			$forum_page['url'] = '<a href="'.$user['url'].'" class="external url" rel="me">'.$user['url'].'</a>';
		}
		else
			$forum_page['url'] = $lang_profile['Unknown'];

		array_push(
			$forum_page['user_data'],
			'<li><span><strong>'.$lang_profile['Website'].'</strong> '.$forum_page['url'].'</span></li>',
			'<li><span><strong>'.$lang_profile['Jabber'].'</strong> '.(($user['jabber'] !='') ? forum_htmlencode(($forum_config['o_censoring'] == '1') ? censor_words($user['jabber']) : $user['jabber']) : $lang_profile['Unknown']).'</span></li>',
			'<li><span><strong>'.$lang_profile['ICQ'].'</strong> '.(($user['icq'] !='') ? forum_htmlencode($user['icq']) : $lang_profile['Unknown']).'</span></li>',
			'<li><span><strong>'.$lang_profile['MSN'].'</strong> '.(($user['msn'] !='') ? forum_htmlencode(($forum_config['o_censoring'] == '1') ? censor_words($user['msn']) : $user['msn']) : $lang_profile['Unknown']).'</span></li>',
			'<li><span><strong>'.$lang_profile['AOL IM'].'</strong> '.(($user['aim'] !='') ? forum_htmlencode(($forum_config['o_censoring'] == '1') ? censor_words($user['aim']) : $user['aim']) : $lang_profile['Unknown']).'</span></li>',
			'<li><span><strong>'.$lang_profile['Yahoo'].'</strong> '.(($user['yahoo'] !='') ? forum_htmlencode(($forum_config['o_censoring'] == '1') ? censor_words($user['yahoo']) : $user['yahoo']) : $lang_profile['Unknown']).'</span></li>'
		);

		if ($forum_config['o_signatures'] == '1' && isset($parsed_signature))
			$forum_page['sig_demo'] = $parsed_signature;

		($hook = get_hook('pf_change_details_about_pre_header_load')) ? eval($hook) : null;

		define('FORUM_PAGE', 'profile-about');
		require FORUM_ROOT.'header.php';

		// START SUBST - <!-- forum_main -->
		ob_start();

?>
<div id="brd-main" class="main sectioned">

	<h1><span><?php echo end($forum_page['crumbs']) ?></span></h1>

<?php generate_profile_menu(); ?>

	<div class="main-head">
		<h2><span><?php printf($lang_profile['About settings'], forum_htmlencode($user['username'])) ?></span></h2>
	</div>

	<div class="main-content frm">
<?php if ($id == $forum_user['id']): ?>		<div class="frm-info">
			<p><?php echo $lang_profile['Profile welcome'] ?></p>
		</div>
<?php endif; ($hook = get_hook('pf_change_details_about_pre_user_info')) ? eval($hook) : null; ?>
		<div class="profile vcard">
			<h3><?php echo $lang_profile['Preview profile'] ?></h3>
			<div class="user">
				<h4 class="user-ident"><?php echo implode(' ', $forum_page['user_ident']) ?></h4>
				<ul class="user-info">
					<?php echo implode("\n\t\t\t\t\t", $forum_page['user_info'])."\n" ?>
				</ul>
			</div>
			<ul class="user-data">
				<?php echo implode("\n\t\t\t\t", $forum_page['user_data'])."\n" ?>
			</ul>
			<h3><?php echo $lang_profile['User actions'] ?></h3>
<?php if (!empty($forum_page['user_actions'])): ?>			<ul class="user-actions">
				<?php echo implode("\n\t\t\t\t", $forum_page['user_actions'])."\n" ?>
			</ul>
<?php endif; if (isset($forum_page['sig_demo'])): ?>			<h3><?php echo $lang_profile['Preview signature'] ?></h3>
			<div class="sig-demo">
				<?php echo $forum_page['sig_demo']."\n" ?>
			</div>
<?php endif; ?>		</div>
<?php ($hook = get_hook('pf_change_details_about_end')) ? eval($hook) : null; ?>
	</div>
</div>
<?php

		$tpl_temp = trim(ob_get_contents());
		$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
		ob_end_clean();
		// END SUBST - <!-- forum_main -->

		require FORUM_ROOT.'footer.php';
	}

	else if ($section == 'identity')
	{
		// Setup the form
		$forum_page['set_count'] = $forum_page['fld_count'] = 0;
		$forum_page['form_action'] = forum_link($forum_url['profile_identity'], $id);

		$forum_page['hidden_fields']['form_sent'] = '<input type="hidden" name="form_sent" value="1" />';
		if ($forum_user['is_admmod'])
			$forum_page['hidden_fields']['csrf_token'] = '<input type="hidden" name="csrf_token" value="'.generate_form_token($forum_page['form_action']).'" />';
		if ($forum_user['is_admmod'] && ($forum_user['g_id'] == FORUM_ADMIN || $forum_user['g_mod_rename_users'] == '1'))
			$forum_page['hidden_fields']['old_username'] = '<input type="hidden" name="old_username" value="'.forum_htmlencode($user['username']).'" />';

		// Does the form have required fields
		$forum_page['has_required'] = ((($forum_user['is_admmod'] && ($forum_user['g_id'] == FORUM_ADMIN || $forum_user['g_mod_rename_users'] == '1')) || ($forum_user['is_admmod'] || $forum_config['o_regs_verify'] != '1')) ? true : false);

		($hook = get_hook('pf_change_details_identity_pre_header_load')) ? eval($hook) : null;

		define('FORUM_PAGE', 'profile-identity');
		require FORUM_ROOT.'header.php';

		// START SUBST - <!-- forum_main -->
		ob_start();

?>
<div id="brd-main" class="main sectioned">

	<h1><span><?php echo end($forum_page['crumbs']) ?></span></h1>

<?php generate_profile_menu(); ?>

	<div class="main-head">
		<h2><span><span><?php echo $lang_profile['Section identity'] ?>:</span> <?php printf($lang_profile['Identity settings'], strtolower($lang_profile['Section identity'])) ?></span></h2>
	</div>

	<div class="main-content frm">
<?php

	// If there were any errors, show them
	if (!empty($errors))
	{
		$forum_page['errors'] = array();
		while (list(, $cur_error) = each($errors))
			$forum_page['errors'][] = '<li class="warn"><span>'.$cur_error.'</span></li>';

		($hook = get_hook('pf_pre_change_details_identity_errors')) ? eval($hook) : null;

?>
		<div class="frm-error">
			<h3 class="warn"><?php echo $lang_profile['Profile update errors'] ?></h3>
			<ul>
				<?php echo implode("\n\t\t\t\t\t", $forum_page['errors'])."\n" ?>
			</ul>
		</div>
<?php

	}

if ($forum_page['has_required']): ?>		<div id="req-msg" class="frm-warn">
			<p class="important"><?php printf($lang_common['Required warn'], '<em class="req-text">'.$lang_common['Required'].'</em>') ?></p>
		</div>
<?php endif; ?>		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
			</div>
<?php if ($forum_page['has_required']): ?>			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_common['Required information'] ?></strong></legend>
<?php if ($forum_user['is_admmod'] && ($forum_user['g_id'] == FORUM_ADMIN || $forum_user['g_mod_rename_users'] == '1')): ?>
				<div class="frm-fld text required">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Username'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_username" value="<?php echo(isset($_POST['req_username']) ? forum_htmlencode($_POST['req_username']) : forum_htmlencode($user['username'])) ?>" size="35" maxlength="25" /></span><br />
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
						<span class="fld-help"><?php echo $lang_profile['Username help'] ?></span>
					</label>
				</div>
<?php endif; if ($forum_user['is_admmod'] || $forum_config['o_regs_verify'] != '1'): ?>				<div class="frm-fld text required">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['E-mail'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_email" value="<?php echo(isset($_POST['req_username']) ? forum_htmlencode($_POST['req_email']) : $user['email']) ?>" size="35" maxlength="80" /></span>
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
					</label>
				</div>
<?php endif; ($hook = get_hook('pf_change_details_identity_req_info_end')) ? eval($hook) : null; ?>			</fieldset>
<?php endif; ($hook = get_hook('pf_change_details_identity_post_req_info_fieldset')) ? eval($hook) : null; ?>			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_profile['Personal legend'] ?></strong></legend>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Realname'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[realname]" value="<?php echo(isset($form['realname']) ? forum_htmlencode($form['realname']) : forum_htmlencode($user['realname'])) ?>" size="35" maxlength="40" /></span>
					</label>
				</div>
<?php if ($forum_user['g_set_title'] == '1'): ?>				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Title'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="title" value="<?php echo(isset($_POST['title']) ? forum_htmlencode($_POST['title']) : forum_htmlencode($user['title'])) ?>" size="35" maxlength="50" /></span><br />
						<span class="fld-help"><?php echo $lang_profile['Leave blank'] ?></span>
					</label>
				</div>
<?php endif; ?>				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Location'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[location]" value="<?php echo((isset($form['location']) ? forum_htmlencode($form['location']) : forum_htmlencode($user['location']))) ?>" size="35" maxlength="30" /></span>
					</label>
				</div>
<?php if ($forum_user['g_id'] == FORUM_ADMIN): ?>				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Edit count'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="num_posts" value="<?php echo $user['num_posts'] ?>" size="8" maxlength="8" /></span>
					</label>
				</div>
<?php endif; if ($forum_user['is_admmod']): ?>				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Admin note'] ?></span><br />
						<span class="fld-input"><input id="fld<?php echo $forum_page['fld_count'] ?>" type="text" name="admin_note" value="<?php echo(isset($_POST['admin_note']) ? forum_htmlencode($_POST['admin_note']) : forum_htmlencode($user['admin_note'])) ?>" size="35" maxlength="30" /></span>
					</label>
				</div>
<?php endif; ($hook = get_hook('pf_change_details_identity_personal_end')) ? eval($hook) : null; ?>			</fieldset>
<?php ($hook = get_hook('pf_change_details_identity_post_personal_fieldset')) ? eval($hook) : null; ?>			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_profile['Contact legend'] ?></strong></legend>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Website'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[url]" value="<?php echo(isset($form['url']) ? forum_htmlencode($form['url']) : forum_htmlencode($user['url'])) ?>" size="50" maxlength="80" /></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Jabber'] ?></span><br />
						<span class="fld-input"><input id="fld<?php echo $forum_page['fld_count'] ?>" type="text" name="form[jabber]" value="<?php echo(isset($form['jabber']) ? forum_htmlencode($form['jabber']) : forum_htmlencode($user['jabber'])) ?>" size="40" maxlength="80" /></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['ICQ'] ?></span><br />
						<span class="fld-input"><input id="fld<?php echo $forum_page['fld_count'] ?>" type="text" name="form[icq]" value="<?php echo(isset($form['icq']) ? forum_htmlencode($form['icq']) : $user['icq']) ?>" size="12" maxlength="12" /></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['MSN'] ?></span><br />
						<span class="fld-input"><input id="fld<?php echo $forum_page['fld_count'] ?>" type="text" name="form[msn]" value="<?php echo(isset($form['msn']) ? forum_htmlencode($form['msn']) : forum_htmlencode($user['msn'])) ?>" size="40" maxlength="80" /></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['AOL IM'] ?></span><br />
						<span class="fld-input"><input id="fld<?php echo $forum_page['fld_count'] ?>" type="text" name="form[aim]" value="<?php echo(isset($form['aim']) ? forum_htmlencode($form['aim']) : forum_htmlencode($user['aim'])) ?>" size="20" maxlength="30" /></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Yahoo'] ?></span><br />
						<span class="fld-input"><input id="fld<?php echo $forum_page['fld_count'] ?>" type="text" name="form[yahoo]" value="<?php echo(isset($form['yahoo']) ? forum_htmlencode($form['yahoo']) : forum_htmlencode($user['yahoo'])) ?>" size="20" maxlength="30" /></span>
					</label>
				</div>
<?php ($hook = get_hook('pf_change_details_identity_contact_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php ($hook = get_hook('pf_change_details_identity_post_contact_fieldset')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="update" value="<?php echo $lang_profile['Update profile'] ?>" /> <?php echo $lang_profile['Instructions'] ?></span>
			</div>
		</form>
	</div>

</div>
<?php

		$tpl_temp = trim(ob_get_contents());
		$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
		ob_end_clean();
		// END SUBST - <!-- forum_main -->

		require FORUM_ROOT.'footer.php';
	}

	else if ($section == 'settings')
	{
		$forum_page['styles'] = array();
		$forum_page['d'] = dir(FORUM_ROOT.'style');
		while (($forum_page['entry'] = $forum_page['d']->read()) !== false)
		{
			if ($forum_page['entry'] != '.' && $forum_page['entry'] != '..' && is_dir(FORUM_ROOT.'style/'.$forum_page['entry']) && file_exists(FORUM_ROOT.'style/'.$forum_page['entry'].'/'.$forum_page['entry'].'.css'))
				$forum_page['styles'][] = $forum_page['entry'];
		}
		$forum_page['d']->close();

		// Setup the form
		$forum_page['set_count'] = $forum_page['fld_count'] = 0;
		$forum_page['form_action'] = forum_link($forum_url['profile_settings'], $id);

		$forum_page['hidden_fields']['form_sent'] = '<input type="hidden" name="form_sent" value="1" />';
		if ($forum_user['is_admmod'])
			$forum_page['hidden_fields']['csrf_token'] = '<input type="hidden" name="csrf_token" value="'.generate_form_token($forum_page['form_action']).'" />';

		($hook = get_hook('pf_change_details_settings_pre_header_load')) ? eval($hook) : null;

		define('FORUM_PAGE', 'profile-settings');
		require FORUM_ROOT.'header.php';

		// START SUBST - <!-- forum_main -->
		ob_start();

?>
<div id="brd-main" class="main sectioned">

	<h1 class="brd main-title"><span><?php echo end($forum_page['crumbs']) ?></span></h1>

<?php generate_profile_menu(); ?>

	<div class="main-head">
		<h2><span><span><?php echo $lang_profile['Section settings'] ?>:</span> <?php printf($lang_profile['Settings settings'], strtolower($lang_profile['Section settings'])) ?></span></h2>
	</div>

	<div class="main-content frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action']  ?>">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
			</div>
<?php ($hook = get_hook('pf_change_details_settings_pre_local_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_profile['Local legend'] ?></strong></legend>
<?php

		$forum_page['languages'] = array();
		$forum_page['d'] = dir(FORUM_ROOT.'lang');
		while (($forum_page['entry'] = $forum_page['d']->read()) !== false)
		{
			if ($forum_page['entry'] != '.' && $forum_page['entry'] != '..' && is_dir(FORUM_ROOT.'lang/'.$forum_page['entry']) && file_exists(FORUM_ROOT.'lang/'.$forum_page['entry'].'/common.php'))
				$forum_page['languages'][] = $forum_page['entry'];
		}
		$forum_page['d']->close();

		// Only display the language selection box if there's more than one language available
		if (count($forum_page['languages']) > 1)
		{
			natcasesort($forum_page['languages']);

?>
				<div class="frm-fld select">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Language'] ?></span><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="form[language]">
<?php

			while (list(, $temp) = @each($forum_page['languages']))
			{
				if ($forum_user['language'] == $temp)
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

?>
				<div class="frm-fld select">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Timezone'] ?></span><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="form[timezone]">
							<option value="-12"<?php if ($user['timezone'] == -12) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-12:00'] ?></option>
							<option value="-11"<?php if ($user['timezone'] == -11) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-11:00'] ?></option>
							<option value="-10"<?php if ($user['timezone'] == -10) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-10:00'] ?></option>
							<option value="-9.5"<?php if ($user['timezone'] == -9.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-09:30'] ?></option>
							<option value="-9"<?php if ($user['timezone'] == -9) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-09:00'] ?></option>
							<option value="-8"<?php if ($user['timezone'] == -8) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-08:00'] ?></option>
							<option value="-7"<?php if ($user['timezone'] == -7) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-07:00'] ?></option>
							<option value="-6"<?php if ($user['timezone'] == -6) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-06:00'] ?></option>
							<option value="-5"<?php if ($user['timezone'] == -5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-05:00'] ?></option>
							<option value="-4"<?php if ($user['timezone'] == -4) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-04:00'] ?></option>
							<option value="-3.5"<?php if ($user['timezone'] == -3.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-03:30'] ?></option>
							<option value="-3"<?php if ($user['timezone'] == -3) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-03:00'] ?></option>
							<option value="-2"<?php if ($user['timezone'] == -2) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-02:00'] ?></option>
							<option value="-1"<?php if ($user['timezone'] == -1) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-01:00'] ?></option>
							<option value="0"<?php if ($user['timezone'] == 0) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC'] ?></option>
							<option value="1"<?php if ($user['timezone'] == 1) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+01:00'] ?></option>
							<option value="2"<?php if ($user['timezone'] == 2) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+02:00'] ?></option>
							<option value="3"<?php if ($user['timezone'] == 3) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+03:00'] ?></option>
							<option value="3.5"<?php if ($user['timezone'] == 3.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+03:30'] ?></option>
							<option value="4"<?php if ($user['timezone'] == 4) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+04:00'] ?></option>
							<option value="4.5"<?php if ($user['timezone'] == 4.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+04:30'] ?></option>
							<option value="5"<?php if ($user['timezone'] == 5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+05:00'] ?></option>
							<option value="5.5"<?php if ($user['timezone'] == 5.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+05:30'] ?></option>
							<option value="5.75"<?php if ($user['timezone'] == 5.75) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+05:45'] ?></option>
							<option value="6"<?php if ($user['timezone'] == 6) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+06:00'] ?></option>
							<option value="6.5"<?php if ($user['timezone'] == 6.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+06:30'] ?></option>
							<option value="7"<?php if ($user['timezone'] == 7) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+07:00'] ?></option>
							<option value="8"<?php if ($user['timezone'] == 8) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+08:00'] ?></option>
							<option value="8.75"<?php if ($user['timezone'] == 8.75) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+08:45'] ?></option>
							<option value="9"<?php if ($user['timezone'] == 9) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+09:00'] ?></option>
							<option value="9.5"<?php if ($user['timezone'] == 9.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+09:30'] ?></option>
							<option value="10"<?php if ($user['timezone'] == 10) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+10:00'] ?></option>
							<option value="10.5"<?php if ($user['timezone'] == 10.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+10:30'] ?></option>
							<option value="11"<?php if ($user['timezone'] == 11) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+11:00'] ?></option>
							<option value="11.5"<?php if ($user['timezone'] == 11.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+11:30'] ?></option>
							<option value="12"<?php if ($user['timezone'] == 12) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+12:00'] ?></option>
							<option value="12.75"<?php if ($user['timezone'] == 12.75) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+12:45'] ?></option>
							<option value="13"<?php if ($user['timezone'] == 13) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+13:00'] ?></option>
							<option value="14"<?php if ($user['timezone'] == 14) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+14:00'] ?></option>
						</select></span><br />
						<span class="fld-extra"><?php echo $lang_profile['Timezone info'] ?></span>
					</label>
				</div>
				<div class="checkbox radbox">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span class="fld-label"><?php echo $lang_profile['Adjust for DST'] ?></span><br /><input type="checkbox" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[dst]" value="1" <?php if ($user['dst'] == 1) echo ' checked="checked"' ?> /> <?php echo $lang_profile['DST label'] ?></label>
				</div>
				<div class="frm-fld select">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Time format'] ?></span><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="form[time_format]">
<?php

		foreach (array_unique($forum_time_formats) as $key => $time_format)
		{
			echo "\t\t\t\t\t\t".'<option value="'.$key.'"';
			if ($user['time_format'] == $key)
				echo ' selected="selected"';
			echo '>'. gmdate($time_format);
			if ($key == 0)
				echo ' ('.$lang_profile['Default'].')';
			echo "</option>\n";
		}

?>
						</select></span>
					</label>
				</div>
				<div class="frm-fld select">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Date format'] ?></span><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="form[date_format]">
<?php

		foreach (array_unique($forum_date_formats) as $key => $date_format)
		{
			echo "\t\t\t\t\t\t\t".'<option value="'.$key.'"';
			if ($user['date_format'] == $key)
				echo ' selected="selected"';
			echo '>'. gmdate($date_format);
			if ($key == 0)
				echo ' ('.$lang_profile['Default'].')';
			echo "</option>\n";
		}

?>
						</select></span>
					</label>
				</div>
<?php ($hook = get_hook('pf_change_details_settings_local_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php ($hook = get_hook('pf_change_details_settings_pre_display_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_profile['Display settings'] ?></strong></legend>
<?php

		// Only display the style selection box if there's more than one style available
		if (count($forum_page['styles']) == 1)
			echo "\t\t\t\t".'<input type="hidden" name="form[style]" value="'.$forum_page['styles'][0].'" />'."\n";
		else if (count($forum_page['styles']) > 1)
		{
			natcasesort($forum_page['styles']);

?>
				<div class="frm-fld select">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Styles'] ?></span><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="form[style]">
<?php

			while (list(, $temp) = @each($forum_page['styles']))
			{
				if ($user['style'] == $temp)
					echo "\t\t\t\t\t\t\t".'<option value="'.$temp.'" selected="selected">'.str_replace('_', ' ', $temp).'</option>'."\n";
				else
					echo "\t\t\t\t\t\t\t".'<option value="'.$temp.'">'.str_replace('_', ' ', $temp).'</option>'."\n";
			}

?>
						</select></span>
					</label>
				</div>
<?php

		}

?>
				<fieldset class="frm-group">
					<legend><span><?php echo $lang_profile['Image display'] ?></span></legend>
					<div class="radbox"><label for="fld<?php echo ++$forum_page['fld_count'] ?>"><input type="checkbox" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[show_smilies]" value="1"<?php if ($user['show_smilies'] == '1') echo ' checked="checked"' ?> /> <?php echo $lang_profile['Show smilies'] ?></label></div>
<?php if ($forum_config['o_avatars'] == '1'): ?>					<div class="radbox"><label for="fld<?php echo ++$forum_page['fld_count'] ?>"><input type="checkbox" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[show_avatars]" value="1"<?php if ($user['show_avatars'] == '1') echo ' checked="checked"' ?> /> <?php echo $lang_profile['Show avatars'] ?></label></div>
<?php endif; if ($forum_config['p_message_img_tag'] == '1'): ?>					<div class="radbox"><label for="fld<?php echo ++$forum_page['fld_count'] ?>"><input type="checkbox" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[show_img]" value="1"<?php if ($user['show_img'] == '1') echo ' checked="checked"' ?> /> <?php echo $lang_profile['Show images'] ?></label></div>
<?php endif; if ($forum_config['o_signatures'] == '1' && $forum_config['p_sig_img_tag'] == '1'): ?>					<div class="radbox"><label for="fld<?php echo ++$forum_page['fld_count'] ?>"><input type="checkbox" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[show_img_sig]" value="1"<?php if ($user['show_img_sig'] == '1') echo ' checked="checked"' ?> /> <?php echo $lang_profile['Show images sigs'] ?></label></div>
<?php endif; ?>				</fieldset>
<?php if ($forum_config['o_signatures'] == '1'): ?>				<fieldset class="frm-group">
					<legend><span><?php echo $lang_profile['Signature display'] ?></span></legend>
					<div class="radbox"><label for="fld<?php echo ++$forum_page['fld_count'] ?>"><input type="checkbox" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[show_sig]" value="1"<?php if ($user['show_sig'] == '1') echo ' checked="checked"' ?> /> <?php echo $lang_profile['Show sigs'] ?></label></div>
				</fieldset>
<?php ($hook = get_hook('pf_change_details_settings_display_end')) ? eval($hook) : null; ?>
<?php endif; ?>			</fieldset>
<?php ($hook = get_hook('pf_change_details_settings_pre_pagination_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_profile['Pagination settings'] ?></strong></legend>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Topics per page'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[disp_topics]" value="<?php echo $user['disp_topics'] ?>" size="6" maxlength="3" /></span>
						<span class="fld-extra"><?php echo $lang_profile['Leave blank'] ?></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Posts per page'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[disp_posts]" value="<?php echo $user['disp_posts'] ?>" size="6" maxlength="3" /></span>
						<span class="fld-extra"><?php echo $lang_profile['Leave blank'] ?></span>
					</label>
				</div>
<?php ($hook = get_hook('pf_change_details_settings_pagination_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php ($hook = get_hook('pf_change_details_settings_pre_other_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_profile['Other settings'] ?></strong></legend>
				<fieldset class="frm-group">
					<legend><span><?php echo $lang_profile['E-mail settings'] ?></span></legend>
					<div class="radbox"><label for="fld<?php echo ++$forum_page['fld_count'] ?>"><input type="radio" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[email_setting]" value="0"<?php if ($user['email_setting'] == '0') echo ' checked="checked"' ?> /> <?php echo $lang_profile['E-mail setting 1'] ?></label></div>
					<div class="radbox"><label for="fld<?php echo ++$forum_page['fld_count'] ?>"><input type="radio" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[email_setting]" value="1"<?php if ($user['email_setting'] == '1') echo ' checked="checked"' ?> /> <?php echo $lang_profile['E-mail setting 2'] ?></label></div>
					<div class="radbox"><label for="fld<?php echo ++$forum_page['fld_count'] ?>"><input type="radio" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[email_setting]" value="2"<?php if ($user['email_setting'] == '2') echo ' checked="checked"' ?> /> <?php echo $lang_profile['E-mail setting 3'] ?></label></div>
				</fieldset>
<?php if ($forum_config['o_subscriptions'] == '1'): ?>				<fieldset class="frm-group">
					<legend><span><?php echo $lang_profile['Subscription settings'] ?></span></legend>
						<div class="radbox"><label for="fld<?php echo ++$forum_page['fld_count'] ?>"><input type="checkbox" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[notify_with_post]" value="1"<?php if ($user['notify_with_post'] == '1') echo ' checked="checked"' ?> /> <?php echo $lang_profile['Notify full'] ?></label></div>
						<div class="radbox"><label for="fld<?php echo ++$forum_page['fld_count'] ?>"><input type="checkbox" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[auto_notify]" value="1"<?php if ($user['auto_notify'] == '1') echo ' checked="checked"' ?> /> <?php echo $lang_profile['Subscribe by default'] ?></label></div>
				</fieldset>
<?php endif; ?>				<div class="checkbox radbox">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span class="fld-label"><?php echo $lang_profile['Persistent login'] ?></span><br /><input type="checkbox" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[save_pass]" value="1"<?php if ($user['save_pass'] == '1') echo ' checked="checked"' ?> /> <?php echo $lang_profile['Save user/pass'] ?></label>
				</div>
<?php ($hook = get_hook('pf_change_details_settings_other_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php ($hook = get_hook('pf_change_details_settings_post_other_fieldset')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="update" value="<?php echo $lang_profile['Update profile'] ?>" /> <?php echo $lang_profile['Instructions'] ?></span>
			</div>
		</form>
	</div>

</div>
<?php

		$tpl_temp = trim(ob_get_contents());
		$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
		ob_end_clean();
		// END SUBST - <!-- forum_main -->

		require FORUM_ROOT.'footer.php';
	}

	else if ($section == 'signature' && $forum_config['o_signatures'] == '1')
	{
		$forum_page['sig_info'][] = '<li>'.$lang_profile['Signature info'].'</li>';

		if ($user['signature'] != '')
			$forum_page['sig_demo'] = $parsed_signature;

		// Setup the form
		$forum_page['set_count'] = $forum_page['fld_count'] = 0;
		$forum_page['form_action'] = forum_link($forum_url['profile_signature'], $id);

		$forum_page['hidden_fields']['form_sent'] = '<input type="hidden" name="form_sent" value="1" />';
		if ($forum_user['is_admmod'])
			$forum_page['hidden_fields']['form_sent'] = '<input type="hidden" name="csrf_token" value="'.generate_form_token($forum_page['form_action']).'" />';

		// Setup help
		$forum_page['main_head_options'] = array();
		if ($forum_config['p_sig_bbcode'] == '1')
			$forum_page['main_head_options']['bbcode'] = '<a class="exthelp" href="'.forum_link($forum_url['help'], 'bbcode').'" title="'.sprintf($lang_common['Help page'], $lang_common['BBCode']).'"><span>'.$lang_common['BBCode'].'</span></a>';
		if ($forum_config['p_sig_img_tag'] == '1')
			$forum_page['main_head_options']['img'] = '<a class="exthelp" href="'.forum_link($forum_url['help'], 'img').'" title="'.sprintf($lang_common['Help page'], $lang_common['Images']).'"><span>'.$lang_common['Images'].'</span></a>';
		if ($forum_config['o_smilies_sig'] == '1')
			$forum_page['main_head_options']['smilies'] = '<a class="exthelp" href="'.forum_link($forum_url['help'], 'smilies').'" title="'.sprintf($lang_common['Help page'], $lang_common['Smilies']).'"><span>'.$lang_common['Smilies'].'</span></a>';

		($hook = get_hook('pf_change_details_signature_pre_header_load')) ? eval($hook) : null;

		define('FORUM_PAGE', 'profile-signature');
		require FORUM_ROOT.'header.php';

		// START SUBST - <!-- forum_main -->
		ob_start();

?>
<div id="brd-main" class="main sectioned">

	<h1><span><?php echo end($forum_page['crumbs']) ?></span></h1>

<?php generate_profile_menu(); ?>

	<div class="main-head">
		<h2><span><span><?php echo $lang_profile['Section signature'] ?>:</span> <?php printf($lang_profile['Sig settings'], strtolower($lang_profile['Section signature'])) ?></span></h2>
<?php if (!empty($forum_page['main_head_options'])) echo "\t\t\t".'<p class="main-options">'.sprintf($lang_common['You may use'], implode(' ', $forum_page['main_head_options'])).'</p>'."\n" ?>
	</div>

	<div class="main-content frm">
<?php

	// If there were any errors, show them
	if (!empty($errors))
	{
		$forum_page['errors'] = array();
		while (list(, $cur_error) = each($errors))
			$forum_page['errors'][] = '<li class="warn"><span>'.$cur_error.'</span></li>';

		($hook = get_hook('pf_pre_change_details_signature_errors')) ? eval($hook) : null;

?>
		<div class="frm-error">
			<h3 class="warn"><?php echo $lang_profile['Profile update errors'] ?></h3>
			<ul>
				<?php echo implode("\n\t\t\t\t\t", $forum_page['errors'])."\n" ?>
			</ul>
		</div>
<?php

	}

?>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
			</div>
<?php ($hook = get_hook('pf_change_details_signature_pre_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_profile['Signature'] ?></strong></legend>
<?php ($hook = get_hook('pf_change_details_signature_fieldset_start')) ? eval($hook) : null; ?>
				<div class="frm-fld text textarea">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Compose signature'] ?></span><br />
						<span class="fld-input">
							<textarea id="fld<?php echo $forum_page['fld_count'] ?>" name="signature" rows="4" cols="65"><?php echo(isset($_POST['signature']) ? forum_htmlencode($_POST['signature']) : forum_htmlencode($user['signature'])) ?></textarea></span><br />
						<span class="fld-help"><?php printf($lang_profile['Sig max size'], $forum_config['p_sig_length'], $forum_config['p_sig_lines']) ?></span>
					</label>
				</div>
			</fieldset>
<?php if (isset($forum_page['sig_demo'])): ?>			<div class="sig-demo">
				<?php echo $forum_page['sig_demo']."\n" ?>
			</div>
<?php endif; ($hook = get_hook('pf_change_details_signature_pre_buttons')) ? eval($hook) : null; ?>			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="update" value="<?php echo $lang_profile['Update profile'] ?>" /> <?php echo $lang_profile['Instructions'] ?></span>
			</div>
		</form>
	</div>

</div>
<?php

		$tpl_temp = trim(ob_get_contents());
		$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
		ob_end_clean();
		// END SUBST - <!-- forum_main -->

		require FORUM_ROOT.'footer.php';
	}

	else if ($section == 'avatar' && $forum_config['o_avatars'] == '1')
	{
		if ($forum_page['img_size'] = @getimagesize($forum_config['o_avatars_dir'].'/'.$id.'.gif'))
			$forum_page['avatar_format'] = 'gif';
		else if ($forum_page['img_size'] = @getimagesize($forum_config['o_avatars_dir'].'/'.$id.'.jpg'))
			$forum_page['avatar_format'] = 'jpg';
		else if ($forum_page['img_size'] = @getimagesize($forum_config['o_avatars_dir'].'/'.$id.'.png'))
			$forum_page['avatar_format'] = 'png';
		else
			$forum_page['avatar_format'] = '';

		// Setup the form
		$forum_page['set_count'] = $forum_page['fld_count'] = 0;
		$forum_page['form_action'] = forum_link($forum_url['profile_avatar'], $id);

		$forum_page['hidden_fields'] = array(
			'<input type="hidden" name="form_sent" value="1" />',
			'<input type="hidden" name="MAX_FILE_SIZE" value="'.$forum_config['o_avatars_size'].'" />'
		);
		if ($forum_user['is_admmod'])
			$forum_page['hidden_fields']['csrf_token'] = '<input type="hidden" name="csrf_token" value="'.generate_form_token($forum_page['form_action']).'" />';

		// Setup form information
		$forum_page['frm_info'] = array();

		if ($forum_page['avatar_format'] != '')
		{
			$forum_page['frm_info']['avatar_change'] = '<li><span>'.$lang_profile['Avatar info change'].'</span></li>';
			$forum_page['frm_info']['avatar_info'] = '<li><span>'.$lang_profile['Avatar info type'].'</span></li>';
			$forum_page['frm_info']['avatar_size'] = '<li><span>'.sprintf($lang_profile['Avatar info size'], $forum_config['o_avatars_width'], $forum_config['o_avatars_height'], $forum_config['o_avatars_size'], ceil($forum_config['o_avatars_size'] / 1024)).'</span></li>';
			$forum_page['avatar_demo'] = '<img src="'.$base_url.'/'.$forum_config['o_avatars_dir'].'/'.$id.'.'.$forum_page['avatar_format'].'" '.$forum_page['img_size'][3].' alt="'.$lang_profile['Avatar'].'" />';
		}
		else
		{
			$forum_page['frm_info']['avatar_none'] = '<li><span>'.$lang_profile['Avatar info none'].'</span></li>';
			$forum_page['frm_info']['avatar_size'] = '<li><span>'.sprintf($lang_profile['Avatar info size'], $forum_config['o_avatars_width'], $forum_config['o_avatars_height'], $forum_config['o_avatars_size'], ceil($forum_config['o_avatars_size'] / 1024)).'</span></li>';
		}

		($hook = get_hook('pf_change_details_avatar_pre_header_load')) ? eval($hook) : null;

		define('FORUM_PAGE', 'profile-avatar');
		require FORUM_ROOT.'header.php';

		// START SUBST - <!-- forum_main -->
		ob_start();

?>
<div id="brd-main" class="main sectioned">

	<h1><span><?php echo end($forum_page['crumbs']) ?></span></h1>

<?php generate_profile_menu(); ?>

	<div class="main-head">
		<h2><span><span><?php echo $lang_profile['Section avatar'] ?>:</span> <?php printf($lang_profile['Avatar settings'], strtolower($lang_profile['Section avatar'])) ?></span></h2>
	</div>

	<div class="main-content frm">
		<div class="frm-info<?php echo ($forum_page['avatar_format'] != '') ? ' av-preview' : '' ?>">
			<?php echo (isset($forum_page['avatar_demo'])) ? $forum_page['avatar_demo']."\n" : ''."\n" ?>
			<ul>
				<?php echo implode("\n\t\t\t\t", $forum_page['frm_info'])."\n\t\t\t" ?>
			</ul>
		</div>
<?php

	// If there were any errors, show them
	if (!empty($errors))
	{
		$forum_page['errors'] = array();
		while (list(, $cur_error) = each($errors))
			$forum_page['errors'][] = '<li class="warn"><span>'.$cur_error.'</span></li>';

		($hook = get_hook('pf_pre_change_details_avatar_errors')) ? eval($hook) : null;

?>
		<div class="frm-error">
			<h3 class="warn"><?php echo $lang_profile['Profile update errors'] ?></h3>
			<ul>
				<?php echo implode("\n\t\t\t\t\t", $forum_page['errors'])."\n" ?>
			</ul>
		</div>
<?php

	}

?>
		<div id="req-msg" class="frm-warn">
			<p class="important"><?php echo $lang_common['No upload warn'] ?></p>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>" enctype="multipart/form-data">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
			</div>
<?php if ($forum_page['avatar_format'] != ''): ?>				<p class="frm-fld link"><span class="fld-label"><a href="<?php echo forum_link($forum_url['delete_avatar'], array($id, generate_form_token('delete_avatar'.$id.$forum_user['id']))) ?>"><?php echo $lang_profile['Delete avatar'] ?></a>:</span> <span class="fm-input"><?php echo $lang_profile['Avatar info remove'] ?></span></p>
<?php endif; ?>			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_profile['Avatar'] ?></strong></legend>
<?php ($hook = get_hook('pf_change_details_avatar_fieldset_start')) ? eval($hook) : null; ?>
				<div class="frm-fld text required">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['Upload avatar file'] ?></span><br />
						<span class="fld-input"><input id="fld<?php echo $forum_page['fld_count'] ?>" name="req_file" type="file" size="40" /></span>
						<span class="fld-help"><?php echo $lang_profile['Avatar upload help'] ?></span>
					</label>
				</div>
<?php ($hook = get_hook('pf_change_details_avatar_fieldset_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php ($hook = get_hook('pf_change_details_avatar_post_fieldset')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="update" value="<?php echo $lang_profile['Update profile'] ?>" /> <?php echo $lang_profile['Instructions'] ?></span>
			</div>
		</form>
	</div>

</div>
<?php

		$tpl_temp = trim(ob_get_contents());
		$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
		ob_end_clean();
		// END SUBST - <!-- forum_main -->

		require FORUM_ROOT.'footer.php';
	}

	else if ($section == 'admin')
	{
		if ($forum_user['g_id'] != FORUM_ADMIN && ($forum_user['g_moderator'] != '1' || $forum_user['g_mod_ban_users'] == '0'))
			message($lang_common['Bad request']);

		$forum_page['user_actions'] = array();
		$forum_page['user_management'] = array();

		if ($forum_user['g_moderator'] == '1')
		{
			$forum_page['user_actions']['ban'] = '<li class="frm-fld link"><span class="fld-label"><a href="'.forum_link($forum_url['admin_bans']).'?add_ban='.$id.'">'.$lang_profile['Ban user'].'</a>:</span> <span class="fld-input">'.$lang_profile['Ban user info'].'</span></li>';
			$forum_page['user_management']['ban'] = '<li><span>'.$lang_profile['Manage ban'].'</span></li>';
		}
		else if ($forum_user['g_moderator'] != '1' && $user['g_id'] != FORUM_ADMIN )
		{
			$forum_page['user_actions']['ban'] = '<li class="frm-fld link"><span class="fld-label"><a href="'.forum_link($forum_url['admin_bans']).'?add_ban='.$id.'">'.$lang_profile['Ban user'].'</a>:</span> <span class="fld-input">'.$lang_profile['Ban user info'].'</span></li>';
			$forum_page['user_actions']['delete'] = '<li class="frm-fld link"><span class="fld-label"><a href="'.forum_link($forum_url['delete_user'], $id).'">'.$lang_profile['Delete user'].'</a>:</span> <span class="fld-input">'.$lang_profile['Delete user info'].'</span></li>';
			$forum_page['user_management']['ban'] = '<li><span>'.$lang_profile['Manage ban'].'</span></li>';
			$forum_page['user_management']['delete'] = '<li><span>'.$lang_profile['Manage delete'].'</span></li>';
		}

		if ($forum_user['g_moderator'] != '1' &&  $forum_user['id'] != $id && $user['g_id'] == FORUM_ADMIN )
			$forum_page['user_management']['groups'] = '<li><span>'.$lang_profile['Manage groups'].'</span></li>';

		// Setup form
		$forum_page['fld_count'] = $forum_page['set_count'] = 0;
		$forum_page['form_action'] = forum_link($forum_url['profile_admin'], $id);

		$forum_page['hidden_fields']['form_sent'] = '<input type="hidden" name="form_sent" value="1" />';
		if ($forum_user['is_admmod'])
			$forum_page['hidden_fields']['csrf_token'] = '<input type="hidden" name="csrf_token" value="'.generate_form_token($forum_page['form_action']).'" />';

		($hook = get_hook('pf_change_details_admin_pre_header_load')) ? eval($hook) : null;

		define('FORUM_PAGE', 'profile-admin');
		require FORUM_ROOT.'header.php';

		// START SUBST - <!-- forum_main -->
		ob_start();

?>
<div id="brd-main" class="main sectioned">

	<h1><span><?php echo end($forum_page['crumbs']) ?></span></h1>

<?php generate_profile_menu(); ?>

	<div class="main-head">
		<h2><span><span><?php echo $lang_profile['Section admin'] ?>:</span> <?php printf($lang_profile['Admin settings'], strtolower($lang_profile['Section admin'])) ?></span></h2>
	</div>

	<div class="main-content frm">
<?php if (!empty($forum_page['user_management'])): ?>		<div class="frm-info">
			<h3><?php echo $lang_profile['User management'] ?></h3>
			<ul>
				<?php echo implode("\n\t\t\t\t", $forum_page['user_management'])."\n" ?>
			</ul>
		</div>
<?php endif; ?>		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
			</div>
<?php if (!empty($forum_page['user_actions'])): ?>			<ul class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<?php echo implode("\n\t\t\t\t", $forum_page['user_actions'])."\n" ?>
			</ul>
<?php endif;

		($hook = get_hook('pf_change_details_admin_pre_group_membership')) ? eval($hook) : null;

		if ($forum_user['g_moderator'] != '1')
		{
			if ($forum_user['id'] != $id)
			{

?>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_profile['Group membership'] ?></strong></legend>
				<div class="frm-fld select">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_profile['User group'] ?></span><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="group_id">
<?php

				$query = array(
					'SELECT'	=> 'g.g_id, g.g_title',
					'FROM'		=> 'groups AS g',
					'WHERE'		=> 'g.g_id!='.FORUM_GUEST,
					'ORDER BY'	=> 'g.g_title'
				);

				($hook = get_hook('pf_qr_get_groups')) ? eval($hook) : null;
				$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
				while ($cur_group = $forum_db->fetch_assoc($result))
				{
					if ($cur_group['g_id'] == $user['g_id'] || ($cur_group['g_id'] == $forum_config['o_default_user_group'] && $user['g_id'] == ''))
						echo "\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.forum_htmlencode($cur_group['g_title']).'</option>'."\n";
					else
						echo "\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.forum_htmlencode($cur_group['g_title']).'</option>'."\n";
				}

?>
						</select></span>
					</label>
					<input type="submit" name="update_group_membership" value="<?php echo $lang_profile['Save'] ?>" />
				</div>
			</fieldset>
<?php if ($user['g_id'] != FORUM_ADMIN && $user['g_moderator'] != '1'): ?>			<div class="frm-buttons">
				<span><?php echo $lang_profile['Instructions'] ?></span>
			</div>
<?php endif;

			}

			if ($user['g_id'] == FORUM_ADMIN || $user['g_moderator'] == '1')
			{
				$forum_page['set_count'] = 0;

?>
			<div class="frm-info">
				<h3><?php echo $lang_profile['Moderator assignment'] ?></h3>
				<ul>
					<li><span><?php echo $lang_profile['Moderator in info'] ?></span></li>
					<li><span><?php echo $lang_profile['Moderator in info 2'] ?></span></li>
				</ul>
			</div>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
<?php

				$query = array(
					'SELECT'	=> 'c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.moderators',
					'FROM'		=> 'categories AS c',
					'JOINS'		=> array(
						array(
							'INNER JOIN'	=> 'forums AS f',
							'ON'			=> 'c.id=f.cat_id'
						)
					),
					'WHERE'		=> 'f.redirect_url IS NULL',
					'ORDER BY'	=> 'c.disp_position, c.id, f.disp_position'
				);

				($hook = get_hook('pf_qr_get_cats_and_forums')) ? eval($hook) : null;
				$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

				$cur_category = 0;
				while ($cur_forum = $forum_db->fetch_assoc($result))
				{
					if ($cur_forum['cid'] != $cur_category)	// A new category since last iteration?
					{
						if ($cur_category)
							echo "\n\t\t\t\t\t".'</fieldset>'."\n";

						echo "\t\t\t\t".'<fieldset class="frm-group">'."\n\t\t\t\t\t".'<legend><span>'.$cur_forum['cat_name'].':</span></legend>'."\n";
						$cur_category = $cur_forum['cid'];
					}

					$moderators = ($cur_forum['moderators'] != '') ? unserialize($cur_forum['moderators']) : array();

					echo "\t\t\t\t\t".'<div class="radbox"><label for="fld'.(++$forum_page['fld_count']).'"><input type="checkbox" id="fld'.$forum_page['fld_count'].'" name="moderator_in['.$cur_forum['fid'].']" value="1"'.((in_array($id, $moderators)) ? ' checked="checked"' : '').' /> '.forum_htmlencode($cur_forum['forum_name']).'</label></div>'."\n";
				}

?>
				</fieldset>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="update_forums" value="<?php echo $lang_profile['Update forums'] ?>" /> <?php echo $lang_profile['Instructions'] ?></span>
			</div>
<?php

			}
		}

		($hook = get_hook('pf_change_details_admin_form_end')) ? eval($hook) : null;

?>
		</form>
	</div>

</div>
<?php

		$tpl_temp = trim(ob_get_contents());
		$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
		ob_end_clean();
		// END SUBST - <!-- forum_main -->

		require FORUM_ROOT.'footer.php';
	}

	($hook = get_hook('pf_change_details_new_section')) ? eval($hook) : null;

	message($lang_common['Bad request']);
}
