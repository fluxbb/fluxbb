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


// Enable DEBUG mode by removing // from the following line
define('FORUM_DEBUG', 1);

if (!defined('FORUM_ROOT'))
	exit('The constant FORUM_ROOT must be defined and point to a valid FluxBB installation root directory.');


// Load the functions script
require FORUM_ROOT.'include/functions.php';

// Reverse the effect of register_globals
forum_unregister_globals();

// Ignore any user abort requests
ignore_user_abort(true);

// Attempt to load the configuration file config.php
if (file_exists(FORUM_ROOT.'config.php'))
	include FORUM_ROOT.'config.php';

if (!defined('FORUM'))
	error('The file \'config.php\' doesn\'t exist or is corrupt. Please run <a href="install.php">install.php</a> to install FluxBB first.');


// Block prefetch requests
if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch')
{
	header('HTTP/1.1 403 Prefetching Forbidden');
	exit;
}

// Record the start time (will be used to calculate the generation time for the page)
list($usec, $sec) = explode(' ', microtime());
$forum_start = ((float)$usec + (float)$sec);

// Make sure PHP reports all errors except E_NOTICE. FluxBB supports E_ALL, but a lot of scripts it may interact with, do not.
error_reporting(E_ALL);

// Force POSIX locale (to prevent functions such as strtolower() from messing up UTF-8 strings)
setlocale(LC_CTYPE, 'C');

// If the cache directory is not specified, we use the default setting
if (!defined('FORUM_CACHE_DIR'))
	define('FORUM_CACHE_DIR', FORUM_ROOT.'cache/');


// Construct REQUEST_URI if it isn't set
if (!isset($_SERVER['REQUEST_URI']))
	$_SERVER['REQUEST_URI'] = (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '').'?'.(isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '');

// Load DB abstraction layer and connect
require FORUM_ROOT.'include/dblayer/common_db.php';

// Start a transaction
$forum_db->start_transaction();


// Load cached config
if (file_exists(FORUM_CACHE_DIR.'cache_config.php'))
	include FORUM_CACHE_DIR.'cache_config.php';

if (!defined('FORUM_CONFIG_LOADED'))
{
	require_once FORUM_ROOT.'include/cache.php';
	generate_config_cache();
	require FORUM_CACHE_DIR.'cache_config.php';
}


// Load hooks
if (file_exists(FORUM_CACHE_DIR.'cache_hooks.php'))
	include FORUM_CACHE_DIR.'cache_hooks.php';

if (!defined('FORUM_HOOKS_LOADED'))
{
	require_once FORUM_ROOT.'include/cache.php';
	generate_hooks_cache();
	require FORUM_CACHE_DIR.'cache_hooks.php';
}


define('FORUM_ESSENTIALS_LOADED', 1);