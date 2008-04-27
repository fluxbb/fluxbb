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


if (!defined('PUN_ROOT'))
	exit('The constant PUN_ROOT must be defined and point to a valid PunBB installation root directory.');

if (!defined('PUN_ESSENTIALS_LOADED'))
	require PUN_ROOT.'include/essentials.php';

// Turn off magic_quotes_runtime
set_magic_quotes_runtime(0);

// Strip slashes from GET/POST/COOKIE (if magic_quotes_gpc is enabled)
if (get_magic_quotes_gpc())
{
	function stripslashes_array($array)
	{
		return is_array($array) ? array_map('stripslashes_array', $array) : stripslashes($array);
	}

	$_GET = stripslashes_array($_GET);
	$_POST = stripslashes_array($_POST);
	$_COOKIE = stripslashes_array($_COOKIE);
}

// If a cookie name is not specified in config.php, we use the default (punbb_cookie)
if (empty($cookie_name))
	$cookie_name = 'punbb_cookie';

// Define a few commonly used constants
define('PUN_UNVERIFIED', 0);
define('PUN_ADMIN', 1);
define('PUN_GUEST', 2);
define('PUN_MEMBER', 3);


// Enable output buffering
if (!defined('PUN_DISABLE_BUFFERING'))
{
	// For some very odd reason, "Norton Internet Security" unsets this
	$_SERVER['HTTP_ACCEPT_ENCODING'] = isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : '';

	// Should we use gzip output compression?
	if ($pun_config['o_gzip'] && extension_loaded('zlib') && (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false || strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'deflate') !== false))
		ob_start('ob_gzhandler');
	else
		ob_start();
}


// Define standard date/time formats
$pun_time_formats = array($pun_config['o_time_format'], 'H:i:s', 'H:i', 'g:i:s a', 'g:i a');
$pun_date_formats = array($pun_config['o_date_format'], 'Y-m-d', 'Y-d-m', 'd-m-Y', 'm-d-Y', 'M j Y', 'jS M Y');

// Create pun_page array
$pun_page = array();

// Login and fetch user info
$pun_user = array();
cookie_login($pun_user);

// Attempt to load the common language file
if (file_exists(PUN_ROOT.'lang/'.$pun_user['language'].'/common.php'))
	include PUN_ROOT.'lang/'.$pun_user['language'].'/common.php';
else
	error('There is no valid language pack \''.pun_htmlencode($pun_user['language']).'\' installed. Please reinstall a language of that name.');

// Check if we are to display a maintenance message
if ($pun_config['o_maintenance'] && $pun_user['g_id'] > PUN_ADMIN && !defined('PUN_TURN_OFF_MAINT'))
	maintenance_message();

// Setup the URL rewriting scheme
if (file_exists(PUN_ROOT.'include/url/'.$pun_config['o_sef'].'.php'))
	require PUN_ROOT.'include/url/'.$pun_config['o_sef'].'.php';
else
	require PUN_ROOT.'include/url/Default.php';


// Load cached updates info
if ($pun_user['g_id'] == PUN_ADMIN)
{
	if (file_exists(PUN_CACHE_DIR.'cache_updates.php'))
		include PUN_CACHE_DIR.'cache_updates.php';

	// Regenerate cache only if automatic updates are enabled and if the cache is more than 12 hours old
	if ($pun_config['o_check_for_updates'] == '1' && (!defined('PUN_UPDATES_LOADED') || $pun_updates['cached'] < (time() - 43200)))
	{
		require_once PUN_ROOT.'include/cache.php';
		generate_updates_cache();
		require PUN_CACHE_DIR.'cache_updates.php';
	}
}


// Load cached bans
if (file_exists(PUN_CACHE_DIR.'cache_bans.php'))
	include PUN_CACHE_DIR.'cache_bans.php';

if (!defined('PUN_BANS_LOADED'))
{
	require_once PUN_ROOT.'include/cache.php';
	generate_bans_cache();
	require PUN_CACHE_DIR.'cache_bans.php';
}

// Check if current user is banned
check_bans();


// Update online list
update_users_online();

// Check to see if we logged in without a cookie being set
if ($pun_user['is_guest'] && isset($_GET['login']))
	message($lang_common['No cookie']);

// If we're an administrator or moderator, make sure the CSRF token in $_POST is valid (token in post.php is dealt with in post.php)
if (!empty($_POST) && $pun_user['is_admmod'] && (isset($_POST['confirm_cancel']) || (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== generate_form_token(get_current_url()))) && basename($_SERVER['PHP_SELF']) != 'post.php')
	csrf_confirm_form();


// A good place to add common functions for your extension
($hook = get_hook('co_common')) ? eval($hook) : null;

if (!defined('PUN_MAX_POSTSIZE'))
	define('PUN_MAX_POSTSIZE', 65535);