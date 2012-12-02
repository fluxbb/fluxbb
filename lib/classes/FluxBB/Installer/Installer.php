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

namespace FluxBB\Installer;

use FluxBB\Core;
use FluxBB\Database;
use FluxBB\Models\Group;
use FluxBB\Models\User;

class Installer
{

	protected $app;

	public function __construct(Application $app)
	{
		$this->app = $app;
	}

	public function writeDatabaseConfig(array $configuration)
	{
		$array = array('connection' => $configuration);
		$config = '<?php'."\n\n".'return '.var_export($array, true).';'."\n";

		$confFile = $this->app['path.config'].'database.php';

		$success = $this->app['files']->put($confFile, $config);

		if (!$success)
		{
			throw new RuntimeException('Unable to write config file. Please create the file "'.$confFile.'" with the following contents:'."\n\n".$config);
		}
	}

	public function createDatabaseTables()
	{
		$migrationClasses = array(
			'FluxBB\Migrations\Install\Bans',
			'FluxBB\Migrations\Install\Categories',
			'FluxBB\Migrations\Install\Censoring',
			'FluxBB\Migrations\Install\Config',
			'FluxBB\Migrations\Install\ForumPerms',
			'FluxBB\Migrations\Install\ForumSubscriptions',
			'FluxBB\Migrations\Install\Forums',
			'FluxBB\Migrations\Install\Groups',
			'FluxBB\Migrations\Install\Posts',
			'FluxBB\Migrations\Install\Reports',
			'FluxBB\Migrations\Install\Sessions',
			'FluxBB\Migrations\Install\TopicSubscriptions',
			'FluxBB\Migrations\Install\Topics',
			'FluxBB\Migrations\Install\Users',
		);

		foreach ($migrationClasses as $class)
		{
			$instance = new $class;
			$instance->up();
		}
	}

	public function createUserGroups()
	{
		// Insert the three preset groups
		$admin_group = Group::create(array(
			'g_id'						=> Group::ADMIN,
			'g_title'					=> t('seed_data.administrators'),
			'g_user_title'				=> t('seed_data.administrator'),
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
			'g_title'					=> t('seed_data.moderators'),
			'g_user_title'				=> t('seed_data.moderator'),
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
			'g_title'					=> t('seed_data.members'),
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

	public function setBoardInfo(array $board)
	{
		// Enable/disable avatars depending on file_uploads setting in PHP configuration
		$avatars = in_array(strtolower(@ini_get('file_uploads')), array('on', 'true', '1')) ? 1 : 0;

		// Insert config data
		$config = array(
			'o_cur_version'				=> Core::version(),
			'o_board_title'				=> $board['title'],
			'o_board_desc'				=> $board['description'],
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
			'o_default_lang'			=> $this->app['config']['app.locale'],
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
			'o_announcement_message'	=> t('seed_data.announcement'),
			'o_rules'					=> 0,
			'o_rules_message'			=> t('seed_data.rules'),
			'o_maintenance'				=> 0,
			'o_maintenance_message'		=> t('seed_data.maintenance_message'),
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
			Database::table('config')->insert(compact('conf_name', 'conf_value'));
		}
	}

	public function createAdminUser(array $user)
	{
		// Create admin user
		$admin = array(
			'username'			=> $user['username'],
			'password'			=> $user['password'],
			'email'				=> $user['email'],
			'language'			=> $this->app['config']['app.locale'],
			'style'				=> 'Air',
			'registered'		=> $this->app['request']->server('REQUEST_TIME'),
			'registration_ip'	=> $this->app['request']->getClientIp(),
			'last_visit'		=> $this->app['request']->server('REQUEST_TIME'),
		);

		$adminGroup = Group::find(Group::ADMIN);

		if (is_null($adminGroup))
		{
			throw new \LogicException('Could not find admin group.');
		}

		$adminGroup->users()->insert($admin);
	}
}
