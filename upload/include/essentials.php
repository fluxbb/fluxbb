<?php
/**
 * Loads the minimum amount of data (eg: functions, database connection, config data, etc)
 * necessary to integrate the site.
 *
 * @copyright Copyright (C) 2008 FluxBB.org, based on code copyright (C) 2002-2008 PunBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package FluxBB
 */


// Enable DEBUG mode by removing // from the following line
define('FORUM_DEBUG', 1);

if (!defined('FORUM_ROOT'))
	exit('The constant FORUM_ROOT must be defined and point to a valid FluxBB installation root directory.');

// Define the version and database revision that this code was written for
define('FORUM_VERSION', '1.3 Beta');
define('FORUM_DB_REVISION', 4);

// Load the functions script
require FORUM_ROOT.'include/functions.php';

// Load UTF-8 functions
require FORUM_ROOT.'include/utf8/utf8.php';
require FORUM_ROOT.'include/utf8/ucwords.php';
require FORUM_ROOT.'include/utf8/trim.php';

// Reverse the effect of register_globals
forum_unregister_globals();

// Ignore any user abort requests
ignore_user_abort(true);

// Attempt to load the configuration file config.php
if (file_exists(FORUM_ROOT.'config.php'))
	include FORUM_ROOT.'config.php';

// If we have the 1.2 constant defined, define the proper 1.3 constant so we don't get
// an incorrect "need to install" message
if (defined('PUN'))
	define('FORUM', 1);

if (!defined('FORUM'))
	error('The file \'config.php\' doesn\'t exist or is corrupt. Please run <a href="'.FORUM_ROOT.'admin/install.php">install.php</a> to install FluxBB first.');


// Block prefetch requests
if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch')
{
	header('HTTP/1.1 403 Prefetching Forbidden');

	// Send no-cache headers
	header('Expires: Thu, 21 Jul 1977 07:30:00 GMT');	// When yours truly first set eyes on this world! :)
	header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
	header('Cache-Control: post-check=0, pre-check=0', false);
	header('Pragma: no-cache');		// For HTTP/1.0 compability

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


// Construct REQUEST_URI if it isn't set (or if it's set improperly)
if (!isset($_SERVER['REQUEST_URI']) || (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) && strpos($_SERVER['REQUEST_URI'], '?') === false))
	$_SERVER['REQUEST_URI'] = (isset($_SERVER['PHP_SELF']) ? str_replace('%26', '&', str_replace('%3D', '=', str_replace('%2F', '/', rawurlencode($_SERVER['PHP_SELF'])))) : '').(isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : '');

// Load DB abstraction layer and connect
require FORUM_ROOT.'include/dblayer/common_db.php';

// Start a transaction
$forum_db->start_transaction();


// Load cached config
if (file_exists(FORUM_CACHE_DIR.'cache_config.php'))
	include FORUM_CACHE_DIR.'cache_config.php';

if (!defined('FORUM_CONFIG_LOADED'))
{
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';

	generate_config_cache();
	require FORUM_CACHE_DIR.'cache_config.php';
}


// Verify that we are running the proper database schema revision
if (defined('PUN') || !isset($forum_config['o_database_revision']) || $forum_config['o_database_revision'] < FORUM_DB_REVISION || version_compare($forum_config['o_cur_version'], FORUM_VERSION, '<'))
	error('Your FluxBB database is out-of-date and must be upgraded in order to continue. Please run <a href="'.FORUM_ROOT.'admin/db_update.php">db_update.php</a> in order to complete the upgrade process.');


// Load hooks
if (file_exists(FORUM_CACHE_DIR.'cache_hooks.php'))
	include FORUM_CACHE_DIR.'cache_hooks.php';

if (!defined('FORUM_HOOKS_LOADED'))
{
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';

	generate_hooks_cache();
	require FORUM_CACHE_DIR.'cache_hooks.php';
}


// A good place to add common functions for your extension
($hook = get_hook('es_essentials')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

define('FORUM_ESSENTIALS_LOADED', 1);
