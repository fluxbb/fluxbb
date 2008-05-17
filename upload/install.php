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


define('FORUM_VERSION', '1.3 Beta');
define('MIN_PHP_VERSION', '4.3.0');
define('MIN_MYSQL_VERSION', '4.1.2');

define('FORUM_ROOT', './');
define('FORUM', 1);
define('FORUM_DEBUG', 1);

//if (file_exists(FORUM_ROOT.'config.php'))
//	exit('The file \'config.php\' already exists which would mean that FluxBB is already installed. You should go <a href="index.php">here</a> instead.');


// Make sure we are running at least MIN_PHP_VERSION
if (!function_exists('version_compare') || version_compare(PHP_VERSION, MIN_PHP_VERSION, '<'))
	exit('You are running PHP version '.PHP_VERSION.'. FluxBB requires at least PHP '.MIN_PHP_VERSION.' to run properly. You must upgrade your PHP installation before you can continue.');

// Disable error reporting for uninitialized variables
error_reporting(E_ALL);

// Turn off PHP time limit
@set_time_limit(0);

// We need some stuff from functions.php
require FORUM_ROOT.'include/functions.php';


//
// Generate output to be used for config.php
//
function generate_config_file()
{
	global $db_type, $db_host, $db_name, $db_username, $db_password, $db_prefix, $base_url, $cookie_name;

	return '<?php'."\n\n".'$db_type = \''.$db_type."';\n".'$db_host = \''.$db_host."';\n".'$db_name = \''.addslashes($db_name)."';\n".'$db_username = \''.addslashes($db_username)."';\n".'$db_password = \''.addslashes($db_password)."';\n".'$db_prefix = \''.addslashes($db_prefix)."';\n".'$p_connect = false;'."\n\n".'$base_url = \''.$base_url.'\';'."\n\n".'$cookie_name = '."'".$cookie_name."';\n".'$cookie_domain = '."'';\n".'$cookie_path = '."'/';\n".'$cookie_secure = 0;'."\n\ndefine('FORUM', 1);";
}


// Load the language file
require FORUM_ROOT.'lang/English/install.php';


if (isset($_POST['generate_config']))
{
	header('Content-Type: text/x-delimtext; name="config.php"');
	header('Content-disposition: attachment; filename=config.php');

	$db_type = $_POST['db_type'];
	$db_host = $_POST['db_host'];
	$db_name = $_POST['db_name'];
	$db_username = $_POST['db_username'];
	$db_password = $_POST['db_password'];
	$db_prefix = $_POST['db_prefix'];
	$base_url = $_POST['base_url'];
	$cookie_name = $_POST['cookie_name'];

	echo generate_config_file();
	exit;
}


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
		error($lang_install['No database support']);

	// Make an educated guess regarding base_url
	$base_url_guess = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://').preg_replace('/:80$/', '', $_SERVER['HTTP_HOST']).str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
	if (substr($base_url_guess, -1) == '/')
		$base_url_guess = substr($base_url_guess, 0, -1);
		
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>FluxBB Installation</title>
<link rel="stylesheet" type="text/css" href="style/Oxygen/Oxygen.css" />
<link rel="stylesheet" type="text/css" href="style/Oxygen/Oxygen_cs.css" />
<!--[if lte IE 6]><link rel="stylesheet" type="text/css" href="style/Oxygen/Oxygen_ie6.css" /><![endif]-->
<!--[if IE 7]><link rel="stylesheet" type="text/css" href="style/Oxygen/Oxygen_ie7.css" /><![endif]-->
</head>
<body>

<div id="brd-install" class="brd-page">
<div class="brd">

<div id="brd-title">
	<p><strong><?php printf($lang_install['Install FluxBB'], FORUM_VERSION) ?></strong></p>
</div>

<div id="brd-desc">
	<p><?php printf ($lang_install['Install welcome'], FORUM_VERSION) ?></p>
</div>

<div id="brd-head">
	<div id="brd-visit">
		<p><?php echo $lang_install['Install intro'] ?></p>
	</div>
</div>


<div id="brd-main" class="main">

	<div class="main-head">
		<h1><span><?php printf($lang_install['Install FluxBB'], FORUM_VERSION) ?></span></h1>
	</div>

	<div class="main-content frm parted">
		<div class="frm-head">
			<h2><span><?php echo $lang_install['Install head'] ?></span></h2>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="install.php">
			<div class="hidden">
				<input type="hidden" name="form_sent" value="1" />
			</div>
			<div class="frm-part part1">
				<h3><span><?php echo $lang_install['Part1'] ?></span></h3>
				<div class="frm-info">
					<p><?php echo $lang_install['Part1 intro'] ?></p>
					<ul class="pair">
						<li><strong><?php echo $lang_install['Database type'] ?></strong> <span><?php echo $lang_install['Database type info'] ?><?php if ($dual_mysql) echo ' '.$lang_install['Mysql type info'] ?></span></li>
						<li><strong><?php echo $lang_install['Database server'] ?></strong> <span><?php echo $lang_install['Database server info'] ?></span></li>
						<li><strong><?php echo $lang_install['Database name'] ?></strong> <span><?php echo $lang_install['Database name info'] ?></span></li>
						<li><strong><?php echo $lang_install['Database user pass'] ?></strong> <span><?php echo $lang_install['Database username info'] ?></span></li>
						<li><strong><?php echo $lang_install['Table prefix'] ?></strong> <span><?php echo $lang_install['Table prefix info'] ?></span></li>
					</ul>
				</div>
				<fieldset class="frm-set set1">
					<legend class="frm-legend"><strong><?php echo $lang_install['Part1 legend'] ?></strong></legend>
					<div class="frm-fld select required">
						<label for="fld1">
							<span class="fld-label"><?php echo $lang_install['Database type'] ?></span><br />
							<span class="fld-input"><select id="fld1" name="req_db_type">
<?php

	foreach ($db_extensions as $db_type)
		echo "\t\t\t\t\t\t\t".'<option value="'.$db_type[0].'">'.$db_type[1].'</option>'."\n";

?>
							</select></span><br />
							<em class="req-text"><?php echo $lang_install['Required'] ?></em>
							<span class="fld-help"><?php echo $lang_install['Database type help'] ?></span>
						</label>
					</div>
					<div class="frm-fld text required">
						<label for="fld2">
							<span class="fld-label"><?php echo $lang_install['Database server'] ?></span><br />
							<span class="fld-input"><input id="fld2" type="text" name="req_db_host" value="localhost" size="50" maxlength="100" /></span><br />
							<em class="req-text"><?php echo $lang_install['Required'] ?></em>
							<span class="fld-help"><?php echo $lang_install['Database server help'] ?></span>
						</label>
					</div>
					<div class="frm-fld text required">
						<label for="fld3">
							<span class="fld-label"><?php echo $lang_install['Database name'] ?></span><br />
							<span class="fld-input"><input id="fld3" type="text" name="req_db_name" size="35" maxlength="50" /></span><br />
							<em class="req-text"><?php echo $lang_install['Required'] ?></em>
							<span class="fld-help"><?php echo $lang_install['Database name help'] ?></span>
						</label>
					</div>
					<div class="frm-fld text">
						<label for="fld4">
							<span class="fld-label"><?php echo $lang_install['Database username'] ?></span><br />
							<span class="fld-input"><input id="fld4" type="text" name="db_username" size="35" maxlength="50" /></span><br />
							<span class="fld-help"><?php echo $lang_install['Database username help'] ?></span>
						</label>
					</div>
					<div class="frm-fld text">
						<label for="fld5">
							<span class="fld-label"><?php echo $lang_install['Database password'] ?></span><br />
							<span class="fld-input"><input id="fld5" type="password" name="db_password" size="35" /></span><br />
							<span class="fld-help"><?php echo $lang_install['Database password help'] ?></span>
						</label>
					</div>
					<div class="frm-fld text">
						<label for="fld6">
							<span class="fld-label"><?php echo $lang_install['Table prefix'] ?></span><br />
							<span class="fld-input"><input id="fld6" type="text" name="db_prefix" size="20" maxlength="30" /></span><br />
							<span class="fld-help"><?php echo $lang_install['Table prefix help'] ?></span>
						</label>
					</div>
				</fieldset>
			</div>
			<div class="frm-part part2">
				<h3><span><?php echo $lang_install['Part2'] ?></span></h3>
				<div class="frm-info">
					<p><?php echo $lang_install['Part2 intro'] ?></p>
					<ul class="pair">
						<li><strong><?php echo $lang_install['Admin username'] ?></strong> <span><?php echo $lang_install['Admin username info'] ?></span></li>
						<li><strong><?php echo $lang_install['Admin password'] ?></strong> <span><?php echo $lang_install['Admin password info'] ?></span></li>
						<li><strong><?php echo $lang_install['Admin e-mail'] ?></strong> <span><?php echo $lang_install['Admin e-mail info'] ?></span></li>
					</ul>
				</div>
				<fieldset class="frm-set set1">
					<legend class="frm-legend"><strong><?php echo $lang_install['Part2 legend'] ?></strong></legend>
					<div class="frm-fld text required">
						<label for="fld7">
							<span class="fld-label"><?php echo $lang_install['Username'] ?></span><br />
							<span class="fld-input"><input id="fld7" type="text" name="req_username" size="35" maxlength="25" /></span><br />
							<em class="req-text"><?php echo $lang_install['Required'] ?></em>
							<span class="fld-help"><?php echo $lang_install['Username help'] ?></span>
						</label>
					</div>
					<div class="frm-fld text required">
						<label for="fld8">
							<span class="fld-label"><?php echo $lang_install['Password'] ?></span><br />
							<span class="fld-input"><input id="fld8" type="password" name="req_password1" size="35" /></span><br />
							<em class="req-text"><?php echo $lang_install['Required'] ?></em>
							<span class="fld-help"><?php echo $lang_install['Password help'] ?></span>
						</label>
					</div>
					<div class="frm-fld text required">
						<label for="fld9">
							<span class="fld-label"><?php echo $lang_install['Admin confirm password'] ?></span><br />
							<span class="fld-input"><input id="fld9" type="password" name="req_password2" size="35" /></span><br />
							<em class="req-text"><?php echo $lang_install['Required'] ?></em>
							<span class="fld-help"><?php echo $lang_install['Confirm password help'] ?></span>
						</label>
					</div>
					<div class="frm-fld text required">
						<label for="fld10">
							<span class="fld-label"><?php echo $lang_install['E-mail address'] ?></span><br />
							<span class="fld-input"><input id="fld10" type="text" name="req_email" size="50" maxlength="80" /></span><br />
							<em class="req-text"><?php echo $lang_install['Required'] ?></em>
							<span class="fld-help"><?php echo $lang_install['E-mail address help'] ?></span>
						</label>
					</div>
				</fieldset>
			</div>
			<div class="frm-part part3">
				<h3><span><?php echo $lang_install['Part3'] ?></span></h3>
				<div class="frm-info">
					<p><?php echo $lang_install['Part3 intro'] ?></p>
					<ul class="pair">
						<li><strong><?php echo $lang_install['Board title and desc'] ?></strong> <span><?php echo $lang_install['Board title info'] ?></span></li>
						<li><strong><?php echo $lang_install['Base URL'] ?></strong> <span><?php echo $lang_install['Base URL info'] ?></span></li>
					</ul>
				</div>
				<fieldset class="frm-set set1">
					<legend class="frm-legend"><strong><?php echo $lang_install['Part3 legend'] ?></strong></legend>
					<div class="frm-fld text">
						<label for="fld11">
							<span class="fld-label"><?php echo $lang_install['Board title'] ?></span><br />
							<span class="fld-input"><input id="fld11" type="text" name="board_title" size="50" maxlength="255" /></span>
						</label>
					</div>
					<div class="frm-fld text">
						<label for="fld12">
							<span class="fld-label"><?php echo $lang_install['Board description'] ?></span><br />
							<span class="fld-input"><input id="fld12" type="text" name="board_descrip" size="50" maxlength="255" /></span>
						</label>
					</div>
					<div class="frm-fld text required">
						<label for="fld13">
							<span class="fld-label"><?php echo $lang_install['Base URL'] ?></span><br />
							<span class="fld-input"><input id="fld13" type="text" name="req_base_url" value="<?php echo $base_url_guess ?>" size="60" maxlength="100" /></span><br />
							<em class="req-text"><?php echo $lang_install['Required'] ?></em>
							<span class="fld-help"><?php echo $lang_install['Base URL help'] ?></span>
						</label>
					</div>
				</fieldset>
			</div>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="start" value="Start install" /></span>
			</div>
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


	$db_type = $_POST['req_db_type'];
	$db_host = trim($_POST['req_db_host']);
	$db_name = trim($_POST['req_db_name']);
	$db_username = unescape(trim($_POST['db_username']));
	$db_password = unescape(trim($_POST['db_password']));
	$db_prefix = trim($_POST['db_prefix']);
	$username = unescape(trim($_POST['req_username']));
	$email = unescape(strtolower(trim($_POST['req_email'])));
	$password1 = unescape(trim($_POST['req_password1']));
	$password2 = unescape(trim($_POST['req_password2']));
	$board_title = unescape(trim($_POST['board_title']));
	$board_descrip = unescape(trim($_POST['board_descrip']));


	// Make sure base_url doesn't end with a slash
	if (substr($_POST['req_base_url'], -1) == '/')
		$base_url = substr($_POST['req_base_url'], 0, -1);
	else
		$base_url = $_POST['req_base_url'];

	// Validate form
	if (forum_strlen($db_name) == 0)
		error($lang_install['Missing database name']);
	if (forum_strlen($username) < 2)
		error($lang_install['Username too short']);
	if (forum_strlen($username) > 25)
		error($lang_install['Username too long']);
	if (forum_strlen($password1) < 4)
		error($lang_install['Pass too short']);
	if ($password1 != $password2)
		error($lang_install['Pass not match']);
	if (strtolower($username) == 'guest')
		error($lang_install['Username guest']);
	if (preg_match('/[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}/', $username))
		error($lang_install['Username IP']);
	if ((strpos($username, '[') !== false || strpos($username, ']') !== false) && strpos($username, '\'') !== false && strpos($username, '"') !== false)
		error($lang_install['Username reserved chars']);
	if (preg_match('#\[b\]|\[/b\]|\[u\]|\[/u\]|\[i\]|\[/i\]|\[color|\[/color\]|\[quote\]|\[/quote\]|\[code\]|\[/code\]|\[img\]|\[/img\]|\[url|\[/url\]|\[email|\[/email\]#i', $username))
		error($lang_install['Username BBCode']);

	// Validate email
	if (strlen($email) > 80 || !preg_match('/^(([^<>()[\]\\.,;:\s@"\']+(\.[^<>()[\]\\.,;:\s@"\']+)*)|("[^"\']+"))@((\[\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\])|(([a-zA-Z\d\-]+\.)+[a-zA-Z]{2,}))$/', $email))
		error($lang_install['Invalid email']);

	// Make sure board title and description aren't left blank
	if ($board_title == '')
		$board_title = 'My FluxBB forum';
	if ($board_descrip == '')
		$board_descrip = 'Unfortunately no one can be told what FluxBB is - you have to see it for yourself.';

	if (forum_strlen($base_url) == 0)
		error($lang_install['Missing base url']);


	// Load the appropriate DB layer class
	switch ($db_type)
	{
		case 'mysql':
			require FORUM_ROOT.'include/dblayer/mysql.php';
			break;

		case 'mysqli':
			require FORUM_ROOT.'include/dblayer/mysqli.php';
			break;

		case 'pgsql':
			require FORUM_ROOT.'include/dblayer/pgsql.php';
			break;

		case 'sqlite':
			require FORUM_ROOT.'include/dblayer/sqlite.php';
			break;

		default:
			error(sprintf($lang_install['No such database type'], $db_type));
	}

	// Create the database object (and connect/select db)
	$forum_db = new DBLayer($db_host, $db_username, $db_password, $db_name, $db_prefix, false);


	// If MySQL, make sure it's at least 4.1.2
	if ($db_type == 'mysql' || $db_type == 'mysqli')
	{
		$result = $forum_db->query('SELECT VERSION()') or error(__FILE__, __LINE__);
		$mysql_version = $forum_db->result($result);
		if (version_compare($mysql_version, MIN_MYSQL_VERSION, '<'))
			error(sprintf($lang_install['Invalid MySQL version'], $mysql_version, MIN_MYSQL_VERSION));
	}

	// Validate prefix
	if (strlen($db_prefix) > 0 && (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $db_prefix) || strlen($db_prefix) > 40))
		error(sprintf($lang_install['Invalid table prefix'], $db_prefix));

	// Check SQLite prefix collision
	if ($db_type == 'sqlite' && strtolower($db_prefix) == 'sqlite_')
		error($lang_install['SQLite prefix collision']);


	// Make sure FluxBB isn't already installed
	$result = $forum_db->query('SELECT 1 FROM '.$db_prefix.'users WHERE id=1');
	if ($forum_db->num_rows($result))
		error(sprintf($lang_install['FluxBB already installed'], $db_prefix, $db_name));


	// Create all tables
	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."bans (
					id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					username VARCHAR(200),
					ip VARCHAR(255),
					email VARCHAR(80),
					message VARCHAR(255),
					expire INT(10) UNSIGNED,
					ban_creator INT(10) UNSIGNED NOT NULL DEFAULT 0,
					PRIMARY KEY (id)
					) ENGINE = MyISAM CHARACTER SET utf8";
			break;

		case 'pgsql':
			$forum_db->start_transaction();

			$sql = 'CREATE TABLE '.$db_prefix."bans (
					id SERIAL,
					username VARCHAR(200),
					ip VARCHAR(255),
					email VARCHAR(80),
					message VARCHAR(255),
					expire INT,
					ban_creator INT NOT NULL DEFAULT 0,
					PRIMARY KEY (id)
					)";
			break;

		case 'sqlite':
			$forum_db->start_transaction();

			$sql = 'CREATE TABLE '.$db_prefix."bans (
					id INTEGER NOT NULL,
					username VARCHAR(200),
					ip  VARCHAR(255),
					email VARCHAR(80),
					message VARCHAR(255),
					expire INTEGER,
					ban_creator INTEGER NOT NULL DEFAULT 0,
					PRIMARY KEY (id)
					)";
			break;

	}

	$forum_db->query($sql) or error(__FILE__, __LINE__);


	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."categories (
					id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					cat_name VARCHAR(80) NOT NULL DEFAULT 'New Category',
					disp_position INT(10) NOT NULL DEFAULT 0,
					PRIMARY KEY (id)
					) ENGINE = MyISAM CHARACTER SET utf8";
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

	$forum_db->query($sql) or error(__FILE__, __LINE__);



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."censoring (
					id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					search_for VARCHAR(60) NOT NULL DEFAULT '',
					replace_with VARCHAR(60) NOT NULL DEFAULT '',
					PRIMARY KEY (id)
					) ENGINE = MyISAM CHARACTER SET utf8";
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

	$forum_db->query($sql) or error(__FILE__, __LINE__);



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."config (
					conf_name VARCHAR(255) NOT NULL DEFAULT '',
					conf_value TEXT,
					PRIMARY KEY (conf_name)
					) ENGINE = MyISAM CHARACTER SET utf8";
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

	$forum_db->query($sql) or error(__FILE__, __LINE__);



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."extensions (
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
			$sql = 'CREATE TABLE '.$db_prefix."extensions (
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
			$sql = 'CREATE TABLE '.$db_prefix."extensions (
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



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."extension_hooks (
					id VARCHAR(50) NOT NULL DEFAULT '',
					extension_id VARCHAR(50) NOT NULL DEFAULT '',
					code TEXT,
					installed INT(10) UNSIGNED NOT NULL DEFAULT 0,
					priority TINYINT(1) UNSIGNED NOT NULL DEFAULT 5,
					PRIMARY KEY(id, extension_id)
					) ENGINE = MyISAM CHARACTER SET utf8";
			break;

		case 'pgsql':
			$sql = 'CREATE TABLE '.$db_prefix."extension_hooks (
					id VARCHAR(50) NOT NULL DEFAULT '',
					extension_id VARCHAR(50) NOT NULL DEFAULT '',
					code TEXT,
					installed INT NOT NULL DEFAULT 0,
					priority SMALLINT NOT NULL DEFAULT 5,
					PRIMARY KEY(id, extension_id)
					)";
			break;

		case 'sqlite':
			$sql = 'CREATE TABLE '.$db_prefix."extension_hooks (
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
					) ENGINE = MyISAM CHARACTER SET utf8";
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

	$forum_db->query($sql) or error(__FILE__, __LINE__);



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
					) ENGINE = MyISAM CHARACTER SET utf8";
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

	$forum_db->query($sql) or error(__FILE__, __LINE__);



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."groups (
					g_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					g_title VARCHAR(50) NOT NULL DEFAULT '',
					g_user_title VARCHAR(50),
					g_moderator TINYINT(1) NOT NULL DEFAULT 0,
					g_mod_edit_users TINYINT(1) NOT NULL DEFAULT 0,
					g_mod_rename_users TINYINT(1) NOT NULL DEFAULT 0,
					g_mod_change_passwords TINYINT(1) NOT NULL DEFAULT 0,
					g_mod_ban_users TINYINT(1) NOT NULL DEFAULT 0,
					g_read_board TINYINT(1) NOT NULL DEFAULT 1,
					g_view_users TINYINT(1) NOT NULL DEFAULT 1,
					g_post_replies TINYINT(1) NOT NULL DEFAULT 1,
					g_post_topics TINYINT(1) NOT NULL DEFAULT 1,
					g_edit_posts TINYINT(1) NOT NULL DEFAULT 1,
					g_delete_posts TINYINT(1) NOT NULL DEFAULT 1,
					g_delete_topics TINYINT(1) NOT NULL DEFAULT 1,
					g_set_title TINYINT(1) NOT NULL DEFAULT 1,
					g_search TINYINT(1) NOT NULL DEFAULT 1,
					g_search_users TINYINT(1) NOT NULL DEFAULT 1,
					g_send_email TINYINT(1) NOT NULL DEFAULT 1,
					g_edit_subjects_interval SMALLINT(6) NOT NULL DEFAULT 300,
					g_post_flood SMALLINT(6) NOT NULL DEFAULT 30,
					g_search_flood SMALLINT(6) NOT NULL DEFAULT 30,
					g_email_flood SMALLINT(6) NOT NULL DEFAULT 60,
					PRIMARY KEY (g_id)
					) ENGINE = MyISAM CHARACTER SET utf8";
			break;

		case 'pgsql':
			$sql = 'CREATE TABLE '.$db_prefix."groups (
					g_id SERIAL,
					g_title VARCHAR(50) NOT NULL DEFAULT '',
					g_user_title VARCHAR(50),
					g_moderator SMALLINT NOT NULL DEFAULT 0,
					g_mod_edit_users SMALLINT NOT NULL DEFAULT 0,
					g_mod_rename_users SMALLINT NOT NULL DEFAULT 0,
					g_mod_change_passwords SMALLINT NOT NULL DEFAULT 0,
					g_mod_ban_users SMALLINT NOT NULL DEFAULT 0,
					g_read_board SMALLINT NOT NULL DEFAULT 1,
					g_view_users SMALLINT NOT NULL DEFAULT 1,
					g_post_replies SMALLINT NOT NULL DEFAULT 1,
					g_post_topics SMALLINT NOT NULL DEFAULT 1,
					g_edit_posts SMALLINT NOT NULL DEFAULT 1,
					g_delete_posts SMALLINT NOT NULL DEFAULT 1,
					g_delete_topics SMALLINT NOT NULL DEFAULT 1,
					g_set_title SMALLINT NOT NULL DEFAULT 1,
					g_search SMALLINT NOT NULL DEFAULT 1,
					g_search_users SMALLINT NOT NULL DEFAULT 1,
					g_send_email SMALLINT NOT NULL DEFAULT 1,
					g_edit_subjects_interval SMALLINT NOT NULL DEFAULT 300,
					g_post_flood SMALLINT NOT NULL DEFAULT 30,
					g_search_flood SMALLINT NOT NULL DEFAULT 30,
					g_email_flood SMALLINT NOT NULL DEFAULT 60,
					PRIMARY KEY (g_id)
					)";
			break;

		case 'sqlite':
			$sql = 'CREATE TABLE '.$db_prefix."groups (
					g_id INTEGER NOT NULL,
					g_title VARCHAR(50) NOT NULL DEFAULT '',
					g_user_title VARCHAR(50),
					g_moderator INTEGER NOT NULL DEFAULT 0,
					g_mod_edit_users INTEGER NOT NULL DEFAULT 0,
					g_mod_rename_users INTEGER NOT NULL DEFAULT 0,
					g_mod_change_passwords INTEGER NOT NULL DEFAULT 0,
					g_mod_ban_users INTEGER NOT NULL DEFAULT 0,
					g_read_board INTEGER NOT NULL DEFAULT 1,
					g_view_users INTEGER NOT NULL DEFAULT 1,
					g_post_replies INTEGER NOT NULL DEFAULT 1,
					g_post_topics INTEGER NOT NULL DEFAULT 1,
					g_edit_posts INTEGER NOT NULL DEFAULT 1,
					g_delete_posts INTEGER NOT NULL DEFAULT 1,
					g_delete_topics INTEGER NOT NULL DEFAULT 1,
					g_set_title INTEGER NOT NULL DEFAULT 1,
					g_search INTEGER NOT NULL DEFAULT 1,
					g_search_users INTEGER NOT NULL DEFAULT 1,
					g_send_email INTEGER NOT NULL DEFAULT 1,
					g_edit_subjects_interval INTEGER NOT NULL DEFAULT 300,
					g_post_flood INTEGER NOT NULL DEFAULT 30,
					g_search_flood INTEGER NOT NULL DEFAULT 30,
					g_email_flood INTEGER NOT NULL DEFAULT 60,
					PRIMARY KEY (g_id)
					)";
			break;
	}

	$forum_db->query($sql) or error(__FILE__, __LINE__);



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."online (
					user_id INT(10) UNSIGNED NOT NULL DEFAULT 1,
					ident VARCHAR(200) NOT NULL DEFAULT '',
					logged INT(10) UNSIGNED NOT NULL DEFAULT 0,
					idle TINYINT(1) NOT NULL DEFAULT 0,
					csrf_token VARCHAR(40) NOT NULL DEFAULT '',
					prev_url VARCHAR(255)
					) TYPE=HEAP CHARACTER SET utf8;";
			break;

		case 'pgsql':
			$sql = 'CREATE TABLE '.$db_prefix."online (
					user_id INT NOT NULL DEFAULT 1,
					ident VARCHAR(200) NOT NULL DEFAULT '',
					logged INT NOT NULL DEFAULT 0,
					idle SMALLINT NOT NULL DEFAULT 0,
					csrf_token VARCHAR(40) NOT NULL DEFAULT '',
					prev_url VARCHAR(255)
					)";
			break;

		case 'sqlite':
			$sql = 'CREATE TABLE '.$db_prefix."online (
					user_id INTEGER NOT NULL DEFAULT 1,
					ident VARCHAR(200) NOT NULL DEFAULT '',
					logged INTEGER NOT NULL DEFAULT 0,
					idle INTEGER NOT NULL DEFAULT 0,
					csrf_token VARCHAR(40) NOT NULL DEFAULT '',
					prev_url VARCHAR(255)
					)";
			break;
	}

	$forum_db->query($sql) or error(__FILE__, __LINE__);



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."posts (
					id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					poster VARCHAR(200) NOT NULL DEFAULT '',
					poster_id INT(10) UNSIGNED NOT NULL DEFAULT 1,
					poster_ip VARCHAR(39),
					poster_email VARCHAR(80),
					message TEXT,
					hide_smilies TINYINT(1) NOT NULL DEFAULT 0,
					posted INT(10) UNSIGNED NOT NULL DEFAULT 0,
					edited INT(10) UNSIGNED,
					edited_by VARCHAR(200),
					topic_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
					PRIMARY KEY (id)
					) ENGINE = MyISAM CHARACTER SET utf8";
			break;

		case 'pgsql':
			$sql = 'CREATE TABLE '.$db_prefix."posts (
					id SERIAL,
					poster VARCHAR(200) NOT NULL DEFAULT '',
					poster_id INT NOT NULL DEFAULT 1,
					poster_ip VARCHAR(39),
					poster_email VARCHAR(80),
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
					poster_ip VARCHAR(39),
					poster_email VARCHAR(80),
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

	$forum_db->query($sql) or error(__FILE__, __LINE__);



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."ranks (
					id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					rank VARCHAR(50) NOT NULL DEFAULT '',
					min_posts MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT 0,
					PRIMARY KEY (id)
					) ENGINE = MyISAM CHARACTER SET utf8";
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

	$forum_db->query($sql) or error(__FILE__, __LINE__);



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
					) ENGINE = MyISAM CHARACTER SET utf8";
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

	$forum_db->query($sql) or error(__FILE__, __LINE__);



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."search_cache (
					id INT(10) UNSIGNED NOT NULL DEFAULT 0,
					ident VARCHAR(200) NOT NULL DEFAULT '',
					search_data TEXT,
					PRIMARY KEY (id)
					) ENGINE = MyISAM CHARACTER SET utf8";
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

	$forum_db->query($sql) or error(__FILE__, __LINE__);



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."search_matches (
					post_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
					word_id MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT 0,
					subject_match TINYINT(1) NOT NULL DEFAULT 0
					) ENGINE = MyISAM CHARACTER SET utf8";
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

	$forum_db->query($sql) or error(__FILE__, __LINE__);



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."search_words (
					id MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT,
					word VARCHAR(20) BINARY NOT NULL DEFAULT '',
					PRIMARY KEY (word),
					KEY ".$db_prefix."search_words_id_idx (id)
					) ENGINE = MyISAM CHARACTER SET utf8";
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

	$forum_db->query($sql) or error(__FILE__, __LINE__);



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."subscriptions (
					user_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
					topic_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
					PRIMARY KEY (user_id, topic_id)
					) ENGINE = MyISAM CHARACTER SET utf8";
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

	$forum_db->query($sql) or error(__FILE__, __LINE__);



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."topics (
					id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					poster VARCHAR(200) NOT NULL DEFAULT '',
					subject VARCHAR(255) NOT NULL DEFAULT '',
					posted INT(10) UNSIGNED NOT NULL DEFAULT 0,
					first_post_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
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
					) ENGINE = MyISAM CHARACTER SET utf8";
			break;

		case 'pgsql':
			$sql = 'CREATE TABLE '.$db_prefix."topics (
					id SERIAL,
					poster VARCHAR(200) NOT NULL DEFAULT '',
					subject VARCHAR(255) NOT NULL DEFAULT '',
					posted INT NOT NULL DEFAULT 0,
					first_post_id INT NOT NULL DEFAULT 0,
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
					first_post_id INTEGER NOT NULL DEFAULT 0,
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

	$forum_db->query($sql) or error(__FILE__, __LINE__);



	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			$sql = 'CREATE TABLE '.$db_prefix."users (
					id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					group_id INT(10) UNSIGNED NOT NULL DEFAULT 4,
					username VARCHAR(200) NOT NULL DEFAULT '',
					password VARCHAR(40) NOT NULL DEFAULT '',
					salt VARCHAR(12),
					email VARCHAR(80) NOT NULL DEFAULT '',
					title VARCHAR(50),
					realname VARCHAR(40),
					url VARCHAR(100),
					jabber VARCHAR(80),
					icq VARCHAR(12),
					msn VARCHAR(80),
					aim VARCHAR(30),
					yahoo VARCHAR(30),
					location VARCHAR(30),
					signature TEXT,
					disp_topics TINYINT(3) UNSIGNED,
					disp_posts TINYINT(3) UNSIGNED,
					email_setting TINYINT(1) NOT NULL DEFAULT 1,
					save_pass TINYINT(1) NOT NULL DEFAULT 1,
					notify_with_post TINYINT(1) NOT NULL DEFAULT 0,
					auto_notify TINYINT(1) NOT NULL DEFAULT 0,
					show_smilies TINYINT(1) NOT NULL DEFAULT 1,
					show_img TINYINT(1) NOT NULL DEFAULT 1,
					show_img_sig TINYINT(1) NOT NULL DEFAULT 1,
					show_avatars TINYINT(1) NOT NULL DEFAULT 1,
					show_sig TINYINT(1) NOT NULL DEFAULT 1,
					access_keys TINYINT(1) NOT NULL DEFAULT 0,
					timezone FLOAT NOT NULL DEFAULT 0,
					dst TINYINT(1) NOT NULL DEFAULT 0,
					time_format INT(10) UNSIGNED NOT NULL DEFAULT 0,
					date_format INT(10) UNSIGNED NOT NULL DEFAULT 0,
					language VARCHAR(25) NOT NULL DEFAULT 'English',
					style VARCHAR(25) NOT NULL DEFAULT 'Oxygen',
					num_posts INT(10) UNSIGNED NOT NULL DEFAULT 0,
					last_post INT(10) UNSIGNED,
					last_search INT(10) UNSIGNED,
					last_email_sent INT(10) UNSIGNED,
					registered INT(10) UNSIGNED NOT NULL DEFAULT 0,
					registration_ip VARCHAR(39) NOT NULL DEFAULT '0.0.0.0',
					last_visit INT(10) UNSIGNED NOT NULL DEFAULT 0,
					admin_note VARCHAR(30),
					activate_string VARCHAR(80),
					activate_key VARCHAR(8),
					PRIMARY KEY (id)
					) ENGINE = MyISAM CHARACTER SET utf8";
			break;

		case 'pgsql':
			$sql = 'CREATE TABLE '.$db_prefix."users (
					id SERIAL,
					group_id INT NOT NULL DEFAULT 4,
					username VARCHAR(200) NOT NULL DEFAULT '',
					password VARCHAR(40) NOT NULL DEFAULT '',
					salt VARCHAR(12),
					email VARCHAR(80) NOT NULL DEFAULT '',
					title VARCHAR(50),
					realname VARCHAR(40),
					url VARCHAR(100),
					jabber VARCHAR(80),
					icq VARCHAR(12),
					msn VARCHAR(80),
					aim VARCHAR(30),
					yahoo VARCHAR(30),
					location VARCHAR(30),
					signature TEXT,
					disp_topics SMALLINT,
					disp_posts SMALLINT,
					email_setting SMALLINT NOT NULL DEFAULT 1,
					save_pass SMALLINT NOT NULL DEFAULT 1,
					notify_with_post SMALLINT NOT NULL DEFAULT 0,
					auto_notify SMALLINT NOT NULL DEFAULT 0,
					show_smilies SMALLINT NOT NULL DEFAULT 1,
					show_img SMALLINT NOT NULL DEFAULT 1,
					show_img_sig SMALLINT NOT NULL DEFAULT 1,
					show_avatars SMALLINT NOT NULL DEFAULT 1,
					show_sig SMALLINT NOT NULL DEFAULT 1,
					access_keys SMALLINT NOT NULL DEFAULT 0,
					timezone REAL NOT NULL DEFAULT 0,
					dst SMALLINT NOT NULL DEFAULT 0,
					time_format INT NOT NULL DEFAULT 0,
					date_format INT NOT NULL DEFAULT 0,
					language VARCHAR(25) NOT NULL DEFAULT 'English',
					style VARCHAR(25) NOT NULL DEFAULT 'Oxygen',
					num_posts INT NOT NULL DEFAULT 0,
					last_post INT,
					last_search INT,
					last_email_sent INT,
					registered INT NOT NULL DEFAULT 0,
					registration_ip VARCHAR(39) NOT NULL DEFAULT '0.0.0.0',
					last_visit INT NOT NULL DEFAULT 0,
					admin_note VARCHAR(30),
					activate_string VARCHAR(80),
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
					salt VARCHAR(12),
					email VARCHAR(80) NOT NULL DEFAULT '',
					title VARCHAR(50),
					realname VARCHAR(40),
					url VARCHAR(100),
					jabber VARCHAR(80),
					icq VARCHAR(12),
					msn VARCHAR(80),
					aim VARCHAR(30),
					yahoo VARCHAR(30),
					location VARCHAR(30),
					signature TEXT,
					disp_topics INTEGER,
					disp_posts INTEGER,
					email_setting INTEGER NOT NULL DEFAULT 1,
					save_pass INTEGER NOT NULL DEFAULT 1,
					notify_with_post INTEGER NOT NULL DEFAULT 0,
					auto_notify INTEGER NOT NULL DEFAULT 0,
					show_smilies INTEGER NOT NULL DEFAULT 1,
					show_img INTEGER NOT NULL DEFAULT 1,
					show_img_sig INTEGER NOT NULL DEFAULT 1,
					show_avatars INTEGER NOT NULL DEFAULT 1,
					show_sig INTEGER NOT NULL DEFAULT 1,
					access_keys INTEGER NOT NULL DEFAULT 0,
					timezone FLOAT NOT NULL DEFAULT 0,
					dst INTEGER NOT NULL DEFAULT 0,
					time_format INTEGER NOT NULL DEFAULT 0,
					date_format INTEGER NOT NULL DEFAULT 0,
					language VARCHAR(25) NOT NULL DEFAULT 'English',
					style VARCHAR(25) NOT NULL DEFAULT 'Oxygen',
					num_posts INTEGER NOT NULL DEFAULT 0,
					last_post INTEGER,
					last_search INTEGER,
					last_email_sent INTEGER,
					registered INTEGER NOT NULL DEFAULT 0,
					registration_ip VARCHAR(39) NOT NULL DEFAULT '0.0.0.0',
					last_visit INTEGER NOT NULL DEFAULT 0,
					admin_note VARCHAR(30),
					activate_string VARCHAR(80),
					activate_key VARCHAR(8),
					PRIMARY KEY (id)
					)";
			break;
	}

	$forum_db->query($sql) or error(__FILE__, __LINE__);


	// Add some indexes
	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
			// We use MySQL's ALTER TABLE ... ADD INDEX syntax instead of CREATE INDEX to avoid problems with users lacking the INDEX privilege
			$queries[] = 'ALTER TABLE '.$db_prefix.'online ADD UNIQUE INDEX '.$db_prefix.'online_user_id_ident_idx(user_id,ident(25))';
			$queries[] = 'ALTER TABLE '.$db_prefix.'online ADD INDEX '.$db_prefix.'online_user_id_idx(user_id)';
			$queries[] = 'ALTER TABLE '.$db_prefix.'posts ADD INDEX '.$db_prefix.'posts_topic_id_idx(topic_id)';
			$queries[] = 'ALTER TABLE '.$db_prefix.'posts ADD INDEX '.$db_prefix.'posts_multi_idx(poster_id, topic_id)';
			$queries[] = 'ALTER TABLE '.$db_prefix.'reports ADD INDEX '.$db_prefix.'reports_zapped_idx(zapped)';
			$queries[] = 'ALTER TABLE '.$db_prefix.'topics ADD INDEX '.$db_prefix.'topics_forum_id_idx(forum_id)';
			$queries[] = 'ALTER TABLE '.$db_prefix.'topics ADD INDEX '.$db_prefix.'topics_moved_to_idx(moved_to)';
			$queries[] = 'ALTER TABLE '.$db_prefix.'topics ADD INDEX '.$db_prefix.'topics_last_post_idx(last_post)';
			$queries[] = 'ALTER TABLE '.$db_prefix.'topics ADD INDEX '.$db_prefix.'topics_first_post_id_idx(first_post_id)';
			$queries[] = 'ALTER TABLE '.$db_prefix.'users ADD INDEX '.$db_prefix.'users_registered_idx(registered)';
			$queries[] = 'ALTER TABLE '.$db_prefix.'users ADD INDEX '.$db_prefix.'users_username_idx(username(8))';
			$queries[] = 'ALTER TABLE '.$db_prefix.'search_matches ADD INDEX '.$db_prefix.'search_matches_word_id_idx(word_id)';
			$queries[] = 'ALTER TABLE '.$db_prefix.'search_matches ADD INDEX '.$db_prefix.'search_matches_post_id_idx(post_id)';
			$queries[] = 'ALTER TABLE '.$db_prefix.'search_cache ADD INDEX '.$db_prefix.'search_cache_ident_idx(ident(8))';
			break;

		default:
			$queries[] = 'CREATE UNIQUE INDEX '.$db_prefix.'online_user_id_ident_idx ON '.$db_prefix.'online(user_id,ident)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'online_user_id_idx ON '.$db_prefix.'online(user_id)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'posts_topic_id_idx ON '.$db_prefix.'posts(topic_id)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'posts_multi_idx ON '.$db_prefix.'posts(poster_id, topic_id)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'reports_zapped_idx ON '.$db_prefix.'reports(zapped)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'search_matches_word_id_idx ON '.$db_prefix.'search_matches(word_id)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'search_matches_post_id_idx ON '.$db_prefix.'search_matches(post_id)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'topics_forum_id_idx ON '.$db_prefix.'topics(forum_id)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'topics_moved_to_idx ON '.$db_prefix.'topics(moved_to)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'topics_last_post_idx ON '.$db_prefix.'topics(last_post)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'topics_first_post_id_idx ON '.$db_prefix.'topics(first_post_id)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'users_registered_idx ON '.$db_prefix.'users(registered)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'users_username_idx ON '.$db_prefix.'users(username)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'search_cache_ident_idx ON '.$db_prefix.'search_cache(ident)';
			$queries[] = 'CREATE INDEX '.$db_prefix.'search_words_id_idx ON '.$db_prefix.'search_words(id)';
			break;
	}

	@reset($queries);
	while (list(, $sql) = @each($queries))
		$forum_db->query($sql) or error(__FILE__, __LINE__);



	$now = time();

	// Insert the four preset groups
	$forum_db->query('INSERT INTO '.$forum_db->prefix."groups (g_title, g_user_title, g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_send_email, g_edit_subjects_interval, g_post_flood, g_search_flood, g_email_flood) VALUES('Administrators', 'Administrator', 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0)") or error(__FILE__, __LINE__);
	$forum_db->query('INSERT INTO '.$forum_db->prefix."groups (g_title, g_user_title, g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_send_email, g_edit_subjects_interval, g_post_flood, g_search_flood, g_email_flood) VALUES('Guest', NULL, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0)") or error(__FILE__, __LINE__);
	$forum_db->query('INSERT INTO '.$forum_db->prefix."groups (g_title, g_user_title, g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_send_email, g_edit_subjects_interval, g_post_flood, g_search_flood, g_email_flood) VALUES('Members', NULL, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 300, 60, 30, 60)") or error(__FILE__, __LINE__);
	$forum_db->query('INSERT INTO '.$forum_db->prefix."groups (g_title, g_user_title, g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_send_email, g_edit_subjects_interval, g_post_flood, g_search_flood, g_email_flood) VALUES('Moderators', 'Moderator', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0)") or error(__FILE__, __LINE__);

	// Insert guest and first admin user
	$forum_db->query('INSERT INTO '.$db_prefix."users (group_id, username, password, email) VALUES(2, 'Guest', 'Guest', 'Guest')") or error(__FILE__, __LINE__);

	$salt = random_key(12);

	$forum_db->query('INSERT INTO '.$db_prefix."users (group_id, username, password, email, num_posts, last_post, registered, registration_ip, last_visit, salt) VALUES(1, '".$forum_db->escape($username)."', '".sha1($salt.sha1($password1))."', '$email', 1, ".$now.", ".$now.", '127.0.0.1', ".$now.", '".$forum_db->escape($salt)."')") or error(__FILE__, __LINE__);

	// Enable/disable avatars depending on file_uploads setting in PHP configuration
	$avatars = in_array(strtolower(@ini_get('file_uploads')), array('on', 'true', '1')) ? 1 : 0;

	// Enable/disable automatic check for updates depending on PHP environment (require cURL, fsockopen or allow_url_fopen)
	$check_for_updates = (function_exists('curl_init') || function_exists('fsockopen') || in_array(strtolower(@ini_get('allow_url_fopen')), array('on', 'true', '1'))) ? 1 : 0;

	// Insert config data
	$config = array(
		'o_cur_version'				=> "'".FORUM_VERSION."'",
		'o_board_title'				=> "'".$forum_db->escape($board_title)."'",
		'o_board_desc'				=> "'".$forum_db->escape($board_descrip)."'",
		'o_default_timezone'		=> "'0'",
		'o_time_format'				=> "'H:i:s'",
		'o_date_format'				=> "'Y-m-d'",
		'o_check_for_updates'		=> "'$check_for_updates'",
		'o_timeout_visit'			=> "'1800'",
		'o_timeout_online'			=> "'300'",
		'o_redirect_delay'			=> "'1'",
		'o_show_version'			=> "'0'",
		'o_show_user_info'			=> "'1'",
		'o_show_post_count'			=> "'1'",
		'o_signatures'				=> "'1'",
		'o_smilies'					=> "'1'",
		'o_smilies_sig'				=> "'1'",
		'o_make_links'				=> "'1'",
		'o_default_lang'			=> "'English'",
		'o_default_style'			=> "'Oxygen'",
		'o_default_user_group'		=> "'3'",
		'o_topic_review'			=> "'15'",
		'o_disp_topics_default'		=> "'30'",
		'o_disp_posts_default'		=> "'25'",
		'o_indent_num_spaces'		=> "'4'",
		'o_quote_depth'				=> "'3'",
		'o_quickpost'				=> "'1'",
		'o_users_online'			=> "'1'",
		'o_censoring'				=> "'0'",
		'o_ranks'					=> "'1'",
		'o_show_dot'				=> "'0'",
		'o_topic_views'				=> "'1'",
		'o_quickjump'				=> "'1'",
		'o_gzip'					=> "'0'",
		'o_additional_navlinks'		=> "''",
		'o_report_method'			=> "'0'",
		'o_regs_report'				=> "'0'",
		'o_mailing_list'			=> "'$email'",
		'o_avatars'					=> "'$avatars'",
		'o_avatars_dir'				=> "'img/avatars'",
		'o_avatars_width'			=> "'60'",
		'o_avatars_height'			=> "'60'",
		'o_avatars_size'			=> "'10240'",
		'o_search_all_forums'		=> "'1'",
		'o_sef'						=> "'Default'",
		'o_admin_email'				=> "'$email'",
		'o_webmaster_email'			=> "'$email'",
		'o_subscriptions'			=> "'1'",
		'o_smtp_host'				=> "NULL",
		'o_smtp_user'				=> "NULL",
		'o_smtp_pass'				=> "NULL",
		'o_smtp_ssl'				=> "'0'",
		'o_regs_allow'				=> "'1'",
		'o_regs_verify'				=> "'0'",
		'o_announcement'			=> "'0'",
		'o_announcement_heading'	=> "'".$lang_install['Default announce heading']."'",
		'o_announcement_message'	=> "'".$lang_install['Default announce message']."'",
		'o_rules'					=> "'0'",
		'o_rules_message'			=> "'".$lang_install['Default rules']."'",
		'o_maintenance'				=> "'0'",
		'o_maintenance_message'		=> "'".$lang_install['Default maint message']."'",
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
		$forum_db->query('INSERT INTO '.$db_prefix."config (conf_name, conf_value) VALUES('$conf_name', $conf_value)") or error(__FILE__, __LINE__);

	// Insert some other default data
	$forum_db->query('INSERT INTO '.$db_prefix."categories (cat_name, disp_position) VALUES('".$lang_install['Default category name']."', 1)") or error(__FILE__, __LINE__);

	$forum_db->query('INSERT INTO '.$db_prefix."forums (forum_name, forum_desc, num_topics, num_posts, last_post, last_post_id, last_poster, disp_position, cat_id) VALUES('".$lang_install['Default forum name']."', '".$lang_install['Default forum descrip']."', 1, 1, ".$now.", 1, '".$forum_db->escape($username)."', 1, 1)") or error(__FILE__, __LINE__);

	$forum_db->query('INSERT INTO '.$db_prefix.'topics (poster, subject, posted, first_post_id, last_post, last_post_id, last_poster, forum_id) VALUES(\''.$forum_db->escape($username).'\', \''.$lang_install['Default topic subject'].'\', '.$now.', 1, '.$now.', 1, \''.$forum_db->escape($username).'\', 1)') or error(__FILE__, __LINE__);

	$forum_db->query('INSERT INTO '.$db_prefix.'posts (poster, poster_id, poster_ip, message, posted, topic_id) VALUES(\''.$forum_db->escape($username).'\', 2, \'127.0.0.1\', \''.$lang_install['Default post contents'].'\', '.$now.', 1)') or error(__FILE__, __LINE__);

	// Add new post to search table
	require FORUM_ROOT.'include/search_idx.php';
	update_search_index('post', $forum_db->insert_id(), $lang_install['Default post contents'], $lang_install['Default topic subject']);

	$forum_db->query('INSERT INTO '.$db_prefix."ranks (rank, min_posts) VALUES('".$lang_install['Default rank 1']."', 0)") or error(__FILE__, __LINE__);
	$forum_db->query('INSERT INTO '.$db_prefix."ranks (rank, min_posts) VALUES('".$lang_install['Default rank 2']."', 10)") or error(__FILE__, __LINE__);


	if ($db_type == 'pgsql' || $db_type == 'sqlite')
		$forum_db->end_transaction();



	$alerts = array();
	// Check if the cache directory is writable
	if (!@is_writable('./cache/'))
		$alerts[] = '<li>'.$lang_install['No cache write'].'</li>';

	// Check if default avatar directory is writable
	if (!@is_writable('./img/avatars/'))
		$alerts[] = '<li>'.$lang_install['No avatar write'].'</li>';

	// Check if we disabled uploading avatars because file_uploads was disabled
	if ($avatars == '0')
		$alerts[] = '<li>'.$lang_install['File upload alert'].'</li>';

	// Add some random bytes at the end of the cookie name to prevent collisions
	$cookie_name = 'forum_cookie_'.random_key(6, false, true);

	/// Generate the config.php file data
	$config = generate_config_file();

	// Attempt to write config.php and serve it up for download if writing fails
	$written = false;
	if (is_writable(FORUM_ROOT))
	{
		$fh = @fopen(FORUM_ROOT.'config.php', 'wb');
		if ($fh)
		{
			fwrite($fh, $config);
			fclose($fh);

			$written = true;
		}
	}


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>FluxBB Installation</title>
<link rel="stylesheet" type="text/css" href="style/Oxygen/Oxygen.css" />
<link rel="stylesheet" type="text/css" href="style/Oxygen/Oxygen_forms.css" />
<link rel="stylesheet" type="text/css" href="style/Oxygen/Oxygen_cs.css" />
<!--[if lte IE 7]><link rel="stylesheet" type="text/css" href="style/Oxygen/Oxygen_ie.css" /><![endif]-->
</head>

<body>

<div id="brd-install" class="brd-page">
<div class="brd">

<div id="brd-title">
	<p><strong><?php printf($lang_install['Install FluxBB'], FORUM_VERSION) ?></strong></p>
</div>

<div id="brd-desc">
	<p><?php printf($lang_install['Success description'], FORUM_VERSION) ?></p>
</div>

<div id="brd-visit">
	<p><?php echo $lang_install['Success welcome'] ?></p>
</div>

<?php
?>

<div id="brd-main" class="main">

	<div class="main-head">
		<h1><span><?php echo $lang_install['Final instructions'] ?></span></h1>
	</div>

	<div class="main-content frm">
<?php

if (!$written)
{

?>
		<div class="frm-info">
			<p class="warn"><?php echo $lang_install['No write info 1'] ?></p>
			<p class="warn"><?php printf($lang_install['No write info 2'], '<a href="index.php">'.$lang_install['Go to index'].'</a>') ?></p>
		</div>
<?php if (!empty($alerts)): ?>		<div class="frm-error">
			<?php echo $lang_install['Warning'] ?></p>
			<ul>
				<?php echo implode("\n\t\t\t\t", $alerts)."\n" ?>
			</ul>
		</div>
<?php endif; ?>		<form class="frm-form" method="post" accept-charset="utf-8" action="install.php">
			<div class="hidden">
			<input type="hidden" name="generate_config" value="1" />
			<input type="hidden" name="db_type" value="<?php echo $db_type; ?>" />
			<input type="hidden" name="db_host" value="<?php echo $db_host; ?>" />
			<input type="hidden" name="db_name" value="<?php echo forum_htmlencode($db_name); ?>" />
			<input type="hidden" name="db_username" value="<?php echo forum_htmlencode($db_username); ?>" />
			<input type="hidden" name="db_password" value="<?php echo forum_htmlencode($db_password); ?>" />
			<input type="hidden" name="db_prefix" value="<?php echo forum_htmlencode($db_prefix); ?>" />
			<input type="hidden" name="base_url" value="<?php echo forum_htmlencode($base_url); ?>" />
			<input type="hidden" name="cookie_name" value="<?php echo forum_htmlencode($cookie_name); ?>" />
			</div>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" value="<?php echo $lang_install['Download config'] ?>" /></span>
			</div>
		</form>
<?php

}
else
{

?>
		<div class="frm-info">
			<p class="warn"><?php printf($lang_install['Write info'], '<a href="index.php">'.$lang_install['Go to index'].'</a>') ?></p>
		</div>
<?php
}

?>
	</div>

</div>
</div>
</div>
</body>
</html>

<?php

}
