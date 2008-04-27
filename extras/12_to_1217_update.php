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


// This script updates the forum database from version 1.2.* to 1.2.17.
// Copy this file to the forum root directory and run it. Then remove it from
// the root directory.


$update_from = array('1.2', '1.2.1', '1.2.2', '1.2.3', '1.2.4', '1.2.5', '1.2.6', '1.2.7', '1.2.8', '1.2.9', '1.2.10', '1.2.11', '1.2.12', '1.2.13', '1.2.14', '1.2.15', '1.2.16');
$update_to = '1.2.17';


define('PUN_ROOT', './');
@include PUN_ROOT.'config.php';

// If PUN isn't defined, config.php is missing or corrupt or we are outside the root directory
if (!defined('PUN'))
	exit('This file must be run from the forum root directory.');

// Enable debug mode
define('PUN_DEBUG', 1);

// Disable error reporting for uninitialized variables
error_reporting(E_ERROR | E_WARNING | E_PARSE);

// Turn off magic_quotes_runtime
set_magic_quotes_runtime(0);

// Turn off PHP time limit
@set_time_limit(0);


// Load the functions script
require PUN_ROOT.'include/functions.php';


// Load DB abstraction layer and try to connect
require PUN_ROOT.'include/dblayer/common_db.php';


// Check current version
$result1 = $db->query('SELECT cur_version FROM '.$db->prefix.'options');
$result2 = $db->query('SELECT conf_value FROM '.$db->prefix.'config WHERE conf_name=\'o_cur_version\'');
$cur_version = ($result1) ? $db->result($result1) : (($result2 && $db->num_rows($result2)) ? $db->result($result2) : 'beta');

if (!in_array($cur_version, $update_from))
	error('Version mismatch. This script updates version '.implode(', ', $update_from).' to version '.$update_to.'. The database \''.$db_name.'\' doesn\'t seem to be running a supported version.', __FILE__, __LINE__);


// Get the forum config
$result = $db->query('SELECT * FROM '.$db->prefix.'config');
while ($cur_config_item = $db->fetch_row($result))
	$pun_config[$cur_config_item[0]] = $cur_config_item[1];


if (!isset($_POST['form_sent']))
{

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title>PunBB Update</title>
<link rel="stylesheet" type="text/css" href="style/Oxygen.css" />
</head>
<body>

<div id="punwrap">
<div id="puninstall" class="pun" style="margin: 10% 20% auto 20%">

<div class="blockform">
	<h2><span>PunBB Update</span></h2>
	<div class="box">
		<form method="post" action="<?php echo $_SERVER['PHP_SELF'] ?>" onsubmit="this.start.disabled=true">
			<div><input type="hidden" name="form_sent" value="1" /></div>
			<div class="inform">
				<p style="font-size: 1.1em">This script will update your current PunBB <?php echo $cur_version ?> forum database to PunBB <?php echo $update_to ?>. The update procedure might take anything from a second to a few minutes depending on the speed of the server and the size of the forum database. Don't forget to make a backup of the database before continuing.</p>
				<p style="font-size: 1.1em">Did you read the update instructions in the documentation? If not, start there.</p>
			</div>
			<p><input type="submit" name="start" value="Start upgrade" /></p>
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
	// If we're upgrading from 1.2
	if ($cur_version == '1.2')
	{
		// Insert new config option o_additional_navlinks
		$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES(\'o_additional_navlinks\', NULL)') or error('Unable to alter DB structure.', __FILE__, __LINE__, $db->error());
	}

	// We need to add a unique index to avoid users having multiple rows in the online table
	if ($db_type == 'mysql' || $db_type == 'mysqli')
	{
		$result = $db->query('SHOW INDEX FROM '.$db->prefix.'online') or error('Unable to check DB structure.', __FILE__, __LINE__, $db->error());

		if ($db->num_rows($result) == 1)
			$db->query('ALTER TABLE '.$db->prefix.'online ADD UNIQUE INDEX '.$db->prefix.'online_user_id_ident_idx(user_id,ident)') or error('Unable to alter DB structure.', __FILE__, __LINE__, $db->error());
	}

	// This feels like a good time to synchronize the forums
	$result = $db->query('SELECT id FROM '.$db->prefix.'forums') or error('Unable to fetch forum info.', __FILE__, __LINE__, $db->error());

	while ($row = $db->fetch_row($result))
		update_forum($row[0]);


	// We'll empty the search cache table as well (using DELETE FROM since SQLite does not support TRUNCATE TABLE)
	$db->query('DELETE FROM '.$db->prefix.'search_cache') or error('Unable to flush search results.', __FILE__, __LINE__, $db->error());


	// Finally, we update the version number
	$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$update_to.'\' WHERE conf_name=\'o_cur_version\'') or error('Unable to update version.', __FILE__, __LINE__, $db->error());


	// Delete all .php files in the cache (someone might have visited the forums while we were updating and thus, generated incorrect cache files)
	$d = dir(PUN_ROOT.'cache');
	while (($entry = $d->read()) !== false)
	{
		if (substr($entry, strlen($entry)-4) == '.php')
			@unlink(PUN_ROOT.'cache/'.$entry);
	}
	$d->close();

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title>PunBB Update</title>
<link rel="stylesheet" type="text/css" href="style/Oxygen.css" />
</head>
<body>

<div id="punwrap">
<div id="puninstall" class="pun" style="margin: 10% 20% auto 20%">

<div class="block">
	<h2><span>Update completed</span></h2>
	<div class="box">
		<div class="inbox">
			<p>Update successful! Your forum database has now been updated to version <?php echo $update_to ?>. You should now remove this script from the forum root directory and follow the rest of the instructions in the documentation.</p>
		</div>
	</div>
</div>

</div>
</div>

</body>
</html>
<?php

}
