<?php
/***********************************************************************

  Copyright (C) 2002-2008  PunBB.org

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


if (!defined('PUN_ROOT'))
	define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';

($hook = get_hook('vf_start')) ? eval($hook) : null;

if ($pun_user['g_read_board'] == '0')
	message($lang_common['No view']);

// Load the viewforum.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/forum.php';


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
			'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].')'
		)
	),
	'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND f.id='.$id
);

($hook = get_hook('vf_qr_get_forum_info')) ? eval($hook) : null;
$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
if (!$pun_db->num_rows($result))
	message($lang_common['Bad request']);

$cur_forum = $pun_db->fetch_assoc($result);

// Is this a redirect forum? In that case, redirect!
if ($cur_forum['redirect_url'] != '')
{
	($hook = get_hook('vf_redirect_forum_pre_redirect')) ? eval($hook) : null;

	header('Location: '.$cur_forum['redirect_url']);
	exit;
}

// Sort out who the moderators are and if we are currently a moderator (or an admin)
$mods_array = array();
if ($cur_forum['moderators'] != '')
	$mods_array = unserialize($cur_forum['moderators']);

$pun_page['is_admmod'] = ($pun_user['g_id'] == PUN_ADMIN || ($pun_user['g_moderator'] == '1' && array_key_exists($pun_user['username'], $mods_array))) ? true : false;

// Sort out whether or not this user can post
$pun_user['may_post'] = (($cur_forum['post_topics'] == '' && $pun_user['g_post_topics'] == '1') || $cur_forum['post_topics'] == '1' || $pun_page['is_admmod']) ? true : false;

// Get topic/forum tracking data
if (!$pun_user['is_guest'])
	$tracked_topics = get_tracked_topics();

// Determine the topic offset (based on $_GET['p'])
$pun_page['num_pages'] = ceil($cur_forum['num_topics'] / $pun_user['disp_topics']);
$pun_page['page'] = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $pun_page['num_pages']) ? 1 : $_GET['p'];
$pun_page['start_from'] = $pun_user['disp_topics'] * ($pun_page['page'] - 1);
$pun_page['finish_at'] = min(($pun_page['start_from'] + $pun_user['disp_topics']), ($cur_forum['num_topics']));

// Navigation links for header and page numbering for title/meta description
if ($pun_page['page'] < $pun_page['num_pages'])
{
	$pun_page['nav'][] = '<link rel="last" href="'.pun_sublink($pun_url['forum'], $pun_url['page'], $pun_page['num_pages'], array($id, sef_friendly($cur_forum['forum_name']))).'" title="'.$lang_common['Page'].' '.$pun_page['num_pages'].'" />';
	$pun_page['nav'][] = '<link rel="next" href="'.pun_sublink($pun_url['forum'], $pun_url['page'], ($pun_page['page'] + 1), array($id, sef_friendly($cur_forum['forum_name']))).'" title="'.$lang_common['Page'].' '.($pun_page['page'] + 1).'" />';
}
if ($pun_page['page'] > 1)
{
	$pun_page['nav'][] = '<link rel="prev" href="'.pun_sublink($pun_url['forum'], $pun_url['page'], ($pun_page['page'] - 1), array($id, sef_friendly($cur_forum['forum_name']))).'" title="'.$lang_common['Page'].' '.($pun_page['page'] - 1).'" />';
	$pun_page['nav'][] = '<link rel="first" href="'.pun_link($pun_url['forum'], array($id, sef_friendly($cur_forum['forum_name']))).'" title="'.$lang_common['Page'].' 1" />';
}


// Fetch list of topics
$query = array(
	'SELECT'	=> 't.id, t.poster, t.subject, t.posted, t.first_post_id, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to',
	'FROM'		=> 'topics AS t',
	'WHERE'		=> 't.forum_id='.$id,
	'ORDER BY'	=> 'sticky DESC, '.(($cur_forum['sort_by'] == '1') ? 'posted' : 'last_post').' DESC',
	'LIMIT'		=> $pun_page['start_from'].', '.$pun_user['disp_topics']
);

// With "has posted" indication
if (!$pun_user['is_guest'] && $pun_config['o_show_dot'] == '1')
{
	$query['SELECT'] .= ', p.poster_id AS has_posted';
	$query['JOINS'][] = array(
		'LEFT JOIN'	=> 'posts AS p',
		'ON'		=> 't.id=p.topic_id AND p.poster_id='.$pun_user['id']
	);

	if ($db_type == 'sqlite')
	{
		$query['WHERE'] = 't.id IN(SELECT id FROM '.$pun_db->prefix.'topics WHERE forum_id='.$id.' ORDER BY sticky DESC, '.(($cur_forum['sort_by'] == '1') ? 'posted' : 'last_post').' DESC LIMIT '.$pun_page['start_from'].', '.$pun_user['disp_topics'].')';
		$query['ORDER BY'] = 't.sticky DESC, t.last_post DESC';
	}

	$query['GROUP BY'] = ($db_type != 'pgsql') ? 't.id' : 't.id, t.subject, t.poster, t.posted, t.first_post_id, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to, p.poster_id';
}

($hook = get_hook('vf_qr_get_topics')) ? eval($hook) : null;
$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);

// Generate page information
if ($pun_page['num_pages'] > 1)
	$pun_page['main_info'] = '<span>'.sprintf($lang_common['Page number'], $pun_page['page'], $pun_page['num_pages']).' </span>'.sprintf($lang_common['Paged info'], $lang_common['Topics'], $pun_page['start_from'] + 1, $pun_page['finish_at'], $cur_forum['num_topics']);
else
	$pun_page['main_info'] = (($pun_db->num_rows($result)) ? sprintf($lang_common['Page info'], $lang_common['Topics'], $cur_forum['num_topics']) : $lang_forum['No topics']);

// Generate paging/posting links
$pun_page['page_post'][] = '<p class="paging"><span class="pages">'.$lang_common['Pages'].'</span> '.paginate($pun_page['num_pages'], $pun_page['page'], $pun_url['forum'], $lang_common['Paging separator'], array($id, sef_friendly($cur_forum['forum_name']))).'</p>';

if ($pun_user['may_post'])
	$pun_page['page_post'][] = '<p class="posting"><a class="newpost" href="'.pun_link($pun_url['new_topic'], $id).'"><span>'.$lang_forum['Post topic'].'</span></a></p>';

// Setup main head/foot options
$pun_page['main_head_options'] = array(
	'<a class="feed-option" href="'.pun_link($pun_url['forum_atom'], $id).'"><span>'.$lang_common['ATOM Feed'].'</span></a>',
	'<a class="feed-option" href="'.pun_link($pun_url['forum_rss'], $id).'"><span>'.$lang_common['RSS Feed'].'</span></a>'
);

$pun_page['main_foot_options'] = array();
if ($pun_user['is_guest'] && !$pun_user['may_post'])
	$pun_page['main_foot_options'][] = sprintf($lang_forum['Forum login nag'], '<a href="'.pun_link($pun_url['login']).'">'.strtolower($lang_common['Login']).'</a>', '<a href="'.pun_link($pun_url['register']).'">'.strtolower($lang_common['Register']).'</a>');

if (!$pun_user['is_guest'] && $pun_db->num_rows($result))
{
	$pun_page['main_foot_options'][] = '<a class="user-option" href="'.pun_link($pun_url['mark_forum_read'], array($id, generate_form_token('markforumread'.$id.$pun_user['id']))).'"><span>'.$lang_forum['Mark forum read'].'</span></a>';

	if ($pun_page['is_admmod'])
		$pun_page['main_foot_options'][] = '<a class="mod-option" href="'.pun_sublink($pun_url['moderate_forum'], $pun_url['page'], $pun_page['page'], $id).'"><span>'.$lang_forum['Moderate forum'].'</span></a>';
}

// Setup breadcrumbs
$pun_page['crumbs'] = array(
	array($pun_config['o_board_title'], pun_link($pun_url['index'])),
	array($cur_forum['forum_name'], pun_link($pun_url['forum'], array($id, sef_friendly($cur_forum['forum_name']))))
);

($hook = get_hook('vf_pre_header_load')) ? eval($hook) : null;

define('PUN_ALLOW_INDEX', 1);
define('PUN_PAGE', 'viewforum');
require PUN_ROOT.'header.php';

?>
<div id="pun-main" class="main paged">

	<h1><span><a class="permalink" href="<?php echo pun_link($pun_url['forum'], array($id, sef_friendly($cur_forum['forum_name']))) ?>" rel="bookmark" title="<?php echo $lang_forum['Permalink forum'] ?>"><?php echo pun_htmlencode($cur_forum['forum_name']) ?></a></span></h1>

	<div class="paged-head">
		<?php echo implode("\n\t\t", $pun_page['page_post'])."\n" ?>
	</div>

	<div class="main-head">
		<p class="main-options"><?php echo implode(' ', $pun_page['main_head_options']) ?></p>
		<h2><span><?php echo $pun_page['main_info'] ?></span></h2>
	</div>

	<div id="forum<?php echo $id ?>" class="main-content forum">
		<table cellspacing="0" summary="<?php printf($lang_forum['Table summary'], pun_htmlencode($cur_forum['forum_name'])) ?>">
			<thead>
				<tr>
<?php ($hook = get_hook('vf_table_header_begin')) ? eval($hook) : null; ?>
					<th class="tcl" scope="col"><?php echo $lang_common['Topic'] ?></th>
					<th class="tc2" scope="col"><?php echo $lang_common['Replies'] ?></th>
<?php if ($pun_config['o_topic_views'] == '1'): ?>					<th class="tc3" scope="col"><?php echo $lang_forum['Views'] ?></th>
<?php endif; ($hook = get_hook('vf_table_header_after_num_views')) ? eval($hook) : null; ?>					<th class="tcr" scope="col"><?php echo $lang_common['Last post'] ?></th>
<?php ($hook = get_hook('vf_table_header_after_last_post')) ? eval($hook) : null; ?>
				</tr>
			</thead>
			<tbody class="statused">
<?php

// If there are topics in this forum
if ($pun_db->num_rows($result))
{
	($hook = get_hook('vf_pre_topic_loop_start')) ? eval($hook) : null;

	$pun_page['item_count'] = 0;

	while ($cur_topic = $pun_db->fetch_assoc($result))
	{
		($hook = get_hook('vf_topic_loop_start')) ? eval($hook) : null;

		++$pun_page['item_count'];

		// Start from scratch
		$pun_page['item_subject'] = $pun_page['item_status'] = $pun_page['item_last_post'] = $pun_page['item_alt_message'] = $pun_page['item_nav'] = array();
		$pun_page['item_indicator'] = '';
		$pun_page['item_alt_message'][] = $lang_common['Topic'].' '.($pun_page['start_from'] + $pun_page['item_count']);

		if ($pun_config['o_censoring'] == '1')
			$cur_topic['subject'] = censor_words($cur_topic['subject']);

		if ($cur_topic['moved_to'] != null)
		{
			$pun_page['item_status'][] = 'moved';
			$pun_page['item_last_post'][] = $pun_page['item_alt_message'][] = $lang_forum['Moved'];
			$pun_page['item_subject'][] = '<a href="'.pun_link($pun_url['topic'], array($cur_topic['moved_to'], sef_friendly($cur_topic['subject']))).'">'.pun_htmlencode($cur_topic['subject']).'</a>';
			$pun_page['item_subject'][] = '<span class="byuser">'.sprintf($lang_common['By user'], pun_htmlencode($cur_topic['poster'])).'</span>';
			$cur_topic['num_replies'] = $cur_topic['num_views'] = ' - ';
		}
		else
		{
			// Should we display the dot or not? :)
			if (!$pun_user['is_guest'] && $pun_config['o_show_dot'] == '1' && $cur_topic['has_posted'] == $pun_user['id'])
			{
				$pun_page['item_indicator'] = $lang_forum['You posted indicator'];
				$pun_page['item_status'][] = 'posted';
				$pun_page['item_alt_message'][] = $lang_forum['You posted'];
			}

			if ($cur_topic['sticky'] == '1')
			{
				$pun_page['item_subject'][] = $lang_forum['Sticky'];
				$pun_page['item_status'][] = 'sticky';
			}

			if ($cur_topic['closed'] == '1')
			{
				$pun_page['item_subject'][] = $lang_common['Closed'];
				$pun_page['item_status'][] = 'closed';
			}

			$pun_page['item_subject'][] = '<a href="'.pun_link($pun_url['topic'], array($cur_topic['id'], sef_friendly($cur_topic['subject']))).'">'.pun_htmlencode($cur_topic['subject']).'</a>';

			$pun_page['item_pages'] = ceil(($cur_topic['num_replies'] + 1) / $pun_user['disp_posts']);

			if ($pun_page['item_pages'] > 1)
				$pun_page['item_nav'][] = paginate($pun_page['item_pages'], -1, $pun_url['topic'], $lang_common['Page separator'], array($cur_topic['id'], sef_friendly($cur_topic['subject'])));

			// Does this topic contain posts we haven't read? If so, tag it accordingly.
			if (!$pun_user['is_guest'] && $cur_topic['last_post'] > $pun_user['last_visit'] && (!isset($tracked_topics['topics'][$cur_topic['id']]) || $tracked_topics['topics'][$cur_topic['id']] < $cur_topic['last_post']) && (!isset($tracked_topics['forums'][$id]) || $tracked_topics['forums'][$id] < $cur_topic['last_post']))
			{
				$pun_page['item_nav'][] = '<a href="'.pun_link($pun_url['topic_new_posts'], array($cur_topic['id'], sef_friendly($cur_topic['subject']))).'" title="'.$lang_forum['New posts info'].'">'.$lang_common['New posts'].'</a>';
				$pun_page['item_status'][] = 'new';
			}

			if (!empty($pun_page['item_nav']))
				$pun_page['item_subject'][] = '<span class="topic-nav">[&#160;'.implode('&#160;&#160;', $pun_page['item_nav']).'&#160;]</span>';

			$pun_page['item_subject'][] = '<span class="byuser">'.sprintf($lang_common['By user'], pun_htmlencode($cur_topic['poster'])).'</span>';
			$pun_page['item_last_post'][] = '<a href="'.pun_link($pun_url['post'], $cur_topic['last_post_id']).'"><span>'.format_time($cur_topic['last_post']).'</span></a>';
			$pun_page['item_last_post'][] = '<span class="byuser">'.sprintf($lang_common['By user'], pun_htmlencode($cur_topic['last_poster'])).'</span>';

			if (empty($pun_page['item_status']))
				$pun_page['item_status'][] = 'normal';
		}

		$pun_page['item_style'] = (($pun_page['item_count'] % 2 != 0) ? 'odd' : 'even').' '.implode(' ', $pun_page['item_status']);
		$pun_page['item_indicator'] = '<span class="status '.implode(' ', $pun_page['item_status']).'" title="'.implode(' - ', $pun_page['item_alt_message']).'"><img src="'.$base_url.'/style/'.$pun_user['style'].'/status.png" alt="'.implode(' - ', $pun_page['item_alt_message']).'" />'.$pun_page['item_indicator'].'</span>';

		($hook = get_hook('vf_row_pre_display')) ? eval($hook) : null;

?>
				<tr class="<?php echo $pun_page['item_style'] ?>">
<?php ($hook = get_hook('vf_table_contents_begin')) ? eval($hook) : null; ?>
					<td class="tcl"><?php echo $pun_page['item_indicator'].' '.implode(' ', $pun_page['item_subject']) ?></td>
					<td class="tc2"><?php echo $cur_topic['num_replies'] ?></td>
<?php if ($pun_config['o_topic_views'] == '1'): ?>					<td class="tc3"><?php echo $cur_topic['num_views'] ?></td>
<?php endif; ($hook = get_hook('vf_table_contents_after_num_views')) ? eval($hook) : null; ?>					<td class="tcr"><?php echo implode(' ', $pun_page['item_last_post']) ?></td>
<?php ($hook = get_hook('vf_table_contents_after_last_post')) ? eval($hook) : null; ?>
				</tr>
<?php

	}
}
// Else there are no topics in this forum
else
{
	$pun_page['item_indicator'] = '<span class="status empty" title="'.$lang_forum['No topics'].'"><img src="'.$base_url.'/style/'.$pun_user['style'].'/status.png" alt="'.$lang_forum['No topics'].'" /></span>';

?>
				<tr class="odd empty">
					<td class="tcl"><?php echo $pun_page['item_indicator'].' '.$lang_forum['First topic nag'] ?></td>
					<td class="tc2">&#160;</td>
<?php if ($pun_config['o_topic_views'] == '1'): ?>					<td class="tc3">&#160;</td>
<?php endif; ?>					<td class="tcr"><?php echo $lang_forum['Never'] ?></td>
				</tr>
<?php

}

?>
			</tbody>
		</table>
	</div>

	<div class="main-foot">
		<p class="h2"><strong><?php echo $pun_page['main_info'] ?></strong></p>
<?php if (!empty($pun_page['main_foot_options'])): ?>		<p class="main-options"><?php echo implode(' ', $pun_page['main_foot_options']) ?></p>
<?php endif; ?>	</div>

	<div class="paged-foot">
		<?php echo implode("\n\t\t", array_reverse($pun_page['page_post']))."\n" ?>
	</div>

</div>

<div id="pun-crumbs-foot">
	<p class="crumbs"><?php echo generate_crumbs(false) ?></p>
</div>
<?php

$forum_id = $id;

($hook = get_hook('vf_end')) ? eval($hook) : null;

require PUN_ROOT.'footer.php';
