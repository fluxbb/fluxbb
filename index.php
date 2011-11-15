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


// Load the index.php language file
$lang->load('index');

// Get list of forums and topics with new posts since last visit
if (!$pun_user['is_guest'])
{
	$query = $db->select(array('fid' => 't.forum_id AS fid', 'tid' => 't.id AS tid', 'last_post' => 't.last_post'), 'topics AS t');

	$query->innerJoin('f', 'forums AS f', 'f.id = t.forum_id');

	$query->leftJoin('fp', 'forum_perms AS fp', 'fp.forum_id = f.id AND fp.group_id = :group_id');

	$query->where = '(fp.read_forum IS NULL OR fp.read_forum = 1) AND t.last_post > :last_visit AND t.moved_to IS NULL';

	$params = array(':group_id' => $pun_user['g_id'], ':last_visit' => $pun_user['last_visit']);

	$result = $query->run($params);

	$new_topics = array();
	foreach ($result as $cur_topic)
		$new_topics[$cur_topic['fid']][$cur_topic['tid']] = $cur_topic['last_post'];

	unset ($query, $params, $result);

	$tracked_topics = get_tracked_topics();
}

if ($pun_config['o_feed_type'] == '1')
	$page_head = array('feed' => '<link rel="alternate" type="application/rss+xml" href="extern.php?action=feed&amp;type=rss" title="'.$lang->t('RSS active topics feed').'" />');
else if ($pun_config['o_feed_type'] == '2')
	$page_head = array('feed' => '<link rel="alternate" type="application/atom+xml" href="extern.php?action=feed&amp;type=atom" title="'.$lang->t('Atom active topics feed').'" />');

$forum_actions = array();

// Display a "mark all as read" link
if (!$pun_user['is_guest'])
	$forum_actions[] = '<a href="misc.php?action=markread">'.$lang->t('Mark all as read').'</a>';

$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']));
define('PUN_ALLOW_INDEX', 1);
define('PUN_ACTIVE_PAGE', 'index');
require PUN_ROOT.'header.php';

// Print the categories and forums
$query = $db->select(array('cid' => 'c.id AS cid', 'cat_name' => 'c.cat_name', 'fid' => 'f.id AS fid', 'forum_name' => 'f.forum_name', 'forum_desc' => 'f.forum_desc', 'redirect_url' => 'f.redirect_url', 'moderators' => 'f.moderators', 'num_topics' => 'f.num_topics', 'num_posts' => 'f.num_posts', 'last_post' => 'f.last_post', 'last_post_id' => 'f.last_post_id', 'last_poster' => 'f.last_poster'), 'categories AS c');

$query->innerJoin('f', 'forums AS f', 'c.id = f.cat_id');

$query->leftJoin('fp', 'forum_perms AS fp', 'fp.forum_id = f.id AND fp.group_id = :group_id');

$query->where = 'fp.read_forum IS NULL OR fp.read_forum = 1';
$query->order = array('cposition' => 'c.disp_position ASC', 'cid' => 'c.id ASC', 'fposition' => 'f.disp_position ASC');

$params = array(':group_id' => $pun_user['g_id']);

$result = $query->run($params);

$cur_category = 0;
$cat_count = 0;
$forum_count = 0;

foreach ($result as $cur_forum)
{
	$moderators = '';

	if ($cur_forum['cid'] != $cur_category) // A new category since last iteration?
	{
		if ($cur_category != 0)
			echo "\t\t\t".'</tbody>'."\n\t\t\t".'</table>'."\n\t\t".'</div>'."\n\t".'</div>'."\n".'</div>'."\n\n";

		++$cat_count;
		$forum_count = 0;

?>
<div id="idx<?php echo $cat_count ?>" class="blocktable">
	<h2><span><?php echo pun_htmlspecialchars($cur_forum['cat_name']) ?></span></h2>
	<div class="box">
		<div class="inbox">
			<table cellspacing="0">
			<thead>
				<tr>
					<th class="tcl" scope="col"><?php echo $lang->t('Forum') ?></th>
					<th class="tc2" scope="col"><?php echo $lang->t('Topics') ?></th>
					<th class="tc3" scope="col"><?php echo $lang->t('Posts') ?></th>
					<th class="tcr" scope="col"><?php echo $lang->t('Last post') ?></th>
				</tr>
			</thead>
			<tbody>
<?php

		$cur_category = $cur_forum['cid'];
	}

	++$forum_count;
	$item_status = ($forum_count % 2 == 0) ? 'roweven' : 'rowodd';
	$forum_field_new = '';
	$icon_type = 'icon';

	// Are there new posts since our last visit?
	if (!$pun_user['is_guest'] && $cur_forum['last_post'] > $pun_user['last_visit'] && (empty($tracked_topics['forums'][$cur_forum['fid']]) || $cur_forum['last_post'] > $tracked_topics['forums'][$cur_forum['fid']]))
	{
		// There are new posts in this forum, but have we read all of them already?
		foreach ($new_topics[$cur_forum['fid']] as $check_topic_id => $check_last_post)
		{
			if ((empty($tracked_topics['topics'][$check_topic_id]) || $tracked_topics['topics'][$check_topic_id] < $check_last_post) && (empty($tracked_topics['forums'][$cur_forum['fid']]) || $tracked_topics['forums'][$cur_forum['fid']] < $check_last_post))
			{
				$item_status .= ' inew';
				$forum_field_new = '<span class="newtext">[ <a href="search.php?action=show_new&amp;fid='.$cur_forum['fid'].'">'.$lang->t('New posts').'</a> ]</span>';
				$icon_type = 'icon icon-new';

				break;
			}
		}
	}

	// Is this a redirect forum?
	if ($cur_forum['redirect_url'] != '')
	{
		$forum_field = '<h3><span class="redirtext">'.$lang->t('Link to').'</span> <a href="'.pun_htmlspecialchars($cur_forum['redirect_url']).'" title="'.$lang->t('Link to').' '.pun_htmlspecialchars($cur_forum['redirect_url']).'">'.pun_htmlspecialchars($cur_forum['forum_name']).'</a></h3>';
		$num_topics = $num_posts = '-';
		$item_status .= ' iredirect';
		$icon_type = 'icon';
	}
	else
	{
		$forum_field = '<h3><a href="viewforum.php?id='.$cur_forum['fid'].'">'.pun_htmlspecialchars($cur_forum['forum_name']).'</a>'.(!empty($forum_field_new) ? ' '.$forum_field_new : '').'</h3>';
		$num_topics = $cur_forum['num_topics'];
		$num_posts = $cur_forum['num_posts'];
	}

	if ($cur_forum['forum_desc'] != '')
		$forum_field .= "\n\t\t\t\t\t\t\t\t".'<div class="forumdesc">'.$cur_forum['forum_desc'].'</div>';

	// If there is a last_post/last_poster
	if ($cur_forum['last_post'] != '')
		$last_post = '<a href="viewtopic.php?pid='.$cur_forum['last_post_id'].'#p'.$cur_forum['last_post_id'].'">'.format_time($cur_forum['last_post']).'</a> <span class="byuser">'.$lang->t('by').' '.pun_htmlspecialchars($cur_forum['last_poster']).'</span>';
	else if ($cur_forum['redirect_url'] != '')
		$last_post = '- - -';
	else
		$last_post = $lang->t('Never');

	if ($cur_forum['moderators'] != '')
	{
		$mods_array = unserialize($cur_forum['moderators']);
		$moderators = array();

		foreach ($mods_array as $mod_username => $mod_id)
		{
			if ($pun_user['g_view_users'] == '1')
				$moderators[] = '<a href="profile.php?id='.$mod_id.'">'.pun_htmlspecialchars($mod_username).'</a>';
			else
				$moderators[] = pun_htmlspecialchars($mod_username);
		}

		$moderators = "\t\t\t\t\t\t\t\t".'<p class="modlist">(<em>'.$lang->t('Moderated by').'</em> '.implode(', ', $moderators).')</p>'."\n";
	}

?>
				<tr class="<?php echo $item_status ?>">
					<td class="tcl">
						<div class="<?php echo $icon_type ?>"><div class="nosize"><?php echo forum_number_format($forum_count) ?></div></div>
						<div class="tclcon">
							<div>
								<?php echo $forum_field."\n".$moderators ?>
							</div>
						</div>
					</td>
					<td class="tc2"><?php echo forum_number_format($num_topics) ?></td>
					<td class="tc3"><?php echo forum_number_format($num_posts) ?></td>
					<td class="tcr"><?php echo $last_post ?></td>
				</tr>
<?php

}

unset ($query, $params, $result);

// Did we output any categories and forums?
if ($cur_category > 0)
	echo "\t\t\t".'</tbody>'."\n\t\t\t".'</table>'."\n\t\t".'</div>'."\n\t".'</div>'."\n".'</div>'."\n\n";
else
	echo '<div id="idx0" class="block"><div class="box"><div class="inbox"><p>'.$lang->t('Empty board').'</p></div></div></div>';

// Collect some board statistics
$stats = fetch_board_stats();

$query = $db->select(array('total_topics' => 'SUM(f.num_topics) AS total_topics', 'total_posts' => 'SUM(f.num_posts) AS total_posts'), 'forums AS f');
$params = array();

$stats = array_merge($stats, current($query->run($params)));
unset ($query, $params);

if ($pun_user['g_view_users'] == '1')
	$stats['newest_user'] = '<a href="profile.php?id='.$stats['last_user']['id'].'">'.pun_htmlspecialchars($stats['last_user']['username']).'</a>';
else
	$stats['newest_user'] = pun_htmlspecialchars($stats['last_user']['username']);

if (!empty($forum_actions))
{

?>
<div class="linksb">
	<div class="inbox crumbsplus">
		<p class="subscribelink clearb"><?php echo implode(' - ', $forum_actions); ?></p>
	</div>
</div>
<?php

}

?>
<div id="brdstats" class="block">
	<h2><span><?php echo $lang->t('Board info') ?></span></h2>
	<div class="box">
		<div class="inbox">
			<dl class="conr">
				<dt><strong><?php echo $lang->t('Board stats') ?></strong></dt>
				<dd><span><?php echo $lang->t('No of users', '<strong>'.forum_number_format($stats['total_users']).'</strong>') ?></span></dd>
				<dd><span><?php echo $lang->t('No of topics', '<strong>'.forum_number_format($stats['total_topics']).'</strong>') ?></span></dd>
				<dd><span><?php echo $lang->t('No of posts', '<strong>'.forum_number_format($stats['total_posts']).'</strong>') ?></span></dd>
			</dl>
			<dl class="conl">
				<dt><strong><?php echo $lang->t('User info') ?></strong></dt>
				<dd><span><?php echo $lang->t('Newest user', $stats['newest_user']) ?></span></dd>
<?php

if ($pun_config['o_users_online'] == '1')
{
	// Fetch users session info and generate strings for output
	$query = $db->select(array('user_id' => 's.user_id', 'username' => 'u.username'), 'sessions AS s');

	$query->InnerJoin('u', 'users AS u', 'u.id = s.user_id');

	$query->where = 's.last_visit > :idle_visit';
	$query->order = array('username' => 'u.username ASC');

	$params = array(':idle_visit' => time() - $pun_config['o_timeout_visit']);

	$num_guests = 0;
	$users = array();

	$result = $query->run($params);
	foreach ($result as $cur_user)
	{
		if ($cur_user['user_id'] > 1)
		{
			if ($pun_user['g_view_users'] == '1')
				$users[] = "\n\t\t\t\t".'<dd><a href="profile.php?id='.$cur_user['user_id'].'">'.pun_htmlspecialchars($cur_user['username']).'</a>';
			else
				$users[] = "\n\t\t\t\t".'<dd>'.pun_htmlspecialchars($cur_user['username']);
		}
		else
			$num_guests++;
	}

	unset ($query, $params, $result);

	$num_users = count($users);
	echo "\t\t\t\t".'<dd><span>'.$lang->t('Users online', '<strong>'.forum_number_format($num_users).'</strong>').'</span></dd>'."\n\t\t\t\t".'<dd><span>'.$lang->t('Guests online', '<strong>'.forum_number_format($num_guests).'</strong>').'</span></dd>'."\n\t\t\t".'</dl>'."\n";


	if ($num_users > 0)
		echo "\t\t\t".'<dl id="onlinelist" class="clearb">'."\n\t\t\t\t".'<dt><strong>'.$lang->t('Online').' </strong></dt>'."\t\t\t\t".implode(',</dd> ', $users).'</dd>'."\n\t\t\t".'</dl>'."\n";
	else
		echo "\t\t\t".'<div class="clearer"></div>'."\n";

}
else
	echo "\t\t\t".'</dl>'."\n\t\t\t".'<div class="clearer"></div>'."\n";


?>
		</div>
	</div>
</div>
<?php

$footer_style = 'index';
require PUN_ROOT.'footer.php';
