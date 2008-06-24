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

// Setup headers
$forum_page['main_head'] = forum_htmlencode($forum_config['o_board_title']);

// Setup main options
$forum_page['main_options'] = array();
if (!$forum_user['is_guest'] || ($forum_user['g_read_board'] == '1' && $forum_user['g_search'] == '1'))
{
	$forum_page['main_options']['recent'] = '<span'.(empty($forum_page['main_options']) ? ' class="item1"' : '').'><a href="'.forum_link($forum_url['search_24h']).'">'.$lang_index['View recent'].'</a></span>';
	$forum_page['main_options']['unanswered'] = '<span'.(empty($forum_page['main_options']) ? ' class="item1"' : '').'><a href="'.forum_link($forum_url['search_unanswered']).'">'.$lang_index['View unanswered'].'</a></span>';
}

if (!$forum_user['is_guest'])
	$forum_page['main_options']['markread'] = '<span'.(empty($forum_page['main_options']) ? ' class="item1"' : '').'><a href="'.forum_link($forum_url['mark_read'], generate_form_token('markread'.$forum_user['id'])).'">'.$lang_index['Mark all as read'].'</a></span>';

($hook = get_hook('in_pre_header_load')) ? eval($hook) : null;

define('FORUM_ALLOW_INDEX', 1);
define('FORUM_PAGE', 'index');
define('FORUM_PAGE_TYPE', 'index');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('in_main_output_start')) ? eval($hook) : null;

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
		$cur_forum['cat_head'] = forum_htmlencode($cur_forum['cat_name']);

		($hook = get_hook('in_forum_pre_cat_head')) ? eval($hook) : null;

?>
<div id="category<?php echo $forum_page['cat_count'] ?>" class="main-content category" style="margin-top: 4px">
	<div class="content-head">
		<h2 class="hn"><span><?php echo $cur_forum['cat_head'] ?></span></h2>
	</div>
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
		$forum_page['item_status']['redirect'] = 'redirect';
		$forum_page['item_alt_message'] = $lang_index['External forum'];
		$forum_page['item_last_post']['redirect'] = $lang_common['Unknown'];

		if ($cur_forum['forum_desc'] != '')
			$forum_page['item_subject']['redirect'] = $cur_forum['forum_desc'];
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
				if ((empty($tracked_topics['topics'][$check_topic_id]) || $tracked_topics['topics'][$check_topic_id] < $check_last_post) && (empty($tracked_topics['forums'][$cur_forum['fid']]) || $tracked_topics['forums'][$cur_forum['fid']] < $check_last_post))
				{
					$forum_page['item_status']['new'] = 'new';
					$forum_page['item_alt_message'] = $lang_index['Forum has new'];
					break;
				}
			}
		}

		if ($cur_forum['forum_desc'] != '')
			$forum_page['item_subject']['desc'] = $cur_forum['forum_desc'];

		if ($cur_forum['moderators'] != '')
		{
			$forum_page['mods_array'] = unserialize($cur_forum['moderators']);
			$forum_page['item_mods'] = array();

			while (list($mod_username, $mod_id) = @each($forum_page['mods_array']))
				$forum_page['item_mods'][] = ($forum_user['g_view_users'] == '1') ? '<a href="'.forum_link($forum_url['user'], $mod_id).'">'.forum_htmlencode($mod_username).'</a>' : forum_htmlencode($mod_username);

			($hook = get_hook('in_row_modify_modlist')) ? eval($hook) : null;

			$forum_page['item_subject']['modlist'] = '<span class="modlist">('.sprintf($lang_index['Moderated by'], implode(', ', $forum_page['item_mods'])).')</span>';
		}

		// If there is a last_post/last_poster.
		if ($cur_forum['last_post'] != '')
		{
			$forum_page['item_last_post']['post'] = '<a href="'.forum_link($forum_url['post'], $cur_forum['last_post_id']).'"><span>'.format_time($cur_forum['last_post']).'</span></a>';
			$forum_page['item_last_post']['poster'] =	'<span class="byuser">'.sprintf($lang_common['By user'], forum_htmlencode($cur_forum['last_poster'])).'</span>';
		}
		else
			$forum_page['item_last_post']['never'] = $lang_common['Never'];

		if (empty($forum_page['item_status']))
			$forum_page['item_status']['normal'] = 'normal';
	}

	($hook = get_hook('in_row_pre_item_merge')) ? eval($hook) : null;

	$forum_page['item_style'] = (($forum_page['item_count'] % 2 != 0) ? 'odd' : 'even').' '.implode(' ', $forum_page['item_status']);
	if ($forum_page['item_count'] == 1)
		$forum_page['item_style'] .= ' row1';

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
<?php

}
else
{

?>
<div class="main-content message">
	<p><?php echo $lang_index['Empty board'] ?></p>
</div>
<?php

}

($hook = get_hook('in_end')) ? eval($hook) : null;

$tpl_temp = trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->


// START SUBST - <!-- forum_stats -->
ob_start();

($hook = get_hook('in_stats_output_start')) ? eval($hook) : null;

// Collect some statistics from the database
$query = array(
	'SELECT'	=> 'COUNT(u.id)-1',
	'FROM'		=> 'users AS u'
);

($hook = get_hook('in_qr_get_user_count')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
$stats['total_users'] = $forum_db->result($result);

$query = array(
	'SELECT'	=> 'u.id, u.username',
	'FROM'		=> 'users AS u',
	'WHERE'		=> 'u.group_id != '.FORUM_UNVERIFIED,
	'ORDER BY'	=> 'u.registered DESC',
	'LIMIT'		=> '1'
);

($hook = get_hook('in_qr_get_newest_user')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
$stats['last_user'] = $forum_db->fetch_assoc($result);

$query = array(
	'SELECT'	=> 'SUM(f.num_topics), SUM(f.num_posts)',
	'FROM'		=> 'forums AS f'
);

($hook = get_hook('in_qr_get_post_stats')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
list($stats['total_topics'], $stats['total_posts']) = $forum_db->fetch_row($result);

$stats_list['no_of_users'] = '<li class="st-users"><span>'.$lang_index['No of users'].':</span> <strong>'. $stats['total_users'].'</strong></li>';
$stats_list['newest_user'] = '<li class="st-users"><span>'.$lang_index['Newest user'].':</span> <strong>'.($forum_user['g_view_users'] == '1' ? '<a href="'.forum_link($forum_url['user'], $stats['last_user']['id']).'">'.forum_htmlencode($stats['last_user']['username']).'</a>' : forum_htmlencode($stats['last_user']['username'])).'</strong></li>';
$stats_list['no_of_topics'] = '<li class="st-activity"><span>'.$lang_index['No of topics'].':</span> <strong>'.intval($stats['total_topics']).'</strong></li>';
$stats_list['no_of_posts'] = '<li class="st-activity"><span>'.$lang_index['No of posts'].':</span> <strong>'.intval($stats['total_posts']).'</strong></li>';

($hook = get_hook('in_pre_stats_info_output')) ? eval($hook) : null;

?>
<div id="brd-stats" class="gen-content">
	<h2 class="hn"><span><?php echo $lang_index['Statistics'] ?></span></h2>
	<ul>
		<?php echo implode("\n\t\t", $stats_list)."\n" ?>
	</ul>
</div>
<?php

($hook = get_hook('in_stats_end')) ? eval($hook) : null;

$tpl_temp = trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_stats -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_stats -->


// START SUBST - <!-- forum_online -->
ob_start();

($hook = get_hook('in_pre_users_online')) ? eval($hook) : null;

if ($forum_config['o_users_online'] == '1')
{
	// Fetch users online info and generate strings for output
	$query = array(
		'SELECT'	=> 'o.user_id, o.ident',
		'FROM'		=> 'online AS o',
		'WHERE'		=> 'o.idle=0',
		'ORDER BY'	=> 'o.ident'
	);

	($hook = get_hook('in_qr_get_online_info')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$num_guests = 0;
	$users = array();

	while ($forum_user_online = $forum_db->fetch_assoc($result))
	{
		($hook = get_hook('in_add_online_user_loop')) ? eval($hook) : null;

		if ($forum_user_online['user_id'] > 1)
			$users[] = ($forum_user['g_view_users'] == '1') ? '<a href="'.forum_link($forum_url['user'], $forum_user_online['user_id']).'">'.forum_htmlencode($forum_user_online['ident']).'</a>' : forum_htmlencode($forum_user_online['ident']);
		else
			++$num_guests;
	}

	($hook = get_hook('in_pre_online_info_output')) ? eval($hook) : null;
?>
<div id="brd-online" class="gen-content">
	<h3 class="hn"><span><?php printf($lang_index['Online'], $num_guests, count($users)) ?></span></h3>
<?php echo (((count($users) > 0)) ? "\t".'<p>'.implode(', ', $users).'</p>' : '') ?>
</div>
<?php

}

($hook = get_hook('in_post_users_online')) ? eval($hook) : null;

($hook = get_hook('in_online_end')) ? eval($hook) : null;

$tpl_temp = trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_online -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_online -->

require FORUM_ROOT.'footer.php';
