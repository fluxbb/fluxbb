<?php

/**
 * Copyright (C) 2008-2011 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

// Make sure we have a usable language pack for admin.
if (file_exists(PUN_ROOT.'lang/'.$pun_user['language'].'/admin_common.php'))
	$admin_language = $pun_user['language'];
else if (file_exists(PUN_ROOT.'lang/'.$pun_config['o_default_lang'].'/admin_common.php'))
	$admin_language = $pun_config['language'];
else
	$admin_language = 'English';

// Attempt to load the admin_common language file
require PUN_ROOT.'lang/'.$admin_language.'/admin_common.php';

//
// Display the admin navigation menu
//
function generate_admin_menu($page = '')
{
	global $pun_config, $pun_user, $lang_admin_common;

	$is_admin = $pun_user['g_id'] == PUN_ADMIN ? true : false;

?>
<div id="adminconsole" class="block2col">
	<div id="adminmenu" class="blockmenu">
		<h2><span><?php echo $lang_admin_common['Moderator menu'] ?></span></h2>
		<div class="box">
			<div class="inbox">
				<ul>
					<li<?php if ($page == 'index') echo ' class="isactive"'; ?>><a href="admin_index.php"><?php echo $lang_admin_common['Index'] ?></a></li>
					<li<?php if ($page == 'users') echo ' class="isactive"'; ?>><a href="admin_users.php"><?php echo $lang_admin_common['Users'] ?></a></li>
<?php if ($is_admin || $pun_user['g_mod_ban_users'] == '1'): ?>					<li<?php if ($page == 'bans') echo ' class="isactive"'; ?>><a href="admin_bans.php"><?php echo $lang_admin_common['Bans'] ?></a></li>
<?php endif; if ($is_admin || $pun_config['o_report_method'] == '0' || $pun_config['o_report_method'] == '2'): ?>					<li<?php if ($page == 'reports') echo ' class="isactive"'; ?>><a href="admin_reports.php"><?php echo $lang_admin_common['Reports'] ?></a></li>
<?php endif; ?>				</ul>
			</div>
		</div>
<?php

	if ($is_admin)
	{

?>
		<h2 class="block2"><span><?php echo $lang_admin_common['Admin menu'] ?></span></h2>
		<div class="box">
			<div class="inbox">
				<ul>
					<li<?php if ($page == 'options') echo ' class="isactive"'; ?>><a href="admin_options.php"><?php echo $lang_admin_common['Options'] ?></a></li>
					<li<?php if ($page == 'permissions') echo ' class="isactive"'; ?>><a href="admin_permissions.php"><?php echo $lang_admin_common['Permissions'] ?></a></li>
					<li<?php if ($page == 'categories') echo ' class="isactive"'; ?>><a href="admin_categories.php"><?php echo $lang_admin_common['Categories'] ?></a></li>
					<li<?php if ($page == 'forums') echo ' class="isactive"'; ?>><a href="admin_forums.php"><?php echo $lang_admin_common['Forums'] ?></a></li>
					<li<?php if ($page == 'groups') echo ' class="isactive"'; ?>><a href="admin_groups.php"><?php echo $lang_admin_common['User groups'] ?></a></li>
					<li<?php if ($page == 'censoring') echo ' class="isactive"'; ?>><a href="admin_censoring.php"><?php echo $lang_admin_common['Censoring'] ?></a></li>
					<li<?php if ($page == 'ranks') echo ' class="isactive"'; ?>><a href="admin_ranks.php"><?php echo $lang_admin_common['Ranks'] ?></a></li>
					<li<?php if ($page == 'maintenance') echo ' class="isactive"'; ?>><a href="admin_maintenance.php"><?php echo $lang_admin_common['Maintenance'] ?></a></li>
				</ul>
			</div>
		</div>
<?php

	}

	// See if there are any plugins
	$plugins = forum_list_plugins($is_admin);

	// Did we find any plugins?
	if (!empty($plugins))
	{

?>
		<h2 class="block2"><span><?php echo $lang_admin_common['Plugins menu'] ?></span></h2>
		<div class="box">
			<div class="inbox">
				<ul>
<?php

		foreach ($plugins as $cur_plugin)
			echo "\t\t\t\t\t".'<li'.(($page == $cur_plugin[1]) ? ' class="isactive"' : '').'><a href="admin_loader.php?plugin='.$cur_plugin[1].'">'.str_replace('_', ' ', $cur_plugin[0]).'</a></li>'."\n";

?>
				</ul>
			</div>
		</div>
<?php

	}

?>
	</div>

<?php

}


//
// Delete topics from $forum_id that are "older than" $prune_date (if $prune_sticky is 1, sticky topics will also be deleted)
//
function prune($forum_id, $prune_sticky, $prune_date)
{
	global $db;

	// Fetch topics to prune
	$query = new SelectQuery(array('id' => 't.id'), 'topics AS t');
	$query->where = 't.forum_id = :forum_id';
	
	$params = array(':forum_id' => $forum_id);
	
	if ($prune_date != -1)
	{
		$query->where .= ' AND t.last_post < :prune_date';
		$params[':prune_date'] = $prune_date;
	}
	if (!$prune_sticky)
		$query->where .= ' AND t.sticky = 0';
	
	$result = $db->query($query, $params);
	
	$topic_ids = array();
	foreach ($result as $row)
		$topic_ids[] = $row['id'];
	unset($query, $params, $result);

	if (!empty($topic_ids))
	{
		// Fetch posts to prune
		$query = new SelectQuery(array('id' => 'p.id'), 'posts AS p');
		$query->where = 'p.topic_id IN :topic_ids';
		
		$params = array(':topic_ids' => $topic_ids);
		
		$result = $db->query($query, $params);
		
		$post_ids = array();
		foreach ($result as $row)
			$post_ids[] = $row['id'];
		unset($query, $params, $result);

		if (!empty($post_ids))
		{
			// Delete topics
			$query = new DeleteQuery('topics');
			$query->where = 'id IN :topic_ids';
			
			$params = array(':topic_ids' => $topic_ids);
			
			$db->query($query, $params);
			unset($query, $params);
			
			// Delete subscriptions
			$query = new DeleteQuery('topic_subscriptions');
			$query->where = 'topic_id IN :topic_ids';
			
			$params = array(':topic_ids' => $topic_ids);
			
			$db->query($query, $params);
			unset($query, $params);
			
			// Delete posts
			$query = new DeleteQuery('posts');
			$query->where = 'id IN :post_ids';
			
			$params = array(':post_ids' => $post_ids);
			
			$db->query($query, $params);
			unset($query, $params);
			
			// We removed a bunch of posts, so now we have to update the search index
			require_once PUN_ROOT.'include/search_idx.php';
			strip_search_index($post_ids);
		}
	}
}
