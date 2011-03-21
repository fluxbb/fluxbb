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

// Load the viewforum.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/forum.php';

// Fetch some info about the forum
$query = new SelectQuery(array('forum_name' => 'f.forum_name', 'redirect_url' => 'f.redirect_url', 'moderators' => 'f.moderators', 'num_topics' => 'f.num_topics', 'sort_by' => 'f.sort_by', 'post_topics' => 'fp.post_topics', 'is_subscribed' => '0 AS is_subscribed'), 'forums AS f');

$query->joins['fp'] = new LeftJoin('forum_perms AS fp');
$query->joins['fp']->on = 'fp.forum_id = f.id AND fp.group_id = :group_id';

$query->where = '(fp.read_forum IS NULL OR fp.read_forum = 1) AND f.id = :forum_id';

$params = array(':group_id' => $pun_user['g_id'], ':forum_id' => $id);

// If we aren't a guest then handle subscription checks
if (!$pun_user['is_guest'])
{
	$query->fields['is_subscribed'] = 's.user_id AS is_subscribed';

	$query->joins['s'] = new LeftJoin('forum_subscriptions AS s');
	$query->joins['s']->on = 'f.id = s.forum_id AND s.user_id = :user_id';

	$params[':user_id'] = $pun_user['id'];
}

$result = $db->query($query, $params);
if (empty($result))
	message($lang_common['Bad request']);

$cur_forum = $result[0];
unset ($query, $params, $result);

// Is this a redirect forum? In that case, redirect!
if ($cur_forum['redirect_url'] != '')
{
	header('Location: '.$cur_forum['redirect_url']);
	exit;
}

// Sort out who the moderators are and if we are currently a moderator (or an admin)
$mods_array = ($cur_forum['moderators'] != '') ? unserialize($cur_forum['moderators']) : array();
$is_admmod = ($pun_user['g_id'] == PUN_ADMIN || ($pun_user['g_moderator'] == '1' && array_key_exists($pun_user['username'], $mods_array))) ? true : false;

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

// Can we or can we not post new topics?
if (($cur_forum['post_topics'] == '' && $pun_user['g_post_topics'] == '1') || $cur_forum['post_topics'] == '1' || $is_admmod)
	$post_link = "\t\t\t".'<p class="postlink conr"><a href="post.php?fid='.$id.'">'.$lang_forum['Post topic'].'</a></p>'."\n";
else
	$post_link = '';

// Get topic/forum tracking data
if (!$pun_user['is_guest'])
	$tracked_topics = get_tracked_topics();

// Determine the topic offset (based on $_GET['p'])
$num_pages = ceil($cur_forum['num_topics'] / $pun_user['disp_topics']);

$p = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $num_pages) ? 1 : intval($_GET['p']);
$start_from = $pun_user['disp_topics'] * ($p - 1);

// Generate paging links
$paging_links = '<span class="pages-label">'.$lang_common['Pages'].' </span>'.paginate($num_pages, $p, 'viewforum.php?id='.$id);


// Add relationship meta tags
$page_head = array();
$page_head['up'] = '<link rel="up" href="index.php" title="'.$lang_common['Forum index'].'" />';

if ($num_pages > 1)
{
	if ($p > 1)
	{
		$page_head['first'] = '<link rel="first" href="viewforum.php?id='.$id.'&amp;p=1" title="'.sprintf($lang_common['Page'], 1).'" />';
		$page_head['prev'] = '<link rel="prev" href="viewforum.php?id='.$id.'&amp;p='.($p-1).'" title="'.sprintf($lang_common['Page'], $p-1).'" />';
	}
	if ($p < $num_pages)
	{
		$page_head['next'] = '<link rel="next" href="viewforum.php?id='.$id.'&amp;p='.($p+1).'" title="'.sprintf($lang_common['Page'], $p+1).'" />';
		$page_head['last'] = '<link rel="last" href="viewforum.php?id='.$id.'&amp;p='.$num_pages.'" title="'.sprintf($lang_common['Page'], $num_pages).'" />';
	}
}

if ($pun_config['o_feed_type'] == '1')
	$page_head['feed'] = '<link rel="alternate" type="application/rss+xml" href="extern.php?action=feed&amp;fid='.$id.'&amp;type=rss" title="'.$lang_common['RSS forum feed'].'" />';
else if ($pun_config['o_feed_type'] == '2')
	$page_head['feed'] = '<link rel="alternate" type="application/atom+xml" href="extern.php?action=feed&amp;fid='.$id.'&amp;type=atom" title="'.$lang_common['Atom forum feed'].'" />';

$forum_actions = array();

if (!$pun_user['is_guest'])
{
	if ($pun_config['o_forum_subscriptions'] == '1')
	{
		if ($cur_forum['is_subscribed'])
			$forum_actions[] = '<span>'.$lang_forum['Is subscribed'].' - </span><a href="misc.php?action=unsubscribe&amp;fid='.$id.'">'.$lang_forum['Unsubscribe'].'</a>';
		else
			$forum_actions[] = '<a href="misc.php?action=subscribe&amp;fid='.$id.'">'.$lang_forum['Subscribe'].'</a>';
	}

	$forum_actions[] = '<a href="misc.php?action=markforumread&amp;fid='.$id.'">'.$lang_common['Mark forum read'].'</a>';
}

$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), pun_htmlspecialchars($cur_forum['forum_name']));
define('PUN_ALLOW_INDEX', 1);
define('PUN_ACTIVE_PAGE', 'index');
require PUN_ROOT.'header.php';

?>
<div class="linkst">
	<div class="inbox crumbsplus">
		<ul class="crumbs">
			<li><a href="index.php"><?php echo $lang_common['Index'] ?></a></li>
			<li><span>»&#160;</span><a href="viewforum.php?id=<?php echo $id ?>"><strong><?php echo pun_htmlspecialchars($cur_forum['forum_name']) ?></strong></a></li>
		</ul>
		<div class="pagepost">
			<p class="pagelink conl"><?php echo $paging_links ?></p>
<?php echo $post_link ?>
		</div>
		<div class="clearer"></div>
	</div>
</div>

<div id="vf" class="blocktable">
	<h2><span><?php echo pun_htmlspecialchars($cur_forum['forum_name']) ?></span></h2>
	<div class="box">
		<div class="inbox">
			<table cellspacing="0">
			<thead>
				<tr>
					<th class="tcl" scope="col"><?php echo $lang_common['Topic'] ?></th>
					<th class="tc2" scope="col"><?php echo $lang_common['Replies'] ?></th>
<?php if ($pun_config['o_topic_views'] == '1'): ?>					<th class="tc3" scope="col"><?php echo $lang_forum['Views'] ?></th>
<?php endif; ?>					<th class="tcr" scope="col"><?php echo $lang_common['Last post'] ?></th>
				</tr>
			</thead>
			<tbody>
<?php

// Retrieve a list of topic IDs, LIMIT is (really) expensive so we only fetch the IDs here then later fetch the remaining data
$query = new SelectQuery(array('id' => 't.id'), 'topics AS t');
$query->where = 't.forum_id = :forum_id';
$query->order = array('sticky' => 't.sticky DESC', 'sort' => $sort_by, 'id' => 't.id DESC');
$query->limit = $pun_user['disp_topics'];
$query->offset = $start_from;

$params = array(':forum_id' => $id);

$topic_ids = $db->query($query, $params);
unset ($query, $params);

// If there are topics in this forum
if (!empty($topic_ids))
{
	$params = array();

	$count = count($topic_ids);
	for ($i = 0;$i < $count;$i++)
		$params[':t'.$i] = $topic_ids[$i]['id'];

	// Fetch list of topics to display on this page
	$query = new SelectQuery(array('has_posted' => '0 AS has_posted', 'tid' => 't.id', 'subject' => 't.subject', 'poster' => 't.poster', 'posted' => 't.posted', 'last_post' => 't.last_post', 'last_post_id' => 't.last_post_id', 'last_poster' => 't.last_poster', 'num_views' => 't.num_views', 'num_replies' => 't.num_replies', 'closed' => 't.closed', 'sticky' => 't.sticky', 'moved_to' => 't.moved_to'), 'topics AS t');
	$query->where = 't.id IN ('.implode(', ', array_keys($params)).')';
	$query->order = array('sticky' => 't.sticky DESC', 'sort' => $sort_by, 'id' => 't.id DESC');

	// With "the dot"
	if ($pun_user['is_guest'] || $pun_config['o_show_dot'] == '1' && !$pun_user['is_guest'])
	{
		$query->fields['has_posted'] = 'p.poster_id AS has_posted';

		$query->joins['p'] = new LeftJoin('posts AS p');
		$query->joins['p']->on = 't.id = p.topic_id AND p.poster_id = :user_id';

		$query->group = array('t.id', 't.subject', 't.poster', 't.posted', 't.last_post', 't.last_post_id', 't.last_poster', 't.num_views', 't.num_replies', 't.closed', 't.sticky', 't.moved_to', 'p.poster_id');

		$params[':user_id'] = $pun_user['id'];
	}

	$topic_count = 0;

	$result = $db->query($query, $params);
	foreach ($result as $cur_topic)
	{
		++$topic_count;
		$status_text = array();
		$item_status = ($topic_count % 2 == 0) ? 'roweven' : 'rowodd';
		$icon_type = 'icon';

		if ($cur_topic['moved_to'] == null)
			$last_post = '<a href="viewtopic.php?pid='.$cur_topic['last_post_id'].'#p'.$cur_topic['last_post_id'].'">'.format_time($cur_topic['last_post']).'</a> <span class="byuser">'.$lang_common['by'].' '.pun_htmlspecialchars($cur_topic['last_poster']).'</span>';
		else
			$last_post = '- - -';

		if ($pun_config['o_censoring'] == '1')
			$cur_topic['subject'] = censor_words($cur_topic['subject']);

		if ($cur_topic['sticky'] == '1')
		{
			$item_status .= ' isticky';
			$status_text[] = '<span class="stickytext">'.$lang_forum['Sticky'].'</span>';
		}

		if ($cur_topic['moved_to'] != 0)
		{
			$subject = '<a href="viewtopic.php?id='.$cur_topic['moved_to'].'">'.pun_htmlspecialchars($cur_topic['subject']).'</a> <span class="byuser">'.$lang_common['by'].' '.pun_htmlspecialchars($cur_topic['poster']).'</span>';
			$status_text[] = '<span class="movedtext">'.$lang_forum['Moved'].'</span>';
			$item_status .= ' imoved';
		}
		else if ($cur_topic['closed'] == '0')
			$subject = '<a href="viewtopic.php?id='.$cur_topic['id'].'">'.pun_htmlspecialchars($cur_topic['subject']).'</a> <span class="byuser">'.$lang_common['by'].' '.pun_htmlspecialchars($cur_topic['poster']).'</span>';
		else
		{
			$subject = '<a href="viewtopic.php?id='.$cur_topic['id'].'">'.pun_htmlspecialchars($cur_topic['subject']).'</a> <span class="byuser">'.$lang_common['by'].' '.pun_htmlspecialchars($cur_topic['poster']).'</span>';
			$status_text[] = '<span class="closedtext">'.$lang_forum['Closed'].'</span>';
			$item_status .= ' iclosed';
		}

		if (!$pun_user['is_guest'] && $cur_topic['last_post'] > $pun_user['last_visit'] && (!isset($tracked_topics['topics'][$cur_topic['id']]) || $tracked_topics['topics'][$cur_topic['id']] < $cur_topic['last_post']) && (!isset($tracked_topics['forums'][$id]) || $tracked_topics['forums'][$id] < $cur_topic['last_post']) && $cur_topic['moved_to'] == null)
		{
			$item_status .= ' inew';
			$icon_type = 'icon icon-new';
			$subject = '<strong>'.$subject.'</strong>';
			$subject_new_posts = '<span class="newtext">[ <a href="viewtopic.php?id='.$cur_topic['id'].'&amp;action=new" title="'.$lang_common['New posts info'].'">'.$lang_common['New posts'].'</a> ]</span>';
		}
		else
			$subject_new_posts = null;

		// Insert the status text before the subject
		$subject = implode(' ', $status_text).' '.$subject;

		// Should we display the dot or not? :)
		if (!$pun_user['is_guest'] && $pun_config['o_show_dot'] == '1')
		{
			if ($cur_topic['has_posted'] == $pun_user['id'])
			{
				$subject = '<strong class="ipost">·&#160;</strong>'.$subject;
				$item_status .= ' iposted';
			}
		}

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
					<td class="tc2"><?php echo ($cur_topic['moved_to'] == null) ? forum_number_format($cur_topic['num_replies']) : '-' ?></td>
<?php if ($pun_config['o_topic_views'] == '1'): ?>					<td class="tc3"><?php echo ($cur_topic['moved_to'] == null) ? forum_number_format($cur_topic['num_views']) : '-' ?></td>
<?php endif; ?>					<td class="tcr"><?php echo $last_post ?></td>
				</tr>
<?php

	}

	unset ($result, $query, $params);
}
else
{
	$colspan = ($pun_config['o_topic_views'] == '1') ? 4 : 3;

?>
				<tr class="rowodd inone">
					<td class="tcl" colspan="<?php echo $colspan ?>">
						<div class="icon inone"><div class="nosize"><!-- --></div></div>
						<div class="tclcon">
							<div>
								<strong><?php echo $lang_forum['Empty forum'] ?></strong>
							</div>
						</div>
					</td>
				</tr>
<?php

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
<?php echo $post_link ?>
		</div>
		<ul class="crumbs">
			<li><a href="index.php"><?php echo $lang_common['Index'] ?></a></li>
			<li><span>»&#160;</span><a href="viewforum.php?id=<?php echo $id ?>"><strong><?php echo pun_htmlspecialchars($cur_forum['forum_name']) ?></strong></a></li>
		</ul>
<?php echo (!empty($forum_actions) ? "\t\t".'<p class="subscribelink clearb">'.implode(' - ', $forum_actions).'</p>'."\n" : '') ?>
		<div class="clearer"></div>
	</div>
</div>
<?php

$forum_id = $id;
$footer_style = 'viewforum';
require PUN_ROOT.'footer.php';
