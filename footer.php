<?php

/**
 * Copyright (C) 2008-2011 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

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
	<h2><span><?php echo $lang->t('Board footer') ?></span></h2>
	<div class="box">
<?php

if (isset($footer_style) && ($footer_style == 'viewforum' || $footer_style == 'viewtopic') && $is_admmod)
{
	echo "\t\t".'<div id="modcontrols" class="inbox">'."\n";

	if ($footer_style == 'viewforum')
	{
		echo "\t\t\t".'<dl>'."\n";
		echo "\t\t\t\t".'<dt><strong>'.$lang_forum['Mod controls'].'</strong></dt>'."\n";
		echo "\t\t\t\t".'<dd><span><a href="moderate.php?fid='.$forum_id.'&amp;p='.$p.'">'.$lang->t('Moderate forum').'</a></span></dd>'."\n";
		echo "\t\t\t".'</dl>'."\n";
	}
	else if ($footer_style == 'viewtopic')
	{
		echo "\t\t\t".'<dl>'."\n";
		echo "\t\t\t\t".'<dt><strong>'.$lang_topic['Mod controls'].'</strong></dt>'."\n";
		echo "\t\t\t\t".'<dd><span><a href="moderate.php?fid='.$forum_id.'&amp;tid='.$id.'&amp;p='.$p.'">'.$lang->t('Moderate topic').'</a></span></dd>'."\n";
		echo "\t\t\t\t".'<dd><span><a href="moderate.php?fid='.$forum_id.'&amp;move_topics='.$id.'">'.$lang->t('Move topic').'</a></span></dd>'."\n";

		if ($cur_topic['closed'] == '1')
			echo "\t\t\t\t".'<dd><span><a href="moderate.php?fid='.$forum_id.'&amp;open='.$id.'">'.$lang->t('Open topic').'</a></span></dd>'."\n";
		else
			echo "\t\t\t\t".'<dd><span><a href="moderate.php?fid='.$forum_id.'&amp;close='.$id.'">'.$lang->t('Close topic').'</a></span></dd>'."\n";

		if ($cur_topic['sticky'] == '1')
			echo "\t\t\t\t".'<dd><span><a href="moderate.php?fid='.$forum_id.'&amp;unstick='.$id.'">'.$lang->t('Unstick topic').'</a></span></dd>'."\n";
		else
			echo "\t\t\t\t".'<dd><span><a href="moderate.php?fid='.$forum_id.'&amp;stick='.$id.'">'.$lang->t('Stick topic').'</a></span></dd>'."\n";

		echo "\t\t\t".'</dl>'."\n";
	}

	echo "\t\t\t".'<div class="clearer"></div>'."\n\t\t".'</div>'."\n";
}

?>
		<div id="brdfooternav" class="inbox">
<?php

echo "\t\t\t".'<div class="conl">'."\n";

// Display the "Jump to" drop list
if ($pun_config['o_quickjump'] == '1')
{
	$quickjump = $cache->get('quickjump');
	if ($quickjump === Cache::NOT_FOUND)
	{
		$quickjump = array();

		// Generate the quick jump cache for all groups
		$group_result = $db->query('SELECT g_id FROM '.$db->prefix.'groups WHERE g_read_board=1') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());
		while ($cur_group = $db->fetch_assoc($group_result))
		{
			$quickjump[$cur_group['g_id']] = array();

			$result = $db->query('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.redirect_url FROM '.$db->prefix.'categories AS c INNER JOIN '.$db->prefix.'forums AS f ON c.id=f.cat_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$cur_group['g_id'].') WHERE fp.read_forum IS NULL OR fp.read_forum=1 ORDER BY c.disp_position, c.id, f.disp_position') or error('Unable to fetch category/forum list', __FILE__, __LINE__, $db->error());
			while ($cur_forum = $db->fetch_assoc($result))
				$quickjump[$cur_group['g_id']][] = $cur_forum;
		}

		$cache->set('quickjump', $quickjump);
	}

	if (!empty($quickjump[$pun_user['g_id']]))
	{
?>
				<form id="qjump" method="get" action="viewforum.php">
					<div>
						<label>
							<span><?php echo $lang->t('Jump to') ?><br /></span>
							<select name="id" onchange="window.location=('viewforum.php?id='+this.options[this.selectedIndex].value)">
<?php

		$cur_category = 0;
		foreach ($quickjump[$pun_user['g_id']] as $cur_forum)
		{
			if ($cur_forum['cid'] != $cur_category) // A new category since last iteration?
			{
				if ($cur_category)
					echo "\t\t\t\t\t\t\t\t".'</optgroup>'."\n";

				echo "\t\t\t\t\t\t".'<optgroup label="'.pun_htmlspecialchars($cur_forum['cat_name']).'">'."\n";
				$cur_category = $cur_forum['cid'];
			}

			$redirect_tag = ($cur_forum['redirect_url'] != '') ? ' &gt;&gt;&gt;' : '';
			echo "\t\t\t\t\t\t\t".'<option value="'.$cur_forum['fid'].'"'. ($forum_id == $cur_forum['fid'] ? ' selected="selected"' : '').'>'.pun_htmlspecialchars($cur_forum['forum_name']).$redirect_tag.'</option>'."\n";
		}

?>
								</optgroup>
							</select>
							<input type="submit" value="<?php echo $lang->t('Go') ?>" accesskey="g" />
						</label>
					</div>
				</form>
<?php

	}
}

echo "\t\t\t".'</div>'."\n";

?>
			<div class="conr">
<?php

// If no footer style has been specified, we use the default (only copyright/debug info)
$footer_style = isset($footer_style) ? $footer_style : NULL;

if ($footer_style == 'index')
{
	if ($pun_config['o_feed_type'] == '1')
		echo "\t\t\t\t".'<p id="feedlinks"><span class="rss"><a href="extern.php?action=feed&amp;type=rss">'.$lang->t('RSS active topics feed').'</a></span></p>'."\n";
	else if ($pun_config['o_feed_type'] == '2')
		echo "\t\t\t\t".'<p id="feedlinks"><span class="atom"><a href="extern.php?action=feed&amp;type=atom">'.$lang->t('Atom active topics feed').'</a></span></p>'."\n";
}
else if ($footer_style == 'viewforum')
{
	if ($pun_config['o_feed_type'] == '1')
		echo "\t\t\t\t".'<p id="feedlinks"><span class="rss"><a href="extern.php?action=feed&amp;fid='.$forum_id.'&amp;type=rss">'.$lang->t('RSS forum feed').'</a></span></p>'."\n";
	else if ($pun_config['o_feed_type'] == '2')
		echo "\t\t\t\t".'<p id="feedlinks"><span class="atom"><a href="extern.php?action=feed&amp;fid='.$forum_id.'&amp;type=atom">'.$lang->t('Atom forum feed').'</a></span></p>'."\n";
}
else if ($footer_style == 'viewtopic')
{
	if ($pun_config['o_feed_type'] == '1')
		echo "\t\t\t\t".'<p id="feedlinks"><span class="rss"><a href="extern.php?action=feed&amp;tid='.$id.'&amp;type=rss">'.$lang->t('RSS topic feed').'</a></span></p>'."\n";
	else if ($pun_config['o_feed_type'] == '2')
		echo "\t\t\t\t".'<p id="feedlinks"><span class="atom"><a href="extern.php?action=feed&amp;tid='.$id.'&amp;type=atom">'.$lang->t('Atom topic feed').'</a></span></p>'."\n";
}

?>
				<p id="poweredby"><?php printf($lang->t('Powered by'), '<a href="http://fluxbb.org/">FluxBB</a>'.(($pun_config['o_show_version'] == '1') ? ' '.$pun_config['o_cur_version'] : '')) ?></p>
			</div>
			<div class="clearer"></div>
		</div>
	</div>
</div>
<?php

// Display debug info (if enabled/defined)
if (defined('PUN_DEBUG'))
{
	echo '<p id="debugtime">[ ';

	// Calculate script generation time
	$time_diff = sprintf('%.3f', get_microtime() - $pun_start);
	echo sprintf($lang->t('Querytime'), $time_diff, $db->get_num_queries());

	if (function_exists('memory_get_usage'))
	{
		echo ' - '.sprintf($lang->t('Memory usage'), file_size(memory_get_usage()));

		if (function_exists('memory_get_peak_usage'))
			echo ' '.sprintf($lang->t('Peak usage'), file_size(memory_get_peak_usage()));
	}

	echo ' ]</p>'."\n";
}


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
