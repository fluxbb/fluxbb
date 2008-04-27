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


define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';


if ($pun_user['g_read_board'] == '0')
	message($lang_common['No view']);


// Load the index.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/index.php';

$page_title = pun_htmlspecialchars($pun_config['o_board_title']);
define('PUN_ALLOW_INDEX', 1);
require PUN_ROOT.'header.php';

// Print the categories and forums
$result = $db->query('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.forum_desc, f.redirect_url, f.moderators, f.num_topics, f.num_posts, f.last_post, f.last_post_id, f.last_poster FROM '.$db->prefix.'categories AS c INNER JOIN '.$db->prefix.'forums AS f ON c.id=f.cat_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE fp.read_forum IS NULL OR fp.read_forum=1 ORDER BY c.disp_position, c.id, f.disp_position', true) or error('Unable to fetch category/forum list', __FILE__, __LINE__, $db->error());

$cur_category = 0;
$cat_count = 0;
while ($cur_forum = $db->fetch_assoc($result))
{
	$moderators = '';

	if ($cur_forum['cid'] != $cur_category)	// A new category since last iteration?
	{
		if ($cur_category != 0)
			echo "\t\t\t".'</tbody>'."\n\t\t\t".'</table>'."\n\t\t".'</div>'."\n\t".'</div>'."\n".'</div>'."\n\n";

		++$cat_count;

?>
<div id="idx<?php echo $cat_count ?>" class="blocktable">
	<h2><span><?php echo pun_htmlspecialchars($cur_forum['cat_name']) ?></span></h2>
	<div class="box">
		<div class="inbox">
			<table cellspacing="0">
			<thead>
				<tr>
					<th class="tcl" scope="col"><?php echo $lang_common['Forum'] ?></th>
					<th class="tc2" scope="col"><?php echo $lang_index['Topics'] ?></th>
					<th class="tc3" scope="col"><?php echo $lang_common['Posts'] ?></th>
					<th class="tcr" scope="col"><?php echo $lang_common['Last post'] ?></th>
				</tr>
			</thead>
			<tbody>
<?php

		$cur_category = $cur_forum['cid'];
	}

	$item_status = '';
	$icon_text = $lang_common['Normal icon'];
	$icon_type = 'icon';

	// Are there new posts?
	if (!$pun_user['is_guest'] && $cur_forum['last_post'] > $pun_user['last_visit'])
	{
		$item_status = 'inew';
		$icon_text = $lang_common['New icon'];
		$icon_type = 'icon inew';
	}

	// Is this a redirect forum?
	if ($cur_forum['redirect_url'] != '')
	{
		$forum_field = '<h3><a href="'.pun_htmlspecialchars($cur_forum['redirect_url']).'" title="'.$lang_index['Link to'].' '.pun_htmlspecialchars($cur_forum['redirect_url']).'">'.pun_htmlspecialchars($cur_forum['forum_name']).'</a></h3>';
		$num_topics = $num_posts = '&nbsp;';
		$item_status = 'iredirect';
		$icon_text = $lang_common['Redirect icon'];
		$icon_type = 'icon';
	}
	else
	{
		$forum_field = '<h3><a href="viewforum.php?id='.$cur_forum['fid'].'">'.pun_htmlspecialchars($cur_forum['forum_name']).'</a></h3>';
		$num_topics = $cur_forum['num_topics'];
		$num_posts = $cur_forum['num_posts'];
	}

	if ($cur_forum['forum_desc'] != '')
		$forum_field .= "\n\t\t\t\t\t\t\t\t".$cur_forum['forum_desc'];


	// If there is a last_post/last_poster.
	if ($cur_forum['last_post'] != '')
		$last_post = '<a href="viewtopic.php?pid='.$cur_forum['last_post_id'].'#p'.$cur_forum['last_post_id'].'">'.format_time($cur_forum['last_post']).'</a> <span class="byuser">'.$lang_common['by'].' '.pun_htmlspecialchars($cur_forum['last_poster']).'</span>';
	else
		$last_post = '&nbsp;';

	if ($cur_forum['moderators'] != '')
	{
		$mods_array = unserialize($cur_forum['moderators']);
		$moderators = array();

		while (list($mod_username, $mod_id) = @each($mods_array))
			$moderators[] = '<a href="profile.php?id='.$mod_id.'">'.pun_htmlspecialchars($mod_username).'</a>';

		$moderators = "\t\t\t\t\t\t\t\t".'<p><em>('.$lang_common['Moderated by'].'</em> '.implode(', ', $moderators).')</p>'."\n";
	}

?>
 				<tr<?php if ($item_status != '') echo ' class="'.$item_status.'"'; ?>>
					<td class="tcl">
						<div class="intd">
							<div class="<?php echo $icon_type ?>"><div class="nosize"><?php echo $icon_text ?></div></div>
							<div class="tclcon">
								<?php echo $forum_field."\n".$moderators ?>
							</div>
						</div>
					</td>
					<td class="tc2"><?php echo $num_topics ?></td>
					<td class="tc3"><?php echo $num_posts ?></td>
					<td class="tcr"><?php echo $last_post ?></td>
				</tr>
<?php

}

// Did we output any categories and forums?
if ($cur_category > 0)
	echo "\t\t\t".'</tbody>'."\n\t\t\t".'</table>'."\n\t\t".'</div>'."\n\t".'</div>'."\n".'</div>'."\n\n";
else
	echo '<div id="idx0" class="block"><div class="box"><div class="inbox"><p>'.$lang_index['Empty board'].'</p></div></div></div>';


// Collect some statistics from the database
$result = $db->query('SELECT COUNT(id)-1 FROM '.$db->prefix.'users') or error('Unable to fetch total user count', __FILE__, __LINE__, $db->error());
$stats['total_users'] = $db->result($result);

$result = $db->query('SELECT id, username FROM '.$db->prefix.'users ORDER BY registered DESC LIMIT 1') or error('Unable to fetch newest registered user', __FILE__, __LINE__, $db->error());
$stats['last_user'] = $db->fetch_assoc($result);

$result = $db->query('SELECT SUM(num_topics), SUM(num_posts) FROM '.$db->prefix.'forums') or error('Unable to fetch topic/post count', __FILE__, __LINE__, $db->error());
list($stats['total_topics'], $stats['total_posts']) = $db->fetch_row($result);

?>
<div id="brdstats" class="block">
	<h2><span><?php echo $lang_index['Board info'] ?></span></h2>
	<div class="box">
		<div class="inbox">
			<dl class="conr">
				<dt><strong><?php echo $lang_index['Board stats'] ?></strong></dt>
				<dd><?php echo $lang_index['No of users'].': <strong>'. $stats['total_users'] ?></strong></dd>
				<dd><?php echo $lang_index['No of topics'].': <strong>'.$stats['total_topics'] ?></strong></dd>
				<dd><?php echo $lang_index['No of posts'].': <strong>'.$stats['total_posts'] ?></strong></dd>
			</dl>
			<dl class="conl">
				<dt><strong><?php echo $lang_index['User info'] ?></strong></dt>
				<dd><?php echo $lang_index['Newest user'] ?>: <a href="profile.php?id=<?php echo $stats['last_user']['id'] ?>"><?php echo pun_htmlspecialchars($stats['last_user']['username']) ?></a></dd>
<?php

if ($pun_config['o_users_online'] == '1')
{
	// Fetch users online info and generate strings for output
	$num_guests = 0;
	$users = array();
	$result = $db->query('SELECT user_id, ident FROM '.$db->prefix.'online WHERE idle=0 ORDER BY ident', true) or error('Unable to fetch online list', __FILE__, __LINE__, $db->error());

	while ($pun_user_online = $db->fetch_assoc($result))
	{
		if ($pun_user_online['user_id'] > 1)
			$users[] = "\n\t\t\t\t".'<dd><a href="profile.php?id='.$pun_user_online['user_id'].'">'.pun_htmlspecialchars($pun_user_online['ident']).'</a>';
		else
			++$num_guests;
	}

	$num_users = count($users);
	echo "\t\t\t\t".'<dd>'. $lang_index['Users online'].': <strong>'.$num_users.'</strong></dd>'."\n\t\t\t\t".'<dd>'.$lang_index['Guests online'].': <strong>'.$num_guests.'</strong></dd>'."\n\t\t\t".'</dl>'."\n";


	if ($num_users > 0)
		echo "\t\t\t".'<dl id="onlinelist" class= "clearb">'."\n\t\t\t\t".'<dt><strong>'.$lang_index['Online'].':&nbsp;</strong></dt>'."\t\t\t\t".implode(',</dd> ', $users).'</dd>'."\n\t\t\t".'</dl>'."\n";
	else
		echo "\t\t\t".'<div class="clearer"></div>'."\n";

}
else
	echo "\t\t".'</dl>'."\n\t\t\t".'<div class="clearer"></div>'."\n";


?>
		</div>
	</div>
</div>
<?php

$footer_style = 'index';
require PUN_ROOT.'footer.php';
