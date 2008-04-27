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

($hook = get_hook('vt_start')) ? eval($hook) : null;

if ($pun_user['g_read_board'] == '0')
	message($lang_common['No view']);

// Load the viewtopic.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/topic.php';


$action = isset($_GET['action']) ? $_GET['action'] : null;
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$pid = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
if ($id < 1 && $pid < 1)
	message($lang_common['Bad request']);


// If a post ID is specified we determine topic ID and page number so we can redirect to the correct message
if ($pid)
{
	$query = array(
		'SELECT'	=> 'p.topic_id, p.posted',
		'FROM'		=> 'posts AS p',
		'WHERE'		=> 'p.id='.$pid
	);

	($hook = get_hook('vt_qr_get_post_info')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	if (!$pun_db->num_rows($result))
		message($lang_common['Bad request']);

	list($id, $posted) = $pun_db->fetch_row($result);

	// Determine on what page the post is located (depending on $pun_user['disp_posts'])
	$query = array(
		'SELECT'	=> '1',
		'FROM'		=> 'posts AS p',
		'WHERE'		=> 'p.topic_id='.$id.' AND p.posted<'.$posted
	);

	($hook = get_hook('vt_qr_get_post_page')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	$num_posts = $pun_db->num_rows($result) + 1;

	$_GET['p'] = ceil($num_posts / $pun_user['disp_posts']);
}

// If action=new, we redirect to the first new post (if any)
else if ($action == 'new' && !$pun_user['is_guest'])
{
	// We need to check if this topic has been viewed recently by the user
	$tracked_topics = get_tracked_topics();
	$last_viewed = isset($tracked_topics['topics'][$id]) ? $tracked_topics['topics'][$id] : $pun_user['last_visit'];

	($hook = get_hook('vt_find_new_post')) ? eval($hook) : null;

	$query = array(
		'SELECT'	=> 'MIN(p.id)',
		'FROM'		=> 'posts AS p',
		'WHERE'		=> 'p.topic_id='.$id.' AND p.posted>'.$last_viewed
	);

	($hook = get_hook('vt_qr_get_first_new_post')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	$first_new_post_id = $pun_db->result($result);

	if ($first_new_post_id)
		header('Location: '.str_replace('&amp;', '&', pun_link($pun_url['post'], $first_new_post_id)));
	else	// If there is no new post, we go to the last post
		header('Location: '.str_replace('&amp;', '&', pun_link($pun_url['topic_last_post'], $id)));

	exit;
}

// If action=last, we redirect to the last post
else if ($action == 'last')
{
	$query = array(
		'SELECT'	=> 't.last_post_id',
		'FROM'		=> 'topics AS t',
		'WHERE'		=> 't.id='.$id
	);

	($hook = get_hook('vt_qr_get_last_post')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	$last_post_id = $pun_db->result($result);

	if ($last_post_id)
	{
		header('Location: '.str_replace('&amp;', '&', pun_link($pun_url['post'], $last_post_id)));
		exit;
	}
}


// Fetch some info about the topic
$query = array(
	'SELECT'	=> 't.subject, t.posted, t.poster, t.first_post_id, t.closed, t.num_replies, t.sticky, f.id AS forum_id, f.forum_name, f.moderators, fp.post_replies',
	'FROM'		=> 'topics AS t',
	'JOINS'		=> array(
		array(
			'INNER JOIN'	=> 'forums AS f',
			'ON'			=> 'f.id=t.forum_id'
		),
		array(
			'LEFT JOIN'		=> 'forum_perms AS fp',
			'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].')'
		)
	),
	'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND t.id='.$id.' AND t.moved_to IS NULL'
);

if (!$pun_user['is_guest'])
{
	$query['SELECT'] .= ', s.user_id AS is_subscribed';
	$query['JOINS'][] = array(
		'LEFT JOIN'	=> 'subscriptions AS s',
		'ON'		=> '(t.id=s.topic_id AND s.user_id='.$pun_user['id'].')'
	);
}

($hook = get_hook('vt_qr_get_topic_info')) ? eval($hook) : null;
$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
if (!$pun_db->num_rows($result))
	message($lang_common['Bad request']);

$cur_topic = $pun_db->fetch_assoc($result);

// Sort out who the moderators are and if we are currently a moderator (or an admin)
$mods_array = ($cur_topic['moderators'] != '') ? unserialize($cur_topic['moderators']) : array();
$pun_page['is_admmod'] = ($pun_user['g_id'] == PUN_ADMIN || ($pun_user['g_moderator'] == '1' && array_key_exists($pun_user['username'], $mods_array))) ? true : false;

// Can we or can we not post replies?
if ($cur_topic['closed'] == '0' || $pun_page['is_admmod'])
	$pun_user['may_post'] = (($cur_topic['post_replies'] == '' && $pun_user['g_post_replies'] == '1') || $cur_topic['post_replies'] == '1' || $pun_page['is_admmod']) ? true : false;
else
	$pun_user['may_post'] = false;

// Add/update this topic in our list of tracked topics
if (!$pun_user['is_guest'])
{
	$tracked_topics = get_tracked_topics();
	$tracked_topics['topics'][$id] = time();
	set_tracked_topics($tracked_topics);
}

// Determine the post offset (based on $_GET['p'])
$pun_page['num_pages'] = ceil(($cur_topic['num_replies'] + 1) / $pun_user['disp_posts']);
$pun_page['page'] = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $pun_page['num_pages']) ? 1 : $_GET['p'];
$pun_page['start_from'] = $pun_user['disp_posts'] * ($pun_page['page'] - 1);
$pun_page['finish_at'] = min(($pun_page['start_from'] + $pun_user['disp_posts']), ($cur_topic['num_replies'] + 1));

// Navigation links for header and page numbering for title/meta description
if ($pun_page['page'] < $pun_page['num_pages'])
{
	$pun_page['nav'][] = '<link rel="last" href="'.pun_sublink($pun_url['topic'], $pun_url['page'], $pun_page['num_pages'], array($id, sef_friendly($cur_topic['subject']))).'" title="'.$lang_common['Page'].' '.$pun_page['num_pages'].'" />';
	$pun_page['nav'][] = '<link rel="next" href="'.pun_sublink($pun_url['topic'], $pun_url['page'], ($pun_page['page'] + 1), array($id, sef_friendly($cur_topic['subject']))).'" title="'.$lang_common['Page'].' '.($pun_page['page'] + 1).'" />';
}
if ($pun_page['page'] > 1)
{
	$pun_page['nav'][] = '<link rel="prev" href="'.pun_sublink($pun_url['topic'], $pun_url['page'], ($pun_page['page'] - 1), array($id, sef_friendly($cur_topic['subject']))).'" title="'.$lang_common['Page'].' '.($pun_page['page'] - 1).'" />';
	$pun_page['nav'][] = '<link rel="first" href="'.pun_link($pun_url['topic'], array($id, sef_friendly($cur_topic['subject']))).'" title="'.$lang_common['Page'].' 1" />';
}

// Generate page information
if ($pun_page['num_pages'] > 1)
	$pun_page['main_info'] = '<span>'.sprintf($lang_common['Page number'], $pun_page['page'], $pun_page['num_pages']).' </span>'.sprintf($lang_common['Paged info'], $lang_common['Posts'], $pun_page['start_from'] + 1, $pun_page['finish_at'], $cur_topic['num_replies'] + 1);
else
	$pun_page['main_info'] = sprintf($lang_common['Page info'], $lang_common['Posts'], ($cur_topic['num_replies'] + 1));

// Generate paging and posting links
$pun_page['page_post'][] = '<p class="paging"><span class="pages">'.$lang_common['Pages'].'</span> '.paginate($pun_page['num_pages'], $pun_page['page'], $pun_url['topic'], $lang_common['Paging separator'], array($id, sef_friendly($cur_topic['subject']))).'</p>';

if ($pun_user['may_post'])
	$pun_page['page_post'][] = '<p class="posting"><a class="newpost" href="'.pun_link($pun_url['new_reply'], $id).'"><span>'.$lang_topic['Post reply'].'</span></a></p>';

// Setup options for main header and footer
$pun_page['main_head_options'] = array();

if (!$pun_user['is_guest'] && $pun_config['o_subscriptions'] == '1')
{
	if ($cur_topic['is_subscribed'])
		$pun_page['main_head_options'][] = '<a class="sub-option" href="'.pun_link($pun_url['unsubscribe'], array($id, generate_form_token('unsubscribe'.$id.$pun_user['id']))).'"><em>'.$lang_topic['Cancel subscription'].'</em></a>';
	else
		$pun_page['main_head_options'][] = '<a class="sub-option" href="'.pun_link($pun_url['subscribe'], array($id, generate_form_token('subscribe'.$id.$pun_user['id']))).'">'.$lang_topic['Subscription'].'</a>';
}

$pun_page['main_head_options'][] = '<a class="feed-option" href="'.pun_link($pun_url['topic_atom'], $id).'">'.$lang_common['ATOM Feed'].'</a>';
$pun_page['main_head_options'][] = '<a class="feed-option" href="'.pun_link($pun_url['topic_rss'], $id).'">'.$lang_common['RSS Feed'].'</a>';

$pun_page['main_foot_options'] = array();
if ($pun_page['is_admmod'])
{
	$pun_page['main_foot_options'][] = '<a class="mod-option" href="'.pun_link($pun_url['move'], array($cur_topic['forum_id'], $id)).'">'.$lang_topic['Move'].'</a>';
	$pun_page['main_foot_options'][] = '<a class="mod-option" href="'.pun_link($pun_url['delete'], $cur_topic['first_post_id']).'">'.$lang_topic['Delete topic'].'</a>';
	$pun_page['main_foot_options'][] = (($cur_topic['closed'] == '1') ? '<a class="mod-option" href="'.pun_link($pun_url['open'], array($cur_topic['forum_id'], $id, generate_form_token('open'.$id))).'">'.$lang_topic['Open'].'</a>' : '<a class="mod-option" href="'.pun_link($pun_url['close'], array($cur_topic['forum_id'], $id, generate_form_token('close'.$id))).'">'.$lang_topic['Close'].'</a>');
	$pun_page['main_foot_options'][] = (($cur_topic['sticky'] == '1') ? '<a class="mod-option" href="'.pun_link($pun_url['unstick'], array($cur_topic['forum_id'], $id, generate_form_token('unstick'.$id))).'">'.$lang_topic['Unstick'].'</a>' : '<a class="mod-option" href="'.pun_link($pun_url['stick'], array($cur_topic['forum_id'], $id, generate_form_token('stick'.$id))).'">'.$lang_topic['Stick'].'</a>');

	if ($cur_topic['num_replies'] != 0)
		$pun_page['main_foot_options'][] = '<a class="mod-option" href="'.pun_sublink($pun_url['delete_multiple'], $pun_url['page'], $pun_page['page'], array($cur_topic['forum_id'], $id)).'">'.$lang_topic['Delete posts'].'</a>';
}

if ($pun_user['is_guest'] && (($cur_topic['post_replies'] == '' && $pun_user['g_post_replies'] == '0') || $cur_topic['post_replies'] == '0'))
	$pun_page['main_foot_options'][] = sprintf($lang_topic['Topic login nag'], '<a href="'.pun_link($pun_url['login']).'">'.strtolower($lang_common['Login']).'</a>', '<a href="'.pun_link($pun_url['register']).'">'.strtolower($lang_common['Register']).'</a>');

if ($pun_config['o_censoring'] == '1')
	$cur_topic['subject'] = censor_words($cur_topic['subject']);

// Setup breadcrumbs
$pun_page['crumbs'] = array(
	array($pun_config['o_board_title'], pun_link($pun_url['index'])),
	array($cur_topic['forum_name'], pun_link($pun_url['forum'], array($cur_topic['forum_id'], sef_friendly($cur_topic['forum_name'])))),
	array($cur_topic['subject'], pun_link($pun_url['topic'], array($id, sef_friendly($cur_topic['subject'])))),
);

($hook = get_hook('vt_pre_header_load')) ? eval($hook) : null;

if (!$pid)
	define('PUN_ALLOW_INDEX', 1);

define('PUN_PAGE', 'viewtopic');
require PUN_ROOT.'header.php';

?>
<div id="pun-main" class="main paged">

	<h1><span><a class="permalink" href="<?php echo pun_link($pun_url['topic'], array($id, sef_friendly($cur_topic['subject']))) ?>" rel="bookmark" title="<?php echo $lang_topic['Permalink topic'] ?>"><?php echo pun_htmlencode($cur_topic['subject']) ?></a></span></h1>

	<div class="paged-head">
		<?php echo implode("\n\t\t", $pun_page['page_post'])."\n" ?>
	</div>

	<div class="main-head">
		<p class="main-options"><?php echo implode(' ', $pun_page['main_head_options']) ?></p>
		<h2><span><?php echo $pun_page['main_info'] ?></span></h2>
	</div>

	<div id="forum<?php echo $cur_topic['forum_id'] ?>" class="main-content topic">
<?php

require PUN_ROOT.'include/parser.php';

$pun_page['item_count'] = 0;	// Keep track of post numbers

// Retrieve the posts (and their respective poster/online status)
$query = array(
	'SELECT'	=> 'u.email, u.title, u.url, u.location, u.signature, u.email_setting, u.num_posts, u.registered, u.admin_note, p.id, p.poster AS username, p.poster_id, p.poster_ip, p.poster_email, p.message, p.hide_smilies, p.posted, p.edited, p.edited_by, g.g_id, g.g_user_title, o.user_id AS is_online',
	'FROM'		=> 'posts AS p',
	'JOINS'		=> array(
		array(
			'INNER JOIN'	=> 'users AS u',
			'ON'			=> 'u.id=p.poster_id'
		),
		array(
			'INNER JOIN'	=> 'groups AS g',
			'ON'			=> 'g.g_id=u.group_id'
		),
		array(
			'LEFT JOIN'		=> 'online AS o',
			'ON'			=> '(o.user_id=u.id AND o.user_id!=1 AND o.idle=0)'
		),
	),
	'WHERE'		=> 'p.topic_id='.$id,
	'ORDER BY'	=> 'p.id',
	'LIMIT'		=> $pun_page['start_from'].','.$pun_user['disp_posts']
);

($hook = get_hook('vt_qr_get_posts')) ? eval($hook) : null;
$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
while ($cur_post = $pun_db->fetch_assoc($result))
{
	($hook = get_hook('vt_post_loop_start')) ? eval($hook) : null;

	++$pun_page['item_count'];

	$signature = '';
	$pun_page['user_ident'] = array();
	$pun_page['user_info'] = array();
	$pun_page['post_options'] = array();
	$pun_page['message'] = array();

	// Generate the post heading
	$pun_page['item_ident'] = array(
		'num'	=> '<strong>'.($pun_page['start_from'] + $pun_page['item_count']).'</strong>',
		'user'	=> '<cite>'.($cur_topic['posted'] == $cur_post['posted'] ? sprintf($lang_topic['Topic by'], pun_htmlencode($cur_post['username'])) : sprintf($lang_topic['Reply by'], pun_htmlencode($cur_post['username']))).'</cite>',
		'date'	=> '<span>'.format_time($cur_post['posted']).'</span>'
	);

	$pun_page['item_head'] = '<a class="permalink" rel="bookmark" title="'.$lang_topic['Permalink post'].'" href="'.pun_link($pun_url['post'], $cur_post['id']).'">'.implode(' ', $pun_page['item_ident']).'</a>';

	// Generate author identification
	if ($cur_post['poster_id'] > 1 && $pun_config['o_avatars'] == '1' && $pun_user['show_avatars'] != '0')
	{
		if (file_exists($pun_config['o_avatars_dir'].'/'.$cur_post['poster_id'].'.gif') && $img_size = @getimagesize($pun_config['o_avatars_dir'].'/'.$cur_post['poster_id'].'.gif'))
			$pun_page['user_ident'][] = '<img src="'.$base_url.'/'.$pun_config['o_avatars_dir'].'/'.$cur_post['poster_id'].'.gif" '.$img_size[3].' alt="" />';
		else if (file_exists($pun_config['o_avatars_dir'].'/'.$cur_post['poster_id'].'.jpg') && $img_size = @getimagesize($pun_config['o_avatars_dir'].'/'.$cur_post['poster_id'].'.jpg'))
			$pun_page['user_ident'][] = '<img src="'.$base_url.'/'.$pun_config['o_avatars_dir'].'/'.$cur_post['poster_id'].'.jpg" '.$img_size[3].' alt="" />';
		else if (file_exists($pun_config['o_avatars_dir'].'/'.$cur_post['poster_id'].'.png') && $img_size = @getimagesize($pun_config['o_avatars_dir'].'/'.$cur_post['poster_id'].'.png'))
			$pun_page['user_ident'][] = '<img src="'.$base_url.'/'.$pun_config['o_avatars_dir'].'/'.$cur_post['poster_id'].'.png" '.$img_size[3].' alt="" />';
	}

	if ($cur_post['poster_id'] > 1)
	{
		$pun_page['user_ident'][] = ($pun_user['g_view_users'] == '1') ? '<strong class="username"><a title="'.sprintf($lang_topic['Go to profile'], pun_htmlencode($cur_post['username'])).'" href="'.pun_link($pun_url['user'], $cur_post['poster_id']).'">'.pun_htmlencode($cur_post['username']).'</a></strong>' : '<strong class="username">'.pun_htmlencode($cur_post['username']).'</strong>';
		$pun_page['user_info'][] = '<li class="title"><span><strong>'.$lang_topic['Title'].'</strong> '.get_title($cur_post).'</span></li>';

		if ($cur_post['is_online'] == $cur_post['poster_id'])
			$pun_page['user_info'][] = '<li class="status"><span><strong>'.$lang_topic['Status'].'</strong> '.$lang_topic['Online'].'</span></li>';
		else
			$pun_page['user_info'][] = '<li class="status"><span><strong>'.$lang_topic['Status'].'</strong> '.$lang_topic['Offline'].'</span></li>';
	}
	else
	{
		$pun_page['user_ident'][] = '<strong class="username">'.pun_htmlencode($cur_post['username']).'</strong>';
		$pun_page['user_info'][] = '<li class="title"><span><strong>'.$lang_topic['Title'].'</strong> '.get_title($cur_post).'</span></li>';
	}

	// Generate author information
	if ($cur_post['poster_id'] > 1)
	{
		if ($pun_config['o_show_user_info'] == '1')
		{
			if ($cur_post['location'] != '')
			{
				if ($pun_config['o_censoring'] == '1')
					$cur_post['location'] = censor_words($cur_post['location']);

				$pun_page['user_info'][] = '<li><span><strong>'.$lang_topic['From'].'</strong> '.pun_htmlencode($cur_post['location']).'</span></li>';
			}

			$pun_page['user_info'][] = '<li><span><strong>'.$lang_topic['Registered'].'</strong> '.format_time($cur_post['registered'], true).'</span></li>';

			if ($pun_config['o_show_post_count'] == '1' || $pun_user['is_admmod'])
				$pun_page['user_info'][] = '<li><span><strong>'.$lang_topic['Posts'].'</strong> '.$cur_post['num_posts'].'</span></li>';
		}

		if ($pun_user['is_admmod'])
		{
			if ($cur_post['admin_note'] != '')
				$pun_page['user_info'][] = '<li><span><strong>'.$lang_topic['Note'].'</strong> '.pun_htmlencode($cur_post['admin_note']).'</span></li>';
		}
	}

	// Generate IP information for moderators/administrators
	if ($pun_user['is_admmod'])
		$pun_page['user_info'][] = '<li><span><strong>'.$lang_topic['IP'].'</strong> <a href="'.pun_link($pun_url['get_host'], $cur_post['id']).'">'.$cur_post['poster_ip'].'</a></span></li>';

	// Generate author contact details
	if ($pun_config['o_show_user_info'] == '1')
	{
		if ($cur_post['poster_id'] > 1)
		{
			if ($cur_post['url'] != '')
				$pun_page['post_options'][] = '<a class="contact external" href="'.pun_htmlencode(($pun_config['o_censoring'] == '1') ? censor_words($cur_post['url']) : $cur_post['url']).'"><span>'.sprintf($lang_topic['Visit website'], pun_htmlencode($cur_post['username'])).'</span></a>';
			if ((($cur_post['email_setting'] == '0' && !$pun_user['is_guest']) || $pun_user['is_admmod']) && $pun_user['g_send_email'] == '1')
				$pun_page['post_options'][] = '<a class="contact" href="mailto:'.$cur_post['email'].'"><span>'.$lang_common['E-mail'].'<span>&#160;'.pun_htmlencode($cur_post['username']).'</span></span></a>';
			else if ($cur_post['email_setting'] == '1' && !$pun_user['is_guest'] && $pun_user['g_send_email'] == '1')
				$pun_page['post_options'][] = '<a class="contact" href="'.pun_link($pun_url['email'], $cur_post['poster_id']).'"><span>'.$lang_common['E-mail'].'<span>&#160;'.pun_htmlencode($cur_post['username']).'</span></span></a>';
		}
		else
		{
			if ($cur_post['poster_email'] != '' && !$pun_user['is_guest'] && $pun_user['g_send_email'] == '1')
				$pun_page['post_options'][] = '<a class="contact" href="mailto:'.$cur_post['poster_email'].'"><span>'.$lang_common['E-mail'].'<span>&#160;'.pun_htmlencode($cur_post['username']).'</span></span></a>';
		}
	}

	// Generate the post options links
	if (!$pun_user['is_guest'])
	{
		$pun_page['post_options'][] = '<a href="'.pun_link($pun_url['report'], $cur_post['id']).'"><span>'.$lang_topic['Report'].'<span>&#160;'.$lang_topic['Post'].' '.($pun_page['start_from'] + $pun_page['item_count']).'</span></span></a>';

		if (!$pun_page['is_admmod'])
		{
			if ($cur_topic['closed'] == '0')
			{
				if ($cur_post['poster_id'] == $pun_user['id'])
				{
					if (($pun_page['start_from'] + $pun_page['item_count']) == 1 && $pun_user['g_delete_topics'] == '1')
						$pun_page['post_options'][] = '<a href="'.pun_link($pun_url['delete'], $cur_topic['first_post_id']).'"><span>'.$lang_topic['Delete topic'].'</span></a>';
					if (($pun_page['start_from'] + $pun_page['item_count']) > 1 && $pun_user['g_delete_posts'] == '1')
						$pun_page['post_options'][] = '<a href="'.pun_link($pun_url['delete'], $cur_post['id']).'"><span>'.$lang_topic['Delete'].'<span>&#160;'.$lang_topic['Post'].' '.($pun_page['start_from'] + $pun_page['item_count']).'</span></span></a>';
					if ($pun_user['g_edit_posts'] == '1')
						$pun_page['post_options'][] = '<a href="'.pun_link($pun_url['edit'], $cur_post['id']).'"><span>'.$lang_topic['Edit'].'<span>&#160;'.$lang_topic['Post'].' '.($pun_page['start_from'] + $pun_page['item_count']).'</span></span></a>';
				}

				if (($cur_topic['post_replies'] == '' && $pun_user['g_post_replies'] == '1') || $cur_topic['post_replies'] == '1')
					$pun_page['post_options'][] = '<a href="'.pun_link($pun_url['quote'], array($id, $cur_post['id'])).'"><span>'.$lang_topic['Quote'].'<span>&#160;'.$lang_topic['Post'].' '.($pun_page['start_from'] + $pun_page['item_count']).'</span></span></a>';
			}
		}
		else
		{
			if (($pun_page['start_from'] + $pun_page['item_count']) == 1)
				$pun_page['post_options'][] = '<a href="'.pun_link($pun_url['delete'], $cur_topic['first_post_id']).'">'.$lang_topic['Delete topic'].'</a>';
			else
				$pun_page['post_options'][] = '<a href="'.pun_link($pun_url['delete'], $cur_post['id']).'"><span>'.$lang_topic['Delete'].'<span>&#160;'.$lang_topic['Post'].' '.($pun_page['start_from'] + $pun_page['item_count']).'</span></span></a>';

			$pun_page['post_options'][] = '<a href="'.pun_link($pun_url['edit'], $cur_post['id']).'"><span>'.$lang_topic['Edit'].'<span>&#160;'.$lang_topic['Post'].' '.($pun_page['start_from'] + $pun_page['item_count']).'</span></span></a>';
			$pun_page['post_options'][] = '<a href="'.pun_link($pun_url['quote'], array($id, $cur_post['id'])).'"><span>'.$lang_topic['Quote'].'<span>&#160;'.$lang_topic['Post'].' '.($pun_page['start_from'] + $pun_page['item_count']).'</span></span></a>';
		}
	}

	// Give the post some class
	$pun_page['item_status'] = array(
		'post',
		($pun_page['item_count'] % 2 == 0) ? 'odd' : 'even'
	);

	if ($pun_page['item_count'] == 1)
		$pun_page['item_status'][] = 'firstpost';

	if (($pun_page['start_from'] + $pun_page['item_count']) == $pun_page['finish_at'])
		$pun_page['item_status'][] = 'lastpost';

	if ($cur_post['id'] == $cur_topic['first_post_id'])
		$pun_page['item_status'][] = 'topicpost';
	else
		$pun_page['item_status'][] = 'replypost';


	// Generate the post title
	if ($cur_post['id'] == $cur_topic['first_post_id'])
		$pun_page['item_subject'] = $lang_common['Topic'].': '.$cur_topic['subject'];
	else
		$pun_page['item_subject'] = $lang_common['Re'].' '.$cur_topic['subject'];

	// Perform the main parsing of the message (BBCode, smilies, censor words etc)
	$pun_page['message'][] = parse_message($cur_post['message'], $cur_post['hide_smilies']);

	if ($cur_post['edited'] != '')
		$pun_page['message'][] = '<p class="lastedit"><em>'.sprintf($lang_topic['Last edited'], pun_htmlencode($cur_post['edited_by']), format_time($cur_post['edited'])).'</em></p>';

	// Do signature parsing/caching
	if ($cur_post['signature'] != '' && $pun_user['show_sig'] != '0' && $pun_config['o_signatures'] == '1')
	{
		if (!isset($signature_cache[$cur_post['poster_id']]))
			$signature_cache[$cur_post['poster_id']] = parse_signature($cur_post['signature']);

		$pun_page['message'][] = '<div class="sig-content">'."\n\t\t\t\t\t\t\t\t".'<span class="sig-line"><!-- --></span>'."\n\t\t\t\t\t\t\t\t".$signature_cache[$cur_post['poster_id']]."\n\t\t\t\t\t\t\t".'</div>';
	}

	($hook = get_hook('vt_row_pre_display')) ? eval($hook) : null;

?>
		<div class="<?php echo implode(' ', $pun_page['item_status']) ?>">
			<div class="postmain">
				<div id="p<?php echo $cur_post['id'] ?>" class="posthead">
					<h3><?php echo $pun_page['item_head'] ?></h3>
				</div>
				<div class="postbody">
					<div class="user<?php if ($cur_post['is_online'] == $cur_post['poster_id']) echo ' online' ?>">
						<h4 class="user-ident"><?php echo implode(' ', $pun_page['user_ident']) ?></h4>
						<ul class="user-info">
							<?php echo implode("\n\t\t\t\t\t\t\t", $pun_page['user_info'])."\n" ?>
						</ul>
					</div>
					<div class="post-entry">
						<h4 class="entry-title"><?php echo $pun_page['item_subject'] ?></h4>
						<div class="entry-content">
							<?php echo implode("\n\t\t\t\t\t\t\t", $pun_page['message'])."\n" ?>
						</div>
					</div>
				</div>
<?php if (!empty($pun_page['post_options'])): ?>				<div class="postfoot">
					<div class="post-options">
						<?php echo implode(' ', $pun_page['post_options'])."\n" ?>
					</div>
				</div>
<?php endif; ?>			</div>
		</div>
<?php

}

?>
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


// Display quick post if enabled
if ($pun_config['o_quickpost'] == '1' &&
	!$pun_user['is_guest'] &&
	($cur_topic['post_replies'] == '1' || ($cur_topic['post_replies'] == '' && $pun_user['g_post_replies'] == '1')) &&
	($cur_topic['closed'] == '0' || $pun_page['is_admmod']))
{

// Setup form
$pun_page['form_action'] = pun_link($pun_url['new_reply'], $id);
$pun_page['form_attributes'] = array();

$pun_page['hidden_fields'] = array(
	'<input type="hidden" name="form_sent" value="1" />',
	'<input type="hidden" name="form_user" value="'.((!$pun_user['is_guest']) ? pun_htmlencode($pun_user['username']) : 'Guest').'" />'
);

if ($pun_user['is_admmod'])
	$pun_page['hidden_fields'][] = '<input type="hidden" name="csrf_token" value="'.generate_form_token($pun_page['form_action']).'" />';

if (!$pun_user['is_guest'] && $pun_config['o_subscriptions'] == '1' && ($pun_user['auto_notify'] == '1' || $cur_topic['is_subscribed']))
	$pun_page['hidden_fields'][] = '<input type="hidden" name="subscribe" value="1" />';

// Setup help
$pun_page['main_head_options'] = array();
if ($pun_config['p_message_bbcode'] == '1')
	$pun_page['main_head_options'][] = '<a class="exthelp" href="'.pun_link($pun_url['help'], 'bbcode').'" title="'.sprintf($lang_common['Help page'], $lang_common['BBCode']).'">'.$lang_common['BBCode'].'</a>';
if ($pun_config['p_message_img_tag'] == '1')
	$pun_page['main_head_options'][] = '<a class="exthelp" href="'.pun_link($pun_url['help'], 'img').'" title="'.sprintf($lang_common['Help page'], $lang_common['Images']).'">'.$lang_common['Images'].'</a>';
if ($pun_config['o_smilies'] == '1')
	$pun_page['main_head_options'][] = '<a class="exthelp" href="'.pun_link($pun_url['help'], 'smilies').'" title="'.sprintf($lang_common['Help page'], $lang_common['Smilies']).'">'.$lang_common['Smilies'].'</a>';

($hook = get_hook('vt_quickpost_pre_display')) ? eval($hook) : null;

?>
<div id="pun-qpost" class="main">

	<div class="main-head">
		<h2><span><?php echo $lang_topic['Quick post'] ?></span></h2>
<?php if (!empty($pun_page['main_head_options'])): ?>		<p class="main-options"><?php printf($lang_common['You may use'], implode(' ', $pun_page['main_head_options'])) ?></p>
<?php endif; ?>	</div>

	<div class="main-content frm">
		<div id="req-msg" class="frm-warn">
			<p class="important"><?php printf($lang_common['Required warn'], '<em class="req-text">'.$lang_common['Required'].'</em>') ?></p>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $pun_page['form_action'] ?>"<?php if (!empty($pun_page['form_attributes'])) echo ' '.implode(' ', $pun_page['form_attributes']) ?>>
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $pun_page['hidden_fields'])."\n" ?>
			</div>
<?php ($hook = get_hook('vt_quickpost_pre_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set1">
				<legend class="frm-legend"><strong><?php echo $lang_common['Write message legend'] ?></strong></legend>
<?php ($hook = get_hook('vt_quickpost_fieldset_start')) ? eval($hook) : null; ?>
				<div class="frm-fld text textarea required">
					<label for="fld1">
						<span class="fld-label"><?php echo $lang_common['Write message'] ?></span><br />
						<span class="fld-input"><textarea id="fld1" name="req_message" rows="7" cols="95"></textarea></span>
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
					</label>
				</div>
			</fieldset>
<?php ($hook = get_hook('vt_quickpost_post_fieldset')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="submit" value="<?php echo $lang_common['Submit'] ?>" accesskey="s" title="<?php echo $lang_common['Submit title'] ?>" /></span>
				<span class="submit"><input type="submit" name="preview" value="<?php echo $lang_common['Preview'] ?>" accesskey="p" title="<?php echo $lang_common['Preview title'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

}

// Increment "num_views" for topic
if ($pun_config['o_topic_views'] == '1')
{
	$query = array(
		'UPDATE'	=> 'topics',
		'SET'		=> 'num_views=num_views+1',
		'WHERE'		=> 'id='.$id,
		'PARAMS'	=> array(
			'LOW_PRIORITY'	=> 1	// MySQL only
		)
	);

	($hook = get_hook('vt_qr_increment_num_views')) ? eval($hook) : null;
	$pun_db->query_build($query) or error(__FILE__, __LINE__);
}

$forum_id = $cur_topic['forum_id'];

($hook = get_hook('vt_end')) ? eval($hook) : null;

require PUN_ROOT.'footer.php';
