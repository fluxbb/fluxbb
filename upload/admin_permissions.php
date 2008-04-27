<?php
/***********************************************************************

  Copyright (C) 2002-2005  Rickard Andersson (rickard@punbb.org)

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


// Tell header.php to use the admin template
define('PUN_ADMIN_CONSOLE', 1);

define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/common_admin.php';


if ($pun_user['g_id'] > PUN_ADMIN)
	message($lang_common['No permission']);


if (isset($_POST['form_sent']))
{
	confirm_referrer('admin_permissions.php');

	$form = array_map('intval', $_POST['form']);

	while (list($key, $input) = @each($form))
	{
		// Only update values that have changed
		if (array_key_exists('p_'.$key, $pun_config) && $pun_config['p_'.$key] != $input)
			$db->query('UPDATE '.$db->prefix.'config SET conf_value='.$input.' WHERE conf_name=\'p_'.$db->escape($key).'\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());
	}

	// Regenerate the config cache
	require_once PUN_ROOT.'include/cache.php';
	generate_config_cache();

	redirect('admin_permissions.php', 'Permissions updated. Redirecting &hellip;');
}


$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / Admin / Permissions';
require PUN_ROOT.'header.php';
generate_admin_menu('permissions');

?>
	<div class="blockform">
		<h2><span>Permissions</span></h2>
		<div class="box">
			<form method="post" action="admin_permissions.php">
				<p class="submittop"><input type="submit" name="save" value="Save changes" /></p>
				<div class="inform">
				<input type="hidden" name="form_sent" value="1" />
					<fieldset>
						<legend>Posting</legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row">BBCode</th>
									<td>
										<input type="radio" name="form[message_bbcode]" value="1"<?php if ($pun_config['p_message_bbcode'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[message_bbcode]" value="0"<?php if ($pun_config['p_message_bbcode'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Allow BBCode in posts (recommended).</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Image tag</th>
									<td>
										<input type="radio" name="form[message_img_tag]" value="1"<?php if ($pun_config['p_message_img_tag'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[message_img_tag]" value="0"<?php if ($pun_config['p_message_img_tag'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Allow the BBCode [img][/img] tag in posts.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">All caps message</th>
									<td>
										<input type="radio" name="form[message_all_caps]" value="1"<?php if ($pun_config['p_message_all_caps'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[message_all_caps]" value="0"<?php if ($pun_config['p_message_all_caps'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Allow a message to contain only capital letters.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">All caps subject</th>
									<td>
										<input type="radio" name="form[subject_all_caps]" value="1"<?php if ($pun_config['p_subject_all_caps'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[subject_all_caps]" value="0"<?php if ($pun_config['p_subject_all_caps'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Allow a subject to contain only capital letters.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Require guest e-mail</th>
									<td>
										<input type="radio" name="form[force_guest_email]" value="1"<?php if ($pun_config['p_force_guest_email'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[force_guest_email]" value="0"<?php if ($pun_config['p_force_guest_email'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Require guests to supply an e-mail address when posting.</span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend>Signatures</legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row">BBCodes in signatures</th>
									<td>
										<input type="radio" name="form[sig_bbcode]" value="1"<?php if ($pun_config['p_sig_bbcode'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[sig_bbcode]" value="0"<?php if ($pun_config['p_sig_bbcode'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Allow BBCodes in user signatures.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Image tag in signatures</th>
									<td>
										<input type="radio" name="form[sig_img_tag]" value="1"<?php if ($pun_config['p_sig_img_tag'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[sig_img_tag]" value="0"<?php if ($pun_config['p_sig_img_tag'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Allow the BBCode [img][/img] tag in user signatures (not recommended).</span>
									</td>
								</tr>
								<tr>
									<th scope="row">All caps signature</th>
									<td>
										<input type="radio" name="form[sig_all_caps]" value="1"<?php if ($pun_config['p_sig_all_caps'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[sig_all_caps]" value="0"<?php if ($pun_config['p_sig_all_caps'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Allow a signature to contain only capital letters.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Maximum signature length</th>
									<td>
										<input type="text" name="form[sig_length]" size="5" maxlength="5" value="<?php echo $pun_config['p_sig_length'] ?>" />
										<span>The maximum number of characters a user signature may contain.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Maximum signature lines</th>
									<td>
										<input type="text" name="form[sig_lines]" size="3" maxlength="3" value="<?php echo $pun_config['p_sig_lines'] ?>" />
										<span>The maximum number of lines a user signature may contain.</span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend>Moderators</legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row">Edit user profiles</th>
									<td>
										<input type="radio" name="form[mod_edit_users]" value="1"<?php if ($pun_config['p_mod_edit_users'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[mod_edit_users]" value="0"<?php if ($pun_config['p_mod_edit_users'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Allow moderators to edit user profiles.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Rename users</th>
									<td>
										<input type="radio" name="form[mod_rename_users]" value="1"<?php if ($pun_config['p_mod_rename_users'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[mod_rename_users]" value="0"<?php if ($pun_config['p_mod_rename_users'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Allow moderators to rename users. Other moderators and administrators are excluded.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Change user passwords</th>
									<td>
										<input type="radio" name="form[mod_change_passwords]" value="1"<?php if ($pun_config['p_mod_change_passwords'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[mod_change_passwords]" value="0"<?php if ($pun_config['p_mod_change_passwords'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Allow moderators to change user passwords. Other moderators and administrators are excluded.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Ban users</th>
									<td>
										<input type="radio" name="form[mod_ban_users]" value="1"<?php if ($pun_config['p_mod_ban_users'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[mod_ban_users]" value="0"<?php if ($pun_config['p_mod_ban_users'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Allow moderators to ban users (and edit/remove current bans).</span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend>Registration</legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row">Allow banned e-mail addresses</th>
									<td>
										<input type="radio" name="form[allow_banned_email]" value="1"<?php if ($pun_config['p_allow_banned_email'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[allow_banned_email]" value="0"<?php if ($pun_config['p_allow_banned_email'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Allow users to register with or change to a banned e-mail address/domain. If left at it's default setting (yes) this action will be allowed, but an alert e-mail will be sent to the mailing list (an effective way of detecting multiple registrations).</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Allow duplicate e-mail addresses</th>
									<td>
										<input type="radio" name="form[allow_dupe_email]" value="1"<?php if ($pun_config['p_allow_dupe_email'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[allow_dupe_email]" value="0"<?php if ($pun_config['p_allow_dupe_email'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Controls whether users should be allowed to register with an e-mail address that another user already has. If allowed, an alert e-mail will be sent to the mailing list if a duplicate is detected.</span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="save" value="Save changes" /></p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

require PUN_ROOT.'footer.php';
