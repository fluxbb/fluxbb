<?php
/**
 * FluxBB - fast, light, user-friendly PHP forum software
 * Copyright (C) 2008-2012 FluxBB.org
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public license for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category	FluxBB
 * @package		Core
 * @copyright	Copyright (c) 2008-2012 FluxBB (http://fluxbb.org)
 * @license		http://www.gnu.org/licenses/gpl.html	GNU General Public License
 */

namespace FluxBB\Tasks;

use FluxBB\Models\Category,
	FluxBB\Models\Config,
	FluxBB\Models\Forum,
	FluxBB\Models\Group,
	FluxBB\Models\Post,
	FluxBB\Models\Topic,
	FluxBB\Models\User;

class Install extends Base
{
	
	public function run($arguments = array())
	{
		// Nothing here. Move on.
	}

	public function config($arguments = array())
	{
		if (count($arguments) < 4)
		{
			throw new BadMethodCallException('At least four arguments expected.');
		}

		$credentials = explode(':', $arguments[3], 2);
		$username = $credentials[0];
		$password = isset($credentials[1]) ? $credentials[1] : '';

		$prefix = isset($arguments[4]) ? $arguments[4] : '';

		$conf = array(
			'default'		=> 'fluxbb_'.$arguments[0],
			'connections'	=> array(
				'fluxbb_'.$arguments[0]	=> array(
					'driver'	=> $arguments[0],
					'host'		=> $arguments[1],
					'database'	=> $arguments[2],
					'username'	=> $username,
					'password'	=> $password,
					'charset'	=> 'utf8',
					'prefix'	=> $prefix,
				),
			),
		);

		$config = '<?php'."\n\n".'return '.var_export($conf, true).';'."\n";

		$conf_dir = path('app').'config/fluxbb/';
		$conf_file = $conf_dir.'database.php';

		$dir_exists = File::mkdir($conf_dir);
		$file_exists = File::put($conf_dir.'database.php', $config);

		if (!$dir_exists || !$file_exists)
		{
			throw new RuntimeException('Unable to write config file. Please create the file "'.$conf_file.'" with the following contents:'."\n\n".$config);
		}
	}

	public function database($arguments = array())
	{
		$this->structure();

		$this->seed_groups();
		$this->seed_config();
	}

	public function admin($arguments = array())
	{
		if (count($arguments) != 3)
		{
			throw new BadMethodCallException('Exactly three arguments expected.');
		}

		$username = $arguments[0];
		$password = $arguments[1];
		$email = $arguments[2];
		
		// Create admin user
		$admin_user = User::create(array(
			'username'			=> $username,
			'password'			=> $password,
			'email'				=> $email,
			'language'			=> Laravel\Config::get('application.language'),
			'style'				=> 'Air',
			'last_post'			=> Request::time(),
			'registered'		=> Request::time(),
			'registration_ip'	=> Request::ip(),
			'last_visit'		=> Request::time(),
		));

		$admin_group = Group::find(Group::ADMIN);

		if (is_null($admin_group))
		{
			throw new LogicException('Could not find admin group.');
		}

		$admin_group->users()->insert($admin_user);
	}

	public function board($arguments = array())
	{
		if (count($arguments) != 2)
		{
			throw new BadMethodCallException('Exactly two arguments expected.');
		}

		Config::set('o_board_title', $arguments[0]);
		Config::set('o_board_desc', $arguments[1]);

		Config::save();
	}

	protected function structure()
	{
		foreach (new FilesystemIterator($this->migration_path()) as $file)
		{
			$migration = basename($file->getFileName(), '.php');

			$this->log('Install '.$migration.'...');

			$class = 'FluxBB_Install_'.Str::classify($migration);
			include_once $file;

			$instance = new $class;
			$instance->up();
		}
	}

	protected function seed_groups()
	{
		// Insert the three preset groups
		$admin_group = Group::create(array(
			'g_id'						=> Group::ADMIN,
			'g_title'					=> trans('seed_data.administrators'),
			'g_user_title'				=> trans('seed_data.administrator'),
			'g_promote_min_posts'		=> 0,
			'g_promote_next_group'		=> 0,
			'g_moderator'				=> 0,
			'g_mod_edit_users'			=> 0,
			'g_mod_rename_users'		=> 0,
			'g_mod_change_passwords'	=> 0,
			'g_mod_ban_users'			=> 0,
			'g_read_board'				=> 1,
			'g_view_users'				=> 1,
			'g_post_replies'			=> 1,
			'g_post_topics'				=> 1,
			'g_edit_posts'				=> 1,
			'g_delete_posts'			=> 1,
			'g_delete_topics'			=> 1,
			'g_post_links'				=> 1,
			'g_set_title'				=> 1,
			'g_search'					=> 1,
			'g_search_users'			=> 1,
			'g_send_email'				=> 1,
			'g_post_flood'				=> 0,
			'g_search_flood'			=> 0,
			'g_email_flood'				=> 0,
			'g_report_flood'			=> 0,
		));

		$moderator_group = Group::create(array(
			'g_id'						=> Group::MOD,
			'g_title'					=> trans('seed_data.moderators'),
			'g_user_title'				=> trans('seed_data.moderator'),
			'g_promote_min_posts'		=> 0,
			'g_promote_next_group'		=> 0,
			'g_moderator'				=> 1,
			'g_mod_edit_users'			=> 1,
			'g_mod_rename_users'		=> 1,
			'g_mod_change_passwords'	=> 1,
			'g_mod_ban_users'			=> 1,
			'g_read_board'				=> 1,
			'g_view_users'				=> 1,
			'g_post_replies'			=> 1,
			'g_post_topics'				=> 1,
			'g_edit_posts'				=> 1,
			'g_delete_posts'			=> 1,
			'g_delete_topics'			=> 1,
			'g_post_links'				=> 1,
			'g_set_title'				=> 1,
			'g_search'					=> 1,
			'g_search_users'			=> 1,
			'g_send_email'				=> 1,
			'g_post_flood'				=> 0,
			'g_search_flood'			=> 0,
			'g_email_flood'				=> 0,
			'g_report_flood'			=> 0,
		));

		$member_group = Group::create(array(
			'g_id'						=> Group::MEMBER,
			'g_title'					=> trans('seed_data.members'),
			'g_user_title'				=> null,
			'g_promote_min_posts'		=> 0,
			'g_promote_next_group'		=> 0,
			'g_moderator'				=> 0,
			'g_mod_edit_users'			=> 0,
			'g_mod_rename_users'		=> 0,
			'g_mod_change_passwords'	=> 0,
			'g_mod_ban_users'			=> 0,
			'g_read_board'				=> 1,
			'g_view_users'				=> 1,
			'g_post_replies'			=> 1,
			'g_post_topics'				=> 1,
			'g_edit_posts'				=> 1,
			'g_delete_posts'			=> 1,
			'g_delete_topics'			=> 1,
			'g_post_links'				=> 1,
			'g_set_title'				=> 0,
			'g_search'					=> 1,
			'g_search_users'			=> 1,
			'g_send_email'				=> 1,
			'g_post_flood'				=> 60,
			'g_search_flood'			=> 30,
			'g_email_flood'				=> 60,
			'g_report_flood'			=> 60,
		));
	}

	protected function seed_config()
	{
		// Enable/disable avatars depending on file_uploads setting in PHP configuration
		$avatars = in_array(strtolower(@ini_get('file_uploads')), array('on', 'true', '1')) ? 1 : 0;

		// Insert config data
		$config = array(
			'o_cur_version'				=> FLUXBB_VERSION,
			'o_board_title'				=> trans('seed_data.board_title'),
			'o_board_desc'				=> trans('seed_data.board_desc'),
			'o_default_timezone'		=> 0,
			'o_time_format'				=> 'H:i:s',
			'o_date_format'				=> 'Y-m-d',
			'o_timeout_visit'			=> 1800,
			'o_timeout_online'			=> 300,
			'o_redirect_delay'			=> 1,
			'o_show_version'			=> 0,
			'o_show_user_info'			=> 1,
			'o_show_post_count'			=> 1,
			'o_signatures'				=> 1,
			'o_smilies'					=> 1,
			'o_smilies_sig'				=> 1,
			'o_make_links'				=> 1,
			'o_default_lang'			=> Laravel\Config::get('application.language'),
			'o_default_style'			=> 'Air', // FIXME
			'o_default_user_group'		=> 4,
			'o_topic_review'			=> 15,
			'o_disp_topics_default'		=> 30,
			'o_disp_posts_default'		=> 25,
			'o_indent_num_spaces'		=> 4,
			'o_quote_depth'				=> 3,
			'o_quickpost'				=> 1,
			'o_users_online'			=> 1,
			'o_censoring'				=> 0,
			'o_show_dot'				=> 0,
			'o_topic_views'				=> 1,
			'o_quickjump'				=> 1,
			'o_gzip'					=> 0,
			'o_additional_navlinks'		=> '',
			'o_report_method'			=> 0,
			'o_regs_report'				=> 0,
			'o_default_email_setting'	=> 1,
			'o_mailing_list'			=> 'email', // FIXME
			'o_avatars'					=> $avatars,
			'o_avatars_dir'				=> 'img/avatars',
			'o_avatars_width'			=> 60,
			'o_avatars_height'			=> 60,
			'o_avatars_size'			=> 10240,
			'o_search_all_forums'		=> 1,
			'o_admin_email'				=> 'email', // FIXME
			'o_webmaster_email'			=> 'email', // FIXME
			'o_forum_subscriptions'		=> 1,
			'o_topic_subscriptions'		=> 1,
			'o_smtp_host'				=> NULL,
			'o_smtp_user'				=> NULL,
			'o_smtp_pass'				=> NULL,
			'o_smtp_ssl'				=> 0,
			'o_regs_allow'				=> 1,
			'o_regs_verify'				=> 0,
			'o_announcement'			=> 0,
			'o_announcement_message'	=> trans('seed_data.announcement'),
			'o_rules'					=> 0,
			'o_rules_message'			=> trans('seed_data.rules'),
			'o_maintenance'				=> 0,
			'o_maintenance_message'		=> trans('seed_data.maintenance_message'),
			'o_default_dst'				=> 0,
			'o_feed_type'				=> 2,
			'o_feed_ttl'				=> 0,
			'p_message_bbcode'			=> 1,
			'p_message_img_tag'			=> 1,
			'p_message_all_caps'		=> 1,
			'p_subject_all_caps'		=> 1,
			'p_sig_all_caps'			=> 1,
			'p_sig_bbcode'				=> 1,
			'p_sig_img_tag'				=> 0,
			'p_sig_length'				=> 400,
			'p_sig_lines'				=> 4,
			'p_allow_banned_email'		=> 1,
			'p_allow_dupe_email'		=> 0,
			'p_force_guest_email'		=> 1
		);

		foreach ($config as $conf_name => $conf_value)
		{
			Config::set($conf_name, $conf_value);
		}

		Config::save();
	}

	protected function migration_path()
	{
		return Bundle::path('fluxbb').'migrations'.DS.'install'.DS;
	}

}
