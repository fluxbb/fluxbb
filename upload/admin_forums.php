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


// Tell header.php to use the admin template
define('PUN_ADMIN_CONSOLE', 1);

define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/common_admin.php';


if ($pun_user['g_id'] > PUN_ADMIN)
	message($lang_common['No permission']);


// Add a "default" forum
if (isset($_POST['add_forum']))
{
	confirm_referrer('admin_forums.php');

	$add_to_cat = intval($_POST['add_to_cat']);
	if ($add_to_cat < 1)
		message($lang_common['Bad request']);

	$db->query('INSERT INTO '.$db->prefix.'forums (cat_id) VALUES('.$add_to_cat.')') or error('Unable to create forum', __FILE__, __LINE__, $db->error());

	// Regenerate the quickjump cache
	require_once PUN_ROOT.'include/cache.php';
	generate_quickjump_cache();

	redirect('admin_forums.php', 'Forum added. Redirecting &hellip;');
}


// Delete a forum
else if (isset($_GET['del_forum']))
{
	confirm_referrer('admin_forums.php');

	$forum_id = intval($_GET['del_forum']);
	if ($forum_id < 1)
		message($lang_common['Bad request']);

	if (isset($_POST['del_forum_comply']))	// Delete a forum with all posts
	{
		@set_time_limit(0);

		// Prune all posts and topics
		prune($forum_id, 1, -1);

		// Locate any "orphaned redirect topics" and delete them
		$result = $db->query('SELECT t1.id FROM '.$db->prefix.'topics AS t1 LEFT JOIN '.$db->prefix.'topics AS t2 ON t1.moved_to=t2.id WHERE t2.id IS NULL AND t1.moved_to IS NOT NULL') or error('Unable to fetch redirect topics', __FILE__, __LINE__, $db->error());
		$num_orphans = $db->num_rows($result);

		if ($num_orphans)
		{
			for ($i = 0; $i < $num_orphans; ++$i)
				$orphans[] = $db->result($result, $i);

			$db->query('DELETE FROM '.$db->prefix.'topics WHERE id IN('.implode(',', $orphans).')') or error('Unable to delete redirect topics', __FILE__, __LINE__, $db->error());
		}

		// Delete the forum and any forum specific group permissions
		$db->query('DELETE FROM '.$db->prefix.'forums WHERE id='.$forum_id) or error('Unable to delete forum', __FILE__, __LINE__, $db->error());
		$db->query('DELETE FROM '.$db->prefix.'forum_perms WHERE forum_id='.$forum_id) or error('Unable to delete group forum permissions', __FILE__, __LINE__, $db->error());

		// Regenerate the quickjump cache
		require_once PUN_ROOT.'include/cache.php';
		generate_quickjump_cache();

		redirect('admin_forums.php', 'Forum deleted. Redirecting &hellip;');
	}
	else	// If the user hasn't confirmed the delete
	{
		$result = $db->query('SELECT forum_name FROM '.$db->prefix.'forums WHERE id='.$forum_id) or error('Unable to fetch forum info', __FILE__, __LINE__, $db->error());
		$forum_name = pun_htmlspecialchars($db->result($result));


		$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / Admin / Forums';
		require PUN_ROOT.'header.php';

		generate_admin_menu('forums');

?>
	<div class="blockform">
		<h2><span>Confirm delete forum</span></h2>
		<div class="box">
			<form method="post" action="admin_forums.php?del_forum=<?php echo $forum_id ?>">
				<div class="inform">
					<fieldset>
						<legend>Important! Read before deleting</legend>
						<div class="infldset">
							<p>Are you sure that you want to delete the forum "<?php echo $forum_name ?>"?</p>
							<p>WARNING! Deleting a forum will delete all posts (if any) in that forum!</p>
						</div>
					</fieldset>
				</div>
				<p><input type="submit" name="del_forum_comply" value="Delete" /><a href="javascript:history.go(-1)">Go back</a></p>
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

	while (list($forum_id, $disp_position) = @each($_POST['position']))
	{
		if (!@preg_match('#^\d+$#', $disp_position))
			message('Position must be a positive integer value.');

		$db->query('UPDATE '.$db->prefix.'forums SET disp_position='.$disp_position.' WHERE id='.intval($forum_id)) or error('Unable to update forum', __FILE__, __LINE__, $db->error());
	}

	// Regenerate the quickjump cache
	require_once PUN_ROOT.'include/cache.php';
	generate_quickjump_cache();

	redirect('admin_forums.php', 'Forums updated. Redirecting &hellip;');
}


else if (isset($_GET['edit_forum']))
{
	$forum_id = intval($_GET['edit_forum']);
	if ($forum_id < 1)
		message($lang_common['Bad request']);

	// Update group permissions for $forum_id
	if (isset($_POST['save']))
	{
		confirm_referrer('admin_forums.php');

		// Start with the forum details
		$forum_name = trim($_POST['forum_name']);
		$forum_desc = pun_linebreaks(trim($_POST['forum_desc']));
		$cat_id = intval($_POST['cat_id']);
		$sort_by = intval($_POST['sort_by']);
		$redirect_url = isset($_POST['redirect_url']) ? trim($_POST['redirect_url']) : null;

		if ($forum_name == '')
			message('You must enter a forum name.');

		if ($cat_id < 1)
			message($lang_common['Bad request']);

		$forum_desc = ($forum_desc != '') ? '\''.$db->escape($forum_desc).'\'' : 'NULL';
		$redirect_url = ($redirect_url != '') ? '\''.$db->escape($redirect_url).'\'' : 'NULL';

		$db->query('UPDATE '.$db->prefix.'forums SET forum_name=\''.$db->escape($forum_name).'\', forum_desc='.$forum_desc.', redirect_url='.$redirect_url.', sort_by='.$sort_by.', cat_id='.$cat_id.' WHERE id='.$forum_id) or error('Unable to update forum', __FILE__, __LINE__, $db->error());

		// Now let's deal with the permissions
		if (isset($_POST['read_forum_old']))
		{
			$result = $db->query('SELECT g_id, g_read_board, g_post_replies, g_post_topics FROM '.$db->prefix.'groups WHERE g_id!='.PUN_ADMIN) or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());
			while ($cur_group = $db->fetch_assoc($result))
			{
				$read_forum_new = ($cur_group['g_read_board'] == '1') ? isset($_POST['read_forum_new'][$cur_group['g_id']]) ? '1' : '0' : intval($_POST['read_forum_old'][$cur_group['g_id']]);
				$post_replies_new = isset($_POST['post_replies_new'][$cur_group['g_id']]) ? '1' : '0';
				$post_topics_new = isset($_POST['post_topics_new'][$cur_group['g_id']]) ? '1' : '0';

				// Check if the new settings differ from the old
				if ($read_forum_new != $_POST['read_forum_old'][$cur_group['g_id']] || $post_replies_new != $_POST['post_replies_old'][$cur_group['g_id']] || $post_topics_new != $_POST['post_topics_old'][$cur_group['g_id']])
				{
					// If the new settings are identical to the default settings for this group, delete it's row in forum_perms
					if ($read_forum_new == '1' && $post_replies_new == $cur_group['g_post_replies'] && $post_topics_new == $cur_group['g_post_topics'])
						$db->query('DELETE FROM '.$db->prefix.'forum_perms WHERE group_id='.$cur_group['g_id'].' AND forum_id='.$forum_id) or error('Unable to delete group forum permissions', __FILE__, __LINE__, $db->error());
					else
					{
						// Run an UPDATE and see if it affected a row, if not, INSERT
						$db->query('UPDATE '.$db->prefix.'forum_perms SET read_forum='.$read_forum_new.', post_replies='.$post_replies_new.', post_topics='.$post_topics_new.' WHERE group_id='.$cur_group['g_id'].' AND forum_id='.$forum_id) or error('Unable to insert group forum permissions', __FILE__, __LINE__, $db->error());
						if (!$db->affected_rows())
							$db->query('INSERT INTO '.$db->prefix.'forum_perms (group_id, forum_id, read_forum, post_replies, post_topics) VALUES('.$cur_group['g_id'].', '.$forum_id.', '.$read_forum_new.', '.$post_replies_new.', '.$post_topics_new.')') or error('Unable to insert group forum permissions', __FILE__, __LINE__, $db->error());
					}
				}
			}
		}

		// Regenerate the quickjump cache
		require_once PUN_ROOT.'include/cache.php';
		generate_quickjump_cache();

		redirect('admin_forums.php', 'Forum updated. Redirecting &hellip;');
	}
	else if (isset($_POST['revert_perms']))
	{
		confirm_referrer('admin_forums.php');

		$db->query('DELETE FROM '.$db->prefix.'forum_perms WHERE forum_id='.$forum_id) or error('Unable to delete group forum permissions', __FILE__, __LINE__, $db->error());

		// Regenerate the quickjump cache
		require_once PUN_ROOT.'include/cache.php';
		generate_quickjump_cache();

		redirect('admin_forums.php?edit_forum='.$forum_id, 'Permissions reverted to defaults. Redirecting &hellip;');
	}


	// Fetch forum info
	$result = $db->query('SELECT id, forum_name, forum_desc, redirect_url, num_topics, sort_by, cat_id FROM '.$db->prefix.'forums WHERE id='.$forum_id) or error('Unable to fetch forum info', __FILE__, __LINE__, $db->error());
	if (!$db->num_rows($result))
		message($lang_common['Bad request']);

	$cur_forum = $db->fetch_assoc($result);


	$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / Admin / Forums';
	require PUN_ROOT.'header.php';

	generate_admin_menu('forums');

?>
	<div class="blockform">
		<h2><span>Edit forum</span></h2>
		<div class="box">
			<form id="edit_forum" method="post" action="admin_forums.php?edit_forum=<?php echo $forum_id ?>">
				<p class="submittop"><input type="submit" name="save" value="Save changes" tabindex="6" /></p>
				<div class="inform">
					<fieldset>
						<legend>Edit forum details</legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row">Forum name</th>
									<td><input type="text" name="forum_name" size="35" maxlength="80" value="<?php echo pun_htmlspecialchars($cur_forum['forum_name']) ?>" tabindex="1" /></td>
								</tr>
								<tr>
									<th scope="row">Description (HTML)</th>
									<td><textarea name="forum_desc" rows="3" cols="50" tabindex="2"><?php echo pun_htmlspecialchars($cur_forum['forum_desc']) ?></textarea></td>
								</tr>
								<tr>
									<th scope="row">Category</th>
									<td>
										<select name="cat_id" tabindex="3">
<?php

	$result = $db->query('SELECT id, cat_name FROM '.$db->prefix.'categories ORDER BY disp_position') or error('Unable to fetch category list', __FILE__, __LINE__, $db->error());
	while ($cur_cat = $db->fetch_assoc($result))
	{
		$selected = ($cur_cat['id'] == $cur_forum['cat_id']) ? ' selected="selected"' : '';
		echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_cat['id'].'"'.$selected.'>'.pun_htmlspecialchars($cur_cat['cat_name']).'</option>'."\n";
	}

?>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">Sort topics by</th>
									<td>
										<select name="sort_by" tabindex="4">
											<option value="0"<?php if ($cur_forum['sort_by'] == '0') echo ' selected="selected"' ?>>Last post</option>
											<option value="1"<?php if ($cur_forum['sort_by'] == '1') echo ' selected="selected"' ?>>Topic start</option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">Redirect URL</th>
									<td><?php echo ($cur_forum['num_topics']) ? 'Only available in empty forums' : '<input type="text" name="redirect_url" size="45" maxlength="100" value="'.pun_htmlspecialchars($cur_forum['redirect_url']).'" tabindex="5" />'; ?></td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend>Edit group permissions for this forum</legend>
						<div class="infldset">
							<p>In this form, you can set the forum specific permissions for the different user groups. If you haven't made any changes to this forums group permissions, what you see below is the default based on settings in <a href="admin_groups.php">User groups</a>. Administrators always have full permissions and are thus excluded. Permission settings that differ from the default permissions for the user group are marked red. The "Read forum" permission checkbox will be disabled if the group in question lacks the "Read board" permission. For redirect forums, only the "Read forum" permission is editable.</p>
							<table id="forumperms" cellspacing="0">
							<thead>
								<tr>
									<th class="atcl">&nbsp;</th>
									<th>Read forum</th>
									<th>Post replies</th>
									<th>Post topics</th>
								</tr>
							</thead>
							<tbody>
<?php

	$result = $db->query('SELECT g.g_id, g.g_title, g.g_read_board, g.g_post_replies, g.g_post_topics, fp.read_forum, fp.post_replies, fp.post_topics FROM '.$db->prefix.'groups AS g LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (g.g_id=fp.group_id AND fp.forum_id='.$forum_id.') WHERE g.g_id!='.PUN_ADMIN.' ORDER BY g.g_id') or error('Unable to fetch group forum permission list', __FILE__, __LINE__, $db->error());

	while ($cur_perm = $db->fetch_assoc($result))
	{
		$read_forum = ($cur_perm['read_forum'] != '0') ? true : false;
		$post_replies = (($cur_perm['g_post_replies'] == '0' && $cur_perm['post_replies'] == '1') || ($cur_perm['g_post_replies'] == '1' && $cur_perm['post_replies'] != '0')) ? true : false;
		$post_topics = (($cur_perm['g_post_topics'] == '0' && $cur_perm['post_topics'] == '1') || ($cur_perm['g_post_topics'] == '1' && $cur_perm['post_topics'] != '0')) ? true : false;

		// Determine if the current sittings differ from the default or not
		$read_forum_def = ($cur_perm['read_forum'] == '0') ? false : true;
		$post_replies_def = (($post_replies && $cur_perm['g_post_replies'] == '0') || (!$post_replies && ($cur_perm['g_post_replies'] == '' || $cur_perm['g_post_replies'] == '1'))) ? false : true;
		$post_topics_def = (($post_topics && $cur_perm['g_post_topics'] == '0') || (!$post_topics && ($cur_perm['g_post_topics'] == '' || $cur_perm['g_post_topics'] == '1'))) ? false : true;

?>
								<tr>
									<th class="atcl"><?php echo pun_htmlspecialchars($cur_perm['g_title']) ?></th>
									<td<?php if (!$read_forum_def) echo ' class="nodefault"'; ?>>
										<input type="hidden" name="read_forum_old[<?php echo $cur_perm['g_id'] ?>]" value="<?php echo ($read_forum) ? '1' : '0'; ?>" />
										<input type="checkbox" name="read_forum_new[<?php echo $cur_perm['g_id'] ?>]" value="1"<?php echo ($read_forum) ? ' checked="checked"' : ''; ?><?php echo ($cur_perm['g_read_board'] == '0') ? ' disabled="disabled"' : ''; ?> />
									</td>
									<td<?php if (!$post_replies_def && $cur_forum['redirect_url'] == '') echo ' class="nodefault"'; ?>>
										<input type="hidden" name="post_replies_old[<?php echo $cur_perm['g_id'] ?>]" value="<?php echo ($post_replies) ? '1' : '0'; ?>" />
										<input type="checkbox" name="post_replies_new[<?php echo $cur_perm['g_id'] ?>]" value="1"<?php echo ($post_replies) ? ' checked="checked"' : ''; ?><?php echo ($cur_forum['redirect_url'] != '') ? ' disabled="disabled"' : ''; ?> />
									</td>
									<td<?php if (!$post_topics_def && $cur_forum['redirect_url'] == '') echo ' class="nodefault"'; ?>>
										<input type="hidden" name="post_topics_old[<?php echo $cur_perm['g_id'] ?>]" value="<?php echo ($post_topics) ? '1' : '0'; ?>" />
										<input type="checkbox" name="post_topics_new[<?php echo $cur_perm['g_id'] ?>]" value="1"<?php echo ($post_topics) ? ' checked="checked"' : ''; ?><?php echo ($cur_forum['redirect_url'] != '') ? ' disabled="disabled"' : ''; ?> />
									</td>
								</tr>
<?php

	}

?>
							</tbody>
							</table>
							<div class="fsetsubmit"><input type="submit" name="revert_perms" value="Revert to default" /></div>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="save" value="Save changes" /></p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>

<?php

	require PUN_ROOT.'footer.php';
}


$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / Admin / Forums';
require PUN_ROOT.'header.php';

generate_admin_menu('forums');

?>
	<div class="blockform">
		<h2><span>Add forum</span></h2>
		<div class="box">
			<form method="post" action="admin_forums.php?action=adddel">
				<div class="inform">
					<fieldset>
						<legend>Create a new forum</legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row">Add forum to category<div><input type="submit" name="add_forum" value=" Add " tabindex="2" /></div></th>
									<td>
										<select name="add_to_cat" tabindex="1">
<?php

	$result = $db->query('SELECT id, cat_name FROM '.$db->prefix.'categories ORDER BY disp_position') or error('Unable to fetch category list', __FILE__, __LINE__, $db->error());
	if ($db->num_rows($result) > 0)
	{
		while ($cur_cat = $db->fetch_assoc($result))
			echo "\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_cat['id'].'">'.pun_htmlspecialchars($cur_cat['cat_name']).'</option>'."\n";
	}
	else
		echo "\t\t\t\t\t\t\t\t\t".'<option value="0" disabled="disabled">No categories exist</option>'."\n";

?>
										</select>
										<span>Select the category to which you wish to add a new forum.</span>
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
$result = $db->query('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.disp_position FROM '.$db->prefix.'categories AS c INNER JOIN '.$db->prefix.'forums AS f ON c.id=f.cat_id ORDER BY c.disp_position, c.id, f.disp_position') or error('Unable to fetch category/forum list', __FILE__, __LINE__, $db->error());

if ($db->num_rows($result) > 0)
{

?>
		<h2 class="block2"><span>Edit forums</span></h2>
		<div class="box">
			<form id="edforum" method="post" action="admin_forums.php?action=edit">
				<p class="submittop"><input type="submit" name="update_positions" value="Update positions" tabindex="3" /></p>
<?php

$tabindex_count = 4;

$cur_category = 0;
while ($cur_forum = $db->fetch_assoc($result))
{
	if ($cur_forum['cid'] != $cur_category)	// A new category since last iteration?
	{
		if ($cur_category != 0)
			echo "\t\t\t\t\t\t\t".'</table>'."\n\t\t\t\t\t\t".'</div>'."\n\t\t\t\t\t".'</fieldset>'."\n\t\t\t\t".'</div>'."\n";

?>
				<div class="inform">
					<fieldset>
						<legend>Category: <?php echo pun_htmlspecialchars($cur_forum['cat_name']) ?></legend>
						<div class="infldset">
							<table cellspacing="0">
<?php

		$cur_category = $cur_forum['cid'];
	}

?>
								<tr>
									<th><a href="admin_forums.php?edit_forum=<?php echo $cur_forum['fid'] ?>">Edit</a> - <a href="admin_forums.php?del_forum=<?php echo $cur_forum['fid'] ?>">Delete</a></th>
									<td>Position&nbsp;&nbsp;<input type="text" name="position[<?php echo $cur_forum['fid'] ?>]" size="3" maxlength="3" value="<?php echo $cur_forum['disp_position'] ?>" tabindex="<?php echo $tabindex_count ?>" />
									&nbsp;&nbsp;<strong><?php echo pun_htmlspecialchars($cur_forum['forum_name']) ?></strong></td>
								</tr>
<?php

	$tabindex_count += 2;
}

?>
							</table>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="update_positions" value="Update positions" tabindex="<?php echo $tabindex_count ?>" /></p>
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
