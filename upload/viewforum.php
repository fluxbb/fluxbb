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

($hook = get_hook('vf_start')) ? eval($hook) : null;

if ($forum_user['g_read_board'] == '0')
	message($lang_common['No view']);

// Load the viewforum.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/forum.php';


$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id < 1)
	message($lang_common['Bad request']);


// Fetch some info about the forum
$query = array(
	'SELECT'	=> 'f.forum_name, f.redirect_url, f.moderators, f.num_topics, f.sort_by, fp.post_topics',
	'FROM'		=> 'forums AS f',
	'JOINS'		=> array(
		array(
			'LEFT JOIN'		=> 'forum_perms AS fp',
			'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
		)
	),
	'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND f.id='.$id
);

($hook = get_hook('vf_qr_get_forum_info')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
if (!$forum_db->num_rows($result))
	message($lang_common['Bad request']);

$cur_forum = $forum_db->fetch_assoc($result);

($hook = get_hook('vf_modify_forum_info')) ? eval($hook) : null;

// Is this a redirect forum? In that case, redirect!
if ($cur_forum['redirect_url'] != '')
{
	($hook = get_hook('vf_redirect_forum_pre_redirect')) ? eval($hook) : null;

	header('Location: '.$cur_forum['redirect_url']);
	exit;
}

// Sort out who the moderators are and if we are currently a moderator (or an admin)
$mods_array = ($cur_forum['moderators'] != '') ? unserialize($cur_forum['moderators']) : array();
$forum_page['is_admmod'] = ($forum_user['g_id'] == FORUM_ADMIN || ($forum_user['g_moderator'] == '1' && array_key_exists($forum_user['username'], $mods_array))) ? true : false;

// Sort out whether or not this user can post
$forum_user['may_post'] = (($cur_forum['post_topics'] == '' && $forum_user['g_post_topics'] == '1') || $cur_forum['post_topics'] == '1' || $forum_page['is_admmod']) ? true : false;

// Get topic/forum tracking data
if (!$forum_user['is_guest'])
	$tracked_topics = get_tracked_topics();

// Determine the topic offset (based on $_GET['p'])
$forum_page['num_pages'] = ceil($cur_forum['num_topics'] / $forum_user['disp_topics']);
$forum_page['page'] = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $forum_page['num_pages']) ? 1 : $_GET['p'];
$forum_page['start_from'] = $forum_user['disp_topics'] * ($forum_page['page'] - 1);
$forum_page['finish_at'] = min(($forum_page['start_from'] + $forum_user['disp_topics']), ($cur_forum['num_topics']));

($hook = get_hook('vf_modify_page_details')) ? eval($hook) : null;

// Navigation links for header and page numbering for title/meta description
if ($forum_page['page'] < $forum_page['num_pages'])
{
	$forum_page['nav']['last'] = '<link rel="last" href="'.forum_sublink($forum_url['forum'], $forum_url['page'], $forum_page['num_pages'], array($id, sef_friendly($cur_forum['forum_name']))).'" title="'.$lang_common['Page'].' '.$forum_page['num_pages'].'" />';
	$forum_page['nav']['next'] = '<link rel="next" href="'.forum_sublink($forum_url['forum'], $forum_url['page'], ($forum_page['page'] + 1), array($id, sef_friendly($cur_forum['forum_name']))).'" title="'.$lang_common['Page'].' '.($forum_page['page'] + 1).'" />';
}
if ($forum_page['page'] > 1)
{
	$forum_page['nav']['prev'] = '<link rel="prev" href="'.forum_sublink($forum_url['forum'], $forum_url['page'], ($forum_page['page'] - 1), array($id, sef_friendly($cur_forum['forum_name']))).'" title="'.$lang_common['Page'].' '.($forum_page['page'] - 1).'" />';
	$forum_page['nav']['first'] = '<link rel="first" href="'.forum_link($forum_url['forum'], array($id, sef_friendly($cur_forum['forum_name']))).'" title="'.$lang_common['Page'].' 1" />';
}


// Fetch list of topics
$query = array(
	'SELECT'	=> 't.id, t.poster, t.subject, t.posted, t.first_post_id, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to',
	'FROM'		=> 'topics AS t',
	'WHERE'		=> 't.forum_id='.$id,
	'ORDER BY'	=> 'sticky DESC, '.(($cur_forum['sort_by'] == '1') ? 'posted' : 'last_post').' DESC',
	'LIMIT'		=> $forum_page['start_from'].', '.$forum_user['disp_topics']
);

// With "has posted" indication
if (!$forum_user['is_guest'] && $forum_config['o_show_dot'] == '1')
{
	$query['SELECT'] .= ', p.poster_id AS has_posted';
	$query['JOINS'][] = array(
		'LEFT JOIN'	=> 'posts AS p',
		'ON'		=> 't.id=p.topic_id AND p.poster_id='.$forum_user['id']
	);

	if ($db_type == 'sqlite')
	{
		$query['WHERE'] = 't.id IN(SELECT id FROM '.$forum_db->prefix.'topics WHERE forum_id='.$id.' ORDER BY sticky DESC, '.(($cur_forum['sort_by'] == '1') ? 'posted' : 'last_post').' DESC LIMIT '.$forum_page['start_from'].', '.$forum_user['disp_topics'].')';
		$query['ORDER BY'] = 't.sticky DESC, t.last_post DESC';
	}

	$query['GROUP BY'] = ($db_type != 'pgsql') ? 't.id' : 't.id, t.subject, t.poster, t.posted, t.first_post_id, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to, p.poster_id';
}

($hook = get_hook('vf_qr_get_topics')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

// Generate paging/posting links
$forum_page['page_post']['paging'] = '<p class="paging"><span class="pages">'.$lang_common['Pages'].'</span> '.paginate($forum_page['num_pages'], $forum_page['page'], $forum_url['forum'], $lang_common['Paging separator'], array($id, sef_friendly($cur_forum['forum_name']))).'</p>';

if ($forum_user['may_post'])
	$forum_page['page_post']['posting'] = '<p class="posting"><a class="newpost" href="'.forum_link($forum_url['new_topic'], $id).'"><span>'.$lang_forum['Post topic'].'</span></a></p>';
else if ($forum_user['is_guest'])
	$forum_page['page_post']['posting'] = '<p class="posting">'.sprintf($lang_forum['Login to post'], '<a href="'.forum_link($forum_url['login']).'">'.strtolower($lang_common['Login']).'</a>', '<a href="'.forum_link($forum_url['register']).'">'.strtolower($lang_common['Register']).'</a>').'</p>';
else
	$forum_page['page_post']['posting'] = '<p class="posting">'.$lang_forum['No posting allowed'].'</p>';

// Setup main options
$forum_page['main_options'] = array();
$forum_page['main_options']['feed'] = '<span class="feed'.(empty($forum_page['main_options']) ? ' item1' : '').'"><a class="feed" href="'.forum_link($forum_url['forum_rss'], $id).'">'.$lang_forum['RSS forum feed'].'</a></span>';

if (!$forum_user['is_guest'] && $forum_db->num_rows($result))
{
	$forum_page['main_options']['mark_read'] = '<span'.(empty($forum_page['main_options']) ? ' class="item1"' : '').'><a href="'.forum_link($forum_url['mark_forum_read'], array($id, generate_form_token('markforumread'.$id.$forum_user['id']))).'">'.$lang_forum['Mark forum read'].'</a></span>';

	if ($forum_page['is_admmod'])
		$forum_page['main_options']['moderate'] = '<span'.(empty($forum_page['main_options']) ? ' class="item1"' : '').'><a href="'.forum_sublink($forum_url['moderate_forum'], $forum_url['page'], $forum_page['page'], $id).'">'.$lang_forum['Moderate forum'].'</a></span>';
}

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	forum_htmlencode($cur_forum['forum_name'])
);

// Setup Headers
$forum_page['main_head'] = '<a class="permalink" href="'.forum_link($forum_url['forum'], array($id, sef_friendly($cur_forum['forum_name']))).'" rel="bookmark" title="'.$lang_forum['Permalink forum'].'">'.forum_htmlencode($cur_forum['forum_name']).'</a>';

if ($forum_page['num_pages'] > 1)
	$forum_page['main_head'] .= '<br /><small>'.sprintf($lang_topic['Paged info'], $forum_page['start_from'] + 1, $forum_page['finish_at'], $cur_forum['num_topics']).'</small>';

($hook = get_hook('vf_pre_header_load')) ? eval($hook) : null;

// Allow indexing if this isn't a link with p=1
if (!isset($_GET['p']) || $forum_page['page'] != 1)
	define('FORUM_ALLOW_INDEX', 1);

define('FORUM_PAGE', 'viewforum');
define('FORUM_PAGE_TYPE', 'forum');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('vf_main_output_start')) ? eval($hook) : null;

?>
<div id="forum<?php echo $id ?>" class="main-content forum">
	<table cellspacing="0" summary="<?php printf($lang_forum['Table summary'], forum_htmlencode($cur_forum['forum_name'])) ?>">
		<thead>
			<tr>
<?php ($hook = get_hook('vf_table_header_begin')) ? eval($hook) : null; ?>
				<th class="tcl" scope="col"><?php echo $lang_common['Topic'] ?></th>
				<th class="tc2" scope="col"><?php echo $lang_common['Replies'] ?></th>
<?php if ($forum_config['o_topic_views'] == '1'): ?>				<th class="tc3" scope="col"><?php echo $lang_forum['Views'] ?></th>
<?php endif; ($hook = get_hook('vf_table_header_after_num_views')) ? eval($hook) : null; ?>				<th class="tcr" scope="col"><?php echo $lang_common['Last post'] ?></th>
<?php ($hook = get_hook('vf_table_header_after_last_post')) ? eval($hook) : null; ?>
			</tr>
		</thead>
			<tbody class="statused">
<?php

// If there are topics in this forum
if ($forum_db->num_rows($result))
{
	($hook = get_hook('vf_pre_topic_loop_start')) ? eval($hook) : null;

	$forum_page['item_count'] = 0;

	while ($cur_topic = $forum_db->fetch_assoc($result))
	{
		($hook = get_hook('vf_topic_loop_start')) ? eval($hook) : null;

		++$forum_page['item_count'];

		// Start from scratch
		$forum_page['item_subject'] = $forum_page['item_status'] = $forum_page['item_last_post'] = $forum_page['item_alt_message'] = $forum_page['item_nav'] = array();
		$forum_page['item_indicator'] = '';
		$forum_page['item_alt_message']['topic'] = $lang_common['Topic'].' '.($forum_page['start_from'] + $forum_page['item_count']);

		if ($forum_config['o_censoring'] == '1')
			$cur_topic['subject'] = censor_words($cur_topic['subject']);

		if ($cur_topic['moved_to'] != null)
		{
			$forum_page['item_status']['moved'] = 'moved';
			$forum_page['item_last_post']['moved'] = $forum_page['item_alt_message']['moved'] = $lang_forum['Moved'];
			$forum_page['item_subject']['moved_to'] = '<a href="'.forum_link($forum_url['topic'], array($cur_topic['moved_to'], sef_friendly($cur_topic['subject']))).'">'.forum_htmlencode($cur_topic['subject']).'</a>';
			$forum_page['item_subject']['moved_by'] = '<span class="byuser">'.sprintf($lang_common['By user'], forum_htmlencode($cur_topic['poster'])).'</span>';
			$cur_topic['num_replies'] = $cur_topic['num_views'] = ' - ';
		}
		else
		{
			// Should we display the dot or not? :)
			if (!$forum_user['is_guest'] && $forum_config['o_show_dot'] == '1' && $cur_topic['has_posted'] == $forum_user['id'])
			{
				$forum_page['item_indicator'] = $lang_forum['You posted indicator'];
				$forum_page['item_status']['posted'] = 'posted';
				$forum_page['item_alt_message']['posted'] = $lang_forum['You posted'];
			}

			if ($cur_topic['sticky'] == '1')
			{
				$forum_page['item_subject']['sticky'] = $lang_forum['Sticky'];
				$forum_page['item_status']['sticky'] = 'sticky';
			}

			if ($cur_topic['closed'] == '1')
			{
				$forum_page['item_subject']['closed'] = $lang_common['Closed'];
				$forum_page['item_status']['closed'] = 'closed';
			}

			$forum_page['item_subject']['subject'] = '<a href="'.forum_link($forum_url['topic'], array($cur_topic['id'], sef_friendly($cur_topic['subject']))).'">'.forum_htmlencode($cur_topic['subject']).'</a>';

			$forum_page['item_pages'] = ceil(($cur_topic['num_replies'] + 1) / $forum_user['disp_posts']);

			if ($forum_page['item_pages'] > 1)
				$forum_page['item_nav']['pages'] = paginate($forum_page['item_pages'], -1, $forum_url['topic'], $lang_common['Page separator'], array($cur_topic['id'], sef_friendly($cur_topic['subject'])));

			// Does this topic contain posts we haven't read? If so, tag it accordingly.
			if (!$forum_user['is_guest'] && $cur_topic['last_post'] > $forum_user['last_visit'] && (!isset($tracked_topics['topics'][$cur_topic['id']]) || $tracked_topics['topics'][$cur_topic['id']] < $cur_topic['last_post']) && (!isset($tracked_topics['forums'][$id]) || $tracked_topics['forums'][$id] < $cur_topic['last_post']))
			{
				$forum_page['item_nav']['new'] = '<a href="'.forum_link($forum_url['topic_new_posts'], array($cur_topic['id'], sef_friendly($cur_topic['subject']))).'" title="'.$lang_forum['New posts info'].'">'.$lang_common['New posts'].'</a>';
				$forum_page['item_status']['new'] = 'new';
			}

			if (!empty($forum_page['item_nav']))
				$forum_page['item_subject']['nav'] = '<span class="topic-nav">[&#160;'.implode('&#160;&#160;', $forum_page['item_nav']).'&#160;]</span>';

			$forum_page['item_subject']['poster'] = '<span class="byuser">'.sprintf($lang_common['By user'], forum_htmlencode($cur_topic['poster'])).'</span>';
			$forum_page['item_last_post']['last_post'] = '<a href="'.forum_link($forum_url['post'], $cur_topic['last_post_id']).'"><span>'.format_time($cur_topic['last_post']).'</span></a>';
			$forum_page['item_last_post']['last_poster'] = '<span class="byuser">'.sprintf($lang_common['By user'], forum_htmlencode($cur_topic['last_poster'])).'</span>';

			if (empty($forum_page['item_status']))
				$forum_page['item_status']['normal'] = 'normal';
		}

		($hook = get_hook('vf_row_pre_item_merge')) ? eval($hook) : null;

		$forum_page['item_style'] = (($forum_page['item_count'] % 2 != 0) ? 'odd' : 'even').' '.implode(' ', $forum_page['item_status']);
		if ($forum_page['item_count'] == 1)
			$forum_page['item_style'] .= ' row1';

		$forum_page['item_indicator'] = '<span class="status '.implode(' ', $forum_page['item_status']).'" title="'.implode(' - ', $forum_page['item_alt_message']).'"><img src="'.$base_url.'/style/'.$forum_user['style'].'/status.png" alt="'.implode(' - ', $forum_page['item_alt_message']).'" />'.$forum_page['item_indicator'].'</span>';

		($hook = get_hook('vf_row_pre_display')) ? eval($hook) : null;

?>
			<tr class="<?php echo $forum_page['item_style'] ?>">
<?php ($hook = get_hook('vf_table_contents_begin')) ? eval($hook) : null; ?>
				<td class="tcl"><?php echo $forum_page['item_indicator'].' '.implode(' ', $forum_page['item_subject']) ?></td>
				<td class="tc2"><?php echo $cur_topic['num_replies'] ?></td>
<?php if ($forum_config['o_topic_views'] == '1'): ?>				<td class="tc3"><?php echo $cur_topic['num_views'] ?></td>
<?php endif; ($hook = get_hook('vf_table_contents_after_num_views')) ? eval($hook) : null; ?>				<td class="tcr"><?php echo implode(' ', $forum_page['item_last_post']) ?></td>
<?php ($hook = get_hook('vf_table_contents_after_last_post')) ? eval($hook) : null; ?>
			</tr>
<?php

	}
}
// Else there are no topics in this forum
else
{
	$forum_page['item_indicator'] = '<span class="status empty" title="'.$lang_forum['No topics'].'"><img src="'.$base_url.'/style/'.$forum_user['style'].'/status.png" alt="'.$lang_forum['No topics'].'" /></span>';

?>
			<tr class="odd empty">
<?php ($hook = get_hook('vf_empty_table_contents_begin')) ? eval($hook) : null; ?>
				<td class="tcl"><?php echo $forum_page['item_indicator'].' '.$lang_forum['First topic nag'] ?></td>
				<td class="tc2">&#160;</td>
<?php if ($forum_config['o_topic_views'] == '1'): ?>				<td class="tc3">&#160;</td>
<?php endif; ($hook = get_hook('vf_empty_table_contents_after_num_views')) ? eval($hook) : null; ?>				<td class="tcr"><?php echo $lang_forum['Never'] ?></td>
<?php ($hook = get_hook('vf_empty_table_contents_after_last_post')) ? eval($hook) : null; ?>
			</tr>
<?php

}

?>
		</tbody>
	</table>
</div>
<?php

($hook = get_hook('vf_end')) ? eval($hook) : null;

$tpl_temp = trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

$forum_id = $id;

require FORUM_ROOT.'footer.php';
