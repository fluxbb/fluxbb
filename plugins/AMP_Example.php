<?php

/**
 * Copyright (C) 2008-2010 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

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
##  6. Since plugin scripts are included from the FluxBB script
##     admin_loader.php, you have access to all FluxBB functions and
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

// Load the admin_plugin_example.php language file
require PUN_ROOT.'lang/'.$admin_language.'/admin_plugin_example.php';

// Tell admin_loader.php that this is indeed a plugin and that it is loaded
define('PUN_PLUGIN_LOADED', 1);

//
// The rest is up to you!
//

// If the "Show text" button was clicked
if (isset($_POST['show_text']))
{
	// Make sure something was entered
	if (trim($_POST['text_to_show']) == '')
		message($lang_admin_plugin_example['No text']);

	// Display the admin navigation menu
	generate_admin_menu($plugin);

?>
	<div class="block">
		<h2><span><?php echo $lang_admin_plugin_example['Example plugin title'] ?></span></h2>
		<div class="box">
			<div class="inbox">
				<p><?php printf($lang_admin_plugin_example['You said'], pun_htmlspecialchars($_POST['text_to_show'])) ?></p>
				<p><a href="javascript: history.go(-1)"><?php echo $lang_admin_common['Go back'] ?></a></p>
			</div>
		</div>
	</div>
<?php

}
else // If not, we show the "Show text" form
{
	// Display the admin navigation menu
	generate_admin_menu($plugin);

?>
	<div class="plugin blockform">
		<h2><span><?php echo $lang_admin_plugin_example['Example plugin title'] ?></span></h2>
		<div class="box">
			<div class="inbox">
				<p><?php echo $lang_admin_plugin_example['Explanation 1'] ?></p>
				<p><?php echo $lang_admin_plugin_example['Explanation 2'] ?></p>
			</div>
		</div>

		<h2 class="block2"><span><?php echo $lang_admin_plugin_example['Example form title'] ?></span></h2>
		<div class="box">
			<form id="example" method="post" action="<?php echo pun_htmlspecialchars($_SERVER['REQUEST_URI']) ?>&amp;foo=bar">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_plugin_example['Legend text'] ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang_admin_plugin_example['Text to show'] ?><div><input type="submit" name="show_text" value="<?php echo $lang_admin_plugin_example['Show text button'] ?>" tabindex="2" /></div></th>
									<td>
										<input type="text" name="text_to_show" size="25" tabindex="1" />
										<span><?php echo $lang_admin_plugin_example['Input content'] ?></span>
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

// Note that the script just ends here. The footer will be included by admin_loader.php
