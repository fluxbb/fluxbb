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


// This particular function doesn't require forum-based moderator access. It can be used
// by all moderators and admins.
if (isset($_GET['get_host']))
{
	if ($pun_user['g_id'] > PUN_MOD)
		message($lang_common['No permission']);

	// Is get_host an IP address or a post ID?
	if (@preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $_GET['get_host']))
		$ip = $_GET['get_host'];
	else
	{
		$get_host = intval($_GET['get_host']);
		if ($get_host < 1)
			message($lang_common['Bad request']);

		$result = $db->query('SELECT poster_ip FROM '.$db->prefix.'posts WHERE id='.$get_host) or error('Unable to fetch post IP address', __FILE__, __LINE__, $db->error());
		if (!$db->num_rows($result))
			message($lang_common['Bad request']);

		$ip = $db->result($result);
	}

	message('The IP address is: '.$ip.'<br />The host name is: '.@gethostbyaddr($ip).'<br /><br /><a href="admin_users.php?show_users='.$ip.'">Show more users for this IP</a>');
}


// All other functions require moderator/admin access
$fid = isset($_GET['fid']) ? intval($_GET['fid']) : 0;
if ($fid < 1)
	message($lang_common['Bad request']);

$result = $db->query('SELECT moderators FROM '.$db->prefix.'forums WHERE id='.$fid) or error('Unable to fetch forum info', __FILE__, __LINE__, $db->error());

$moderators = $db->result($result);
$mods_array = ($moderators != '') ? unserialize($moderators) : array();

if ($pun_user['g_id'] != PUN_ADMIN && ($pun_user['g_id'] != PUN_MOD || !array_key_exists($pun_user['username'], $mods_array)))
	message($lang_common['No permission']);


// Load the misc.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/misc.php';


// All other topic moderation features require a topic id in GET
if (isset($_GET['tid']))
{
	$tid = intval($_GET['tid']);
	if ($tid < 1)
		message($lang_common['Bad request']);

	// Fetch some info about the topic
	$result = $db->query('SELECT t.subject, t.num_replies, f.id AS forum_id, forum_name FROM '.$db->prefix.'topics AS t INNER JOIN '.$db->prefix.'forums AS f ON f.id=t.forum_id LEFT JOIN '.$db->prefix.'subscriptions AS s ON (t.id=s.topic_id AND s.user_id='.$pun_user['id'].') LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND f.id='.$fid.' AND t.id='.$tid.' AND t.moved_to IS NULL') or error('Unable to fetch topic info', __FILE__, __LINE__, $db->error());
	if (!$db->num_rows($result))
		message($lang_common['Bad request']);

	$cur_topic = $db->fetch_assoc($result);


	// Delete one or more posts
	if (isset($_POST['delete_posts']) || isset($_POST['delete_posts_comply']))
	{
		$posts = $_POST['posts'];
		if (empty($posts))
			message($lang_misc['No posts selected']);

		if (isset($_POST['delete_posts_comply']))
		{
			confirm_referrer('moderate.php');

			if (@preg_match('/[^0-9,]/', $posts))
				message($lang_common['Bad request']);

			// Verify that the post IDs are valid
			$result = $db->query('SELECT 1 FROM '.$db->prefix.'posts WHERE id IN('.$posts.') AND topic_id='.$tid) or error('Unable to check posts', __FILE__, __LINE__, $db->error());

			if ($db->num_rows($result) != substr_count($posts, ',') + 1)
				message($lang_common['Bad request']);

			// Delete the posts
			$db->query('DELETE FROM '.$db->prefix.'posts WHERE id IN('.$posts.')') or error('Unable to delete posts', __FILE__, __LINE__, $db->error());

			require PUN_ROOT.'include/search_idx.php';
			strip_search_index($posts);

			// Get last_post, last_post_id, and last_poster for the topic after deletion
			$result = $db->query('SELECT id, poster, posted FROM '.$db->prefix.'posts WHERE topic_id='.$tid.' ORDER BY id DESC LIMIT 1') or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
			$last_post = $db->fetch_assoc($result);

			// How many posts did we just delete?
			$num_posts_deleted = substr_count($posts, ',') + 1;

			// Update the topic
			$db->query('UPDATE '.$db->prefix.'topics SET last_post='.$last_post['posted'].', last_post_id='.$last_post['id'].', last_poster=\''.$db->escape($last_post['poster']).'\', num_replies=num_replies-'.$num_posts_deleted.' WHERE id='.$tid) or error('Unable to update topic', __FILE__, __LINE__, $db->error());

			update_forum($fid);

			redirect('viewtopic.php?id='.$tid, $lang_misc['Delete posts redirect']);
		}


		$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / '.$lang_misc['Moderate'];
		require PUN_ROOT.'header.php';

?>
<div class="blockform">
	<h2><span><?php echo $lang_misc['Delete posts'] ?></span></h2>
	<div class="box">
		<form method="post" action="moderate.php?fid=<?php echo $fid ?>&amp;tid=<?php echo $tid ?>">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_misc['Confirm delete legend'] ?></legend>
					<div class="infldset">
						<input type="hidden" name="posts" value="<?php echo implode(',', array_map('intval', array_keys($posts))) ?>" />
						<p><?php echo $lang_misc['Delete posts comply'] ?></p>
					</div>
				</fieldset>
			</div>
			<p><input type="submit" name="delete_posts_comply" value="<?php echo $lang_misc['Delete'] ?>" /><a href="javascript:history.go(-1)"><?php echo $lang_common['Go back'] ?></a></p>
		</form>
	</div>
</div>
<?php

		require PUN_ROOT.'footer.php';
	}


	// Show the delete multiple posts view

	// Load the viewtopic.php language file
	require PUN_ROOT.'lang/'.$pun_user['language'].'/topic.php';

	// Used to disable the Move and Delete buttons if there are no replies to this topic
	$button_status = ($cur_topic['num_replies'] == 0) ? ' disabled' : '';


	// Determine the post offset (based on $_GET['p'])
	$num_pages = ceil(($cur_topic['num_replies'] + 1) / $pun_user['disp_posts']);

	$p = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $num_pages) ? 1 : $_GET['p'];
	$start_from = $pun_user['disp_posts'] * ($p - 1);

	// Generate paging links
	$paging_links = $lang_common['Pages'].': '.paginate($num_pages, $p, 'moderate.php?fid='.$fid.'&amp;tid='.$tid);


	if ($pun_config['o_censoring'] == '1')
		$cur_topic['subject'] = censor_words($cur_topic['subject']);


	$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / '.$cur_topic['subject'];
	require PUN_ROOT.'header.php';

?>
<div class="linkst">
	<div class="inbox">
		<p class="pagelink conl"><?php echo $paging_links ?></p>
		<ul><li><a href="index.php"><?php echo $lang_common['Index'] ?></a></li><li>&nbsp;&raquo;&nbsp;<a href="viewforum.php?id=<?php echo $fid ?>"><?php echo pun_htmlspecialchars($cur_topic['forum_name']) ?></a></li><li>&nbsp;&raquo;&nbsp;<?php echo pun_htmlspecialchars($cur_topic['subject']) ?></li></ul>
		<div class="clearer"></div>
	</div>
</div>

<form method="post" action="moderate.php?fid=<?php echo $fid ?>&amp;tid=<?php echo $tid ?>">
<?php

	require PUN_ROOT.'include/parser.php';

	$bg_switch = true;	// Used for switching background color in posts
	$post_count = 0;	// Keep track of post numbers

	// Retrieve the posts (and their respective poster)
	$result = $db->query('SELECT u.title, u.num_posts, g.g_id, g.g_user_title, p.id, p.poster, p.poster_id, p.message, p.hide_smilies, p.posted, p.edited, p.edited_by FROM '.$db->prefix.'posts AS p INNER JOIN '.$db->prefix.'users AS u ON u.id=p.poster_id INNER JOIN '.$db->prefix.'groups AS g ON g.g_id=u.group_id WHERE p.topic_id='.$tid.' ORDER BY p.id LIMIT '.$start_from.','.$pun_user['disp_posts'], true) or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());

	while ($cur_post = $db->fetch_assoc($result))
	{
		$post_count++;

		// If the poster is a registered user.
		if ($cur_post['poster_id'] > 1)
		{
			$poster = '<a href="profile.php?id='.$cur_post['poster_id'].'">'.pun_htmlspecialchars($cur_post['poster']).'</a>';

			// get_title() requires that an element 'username' be present in the array
			$cur_post['username'] = $cur_post['poster'];
			$user_title = get_title($cur_post);

			if ($pun_config['o_censoring'] == '1')
				$user_title = censor_words($user_title);
		}
		// If the poster is a guest (or a user that has been deleted)
		else
		{
			$poster = pun_htmlspecialchars($cur_post['poster']);
			$user_title = $lang_topic['Guest'];
		}

		// Switch the background color for every message.
		$bg_switch = ($bg_switch) ? $bg_switch = false : $bg_switch = true;
		$vtbg = ($bg_switch) ? ' roweven' : ' rowodd';

		// Perform the main parsing of the message (BBCode, smilies, censor words etc)
		$cur_post['message'] = parse_message($cur_post['message'], $cur_post['hide_smilies']);

?>

<div class="blockpost<?php echo $vtbg ?>">
	<a name="<?php echo $cur_post['id'] ?>"></a>
	<h2><span><span class="conr">#<?php echo ($start_from + $post_count) ?>&nbsp;</span><a href="viewtopic.php?pid=<?php echo $cur_post['id'].'#p'.$cur_post['id'] ?>"><?php echo format_time($cur_post['posted']) ?></a></span></h2>
	<div class="box">
		<div class="inbox">
			<div class="postleft">
				<dl>
					<dt><strong><?php echo $poster ?></strong></dt>
					<dd><strong><?php echo $user_title ?></strong></dd>
				</dl>
			</div>
			<div class="postright">
				<h3 class="nosize"><?php echo $lang_common['Message'] ?></h3>
				<div class="postmsg">
					<?php echo $cur_post['message']."\n" ?>
<?php if ($cur_post['edited'] != '') echo "\t\t\t\t\t".'<p class="postedit"><em>'.$lang_topic['Last edit'].' '.pun_htmlspecialchars($cur_post['edited_by']).' ('.format_time($cur_post['edited']).')</em></p>'."\n"; ?>
				</div>
				<?php if ($start_from + $post_count > 1) echo '<p class="multidelete"><label><strong>'.$lang_misc['Select'].'</strong>&nbsp;&nbsp;<input type="checkbox" name="posts['.$cur_post['id'].']" value="1" /></label></p>'."\n" ?>
			</div>
			<div class="clearer"></div>
		</div>
	</div>
</div>




<?php

	}

?>
<div class="postlinksb">
	<div class="inbox">
		<p class="pagelink conl"><?php echo $paging_links ?></p>
		<p class="conr"><input type="submit" name="delete_posts" value="<?php echo $lang_misc['Delete'] ?>"<?php echo $button_status ?> /></p>
		<div class="clearer"></div>
	</div>
</div>
</form>
<?php

	require PUN_ROOT.'footer.php';
}


// Move one or more topics
if (isset($_REQUEST['move_topics']) || isset($_POST['move_topics_to']))
{
	if (isset($_POST['move_topics_to']))
	{
		confirm_referrer('moderate.php');

		if (@preg_match('/[^0-9,]/', $_POST['topics']))
			message($lang_common['Bad request']);

		$topics = explode(',', $_POST['topics']);
		$move_to_forum = isset($_POST['move_to_forum']) ? intval($_POST['move_to_forum']) : 0;
		if (empty($topics) || $move_to_forum < 1)
			message($lang_common['Bad request']);

		// Verify that the topic IDs are valid
		$result = $db->query('SELECT 1 FROM '.$db->prefix.'topics WHERE id IN('.implode(',',$topics).') AND forum_id='.$fid) or error('Unable to check topics', __FILE__, __LINE__, $db->error());

		if ($db->num_rows($result) != count($topics))
			message($lang_common['Bad request']);

		// Delete any redirect topics if there are any (only if we moved/copied the topic back to where it where it was once moved from)
		$db->query('DELETE FROM '.$db->prefix.'topics WHERE forum_id='.$move_to_forum.' AND moved_to IN('.implode(',',$topics).')') or error('Unable to delete redirect topics', __FILE__, __LINE__, $db->error());

		// Move the topic(s)
		$db->query('UPDATE '.$db->prefix.'topics SET forum_id='.$move_to_forum.' WHERE id IN('.implode(',',$topics).')') or error('Unable to move topics', __FILE__, __LINE__, $db->error());

		// Should we create redirect topics?
		if (isset($_POST['with_redirect']))
		{
			while (list(, $cur_topic) = @each($topics))
			{
				// Fetch info for the redirect topic
				$result = $db->query('SELECT poster, subject, posted, last_post FROM '.$db->prefix.'topics WHERE id='.$cur_topic) or error('Unable to fetch topic info', __FILE__, __LINE__, $db->error());
				$moved_to = $db->fetch_assoc($result);

				// Create the redirect topic
				$db->query('INSERT INTO '.$db->prefix.'topics (poster, subject, posted, last_post, moved_to, forum_id) VALUES(\''.$db->escape($moved_to['poster']).'\', \''.$db->escape($moved_to['subject']).'\', '.$moved_to['posted'].', '.$moved_to['last_post'].', '.$cur_topic.', '.$fid.')') or error('Unable to create redirect topic', __FILE__, __LINE__, $db->error());
			}
		}

		update_forum($fid);				// Update the forum FROM which the topic was moved
		update_forum($move_to_forum);	// Update the forum TO which the topic was moved

		$redirect_msg = (count($topics) > 1) ? $lang_misc['Move topics redirect'] : $lang_misc['Move topic redirect'];
		redirect('viewforum.php?id='.$move_to_forum, $redirect_msg);
	}

	if (isset($_POST['move_topics']))
	{
		$topics = isset($_POST['topics']) ? $_POST['topics'] : array();
		if (empty($topics))
			message($lang_misc['No topics selected']);

		$topics = implode(',', array_map('intval', array_keys($topics)));
		$action = 'multi';
	}
	else
	{
		$topics = intval($_GET['move_topics']);
		if ($topics < 1)
			message($lang_common['Bad request']);

		$action = 'single';
	}

	$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / Moderate';
	require PUN_ROOT.'header.php';

?>
<div class="blockform">
	<h2><span><?php echo ($action == 'single') ? $lang_misc['Move topic'] : $lang_misc['Move topics'] ?></span></h2>
	<div class="box">
		<form method="post" action="moderate.php?fid=<?php echo $fid ?>">
			<div class="inform">
			<input type="hidden" name="topics" value="<?php echo $topics ?>" />
				<fieldset>
					<legend><?php echo $lang_misc['Move legend'] ?></legend>
					<div class="infldset">
						<label><?php echo $lang_misc['Move to'] ?>
						<br /><select name="move_to_forum">
<?php

	$result = $db->query('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name FROM '.$db->prefix.'categories AS c INNER JOIN '.$db->prefix.'forums AS f ON c.id=f.cat_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND f.redirect_url IS NULL ORDER BY c.disp_position, c.id, f.disp_position', true) or error('Unable to fetch category/forum list', __FILE__, __LINE__, $db->error());

	$cur_category = 0;
	while ($cur_forum = $db->fetch_assoc($result))
	{
		if ($cur_forum['cid'] != $cur_category)	// A new category since last iteration?
		{
			if ($cur_category)
				echo "\t\t\t\t\t\t\t".'</optgroup>'."\n";

			echo "\t\t\t\t\t\t\t".'<optgroup label="'.pun_htmlspecialchars($cur_forum['cat_name']).'">'."\n";
			$cur_category = $cur_forum['cid'];
		}

		if ($cur_forum['fid'] != $fid)
			echo "\t\t\t\t\t\t\t\t".'<option value="'.$cur_forum['fid'].'">'.pun_htmlspecialchars($cur_forum['forum_name']).'</option>'."\n";
	}

?>
							</optgroup>
						</select>
						<br /></label>
						<div class="rbox">
							<label><input type="checkbox" name="with_redirect" value="1"<?php if ($action == 'single') echo ' checked="checked"' ?> /><?php echo $lang_misc['Leave redirect'] ?><br /></label>
						</div>
					</div>
				</fieldset>
			</div>
			<p><input type="submit" name="move_topics_to" value="<?php echo $lang_misc['Move'] ?>" /><a href="javascript:history.go(-1)"><?php echo $lang_common['Go back'] ?></a></p>
		</form>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


// Delete one or more topics
if (isset($_REQUEST['delete_topics']) || isset($_POST['delete_topics_comply']))
{
	$topics = isset($_POST['topics']) ? $_POST['topics'] : array();
	if (empty($topics))
		message($lang_misc['No topics selected']);

	if (isset($_POST['delete_topics_comply']))
	{
		confirm_referrer('moderate.php');

		if (@preg_match('/[^0-9,]/', $topics))
			message($lang_common['Bad request']);

		require PUN_ROOT.'include/search_idx.php';

		// Verify that the topic IDs are valid
		$result = $db->query('SELECT 1 FROM '.$db->prefix.'topics WHERE id IN('.$topics.') AND forum_id='.$fid) or error('Unable to check topics', __FILE__, __LINE__, $db->error());

		if ($db->num_rows($result) != substr_count($topics, ',') + 1)
			message($lang_common['Bad request']);

		// Delete the topics and any redirect topics
		$db->query('DELETE FROM '.$db->prefix.'topics WHERE id IN('.$topics.') OR moved_to IN('.$topics.')') or error('Unable to delete topic', __FILE__, __LINE__, $db->error());

		// Delete any subscriptions
		$db->query('DELETE FROM '.$db->prefix.'subscriptions WHERE topic_id IN('.$topics.')') or error('Unable to delete subscriptions', __FILE__, __LINE__, $db->error());

		// Create a list of the post ID's in this topic and then strip the search index
		$result = $db->query('SELECT id FROM '.$db->prefix.'posts WHERE topic_id IN('.$topics.')') or error('Unable to fetch posts', __FILE__, __LINE__, $db->error());

		$post_ids = '';
		while ($row = $db->fetch_row($result))
			$post_ids .= ($post_ids != '') ? ','.$row[0] : $row[0];

		// We have to check that we actually have a list of post ID's since we could be deleting just a redirect topic
		if ($post_ids != '')
			strip_search_index($post_ids);

		// Delete posts
		$db->query('DELETE FROM '.$db->prefix.'posts WHERE topic_id IN('.$topics.')') or error('Unable to delete posts', __FILE__, __LINE__, $db->error());

		update_forum($fid);

		redirect('viewforum.php?id='.$fid, $lang_misc['Delete topics redirect']);
	}


	$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / '.$lang_misc['Moderate'];
	require PUN_ROOT.'header.php';

?>
<div class="blockform">
	<h2><?php echo $lang_misc['Delete topics'] ?></h2>
	<div class="box">
		<form method="post" action="moderate.php?fid=<?php echo $fid ?>">
			<input type="hidden" name="topics" value="<?php echo implode(',', array_map('intval', array_keys($topics))) ?>" />
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_misc['Confirm delete legend'] ?></legend>
					<div class="infldset">
						<p><?php echo $lang_misc['Delete topics comply'] ?></p>
					</div>
				</fieldset>
			</div>
			<p><input type="submit" name="delete_topics_comply" value="<?php echo $lang_misc['Delete'] ?>" /><a href="javascript:history.go(-1)"><?php echo $lang_common['Go back'] ?></a></p>
		</form>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


// Open or close one or more topics
else if (isset($_REQUEST['open']) || isset($_REQUEST['close']))
{
	$action = (isset($_REQUEST['open'])) ? 0 : 1;

	// There could be an array of topic ID's in $_POST
	if (isset($_POST['open']) || isset($_POST['close']))
	{
		confirm_referrer('moderate.php');

		$topics = isset($_POST['topics']) ? @array_map('intval', @array_keys($_POST['topics'])) : array();
		if (empty($topics))
			message($lang_misc['No topics selected']);

		$db->query('UPDATE '.$db->prefix.'topics SET closed='.$action.' WHERE id IN('.implode(',', $topics).') AND forum_id='.$fid) or error('Unable to close topics', __FILE__, __LINE__, $db->error());

		$redirect_msg = ($action) ? $lang_misc['Close topics redirect'] : $lang_misc['Open topics redirect'];
		redirect('moderate.php?fid='.$fid, $redirect_msg);
	}
	// Or just one in $_GET
	else
	{
		confirm_referrer('viewtopic.php');

		$topic_id = ($action) ? intval($_GET['close']) : intval($_GET['open']);
		if ($topic_id < 1)
			message($lang_common['Bad request']);

		$db->query('UPDATE '.$db->prefix.'topics SET closed='.$action.' WHERE id='.$topic_id.' AND forum_id='.$fid) or error('Unable to close topic', __FILE__, __LINE__, $db->error());

		$redirect_msg = ($action) ? $lang_misc['Close topic redirect'] : $lang_misc['Open topic redirect'];
		redirect('viewtopic.php?id='.$topic_id, $redirect_msg);
	}
}


// Stick a topic
else if (isset($_GET['stick']))
{
	confirm_referrer('viewtopic.php');

	$stick = intval($_GET['stick']);
	if ($stick < 1)
		message($lang_common['Bad request']);

	$db->query('UPDATE '.$db->prefix.'topics SET sticky=\'1\' WHERE id='.$stick.' AND forum_id='.$fid) or error('Unable to stick topic', __FILE__, __LINE__, $db->error());

	redirect('viewtopic.php?id='.$stick, $lang_misc['Stick topic redirect']);
}


// Unstick a topic
else if (isset($_GET['unstick']))
{
	confirm_referrer('viewtopic.php');

	$unstick = intval($_GET['unstick']);
	if ($unstick < 1)
		message($lang_common['Bad request']);

	$db->query('UPDATE '.$db->prefix.'topics SET sticky=\'0\' WHERE id='.$unstick.' AND forum_id='.$fid) or error('Unable to unstick topic', __FILE__, __LINE__, $db->error());

	redirect('viewtopic.php?id='.$unstick, $lang_misc['Unstick topic redirect']);
}


// No specific forum moderation action was specified in the query string, so we'll display the moderator forum

// Load the viewforum.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/forum.php';

// Fetch some info about the forum
$result = $db->query('SELECT f.forum_name, f.redirect_url, f.num_topics FROM '.$db->prefix.'forums AS f LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND f.id='.$fid) or error('Unable to fetch forum info', __FILE__, __LINE__, $db->error());
if (!$db->num_rows($result))
	message($lang_common['Bad request']);

$cur_forum = $db->fetch_assoc($result);

// Is this a redirect forum? In that case, abort!
if ($cur_forum['redirect_url'] != '')
	message($lang_common['Bad request']);

$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / '.pun_htmlspecialchars($cur_forum['forum_name']);
require PUN_ROOT.'header.php';

// Determine the topic offset (based on $_GET['p'])
$num_pages = ceil($cur_forum['num_topics'] / $pun_user['disp_topics']);

$p = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $num_pages) ? 1 : $_GET['p'];
$start_from = $pun_user['disp_topics'] * ($p - 1);

// Generate paging links
$paging_links = $lang_common['Pages'].': '.paginate($num_pages, $p, 'moderate.php?fid='.$fid)

?>
<div class="linkst">
	<div class="inbox">
		<p class="pagelink conl"><?php echo $paging_links ?></p>
		<ul><li><a href="index.php"><?php echo $lang_common['Index'] ?></a>&nbsp;</li><li>&raquo;&nbsp;<?php echo pun_htmlspecialchars($cur_forum['forum_name']) ?></li></ul>
		<div class="clearer"></div>
	</div>
</div>

<form method="post" action="moderate.php?fid=<?php echo $fid ?>">
<div id="vf" class="blocktable">
	<h2><span><?php echo pun_htmlspecialchars($cur_forum['forum_name']) ?></span></h2>
	<div class="box">
		<div class="inbox">
			<table cellspacing="0">
			<thead>
				<tr>
					<th class="tcl" scope="col"><?php echo $lang_common['Topic'] ?></th>
					<th class="tc2" scope="col"><?php echo $lang_common['Replies'] ?></th>
					<th class="tc3" scope="col"><?php echo $lang_forum['Views'] ?></th>
					<th class="tcr"><?php echo $lang_common['Last post'] ?></th>
					<th class="tcmod" scope="col"><?php echo $lang_misc['Select'] ?></th>
				</tr>
			</thead>
			<tbody>
<?php

// Select topics
$result = $db->query('SELECT id, poster, subject, posted, last_post, last_post_id, last_poster, num_views, num_replies, closed, sticky, moved_to FROM '.$db->prefix.'topics WHERE forum_id='.$fid.' ORDER BY sticky DESC, last_post DESC LIMIT '.$start_from.', '.$pun_user['disp_topics']) or error('Unable to fetch topic list for forum', __FILE__, __LINE__, $db->error());

// If there are topics in this forum.
if ($db->num_rows($result))
{
	$button_status = '';

	while ($cur_topic = $db->fetch_assoc($result))
	{

		$icon_text = $lang_common['Normal icon'];
		$item_status = '';
		$icon_type = 'icon';

		if ($cur_topic['moved_to'] == null)
		{
			$last_post = '<a href="viewtopic.php?pid='.$cur_topic['last_post_id'].'#p'.$cur_topic['last_post_id'].'">'.format_time($cur_topic['last_post']).'</a> '.$lang_common['by'].' '.pun_htmlspecialchars($cur_topic['last_poster']);
			$ghost_topic = false;
		}
		else
		{
			$last_post = '&nbsp;';
			$ghost_topic = true;
		}

		if ($pun_config['o_censoring'] == '1')
			$cur_topic['subject'] = censor_words($cur_topic['subject']);

		if ($cur_topic['moved_to'] != 0)
			$subject = $lang_forum['Moved'].': <a href="viewtopic.php?id='.$cur_topic['moved_to'].'">'.pun_htmlspecialchars($cur_topic['subject']).'</a> <span class="byuser">'.$lang_common['by'].' '.pun_htmlspecialchars($cur_topic['poster']).'</span>';
		else if ($cur_topic['closed'] == '0')
			$subject = '<a href="viewtopic.php?id='.$cur_topic['id'].'">'.pun_htmlspecialchars($cur_topic['subject']).'</a> <span>'.$lang_common['by'].'&nbsp;'.pun_htmlspecialchars($cur_topic['poster']).'</span>';
		else
		{
			$subject = '<a href="viewtopic.php?id='.$cur_topic['id'].'">'.pun_htmlspecialchars($cur_topic['subject']).'</a> <span class="byuser">'.$lang_common['by'].' '.pun_htmlspecialchars($cur_topic['poster']).'</span>';
			$icon_text = $lang_common['Closed icon'];
			$item_status = 'iclosed';
		}

		if ($cur_topic['last_post'] > $pun_user['last_visit'] && !$ghost_topic)
		{
			$icon_text .= ' '.$lang_common['New icon'];
			$item_status .= ' inew';
			$icon_type = 'icon inew';
			$subject = '<strong>'.$subject.'</strong>';
			$subject_new_posts = '<span class="newtext">[&nbsp;<a href="viewtopic.php?id='.$cur_topic['id'].'&amp;action=new" title="'.$lang_common['New posts info'].'">'.$lang_common['New posts'].'</a>&nbsp;]</span>';
		}
		else
			$subject_new_posts = null;

		// We won't display "the dot", but we add the spaces anyway
		if ($pun_config['o_show_dot'] == '1')
			$subject = '&nbsp;&nbsp;'.$subject;

		if ($cur_topic['sticky'] == '1')
		{
			$subject = '<span class="stickytext">'.$lang_forum['Sticky'].': </span>'.$subject;
			$item_status .= ' isticky';
			$icon_text .= ' '.$lang_forum['Sticky'];
		}

		$num_pages_topic = ceil(($cur_topic['num_replies'] + 1) / $pun_user['disp_posts']);

		if ($num_pages_topic > 1)
			$subject_multipage = '[ '.paginate($num_pages_topic, -1, 'viewtopic.php?id='.$cur_topic['id']).' ]';
		else
			$subject_multipage = null;

		// Should we show the "New posts" and/or the multipage links?
		if (!empty($subject_new_posts) || !empty($subject_multipage))
		{
			$subject .= '&nbsp; '.(!empty($subject_new_posts) ? $subject_new_posts : '');
			$subject .= !empty($subject_multipage) ? ' '.$subject_multipage : '';
		}

?>
				<tr<?php if ($item_status != '') echo ' class="'.trim($item_status).'"'; ?>>
					<td class="tcl">
						<div class="<?php echo $icon_type ?>"><div class="nosize"><?php echo trim($icon_text) ?></div></div>
						<div class="tclcon">
							<?php echo $subject."\n" ?>
						</div>
					</td>
					<td class="tc2"><?php echo (!$ghost_topic) ? $cur_topic['num_replies'] : '&nbsp;' ?></td>
					<td class="tc3"><?php echo (!$ghost_topic) ? $cur_topic['num_views'] : '&nbsp;' ?></td>
					<td class="tcr"><?php echo $last_post ?></td>
					<td class="tcmod"><input type="checkbox" name="topics[<?php echo $cur_topic['id'] ?>]" value="1" /></td>
				</tr>
<?php

	}
}
else
{
	$button_status = ' disabled';
	echo "\t\t\t\t\t".'<tr><td class="tcl" colspan="5">'.$lang_forum['Empty forum'].'</td></tr>'."\n";
}

?>
			</tbody>
			</table>
		</div>
	</div>
</div>

<div class="linksb">
	<div class="inbox">
		<p class="pagelink conl"><?php echo $paging_links ?></p>
		<p class="conr"><input type="submit" name="move_topics" value="<?php echo $lang_misc['Move'] ?>"<?php echo $button_status ?> />&nbsp;&nbsp;<input type="submit" name="delete_topics" value="<?php echo $lang_misc['Delete'] ?>"<?php echo $button_status ?> />&nbsp;&nbsp;<input type="submit" name="open" value="<?php echo $lang_misc['Open'] ?>"<?php echo $button_status ?> />&nbsp;&nbsp;<input type="submit" name="close" value="<?php echo $lang_misc['Close'] ?>"<?php echo $button_status ?> /></p>
		<div class="clearer"></div>
	</div>
</div>
</form>
<?php

require PUN_ROOT.'footer.php';
