<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;


// Load and create the appropriate DB adapter (and open/connect to/select db)
switch ($db_type)
{
	case 'mysql':
	case 'mysqli':
		require_once PUN_ROOT.'include/dblayer/mysqli.php';
		$db = new MysqlDBLayer($db_host, $db_username, $db_password, $db_name, $db_prefix, $p_connect);
		break;

	case 'mysql_innodb':
	case 'mysqli_innodb':
		require_once PUN_ROOT.'include/dblayer/mysqli_innodb.php';
		$db = new MysqlInnodbDBLayer($db_host, $db_username, $db_password, $db_name, $db_prefix, $p_connect);
		break;

	case 'pgsql':
		require_once PUN_ROOT.'include/dblayer/pgsql.php';
		$db = new PgsqlDBLayer($db_host, $db_username, $db_password, $db_name, $db_prefix, $p_connect);
		break;

	case 'sqlite':
		require_once PUN_ROOT.'include/dblayer/sqlite.php';
		$db = new SqliteDBLayer($db_name, $db_prefix, $p_connect);
		break;

	default:
		error('\''.$db_type.'\' is not a valid database type. Please check settings in config.php.', __FILE__, __LINE__);
		break;
}
