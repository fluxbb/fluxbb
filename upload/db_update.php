<?php
/**
 * Database updating script
 *
 * Updates the database to the latest version.
 *
 * @copyright Copyright (C) 2008 FluxBB.org, based on code copyright (C) 2002-2008 PunBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package FluxBB
 */


define('UPDATE_TO', '1.4');
define('UPDATE_TO_DB_REVISION', 2);

// The number of items to process per pageview (lower this if the update script times out during UTF-8 conversion)
define('PER_PAGE', 300);


// Make sure we are running at least PHP 4.1.0
if (intval(str_replace('.', '', phpversion())) < 410)
	exit('You are running PHP version '.PHP_VERSION.'. FluxBB requires at least PHP 4.1.0 to run properly. You must upgrade your PHP installation before you can continue.');


define('PUN_ROOT', './');

// Attempt to load the configuration file config.php
if (file_exists(PUN_ROOT.'config.php'))
	include PUN_ROOT.'config.php';

// If PUN isn't defined, config.php is missing or corrupt or we are outside the root directory
if (!defined('PUN'))
	exit('This file must be run from the forum root directory.');

// Enable debug mode
define('PUN_DEBUG', 1);

// Turn on full PHP error reporting
error_reporting(E_ALL);

// Turn off magic_quotes_runtime
set_magic_quotes_runtime(0);

// Turn off PHP time limit
@set_time_limit(0);

// If a cookie name is not specified in config.php, we use the default (forum_cookie)
if (empty($cookie_name))
	$cookie_name = 'pun_cookie';

// If the cache directory is not specified, we use the default setting
if (!defined('FORUM_CACHE_DIR'))
	define('FORUM_CACHE_DIR', PUN_ROOT.'cache/');

// Load the functions script
require PUN_ROOT.'include/functions.php';

// Load UTF-8 functions
require PUN_ROOT.'include/utf8/utf8.php';

// Strip out "bad" UTF-8 characters
forum_remove_bad_characters();

// Instruct DB abstraction layer that we don't want it to "SET NAMES". If we need to, we'll do it ourselves below.
define('FORUM_NO_SET_NAMES', 1);

// Load DB abstraction layer and try to connect
require PUN_ROOT.'include/dblayer/common_db.php';

// Check current version
$result = $db->query('SELECT conf_value FROM '.$db->prefix.'config WHERE conf_name=\'o_cur_version\'') or error('Unable to fetch version info.', __FILE__, __LINE__, $db->error());
$cur_version = $db->result($result);

if (version_compare($cur_version, '1.2', '<'))
	exit('Version mismatch. The database \''.$db_name.'\' doesn\'t seem to be running a FluxBB database schema supported by this update script.');

// If we've already done charset conversion in a previous update, we have to do SET NAMES
$db->set_names(strpos($cur_version, '1.3') === 0 ? 'utf8' : 'latin1');

// Get the forum config
$result = $db->query('SELECT * FROM '.$db->prefix.'config') or error('Unable to fetch config.', __FILE__, __LINE__, $db->error());
while ($cur_config_item = $db->fetch_row($result))
	$pun_config[$cur_config_item[0]] = $cur_config_item[1];

// Check the database revision and the current version
if (isset($pun_config['o_database_revision']) && $pun_config['o_database_revision'] >= UPDATE_TO_DB_REVISION && version_compare($pun_config['o_cur_version'], UPDATE_TO, '>='))
	exit('Your database is already as up-to-date as this script can make it.');


//
// Determines whether $str is UTF-8 encoded or not
//
function seems_utf8($str)
{
	$str_len = strlen($str);
	for ($i = 0; $i < $str_len; ++$i)
	{
		if (ord($str[$i]) < 0x80) continue; # 0bbbbbbb
		else if ((ord($str[$i]) & 0xE0) == 0xC0) $n=1; # 110bbbbb
		else if ((ord($str[$i]) & 0xF0) == 0xE0) $n=2; # 1110bbbb
		else if ((ord($str[$i]) & 0xF8) == 0xF0) $n=3; # 11110bbb
		else if ((ord($str[$i]) & 0xFC) == 0xF8) $n=4; # 111110bb
		else if ((ord($str[$i]) & 0xFE) == 0xFC) $n=5; # 1111110b
		else return false; # Does not match any model

		for ($j = 0; $j < $n; ++$j) # n bytes matching 10bbbbbb follow ?
		{
			if ((++$i == strlen($str)) || ((ord($str[$i]) & 0xC0) != 0x80))
				return false;
		}
	}

	return true;
}


//
// Translates the number from an HTML numeric entity into an UTF-8 character
//
function dcr2utf8($src)
{
	$dest = '';
	if ($src < 0)
		return false;
	else if ($src <= 0x007f)
		$dest .= chr($src);
	else if ($src <= 0x07ff)
	{
		$dest .= chr(0xc0 | ($src >> 6));
		$dest .= chr(0x80 | ($src & 0x003f));
	}
	else if ($src == 0xFEFF)
	{
		// nop -- zap the BOM
	}
	else if ($src >= 0xD800 && $src <= 0xDFFF)
	{
		// found a surrogate
		return false;
	}
	else if ($src <= 0xffff)
	{
		$dest .= chr(0xe0 | ($src >> 12));
		$dest .= chr(0x80 | (($src >> 6) & 0x003f));
		$dest .= chr(0x80 | ($src & 0x003f));
	}
	else if ($src <= 0x10ffff)
	{
		$dest .= chr(0xf0 | ($src >> 18));
		$dest .= chr(0x80 | (($src >> 12) & 0x3f));
		$dest .= chr(0x80 | (($src >> 6) & 0x3f));
		$dest .= chr(0x80 | ($src & 0x3f));
	}
	else
	{
		// out of range
		return false;
	}

	return $dest;
}


//
// Attemts to convert $str from $old_charset to UTF-8. Also converts HTML entities (including numeric entities) to UTF-8 characters.
//
function convert_to_utf8(&$str, $old_charset)
{
	if ($str == '')
		return false;

	$save = $str;

	// Replace literal entities (for non-UTF-8 compliant html_entity_encode)
	if (version_compare(PHP_VERSION, '5.0.0', '<') && $old_charset == 'ISO-8859-1' || $old_charset == 'ISO-8859-15')
		$str = html_entity_decode($str, ENT_QUOTES, $old_charset);

	if (!seems_utf8($str))
	{
		if ($old_charset == 'ISO-8859-1')
			$str = utf8_encode($str);
		else if (function_exists('iconv'))
			$str = iconv($old_charset, 'UTF-8', $str);
		else if (function_exists('mb_convert_encoding'))
			$str = mb_convert_encoding($str, 'UTF-8', $old_charset);
	}

	// Replace literal entities (for UTF-8 compliant html_entity_encode)
	if (version_compare(PHP_VERSION, '5.0.0', '>='))
		$str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');

	// Replace numeric entities
	$str = preg_replace_callback('/&#([0-9]+);/', 'utf8_callback_1', $str);
	$str = preg_replace_callback('/&#x([a-f0-9]+);/i', 'utf8_callback_2', $str);

	return ($save != $str);
}


function utf8_callback_1($matches)
{
	return dcr2utf8($matches[1]);
}


function utf8_callback_2($matches)
{
	return dcr2utf8(hexdec($matches[1]));
}


//
// Tries to determine whether post data in the database is UTF-8 encoded or not
//
function db_seems_utf8()
{
	global $db_type, $db;

	$seems_utf8 = true;

	$result = $db->query('SELECT MIN(id), MAX(id) FROM '.$db->prefix.'posts') or error('Unable to fetch post IDs', __FILE__, __LINE__, $db->error());
	list($min_id, $max_id) = $db->fetch_row($result);

	if (empty($min_id) || empty($max_id))
		return true;

	// Get a random soup of data and check if it appears to be UTF-8
	for ($i = 0; $i < 100; ++$i)
	{
		$id = ($i == 0) ? $min_id : (($i == 1) ? $max_id : rand($min_id, $max_id));

		$result = $db->query('SELECT p.message, p.poster, t.subject, f.forum_name FROM '.$db->prefix.'posts AS p INNER JOIN '.$db->prefix.'topics AS t ON (t.id = p.topic_id) INNER JOIN '.$db->prefix.'forums AS f ON (f.id = t.forum_id) WHERE p.id >= '.$id.' LIMIT 1') or error('Unable to fetch post information', __FILE__, __LINE__, $db->error());
		$temp = $db->fetch_row($result);

		if (!seems_utf8($temp[0].$temp[1].$temp[2].$temp[3]))
		{
			$seems_utf8 = false;
			break;
		}
	}

	return $seems_utf8;
}


//
// Safely converts text type columns into utf8 (MySQL only)
// Function based on update_convert_table_utf8() from the Drupal project (http://drupal.org/)
//
function convert_table_utf8($table)
{
	global $db;

	$types = array(
		'char' 			=> 'binary',
		'varchar'		=> 'varbinary',
		'tinytext'		=> 'tinyblob',
		'mediumtext'	=> 'mediumblob',
		'text'			=> 'blob',
		'longtext'		=> 'longblob'
	);

	// Set table default charset to utf8
	$db->query('ALTER TABLE `'.$table.'` CHARACTER SET utf8') or error('Unable to set table character set', __FILE__, __LINE__, $db->error());

	// Find out which columns need converting and build SQL statements
	$result = $db->query('SHOW FULL COLUMNS FROM `'.$table.'`') or error('Unable to fetch column information', __FILE__, __LINE__, $db->error());
	while ($cur_column = $db->fetch_assoc($result))
	{
		list($type) = explode('(', $cur_column['Type']);
		if (isset($types[$type]) && strpos($cur_column['Collation'], 'utf8') === false)
		{
			$allow_null = ($cur_column['Null'] == 'YES');

			$db->alter_field($table, $cur_column['Field'], preg_replace('/'.$type.'/i', $types[$type], $cur_column['Type']), $allow_null, $cur_column['Default']);
			$db->alter_field($table, $cur_column['Field'], $cur_column['Type'].' CHARACTER SET utf8', $allow_null, $cur_column['Default']);
		}
	}
}


header('Content-type: text/html; charset=utf-8');

// Empty all output buffers and stop buffering
while (@ob_end_clean());


$stage = isset($_GET['stage']) ? $_GET['stage'] : '';
$old_charset = isset($_GET['req_old_charset']) ? str_replace('ISO8859', 'ISO-8859', strtoupper($_GET['req_old_charset'])) : 'ISO-8859-1';
$start_at = isset($_GET['start_at']) ? intval($_GET['start_at']) : 0;
$query_str = '';

switch ($stage)
{
	// Show form
	case '':
		$db_seems_utf8 = db_seems_utf8();

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>FluxBB Database Update</title>
<link rel="stylesheet" type="text/css" href="style/Oxygen.css" />
</head>
<body>

<div id="punwrap">
<div id="puninstall" class="pun" style="margin: 10% 20% auto 20%">

<div class="blockform">
	<h2><span>FluxBB Update</span></h2>
	<div class="box">
		<form method="get" action="<?php echo pun_htmlspecialchars($_SERVER['REQUEST_URI']) ?>" onsubmit="this.start.disabled=true">
		<input type="hidden" name="stage" value="start" />
			<div class="inform">
				<p style="font-size: 1.1em">This script will update your forum database. The update procedure might take anything from a second to a few minutes depending on the speed of the server and the size of the forum database. Don't forget to make a backup of the database before continuing.</p>
				<p style="font-size: 1.1em">Did you read the update instructions in the documentation? If not, start there.</p>
<?php

if (strpos($cur_version, '1.2') === 0 && (!$db_seems_utf8 || isset($_GET['force'])))
{
	if (!function_exists('iconv') && !function_exists('mb_convert_encoding'))
	{

?>
				<p style="font-size: 1.1em"><strong>IMPORTANT!</strong> FluxBB has detected that this PHP environment does not have support for the encoding mechanisms required to do UTF-8 conversion from character sets other than ISO-8859-1. What this means is that if the current character set is not ISO-8859-1, FluxBB won't be able to convert your forum database to UTF-8 and you will have to do it manually. Instructions for doing manual charset conversion can be found in the update instructions.</p>
<?php

	}
}

if (strpos($cur_version, '1.2') === 0 && $db_seems_utf8 && !isset($_GET['force']))
{

?>
				<p style="font-size: 1.1em"><span><strong>IMPORTANT!</strong> Based on a random selection of 100 posts, topic subjects, usernames and forum names from the database, it appears as if text in the database is currently UTF-8 encoded. This is a good thing. Based on this, the update process will not attempt to do charset conversion. If you have reason to believe that the charset conversion is required nonetheless, you can <a href="<?php echo pun_htmlspecialchars($_SERVER['REQUEST_URI']).((substr_count($_SERVER['REQUEST_URI'], '?') == 1) ? '&amp;' : '?').'force=1' ?>">force the conversion to run</a>.</p>
<?php

}

if (strpos($cur_version, '1.2') === 0 && (!$db_seems_utf8 || isset($_GET['force'])))
{

?>
			</div>
			<div class="inform">
				<p style="font-size: 1.1em"><strong>Enable conversion:</strong> When enabled this update script will, after it has made the required structural changes to the database, convert all text in the database from the current character set to UTF-8. This conversion is required if you're upgrading from version 1.2 and you are not currently using an UTF-8 language pack.</p>
				<p style="font-size: 1.1em"><strong>Current character set:</strong> If the primary language in your forum is English, you can leave this at the default value. However, if your forum is non-English, you should enter the character set of the primary language pack used in the forum.</p>
			<fieldset>
				<legend>Charset conversion</legend>
				<div class="infldset">
					<table class="aligntop" cellspacing="0">
						<tr>
							<th scope="row">Enable conversion:</th>
							<td>
								<input type="checkbox" name="convert_charset" value="1" checked="checked" />
								<span>Perform database charset conversion.</span>
							</td>
						</tr>
						<tr>
							<th scope="row">Current character set:</th>
							<td>
								<input type="text" name="req_old_charset" size="12" maxlength="20" value="ISO-8859-1" /><br />
								<span>Accept default for English forums otherwise the character set of the primary langauge pack.</span>
							</td>
						</tr>
					</table>
				</div>
			</fieldset>
<?php

}

?>
			</div>
			<p><input type="submit" name="start" value="Start update" /></p>
		</form>
	</div>
</div>

</div>
</div>

</body>
</html>
<?php

		break;


	// Start by updating the database structure
	case 'start':
		// Make all e-mail fields VARCHAR(80)
		$db->alter_field('bans', 'email', 'VARCHAR(80)', true);
		$db->alter_field('posts', 'poster_email', 'VARCHAR(80)', true);
		$db->alter_field('users', 'email', 'VARCHAR(80)', false, '');
		$db->alter_field('users', 'jabber', 'VARCHAR(80)', true);
		$db->alter_field('users', 'msn', 'VARCHAR(80)', true);
		$db->alter_field('users', 'activate_string', 'VARCHAR(80)', true);

		// Make all IP fields VARCHAR(39) to support IPv6
		$db->alter_field('posts', 'poster_ip', 'VARCHAR(39)', true);
		$db->alter_field('users', 'registration_ip', 'VARCHAR(39)', false, '0.0.0.0');

		// Add the DST option to the users table
		$db->add_field('users', 'dst', 'TINYINT(1)', false, 0, 'timezone');

		// Add the last_post field to the online table
		$db->add_field('online', 'last_post', 'INT(10) UNSIGNED', true, null, null);

		// Add the last_search field to the online table
		$db->add_field('online', 'last_search', 'INT(10) UNSIGNED', true, null, null);

		// Add the last_search column to the users table
		$db->add_field('users', 'last_search', 'INT(10) UNSIGNED', true, null, 'last_post');

		// Drop use_avatar column from users table
		$db->drop_field('users', 'use_avatar');

		// Drop save_pass column from users table
		$db->drop_field('users', 'save_pass');

		// Drop g_edit_subjects_interval column from groups table
		$db->drop_field('groups', 'g_edit_subjects_interval');

		$new_config = array();

		// Add database revision number
		if (!array_key_exists('o_database_revision', $pun_config))
			$new_config[] = '\'o_database_revision\', \'0\'';

		// Add default email setting option
		if (!array_key_exists('o_default_email_setting', $pun_config))
			$new_config[] = '\'o_default_email_setting\', \'1\'';

		// Make sure we have o_additional_navlinks (was added in 1.2.1)
		if (!array_key_exists('o_additional_navlinks', $pun_config))
			$new_config[] = '\'o_additional_navlinks\', \'\'';

		// Insert new config option o_topic_views
		if (!array_key_exists('o_topic_views', $pun_config))
			$new_config[] = '\'o_topic_views\', \'1\'';

		// Insert new config option o_signatures
		if (!array_key_exists('o_signatures', $pun_config))
			$new_config[] = '\'o_signatures\', \'1\'';

		// Insert new config option o_smtp_ssl
		if (!array_key_exists('o_smtp_ssl', $pun_config))
			$new_config[] = '\'o_smtp_ssl\', \'0\'';

		// Insert new config option o_default_dst
		if (!array_key_exists('o_default_dst', $pun_config))
			$new_config[] = '\'o_default_dst\', \'0\'';

		if (!array_key_exists('o_quote_depth', $pun_config))
			$new_config[] = '\'o_quote_depth\', \'3\'';

		if (!empty($new_config))
			$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES ('.implode('), (', $new_config).')') or error('Unable to insert config values', __FILE__, __LINE__, $db->error());

		unset($new_config);

		// Server timezone is now simply the default timezone
		if (!array_key_exists('o_default_timezone', $pun_config))
			$db->query('UPDATE '.$db->prefix.'config SET conf_name = \'o_default_timezone\' WHERE conf_name = \'o_server_timezone\'') or error('Unable to update timezone config', __FILE__, __LINE__, $db->error());

		// Increase visit timeout to 30 minutes (only if it hasn't been changed from the default)
		if (!array_key_exists('o_database_revision', $pun_config) && $pun_config['o_timeout_visit'] == '600')
			$db->query('UPDATE '.$db->prefix.'config SET conf_value = \'1800\' WHERE conf_name = \'o_timeout_visit\'') or error('Unable to update visit timeout config', __FILE__, __LINE__, $db->error());

		// Remove obsolete g_post_polls permission from groups table
		$db->drop_field('groups', 'g_post_polls');

		// Make room for multiple moderator groups
		if (!$db->field_exists('groups', 'g_moderator'))
		{
			// Add g_moderator column to groups table
			$db->add_field('groups', 'g_moderator', 'TINYINT(1)', false, 0, 'g_user_title');

			// Give the moderator group moderator privileges
			$db->query('UPDATE '.$db->prefix.'groups SET g_moderator = 1 WHERE g_id = 2') or error('Unable to update moderator powers', __FILE__, __LINE__, $db->error());
		}

		// Replace obsolete p_mod_edit_users config setting with new per-group permission
		if (array_key_exists('p_mod_edit_users', $pun_config))
		{
			$db->query('DELETE FROM '.$db->prefix.'config WHERE conf_name = \'p_mod_edit_users\'') or error('Unable to update moderator powers', __FILE__, __LINE__, $db->error());

			$db->add_field('groups', 'g_mod_edit_users', 'TINYINT(1)', false, 0, 'g_moderator');

			$db->query('UPDATE '.$db->prefix.'groups SET g_mod_edit_users = '.$pun_config['p_mod_edit_users'].' WHERE g_moderator = 1') or error('Unable to update moderator powers', __FILE__, __LINE__, $db->error());
		}

		// Replace obsolete p_mod_rename_users config setting with new per-group permission
		if (array_key_exists('p_mod_rename_users', $pun_config))
		{
			$db->query('DELETE FROM '.$db->prefix.'config WHERE conf_name = \'p_mod_rename_users\'') or error('Unable to update moderator powers', __FILE__, __LINE__, $db->error());

			$db->add_field('groups', 'g_mod_rename_users', 'TINYINT(1)', false, 0, 'g_mod_edit_users');

			$db->query('UPDATE '.$db->prefix.'groups SET g_mod_rename_users = '.$pun_config['p_mod_rename_users'].' WHERE g_moderator = 1') or error('Unable to update moderator powers', __FILE__, __LINE__, $db->error());
		}

		// Replace obsolete p_mod_change_passwords config setting with new per-group permission
		if (array_key_exists('p_mod_change_passwords', $pun_config))
		{
			$db->query('DELETE FROM '.$db->prefix.'config WHERE conf_name = \'p_mod_change_passwords\'') or error('Unable to update moderator powers', __FILE__, __LINE__, $db->error());

			$db->add_field('groups', 'g_mod_change_passwords', 'TINYINT(1)', false, 0, 'g_mod_rename_users');

			$db->query('UPDATE '.$db->prefix.'groups SET g_mod_change_passwords = '.$pun_config['p_mod_change_passwords'].' WHERE g_moderator = 1') or error('Unable to update moderator powers', __FILE__, __LINE__, $db->error());
		}

		// Replace obsolete p_mod_ban_users config setting with new per-group permission
		if (array_key_exists('p_mod_ban_users', $pun_config))
		{
			$db->query('DELETE FROM '.$db->prefix.'config WHERE conf_name = \'p_mod_ban_users\'') or error('Unable to update moderator powers', __FILE__, __LINE__, $db->error());

			$db->add_field('groups', 'g_mod_ban_users', 'TINYINT(1)', false, 0, 'g_mod_change_passwords');

			$db->query('UPDATE '.$db->prefix.'groups SET g_mod_ban_users = '.$pun_config['p_mod_ban_users'].' WHERE g_moderator = 1') or error('Unable to update moderator powers', __FILE__, __LINE__, $db->error());
		}

		// We need to add a unique index to avoid users having multiple rows in the online table
		if (!$db->index_exists('online', 'user_id_ident_idx'))
		{
			$db->query('DELETE FROM '.$db->prefix.'online') or error('Unable to clear online table', __FILE__, __LINE__, $db->error());

			switch ($db_type)
			{
				case 'mysql':
				case 'mysqli':
				case 'mysql_innodb':
				case 'mysqli_innodb':
					$db->add_index('online', 'user_id_ident_idx', array('user_id', 'ident(25)'), true);
					break;

				default:
					$db->add_index('online', 'user_id_ident_idx', array('user_id', 'ident'), true);
					break;
			}
		}

		// Remove the redundant user_id_idx on the online table
		$db->drop_index('online', 'user_id_idx');

		// Add an index to ident on the online table
		switch ($db_type)
		{
			case 'mysql':
			case 'mysqli':
			case 'mysql_innodb':
			case 'mysqli_innodb':
				$db->add_index('online', 'ident_idx', array('ident(25)'));
				break;

			default:
				$db->add_index('online', 'ident_idx', array('ident'));
				break;
		}

		// Add an index to logged on the online table
		$db->add_index('online', 'logged_idx', array('logged'));

		// Add an index on last_post in the topics table
		$db->add_index('topics', 'last_post_idx', array('last_post'));

		// Add g_view_users field to groups table
		$db->add_field('groups', 'g_view_users', 'TINYINT(1)', false, 1, 'g_read_board');

		// Add the last_email_sent column to the users table and the g_send_email and
		// g_email_flood columns to the groups table
		$db->add_field('users', 'last_email_sent', 'INT(10) UNSIGNED', true, null, 'last_search');
		$db->add_field('groups', 'g_send_email', 'TINYINT(1)', false, 1, 'g_search_users');
		$db->add_field('groups', 'g_email_flood', 'SMALLINT(6)', false, 60, 'g_search_flood');

		// Set non-default g_send_email and g_flood_email values properly
		$db->query('UPDATE '.$db->prefix.'groups SET g_send_email = 0 WHERE g_id = 3') or error('Unable to update group email permissions', __FILE__, __LINE__, $db->error());
		$db->query('UPDATE '.$db->prefix.'groups SET g_email_flood = 0 WHERE g_id IN (1,2,3)') or error('Unable to update group email permissions', __FILE__, __LINE__, $db->error());

		// Add the auto notify/subscription option to the users table
		$db->add_field('users', 'auto_notify', 'TINYINT(1)', false, 0, 'notify_with_post');

		// Add the first_post_id column to the topics table
		if (!$db->field_exists('topics', 'first_post_id'))
		{
			$db->add_field('topics', 'first_post_id', 'INT(10) UNSIGNED', false, 0, 'posted');
			$db->add_index('topics', 'first_post_id_idx', array('first_post_id'));

			// Now that we've added the column and indexed it, we need to give it correct data\
			$result = $db->query('SELECT MIN(id) AS first_post, topic_id FROM '.$db->prefix.'posts GROUP BY topic_id') or error('Unable to fetch first_post_id', __FILE__, __LINE__, $db->error());

			while ($cur_post = $db->fetch_assoc($result))
			{
				$db->query('UPDATE '.$db->prefix.'topics SET first_post_id = '.$cur_post['first_post'].' WHERE id = '.$cur_post['topic_id']) or error('Unable to update first_post_id', __FILE__, __LINE__, $db->error());
			}
		}

		// Move any users with the old unverified status to their new group
		$db->query('UPDATE '.$db->prefix.'users SET group_id=0 WHERE group_id=32000') or error('Unable to move unverified users', __FILE__, __LINE__, $db->error());

		// Add the ban_creator column to the bans table
		$db->add_field('bans', 'ban_creator', 'INT(10) UNSIGNED', false, 0);

		// Add the time/date format settings to the user table
		$db->add_field('users', 'time_format', 'INT(10) UNSIGNED', false, 0, 'dst');
		$db->add_field('users', 'date_format', 'INT(10) UNSIGNED', false, 0, 'dst');

		// Should we do charset conversion or not?
		if (strpos($cur_version, '1.2') === 0 && isset($_GET['convert_charset']))
			$query_str = '?stage=conv_misc&req_old_charset='.$old_charset.'&req_per_page='.PER_PAGE;
		else
			$query_str = '?stage=conv_tables';
		break;


	// Convert config, categories, forums, groups, ranks and censor words
	case 'conv_misc':
		if (strpos($cur_version, '1.2') !== 0)
		{
			$query_str = '?stage=conv_tables';
			break;
		}

		// Convert config
		echo 'Converting configuration …'."<br />\n";
		foreach ($pun_config as $conf_name => $conf_value)
		{
			if (convert_to_utf8($conf_value, $old_charset))
			{
				$db->query('UPDATE '.$db->prefix.'config SET conf_value = \''.$db->escape($conf_value).'\' WHERE conf_name = \''.$conf_name.'\'') or error('Unable to update config', __FILE__, __LINE__, $db->error());
			}
		}

		// Convert categories
		echo 'Converting categories …'."<br />\n";
		$result = $db->query('SELECT id, cat_name FROM '.$db->prefix.'categories ORDER BY id') or error('Unable to fetch categories', __FILE__, __LINE__, $db->error());

		while ($cur_item = $db->fetch_assoc($result))
		{
			if (convert_to_utf8($cur_item['cat_name'], $old_charset))
			{
				$db->query('UPDATE '.$db->prefix.'categories SET cat_name = \''.$db->escape($cur_item['cat_name']).'\' WHERE id = '.$cur_item['id']) or error('Unable to update category', __FILE__, __LINE__, $db->error());
			}
		}

		// Convert forums
		echo 'Converting forums …'."<br />\n";
		$result = $db->query('SELECT id, forum_name, forum_desc, moderators FROM '.$db->prefix.'forums ORDER BY id') or error('Unable to fetch forums', __FILE__, __LINE__, $db->error());

		while ($cur_item = $db->fetch_assoc($result))
		{
			$moderators = ($cur_item['moderators'] != '') ? unserialize($cur_item['moderators']) : array();
			$moderators_utf8 = array();
			foreach ($moderators as $mod_username => $mod_user_id)
			{
				convert_to_utf8($mod_username, $old_charset);
				$moderators_utf8[$mod_username] = $mod_user_id;
			}

			if (convert_to_utf8($cur_item['forum_name'], $old_charset) | convert_to_utf8($cur_item['forum_desc'], $old_charset) || $moderators !== $moderators_utf8)
			{
				$cur_item['forum_desc'] = $cur_item['forum_desc'] != '' ? '\''.$db->escape($cur_item['forum_desc']).'\'' : 'NULL';
				$cur_item['moderators'] = !empty($moderators_utf8) ? '\''.$db->escape(serialize($moderators_utf8)).'\'' : 'NULL';

				$db->query('UPDATE '.$db->prefix.'forums SET forum_name = \''.$db->escape($cur_item['forum_name']).'\', forum_desc = '.$cur_item['forum_desc'].', moderators = '.$cur_item['moderators'].' WHERE id = '.$cur_item['id']) or error('Unable to update forum', __FILE__, __LINE__, $db->error());
			}
		}

		// Convert groups
		echo 'Converting groups …'."<br />\n";
		$result = $db->query('SELECT g_id, g_title, g_user_title FROM '.$db->prefix.'groups ORDER BY g_id') or error('Unable to fetch groups', __FILE__, __LINE__, $db->error());

		while ($cur_item = $db->fetch_assoc($result))
		{
			if (convert_to_utf8($cur_item['g_title'], $old_charset) | convert_to_utf8($cur_item['g_user_title'], $old_charset))
			{
				$cur_item['g_user_title'] = $cur_item['g_user_title'] != '' ? '\''.$db->escape($cur_item['g_user_title']).'\'' : 'NULL';

				$db->query('UPDATE '.$db->prefix.'groups SET g_title = \''.$db->escape($cur_item['g_title']).'\', g_user_title = '.$cur_item['g_user_title'].' WHERE g_id = '.$cur_item['g_id']) or error('Unable to update group', __FILE__, __LINE__, $db->error());
			}
		}

		// Convert ranks
		echo 'Converting ranks …'."<br />\n";
		$result = $db->query('SELECT id, rank FROM '.$db->prefix.'ranks ORDER BY id') or error('Unable to fetch ranks', __FILE__, __LINE__, $db->error());

		while ($cur_item = $db->fetch_assoc($result))
		{
			if (convert_to_utf8($cur_item['rank'], $old_charset))
			{
				$db->query('UPDATE '.$db->prefix.'ranks SET rank = \''.$db->escape($cur_item['rank']).'\' WHERE id = '.$cur_item['id']) or error('Unable to update rank', __FILE__, __LINE__, $db->error());
			}
		}

		// Convert censor words
		echo 'Converting censor words …'."<br />\n";
		$result = $db->query('SELECT id, search_for, replace_with FROM '.$db->prefix.'censoring ORDER BY id') or error('Unable to fetch censors', __FILE__, __LINE__, $db->error());

		while ($cur_item = $db->fetch_assoc($result))
		{
			if (convert_to_utf8($cur_item['search_for'], $old_charset) | convert_to_utf8($cur_item['replace_with'], $old_charset))
			{
				$db->query('UPDATE '.$db->prefix.'censoring SET search_for = \''.$db->escape($cur_item['search_for']).'\', replace_with = \''.$db->escape($cur_item['replace_with']).'\' WHERE id = '.$cur_item['id']) or error('Unable to update censor', __FILE__, __LINE__, $db->error());
			}
		}

		$query_str = '?stage=conv_reports&req_old_charset='.$old_charset.'&req_per_page='.PER_PAGE;
		break;


	// Convert reports
	case 'conv_reports':
		if (strpos($cur_version, '1.2') !== 0)
		{
			$query_str = '?stage=conv_tables';
			break;
		}

		// Determine where to start
		if ($start_at == 0)
		{
			$result = $db->query('SELECT id FROM '.$db->prefix.'reports ORDER BY id LIMIT 1') or error('Unable to fetch first report ID', __FILE__, __LINE__, $db->error());

			if ($db->num_rows($result))
				$start_at = $db->result($result);
		}
		$end_at = $start_at + PER_PAGE;

		// Fetch reports to process this cycle
		$result = $db->query('SELECT id, message FROM '.$db->prefix.'reports WHERE id >= '.$start_at.' AND id < '.$end_at.' ORDER BY id') or error('Unable to fetch reports', __FILE__, __LINE__, $db->error());

		while ($cur_item = $db->fetch_assoc($result))
		{
			echo 'Converting report '.$cur_item['id'].' …<br />'."\n";
			if (convert_to_utf8($cur_item['message'], $old_charset))
			{
				$db->query('UPDATE '.$db->prefix.'reports SET message = \''.$db->escape($cur_item['message']).'\' WHERE id = '.$cur_item['id']) or error('Unable to update report', __FILE__, __LINE__, $db->error());
			}
		}

		// Check if there is more work to do
		$result = $db->query('SELECT id FROM '.$db->prefix.'reports WHERE id >= '.$end_at.' ORDER BY id LIMIT 1') or error('Unable to fetch next ID', __FILE__, __LINE__, $db->error());

		if ($db->num_rows($result))
			$query_str = '?stage=conv_reports&req_old_charset='.$old_charset.'&req_per_page='.PER_PAGE.'&start_at='.$db->result($result);
		else
			$query_str = '?stage=conv_search_words&req_old_charset='.$old_charset.'&req_per_page='.PER_PAGE;
		break;


	// Convert search words
	case 'conv_search_words':
		if (strpos($cur_version, '1.2') !== 0)
		{
			$query_str = '?stage=conv_tables';
			break;
		}

		// Determine where to start
		if ($start_at == 0)
		{
			// Get the first search word ID from the db
			$result = $db->query('SELECT id FROM '.$db->prefix.'search_words ORDER BY id LIMIT 1') or error('Unable to fetch first search_words ID', __FILE__, __LINE__, $db->error());

			if ($db->num_rows($result))
				$start_at = $db->result($result);
		}
		$end_at = $start_at + PER_PAGE;

		// Fetch words to process this cycle
		$result = $db->query('SELECT id, word FROM '.$db->prefix.'search_words WHERE id >= '.$start_at.' AND id < '.$end_at.' ORDER BY id') or error('Unable to fetch search words', __FILE__, __LINE__, $db->error());

		while ($cur_item = $db->fetch_assoc($result))
		{
			echo 'Converting search word '.$cur_item['id'].' …<br />'."\n";
			if (convert_to_utf8($cur_item['word'], $old_charset))
			{
				$db->query('UPDATE '.$db->prefix.'search_words SET word = \''.$db->escape($cur_item['word']).'\' WHERE id = '.$cur_item['id']) or error('Unable to update search word', __FILE__, __LINE__, $db->error());
			}
		}

		// Check if there is more work to do
		$result = $db->query('SELECT id FROM '.$db->prefix.'search_words WHERE id >= '.$end_at.' ORDER BY id LIMIT 1') or error('Unable to fetch next ID', __FILE__, __LINE__, $db->error());

		if ($db->num_rows($result))
			$query_str = '?stage=conv_search_words&req_old_charset='.$old_charset.'&req_per_page='.PER_PAGE.'&start_at='.$db->result($result);
		else
			$query_str = '?stage=conv_users&req_old_charset='.$old_charset.'&req_per_page='.PER_PAGE;
		break;


	// Convert users
	case 'conv_users':
		if (strpos($cur_version, '1.2') !== 0)
		{
			$query_str = '?stage=conv_tables';
			break;
		}

		// Determine where to start
		if ($start_at == 0)
			$start_at = 2;

		$end_at = $start_at + PER_PAGE;

		// Fetch users to process this cycle
		$result = $db->query('SELECT id, username, title, realname, location, signature, admin_note FROM '.$db->prefix.'users WHERE id >= '.$start_at.' AND id < '.$end_at.' ORDER BY id') or error('Unable to fetch users', __FILE__, __LINE__, $db->error());

		while ($cur_item = $db->fetch_assoc($result))
		{
			echo 'Converting user '.$cur_item['id'].' …<br />'."\n";
			if (convert_to_utf8($cur_item['username'], $old_charset) | convert_to_utf8($cur_item['title'], $old_charset) | convert_to_utf8($cur_item['realname'], $old_charset) | convert_to_utf8($cur_item['location'], $old_charset) | convert_to_utf8($cur_item['signature'], $old_charset) | convert_to_utf8($cur_item['admin_note'], $old_charset))
			{
				$cur_item['title'] = $cur_item['title'] != '' ? '\''.$db->escape($cur_item['title']).'\'' : 'NULL';
				$cur_item['realname'] = $cur_item['realname'] != '' ? '\''.$db->escape($cur_item['realname']).'\'' : 'NULL';
				$cur_item['location'] = $cur_item['location'] != '' ? '\''.$db->escape($cur_item['location']).'\'' : 'NULL';
				$cur_item['signature'] = $cur_item['signature'] != '' ? '\''.$db->escape($cur_item['signature']).'\'' : 'NULL';
				$cur_item['admin_note'] = $cur_item['admin_note'] != '' ? '\''.$db->escape($cur_item['admin_note']).'\'' : 'NULL';

				$db->query('UPDATE '.$db->prefix.'users SET username = \''.$db->escape($cur_item['username']).'\', title = '.$cur_item['title'].', realname = '.$cur_item['realname'].', location = '.$cur_item['location'].', signature = '.$cur_item['signature'].', admin_note = '.$cur_item['admin_note'].' WHERE id = '.$cur_item['id']) or error('Unable to update user', __FILE__, __LINE__, $db->error());
			}
		}

		// Check if there is more work to do
		$result = $db->query('SELECT id FROM '.$db->prefix.'users WHERE id >= '.$end_at.' ORDER BY id LIMIT 1') or error('Unable to fetch next ID', __FILE__, __LINE__, $db->error());

		if ($db->num_rows($result))
			$query_str = '?stage=conv_users&req_old_charset='.$old_charset.'&req_per_page='.PER_PAGE.'&start_at='.$db->result($result);
		else
			$query_str = '?stage=conv_topics&req_old_charset='.$old_charset.'&req_per_page='.PER_PAGE;
		break;


	// Convert topics
	case 'conv_topics':
		if (strpos($cur_version, '1.2') !== 0)
		{
			$query_str = '?stage=conv_tables';
			break;
		}

		// Determine where to start
		if ($start_at == 0)
		{
			// Get the first topic ID from the db
			$result = $db->query('SELECT id FROM '.$db->prefix.'topics ORDER BY id LIMIT 1') or error('Unable to fetch first topic ID', __FILE__, __LINE__, $db->error());

			if ($db->num_rows($result))
				$start_at = $db->result($result);
		}
		$end_at = $start_at + PER_PAGE;

		// Fetch topics to process this cycle
		$result = $db->query('SELECT id, poster, subject, last_poster FROM '.$db->prefix.'topics WHERE id >= '.$start_at.' AND id < '.$end_at.' ORDER BY id') or error('Unable to fetch topics', __FILE__, __LINE__, $db->error());

		while ($cur_item = $db->fetch_assoc($result))
		{
			echo 'Converting topic '.$cur_item['id'].' …<br />'."\n";
			if (convert_to_utf8($cur_item['poster'], $old_charset) | convert_to_utf8($cur_item['subject'], $old_charset) | convert_to_utf8($cur_item['last_poster'], $old_charset))
			{
				$db->query('UPDATE '.$db->prefix.'topics SET poster = \''.$db->escape($cur_item['poster']).'\', subject = \''.$db->escape($cur_item['subject']).'\', last_poster = \''.$db->escape($cur_item['last_poster']).'\' WHERE id = '.$cur_item['id']) or error('Unable to update topic', __FILE__, __LINE__, $db->error());
			}
		}

		// Check if there is more work to do
		$result = $db->query('SELECT id FROM '.$db->prefix.'topics WHERE id >= '.$end_at.' ORDER BY id LIMIT 1') or error('Unable to fetch next ID', __FILE__, __LINE__, $db->error());

		if ($db->num_rows($result))
			$query_str = '?stage=conv_topics&req_old_charset='.$old_charset.'&req_per_page='.PER_PAGE.'&start_at='.$db->result($result);
		else
			$query_str = '?stage=conv_posts&req_old_charset='.$old_charset.'&req_per_page='.PER_PAGE;
		break;


	// Convert posts
	case 'conv_posts':
		if (strpos($cur_version, '1.2') !== 0)
		{
			$query_str = '?stage=conv_tables';
			break;
		}

		// Determine where to start
		if ($start_at == 0)
		{
			// Get the first post ID from the db
			$result = $db->query('SELECT id FROM '.$db->prefix.'posts ORDER BY id LIMIT 1') or error('Unable to fetch first post ID', __FILE__, __LINE__, $db->error());

			if ($db->num_rows($result))
				$start_at = $db->result($result);
		}
		$end_at = $start_at + PER_PAGE;

		// Fetch posts to process this cycle
		$result = $db->query('SELECT id, poster, message, edited_by FROM '.$db->prefix.'posts WHERE id >= '.$start_at.' AND id < '.$end_at.' ORDER BY id') or error('Unable to fetch posts', __FILE__, __LINE__, $db->error());

		while ($cur_item = $db->fetch_assoc($result))
		{
			echo 'Converting post '.$cur_item['id'].' …<br />'."\n";
			if (convert_to_utf8($cur_item['poster'], $old_charset) | convert_to_utf8($cur_item['message'], $old_charset) | convert_to_utf8($cur_item['edited_by'], $old_charset))
			{
				$cur_item['edited_by'] = $cur_item['edited_by'] != '' ? '\''.$db->escape($cur_item['edited_by']).'\'' : 'NULL';

				$db->query('UPDATE '.$db->prefix.'posts SET poster = \''.$db->escape($cur_item['poster']).'\', message = \''.$db->escape($cur_item['message']).'\', edited_by = '.$cur_item['edited_by'].' WHERE id = '.$cur_item['id']) or error('Unable to update post', __FILE__, __LINE__, $db->error());
			}
		}

		// Check if there is more work to do
		$result = $db->query('SELECT id FROM '.$db->prefix.'posts WHERE id >= '.$end_at.' ORDER BY id LIMIT 1') or error('Unable to fetch next ID', __FILE__, __LINE__, $db->error());

		if ($db->num_rows($result))
			$query_str = '?stage=conv_posts&req_old_charset='.$old_charset.'&req_per_page='.PER_PAGE.'&start_at='.$db->result($result);
		else
			$query_str = '?stage=conv_tables';
		break;


	// Convert table columns to utf8 (MySQL only)
	case 'conv_tables':
		// Do the cumbersome charset conversion of MySQL tables/columns
		if ($db_type == 'mysql' || $db_type == 'mysqli' || $db_type == 'mysql_innodb' || $db_type == 'mysqli_innodb')
		{
			echo 'Converting table '.$db->prefix.'bans …<br />'."\n"; flush();
			convert_table_utf8($db->prefix.'bans');
			echo 'Converting table '.$db->prefix.'categories …<br />'."\n"; flush();
			convert_table_utf8($db->prefix.'categories');
			echo 'Converting table '.$db->prefix.'censoring …<br />'."\n"; flush();
			convert_table_utf8($db->prefix.'censoring');
			echo 'Converting table '.$db->prefix.'config …<br />'."\n"; flush();
			convert_table_utf8($db->prefix.'config');
			echo 'Converting table '.$db->prefix.'forum_perms …<br />'."\n"; flush();
			convert_table_utf8($db->prefix.'forum_perms');
			echo 'Converting table '.$db->prefix.'forums …<br />'."\n"; flush();
			convert_table_utf8($db->prefix.'forums');
			echo 'Converting table '.$db->prefix.'groups …<br />'."\n"; flush();
			convert_table_utf8($db->prefix.'groups');
			echo 'Converting table '.$db->prefix.'online …<br />'."\n"; flush();
			convert_table_utf8($db->prefix.'online');
			echo 'Converting table '.$db->prefix.'posts …<br />'."\n"; flush();
			convert_table_utf8($db->prefix.'posts');
			echo 'Converting table '.$db->prefix.'ranks …<br />'."\n"; flush();
			convert_table_utf8($db->prefix.'ranks');
			echo 'Converting table '.$db->prefix.'reports …<br />'."\n"; flush();
			convert_table_utf8($db->prefix.'reports');
			echo 'Converting table '.$db->prefix.'search_cache …<br />'."\n"; flush();
			convert_table_utf8($db->prefix.'search_cache');
			echo 'Converting table '.$db->prefix.'search_matches …<br />'."\n"; flush();
			convert_table_utf8($db->prefix.'search_matches');
			echo 'Converting table '.$db->prefix.'search_words …<br />'."\n"; flush();
			convert_table_utf8($db->prefix.'search_words');
			echo 'Converting table '.$db->prefix.'subscriptions …<br />'."\n"; flush();
			convert_table_utf8($db->prefix.'subscriptions');
			echo 'Converting table '.$db->prefix.'topics …<br />'."\n"; flush();
			convert_table_utf8($db->prefix.'topics');
			echo 'Converting table '.$db->prefix.'users …<br />'."\n"; flush();
			convert_table_utf8($db->prefix.'users');
		}

		$query_str = '?stage=finish';
		break;

	// Show results page
	case 'finish':
		// Now we're definitely using UTF-8, so we convert the output properly
		$db->set_names('utf8');

		// We update the version number
		$db->query('UPDATE '.$db->prefix.'config SET conf_value = \''.UPDATE_TO.'\' WHERE conf_name = \'o_cur_version\'') or error('Unable to update version', __FILE__, __LINE__, $db->error());

		// And the database revision number
		$db->query('UPDATE '.$db->prefix.'config SET conf_value = \''.UPDATE_TO_DB_REVISION.'\' WHERE conf_name = \'o_database_revision\'') or error('Unable to update database revision number', __FILE__, __LINE__, $db->error());

		// This feels like a good time to synchronize the forums
		$result = $db->query('SELECT id FROM '.$db->prefix.'forums') or error('Unable to fetch forum IDs', __FILE__, __LINE__, $db->error());

		while ($row = $db->fetch_row($result))
			update_forum($row[0]);

		// We'll empty the search cache table as well (using DELETE FROM since SQLite does not support TRUNCATE TABLE)
		$db->query('DELETE FROM '.$db->prefix.'search_cache') or error('Unable to clear search cache', __FILE__, __LINE__, $db->error());

		// Empty the PHP cache
		forum_clear_cache();

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>FluxBB Database Update</title>
<link rel="stylesheet" type="text/css" href="style/Oxygen.css" />
</head>
<body>

<div id="punwrap">
<div id="puninstall" class="pun" style="margin: 10% 20% auto 20%">

<div class="blockform">
	<h2><span>FluxBB Update</span></h2>
	<div class="box">
		<div class="fakeform">
			<div class="inform">
				<p style="font-size: 1.1em">Your forum database was successfully updated. You may now <a href="<?php echo PUN_ROOT ?>index.php">go to the forum index</a>.</p>
			</div>
		</div>
	</div>
</div>

</div>
</div>

</body>
</html>
<?php

		break;
}

$db->end_transaction();
$db->close();

if ($query_str != '')
	exit('<script type="text/javascript">window.location="db_update.php'.$query_str.'"</script><br />JavaScript seems to be disabled. <a href="db_update.php'.$query_str.'">Click here to continue</a>.');
