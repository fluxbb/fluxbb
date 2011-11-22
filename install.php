<?php

/**
 * Copyright (C) 2008-2011 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// The FluxBB version this script installs
define('FORUM_VERSION', '1.4.7');

define('FORUM_DB_REVISION', 15);
define('FORUM_SI_REVISION', 2);
define('FORUM_PARSER_REVISION', 2);

define('MIN_PHP_VERSION', '4.4.0');
define('MIN_MYSQL_VERSION', '4.1.2');
define('MIN_PGSQL_VERSION', '7.0.0');
define('PUN_SEARCH_MIN_WORD', 3);
define('PUN_SEARCH_MAX_WORD', 20);


define('PUN_ROOT', dirname(__FILE__).'/');

// Load the functions script
require PUN_ROOT.'include/functions.php';

// Load UTF-8 functions
require PUN_ROOT.'modules/utf8/php-utf8.php';
require PUN_ROOT.'modules/utf8/functions/trim.php';

// Strip out "bad" UTF-8 characters
forum_remove_bad_characters();

// Reverse the effect of register_globals
forum_unregister_globals();

// Disable error reporting for uninitialized variables
error_reporting(E_ALL);

// Force POSIX locale (to prevent functions such as strtolower() from messing up UTF-8 strings)
setlocale(LC_CTYPE, 'C');

// Turn off magic_quotes_runtime
if (get_magic_quotes_runtime())
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
	$_REQUEST = stripslashes_array($_REQUEST);
}

// Turn off PHP time limit
@set_time_limit(0);


if (file_exists(PUN_ROOT.'config.php'))
{
	// Check to see whether FluxBB is already installed
	include PUN_ROOT.'config.php';

	// If we have the 1.3-legacy constant defined, define the proper 1.4 constant so we don't get an incorrect "need to install" message
	if (defined('FORUM'))
		define('PUN', FORUM);
}

// If the cache directory is not specified, we use the default setting
if (!defined('FORUM_CACHE_DIR'))
	define('FORUM_CACHE_DIR', PUN_ROOT.'cache/');

// Load the cache module
require PUN_ROOT.'modules/cache/cache.php';
$cache = Cache::load('file', array('dir' => FORUM_CACHE_DIR), 'varexport'); // TODO: Move this config into config.php
// TODO: according to the comment above - how do you want to move this to config when it doesn't exist? :)

// Load the language system
require PUN_ROOT.'include/classes/lang.php';
$lang = new Flux_Lang();

// If we've been passed a default language, use it
$install_lang = isset($_REQUEST['install_lang']) ? trim($_REQUEST['install_lang']) : 'English';
$lang->setLanguage($install_lang);

// Load the install.php language file
$lang->load('install');

// If PUN is defined, config.php is probably valid and thus the software is installed
if (defined('PUN'))
	exit($lang->t('Already installed'));

// Define PUN because email.php requires it
define('PUN', 1);

// Make sure we are running at least MIN_PHP_VERSION
if (!function_exists('version_compare') || version_compare(PHP_VERSION, MIN_PHP_VERSION, '<'))
	exit($lang->t('You are running error', 'PHP', PHP_VERSION, FORUM_VERSION, MIN_PHP_VERSION));

// Load the DB module
require PUN_ROOT.'modules/database/src/Database/Adapter.php';


//
// Generate output to be used for config.php
//
function generate_config_file()
{
	global $db_type, $db_host, $db_name, $db_username, $db_password, $db_prefix, $cookie_name, $cookie_seed;

	return '<?php'."\n\n".'$flux_config = array();'."\n\n".'$flux_config[\'db\'][\'type\'] = \''.$db_type."';\n".'$flux_config[\'db\'][\'host\'] = \''.$db_host."';\n".'$flux_config[\'db\'][\'dbname\'] = \''.addslashes($db_name)."';\n".'$flux_config[\'db\'][\'username\'] = \''.addslashes($db_username)."';\n".'$flux_config[\'db\'][\'password\'] = \''.addslashes($db_password)."';\n".'$flux_config[\'db\'][\'prefix\'] = \''.addslashes($db_prefix)."';\n\n".'$flux_config[\'cache\'][\'type\'] = '."'file';\n".'$flux_config[\'cache\'][\'dir\'] = PUN_ROOT.\'cache/\';'."\n\n".'$flux_config[\'cookie\'][\'name\'] = '."'".$cookie_name."';\n".'$flux_config[\'cookie\'][\'domain\'] = '."'';\n".'$flux_config[\'cookie\'][\'path\'] = '."'/';\n".'$flux_config[\'cookie\'][\'secure\'] = 0;'."\n".'$flux_config[\'cookie\'][\'seed\'] = \''.random_key(16, false, true)."';\n\ndefine('PUN', 1);\n";
}


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
	$cookie_name = $_POST['cookie_name'];
	$cookie_seed = $_POST['cookie_seed'];

	echo generate_config_file();
	exit;
}


if (!isset($_POST['form_sent']))
{
	// Make an educated guess regarding base_url
	$base_url  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://';	// protocol
	$base_url .= preg_replace('%:(80|443)$%', '', $_SERVER['HTTP_HOST']);							// host[:port]
	$base_url .= str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));							// path

	if (substr($base_url, -1) == '/')
		$base_url = substr($base_url, 0, -1);

	$db_type = $db_name = $db_username = $db_prefix = $username = $email = '';
	$db_host = 'localhost';
	$title = $lang->t('My FluxBB Forum');
	$description = '<p><span>'.$lang->t('Description').'</span></p>';
	$default_lang = $install_lang;
	$default_style = 'Air';
}
else
{
	$db_type = $_POST['req_db_type'];
	$db_host = pun_trim($_POST['req_db_host']);
	$db_name = pun_trim($_POST['req_db_name']);
	$db_username = pun_trim($_POST['db_username']);
	$db_password = pun_trim($_POST['db_password']);
	$db_prefix = pun_trim($_POST['db_prefix']);
	$username = pun_trim($_POST['req_username']);
	$email = strtolower(pun_trim($_POST['req_email']));
	$password1 = pun_trim($_POST['req_password1']);
	$password2 = pun_trim($_POST['req_password2']);
	$title = pun_trim($_POST['req_title']);
	$description = pun_trim($_POST['desc']);
	$base_url = pun_trim($_POST['req_base_url']);
	$default_lang = pun_trim($_POST['req_default_lang']);
	$default_style = pun_trim($_POST['req_default_style']);
	$alerts = array();

	// Make sure base_url doesn't end with a slash
	if (substr($base_url, -1) == '/')
		$base_url = substr($base_url, 0, -1);

	// Validate username and passwords
	if (pun_strlen($username) < 2)
		$alerts[] = $lang->t('Username 1');
	else if (pun_strlen($username) > 25) // This usually doesn't happen since the form element only accepts 25 characters
		$alerts[] = $lang->t('Username 2');
	else if (!strcasecmp($username, 'Guest'))
		$alerts[] = $lang->t('Username 3');
	else if (preg_match('%[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}%', $username) || preg_match('%((([0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}:[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){5}:([0-9A-Fa-f]{1,4}:)?[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){4}:([0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){3}:([0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){2}:([0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(([0-9A-Fa-f]{1,4}:){0,5}:((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(::([0-9A-Fa-f]{1,4}:){0,5}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|([0-9A-Fa-f]{1,4}::([0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})|(::([0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){1,7}:))%', $username))
		$alerts[] = $lang->t('Username 4');
	else if ((strpos($username, '[') !== false || strpos($username, ']') !== false) && strpos($username, '\'') !== false && strpos($username, '"') !== false)
		$alerts[] = $lang->t('Username 5');
	else if (preg_match('%(?:\[/?(?:b|u|i|h|colou?r|quote|code|img|url|email|list)\]|\[(?:code|quote|list)=)%i', $username))
		$alerts[] = $lang->t('Username 6');

	if (pun_strlen($password1) < 4)
		$alerts[] = $lang->t('Short password');
	else if ($password1 != $password2)
		$alerts[] = $lang->t('Passwords not match');

	// Validate email
	require PUN_ROOT.'include/email.php';

	if (!is_valid_email($email))
		$alerts[] = $lang->t('Wrong email');

	if ($title == '')
		$alerts[] = $lang->t('No board title');

	if (!Flux_Lang::languageExists($default_lang))
		$alerts[] = $lang->t('Error default language');

	$styles = forum_list_styles();
	if (!in_array($default_style, $styles))
		$alerts[] = $lang->t('Error default style');
}

// Check if the cache directory is writable
if (!@is_writable(FORUM_CACHE_DIR))
	$alerts[] = $lang->t('Alert cache', FORUM_CACHE_DIR);

// Check if default avatar directory is writable
if (!@is_writable(PUN_ROOT.'img/avatars/'))
	$alerts[] = $lang->t('Alert avatar', PUN_ROOT.'img/avatars/');

if (!isset($_POST['form_sent']) || !empty($alerts))
{
	// Determine available database extensions
	$db_extensions = Flux_Database_Adapter::getDriverList();

	if (empty($db_extensions))
		error($lang->t('No DB extensions'));

	// Fetch a list of installed languages
	$languages = Flux_Lang::getLanguageList();

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo $lang->t('FluxBB Installation') ?></title>
<link rel="stylesheet" type="text/css" href="style/<?php echo $default_style ?>.css" />
<script type="text/javascript">
/* <![CDATA[ */
function process_form(the_form)
{
	var element_names = {
		"req_db_type": "<?php echo $lang->t('Database type') ?>",
		"req_db_host": "<?php echo $lang->t('Database server hostname') ?>",
		"req_db_name": "<?php echo $lang->t('Database name') ?>",
		"db_prefix": "<?php echo $lang->t('Table prefix') ?>",
		"req_username": "<?php echo $lang->t('Administrator username') ?>",
		"req_password1": "<?php echo $lang->t('Administrator password 1') ?>",
		"req_password2": "<?php echo $lang->t('Administrator password 2') ?>",
		"req_email": "<?php echo $lang->t('Administrator email') ?>",
		"req_title": "<?php echo $lang->t('Board title') ?>",
		"req_base_url": "<?php echo $lang->t('Base URL') ?>"
	};
	if (document.all || document.getElementById)
	{
		for (var i = 0; i < the_form.length; ++i)
		{
			var elem = the_form.elements[i];
			if (elem.name && (/^req_/.test(elem.name)))
			{
				if (!elem.value && elem.type && (/^(?:text(?:area)?|password|file)$/i.test(elem.type)))
				{
					alert('"' + element_names[elem.name] + '" <?php echo $lang->t('Required field') ?>');
					elem.focus();
					return false;
				}
			}
		}
	}
	return true;
}
/* ]]> */
</script>
</head>
<body onload="document.getElementById('install').req_db_type.focus();document.getElementById('install').start.disabled=false;" onunload="">

<div id="puninstall" class="pun">
<div class="top-box"><div><!-- Top Corners --></div></div>
<div class="punwrap">

<div id="brdheader" class="block">
	<div class="box">
		<div id="brdtitle" class="inbox">
			<h1><span><?php echo $lang->t('FluxBB Installation') ?></span></h1>
			<div id="brddesc"><p><?php echo $lang->t('Install message') ?></p><p><?php echo $lang->t('Welcome') ?></p></div>
		</div>
	</div>
</div>

<div id="brdmain">
<?php if (count($languages) > 1): ?><div class="blockform">
	<h2><span><?php echo $lang->t('Choose install language') ?></span></h2>
	<div class="box">
		<form id="install" method="post" action="install.php">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang->t('Install language') ?></legend>
					<div class="infldset">
						<p><?php echo $lang->t('Choose install language info') ?></p>
						<label><strong><?php echo $lang->t('Install language') ?></strong>
						<br /><select name="install_lang">
<?php

		foreach ($languages as $temp)
		{
			if ($temp == $install_lang)
				echo "\t\t\t\t\t".'<option value="'.$temp.'" selected="selected">'.$temp.'</option>'."\n";
			else
				echo "\t\t\t\t\t".'<option value="'.$temp.'">'.$temp.'</option>'."\n";
		}

?>
						</select>
						<br /></label>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="start" value="<?php echo $lang->t('Change language') ?>" /></p>
		</form>
	</div>
</div>
<?php endif; ?>

<div class="blockform">
	<h2><span><?php echo $lang->t('Install') ?></span></h2>
	<div class="box">
		<form id="install" method="post" action="install.php" onsubmit="this.start.disabled=true;if(process_form(this)){return true;}else{this.start.disabled=false;return false;}">
		<div><input type="hidden" name="form_sent" value="1" /><input type="hidden" name="install_lang" value="<?php echo pun_htmlspecialchars($install_lang) ?>" /></div>
			<div class="inform">
<?php if (!empty($alerts)): ?>				<div class="forminfo error-info">
					<h3><?php echo $lang->t('Errors') ?></h3>
					<ul class="error-list">
<?php

foreach ($alerts as $cur_alert)
	echo "\t\t\t\t\t\t".'<li><strong>'.$cur_alert.'</strong></li>'."\n";
?>
					</ul>
				</div>
<?php endif; ?>			</div>
			<div class="inform">
				<div class="forminfo">
					<h3><?php echo $lang->t('Database setup') ?></h3>
					<p><?php echo $lang->t('Info 1') ?></p>
				</div>
				<fieldset>
				<legend><?php echo $lang->t('Select database') ?></legend>
					<div class="infldset">
						<p><?php echo $lang->t('Info 2') ?></p>
<?php /*if ($dual_mysql): ?>						<p><?php echo $lang->t('Dual MySQL') ?></p>
<?php endif; ?><?php if ($mysql_innodb): ?>						<p><?php echo $lang->t('InnoDB') ?></p>
<?php endif;*/ ?>						<label class="required"><strong><?php echo $lang->t('Database type') ?> <span><?php echo $lang->t('Required') ?></span></strong>
						<br /><select name="req_db_type">
<?php

	foreach ($db_extensions as $cur_extension)
	{
		if ($cur_extension == $db_type)
			echo "\t\t\t\t\t\t\t".'<option value="'.$cur_extension.'" selected="selected">'.$cur_extension.'</option>'."\n";
		else
			echo "\t\t\t\t\t\t\t".'<option value="'.$cur_extension.'">'.$cur_extension.'</option>'."\n";
	}

?>
						</select>
						<br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang->t('Database hostname') ?></legend>
					<div class="infldset">
						<p><?php echo $lang->t('Info 3') ?></p>
						<label class="required"><strong><?php echo $lang->t('Database server hostname') ?> <span><?php echo $lang->t('Required') ?></span></strong><br /><input type="text" name="req_db_host" value="<?php echo pun_htmlspecialchars($db_host) ?>" size="50" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang->t('Database enter name') ?></legend>
					<div class="infldset">
						<p><?php echo $lang->t('Info 4') ?></p>
						<label class="required"><strong><?php echo $lang->t('Database name') ?> <span><?php echo $lang->t('Required') ?></span></strong><br /><input id="req_db_name" type="text" name="req_db_name" value="<?php echo pun_htmlspecialchars($db_name) ?>" size="30" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang->t('Database enter informations') ?></legend>
					<div class="infldset">
						<p><?php echo $lang->t('Info 5') ?></p>
						<label class="conl"><?php echo $lang->t('Database username') ?><br /><input type="text" name="db_username" value="<?php echo pun_htmlspecialchars($db_username) ?>" size="30" /><br /></label>
						<label class="conl"><?php echo $lang->t('Database password') ?><br /><input type="password" name="db_password" size="30" /><br /></label>
						<div class="clearer"></div>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang->t('Database enter prefix') ?></legend>
					<div class="infldset">
						<p><?php echo $lang->t('Info 6') ?></p>
						<label><?php echo $lang->t('Table prefix') ?><br /><input id="db_prefix" type="text" name="db_prefix" value="<?php echo pun_htmlspecialchars($db_prefix) ?>" size="20" maxlength="30" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<div class="forminfo">
					<h3><?php echo $lang->t('Administration setup') ?></h3>
					<p><?php echo $lang->t('Info 7') ?></p>
				</div>
				<fieldset>
					<legend><?php echo $lang->t('Admin enter username') ?></legend>
					<div class="infldset">
						<p><?php echo $lang->t('Info 8') ?></p>
						<label class="required"><strong><?php echo $lang->t('Administrator username') ?> <span><?php echo $lang->t('Required') ?></span></strong><br /><input type="text" name="req_username" value="<?php echo pun_htmlspecialchars($username) ?>" size="25" maxlength="25" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang->t('Admin enter password') ?></legend>
					<div class="infldset">
						<p><?php echo $lang->t('Info 9') ?></p>
						<label class="conl required"><strong><?php echo $lang->t('Password') ?> <span><?php echo $lang->t('Required') ?></span></strong><br /><input id="req_password1" type="password" name="req_password1" size="16" /><br /></label>
						<label class="conl required"><strong><?php echo $lang->t('Confirm password') ?> <span><?php echo $lang->t('Required') ?></span></strong><br /><input type="password" name="req_password2" size="16" /><br /></label>
						<div class="clearer"></div>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang->t('Admin enter email') ?></legend>
					<div class="infldset">
						<p><?php echo $lang->t('Info 10') ?></p>
						<label class="required"><strong><?php echo $lang->t('Administrator email') ?> <span><?php echo $lang->t('Required') ?></span></strong><br /><input id="req_email" type="text" name="req_email" value="<?php echo pun_htmlspecialchars($email) ?>" size="50" maxlength="80" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<div class="forminfo">
					<h3><?php echo $lang->t('Board setup') ?></h3>
					<p><?php echo $lang->t('Info 11') ?></p>
				</div>
				<fieldset>
					<legend><?php echo $lang->t('Enter board title') ?></legend>
					<div class="infldset">
						<p><?php echo $lang->t('Info 12') ?></p>
						<label class="required"><strong><?php echo $lang->t('Board title') ?> <span><?php echo $lang->t('Required') ?></span></strong><br /><input id="req_title" type="text" name="req_title" value="<?php echo pun_htmlspecialchars($title) ?>" size="60" maxlength="255" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang->t('Enter board description') ?></legend>
					<div class="infldset">
						<p><?php echo $lang->t('Info 13') ?></p>
						<label><?php echo $lang->t('Board description') ?><br /><input id="desc" type="text" name="desc" value="<?php echo pun_htmlspecialchars($description) ?>" size="60" maxlength="255" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang->t('Enter base URL') ?></legend>
					<div class="infldset">
						<p><?php echo $lang->t('Info 14') ?></p>
						<label class="required"><strong><?php echo $lang->t('Base URL') ?> <span><?php echo $lang->t('Required') ?></span></strong><br /><input id="req_base_url" type="text" name="req_base_url" value="<?php echo pun_htmlspecialchars($base_url) ?>" size="60" maxlength="100" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang->t('Choose the default language') ?></legend>
					<div class="infldset">
						<p><?php echo $lang->t('Info 15') ?></p>
						<label class="required"><strong><?php echo $lang->t('Default language') ?> <span><?php echo $lang->t('Required') ?></span></strong><br /><select id="req_default_lang" name="req_default_lang">
<?php

		$languages = Flux_Lang::getLanguageList();
		foreach ($languages as $temp)
		{
			if ($temp == $default_lang)
				echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$temp.'" selected="selected">'.$temp.'</option>'."\n";
			else
				echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$temp.'">'.$temp.'</option>'."\n";
		}

?>
						</select><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang->t('Choose the default style') ?></legend>
					<div class="infldset">
						<p><?php echo $lang->t('Info 16') ?></p>
						<label class="required"><strong><?php echo $lang->t('Default style') ?> <span><?php echo $lang->t('Required') ?></span></strong><br /><select id="req_default_style" name="req_default_style">
<?php

		$styles = forum_list_styles();
		foreach ($styles as $temp)
		{
			if ($temp == $default_style)
				echo "\t\t\t\t\t\t\t\t\t".'<option value="'.$temp.'" selected="selected">'.str_replace('_', ' ', $temp).'</option>'."\n";
			else
				echo "\t\t\t\t\t\t\t\t\t".'<option value="'.$temp.'">'.str_replace('_', ' ', $temp).'</option>'."\n";
		}

?>
						</select><br /></label>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="start" value="<?php echo $lang->t('Start install') ?>" /></p>
		</form>
	</div>
</div>
</div>

</div>
<div class="end-box"><div><!-- Bottom Corners --></div></div>
</div>

</body>
</html>
<?php

}
else
{
	if (!Flux_Database_Adapter::driverExists($db_type))
		error($lang->t('DB type not valid', pun_htmlspecialchars($db_type)));

	// Create the database object (and connect/select db)
	$options = array('host' => $db_host, 'dbname' => $db_name, 'username' => $db_username, 'password' => $db_password, 'prefix' => $db_prefix);
	$db = Flux_Database_Adapter::factory($db_type, $options);

	// Validate prefix
	if (strlen($db_prefix) > 0 && (!preg_match('%^[a-zA-Z_][a-zA-Z0-9_]*$%', $db_prefix) || strlen($db_prefix) > 40))
		error($lang->t('Table prefix error', $db->prefix));

	// Do some DB type specific checks
	switch ($db_type)
	{
		// TODO: fix the version checks
//		case 'mysql':
//		case 'mysqli':
//		case 'mysql_innodb':
//		case 'mysqli_innodb':
//			$mysql_info = $db->getVersion();
//			if (version_compare($mysql_info['version'], MIN_MYSQL_VERSION, '<'))
//				error($lang->t('You are running error', 'MySQL', $mysql_info['version'], FORUM_VERSION, MIN_MYSQL_VERSION));
//			break;

//		case 'pgsql':
//			$pgsql_info = $db->getVersion();
//			if (version_compare($pgsql_info['version'], MIN_PGSQL_VERSION, '<'))
//				error($lang->t('You are running error', 'PostgreSQL', $pgsql_info['version'], FORUM_VERSION, MIN_PGSQL_VERSION));
//			break;

		case 'SQLite':
			if (strtolower($db_prefix) == 'sqlite_')
				error($lang->t('Prefix reserved'));
			break;
	}

	if ($db->tableExists('users')->run())
	{
		// Make sure FluxBB isn't already installed
		$query = $db->select(array('1' => '1'), 'users AS u');
		$query->where = 'id = :id';
		$params = array(':id' => 1);
		$result = $query->run($params);

		if (!empty($result))
			error($lang->t('Existing table error', $db->prefix.'users', $db_name));

		unset($query, $params, $result);
	}

	// Start a transaction
	$db->startTransaction();

	// Create all tables
	$query = $db->createTable('bans');
	$query->field('id', Flux_Database_Query_Helper_TableColumn::TYPE_SERIAL);
	$query->field('username', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(200));
	$query->field('ip', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(255));
	$query->field('email', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(80));
	$query->field('message', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(255));
	$query->field('expire', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED);
	$query->field('ban_creator', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0, false);

	$query->index('username_idx', array('username' => 'username(25)'));
	$query->index('PRIMARY', array('id'));
	$query->run();

	unset ($query);

	$query = $db->createTable('categories');
	$query->field('id', Flux_Database_Query_Helper_TableColumn::TYPE_SERIAL);
	$query->field('cat_name', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(80), 'New Category', false);
	$query->field('disp_position', Flux_Database_Query_Helper_TableColumn::TYPE_INT, 0, false);
	$query->index('PRIMARY', array('id'));
	$query->run();

	unset ($query);

	$query = $db->createTable('censoring');
	$query->field('id', Flux_Database_Query_Helper_TableColumn::TYPE_SERIAL);
	$query->field('search_for', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(60), '', false);
	$query->field('replace_with', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(60), '', false);
	$query->index('PRIMARY', array('id'));
	$query->run();

	unset ($query);

	$query = $db->createTable('config');
	$query->field('conf_name', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(255), '', true);
	$query->field('conf_value', Flux_Database_Query_Helper_TableColumn::TYPE_TEXT);

	$query->index('PRIMARY', array('conf_name'));
	$query->run();

	unset ($query);

	$query = $db->createTable('forum_perms');
	$query->field('group_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT, 0, true);
	$query->field('forum_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT, 0, false);
	$query->field('read_forum', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 1, false);
	$query->field('post_replies', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 1, false);
	$query->field('post_topics', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 1, false);

	$query->index('PRIMARY', array('group_id', 'forum_id'));
	$query->run();

	unset ($query);

	$query = $db->createTable('forums');
	$query->field('id', Flux_Database_Query_Helper_TableColumn::TYPE_SERIAL);
	$query->field('forum_name', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(80), 'New forum', false);
	$query->field('forum_desc', Flux_Database_Query_Helper_TableColumn::TYPE_TEXT);
	$query->field('redirect_url', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(100));
	$query->field('moderators', Flux_Database_Query_Helper_TableColumn::TYPE_TEXT);
	$query->field('num_topics', Flux_Database_Query_Helper_TableColumn::TYPE_MEDIUMINT_UNSIGNED, 0, false);
	$query->field('num_posts', Flux_Database_Query_Helper_TableColumn::TYPE_MEDIUMINT_UNSIGNED, 0, false);
	$query->field('last_post', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED);
	$query->field('last_post_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED);
	$query->field('last_poster', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(200));
	$query->field('sort_by', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 0, false);
	$query->field('disp_position', Flux_Database_Query_Helper_TableColumn::TYPE_INT, 0, false);
	$query->field('cat_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0, false);
	$query->index('PRIMARY', array('id'));
	$query->run();

	unset ($query);

	$query = $db->createTable('groups');
	$query->field('g_id', Flux_Database_Query_Helper_TableColumn::TYPE_SERIAL);
	$query->field('g_title', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(50), '', false);
	$query->field('g_user_title', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(50));
	$query->field('g_moderator', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 0, false);
	$query->field('g_mod_edit_users', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 0, false);
	$query->field('g_mod_rename_users', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 0, false);
	$query->field('g_mod_change_passwords', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 0, false);
	$query->field('g_mod_ban_users', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 0, false);
	$query->field('g_read_board', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 1, false);
	$query->field('g_view_users', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 1, false);
	$query->field('g_post_replies', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 1, false);
	$query->field('g_post_topics', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 1, false);
	$query->field('g_edit_posts', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 1, false);
	$query->field('g_delete_posts', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 1, false);
	$query->field('g_delete_topics', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 1, false);
	$query->field('g_set_title', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 1, false);
	$query->field('g_search', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 1, false);
	$query->field('g_search_users', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 1, false);
	$query->field('g_send_email', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 1, false);
	$query->field('g_post_flood', Flux_Database_Query_Helper_TableColumn::TYPE_SMALLINT, 30, false);
	$query->field('g_search_flood', Flux_Database_Query_Helper_TableColumn::TYPE_SMALLINT, 30, false);
	$query->field('g_email_flood', Flux_Database_Query_Helper_TableColumn::TYPE_SMALLINT, 60, false);
	$query->field('g_report_flood', Flux_Database_Query_Helper_TableColumn::TYPE_SMALLINT, 60, false);
	$query->index('PRIMARY', array('g_id'));
	$query->run();

	unset ($query);

	$query = $db->createTable('online');
	$query->field('user_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 1, false);
	$query->field('ident', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(200), '', false);
	$query->field('logged', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0, false);
	$query->field('idle', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 0, false);
	$query->field('last_post', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED);
	$query->field('last_search', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED);

	$query->index('user_id_ident_idx', array('user_id', 'ident' => 'ident(25)'), true);
	$query->index('ident_idx', array('ident' => 'ident(25)'));
	$query->index('logged_idx', array('logged'));
	$query->run();

	unset ($query);

	$query = $db->createTable('posts');
	$query->field('id', Flux_Database_Query_Helper_TableColumn::TYPE_SERIAL);
	$query->field('poster', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(200), '', false);
	$query->field('poster_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 1, false);
	$query->field('poster_ip', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(39));
	$query->field('poster_email', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(80));
	$query->field('message', Flux_Database_Query_Helper_TableColumn::TYPE_MEDIUMTEXT);
	$query->field('hide_smilies', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 0, false);
	$query->field('posted', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0, false);
	$query->field('edited', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED);
	$query->field('edited_by', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(200));
	$query->field('topic_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0, false);

	$query->index('topic_id_idx', array('topic_id'));
	$query->index('multi_idx', array('poster_id', 'topic_id'));
	$query->index('PRIMARY', array('id'));
	$query->run();

	unset ($query);

	$query = $db->createTable('ranks');
	$query->field('id', Flux_Database_Query_Helper_TableColumn::TYPE_SERIAL);
	$query->field('rank', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(50), '', false);
	$query->field('min_posts', Flux_Database_Query_Helper_TableColumn::TYPE_MEDIUMINT_UNSIGNED, 0, false);
	$query->index('PRIMARY', array('id'));
	$query->run();

	unset ($query);

	$query = $db->createTable('reports');
	$query->field('id', Flux_Database_Query_Helper_TableColumn::TYPE_SERIAL);
	$query->field('post_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0, false);
	$query->field('topic_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0, false);
	$query->field('forum_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0, false);
	$query->field('reported_by', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0, false);
	$query->field('created', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0, false);
	$query->field('message', Flux_Database_Query_Helper_TableColumn::TYPE_TEXT);
	$query->field('zapped', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED);
	$query->field('zapped_by', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED);

	$query->index('zapped_idx', array('zapped'));
	$query->index('PRIMARY', array('id'));
	$query->run();

	unset ($query);

	$query = $db->createTable('search_cache');
	$query->field('id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0, false);
	$query->field('ident', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(200), '', false);
	$query->field('search_data', Flux_Database_Query_Helper_TableColumn::TYPE_MEDIUMTEXT);
	$query->index('ident_idx', array('ident' => 'ident(8)'));
	$query->index('PRIMARY', array('id'));
	$query->run();

	unset ($query);

	$query = $db->createTable('search_matches');
	$query->field('post_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0, false);
	$query->field('word_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0, false);
	$query->field('subject_match', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 0, false);
	$query->index('word_id_idx', array('word_id'));
	$query->index('post_id_idx', array('post_id'));
	$query->run();

	unset ($query);

	$query = $db->createTable('search_words');
	$query->field('id', Flux_Database_Query_Helper_TableColumn::TYPE_SERIAL);
	$query->field('word', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(20), '', true, 'bin');
	$query->index('id_idx', array('id'));
	$query->index('PRIMARY', array('word'));

	if ($db_type == 'SQLite')
	{
		$query->primary = array('id');
		$query->index('word_idx', array('word'));
	}

	$query->run();

	unset ($query);

	$query = $db->createTable('topic_subscriptions');
	$query->field('user_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0, false);
	$query->field('topic_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0, false);

	$query->index('PRIMARY', array('user_id', 'topic_id'));
	$query->run();

	unset ($query);

	$query = $db->createTable('forum_subscriptions');
	$query->field('user_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0);
	$query->field('forum_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0);

	$query->index('PRIMARY', array('user_id', 'forum_id'));
	$query->run();

	unset ($query);

	$query = $db->createTable('topics');
	$query->field('id', Flux_Database_Query_Helper_TableColumn::TYPE_SERIAL);
	$query->field('poster', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(200), '', false);
	$query->field('subject', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(255), '', false);
	$query->field('posted', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0, false);
	$query->field('first_post_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0, false);
	$query->field('last_post', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0, false);
	$query->field('last_post_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0, false);
	$query->field('last_poster', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(200));
	$query->field('num_views', Flux_Database_Query_Helper_TableColumn::TYPE_MEDIUMINT_UNSIGNED, 0, false);
	$query->field('num_replies', Flux_Database_Query_Helper_TableColumn::TYPE_MEDIUMINT_UNSIGNED, 0, false);
	$query->field('closed', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 0, false);
	$query->field('sticky', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 0, false);
	$query->field('moved_to', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED);
	$query->field('forum_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0, false);

	$query->index('forum_id_idx', array('forum_id'));
	$query->index('moved_to_idx', array('moved_to'));
	$query->index('last_post_idx', array('last_post'));
	$query->index('first_post_id_idx', array('first_post_id'));
	$query->index('PRIMARY', array('id'));
	$query->run();

	unset ($query);

	$query = $db->createTable('users');
	$query->field('id', Flux_Database_Query_Helper_TableColumn::TYPE_SERIAL);
	$query->field('group_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 3, false);
	$query->field('username', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(200), '', false);
	$query->field('password', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(40), '', false);
	$query->field('email', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(80), '', false);
	$query->field('title', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(50), NULL);
	$query->field('realname', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(40));
	$query->field('url', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(100));
	$query->field('jabber', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(80));
	$query->field('icq', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(12));
	$query->field('msn', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(80));
	$query->field('aim', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(30));
	$query->field('yahoo', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(30));
	$query->field('location', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(30));
	$query->field('signature', Flux_Database_Query_Helper_TableColumn::TYPE_TEXT);
	$query->field('disp_topics', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT_UNSIGNED);
	$query->field('disp_posts', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT_UNSIGNED);

	$query->field('email_setting', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 1, false);
	$query->field('notify_with_post', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 0, false);
	$query->field('auto_notify', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 0, false);
	$query->field('show_smilies', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 1, false);
	$query->field('show_img', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 1, false);
	$query->field('show_img_sig', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 1, false);
	$query->field('show_avatars', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 1, false);
	$query->field('show_sig', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 1, false);
	$query->field('timezone', Flux_Database_Query_Helper_TableColumn::TYPE_FLOAT, 0, false);
	$query->field('dst', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 0, false);
	$query->field('time_format', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 0, false);
	$query->field('date_format', Flux_Database_Query_Helper_TableColumn::TYPE_TINYINT, 0, false);
	$query->field('language', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(25),$default_lang, false);
	$query->field('style', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(25), $default_style, false);
	$query->field('num_posts', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0, false);
	$query->field('last_post', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED);
	$query->field('last_search', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED);
	$query->field('last_email_sent', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED);
	$query->field('last_report_sent', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED);
	$query->field('registered', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0, false);
	$query->field('registration_ip', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(39), '0.0.0.0', false);
	$query->field('last_visit', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, 0, false);
	$query->field('admin_note', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(30));
	$query->field('activate_string', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(80));
	$query->field('activate_key', Flux_Database_Query_Helper_TableColumn::TYPE_VARCHAR(8));
	$query->field('last_mark', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, '\'0\'');

	$query->index('username_idx', array('username' => 'username(25)'), true);
	$query->index('registered_idx', array('registered'));
	$query->index('PRIMARY', array('id'));
	$query->run();

	unset ($query);

	$query = $db->createTable('forums_track');
	$query->field('user_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, '\'0\'');
	$query->field('forum_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, '\'0\'');
	$query->field('mark_time', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, '\'0\'');

	$query->index('PRIMARY', array('user_id', 'forum_id'));
	$query->run();

	unset ($query);

	$query = $db->createTable('topics_track');
	$query->field('user_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, '\'0\'');
	$query->field('topic_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, '\'0\'');
	$query->field('forum_id', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, '\'0\'');
	$query->field('mark_time', Flux_Database_Query_Helper_TableColumn::TYPE_INT_UNSIGNED, '\'0\'');

	$query->index('PRIMARY', array('user_id', 'topic_id'));
	$query->index('forum_id_idx', array('forum_id'));
	$query->run();

	unset ($query);


	$now = time();

	// Insert the four preset groups
	// TODO: should g_id be removed for the pgsql?
	$query = $db->insert(array('g_id' => ':g_id', 'g_title' => ':g_title', 'g_user_title' => ':g_user_title', 'g_moderator' => ':g_moderator', 'g_mod_edit_users' => ':g_mod_edit_users', 'g_mod_rename_users' => ':g_mod_rename_users', 'g_mod_change_passwords' => ':g_mod_change_passwords', 'g_mod_ban_users' => ':g_mod_ban_users', 'g_read_board' => ':g_read_board', 'g_view_users' => ':g_view_users', 'g_post_replies' => ':g_post_replies', 'g_post_topics' => ':g_post_topics', 'g_edit_posts' => ':g_edit_posts', 'g_delete_posts' => ':g_delete_posts', 'g_delete_topics' => ':g_delete_topics', 'g_set_title' => ':g_set_title', 'g_search' => ':g_search', 'g_search_users' => ':g_search_users', 'g_send_email' => ':g_send_email', 'g_post_flood' => ':g_post_flood', 'g_search_flood' => ':g_search_flood', 'g_email_flood' => ':g_email_flood', 'g_report_flood' => ':g_report_flood'), 'groups');

	$params = array(':g_id' => 1, ':g_title' => $lang->t('Administrators'), ':g_user_title' => $lang->t('Administrator'), ':g_moderator' => 0, ':g_mod_edit_users' => 0, ':g_mod_rename_users' => 0, ':g_mod_change_passwords' => 0, ':g_mod_ban_users' => 0, ':g_read_board' => 1, ':g_view_users' => 1, ':g_post_replies' => 1, ':g_post_topics' => 1, ':g_edit_posts' => 1, ':g_delete_posts' => 1, ':g_delete_topics' => 1, ':g_set_title' => 1, ':g_search' => 1, ':g_search_users' => 1, ':g_send_email' => 1, ':g_post_flood' => 0, ':g_search_flood' => 0, ':g_email_flood' => 0, ':g_report_flood' => 0);
	$query->run($params);
	unset($params);

	$params = array(':g_id' => 2, ':g_title' => $lang->t('Moderators'), ':g_user_title' => $lang->t('Moderator'), ':g_moderator' => 1, ':g_mod_edit_users' => 1, ':g_mod_rename_users' => 1, ':g_mod_change_passwords' => 1, ':g_mod_ban_users' => 1, ':g_read_board' => 1, ':g_view_users' => 1, ':g_post_replies' => 1, ':g_post_topics' => 1, ':g_edit_posts' => 1, ':g_delete_posts' => 1, ':g_delete_topics' => 1, ':g_set_title' => 1, ':g_search' => 1, ':g_search_users' => 1, ':g_send_email' => 1, ':g_post_flood' => 0, ':g_search_flood' => 0, ':g_email_flood' => 0, ':g_report_flood' => 0);
	$query->run($params);
	unset($params);

	$params = array(':g_id' => 3, ':g_title' => $lang->t('Guests'), ':g_user_title' => NULL, ':g_moderator' => 0, ':g_mod_edit_users' => 0, ':g_mod_rename_users' => 0, ':g_mod_change_passwords' => 0, ':g_mod_ban_users' => 0, ':g_read_board' => 1, ':g_view_users' => 1, ':g_post_replies' => 0, ':g_post_topics' => 0, ':g_edit_posts' => 0, ':g_delete_posts' => 0, ':g_delete_topics' => 0, ':g_set_title' => 0, ':g_search' => 1, ':g_search_users' => 1, ':g_send_email' => 0, ':g_post_flood' => 60, ':g_search_flood' => 30, ':g_email_flood' => 0, ':g_report_flood' => 0);
	$query->run($params);
	unset($params);

	$params = array(':g_id' => 4, ':g_title' => $lang->t('Members'), ':g_user_title' => NULL, ':g_moderator' => 0, ':g_mod_edit_users' => 0, ':g_mod_rename_users' => 0, ':g_mod_change_passwords' => 0, ':g_mod_ban_users' => 0, ':g_read_board' => 1, ':g_view_users' => 1, ':g_post_replies' => 1, ':g_post_topics' => 1, ':g_edit_posts' => 1, ':g_delete_posts' => 1, ':g_delete_topics' => 1, ':g_set_title' => 0, ':g_search' => 1, ':g_search_users' => 1, ':g_send_email' => 1, ':g_post_flood' => 60, ':g_search_flood' => 30, ':g_email_flood' => 60, ':g_report_flood' => 60);
	$query->run($params);
	unset($params);

	unset($query);

	// Insert guest and first admin user
	$query = $db->insert(array('group_id' => ':group_id', 'username' => ':username', 'password' => ':password', 'email' => ':email'), 'users');
	$params = array(':group_id' => 3, ':username' => $lang->t('Guest'), ':password' => $lang->t('Guest'), ':email' => $lang->t('Guest'));
	$query->run($params);
	unset($query, $params);

	$query = $db->insert(array('group_id' => ':group_id', 'username' => ':username', 'password' => ':password', 'email' => ':email', 'language' => ':language', 'style' => ':style', 'num_posts' => ':num_posts', 'last_post' => ':last_post', 'registered' => ':registered', 'registration_ip' => ':registration_ip', 'last_visit' => ':last_visit'), 'users');
	$params = array(':group_id' => 1, ':username' => $username, ':password' => pun_hash($password1), ':email' => $email, ':language' => $default_lang, ':style' => $default_style, ':num_posts' => 1, ':last_post' => $now, ':registered' => $now, ':registration_ip' => get_remote_address(), ':last_visit' => $now);
	$query->run($params);
	unset($query, $params);

	// Enable/disable avatars depending on file_uploads setting in PHP configuration
	$avatars = in_array(strtolower(@ini_get('file_uploads')), array('on', 'true', '1')) ? 1 : 0;

	// Insert config data
	$config = array(
		'o_cur_version'				=> FORUM_VERSION,
		'o_database_revision'		=> FORUM_DB_REVISION,
		'o_searchindex_revision'	=> FORUM_SI_REVISION,
		'o_parser_revision'			=> FORUM_PARSER_REVISION,
		'o_board_title'				=> $title,
		'o_board_desc'				=> $description,
		'o_default_timezone'		=> 0,
		'o_time_format'				=> 'H:i:s',
		'o_date_format'				=> 'Y-m-d',
		'o_timeout_visit'			=> 1800,
		'o_timeout_online'			=> 300,
		'o_redirect_delay'			=> 1,
		'o_show_version'			=> 0,
		'o_show_user_info'			=> 1,
		'o_show_post_count'			=> 1,
		'o_signatures'				=> 1,
		'o_smilies'					=> 1,
		'o_smilies_sig'				=> 1,
		'o_make_links'				=> 1,
		'o_default_lang'			=> $default_lang,
		'o_default_style'			=> $default_style,
		'o_default_user_group'		=> 4,
		'o_topic_review'			=> 15,
		'o_disp_topics_default'		=> 30,
		'o_disp_posts_default'		=> 25,
		'o_indent_num_spaces'		=> 4,
		'o_quote_depth'				=> 3,
		'o_quickpost'				=> 1,
		'o_users_online'			=> 1,
		'o_censoring'				=> 0,
		'o_ranks'					=> 1,
		'o_show_dot'				=> 0,
		'o_topic_views'				=> 1,
		'o_quickjump'				=> 1,
		'o_gzip'					=> 0,
		'o_additional_navlinks'		=> '',
		'o_report_method'			=> 0,
		'o_regs_report'				=> 0,
		'o_default_email_setting'	=> 1,
		'o_mailing_list'			=> $email,
		'o_avatars'					=> $avatars,
		'o_avatars_dir'				=> 'img/avatars',
		'o_avatars_width'			=> 60,
		'o_avatars_height'			=> 60,
		'o_avatars_size'			=> 10240,
		'o_search_all_forums'		=> 1,
		'o_base_url'				=> $base_url,
		'o_admin_email'				=> $email,
		'o_webmaster_email'			=> $email,
		'o_forum_subscriptions'		=> 1,
		'o_topic_subscriptions'		=> 1,
		'o_smtp_host'				=> NULL,
		'o_smtp_user'				=> NULL,
		'o_smtp_pass'				=> NULL,
		'o_smtp_ssl'				=> 0,
		'o_regs_allow'				=> 1,
		'o_regs_verify'				=> 0,
		'o_announcement'			=> 0,
		'o_announcement_message'	=> $lang->t('Announcement'),
		'o_rules'					=> 0,
		'o_rules_message'			=> $lang->t('Rules'),
		'o_maintenance'				=> 0,
		'o_maintenance_message'		=> $lang->t('Maintenance message'),
		'o_default_dst'				=> 0,
		'o_feed_type'				=> 2,
		'o_feed_ttl'				=> 0,
		'p_message_bbcode'			=> 1,
		'p_message_img_tag'			=> 1,
		'p_message_all_caps'		=> 1,
		'p_subject_all_caps'		=> 1,
		'p_sig_all_caps'			=> 1,
		'p_sig_bbcode'				=> 1,
		'p_sig_img_tag'				=> 0,
		'p_sig_length'				=> 400,
		'p_sig_lines'				=> 4,
		'p_allow_banned_email'		=> 1,
		'p_allow_dupe_email'		=> 0,
		'p_force_guest_email'		=> 1
	);

	$query = $db->insert(array('conf_name' => ':conf_name', 'conf_value' => ':conf_value'), 'config');

	foreach ($config as $conf_name => $conf_value)
	{
		$params = array(':conf_name' => $conf_name, ':conf_value' => $conf_value);
		$query->run($params);
		unset($params);
	}
	unset($query);

	// Insert some other default data
	$subject = $lang->t('Test post');
	$message = $lang->t('Message');

	$query = $db->insert(array('rank' => ':rank', 'min_posts' => ':min_posts'), 'ranks');

	// Insert default ranks
	$params = array(':rank' => $lang->t('New member'), ':min_posts' => 0);
	$query->run($params);

	$params = array(':rank' => $lang->t('Member'), ':min_posts' => 10);
	$query->run($params);
	unset($query, $params);

	// Insert first category and forum
	$query = $db->insert(array('cat_name' => ':cat_name', 'disp_position' => ':disp_position'), 'categories');
	$params = array(':cat_name' => $lang->t('Test category'), ':disp_position' => 1);
	$query->run($params);
	unset($query, $params);

	$query = $db->insert(array('forum_name' => ':forum_name', 'forum_desc' => ':forum_desc', 'num_topics' => ':num_topics', 'num_posts' => ':num_posts', 'last_post' => ':last_post', 'last_post_id' => ':last_post_id', 'last_poster' => ':last_poster', 'disp_position' => ':disp_position', 'cat_id' => ':cat_id'), 'forums');
	$params = array(':forum_name' => $lang->t('Test forum'), ':forum_desc' => $lang->t('This is just a test forum'), ':num_topics' => 1, ':num_posts' => 1, ':last_post' => $now, ':last_post_id' => 1, ':last_poster' => $username, ':disp_position' => 1, ':cat_id' => 1);
	$query->run($params);
	unset($query, $params);

	// Insert first topic and post
	$query = $db->insert(array('poster' => ':poster', 'subject' => ':subject', 'posted' => ':posted', 'first_post_id' => ':first_post_id', 'last_post' => ':last_post', 'last_post_id' => ':last_post_id', 'last_poster' => ':last_poster', 'forum_id' => ':forum_id'), 'topics');
	$params = array(':poster' => $username, ':subject' => $subject, ':posted' => $now, ':first_post_id' => 1, ':last_post' => $now, ':last_post_id' => 1, ':last_poster' => $username, ':forum_id' => 1);
	$query->run($params);
	unset($query, $params);

	$query = $db->insert(array('poster' => ':poster', 'poster_id' => ':poster_id', 'poster_ip' => ':poster_ip', 'message' => ':message', 'posted' => ':posted', 'topic_id' => ':topic_id'), 'posts');
	$params = array(':poster' => $username, ':poster_id' => 2, ':poster_ip' => get_remote_address(), ':message' => $message, ':posted' => $now, ':topic_id' => 1);
	$query->run($params);
	unset($query, $params);

	// Index the test post so searching for it works
	require PUN_ROOT.'include/search_idx.php';
	$pun_config['o_default_lang'] = $default_lang;
	update_search_index('post', 1, $message, $subject);

	$db->commitTransaction();


	$alerts = array();

	// Check if we disabled uploading avatars because file_uploads was disabled
	if ($avatars == '0')
		$alerts[] = $lang->t('Alert upload');

	// Add some random bytes at the end of the cookie name to prevent collisions
	$cookie_name = 'pun_cookie_'.random_key(6, false, true);

	// Generate the config.php file data
	$config = generate_config_file();

	// Attempt to write config.php and serve it up for download if writing fails
	$written = false;
	if (is_writable(PUN_ROOT))
	{
		$fh = @fopen(PUN_ROOT.'config.php', 'wb');
		if ($fh)
		{
			fwrite($fh, $config);
			fclose($fh);

			$written = true;
		}
	}


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo $lang->t('FluxBB Installation') ?></title>
<link rel="stylesheet" type="text/css" href="style/<?php echo $default_style ?>.css" />
</head>
<body>

<div id="puninstall" class="pun">
<div class="top-box"><div><!-- Top Corners --></div></div>
<div class="punwrap">

<div id="brdheader" class="block">
	<div class="box">
		<div id="brdtitle" class="inbox">
			<h1><span><?php echo $lang->t('FluxBB Installation') ?></span></h1>
			<div id="brddesc"><p><?php echo $lang->t('FluxBB has been installed') ?></p></div>
		</div>
	</div>
</div>

<div id="brdmain">

<div class="blockform">
	<h2><span><?php echo $lang->t('Final instructions') ?></span></h2>
	<div class="box">
<?php

if (!$written)
{

?>
		<form method="post" action="install.php">
			<div class="inform">
				<div class="forminfo">
					<p><?php echo $lang->t('Info 17') ?></p>
					<p><?php echo $lang->t('Info 18') ?></p>
				</div>
				<input type="hidden" name="generate_config" value="1" />
				<input type="hidden" name="db_type" value="<?php echo $db_type; ?>" />
				<input type="hidden" name="db_host" value="<?php echo $db_host; ?>" />
				<input type="hidden" name="db_name" value="<?php echo pun_htmlspecialchars($db_name); ?>" />
				<input type="hidden" name="db_username" value="<?php echo pun_htmlspecialchars($db_username); ?>" />
				<input type="hidden" name="db_password" value="<?php echo pun_htmlspecialchars($db_password); ?>" />
				<input type="hidden" name="db_prefix" value="<?php echo pun_htmlspecialchars($db_prefix); ?>" />
				<input type="hidden" name="cookie_name" value="<?php echo pun_htmlspecialchars($cookie_name); ?>" />
				<input type="hidden" name="cookie_seed" value="<?php echo pun_htmlspecialchars($cookie_seed); ?>" />

<?php if (!empty($alerts)): ?>				<div class="forminfo error-info">
					<ul class="error-list">
<?php

foreach ($alerts as $cur_alert)
	echo "\t\t\t\t\t".'<li>'.$cur_alert.'</li>'."\n";
?>
					</ul>
				</div>
<?php endif; ?>			</div>
			<p class="buttons"><input type="submit" value="<?php echo $lang->t('Download config.php file') ?>" /></p>
		</form>

<?php

}
else
{

?>
		<div class="fakeform">
			<div class="inform">
				<div class="forminfo">
					<p><?php echo $lang->t('FluxBB fully installed') ?></p>
				</div>
			</div>
		</div>
<?php

}

?>
	</div>
</div>

</div>

</div>
<div class="end-box"><div><!-- Bottom Corners --></div></div>
</div>

</body>
</html>
<?php

}
