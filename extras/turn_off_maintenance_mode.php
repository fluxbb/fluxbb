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

//
// This script turns off the maintenance mode. Use it you happened to log out
// while the forum was in maintenance mode. Copy this file to the forum root
// directory and run it.
//


define('PUN_ROOT', './');
if (!file_exists(PUN_ROOT.'include/common.php'))
	exit('This file must be run from the forum root directory.');

// Make sure common.php doesn't exit with the maintenance message
define('PUN_TURN_OFF_MAINT', 1);

require PUN_ROOT.'include/common.php';


if (isset($_POST['form_sent']))
{
	$pun_db->query('UPDATE '.$pun_db->prefix.'config SET conf_value=\'0\' WHERE conf_name=\'o_maintenance\'') or error(__FILE__, __LINE__);

	// Regenerate the config cache
	require_once PUN_ROOT.'include/cache.php';
	generate_config_cache();

	$pun_db->close();

	exit('<script type="text/javascript">window.location="turn_off_maintenance_mode.php?done=1"</script>JavaScript redirect unsuccessful. Click <a href="turn_off_maintenance_mode.php?done=1">here</a> to continue.');
}


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Turn off maintenance mode</title>
<?php

// Include stylesheets
require PUN_ROOT.'style/'.$pun_user['style'].'/'.$pun_user['style'].'.php';

?>
</head>
<body>

<div id="pun-util">
<div class="pun">

<?php

if (isset($_GET['done']))
{

?>
<div id="pun-main" class="main">

	<h1><span><?php echo $lang_common['Maintenance'] ?></span></h1>

	<div class="main-head">
		<h2><span>Turn off maintenance mode completed</span></h2>
	</div>
	<div class="main-content message">
		<p>Maintenance mode has been turned off. You should now remove this script from the forum root directory.</p>
	</div>

</div>
<?php

}
else
{

?>
<div id="pun-main" class="main">

	<h1><span><?php echo $lang_common['Maintenance'] ?></span></h1>

	<div class="main-head">
		<h2><span>Turn off maintenance mode</span></h2>
	</div>

	<div class="main-content frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $_SERVER['PHP_SELF'] ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token($_SERVER['PHP_SELF']) ?>" />
				<input type="hidden" name="form_sent" value="1" />
			</div>
			<div class="frm-info">
				<p>This script turns off maintenance mode.<br />Use it you happened to log out while the forum was in maintenance mode.</p>
			</div>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="start" value="Start" /></span>
			</div>
		</form>
	</div>

</div>
<?php

}

?>

</div>
</div>

</body>
</html>
