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


// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;


//
// Return current timestamp (with microseconds) as a float (used in dblayer)
//
if (defined('PUN_SHOW_QUERIES'))
{
	function get_microtime()
	{
		list($usec, $sec) = explode(' ', microtime());
		return ((float)$usec + (float)$sec);
	}
}


// Load the appropriate DB layer class
switch ($db_type)
{
	case 'mysql':
		require PUN_ROOT.'include/dblayer/mysql.php';
		break;

	case 'mysqli':
		require PUN_ROOT.'include/dblayer/mysqli.php';
		break;

	case 'pgsql':
		require PUN_ROOT.'include/dblayer/pgsql.php';
		break;

	case 'sqlite':
		require PUN_ROOT.'include/dblayer/sqlite.php';
		break;

	default:
		error('\''.$db_type.'\' is not a valid database type. Please check settings in config.php.', __FILE__, __LINE__);
		break;
}


// Create the database adapter object (and open/connect to/select db)
$db = new DBLayer($db_host, $db_username, $db_password, $db_name, $db_prefix, $p_connect);
