<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */


define('PUN_SEARCH_MIN_WORD', 3);
define('PUN_SEARCH_MAX_WORD', 20);

define('PUN_ROOT', dirname(__FILE__).'/');

// Send the Content-type header in case the web server is setup to send something else
header('Content-type: text/html; charset=utf-8');

// Load the functions script
require PUN_ROOT.'include/functions.php';

// Load Installer
require PUN_ROOT.'include/install.php';

// Load UTF-8 functions
require PUN_ROOT.'include/utf8/utf8.php';

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


// If we've been passed a default language, use it
$install_lang = isset($_REQUEST['install_lang']) ? pun_trim($_REQUEST['install_lang']) : Installer::DEFAULT_LANG;

// If such a language pack doesn't exist, or isn't up-to-date enough to translate this page, default to English
if (!file_exists(PUN_ROOT.'lang/'.$install_lang.'/install.php'))
	$install_lang = Installer::DEFAULT_LANG;

require PUN_ROOT.'lang/'.$install_lang.'/install.php';

if (file_exists(PUN_ROOT.'config.php'))
{
	// Check to see whether FluxBB is already installed
	include PUN_ROOT.'config.php';

	// If we have the 1.3-legacy constant defined, define the proper 1.4 constant so we don't get an incorrect "need to install" message
	if (defined('FORUM'))
		define('PUN', FORUM);

	// If PUN is defined, config.php is probably valid and thus the software is installed
	if (defined('PUN'))
		exit($lang_install['Already installed']);
}

// Define PUN because email.php requires it
define('PUN', 1);

// If the cache directory is not specified, we use the default setting
if (!defined('FORUM_CACHE_DIR'))
	define('FORUM_CACHE_DIR', PUN_ROOT.'cache/');

// Make sure we are running at least MIN_PHP_VERSION
if (!Installer::is_supported_php_version())
	exit(sprintf($lang_install['You are running error'], 'PHP', PHP_VERSION, Installer::FORUM_VERSION, Installer::MIN_PHP_VERSION));


if (isset($_POST['generate_config']))
{
	header('Content-Type: text/x-delimtext; name="config.php"');
	header('Content-disposition: attachment; filename=config.php');

	echo Installer::generate_config_file($_POST['db_type'], $_POST['db_host'], $_POST['db_name'], $_POST['db_username'], $_POST['db_password'], $_POST['db_prefix']);
	exit;
}


if (!isset($_POST['form_sent']))
{
	$base_url = Installer::guess_base_url();

	// Make sure base_url doesn't end with a slash
	if (substr($base_url, -1) == '/')
		$base_url = substr($base_url, 0, -1);

	$db_type = $db_name = $db_username = $db_prefix = $username = $email = '';
	$db_host = 'localhost';
	$title = $lang_install['My FluxBB Forum'];
	$description = '<p><span>'.$lang_install['Description'].'</span></p>';
	$default_lang = $install_lang;
	$default_style = Installer::DEFAULT_STYLE;
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

	// Make sure base_url doesn't end with a slash
	if (substr($base_url, -1) == '/')
		$base_url = substr($base_url, 0, -1);

	$alerts = Installer::validate_config($username, $password1, $password2, $email, $title, $default_lang, $default_style);
}

// Check if the cache directory is writable
if (!forum_is_writable(FORUM_CACHE_DIR))
	$alerts[] = sprintf($lang_install['Alert cache'], FORUM_CACHE_DIR);

// Check if default avatar directory is writable
if (!forum_is_writable(PUN_ROOT.'img/avatars/'))
	$alerts[] = sprintf($lang_install['Alert avatar'], PUN_ROOT.'img/avatars/');

if (!isset($_POST['form_sent']) || !empty($alerts))
{
	$db_extensions = Installer::determine_database_extensions();
	if (empty($db_extensions))
		error($lang_install['No DB extensions']);

	// Fetch a list of installed languages
	$languages = forum_list_langs();

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo $lang_install['FluxBB Installation'] ?></title>
<link rel="stylesheet" type="text/css" href="style/<?php echo $default_style ?>.css" />
<script type="text/javascript">
/* <![CDATA[ */
function process_form(the_form)
{
	var required_fields = {
		"req_db_type": "<?php echo $lang_install['Database type'] ?>",
		"req_db_host": "<?php echo $lang_install['Database server hostname'] ?>",
		"req_db_name": "<?php echo $lang_install['Database name'] ?>",
		"req_username": "<?php echo $lang_install['Administrator username'] ?>",
		"req_password1": "<?php echo $lang_install['Password'] ?>",
		"req_password2": "<?php echo $lang_install['Confirm password'] ?>",
		"req_email": "<?php echo $lang_install['Administrator email'] ?>",
		"req_title": "<?php echo $lang_install['Board title'] ?>",
		"req_base_url": "<?php echo $lang_install['Base URL'] ?>"
	};
	if (document.all || document.getElementById)
	{
		for (var i = 0; i < the_form.length; ++i)
		{
			var elem = the_form.elements[i];
			if (elem.name && required_fields[elem.name] && !elem.value && elem.type && (/^(?:text(?:area)?|password|file)$/i.test(elem.type)))
			{
				alert('"' + required_fields[elem.name] + '" <?php echo $lang_install['Required field'] ?>');
				elem.focus();
				return false;
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
			<h1><span><?php echo $lang_install['FluxBB Installation'] ?></span></h1>
			<div id="brddesc"><p><?php echo $lang_install['Welcome'] ?></p></div>
		</div>
	</div>
</div>

<div id="brdmain">
<?php if (count($languages) > 1): ?><div class="blockform">
	<h2><span><?php echo $lang_install['Choose install language'] ?></span></h2>
	<div class="box">
		<form id="install" method="post" action="install.php">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_install['Install language'] ?></legend>
					<div class="infldset">
						<p><?php echo $lang_install['Choose install language info'] ?></p>
						<label><strong><?php echo $lang_install['Install language'] ?></strong>
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
			<p class="buttons"><input type="submit" name="start" value="<?php echo $lang_install['Change language'] ?>" /></p>
		</form>
	</div>
</div>
<?php endif; ?>

<div class="blockform">
	<h2><span><?php echo sprintf($lang_install['Install'], Installer::FORUM_VERSION) ?></span></h2>
	<div class="box">
		<form id="install" method="post" action="install.php" onsubmit="this.start.disabled=true;if(process_form(this)){return true;}else{this.start.disabled=false;return false;}">
		<div><input type="hidden" name="form_sent" value="1" /><input type="hidden" name="install_lang" value="<?php echo pun_htmlspecialchars($install_lang) ?>" /></div>
			<div class="inform">
<?php if (!empty($alerts)): ?>				<div class="forminfo error-info">
					<h3><?php echo $lang_install['Errors'] ?></h3>
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
					<h3><?php echo $lang_install['Database setup'] ?></h3>
					<p><?php echo $lang_install['Info 1'] ?></p>
				</div>
				<fieldset>
				<legend><?php echo $lang_install['Select database'] ?></legend>
					<div class="infldset">
						<p><?php echo $lang_install['Info 2'] ?></p>
						<label class="required"><strong><?php echo $lang_install['Database type'] ?> <span><?php echo $lang_install['Required'] ?></span></strong>
						<br /><select name="req_db_type">
<?php

	foreach ($db_extensions as $temp)
	{
		if ($temp[0] == $db_type)
			echo "\t\t\t\t\t\t\t".'<option value="'.$temp[0].'" selected="selected">'.$temp[1].'</option>'."\n";
		else
			echo "\t\t\t\t\t\t\t".'<option value="'.$temp[0].'">'.$temp[1].'</option>'."\n";
	}

?>
						</select>
						<br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_install['Database hostname'] ?></legend>
					<div class="infldset">
						<p><?php echo $lang_install['Info 3'] ?></p>
						<label class="required"><strong><?php echo $lang_install['Database server hostname'] ?> <span><?php echo $lang_install['Required'] ?></span></strong><br /><input type="text" name="req_db_host" value="<?php echo pun_htmlspecialchars($db_host) ?>" size="50" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_install['Database enter name'] ?></legend>
					<div class="infldset">
						<p><?php echo $lang_install['Info 4'] ?></p>
						<label class="required"><strong><?php echo $lang_install['Database name'] ?> <span><?php echo $lang_install['Required'] ?></span></strong><br /><input id="req_db_name" type="text" name="req_db_name" value="<?php echo pun_htmlspecialchars($db_name) ?>" size="30" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_install['Database enter informations'] ?></legend>
					<div class="infldset">
						<p><?php echo $lang_install['Info 5'] ?></p>
						<label class="conl"><?php echo $lang_install['Database username'] ?><br /><input type="text" name="db_username" value="<?php echo pun_htmlspecialchars($db_username) ?>" size="30" /><br /></label>
						<label class="conl"><?php echo $lang_install['Database password'] ?><br /><input type="password" name="db_password" size="30" /><br /></label>
						<div class="clearer"></div>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_install['Database enter prefix'] ?></legend>
					<div class="infldset">
						<p><?php echo $lang_install['Info 6'] ?></p>
						<label><?php echo $lang_install['Table prefix'] ?><br /><input id="db_prefix" type="text" name="db_prefix" value="<?php echo pun_htmlspecialchars($db_prefix) ?>" size="20" maxlength="30" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<div class="forminfo">
					<h3><?php echo $lang_install['Administration setup'] ?></h3>
					<p><?php echo $lang_install['Info 7'] ?></p>
				</div>
				<fieldset>
					<legend><?php echo $lang_install['Administration setup'] ?></legend>
					<div class="infldset">
						<p><?php echo $lang_install['Info 8'] ?></p>
						<label class="required"><strong><?php echo $lang_install['Administrator username'] ?> <span><?php echo $lang_install['Required'] ?></span></strong><br /><input type="text" name="req_username" value="<?php echo pun_htmlspecialchars($username) ?>" size="25" maxlength="25" /><br /></label>
						<label class="conl required"><strong><?php echo $lang_install['Password'] ?> <span><?php echo $lang_install['Required'] ?></span></strong><br /><input id="req_password1" type="password" name="req_password1" size="16" /><br /></label>
						<label class="conl required"><strong><?php echo $lang_install['Confirm password'] ?> <span><?php echo $lang_install['Required'] ?></span></strong><br /><input type="password" name="req_password2" size="16" /><br /></label>
						<div class="clearer"></div>
						<label class="required"><strong><?php echo $lang_install['Administrator email'] ?> <span><?php echo $lang_install['Required'] ?></span></strong><br /><input id="req_email" type="text" name="req_email" value="<?php echo pun_htmlspecialchars($email) ?>" size="50" maxlength="80" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<div class="forminfo">
					<h3><?php echo $lang_install['Board setup'] ?></h3>
					<p><?php echo $lang_install['Info 11'] ?></p>
				</div>
				<fieldset>
					<legend><?php echo $lang_install['General information'] ?></legend>
					<div class="infldset">
						<label class="required"><strong><?php echo $lang_install['Board title'] ?> <span><?php echo $lang_install['Required'] ?></span></strong><br /><input id="req_title" type="text" name="req_title" value="<?php echo pun_htmlspecialchars($title) ?>" size="60" maxlength="255" /><br /></label>
						<label><?php echo $lang_install['Board description'] ?><br /><input id="desc" type="text" name="desc" value="<?php echo pun_htmlspecialchars($description) ?>" size="60" maxlength="255" /><br /></label>
						<label class="required"><strong><?php echo $lang_install['Base URL'] ?> <span><?php echo $lang_install['Required'] ?></span></strong><br /><input id="req_base_url" type="text" name="req_base_url" value="<?php echo pun_htmlspecialchars($base_url) ?>" size="60" maxlength="100" /><br /></label>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_install['Appearance'] ?></legend>
					<div class="infldset">
						<p><?php echo $lang_install['Info 15'] ?></p>
						<label class="required"><strong><?php echo $lang_install['Default language'] ?> <span><?php echo $lang_install['Required'] ?></span></strong><br /><select id="req_default_lang" name="req_default_lang">
<?php

		$languages = forum_list_langs();
		foreach ($languages as $temp)
		{
			if ($temp == $default_lang)
				echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$temp.'" selected="selected">'.$temp.'</option>'."\n";
			else
				echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$temp.'">'.$temp.'</option>'."\n";
		}

?>
						</select><br /></label>
						<label class="required"><strong><?php echo $lang_install['Default style'] ?> <span><?php echo $lang_install['Required'] ?></span></strong><br /><select id="req_default_style" name="req_default_style">
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
			<p class="buttons"><input type="submit" name="start" value="<?php echo $lang_install['Start install'] ?>" /></p>
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
	// Enable/disable avatars depending on file_uploads setting in PHP configuration
	$avatars = in_array(strtolower(@ini_get('file_uploads')), array('on', 'true', '1'));

	// Create the tables
	$db = Installer::create_database(
		$db_type, $db_host, $db_name, $db_username, $db_password, $db_prefix,
		$title, $description, $default_lang, $default_style, $email, $avatars, $base_url
	);

	// Insert some other default data
	Installer::insert_default_groups(); // groups
	Installer::insert_default_users($username, $password1, $email, $default_lang, $default_style); // users
	Installer::insert_default_forum_and_post($username); // forum & post

	$alerts = array();

	// Check if we disabled uploading avatars because file_uploads was disabled
	if (!$avatars)
		$alerts[] = $lang_install['Alert upload'];

	// Generate the config.php file data
	$config = Installer::generate_config_file($db_type, $db_host, $db_name, $db_username, $db_password, $db_prefix);

	// Attempt to write config.php and serve it up for download if writing fails
	$written = false;
	if (forum_is_writable(PUN_ROOT))
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
<title><?php echo $lang_install['FluxBB Installation'] ?></title>
<link rel="stylesheet" type="text/css" href="style/<?php echo $default_style ?>.css" />
</head>
<body>

<div id="puninstall" class="pun">
<div class="top-box"><div><!-- Top Corners --></div></div>
<div class="punwrap">

<div id="brdheader" class="block">
	<div class="box">
		<div id="brdtitle" class="inbox">
			<h1><span><?php echo $lang_install['FluxBB Installation'] ?></span></h1>
			<div id="brddesc"><p><?php echo $lang_install['FluxBB has been installed'] ?></p></div>
		</div>
	</div>
</div>

<div id="brdmain">

<div class="blockform">
	<h2><span><?php echo $lang_install['Final instructions'] ?></span></h2>
	<div class="box">
<?php

if (!$written)
{

?>
		<form method="post" action="install.php">
			<div class="inform">
				<div class="forminfo">
					<p><?php echo $lang_install['Info 17'] ?></p>
					<p><?php echo $lang_install['Info 18'] ?></p>
				</div>
				<input type="hidden" name="generate_config" value="1" />
				<input type="hidden" name="db_type" value="<?php echo $db_type; ?>" />
				<input type="hidden" name="db_host" value="<?php echo $db_host; ?>" />
				<input type="hidden" name="db_name" value="<?php echo pun_htmlspecialchars($db_name); ?>" />
				<input type="hidden" name="db_username" value="<?php echo pun_htmlspecialchars($db_username); ?>" />
				<input type="hidden" name="db_password" value="<?php echo pun_htmlspecialchars($db_password); ?>" />
				<input type="hidden" name="db_prefix" value="<?php echo pun_htmlspecialchars($db_prefix); ?>" />

<?php if (!empty($alerts)): ?>				<div class="forminfo error-info">
					<ul class="error-list">
<?php

foreach ($alerts as $cur_alert)
	echo "\t\t\t\t\t".'<li>'.$cur_alert.'</li>'."\n";
?>
					</ul>
				</div>
<?php endif; ?>			</div>
			<p class="buttons"><input type="submit" value="<?php echo $lang_install['Download config.php file'] ?>" /></p>
		</form>

<?php

}
else
{

?>
		<div class="fakeform">
			<div class="inform">
				<div class="forminfo">
					<p><?php echo $lang_install['FluxBB fully installed'] ?></p>
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
