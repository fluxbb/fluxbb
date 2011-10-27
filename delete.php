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


$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id < 1)
	message($lang_common['Bad request']);

// Fetch some info about the post, the topic and the forum
$query = $db->select(array('fid' => 'f.id AS fid', 'forum_name' => 'f.forum_name', 'moderators' => 'f.moderators', 'redirect_url' => 'f.redirect_url', 'post_replies' => 'fp.post_replies', 'post_topics' => 'fp.post_topics', 'tid' => 't.id AS tid', 'subject' => 't.subject', 'first_post_id' => 't.first_post_id', 'closed' => 't.closed', 'posted' => 'p.posted', 'poster' => 'p.poster', 'poster_id' => 'p.poster_id', 'message' => 'p.message', 'hide_smilies' => 'p.hide_smilies'), 'posts AS p');

$query->InnerJoin('t', 'topics AS t', 't.id = p.topic_id');

$query->InnerJoin('f', 'forums AS f', 'f.id = t.forum_id');

$query->LeftJoin('fp', 'forum_perms AS fp', 'fp.forum_id = f.id AND fp.group_id = :group_id');

$query->where = '(fp.read_forum IS NULL OR fp.read_forum=1) AND p.id = :post_id';

$params = array(':group_id' => $pun_user['g_id'], ':post_id' => $id);

$result = $query->run($params);
if (empty($result))
	message($lang_common['Bad request']);

$cur_post = $result[0];
unset($query, $params, $result);

if ($pun_config['o_censoring'] == '1')
	$cur_post['subject'] = censor_words($cur_post['subject']);

// Sort out who the moderators are and if we are currently a moderator (or an admin)
$mods_array = ($cur_post['moderators'] != '') ? unserialize($cur_post['moderators']) : array();
$is_admmod = ($pun_user['g_id'] == PUN_ADMIN || ($pun_user['g_moderator'] == '1' && array_key_exists($pun_user['username'], $mods_array))) ? true : false;

$is_topic_post = ($id == $cur_post['first_post_id']) ? true : false;

// Do we have permission to edit this post?
if (($pun_user['g_delete_posts'] == '0' ||
	($pun_user['g_delete_topics'] == '0' && $is_topic_post) ||
	$cur_post['poster_id'] != $pun_user['id'] ||
	$cur_post['closed'] == '1') &&
	!$is_admmod)
	message($lang_common['No permission']);

// Load the delete.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/delete.php';


if (isset($_POST['delete']))
{
	if ($is_admmod)
		confirm_referrer('delete.php');

	require PUN_ROOT.'include/search_idx.php';

	if ($is_topic_post)
	{
		// Delete the topic and all of it's posts
		delete_topic($cur_post['tid']);
		update_forum($cur_post['fid']);

		redirect('viewforum.php?id='.$cur_post['fid'], $lang_delete['Topic del redirect']);
	}
	else
	{
		// Delete just this one post
		delete_post($id, $cur_post['tid']);
		update_forum($cur_post['fid']);

		// Redirect towards the previous post
		$query = $db->select(array('id' => 'p.id'), 'posts AS p');
		$query->where = 'p.topic_id = :topic_id AND p.id < :post_id';
		$query->order = array('id' => 'p.id DESC');
		$query->limit = 1;

		$params = array(':topic_id' => $cur_post['tid'], ':post_id' => $id);

		$result = $query->run($params);
		$post_id = $result[0]['id'];
		unset($query, $params, $result);

		redirect('viewtopic.php?pid='.$post_id.'#p'.$post_id, $lang_delete['Post del redirect']);
	}
}


$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_delete['Delete post']);
define ('PUN_ACTIVE_PAGE', 'index');
require PUN_ROOT.'header.php';

require PUN_ROOT.'include/parser.php';
$cur_post['message'] = parse_message($cur_post['message'], $cur_post['hide_smilies']);

?>
<div class="linkst">
	<div class="inbox">
		<ul class="crumbs">
			<li><a href="index.php"><?php echo $lang_common['Index'] ?></a></li>
			<li><span>»&#160;</span><a href="viewforum.php?id=<?php echo $cur_post['fid'] ?>"><?php echo pun_htmlspecialchars($cur_post['forum_name']) ?></a></li>
			<li><span>»&#160;</span><a href="viewtopic.php?pid=<?php echo $id ?>#p<?php echo $id ?>"><?php echo pun_htmlspecialchars($cur_post['subject']) ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang_delete['Delete post'] ?></strong></li>
		</ul>
	</div>
</div>

<div class="blockform">
	<h2><span><?php echo $lang_delete['Delete post'] ?></span></h2>
	<div class="box">
		<form method="post" action="delete.php?id=<?php echo $id ?>">
			<div class="inform">
				<div class="forminfo">
					<h3><span><?php printf($is_topic_post ? $lang_delete['Topic by'] : $lang_delete['Reply by'], '<strong>'.pun_htmlspecialchars($cur_post['poster']).'</strong>', format_time($cur_post['posted'])) ?></span></h3>
					<p><?php echo ($is_topic_post) ? '<strong>'.$lang_delete['Topic warning'].'</strong>' : '<strong>'.$lang_delete['Warning'].'</strong>' ?><br /><?php echo $lang_delete['Delete info'] ?></p>
				</div>
			</div>
			<p class="buttons"><input type="submit" name="delete" value="<?php echo $lang_delete['Delete'] ?>" /> <a href="javascript:history.go(-1)"><?php echo $lang_common['Go back'] ?></a></p>
		</form>
	</div>
</div>

<div id="postreview">
	<div class="blockpost">
		<div class="box">
			<div class="inbox">
				<div class="postbody">
					<div class="postleft">
						<dl>
							<dt><strong><?php echo pun_htmlspecialchars($cur_post['poster']) ?></strong></dt>
							<dd><span><?php echo format_time($cur_post['posted']) ?></span></dd>
						</dl>
					</div>
					<div class="postright">
						<div class="postmsg">
							<?php echo $cur_post['message']."\n" ?>
						</div>
					</div>
				</div>
				<div class="clearer"></div>
			</div>
		</div>
	</div>
</div>
<?php

require PUN_ROOT.'footer.php';
