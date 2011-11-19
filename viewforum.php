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


$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id < 1)
	message($lang->t('Bad request'));

// Load the viewforum.php language file
$lang->load('forum');

// Fetch some info about the forum
$query = $db->select(array('forum_name' => 'f.forum_name', 'redirect_url' => 'f.redirect_url', 'moderators' => 'f.moderators', 'num_topics' => 'f.num_topics', 'sort_by' => 'f.sort_by', 'post_topics' => 'fp.post_topics', 'is_subscribed' => '0 AS is_subscribed'), 'forums AS f');

$query->leftJoin('fp', 'forum_perms AS fp', 'fp.forum_id = f.id AND fp.group_id = :group_id');

$query->where = '(fp.read_forum IS NULL OR fp.read_forum = 1) AND f.id = :forum_id';

$params = array(':group_id' => $pun_user['g_id'], ':forum_id' => $id);

// If we aren't a guest then handle subscription checks
if (!$pun_user['is_guest'])
{
	$query->fields['is_subscribed'] = 's.user_id AS is_subscribed';

	$query->leftJoin('s', 'forum_subscriptions AS s', 'f.id = s.forum_id AND s.user_id = :user_id');

	// Topic/forum tracing
	$query->fields['mark_time'] = 'ft.mark_time AS forum_mark_time';

	$query->leftJoin('ft', 'forums_track AS ft', 'ft.user_id = :ft_user_id AND f.id = ft.forum_id');

	$params[':user_id'] = $params[':ft_user_id'] = $pun_user['id'];
}

$result = $query->run($params);
if (empty($result))
	message($lang->t('Bad request'));

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
	$post_link = "\t\t\t".'<p class="postlink conr"><a href="post.php?fid='.$id.'">'.$lang->t('Post topic').'</a></p>'."\n";
else
	$post_link = '';

// Determine the topic offset (based on $_GET['p'])
$num_pages = ceil($cur_forum['num_topics'] / $pun_user['disp_topics']);

$p = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $num_pages) ? 1 : intval($_GET['p']);
$start_from = $pun_user['disp_topics'] * ($p - 1);

// Generate paging links
$paging_links = '<span class="pages-label">'.$lang->t('Pages').' </span>'.paginate($num_pages, $p, 'viewforum.php?id='.$id);

if ($pun_config['o_feed_type'] == '1')
	$page_head = array('feed' => '<link rel="alternate" type="application/rss+xml" href="extern.php?action=feed&amp;fid='.$id.'&amp;type=rss" title="'.$lang->t('RSS forum feed').'" />');
else if ($pun_config['o_feed_type'] == '2')
	$page_head = array('feed' => '<link rel="alternate" type="application/atom+xml" href="extern.php?action=feed&amp;fid='.$id.'&amp;type=atom" title="'.$lang->t('Atom forum feed').'" />');

$forum_actions = array();

if (!$pun_user['is_guest'])
{
	if ($pun_config['o_forum_subscriptions'] == '1')
	{
		if ($cur_forum['is_subscribed'])
			$forum_actions[] = '<span>'.$lang->t('Is subscribed').' - </span><a href="misc.php?action=unsubscribe&amp;fid='.$id.'">'.$lang->t('Unsubscribe').'</a>';
		else
			$forum_actions[] = '<a href="misc.php?action=subscribe&amp;fid='.$id.'">'.$lang->t('Subscribe').'</a>';
	}

	$forum_actions[] = '<a href="misc.php?action=markforumread&amp;fid='.$id.'">'.$lang->t('Mark forum read').'</a>';
}

$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), pun_htmlspecialchars($cur_forum['forum_name']));
define('PUN_ALLOW_INDEX', 1);
define('PUN_ACTIVE_PAGE', 'index');
require PUN_ROOT.'header.php';

?>
<div class="linkst">
	<div class="inbox crumbsplus">
		<ul class="crumbs">
			<li><a href="index.php"><?php echo $lang->t('Index') ?></a></li>
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
					<th class="tcl" scope="col"><?php echo $lang->t('Topic') ?></th>
					<th class="tc2" scope="col"><?php echo $lang->t('Replies') ?></th>
<?php if ($pun_config['o_topic_views'] == '1'): ?>					<th class="tc3" scope="col"><?php echo $lang->t('Views') ?></th>
<?php endif; ?>					<th class="tcr" scope="col"><?php echo $lang->t('Last post') ?></th>
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

$params = array(':forum_id' => $id);

$topic_ids = $query->run($params);
unset ($query, $params);

// If there are topics in this forum
if (!empty($topic_ids))
{
	// Translate from a 3d array into 2d array: $topics_ids[0]['id'] -> $topics_ids[0]
	foreach ($topic_ids as $key => $value)
		$topic_ids[$key] = $value['id'];

	// Fetch list of topics to display on this page
	$query = $db->select(array('has_posted' => '0 AS has_posted', 'tid' => 't.id', 'subject' => 't.subject', 'poster' => 't.poster', 'posted' => 't.posted', 'last_post' => 't.last_post', 'last_post_id' => 't.last_post_id', 'last_poster' => 't.last_poster', 'num_views' => 't.num_views', 'num_replies' => 't.num_replies', 'closed' => 't.closed', 'sticky' => 't.sticky', 'moved_to' => 't.moved_to'), 'topics AS t');
	$query->where = 't.id IN :tids';
	$query->order = array('sticky' => 't.sticky DESC', 'sort' => $sort_by, 'id' => 't.id DESC');

	$params = array(':tids' => $topic_ids);

	// With "the dot"
	if ($pun_user['is_guest'] || $pun_config['o_show_dot'] == '1' && !$pun_user['is_guest'])
	{
		$query->fields['has_posted'] = 'p.poster_id AS has_posted';

		$query->leftJoin('p', 'posts AS p', 't.id = p.topic_id AND p.poster_id = :user_id');

		$query->group = array('t.id', 't.subject', 't.poster', 't.posted', 't.last_post', 't.last_post_id', 't.last_poster', 't.num_views', 't.num_replies', 't.closed', 't.sticky', 't.moved_to', 'p.poster_id');

		$params[':user_id'] = $pun_user['id'];
	}

	if (!$pun_user['is_guest'])
	{
		// Topic tracking
		$query->fields['mark_time'] = 'tt.mark_time';
		$query->leftJoin('tt', 'topics_track AS tt', 'tt.user_id = :tt_user_id AND t.id = tt.topic_id');
		$params[':tt_user_id'] = $pun_user['id'];
	}

	$topic_count = 0;

	$result = $query->run($params);

	// Get topic tracking data
	if (!$pun_user['is_guest'])
	{
		// Generate topic list...
		$topic_list = array();
		foreach ($result as $cur_topic)
			$topic_list[$cur_topic['id']] = $cur_topic;

		$topic_tracking_info = get_topic_tracking($id, $topic_ids, $topic_list, array($id => $cur_forum['forum_mark_time']), false);
	}

	foreach ($result as $cur_topic)
	{
		++$topic_count;
		$status_text = array();
		$item_status = ($topic_count % 2 == 0) ? 'roweven' : 'rowodd';
		$icon_type = 'icon';

		if ($cur_topic['moved_to'] == null)
			$last_post = '<a href="viewtopic.php?pid='.$cur_topic['last_post_id'].'#p'.$cur_topic['last_post_id'].'">'.format_time($cur_topic['last_post']).'</a> <span class="byuser">'.$lang->t('by').' '.pun_htmlspecialchars($cur_topic['last_poster']).'</span>';
		else
			$last_post = '- - -';

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

		if (!$pun_user['is_guest'] && (isset($topic_tracking_info[$cur_topic['id']]) && $cur_topic['last_post'] > $topic_tracking_info[$cur_topic['id']]) && $cur_topic['moved_to'] == null)
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
								<strong><?php echo $lang->t('Empty forum') ?></strong>
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
			<li><a href="index.php"><?php echo $lang->t('Index') ?></a></li>
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
