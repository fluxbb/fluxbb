<?php

/**
 * Copyright (C) 2008-2011 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';


// This particular function doesn't require forum-based moderator access. It can be used
// by all moderators and admins
if (isset($_GET['get_host']))
{
	if (!$pun_user['is_admmod'])
		message($lang->t('No permission'));

	// Is get_host an IP address or a post ID?
	if (@preg_match('%^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$%', $_GET['get_host']) || @preg_match('%^((([0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}:[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){5}:([0-9A-Fa-f]{1,4}:)?[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){4}:([0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){3}:([0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){2}:([0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(([0-9A-Fa-f]{1,4}:){0,5}:((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(::([0-9A-Fa-f]{1,4}:){0,5}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|([0-9A-Fa-f]{1,4}::([0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})|(::([0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){1,7}:))$%', $_GET['get_host']))
		$ip = $_GET['get_host'];
	else
	{
		$get_host = intval($_GET['get_host']);
		if ($get_host < 1)
			message($lang->t('Bad request'));

		$query = $db->select(array('poster_ip' => 'p.poster_ip'), 'posts AS p');
		$query->where = 'id = :pid';

		$params = array(':pid' => $get_host);

		$result = $query->run($params);
		if (empty($result))
			message($lang->t('Bad request'));

		$ip = $result[0]['poster_ip'];
		unset ($result, $query, $params);
	}

	// Load the misc.php language file
	$lang->load('misc');

	message($lang->t('Host info 1', $ip).'<br />'.$lang->t('Host info 2', @gethostbyaddr($ip)).'<br /><br /><a href="admin_users.php?show_users='.$ip.'">'.$lang->t('Show more users').'</a>');
}


// All other functions require moderator/admin access
$fid = isset($_GET['fid']) ? intval($_GET['fid']) : 0;
if ($fid < 1)
	message($lang->t('Bad request'));

$query = $db->select(array('moderators' => 'f.moderators'), 'forums AS f');
$query->where = 'f.id = :forum_id';

$params = array(':forum_id' => $fid);

$result = $query->run($params);
$mods_array = empty($result[0]['moderators']) ? array() : unserialize($result[0]['moderators']);
unset ($result, $query, $params);

if ($pun_user['g_id'] != PUN_ADMIN && ($pun_user['g_moderator'] == '0' || !array_key_exists($pun_user['username'], $mods_array)))
	message($lang->t('No permission'));

// Get topic/forum tracking data
if (!$pun_user['is_guest'])
	$tracked_topics = get_tracked_topics();

// Load the misc.php language file
$lang->load('misc');


// All other topic moderation features require a topic ID in GET
if (isset($_GET['tid']))
{
	$tid = intval($_GET['tid']);
	if ($tid < 1)
		message($lang->t('Bad request'));

	// Fetch some info about the topic
	$query = $db->select(array('subject' => 't.subject', 'num_replies' => 't.num_replies', 'first_post_id' => 't.first_post_id', 'forum_id' => 'f.id AS forum_id', 'forum_name' => 'forum_name'), 'topics AS t');

	$query->innerJoin('f', 'forums AS f', 'f.id = t.forum_id');

	$query->leftJoin('fp', 'forum_perms AS fp', 'fp.forum_id = f.id AND fp.group_id = :group_id');

	$query->where = '(fp.read_forum IS NULL OR fp.read_forum = 1) AND f.id = :forum_id AND t.id = :topic_id AND t.moved_to IS NULL';

	$params = array(':group_id' => $pun_user['g_id'], ':forum_id' => $fid, ':topic_id' => $tid);

	$result = $query->run($params);
	if (empty($result))
		message($lang->t('Bad request'));

	$cur_topic = $result[0];
	unset ($result, $query, $params);

	// Delete one or more posts
	if (isset($_POST['delete_posts']) || isset($_POST['delete_posts_comply']))
	{
		if (isset($_POST['delete_posts_comply']))
		{
			confirm_referrer('moderate.php');

			if (@preg_match('%[^0-9,]%', $_POST['posts']))
				message($lang->t('Bad request'));

			$posts = explode(',', $_POST['posts']);
			if (empty($posts))
				message($lang->t('No posts selected'));

			// How many posts did we just delete?
			$num_posts_deleted = count($posts);

			// Verify that the post IDs are valid
			$query = $db->select(array('num_posts' => 'COUNT(p.id) AS num_posts'), 'posts AS p');
			$query->where = 'p.id IN :pids AND p.topic_id = :topic_id';

			$params = array(':pids' => $posts, ':topic_id' => $tid);

			$result = $query->run($params);
			if ($result[0]['num_posts'] != $num_posts_deleted)
				message($lang->t('Bad request'));

			unset ($result, $query, $params);

			// Delete the posts
			$query = $db->delete('posts');
			$query->where = 'id IN :pids';

			$params = array(':pids' => $posts);

			$query->run($params);
			unset ($query, $params);

			require PUN_ROOT.'include/search_idx.php';
			strip_search_index($posts);

			// Get last_post, last_post_id, and last_poster for the topic after deletion
			$query = $db->select(array('id' => 'p.id', 'poster' => 'p.poster', 'posted' => 'p.posted'), 'posts AS p');
			$query->where = 'p.topic_id = :topic_id';
			$query->order = array('id' => 'p.id DESC');
			$query->limit = 1;

			$params = array(':topic_id' => $tid);

			$result = $query->run($params);
			$last_post = $result[0];
			unset ($result, $query, $params);

			// Update the topic
			$query = $db->update(array('last_post' => ':last_post', 'last_post_id' => ':last_post_id', 'last_poster' => ':last_poster', 'num_replies' => 'num_replies - :num_deleted'), 'topics');
			$query->where = 'id = :topic_id';

			$params = array(':last_post' => $last_post['posted'], ':last_post_id' => $last_post['id'], ':last_poster' => $last_post['poster'], ':num_deleted' => $num_posts_deleted, ':topic_id' => $tid);

			$query->run($params);
			unset ($query, $params);

			update_forum($fid);

			redirect('viewtopic.php?id='.$tid, $lang->t('Delete posts redirect'));
		}

		$posts = isset($_POST['posts']) ? $_POST['posts'] : array();
		if (empty($posts))
			message($lang->t('No posts selected'));

		$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Moderate'));
		define('PUN_ACTIVE_PAGE', 'index');
		require PUN_ROOT.'header.php';

?>
<div class="blockform">
	<h2><span><?php echo $lang->t('Delete posts') ?></span></h2>
	<div class="box">
		<form method="post" action="moderate.php?fid=<?php echo $fid ?>&amp;tid=<?php echo $tid ?>">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang->t('Confirm delete legend') ?></legend>
					<div class="infldset">
						<input type="hidden" name="posts" value="<?php echo implode(',', array_map('intval', array_keys($posts))) ?>" />
						<p><?php echo $lang->t('Delete posts comply') ?></p>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="delete_posts_comply" value="<?php echo $lang->t('Delete') ?>" /> <a href="javascript:history.go(-1)"><?php echo $lang->t('Go back') ?></a></p>
		</form>
	</div>
</div>
<?php

		require PUN_ROOT.'footer.php';
	}
	else if (isset($_POST['split_posts']) || isset($_POST['split_posts_comply']))
	{
		if (isset($_POST['split_posts_comply']))
		{
			confirm_referrer('moderate.php');

			if (@preg_match('%[^0-9,]%', $_POST['posts']))
				message($lang->t('Bad request'));

			$posts = explode(',', $_POST['posts']);
			if (empty($posts))
				message($lang->t('No posts selected'));

			$move_to_forum = isset($_POST['move_to_forum']) ? intval($_POST['move_to_forum']) : 0;
			if ($move_to_forum < 1)
				message($lang->t('Bad request'));

			// How many posts did we just split off?
			$num_posts_splitted = count($posts);

			// Verify that the post IDs are valid
			$query = $db->select(array('num_posts' => 'COUNT(p.id) AS num_posts'), 'posts AS p');
			$query->where = 'p.id IN :pids AND p.topic_id = :topic_id';

			$params = array(':pids' => $posts, ':topic_id' => $tid);

			$result = $query->run($params);
			if ($result[0]['num_posts'] != $num_posts_splitted)
				message($lang->t('Bad request'));

			unset ($result, $query, $params);

			// Verify that the move to forum ID is valid
			$query = $db->select(array('one' => '1'), 'forums AS f');

			$query->leftJoin('fp', 'forum_perms AS fp', 'fp.group_id = :group_id AND fp.forum_id = :forum_id');

			$query->where = 'f.redirect_url IS NULL AND (fp.post_topics IS NULL OR fp.post_topics = 1)';

			$params = array(':group_id' => $pun_user['g_id'], ':forum_id' => $move_to_forum);

			$result = $query->run($params);
			if (empty($result))
				message($lang->t('Bad request'));

			unset ($result, $query, $params);

			// Load the post.php language file
			$lang->load('post');

			// Check subject
			$new_subject = isset($_POST['new_subject']) ? pun_trim($_POST['new_subject']) : '';

			if ($new_subject == '')
				message($lang->t('No subject'));
			else if (pun_strlen($new_subject) > 70)
				message($lang->t('Too long subject'));

			// Get data from the new first post
			$query = $db->select(array('id' => 'p.id', 'poster' => 'p.poster', 'posted' => 'p.posted'), 'posts AS p');
			$query->where = 'p.id IN :pids';
			$query->order = array('id' => 'o.id ASC');
			$query->limit = 1;

			$params = array(':pids' => $posts);

			$result = $query->run($params);
			$first_post = $result[0];
			unset ($result, $query, $params);

			// Create the new topic
			$query = $db->insert(array('poster' => ':poster', 'subject' => ':subject', 'posted' => ':posted', 'first_post_id' => ':first_post_id', 'forum_id' => ':forum_id'), 'topics');
			$params = array(':poster' => $first_post['poster'], ':subject' => $new_subject, ':posted' => $first_post['posted'], ':first_post_id' => $first_post['id'], ':forum_id' => $move_to_forum);

			$query->run($params);
			$new_tid = $db->insertId();
			unset ($query, $params);

			// Move the posts to the new topic
			$query = $db->update(array('topic_id' => ':topic_id'), 'posts');
			$query->where = 'id IN :pids';

			$params = array(':topic_id' => $new_tid, ':pids' => $posts);

			$query->run($params);
			unset ($query, $params);

			// Get last_post, last_post_id, and last_poster from the topic and update it
			$query = $db->select(array('id' => 'p.id', 'poster' => 'p.poster', 'posted' => 'p.posted'), 'posts AS p');
			$query->where = 'p.topic_id = :topic_id';
			$query->order = array('id' => 'p.id DESC');
			$query->limit = 1;

			$params = array(':topic_id' => $tid);
			$result = $query->run($params);
			$last_post = $result[0];
			unset ($result, $query, $params);

			$query = $db->update(array('last_post' => ':last_post', 'last_post_id' => ':last_post_id', 'last_poster' => ':last_poster', 'num_replies' => 'num_replies - :num_splitted'), 'topics');
			$query->where = 'id = :topic_id';

			$params = array(':last_post' => $last_post['posted'], ':last_post_id' => $last_post['id'], ':last_poster' => $last_post['poster'], ':num_splitted' => $num_posts_splitted, ':topic_id' => $tid);

			$query->run($params);
			unset ($query, $params);

			// Get last_post, last_post_id, and last_poster from the new topic and update it
			$query = $db->select(array('id' => 'p.id', 'poster' => 'p.poster', 'posted' => 'p.posted'), 'posts AS p');
			$query->where = 'p.topic_id = :topic_id';
			$query->order = array('id' => 'p.id DESC');
			$query->limit = 1;

			$params = array(':topic_id' => $new_tid);
			$result = $query->run($params);
			$last_post = $result[0];
			unset ($result, $query, $params);

			$query = $db->update(array('last_post' => ':last_post', 'last_post_id' => ':last_post_id', 'last_poster' => ':last_poster', 'num_replies' => ':num_replies'), 'topics');
			$query->where = 'id = :topic_id';

			$params = array(':last_post' => $last_post['posted'], ':last_post_id' => $last_post['id'], ':last_poster' => $last_post['poster'], ':num_replies' => $num_posts_splitted - 1, ':topic_id' => $new_tid);

			$query->run($params);
			unset ($query, $params);

			update_forum($fid);
			update_forum($move_to_forum);

			redirect('viewtopic.php?id='.$new_tid, $lang->t('Split posts redirect'));
		}

		$query = $db->select(array('cid' => 'c.id AS cid', 'cat_name' => 'c.cat_name', 'fid' => 'f.id AS fid', 'forum_name' => 'f.forum_name'), 'categories AS c');

		$query->innerJoin('f', 'forums AS f', 'c.id = f.cat_id');

		$query->leftJoin('fp', 'forum_perms AS fp', 'fp.forum_id = f.id AND fp.group_id = :group_id');

		$query->where = '(fp.post_topics IS NULL OR fp.post_topics = 1) AND f.redirect_url IS NULL';
		$query->order = array('cposition' => 'c.disp_position ASC', 'cid' => 'c.id ASC', 'fposition' => 'f.disp_position ASC');

		$params = array(':group_id' => $pun_user['g_id']);

		$result = $query->run($params);

		$posts = isset($_POST['posts']) ? $_POST['posts'] : array();
		if (empty($posts))
			message($lang->t('No posts selected'));

		$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Moderate'));
		$focus_element = array('subject','new_subject');
		define('PUN_ACTIVE_PAGE', 'index');
		require PUN_ROOT.'header.php';

?>
<div class="blockform">
	<h2><span><?php echo $lang->t('Split posts') ?></span></h2>
	<div class="box">
		<form id="subject" method="post" action="moderate.php?fid=<?php echo $fid ?>&amp;tid=<?php echo $tid ?>">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang->t('Confirm split legend') ?></legend>
					<div class="infldset">
						<input type="hidden" name="posts" value="<?php echo implode(',', array_map('intval', array_keys($posts))) ?>" />
						<label class="required"><strong><?php echo $lang->t('New subject') ?> <span><?php echo $lang->t('Required') ?></span></strong><br /><input type="text" name="new_subject" size="80" maxlength="70" /><br /></label>
						<label><?php echo $lang->t('Move to') ?>
						<br /><select name="move_to_forum">
<?php

	$cur_category = 0;
	foreach ($result as $cur_forum)
	{
		if ($cur_forum['cid'] != $cur_category) // A new category since last iteration?
		{
			if ($cur_category)
				echo "\t\t\t\t\t\t\t".'</optgroup>'."\n";

			echo "\t\t\t\t\t\t\t".'<optgroup label="'.pun_htmlspecialchars($cur_forum['cat_name']).'">'."\n";
			$cur_category = $cur_forum['cid'];
		}

		echo "\t\t\t\t\t\t\t\t".'<option value="'.$cur_forum['fid'].'"'.($fid == $cur_forum['fid'] ? ' selected="selected"' : '').'>'.pun_htmlspecialchars($cur_forum['forum_name']).'</option>'."\n";
	}

	unset ($result, $query, $params);

?>
							</optgroup>
						</select>
						<br /></label>
						<p><?php echo $lang->t('Split posts comply') ?></p>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="split_posts_comply" value="<?php echo $lang->t('Split') ?>" /> <a href="javascript:history.go(-1)"><?php echo $lang->t('Go back') ?></a></p>
		</form>
	</div>
</div>
<?php

		require PUN_ROOT.'footer.php';
	}


	// Show the moderate posts view

	// Load the viewtopic.php language file
	$lang->load('topic');

	// Used to disable the Move and Delete buttons if there are no replies to this topic
	$button_status = ($cur_topic['num_replies'] == 0) ? ' disabled="disabled"' : '';


	// Determine the post offset (based on $_GET['p'])
	$num_pages = ceil(($cur_topic['num_replies'] + 1) / $pun_user['disp_posts']);

	$p = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $num_pages) ? 1 : intval($_GET['p']);
	$start_from = $pun_user['disp_posts'] * ($p - 1);

	// Generate paging links
	$paging_links = '<span class="pages-label">'.$lang->t('Pages').' </span>'.paginate($num_pages, $p, 'moderate.php?fid='.$fid.'&amp;tid='.$tid);


	if ($pun_config['o_censoring'] == '1')
		$cur_topic['subject'] = censor_words($cur_topic['subject']);


	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), pun_htmlspecialchars($cur_topic['forum_name']), pun_htmlspecialchars($cur_topic['subject']));
	define('PUN_ACTIVE_PAGE', 'index');
	require PUN_ROOT.'header.php';

?>
<div class="linkst">
	<div class="inbox crumbsplus">
		<ul class="crumbs">
			<li><a href="index.php"><?php echo $lang->t('Index') ?></a></li>
			<li><span>»&#160;</span><a href="viewforum.php?id=<?php echo $fid ?>"><?php echo pun_htmlspecialchars($cur_topic['forum_name']) ?></a></li>
			<li><span>»&#160;</span><a href="viewtopic.php?id=<?php echo $tid ?>"><?php echo pun_htmlspecialchars($cur_topic['subject']) ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang->t('Moderate') ?></strong></li>
		</ul>
		<div class="pagepost">
			<p class="pagelink conl"><?php echo $paging_links ?></p>
		</div>
		<div class="clearer"></div>
	</div>
</div>

<form method="post" action="moderate.php?fid=<?php echo $fid ?>&amp;tid=<?php echo $tid ?>">
<?php

	require PUN_ROOT.'include/parser.php';

	$post_count = 0; // Keep track of post numbers

	// Retrieve a list of post IDs, LIMIT is (really) expensive so we only fetch the IDs here then later fetch the remaining data
	$query = $db->select(array('id' => 'p.id'), 'posts AS p');
	$query->where = 'p.topic_id = :topic_id';
	$query->order = array('id' => 'p.id ASC');
	$query->offset = $start_from;
	$query->limit = $pun_user['disp_posts'];

	$params = array(':topic_id' => $tid);

	$post_ids = $query->run($params);
	unset ($query, $params);

	// If there are posts in this topic
	if (empty($post_ids))
		error('The post table and topic table seem to be out of sync!', __FILE__, __LINE__);

	// Translate from a 3d array into 2d array: $post_ids[0]['id'] -> $post_ids[0]
	foreach ($post_ids as $key => $value)
		$post_ids[$key] = $value['id'];

	// Retrieve the posts (and their respective poster)
	$query = $db->select(array('title' => 'u.title', 'num_posts' => 'u.num_posts', 'g_id' => 'g.g_id', 'g_user_title' => 'g.g_user_title', 'id' => 'p.id', 'poster' => 'p.poster', 'poster_id' => 'p.poster_id', 'message' => 'p.message', 'hide_smilies' => 'p.hide_smilies', 'posted' => 'p.posted', 'edited' => 'p.edited', 'edited_by' => 'p.edited_by'), 'posts AS p');

	$query->innerJoin('u', 'users AS u', 'u.id = p.poster_id');

	$query->Innerjoin('g', 'groups AS g', 'g.g_id = u.group_id');

	$query->where = 'p.id IN :pids';
	$query->order = array('id' => 'p.id ASC');

	$params = array(':pids' => $post_ids);

	$result = $query->run($params);
	foreach ($result as $cur_post)
	{
		$post_count++;

		// If the poster is a registered user
		if ($cur_post['poster_id'] > 1)
		{
			if ($pun_user['g_view_users'] == '1')
				$poster = '<a href="profile.php?id='.$cur_post['poster_id'].'">'.pun_htmlspecialchars($cur_post['poster']).'</a>';
			else
				$poster = pun_htmlspecialchars($cur_post['poster']);

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
			$user_title = $lang->t('Guest');
		}

		// Perform the main parsing of the message (BBCode, smilies, censor words etc)
		$cur_post['message'] = parse_message($cur_post['message'], $cur_post['hide_smilies']);

?>

<div id="p<?php echo $cur_post['id'] ?>" class="blockpost<?php if($cur_post['id'] == $cur_topic['first_post_id']) echo ' firstpost' ?><?php echo ($post_count % 2 == 0) ? ' roweven' : ' rowodd' ?><?php if ($post_count == 1) echo ' blockpost1' ?>">
	<h2><span><span class="conr">#<?php echo ($start_from + $post_count) ?></span> <a href="viewtopic.php?pid=<?php echo $cur_post['id'].'#p'.$cur_post['id'] ?>"><?php echo format_time($cur_post['posted']) ?></a></span></h2>
	<div class="box">
		<div class="inbox">
			<div class="postbody">
				<div class="postleft">
					<dl>
						<dt><strong><?php echo $poster ?></strong></dt>
						<dd class="usertitle"><strong><?php echo $user_title ?></strong></dd>
					</dl>
				</div>
				<div class="postright">
					<h3 class="nosize"><?php echo $lang->t('Message') ?></h3>
					<div class="postmsg">
						<?php echo $cur_post['message']."\n" ?>
<?php if ($cur_post['edited'] != '') echo "\t\t\t\t\t\t".'<p class="postedit"><em>'.$lang->t('Last edit').' '.pun_htmlspecialchars($cur_post['edited_by']).' ('.format_time($cur_post['edited']).')</em></p>'."\n"; ?>
					</div>
				</div>
			</div>
		</div>
		<div class="inbox">
			<div class="postfoot clearb">
				<div class="postfootright"><?php echo ($cur_post['id'] != $cur_topic['first_post_id']) ? '<p class="multidelete"><label><strong>'.$lang->t('Select').'</strong>&#160;<input type="checkbox" name="posts['.$cur_post['id'].']" value="1" /></label></p>' : '<p>'.$lang->t('Cannot select first').'</p>' ?></div>
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
			<p class="conr modbuttons"><input type="submit" name="split_posts" value="<?php echo $lang->t('Split') ?>"<?php echo $button_status ?> /> <input type="submit" name="delete_posts" value="<?php echo $lang->t('Delete') ?>"<?php echo $button_status ?> /></p>
			<div class="clearer"></div>
		</div>
		<ul class="crumbs">
			<li><a href="index.php"><?php echo $lang->t('Index') ?></a></li>
			<li><span>»&#160;</span><a href="viewforum.php?id=<?php echo $fid ?>"><?php echo pun_htmlspecialchars($cur_topic['forum_name']) ?></a></li>
			<li><span>»&#160;</span><a href="viewtopic.php?id=<?php echo $tid ?>"><?php echo pun_htmlspecialchars($cur_topic['subject']) ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang->t('Moderate') ?></strong></li>
		</ul>
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

		if (@preg_match('%[^0-9,]%', $_POST['topics']))
			message($lang->t('Bad request'));

		$topics = explode(',', $_POST['topics']);
		$move_to_forum = isset($_POST['move_to_forum']) ? intval($_POST['move_to_forum']) : 0;
		if (empty($topics) || $move_to_forum < 1)
			message($lang->t('Bad request'));

		// Verify that the topic IDs are valid
		$query = $db->select(array('num_topics' => 'COUNT(t.id) AS num_topics'), 'topics AS t');
		$query->where = 't.id IN :tids AND t.forum_id = :forum_id';

		$params = array(':tids' => $topics, ':forum_id' => $fid);

		$result = $query->run($params);
		if ($result[0]['num_topics'] != count($topics))
			message($lang->t('Bad request'));

		unset ($result, $query, $params);

		// Verify that the move to forum ID is valid
		$query = $db->select(array('one' => '1'), 'forums AS f');

		$query->leftJoin('fp', 'forum_perms AS fp', 'fp.group_id = :group_id AND fp.forum_id = :forum_id');

		$query->where = 'f.redirect_url IS NULL AND (fp.post_topics IS NULL OR fp.post_topics = 1)';

		$params = array(':group_id' => $pun_user['g_id'], ':forum_id' => $move_to_forum);

		if (empty($result))
			message($lang->t('Bad request'));

		unset ($result, $query, $params);

		// Delete any redirect topics if there are any (only if we moved/copied the topic back to where it was once moved from)
		$query = $db->delete('topics');
		$query->where = 'forum_id = :forum_id AND moved_to IN :tids';

		$params = array(':forum_id' => $move_to_forum, ':tids' => $topics);

		$query->run($params);
		unset ($query, $params);

		// Move the topic(s)
		$query = $db->update(array('forum_id' => ':forum_id'), 'topics');
		$query->where = 'id IN :tids';

		$params = array(':forum_id' => $move_to_forum, ':tids' => $topics);

		$query->run($params);
		unset ($query, $params);

		// Should we create redirect topics?
		if (isset($_POST['with_redirect']))
		{
			$query = $db->select(array('poster' => 't.poster', 'subject' => 't.subject', 'posted' => 't.posted', 'last_post' => 't.last_post'), 'topics AS t');
			$query->where = 't.id IN :tids';

			$params = array(':tids' => $topics);

			$result = $query->run($params);
			unset ($query, $params);

			$insert_query = $db->insert(array('poster' => ':poster', 'subject' => ':subject', 'posted' => ':posted', 'last_post' => ':last_post', 'moved_to' => ':moved_to', 'forum_id' => ':forum_id'), 'topics');

			foreach ($result as $cur_topic)
			{
				$params = array(':poster' => $cur_topic['poster'], ':subject' => $cur_topic['subject'], ':posted' => $cur_topic['posted'], ':last_post' => $cur_topic['last_post'], ':moved_to' => $cur_topic,':forum_id' => $fid);

				$insert_query->run($params);
				unset ($params);
			}

			unset ($result, $insert_query);
		}

		update_forum($fid); // Update the forum FROM which the topic was moved
		update_forum($move_to_forum); // Update the forum TO which the topic was moved

		$redirect_msg = (count($topics) > 1) ? $lang->t('Move topics redirect') : $lang->t('Move topic redirect');
		redirect('viewforum.php?id='.$move_to_forum, $redirect_msg);
	}

	if (isset($_POST['move_topics']))
	{
		$topics = isset($_POST['topics']) ? $_POST['topics'] : array();
		if (empty($topics))
			message($lang->t('No topics selected'));

		$topics = implode(',', array_map('intval', array_keys($topics)));
		$action = 'multi';
	}
	else
	{
		$topics = intval($_GET['move_topics']);
		if ($topics < 1)
			message($lang->t('Bad request'));

		$action = 'single';
	}

	$query = $db->select(array('cid' => 'c.id AS cid', 'cat_name' => 'c.cat_name', 'fid' => 'f.id AS fid', 'forum_name' => 'f.forum_name'), 'categories AS c');

	$query->innerJoin('f', 'forums AS f', 'c.id = f.cat_id');

	$query->leftJoin('fp', 'forum_perms AS fp', 'fp.forum_id = f.id AND fp.group_id = :group_id');

	$query->where = '(fp.post_topics IS NULL OR fp.post_topics = 1) AND f.redirect_url IS NULL';
	$query->order = array('cposition' => 'c.disp_position ASC', 'cid' => 'c.id ASC', 'fposition' => 'f.disp_position ASC');

	$params = array(':group_id' => $pun_user['g_id']);

	$result = $query->run($params);
	unset ($query, $params);

	if (count($result) < 2)
		message($lang->t('Nowhere to move'));

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Moderate'));
	define('PUN_ACTIVE_PAGE', 'index');
	require PUN_ROOT.'header.php';

?>
<div class="blockform">
	<h2><span><?php echo ($action == 'single') ? $lang->t('Move topic') : $lang->t('Move topics') ?></span></h2>
	<div class="box">
		<form method="post" action="moderate.php?fid=<?php echo $fid ?>">
			<div class="inform">
			<input type="hidden" name="topics" value="<?php echo $topics ?>" />
				<fieldset>
					<legend><?php echo $lang->t('Move legend') ?></legend>
					<div class="infldset">
						<label><?php echo $lang->t('Move to') ?>
						<br /><select name="move_to_forum">
<?php

	$cur_category = 0;
	foreach ($result as $cur_forum)
	{
		if ($cur_forum['cid'] != $cur_category) // A new category since last iteration?
		{
			if ($cur_category)
				echo "\t\t\t\t\t\t\t".'</optgroup>'."\n";

			echo "\t\t\t\t\t\t\t".'<optgroup label="'.pun_htmlspecialchars($cur_forum['cat_name']).'">'."\n";
			$cur_category = $cur_forum['cid'];
		}

		if ($cur_forum['fid'] != $fid)
			echo "\t\t\t\t\t\t\t\t".'<option value="'.$cur_forum['fid'].'">'.pun_htmlspecialchars($cur_forum['forum_name']).'</option>'."\n";
	}

	unset ($result);

?>
							</optgroup>
						</select>
						<br /></label>
						<div class="rbox">
							<label><input type="checkbox" name="with_redirect" value="1"<?php if ($action == 'single') echo ' checked="checked"' ?> /><?php echo $lang->t('Leave redirect') ?><br /></label>
						</div>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="move_topics_to" value="<?php echo $lang->t('Move') ?>" /> <a href="javascript:history.go(-1)"><?php echo $lang->t('Go back') ?></a></p>
		</form>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}

// Merge two or more topics
else if (isset($_POST['merge_topics']) || isset($_POST['merge_topics_comply']))
{
	if (isset($_POST['merge_topics_comply']))
	{
		confirm_referrer('moderate.php');

		if (@preg_match('%[^0-9,]%', $_POST['topics']))
			message($lang->t('Bad request'));

		$topics = explode(',', $_POST['topics']);
		if (count($topics) < 2)
			message($lang->t('Not enough topics selected'));

		// Verify that the topic IDs are valid (redirect links will point to the merged topic after the merge)
		$query = $db->select(array('id' => 't.id'), 'topics AS t');
		$query->where = 't.id IN :tids AND t.forum_id = :forum_id';
		$query->order = array('id' => 't.id ASC');

		$params = array(':tids' => $topics, ':forum_id' => $fid);

		$result = $query->run($params);
		if (count($result[0]) != count($topics))
			message($lang->t('Bad request'));

		// The topic that we are merging into is the one with the smallest ID
		$merge_to_tid = $result[0]['id'];
		unset ($result, $query, $params);

		// Make any redirect topics point to our new, merged topic
		$query = $db->update(array('moved_to' => ':merge_id'), 'topics');
		$query->where = 'moved_to IN :tids';

		$params = array(':merge_id' => $merge_to_tid, ':tids' => $topics);

		// Should we create redirect topics?
		if (isset($_POST['with_redirect']))
			$query->where .= ' OR (id IN :tids AND id != :merge_id)';

		$query->run($params);
		unset ($query, $params);

		// Merge the posts into the topic
		$query = $db->update(array('topic_id' => ':merge_id'), 'posts');
		$query->where = 'topic_id IN :tids';

		$params = array(':merge_id' => $merge_to_tid, ':tids' => $topics);

		$query->run($params);
		unset ($query, $params);

		// Delete any subscriptions
		$query = $db->delete('topic_subscriptions');
		$query->where = 'topic_id IN :tids AND topic_id != :merge_id';

		$params = array(':merge_id' => $merge_to_tid, ':tids' => $topics);

		$query->run($params);
		unset ($query, $params);

		// Without redirection the old topics are removed
		if (!isset($_POST['with_redirect']))
		{
			$query = $db->delete('topics');
			$query->where = 'id IN :tids AND id != :merge_id';

			$params = array(':merge_id' => $merge_to_tid, ':tids' => $topics);

			$query->run($params);
			unset ($query, $params);
		}

		// Count number of replies in the topic
		$query = $db->select(array('num_replies' => '(COUNT(p.id) - 1) AS num_replies'), 'posts AS p');
		$query->where = 'p.topic_id = :merge_to';

		$params = array(':merge_to' => $merge_to_tid);

		$result = $query->run($params);
		$num_replies = $result[0]['num_replies'];
		unset ($result, $query, $params);

		// Get last_post, last_post_id and last_poster
		$query = $db->select(array('posted' => 'p.posted', 'id' => 'p.id', 'poster' => 'p.poster'), 'posts AS p');
		$query->where = 'p.topic_id = :merge_to';
		$query->order = array('id' => 'p.id DESC');
		$query->limit = 1;

		$result = $query->run($params);
		$last_post = $result[0];
		unset ($result, $query, $params);

		// Update topic
		$query = $db->update(array('num_replies' => ':num_replies', 'last_post' => ':last_post', 'last_post_id' => ':last_post_id', 'last_poster' => ':last_poster'), 'topics');
		$query->where = 'id = :merge_to';

		$params = array(':num_replies' => $num_replies, ':last_post' => $last_post['posted'], ':last_post_id' => $last_post['id'], ':last_poster' => $last_post['poster'], ':merge_to' => $merge_to_tid);

		$query->run($params);
		unset ($query, $params);

		// Update the forum FROM which the topic was moved and redirect
		update_forum($fid);
		redirect('viewforum.php?id='.$fid, $lang->t('Merge topics redirect'));
	}

	$topics = isset($_POST['topics']) ? $_POST['topics'] : array();
	if (count($topics) < 2)
		message($lang->t('Not enough topics selected'));

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Moderate'));
	define('PUN_ACTIVE_PAGE', 'index');
	require PUN_ROOT.'header.php';

?>
<div class="blockform">
	<h2><span><?php echo $lang->t('Merge topics') ?></span></h2>
	<div class="box">
		<form method="post" action="moderate.php?fid=<?php echo $fid ?>">
			<input type="hidden" name="topics" value="<?php echo implode(',', array_map('intval', array_keys($topics))) ?>" />
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang->t('Confirm merge legend') ?></legend>
					<div class="infldset">
						<div class="rbox">
							<label><input type="checkbox" name="with_redirect" value="1" /><?php echo $lang->t('Leave redirect') ?><br /></label>
						</div>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="merge_topics_comply" value="<?php echo $lang->t('Merge') ?>" /> <a href="javascript:history.go(-1)"><?php echo $lang->t('Go back') ?></a></p>
		</form>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}

// Delete one or more topics
else if (isset($_POST['delete_topics']) || isset($_POST['delete_topics_comply']))
{
	if (isset($_POST['delete_topics_comply']))
	{
		confirm_referrer('moderate.php');

		if (@preg_match('%[^0-9,]%', $_POST['topics']))
			message($lang->t('Bad request'));

		$topics = explode(',', $_POST['topics']);
		if (empty($topics))
			message($lang->t('No topics selected'));

		require PUN_ROOT.'include/search_idx.php';

		// Verify that the topic IDs are valid
		$query = $db->select(array('num_topics' => 'COUNT(t.id) AS num_topics'), 'topics AS t');
		$query->where = 'id IN :tids AND forum_id = :forum_id';

		$params = array(':tids' => $topics, ':forum_id' => $fid);

		$result = $query->run($params);
		if ($result[0]['num_topics'] != count($topics))
			message($lang->t('Bad request'));

		unset ($result, $query, $params);

		// Delete the topics and any redirect topics
		$query = $db->delete('topics');
		$query->where = 'id IN :tids OR moved_to IN :tids';

		$params = array(':tids' => $topics);

		$query->run($params);
		unset ($query, $params);

		// Delete any subscriptions
		$query = $db->delete('topic_subscriptions');
		$query->where = 'topic_id IN :tids';

		$params = array(':tids' => $topics);

		$query->run($params);
		unset ($query, $params);

		// Create a list of the post IDs in this topic and then strip the search index
		$query = $db->select(array('id' => 'p.id'), 'posts AS p');
		$query->where = 'p.topic_id IN :tids';

		$params = array(':tids' => $topics);

		$result = $query->run($params);

		$post_ids = array();
		foreach ($result as $cur_post)
			$post_ids[] = $cur_post['id'];

		unset ($result, $query, $params);

		// We have to check that we actually have a list of post IDs since we could be deleting just a redirect topic
		if (!empty($post_ids))
			strip_search_index($post_ids);

		// Delete posts
		$query = $db->delete('posts');
		$query->where = 'topic_id IN :tids';

		$params = array(':tids' => $topics);

		$query->run($params);
		unset ($query, $params);

		update_forum($fid);

		redirect('viewforum.php?id='.$fid, $lang->t('Delete topics redirect'));
	}

	$topics = isset($_POST['topics']) ? $_POST['topics'] : array();
	if (empty($topics))
		message($lang->t('No topics selected'));

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Moderate'));
	define('PUN_ACTIVE_PAGE', 'index');
	require PUN_ROOT.'header.php';

?>
<div class="blockform">
	<h2><span><?php echo $lang->t('Delete topics') ?></span></h2>
	<div class="box">
		<form method="post" action="moderate.php?fid=<?php echo $fid ?>">
			<input type="hidden" name="topics" value="<?php echo implode(',', array_map('intval', array_keys($topics))) ?>" />
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang->t('Confirm delete legend') ?></legend>
					<div class="infldset">
						<p><?php echo $lang->t('Delete topics comply') ?></p>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="delete_topics_comply" value="<?php echo $lang->t('Delete') ?>" /><a href="javascript:history.go(-1)"><?php echo $lang->t('Go back') ?></a></p>
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

	// There could be an array of topic IDs in $_POST
	if (isset($_POST['open']) || isset($_POST['close']))
	{
		confirm_referrer('moderate.php');

		$topics = isset($_POST['topics']) ? @array_map('intval', @array_keys($_POST['topics'])) : array();
		if (empty($topics))
			message($lang->t('No topics selected'));

		$query = $db->update(array('closed' => ':closed'), 'topics');
		$query->where = 'id IN :tids AND forum_id = :forum_id';

		$params = array(':closed' => $action, ':tids' => $topics, ':forum_id' => $fid);

		$query->run($params);
		unset ($query, $params);

		$redirect_msg = ($action) ? $lang->t('Close topics redirect') : $lang->t('Open topics redirect');
		redirect('moderate.php?fid='.$fid, $redirect_msg);
	}
	// Or just one in $_GET
	else
	{
		confirm_referrer('viewtopic.php');

		$topic_id = ($action) ? intval($_GET['close']) : intval($_GET['open']);
		if ($topic_id < 1)
			message($lang->t('Bad request'));

		$query = $db->update(array('closed' => ':closed'), 'topics');
		$query->where = 'id = :topic_id AND forum_id = :forum_id';

		$params = array(':closed' => $action, ':topic_id' => $topic_id, ':forum_id' => $fid);

		$query->run($params);
		unset ($query, $params);

		$redirect_msg = ($action) ? $lang->t('Close topic redirect') : $lang->t('Open topic redirect');
		redirect('viewtopic.php?id='.$topic_id, $redirect_msg);
	}
}


// Stick a topic
else if (isset($_GET['stick']))
{
	confirm_referrer('viewtopic.php');

	$stick = intval($_GET['stick']);
	if ($stick < 1)
		message($lang->t('Bad request'));

	$query = $db->update(array('sticky' => '1'), 'topics');
	$query->where = 'id = :topic_id AND forum_id = :forum_id';

	$params = array(':topic_id' => $stick, ':forum_id' => $fid);

	$query->run($params);
	unset ($query, $params);

	redirect('viewtopic.php?id='.$stick, $lang->t('Stick topic redirect'));
}


// Unstick a topic
else if (isset($_GET['unstick']))
{
	confirm_referrer('viewtopic.php');

	$unstick = intval($_GET['unstick']);
	if ($unstick < 1)
		message($lang->t('Bad request'));

	$query = $db->update(array('sticky' => '0'), 'topics');
	$query->where = 'id = :topic_id AND forum_id = :forum_id';

	$params = array(':topic_id' => $unstick, ':forum_id' => $fid);

	$query->run($params);
	unset ($query, $params);

	redirect('viewtopic.php?id='.$unstick, $lang->t('Unstick topic redirect'));
}


// No specific forum moderation action was specified in the query string, so we'll display the moderator forum

// Load the viewforum.php language file
$lang->load('forum');

// Fetch some info about the forum
$query = $db->select(array('forum_name' => 'f.forum_name', 'redirect_url' => 'f.redirect_url', 'num_topics' => 'f.num_topics', 'sort_by' => 'f.sort_by'), 'forums AS f');

$query->leftJoin('fp', 'forum_perms AS fp', 'fp.forum_id = f.id AND fp.group_id = :group_id');

$query->where = '(fp.read_forum IS NULL OR fp.read_forum = 1) AND f.id = :forum_id';

$params = array(':group_id' => $pun_user['g_id'], ':forum_id' => $fid);

$result = $query->run($params);
if (empty($result))
	message($lang->t('Bad request'));

$cur_forum = $result[0];
unset ($result, $query, $params);

// Is this a redirect forum? In that case, abort!
if ($cur_forum['redirect_url'] != '')
	message($lang->t('Bad request'));

switch ($cur_forum['sort_by'])
{
	case 0:
		$sort_by = 't.last_post DESC';
		break;
	case 1:
		$sort_by = 't.posted DESC';
		break;
	case 2:
		$sort_by = 't.subject ASC';
		break;
	default:
		$sort_by = 't.last_post DESC';
		break;
}

// Determine the topic offset (based on $_GET['p'])
$num_pages = ceil($cur_forum['num_topics'] / $pun_user['disp_topics']);

$p = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $num_pages) ? 1 : intval($_GET['p']);
$start_from = $pun_user['disp_topics'] * ($p - 1);

// Generate paging links
$paging_links = '<span class="pages-label">'.$lang->t('Pages').' </span>'.paginate($num_pages, $p, 'moderate.php?fid='.$fid);

$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), pun_htmlspecialchars($cur_forum['forum_name']));
define('PUN_ACTIVE_PAGE', 'index');
require PUN_ROOT.'header.php';

?>
<div class="linkst">
	<div class="inbox crumbsplus">
		<ul class="crumbs">
			<li><a href="index.php"><?php echo $lang->t('Index') ?></a></li>
			<li><span>»&#160;</span><a href="viewforum.php?id=<?php echo $fid ?>"><?php echo pun_htmlspecialchars($cur_forum['forum_name']) ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang->t('Moderate') ?></strong></li>
		</ul>
		<div class="pagepost">
			<p class="pagelink conl"><?php echo $paging_links ?></p>
		</div>
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
					<th class="tcl" scope="col"><?php echo $lang->t('Topic') ?></th>
					<th class="tc2" scope="col"><?php echo $lang->t('Replies') ?></th>
<?php if ($pun_config['o_topic_views'] == '1'): ?>					<th class="tc3" scope="col"><?php echo $lang->t('Views') ?></th>
<?php endif; ?>					<th class="tcr"><?php echo $lang->t('Last post') ?></th>
					<th class="tcmod" scope="col"><?php echo $lang->t('Select') ?></th>
				</tr>
			</thead>
			<tbody>
<?php


// Retrieve a list of topic IDs, LIMIT is (really) expensive so we only fetch the IDs here then later fetch the remaining data
$query = $db->select(array('id' => 't.id'), 'topics AS t');
$query->where = 't.forum_id = :forum_id';
$query->order = array('sticky' => 't.sticky DESC', 'sort' => $sort_by, 'id' => 't.id DESC');
$query->limit = $pun_user['disp_topics'];
$query->offset = $start_from;

$params = array(':forum_id' => $fid);

$topic_ids = $query->run($params);
unset ($query, $params);

// If there are topics in this forum
if (!empty($topic_ids))
{
	// Translate from a 3d array into 2d array: $topics_ids[0]['id'] -> $topics_ids[0]
	foreach ($topic_ids as $key => $value)
		$topic_ids[$key] = $value['id'];

	// Select topics
	$query = $db->select(array('id, poster, subject, posted, last_post, last_post_id, last_poster, num_views, num_replies, closed, sticky, moved_to'), 'topics AS t');
	$query->where = 't.id IN :tids';
	$query->order = array('sticky' => 't.sticky DESC', 'sort' => $sort_by, 'id' => 't.id DESC');

	$params = array(':tids' => $topic_ids);

	$button_status = '';
	$topic_count = 0;

	$result = $query->run($params);
	foreach ($result as $cur_topic)
	{

		++$topic_count;
		$status_text = array();
		$item_status = ($topic_count % 2 == 0) ? 'roweven' : 'rowodd';
		$icon_type = 'icon';

		if ($cur_topic['moved_to'] == null)
		{
			$last_post = '<a href="viewtopic.php?pid='.$cur_topic['last_post_id'].'#p'.$cur_topic['last_post_id'].'">'.format_time($cur_topic['last_post']).'</a> <span class="byuser">'.$lang->t('by').' '.pun_htmlspecialchars($cur_topic['last_poster']).'</span>';
			$ghost_topic = false;
		}
		else
		{
			$last_post = '- - -';
			$ghost_topic = true;
		}

		if ($pun_config['o_censoring'] == '1')
			$cur_topic['subject'] = censor_words($cur_topic['subject']);

		if ($cur_topic['sticky'] == '1')
		{
			$item_status .= ' isticky';
			$status_text[] = '<span class="stickytext">'.$lang->t('Sticky').'</span>';
		}

		if ($cur_topic['moved_to'] != 0)
		{
			$subject = '<a href="viewtopic.php?id='.$cur_topic['moved_to'].'">'.pun_htmlspecialchars($cur_topic['subject']).'</a> <span class="byuser">'.$lang->t('by').' '.pun_htmlspecialchars($cur_topic['poster']).'</span>';
			$status_text[] = '<span class="movedtext">'.$lang->t('Moved').'</span>';
			$item_status .= ' imoved';
		}
		else if ($cur_topic['closed'] == '0')
			$subject = '<a href="viewtopic.php?id='.$cur_topic['id'].'">'.pun_htmlspecialchars($cur_topic['subject']).'</a> <span class="byuser">'.$lang->t('by').' '.pun_htmlspecialchars($cur_topic['poster']).'</span>';
		else
		{
			$subject = '<a href="viewtopic.php?id='.$cur_topic['id'].'">'.pun_htmlspecialchars($cur_topic['subject']).'</a> <span class="byuser">'.$lang->t('by').' '.pun_htmlspecialchars($cur_topic['poster']).'</span>';
			$status_text[] = '<span class="closedtext">'.$lang->t('Closed').'</span>';
			$item_status .= ' iclosed';
		}

		if (!$ghost_topic && $cur_topic['last_post'] > $pun_user['last_visit'] && (!isset($tracked_topics['topics'][$cur_topic['id']]) || $tracked_topics['topics'][$cur_topic['id']] < $cur_topic['last_post']) && (!isset($tracked_topics['forums'][$fid]) || $tracked_topics['forums'][$fid] < $cur_topic['last_post']))
		{
			$item_status .= ' inew';
			$icon_type = 'icon icon-new';
			$subject = '<strong>'.$subject.'</strong>';
			$subject_new_posts = '<span class="newtext">[ <a href="viewtopic.php?id='.$cur_topic['id'].'&amp;action=new" title="'.$lang->t('New posts info').'">'.$lang->t('New posts').'</a> ]</span>';
		}
		else
			$subject_new_posts = null;

		// Insert the status text before the subject
		$subject = implode(' ', $status_text).' '.$subject;

		$num_pages_topic = ceil(($cur_topic['num_replies'] + 1) / $pun_user['disp_posts']);

		if ($num_pages_topic > 1)
			$subject_multipage = '<span class="pagestext">[ '.paginate($num_pages_topic, -1, 'viewtopic.php?id='.$cur_topic['id']).' ]</span>';
		else
			$subject_multipage = null;

		// Should we show the "New posts" and/or the multipage links?
		if (!empty($subject_new_posts) || !empty($subject_multipage))
		{
			$subject .= !empty($subject_new_posts) ? ' '.$subject_new_posts : '';
			$subject .= !empty($subject_multipage) ? ' '.$subject_multipage : '';
		}

?>
				<tr class="<?php echo $item_status ?>">
					<td class="tcl">
						<div class="<?php echo $icon_type ?>"><div class="nosize"><?php echo forum_number_format($topic_count + $start_from) ?></div></div>
						<div class="tclcon">
							<div>
								<?php echo $subject."\n" ?>
							</div>
						</div>
					</td>
					<td class="tc2"><?php echo (!$ghost_topic) ? forum_number_format($cur_topic['num_replies']) : '-' ?></td>
<?php if ($pun_config['o_topic_views'] == '1'): ?>					<td class="tc3"><?php echo (!$ghost_topic) ? forum_number_format($cur_topic['num_views']) : '-' ?></td>
<?php endif; ?>					<td class="tcr"><?php echo $last_post ?></td>
					<td class="tcmod"><input type="checkbox" name="topics[<?php echo $cur_topic['id'] ?>]" value="1" /></td>
				</tr>
<?php

	}

	unset ($result, $query, $params);
}
else
{
	$colspan = ($pun_config['o_topic_views'] == '1') ? 5 : 4;
	$button_status = ' disabled="disabled"';
	echo "\t\t\t\t\t".'<tr><td class="tcl" colspan="'.$colspan.'">'.$lang->t('Empty forum').'</td></tr>'."\n";
}

?>
			</tbody>
			</table>
		</div>
	</div>
</div>

<div class="linksb">
	<div class="inbox crumbsplus">
		<div class="pagepost">
			<p class="pagelink conl"><?php echo $paging_links ?></p>
			<p class="conr modbuttons"><input type="submit" name="move_topics" value="<?php echo $lang->t('Move') ?>"<?php echo $button_status ?> /> <input type="submit" name="delete_topics" value="<?php echo $lang->t('Delete') ?>"<?php echo $button_status ?> /> <input type="submit" name="merge_topics" value="<?php echo $lang->t('Merge') ?>"<?php echo $button_status ?> /> <input type="submit" name="open" value="<?php echo $lang->t('Open') ?>"<?php echo $button_status ?> /> <input type="submit" name="close" value="<?php echo $lang->t('Close') ?>"<?php echo $button_status ?> /></p>
			<div class="clearer"></div>
		</div>
		<ul class="crumbs">
			<li><a href="index.php"><?php echo $lang->t('Index') ?></a></li>
			<li><span>»&#160;</span><a href="viewforum.php?id=<?php echo $fid ?>"><?php echo pun_htmlspecialchars($cur_forum['forum_name']) ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang->t('Moderate') ?></strong></li>
		</ul>
		<div class="clearer"></div>
	</div>
</div>
</form>
<?php

require PUN_ROOT.'footer.php';
