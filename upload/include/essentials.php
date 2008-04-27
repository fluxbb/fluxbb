<?php
/***********************************************************************

  Copyright (C) 2002-2008  PunBB.org

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


// Enable DEBUG mode by removing // from the following line
define('PUN_DEBUG', 1);

// This displays all executed queries in the page footer.
// DO NOT enable this in a production environment!
//define('PUN_SHOW_QUERIES', 1);

// Enable this if an extension is causing problems and you can't access the admin interface
//define('PUN_DISABLE_HOOKS', 1);

if (!defined('PUN_ROOT'))
	exit('The constant PUN_ROOT must be defined and point to a valid PunBB installation root directory.');


// Load the functions script
require PUN_ROOT.'include/functions.php';

// Reverse the effect of register_globals
pun_unregister_globals();


// Attempt to load the configuration file config.php
if (file_exists(PUN_ROOT.'config.php'))
	include PUN_ROOT.'config.php';

if (!defined('PUN'))
	error('The file \'config.php\' doesn\'t exist or is corrupt. Please run <a href="install.php">install.php</a> to install PunBB first.');


// Record the start time (will be used to calculate the generation time for the page)
list($usec, $sec) = explode(' ', microtime());
$pun_start = ((float)$usec + (float)$sec);

// Make sure PHP reports all errors except E_NOTICE. PunBB supports E_ALL, but a lot of scripts it may interact with, do not.
error_reporting(E_ALL);

// Force POSIX locale (to prevent functions such as strtolower() from messing up UTF-8 strings)
setlocale(LC_CTYPE, 'C');

// If the cache directory is not specified, we use the default setting
if (!defined('PUN_CACHE_DIR'))
	define('PUN_CACHE_DIR', PUN_ROOT.'cache/');


// Construct REQUEST_URI if it isn't set
if (!isset($_SERVER['REQUEST_URI']))
	$_SERVER['REQUEST_URI'] = (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '').'?'.(isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '');

// Load DB abstraction layer and connect
require PUN_ROOT.'include/dblayer/common_db.php';

// Start a transaction
$pun_db->start_transaction();


// Load cached config
if (file_exists(PUN_CACHE_DIR.'cache_config.php'))
	include PUN_CACHE_DIR.'cache_config.php';

if (!defined('PUN_CONFIG_LOADED'))
{
	require_once PUN_ROOT.'include/cache.php';
	generate_config_cache();
	require PUN_CACHE_DIR.'cache_config.php';
}


// Load hooks
if (file_exists(PUN_CACHE_DIR.'cache_hooks.php'))
	include PUN_CACHE_DIR.'cache_hooks.php';

if (!defined('PUN_HOOKS_LOADED'))
{
	require_once PUN_ROOT.'include/cache.php';
	generate_hooks_cache();
	require PUN_CACHE_DIR.'cache_hooks.php';
}


define('PUN_ESSENTIALS_LOADED', 1);