<?php
/**
 * Lists the posts in the specified topic.
 *
 * @copyright Copyright (C) 2008 FluxBB.org, based on code copyright (C) 2002-2008 PunBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package FluxBB
 */


if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', './');
require FORUM_ROOT.'include/common.php';

($hook = get_hook('vt_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

if ($forum_user['g_read_board'] == '0')
	message($lang_common['No view']);

// Load the viewtopic.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/topic.php';


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

	($hook = get_hook('vt_qr_get_post_info')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	if (!$forum_db->num_rows($result))
		message($lang_common['Bad request']);

	list($id, $posted) = $forum_db->fetch_row($result);

	// Determine on what page the post is located (depending on $forum_user['disp_posts'])
	$query = array(
		'SELECT'	=> 'COUNT(p.id)',
		'FROM'		=> 'posts AS p',
		'WHERE'		=> 'p.topic_id='.$id.' AND p.posted<'.$posted
	);

	($hook = get_hook('vt_qr_get_post_page')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$num_posts = $forum_db->result($result) + 1;

	$_GET['p'] = ceil($num_posts / $forum_user['disp_posts']);
}

// If action=new, we redirect to the first new post (if any)
else if ($action == 'new' && !$forum_user['is_guest'])
{
	// We need to check if this topic has been viewed recently by the user
	$tracked_topics = get_tracked_topics();
	$last_viewed = isset($tracked_topics['topics'][$id]) ? $tracked_topics['topics'][$id] : $forum_user['last_visit'];

	($hook = get_hook('vt_find_new_post')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	$query = array(
		'SELECT'	=> 'MIN(p.id)',
		'FROM'		=> 'posts AS p',
		'WHERE'		=> 'p.topic_id='.$id.' AND p.posted>'.$last_viewed
	);

	($hook = get_hook('vt_qr_get_first_new_post')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$first_new_post_id = $forum_db->result($result);

	if ($first_new_post_id)
		header('Location: '.str_replace('&amp;', '&', forum_link($forum_url['post'], $first_new_post_id)));
	else	// If there is no new post, we go to the last post
		header('Location: '.str_replace('&amp;', '&', forum_link($forum_url['topic_last_post'], $id)));

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

	($hook = get_hook('vt_qr_get_last_post')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$last_post_id = $forum_db->result($result);

	if ($last_post_id)
	{
		header('Location: '.str_replace('&amp;', '&', forum_link($forum_url['post'], $last_post_id)));
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
			'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
		)
	),
	'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND t.id='.$id.' AND t.moved_to IS NULL'
);

if (!$forum_user['is_guest'])
{
	$query['SELECT'] .= ', s.user_id AS is_subscribed';
	$query['JOINS'][] = array(
		'LEFT JOIN'	=> 'subscriptions AS s',
		'ON'		=> '(t.id=s.topic_id AND s.user_id='.$forum_user['id'].')'
	);
}

($hook = get_hook('vt_qr_get_topic_info')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
if (!$forum_db->num_rows($result))
	message($lang_common['Bad request']);

$cur_topic = $forum_db->fetch_assoc($result);

($hook = get_hook('vt_modify_topic_info')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

// Sort out who the moderators are and if we are currently a moderator (or an admin)
$mods_array = ($cur_topic['moderators'] != '') ? unserialize($cur_topic['moderators']) : array();
$forum_page['is_admmod'] = ($forum_user['g_id'] == FORUM_ADMIN || ($forum_user['g_moderator'] == '1' && array_key_exists($forum_user['username'], $mods_array))) ? true : false;

// Can we or can we not post replies?
if ($cur_topic['closed'] == '0' || $forum_page['is_admmod'])
	$forum_user['may_post'] = (($cur_topic['post_replies'] == '' && $forum_user['g_post_replies'] == '1') || $cur_topic['post_replies'] == '1' || $forum_page['is_admmod']) ? true : false;
else
	$forum_user['may_post'] = false;

// Add/update this topic in our list of tracked topics
if (!$forum_user['is_guest'])
{
	$tracked_topics = get_tracked_topics();
	$tracked_topics['topics'][$id] = time();
	set_tracked_topics($tracked_topics);
}

// Determine the post offset (based on $_GET['p'])
$forum_page['num_pages'] = ceil(($cur_topic['num_replies'] + 1) / $forum_user['disp_posts']);
$forum_page['page'] = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $forum_page['num_pages']) ? 1 : $_GET['p'];
$forum_page['start_from'] = $forum_user['disp_posts'] * ($forum_page['page'] - 1);
$forum_page['finish_at'] = min(($forum_page['start_from'] + $forum_user['disp_posts']), ($cur_topic['num_replies'] + 1));
$forum_page['page_info'] =  generate_page_info($lang_topic['Posts'], ($forum_page['start_from'] + 1), ($cur_topic['num_replies'] + 1));

($hook = get_hook('vt_modify_page_details')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

// Navigation links for header and page numbering for title/meta description
if ($forum_page['page'] < $forum_page['num_pages'])
{
	$forum_page['nav']['last'] = '<link rel="last" href="'.forum_sublink($forum_url['topic'], $forum_url['page'], $forum_page['num_pages'], array($id, sef_friendly($cur_topic['subject']))).'" title="'.$lang_common['Page'].' '.$forum_page['num_pages'].'" />';
	$forum_page['nav']['next'] = '<link rel="next" href="'.forum_sublink($forum_url['topic'], $forum_url['page'], ($forum_page['page'] + 1), array($id, sef_friendly($cur_topic['subject']))).'" title="'.$lang_common['Page'].' '.($forum_page['page'] + 1).'" />';
}
if ($forum_page['page'] > 1)
{
	$forum_page['nav']['prev'] = '<link rel="prev" href="'.forum_sublink($forum_url['topic'], $forum_url['page'], ($forum_page['page'] - 1), array($id, sef_friendly($cur_topic['subject']))).'" title="'.$lang_common['Page'].' '.($forum_page['page'] - 1).'" />';
	$forum_page['nav']['first'] = '<link rel="first" href="'.forum_link($forum_url['topic'], array($id, sef_friendly($cur_topic['subject']))).'" title="'.$lang_common['Page'].' 1" />';
}

if ($forum_config['o_censoring'] == '1')
	$cur_topic['subject'] = censor_words($cur_topic['subject']);

// Generate paging and posting links
$forum_page['page_post']['paging'] = '<p class="paging"><span class="pages">'.$lang_common['Pages'].'</span> '.paginate($forum_page['num_pages'], $forum_page['page'], $forum_url['topic'], $lang_common['Paging separator'], array($id, sef_friendly($cur_topic['subject']))).'</p>';

if ($forum_user['may_post'])
	$forum_page['page_post']['posting'] = '<p class="posting"><a class="newpost" href="'.forum_link($forum_url['new_reply'], $id).'"><span>'.$lang_topic['Post reply'].'</span></a></p>';
else if (!$forum_user['may_post'] && !$forum_user['is_guest'] && $cur_topic['closed'] != '1')
	$forum_page['page_post']['posting'] = '<p class="posting">'.$lang_topic['No permission'].'</p>';
if (!$forum_user['may_post'] && $forum_user['is_guest'])
	$forum_page['page_post']['posting'] = '<p class="posting">'.sprintf($lang_topic['Login to post'], '<a href="'.forum_link($forum_url['login']).'">'.$lang_common['login'].'</a>', '<a href="'.forum_link($forum_url['register']).'">'.$lang_common['register'].'</a>').'</p>';

// Setup main options
$forum_page['main_options_head'] = $lang_topic['Topic options'];
$forum_page['main_options'] = array();
$forum_page['main_options']['rss'] = '<span class="feed'.(empty($forum_page['main_options']) ? ' item1' : '').'"><a class="feed-option" href="'.forum_link($forum_url['topic_rss'], $id).'">'.$lang_topic['RSS topic feed'].'</a></span>';

if (!$forum_user['is_guest'] && $forum_config['o_subscriptions'] == '1')
{
	if ($cur_topic['is_subscribed'])
		$forum_page['main_options']['unsubscribe'] = '<span><a class="sub-option" href="'.forum_link($forum_url['unsubscribe'], array($id, generate_form_token('unsubscribe'.$id.$forum_user['id']))).'"><em>'.$lang_topic['Unsubscribe'].'</em></a></span>';
	else
		$forum_page['main_options']['subscribe'] = '<span><a class="sub-option" href="'.forum_link($forum_url['subscribe'], array($id, generate_form_token('subscribe'.$id.$forum_user['id']))).'" title="'.$lang_topic['Subscribe info'].'">'.$lang_topic['Subscribe'].'</a></span>';
}

if ($forum_page['is_admmod'])
{
	$forum_page['main_options']['move'] = '<span><a class="mod-option" href="'.forum_link($forum_url['move'], array($cur_topic['forum_id'], $id)).'">'.$lang_topic['Move'].'</a></span>';
	$forum_page['main_options']['delete'] = '<span><a class="mod-option" href="'.forum_link($forum_url['delete'], $cur_topic['first_post_id']).'">'.$lang_topic['Delete topic'].'</a></span>';
	$forum_page['main_options']['closed'] = (($cur_topic['closed'] == '1') ? '<span><a class="mod-option" href="'.forum_link($forum_url['open'], array($cur_topic['forum_id'], $id, generate_form_token('open'.$id))).'">'.$lang_topic['Open'].'</a></span>' : '<span><a class="mod-option" href="'.forum_link($forum_url['close'], array($cur_topic['forum_id'], $id, generate_form_token('close'.$id))).'">'.$lang_topic['Close'].'</a></span>');
	$forum_page['main_options']['sticky'] = (($cur_topic['sticky'] == '1') ? '<span><a class="mod-option" href="'.forum_link($forum_url['unstick'], array($cur_topic['forum_id'], $id, generate_form_token('unstick'.$id))).'">'.$lang_topic['Unstick'].'</a></span>' : '<span><a class="mod-option" href="'.forum_link($forum_url['stick'], array($cur_topic['forum_id'], $id, generate_form_token('stick'.$id))).'">'.$lang_topic['Stick'].'</a></span>');

	if ($cur_topic['num_replies'] != 0)
		$forum_page['main_options']['moderate_topic'] = '<span><a class="mod-option" href="'.forum_sublink($forum_url['moderate_topic'], $forum_url['page'], $forum_page['page'], array($cur_topic['forum_id'], $id)).'">'.$lang_topic['Moderate topic'].'</a></span>';
}

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($cur_topic['forum_name'], forum_link($forum_url['forum'], array($cur_topic['forum_id'], sef_friendly($cur_topic['forum_name'])))),
	$cur_topic['subject']
);

// Setup main heading
$forum_page['main_head'] = (($cur_topic['closed'] == '1') ? $lang_topic['Topic closed'].' ' : '').'<a class="permalink" href="'.forum_link($forum_url['topic'], array($id, sef_friendly($cur_topic['subject']))).'" rel="bookmark" title="'.$lang_topic['Permalink topic'].'">'.forum_htmlencode($cur_topic['subject']).'</a>';

($hook = get_hook('vt_pre_header_load')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

// Allow indexing if this isn't a permalink and it isn't a link with p=1
if (!$pid && (!isset($_GET['p']) || $forum_page['page'] != 1))
	define('FORUM_ALLOW_INDEX', 1);

define('FORUM_PAGE', 'viewtopic');
define('FORUM_PAGE_TYPE', 'topic');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('vt_main_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
	<div class="main-pagehead">
		<h2 class="hn"><span><?php echo $forum_page['page_info'] ?></span></h2>
	</div>
	<div id="forum<?php echo $cur_topic['forum_id'] ?>" class="main-content main-topic">
<?php

if (!defined('FORUM_PARSER_LOADED'))
	require FORUM_ROOT.'include/parser.php';

$forum_page['item_count'] = 0;	// Keep track of post numbers

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
	'LIMIT'		=> $forum_page['start_from'].','.$forum_user['disp_posts']
);

($hook = get_hook('vt_qr_get_posts')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

$user_data_cache = array();
while ($cur_post = $forum_db->fetch_assoc($result))
{
	($hook = get_hook('vt_post_loop_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	++$forum_page['item_count'];

	$forum_page['user_ident'] = array();
	$forum_page['user_info'] = array();
	$forum_page['post_options'] = array();
	$forum_page['post_contacts'] = array();
	$forum_page['post_actions'] = array();
	$forum_page['message'] = array();

	// Generate the post heading
	$forum_page['item_ident'] = array(
		'num'	=> '<strong>'.forum_number_format($forum_page['start_from'] + $forum_page['item_count']).'</strong>',
		'user'	=> '<cite>'.($cur_topic['posted'] == $cur_post['posted'] ? sprintf($lang_topic['Topic by'], forum_htmlencode($cur_post['username'])) : sprintf($lang_topic['Reply by'], forum_htmlencode($cur_post['username']))).'</cite>',
		'date'	=> '<span>'.format_time($cur_post['posted']).'</span>'
	);

	($hook = get_hook('vt_row_pre_item_head')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	$forum_page['item_head'] = '<a class="permalink" rel="bookmark" title="'.$lang_topic['Permalink post'].'" href="'.forum_link($forum_url['post'], $cur_post['id']).'">'.implode(' ', $forum_page['item_ident']).'</a>';

	if (isset($user_data_cache[$cur_post['poster_id']]['user_ident']))
		$forum_page['user_ident'] = $user_data_cache[$cur_post['poster_id']]['user_ident'];
	else
	{
		// Generate author identification
		if ($cur_post['poster_id'] > 1 && $forum_config['o_avatars'] == '1' && $forum_user['show_avatars'] != '0')
		{
			$forum_page['avatar_markup'] = generate_avatar_markup($cur_post['poster_id']);

			if (!empty($forum_page['avatar_markup']))
				$forum_page['user_ident']['avatar'] = $forum_page['avatar_markup'];
		}

		if ($cur_post['poster_id'] > 1)
		{
			$forum_page['user_ident']['username'] = ($forum_user['g_view_users'] == '1') ? '<strong class="username"><a title="'.sprintf($lang_topic['Go to profile'], forum_htmlencode($cur_post['username'])).'" href="'.forum_link($forum_url['user'], $cur_post['poster_id']).'">'.forum_htmlencode($cur_post['username']).'</a></strong>' : '<strong class="username">'.forum_htmlencode($cur_post['username']).'</strong>';
			$forum_page['user_ident']['usertitle'] = '<span class="usertitle">'.get_title($cur_post).'</span>';

			if ($cur_post['is_online'] == $cur_post['poster_id'])
				$forum_page['user_status'] = $lang_topic['Online'];
			else
				$forum_page['user_status'] = $lang_topic['Offline'];

			$forum_page['user_ident']['status'] = '<small class="userstatus">'.$forum_page['user_status'].'</small>';
		}
		else
		{
			$forum_page['user_ident']['username'] = '<strong class="username">'.forum_htmlencode($cur_post['username']).'</strong>';
			$forum_page['user_ident']['usertitle'] = '<span class="usertitle">'.get_title($cur_post).'</span>';
		}
	}

	if (isset($user_data_cache[$cur_post['poster_id']]['user_info']))
		$forum_page['user_info'] = $user_data_cache[$cur_post['poster_id']]['user_info'];
	else
	{
		// Generate author information
		if ($cur_post['poster_id'] > 1)
		{
			if ($forum_config['o_show_user_info'] == '1')
			{
				if ($cur_post['location'] != '')
				{
					if ($forum_config['o_censoring'] == '1')
						$cur_post['location'] = censor_words($cur_post['location']);

					$forum_page['user_info']['from'] = '<li><span>'.$lang_topic['From'].' <strong> '.forum_htmlencode($cur_post['location']).'</strong></span></li>';
				}

				$forum_page['user_info']['registered'] = '<li><span>'.$lang_topic['Registered'].' <strong> '.format_time($cur_post['registered'], 1).'</strong></span></li>';

				if ($forum_config['o_show_post_count'] == '1' || $forum_user['is_admmod'])
					$forum_page['user_info']['posts'] = '<li><span>'.$lang_topic['Posts'].' <strong> '.forum_number_format($cur_post['num_posts']).'</strong></span></li>';
			}

			if ($forum_user['is_admmod'])
			{
				if ($cur_post['admin_note'] != '')
					$forum_page['user_info']['note'] = '<li><span>'.$lang_topic['Note'].' <strong> '.forum_htmlencode($cur_post['admin_note']).'</strong></span></li>';
			}
		}
	}

	// Generate IP information for moderators/administrators
	if ($forum_user['is_admmod'])
		$forum_page['user_info']['ip'] = '<li><span>'.$lang_topic['IP'].' <a href="'.forum_link($forum_url['get_host'], $cur_post['id']).'">'.$cur_post['poster_ip'].'</a></span></li>';

	// Generate author contact details
	if ($forum_config['o_show_user_info'] == '1')
	{
		if (isset($user_data_cache[$cur_post['poster_id']]['post_contacts']))
			$forum_page['post_contacts'] = $user_data_cache[$cur_post['poster_id']]['post_contacts'];
		else
		{
			if ($cur_post['poster_id'] > 1)
			{
				if ($cur_post['url'] != '')
					$forum_page['post_contacts']['url'] = '<span class="user-url'.(!empty($forum_page['post_contacts']) ? ' item1' : '').'"><a class="external" href="'.forum_htmlencode(($forum_config['o_censoring'] == '1') ? censor_words($cur_post['url']) : $cur_post['url']).'">'.sprintf($lang_topic['Visit website'], '<span>'.sprintf($lang_topic['User possessive'], forum_htmlencode($cur_post['username'])).'</span>').'</a></span>';
				if ((($cur_post['email_setting'] == '0' && !$forum_user['is_guest']) || $forum_user['is_admmod']) && $forum_user['g_send_email'] == '1')
					$forum_page['post_contacts']['email'] = '<span class="user-email'.(!empty($forum_page['post_contacts']) ? ' item1' : '').'"><a href="mailto:'.forum_htmlencode($cur_post['email']).'">'.$lang_topic['E-mail'].'<span>&#160;'.forum_htmlencode($cur_post['username']).'</span></a></span>';
				else if ($cur_post['email_setting'] == '1' && !$forum_user['is_guest'] && $forum_user['g_send_email'] == '1')
					$forum_page['post_contacts']['email'] = '<span class="user-email'.(!empty($forum_page['post_contacts']) ? ' item1' : '').'"><a href="'.forum_link($forum_url['email'], $cur_post['poster_id']).'">'.$lang_topic['E-mail'].'<span>&#160;'.forum_htmlencode($cur_post['username']).'</span></a></span>';
			}
			else
			{
				if ($cur_post['poster_email'] != '' && !$forum_user['is_guest'] && $forum_user['g_send_email'] == '1')
					$forum_page['post_contacts']['email'] = '<span class="user-email'.(!empty($forum_page['post_contacts']) ? ' item1' : '').'"><a href="mailto:'.forum_htmlencode($cur_post['poster_email']).'">'.$lang_topic['E-mail'].'<span>&#160;'.forum_htmlencode($cur_post['username']).'</span></a></span>';
			}
		}

		if (!empty($forum_page['post_contacts']))
			$forum_page['post_options']['contacts'] = '<p class="post-contacts">'.implode(' ', $forum_page['post_contacts']).'</p>';
	}

	// Generate the post options links
	if (!$forum_user['is_guest'])
	{
		$forum_page['post_actions']['report'] = '<span class="report-post'.(!empty($forum_page['post_actions']) ? ' item1' : '').'"><a href="'.forum_link($forum_url['report'], $cur_post['id']).'">'.$lang_topic['Report'].'<span> '.$lang_topic['Post'].' '.forum_number_format($forum_page['start_from'] + $forum_page['item_count']).'</span></a></span>';

		if (!$forum_page['is_admmod'])
		{
			if ($cur_topic['closed'] == '0')
			{
				if ($cur_post['poster_id'] == $forum_user['id'])
				{
					if (($forum_page['start_from'] + $forum_page['item_count']) == 1 && $forum_user['g_delete_topics'] == '1')
						$forum_page['post_actions']['delete'] = '<span class="delete-topic'.(!empty($forum_page['post_actions']) ? ' item1' : '').'"><a href="'.forum_link($forum_url['delete'], $cur_topic['first_post_id']).'">'.$lang_topic['Delete topic'].'</a></span>';
					if (($forum_page['start_from'] + $forum_page['item_count']) > 1 && $forum_user['g_delete_posts'] == '1')
						$forum_page['post_actions']['delete'] = '<span class="delete-post'.(!empty($forum_page['post_actions']) ? ' item1' : '').'"><a href="'.forum_link($forum_url['delete'], $cur_post['id']).'">'.$lang_topic['Delete'].'<span> '.$lang_topic['Post'].' '.forum_number_format($forum_page['start_from'] + $forum_page['item_count']).'</span></a></span>';
					if ($forum_user['g_edit_posts'] == '1')
						$forum_page['post_actions']['edit'] = '<span class="edit-post'.(!empty($forum_page['post_actions']) ? ' item1' : '').'"><a href="'.forum_link($forum_url['edit'], $cur_post['id']).'">'.$lang_topic['Edit'].'<span> '.$lang_topic['Post'].' '.forum_number_format($forum_page['start_from'] + $forum_page['item_count']).'</span></a></span>';
				}

				if (($cur_topic['post_replies'] == '' && $forum_user['g_post_replies'] == '1') || $cur_topic['post_replies'] == '1')
					$forum_page['post_actions']['quote'] = '<span class="quote-post'.(!empty($forum_page['post_actions']) ? ' item1' : '').'"><a href="'.forum_link($forum_url['quote'], array($id, $cur_post['id'])).'">'.$lang_topic['Quote'].'<span> '.$lang_topic['Post'].' '.forum_number_format($forum_page['start_from'] + $forum_page['item_count']).'</span></a></span>';
			}
		}
		else
		{
			if (($forum_page['start_from'] + $forum_page['item_count']) == 1)
				$forum_page['post_actions']['delete'] = '<span class="delete-topic'.(!empty($forum_page['post_actions']) ? ' item1' : '').'"><a href="'.forum_link($forum_url['delete'], $cur_topic['first_post_id']).'">'.$lang_topic['Delete topic'].'</a></span>';
			else
				$forum_page['post_actions']['delete'] = '<span class="delete-post'.(!empty($forum_page['post_actions']) ? ' item1' : '').'"><a href="'.forum_link($forum_url['delete'], $cur_post['id']).'">'.$lang_topic['Delete'].'<span> '.$lang_topic['Post'].' '.forum_number_format($forum_page['start_from'] + $forum_page['item_count']).'</span></a></span>';

			$forum_page['post_actions']['edit'] = '<span class="edit-post'.(!empty($forum_page['post_actions']) ? ' item1' : '').'"><a href="'.forum_link($forum_url['edit'], $cur_post['id']).'">'.$lang_topic['Edit'].'<span> '.$lang_topic['Post'].' '.forum_number_format($forum_page['start_from'] + $forum_page['item_count']).'</span></a></span>';
			$forum_page['post_actions']['quote'] = '<span class="quote-post'.(!empty($forum_page['post_actions']) ? ' item1' : '').'"><a href="'.forum_link($forum_url['quote'], array($id, $cur_post['id'])).'">'.$lang_topic['Quote'].'<span> '.$lang_topic['Post'].' '.forum_number_format($forum_page['start_from'] + $forum_page['item_count']).'</span></a></span>';
		}
	}
	else
	{
		if ($cur_topic['closed'] == '0')
		{
			if (($cur_topic['post_replies'] == '' && $forum_user['g_post_replies'] == '1') || $cur_topic['post_replies'] == '1')
				$forum_page['post_actions']['quote'] = '<span class="report-post'.(!empty($forum_page['post_actions']) ? ' item1' : '').'"><a href="'.forum_link($forum_url['quote'], array($id, $cur_post['id'])).'">'.$lang_topic['Quote'].'<span> '.$lang_topic['Post'].' '.forum_number_format($forum_page['start_from'] + $forum_page['item_count']).'</span></a></span>';
		}
	}

	($hook = get_hook('vt_row_modify_post_actions')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	if (!empty($forum_page['post_actions']))
		$forum_page['post_options']['actions'] = '<p class="post-actions">'.implode(' ', $forum_page['post_actions']).'</p>';

	// Give the post some class
	$forum_page['item_status'] = array(
		'post',
		($forum_page['item_count'] % 2 != 0) ? 'odd' : 'even'
	);

	if ($forum_page['item_count'] == 1)
		$forum_page['item_status']['firstpost'] = 'firstpost';

	if (($forum_page['start_from'] + $forum_page['item_count']) == $forum_page['finish_at'])
		$forum_page['item_status']['lastpost'] = 'lastpost';

	if ($cur_post['id'] == $cur_topic['first_post_id'])
		$forum_page['item_status']['topicpost'] = 'topicpost';
	else
		$forum_page['item_status']['replypost'] = 'replypost';


	// Generate the post title
	if ($cur_post['id'] == $cur_topic['first_post_id'])
		$forum_page['item_subject'] = sprintf($lang_topic['Topic title'], $cur_topic['subject']);
	else
		$forum_page['item_subject'] = sprintf($lang_topic['Reply title'], $cur_topic['subject']);

	$forum_page['item_subject'] = forum_htmlencode($forum_page['item_subject']);

	// Perform the main parsing of the message (BBCode, smilies, censor words etc)
	$forum_page['message']['message'] = parse_message($cur_post['message'], $cur_post['hide_smilies']);

	if ($cur_post['edited'] != '')
		$forum_page['message']['edited'] = '<p class="lastedit"><em>'.sprintf($lang_topic['Last edited'], forum_htmlencode($cur_post['edited_by']), format_time($cur_post['edited'])).'</em></p>';

	// Do signature parsing/caching
	if ($cur_post['signature'] != '' && $forum_user['show_sig'] != '0' && $forum_config['o_signatures'] == '1')
	{
		if (!isset($signature_cache[$cur_post['poster_id']]))
			$signature_cache[$cur_post['poster_id']] = parse_signature($cur_post['signature']);

		$forum_page['message']['signature'] = '<div class="sig-content"><span class="sig-line"><!-- --></span>'.$signature_cache[$cur_post['poster_id']].'</div>';
	}

	($hook = get_hook('vt_row_pre_display')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	// Do user data caching for the post
	if ($cur_post['poster_id'] > 1 && !isset($user_data_cache[$cur_post['poster_id']]))
	{
		$user_data_cache[$cur_post['poster_id']] = array(
			'user_ident'	=> $forum_page['user_ident'],
			'user_info'		=> $forum_page['user_info'],
			'post_contacts'	=> $forum_page['post_contacts']
		);

		($hook = get_hook('vt_row_add_user_data_cache')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	}

?>
		<div class="<?php echo implode(' ', $forum_page['item_status']) ?>">
			<div id="p<?php echo $cur_post['id'] ?>" class="posthead">
				<h3 class="hn"><?php echo $forum_page['item_head'] ?></h3>
			</div>
			<div class="postbody">
				<div class="post-author user<?php echo ($cur_post['is_online'] == $cur_post['poster_id']) ? ' online' : '' ?>">
					<h4 class="user-ident"><?php echo implode('<br />', $forum_page['user_ident']) ?></h4>
					<ul class="user-info">
						<?php echo implode("\n\t\t\t\t\t\t", $forum_page['user_info'])."\n" ?>
					</ul>
				</div>
				<div class="post-entry">
					<h4 class="entry-title hn"><?php echo $forum_page['item_subject'] ?></h4>
					<div class="entry-content">
						<?php echo implode("\n\t\t\t\t\t\t", $forum_page['message'])."\n" ?>
					</div>
				</div>
			</div>
<?php if (!empty($forum_page['post_options'])): ?>			<div class="postfoot">
				<div class="post-options">
					<?php echo implode("\n\t\t\t\t\t", $forum_page['post_options'])."\n" ?>
				</div>
			</div>
<?php endif; ?>		</div>
<?php

}

?>
	</div>
<?php

($hook = get_hook('vt_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->



// Display quick post if enabled
if ($forum_config['o_quickpost'] == '1' &&
	!$forum_user['is_guest'] &&
	($cur_topic['post_replies'] == '1' || ($cur_topic['post_replies'] == '' && $forum_user['g_post_replies'] == '1')) &&
	($cur_topic['closed'] == '0' || $forum_page['is_admmod']))
{

// START SUBST - <!-- forum_qpost -->
ob_start();

($hook = get_hook('vt_qpost_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

// Setup form
$forum_page['form_action'] = forum_link($forum_url['new_reply'], $id);
$forum_page['form_attributes'] = array();

$forum_page['hidden_fields'] = array(
	'form_sent'		=> '<input type="hidden" name="form_sent" value="1" />',
	'form_user'		=> '<input type="hidden" name="form_user" value="'.((!$forum_user['is_guest']) ? forum_htmlencode($forum_user['username']) : 'Guest').'" />',
	'csrf_token'	=> '<input type="hidden" name="csrf_token" value="'.generate_form_token($forum_page['form_action']).'" />'
);

if (!$forum_user['is_guest'] && $forum_config['o_subscriptions'] == '1' && ($forum_user['auto_notify'] == '1' || $cur_topic['is_subscribed']))
	$forum_page['hidden_fields']['subscribe'] = '<input type="hidden" name="subscribe" value="1" />';

// Setup help
$forum_page['main_head_options'] = array();
if ($forum_config['p_message_bbcode'] == '1')
	$forum_page['text_options']['bbcode'] = '<span'.(empty($forum_page['text_options']) ? ' class="item1"' : '').'><a class="exthelp" href="'.forum_link($forum_url['help'], 'bbcode').'" title="'.sprintf($lang_common['Help page'], $lang_common['BBCode']).'">'.$lang_common['BBCode'].'</a></span>';
if ($forum_config['p_message_img_tag'] == '1')
	$forum_page['text_options']['img'] = '<span><a class="exthelp" href="'.forum_link($forum_url['help'], 'img').'" title="'.sprintf($lang_common['Help page'], $lang_common['Images']).'">'.$lang_common['Images'].'</a></span>';
if ($forum_config['o_smilies'] == '1')
	$forum_page['text_options']['smilies'] = '<span><a class="exthelp" href="'.forum_link($forum_url['help'], 'smilies').'" title="'.sprintf($lang_common['Help page'], $lang_common['Smilies']).'">'.$lang_common['Smilies'].'</a></span>';

($hook = get_hook('vt_quickpost_pre_display')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
<div class="main-subhead">
	<h2 class="hn"><span><?php echo $lang_topic['Quick post'] ?></span></h2>
</div>
<div id="brd-qpost" class="main-content main-frm">
<?php if (!empty($forum_page['text_options'])) echo "\t".'<p class="content-options options">'.sprintf($lang_common['You may use'], implode(' ', $forum_page['text_options'])).'</p>'."\n" ?>
	<div id="req-msg" class="req-warn">
		<p class="important"><?php printf($lang_common['Required warn'], '<em>'.$lang_common['Reqmark'].'</em>') ?></p>
	</div>
	<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>"<?php if (!empty($forum_page['form_attributes'])) echo ' '.implode(' ', $forum_page['form_attributes']) ?>>
		<div class="hidden">
			<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
		</div>
<?php ($hook = get_hook('vt_quickpost_pre_fieldset')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
		<fieldset class="frm-group group1">
			<legend class="group-legend"><strong><?php echo $lang_common['Write message legend'] ?></strong></legend>
<?php ($hook = get_hook('vt_quickpost_fieldset_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			<div class="sf-set set1">
				<div class="sf-box textarea required">
					<label for="fld1"><span><em><?php echo $lang_common['Reqmark'] ?></em> <?php echo $lang_common['Write message'] ?></span></label><br />
					<span class="fld-input"><textarea id="fld1" name="req_message" rows="7" cols="95"></textarea></span>
				</div>
			</div>
		</fieldset>
<?php ($hook = get_hook('vt_quickpost_post_fieldset')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
		<div class="frm-buttons">
			<span class="submit"><input type="submit" name="submit" value="<?php echo $lang_common['Submit'] ?>" /></span>
			<span class="submit"><input type="submit" name="preview" value="<?php echo $lang_common['Preview'] ?>" /></span>
		</div>
	</form>
</div>
<?php

($hook = get_hook('vt_quickpost_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_qpost -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_qpost -->

}

// Increment "num_views" for topic
if ($forum_config['o_topic_views'] == '1')
{
	$query = array(
		'UPDATE'	=> 'topics',
		'SET'		=> 'num_views=num_views+1',
		'WHERE'		=> 'id='.$id,
	);

	($hook = get_hook('vt_qr_increment_num_views')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);
}

$forum_id = $cur_topic['forum_id'];

require FORUM_ROOT.'footer.php';
