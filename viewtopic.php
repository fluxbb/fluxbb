<?php

/**
 * Copyright (C) 2008-2011 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';


if ($pun_user['g_read_board'] == '0')
	message($lang_common['No view']);


$action = isset($_GET['action']) ? $_GET['action'] : null;
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$pid = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
if ($id < 1 && $pid < 1)
	message($lang_common['Bad request']);

// Load the viewtopic.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/topic.php';


// If a post ID is specified we determine topic ID and page number so we can redirect to the correct message
if ($pid)
{
	$query = new SelectQuery(array('topic_id' => 'p.topic_id', 'posted' => 'p.posted'), 'posts AS p');
	$query->where = 'id = :pid';

	$params = array(':pid' => $pid);

	$result = $db->query($query, $params);
	if (empty($result))
		message($lang_common['Bad request']);

	$id = $result[0]['topic_id'];
	$posted = $result[0]['posted'];
	unset ($result, $query, $params);

	// Determine on what page the post is located (depending on $forum_user['disp_posts'])
	$query = new SelectQuery(array('num_posts' => '(COUNT(p.id) + 1) AS num_posts'), 'posts AS p');
	$query->where = 'p.topic_id = :tid AND p.posted < :posted';

	$params = array(':tid' => $id, ':posted' => $posted);

	$result = $db->query($query, $params);
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

		$query = new SelectQuery(array('new_pid' => 'MIN(p.id) AS new_pid'), 'posts AS p');
		$query->where = 'p.topic_id = :tid AND p.posted > :last_viewed';

		$params = array(':tid' => $id, ':last_viewed' => $last_viewed);

		$result = $db->query($query, $params);
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
	$query = new SelectQuery(array('last_pid' => 'MAX(p.id) AS last_pid'), 'posts AS p');
	$query->where = 'topic_id = :tid';

	$params = array(':tid' => $id);

	$result = $db->query($query, $params);
	unset ($query, $params);

	if (!empty($result))
	{
		$last_post_id = $result[0]['last_pid'];

		header('Location: viewtopic.php?pid='.$last_post_id.'#p'.$last_post_id);
		exit;
	}
}

// Fetch some info about the topic
$query = new SelectQuery(array('subject' => 't.subject', 'closed' => 't.closed', 'num_replies' => 't.num_replies', 'sticky' => 't.sticky', 'first_post_id' => 't.first_post_id', 'forum_id' => 'f.id AS forum_id', 'forum_name' => 'f.forum_name', 'moderators' => 'f.moderators', 'post_replies' => 'fp.post_replies', 'is_subscribed' => '0 AS is_subscribed'), 'topics AS t');

$query->joins['f'] = new InnerJoin('forums AS f');
$query->joins['f']->on = 'f.id = t.forum_id';

$query->joins['fp'] = new LeftJoin('forum_perms AS fp');
$query->joins['fp']->on = 'fp.forum_id = f.id AND fp.group_id = :group_id';

$query->where = '(fp.read_forum IS NULL OR fp.read_forum = 1) AND t.id = :tid AND t.moved_to IS NULL';

$params = array(':group_id' => $pun_user['g_id'], ':tid' => $id);

// If we aren't a guest then handle subscription checks
if (!$pun_user['is_guest'])
{
	$query->fields['is_subscribed'] = 's.user_id AS is_subscribed';

	$query->joins['s'] = new LeftJoin('topic_subscriptions AS s');
	$query->joins['s']->on = 't.id = s.topic_id AND s.user_id = :user_id';

	$params[':user_id'] = $pun_user['id'];
}

$result = $db->query($query, $params);
if (empty($result))
	message($lang_common['Bad request']);

$cur_topic = $result[0];
unset ($query, $params, $result);

// Sort out who the moderators are and if we are currently a moderator (or an admin)
$mods_array = ($cur_topic['moderators'] != '') ? unserialize($cur_topic['moderators']) : array();
$is_admmod = ($pun_user['g_id'] == PUN_ADMIN || ($pun_user['g_moderator'] == '1' && array_key_exists($pun_user['username'], $mods_array))) ? true : false;

// Can we or can we not post replies?
if ($cur_topic['closed'] == '0')
{
	if (($cur_topic['post_replies'] == '' && $pun_user['g_post_replies'] == '1') || $cur_topic['post_replies'] == '1' || $is_admmod)
		$post_link = "\t\t\t".'<p class="postlink conr"><a href="post.php?tid='.$id.'">'.$lang_topic['Post reply'].'</a></p>'."\n";
	else
		$post_link = '';
}
else
{
	$post_link = $lang_topic['Topic closed'];

	if ($is_admmod)
		$post_link .= ' / <a href="post.php?tid='.$id.'">'.$lang_topic['Post reply'].'</a>';

	$post_link = "\t\t\t".'<p class="postlink conr">'.$post_link.'</p>'."\n";
}


// Add/update this topic in our list of tracked topics
if (!$pun_user['is_guest'])
{
	$tracked_topics = get_tracked_topics();
	$tracked_topics['topics'][$id] = time();
	set_tracked_topics($tracked_topics);
}


// Determine the post offset (based on $_GET['p'])
$num_pages = ceil(($cur_topic['num_replies'] + 1) / $pun_user['disp_posts']);

$p = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $num_pages) ? 1 : intval($_GET['p']);
$start_from = $pun_user['disp_posts'] * ($p - 1);

// Generate paging links
$paging_links = '<span class="pages-label">'.$lang_common['Pages'].' </span>'.paginate($num_pages, $p, 'viewtopic.php?id='.$id);


// Add relationship meta tags
$page_head = array();
$page_head['up'] = '<link rel="up" href="viewforum.php?id='.$cur_topic['forum_id'].'" title="'.pun_htmlspecialchars($cur_topic['forum_name']).'" />';

if ($num_pages > 1)
{
	if ($p > 1)
	{
		$page_head['first'] = '<link rel="first" href="viewtopic.php?id='.$id.'&amp;p=1" title="'.sprintf($lang_common['Page'], 1).'" />';
		$page_head['prev'] = '<link rel="prev" href="viewtopic.php?id='.$id.'&amp;p='.($p-1).'" title="'.sprintf($lang_common['Page'], $p-1).'" />';
	}
	if ($p < $num_pages)
	{
		$page_head['next'] = '<link rel="next" href="viewtopic.php?id='.$id.'&amp;p='.($p+1).'" title="'.sprintf($lang_common['Page'], $p+1).'" />';
		$page_head['last'] = '<link rel="last" href="viewtopic.php?id='.$id.'&amp;p='.$num_pages.'" title="'.sprintf($lang_common['Page'], $num_pages).'" />';
	}
}


if ($pun_config['o_censoring'] == '1')
	$cur_topic['subject'] = censor_words($cur_topic['subject']);


$quickpost = false;
if ($pun_config['o_quickpost'] == '1' &&
	($cur_topic['post_replies'] == '1' || ($cur_topic['post_replies'] == '' && $pun_user['g_post_replies'] == '1')) &&
	($cur_topic['closed'] == '0' || $is_admmod))
{
	// Load the post.php language file
	require PUN_ROOT.'lang/'.$pun_user['language'].'/post.php';

	$required_fields = array('req_message' => $lang_common['Message']);
	if ($pun_user['is_guest'])
	{
		$required_fields['req_username'] = $lang_post['Guest name'];
		if ($pun_config['p_force_guest_email'] == '1')
			$required_fields['req_email'] = $lang_common['Email'];
	}
	$quickpost = true;
}

if (!$pun_user['is_guest'] && $pun_config['o_topic_subscriptions'] == '1')
{
	if ($cur_topic['is_subscribed'])
		// I apologize for the variable naming here. It's a mix of subscription and action I guess :-)
		$subscraction = "\t\t".'<p class="subscribelink clearb"><span>'.$lang_topic['Is subscribed'].' - </span><a href="misc.php?action=unsubscribe&amp;tid='.$id.'">'.$lang_topic['Unsubscribe'].'</a></p>'."\n";
	else
		$subscraction = "\t\t".'<p class="subscribelink clearb"><a href="misc.php?action=subscribe&amp;tid='.$id.'">'.$lang_topic['Subscribe'].'</a></p>'."\n";
}
else
	$subscraction = '';

if ($pun_config['o_feed_type'] == '1')
	$page_head['feed'] = '<link rel="alternate" type="application/rss+xml" href="extern.php?action=feed&amp;tid='.$id.'&amp;type=rss" title="'.$lang_common['RSS topic feed'].'" />';
else if ($pun_config['o_feed_type'] == '2')
	$page_head['feed'] = '<link rel="alternate" type="application/atom+xml" href="extern.php?action=feed&amp;tid='.$id.'&amp;type=atom" title="'.$lang_common['Atom topic feed'].'" />';

$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), pun_htmlspecialchars($cur_topic['forum_name']), pun_htmlspecialchars($cur_topic['subject']));
define('PUN_ALLOW_INDEX', 1);
define('PUN_ACTIVE_PAGE', 'index');
require PUN_ROOT.'header.php';

?>
<div class="linkst">
	<div class="inbox crumbsplus">
		<ul class="crumbs">
			<li><a href="index.php"><?php echo $lang_common['Index'] ?></a></li>
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
$query = new SelectQuery(array('id' => 'p.id'), 'posts AS p');
$query->where = 'p.topic_id = :tid';
$query->order = array('id' => 'p.id ASC');
$query->limit = $pun_user['disp_posts'];
$query->offset = $start_from;

$params = array(':tid' => $id);

$post_ids = $db->query($query, $params);
unset ($query, $params);

// If there are posts in this topic
if (empty($post_ids))
	error('The post table and topic table seem to be out of sync!', __FILE__, __LINE__);

// Translate from a 3d array into 2d array: $post_ids[0]['id'] -> $post_ids[0]
foreach ($post_ids as $key => $value)
	$post_ids[$key] = $value['id'];

// Retrieve the posts (and their respective poster/online status)
$query = new SelectQuery(array('email' => 'u.email', 'title' => 'u.title', 'url' => 'u.url', 'location' => 'u.location', 'signature' => 'u.signature', 'email_setting' => 'u.email_setting', 'num_posts' => 'u.num_posts', 'registered' => 'u.registered', 'admin_note' => 'u.admin_note', 'pid' => 'p.id', 'username' => 'p.poster AS username', 'poster_id' => 'p.poster_id', 'poster_ip' => 'p.poster_ip', 'poster_email' => 'p.poster_email', 'message' => 'p.message', 'hide_smilies' => 'p.hide_smilies', 'posted' => 'p.posted', 'edited' => 'p.edited', 'edited_by' => 'p.edited_by', 'gid' => 'g.g_id', 'g_user_title' => 'g.g_user_title', 'is_online' => 'o.user_id AS is_online'), 'posts AS p');

$query->joins['u'] = new InnerJoin('users AS u');
$query->joins['u']->on = 'u.id = p.poster_id';

$query->joins['g'] = new InnerJoin('groups AS g');
$query->joins['g']->on = 'g.g_id = u.group_id';

$query->joins['o'] = new LeftJoin('online AS o');
$query->joins['o']->on = 'o.user_id = u.id AND o.user_id != 1 AND o.idle = 0';

$query->where = 'p.id IN :pids';
$query->order = array('pid' => 'p.id ASC');

$params = array(':pids' => $post_ids);

$result = $db->query($query, $params);
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
		$is_online = ($cur_post['is_online'] == $cur_post['poster_id']) ? '<strong>'.$lang_topic['Online'].'</strong>' : '<span>'.$lang_topic['Offline'].'</span>';

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

				$user_info[] = '<dd><span>'.$lang_topic['From'].' '.pun_htmlspecialchars($cur_post['location']).'</span></dd>';
			}

			$user_info[] = '<dd><span>'.$lang_topic['Registered'].' '.format_time($cur_post['registered'], true).'</span></dd>';

			if ($pun_config['o_show_post_count'] == '1' || $pun_user['is_admmod'])
				$user_info[] = '<dd><span>'.$lang_topic['Posts'].' '.forum_number_format($cur_post['num_posts']).'</span></dd>';

			// Now let's deal with the contact links (Email and URL)
			if ((($cur_post['email_setting'] == '0' && !$pun_user['is_guest']) || $pun_user['is_admmod']) && $pun_user['g_send_email'] == '1')
				$user_contacts[] = '<span class="email"><a href="mailto:'.$cur_post['email'].'">'.$lang_common['Email'].'</a></span>';
			else if ($cur_post['email_setting'] == '1' && !$pun_user['is_guest'] && $pun_user['g_send_email'] == '1')
				$user_contacts[] = '<span class="email"><a href="misc.php?email='.$cur_post['poster_id'].'">'.$lang_common['Email'].'</a></span>';

			if ($cur_post['url'] != '')
			{
				if ($pun_config['o_censoring'] == '1')
					$cur_post['url'] = censor_words($cur_post['url']);

				$user_contacts[] = '<span class="website"><a href="'.pun_htmlspecialchars($cur_post['url']).'">'.$lang_topic['Website'].'</a></span>';
			}
		}

		if ($pun_user['is_admmod'])
		{
			$user_info[] = '<dd><span><a href="moderate.php?get_host='.$cur_post['id'].'" title="'.$cur_post['poster_ip'].'">'.$lang_topic['IP address logged'].'</a></span></dd>';

			if ($cur_post['admin_note'] != '')
				$user_info[] = '<dd><span>'.$lang_topic['Note'].' <strong>'.pun_htmlspecialchars($cur_post['admin_note']).'</strong></span></dd>';
		}
	}
	// If the poster is a guest (or a user that has been deleted)
	else
	{
		$username = pun_htmlspecialchars($cur_post['username']);
		$user_title = get_title($cur_post);

		if ($pun_user['is_admmod'])
			$user_info[] = '<dd><span><a href="moderate.php?get_host='.$cur_post['id'].'" title="'.$cur_post['poster_ip'].'">'.$lang_topic['IP address logged'].'</a></span></dd>';

		if ($pun_config['o_show_user_info'] == '1' && $cur_post['poster_email'] != '' && !$pun_user['is_guest'] && $pun_user['g_send_email'] == '1')
			$user_contacts[] = '<span class="email"><a href="mailto:'.$cur_post['poster_email'].'">'.$lang_common['Email'].'</a></span>';
	}

	// Generation post action array (quote, edit, delete etc.)
	if (!$is_admmod)
	{
		if (!$pun_user['is_guest'])
			$post_actions[] = '<li class="postreport"><span><a href="misc.php?report='.$cur_post['id'].'">'.$lang_topic['Report'].'</a></span></li>';

		if ($cur_topic['closed'] == '0')
		{
			if ($cur_post['poster_id'] == $pun_user['id'])
			{
				if ((($start_from + $post_count) == 1 && $pun_user['g_delete_topics'] == '1') || (($start_from + $post_count) > 1 && $pun_user['g_delete_posts'] == '1'))
					$post_actions[] = '<li class="postdelete"><span><a href="delete.php?id='.$cur_post['id'].'">'.$lang_topic['Delete'].'</a></span></li>';
				if ($pun_user['g_edit_posts'] == '1')
					$post_actions[] = '<li class="postedit"><span><a href="edit.php?id='.$cur_post['id'].'">'.$lang_topic['Edit'].'</a></span></li>';
			}

			if (($cur_topic['post_replies'] == '' && $pun_user['g_post_replies'] == '1') || $cur_topic['post_replies'] == '1')
				$post_actions[] = '<li class="postquote"><span><a href="post.php?tid='.$id.'&amp;qid='.$cur_post['id'].'">'.$lang_topic['Quote'].'</a></span></li>';
		}
	}
	else
	{
		$post_actions[] = '<li class="postreport"><span><a href="misc.php?report='.$cur_post['id'].'">'.$lang_topic['Report'].'</a></span></li>';
		$post_actions[] = '<li class="postdelete"><span><a href="delete.php?id='.$cur_post['id'].'">'.$lang_topic['Delete'].'</a></span></li>';
		$post_actions[] = '<li class="postedit"><span><a href="edit.php?id='.$cur_post['id'].'">'.$lang_topic['Edit'].'</a></span></li>';
		$post_actions[] = '<li class="postquote"><span><a href="post.php?tid='.$id.'&amp;qid='.$cur_post['id'].'">'.$lang_topic['Quote'].'</a></span></li>';
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
					<h3><?php if ($cur_post['id'] != $cur_topic['first_post_id']) echo $lang_topic['Re'].' '; ?><?php echo pun_htmlspecialchars($cur_topic['subject']) ?></h3>
					<div class="postmsg">
						<?php echo $cur_post['message']."\n" ?>
<?php if ($cur_post['edited'] != '') echo "\t\t\t\t\t\t".'<p class="postedit"><em>'.$lang_topic['Last edit'].' '.pun_htmlspecialchars($cur_post['edited_by']).' ('.format_time($cur_post['edited']).')</em></p>'."\n"; ?>
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
			<li><a href="index.php"><?php echo $lang_common['Index'] ?></a></li>
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
	<h2><span><?php echo $lang_topic['Quick post'] ?></span></h2>
	<div class="box">
		<form id="quickpostform" method="post" action="post.php?tid=<?php echo $id ?>" onsubmit="this.submit.disabled=true;if(process_form(this)){return true;}else{this.submit.disabled=false;return false;}">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_common['Write message legend'] ?></legend>
					<div class="infldset txtarea">
						<input type="hidden" name="form_sent" value="1" />
<?php if ($pun_config['o_topic_subscriptions'] == '1' && ($pun_user['auto_notify'] == '1' || $cur_topic['is_subscribed'])): ?>						<input type="hidden" name="subscribe" value="1" />
<?php endif; ?>
<?php

if ($pun_user['is_guest'])
{
	$email_label = ($pun_config['p_force_guest_email'] == '1') ? '<strong>'.$lang_common['Email'].' <span>'.$lang_common['Required'].'</span></strong>' : $lang_common['Email'];
	$email_form_name = ($pun_config['p_force_guest_email'] == '1') ? 'req_email' : 'email';

?>
						<label class="conl required"><strong><?php echo $lang_post['Guest name'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br /><input type="text" name="req_username" value="<?php if (isset($_POST['req_username'])) echo pun_htmlspecialchars($username); ?>" size="25" maxlength="25" tabindex="<?php echo $cur_index++ ?>" /><br /></label>
						<label class="conl<?php echo ($pun_config['p_force_guest_email'] == '1') ? ' required' : '' ?>"><?php echo $email_label ?><br /><input type="text" name="<?php echo $email_form_name ?>" value="<?php if (isset($_POST[$email_form_name])) echo pun_htmlspecialchars($email); ?>" size="50" maxlength="80" tabindex="<?php echo $cur_index++ ?>" /><br /></label>
						<div class="clearer"></div>
<?php

	echo "\t\t\t\t\t\t".'<label class="required"><strong>'.$lang_common['Message'].' <span>'.$lang_common['Required'].'</span></strong><br />';
}
else
	echo "\t\t\t\t\t\t".'<label>';

?>
<textarea name="req_message" rows="7" cols="75" tabindex="<?php echo $cur_index++ ?>"></textarea></label>
						<ul class="bblinks">
							<li><span><a href="help.php#bbcode" onclick="window.open(this.href); return false;"><?php echo $lang_common['BBCode'] ?></a> <?php echo ($pun_config['p_message_bbcode'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
							<li><span><a href="help.php#img" onclick="window.open(this.href); return false;"><?php echo $lang_common['img tag'] ?></a> <?php echo ($pun_config['p_message_bbcode'] == '1' && $pun_config['p_message_img_tag'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
							<li><span><a href="help.php#smilies" onclick="window.open(this.href); return false;"><?php echo $lang_common['Smilies'] ?></a> <?php echo ($pun_config['o_smilies'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
						</ul>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="submit" tabindex="<?php echo $cur_index++ ?>" value="<?php echo $lang_common['Submit'] ?>" accesskey="s" /> <input type="submit" name="preview" value="<?php echo $lang_topic['Preview'] ?>" tabindex="<?php echo $cur_index++ ?>" accesskey="p" /></p>
		</form>
	</div>
</div>
<?php

}

// Increment "num_views" for topic
if ($pun_config['o_topic_views'] == '1')
{
	$query = new UpdateQuery(array('num_views' => 'num_views + 1'), 'topics');
	$query->where = 'id = :tid';

	$params = array(':tid' => $id);

	$db->query($query, $params);
	unset ($query, $params);
}

$forum_id = $cur_topic['forum_id'];
$footer_style = 'viewtopic';
require PUN_ROOT.'footer.php';
