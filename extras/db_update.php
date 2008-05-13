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


// This script updates the forum database to the latest version.
// Copy this file to the forum root directory and run it. Then remove it from
// the root directory.

define('UPDATE_TO', '1.3 Beta');

// An array of hotfix extensions that this version supersedes and replaces
$supersedes_ext = array('hotfix_13svn_test');

// The number of items to process per pageview (lower this if the update script times out during UTF-8 conversion)
define('PER_PAGE', 300);

define('MIN_MYSQL_VERSION', '4.1.2');


// Make sure we are running at least PHP 4.3.0
if (!function_exists('version_compare') || version_compare(PHP_VERSION, '4.3.0', '<'))
	exit('You are running PHP version '.PHP_VERSION.'. '.UPDATE_TO.' requires at least PHP 4.3.0 to run properly. You must upgrade your PHP installation before you can continue.');


define('FORUM_ROOT', './');
@include FORUM_ROOT.'config.php';


// If FORUM isn't defined, config.php is missing or corrupt or we are outside the root directory
if (!defined('FORUM'))
	exit('This file must be run from the forum root directory.');

// Enable debug mode
define('FORUM_DEBUG', 1);

// Turn on full PHP error reporting
error_reporting(E_ALL);

// Turn off magic_quotes_runtime
set_magic_quotes_runtime(0);

// Turn off PHP time limit
@set_time_limit(0);

// If a cookie name is not specified in config.php, we use the default (forum_cookie)
if (empty($cookie_name))
	$cookie_name = 'forum_cookie';

// If the cache directory is not specified, we use the default setting
if (!defined('FORUM_CACHE_DIR'))
	define('FORUM_CACHE_DIR', FORUM_ROOT.'cache/');

// Load the functions script
require FORUM_ROOT.'include/functions.php';


// Instruct DB abstraction layer that we don't want it to "SET NAMES". If we need to, we'll do it ourselves below.
define('FORUM_NO_SET_NAMES', 1);

// Load DB abstraction layer and try to connect
require FORUM_ROOT.'include/dblayer/common_db.php';


// Check current version
$result = $forum_db->query('SELECT conf_value FROM '.$forum_db->prefix.'config WHERE conf_name=\'o_cur_version\'');
$cur_version = $forum_db->result($result);
if (version_compare($cur_version, '1.2', '<'))
	error('Version mismatch. The database \''.$db_name.'\' doesn\'t seem to be running a FluxBB database schema supported by this update script.', __FILE__, __LINE__);

// If we've already done charset conversion in a previous update, we have to do SET NAMES
if (strpos($cur_version, '1.3') === 0)
	$forum_db->query('SET NAMES \'utf8\'') or error(__FILE__, __LINE__);


// If MySQL, make sure it's at least 4.1.2
if ($db_type == 'mysql' || $db_type == 'mysqli')
{
	$result = $forum_db->query('SELECT VERSION()') or error(__FILE__, __LINE__);
	$mysql_version = $forum_db->result($result);
	if (version_compare($mysql_version, MIN_MYSQL_VERSION, '<'))
		error('You are running MySQL version '.$mysql_version.'. FluxBB '.UPDATE_TO.' requires at least MySQL '.MIN_MYSQL_VERSION.' to run properly. You must upgrade your MySQL installation before you can continue.');
}


// Get the forum config
$result = $forum_db->query('SELECT * FROM '.$forum_db->prefix.'config');
while ($cur_config_item = $forum_db->fetch_row($result))
	$forum_config[$cur_config_item[0]] = $cur_config_item[1];

// If $base_url isn't set, use o_base_url from config
if (!isset($base_url))
	$base_url = $forum_config['o_base_url'];

// There's no $forum_user, but we need the style element
// We default to Oxygen if the default style is invalid (a 1.2 to 1.3 upgrade most likely)
if (file_exists(FORUM_ROOT.'style/'.$forum_config['o_default_style'].'/'.$forum_config['o_default_style'].'.php'))
	$forum_user['style'] = $forum_config['o_default_style'];
else
{
	$forum_user['style'] = 'Oxygen';

	$forum_db->query('UPDATE '.$forum_db->prefix.'config SET conf_value=\'Oxygen\' WHERE conf_name=\'o_default_style\'') or error(__FILE__, __LINE__);
}

// Make sure the default language exists
// We default to English if the default language is invalid (a 1.2 to 1.3 upgrade most likely)
if (!file_exists(FORUM_ROOT.'lang/'.$forum_config['o_default_lang'].'/common.php'))
{
	$forum_db->query('UPDATE '.$forum_db->prefix.'config SET conf_value=\'English\' WHERE conf_name=\'o_default_lang\'') or error(__FILE__, __LINE__);
}


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
	global $db_type, $forum_db;

	$seems_utf8 = true;

	$result = $forum_db->query('SELECT MIN(id), MAX(id) FROM '.$forum_db->prefix.'posts') or error(__FILE__, __LINE__);
	list($min_id, $max_id) = $forum_db->fetch_row($result);

	// Get a random soup of data and check if it appears to be UTF-8
	for ($i = 0; $i < 100; ++$i)
	{
		$id = ($i == 0) ? $min_id : (($i == 1) ? $max_id : rand($min_id, $max_id));

		$result = $forum_db->query('SELECT p.message, p.poster, t.subject, f.forum_name FROM '.$forum_db->prefix.'posts AS p INNER JOIN '.$forum_db->prefix.'topics AS t ON t.id = p.topic_id INNER JOIN '.$forum_db->prefix.'forums AS f ON f.id = t.forum_id WHERE p.id>='.$id.' LIMIT 1') or error(__FILE__, __LINE__);
		$temp = $forum_db->fetch_row($result);

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
	global $forum_db;

	$types = array(
		'char' 			=> 'binary',
		'varchar'		=> 'varbinary',
		'tinytext'		=> 'tinyblob',
		'mediumtext'	=> 'mediumblob',
		'text'			=> 'blob',
		'longtext'		=> 'longblob'
	);

	$convert_to_binary = array();
	$convert_to_utf8 = array();

	// Set table default charset to utf8
	$forum_db->query('ALTER TABLE `'.$table.'` CHARACTER SET utf8') or error(__FILE__, __LINE__);

	// Find out which columns need converting and build SQL statements
	$result = $forum_db->query('SHOW FULL COLUMNS FROM `'.$table.'`') or error(__FILE__, __LINE__);
	while ($cur_column = $forum_db->fetch_assoc($result))
	{
		list($type) = explode('(', $cur_column['Type']);
		if (isset($types[$type]) && strpos($cur_column['Collation'], 'utf8') === false)
		{
			$names = 'CHANGE `'. $cur_column['Field'] .'` `'. $cur_column['Field'] .'` ';

			$attributes = $cur_column['Null'] == 'YES' ? ' NULL' : ' NOT NULL';
			// Only supply a default value if a default value is specified
			if ($cur_column['Default'] !== null)
				$attributes .= ' DEFAULT \''.$forum_db->escape($cur_column['Default']).'\'';

			$convert_to_binary[] = $names.preg_replace('/'. $type .'/i', $types[$type], $cur_column['Type']) . $attributes;
			$convert_to_utf8[] = $names.$cur_column['Type'] .' CHARACTER SET utf8'. $attributes;
		}
	}

	if (!empty($convert_to_binary))
	{
		// Convert text columns to binary
		$forum_db->query('ALTER TABLE `'.$table.'` '.implode(', ', $convert_to_binary)) or error(__FILE__, __LINE__);
		// Convert binary columns to utf8
		$forum_db->query('ALTER TABLE `'.$table.'` '.implode(', ', $convert_to_utf8)) or error(__FILE__, __LINE__);
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
<?php

// Include the stylesheets
require FORUM_ROOT.'style/'.$forum_user['style'].'/'.$forum_user['style'].'.php';

?>
<script type="text/javascript" src="<?php echo $base_url ?>/include/js/common.js"></script>
</head>
<body>

<div id="brd-update" class="brd-page">
<div class="brd">

<div id="brd-title">
	<div><strong>FluxBB Database Update</strong></div>
</div>

<div id="brd-desc">
	<div>Update database tables of current installation</div>
</div>

<div id="brd-main" class="main">

	<h1><span>FluxBB Database Update</span></h1>

	<div class="main-head">
		<h2><span>Perform update of database tables</span></h2>
	</div>

	<div class="main-content frm">
		<div class="frm-info">
			<ul>
				<li class="warn"><span><strong>WARNING!</strong> This script will update your FluxBB forum database. The update procedure might take anything from a few seconds to a few minutes (or in extreme cases, hours) depending on the speed of the server, the size of the forum database and the number of changes required.</span></li>
				<li><span>Do not forget to make a backup of the database before continuing.</span></li>
				<li><span> Did you read the update instructions in the documentation? If not, start there.</span></li>
<?php

if (!$db_seems_utf8 || isset($_GET['force']))
{
	if (!function_exists('iconv') && !function_exists('mb_convert_encoding'))
	{

?>
				<li class="important"><strong>IMPORTANT!</strong> FluxBB has detected that this PHP environment does not have support for the encoding mechanisms required to do UTF-8 conversion from character sets other than ISO-8859-1. What this means is that if the current character set is not ISO-8859-1, FluxBB won't be able to convert your forum database to UTF-8 and you will have to do it manually. Instructions for doing manual charset conversion can be found in the update instructions.</span></li>
<?php

	}
}

if ($db_seems_utf8 && !isset($_GET['force']))
{

?>
				<li class="important"><span><strong>IMPORTANT!</strong> Based on a random selection of 100 posts, topic subjects, usernames and forum names from the database, it appears as if text in the database is currently UTF-8 encoded. This is a good thing. Based on this, the update process will not attempt to do charset conversion. If you have reason to believe that the charset conversion is required nonetheless, you can <a href="<?php echo $_SERVER['PHP_SELF'] ?>?force=1">force the conversion to run</a>.</span></li>
<?php

}

?>
			</ul>
		</div>
		<div id="req-msg" class="frm-warn">
			<p class="important"><strong>Important!</strong> All fields marked <em class="req-text">(Required)</em> must be completed before submitting this form.</p>
		</div>
		<form class="frm-form" method="get" accept-charset="utf-8" action="<?php echo $_SERVER['PHP_SELF'] ?>">
			<div class="hidden">
				<input type="hidden" name="stage" value="start" />
			</div>
<?php

		if (!$db_seems_utf8 || isset($_GET['force']))
		{

?>
			<div class="frm-info">
				<p class="important"><strong>Enable conversion:</strong> When enabled this update script will, after it has made the required structural changes to the database, convert all text in the database from the current character set to UTF-8. This conversion is required if you're upgrading from FluxBB 1.2 and you are not currently using an UTF-8 language pack.</p>
				<p class="important"><strong>Current character set:</strong> If the primary language in your forum is English, you can leave this at the default value. However, if your forum is non-English, you should enter the character set of the primary language pack used in the forum.</p>
			</div>
			<fieldset class="frm-set set1">
				<legend class="frm-legend"><span>Charset conversion</span></legend>
				<div class="radbox checkbox">
					<label for="fld1"><span class="fld-label">Enable conversion:</span><br /><input type="checkbox" id="fld1" name="convert_charset" value="1" checked="checked" /> Perform database charset conversion.</label>
				</div>
				<div class="frm-fld text required">
					<label for="fld2">
						<span class="fld-label">Current character set:</span><br />
						<span class="fld-input"><input type="text" id="fld2" name="req_old_charset" size="12" maxlength="20" value="ISO-8859-1" /></span>
						<em class="req-text">(Required)</em>
					</label>
				</div>
			</fieldset>
<?php

		}


?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="start" value="Start update" /></span>
			</div>
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
		// Put back dropped search tables
		if (!$forum_db->table_exists($forum_db->prefix.'search_cache') && ($db_type == 'mysql' || $db_type == 'mysqli'))
		{
			$sql = 'CREATE TABLE '.$forum_db->prefix."search_cache (
					id INT(10) UNSIGNED NOT NULL DEFAULT 0,
					ident VARCHAR(200) NOT NULL DEFAULT '',
					search_data TEXT,
					PRIMARY KEY (id)
					) ENGINE = MyISAM CHARACTER SET utf8";
					
			$forum_db->query($sql) or error(__FILE__, __LINE__);
					
			$sql = 'CREATE TABLE '.$forum_db->prefix."search_matches (
					post_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
					word_id MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT 0,
					subject_match TINYINT(1) NOT NULL DEFAULT 0
					) ENGINE = MyISAM CHARACTER SET utf8";
			
			$forum_db->query($sql) or error(__FILE__, __LINE__);
					
			$sql = 'CREATE TABLE '.$forum_db->prefix."search_words (
					id MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT,
					word VARCHAR(20) BINARY NOT NULL DEFAULT '',
					PRIMARY KEY (word),
					KEY ".$forum_db->prefix."search_words_id_idx (id)
					) ENGINE = MyISAM CHARACTER SET utf8";

			$forum_db->query($sql) or error(__FILE__, __LINE__);
		}
	
		// Add the extensions table if it doesn't already exist
		if (!$forum_db->table_exists($forum_db->prefix.'extensions'))
		{
			switch ($db_type)
			{
				case 'mysql':
				case 'mysqli':
					$sql = 'CREATE TABLE '.$forum_db->prefix."extensions (
							id VARCHAR(50) NOT NULL DEFAULT '',
							title VARCHAR(255) NOT NULL DEFAULT '',
							version VARCHAR(25) NOT NULL DEFAULT '',
							description TEXT,
							author VARCHAR(50) NOT NULL DEFAULT '',
							uninstall TEXT,
							uninstall_note TEXT,
							disabled TINYINT(1) NOT NULL DEFAULT 0,
							PRIMARY KEY(id)
							) ENGINE = MyISAM CHARACTER SET utf8";
					break;

				case 'pgsql':
					$sql = 'CREATE TABLE '.$forum_db->prefix."extensions (
							id VARCHAR(50) NOT NULL DEFAULT '',
							title VARCHAR(255) NOT NULL DEFAULT '',
							version VARCHAR(25) NOT NULL DEFAULT '',
							description TEXT,
							author VARCHAR(50) NOT NULL DEFAULT '',
							uninstall TEXT,
							uninstall_note TEXT,
							disabled SMALLINT NOT NULL DEFAULT 0,
							PRIMARY KEY(id)
							)";
					break;

				case 'sqlite':
					$sql = 'CREATE TABLE '.$forum_db->prefix."extensions (
							id VARCHAR(50) NOT NULL DEFAULT '',
							title VARCHAR(255) NOT NULL DEFAULT '',
							version VARCHAR(25) NOT NULL DEFAULT '',
							description TEXT,
							author VARCHAR(50) NOT NULL DEFAULT '',
							uninstall TEXT,
							uninstall_note TEXT,
							disabled INTEGER NOT NULL DEFAULT 0,
							PRIMARY KEY(id)
							)";
					break;
			}

			$forum_db->query($sql) or error(__FILE__, __LINE__);
		}

		// Add uninstall_note field to extensions
		$forum_db->add_field($forum_db->prefix.'extensions', 'uninstall_note', 'TEXT', true, null, 'uninstall');

		// Drop uninstall_notes (plural) field
		$forum_db->drop_field($forum_db->prefix.'extensions', 'uninstall_notes');

		// Add disabled field to extensions
		$forum_db->add_field($forum_db->prefix.'extensions', 'disabled', 'TINYINT(1)', false, 0, 'uninstall_note');

		// Add the extension_hooks table
		if (!$forum_db->table_exists($forum_db->prefix.'extension_hooks'))
		{
			switch ($db_type)
			{
				case 'mysql':
				case 'mysqli':
					$sql = 'CREATE TABLE '.$forum_db->prefix."extension_hooks (
							id VARCHAR(50) NOT NULL DEFAULT '',
							extension_id VARCHAR(50) NOT NULL DEFAULT '',
							code TEXT,
							installed INT(10) UNSIGNED NOT NULL DEFAULT 0,
							priority TINYINT(1) UNSIGNED NOT NULL DEFAULT 5,
							PRIMARY KEY(id, extension_id)
							) ENGINE = MyISAM CHARACTER SET utf8";
					break;

				case 'pgsql':
					$sql = 'CREATE TABLE '.$forum_db->prefix."extension_hooks (
							id VARCHAR(50) NOT NULL DEFAULT '',
							extension_id VARCHAR(50) NOT NULL DEFAULT '',
							code TEXT,
							installed INT NOT NULL DEFAULT 0,
							priority SMALLINT NOT NULL DEFAULT 5,
							PRIMARY KEY(id, extension_id)
							)";
					break;

				case 'sqlite':
					$sql = 'CREATE TABLE '.$forum_db->prefix."extension_hooks (
							id VARCHAR(50) NOT NULL DEFAULT '',
							extension_id VARCHAR(50) NOT NULL DEFAULT '',
							code TEXT,
							installed INTEGER NOT NULL DEFAULT 0,
							priority INTEGER NOT NULL DEFAULT 5,
							PRIMARY KEY(id, extension_id)
							)";
					break;
			}

			$forum_db->query($sql) or error(__FILE__, __LINE__);
		}

		// Add priority field to extension_hooks
		$forum_db->add_field($forum_db->prefix.'extension_hooks', 'priority', 'TINYINT(1)', false, 5, 'installed');

		if ($db_type == 'mysql' || $db_type == 'mysqli')
		{
			// Make all e-mail fields VARCHAR(80)
			$forum_db->query('ALTER TABLE '.$forum_db->prefix.'bans CHANGE email email VARCHAR(80)') or error(__FILE__, __LINE__);
			$forum_db->query('ALTER TABLE '.$forum_db->prefix.'posts CHANGE poster_email poster_email VARCHAR(80)') or error(__FILE__, __LINE__);
			$forum_db->query('ALTER TABLE '.$forum_db->prefix.'users CHANGE email email VARCHAR(80)') or error(__FILE__, __LINE__);
			$forum_db->query('ALTER TABLE '.$forum_db->prefix.'users CHANGE jabber jabber VARCHAR(80)') or error(__FILE__, __LINE__);
			$forum_db->query('ALTER TABLE '.$forum_db->prefix.'users CHANGE msn msn VARCHAR(80)') or error(__FILE__, __LINE__);
			$forum_db->query('ALTER TABLE '.$forum_db->prefix.'users CHANGE activate_string activate_string VARCHAR(80)') or error(__FILE__, __LINE__);

			// Remove NOT NULL from TEXT fields for consistency. See http://dev.punbb.org/changeset/596
			$forum_db->query('ALTER TABLE '.$forum_db->prefix.'posts CHANGE message message TEXT') or error(__FILE__, __LINE__);
			$forum_db->query('ALTER TABLE '.$forum_db->prefix.'reports CHANGE message message TEXT') or error(__FILE__, __LINE__);
			
			// Drop fulltext indexes  (should only apply to SVN installs)
			if ($forum_db->index_exists($forum_db->prefix.'topics', $forum_db->prefix.'topics_subject_idx'))
				$forum_db->query('ALTER TABLE '.$forum_db->prefix.'topics DROP INDEX '.$forum_db->prefix.'topics_subject_idx') or error(__FILE__, __LINE__);
			if ($forum_db->index_exists($forum_db->prefix.'posts', $forum_db->prefix.'posts_message_idx'))
				$forum_db->query('ALTER TABLE '.$forum_db->prefix.'posts DROP INDEX '.$forum_db->prefix.'posts_message_idx') or error(__FILE__, __LINE__);
		}

		// Make all IP fields VARCHAR(39) to support IPv6
		switch ($db_type)
		{
			case 'mysql':
			case 'mysqli':
				$forum_db->query('ALTER TABLE '.$forum_db->prefix.'posts CHANGE poster_ip poster_ip VARCHAR(39)') or error(__FILE__, __LINE__);
				$forum_db->query('ALTER TABLE '.$forum_db->prefix.'users CHANGE registration_ip registration_ip VARCHAR(39) NOT NULL DEFAULT \'0.0.0.0\'') or error(__FILE__, __LINE__);
				break;

			case 'pgsql':
				$forum_db->add_field($forum_db->prefix.'posts', 'tmp_poster_ip', 'VARCHAR(39)', true, null, 'poster_ip');
				$forum_db->query('UPDATE '.$forum_db->prefix.'posts SET tmp_poster_ip = poster_ip') or error(__FILE__, __LINE__);
				$forum_db->drop_field($forum_db->prefix.'posts', 'poster_ip');
				$forum_db->query('ALTER TABLE '.$forum_db->prefix.'posts RENAME COLUMN tmp_poster_ip TO poster_ip') or error(__FILE__, __LINE__);

				$forum_db->add_field($forum_db->prefix.'users', 'tmp_registration_ip', 'VARCHAR(39)', false, '0.0.0.0', 'registration_ip');
				$forum_db->query('UPDATE '.$forum_db->prefix.'users SET tmp_registration_ip = registration_ip') or error(__FILE__, __LINE__);
				$forum_db->drop_field($forum_db->prefix.'users', 'registration_ip');
				$forum_db->query('ALTER TABLE '.$forum_db->prefix.'users RENAME COLUMN tmp_registration_ip TO registration_ip') or error(__FILE__, __LINE__);
				break;

			case 'sqlite':
				break;
		}

		// Add the DST option to the users table
		$forum_db->add_field($forum_db->prefix.'users', 'dst', 'TINYINT(1)', false, 0, 'timezone');

		// Add the salt field to the users table
		$forum_db->add_field($forum_db->prefix.'users', 'salt', 'VARCHAR(12)', true, null, 'password');

		// Add the access_keys field to the users table
		$forum_db->add_field($forum_db->prefix.'users', 'access_keys', 'TINYINT(1)', false, 0, 'show_sig');

		// Add the CSRF token field to the online table
		$forum_db->add_field($forum_db->prefix.'online', 'csrf_token', 'VARCHAR(40)', false, '', null);

		// Add the prev_url field to the online table
		$forum_db->add_field($forum_db->prefix.'online', 'prev_url', 'VARCHAR(255)', true, null, null);

		// Drop use_avatar column from users table
		$forum_db->drop_field($forum_db->prefix.'users', 'use_avatar');

		// Add quote depth option
		if (!array_key_exists('o_quote_depth', $forum_config))
			$forum_db->query('INSERT INTO '.$forum_db->prefix.'config (conf_name, conf_value) VALUES(\'o_quote_depth\', \'3\')') or error(__FILE__, __LINE__);

		// Make sure we have o_additional_navlinks (was added in 1.2.1)
		if (!array_key_exists('o_additional_navlinks', $forum_config))
			$forum_db->query('INSERT INTO '.$forum_db->prefix.'config (conf_name, conf_value) VALUES(\'o_additional_navlinks\', \'\')') or error(__FILE__, __LINE__);

		// Insert new config options o_sef
		if (!array_key_exists('o_sef', $forum_config))
			$forum_db->query('INSERT INTO '.$forum_db->prefix.'config (conf_name, conf_value) VALUES(\'o_sef\', \'Default\')') or error(__FILE__, __LINE__);

		// Insert new config option o_topic_views
		if (!array_key_exists('o_topic_views', $forum_config))
			$forum_db->query('INSERT INTO '.$forum_db->prefix.'config (conf_name, conf_value) VALUES(\'o_topic_views\', \'1\')') or error(__FILE__, __LINE__);

		// Insert new config option o_signatures
		if (!array_key_exists('o_signatures', $forum_config))
			$forum_db->query('INSERT INTO '.$forum_db->prefix.'config (conf_name, conf_value) VALUES(\'o_signatures\', \'1\')') or error(__FILE__, __LINE__);

		// Insert new config option o_smtp_ssl
		if (!array_key_exists('o_smtp_ssl', $forum_config))
			$forum_db->query('INSERT INTO '.$forum_db->prefix.'config (conf_name, conf_value) VALUES(\'o_smtp_ssl\', \'0\')') or error(__FILE__, __LINE__);

		// Insert new config option o_check_for_updates
		if (!array_key_exists('o_check_for_updates', $forum_config))
		{
			$check_for_updates = (function_exists('curl_init') || function_exists('fsockopen') || in_array(strtolower(@ini_get('allow_url_fopen')), array('on', 'true', '1'))) ? 1 : 0;
			$forum_db->query('INSERT INTO '.$forum_db->prefix.'config (conf_name, conf_value) VALUES(\'o_check_for_updates\', \''.$check_for_updates.'\')') or error(__FILE__, __LINE__);
		}

		// Insert new config option o_announcement_heading
		if (!array_key_exists('o_announcement_heading', $forum_config))
			$forum_db->query('INSERT INTO '.$forum_db->prefix.'config (conf_name, conf_value) VALUES(\'o_announcement_heading\', \'\')') or error(__FILE__, __LINE__);

		// Server timezone is now simply the default timezone
		if (!array_key_exists('o_default_timezone', $forum_config))
			$forum_db->query('UPDATE '.$forum_db->prefix.'config SET conf_name=\'o_default_timezone\' WHERE conf_name=\'o_server_timezone\'') or error(__FILE__, __LINE__);

		// Increase visit timeout to 30 minutes (only if it hasn't been changed from the default)
		if ($forum_config['o_timeout_visit'] == '600')
			$forum_db->query('UPDATE '.$forum_db->prefix.'config SET conf_value=\'1800\' WHERE conf_name=\'o_timeout_visit\'') or error(__FILE__, __LINE__);

		// Remove obsolete g_post_polls permission from groups table
		if ($forum_db->field_exists($forum_db->prefix.'groups', 'g_post_polls') && $db_type != 'sqlite')	// No DROP column in SQLite
			$forum_db->query('ALTER TABLE '.$forum_db->prefix.'groups DROP g_post_polls') or error(__FILE__, __LINE__);

		// Make room for multiple moderator groups
		if (!$forum_db->field_exists($forum_db->prefix.'groups', 'g_moderator'))
		{
			// Add g_moderator column to groups table
			$forum_db->add_field($forum_db->prefix.'groups', 'g_moderator', 'TINYINT(1)', false, 0, 'g_user_title');

			// Give the moderator group moderator privileges
			$forum_db->query('UPDATE '.$forum_db->prefix.'groups SET g_moderator=1 WHERE g_id=2') or error(__FILE__, __LINE__);

			// Shuffle the group IDs around a bit
			$result = $forum_db->query('SELECT MAX(g_id)+1 FROM '.$forum_db->prefix.'groups') or error(__FILE__, __LINE__);
			$temp_id = $forum_db->result($result);

			$forum_db->query('UPDATE '.$forum_db->prefix.'groups SET g_id='.$temp_id.' WHERE g_id=2') or error(__FILE__, __LINE__);
			$forum_db->query('UPDATE '.$forum_db->prefix.'groups SET g_id=2 WHERE g_id=3') or error(__FILE__, __LINE__);
			$forum_db->query('UPDATE '.$forum_db->prefix.'groups SET g_id=3 WHERE g_id=4') or error(__FILE__, __LINE__);
			$forum_db->query('UPDATE '.$forum_db->prefix.'groups SET g_id=4 WHERE g_id='.$temp_id) or error(__FILE__, __LINE__);

			$forum_db->query('UPDATE '.$forum_db->prefix.'users SET group_id='.$temp_id.' WHERE group_id=2') or error(__FILE__, __LINE__);
			$forum_db->query('UPDATE '.$forum_db->prefix.'users SET group_id=2 WHERE group_id=3') or error(__FILE__, __LINE__);
			$forum_db->query('UPDATE '.$forum_db->prefix.'users SET group_id=3 WHERE group_id=4') or error(__FILE__, __LINE__);
			$forum_db->query('UPDATE '.$forum_db->prefix.'users SET group_id=4 WHERE group_id='.$temp_id) or error(__FILE__, __LINE__);

			$forum_db->query('UPDATE '.$forum_db->prefix.'forum_perms SET group_id='.$temp_id.' WHERE group_id=2') or error(__FILE__, __LINE__);
			$forum_db->query('UPDATE '.$forum_db->prefix.'forum_perms SET group_id=2 WHERE group_id=3') or error(__FILE__, __LINE__);
			$forum_db->query('UPDATE '.$forum_db->prefix.'forum_perms SET group_id=3 WHERE group_id=4') or error(__FILE__, __LINE__);
			$forum_db->query('UPDATE '.$forum_db->prefix.'forum_perms SET group_id=4 WHERE group_id='.$temp_id) or error(__FILE__, __LINE__);

			// Update the default usergroup if it uses the old ID for the members group
			$forum_db->query('UPDATE '.$forum_db->prefix.'config SET conf_value="3" WHERE conf_name="o_default_user_group" and conf_value="4"') or error(__FILE__, __LINE__);
		}

		// Replace obsolete p_mod_edit_users config setting with new per-group permission
		if (array_key_exists('p_mod_edit_users', $forum_config))
		{
			$forum_db->query('DELETE FROM '.$forum_db->prefix.'config WHERE conf_name=\'p_mod_edit_users\'') or error(__FILE__, __LINE__);
			$forum_db->add_field($forum_db->prefix.'groups', 'g_mod_edit_users', 'TINYINT(1)', false, 0, 'g_moderator');
			$forum_db->query('UPDATE '.$forum_db->prefix.'groups SET g_mod_edit_users='.$forum_config['p_mod_edit_users'].' WHERE g_moderator=1') or error(__FILE__, __LINE__);
		}

		// Replace obsolete p_mod_rename_users config setting with new per-group permission
		if (array_key_exists('p_mod_rename_users', $forum_config))
		{
			$forum_db->query('DELETE FROM '.$forum_db->prefix.'config WHERE conf_name=\'p_mod_rename_users\'') or error(__FILE__, __LINE__);
			$forum_db->add_field($forum_db->prefix.'groups', 'g_mod_rename_users', 'TINYINT(1)', false, 0, 'g_mod_edit_users');
			$forum_db->query('UPDATE '.$forum_db->prefix.'groups SET g_mod_rename_users='.$forum_config['p_mod_rename_users'].' WHERE g_moderator=1') or error(__FILE__, __LINE__);
		}

		// Replace obsolete p_mod_change_passwords config setting with new per-group permission
		if (array_key_exists('p_mod_change_passwords', $forum_config))
		{
			$forum_db->query('DELETE FROM '.$forum_db->prefix.'config WHERE conf_name=\'p_mod_change_passwords\'') or error(__FILE__, __LINE__);
			$forum_db->add_field($forum_db->prefix.'groups', 'g_mod_change_passwords', 'TINYINT(1)', false, 0, 'g_mod_rename_users');
			$forum_db->query('UPDATE '.$forum_db->prefix.'groups SET g_mod_change_passwords='.$forum_config['p_mod_change_passwords'].' WHERE g_moderator=1') or error(__FILE__, __LINE__);
		}

		// Replace obsolete p_mod_ban_users config setting with new per-group permission
		if (array_key_exists('p_mod_ban_users', $forum_config))
		{
			$forum_db->query('DELETE FROM '.$forum_db->prefix.'config WHERE conf_name=\'p_mod_ban_users\'') or error(__FILE__, __LINE__);
			$forum_db->add_field($forum_db->prefix.'groups', 'g_mod_ban_users', 'TINYINT(1)', false, 0, 'g_mod_change_passwords');
			$forum_db->query('UPDATE '.$forum_db->prefix.'groups SET g_mod_ban_users='.$forum_config['p_mod_ban_users'].' WHERE g_moderator=1') or error(__FILE__, __LINE__);
		}

		// We need to add a unique index to avoid users having multiple rows in the online table
		if (!$forum_db->index_exists($forum_db->prefix.'online', $forum_db->prefix.'online_user_id_ident_idx'))
		{
			$forum_db->query('DELETE FROM '.$forum_db->prefix.'online') or error(__FILE__, __LINE__);

			switch ($db_type)
			{
				case 'mysql':
				case 'mysqli':
					$forum_db->query('ALTER TABLE '.$forum_db->prefix.'online ADD UNIQUE INDEX '.$forum_db->prefix.'online_user_id_ident_idx (user_id, ident(25))') or error(__FILE__, __LINE__);
					break;

				default:
					$forum_db->query('CREATE UNIQUE INDEX '.$forum_db->prefix.'online_user_id_ident_idx ON '.$forum_db->prefix.'online(user_id, ident)') or error(__FILE__, __LINE__);
					break;
			}
		}

		// Add an index on last_post in the topics table
		if (!$forum_db->index_exists($forum_db->prefix.'topics', $forum_db->prefix.'topics_last_post_idx'))
		{
			switch ($db_type)
			{
				case 'mysql':
				case 'mysqli':
					$forum_db->query('ALTER TABLE '.$forum_db->prefix.'topics ADD INDEX '.$forum_db->prefix.'topics_last_post_idx(last_post)') or error(__FILE__, __LINE__);
					break;

				default:
					$forum_db->query('CREATE INDEX '.$forum_db->prefix.'topics_last_post_idx ON '.$forum_db->prefix.'topics(last_post)') or error(__FILE__, __LINE__);
					break;
			}
		}

		// Remove any remnants of the now defunct post approval system
		$forum_db->drop_field($forum_db->prefix.'forums', 'approval');
		$forum_db->drop_field($forum_db->prefix.'groups', 'g_posts_approved');
		$forum_db->drop_field($forum_db->prefix.'posts', 'approved');

		// Add g_view_users field to groups table
		$forum_db->add_field($forum_db->prefix.'groups', 'g_view_users', 'TINYINT(1)', false, 1, 'g_read_board');

		// Add the time/date format settings to the user table
		$forum_db->add_field($forum_db->prefix.'users', 'time_format', 'INT(10)', false, 0, 'dst');
		$forum_db->add_field($forum_db->prefix.'users', 'date_format', 'INT(10)', false, 0, 'dst');

		// Add the last_search column to the users table
		$forum_db->add_field($forum_db->prefix.'users', 'last_search', 'INT(10)', true, null, 'last_post');

		// Add the last_email_sent column to the users table and the g_send_email and
		// g_email_flood columns to the groups table
		$forum_db->add_field($forum_db->prefix.'users', 'last_email_sent', 'INT(10)', true, null, 'last_search');
		$forum_db->add_field($forum_db->prefix.'groups', 'g_send_email', 'TINYINT(1)', false, 1, 'g_search_users');
		$forum_db->add_field($forum_db->prefix.'groups', 'g_email_flood', 'INT(10)', false, 60, 'g_search_flood');

		// Set non-default g_send_email and g_flood_email values properly
		$forum_db->query('UPDATE '.$forum_db->prefix.'groups SET g_send_email=0 WHERE g_id=2') or error(__FILE__, __LINE__);
		$forum_db->query('UPDATE '.$forum_db->prefix.'groups SET g_email_flood=0 WHERE g_id IN (1,2,4)') or error(__FILE__, __LINE__);

		// Add the auto notify/subscription option to the users table
		$forum_db->add_field($forum_db->prefix.'users', 'auto_notify', 'TINYINT(1)', false, 0, 'notify_with_post');

		// Add the first_post_id column to the topics table
		if (!$forum_db->field_exists($forum_db->prefix.'topics', 'first_post_id'))
		{
			switch ($db_type)
			{
				case 'mysql':
				case 'mysqli':
					$forum_db->query('ALTER TABLE '.$forum_db->prefix.'topics ADD first_post_id INT(10) UNSIGNED NOT NULL DEFAULT 0 AFTER posted') or error(__FILE__, __LINE__);
					$forum_db->query('ALTER TABLE '.$forum_db->prefix.'topics ADD INDEX '.$forum_db->prefix.'topics_first_post_id_idx(first_post_id)') or error(__FILE__, __LINE__);
					break;

				default:
					$forum_db->add_field($forum_db->prefix.'topics', 'first_post_id', 'INT', false, 0, null);
					$forum_db->query('CREATE INDEX '.$forum_db->prefix.'topics_first_post_id_idx ON '.$forum_db->prefix.'topics(first_post_id)') or error(__FILE__, __LINE__);
					break;
			}

			// Now that we've added the column and indexed it, we need to give it correct data
			$result = $forum_db->query('SELECT min(id) AS first_post, topic_id FROM '.$forum_db->prefix.'posts GROUP BY topic_id') or error(__FILE__, __LINE__);

			while ($cur_post = $forum_db->fetch_assoc($result))
			{
				$forum_db->query('UPDATE '.$forum_db->prefix.'topics SET first_post_id = '.$cur_post['first_post'].' WHERE id = '.$cur_post['topic_id']) or error(__FILE__, __LINE__);
			}
		}

		// Move any users with the old unverified status to their new group
		$forum_db->query('UPDATE '.$forum_db->prefix.'users SET group_id=0 WHERE group_id=32000') or error(__FILE__, __LINE__);

		// Add the ban_creator column to the bans table
		if (!$forum_db->field_exists($forum_db->prefix.'bans', 'ban_creator'))
		{
			switch ($db_type)
			{
				case 'mysql':
				case 'mysqli':
					$forum_db->query('ALTER TABLE '.$forum_db->prefix.'bans ADD ban_creator INT(10) UNSIGNED NOT NULL DEFAULT 0') or error(__FILE__, __LINE__);
					break;

				default:
					$forum_db->add_field($forum_db->prefix.'bans', 'ban_creator', 'INT', false, 0, null);
					break;
			}
		}

		// Remove any hotfix extensions this update supersedes
		if (!empty($supersedes_ext))
		{
			$forum_db->query('DELETE FROM '.$forum_db->prefix.'extension_hooks WHERE extension_id IN(\''.implode('\',\'', $supersedes_ext).'\')') or error(__FILE__, __LINE__);
			$forum_db->query('DELETE FROM '.$forum_db->prefix.'extensions WHERE id IN(\''.implode('\',\'', $supersedes_ext).'\')') or error(__FILE__, __LINE__);
		}

		// Should we do charset conversion or not?
		if (isset($_GET['convert_charset']))
			$query_str = '?stage=conv_misc&req_old_charset='.$old_charset.'&req_per_page='.PER_PAGE;
		else
			$query_str = '?stage=conv_tables';
		break;


	// Convert config, categories, forums, groups, ranks and censor words
	case 'conv_misc':
		// Convert config
		echo 'Converting configuration …'."<br />\n";
		foreach ($forum_config as $conf_name => $conf_value)
		{
			if (convert_to_utf8($conf_value, $old_charset))
				$forum_db->query('UPDATE '.$forum_db->prefix.'config SET conf_value=\''.$forum_db->escape($conf_value).'\' WHERE conf_name=\''.$conf_name.'\'') or error(__FILE__, __LINE__);
		}

		// Convert categories
		echo 'Converting categories …'."<br />\n";
		$result = $forum_db->query('SELECT id, cat_name FROM '.$forum_db->prefix.'categories ORDER BY id') or error(__FILE__, __LINE__);
		while ($cur_item = $forum_db->fetch_assoc($result))
		{
			if (convert_to_utf8($cur_item['cat_name'], $old_charset))
				$forum_db->query('UPDATE '.$forum_db->prefix.'categories SET cat_name=\''.$forum_db->escape($cur_item['cat_name']).'\' WHERE id='.$cur_item['id']) or error(__FILE__, __LINE__);
		}

		// Convert forums
		echo 'Converting forums …'."<br />\n";
		$result = $forum_db->query('SELECT id, forum_name, forum_desc, moderators FROM '.$forum_db->prefix.'forums ORDER BY id') or error(__FILE__, __LINE__);
		while ($cur_item = $forum_db->fetch_assoc($result))
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
				$cur_item['forum_desc'] = $cur_item['forum_desc'] != '' ? '\''.$forum_db->escape($cur_item['forum_desc']).'\'' : 'NULL';
				$cur_item['moderators'] = !empty($moderators_utf8) ? '\''.$forum_db->escape(serialize($moderators_utf8)).'\'' : 'NULL';

				$forum_db->query('UPDATE '.$forum_db->prefix.'forums SET forum_name=\''.$forum_db->escape($cur_item['forum_name']).'\', forum_desc='.$cur_item['forum_desc'].', moderators='.$cur_item['moderators'].' WHERE id='.$cur_item['id']) or error(__FILE__, __LINE__);
			}
		}

		// Convert groups
		echo 'Converting groups …'."<br />\n";
		$result = $forum_db->query('SELECT g_id, g_title, g_user_title FROM '.$forum_db->prefix.'groups ORDER BY g_id') or error(__FILE__, __LINE__);
		while ($cur_item = $forum_db->fetch_assoc($result))
		{
			if (convert_to_utf8($cur_item['g_title'], $old_charset) | convert_to_utf8($cur_item['g_user_title'], $old_charset))
			{
				$cur_item['g_user_title'] = $cur_item['g_user_title'] != '' ? '\''.$forum_db->escape($cur_item['g_user_title']).'\'' : 'NULL';

				$forum_db->query('UPDATE '.$forum_db->prefix.'groups SET g_title=\''.$forum_db->escape($cur_item['g_title']).'\', g_user_title='.$cur_item['g_user_title'].' WHERE g_id='.$cur_item['g_id']) or error(__FILE__, __LINE__);
			}
		}

		// Convert ranks
		echo 'Converting ranks …'."<br />\n";
		$result = $forum_db->query('SELECT id, rank FROM '.$forum_db->prefix.'ranks ORDER BY id') or error(__FILE__, __LINE__);
		while ($cur_item = $forum_db->fetch_assoc($result))
		{
			if (convert_to_utf8($cur_item['rank'], $old_charset))
				$forum_db->query('UPDATE '.$forum_db->prefix.'ranks SET rank=\''.$forum_db->escape($cur_item['rank']).'\' WHERE id='.$cur_item['id']) or error(__FILE__, __LINE__);
		}

		// Convert censor words
		echo 'Converting censor words …'."<br />\n";
		$result = $forum_db->query('SELECT id, search_for, replace_with FROM '.$forum_db->prefix.'censoring ORDER BY id') or error(__FILE__, __LINE__);
		while ($cur_item = $forum_db->fetch_assoc($result))
		{
			if (convert_to_utf8($cur_item['search_for'], $old_charset) | convert_to_utf8($cur_item['replace_with'], $old_charset))
				$forum_db->query('UPDATE '.$forum_db->prefix.'censoring SET search_for=\''.$forum_db->escape($cur_item['search_for']).'\', replace_with=\''.$forum_db->escape($cur_item['replace_with']).'\' WHERE id='.$cur_item['id']) or error(__FILE__, __LINE__);
		}

		$query_str = '?stage=conv_reports&req_old_charset='.$old_charset.'&req_per_page='.PER_PAGE;
		break;


	// Convert reports
	case 'conv_reports':
		// Determine where to start
		if ($start_at == 0)
		{
			// Get the first report ID from the db
			$result = $forum_db->query('SELECT id FROM '.$forum_db->prefix.'reports ORDER BY id LIMIT 1') or error(__FILE__, __LINE__);
			if ($forum_db->num_rows($result))
				$start_at = $forum_db->result($result);
		}
		$end_at = $start_at + PER_PAGE;

		// Fetch reports to process this cycle
		$result = $forum_db->query('SELECT id, message FROM '.$forum_db->prefix.'reports WHERE id>='.$start_at.' AND id<'.$end_at.' ORDER BY id') or error(__FILE__, __LINE__);
		while ($cur_item = $forum_db->fetch_assoc($result))
		{
			echo 'Converting report '.$cur_item['id'].' …<br />'."\n";
			if (convert_to_utf8($cur_item['message'], $old_charset))
				$forum_db->query('UPDATE '.$forum_db->prefix.'reports SET message=\''.$forum_db->escape($cur_item['message']).'\' WHERE id='.$cur_item['id']) or error(__FILE__, __LINE__);
		}

		// Check if there is more work to do
		$result = $forum_db->query('SELECT id FROM '.$forum_db->prefix.'reports WHERE id>='.$end_at) or error(__FILE__, __LINE__);
		if ($forum_db->num_rows($result))
			$query_str = '?stage=conv_reports&req_old_charset='.$old_charset.'&req_per_page='.PER_PAGE.'&start_at='.$end_at;
		else
			$query_str = '?stage=conv_search_words&req_old_charset='.$old_charset.'&req_per_page='.PER_PAGE;
		break;


	// Convert search words
	case 'conv_search_words':
		// Determine where to start
		if ($start_at == 0)
		{
			// Get the first search word ID from the db
			$result = $forum_db->query('SELECT id FROM '.$forum_db->prefix.'search_words ORDER BY id LIMIT 1') or error(__FILE__, __LINE__);
			if ($forum_db->num_rows($result))
				$start_at = $forum_db->result($result);
		}
		$end_at = $start_at + PER_PAGE;

		// Fetch words to process this cycle
		$result = $forum_db->query('SELECT id, word FROM '.$forum_db->prefix.'search_words WHERE id>='.$start_at.' AND id<'.$end_at.' ORDER BY id') or error(__FILE__, __LINE__);
		while ($cur_item = $forum_db->fetch_assoc($result))
		{
			echo 'Converting search word '.$cur_item['id'].' …<br />'."\n";
			if (convert_to_utf8($cur_item['word'], $old_charset))
				$forum_db->query('UPDATE '.$forum_db->prefix.'search_words SET word=\''.$forum_db->escape($cur_item['word']).'\' WHERE id='.$cur_item['id']) or error(__FILE__, __LINE__);
		}

		// Check if there is more work to do
		$result = $forum_db->query('SELECT id FROM '.$forum_db->prefix.'search_words WHERE id>='.$end_at) or error(__FILE__, __LINE__);
		if ($forum_db->num_rows($result))
			$query_str = '?stage=conv_search_words&req_old_charset='.$old_charset.'&req_per_page='.PER_PAGE.'&start_at='.$end_at;
		else
			$query_str = '?stage=conv_users&req_old_charset='.$old_charset.'&req_per_page='.PER_PAGE;
		break;


	// Convert users
	case 'conv_users':
		// Determine where to start
		if ($start_at == 0)
			$start_at = 2;

		$end_at = $start_at + PER_PAGE;

		// Fetch users to process this cycle
		$result = $forum_db->query('SELECT id, username, title, realname, location, signature, admin_note FROM '.$forum_db->prefix.'users WHERE id>='.$start_at.' AND id<'.$end_at.' ORDER BY id') or error(__FILE__, __LINE__);
		while ($cur_item = $forum_db->fetch_assoc($result))
		{
			echo 'Converting user '.$cur_item['id'].' …<br />'."\n";
			if (convert_to_utf8($cur_item['username'], $old_charset) | convert_to_utf8($cur_item['title'], $old_charset) | convert_to_utf8($cur_item['realname'], $old_charset) | convert_to_utf8($cur_item['location'], $old_charset) | convert_to_utf8($cur_item['signature'], $old_charset) | convert_to_utf8($cur_item['admin_note'], $old_charset))
			{
				$cur_item['title'] = $cur_item['title'] != '' ? '\''.$forum_db->escape($cur_item['title']).'\'' : 'NULL';
				$cur_item['realname'] = $cur_item['realname'] != '' ? '\''.$forum_db->escape($cur_item['realname']).'\'' : 'NULL';
				$cur_item['location'] = $cur_item['location'] != '' ? '\''.$forum_db->escape($cur_item['location']).'\'' : 'NULL';
				$cur_item['signature'] = $cur_item['signature'] != '' ? '\''.$forum_db->escape($cur_item['signature']).'\'' : 'NULL';
				$cur_item['admin_note'] = $cur_item['admin_note'] != '' ? '\''.$forum_db->escape($cur_item['admin_note']).'\'' : 'NULL';

				$forum_db->query('UPDATE '.$forum_db->prefix.'users SET username=\''.$forum_db->escape($cur_item['username']).'\', title='.$cur_item['title'].', realname='.$cur_item['realname'].', location='.$cur_item['location'].', signature='.$cur_item['signature'].', admin_note='.$cur_item['admin_note'].' WHERE id='.$cur_item['id']) or error(__FILE__, __LINE__);
			}
		}

		// Check if there is more work to do
		$result = $forum_db->query('SELECT id FROM '.$forum_db->prefix.'users WHERE id>='.$end_at) or error(__FILE__, __LINE__);
		if ($forum_db->num_rows($result))
			$query_str = '?stage=conv_users&req_old_charset='.$old_charset.'&req_per_page='.PER_PAGE.'&start_at='.$end_at;
		else
			$query_str = '?stage=conv_topics&req_old_charset='.$old_charset.'&req_per_page='.PER_PAGE;
		break;


	// Convert topics
	case 'conv_topics':
		// Determine where to start
		if ($start_at == 0)
		{
			// Get the first topic ID from the db
			$result = $forum_db->query('SELECT id FROM '.$forum_db->prefix.'topics ORDER BY id LIMIT 1') or error(__FILE__, __LINE__);
			if ($forum_db->num_rows($result))
				$start_at = $forum_db->result($result);
		}
		$end_at = $start_at + PER_PAGE;

		// Fetch topics to process this cycle
		$result = $forum_db->query('SELECT id, poster, subject, last_poster FROM '.$forum_db->prefix.'topics WHERE id>='.$start_at.' AND id<'.$end_at.' ORDER BY id') or error(__FILE__, __LINE__);
		while ($cur_item = $forum_db->fetch_assoc($result))
		{
			echo 'Converting topic '.$cur_item['id'].' …<br />'."\n";
			if (convert_to_utf8($cur_item['poster'], $old_charset) | convert_to_utf8($cur_item['subject'], $old_charset) | convert_to_utf8($cur_item['last_poster'], $old_charset))
				$forum_db->query('UPDATE '.$forum_db->prefix.'topics SET poster=\''.$forum_db->escape($cur_item['poster']).'\', subject=\''.$forum_db->escape($cur_item['subject']).'\', last_poster=\''.$forum_db->escape($cur_item['last_poster']).'\' WHERE id='.$cur_item['id']) or error(__FILE__, __LINE__);
		}

		// Check if there is more work to do
		$result = $forum_db->query('SELECT id FROM '.$forum_db->prefix.'topics WHERE id>='.$end_at) or error(__FILE__, __LINE__);
		if ($forum_db->num_rows($result))
			$query_str = '?stage=conv_topics&req_old_charset='.$old_charset.'&req_per_page='.PER_PAGE.'&start_at='.$end_at;
		else
			$query_str = '?stage=conv_posts&req_old_charset='.$old_charset.'&req_per_page='.PER_PAGE;
		break;


	// Convert posts
	case 'conv_posts':
		// Determine where to start
		if ($start_at == 0)
		{
			// Get the first post ID from the db
			$result = $forum_db->query('SELECT id FROM '.$forum_db->prefix.'posts ORDER BY id LIMIT 1') or error(__FILE__, __LINE__);
			if ($forum_db->num_rows($result))
				$start_at = $forum_db->result($result);
		}
		$end_at = $start_at + PER_PAGE;

		// Fetch posts to process this cycle
		$result = $forum_db->query('SELECT id, poster, message, edited_by FROM '.$forum_db->prefix.'posts WHERE id>='.$start_at.' AND id<'.$end_at.' ORDER BY id') or error(__FILE__, __LINE__);
		while ($cur_item = $forum_db->fetch_assoc($result))
		{
			echo 'Converting post '.$cur_item['id'].' …<br />'."\n";
			if (convert_to_utf8($cur_item['poster'], $old_charset) | convert_to_utf8($cur_item['message'], $old_charset) | convert_to_utf8($cur_item['edited_by'], $old_charset))
			{
				$cur_item['edited_by'] = $cur_item['edited_by'] != '' ? '\''.$forum_db->escape($cur_item['edited_by']).'\'' : 'NULL';

				$forum_db->query('UPDATE '.$forum_db->prefix.'posts SET poster=\''.$forum_db->escape($cur_item['poster']).'\', message=\''.$forum_db->escape($cur_item['message']).'\', edited_by='.$cur_item['edited_by'].' WHERE id='.$cur_item['id']) or error(__FILE__, __LINE__);
			}
		}

		// Check if there is more work to do
		$result = $forum_db->query('SELECT id FROM '.$forum_db->prefix.'posts WHERE id>='.$end_at) or error(__FILE__, __LINE__);
		if ($forum_db->num_rows($result))
			$query_str = '?stage=conv_posts&req_old_charset='.$old_charset.'&req_per_page='.PER_PAGE.'&start_at='.$end_at;
		else
			$query_str = ($db_type == 'mysql' || $db_type == 'mysqli') ? '?stage=conv_tables' : '?stage=finish';
		break;


	// Convert table columns to utf8 (MySQL only)
	case 'conv_tables':
		// Do the cumbersome charset conversion of MySQL tables/columns
		if ($db_type == 'mysql' || $db_type == 'mysqli')
		{
			echo 'Converting table '.$forum_db->prefix.'bans …<br />'."\n"; flush();
			convert_table_utf8($forum_db->prefix.'bans');
			echo 'Converting table '.$forum_db->prefix.'categories …<br />'."\n"; flush();
			convert_table_utf8($forum_db->prefix.'categories');
			echo 'Converting table '.$forum_db->prefix.'censoring …<br />'."\n"; flush();
			convert_table_utf8($forum_db->prefix.'censoring');
			echo 'Converting table '.$forum_db->prefix.'config …<br />'."\n"; flush();
			convert_table_utf8($forum_db->prefix.'config');
			echo 'Converting table '.$forum_db->prefix.'extension_hooks …<br />'."\n"; flush();
			convert_table_utf8($forum_db->prefix.'extension_hooks');
			echo 'Converting table '.$forum_db->prefix.'extensions …<br />'."\n"; flush();
			convert_table_utf8($forum_db->prefix.'extensions');
			echo 'Converting table '.$forum_db->prefix.'forum_perms …<br />'."\n"; flush();
			convert_table_utf8($forum_db->prefix.'forum_perms');
			echo 'Converting table '.$forum_db->prefix.'forums …<br />'."\n"; flush();
			convert_table_utf8($forum_db->prefix.'forums');
			echo 'Converting table '.$forum_db->prefix.'groups …<br />'."\n"; flush();
			convert_table_utf8($forum_db->prefix.'groups');
			echo 'Converting table '.$forum_db->prefix.'online …<br />'."\n"; flush();
			convert_table_utf8($forum_db->prefix.'online');
			echo 'Converting table '.$forum_db->prefix.'posts …<br />'."\n"; flush();
			convert_table_utf8($forum_db->prefix.'posts');
			echo 'Converting table '.$forum_db->prefix.'ranks …<br />'."\n"; flush();
			convert_table_utf8($forum_db->prefix.'ranks');
			echo 'Converting table '.$forum_db->prefix.'reports …<br />'."\n"; flush();
			convert_table_utf8($forum_db->prefix.'reports');
			echo 'Converting table '.$forum_db->prefix.'search_cache …<br />'."\n"; flush();
			convert_table_utf8($forum_db->prefix.'search_cache');
			echo 'Converting table '.$forum_db->prefix.'search_matches …<br />'."\n"; flush();
			convert_table_utf8($forum_db->prefix.'search_matches');
			echo 'Converting table '.$forum_db->prefix.'search_words …<br />'."\n"; flush();
			convert_table_utf8($forum_db->prefix.'search_words');
			echo 'Converting table '.$forum_db->prefix.'subscriptions …<br />'."\n"; flush();
			convert_table_utf8($forum_db->prefix.'subscriptions');
			echo 'Converting table '.$forum_db->prefix.'topics …<br />'."\n"; flush();
			convert_table_utf8($forum_db->prefix.'topics');
			echo 'Converting table '.$forum_db->prefix.'users …<br />'."\n"; flush();
			convert_table_utf8($forum_db->prefix.'users');
		}

		$query_str = '?stage=finish';
		break;


	// Show results page
	case 'finish':
		// We update the version number
		$forum_db->query('UPDATE '.$forum_db->prefix.'config SET conf_value=\''.UPDATE_TO.'\' WHERE conf_name=\'o_cur_version\'') or error(__FILE__, __LINE__);

		// This feels like a good time to synchronize the forums
		$result = $forum_db->query('SELECT id FROM '.$forum_db->prefix.'forums') or error(__FILE__, __LINE__);
		while ($row = $forum_db->fetch_row($result))
			sync_forum($row[0]);

		// We'll empty the search cache table as well (using DELETE FROM since SQLite does not support TRUNCATE TABLE)
		$forum_db->query('DELETE FROM '.$forum_db->prefix.'search_cache') or error(__FILE__, __LINE__);

		// Empty the PHP cache
		forum_clear_cache();

		// Drop Base URL row from database config
		if (array_key_exists('o_base_url', $forum_config))
		{
			// Generate new config file
			$new_config = "<?php\n\n\$db_type = '$db_type';\n\$db_host = '$db_host';\n\$db_name = '".addslashes($db_name)."';\n\$db_username = '".addslashes($db_username)."';\n\$db_password = '".addslashes($db_password)."';\n\$db_prefix = '".addslashes($db_prefix)."';\n\$p_connect = ".($p_connect ? 'true' : 'false').";\n\n\$base_url = '$base_url';\n\n\$cookie_name = '$cookie_name';\n\$cookie_domain = '$cookie_domain';\n\$cookie_path = '$cookie_path';\n\$cookie_secure = $cookie_secure;\n\ndefine('FORUM', 1);";

			$forum_db->query('DELETE FROM '.$forum_db->prefix.'config WHERE conf_name=\'o_base_url\'') or error(__FILE__, __LINE__);
		}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>FluxBB Database Update</title>
<?php

// Include the stylesheets
require FORUM_ROOT.'style/'.$forum_user['style'].'/'.$forum_user['style'].'.php';

?>
<script type="text/javascript" src="<?php echo $base_url ?>/include/js/common.js"></script>
</head>
<body>

<div id="brd-update" class="brd-page">
<div class="brd">

<div id="brd-title">
	<div><strong>FluxBB Database Update</strong></div>
</div>

<div id="brd-desc">
	<div>Update database tables of current installation</div>
</div>

<div id="brd-main" class="main">

	<h1><span>FluxBB Database Update</span></h1>

	<div class="main-head">
		<h2><span>FluxBB Database Update completed!</span></h2>
	</div>

	<div class="main-content frm">
		<div class="frm-info">
			<p>Your forum database was updated successfully.</p>
<?php if (isset($new_config)): ?>					<p>In order to complete the process, you must now update your config.php script. <strong>Copy and paste the text in the text box below into the file called config.php in the root directory of your FluxBB installation</strong>. The file already exists, so you must edit/overwrite the contents of the old file.</p>
<?php endif; ?>					<p class="important"><strong>Important!</strong> Once the update is completed you should remove this script from the forum root directory and follow the rest of the instructions in the update documentation.</p>
</div>
<?php if (isset($new_config)): ?>		<form class="frm-form" action="foo">
			<fieldset class="frm-set set1">
				<legend class="frm-legend"><span>New config.php contents</span></legend>
				<div class="frm-field text textarea">
					<label for="fld1">
						<span class="frm-label">Copy contents:</span><br />
						<span class="frm-input"><textarea id="fld1" readonly="readonly" cols="80" rows="20"><?php echo forum_htmlencode($new_config) ?></textarea></span>
					</label>
				</div>
			</fieldset>
		</form>
<?php endif; ?>
	</div>

</div>

</div>
</div>
</body>
</html>
<?php

		break;
}

$forum_db->end_transaction();
$forum_db->close();

if ($query_str != '')
	exit('<script type="text/javascript">window.location="db_update.php'.$query_str.'"</script><br />JavaScript seems to be disabled. <a href="db_update.php'.$query_str.'">Click here to continue</a>.');
