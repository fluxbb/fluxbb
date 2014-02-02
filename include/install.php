<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

class Installer
{
	// The FluxBB version this script installs
	const FORUM_VERSION = '1.5.6';

	// Internal revision number of services
	const FORUM_DB_REVISION = 20;
	const FORUM_SI_REVISION = 2;
	const FORUM_PARSER_REVISION = 2;

	// Minimum require version of dependencies
	const MIN_PHP_VERSION = '4.4.0';
	const MIN_MYSQL_VERSION = '4.1.2';
	const MIN_PGSQL_VERSION = '7.0.0';

	public static function is_supported_php_version() {
		return function_exists('version_compare') && version_compare(PHP_VERSION, MIN_PHP_VERSION, '>=');
	}

	public static function determine_database_extensions()
	{
		// Determine available database extensions
		$db_extensions = array();

		if (function_exists('mysqli_connect'))
		{
			$db_extensions[] = array('mysqli', 'MySQL Improved');
			$db_extensions[] = array('mysqli_innodb', 'MySQL Improved (InnoDB)');
		}

		if (function_exists('mysql_connect'))
		{
			$db_extensions[] = array('mysql', 'MySQL Standard');
			$db_extensions[] = array('mysql_innodb', 'MySQL Standard (InnoDB)');
		}

		if (function_exists('sqlite_open'))
			$db_extensions[] = array('sqlite', 'SQLite');

		if (function_exists('pg_connect'))
			$db_extensions[] = array('pgsql', 'PostgreSQL');

		return $db_extensions;
	}

	public static function generate_config_file($db_type, $db_host, $db_name, $db_username, $db_password, $db_prefix = '', $cookie_name = false, $cookie_seed = false)
	{
		if ($cookie_name === false)
			$cookie_name = 'pun_cookie_'.random_key(6, false, true);

		if ($cookie_seed === false)
			$cookie_seed = random_key(16, false, true);

		return '<?php'."\n\n".'$db_type = \''.$db_type."';\n".'$db_host = \''.$db_host."';\n".'$db_name = \''.addslashes($db_name)."';\n".'$db_username = \''.addslashes($db_username)."';\n".'$db_password = \''.addslashes($db_password)."';\n".'$db_prefix = \''.addslashes($db_prefix)."';\n".'$p_connect = false;'."\n\n".'$cookie_name = '."'".$cookie_name."';\n".'$cookie_domain = '."'';\n".'$cookie_path = '."'/';\n".'$cookie_secure = 0;'."\n".'$cookie_seed = \''.$cookie_seed."';\n\ndefine('PUN', 1);\n";
	}

	public static function create_database(
		$db_type, $db_host, $db_name, $db_username, $db_password, $db_prefix,
		$title, $description, $default_lang, $default_style, $username, $password, $email, $avatars, $base_url
	) {
		global $lang_install;

		// Load the appropriate DB layer class
		switch ($db_type)
		{
			case 'mysql':
				require PUN_ROOT.'include/dblayer/mysql.php';
				break;

			case 'mysql_innodb':
				require PUN_ROOT.'include/dblayer/mysql_innodb.php';
				break;

			case 'mysqli':
				require PUN_ROOT.'include/dblayer/mysqli.php';
				break;

			case 'mysqli_innodb':
				require PUN_ROOT.'include/dblayer/mysqli_innodb.php';
				break;

			case 'pgsql':
				require PUN_ROOT.'include/dblayer/pgsql.php';
				break;

			case 'sqlite':
				require PUN_ROOT.'include/dblayer/sqlite.php';
				break;

			default:
				error(sprintf($lang_install['DB type not valid'], pun_htmlspecialchars($db_type)));
		}

		// Create the database object (and connect/select db)
		$db = new DBLayer($db_host, $db_username, $db_password, $db_name, $db_prefix, false);

		// Validate prefix
		if (strlen($db_prefix) > 0 && (!preg_match('%^[a-zA-Z_][a-zA-Z0-9_]*$%', $db_prefix) || strlen($db_prefix) > 40))
			error(sprintf($lang_install['Table prefix error'], $db->prefix));

		// Do some DB type specific checks
		switch ($db_type)
		{
			case 'mysql':
			case 'mysqli':
			case 'mysql_innodb':
			case 'mysqli_innodb':
				$mysql_info = $db->get_version();
				if (version_compare($mysql_info['version'], Installer::MIN_MYSQL_VERSION, '<'))
					error(sprintf($lang_install['You are running error'], 'MySQL', $mysql_info['version'], Installer::FORUM_VERSION, Installer::MIN_MYSQL_VERSION));
				break;

			case 'pgsql':
				$pgsql_info = $db->get_version();
				if (version_compare($pgsql_info['version'], Installer::MIN_PGSQL_VERSION, '<'))
					error(sprintf($lang_install['You are running error'], 'PostgreSQL', $pgsql_info['version'], Installer::FORUM_VERSION, Installer::MIN_PGSQL_VERSION));
				break;

			case 'sqlite':
				if (strtolower($db_prefix) == 'sqlite_')
					error($lang_install['Prefix reserved']);
				break;
		}


		// Make sure FluxBB isn't already installed
		$result = $db->query('SELECT 1 FROM '.$db_prefix.'users WHERE id=1');
		if ($db->num_rows($result))
			error(sprintf($lang_install['Existing table error'], $db_prefix, $db_name));

		// Check if InnoDB is available
		if ($db_type == 'mysql_innodb' || $db_type == 'mysqli_innodb')
		{
			$result = $db->query('SHOW VARIABLES LIKE \'have_innodb\'');
			list (, $result) = $db->fetch_row($result);
			if ((strtoupper($result) != 'YES'))
				error($lang_install['InnoDB off']);
		}


		// Start a transaction
		$db->start_transaction();


		// Create all tables
		$schema = array(
			'FIELDS'		=> array(
				'id'			=> array(
					'datatype'		=> 'SERIAL',
					'allow_null'	=> false
				),
				'username'		=> array(
					'datatype'		=> 'VARCHAR(200)',
					'allow_null'	=> true
				),
				'ip'			=> array(
					'datatype'		=> 'VARCHAR(255)',
					'allow_null'	=> true
				),
				'email'			=> array(
					'datatype'		=> 'VARCHAR(80)',
					'allow_null'	=> true
				),
				'message'		=> array(
					'datatype'		=> 'VARCHAR(255)',
					'allow_null'	=> true
				),
				'expire'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> true
				),
				'ban_creator'	=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				)
			),
			'PRIMARY KEY'	=> array('id'),
			'INDEXES'		=> array(
				'username_idx'	=> array('username')
			)
		);

		if ($db_type == 'mysql' || $db_type == 'mysqli' || $db_type == 'mysql_innodb' || $db_type == 'mysqli_innodb')
			$schema['INDEXES']['username_idx'] = array('username(25)');

		$db->create_table('bans', $schema) or error('Unable to create bans table', __FILE__, __LINE__, $db->error());


		$schema = array(
			'FIELDS'		=> array(
				'id'			=> array(
					'datatype'		=> 'SERIAL',
					'allow_null'	=> false
				),
				'cat_name'		=> array(
					'datatype'		=> 'VARCHAR(80)',
					'allow_null'	=> false,
					'default'		=> '\'New Category\''
				),
				'disp_position'	=> array(
					'datatype'		=> 'INT(10)',
					'allow_null'	=> false,
					'default'		=> '0'
				)
			),
			'PRIMARY KEY'	=> array('id')
		);

		$db->create_table('categories', $schema) or error('Unable to create categories table', __FILE__, __LINE__, $db->error());


		$schema = array(
			'FIELDS'		=> array(
				'id'			=> array(
					'datatype'		=> 'SERIAL',
					'allow_null'	=> false
				),
				'search_for'	=> array(
					'datatype'		=> 'VARCHAR(60)',
					'allow_null'	=> false,
					'default'		=> '\'\''
				),
				'replace_with'	=> array(
					'datatype'		=> 'VARCHAR(60)',
					'allow_null'	=> false,
					'default'		=> '\'\''
				)
			),
			'PRIMARY KEY'	=> array('id')
		);

		$db->create_table('censoring', $schema) or error('Unable to create censoring table', __FILE__, __LINE__, $db->error());


		$schema = array(
			'FIELDS'		=> array(
				'conf_name'		=> array(
					'datatype'		=> 'VARCHAR(255)',
					'allow_null'	=> false,
					'default'		=> '\'\''
				),
				'conf_value'	=> array(
					'datatype'		=> 'TEXT',
					'allow_null'	=> true
				)
			),
			'PRIMARY KEY'	=> array('conf_name')
		);

		$db->create_table('config', $schema) or error('Unable to create config table', __FILE__, __LINE__, $db->error());


		$schema = array(
			'FIELDS'		=> array(
				'group_id'		=> array(
					'datatype'		=> 'INT(10)',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'forum_id'		=> array(
					'datatype'		=> 'INT(10)',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'read_forum'	=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '1'
				),
				'post_replies'	=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '1'
				),
				'post_topics'	=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '1'
				)
			),
			'PRIMARY KEY'	=> array('group_id', 'forum_id')
		);

		$db->create_table('forum_perms', $schema) or error('Unable to create forum_perms table', __FILE__, __LINE__, $db->error());


		$schema = array(
			'FIELDS'		=> array(
				'id'			=> array(
					'datatype'		=> 'SERIAL',
					'allow_null'	=> false
				),
				'forum_name'	=> array(
					'datatype'		=> 'VARCHAR(80)',
					'allow_null'	=> false,
					'default'		=> '\'New forum\''
				),
				'forum_desc'	=> array(
					'datatype'		=> 'TEXT',
					'allow_null'	=> true
				),
				'redirect_url'	=> array(
					'datatype'		=> 'VARCHAR(100)',
					'allow_null'	=> true
				),
				'moderators'	=> array(
					'datatype'		=> 'TEXT',
					'allow_null'	=> true
				),
				'num_topics'	=> array(
					'datatype'		=> 'MEDIUMINT(8) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'num_posts'		=> array(
					'datatype'		=> 'MEDIUMINT(8) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'last_post'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> true
				),
				'last_post_id'	=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> true
				),
				'last_poster'	=> array(
					'datatype'		=> 'VARCHAR(200)',
					'allow_null'	=> true
				),
				'sort_by'		=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'disp_position'	=> array(
					'datatype'		=> 'INT(10)',
					'allow_null'	=> false,
					'default'		=>	'0'
				),
				'cat_id'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=>	'0'
				)
			),
			'PRIMARY KEY'	=> array('id')
		);

		$db->create_table('forums', $schema) or error('Unable to create forums table', __FILE__, __LINE__, $db->error());


		$schema = array(
			'FIELDS'		=> array(
				'g_id'						=> array(
					'datatype'		=> 'SERIAL',
					'allow_null'	=> false
				),
				'g_title'					=> array(
					'datatype'		=> 'VARCHAR(50)',
					'allow_null'	=> false,
					'default'		=> '\'\''
				),
				'g_user_title'				=> array(
					'datatype'		=> 'VARCHAR(50)',
					'allow_null'	=> true
				),
				'g_promote_min_posts'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'g_promote_next_group'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'g_moderator'				=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'g_mod_edit_users'			=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'g_mod_rename_users'		=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'g_mod_change_passwords'	=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'g_mod_ban_users'			=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'g_read_board'				=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '1'
				),
				'g_view_users'				=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '1'
				),
				'g_post_replies'			=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '1'
				),
				'g_post_topics'				=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '1'
				),
				'g_edit_posts'				=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '1'
				),
				'g_delete_posts'			=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '1'
				),
				'g_delete_topics'			=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '1'
				),
				'g_post_links'				=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '1'
				),
				'g_set_title'				=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '1'
				),
				'g_search'					=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '1'
				),
				'g_search_users'			=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '1'
				),
				'g_send_email'				=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '1'
				),
				'g_post_flood'				=> array(
					'datatype'		=> 'SMALLINT(6)',
					'allow_null'	=> false,
					'default'		=> '30'
				),
				'g_search_flood'			=> array(
					'datatype'		=> 'SMALLINT(6)',
					'allow_null'	=> false,
					'default'		=> '30'
				),
				'g_email_flood'				=> array(
					'datatype'		=> 'SMALLINT(6)',
					'allow_null'	=> false,
					'default'		=> '60'
				),
				'g_report_flood'			=> array(
					'datatype'		=> 'SMALLINT(6)',
					'allow_null'	=> false,
					'default'		=> '60'
				)
			),
			'PRIMARY KEY'	=> array('g_id')
		);

		$db->create_table('groups', $schema) or error('Unable to create groups table', __FILE__, __LINE__, $db->error());


		$schema = array(
			'FIELDS'		=> array(
				'user_id'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '1'
				),
				'ident'			=> array(
					'datatype'		=> 'VARCHAR(200)',
					'allow_null'	=> false,
					'default'		=> '\'\''
				),
				'logged'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'idle'			=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'last_post'			=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> true
				),
				'last_search'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> true
				),
			),
			'UNIQUE KEYS'	=> array(
				'user_id_ident_idx'	=> array('user_id', 'ident')
			),
			'INDEXES'		=> array(
				'ident_idx'		=> array('ident'),
				'logged_idx'	=> array('logged')
			)
		);

		if ($db_type == 'mysql' || $db_type == 'mysqli' || $db_type == 'mysql_innodb' || $db_type == 'mysqli_innodb')
		{
			$schema['UNIQUE KEYS']['user_id_ident_idx'] = array('user_id', 'ident(25)');
			$schema['INDEXES']['ident_idx'] = array('ident(25)');
		}

		if ($db_type == 'mysql_innodb' || $db_type == 'mysqli_innodb')
			$schema['ENGINE'] = 'InnoDB';

		$db->create_table('online', $schema) or error('Unable to create online table', __FILE__, __LINE__, $db->error());


		$schema = array(
			'FIELDS'		=> array(
				'id'			=> array(
					'datatype'		=> 'SERIAL',
					'allow_null'	=> false
				),
				'poster'		=> array(
					'datatype'		=> 'VARCHAR(200)',
					'allow_null'	=> false,
					'default'		=> '\'\''
				),
				'poster_id'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '1'
				),
				'poster_ip'		=> array(
					'datatype'		=> 'VARCHAR(39)',
					'allow_null'	=> true
				),
				'poster_email'	=> array(
					'datatype'		=> 'VARCHAR(80)',
					'allow_null'	=> true
				),
				'message'		=> array(
					'datatype'		=> 'MEDIUMTEXT',
					'allow_null'	=> true
				),
				'hide_smilies'	=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'posted'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'edited'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> true
				),
				'edited_by'		=> array(
					'datatype'		=> 'VARCHAR(200)',
					'allow_null'	=> true
				),
				'topic_id'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				)
			),
			'PRIMARY KEY'	=> array('id'),
			'INDEXES'		=> array(
				'topic_id_idx'	=> array('topic_id'),
				'multi_idx'		=> array('poster_id', 'topic_id')
			)
		);

		$db->create_table('posts', $schema) or error('Unable to create posts table', __FILE__, __LINE__, $db->error());


		$schema = array(
			'FIELDS'		=> array(
				'id'			=> array(
					'datatype'		=> 'SERIAL',
					'allow_null'	=> false
				),
				'post_id'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'topic_id'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'forum_id'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'reported_by'	=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'created'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'message'		=> array(
					'datatype'		=> 'TEXT',
					'allow_null'	=> true
				),
				'zapped'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> true
				),
				'zapped_by'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> true
				)
			),
			'PRIMARY KEY'	=> array('id'),
			'INDEXES'		=> array(
				'zapped_idx'	=> array('zapped')
			)
		);

		$db->create_table('reports', $schema) or error('Unable to create reports table', __FILE__, __LINE__, $db->error());


		$schema = array(
			'FIELDS'		=> array(
				'id'			=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'ident'			=> array(
					'datatype'		=> 'VARCHAR(200)',
					'allow_null'	=> false,
					'default'		=> '\'\''
				),
				'search_data'	=> array(
					'datatype'		=> 'MEDIUMTEXT',
					'allow_null'	=> true
				)
			),
			'PRIMARY KEY'	=> array('id'),
			'INDEXES'		=> array(
				'ident_idx'	=> array('ident')
			)
		);

		if ($db_type == 'mysql' || $db_type == 'mysqli' || $db_type == 'mysql_innodb' || $db_type == 'mysqli_innodb')
			$schema['INDEXES']['ident_idx'] = array('ident(8)');

		$db->create_table('search_cache', $schema) or error('Unable to create search_cache table', __FILE__, __LINE__, $db->error());


		$schema = array(
			'FIELDS'		=> array(
				'post_id'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'word_id'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'subject_match'	=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '0'
				)
			),
			'INDEXES'		=> array(
				'word_id_idx'	=> array('word_id'),
				'post_id_idx'	=> array('post_id')
			)
		);

		$db->create_table('search_matches', $schema) or error('Unable to create search_matches table', __FILE__, __LINE__, $db->error());


		$schema = array(
			'FIELDS'		=> array(
				'id'			=> array(
					'datatype'		=> 'SERIAL',
					'allow_null'	=> false
				),
				'word'			=> array(
					'datatype'		=> 'VARCHAR(20)',
					'allow_null'	=> false,
					'default'		=> '\'\'',
					'collation'		=> 'bin'
				)
			),
			'PRIMARY KEY'	=> array('word'),
			'INDEXES'		=> array(
				'id_idx'	=> array('id')
			)
		);

		if ($db_type == 'sqlite')
		{
			$schema['PRIMARY KEY'] = array('id');
			$schema['UNIQUE KEYS'] = array('word_idx'	=> array('word'));
		}

		$db->create_table('search_words', $schema) or error('Unable to create search_words table', __FILE__, __LINE__, $db->error());


		$schema = array(
			'FIELDS'		=> array(
				'user_id'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'topic_id'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				)
			),
			'PRIMARY KEY'	=> array('user_id', 'topic_id')
		);

		$db->create_table('topic_subscriptions', $schema) or error('Unable to create topic subscriptions table', __FILE__, __LINE__, $db->error());


		$schema = array(
			'FIELDS'		=> array(
				'user_id'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'forum_id'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				)
			),
			'PRIMARY KEY'	=> array('user_id', 'forum_id')
		);

		$db->create_table('forum_subscriptions', $schema) or error('Unable to create forum subscriptions table', __FILE__, __LINE__, $db->error());


		$schema = array(
			'FIELDS'		=> array(
				'id'			=> array(
					'datatype'		=> 'SERIAL',
					'allow_null'	=> false
				),
				'poster'		=> array(
					'datatype'		=> 'VARCHAR(200)',
					'allow_null'	=> false,
					'default'		=> '\'\''
				),
				'subject'		=> array(
					'datatype'		=> 'VARCHAR(255)',
					'allow_null'	=> false,
					'default'		=> '\'\''
				),
				'posted'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'first_post_id'	=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'last_post'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'last_post_id'	=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'last_poster'	=> array(
					'datatype'		=> 'VARCHAR(200)',
					'allow_null'	=> true
				),
				'num_views'		=> array(
					'datatype'		=> 'MEDIUMINT(8) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'num_replies'	=> array(
					'datatype'		=> 'MEDIUMINT(8) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'closed'		=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'sticky'		=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'moved_to'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> true
				),
				'forum_id'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				)
			),
			'PRIMARY KEY'	=> array('id'),
			'INDEXES'		=> array(
				'forum_id_idx'		=> array('forum_id'),
				'moved_to_idx'		=> array('moved_to'),
				'last_post_idx'		=> array('last_post'),
				'first_post_id_idx'	=> array('first_post_id')
			)
		);

		$db->create_table('topics', $schema) or error('Unable to create topics table', __FILE__, __LINE__, $db->error());


		$schema = array(
			'FIELDS'		=> array(
				'id'				=> array(
					'datatype'		=> 'SERIAL',
					'allow_null'	=> false
				),
				'group_id'			=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '3'
				),
				'username'			=> array(
					'datatype'		=> 'VARCHAR(200)',
					'allow_null'	=> false,
					'default'		=> '\'\''
				),
				'password'			=> array(
					'datatype'		=> 'VARCHAR(40)',
					'allow_null'	=> false,
					'default'		=> '\'\''
				),
				'email'				=> array(
					'datatype'		=> 'VARCHAR(80)',
					'allow_null'	=> false,
					'default'		=> '\'\''
				),
				'title'				=> array(
					'datatype'		=> 'VARCHAR(50)',
					'allow_null'	=> true
				),
				'realname'			=> array(
					'datatype'		=> 'VARCHAR(40)',
					'allow_null'	=> true
				),
				'url'				=> array(
					'datatype'		=> 'VARCHAR(100)',
					'allow_null'	=> true
				),
				'jabber'			=> array(
					'datatype'		=> 'VARCHAR(80)',
					'allow_null'	=> true
				),
				'icq'				=> array(
					'datatype'		=> 'VARCHAR(12)',
					'allow_null'	=> true
				),
				'msn'				=> array(
					'datatype'		=> 'VARCHAR(80)',
					'allow_null'	=> true
				),
				'aim'				=> array(
					'datatype'		=> 'VARCHAR(30)',
					'allow_null'	=> true
				),
				'yahoo'				=> array(
					'datatype'		=> 'VARCHAR(30)',
					'allow_null'	=> true
				),
				'location'			=> array(
					'datatype'		=> 'VARCHAR(30)',
					'allow_null'	=> true
				),
				'signature'			=> array(
					'datatype'		=> 'TEXT',
					'allow_null'	=> true
				),
				'disp_topics'		=> array(
					'datatype'		=> 'TINYINT(3) UNSIGNED',
					'allow_null'	=> true
				),
				'disp_posts'		=> array(
					'datatype'		=> 'TINYINT(3) UNSIGNED',
					'allow_null'	=> true
				),
				'email_setting'		=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '1'
				),
				'notify_with_post'	=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'auto_notify'		=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'show_smilies'		=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '1'
				),
				'show_img'			=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '1'
				),
				'show_img_sig'		=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '1'
				),
				'show_avatars'		=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '1'
				),
				'show_sig'			=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '1'
				),
				'timezone'			=> array(
					'datatype'		=> 'FLOAT',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'dst'				=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'time_format'		=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'date_format'		=> array(
					'datatype'		=> 'TINYINT(1)',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'language'			=> array(
					'datatype'		=> 'VARCHAR(25)',
					'allow_null'	=> false,
					'default'		=> '\''.$db->escape($default_lang).'\''
				),
				'style'				=> array(
					'datatype'		=> 'VARCHAR(25)',
					'allow_null'	=> false,
					'default'		=> '\''.$db->escape($default_style).'\''
				),
				'num_posts'			=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'last_post'			=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> true
				),
				'last_search'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> true
				),
				'last_email_sent'	=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> true
				),
				'last_report_sent'	=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> true
				),
				'registered'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'registration_ip'	=> array(
					'datatype'		=> 'VARCHAR(39)',
					'allow_null'	=> false,
					'default'		=> '\'0.0.0.0\''
				),
				'last_visit'		=> array(
					'datatype'		=> 'INT(10) UNSIGNED',
					'allow_null'	=> false,
					'default'		=> '0'
				),
				'admin_note'		=> array(
					'datatype'		=> 'VARCHAR(30)',
					'allow_null'	=> true
				),
				'activate_string'	=> array(
					'datatype'		=> 'VARCHAR(80)',
					'allow_null'	=> true
				),
				'activate_key'		=> array(
					'datatype'		=> 'VARCHAR(8)',
					'allow_null'	=> true
				),
			),
			'PRIMARY KEY'	=> array('id'),
			'UNIQUE KEYS'	=> array(
				'username_idx'		=> array('username')
			),
			'INDEXES'		=> array(
				'registered_idx'	=> array('registered')
			)
		);

		if ($db_type == 'mysql' || $db_type == 'mysqli' || $db_type == 'mysql_innodb' || $db_type == 'mysqli_innodb')
			$schema['UNIQUE KEYS']['username_idx'] = array('username(25)');

		$db->create_table('users', $schema) or error('Unable to create users table', __FILE__, __LINE__, $db->error());


		$now = time();

		// Insert the four preset groups
		$db->query('INSERT INTO '.$db->prefix.'groups ('.($db_type != 'pgsql' ? 'g_id, ' : '').'g_title, g_user_title, g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_send_email, g_post_flood, g_search_flood, g_email_flood, g_report_flood) VALUES('.($db_type != 'pgsql' ? '1, ' : '').'\''.$db->escape($lang_install['Administrators']).'\', \''.$db->escape($lang_install['Administrator']).'\', 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0)') or error('Unable to add group', __FILE__, __LINE__, $db->error());

		$db->query('INSERT INTO '.$db->prefix.'groups ('.($db_type != 'pgsql' ? 'g_id, ' : '').'g_title, g_user_title, g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_send_email, g_post_flood, g_search_flood, g_email_flood, g_report_flood) VALUES('.($db_type != 'pgsql' ? '2, ' : '').'\''.$db->escape($lang_install['Moderators']).'\', \''.$db->escape($lang_install['Moderator']).'\', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0)') or error('Unable to add group', __FILE__, __LINE__, $db->error());

		$db->query('INSERT INTO '.$db->prefix.'groups ('.($db_type != 'pgsql' ? 'g_id, ' : '').'g_title, g_user_title, g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_send_email, g_post_flood, g_search_flood, g_email_flood, g_report_flood) VALUES('.($db_type != 'pgsql' ? '3, ' : '').'\''.$db->escape($lang_install['Guests']).'\', NULL, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, 0, 1, 1, 0, 60, 30, 0, 0)') or error('Unable to add group', __FILE__, __LINE__, $db->error());

		$db->query('INSERT INTO '.$db->prefix.'groups ('.($db_type != 'pgsql' ? 'g_id, ' : '').'g_title, g_user_title, g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_send_email, g_post_flood, g_search_flood, g_email_flood, g_report_flood) VALUES('.($db_type != 'pgsql' ? '4, ' : '').'\''.$db->escape($lang_install['Members']).'\', NULL, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 60, 30, 60, 60)') or error('Unable to add group', __FILE__, __LINE__, $db->error());

		// Insert guest and first admin user
		$db->query('INSERT INTO '.$db_prefix.'users (group_id, username, password, email) VALUES(3, \''.$db->escape($lang_install['Guest']).'\', \''.$db->escape($lang_install['Guest']).'\', \''.$db->escape($lang_install['Guest']).'\')')
			or error('Unable to add guest user. Please check your configuration and try again', __FILE__, __LINE__, $db->error());

		$db->query('INSERT INTO '.$db_prefix.'users (group_id, username, password, email, language, style, num_posts, last_post, registered, registration_ip, last_visit) VALUES(1, \''.$db->escape($username).'\', \''.pun_hash($password).'\', \''.$email.'\', \''.$db->escape($default_lang).'\', \''.$db->escape($default_style).'\', 1, '.$now.', '.$now.', \''.$db->escape(get_remote_address()).'\', '.$now.')')
			or error('Unable to add administrator user. Please check your configuration and try again', __FILE__, __LINE__, $db->error());

		// Insert config data
		$pun_config = array(
			'o_cur_version'				=> Installer::FORUM_VERSION,
			'o_database_revision'		=> Installer::FORUM_DB_REVISION,
			'o_searchindex_revision'	=> Installer::FORUM_SI_REVISION,
			'o_parser_revision'			=> Installer::FORUM_PARSER_REVISION,
			'o_board_title'				=> $title,
			'o_board_desc'				=> $description,
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
			'o_default_lang'			=> $default_lang,
			'o_default_style'			=> $default_style,
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
			'o_mailing_list'			=> $email,
			'o_avatars'					=> $avatars ? '1' : '0',
			'o_avatars_dir'				=> 'img/avatars',
			'o_avatars_width'			=> 60,
			'o_avatars_height'			=> 60,
			'o_avatars_size'			=> 10240,
			'o_search_all_forums'		=> 1,
			'o_base_url'				=> $base_url,
			'o_admin_email'				=> $email,
			'o_webmaster_email'			=> $email,
			'o_forum_subscriptions'		=> 1,
			'o_topic_subscriptions'		=> 1,
			'o_smtp_host'				=> NULL,
			'o_smtp_user'				=> NULL,
			'o_smtp_pass'				=> NULL,
			'o_smtp_ssl'				=> 0,
			'o_regs_allow'				=> 1,
			'o_regs_verify'				=> 0,
			'o_announcement'			=> 0,
			'o_announcement_message'	=> $lang_install['Announcement'],
			'o_rules'					=> 0,
			'o_rules_message'			=> $lang_install['Rules'],
			'o_maintenance'				=> 0,
			'o_maintenance_message'		=> $lang_install['Maintenance message'],
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

		foreach ($pun_config as $conf_name => $conf_value)
		{
			$db->query('INSERT INTO '.$db_prefix.'config (conf_name, conf_value) VALUES(\''.$conf_name.'\', '.(is_null($conf_value) ? 'NULL' : '\''.$db->escape($conf_value).'\'').')')
				or error('Unable to insert into table '.$db_prefix.'config. Please check your configuration and try again', __FILE__, __LINE__, $db->error());
		}

		$db->end_transaction();

		return $db;
	}

	public static function insert_default_forum_and_post($category, $forum, $forum_description, $subject, $message, $username)
	{
		global $db, $lang_install;

		$now = time();

		$db->start_transaction();

		$db->query('INSERT INTO '.$db_prefix.'categories (cat_name, disp_position) VALUES(\''.$db->escape($category).'\', 1)')
			or error('Unable to insert into table '.$db_prefix.'categories. Please check your configuration and try again', __FILE__, __LINE__, $db->error());

		$db->query('INSERT INTO '.$db_prefix.'forums (forum_name, forum_desc, num_topics, num_posts, last_post, last_post_id, last_poster, disp_position, cat_id) VALUES(\''.$db->escape($forum).'\', \''.$db->escape($forum_description).'\', 1, 1, '.$now.', 1, \''.$db->escape($username).'\', 1, 1)')
			or error('Unable to insert into table '.$db_prefix.'forums. Please check your configuration and try again', __FILE__, __LINE__, $db->error());

		$db->query('INSERT INTO '.$db_prefix.'topics (poster, subject, posted, first_post_id, last_post, last_post_id, last_poster, forum_id) VALUES(\''.$db->escape($username).'\', \''.$db->escape($subject).'\', '.$now.', 1, '.$now.', 1, \''.$db->escape($username).'\', 1)')
			or error('Unable to insert into table '.$db_prefix.'topics. Please check your configuration and try again', __FILE__, __LINE__, $db->error());

		$db->query('INSERT INTO '.$db_prefix.'posts (poster, poster_id, poster_ip, message, posted, topic_id) VALUES(\''.$db->escape($username).'\', 2, \''.$db->escape(get_remote_address()).'\', \''.$db->escape($message).'\', '.$now.', 1)')
			or error('Unable to insert into table '.$db_prefix.'posts. Please check your configuration and try again', __FILE__, __LINE__, $db->error());

		// Index the test post so searching for it works
		require PUN_ROOT.'include/search_idx.php';
		update_search_index('post', 1, $message, $subject);

		$db->end_transaction();
	}
}
