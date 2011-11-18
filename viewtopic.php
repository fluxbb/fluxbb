<?php

/**
 * Copyright (C) 2008-2011 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';


if ($pun_user['g_read_board'] == '0')
	message($lang->t('No view'));


$action = isset($_GET['action']) ? $_GET['action'] : null;
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$pid = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
if ($id < 1 && $pid < 1)
	message($lang->t('Bad request'));

// Load the viewtopic.php language file
$lang->load('topic');


// If a post ID is specified we determine topic ID and page number so we can redirect to the correct message
if ($pid)
{
	$query = $db->select(array('topic_id' => 'p.topic_id', 'posted' => 'p.posted'), 'posts AS p');
	$query->where = 'id = :pid';

	$params = array(':pid' => $pid);

	$result = $query->run($params);
	if (empty($result))
		message($lang->t('Bad request'));

	$id = $result[0]['topic_id'];
	$posted = $result[0]['posted'];
	unset ($result, $query, $params);

	// Determine on what page the post is located (depending on $forum_user['disp_posts'])
	$query = $db->select(array('num_posts' => '(COUNT(p.id) + 1) AS num_posts'), 'posts AS p');
	$query->where = 'p.topic_id = :tid AND p.posted < :posted';

	$params = array(':tid' => $id, ':posted' => $posted);

	$result = $query->run($params);
	$num_posts = $result[0]['num_posts'];

	unset ($result, $query, $params);
	$_GET['p'] = ceil($num_posts / $pun_user['disp_posts']);
}

// If action=new, we redirect to the first new post (if any)
else if ($action == 'new')
{
	if (!$pun_user['is_guest'])
	{
		// We need to check if this topic has been viewed recently by the user
		$tracked_topics = get_tracked_topics();
		$last_viewed = isset($tracked_topics['topics'][$id]) ? $tracked_topics['topics'][$id] : $pun_user['last_visit'];

		$query = $db->select(array('new_pid' => 'MIN(p.id) AS new_pid'), 'posts AS p');
		$query->where = 'p.topic_id = :tid AND p.posted > :last_viewed';

		$params = array(':tid' => $id, ':last_viewed' => $last_viewed);

		$result = $query->run($params);
		unset ($query, $params);

		if (!empty($result))
		{
			$first_new_post_id = $result[0]['new_pid'];

			header('Location: viewtopic.php?pid='.$first_new_post_id.'#p'.$first_new_post_id);
			exit;
		}
	}

	// If there is no new post, we go to the last post
	header('Location: viewtopic.php?id='.$id.'&action=last');
	exit;
}

// If action=last, we redirect to the last post
else if ($action == 'last')
{
	$query = $db->select(array('last_pid' => 'MAX(p.id) AS last_pid'), 'posts AS p');
	$query->where = 'topic_id = :tid';

	$params = array(':tid' => $id);

	$result = $query->run($params);
	unset ($query, $params);

	if (!empty($result))
	{
		$last_post_id = $result[0]['last_pid'];

		header('Location: viewtopic.php?pid='.$last_post_id.'#p'.$last_post_id);
		exit;
	}
}

// Fetch some info about the topic
$query = $db->select(array('subject' => 't.subject', 'closed' => 't.closed', 'num_replies' => 't.num_replies', 'sticky' => 't.sticky', 'first_post_id' => 't.first_post_id', 'last_post' => 't.last_post', 'forum_id' => 'f.id AS forum_id', 'forum_name' => 'f.forum_name', 'moderators' => 'f.moderators', 'post_replies' => 'fp.post_replies', 'is_subscribed' => '0 AS is_subscribed'), 'topics AS t');

$query->innerJoin('f', 'forums AS f', 'f.id = t.forum_id');

$query->leftJoin('fp', 'forum_perms AS fp', 'fp.forum_id = f.id AND fp.group_id = :group_id');

$query->where = '(fp.read_forum IS NULL OR fp.read_forum = 1) AND t.id = :tid AND t.moved_to IS NULL';

$params = array(':group_id' => $pun_user['g_id'], ':tid' => $id);

// If we aren't a guest then handle subscription checks
if (!$pun_user['is_guest'])
{
	$query->fields['is_subscribed'] = 's.user_id AS is_subscribed';

	$query->leftJoin('s', 'topic_subscriptions AS s', 't.id = s.topic_id AND s.user_id = :user_id');

	// Topic/forum tracing
	$query->fields['mark_time'] = 'tt.mark_time';
	$query->fields['forum_mark_time'] = 'ft.mark_time as forum_mark_time';

	$query->leftJoin('tt', 'topics_track AS tt', 'tt.user_id = :tt_user_id AND t.id = tt.topic_id');
	$query->leftJoin('ft', 'forums_track AS ft', 'ft.user_id = :ft_user_id AND t.id = ft.forum_id');

	$params[':user_id'] = $params[':tt_user_id'] = $params[':ft_user_id'] = $pun_user['id'];
}

$result = $query->run($params);
if (empty($result))
	message($lang->t('Bad request'));

$cur_topic = $result[0];
unset ($query, $params, $result);

// Sort out who the moderators are and if we are currently a moderator (or an admin)
$mods_array = ($cur_topic['moderators'] != '') ? unserialize($cur_topic['moderators']) : array();
$is_admmod = ($pun_user['g_id'] == PUN_ADMIN || ($pun_user['g_moderator'] == '1' && array_key_exists($pun_user['username'], $mods_array))) ? true : false;

// Can we or can we not post replies?
if ($cur_topic['closed'] == '0')
{
	if (($cur_topic['post_replies'] == '' && $pun_user['g_post_replies'] == '1') || $cur_topic['post_replies'] == '1' || $is_admmod)
		$post_link = "\t\t\t".'<p class="postlink conr"><a href="post.php?tid='.$id.'">'.$lang->t('Post reply').'</a></p>'."\n";
	else
		$post_link = '';
}
else
{
	$post_link = $lang->t('Topic closed');

	if ($is_admmod)
		$post_link .= ' / <a href="post.php?tid='.$id.'">'.$lang->t('Post reply').'</a>';

	$post_link = "\t\t\t".'<p class="postlink conr">'.$post_link.'</p>'."\n";
}


// Add/update this topic in our list of tracked topics
if (!$pun_user['is_guest'])
{
	// Get topic tracking info
	if (!isset($topic_tracking_info))
	{
		$tmp_topic_data = array($id => $cur_topic);
		$topic_tracking_info = get_topic_tracking($cur_topic['forum_id'], $id, $tmp_topic_data, array($cur_topic['forum_id'] => $cur_topic['forum_mark_time']));
		unset($tmp_topic_data);
	}

	// Only mark topic if it's currently unread. Also make sure we do not set topic tracking back if earlier pages are viewed.
	if (isset($topic_tracking_info[$id]) && $cur_topic['last_post'] > $topic_tracking_info[$id] && $cur_topic['last_post'] > $topic_tracking_info[$id])
	{
		mark_read('topic', $cur_topic['forum_id'], $id, $cur_topic['last_post']);
		update_forum_tracking_info($cur_topic['forum_id'], $cur_topic['last_post'], (isset($cur_topic['forum_mark_time'])) ? $cur_topic['forum_mark_time'] : false, false);
	}
}


// Determine the post offset (based on $_GET['p'])
$num_pages = ceil(($cur_topic['num_replies'] + 1) / $pun_user['disp_posts']);

$p = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $num_pages) ? 1 : intval($_GET['p']);
$start_from = $pun_user['disp_posts'] * ($p - 1);

// Generate paging links
$paging_links = '<span class="pages-label">'.$lang->t('Pages').' </span>'.paginate($num_pages, $p, 'viewtopic.php?id='.$id);


if ($pun_config['o_censoring'] == '1')
	$cur_topic['subject'] = censor_words($cur_topic['subject']);


$quickpost = false;
if ($pun_config['o_quickpost'] == '1' &&
	($cur_topic['post_replies'] == '1' || ($cur_topic['post_replies'] == '' && $pun_user['g_post_replies'] == '1')) &&
	($cur_topic['closed'] == '0' || $is_admmod))
{
	// Load the post.php language file
	$lang->load('post');

	$required_fields = array('req_message' => $lang->t('Message'));
	if ($pun_user['is_guest'])
	{
		$required_fields['req_username'] = $lang->t('Guest name');
		if ($pun_config['p_force_guest_email'] == '1')
			$required_fields['req_email'] = $lang->t('Email');
	}
	$quickpost = true;
}

if (!$pun_user['is_guest'] && $pun_config['o_topic_subscriptions'] == '1')
{
	if ($cur_topic['is_subscribed'])
		// I apologize for the variable naming here. It's a mix of subscription and action I guess :-)
		$subscraction = "\t\t".'<p class="subscribelink clearb"><span>'.$lang->t('Is subscribed').' - </span><a href="misc.php?action=unsubscribe&amp;tid='.$id.'">'.$lang->t('Unsubscribe').'</a></p>'."\n";
	else
		$subscraction = "\t\t".'<p class="subscribelink clearb"><a href="misc.php?action=subscribe&amp;tid='.$id.'">'.$lang->t('Subscribe').'</a></p>'."\n";
}
else
	$subscraction = '';

if ($pun_config['o_feed_type'] == '1')
	$page_head = array('feed' => '<link rel="alternate" type="application/rss+xml" href="extern.php?action=feed&amp;tid='.$id.'&amp;type=rss" title="'.$lang->t('RSS topic feed').'" />');
else if ($pun_config['o_feed_type'] == '2')
	$page_head = array('feed' => '<link rel="alternate" type="application/atom+xml" href="extern.php?action=feed&amp;tid='.$id.'&amp;type=atom" title="'.$lang->t('Atom topic feed').'" />');

$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), pun_htmlspecialchars($cur_topic['forum_name']), pun_htmlspecialchars($cur_topic['subject']));
define('PUN_ALLOW_INDEX', 1);
define('PUN_ACTIVE_PAGE', 'index');
require PUN_ROOT.'header.php';

?>
<div class="linkst">
	<div class="inbox crumbsplus">
		<ul class="crumbs">
			<li><a href="index.php"><?php echo $lang->t('Index') ?></a></li>
			<li><span>»&#160;</span><a href="viewforum.php?id=<?php echo $cur_topic['forum_id'] ?>"><?php echo pun_htmlspecialchars($cur_topic['forum_name']) ?></a></li>
			<li><span>»&#160;</span><a href="viewtopic.php?id=<?php echo $id ?>"><strong><?php echo pun_htmlspecialchars($cur_topic['subject']) ?></strong></a></li>
		</ul>
		<div class="pagepost">
			<p class="pagelink conl"><?php echo $paging_links ?></p>
<?php echo $post_link ?>
		</div>
		<div class="clearer"></div>
	</div>
</div>

<?php


require PUN_ROOT.'include/parser.php';

$post_count = 0; // Keep track of post numbers

// Retrieve a list of post IDs, LIMIT is (really) expensive so we only fetch the IDs here then later fetch the remaining data
$query = $db->select(array('id' => 'p.id'), 'posts AS p');
$query->where = 'p.topic_id = :tid';
$query->order = array('id' => 'p.id ASC');
$query->limit = $pun_user['disp_posts'];
$query->offset = $start_from;

$params = array(':tid' => $id);

$post_ids = $query->run($params);
unset ($query, $params);

// If there are posts in this topic
if (empty($post_ids))
	error('The post table and topic table seem to be out of sync!', __FILE__, __LINE__);

// Translate from a 3d array into 2d array: $post_ids[0]['id'] -> $post_ids[0]
foreach ($post_ids as $key => $value)
	$post_ids[$key] = $value['id'];

// Retrieve the posts (and their respective poster/online status)
$query = $db->select(array('email' => 'u.email', 'title' => 'u.title', 'url' => 'u.url', 'location' => 'u.location', 'signature' => 'u.signature', 'email_setting' => 'u.email_setting', 'num_posts' => 'u.num_posts', 'registered' => 'u.registered', 'admin_note' => 'u.admin_note', 'pid' => 'p.id', 'username' => 'p.poster AS username', 'poster_id' => 'p.poster_id', 'poster_ip' => 'p.poster_ip', 'poster_email' => 'p.poster_email', 'message' => 'p.message', 'hide_smilies' => 'p.hide_smilies', 'posted' => 'p.posted', 'edited' => 'p.edited', 'edited_by' => 'p.edited_by', 'gid' => 'g.g_id', 'g_user_title' => 'g.g_user_title', 'is_online' => 'o.user_id AS is_online'), 'posts AS p');

$query->innerJoin('u', 'users AS u', 'u.id = p.poster_id');

$query->innerJoin('g', 'groups AS g', 'g.g_id = u.group_id');

$query->leftJoin('o', 'online AS o', 'o.user_id = u.id AND o.user_id != 1 AND o.idle = 0');

$query->where = 'p.id IN :pids';
$query->order = array('pid' => 'p.id ASC');

$params = array(':pids' => $post_ids);

$result = $query->run($params);
foreach ($result as $cur_post)
{
	$post_count++;
	$user_avatar = '';
	$user_info = array();
	$user_contacts = array();
	$post_actions = array();
	$is_online = '';
	$signature = '';

	// If the poster is a registered user
	if ($cur_post['poster_id'] > 1)
	{
		if ($pun_user['g_view_users'] == '1')
			$username = '<a href="profile.php?id='.$cur_post['poster_id'].'">'.pun_htmlspecialchars($cur_post['username']).'</a>';
		else
			$username = pun_htmlspecialchars($cur_post['username']);

		$user_title = get_title($cur_post);

		if ($pun_config['o_censoring'] == '1')
			$user_title = censor_words($user_title);

		// Format the online indicator
		$is_online = ($cur_post['is_online'] == $cur_post['poster_id']) ? '<strong>'.$lang->t('Online').'</strong>' : '<span>'.$lang->t('Offline').'</span>';

		if ($pun_config['o_avatars'] == '1' && $pun_user['show_avatars'] != '0')
		{
			if (isset($user_avatar_cache[$cur_post['poster_id']]))
				$user_avatar = $user_avatar_cache[$cur_post['poster_id']];
			else
				$user_avatar = $user_avatar_cache[$cur_post['poster_id']] = generate_avatar_markup($cur_post['poster_id']);
		}

		// We only show location, register date, post count and the contact links if "Show user info" is enabled
		if ($pun_config['o_show_user_info'] == '1')
		{
			if ($cur_post['location'] != '')
			{
				if ($pun_config['o_censoring'] == '1')
					$cur_post['location'] = censor_words($cur_post['location']);

				$user_info[] = '<dd><span>'.$lang->t('From').' '.pun_htmlspecialchars($cur_post['location']).'</span></dd>';
			}

			$user_info[] = '<dd><span>'.$lang->t('Registered').' '.format_time($cur_post['registered'], true).'</span></dd>';

			if ($pun_config['o_show_post_count'] == '1' || $pun_user['is_admmod'])
				$user_info[] = '<dd><span>'.$lang->t('Posts').' '.forum_number_format($cur_post['num_posts']).'</span></dd>';

			// Now let's deal with the contact links (Email and URL)
			if ((($cur_post['email_setting'] == '0' && !$pun_user['is_guest']) || $pun_user['is_admmod']) && $pun_user['g_send_email'] == '1')
				$user_contacts[] = '<span class="email"><a href="mailto:'.$cur_post['email'].'">'.$lang->t('Email').'</a></span>';
			else if ($cur_post['email_setting'] == '1' && !$pun_user['is_guest'] && $pun_user['g_send_email'] == '1')
				$user_contacts[] = '<span class="email"><a href="misc.php?email='.$cur_post['poster_id'].'">'.$lang->t('Email').'</a></span>';

			if ($cur_post['url'] != '')
			{
				if ($pun_config['o_censoring'] == '1')
					$cur_post['url'] = censor_words($cur_post['url']);

				$user_contacts[] = '<span class="website"><a href="'.pun_htmlspecialchars($cur_post['url']).'">'.$lang->t('Website').'</a></span>';
			}
		}

		if ($pun_user['is_admmod'])
		{
			$user_info[] = '<dd><span><a href="moderate.php?get_host='.$cur_post['id'].'" title="'.$cur_post['poster_ip'].'">'.$lang->t('IP address logged').'</a></span></dd>';

			if ($cur_post['admin_note'] != '')
				$user_info[] = '<dd><span>'.$lang->t('Note').' <strong>'.pun_htmlspecialchars($cur_post['admin_note']).'</strong></span></dd>';
		}
	}
	// If the poster is a guest (or a user that has been deleted)
	else
	{
		$username = pun_htmlspecialchars($cur_post['username']);
		$user_title = get_title($cur_post);

		if ($pun_user['is_admmod'])
			$user_info[] = '<dd><span><a href="moderate.php?get_host='.$cur_post['id'].'" title="'.$cur_post['poster_ip'].'">'.$lang->t('IP address logged').'</a></span></dd>';

		if ($pun_config['o_show_user_info'] == '1' && $cur_post['poster_email'] != '' && !$pun_user['is_guest'] && $pun_user['g_send_email'] == '1')
			$user_contacts[] = '<span class="email"><a href="mailto:'.$cur_post['poster_email'].'">'.$lang->t('Email').'</a></span>';
	}

	// Generation post action array (quote, edit, delete etc.)
	if (!$is_admmod)
	{
		if (!$pun_user['is_guest'])
			$post_actions[] = '<li class="postreport"><span><a href="misc.php?report='.$cur_post['id'].'">'.$lang->t('Report').'</a></span></li>';

		if ($cur_topic['closed'] == '0')
		{
			if ($cur_post['poster_id'] == $pun_user['id'])
			{
				if ((($start_from + $post_count) == 1 && $pun_user['g_delete_topics'] == '1') || (($start_from + $post_count) > 1 && $pun_user['g_delete_posts'] == '1'))
					$post_actions[] = '<li class="postdelete"><span><a href="delete.php?id='.$cur_post['id'].'">'.$lang->t('Delete').'</a></span></li>';
				if ($pun_user['g_edit_posts'] == '1')
					$post_actions[] = '<li class="postedit"><span><a href="edit.php?id='.$cur_post['id'].'">'.$lang->t('Edit').'</a></span></li>';
			}

			if (($cur_topic['post_replies'] == '' && $pun_user['g_post_replies'] == '1') || $cur_topic['post_replies'] == '1')
				$post_actions[] = '<li class="postquote"><span><a href="post.php?tid='.$id.'&amp;qid='.$cur_post['id'].'">'.$lang->t('Quote').'</a></span></li>';
		}
	}
	else
	{
		$post_actions[] = '<li class="postreport"><span><a href="misc.php?report='.$cur_post['id'].'">'.$lang->t('Report').'</a></span></li>';
		$post_actions[] = '<li class="postdelete"><span><a href="delete.php?id='.$cur_post['id'].'">'.$lang->t('Delete').'</a></span></li>';
		$post_actions[] = '<li class="postedit"><span><a href="edit.php?id='.$cur_post['id'].'">'.$lang->t('Edit').'</a></span></li>';
		$post_actions[] = '<li class="postquote"><span><a href="post.php?tid='.$id.'&amp;qid='.$cur_post['id'].'">'.$lang->t('Quote').'</a></span></li>';
	}

	// Perform the main parsing of the message (BBCode, smilies, censor words etc)
	$cur_post['message'] = parse_message($cur_post['message'], $cur_post['hide_smilies']);

	// Do signature parsing/caching
	if ($pun_config['o_signatures'] == '1' && $cur_post['signature'] != '' && $pun_user['show_sig'] != '0')
	{
		if (isset($signature_cache[$cur_post['poster_id']]))
			$signature = $signature_cache[$cur_post['poster_id']];
		else
		{
			$signature = parse_signature($cur_post['signature']);
			$signature_cache[$cur_post['poster_id']] = $signature;
		}
	}

?>
<div id="p<?php echo $cur_post['id'] ?>" class="blockpost<?php echo ($post_count % 2 == 0) ? ' roweven' : ' rowodd' ?><?php if ($cur_post['id'] == $cur_topic['first_post_id']) echo ' firstpost'; ?><?php if ($post_count == 1) echo ' blockpost1'; ?>">
	<h2><span><span class="conr">#<?php echo ($start_from + $post_count) ?></span> <a href="viewtopic.php?pid=<?php echo $cur_post['id'].'#p'.$cur_post['id'] ?>"><?php echo format_time($cur_post['posted']) ?></a></span></h2>
	<div class="box">
		<div class="inbox">
			<div class="postbody">
				<div class="postleft">
					<dl>
						<dt><strong><?php echo $username ?></strong></dt>
						<dd class="usertitle"><strong><?php echo $user_title ?></strong></dd>
<?php if ($user_avatar != '') echo "\t\t\t\t\t\t".'<dd class="postavatar">'.$user_avatar.'</dd>'."\n"; ?>
<?php if (count($user_info)) echo "\t\t\t\t\t\t".implode("\n\t\t\t\t\t\t", $user_info)."\n"; ?>
<?php if (count($user_contacts)) echo "\t\t\t\t\t\t".'<dd class="usercontacts">'.implode(' ', $user_contacts).'</dd>'."\n"; ?>
					</dl>
				</div>
				<div class="postright">
					<h3><?php if ($cur_post['id'] != $cur_topic['first_post_id']) echo $lang->t('Re').' '; ?><?php echo pun_htmlspecialchars($cur_topic['subject']) ?></h3>
					<div class="postmsg">
						<?php echo $cur_post['message']."\n" ?>
<?php if ($cur_post['edited'] != '') echo "\t\t\t\t\t\t".'<p class="postedit"><em>'.$lang->t('Last edit').' '.pun_htmlspecialchars($cur_post['edited_by']).' ('.format_time($cur_post['edited']).')</em></p>'."\n"; ?>
					</div>
<?php if ($signature != '') echo "\t\t\t\t\t".'<div class="postsignature postmsg"><hr />'.$signature.'</div>'."\n"; ?>
				</div>
			</div>
		</div>
		<div class="inbox">
			<div class="postfoot clearb">
				<div class="postfootleft"><?php if ($cur_post['poster_id'] > 1) echo '<p>'.$is_online.'</p>'; ?></div>
<?php if (count($post_actions)) echo "\t\t\t\t".'<div class="postfootright">'."\n\t\t\t\t\t".'<ul>'."\n\t\t\t\t\t\t".implode("\n\t\t\t\t\t\t", $post_actions)."\n\t\t\t\t\t".'</ul>'."\n\t\t\t\t".'</div>'."\n" ?>
			</div>
		</div>
	</div>
</div>

<?php

}

unset ($result, $query, $params);

?>
<div class="postlinksb">
	<div class="inbox crumbsplus">
		<div class="pagepost">
			<p class="pagelink conl"><?php echo $paging_links ?></p>
<?php echo $post_link ?>
		</div>
		<ul class="crumbs">
			<li><a href="index.php"><?php echo $lang->t('Index') ?></a></li>
			<li><span>»&#160;</span><a href="viewforum.php?id=<?php echo $cur_topic['forum_id'] ?>"><?php echo pun_htmlspecialchars($cur_topic['forum_name']) ?></a></li>
			<li><span>»&#160;</span><a href="viewtopic.php?id=<?php echo $id ?>"><strong><?php echo pun_htmlspecialchars($cur_topic['subject']) ?></strong></a></li>
		</ul>
<?php echo $subscraction ?>
		<div class="clearer"></div>
	</div>
</div>

<?php

// Display quick post if enabled
if ($quickpost)
{

$cur_index = 1;

?>
<div id="quickpost" class="blockform">
	<h2><span><?php echo $lang->t('Quick post') ?></span></h2>
	<div class="box">
		<form id="quickpostform" method="post" action="post.php?tid=<?php echo $id ?>" onsubmit="this.submit.disabled=true;if(process_form(this)){return true;}else{this.submit.disabled=false;return false;}">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang->t('Write message legend') ?></legend>
					<div class="infldset txtarea">
						<input type="hidden" name="form_sent" value="1" />
<?php if ($pun_config['o_topic_subscriptions'] == '1' && ($pun_user['auto_notify'] == '1' || $cur_topic['is_subscribed'])): ?>						<input type="hidden" name="subscribe" value="1" />
<?php endif; ?>
<?php

if ($pun_user['is_guest'])
{
	$email_label = ($pun_config['p_force_guest_email'] == '1') ? '<strong>'.$lang->t('Email').' <span>'.$lang->t('Required').'</span></strong>' : $lang->t('Email');
	$email_form_name = ($pun_config['p_force_guest_email'] == '1') ? 'req_email' : 'email';

?>
						<label class="conl required"><strong><?php echo $lang->t('Guest name') ?> <span><?php echo $lang->t('Required') ?></span></strong><br /><input type="text" name="req_username" value="<?php if (isset($_POST['req_username'])) echo pun_htmlspecialchars($username); ?>" size="25" maxlength="25" tabindex="<?php echo $cur_index++ ?>" /><br /></label>
						<label class="conl<?php echo ($pun_config['p_force_guest_email'] == '1') ? ' required' : '' ?>"><?php echo $email_label ?><br /><input type="text" name="<?php echo $email_form_name ?>" value="<?php if (isset($_POST[$email_form_name])) echo pun_htmlspecialchars($email); ?>" size="50" maxlength="80" tabindex="<?php echo $cur_index++ ?>" /><br /></label>
						<div class="clearer"></div>
<?php

	echo "\t\t\t\t\t\t".'<label class="required"><strong>'.$lang->t('Message').' <span>'.$lang->t('Required').'</span></strong><br />';
}
else
	echo "\t\t\t\t\t\t".'<label>';

?>
<textarea name="req_message" rows="7" cols="75" tabindex="<?php echo $cur_index++ ?>"></textarea></label>
						<ul class="bblinks">
							<li><span><a href="help.php#bbcode" onclick="window.open(this.href); return false;"><?php echo $lang->t('BBCode') ?></a> <?php echo ($pun_config['p_message_bbcode'] == '1') ? $lang->t('on') : $lang->t('off'); ?></span></li>
							<li><span><a href="help.php#img" onclick="window.open(this.href); return false;"><?php echo $lang->t('img tag') ?></a> <?php echo ($pun_config['p_message_bbcode'] == '1' && $pun_config['p_message_img_tag'] == '1') ? $lang->t('on') : $lang->t('off'); ?></span></li>
							<li><span><a href="help.php#smilies" onclick="window.open(this.href); return false;"><?php echo $lang->t('Smilies') ?></a> <?php echo ($pun_config['o_smilies'] == '1') ? $lang->t('on') : $lang->t('off'); ?></span></li>
						</ul>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="submit" tabindex="<?php echo $cur_index++ ?>" value="<?php echo $lang->t('Submit') ?>" accesskey="s" /> <input type="submit" name="preview" value="<?php echo $lang->t('Preview') ?>" tabindex="<?php echo $cur_index++ ?>" accesskey="p" /></p>
		</form>
	</div>
</div>
<?php

}

// Increment "num_views" for topic
if ($pun_config['o_topic_views'] == '1')
{
	$query = $db->update(array('num_views' => 'num_views + 1'), 'topics');
	$query->where = 'id = :tid';

	$params = array(':tid' => $id);

	$query->run($params);
	unset ($query, $params);
}

$forum_id = $cur_topic['forum_id'];
$footer_style = 'viewtopic';
require PUN_ROOT.'footer.php';
