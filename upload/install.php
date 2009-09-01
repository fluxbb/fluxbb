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


// The FluxBB version this script installs
$fluxbb_version = '1.2.22';


define('PUN_ROOT', './');
if (file_exists(PUN_ROOT.'config.php'))
	exit('The file \'config.php\' already exists which would mean that FluxBB is already installed. You should go <a href="index.php">here</a> instead.');


// Make sure we are running at least PHP 4.1.0
if (intval(str_replace('.', '', phpversion())) < 410)
	exit('You are running PHP version '.PHP_VERSION.'. FluxBB requires at least PHP 4.1.0 to run properly. You must upgrade your PHP installation before you can continue.');

// Disable error reporting for uninitialized variables
error_reporting(E_ALL);

// Turn off PHP time limit
@set_time_limit(0);


if (!isset($_POST['form_sent']))
{
	// Determine available database extensions
	$dual_mysql = false;
	$db_extensions = array();
	if (function_exists('mysqli_connect'))
		$db_extensions[] = array('mysqli', 'MySQL Improved');
	if (function_exists('mysql_connect'))
	{
		$db_extensions[] = array('mysql', 'MySQL Standard');

		if (count($db_extensions) > 1)
			$dual_mysql = true;
	}
	if (function_exists('sqlite_open'))
		$db_extensions[] = array('sqlite', 'SQLite');
	if (function_exists('pg_connect'))
		$db_extensions[] = array('pgsql', 'PostgreSQL');

	if (empty($db_extensions))
		exit('This PHP environment does not have support for any of the databases that FluxBB supports. PHP needs to have support for either MySQL, PostgreSQL or SQLite in order for FluxBB to be installed.');

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title>FluxBB Installation</title>
<link rel="stylesheet" type="text/css" href="style/Oxygen.css" />
<script type="text/javascript">
<!--
function process_form(the_form)
{
	var element_names = new Object()
	element_names["req_db_type"] = "Database type"
	element_names["req_db_host"] = "Database server hostname"
	element_names["req_db_name"] = "Database name"
	element_names["db_prefix"] = "Table prefix"
	element_names["req_username"] = "Administrator username"
	element_names["req_password1"] = "Administrator password 1"
	element_names["req_password2"] = "Administrator password 2"
	element_names["req_email"] = "Administrator's e-mail"
	element_names["req_base_url"] = "Base URL"

	if (document.all || document.getElementById)
	{
		for (i = 0; i < the_form.length; ++i)
		{
			var elem = the_form.elements[i]
			if (elem.name && elem.name.substring(0, 4) == "req_")
			{
				if (elem.type && (elem.type=="text" || elem.type=="textarea" || elem.type=="password" || elem.type=="file") && elem.value=='')
				{
					alert("\"" + element_names[elem.name] + "\" is a required field in this form.")
					elem.focus()
					return false
				}
			}
		}
	}

	return true
}
// -->
</script>
</head>
<body onload="document.getElementById('install').req_db_type.focus()">

<div id="puninstall" style="margin: auto 10% auto 10%">
<div class="pun">

<div class="block">
	<h2><span>FluxBB Installation</span></h2>
	<div class="box">
		<div class="inbox">
			<p>Welcome to FluxBB installation! You are about to install FluxBB. In order to install FluxBB you must complete the form set out below. If you encounter any difficulties with the installation, please refer to the documentation.</p>
		</div>
	</div>
</div>

<div class="blockform">
	<h2><span>Install FluxBB 1.2</span></h2>
	<div class="box">
		<form id="install" method="post" action="install.php" onsubmit="this.start.disabled=true;if(process_form(this)){return true;}else{this.start.disabled=false;return false;}">
		<div><input type="hidden" name="form_sent" value="1" /></div>
			<div class="inform">
				<div class="forminfo">
					<h3>Database setup</h3>
					<p>Please enter the requested information in order to setup your database for FluxBB. You must know all the information asked for before proceeding with the installation.</p>
				</div>
				<fieldset>
				<legend>Select your database type</legend>
					<div class="infldset">
						<p>FluxBB currently supports MySQL, PostgreSQL and SQLite. If your database of choice is missing from the drop-down menu below, it means this PHP environment does not have support for that particular database. More information regarding support for particular versions of each database can be found in the FAQ.</p>
<?php if ($dual_mysql): ?>						<p>FluxBB has detected that your PHP environment supports two different ways of communicating with MySQL. The two options are called standard and improved. If you are uncertain which one to use, start by trying improved and if that fails, try standard.</p>
<?php endif; ?>						<label><strong>Database type</strong>
						<br /><select name="req_db_type">
<?php

	foreach ($db_extensions as $db_type)
		echo "\t\t\t\t\t\t\t".'<option value="'.$db_type[0].'">'.$db_type[1].'</option>'."\n";

?>
						</select>
						<br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend>Enter your database server hostname</legend>
					<div class="infldset">
						<p>The address of the database server (example: localhost, db.myhost.com or 192.168.0.15). You can specify a custom port number if your database doesn't run on the default port (example: localhost:3580). For SQLite support, just enter anything or leave it at 'localhost'.</p>
						<label><strong>Database server hostname</strong><br /><input type="text" name="req_db_host" value="localhost" size="50" maxlength="100" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend>Enter then name of your database</legend>
					<div class="infldset">
						<p>The name of the database that FluxBB will be installed into. The database must exist. For SQLite, this is the relative path to the database file. If the SQLite database file does not exist, FluxBB will attempt to create it.</p>
						<label for="req_db_name"><strong>Database name</strong><br /><input id="req_db_name" type="text" name="req_db_name" size="30" maxlength="50" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend>Enter your database username and password</legend>
					<div class="infldset">
						<p>Enter the username and password with which you connect to the database. Ignore for SQLite.</p>
						<label class="conl">Database username<br /><input type="text" name="db_username" size="30" maxlength="50" /><br /></label>
						<label class="conl">Database password<br /><input type="text" name="db_password" size="30" maxlength="50" /><br /></label>
						<div class="clearer"></div>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend>Enter database table prefix</legend>
					<div class="infldset">
						<p>If you like you can specify a table prefix. This way you can run multiple copies of FluxBB in the same database (example: foo_).</p>
						<label>Table prefix<br /><input id="db_prefix" type="text" name="db_prefix" size="20" maxlength="30" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<div class="forminfo">
					<h3>Administration setup</h3>
					<p>Please enter the requested information in order to setup an administrator for your FluxBB installation</p>
				</div>
				<fieldset>
					<legend>Enter Administrators username</legend>
					<div class="infldset">
						<p>The username of the forum administrator. You can later create more administrators and moderators. Usernames can be between 2 and 25 characters long.</p>
						<label><strong>Administrator username</strong><br /><input type="text" name="req_username" size="25" maxlength="25" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend>Enter and confirm Administrator password</legend>
					<div class="infldset">
					<p>Passwords can be between 4 and 16 characters long. Passwords are case sensitive.</p>
						<label class="conl"><strong>Password</strong><br /><input id="req_password1" type="text" name="req_password1" size="16" maxlength="16" /><br /></label>
						<label class="conl"><strong>Confirm password</strong><br /><input type="text" name="req_password2" size="16" maxlength="16" /><br /></label>
						<div class="clearer"></div>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend>Enter Administrator's e-mail</legend>
					<div class="infldset">
						<p>The e-mail address of the forum administrator.</p>
						<label for="req_email"><strong>Administrator's e-mail</strong><br /><input id="req_email" type="text" name="req_email" size="50" maxlength="50" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend>Enter the Base URL of your FluxBB installation</legend>
					<div class="infldset">
						<p>The URL (without trailing slash) of your FluxBB forum (example: http://forum.myhost.com or http://myhost.com/~myuser). This <strong>must</strong> be correct or administrators and moderators will not be able to submit any forms. Please note that the preset value below is just an educated guess by FluxBB.</p>
						<label><strong>Base URL</strong><br /><input type="text" name="req_base_url" value="http://<?php echo $_SERVER['SERVER_NAME'].str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])) ?>" size="60" maxlength="100" /><br /></label>
					</div>
				</fieldset>
			</div>
			<p><input type="submit" name="start" value="Start install" /></p>
		</form>
	</div>
</div>

</div>
</div>

</body>
</html>
<?php

}
else
{
	//
	// Strip slashes only if magic_quotes_gpc is on.
	//
	function unescape($str)
	{
		return (get_magic_quotes_gpc() == 1) ? stripslashes($str) : $str;
	}


	//
	// Compute a hash of $str.
	// Uses sha1() if available. If not, SHA1 through mhash() if available. If not, fall back on md5().
	//
	function pun_hash($str)
	{
		if (function_exists('sha1'))	// Only in PHP 4.3.0+
			return sha1($str);
		else if (function_exists('mhash'))	// Only if Mhash library is loaded
			return bin2hex(mhash(MHASH_SHA1, $str));
		else
			return md5($str);
	}


	//
	// A temporary replacement for the full error handler found in functions.php.
	// It's here because a function called error() must be callable in the database abstraction layer.
	//
	function error($message, $file = false, $line = false, $db_error = false)
	{
		if ($file !== false && $line !== false)
			echo '<strong style="color: A00000">An error occured on line '.$line.' in file '.$file.'.</strong><br /><br />';
		else
			echo '<strong style="color: A00000">An error occured.</strong><br /><br />';

		echo '<strong>FluxBB reported:</strong> '.htmlspecialchars($message).'<br /><br />';

		if ($db_error !== false)
			echo '<strong>Database reported:</strong> '.htmlspecialchars($db_error['error_msg']).(($db_error['error_no']) ? ' (Errno: '.$db_error['error_no'].')' : '');

		exit;
	}
	
	
	// 
	// Calls htmlspecialchars with a few options already set 
	// 
	function pun_htmlspecialchars($str) 
	{ 
		return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); 
	}


	$db_type = $_POST['req_db_type'];
	$db_host = trim($_POST['req_db_host']);
	$db_name = trim($_POST['req_db_name']);
	$db_username = unescape(trim($_POST['db_username']));
	$db_password = unescape(trim($_POST['db_password']));
	$db_prefix = trim($_POST['db_prefix']);
	$username = unescape(trim($_POST['req_username']));
	$email = strtolower(trim($_POST['req_email']));
	$password1 = unescape(trim($_POST['req_password1']));
	$password2 = unescape(trim($_POST['req_password2']));


	// Make sure base_url doesn't end with a slash
	if (substr($_POST['req_base_url'], -1) == '/')
		$base_url = substr($_POST['req_base_url'], 0, -1);
	else
		$base_url = $_POST['req_base_url'];


	// Validate username and passwords
	if (strlen($username) < 2)
		error('Usernames must be at least 2 characters long. Please go back and correct.');
	if (strlen($password1) < 4)
		error('Passwords must be at least 4 characters long. Please go back and correct.');
	if ($password1 != $password2)
		error('Passwords do not match. Please go back and correct.');
	if (!strcasecmp($username, 'Guest'))
		error('The username guest is reserved. Please go back and correct.');
	if (preg_match('/[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}/', $username))
		error('Usernames may not be in the form of an IP address. Please go back and correct.');
	if (preg_match('#\[b\]|\[/b\]|\[u\]|\[/u\]|\[i\]|\[/i\]|\[color|\[/color\]|\[quote\]|\[/quote\]|\[code\]|\[/code\]|\[img\]|\[/img\]|\[url|\[/url\]|\[email|\[/email\]#i', $username))
		error('Usernames may not contain any of the text formatting tags (BBCode) that the forum uses. Please go back and correct.');

	if (strlen($email) > 50 || !preg_match('/^(([^<>()[\]\\.,;:\s@"\']+(\.[^<>()[\]\\.,;:\s@"\']+)*)|("[^"\']+"))@((\[\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\])|(([a-zA-Z\d\-]+\.)+[a-zA-Z]{2,}))$/', $email))
		error('The administrator e-mail address you entered is invalid. Please go back and correct.');


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
			error('\''.pun_htmlspecialchars($db_type).'\' is not a valid database type.');
	}

	// Create the database object (and connect/select db)
	$db = new DBLayer($db_host, $db_username, $db_password, $db_name, $db_prefix, false);


	// Do some DB type specific checks
	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			break;

		case 'pgsql':
			// Make sure we are running at least PHP 4.3.0 (needed only for PostgreSQL)
			if (version_compare(PHP_VERSION, '4.3.0', '<'))
				error('You are running PHP version '.PHP_VERSION.'. FluxBB requires at least PHP 4.3.0 to run properly when using PostgreSQL. You must upgrade your PHP installation or use a different database before you can continue.');
			break;

		case 'sqlite':
			if (strtolower($db_prefix) == 'sqlite_')
				error('The table prefix \'sqlite_\' is reserved for use by the SQLite engine. Please choose a different prefix.');
			break;
	}


	// Make sure FluxBB isn't already installed
	$result = $db->query('SELECT 1 FROM '.$db_prefix.'users WHERE id=1');
	if ($db->num_rows($result))
		error('A table called "'.$db_prefix.'users" is already present in the database "'.$db_name.'". This could mean that FluxBB is already installed or that another piece of software is installed and is occupying one or more of the table names FluxBB requires. If you want to install multiple copies of FluxBB in the same database, you must choose a different table prefix.');


	// Create all tables
	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."bans (
					id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					username VARCHAR(200),
					ip VARCHAR(255),
					email VARCHAR(50),
					message VARCHAR(255),
					expire INT(10) UNSIGNED,
					PRIMARY KEY (id)
					) TYPE=MyISAM;";
			break;

		case 'pgsql':
			$db->start_transaction();

			$sql = 'CREATE TABLE '.$db_prefix."bans (
					id SERIAL,
					username VARCHAR(200),
					ip VARCHAR(255),
					email VARCHAR(50),
					message VARCHAR(255),
					expire INT,
					PRIMARY KEY (id)
					)";
			break;

		case 'sqlite':
			$db->start_transaction();

			$sql = 'CREATE TABLE '.$db_prefix."bans (
					id INTEGER NOT NULL,
					username VARCHAR(200),
					ip  VARCHAR(255),
					email VARCHAR(50),
					message VARCHAR(255),
					expire INTEGER,
					PRIMARY KEY (id)
					)";
			break;

	}

	$db->query($sql) or error('Unable to create table '.$db_prefix.'bans. Please check your settings and try again.',  __FILE__, __LINE__, $db->error());


	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."categories (
					id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					cat_name VARCHAR(80) NOT NULL DEFAULT 'New Category',
					disp_position INT(10) NOT NULL DEFAULT 0,
					PRIMARY KEY (id)
					) TYPE=MyISAM;";
			break;

		case 'pgsql':
			$sql = 'CREATE TABLE '.$db_prefix."categories (
					id SERIAL,
					cat_name VARCHAR(80) NOT NULL DEFAULT 'New Category',
					disp_position INT NOT NULL DEFAULT 0,
					PRIMARY KEY (id)
					)";
			break;

		case 'sqlite':
			$sql = 'CREATE TABLE '.$db_prefix."categories (
					id INTEGER NOT NULL,
					cat_name VARCHAR(80) NOT NULL DEFAULT 'New Category',
					disp_position INTEGER NOT NULL DEFAULT 0,
					PRIMARY KEY (id)
					)";
			break;
	}

	$db->query($sql) or error('Unable to create table '.$db_prefix.'categories. Please check your settings and try again.',  __FILE__, __LINE__, $db->error());



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."censoring (
					id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					search_for VARCHAR(60) NOT NULL DEFAULT '',
					replace_with VARCHAR(60) NOT NULL DEFAULT '',
					PRIMARY KEY (id)
					) TYPE=MyISAM;";
			break;

		case 'pgsql':
			$sql = 'CREATE TABLE '.$db_prefix."censoring (
					id SERIAL,
					search_for VARCHAR(60) NOT NULL DEFAULT '',
					replace_with VARCHAR(60) NOT NULL DEFAULT '',
					PRIMARY KEY (id)
					)";
			break;

		case 'sqlite':
			$sql = 'CREATE TABLE '.$db_prefix."censoring (
					id INTEGER NOT NULL,
					search_for VARCHAR(60) NOT NULL DEFAULT '',
					replace_with VARCHAR(60) NOT NULL DEFAULT '',
					PRIMARY KEY (id)
					)";
			break;
	}

	$db->query($sql) or error('Unable to create table '.$db_prefix.'censoring. Please check your settings and try again.',  __FILE__, __LINE__, $db->error());



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."config (
					conf_name VARCHAR(255) NOT NULL DEFAULT '',
					conf_value TEXT,
					PRIMARY KEY (conf_name)
					) TYPE=MyISAM;";
			break;

		case 'pgsql':
			$sql = 'CREATE TABLE '.$db_prefix."config (
					conf_name VARCHAR(255) NOT NULL DEFAULT '',
					conf_value TEXT,
					PRIMARY KEY (conf_name)
					)";
			break;

		case 'sqlite':
			$sql = 'CREATE TABLE '.$db_prefix."config (
					conf_name VARCHAR(255) NOT NULL DEFAULT '',
					conf_value TEXT,
					PRIMARY KEY (conf_name)
					)";
			break;
	}

	$db->query($sql) or error('Unable to create table '.$db_prefix.'config. Please check your settings and try again.',  __FILE__, __LINE__, $db->error());



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."forum_perms (
					group_id INT(10) NOT NULL DEFAULT 0,
					forum_id INT(10) NOT NULL DEFAULT 0,
					read_forum TINYINT(1) NOT NULL DEFAULT 1,
					post_replies TINYINT(1) NOT NULL DEFAULT 1,
					post_topics TINYINT(1) NOT NULL DEFAULT 1,
					PRIMARY KEY (group_id, forum_id)
					) TYPE=MyISAM;";
			break;

		case 'pgsql':
			$sql = 'CREATE TABLE '.$db_prefix."forum_perms (
					group_id INT NOT NULL DEFAULT 0,
					forum_id INT NOT NULL DEFAULT 0,
					read_forum SMALLINT NOT NULL DEFAULT 1,
					post_replies SMALLINT NOT NULL DEFAULT 1,
					post_topics SMALLINT NOT NULL DEFAULT 1,
					PRIMARY KEY (group_id, forum_id)
					)";
			break;

		case 'sqlite':
			$sql = 'CREATE TABLE '.$db_prefix."forum_perms (
					group_id INTEGER NOT NULL DEFAULT 0,
					forum_id INTEGER NOT NULL DEFAULT 0,
					read_forum INTEGER NOT NULL DEFAULT 1,
					post_replies INTEGER NOT NULL DEFAULT 1,
					post_topics INTEGER NOT NULL DEFAULT 1,
					PRIMARY KEY (group_id, forum_id)
					)";
			break;
	}

	$db->query($sql) or error('Unable to create table '.$db_prefix.'forum_perms. Please check your settings and try again.',  __FILE__, __LINE__, $db->error());



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."forums (
					id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					forum_name VARCHAR(80) NOT NULL DEFAULT 'New forum',
					forum_desc TEXT,
					redirect_url VARCHAR(100),
					moderators TEXT,
					num_topics MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT 0,
					num_posts MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT 0,
					last_post INT(10) UNSIGNED,
					last_post_id INT(10) UNSIGNED,
					last_poster VARCHAR(200),
					sort_by TINYINT(1) NOT NULL DEFAULT 0,
					disp_position INT(10) NOT NULL DEFAULT 0,
					cat_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
					PRIMARY KEY (id)
					) TYPE=MyISAM;";
			break;

		case 'pgsql':
			$sql = 'CREATE TABLE '.$db_prefix."forums (
					id SERIAL,
					forum_name VARCHAR(80) NOT NULL DEFAULT 'New forum',
					forum_desc TEXT,
					redirect_url VARCHAR(100),
					moderators TEXT,
					num_topics INT NOT NULL DEFAULT 0,
					num_posts INT NOT NULL DEFAULT 0,
					last_post INT,
					last_post_id INT,
					last_poster VARCHAR(200),
					sort_by SMALLINT NOT NULL DEFAULT 0,
					disp_position INT NOT NULL DEFAULT 0,
					cat_id INT NOT NULL DEFAULT 0,
					PRIMARY KEY (id)
					)";
			break;

		case 'sqlite':
			$sql = 'CREATE TABLE '.$db_prefix."forums (
					id INTEGER NOT NULL,
					forum_name VARCHAR(80) NOT NULL DEFAULT 'New forum',
					forum_desc TEXT,
					redirect_url VARCHAR(100),
					moderators TEXT,
					num_topics INTEGER NOT NULL DEFAULT 0,
					num_posts INTEGER NOT NULL DEFAULT 0,
					last_post INTEGER,
					last_post_id INTEGER,
					last_poster VARCHAR(200),
					sort_by INTEGER NOT NULL DEFAULT 0,
					disp_position INTEGER NOT NULL DEFAULT 0,
					cat_id INTEGER NOT NULL DEFAULT 0,
					PRIMARY KEY (id)
					)";
			break;
	}

	$db->query($sql) or error('Unable to create table '.$db_prefix.'forums. Please check your settings and try again.',  __FILE__, __LINE__, $db->error());



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."groups (
					g_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					g_title VARCHAR(50) NOT NULL DEFAULT '',
					g_user_title VARCHAR(50),
					g_read_board TINYINT(1) NOT NULL DEFAULT 1,
					g_post_replies TINYINT(1) NOT NULL DEFAULT 1,
					g_post_topics TINYINT(1) NOT NULL DEFAULT 1,
					g_post_polls TINYINT(1) NOT NULL DEFAULT 1,
					g_edit_posts TINYINT(1) NOT NULL DEFAULT 1,
					g_delete_posts TINYINT(1) NOT NULL DEFAULT 1,
					g_delete_topics TINYINT(1) NOT NULL DEFAULT 1,
					g_set_title TINYINT(1) NOT NULL DEFAULT 1,
					g_search TINYINT(1) NOT NULL DEFAULT 1,
					g_search_users TINYINT(1) NOT NULL DEFAULT 1,
					g_edit_subjects_interval SMALLINT(6) NOT NULL DEFAULT 300,
					g_post_flood SMALLINT(6) NOT NULL DEFAULT 30,
					g_search_flood SMALLINT(6) NOT NULL DEFAULT 30,
					PRIMARY KEY (g_id)
					) TYPE=MyISAM;";
			break;

		case 'pgsql':
			$sql = 'CREATE TABLE '.$db_prefix."groups (
					g_id SERIAL,
					g_title VARCHAR(50) NOT NULL DEFAULT '',
					g_user_title VARCHAR(50),
					g_read_board SMALLINT NOT NULL DEFAULT 1,
					g_post_replies SMALLINT NOT NULL DEFAULT 1,
					g_post_topics SMALLINT NOT NULL DEFAULT 1,
					g_post_polls SMALLINT NOT NULL DEFAULT 1,
					g_edit_posts SMALLINT NOT NULL DEFAULT 1,
					g_delete_posts SMALLINT NOT NULL DEFAULT 1,
					g_delete_topics SMALLINT NOT NULL DEFAULT 1,
					g_set_title SMALLINT NOT NULL DEFAULT 1,
					g_search SMALLINT NOT NULL DEFAULT 1,
					g_search_users SMALLINT NOT NULL DEFAULT 1,
					g_edit_subjects_interval SMALLINT NOT NULL DEFAULT 300,
					g_post_flood SMALLINT NOT NULL DEFAULT 30,
					g_search_flood SMALLINT NOT NULL DEFAULT 30,
					PRIMARY KEY (g_id)
					)";
			break;

		case 'sqlite':
			$sql = 'CREATE TABLE '.$db_prefix."groups (
					g_id INTEGER NOT NULL,
					g_title VARCHAR(50) NOT NULL DEFAULT '',
					g_user_title VARCHAR(50),
					g_read_board INTEGER NOT NULL DEFAULT 1,
					g_post_replies INTEGER NOT NULL DEFAULT 1,
					g_post_topics INTEGER NOT NULL DEFAULT 1,
					g_post_polls INTEGER NOT NULL DEFAULT 1,
					g_edit_posts INTEGER NOT NULL DEFAULT 1,
					g_delete_posts INTEGER NOT NULL DEFAULT 1,
					g_delete_topics INTEGER NOT NULL DEFAULT 1,
					g_set_title INTEGER NOT NULL DEFAULT 1,
					g_search INTEGER NOT NULL DEFAULT 1,
					g_search_users INTEGER NOT NULL DEFAULT 1,
					g_edit_subjects_interval INTEGER NOT NULL DEFAULT 300,
					g_post_flood INTEGER NOT NULL DEFAULT 30,
					g_search_flood INTEGER NOT NULL DEFAULT 30,
					PRIMARY KEY (g_id)
					)";
			break;
	}

	$db->query($sql) or error('Unable to create table '.$db_prefix.'groups. Please check your settings and try again.',  __FILE__, __LINE__, $db->error());



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."online (
					user_id INT(10) UNSIGNED NOT NULL DEFAULT 1,
					ident VARCHAR(200) NOT NULL DEFAULT '',
					logged INT(10) UNSIGNED NOT NULL DEFAULT 0,
					idle TINYINT(1) NOT NULL DEFAULT 0
					) TYPE=HEAP;";
			break;

		case 'pgsql':
			$sql = 'CREATE TABLE '.$db_prefix."online (
					user_id INT NOT NULL DEFAULT 1,
					ident VARCHAR(200) NOT NULL DEFAULT '',
					logged INT NOT NULL DEFAULT 0,
					idle SMALLINT NOT NULL DEFAULT 0
					)";
			break;

		case 'sqlite':
			$sql = 'CREATE TABLE '.$db_prefix."online (
					user_id INTEGER NOT NULL DEFAULT 1,
					ident VARCHAR(200) NOT NULL DEFAULT '',
					logged INTEGER NOT NULL DEFAULT 0,
					idle INTEGER NOT NULL DEFAULT 0
					)";
			break;
	}

	$db->query($sql) or error('Unable to create table '.$db_prefix.'online. Please check your settings and try again.',  __FILE__, __LINE__, $db->error());



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."posts (
					id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					poster VARCHAR(200) NOT NULL DEFAULT '',
					poster_id INT(10) UNSIGNED NOT NULL DEFAULT 1,
					poster_ip VARCHAR(15),
					poster_email VARCHAR(50),
					message TEXT,
					hide_smilies TINYINT(1) NOT NULL DEFAULT 0,
					posted INT(10) UNSIGNED NOT NULL DEFAULT 0,
					edited INT(10) UNSIGNED,
					edited_by VARCHAR(200),
					topic_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
					PRIMARY KEY (id)
					) TYPE=MyISAM;";
			break;

		case 'pgsql':
			$sql = 'CREATE TABLE '.$db_prefix."posts (
					id SERIAL,
					poster VARCHAR(200) NOT NULL DEFAULT '',
					poster_id INT NOT NULL DEFAULT 1,
					poster_ip VARCHAR(15),
					poster_email VARCHAR(50),
					message TEXT,
					hide_smilies SMALLINT NOT NULL DEFAULT 0,
					posted INT NOT NULL DEFAULT 0,
					edited INT,
					edited_by VARCHAR(200),
					topic_id INT NOT NULL DEFAULT 0,
					PRIMARY KEY (id)
					)";
			break;

		case 'sqlite':
			$sql = 'CREATE TABLE '.$db_prefix."posts (
					id INTEGER NOT NULL,
					poster VARCHAR(200) NOT NULL DEFAULT '',
					poster_id INTEGER NOT NULL DEFAULT 1,
					poster_ip VARCHAR(15),
					poster_email VARCHAR(50),
					message TEXT,
					hide_smilies INTEGER NOT NULL DEFAULT 0,
					posted INTEGER NOT NULL DEFAULT 0,
					edited INTEGER,
					edited_by VARCHAR(200),
					topic_id INTEGER NOT NULL DEFAULT 0,
					PRIMARY KEY (id)
					)";
			break;
	}

	$db->query($sql) or error('Unable to create table '.$db_prefix.'posts. Please check your settings and try again.',  __FILE__, __LINE__, $db->error());



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."ranks (
					id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					rank VARCHAR(50) NOT NULL DEFAULT '',
					min_posts MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT 0,
					PRIMARY KEY (id)
					) TYPE=MyISAM;";
			break;

		case 'pgsql':
			$sql = 'CREATE TABLE '.$db_prefix."ranks (
					id SERIAL,
					rank VARCHAR(50) NOT NULL DEFAULT '',
					min_posts INT NOT NULL DEFAULT 0,
					PRIMARY KEY (id)
					)";
			break;

		case 'sqlite':
			$sql = 'CREATE TABLE '.$db_prefix."ranks (
					id INTEGER NOT NULL,
					rank VARCHAR(50) NOT NULL DEFAULT '',
					min_posts INTEGER NOT NULL DEFAULT 0,
					PRIMARY KEY (id)
					)";
			break;
	}

	$db->query($sql) or error('Unable to create table '.$db_prefix.'titles. Please check your settings and try again.',  __FILE__, __LINE__, $db->error());



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."reports (
					id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					post_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
					topic_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
					forum_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
					reported_by INT(10) UNSIGNED NOT NULL DEFAULT 0,
					created INT(10) UNSIGNED NOT NULL DEFAULT 0,
					message TEXT,
					zapped INT(10) UNSIGNED,
					zapped_by INT(10) UNSIGNED,
					PRIMARY KEY (id)
					) TYPE=MyISAM;";
			break;

		case 'pgsql':
			$sql = 'CREATE TABLE '.$db_prefix."reports (
					id SERIAL,
					post_id INT NOT NULL DEFAULT 0,
					topic_id INT NOT NULL DEFAULT 0,
					forum_id INT NOT NULL DEFAULT 0,
					reported_by INT NOT NULL DEFAULT 0,
					created INT NOT NULL DEFAULT 0,
					message TEXT,
					zapped INT,
					zapped_by INT,
					PRIMARY KEY (id)
					)";
			break;

		case 'sqlite':
			$sql = 'CREATE TABLE '.$db_prefix."reports (
					id INTEGER NOT NULL,
					post_id INTEGER NOT NULL DEFAULT 0,
					topic_id INTEGER NOT NULL DEFAULT 0,
					forum_id INTEGER NOT NULL DEFAULT 0,
					reported_by INTEGER NOT NULL DEFAULT 0,
					created INTEGER NOT NULL DEFAULT 0,
					message TEXT,
					zapped INTEGER,
					zapped_by INTEGER,
					PRIMARY KEY (id)
					)";
			break;
	}

	$db->query($sql) or error('Unable to create table '.$db_prefix.'reports. Please check your settings and try again.',  __FILE__, __LINE__, $db->error());



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."search_cache (
					id INT(10) UNSIGNED NOT NULL DEFAULT 0,
					ident VARCHAR(200) NOT NULL DEFAULT '',
					search_data TEXT,
					PRIMARY KEY (id)
					) TYPE=MyISAM;";
			break;

		case 'pgsql':
			$sql = 'CREATE TABLE '.$db_prefix."search_cache (
					id INT NOT NULL DEFAULT 0,
					ident VARCHAR(200) NOT NULL DEFAULT '',
					search_data TEXT,
					PRIMARY KEY (id)
					)";
			break;

		case 'sqlite':
			$sql = 'CREATE TABLE '.$db_prefix."search_cache (
					id INTEGER NOT NULL DEFAULT 0,
					ident VARCHAR(200) NOT NULL DEFAULT '',
					search_data TEXT,
					PRIMARY KEY (id)
					)";
			break;
	}

	$db->query($sql) or error('Unable to create table '.$db_prefix.'search_cache. Please check your settings and try again.',  __FILE__, __LINE__, $db->error());



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."search_matches (
					post_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
					word_id MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT 0,
					subject_match TINYINT(1) NOT NULL DEFAULT 0
					) TYPE=MyISAM;";
			break;

		case 'pgsql':
			$sql = 'CREATE TABLE '.$db_prefix."search_matches (
					post_id INT NOT NULL DEFAULT 0,
					word_id INT NOT NULL DEFAULT 0,
					subject_match SMALLINT NOT NULL DEFAULT 0
					)";
			break;

		case 'sqlite':
			$sql = 'CREATE TABLE '.$db_prefix."search_matches (
					post_id INTEGER NOT NULL DEFAULT 0,
					word_id INTEGER NOT NULL DEFAULT 0,
					subject_match INTEGER NOT NULL DEFAULT 0
					)";
			break;
	}

	$db->query($sql) or error('Unable to create table '.$db_prefix.'search_matches. Please check your settings and try again.',  __FILE__, __LINE__, $db->error());



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."search_words (
					id MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT,
					word VARCHAR(20) BINARY NOT NULL DEFAULT '',
					PRIMARY KEY (word),
					KEY ".$db_prefix."search_words_id_idx (id)
					) TYPE=MyISAM;";
			break;

		case 'pgsql':
			$sql = 'CREATE TABLE '.$db_prefix."search_words (
					id SERIAL,
					word VARCHAR(20) NOT NULL DEFAULT '',
					PRIMARY KEY (word)
					)";
			break;

		case 'sqlite':
			$sql = 'CREATE TABLE '.$db_prefix."search_words (
					id INTEGER NOT NULL,
					word VARCHAR(20) NOT NULL DEFAULT '',
					PRIMARY KEY (id),
					UNIQUE (word)
					)";
			break;
	}

	$db->query($sql) or error('Unable to create table '.$db_prefix.'search_words. Please check your settings and try again.',  __FILE__, __LINE__, $db->error());



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."subscriptions (
					user_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
					topic_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
					PRIMARY KEY (user_id, topic_id)
					) TYPE=MyISAM;";
			break;

		case 'pgsql':
			$sql = 'CREATE TABLE '.$db_prefix."subscriptions (
					user_id INT NOT NULL DEFAULT 0,
					topic_id INT NOT NULL DEFAULT 0,
					PRIMARY KEY (user_id, topic_id)
					)";
			break;

		case 'sqlite':
			$sql = 'CREATE TABLE '.$db_prefix."subscriptions (
					user_id INTEGER NOT NULL DEFAULT 0,
					topic_id INTEGER NOT NULL DEFAULT 0,
					PRIMARY KEY (user_id, topic_id)
					)";
			break;
	}

	$db->query($sql) or error('Unable to create table '.$db_prefix.'subscriptions. Please check your settings and try again.',  __FILE__, __LINE__, $db->error());



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."topics (
					id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					poster VARCHAR(200) NOT NULL DEFAULT '',
					subject VARCHAR(255) NOT NULL DEFAULT '',
					posted INT(10) UNSIGNED NOT NULL DEFAULT 0,
					last_post INT(10) UNSIGNED NOT NULL DEFAULT 0,
					last_post_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
					last_poster VARCHAR(200),
					num_views MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT 0,
					num_replies MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT 0,
					closed TINYINT(1) NOT NULL DEFAULT 0,
					sticky TINYINT(1) NOT NULL DEFAULT 0,
					moved_to INT(10) UNSIGNED,
					forum_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
					PRIMARY KEY (id)
					) TYPE=MyISAM;";
			break;

		case 'pgsql':
			$sql = 'CREATE TABLE '.$db_prefix."topics (
					id SERIAL,
					poster VARCHAR(200) NOT NULL DEFAULT '',
					subject VARCHAR(255) NOT NULL DEFAULT '',
					posted INT NOT NULL DEFAULT 0,
					last_post INT NOT NULL DEFAULT 0,
					last_post_id INT NOT NULL DEFAULT 0,
					last_poster VARCHAR(200),
					num_views INT NOT NULL DEFAULT 0,
					num_replies INT NOT NULL DEFAULT 0,
					closed SMALLINT NOT NULL DEFAULT 0,
					sticky SMALLINT NOT NULL DEFAULT 0,
					moved_to INT,
					forum_id INT NOT NULL DEFAULT 0,
					PRIMARY KEY (id)
					)";
			break;

		case 'sqlite':
			$sql = 'CREATE TABLE '.$db_prefix."topics (
					id INTEGER NOT NULL,
					poster VARCHAR(200) NOT NULL DEFAULT '',
					subject VARCHAR(255) NOT NULL DEFAULT '',
					posted INTEGER NOT NULL DEFAULT 0,
					last_post INTEGER NOT NULL DEFAULT 0,
					last_post_id INTEGER NOT NULL DEFAULT 0,
					last_poster VARCHAR(200),
					num_views INTEGER NOT NULL DEFAULT 0,
					num_replies INTEGER NOT NULL DEFAULT 0,
					closed INTEGER NOT NULL DEFAULT 0,
					sticky INTEGER NOT NULL DEFAULT 0,
					moved_to INTEGER,
					forum_id INTEGER NOT NULL DEFAULT 0,
					PRIMARY KEY (id)
					)";
			break;
	}

	$db->query($sql) or error('Unable to create table '.$db_prefix.'topics. Please check your settings and try again.',  __FILE__, __LINE__, $db->error());



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."users (
					id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					group_id INT(10) UNSIGNED NOT NULL DEFAULT 4,
					username VARCHAR(200) NOT NULL DEFAULT '',
					password VARCHAR(40) NOT NULL DEFAULT '',
					email VARCHAR(50) NOT NULL DEFAULT '',
					title VARCHAR(50),
					realname VARCHAR(40),
					url VARCHAR(100),
					jabber VARCHAR(75),
					icq VARCHAR(12),
					msn VARCHAR(50),
					aim VARCHAR(30),
					yahoo VARCHAR(30),
					location VARCHAR(30),
					use_avatar TINYINT(1) NOT NULL DEFAULT 0,
					signature TEXT,
					disp_topics TINYINT(3) UNSIGNED,
					disp_posts TINYINT(3) UNSIGNED,
					email_setting TINYINT(1) NOT NULL DEFAULT 1,
					save_pass TINYINT(1) NOT NULL DEFAULT 1,
					notify_with_post TINYINT(1) NOT NULL DEFAULT 0,
					show_smilies TINYINT(1) NOT NULL DEFAULT 1,
					show_img TINYINT(1) NOT NULL DEFAULT 1,
					show_img_sig TINYINT(1) NOT NULL DEFAULT 1,
					show_avatars TINYINT(1) NOT NULL DEFAULT 1,
					show_sig TINYINT(1) NOT NULL DEFAULT 1,
					timezone FLOAT NOT NULL DEFAULT 0,
					language VARCHAR(25) NOT NULL DEFAULT 'English',
					style VARCHAR(25) NOT NULL DEFAULT 'Oxygen',
					num_posts INT(10) UNSIGNED NOT NULL DEFAULT 0,
					last_post INT(10) UNSIGNED,
					registered INT(10) UNSIGNED NOT NULL DEFAULT 0,
					registration_ip VARCHAR(15) NOT NULL DEFAULT '0.0.0.0',
					last_visit INT(10) UNSIGNED NOT NULL DEFAULT 0,
					admin_note VARCHAR(30),
					activate_string VARCHAR(50),
					activate_key VARCHAR(8),
					PRIMARY KEY (id)
					) TYPE=MyISAM;";
			break;

		case 'pgsql':
			$sql = 'CREATE TABLE '.$db_prefix."users (
					id SERIAL,
					group_id INT NOT NULL DEFAULT 4,
					username VARCHAR(200) NOT NULL DEFAULT '',
					password VARCHAR(40) NOT NULL DEFAULT '',
					email VARCHAR(50) NOT NULL DEFAULT '',
					title VARCHAR(50),
					realname VARCHAR(40),
					url VARCHAR(100),
					jabber VARCHAR(75),
					icq VARCHAR(12),
					msn VARCHAR(50),
					aim VARCHAR(30),
					yahoo VARCHAR(30),
					location VARCHAR(30),
					use_avatar SMALLINT NOT NULL DEFAULT 0,
					signature TEXT,
					disp_topics SMALLINT,
					disp_posts SMALLINT,
					email_setting SMALLINT NOT NULL DEFAULT 1,
					save_pass SMALLINT NOT NULL DEFAULT 1,
					notify_with_post SMALLINT NOT NULL DEFAULT 0,
					show_smilies SMALLINT NOT NULL DEFAULT 1,
					show_img SMALLINT NOT NULL DEFAULT 1,
					show_img_sig SMALLINT NOT NULL DEFAULT 1,
					show_avatars SMALLINT NOT NULL DEFAULT 1,
					show_sig SMALLINT NOT NULL DEFAULT 1,
					timezone REAL NOT NULL DEFAULT 0,
					language VARCHAR(25) NOT NULL DEFAULT 'English',
					style VARCHAR(25) NOT NULL DEFAULT 'Oxygen',
					num_posts INT NOT NULL DEFAULT 0,
					last_post INT,
					registered INT NOT NULL DEFAULT 0,
					registration_ip VARCHAR(15) NOT NULL DEFAULT '0.0.0.0',
					last_visit INT NOT NULL DEFAULT 0,
					admin_note VARCHAR(30),
					activate_string VARCHAR(50),
					activate_key VARCHAR(8),
					PRIMARY KEY (id)
					)";
			break;

		case 'sqlite':
			$sql = 'CREATE TABLE '.$db_prefix."users (
					id INTEGER NOT NULL,
					group_id INTEGER NOT NULL DEFAULT 4,
					username VARCHAR(200) NOT NULL DEFAULT '',
					password VARCHAR(40) NOT NULL DEFAULT '',
					email VARCHAR(50) NOT NULL DEFAULT '',
					title VARCHAR(50),
					realname VARCHAR(40),
					url VARCHAR(100),
					jabber VARCHAR(75),
					icq VARCHAR(12),
					msn VARCHAR(50),
					aim VARCHAR(30),
					yahoo VARCHAR(30),
					location VARCHAR(30),
					use_avatar INTEGER NOT NULL DEFAULT 0,
					signature TEXT,
					disp_topics INTEGER,
					disp_posts INTEGER,
					email_setting INTEGER NOT NULL DEFAULT 1,
					save_pass INTEGER NOT NULL DEFAULT 1,
					notify_with_post INTEGER NOT NULL DEFAULT 0,
					show_smilies INTEGER NOT NULL DEFAULT 1,
					show_img INTEGER NOT NULL DEFAULT 1,
					show_img_sig INTEGER NOT NULL DEFAULT 1,
					show_avatars INTEGER NOT NULL DEFAULT 1,
					show_sig INTEGER NOT NULL DEFAULT 1,
					timezone FLOAT NOT NULL DEFAULT 0,
					language VARCHAR(25) NOT NULL DEFAULT 'English',
					style VARCHAR(25) NOT NULL DEFAULT 'Oxygen',
					num_posts INTEGER NOT NULL DEFAULT 0,
					last_post INTEGER,
					registered INTEGER NOT NULL DEFAULT 0,
					registration_ip VARCHAR(15) NOT NULL DEFAULT '0.0.0.0',
					last_visit INTEGER NOT NULL DEFAULT 0,
					admin_note VARCHAR(30),
					activate_string VARCHAR(50),
					activate_key VARCHAR(8),
					PRIMARY KEY (id)
					)";
			break;
	}

	$db->query($sql) or error('Unable to create table '.$db_prefix.'users. Please check your settings and try again.',  __FILE__, __LINE__, $db->error());


	// Add some indexes
	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			// We use MySQL's ALTER TABLE ... ADD INDEX syntax instead of CREATE INDEX to avoid problems with users lacking the INDEX privilege
			$queries[] = 'ALTER TABLE '.$db_prefix.'online ADD UNIQUE INDEX '.$db_prefix.'online_user_id_ident_idx(user_id,ident)';
			$queries[] = 'ALTER TABLE '.$db_prefix.'online ADD INDEX '.$db_prefix.'online_user_id_idx(user_id)';
			$queries[] = 'ALTER TABLE '.$db_prefix.'posts ADD INDEX '.$db_prefix.'posts_topic_id_idx(topic_id)';
			$queries[] = 'ALTER TABLE '.$db_prefix.'posts ADD INDEX '.$db_prefix.'posts_multi_idx(poster_id, topic_id)';
			$queries[] = 'ALTER TABLE '.$db_prefix.'reports ADD INDEX '.$db_prefix.'reports_zapped_idx(zapped)';
			$queries[] = 'ALTER TABLE '.$db_prefix.'search_matches ADD INDEX '.$db_prefix.'search_matches_word_id_idx(word_id)';
			$queries[] = 'ALTER TABLE '.$db_prefix.'search_matches ADD INDEX '.$db_prefix.'search_matches_post_id_idx(post_id)';
			$queries[] = 'ALTER TABLE '.$db_prefix.'topics ADD INDEX '.$db_prefix.'topics_forum_id_idx(forum_id)';
			$queries[] = 'ALTER TABLE '.$db_prefix.'topics ADD INDEX '.$db_prefix.'topics_moved_to_idx(moved_to)';
			$queries[] = 'ALTER TABLE '.$db_prefix.'users ADD INDEX '.$db_prefix.'users_registered_idx(registered)';
			$queries[] = 'ALTER TABLE '.$db_prefix.'search_cache ADD INDEX '.$db_prefix.'search_cache_ident_idx(ident(8))';
			$queries[] = 'ALTER TABLE '.$db_prefix.'users ADD INDEX '.$db_prefix.'users_username_idx(username(8))';
			break;

		default:
			$queries[] = 'CREATE INDEX '.$db_prefix.'online_user_id_idx ON '.$db_prefix.'online(user_id)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'posts_topic_id_idx ON '.$db_prefix.'posts(topic_id)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'posts_multi_idx ON '.$db_prefix.'posts(poster_id, topic_id)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'reports_zapped_idx ON '.$db_prefix.'reports(zapped)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'search_matches_word_id_idx ON '.$db_prefix.'search_matches(word_id)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'search_matches_post_id_idx ON '.$db_prefix.'search_matches(post_id)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'topics_forum_id_idx ON '.$db_prefix.'topics(forum_id)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'topics_moved_to_idx ON '.$db_prefix.'topics(moved_to)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'users_registered_idx ON '.$db_prefix.'users(registered)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'users_username_idx ON '.$db_prefix.'users(username)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'search_cache_ident_idx ON '.$db_prefix.'search_cache(ident)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'search_words_id_idx ON '.$db_prefix.'search_words(id)';
			break;
	}

	@reset($queries);
	while (list(, $sql) = @each($queries))
		$db->query($sql) or error('Unable to create indexes. Please check your configuration and try again.',  __FILE__, __LINE__, $db->error());



	$now = time();

	// Insert the four preset groups
	$db->query('INSERT INTO '.$db->prefix."groups (g_title, g_user_title, g_read_board, g_post_replies, g_post_topics, g_post_polls, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_edit_subjects_interval, g_post_flood, g_search_flood) VALUES('Administrators', 'Administrator', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0)") or error('Unable to add group', __FILE__, __LINE__, $db->error());
	$db->query('INSERT INTO '.$db->prefix."groups (g_title, g_user_title, g_read_board, g_post_replies, g_post_topics, g_post_polls, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_edit_subjects_interval, g_post_flood, g_search_flood) VALUES('Moderators', 'Moderator', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0)") or error('Unable to add group', __FILE__, __LINE__, $db->error());
	$db->query('INSERT INTO '.$db->prefix."groups (g_title, g_user_title, g_read_board, g_post_replies, g_post_topics, g_post_polls, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_edit_subjects_interval, g_post_flood, g_search_flood) VALUES('Guest', NULL, 1, 0, 0, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0)") or error('Unable to add group', __FILE__, __LINE__, $db->error());
	$db->query('INSERT INTO '.$db->prefix."groups (g_title, g_user_title, g_read_board, g_post_replies, g_post_topics, g_post_polls, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_edit_subjects_interval, g_post_flood, g_search_flood) VALUES('Members', NULL, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 300, 60, 30)") or error('Unable to add group', __FILE__, __LINE__, $db->error());

	// Insert guest and first admin user
	$db->query('INSERT INTO '.$db_prefix."users (group_id, username, password, email) VALUES(3, 'Guest', 'Guest', 'Guest')")
		or error('Unable to add guest user. Please check your configuration and try again.');

	$db->query('INSERT INTO '.$db_prefix."users (group_id, username, password, email, num_posts, last_post, registered, registration_ip, last_visit) VALUES(1, '".$db->escape($username)."', '".pun_hash($password1)."', '$email', 1, ".$now.", ".$now.", '127.0.0.1', ".$now.')')
		or error('Unable to add administrator user. Please check your configuration and try again.');

	// Insert config data
	$config = array(
		'o_cur_version'				=> "'$fluxbb_version'",
		'o_board_title'				=> "'My FluxBB forum'",
		'o_board_desc'				=> "'Unfortunately no one can be told what FluxBB is - you have to see it for yourself.'",
		'o_server_timezone'			=> "'0'",
		'o_time_format'				=> "'H:i:s'",
		'o_date_format'				=> "'Y-m-d'",
		'o_timeout_visit'			=> "'600'",
		'o_timeout_online'			=> "'300'",
		'o_redirect_delay'			=> "'1'",
		'o_show_version'			=> "'0'",
		'o_show_user_info'			=> "'1'",
		'o_show_post_count'			=> "'1'",
		'o_smilies'					=> "'1'",
		'o_smilies_sig'				=> "'1'",
		'o_make_links'				=> "'1'",
		'o_default_lang'			=> "'English'",
		'o_default_style'			=> "'Oxygen'",
		'o_default_user_group'		=> "'4'",
		'o_topic_review'			=> "'15'",
		'o_disp_topics_default'		=> "'30'",
		'o_disp_posts_default'		=> "'25'",
		'o_indent_num_spaces'		=> "'4'",
		'o_quickpost'				=> "'1'",
		'o_users_online'			=> "'1'",
		'o_censoring'				=> "'0'",
		'o_ranks'					=> "'1'",
		'o_show_dot'				=> "'0'",
		'o_quickjump'				=> "'1'",
		'o_gzip'					=> "'0'",
		'o_additional_navlinks'		=> "''",
		'o_report_method'			=> "'0'",
		'o_regs_report'				=> "'0'",
		'o_mailing_list'			=> "'$email'",
		'o_avatars'					=> "'1'",
		'o_avatars_dir'				=> "'img/avatars'",
		'o_avatars_width'			=> "'60'",
		'o_avatars_height'			=> "'60'",
		'o_avatars_size'			=> "'10240'",
		'o_search_all_forums'		=> "'1'",
		'o_base_url'				=> "'$base_url'",
		'o_admin_email'				=> "'$email'",
		'o_webmaster_email'			=> "'$email'",
		'o_subscriptions'			=> "'1'",
		'o_smtp_host'				=> "NULL",
		'o_smtp_user'				=> "NULL",
		'o_smtp_pass'				=> "NULL",
		'o_regs_allow'				=> "'1'",
		'o_regs_verify'				=> "'0'",
		'o_announcement'			=> "'0'",
		'o_announcement_message'	=> "'Enter your announcement here.'",
		'o_rules'					=> "'0'",
		'o_rules_message'			=> "'Enter your rules here.'",
		'o_maintenance'				=> "'0'",
		'o_maintenance_message'		=> "'The forums are temporarily down for maintenance. Please try again in a few minutes.<br />\\n<br />\\n/Administrator'",
		'p_mod_edit_users'			=> "'1'",
		'p_mod_rename_users'		=> "'0'",
		'p_mod_change_passwords'	=> "'0'",
		'p_mod_ban_users'			=> "'0'",
		'p_message_bbcode'			=> "'1'",
		'p_message_img_tag'			=> "'1'",
		'p_message_all_caps'		=> "'1'",
		'p_subject_all_caps'		=> "'1'",
		'p_sig_all_caps'			=> "'1'",
		'p_sig_bbcode'				=> "'1'",
		'p_sig_img_tag'				=> "'0'",
		'p_sig_length'				=> "'400'",
		'p_sig_lines'				=> "'4'",
		'p_allow_banned_email'		=> "'1'",
		'p_allow_dupe_email'		=> "'0'",
		'p_force_guest_email'		=> "'1'"
	);

	while (list($conf_name, $conf_value) = @each($config))
	{
		$db->query('INSERT INTO '.$db_prefix."config (conf_name, conf_value) VALUES('$conf_name', $conf_value)")
			or error('Unable to insert into table '.$db_prefix.'config. Please check your configuration and try again.');
	}

	// Insert some other default data
	$db->query('INSERT INTO '.$db_prefix."categories (cat_name, disp_position) VALUES('Test category', 1)")
		or error('Unable to insert into table '.$db_prefix.'categories. Please check your configuration and try again.');

	$db->query('INSERT INTO '.$db_prefix."forums (forum_name, forum_desc, num_topics, num_posts, last_post, last_post_id, last_poster, disp_position, cat_id) VALUES('Test forum', 'This is just a test forum', 1, 1, ".$now.", 1, '".$db->escape($username)."', 1, 1)")
		or error('Unable to insert into table '.$db_prefix.'forums. Please check your configuration and try again.');

	$db->query('INSERT INTO '.$db_prefix."topics (poster, subject, posted, last_post, last_post_id, last_poster, forum_id) VALUES('".$db->escape($username)."', 'Test post', ".$now.", ".$now.", 1, '".$db->escape($username)."', 1)")
		or error('Unable to insert into table '.$db_prefix.'topics. Please check your configuration and try again.');

	$db->query('INSERT INTO '.$db_prefix."posts (poster, poster_id, poster_ip, message, posted, topic_id) VALUES('".$db->escape($username)."', 2, '127.0.0.1', 'If you are looking at this (which I guess you are), the install of FluxBB appears to have worked! Now log in and head over to the administration control panel to configure your forum.', ".$now.', 1)')
		or error('Unable to insert into table '.$db_prefix.'posts. Please check your configuration and try again.');

	$db->query('INSERT INTO '.$db_prefix."ranks (rank, min_posts) VALUES('New member', 0)")
		or error('Unable to insert into table '.$db_prefix.'ranks. Please check your configuration and try again.');

	$db->query('INSERT INTO '.$db_prefix."ranks (rank, min_posts) VALUES('Member', 10)")
		or error('Unable to insert into table '.$db_prefix.'ranks. Please check your configuration and try again.');


	if ($db_type == 'pgsql' || $db_type == 'sqlite')
		$db->end_transaction();



	$alerts = '';
	// Check if the cache directory is writable
	if (!@is_writable('./cache/'))
		$alerts .= '<p style="font-size: 1.1em"><span style="color: #C03000"><strong>The cache directory is currently not writable!</strong></span> In order for FluxBB to function properly, the directory named <em>cache</em> must be writable by PHP. Use chmod to set the appropriate directory permissions. If in doubt, chmod to 0777.</p>';

	// Check if default avatar directory is writable
	if (!@is_writable('./img/avatars/'))
		$alerts .= '<p style="font-size: 1.1em"><span style="color: #C03000"><strong>The avatar directory is currently not writable!</strong></span> If you want users to be able to upload their own avatar images you must see to it that the directory named <em>img/avatars</em> is writable by PHP. You can later choose to save avatar images in a different directory (see Admin/Options). Use chmod to set the appropriate directory permissions. If in doubt, chmod to 0777.</p>';


	/// Display config.php and give further instructions
	$config = '<?php'."\n\n".'$db_type = \''.$db_type."';\n".'$db_host = \''.$db_host."';\n".'$db_name = \''.$db_name."';\n".'$db_username = \''.$db_username."';\n".'$db_password = \''.$db_password."';\n".'$db_prefix = \''.$db_prefix."';\n".'$p_connect = false;'."\n\n".'$cookie_name = '."'forum_cookie';\n".'$cookie_domain = '."'';\n".'$cookie_path = '."'/';\n".'$cookie_secure = 0;'."\n".'$cookie_seed = \''.substr(pun_hash(uniqid(rand(), true)), 0, 16)."';\n\ndefine('PUN', 1);";


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title>FluxBB Installation</title>
<link rel="stylesheet" type="text/css" href="style/Oxygen.css" />
</head>
<body>

<div id="puninstall" style="margin: auto 10% auto 10%">
<div class="pun">

<div class="blockform">
	<h2>Final instructions</h2>
	<div class="box">
		<div class="fakeform">
			<div class="inform">
				<div class="forminfo">
					<p>To finalize the installation all you need to do is to <strong>copy and paste the text in the text box below into a file called config.php and then upload this file to the root directory of your FluxBB installation</strong>. Make sure there are no linebreaks or spaces before &lt;?php. You can later edit config.php if you reconfigure your setup (e.g. change the database password or ).</p>
<?php if ($alerts != ''): ?>					<?php echo $alerts."\n" ?>
<?php endif; ?>				</div>
				<fieldset>
					<legend>Copy contents to config.php</legend>
					<div class="infldset">
						<textarea cols="80" rows="20"><?php echo htmlspecialchars($config) ?></textarea>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<div class="forminfo">
					<p>Once you have created config.php with the contents above, FluxBB is installed!</p>
					<p><a href="index.php">Go to forum index</a></p>
				</div>
			</div>
		</div>
	</div>
</div>

</div>
</div>

</body>
</html>
<?php

}
