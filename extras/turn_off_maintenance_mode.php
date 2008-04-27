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
	$db->query('UPDATE '.$db->prefix.'config SET conf_value=\'0\' WHERE conf_name=\'o_maintenance\'') or error('Unable to turn off maintenance mode', __FILE__, __LINE__, $db->error());

	// Regenerate the config cache
	require_once PUN_ROOT.'include/cache.php';
	generate_config_cache();

	$db->close();

	exit('<script type="text/javascript">window.location="turn_off_maintenance_mode.php?done=1"</script>JavaScript redirect unsuccessful. Click <a href="turn_off_maintenance_mode.php?done=1">here</a> to continue.');
}


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title>Turn off maintenance mode</title>
<link rel="stylesheet" type="text/css" href="style/<?php echo $pun_config['o_default_style'].'.css' ?>" />
</head>
<body>

<div id="punwrap">
<div id="punutil" class="pun" style="margin: 10% 20% auto 20%">

<?php

if (isset($_GET['done']))
{

?>
<div class="block">
	<h2><span>Turn off maintenance mode completed</span></h2>
	<div class="box">
		<div class="inbox">
			<p>Maintenance mode has been turned off. You should now remove this script from the forum root directory.</p>
		</div>
	</div>
</div>
<?php

}
else
{

?>
<div class="blockform">
	<h2><span>Turn off maintenance mode</span></h2>
	<div class="box">
		<form method="post" action="<?php echo $_SERVER['PHP_SELF'] ?>" onsubmit="this.start.disabled=true">
			<div><input type="hidden" name="form_sent" value="1" /></div>
			<div class="inform">
				<p style="font-size: 1.1em">This script turns off maintenance mode. Use it you happened to log out while the forum was in maintenance mode.</p>
			</div>
			<p><input type="submit" name="start" value="Start" /></p>
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
