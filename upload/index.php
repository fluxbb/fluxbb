<?php
/***********************************************************************

  Copyright (C) 2008  FluxBB.org

  Based on code copyright (C) 2002-2008  PunBB.org

  This file is part of FluxBB.

  FluxBB is free software; you can redistribute it and/or modify it
  under the terms of the GNU General Public License as published
  by the Free Software Foundation; either version 2 of the License,
  or (at your option) any later version.

  FluxBB is distributed in the hope that it will be useful, but
  WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston,
  MA  02111-1307  USA

************************************************************************/


if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', './');
require FORUM_ROOT.'include/common.php';

($hook = get_hook('in_start')) ? eval($hook) : null;

if ($forum_user['g_read_board'] == '0')
	message($lang_common['No view']);

// Load the index.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/index.php';


// Get list of forums and topics with new posts since last visit
if (!$forum_user['is_guest'])
{
	$query = array(
		'SELECT'	=> 't.forum_id, t.id, t.last_post',
		'FROM'		=> 'topics AS t',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'forums AS f',
				'ON'			=> 'f.id=t.forum_id'
			),
			array(
				'LEFT JOIN'		=> 'forum_perms AS fp',
				'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
			)
		),
		'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND t.last_post>'.$forum_user['last_visit'].' AND t.moved_to IS NULL'
	);

	($hook = get_hook('in_qr_get_new_topics')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$new_topics = array();
	while ($cur_topic = $forum_db->fetch_assoc($result))
		$new_topics[$cur_topic['forum_id']][$cur_topic['id']] = $cur_topic['last_post'];

	$tracked_topics = get_tracked_topics();
}

($hook = get_hook('in_pre_header_load')) ? eval($hook) : null;

define('FORUM_ALLOW_INDEX', 1);
define('FORUM_PAGE', 'index');
require FORUM_ROOT.'header.php';

// Print the categories and forums
$query = array(
	'SELECT'	=> 'c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.forum_desc, f.redirect_url, f.moderators, f.num_topics, f.num_posts, f.last_post, f.last_post_id, f.last_poster',
	'FROM'		=> 'categories AS c',
	'JOINS'		=> array(
		array(
			'INNER JOIN'	=> 'forums AS f',
			'ON'			=> 'c.id=f.cat_id'
		),
		array(
			'LEFT JOIN'		=> 'forum_perms AS fp',
			'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
		)
	),
	'WHERE'		=> 'fp.read_forum IS NULL OR fp.read_forum=1',
	'ORDER BY'	=> 'c.disp_position, c.id, f.disp_position'
);

($hook = get_hook('in_qr_get_cats_and_forums')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

?>
<div id="brd-main" class="main">

	<h1><span><?php echo forum_htmlencode($forum_config['o_board_title']) ?></span></h1>
<?php

$forum_page['cur_category'] = $forum_page['cat_count'] = $forum_page['item_count'] = 0;

while ($cur_forum = $forum_db->fetch_assoc($result))
{
	($hook = get_hook('in_forum_loop_start')) ? eval($hook) : null;

	$forum_page['item_mods'] = '';
	++$forum_page['item_count'];

	if ($cur_forum['cid'] != $forum_page['cur_category'])	// A new category since last iteration?
	{
		if ($forum_page['cur_category'] != 0)
		{

?>
			</tbody>
		</table>
	</div>
<?php

		}

		++$forum_page['cat_count'];
		$forum_page['item_count'] = 1;

?>
	<div class="main-head">
		<h2><span><?php echo forum_htmlencode($cur_forum['cat_name']) ?></span></h2>
	</div>

	<div id="category<?php echo $forum_page['cat_count'] ?>" class="main-content category">
		<table cellspacing="0" summary="<?php printf($lang_index['Table summary'], forum_htmlencode($cur_forum['cat_name'])) ?>">
			<thead>
				<tr>
<?php ($hook = get_hook('in_table_header_begin')) ? eval($hook) : null; ?>
					<th class="tcl" scope="col"><?php echo $lang_common['Forum'] ?></th>
					<th class="tc2" scope="col"><?php echo $lang_common['Topics'] ?></th>
					<th class="tc3" scope="col"><?php echo $lang_common['Posts'] ?></th>
<?php ($hook = get_hook('in_table_header_after_num_posts')) ? eval($hook) : null; ?>
					<th class="tcr" scope="col"><?php echo $lang_common['Last post'] ?></th>
<?php ($hook = get_hook('in_table_header_after_last_post')) ? eval($hook) : null; ?>
				</tr>
			</thead>
			<tbody class="statused">
<?php

		$forum_page['cur_category'] = $cur_forum['cid'];
	}

	$forum_page['item_status'] = $forum_page['item_subject'] = $forum_page['item_last_post'] = array();
	$forum_page['item_alt_message'] = $lang_common['Forum'];
	$forum_page['item_indicator'] = '';

	// Is this a redirect forum?
	if ($cur_forum['redirect_url'] != '')
	{
		$forum_page['item_title'] = '<h3><a class="external" href="'.forum_htmlencode($cur_forum['redirect_url']).'" title="'.sprintf($lang_index['Link to'], forum_htmlencode($cur_forum['redirect_url'])).'"><span>'.forum_htmlencode($cur_forum['forum_name']).'</span></a></h3>';
		$cur_forum['num_topics'] = $cur_forum['num_posts'] = ' - ';
		$forum_page['item_status'][] = 'redirect';
		$forum_page['item_alt_message'] = $lang_index['External forum'];
		$forum_page['item_last_post'][] = $lang_common['Unknown'];

		if ($cur_forum['forum_desc'] != '')
			$forum_page['item_subject'][] = $cur_forum['forum_desc'];
	}
	else
	{
		$forum_page['item_title'] = '<h3><a href="'.forum_link($forum_url['forum'], array($cur_forum['fid'], sef_friendly($cur_forum['forum_name']))).'"><span>'.forum_htmlencode($cur_forum['forum_name']).'</span></a></h3>';

		// Are there new posts since our last visit?
		if (!$forum_user['is_guest'] && $cur_forum['last_post'] > $forum_user['last_visit'] && (empty($tracked_topics['forums'][$cur_forum['fid']]) || $cur_forum['last_post'] > $tracked_topics['forums'][$cur_forum['fid']]))
		{
			// There are new posts in this forum, but have we read all of them already?
			while (list($check_topic_id, $check_last_post) = @each($new_topics[$cur_forum['fid']]))
			{
				if (empty($tracked_topics['topics'][$check_topic_id]) || $tracked_topics['topics'][$check_topic_id] < $check_last_post)
				{
					$forum_page['item_status'][] = 'new';
					$forum_page['item_alt_message'] = $lang_index['Forum has new'];
					break;
				}
			}
		}

		if ($cur_forum['forum_desc'] != '')
			$forum_page['item_subject'][] = $cur_forum['forum_desc'];

		if ($cur_forum['moderators'] != '')
		{
			$forum_page['mods_array'] = unserialize($cur_forum['moderators']);
			$forum_page['item_mods'] = array();

			while (list($mod_username, $mod_id) = @each($forum_page['mods_array']))
				$forum_page['item_mods'][] = ($forum_user['g_view_users'] == '1') ? '<a href="'.forum_link($forum_url['user'], $mod_id).'">'.forum_htmlencode($mod_username).'</a>' : forum_htmlencode($mod_username);

			$forum_page['item_subject'][] = '<span class="modlist">('.sprintf($lang_index['Moderated by'], implode(', ', $forum_page['item_mods'])).')</span>';
		}

		// If there is a last_post/last_poster.
		if ($cur_forum['last_post'] != '')
		{
			$forum_page['item_last_post'][] = '<a href="'.forum_link($forum_url['post'], $cur_forum['last_post_id']).'"><span>'.format_time($cur_forum['last_post']).'</span></a>';
			$forum_page['item_last_post'][] =	'<span class="byuser">'.sprintf($lang_common['By user'], forum_htmlencode($cur_forum['last_poster'])).'</span>';
		}
		else
			$forum_page['item_last_post'][] = $lang_common['Never'];

		if (empty($forum_page['item_status']))
			$forum_page['item_status'][] = 'normal';
	}

	$forum_page['item_style'] = (($forum_page['item_count'] % 2 != 0) ? 'odd' : 'even').' '.implode(' ', $forum_page['item_status']);
	$forum_page['item_indicator'] = '<span class="status '.implode(' ', $forum_page['item_status']).'" title="'.$forum_page['item_alt_message'].'"><img src="'.$base_url.'/style/'.$forum_user['style'].'/status.png" alt="'.$forum_page['item_alt_message'].'" /></span>';

	($hook = get_hook('in_row_pre_display')) ? eval($hook) : null;

?>
				<tr id="forum<?php echo $cur_forum['fid'] ?>" class="<?php echo $forum_page['item_style'] ?>">
<?php ($hook = get_hook('in_table_contents_begin')) ? eval($hook) : null; ?>
					<td class="tcl"><?php echo $forum_page['item_indicator'].' '.$forum_page['item_title'].implode('<br />', $forum_page['item_subject']) ?></td>
					<td class="tc2"><?php echo $cur_forum['num_topics'] ?></td>
					<td class="tc3"><?php echo $cur_forum['num_posts'] ?></td>
<?php ($hook = get_hook('in_table_contents_after_num_posts')) ? eval($hook) : null; ?>
					<td class="tcr"><?php if (!empty($forum_page['item_last_post'])) echo implode(' ', $forum_page['item_last_post']) ?></td>
<?php ($hook = get_hook('in_table_contents_after_last_post')) ? eval($hook) : null; ?>
				</tr>
<?php

}

// Did we output any categories and forums?
if ($forum_page['cur_category'] > 0)
{

?>
			</tbody>
		</table>
	</div>

</div>
<?php

}
else
{

?>
<div id="brd-main" class="main">

	<h1><span><?php echo forum_htmlencode($forum_config['o_board_title']) ?></span></h1>

	<div class="main-head">
		<h2><span><?php echo $lang_common['Forum message'] ?></span></h2>
	</div>
	<div class="main-content message">
		<p><?php echo $lang_index['Empty board'] ?></p>
	</div>

</div>
<?php

}

($hook = get_hook('in_end')) ? eval($hook) : null;

require FORUM_ROOT.'footer.php';
