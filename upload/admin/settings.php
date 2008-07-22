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
	define('FORUM_ROOT', '../');
require FORUM_ROOT.'include/common.php';
require FORUM_ROOT.'include/common_admin.php';

($hook = get_hook('aop_start')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

if ($forum_user['g_id'] != FORUM_ADMIN)
	message($lang_common['No permission']);

// Load the admin.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin_common.php';
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin_settings.php';

$section = isset($_GET['section']) ? $_GET['section'] : null;


if (isset($_POST['form_sent']))
{
	$form = array_map('trim', $_POST['form']);

	($hook = get_hook('aop_form_submitted')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

	// Validate input depending on section
	switch ($section)
	{
		case 'setup':
		{
			($hook = get_hook('aop_setup_validation')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

			if ($form['board_title'] == '')
				message($lang_admin_settings['Error no board title']);

			// Clean default_lang, default_style, and sef
			$form['default_style'] = preg_replace('#[\.\\\/]#', '', $form['default_style']);
			$form['default_lang'] = preg_replace('#[\.\\\/]#', '', $form['default_lang']);
			$form['sef'] = preg_replace('#[\.\\\/]#', '', $form['sef']);

			// Make sure default_lang, default_style, and sef exist
			if (!file_exists(FORUM_ROOT.'style/'.$form['default_style'].'/'.$form['default_style'].'.php'))
				message($lang_common['Bad request']);
			if (!file_exists(FORUM_ROOT.'lang/'.$form['default_lang'].'/common.php'))
				message($lang_common['Bad request']);
			if (!file_exists(FORUM_ROOT.'include/url/'.$form['sef'].'.php'))
				message($lang_common['Bad request']);

			$form['timeout_visit'] = intval($form['timeout_visit']);
			$form['timeout_online'] = intval($form['timeout_online']);
			$form['redirect_delay'] = intval($form['redirect_delay']);

			if ($form['timeout_online'] >= $form['timeout_visit'])
				message($lang_admin_settings['Error timeout value']);

			$form['disp_topics_default'] = (intval($form['disp_topics_default']) > 0) ? intval($form['disp_topics_default']) : 1;
			$form['disp_posts_default'] = (intval($form['disp_posts_default']) > 0) ? intval($form['disp_posts_default']) : 1;

			if ($form['additional_navlinks'] != '')
				$form['additional_navlinks'] = forum_trim(forum_linebreaks($form['additional_navlinks']));

			break;
		}

		case 'features':
		{
			($hook = get_hook('aop_features_validation')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

			if (!isset($form['search_all_forums']) || $form['search_all_forums'] != '1') $form['search_all_forums'] = '0';
			if (!isset($form['ranks']) || $form['ranks'] != '1') $form['ranks'] = '0';
			if (!isset($form['censoring']) || $form['censoring'] != '1') $form['censoring'] = '0';
			if (!isset($form['quickjump']) || $form['quickjump'] != '1') $form['quickjump'] = '0';
			if (!isset($form['show_version']) || $form['show_version'] != '1') $form['show_version'] = '0';
			if (!isset($form['users_online']) || $form['users_online'] != '1') $form['users_online'] = '0';

			if (!isset($form['quickpost']) || $form['quickpost'] != '1') $form['quickpost'] = '0';
			if (!isset($form['subscriptions']) || $form['subscriptions'] != '1') $form['subscriptions'] = '0';
			if (!isset($form['force_guest_email']) || $form['force_guest_email'] != '1') $form['force_guest_email'] = '0';
			if (!isset($form['show_dot']) || $form['show_dot'] != '1') $form['show_dot'] = '0';
			if (!isset($form['topic_views']) || $form['topic_views'] != '1') $form['topic_views'] = '0';
			if (!isset($form['show_post_count']) || $form['show_post_count'] != '1') $form['show_post_count'] = '0';
			if (!isset($form['show_user_info']) || $form['show_user_info'] != '1') $form['show_user_info'] = '0';

			if (!isset($form['message_bbcode']) || $form['message_bbcode'] != '1') $form['message_bbcode'] = '0';
			if (!isset($form['message_img_tag']) || $form['message_img_tag'] != '1') $form['message_img_tag'] = '0';
			if (!isset($form['smilies']) || $form['smilies'] != '1') $form['smilies'] = '0';
			if (!isset($form['make_links']) || $form['make_links'] != '1') $form['make_links'] = '0';
			if (!isset($form['message_all_caps']) || $form['message_all_caps'] != '1') $form['message_all_caps'] = '0';
			if (!isset($form['subject_all_caps']) || $form['subject_all_caps'] != '1') $form['subject_all_caps'] = '0';

			$form['indent_num_spaces'] = intval($form['indent_num_spaces']);
			$form['quote_depth'] = intval($form['quote_depth']);

			if (!isset($form['signatures']) || $form['signatures'] != '1') $form['signatures'] = '0';
			if (!isset($form['sig_bbcode']) || $form['sig_bbcode'] != '1') $form['sig_bbcode'] = '0';
			if (!isset($form['sig_img_tag']) || $form['sig_img_tag'] != '1') $form['sig_img_tag'] = '0';
			if (!isset($form['smilies_sig']) || $form['smilies_sig'] != '1') $form['smilies_sig'] = '0';
			if (!isset($form['sig_all_caps']) || $form['sig_all_caps'] != '1') $form['sig_all_caps'] = '0';

			$form['sig_length'] = intval($form['sig_length']);
			$form['sig_lines'] = intval($form['sig_lines']);

			if (!isset($form['avatars']) || $form['avatars'] != '1') $form['avatars'] = '0';

			// Make sure avatars_dir doesn't end with a slash
			if (substr($form['avatars_dir'], -1) == '/')
				$form['avatars_dir'] = substr($form['avatars_dir'], 0, -1);

			$form['avatars_width'] = intval($form['avatars_width']);
			$form['avatars_height'] = intval($form['avatars_height']);
			$form['avatars_size'] = intval($form['avatars_size']);

			if (!isset($form['check_for_updates']) || $form['check_for_updates'] != '1') $form['check_for_updates'] = '0';
			if (!isset($form['gzip']) || $form['gzip'] != '1') $form['gzip'] = '0';

			break;
		}

		case 'email':
		{
			($hook = get_hook('aop_email_validation')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

			if (!defined('FORUM_EMAIL_FUNCTIONS_LOADED'))
				require FORUM_ROOT.'include/email.php';

			$form['admin_email'] = strtolower($form['admin_email']);
			if (!is_valid_email($form['admin_email']))
				message($lang_admin_settings['Error invalid admin e-mail']);

			$form['webmaster_email'] = strtolower($form['webmaster_email']);
			if (!is_valid_email($form['webmaster_email']))
				message($lang_admin_settings['Error invalid web e-mail']);

			if (!isset($form['smtp_ssl']) || $form['smtp_ssl'] != '1') $form['smtp_ssl'] = '0';

			break;
		}

		case 'announcements':
		{
			($hook = get_hook('aop_announcements_validation')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

			if (!isset($form['announcement']) || $form['announcement'] != '1') $form['announcement'] = '0';

			if ($form['announcement_message'] != '')
				$form['announcement_message'] = forum_linebreaks($form['announcement_message']);
			else
				$form['announcement_message'] = $lang_admin_settings['Announcement message default'];

			break;
		}

		case 'registration':
		{
			($hook = get_hook('aop_registration_validation')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

			if (!isset($form['regs_allow']) || $form['regs_allow'] != '1') $form['regs_allow'] = '0';
			if (!isset($form['regs_verify']) || $form['regs_verify'] != '1') $form['regs_verify'] = '0';
			if (!isset($form['allow_banned_email']) || $form['allow_banned_email'] != '1') $form['allow_banned_email'] = '0';
			if (!isset($form['allow_dupe_email']) || $form['allow_dupe_email'] != '1') $form['allow_dupe_email'] = '0';
			if (!isset($form['regs_report']) || $form['regs_report'] != '1') $form['regs_report'] = '0';

			if (!isset($form['rules']) || $form['rules'] != '1') $form['rules'] = '0';

			if ($form['rules_message'] != '')
				$form['rules_message'] = forum_linebreaks($form['rules_message']);
			else
				$form['rules_message'] = $lang_admin_settings['Rules default'];

			break;
		}

		case 'maintenance':
		{
			($hook = get_hook('aop_maintenance_validation')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

			if (!isset($form['maintenance']) || $form['maintenance'] != '1') $form['maintenance'] = '0';

			if ($form['maintenance_message'] != '')
				$form['maintenance_message'] = forum_linebreaks($form['maintenance_message']);
			else
				$form['maintenance_message'] = $lang_admin_settings['Maintenance message default'];

			break;
		}

		default:
		{
			($hook = get_hook('aop_new_section_validation')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
			break;
		}
	}

	($hook = get_hook('aop_pre_update_configuration')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

	while (list($key, $input) = @each($form))
	{
		// Only update permission values that have changed
		if (array_key_exists('p_'.$key, $forum_config) && $forum_config['p_'.$key] != $input)
		{
			$query = array(
				'UPDATE'	=> 'config',
				'SET'		=> 'conf_value='.$input,
				'WHERE'		=> 'conf_name=\'p_'.$forum_db->escape($key).'\''
			);

			($hook = get_hook('aop_qr_update_permission_conf')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);
		}

		// Only update option values that have changed
		if (array_key_exists('o_'.$key, $forum_config) && $forum_config['o_'.$key] != $input)
		{
			if ($input != '' || is_int($input))
				$value = '\''.$forum_db->escape($input).'\'';
			else
				$value = 'NULL';

			$query = array(
				'UPDATE'	=> 'config',
				'SET'		=> 'conf_value='.$value,
				'WHERE'		=> 'conf_name=\'o_'.$forum_db->escape($key).'\''
			);

			($hook = get_hook('aop_qr_update_permission_option')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);
		}
	}

	// Regenerate the config cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';

	generate_config_cache();

	redirect(forum_link($forum_url['admin_settings_'.$section]), $lang_admin_settings['Settings updated'].' '.$lang_admin_common['Redirect']);
}


if (!$section || $section == 'setup')
{
	// Setup the form
	$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
		array($lang_admin_common['Settings'], forum_link($forum_url['admin_settings_setup'])),
		$lang_admin_common['Setup']
	);

	$forum_page['main_head'] = $lang_admin_common['Settings'];

	($hook = get_hook('aop_setup_pre_header_load')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

	define('FORUM_PAGE_SECTION', 'settings');
	define('FORUM_PAGE', 'admin-settings-setup');
	define('FORUM_PAGE_TYPE', 'sectioned');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

	($hook = get_hook('aop_setup_output_start')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_settings_setup']) ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_settings_setup'])) ?>" />
				<input type="hidden" name="form_sent" value="1" />
			</div>
<?php ($hook = get_hook('aop_setup_pre_personal_part')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>

				<div class="content-head">
					<h2 class="hn"><span><?php printf($lang_admin_settings['Setup head'], $lang_admin_settings['Setup personal']) ?></span></h2>
				</div>
				<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
					<legend class="group-legend"><strong><?php echo $lang_admin_settings['Setup personal legend'] ?></strong></legend>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
								<span><?php echo $lang_admin_settings['Board title label'] ?></span>
							</label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[board_title]" size="50" maxlength="255" value="<?php echo forum_htmlencode($forum_config['o_board_title']) ?>" /></span>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
								<span><?php echo $lang_admin_settings['Board description label'] ?></span>
							</label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[board_desc]" size="50" maxlength="255" value="<?php echo forum_htmlencode($forum_config['o_board_desc']) ?>" /></span>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box select">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
								<span><?php echo $lang_admin_settings['Default style label'] ?></span>
							</label><br />
							<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="form[default_style]">
<?php

		$styles = array();
		$d = dir(FORUM_ROOT.'style');
		while (($entry = $d->read()) !== false)
		{
			if ($entry != '.' && $entry != '..' && is_dir(FORUM_ROOT.'style/'.$entry) && file_exists(FORUM_ROOT.'style/'.$entry.'/'.$entry.'.php'))
				$styles[] = $entry;
		}
		$d->close();

		@natcasesort($styles);

		while (list(, $temp) = @each($styles))
		{
			if ($forum_config['o_default_style'] == $temp)
				echo "\t\t\t\t\t\t\t\t".'<option value="'.$temp.'" selected="selected">'.str_replace('_', ' ', $temp).'</option>'."\n";
			else
				echo "\t\t\t\t\t\t\t\t".'<option value="'.$temp.'">'.str_replace('_', ' ', $temp).'</option>'."\n";
		}

?>
							</select></span>
						</div>
					</div>
<?php ($hook = get_hook('aop_setup_personal_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
				</fieldset>
<?php

// Reset counter
$forum_page['group_count'] = $forum_page['item_count'] = 0;

($hook = get_hook('aop_setup_pre_local_part')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
				<div class="content-head">
					<h2 class="hn"><span><?php printf($lang_admin_settings['Setup head'], $lang_admin_settings['Setup local']) ?></span></h2>
				</div>
				<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
					<legend class="group-legend"><strong><?php echo $lang_admin_settings['Setup local legend'] ?></strong></legend>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box select">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Default language label'] ?></span><small><?php echo $lang_admin_settings['Default language help'] ?></small></label><br />
							<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="form[default_lang]">
<?php

		$languages = array();
		$d = dir(FORUM_ROOT.'lang');
		while (($entry = $d->read()) !== false)
		{
			if ($entry != '.' && $entry != '..' && is_dir(FORUM_ROOT.'lang/'.$entry) && file_exists(FORUM_ROOT.'lang/'.$entry.'/common.php'))
				$languages[] = $entry;
		}
		$d->close();

		@natcasesort($languages);

		while (list(, $temp) = @each($languages))
		{
			if ($forum_config['o_default_lang'] == $temp)
				echo "\t\t\t\t\t\t\t\t".'<option value="'.$temp.'" selected="selected">'.$temp.'</option>'."\n";
			else
				echo "\t\t\t\t\t\t\t\t".'<option value="'.$temp.'">'.$temp.'</option>'."\n";
		}

		// Load the profile.php language file
		require FORUM_ROOT.'lang/'.$forum_user['language'].'/profile.php';

?>
							</select></span>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box select">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Default timezone label'] ?></span></label><br />
							<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="form[default_timezone]">
								<option value="-12"<?php if ($forum_config['o_default_timezone'] == -12) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-12:00'] ?></option>
								<option value="-11"<?php if ($forum_config['o_default_timezone'] == -11) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-11:00'] ?></option>
								<option value="-10"<?php if ($forum_config['o_default_timezone'] == -10) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-10:00'] ?></option>
								<option value="-9.5"<?php if ($forum_config['o_default_timezone'] == -9.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-09:30'] ?></option>
								<option value="-9"<?php if ($forum_config['o_default_timezone'] == -9) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-09:00'] ?></option>
								<option value="-8"<?php if ($forum_config['o_default_timezone'] == -8) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-08:00'] ?></option>
								<option value="-7"<?php if ($forum_config['o_default_timezone'] == -7) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-07:00'] ?></option>
								<option value="-6"<?php if ($forum_config['o_default_timezone'] == -6) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-06:00'] ?></option>
								<option value="-5"<?php if ($forum_config['o_default_timezone'] == -5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-05:00'] ?></option>
								<option value="-4"<?php if ($forum_config['o_default_timezone'] == -4) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-04:00'] ?></option>
								<option value="-3.5"<?php if ($forum_config['o_default_timezone'] == -3.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-03:30'] ?></option>
								<option value="-3"<?php if ($forum_config['o_default_timezone'] == -3) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-03:00'] ?></option>
								<option value="-2"<?php if ($forum_config['o_default_timezone'] == -2) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-02:00'] ?></option>
								<option value="-1"<?php if ($forum_config['o_default_timezone'] == -1) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC-01:00'] ?></option>
								<option value="0"<?php if ($forum_config['o_default_timezone'] == 0) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC'] ?></option>
								<option value="1"<?php if ($forum_config['o_default_timezone'] == 1) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+01:00'] ?></option>
								<option value="2"<?php if ($forum_config['o_default_timezone'] == 2) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+02:00'] ?></option>
								<option value="3"<?php if ($forum_config['o_default_timezone'] == 3) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+03:00'] ?></option>
								<option value="3.5"<?php if ($forum_config['o_default_timezone'] == 3.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+03:30'] ?></option>
								<option value="4"<?php if ($forum_config['o_default_timezone'] == 4) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+04:00'] ?></option>
								<option value="4.5"<?php if ($forum_config['o_default_timezone'] == 4.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+04:30'] ?></option>
								<option value="5"<?php if ($forum_config['o_default_timezone'] == 5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+05:00'] ?></option>
								<option value="5.5"<?php if ($forum_config['o_default_timezone'] == 5.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+05:30'] ?></option>
								<option value="5.75"<?php if ($forum_config['o_default_timezone'] == 5.75) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+05:45'] ?></option>
								<option value="6"<?php if ($forum_config['o_default_timezone'] == 6) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+06:00'] ?></option>
								<option value="6.5"<?php if ($forum_config['o_default_timezone'] == 6.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+06:30'] ?></option>
								<option value="7"<?php if ($forum_config['o_default_timezone'] == 7) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+07:00'] ?></option>
								<option value="8"<?php if ($forum_config['o_default_timezone'] == 8) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+08:00'] ?></option>
								<option value="8.75"<?php if ($forum_config['o_default_timezone'] == 8.75) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+08:45'] ?></option>
								<option value="9"<?php if ($forum_config['o_default_timezone'] == 9) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+09:00'] ?></option>
								<option value="9.5"<?php if ($forum_config['o_default_timezone'] == 9.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+09:30'] ?></option>
								<option value="10"<?php if ($forum_config['o_default_timezone'] == 10) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+10:00'] ?></option>
								<option value="10.5"<?php if ($forum_config['o_default_timezone'] == 10.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+10:30'] ?></option>
								<option value="11"<?php if ($forum_config['o_default_timezone'] == 11) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+11:00'] ?></option>
								<option value="11.5"<?php if ($forum_config['o_default_timezone'] == 11.5) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+11:30'] ?></option>
								<option value="12"<?php if ($forum_config['o_default_timezone'] == 12) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+12:00'] ?></option>
								<option value="12.75"<?php if ($forum_config['o_default_timezone'] == 12.75) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+12:45'] ?></option>
								<option value="13"<?php if ($forum_config['o_default_timezone'] == 13) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+13:00'] ?></option>
								<option value="14"<?php if ($forum_config['o_default_timezone'] == 14) echo ' selected="selected"' ?>><?php echo $lang_profile['UTC+14:00'] ?></option>
							</select></span>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Time format label'] ?></span><small><?php printf($lang_admin_settings['Current format'], format_time(time(), 2, null, $forum_config['o_time_format']), $lang_admin_settings['External format help']) ?></small></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[time_format]" size="25" maxlength="25" value="<?php echo forum_htmlencode($forum_config['o_time_format']) ?>" /></span>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Date format label'] ?></span><small><?php printf($lang_admin_settings['Current format'], format_time(time(), 1, $forum_config['o_date_format'], null, true), $lang_admin_settings['External format help']) ?></small></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[date_format]" size="25" maxlength="25" value="<?php echo forum_htmlencode($forum_config['o_date_format']) ?>" /></span>
						</div>
					</div>
<?php ($hook = get_hook('aop_setup_local_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
				</fieldset>
<?php

// Reset counter
$forum_page['group_count'] = $forum_page['item_count'] = 0;

($hook = get_hook('aop_setup_pre_timeouts_part')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
				<div class="content-head">
					<h2 class="hn"><span><?php printf($lang_admin_settings['Setup head'], $lang_admin_settings['Setup timeouts']) ?></span></h2>
				</div>
				<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
					<legend class="group-legend"><strong><?php echo $lang_admin_settings['Setup timeouts legend'] ?></strong></legend>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Visit timeout label'] ?></span><small><?php echo $lang_admin_settings['Visit timeout help'] ?></small></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[timeout_visit]" size="5" maxlength="5" value="<?php echo $forum_config['o_timeout_visit'] ?>" /></span>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Online timeout label'] ?></span><small><?php echo $lang_admin_settings['Online timeout help'] ?></small></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[timeout_online]" size="5" maxlength="5" value="<?php echo $forum_config['o_timeout_online'] ?>" /></span>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Redirect time label'] ?></span><small><?php echo $lang_admin_settings['Redirect time help'] ?></small></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[redirect_delay]" size="5" maxlength="5" value="<?php echo $forum_config['o_redirect_delay'] ?>" /></span>
						</div>
					</div>
<?php ($hook = get_hook('aop_setup_timeouts_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
				</fieldset>
<?php

// Reset counter
$forum_page['group_count'] = $forum_page['item_count'] = 0;

($hook = get_hook('aop_setup_pre_pagination_part')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
				<div class="content-head">
					<h2 class="hn"><span><?php printf($lang_admin_settings['Setup head'], $lang_admin_settings['Setup pagination']) ?></span></h2>
				</div>
				<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
					<legend class="group-legend"><strong><?php echo $lang_admin_settings['Setup pagination legend'] ?></strong></legend>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Topics per page label'] ?></span></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[disp_topics_default]" size="3" maxlength="3" value="<?php echo $forum_config['o_disp_topics_default'] ?>" /></span>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Posts per page label'] ?></span></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[disp_posts_default]" size="3" maxlength="3" value="<?php echo $forum_config['o_disp_posts_default'] ?>" /></span>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box frm-short text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Topic review label'] ?></span><small><?php echo $lang_admin_settings['Topic review help'] ?></small></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[topic_review]" size="3" maxlength="3" value="<?php echo $forum_config['o_topic_review'] ?>" /></span>
						</div>
					</div>
<?php ($hook = get_hook('aop_setup_pagination_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
				</fieldset>
<?php

// Reset counter
$forum_page['group_count'] = $forum_page['item_count'] = 0;

($hook = get_hook('aop_setup_pre_reports_part')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
				<div class="content-head">
					<h2 class="hn"><span><?php printf($lang_admin_settings['Setup head'], $lang_admin_settings['Setup reports']) ?></span></h2>
				</div>
				<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
					<legend class="group-legend"><strong><?php echo $lang_admin_settings['Setup reports legend'] ?></strong></legend>
					<fieldset class="mf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<legend><span><?php echo $lang_admin_settings['Reporting method'] ?></span></legend>
						<div class="mf-box">
							<div class="mf-item">
								<span class="fld-input"><input type="radio" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[report_method]" value="0"<?php if ($forum_config['o_report_method'] == '0') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_settings['Report internal label'] ?></label>
							</div>
							<div class="mf-item">
								<span class="fld-input"><input type="radio" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[report_method]" value="1"<?php if ($forum_config['o_report_method'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_settings['Report email label'] ?></label>
							</div>
							<div class="mf-item">
								<span class="fld-input"><input type="radio" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[report_method]" value="2"<?php if ($forum_config['o_report_method'] == '2') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_settings['Report both label'] ?></label>
							</div>
						</div>
					</fieldset>
<?php ($hook = get_hook('aop_setup_reports_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
				</fieldset>
<?php

// Reset counter
$forum_page['group_count'] = $forum_page['item_count'] = 0;

($hook = get_hook('aop_setup_pre_url_scheme_part')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
				<div class="content-head">
					<h2 class="hn"><span><?php printf($lang_admin_settings['Setup head'], $lang_admin_settings['Setup URL']) ?></span></h2>
				</div>
				<div class="ct-box">
					<p class="warn"><?php echo $lang_admin_settings['URL scheme info'] ?></p>
				</div>
				<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
					<legend class="group-legend"><strong><?php echo $lang_admin_settings['Setup URL legend'] ?></strong></legend>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box select">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['URL scheme label'] ?></span><small><?php echo $lang_admin_settings['URL scheme help'] ?></small></label><br />
							<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="form[sef]">
<?php

		$url_schemes = array();
		$d = dir(FORUM_ROOT.'include/url');
		while (($entry = $d->read()) !== false)
		{
			if ($entry != '.' && $entry != '..' && substr($entry, strlen($entry)-4) == '.php')
				$url_schemes[] = $entry;
		}
		$d->close();

		@natcasesort($url_schemes);

		while (list(, $temp) = @each($url_schemes))
		{
			$temp = substr($temp, 0, -4);
			if ($forum_config['o_sef'] == $temp)
				echo "\t\t\t\t\t\t\t\t".'<option value="'.$temp.'" selected="selected">'.str_replace('_', ' ', $temp).'</option>'."\n";
			else
				echo "\t\t\t\t\t\t\t\t".'<option value="'.$temp.'">'.str_replace('_', ' ', $temp).'</option>'."\n";
		}

?>
							</select></span>
						</div>
					</div>
<?php ($hook = get_hook('aop_setup_url_scheme_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
				</fieldset>
<?php

// Reset counter
$forum_page['group_count'] = $forum_page['item_count'] = 0;

($hook = get_hook('aop_setup_pre_links_part')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
				<div class="content-head">
					<h2 class="hn"><span><?php printf($lang_admin_settings['Setup head'], $lang_admin_settings['Setup links']) ?></span></h2>
				</div>
				<div class="ct-box">
					<p class="warn"><?php echo $lang_admin_settings['Setup links info'] ?></p>
				</div>
				<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
					<legend class="group-legend"><strong><?php echo $lang_admin_settings['Setup links legend'] ?></strong></legend>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box textarea">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Enter links label'] ?></span></label><br />
							<span class="fld-input"><textarea id="fld<?php echo $forum_page['fld_count'] ?>" name="form[additional_navlinks]" rows="3" cols="55"><?php echo forum_htmlencode($forum_config['o_additional_navlinks']) ?></textarea></span>
						</div>
					</div>
<?php ($hook = get_hook('aop_setup_links_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
				</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="save" value="<?php echo $lang_admin_common['Save changes'] ?>" /></span>
			</div>
		</form>
	</div>

<?php

}

else if ($section == 'features')
{
	// Setup the form
	$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
		array($lang_admin_common['Settings'], forum_link($forum_url['admin_settings_setup'])),
		$lang_admin_common['Features']
	);

	($hook = get_hook('aop_features_pre_header_load')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

	define('FORUM_PAGE_SECTION', 'settings');
	define('FORUM_PAGE', 'admin-settings-features');
	define('FORUM_PAGE_TYPE', 'sectioned');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

	($hook = get_hook('aop_features_output_start')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_settings_features']) ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_settings_features'])) ?>" />
				<input type="hidden" name="form_sent" value="1" />
			</div>
<?php ($hook = get_hook('aop_features_pre_general_part')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
				<div class="content-head">
					<h2 class="hn"><span><?php printf($lang_admin_settings['Features head'], $lang_admin_settings['Features general']) ?></span></h2>
				</div>
				<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
					<legend class="group-legend"><strong><?php echo $lang_admin_settings['Features general legend'] ?></strong></legend>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box checkbox">
							<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[search_all_forums]" value="1"<?php if ($forum_config['o_search_all_forums'] == '1') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Searching'] ?></span> <?php echo $lang_admin_settings['Search all label'] ?></label>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box checkbox">
							<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[ranks]" value="1"<?php if ($forum_config['o_ranks'] == '1') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['User ranks'] ?></span> <?php echo $lang_admin_settings['User ranks label'] ?></label>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box checkbox">
							<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[censoring]" value="1"<?php if ($forum_config['o_censoring'] == '1') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Censor words'] ?></span> <?php echo $lang_admin_settings['Censor words label'] ?></label>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box checkbox">
							<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[quickjump]" value="1"<?php if ($forum_config['o_quickjump'] == '1') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Quick jump'] ?></span> <?php echo $lang_admin_settings['Quick jump label'] ?></label>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box checkbox">
							<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[show_version]" value="1"<?php if ($forum_config['o_show_version'] == '1') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Show version'] ?></span> <?php echo $lang_admin_settings['Show version label'] ?></label>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box checkbox">
							<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[users_online]" value="1"<?php if ($forum_config['o_users_online'] == '1') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Online list'] ?></span> <?php echo $lang_admin_settings['Users online label'] ?></label>
						</div>
					</div>
<?php ($hook = get_hook('aop_features_general_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
				</fieldset>
<?php

// Reset counter
$forum_page['group_count'] = $forum_page['item_count'] = 0;

($hook = get_hook('aop_features_pre_posting_part')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
				<div class="content-head">
					<h2 class="hn"><span><?php printf($lang_admin_settings['Features head'], $lang_admin_settings['Features posting']) ?></span></h2>
				</div>
				<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
					<legend class="group-legend"><span><?php echo $lang_admin_settings['Features posting legend'] ?></span></legend>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box checkbox">
							<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[quickpost]" value="1"<?php if ($forum_config['o_quickpost'] == '1') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Quick post'] ?></span> <?php echo $lang_admin_settings['Quick post label'] ?></label>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box checkbox">
							<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[subscriptions]" value="1"<?php if ($forum_config['o_subscriptions'] == '1') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Subscriptions'] ?></span> <?php echo $lang_admin_settings['Subscriptions label'] ?></label>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box checkbox">
							<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[force_guest_email]" value="1"<?php if ($forum_config['p_force_guest_email'] == '1') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Guest posting'] ?></span> <?php echo $lang_admin_settings['Guest posting label'] ?></label>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box checkbox">
							<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[show_dot]" value="1"<?php if ($forum_config['o_show_dot'] == '1') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['User has posted'] ?></span> <?php echo $lang_admin_settings['User has posted label'] ?></label>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box checkbox">
							<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[topic_views]" value="1"<?php if ($forum_config['o_topic_views'] == '1') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Topic views'] ?></span> <?php echo $lang_admin_settings['Topic views label'] ?></label>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box checkbox">
							<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[show_post_count]" value="1"<?php if ($forum_config['o_show_post_count'] == '1') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['User post count'] ?></span> <?php echo $lang_admin_settings['User post count label'] ?></label>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box checkbox">
							<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[show_user_info]" value="1"<?php if ($forum_config['o_show_user_info'] == '1') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['User info'] ?></span> <?php echo $lang_admin_settings['User info label'] ?></label>
						</div>
					</div>
<?php ($hook = get_hook('aop_features_posting_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
				</fieldset>
<?php

// Reset counter
$forum_page['group_count'] = $forum_page['item_count'] = 0;

($hook = get_hook('aop_features_pre_message_part')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
				<div class="content-head">
					<h2 class="hn"><span><?php printf($lang_admin_settings['Features head'], $lang_admin_settings['Features posts']) ?></span></h2>
				</div>
				<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
					<legend class="group-legend"><span><?php echo $lang_admin_settings['Features posts legend'] ?></span></legend>
					<fieldset class="mf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<legend><span><?php echo $lang_admin_settings['Post content group'] ?></span></legend>
						<div class="mf-box">
							<div class="mf-item">
								<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[message_bbcode]" value="1"<?php if ($forum_config['p_message_bbcode'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_settings['Allow BBCode label'] ?></label>
							</div>
							<div class="mf-item">
								<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[message_img_tag]" value="1"<?php if ($forum_config['p_message_img_tag'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_settings['Allow img label'] ?></label>
							</div>
							<div class="mf-item">
								<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[smilies]" value="1"<?php if ($forum_config['o_smilies'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_settings['Smilies in posts label'] ?></label>
							</div>
							<div class="mf-item">
								<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[make_links]" value="1"<?php if ($forum_config['o_make_links'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_settings['Make clickable links label'] ?></label>
							</div>
						</div>
					</fieldset>
					<fieldset class="mf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<legend><span><?php echo $lang_admin_settings['Allow capitals group'] ?></span></legend>
						<div class="mf-box">
							<div class="mf-item">
								<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[message_all_caps]" value="1"<?php if ($forum_config['p_message_all_caps'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_settings['All caps message label'] ?></label>
							</div>
							<div class="mf-item">
								<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[subject_all_caps]" value="1"<?php if ($forum_config['p_subject_all_caps'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_settings['All caps subject label'] ?></label>
							</div>
						</div>
					</fieldset>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Indent size label'] ?></span><small><?php echo $lang_admin_settings['Indent size help'] ?></small></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[indent_num_spaces]" size="3" maxlength="3" value="<?php echo $forum_config['o_indent_num_spaces'] ?>" /></span>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Quote depth label'] ?></span><small><?php echo $lang_admin_settings['Quote depth help'] ?></small></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[quote_depth]" size="3" maxlength="3" value="<?php echo $forum_config['o_quote_depth'] ?>" /></span>
						</div>
					</div>
<?php ($hook = get_hook('aop_features_message_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
				</fieldset>
<?php

// Reset counter
$forum_page['group_count'] = $forum_page['item_count'] = 0;

($hook = get_hook('aop_features_pre_sigs_part')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
			<div class="content-head">
				<h2 class="hn"><span><?php printf($lang_admin_settings['Features head'], $lang_admin_settings['Features sigs']) ?></span></h2>
			</div>
				<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
					<legend class="group-legend"><span><?php echo $lang_admin_settings['Features sigs legend'] ?></span></legend>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box checkbox">
							<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[signatures]" value="1"<?php if ($forum_config['o_signatures'] == '1') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Allow signatures'] ?></span> <?php echo $lang_admin_settings['Allow signatures label'] ?></label>
						</div>
					</div>
					<fieldset class="mf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<legend><span><?php echo $lang_admin_settings['Signature content group'] ?></span></legend>
						<div class="mf-box">
							<div class="mf-item">
								<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[sig_bbcode]" value="1"<?php if ($forum_config['p_sig_bbcode'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_settings['BBCode in sigs label'] ?></label>
							</div>
							<div class="mf-item">
								<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[sig_img_tag]" value="1"<?php if ($forum_config['p_sig_img_tag'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_settings['Img in sigs label'] ?></label>
							</div>
							<div class="mf-item">
								<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[smilies_sig]" value="1"<?php if ($forum_config['o_smilies_sig'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_settings['Smilies in sigs label'] ?></label>
							</div>
							<div class="mf-item">
								<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[sig_all_caps]" value="1"<?php if ($forum_config['p_sig_all_caps'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_settings['All caps sigs label'] ?></label>
							</div>
						</div>
					</fieldset>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Max sig length label'] ?></span></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[sig_length]" size="5" maxlength="5" value="<?php echo $forum_config['p_sig_length'] ?>" /></span>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Max sig lines label'] ?></span></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[sig_lines]" size="5" maxlength="3" value="<?php echo $forum_config['p_sig_lines'] ?>" /></span>
						</div>
					</div>
<?php ($hook = get_hook('aop_features_sigs_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
				</fieldset>
<?php

// Reset counter
$forum_page['group_count'] = $forum_page['item_count'] = 0;

($hook = get_hook('aop_features_pre_avatars_part')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
			<div class="content-head">
				<h2 class="hn"><span><?php printf($lang_admin_settings['Features head'], $lang_admin_settings['Features Avatars']) ?></span></h2>
			</div>
				<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
					<legend class="group-legend"><span><?php echo $lang_admin_settings['Features Avatars legend'] ?></span></legend>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box checkbox">
							<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[avatars]" value="1"<?php if ($forum_config['o_avatars'] == '1') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Allow avatars'] ?></span> <?php echo $lang_admin_settings['Allow avatars label'] ?></label>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Avatar directory label'] ?></span><small><?php echo $lang_admin_settings['Avatar directory help'] ?></small></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[avatars_dir]" size="35" maxlength="50" value="<?php echo forum_htmlencode($forum_config['o_avatars_dir']) ?>" /></span>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Avatar Max width label'] ?></span><small><?php echo $lang_admin_settings['Avatar Max width help'] ?></small></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[avatars_width]" size="6" maxlength="5" value="<?php echo $forum_config['o_avatars_width'] ?>" /></span>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Avatar Max height label'] ?></span><small><?php echo $lang_admin_settings['Avatar Max height help'] ?></small></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[avatars_height]" size="6" maxlength="5" value="<?php echo $forum_config['o_avatars_height'] ?>" /></span>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Avatar Max size label'] ?></span><small><?php echo $lang_admin_settings['Avatar Max size help'] ?></small></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[avatars_size]" size="6" maxlength="6" value="<?php echo $forum_config['o_avatars_size'] ?>" /></span>
						</div>
					</div>
<?php ($hook = get_hook('aop_features_avatars_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
				</fieldset>
<?php

// Reset counter
$forum_page['group_count'] = $forum_page['item_count'] = 0;

($hook = get_hook('aop_features_pre_updates_part')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
			<div class="content-head">
				<h2 class="hn"><span><?php printf($lang_admin_settings['Features head'], $lang_admin_settings['Features update']) ?></span></h2>
			</div>
<?php if (function_exists('curl_init') || function_exists('fsockopen') || in_array(strtolower(@ini_get('allow_url_fopen')), array('on', 'true', '1'))): ?>				<div class="ct-box">
					<p><?php echo $lang_admin_settings['Features update info'] ?></p>
				</div>
				<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
					<legend class="group-legend"><strong><?php echo $lang_admin_settings['Features update legend'] ?></strong></legend>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box checkbox">
							<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[check_for_updates]" value="1"<?php if ($forum_config['o_check_for_updates'] == '1') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Update check'] ?></span> <?php echo $lang_admin_settings['Update check label'] ?></label>
						</div>
					</div>
				</fieldset>
<?php else: ?>				<div class="ct-box">
					<p><?php echo $lang_admin_settings['Features update disabled info'] ?></p>
				</div>
<?php endif; ($hook = get_hook('aop_features_updates_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
<?php

// Reset counter
$forum_page['group_count'] = $forum_page['item_count'] = 0;

($hook = get_hook('aop_features_pre_gzip_part')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
			<div class="content-head">
				<h2 class="hn"><span><?php printf($lang_admin_settings['Features head'], $lang_admin_settings['Features gzip']) ?></span></h2>
			</div>
				<div class="ct-box">
					<p><?php echo $lang_admin_settings['Features gzip info'] ?></p>
				</div>
				<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
					<legend class="group-legend"><strong><?php echo $lang_admin_settings['Features gzip legend'] ?></strong></legend>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box checkbox">
							<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[gzip]" value="1"<?php if ($forum_config['o_gzip'] == '1') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Enable gzip'] ?></span> <?php echo $lang_admin_settings['Enable gzip label'] ?></label>
						</div>
					</div>
<?php ($hook = get_hook('aop_features_gzip_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
				</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="save" value="<?php echo $lang_admin_common['Save changes'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

}
else if ($section == 'announcements')
{
	// Setup the form
	$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
		array($lang_admin_common['Settings'], forum_link($forum_url['admin_settings_setup'])),
		$lang_admin_common['Announcements']
	);

	($hook = get_hook('aop_announcements_pre_header_load')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

	define('FORUM_PAGE_SECTION', 'settings');
	define('FORUM_PAGE', 'admin-settings-announcements');
	define('FORUM_PAGE_TYPE', 'sectioned');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

	($hook = get_hook('aop_announcements_output_start')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
	<div class="main-content main-frm">
		<div class="content-head">
			<h2 class="hn"><span><?php echo $lang_admin_settings['Announcements head'] ?></span></h2>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_settings_announcements']) ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_settings_announcements'])) ?>" />
				<input type="hidden" name="form_sent" value="1" />
			</div>
				<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_admin_settings['Announcements legend'] ?></strong></legend>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box checkbox">
						<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[announcement]" value="1"<?php if ($forum_config['o_announcement'] == '1') echo ' checked="checked"' ?> /></span>
						<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Enable announcement'] ?></span> <?php echo $lang_admin_settings['Enable announcement label'] ?></label>
					</div>
				</div>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Announcement heading label'] ?></span></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[announcement_heading]" size="50" maxlength="255" value="<?php echo forum_htmlencode($forum_config['o_announcement_heading']) ?>" /></span>
					</div>
				</div>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box textarea">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Announcement message label'] ?></span><small><?php echo $lang_admin_settings['Announcement message help'] ?></small></label><br />
						<span class="fld-input"><textarea id="fld<?php echo $forum_page['fld_count'] ?>" name="form[announcement_message]" rows="5" cols="55"><?php echo forum_htmlencode($forum_config['o_announcement_message']) ?></textarea></span>
					</div>
				</div>
<?php ($hook = get_hook('aop_announcements_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="save" value="<?php echo $lang_admin_common['Save changes'] ?>" /></span>
			</div>
		</form>
	</div>
<?php
}
else if ($section == 'registration')
{
	// Setup the form
	$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
		array($lang_admin_common['Settings'], forum_link($forum_url['admin_settings_setup'])),
		$lang_admin_common['Registration']
	);

	($hook = get_hook('aop_registration_pre_header_load')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

	define('FORUM_PAGE_SECTION', 'settings');
	define('FORUM_PAGE', 'admin-settings-registration');
	define('FORUM_PAGE_TYPE', 'sectioned');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

	($hook = get_hook('aop_registration_output_start')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_settings_registration']) ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_settings_registration'])) ?>" />
				<input type="hidden" name="form_sent" value="1" />
			</div>
<?php ($hook = get_hook('aop_registration_pre_new_regs_part')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
			<div class="content-head">
				<h2 class="hn"><span><?php printf($lang_admin_settings['Registration head'], $lang_admin_settings['Registration new']) ?></span></h2>
			</div>
			<div class="ct-box">
				<p><?php echo $lang_admin_settings['New reg info'] ?></p>
			</div>
			<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><span><?php echo $lang_admin_settings['Registration new legend'] ?></span></legend>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box checkbox">
						<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[regs_allow]" value="1"<?php if ($forum_config['o_regs_allow'] == '1') echo ' checked="checked"' ?> /></span>
						<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Allow new reg'] ?></span> <?php echo $lang_admin_settings['Allow new reg label'] ?></label>
					</div>
				</div>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box checkbox">
						<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[regs_verify]" value="1"<?php if ($forum_config['o_regs_verify'] == '1') echo ' checked="checked"' ?> /></span>
						<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Verify reg'] ?></span> <?php echo $lang_admin_settings['Verify reg label'] ?></label>
					</div>
				</div>
				<fieldset class="mf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<legend><span><?php echo $lang_admin_settings['Reg e-mail group'] ?></span></legend>
					<div class="mf-box">
						<div class="mf-item">
							<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[allow_banned_email]" value="1"<?php if ($forum_config['p_allow_banned_email'] == '1') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_settings['Allow banned label'] ?></label>
						</div>
						<div class="mf-item">
							<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[allow_dupe_email]" value="1"<?php if ($forum_config['p_allow_dupe_email'] == '1') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_settings['Allow dupe label'] ?></label>
						</div>
					</div>
				</fieldset>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box checkbox">
						<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[regs_report]" value="1"<?php if ($forum_config['o_regs_report'] == '1') echo ' checked="checked"' ?> /></span>
						<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Report new reg'] ?></span> <?php echo $lang_admin_settings['Report new reg label'] ?></label>
					</div>
				</div>
				<fieldset class="mf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<legend><span><?php echo $lang_admin_settings['E-mail setting group'] ?></span></legend>
					<div class="mf-box">
						<div class="mf-item">
							<span class="fld-input"><input type="radio" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[default_email_setting]" value="0"<?php if ($forum_config['o_default_email_setting'] == '0') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_settings['Display e-mail label'] ?></label>
						</div>
						<div class="mf-item">
							<span class="fld-input"><input type="radio" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[default_email_setting]" value="1"<?php if ($forum_config['o_default_email_setting'] == '1') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_settings['Allow form e-mail label'] ?></label>
						</div>
						<div class="mf-item">
							<span class="fld-input"><input type="radio" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[default_email_setting]" value="2"<?php if ($forum_config['o_default_email_setting'] == '2') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_settings['Disallow form e-mail label'] ?></label>
						</div>
					</div>
				</fieldset>
<?php ($hook = get_hook('aop_registration_new_regs_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
				</fieldset>
<?php

// Reset counter
$forum_page['group_count'] = $forum_page['item_count'] = 0;

($hook = get_hook('aop_registration_pre_rules_part')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
			<div class="content-head">
				<h2 class="hn"><span><?php printf($lang_admin_settings['Registration head'], $lang_admin_settings['Registration rules']) ?></span></h2>
			</div>
				<div class="ct-box">
					<p><?php echo $lang_admin_settings['Registration rules info'] ?></p>
				</div>
				<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
					<legend class="group-legend"><span><?php echo $lang_admin_settings['Registration rules legend'] ?></span></legend>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box checkbox">
							<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[rules]" value="1"<?php if ($forum_config['o_rules'] == '1') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Require rules'] ?></span><?php echo $lang_admin_settings['Require rules label'] ?></label>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box textarea">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Compose rules label'] ?></span><small><?php echo $lang_admin_settings['Compose rules help'] ?></small></label><br />
							<span class="fld-input"><textarea id="fld<?php echo $forum_page['fld_count'] ?>" name="form[rules_message]" rows="10" cols="55"><?php echo forum_htmlencode($forum_config['o_rules_message']) ?></textarea></span>
						</div>
					</div>
<?php ($hook = get_hook('aop_registration_rules_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
				</fieldset>
				<div class="frm-buttons">
					<span class="submit"><input type="submit" name="save" value="<?php echo $lang_admin_common['Save changes'] ?>" /></span>
				</div>
		</form>
	</div>
<?php

}

else if ($section == 'maintenance')
{
	// Setup the form
	$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
		array($lang_admin_common['Settings'], forum_link($forum_url['admin_settings_maintenance'])),
		$lang_admin_common['Maintenance mode']
	);

	($hook = get_hook('aop_maintenance_pre_header_load')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

	define('FORUM_PAGE_SECTION', 'management');
	define('FORUM_PAGE', 'admin-settings-maintenance');
	define('FORUM_PAGE_TYPE', 'sectioned');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

	($hook = get_hook('aop_maintenance_output_start')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
	<div class="main-content main-frm">
		<div class="content-head">
			<h2 class="hn"><span><?php echo $lang_admin_settings['Maintenance head'] ?></span></h2>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_settings_maintenance']) ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_settings_maintenance'])) ?>" />
				<input type="hidden" name="form_sent" value="1" />
			</div>
			<div class="ct-box">
				<p class="important"><?php echo $lang_admin_settings['Maintenance mode info'] ?></p>
				<p class="warn"><?php echo $lang_admin_settings['Maintenance mode warn'] ?></p>
			</div>
			<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_admin_settings['Maintenance legend'] ?></strong></legend>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box checkbox">
						<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[maintenance]" value="1"<?php if ($forum_config['o_maintenance'] == '1') echo ' checked="checked"' ?> /></span>
						<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Maintenance mode'] ?></span> <?php echo $lang_admin_settings['Maintenance mode label'] ?></label>
					</div>
				</div>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box textarea">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Maintenance message label'] ?></span><small><?php echo $lang_admin_settings['Maintenance message help'] ?></small></label><br />
						<span class="fld-input"><textarea id="fld<?php echo $forum_page['fld_count'] ?>" name="form[maintenance_message]" rows="5" cols="55"><?php echo forum_htmlencode($forum_config['o_maintenance_message']) ?></textarea></span>
					</div>
				</div>
<?php ($hook = get_hook('aop_maintenance_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="save" value="<?php echo $lang_admin_common['Save changes'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

}

else if ($section == 'email')
{
	// Setup the form
	$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
		array($lang_admin_common['Settings'], forum_link($forum_url['admin_settings_setup'])),
		$lang_admin_common['E-mail']
	);

	($hook = get_hook('aop_email_pre_header_load')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

	define('FORUM_PAGE_SECTION', 'settings');
	define('FORUM_PAGE', 'admin-settings-email');
	define('FORUM_PAGE_TYPE', 'sectioned');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

	($hook = get_hook('aop_email_output_start')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
	<div class="main-content frm parted">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_settings_email']) ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_settings_email'])) ?>" />
				<input type="hidden" name="form_sent" value="1" />
			</div>
<?php ($hook = get_hook('aop_email_pre_addresses_part')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
			<div class="content-head">
				<h2 class="hn"><span><?php printf($lang_admin_settings['E-mail head'], $lang_admin_settings['E-mail addresses']) ?></span></h2>
			</div>
				<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
					<legend class="group-legend"><strong><?php echo $lang_admin_settings['E-mail addresses legend'] ?></strong></legend>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Admin e-mail'] ?></span></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[admin_email]" size="50" maxlength="80" value="<?php echo $forum_config['o_admin_email'] ?>" /></span>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Webmaster e-mail label'] ?></span><small><?php echo $lang_admin_settings['Webmaster e-mail help'] ?></small></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[webmaster_email]" size="50" maxlength="80" value="<?php echo $forum_config['o_webmaster_email'] ?>" /></span>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box textarea">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['Mailing list label'] ?></span><small><?php echo $lang_admin_settings['Mailing list help'] ?></small></label><br />
							<span class="fld-input"><textarea id="fld<?php echo $forum_page['fld_count'] ?>" name="form[mailing_list]" rows="5" cols="55"><?php echo forum_htmlencode($forum_config['o_mailing_list']) ?></textarea></span>
						</div>
					</div>
<?php ($hook = get_hook('aop_email_addresses_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
				</fieldset>
<?php

// Reset counter
$forum_page['group_count'] = $forum_page['item_count'] = 0;

($hook = get_hook('aop_email_pre_smtp_part')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
			<div class="content-head">
				<h2 class="hn"><span><?php printf($lang_admin_settings['E-mail head'], $lang_admin_settings['E-mail server']) ?></span></h2>
			</div>
				<div class="ct-box">
					<p><?php echo $lang_admin_settings['E-mail server info'] ?></p>
				</div>
				<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
					<legend class="group-legend"><strong><?php echo $lang_admin_settings['E-mail server legend'] ?></strong></legend>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['SMTP address label'] ?></span><small><?php echo $lang_admin_settings['SMTP address help'] ?></small></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[smtp_host]" size="35" maxlength="100" value="<?php echo forum_htmlencode($forum_config['o_smtp_host']) ?>" /></span>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['SMTP username label'] ?></span><small><?php echo $lang_admin_settings['SMTP username help'] ?></small></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[smtp_user]" size="35" maxlength="50" value="<?php echo forum_htmlencode($forum_config['o_smtp_user']) ?>" /></span>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['SMTP password label'] ?></span><small><?php echo $lang_admin_settings['SMTP password help'] ?></small></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[smtp_pass]" size="35" maxlength="50" value="<?php echo forum_htmlencode($forum_config['o_smtp_pass']) ?>" /></span>
						</div>
					</div>
					<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<div class="sf-box checkbox">
							<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="form[smtp_ssl]" value="1"<?php if ($forum_config['o_smtp_ssl'] == '1') echo ' checked="checked"' ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_settings['SMTP SSL'] ?></span> <?php echo $lang_admin_settings['SMTP SSL label'] ?></label>
						</div>
					</div>
<?php ($hook = get_hook('aop_email_smtp_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
				</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="save" value="<?php echo $lang_admin_common['Save changes'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

}

($hook = get_hook('aop_new_section')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
