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
	// Custom referrer check (so we can output a custom error message)
	if (!preg_match('#^'.preg_quote(str_replace('www.', '', $pun_config['o_base_url']).'/admin_options.php', '#').'#i', str_replace('www.', '', (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''))))
		message('Bad HTTP_REFERER. If you have moved these forums from one location to another or switched domains, you need to update the Base URL manually in the database (look for o_base_url in the config table) and then clear the cache by deleting all .php files in the /cache directory.');

	$form = array_map('trim', $_POST['form']);

	if ($form['board_title'] == '')
		message('You must enter a board title.');

	// Clean default_lang
	$form['default_lang'] = preg_replace('#[\.\\\/]#', '', $form['default_lang']);

	require PUN_ROOT.'include/email.php';

	$form['admin_email'] = strtolower($form['admin_email']);
	if (!is_valid_email($form['admin_email']))
		message('The admin e-mail address you entered is invalid.');

	$form['webmaster_email'] = strtolower($form['webmaster_email']);
	if (!is_valid_email($form['webmaster_email']))
		message('The webmaster e-mail address you entered is invalid.');

	if ($form['mailing_list'] != '')
		$form['mailing_list'] = strtolower(preg_replace('/[\s]/', '', $form['mailing_list']));

	// Make sure base_url doesn't end with a slash
	if (substr($form['base_url'], -1) == '/')
		$form['base_url'] = substr($form['base_url'], 0, -1);

	// Clean avatars_dir
	$form['avatars_dir'] = str_replace("\0", '', $form['avatars_dir']);

	// Make sure avatars_dir doesn't end with a slash
	if (substr($form['avatars_dir'], -1) == '/')
		$form['avatars_dir'] = substr($form['avatars_dir'], 0, -1);

	if ($form['additional_navlinks'] != '')
		$form['additional_navlinks'] = trim(pun_linebreaks($form['additional_navlinks']));

	if ($form['announcement_message'] != '')
		$form['announcement_message'] = pun_linebreaks($form['announcement_message']);
	else
	{
		$form['announcement_message'] = 'Enter your announcement here.';

		if ($form['announcement'] == '1')
			$form['announcement'] = '0';
	}

	if ($form['rules_message'] != '')
		$form['rules_message'] = pun_linebreaks($form['rules_message']);
	else
	{
		$form['rules_message'] = 'Enter your rules here.';

		if ($form['rules'] == '1')
			$form['rules'] = '0';
	}

	if ($form['maintenance_message'] != '')
		$form['maintenance_message'] = pun_linebreaks($form['maintenance_message']);
	else
	{
		$form['maintenance_message'] = 'The forums are temporarily down for maintenance. Please try again in a few minutes.\n\n/Administrator';

		if ($form['maintenance'] == '1')
			$form['maintenance'] = '0';
	}

	$form['timeout_visit'] = intval($form['timeout_visit']);
	$form['timeout_online'] = intval($form['timeout_online']);
	$form['redirect_delay'] = intval($form['redirect_delay']);
	$form['topic_review'] = intval($form['topic_review']);
	$form['disp_topics_default'] = intval($form['disp_topics_default']);
	$form['disp_posts_default'] = intval($form['disp_posts_default']);
	$form['indent_num_spaces'] = intval($form['indent_num_spaces']);
	$form['avatars_width'] = intval($form['avatars_width']);
	$form['avatars_height'] = intval($form['avatars_height']);
	$form['avatars_size'] = intval($form['avatars_size']);

	if ($form['timeout_online'] >= $form['timeout_visit'])
		message('The value of "Timeout online" must be smaller than the value of "Timeout visit".');

	while (list($key, $input) = @each($form))
	{
		// Only update values that have changed
		if (array_key_exists('o_'.$key, $pun_config) && $pun_config['o_'.$key] != $input)
		{
			if ($input != '' || is_int($input))
				$value = '\''.$db->escape($input).'\'';
			else
				$value = 'NULL';

			$db->query('UPDATE '.$db->prefix.'config SET conf_value='.$value.' WHERE conf_name=\'o_'.$db->escape($key).'\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());
		}
	}

	// Regenerate the config cache
	require_once PUN_ROOT.'include/cache.php';
	generate_config_cache();

	redirect('admin_options.php', 'Options updated. Redirecting &hellip;');
}


$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / Admin / Options';
$form_name = 'update_options';
require PUN_ROOT.'header.php';

generate_admin_menu('options');

?>
	<div class="blockform">
		<h2><span>Options</span></h2>
		<div class="box">
			<form method="post" action="admin_options.php?action=foo">
				<p class="submittop"><input type="submit" name="save" value="Save changes" /></p>
				<div class="inform">
				<input type="hidden" name="form_sent" value="1" />
					<fieldset>
						<legend>Essentials</legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row">Board title</th>
									<td>
										<input type="text" name="form[board_title]" size="50" maxlength="255" value="<?php echo pun_htmlspecialchars($pun_config['o_board_title']) ?>" />
										<span>The title of this bulletin board (shown at the top of every page). This field may <strong>not</strong> contain HTML.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Board description</th>
									<td>
										<input type="text" name="form[board_desc]" size="50" maxlength="255" value="<?php echo pun_htmlspecialchars($pun_config['o_board_desc']) ?>" />
										<span>A short description of this bulletin board (shown at the top of every page). This field may contain HTML.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Base URL</th>
									<td>
										<input type="text" name="form[base_url]" size="50" maxlength="100" value="<?php echo $pun_config['o_base_url'] ?>" />
										<span>The complete URL of the forum without trailing slash (i.e. http://www.mydomain.com/forums). This <strong>must</strong> be correct in order for all admin and moderator features to work. If you get "Bad referer" errors, it's probably incorrect.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Server timezone</th>
									<td>
										<select name="form[server_timezone]">
											<option value="-12"<?php if ($pun_config['o_server_timezone'] == -12 ) echo ' selected="selected"' ?>>-12</option>
											<option value="-11"<?php if ($pun_config['o_server_timezone'] == -11) echo ' selected="selected"' ?>>-11</option>
											<option value="-10"<?php if ($pun_config['o_server_timezone'] == -10) echo ' selected="selected"' ?>>-10</option>
											<option value="-9.5"<?php if ($pun_config['o_server_timezone'] == -9.5) echo ' selected="selected"' ?>>-09.5</option>
											<option value="-9"<?php if ($pun_config['o_server_timezone'] == -9 ) echo ' selected="selected"' ?>>-09</option>
											<option value="-8.5"<?php if ($pun_config['o_server_timezone'] == -8.5) echo ' selected="selected"' ?>>-08.5</option>
											<option value="-8"<?php if ($pun_config['o_server_timezone'] == -8 ) echo ' selected="selected"' ?>>-08 PST</option>
											<option value="-7"<?php if ($pun_config['o_server_timezone'] == -7 ) echo ' selected="selected"' ?>>-07 MST</option>
											<option value="-6"<?php if ($pun_config['o_server_timezone'] == -6 ) echo ' selected="selected"' ?>>-06 CST</option>
											<option value="-5"<?php if ($pun_config['o_server_timezone'] == -5 ) echo ' selected="selected"' ?>>-05 EST</option>
											<option value="-4"<?php if ($pun_config['o_server_timezone'] == -4 ) echo ' selected="selected"' ?>>-04 AST</option>
											<option value="-3.5"<?php if ($pun_config['o_server_timezone'] == -3.5) echo ' selected="selected"' ?>>-03.5</option>
											<option value="-3"<?php if ($pun_config['o_server_timezone'] == -3 ) echo ' selected="selected"' ?>>-03 ADT</option>
											<option value="-2"<?php if ($pun_config['o_server_timezone'] == -2 ) echo ' selected="selected"' ?>>-02</option>
											<option value="-1"<?php if ($pun_config['o_server_timezone'] == -1) echo ' selected="selected"' ?>>-01</option>
											<option value="0"<?php if ($pun_config['o_server_timezone'] == 0) echo ' selected="selected"' ?>>00 GMT</option>
											<option value="1"<?php if ($pun_config['o_server_timezone'] == 1) echo ' selected="selected"' ?>>+01 CET</option>
											<option value="2"<?php if ($pun_config['o_server_timezone'] == 2 ) echo ' selected="selected"' ?>>+02</option>
											<option value="3"<?php if ($pun_config['o_server_timezone'] == 3 ) echo ' selected="selected"' ?>>+03</option>
											<option value="3.5"<?php if ($pun_config['o_server_timezone'] == 3.5) echo ' selected="selected"' ?>>+03.5</option>
											<option value="4"<?php if ($pun_config['o_server_timezone'] == 4 ) echo ' selected="selected"' ?>>+04</option>
											<option value="4.5"<?php if ($pun_config['o_server_timezone'] == 4.5) echo ' selected="selected"' ?>>+04.5</option>
											<option value="5"<?php if ($pun_config['o_server_timezone'] == 5 ) echo ' selected="selected"' ?>>+05</option>
											<option value="5.5"<?php if ($pun_config['o_server_timezone'] == 5.5) echo ' selected="selected"' ?>>+05.5</option>
											<option value="6"<?php if ($pun_config['o_server_timezone'] == 6 ) echo ' selected="selected"' ?>>+06</option>
											<option value="6.5"<?php if ($pun_config['o_server_timezone'] == 6.5) echo ' selected="selected"' ?>>+06.5</option>
											<option value="7"<?php if ($pun_config['o_server_timezone'] == 7 ) echo ' selected="selected"' ?>>+07</option>
											<option value="8"<?php if ($pun_config['o_server_timezone'] == 8 ) echo ' selected="selected"' ?>>+08</option>
											<option value="9"<?php if ($pun_config['o_server_timezone'] == 9 ) echo ' selected="selected"' ?>>+09</option>
											<option value="9.5"<?php if ($pun_config['o_server_timezone'] == 9.5) echo ' selected="selected"' ?>>+09.5</option>
											<option value="10"<?php if ($pun_config['o_server_timezone'] == 10) echo ' selected="selected"' ?>>+10</option>
											<option value="10.5"<?php if ($pun_config['o_server_timezone'] == 10.5) echo ' selected="selected"' ?>>+10.5</option>
											<option value="11"<?php if ($pun_config['o_server_timezone'] == 11) echo ' selected="selected"' ?>>+11</option>
											<option value="11.5"<?php if ($pun_config['o_server_timezone'] == 11.5) echo ' selected="selected"' ?>>+11.5</option>
											<option value="12"<?php if ($pun_config['o_server_timezone'] == 12 ) echo ' selected="selected"' ?>>+12</option>
											<option value="13"<?php if ($pun_config['o_server_timezone'] == 13 ) echo ' selected="selected"' ?>>+13</option>
										</select>
										<span>The timezone of the server where PunBB is installed.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Default language</th>
									<td>
										<select name="form[default_lang]">
<?php

		$languages = array();
		$d = dir(PUN_ROOT.'lang');
		while (($entry = $d->read()) !== false)
		{
			if ($entry != '.' && $entry != '..' && is_dir(PUN_ROOT.'lang/'.$entry) && file_exists(PUN_ROOT.'lang/'.$entry.'/common.php'))
				$languages[] = $entry;
		}
		$d->close();

		@natsort($languages);

		while (list(, $temp) = @each($languages))
		{
			if ($pun_config['o_default_lang'] == $temp)
				echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$temp.'" selected="selected">'.$temp.'</option>'."\n";
			else
				echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$temp.'">'.$temp.'</option>'."\n";
		}

?>
										</select>
										<span>This is the default language style used if the visitor is a guest or a user that hasn't changed from the default in his/her profile. If you remove a language pack, this must be updated.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Default style</th>
									<td>
										<select name="form[default_style]">
<?php

		$styles = array();
		$d = dir(PUN_ROOT.'style');
		while (($entry = $d->read()) !== false)
		{
			if (substr($entry, strlen($entry)-4) == '.css')
				$styles[] = substr($entry, 0, strlen($entry)-4);
		}
		$d->close();

		@natsort($styles);

		while (list(, $temp) = @each($styles))
		{
			if ($pun_config['o_default_style'] == $temp)
				echo "\t\t\t\t\t\t\t\t\t".'<option value="'.$temp.'" selected="selected">'.str_replace('_', ' ', $temp).'</option>'."\n";
			else
				echo "\t\t\t\t\t\t\t\t\t".'<option value="'.$temp.'">'.str_replace('_', ' ', $temp).'</option>'."\n";
		}

?>
										</select>
										<span>This is the default style used for guests and users who haven't changed from the default in their profile.</span></td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend>Time and timeouts</legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row">Time format</th>
									<td>
										<input type="text" name="form[time_format]" size="25" maxlength="25" value="<?php echo pun_htmlspecialchars($pun_config['o_time_format']) ?>" />
										<span>[Current format: <?php echo date($pun_config['o_time_format']) ?>]&nbsp;See <a href="http://www.php.net/manual/en/function.date.php">here</a> for formatting options.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Date format</th>
									<td>
										<input type="text" name="form[date_format]" size="25" maxlength="25" value="<?php echo pun_htmlspecialchars($pun_config['o_date_format']) ?>" />
										<span>[Current format: <?php echo date($pun_config['o_date_format']) ?>]&nbsp;See <a href="http://www.php.net/manual/en/function.date.php">here</a> for formatting options.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Visit timeout</th>
									<td>
										<input type="text" name="form[timeout_visit]" size="5" maxlength="5" value="<?php echo $pun_config['o_timeout_visit'] ?>" />
										<span>Number of seconds a user must be idle before his/hers last visit data is updated (primarily affects new message indicators).</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Online timeout</th>
									<td>
										<input type="text" name="form[timeout_online]" size="5" maxlength="5" value="<?php echo $pun_config['o_timeout_online'] ?>" />
										<span>Number of seconds a user must be idle before being removed from the online users list.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Redirect time</th>
									<td>
										<input type="text" name="form[redirect_delay]" size="3" maxlength="3" value="<?php echo $pun_config['o_redirect_delay'] ?>" />
										<span>Number of seconds to wait when redirecting. If set to 0, no redirect page will be displayed (not recommended).</span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend>Display</legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row">Version number</th>
									<td>
										<input type="radio" name="form[show_version]" value="1"<?php if ($pun_config['o_show_version'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[show_version]" value="0"<?php if ($pun_config['o_show_version'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Show version number in footer.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">User info in posts</th>
									<td>
										<input type="radio" name="form[show_user_info]" value="1"<?php if ($pun_config['o_show_user_info'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[show_user_info]" value="0"<?php if ($pun_config['o_show_user_info'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Show information about the poster under the username in topic view. The information affected is location, register date, post count and the contact links (e-mail and URL).</span>
									</td>
								</tr>
								<tr>
									<th scope="row">User post count</th>
									<td>
										<input type="radio" name="form[show_post_count]" value="1"<?php if ($pun_config['o_show_post_count'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[show_post_count]" value="0"<?php if ($pun_config['o_show_post_count'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Show the number of posts a user has made (affects topic view, profile and userlist).</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Smilies</th>
									<td>
										<input type="radio" name="form[smilies]" value="1"<?php if ($pun_config['o_smilies'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[smilies]" value="0"<?php if ($pun_config['o_smilies'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Convert smilies to small icons.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Smilies in signatures</th>
									<td>
										<input type="radio" name="form[smilies_sig]" value="1"<?php if ($pun_config['o_smilies_sig'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[smilies_sig]" value="0"<?php if ($pun_config['o_smilies_sig'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Convert smilies to small icons in user signatures.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Make clickable links</th>
									<td>
										<input type="radio" name="form[make_links]" value="1"<?php if ($pun_config['o_make_links'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[make_links]" value="0"<?php if ($pun_config['o_make_links'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>When enabled, PunBB will automatically detect any URL's in posts and make them clickable hyperlinks.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Topic review</th>
									<td>
										<input type="text" name="form[topic_review]" size="3" maxlength="3" value="<?php echo $pun_config['o_topic_review'] ?>" />
										<span>Maximum number of posts to display when posting (newest first). 0 to disable.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Topics per page default</th>
									<td>
										<input type="text" name="form[disp_topics_default]" size="3" maxlength="3" value="<?php echo $pun_config['o_disp_topics_default'] ?>" />
										<span>The default number of topics to display per page in a forum. Users can personalize this setting.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Posts per page default</th>
									<td>
										<input type="text" name="form[disp_posts_default]" size="3" maxlength="3" value="<?php echo $pun_config['o_disp_posts_default'] ?>" />
										<span>The default number of posts to display per page in a topic. Users can personalize this setting.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Indent size</th>
									<td>
										<input type="text" name="form[indent_num_spaces]" size="3" maxlength="3" value="<?php echo $pun_config['o_indent_num_spaces'] ?>" />
										<span>If set to 8, a regular tab will be used when displaying text within the [code][/code] tag. Otherwise this many spaces will be used to indent the text.</span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend>Features</legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row">Quick post</th>
									<td>
										<input type="radio" name="form[quickpost]" value="1"<?php if ($pun_config['o_quickpost'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[quickpost]" value="0"<?php if ($pun_config['o_quickpost'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>When enabled, PunBB will add a quick post form at the bottom of topics. This way users can post directly from the topic view.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Users online</th>
									<td>
										<input type="radio" name="form[users_online]" value="1"<?php if ($pun_config['o_users_online'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[users_online]" value="0"<?php if ($pun_config['o_users_online'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Display info on the index page about guests and registered users currently browsing the forums.</span>
									</td>
								</tr>
								<tr>
									<th scope="row"><a name="censoring">Censor words</a></th>
									<td>
										<input type="radio" name="form[censoring]" value="1"<?php if ($pun_config['o_censoring'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[censoring]" value="0"<?php if ($pun_config['o_censoring'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Enable this to censor specific words in the forum. See <a href="admin_censoring.php">Censoring</a> for more info.</span>
									</td>
								</tr>
								<tr>
									<th scope="row"><a name="ranks">User ranks</a></th>
									<td>
										<input type="radio" name="form[ranks]" value="1"<?php if ($pun_config['o_ranks'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[ranks]" value="0"<?php if ($pun_config['o_ranks'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Enable this to use user ranks. See <a href="admin_ranks.php">Ranks</a> for more info.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">User has posted earlier</th>
									<td>
										<input type="radio" name="form[show_dot]" value="1"<?php if ($pun_config['o_show_dot'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[show_dot]" value="0"<?php if ($pun_config['o_show_dot'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>This feature displays a dot in front of topics in viewforum.php in case the currently logged in user has posted in that topic earlier. Disable if you are experiencing high server load.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Quick jump</th>
									<td>
										<input type="radio" name="form[quickjump]" value="1"<?php if ($pun_config['o_quickjump'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[quickjump]" value="0"<?php if ($pun_config['o_quickjump'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Enable the quick jump (jump to forum) drop list.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">GZip output</th>
									<td>
										<input type="radio" name="form[gzip]" value="1"<?php if ($pun_config['o_gzip'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[gzip]" value="0"<?php if ($pun_config['o_gzip'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>If enabled, PunBB will gzip the output sent to browsers. This will reduce bandwidth usage, but use a little more CPU. This feature requires that PHP is configured with zlib (--with-zlib). Note: If you already have one of the Apache modules mod_gzip or mod_deflate set up to compress PHP scripts, you should disable this feature.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Search all forums</th>
									<td>
										<input type="radio" name="form[search_all_forums]" value="1"<?php if ($pun_config['o_search_all_forums'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[search_all_forums]" value="0"<?php if ($pun_config['o_search_all_forums'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>When disabled, searches will only be allowed in one forum at a time. Disable if server load is high due to excessive searching.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Additional menu items</th>
									<td>
										<textarea name="form[additional_navlinks]" rows="3" cols="55"><?php echo pun_htmlspecialchars($pun_config['o_additional_navlinks']) ?></textarea>
										<span>By entering HTML hyperlinks into this textbox, any number of items can be added to the navigation menu at the top of all pages. The format for adding new links is X = &lt;a href="URL"&gt;LINK&lt;/a&gt; where X is the position at which the link should be inserted (e.g. 0 to insert at the beginning and 2 to insert after "User list"). Separate entries with a linebreak.</span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend>Reports</legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row">Report method</th>
									<td>
										<input type="radio" name="form[report_method]" value="0"<?php if ($pun_config['o_report_method'] == '0') echo ' checked="checked"' ?> />&nbsp;Internal&nbsp;&nbsp;&nbsp;<input type="radio" name="form[report_method]" value="1"<?php if ($pun_config['o_report_method'] == '1') echo ' checked="checked"' ?> />&nbsp;E-mail&nbsp;&nbsp;&nbsp;<input type="radio" name="form[report_method]" value="2"<?php if ($pun_config['o_report_method'] == '2') echo ' checked="checked"' ?> />&nbsp;Both
										<span>Select the method for handling topic/post reports. You can choose whether topic/post reports should be handled by the internal report system,  e-mailed to the addresses on the mailing list (see below) or both.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Report new registrations</th>
									<td>
										<input type="radio" name="form[regs_report]" value="1"<?php if ($pun_config['o_regs_report'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[regs_report]" value="0"<?php if ($pun_config['o_regs_report'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>If enabled, PunBB will notify users on the mailing list (see below) when a new user registers in the forums.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Mailing list</th>
									<td>
										<textarea name="form[mailing_list]" rows="5" cols="55"><?php echo pun_htmlspecialchars($pun_config['o_mailing_list']) ?></textarea>
										<span>A comma separated list of subscribers. The people on this list are the recipients of reports.</span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend>Avatars</legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row">Use avatars</th>
									<td>
										<input type="radio" name="form[avatars]" value="1"<?php if ($pun_config['o_avatars'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[avatars]" value="0"<?php if ($pun_config['o_avatars'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>When enabled, users will be able to upload an avatar which will be displayed under their title/rank.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Upload directory</th>
									<td>
										<input type="text" name="form[avatars_dir]" size="35" maxlength="50" value="<?php echo pun_htmlspecialchars($pun_config['o_avatars_dir']) ?>" />
										<span>The upload directory for avatars (relative to the PunBB root directory). PHP must have write permissions to this directory.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Max width</th>
									<td>
										<input type="text" name="form[avatars_width]" size="5" maxlength="5" value="<?php echo $pun_config['o_avatars_width'] ?>" />
										<span>The maximum allowed width of avatars in pixels (60 is recommended).</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Max height</th>
									<td>
										<input type="text" name="form[avatars_height]" size="5" maxlength="5" value="<?php echo $pun_config['o_avatars_height'] ?>" />
										<span>The maximum allowed height of avatars in pixels (60 is recommended).</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Max size</th>
									<td>
										<input type="text" name="form[avatars_size]" size="6" maxlength="6" value="<?php echo $pun_config['o_avatars_size'] ?>" />
										<span>The maximum allowed size of avatars in bytes (10240 is recommended).</span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend>E-mail</legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row">Admin e-mail</th>
									<td>
										<input type="text" name="form[admin_email]" size="50" maxlength="50" value="<?php echo $pun_config['o_admin_email'] ?>" />
										<span>The e-mail address of the forum administrator.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Webmaster e-mail</th>
									<td>
										<input type="text" name="form[webmaster_email]" size="50" maxlength="50" value="<?php echo $pun_config['o_webmaster_email'] ?>" />
										<span>This is the address that all e-mails sent by the forum will be addressed from.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Subscriptions</th>
									<td>
										<input type="radio" name="form[subscriptions]" value="1"<?php if ($pun_config['o_subscriptions'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[subscriptions]" value="0"<?php if ($pun_config['o_subscriptions'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Enable users to subscribe to topics (recieve e-mail when someone replies).</span>
									</td>
								</tr>
								<tr>
									<th scope="row">SMTP server address</th>
									<td>
										<input type="text" name="form[smtp_host]" size="30" maxlength="100" value="<?php echo pun_htmlspecialchars($pun_config['o_smtp_host']) ?>" />
										<span>The address of an external SMTP server to send e-mails with. You can specify a custom port number if the SMTP server doesn't run on the default port 25 (example: mail.myhost.com:3580). Leave blank to use the local mail program.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">SMTP username</th>
									<td>
										<input type="text" name="form[smtp_user]" size="25" maxlength="50" value="<?php echo pun_htmlspecialchars($pun_config['o_smtp_user']) ?>" />
										<span>Username for SMTP server. Only enter a username if it is required by the SMTP server (most servers <strong>do not</strong> require authentication).</span>
									</td>
								</tr>
								<tr>
									<th scope="row">SMTP password</th>
									<td>
										<input type="text" name="form[smtp_pass]" size="25" maxlength="50" value="<?php echo pun_htmlspecialchars($pun_config['o_smtp_pass']) ?>" />
										<span>Password for SMTP server. Only enter a password if it is required by the SMTP server (most servers <strong>do not</strong> require authentication).</span>
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
									<th scope="row">Allow new registrations</th>
									<td>
										<input type="radio" name="form[regs_allow]" value="1"<?php if ($pun_config['o_regs_allow'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[regs_allow]" value="0"<?php if ($pun_config['o_regs_allow'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Controls whether this forum accepts new registrations. Disable only under special circumstances.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Verify registrations</th>
									<td>
										<input type="radio" name="form[regs_verify]" value="1"<?php if ($pun_config['o_regs_verify'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[regs_verify]" value="0"<?php if ($pun_config['o_regs_verify'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>When enabled, users are e-mailed a random password when they register. They can then log in and change the password in their profile if they see fit. This feature also requires users to verify new e-mail addresses if they choose to change from the one they registered with. This is an effective way of avoiding registration abuse and making sure that all users have "correct" e-mail addresses in their profiles.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Use forum rules</th>
									<td>
										<input type="radio" name="form[rules]" value="1"<?php if ($pun_config['o_rules'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[rules]" value="0"<?php if ($pun_config['o_rules'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>When enabled, users must agree to a set of rules when registering (enter text below). The rules will always be available through a link in the navigation table at the top of every page.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Rules</th>
									<td>
										<textarea name="form[rules_message]" rows="10" cols="55"><?php echo pun_htmlspecialchars($pun_config['o_rules_message']) ?></textarea>
										<span>Here you can enter any rules or other information that the user must review and accept when registering. If you enabled rules above you have to enter something here, otherwise it will be disabled. This text will not be parsed like regular posts and thus may contain HTML.</span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend>Announcement</legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row">Display announcement</th>
									<td>
										<input type="radio" name="form[announcement]" value="1"<?php if ($pun_config['o_announcement'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[announcement]" value="0"<?php if ($pun_config['o_announcement'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>Enable this to display the below message in the forums.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Announcement message</th>
									<td>
										<textarea name="form[announcement_message]" rows="5" cols="55"><?php echo pun_htmlspecialchars($pun_config['o_announcement_message']) ?></textarea>
										<span>This text will not be parsed like regular posts and thus may contain HTML.</span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend>Maintenance</legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><a name="maintenance">Maintenance mode</a></th>
									<td>
										<input type="radio" name="form[maintenance]" value="1"<?php if ($pun_config['o_maintenance'] == '1') echo ' checked="checked"' ?> />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="form[maintenance]" value="0"<?php if ($pun_config['o_maintenance'] == '0') echo ' checked="checked"' ?> />&nbsp;<strong>No</strong>
										<span>When enabled, the board will only be available to administrators. This should be used if the board needs to taken down temporarily for maintenance. WARNING! Do not log out when the board is in maintenance mode. You will not be able to login again.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Maintenance message</th>
									<td>
										<textarea name="form[maintenance_message]" rows="5" cols="55"><?php echo pun_htmlspecialchars($pun_config['o_maintenance_message']) ?></textarea>
										<span>The message that will be displayed to users when the board is in maintenance mode. If left blank a default message will be used. This text will not be parsed like regular posts and thus may contain HTML.</span>
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
