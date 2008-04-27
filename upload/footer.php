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

$tpl_temp = trim(ob_get_contents());
$tpl_main = str_replace('<pun_main>', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <pun_main>


// START SUBST - <pun_footer>
ob_start();

?>
<div id="brdfooter" class="block">
	<h2><span><?php echo $lang_common['Board footer'] ?></span></h2>
	<div class="box">
		<div class="inbox">
<?php

// If no footer style has been specified, we use the default (only copyright/debug info)
$footer_style = isset($footer_style) ? $footer_style : NULL;

if ($footer_style == 'index' || $footer_style == 'search')
{
	if (!$pun_user['is_guest'])
	{
		echo "\n\t\t\t".'<dl id="searchlinks" class="conl">'."\n\t\t\t\t".'<dt><strong>'.$lang_common['Search links'].'</strong></dt>'."\n\t\t\t\t".'<dd><a href="search.php?action=show_24h">'.$lang_common['Show recent posts'].'</a></dd>'."\n";
		echo "\t\t\t\t".'<dd><a href="search.php?action=show_unanswered">'.$lang_common['Show unanswered posts'].'</a></dd>'."\n";

		if ($pun_config['o_subscriptions'] == '1')
			echo "\t\t\t\t".'<dd><a href="search.php?action=show_subscriptions">'.$lang_common['Show subscriptions'].'</a></dd>'."\n";

		echo "\t\t\t\t".'<dd><a href="search.php?action=show_user&amp;user_id='.$pun_user['id'].'">'.$lang_common['Show your posts'].'</a></dd>'."\n\t\t\t".'</dl>'."\n";
	}
	else
	{
		if ($pun_user['g_search'] == '1')
		{
			echo "\n\t\t\t".'<dl id="searchlinks" class="conl">'."\n\t\t\t\t".'<dt><strong>'.$lang_common['Search links'].'</strong></dt><dd><a href="search.php?action=show_24h">'.$lang_common['Show recent posts'].'</a></dd>'."\n";
			echo "\t\t\t\t".'<dd><a href="search.php?action=show_unanswered">'.$lang_common['Show unanswered posts'].'</a></dd>'."\n\t\t\t".'</dl>'."\n";
		}
	}
}
else if ($footer_style == 'viewforum' || $footer_style == 'viewtopic')
{
	echo "\n\t\t\t".'<div class="conl">'."\n";

	// Display the "Jump to" drop list
	if ($pun_config['o_quickjump'] == '1')
	{
		// Load cached quickjump
		@include PUN_ROOT.'cache/cache_quickjump_'.$pun_user['g_id'].'.php';
		if (!defined('PUN_QJ_LOADED'))
		{
			require_once PUN_ROOT.'include/cache.php';
			generate_quickjump_cache($pun_user['g_id']);
			require PUN_ROOT.'cache/cache_quickjump_'.$pun_user['g_id'].'.php';
		}
	}

	if ($footer_style == 'viewforum' && $is_admmod)
		echo "\t\t\t".'<p id="modcontrols"><a href="moderate.php?fid='.$forum_id.'&amp;p='.$p.'">'.$lang_common['Moderate forum'].'</a></p>'."\n";
	else if ($footer_style == 'viewtopic' && $is_admmod)
	{
		echo "\t\t\t".'<dl id="modcontrols"><dt><strong>'.$lang_topic['Mod controls'].'</strong></dt><dd><a href="moderate.php?fid='.$forum_id.'&amp;tid='.$id.'&amp;p='.$p.'">'.$lang_common['Delete posts'].'</a></dd>'."\n";
		echo "\t\t\t".'<dd><a href="moderate.php?fid='.$forum_id.'&amp;move_topics='.$id.'">'.$lang_common['Move topic'].'</a></dd>'."\n";

		if ($cur_topic['closed'] == '1')
			echo "\t\t\t".'<dd><a href="moderate.php?fid='.$forum_id.'&amp;open='.$id.'">'.$lang_common['Open topic'].'</a></dd>'."\n";
		else
			echo "\t\t\t".'<dd><a href="moderate.php?fid='.$forum_id.'&amp;close='.$id.'">'.$lang_common['Close topic'].'</a></dd>'."\n";

		if ($cur_topic['sticky'] == '1')
			echo "\t\t\t".'<dd><a href="moderate.php?fid='.$forum_id.'&amp;unstick='.$id.'">'.$lang_common['Unstick topic'].'</a></dd></dl>'."\n";
		else
			echo "\t\t\t".'<dd><a href="moderate.php?fid='.$forum_id.'&amp;stick='.$id.'">'.$lang_common['Stick topic'].'</a></dd></dl>'."\n";
	}

	echo "\t\t\t".'</div>'."\n";
}

?>
			<p class="conr">Powered by <a href="http://www.punbb.org/">PunBB</a><?php if ($pun_config['o_show_version'] == '1') echo ' '.$pun_config['o_cur_version']; ?><br />&copy; Copyright 2002&#8211;2005 Rickard Andersson</p>
<?php

// Display debug info (if enabled/defined)
if (defined('PUN_DEBUG'))
{
	// Calculate script generation time
	list($usec, $sec) = explode(' ', microtime());
	$time_diff = sprintf('%.3f', ((float)$usec + (float)$sec) - $pun_start);
	echo "\t\t\t".'<p class="conr">[ Generated in '.$time_diff.' seconds, '.$db->get_num_queries().' queries executed ]</p>'."\n";
}

?>
			<div class="clearer"></div>
		</div>
	</div>
</div>
<?php


// End the transaction
$db->end_transaction();

// Display executed queries (if enabled)
if (defined('PUN_SHOW_QUERIES'))
	display_saved_queries();

$tpl_temp = trim(ob_get_contents());
$tpl_main = str_replace('<pun_footer>', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <pun_footer>


// Close the db connection (and free up any result data)
$db->close();

// Spit out the page
exit($tpl_main);
