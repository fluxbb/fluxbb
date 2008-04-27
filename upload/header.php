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


// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

// Send no-cache headers
header('Expires: Thu, 21 Jul 1977 07:30:00 GMT');	// When yours truly first set eyes on this world! :)
header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');		// For HTTP/1.0 compability


// Load the template
if (defined('PUN_ADMIN_CONSOLE'))
	$tpl_main = file_get_contents(PUN_ROOT.'include/template/admin.tpl');
else if (defined('PUN_HELP'))
	$tpl_main = file_get_contents(PUN_ROOT.'include/template/help.tpl');
else
	$tpl_main = file_get_contents(PUN_ROOT.'include/template/main.tpl');


// START SUBST - <pun_include "*">
while (preg_match('#<pun_include "([^/\\\\]*?)\.(php[45]?|inc|html?|txt)">#', $tpl_main, $cur_include))
{
	if (!file_exists(PUN_ROOT.'include/user/'.$cur_include[1].'.'.$cur_include[2]))
		error('Unable to process user include '.htmlspecialchars($cur_include[0]).' from template main.tpl. There is no such file in folder /include/user/');

	ob_start();
	include PUN_ROOT.'include/user/'.$cur_include[1].'.'.$cur_include[2];
	$tpl_temp = ob_get_contents();
	$tpl_main = str_replace($cur_include[0], $tpl_temp, $tpl_main);
    ob_end_clean();
}
// END SUBST - <pun_include "*">


// START SUBST - <pun_content_direction>
$tpl_main = str_replace('<pun_content_direction>', $lang_common['lang_direction'], $tpl_main);
// END SUBST - <pun_content_direction>


// START SUBST - <pun_char_encoding>
$tpl_main = str_replace('<pun_char_encoding>', $lang_common['lang_encoding'], $tpl_main);
// END SUBST - <pun_char_encoding>


// START SUBST - <pun_head>
ob_start();

// Is this a page that we want search index spiders to index?
if (!defined('PUN_ALLOW_INDEX'))
	echo '<meta name="ROBOTS" content="NOINDEX, FOLLOW" />'."\n";

?>
<title><?php echo $page_title ?></title>
<link rel="stylesheet" type="text/css" href="style/<?php echo $pun_user['style'].'.css' ?>" />
<?php

if (defined('PUN_ADMIN_CONSOLE'))
	echo '<link rel="stylesheet" type="text/css" href="style/imports/base_admin.css" />'."\n";

if (isset($required_fields))
{
	// Output JavaScript to validate form (make sure required fields are filled out)

?>
<script type="text/javascript">
<!--
function process_form(the_form)
{
	var element_names = new Object()
<?php

	// Output a JavaScript array with localised field names
	while (list($elem_orig, $elem_trans) = @each($required_fields))
		echo "\t".'element_names["'.$elem_orig.'"] = "'.addslashes(str_replace('&nbsp;', ' ', $elem_trans)).'"'."\n";

?>

	if (document.all || document.getElementById)
	{
		for (i = 0; i < the_form.length; ++i)
		{
			var elem = the_form.elements[i]
			if (elem.name && elem.name.substring(0, 4) == "req_")
			{
				if (elem.type && (elem.type=="text" || elem.type=="textarea" || elem.type=="password" || elem.type=="file") && elem.value=='')
				{
					alert("\"" + element_names[elem.name] + "\" <?php echo $lang_common['required field'] ?>")
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
<?php

}

$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
if (strpos($user_agent, 'msie') !== false && strpos($user_agent, 'windows') !== false && strpos($user_agent, 'opera') === false)
	echo '<script type="text/javascript" src="style/imports/minmax.js"></script>';

$tpl_temp = trim(ob_get_contents());
$tpl_main = str_replace('<pun_head>', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <pun_head>


// START SUBST - <body>
if (isset($focus_element))
{
	$tpl_main = str_replace('<body onload="', '<body onload="document.getElementById(\''.$focus_element[0].'\').'.$focus_element[1].'.focus();', $tpl_main);
	$tpl_main = str_replace('<body>', '<body onload="document.getElementById(\''.$focus_element[0].'\').'.$focus_element[1].'.focus()">', $tpl_main);
}
// END SUBST - <body>


// START SUBST - <pun_page>
$tpl_main = str_replace('<pun_page>', htmlspecialchars(basename($_SERVER['PHP_SELF'], '.php')), $tpl_main);
// END SUBST - <pun_page>


// START SUBST - <pun_title>
$tpl_main = str_replace('<pun_title>', '<h1><span>'.pun_htmlspecialchars($pun_config['o_board_title']).'</span></h1>', $tpl_main);
// END SUBST - <pun_title>


// START SUBST - <pun_desc>
$tpl_main = str_replace('<pun_desc>', '<p><span>'.$pun_config['o_board_desc'].'</span></p>', $tpl_main);
// END SUBST - <pun_desc>


// START SUBST - <pun_navlinks>
$tpl_main = str_replace('<pun_navlinks>','<div id="brdmenu" class="inbox">'."\n\t\t\t". generate_navlinks()."\n\t\t".'</div>', $tpl_main);
// END SUBST - <pun_navlinks>


// START SUBST - <pun_status>
if ($pun_user['is_guest'])
	$tpl_temp = '<div id="brdwelcome" class="inbox">'."\n\t\t\t".'<p>'.$lang_common['Not logged in'].'</p>'."\n\t\t".'</div>';
else
{
	$tpl_temp = '<div id="brdwelcome" class="inbox">'."\n\t\t\t".'<ul class="conl">'."\n\t\t\t\t".'<li>'.$lang_common['Logged in as'].' <strong>'.pun_htmlspecialchars($pun_user['username']).'</strong></li>'."\n\t\t\t\t".'<li>'.$lang_common['Last visit'].': '.format_time($pun_user['last_visit']).'</li>';

	if ($pun_user['g_id'] < PUN_GUEST)
	{
		$result_header = $db->query('SELECT COUNT(id) FROM '.$db->prefix.'reports WHERE zapped IS NULL') or error('Unable to fetch reports info', __FILE__, __LINE__, $db->error());

		if ($db->result($result_header))
			$tpl_temp .= "\n\t\t\t\t".'<li class="reportlink"><strong><a href="admin_reports.php">There are new reports</a></strong></li>';

		if ($pun_config['o_maintenance'] == '1')
			$tpl_temp .= "\n\t\t\t\t".'<li class="maintenancelink"><strong><a href="admin_options.php#maintenance">Maintenance mode is enabled!</a></strong></li>';
	}

	if (in_array(basename($_SERVER['PHP_SELF']), array('index.php', 'search.php')))
		$tpl_temp .= "\n\t\t\t".'</ul>'."\n\t\t\t".'<ul class="conr">'."\n\t\t\t\t".'<li><a href="search.php?action=show_new">'.$lang_common['Show new posts'].'</a></li>'."\n\t\t\t\t".'<li><a href="misc.php?action=markread">'.$lang_common['Mark all as read'].'</a></li>'."\n\t\t\t".'</ul>'."\n\t\t\t".'<div class="clearer"></div>'."\n\t\t".'</div>';
	else
		$tpl_temp .= "\n\t\t\t".'</ul>'."\n\t\t\t".'<div class="clearer"></div>'."\n\t\t".'</div>';
}

$tpl_main = str_replace('<pun_status>', $tpl_temp, $tpl_main);
// END SUBST - <pun_status>


// START SUBST - <pun_announcement>
if ($pun_config['o_announcement'] == '1')
{
	ob_start();

?>
<div id="announce" class="block">
	<h2><span><?php echo $lang_common['Announcement'] ?></span></h2>
	<div class="box">
		<div class="inbox">
			<div><?php echo $pun_config['o_announcement_message'] ?></div>
		</div>
	</div>
</div>
<?php

	$tpl_temp = trim(ob_get_contents());
	$tpl_main = str_replace('<pun_announcement>', $tpl_temp, $tpl_main);
	ob_end_clean();
}
else
	$tpl_main = str_replace('<pun_announcement>', '', $tpl_main);
// END SUBST - <pun_announcement>


// START SUBST - <pun_main>
ob_start();


define('PUN_HEADER', 1);
