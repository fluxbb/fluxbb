<?php

/**
 * Copyright (C) 2008-2011 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Tell header.php to use the admin template
define('PUN_ADMIN_CONSOLE', 1);

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/common_admin.php';


if ($pun_user['g_id'] != PUN_ADMIN)
	message($lang->t('No permission'));

// Load the admin_forums.php language file
$lang->load('admin_forums');

// Add a "default" forum
if (isset($_POST['add_forum']))
{
	confirm_referrer('admin_forums.php');

	$add_to_cat = intval($_POST['add_to_cat']);
	if ($add_to_cat < 1)
		message($lang->t('Bad request'));

	$query = $db->insert(array('forum_name' => ':forum_name', 'cat_id' => ':cat_id'), 'forums');
	$params = array(':forum_name' => $lang->t('New forum'), ':cat_id' => $add_to_cat);

	$query->run($params);
	unset ($query, $params);

	// Regenerate the quick jump cache
	$cache->delete('quickjump');

	redirect('admin_forums.php', $lang->t('Forum added redirect'));
}

// Delete a forum
else if (isset($_GET['del_forum']))
{
	confirm_referrer('admin_forums.php');

	$forum_id = intval($_GET['del_forum']);
	if ($forum_id < 1)
		message($lang->t('Bad request'));

	if (isset($_POST['del_forum_comply'])) // Delete a forum with all posts
	{
		@set_time_limit(0);

		// Prune all posts and topics
		prune($forum_id, 1, -1);

		// Locate any "orphaned redirect topics" and delete them
		$query = $db->select(array('id' => 't1.id'), 'topics AS t1');

		$query->leftJoin('t2', 'topics AS t2', 't1.moved_to = t2.id');

		$query->where = 't2.id IS NULL AND t1.moved_to IS NOT NULL';

		$params = array();

		$result = $query->run($params);
		unset ($query, $params);

		if (!empty($result))
		{
			$orphans = array();
			foreach ($result as $cur_orphan)
				$orphans[] = $cur_orphan['id'];

			$query = $db->delete('topics');
			$query->where = 'id IN :tids';

			$params = array(':tids' => $orphans);

			$query->run($params);
			unset ($query, $params);
		}

		unset ($result);

		// Delete the forum
		$query = $db->delete('forums');
		$query->where = 'id = :forum_id';

		$params = array(':forum_id' => $forum_id);

		$query->run($params);
		unset ($query, $params);

		// Delete any forum specific group permissions
		$query = $db->delete('forum_perms');
		$query->where = 'forum_id = :forum_id';

		$params = array(':forum_id' => $forum_id);

		$query->run($params);
		unset ($query, $params);

		// Delete any subscriptions for this forum
		$query = $db->delete('forum_subscriptions');
		$query->where = 'forum_id = :forum_id';

		$params = array(':forum_id' => $forum_id);

		$query->run($params);
		unset ($query, $params);

		// Regenerate the quick jump cache
		$cache->delete('quickjump');

		redirect('admin_forums.php', $lang->t('Forum deleted redirect'));
	}
	else // If the user hasn't confirmed the delete
	{
		$query = $db->select(array('forum_name' => 'f.forum_name'), 'forums AS f');
		$query->where = 'f.id = :forum_id';

		$params = array(':forum_id' => $forum_id);

		$result = $query->run($params);
		$forum_name = pun_htmlspecialchars($result[0]['forum_name']);
		unset ($reuslt, $query, $params);

		$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Admin'), $lang->t('Forums'));
		define('PUN_ACTIVE_PAGE', 'admin');
		require PUN_ROOT.'header.php';

		generate_admin_menu('forums');

?>
	<div class="blockform">
		<h2><span><?php echo $lang->t('Confirm delete head') ?></span></h2>
		<div class="box">
			<form method="post" action="admin_forums.php?del_forum=<?php echo $forum_id ?>">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Confirm delete forum subhead') ?></legend>
						<div class="infldset">
							<p><?php echo $lang->t('Confirm delete forum info', $forum_name) ?></p>
							<p class="warntext"><?php echo $lang->t('Confirm delete warn') ?></p>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="del_forum_comply" value="<?php echo $lang->t('Delete') ?>" /><a href="javascript:history.go(-1)"><?php echo $lang->t('Go back') ?></a></p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

		require PUN_ROOT.'footer.php';
	}
}

// Update forum positions
else if (isset($_POST['update_positions']))
{
	confirm_referrer('admin_forums.php');

	$query = $db->update(array('disp_position' => ':position'), 'forums');
	$query->where = 'id = :forum_id';

	foreach ($_POST['position'] as $forum_id => $disp_position)
	{
		$disp_position = trim($disp_position);
		if ($disp_position == '' || preg_match('%[^0-9]%', $disp_position))
			message($lang->t('Must be integer message'));

		$params = array(':position' => $disp_position, ':forum_id' => $forum_id);
		$query->run($params);
		unset ($params);
	}

	unset ($query);

	// Regenerate the quick jump cache
	$cache->delete('quickjump');

	redirect('admin_forums.php', $lang->t('Forums updated redirect'));
}

else if (isset($_GET['edit_forum']))
{
	$forum_id = intval($_GET['edit_forum']);
	if ($forum_id < 1)
		message($lang->t('Bad request'));

	// Update group permissions for $forum_id
	if (isset($_POST['save']))
	{
		confirm_referrer('admin_forums.php');

		// Start with the forum details
		$forum_name = pun_trim($_POST['forum_name']);
		$forum_desc = pun_linebreaks(pun_trim($_POST['forum_desc']));
		$cat_id = intval($_POST['cat_id']);
		$sort_by = intval($_POST['sort_by']);
		$redirect_url = isset($_POST['redirect_url']) ? trim($_POST['redirect_url']) : null;

		if ($forum_name == '')
			message($lang->t('Must enter forum name message'));

		if ($cat_id < 1)
			message($lang->t('Bad request'));

		$query = $db->update(array('forum_name' => ':forum_name', 'forum_desc' => ':forum_desc', 'redirect_url' => ':redirect_url', 'sort_by' => ':sort_by', 'cat_id' => ':cat_id'), 'forums');
		$query->where = 'id = :forum_id';

		$params = array(':forum_name' => $forum_name, ':forum_desc' => empty($forum_desc) ? null : $forum_desc, ':redirect_url' => empty($redirect_url) ? null : $redirect_url, ':sort_by' => $sort_by, ':cat_id' => $cat_id, ':forum_id' => $forum_id);

		$query->run($params);
		unset ($query, $params);

		// Now let's deal with the permissions
		if (isset($_POST['read_forum_old']))
		{
			$query = $db->select(array('g_id' => 'g.g_id', 'g_read_board' => 'g.g_read_board', 'g_post_replies' => 'g.g_post_replies', 'g_post_topics' => 'g.g_post_topics'), 'groups AS g');
			$query->where = 'g.g_id != :group_admin';

			$params = array(':group_admin' => PUN_ADMIN);

			$result = $query->run($params);
			unset ($query, $params);

			$delete_query = $db->delete('forum_perms');
			$delete_query->where = 'group_id = :group_id AND forum_id = :forum_id';

			$replace_query = $db->replace(array('read_forum' => ':read_forum', 'post_replies' => ':post_replies', 'post_topics' => ':post_topics'), 'forum_perms', array('group_id' => ':group_id', 'forum_id' => ':forum_id'));

			foreach ($result as $cur_group)
			{
				$read_forum_new = ($cur_group['g_read_board'] == '1') ? isset($_POST['read_forum_new'][$cur_group['g_id']]) ? '1' : '0' : intval($_POST['read_forum_old'][$cur_group['g_id']]);
				$post_replies_new = isset($_POST['post_replies_new'][$cur_group['g_id']]) ? '1' : '0';
				$post_topics_new = isset($_POST['post_topics_new'][$cur_group['g_id']]) ? '1' : '0';

				// Check if the new settings differ from the old
				if ($read_forum_new != $_POST['read_forum_old'][$cur_group['g_id']] || $post_replies_new != $_POST['post_replies_old'][$cur_group['g_id']] || $post_topics_new != $_POST['post_topics_old'][$cur_group['g_id']])
				{
					// If the new settings are identical to the default settings for this group, delete it's row in forum_perms
					if ($read_forum_new == '1' && $post_replies_new == $cur_group['g_post_replies'] && $post_topics_new == $cur_group['g_post_topics'])
					{
						$params = array(':group_id' => $cur_group['g_id'], ':forum_id' => $forum_id);

						$delete_query->run($params);
						unset ($params);
					}
					else
					{
						$params = array(':group_id' => $cur_group['g_id'], ':forum_id' => $forum_id, ':read_forum' => $read_forum_new, ':post_replies' => $post_replies_new, ':post_topics' => $post_topics_new);

						$replace_query->run($params);
						unset ($params);
					}
				}
			}

			unset ($result, $delete_query, $replace_query);
		}

		// Regenerate the quick jump cache
		$cache->delete('quickjump');

		redirect('admin_forums.php', $lang->t('Forum updated redirect'));
	}
	else if (isset($_POST['revert_perms']))
	{
		confirm_referrer('admin_forums.php');

		$query = $db->delete('forum_perms');
		$query->where = 'forum_id = :forum_id';

		$params = array(':forum_id' => $forum_id);

		$query->run($params);
		unset ($query, $params);

		// Regenerate the quick jump cache
		$cache->delete('quickjump');

		redirect('admin_forums.php?edit_forum='.$forum_id, $lang->t('Perms reverted redirect'));
	}

	// Fetch forum info
	$query = $db->select(array('id' => 'f.id', 'forum_name' => 'f.forum_name', 'forum_desc' => 'f.forum_desc', 'redirect_url' => 'f.redirect_url', 'num_topics' => 'f.num_topics', 'sort_by' => 'f.sort_by', 'cat_id' => 'f.cat_id'), 'forums AS f');
	$query->where = 'f.id = :forum_id';

	$params = array(':forum_id' => $forum_id);

	$result = $query->run($params);
	if (empty($result))
		message($lang->t('Bad request'));

	$cur_forum = $result[0];
	unset ($result, $query, $params);

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Admin'), $lang->t('Forums'));
	define('PUN_ACTIVE_PAGE', 'admin');
	require PUN_ROOT.'header.php';

	generate_admin_menu('forums');

?>
	<div class="blockform">
		<h2><span><?php echo $lang->t('Edit forum head') ?></span></h2>
		<div class="box">
			<form id="edit_forum" method="post" action="admin_forums.php?edit_forum=<?php echo $forum_id ?>">
				<p class="submittop"><input type="submit" name="save" value="<?php echo $lang->t('Save changes') ?>" tabindex="6" /></p>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Edit details subhead') ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang->t('Forum name label') ?></th>
									<td><input type="text" name="forum_name" size="35" maxlength="80" value="<?php echo pun_htmlspecialchars($cur_forum['forum_name']) ?>" tabindex="1" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Forum description label') ?></th>
									<td><textarea name="forum_desc" rows="3" cols="50" tabindex="2"><?php echo pun_htmlspecialchars($cur_forum['forum_desc']) ?></textarea></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Category label') ?></th>
									<td>
										<select name="cat_id" tabindex="3">
<?php

	$query = $db->select(array('id' => 'c.id', 'cat_name' => 'c.cat_name'), 'categories AS c');
	$query->order = array('cposition' => 'c.disp_position ASC');

	$params = array();

	$result = $query->run($params);
	foreach ($result as $cur_cat)
	{
		$selected = ($cur_cat['id'] == $cur_forum['cat_id']) ? ' selected="selected"' : '';
		echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_cat['id'].'"'.$selected.'>'.pun_htmlspecialchars($cur_cat['cat_name']).'</option>'."\n";
	}

	unset ($result, $query, $params);

?>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Sort by label') ?></th>
									<td>
										<select name="sort_by" tabindex="4">
											<option value="0"<?php if ($cur_forum['sort_by'] == '0') echo ' selected="selected"' ?>><?php echo $lang->t('Last post') ?></option>
											<option value="1"<?php if ($cur_forum['sort_by'] == '1') echo ' selected="selected"' ?>><?php echo $lang->t('Topic start') ?></option>
											<option value="2"<?php if ($cur_forum['sort_by'] == '2') echo ' selected="selected"' ?>><?php echo $lang->t('Subject') ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Redirect label') ?></th>
									<td><?php echo ($cur_forum['num_topics']) ? $lang->t('Redirect help') : '<input type="text" name="redirect_url" size="45" maxlength="100" value="'.pun_htmlspecialchars($cur_forum['redirect_url']).'" tabindex="5" />'; ?></td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Group permissions subhead') ?></legend>
						<div class="infldset">
							<p><?php echo $lang->t('Group permissions info', '<a href="admin_groups.php">'.$lang->t('User groups').'</a>') ?></p>
							<table id="forumperms" cellspacing="0">
							<thead>
								<tr>
									<th class="atcl">&#160;</th>
									<th><?php echo $lang->t('Read forum label') ?></th>
									<th><?php echo $lang->t('Post replies label') ?></th>
									<th><?php echo $lang->t('Post topics label') ?></th>
								</tr>
							</thead>
							<tbody>
<?php

	$query = $db->select(array('g_id' => 'g.g_id', 'g_title' => 'g.g_title', 'g_read_board' => 'g.g_read_board', 'g_post_replies' => 'g.g_post_replies', 'g_post_topics' => 'g.g_post_topics', 'read_forum' => 'fp.read_forum', 'post_replies' => 'fp.post_replies', 'post_topics' => 'fp.post_topics'), 'groups AS g');

	$query->leftJoin('fp', 'forum_perms AS fp', 'g.g_id = fp.group_id AND fp.forum_id = :forum_id');

	$query->where = 'g.g_id != :group_admin';
	$query->order = array('g_id' => 'g.g_id ASC');

	$params = array(':forum_id' => $forum_id, ':group_admin' => PUN_ADMIN);

	$result = $query->run($params);

	$cur_index = 7;
	foreach ($result as $cur_perm)
	{
		$read_forum = ($cur_perm['read_forum'] != '0') ? true : false;
		$post_replies = (($cur_perm['g_post_replies'] == '0' && $cur_perm['post_replies'] == '1') || ($cur_perm['g_post_replies'] == '1' && $cur_perm['post_replies'] != '0')) ? true : false;
		$post_topics = (($cur_perm['g_post_topics'] == '0' && $cur_perm['post_topics'] == '1') || ($cur_perm['g_post_topics'] == '1' && $cur_perm['post_topics'] != '0')) ? true : false;

		// Determine if the current settings differ from the default or not
		$read_forum_def = ($cur_perm['read_forum'] == '0') ? false : true;
		$post_replies_def = (($post_replies && $cur_perm['g_post_replies'] == '0') || (!$post_replies && ($cur_perm['g_post_replies'] == '' || $cur_perm['g_post_replies'] == '1'))) ? false : true;
		$post_topics_def = (($post_topics && $cur_perm['g_post_topics'] == '0') || (!$post_topics && ($cur_perm['g_post_topics'] == '' || $cur_perm['g_post_topics'] == '1'))) ? false : true;

?>
								<tr>
									<th class="atcl"><?php echo pun_htmlspecialchars($cur_perm['g_title']) ?></th>
									<td<?php if (!$read_forum_def) echo ' class="nodefault"'; ?>>
										<input type="hidden" name="read_forum_old[<?php echo $cur_perm['g_id'] ?>]" value="<?php echo ($read_forum) ? '1' : '0'; ?>" />
										<input type="checkbox" name="read_forum_new[<?php echo $cur_perm['g_id'] ?>]" value="1"<?php echo ($read_forum) ? ' checked="checked"' : ''; ?><?php echo ($cur_perm['g_read_board'] == '0') ? ' disabled="disabled"' : ''; ?> tabindex="<?php echo $cur_index++ ?>" />
									</td>
									<td<?php if (!$post_replies_def && $cur_forum['redirect_url'] == '') echo ' class="nodefault"'; ?>>
										<input type="hidden" name="post_replies_old[<?php echo $cur_perm['g_id'] ?>]" value="<?php echo ($post_replies) ? '1' : '0'; ?>" />
										<input type="checkbox" name="post_replies_new[<?php echo $cur_perm['g_id'] ?>]" value="1"<?php echo ($post_replies) ? ' checked="checked"' : ''; ?><?php echo ($cur_forum['redirect_url'] != '') ? ' disabled="disabled"' : ''; ?> tabindex="<?php echo $cur_index++ ?>" />
									</td>
									<td<?php if (!$post_topics_def && $cur_forum['redirect_url'] == '') echo ' class="nodefault"'; ?>>
										<input type="hidden" name="post_topics_old[<?php echo $cur_perm['g_id'] ?>]" value="<?php echo ($post_topics) ? '1' : '0'; ?>" />
										<input type="checkbox" name="post_topics_new[<?php echo $cur_perm['g_id'] ?>]" value="1"<?php echo ($post_topics) ? ' checked="checked"' : ''; ?><?php echo ($cur_forum['redirect_url'] != '') ? ' disabled="disabled"' : ''; ?> tabindex="<?php echo $cur_index++ ?>" />
									</td>
								</tr>
<?php

	}

	unset ($result, $query, $params);

?>
							</tbody>
							</table>
							<div class="fsetsubmit"><input type="submit" name="revert_perms" value="<?php echo $lang->t('Revert to default') ?>" tabindex="<?php echo $cur_index++ ?>" /></div>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="save" value="<?php echo $lang->t('Save changes') ?>" tabindex="<?php echo $cur_index++ ?>" /></p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>

<?php

	require PUN_ROOT.'footer.php';
}

$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Admin'), $lang->t('Forums'));
define('PUN_ACTIVE_PAGE', 'admin');
require PUN_ROOT.'header.php';

generate_admin_menu('forums');

?>
	<div class="blockform">
		<h2><span><?php echo $lang->t('Add forum head') ?></span></h2>
		<div class="box">
			<form method="post" action="admin_forums.php?action=adddel">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Create new subhead') ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang->t('Add forum label') ?><div><input type="submit" name="add_forum" value="<?php echo $lang->t('Add forum') ?>" tabindex="2" /></div></th>
									<td>
										<select name="add_to_cat" tabindex="1">
<?php

	$query = $db->select(array('id' => 'c.id', 'cat_name' => 'c.cat_name'), 'categories AS c');
	$query->order = array('cposition' => 'c.disp_position ASC');

	$params = array();

	$result = $query->run($params);
	if (!empty($result))
	{
		foreach ($result as $cur_cat)
			echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_cat['id'].'">'.pun_htmlspecialchars($cur_cat['cat_name']).'</option>'."\n";
	}
	else
		echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="0" disabled="disabled">'.$lang->t('No categories exist').'</option>'."\n";

	unset ($result, $query, $params);

?>
										</select>
										<span><?php echo $lang->t('Add forum help') ?></span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
			</form>
		</div>
<?php

// Display all the categories and forums
$query = $db->select(array('cid' => 'c.id AS cid', 'cat_name' => 'c.cat_name', 'fid' => 'f.id AS fid', 'forum_name' => 'f.forum_name', 'fposition' => 'f.disp_position'), 'categories AS c');

$query->innerJoin('f', 'forums AS f', 'c.id = f.cat_id');

$query->order = array('cposition' => 'c.disp_position ASC', 'cid' => 'c.id ASC', 'fposition' => 'f.disp_position ASC');

$params = array();

$result = $query->run($params);
if (!empty($result))
{

?>
		<h2 class="block2"><span><?php echo $lang->t('Edit forums head') ?></span></h2>
		<div class="box">
			<form id="edforum" method="post" action="admin_forums.php?action=edit">
				<p class="submittop"><input type="submit" name="update_positions" value="<?php echo $lang->t('Update positions') ?>" tabindex="3" /></p>
<?php

$cur_index = 4;

$cur_category = 0;
foreach ($result as $cur_forum)
{
	if ($cur_forum['cid'] != $cur_category) // A new category since last iteration?
	{
		if ($cur_category != 0)
			echo "\t\t\t\t\t\t\t".'</tbody>'."\n\t\t\t\t\t\t\t".'</table>'."\n\t\t\t\t\t\t".'</div>'."\n\t\t\t\t\t".'</fieldset>'."\n\t\t\t\t".'</div>'."\n";

?>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Category subhead') ?> <?php echo pun_htmlspecialchars($cur_forum['cat_name']) ?></legend>
						<div class="infldset">
							<table cellspacing="0">
							<thead>
								<tr>
									<th class="tcl"><?php echo $lang->t('Action') ?></th>
									<th class="tc2"><?php echo $lang->t('Position label') ?></th>
									<th class="tcr"><?php echo $lang->t('Forum label') ?></th>
								</tr>
							</thead>
							<tbody>
<?php

		$cur_category = $cur_forum['cid'];
	}

?>
								<tr>
									<td class="tcl"><a href="admin_forums.php?edit_forum=<?php echo $cur_forum['fid'] ?>" tabindex="<?php echo $cur_index++ ?>"><?php echo $lang->t('Edit link') ?></a> | <a href="admin_forums.php?del_forum=<?php echo $cur_forum['fid'] ?>" tabindex="<?php echo $cur_index++ ?>"><?php echo $lang->t('Delete link') ?></a></td>
									<td class="tc2"><input type="text" name="position[<?php echo $cur_forum['fid'] ?>]" size="3" maxlength="3" value="<?php echo $cur_forum['disp_position'] ?>" tabindex="<?php echo $cur_index++ ?>" /></td>
									<td class="tcr"><strong><?php echo pun_htmlspecialchars($cur_forum['forum_name']) ?></strong></td>
								</tr>
<?php

}

unset ($result, $query, $params);

?>
							</tbody>
							</table>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="update_positions" value="<?php echo $lang->t('Update positions') ?>" tabindex="<?php echo $cur_index++ ?>" /></p>
			</form>
		</div>
<?php

}

?>
	</div>
	<div class="clearer"></div>
</div>
<?php

require PUN_ROOT.'footer.php';
