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

##
##
##  A few notes of interest for aspiring plugin authors:
##
##  1. If you want to display a message via the message() function, you
##     must do so before calling generate_admin_menu($plugin).
##
##  2. Plugins are loaded by admin_loader.php and must not be
##     terminated (e.g. by calling exit()). After the plugin script has
##     finished, the loader script displays the footer, so don't worry
##     about that. Please note that terminating a plugin by calling
##     message() or redirect() is fine though.
##
##  3. The action attribute of any and all <form> tags and the target
##     URL for the redirect() function must be set to the value of
##     $_SERVER['REQUEST_URI']. This URL can however be extended to
##     include extra variables (like the addition of &amp;foo=bar in
##     the form of this example plugin).
##
##  4. If your plugin is for administrators only, the filename must
##     have the prefix "AP_". If it is for both administrators and
##     moderators, use the prefix "AMP_". This example plugin has the
##     prefix "AMP_" and is therefore available for both admins and
##     moderators in the navigation menu.
##
##  5. Use _ instead of spaces in the file name.
##
##  6. Since plugin scripts are included from the PunBB script
##     admin_loader.php, you have access to all PunBB functions and
##     global variables (e.g. $db, $pun_config, $pun_user etc).
##
##  7. Do your best to keep the look and feel of your plugins' user
##     interface similar to the rest of the admin scripts. Feel free to
##     borrow markup and code from the admin scripts to use in your
##     plugins. If you create your own styles they need to be added to
##     the "base_admin" style sheet.
##
##  8. Plugins must be released under the GNU General Public License or
##     a GPL compatible license. Copy the GPL preamble at the top of
##     this file into your plugin script and alter the copyright notice
##     to refrect the author of the plugin (i.e. you).
##
##


// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

// Tell admin_loader.php that this is indeed a plugin and that it is loaded
define('PUN_PLUGIN_LOADED', 1);

//
// The rest is up to you!
//

// If the "Show text" button was clicked
if (isset($_POST['show_text']))
{
	// Make sure something something was entered
	if (trim($_POST['text_to_show']) == '')
		message('You didn\'t enter anything!');

	// Display the admin navigation menu
	generate_admin_menu($plugin);

?>
	<div class="block">
		<h2><span>Example plugin</span></h2>
		<div class="box">
			<div class="inbox">
				<p>You said "<?php echo pun_htmlspecialchars($_POST['text_to_show']) ?>". Great stuff.</p>
				<p><a href="javascript: history.go(-1)">Go back</a></p>
			</div>
		</div>
	</div>
<?php

}
else	// If not, we show the "Show text" form
{
	// Display the admin navigation menu
	generate_admin_menu($plugin);

?>
	<div id="exampleplugin" class="blockform">
		<h2><span>Example plugin</span></h2>
		<div class="box">
			<div class="inbox">
				<p>This plugin doesn't do anything useful. Hence the name "Example".</p>
				<p>This would be a good spot to talk a little about your plugin. Describe what it does and how it should be used. Be brief, but informative.</p>
			</div>
		</div>

		<h2 class="block2"><span>An example form</span></h2>
		<div class="box">
			<form id="example" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>&amp;foo=bar">
				<div class="inform">
					<fieldset>
						<legend>Enter a piece of text and hit "Show text"!</legend>
						<div class="infldset">
						<table class="aligntop" cellspacing="0">
							<tr>
								<th scope="row">Text to show<div><input type="submit" name="show_text" value="Show text" tabindex="2" /></div></th>
								<td>
									<input type="text" name="text_to_show" size="25" tabindex="1" />
									<span>The text you want to display.</span>
								</td>
							</tr>
						</table>
						</div>
					</fieldset>
				</div>
			</form>
		</div>
	</div>
<?php

}

// Note that the script just ends here. The footer will be included by admin_loader.php.
