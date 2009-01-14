<?php
/**
 * Loads the minimum amount of data (eg: functions, database connection, config data, etc)
 * necessary to integrate the site.
 *
 * @copyright Copyright (C) 2008 FluxBB.org, based on code copyright (C) 2002-2008 PunBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package FluxBB
 */

if (!defined('FORUM_ROOT'))
	exit('The constant FORUM_ROOT must be defined and point to a valid FluxBB installation root directory.');

// Define the version and database revision that this code was written for
define('FORUM_VERSION', '1.3 Beta');
define('FORUM_DB_REVISION', 5);

// Attempt to load the configuration file config.php
if (file_exists(FORUM_ROOT.'config.php'))
	include FORUM_ROOT.'config.php';

// If we have the 1.2 constant defined, define the proper 1.3 constant so we don't get
// an incorrect "need to install" message
if (defined('PUN'))
	define('FORUM', 1);

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

// If the request_uri is invalid try fix it
if (!defined('FORUM_IGNORE_REQUEST_URI'))
	forum_fix_request_uri();

if (!isset($base_url))
{
	// Make an educated guess regarding base_url
	$base_url_guess = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://').preg_replace('/:80$/', '', $_SERVER['HTTP_HOST']).str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
	if (substr($base_url_guess, -1) == '/')
		$base_url_guess = substr($base_url_guess, 0, -1);

	$base_url = $base_url_guess;
}

// Verify that we are running the proper database schema revision
if (defined('PUN') || !isset($forum_config['o_database_revision']) || $forum_config['o_database_revision'] < FORUM_DB_REVISION || version_compare($forum_config['o_cur_version'], FORUM_VERSION, '<'))
	error('Your FluxBB database is out-of-date and must be upgraded in order to continue. Please run <a href="'.$base_url.'/admin/db_update.php">db_update.php</a> in order to complete the upgrade process.');

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

// Define a few commonly used constants
define('FORUM_UNVERIFIED', 0);
define('FORUM_ADMIN', 1);
define('FORUM_GUEST', 2);

// A good place to add common functions for your extension
($hook = get_hook('es_essentials')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

if (!defined('FORUM_MAX_POSTSIZE'))
	define('FORUM_MAX_POSTSIZE', 65535);

if (!defined('FORUM_SEARCH_MIN_WORD'))
	define('FORUM_SEARCH_MIN_WORD', 3);
if (!defined('FORUM_SEARCH_MAX_WORD'))
	define('FORUM_SEARCH_MAX_WORD', 20);

define('FORUM_ESSENTIALS_LOADED', 1);
