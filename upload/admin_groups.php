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


// Add/edit a group (stage 1)
if (isset($_POST['add_group']) || isset($_GET['edit_group']))
{
	if (isset($_POST['add_group']))
	{
		$base_group = intval($_POST['base_group']);

		$result = $db->query('SELECT * FROM '.$db->prefix.'groups WHERE g_id='.$base_group) or error('Unable to fetch user group info', __FILE__, __LINE__, $db->error());
		$group = $db->fetch_assoc($result);

		$mode = 'add';
	}
	else	// We are editing a group
	{
		$group_id = intval($_GET['edit_group']);
		if ($group_id < 1)
			message($lang_common['Bad request']);

		$result = $db->query('SELECT * FROM '.$db->prefix.'groups WHERE g_id='.$group_id) or error('Unable to fetch user group info', __FILE__, __LINE__, $db->error());
		if (!$db->num_rows($result))
			message($lang_common['Bad request']);

		$group = $db->fetch_assoc($result);

		$mode = 'edit';
	}


	$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / Admin / User groups';
	$required_fields = array('req_title' => 'Group title');
	$focus_element = array('groups2', 'req_title');
	require PUN_ROOT.'header.php';

	generate_admin_menu('groups');

?>
	<div class="blockform">
		<h2><span>Group settings</span></h2>
		<div class="box">
			<form id="groups2" method="post" action="admin_groups.php" onsubmit="return process_form(this)">
				<p class="submittop"><input type="submit" name="add_edit_group" value=" Save " /></p>
				<div class="inform">
					<input type="hidden" name="mode" value="<?php echo $mode ?>" />
<?php if ($mode == 'edit'): ?>				<input type="hidden" name="group_id" value="<?php echo $group_id ?>" />
<?php endif; ?><?php if ($mode == 'add'): ?>				<input type="hidden" name="base_group" value="<?php echo $base_group ?>" />
<?php endif; ?>					<fieldset>
						<legend>Setup group options and permissions</legend>
						<div class="infldset">
							<p>Below options and permissions are the default permissions for the user group. These options apply if no forum specific permissions are in effect.</p>
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row">Group title</th>
									<td>
										<input type="text" name="req_title" size="25" maxlength="50" value="<?php if ($mode == 'edit') echo pun_htmlspecialchars($group['g_title']); ?>" tabindex="1" />
									</td>
								</tr>
								<tr>
									<th scope="row">User title</th>
									<td>
										<input type="text" name="user_title" size="25" maxlength="50" value="<?php echo pun_htmlspecialchars($group['g_user_title']) ?>" tabindex="2" />
										<span>This title will override any rank users in this group have attained. Leave blank to use default title or rank.</span>
									</td>
								</tr>
<?php if ($group['g_id'] != PUN_ADMIN): ?>								<tr>
									<th scope="row">Read board</th>
									<td>
										<input type="radio" name="read_board" value="1"<?php if ($group['g_read_board'] == '1') echo ' checked="checked"' ?> tabindex="3" />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="read_board" value="0"<?php if ($group['g_read_board'] == '0') echo ' checked="checked"' ?> tabindex="4" />&nbsp;<strong>No</strong>
										<span>Allow users in this group to view the board. This setting applies to every aspect of the board and can therefore not be overridden by forum specific settings. If this is set to "No", users in this group will only be able to login/logout and register.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Post replies</th>
									<td>
										<input type="radio" name="post_replies" value="1"<?php if ($group['g_post_replies'] == '1') echo ' checked="checked"' ?> tabindex="5" />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="post_replies" value="0"<?php if ($group['g_post_replies'] == '0') echo ' checked="checked"' ?> tabindex="6" />&nbsp;<strong>No</strong>
										<span>Allow users in this group to post replies in topics.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Post topics</th>
									<td>
										<input type="radio" name="post_topics" value="1"<?php if ($group['g_post_topics'] == '1') echo ' checked="checked"' ?> tabindex="7" />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="post_topics" value="0"<?php if ($group['g_post_topics'] == '0') echo ' checked="checked"' ?> tabindex="8" />&nbsp;<strong>No</strong>
										<span>Allow users in this group to post new topics.</span>
									</td>
								</tr>
<?php if ($group['g_id'] != PUN_GUEST): ?>								<tr>
									<th scope="row">Edit posts</th>
									<td>
										<input type="radio" name="edit_posts" value="1"<?php if ($group['g_edit_posts'] == '1') echo ' checked="checked"' ?> tabindex="11" />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="edit_posts" value="0"<?php if ($group['g_edit_posts'] == '0') echo ' checked="checked"' ?> tabindex="12" />&nbsp;<strong>No</strong>
										<span>Allow users in this group to edit their own posts.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Delete posts</th>
									<td>
										<input type="radio" name="delete_posts" value="1"<?php if ($group['g_delete_posts'] == '1') echo ' checked="checked"' ?> tabindex="13" />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="delete_posts" value="0"<?php if ($group['g_delete_posts'] == '0') echo ' checked="checked"' ?> tabindex="14" />&nbsp;<strong>No</strong>
										<span>Allow users in this group to delete their own posts.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Delete topics</th>
									<td>
										<input type="radio" name="delete_topics" value="1"<?php if ($group['g_delete_topics'] == '1') echo ' checked="checked"' ?> tabindex="15" />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="delete_topics" value="0"<?php if ($group['g_delete_topics'] == '0') echo ' checked="checked"' ?> tabindex="16" />&nbsp;<strong>No</strong>
										<span>Allow users in this group to delete their own topics (including any replies).</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Set user title</th>
									<td>
										<input type="radio" name="set_title" value="1"<?php if ($group['g_set_title'] == '1') echo ' checked="checked"' ?> tabindex="17" />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="set_title" value="0"<?php if ($group['g_set_title'] == '0') echo ' checked="checked"' ?> tabindex="18" />&nbsp;<strong>No</strong>
										<span>Allow users in this group to set their own user title.</span>
									</td>
								</tr>
<?php endif; ?>								<tr>
									<th scope="row">Use search</th>
									<td>
										<input type="radio" name="search" value="1"<?php if ($group['g_search'] == '1') echo ' checked="checked"' ?> tabindex="19" />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="search" value="0"<?php if ($group['g_search'] == '0') echo ' checked="checked"' ?> tabindex="20" />&nbsp;<strong>No</strong>
										<span>Allow users in this group to use the search feature.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Search user list</th>
									<td>
										<input type="radio" name="search_users" value="1"<?php if ($group['g_search_users'] == '1') echo ' checked="checked"' ?> tabindex="21" />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="search_users" value="0"<?php if ($group['g_search_users'] == '0') echo ' checked="checked"' ?> tabindex="22" />&nbsp;<strong>No</strong>
										<span>Allow users in this group to freetext search for users in the user list.</span>
									</td>
								</tr>
<?php if ($group['g_id'] != PUN_GUEST): ?>								<tr>
									<th scope="row">Edit subjects interval</th>
									<td>
										<input type="text" name="edit_subjects_interval" size="5" maxlength="5" value="<?php echo $group['g_edit_subjects_interval'] ?>" tabindex="23" />
										<span>Number of seconds after post time that users in this group may edit the subject of topics they've posted. Set to 0 to allow edits indefinitely.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Post flood interval</th>
									<td>
										<input type="text" name="post_flood" size="5" maxlength="4" value="<?php echo $group['g_post_flood'] ?>" tabindex="24" />
										<span>Number of seconds that users in this group have to wait between posts. Set to 0 to disable.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Search flood interval</th>
									<td>
										<input type="text" name="search_flood" size="5" maxlength="4" value="<?php echo $group['g_search_flood'] ?>" tabindex="25" />
										<span>Number of seconds that users in this group have to wait between searches. Set to 0 to disable.</span>
									</td>
								</tr>
<?php endif; ?><?php endif; ?>							</table>
<?php if ($group['g_id'] == PUN_MOD ): ?>							<p class="warntext">Please note that in order for a user in this group to have moderator abilities, he/she must be assigned to moderate one or more forums. This is done via the user administration page of the user's profile.</p>
<?php endif; ?>						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="add_edit_group" value=" Save " tabindex="26" /></p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


// Add/edit a group (stage 2)
else if (isset($_POST['add_edit_group']))
{
	confirm_referrer('admin_groups.php');

	// Is this the admin group? (special rules apply)
	$is_admin_group = (isset($_POST['group_id']) && $_POST['group_id'] == PUN_ADMIN) ? true : false;

	$title = trim($_POST['req_title']);
	$user_title = trim($_POST['user_title']);
	$read_board = isset($_POST['read_board']) ? intval($_POST['read_board']) : '1';
	$post_replies = isset($_POST['post_replies']) ? intval($_POST['post_replies']) : '1';
	$post_topics = isset($_POST['post_topics']) ? intval($_POST['post_topics']) : '1';
	$edit_posts = isset($_POST['edit_posts']) ? intval($_POST['edit_posts']) : ($is_admin_group) ? '1' : '0';
	$delete_posts = isset($_POST['delete_posts']) ? intval($_POST['delete_posts']) : ($is_admin_group) ? '1' : '0';
	$delete_topics = isset($_POST['delete_topics']) ? intval($_POST['delete_topics']) : ($is_admin_group) ? '1' : '0';
	$set_title = isset($_POST['set_title']) ? intval($_POST['set_title']) : ($is_admin_group) ? '1' : '0';
	$search = isset($_POST['search']) ? intval($_POST['search']) : '1';
	$search_users = isset($_POST['search_users']) ? intval($_POST['search_users']) : '1';
	$edit_subjects_interval = isset($_POST['edit_subjects_interval']) ? intval($_POST['edit_subjects_interval']) : '0';
	$post_flood = isset($_POST['post_flood']) ? intval($_POST['post_flood']) : '0';
	$search_flood = isset($_POST['search_flood']) ? intval($_POST['search_flood']) : '0';

	if ($title == '')
		message('You must enter a group title.');

	$user_title = ($user_title != '') ? '\''.$db->escape($user_title).'\'' : 'NULL';

	if ($_POST['mode'] == 'add')
	{
		$result = $db->query('SELECT 1 FROM '.$db->prefix.'groups WHERE g_title=\''.$db->escape($title).'\'') or error('Unable to check group title collision', __FILE__, __LINE__, $db->error());
		if ($db->num_rows($result))
			message('There is already a group with the title \''.pun_htmlspecialchars($title).'\'.');

		$db->query('INSERT INTO '.$db->prefix.'groups (g_title, g_user_title, g_read_board, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_edit_subjects_interval, g_post_flood, g_search_flood) VALUES(\''.$db->escape($title).'\', '.$user_title.', '.$read_board.', '.$post_replies.', '.$post_topics.', '.$edit_posts.', '.$delete_posts.', '.$delete_topics.', '.$set_title.', '.$search.', '.$search_users.', '.$edit_subjects_interval.', '.$post_flood.', '.$search_flood.')') or error('Unable to add group', __FILE__, __LINE__, $db->error());
		$new_group_id = $db->insert_id();

		// Now lets copy the forum specific permissions from the group which this group is based on
		$result = $db->query('SELECT forum_id, read_forum, post_replies, post_topics FROM '.$db->prefix.'forum_perms WHERE group_id='.intval($_POST['base_group'])) or error('Unable to fetch group forum permission list', __FILE__, __LINE__, $db->error());
		while ($cur_forum_perm = $db->fetch_assoc($result))
			$db->query('INSERT INTO '.$db->prefix.'forum_perms (group_id, forum_id, read_forum, post_replies, post_topics) VALUES('.$new_group_id.', '.$cur_forum_perm['forum_id'].', '.$cur_forum_perm['read_forum'].', '.$cur_forum_perm['post_replies'].', '.$cur_forum_perm['post_topics'].')') or error('Unable to insert group forum permissions', __FILE__, __LINE__, $db->error());
	}
	else
	{
		$result = $db->query('SELECT 1 FROM '.$db->prefix.'groups WHERE g_title=\''.$db->escape($title).'\' AND g_id!='.intval($_POST['group_id'])) or error('Unable to check group title collision', __FILE__, __LINE__, $db->error());
		if ($db->num_rows($result))
			message('There is already a group with the title \''.pun_htmlspecialchars($title).'\'.');

		$db->query('UPDATE '.$db->prefix.'groups SET g_title=\''.$db->escape($title).'\', g_user_title='.$user_title.', g_read_board='.$read_board.', g_post_replies='.$post_replies.', g_post_topics='.$post_topics.', g_edit_posts='.$edit_posts.', g_delete_posts='.$delete_posts.', g_delete_topics='.$delete_topics.', g_set_title='.$set_title.', g_search='.$search.', g_search_users='.$search_users.', g_edit_subjects_interval='.$edit_subjects_interval.', g_post_flood='.$post_flood.', g_search_flood='.$search_flood.' WHERE g_id='.intval($_POST['group_id'])) or error('Unable to update group', __FILE__, __LINE__, $db->error());
	}

	// Regenerate the quickjump cache
	require_once PUN_ROOT.'include/cache.php';
	generate_quickjump_cache();

	redirect('admin_groups.php', 'Group '.(($_POST['mode'] == 'edit') ? 'edited' : 'added').'. Redirecting &hellip;');
}


// Set default group
else if (isset($_POST['set_default_group']))
{
	confirm_referrer('admin_groups.php');

	$group_id = intval($_POST['default_group']);
	if ($group_id < 4)
		message($lang_common['Bad request']);

	$db->query('UPDATE '.$db->prefix.'config SET conf_value='.$group_id.' WHERE conf_name=\'o_default_user_group\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());

	// Regenerate the config cache
	require_once PUN_ROOT.'include/cache.php';
	generate_config_cache();

	redirect('admin_groups.php', 'Default group set. Redirecting &hellip;');
}


// Remove a group
else if (isset($_GET['del_group']))
{
	confirm_referrer('admin_groups.php');

	$group_id = intval($_GET['del_group']);
	if ($group_id < 5)
		message($lang_common['Bad request']);

	// Make sure we don't remove the default group
	if ($group_id == $pun_config['o_default_user_group'])
		message('The default group cannot be removed. In order to delete this group, you must first setup a different group as the default.');


	// Check if this group has any members
	$result = $db->query('SELECT g.g_title, COUNT(u.id) FROM '.$db->prefix.'groups AS g INNER JOIN '.$db->prefix.'users AS u ON g.g_id=u.group_id WHERE g.g_id='.$group_id.' GROUP BY g.g_id, g_title') or error('Unable to fetch group info', __FILE__, __LINE__, $db->error());

	// If the group doesn't have any members or if we've already selected a group to move the members to
	if (!$db->num_rows($result) || isset($_POST['del_group']))
	{
		if (isset($_POST['del_group']))
		{
			$move_to_group = intval($_POST['move_to_group']);
			$db->query('UPDATE '.$db->prefix.'users SET group_id='.$move_to_group.' WHERE group_id='.$group_id) or error('Unable to move users into group', __FILE__, __LINE__, $db->error());
		}

		// Delete the group and any forum specific permissions
		$db->query('DELETE FROM '.$db->prefix.'groups WHERE g_id='.$group_id) or error('Unable to delete group', __FILE__, __LINE__, $db->error());
		$db->query('DELETE FROM '.$db->prefix.'forum_perms WHERE group_id='.$group_id) or error('Unable to delete group forum permissions', __FILE__, __LINE__, $db->error());

		// Regenerate the quickjump cache
		require_once PUN_ROOT.'include/cache.php';
		generate_quickjump_cache();

		redirect('admin_groups.php', 'Group removed. Redirecting &hellip;');
	}


	list($group_title, $group_members) = $db->fetch_row($result);

	$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / Admin / User groups';
	require PUN_ROOT.'header.php';

	generate_admin_menu('groups');

?>
	<div class="blockform">
		<h2><span>Remove group</span></h2>
		<div class="box">
			<form id="groups" method="post" action="admin_groups.php?del_group=<?php echo $group_id ?>">
				<div class="inform">
					<fieldset>
						<legend>Move users currently in group</legend>
						<div class="infldset">
							<p>The group "<?php echo pun_htmlspecialchars($group_title) ?>" currently has <?php echo $group_members ?> members. Please select a group to which these members will be assigned upon removal.</p>
							<label>Move users to
							<select name="move_to_group">
<?php

	$result = $db->query('SELECT g_id, g_title FROM '.$db->prefix.'groups WHERE g_id!='.PUN_GUEST.' AND g_id!='.$group_id.' ORDER BY g_title') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());

	while ($cur_group = $db->fetch_assoc($result))
	{
		if ($cur_group['g_id'] == PUN_MEMBER)	// Pre-select the pre-defined Members group
			echo "\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
		else
			echo "\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
	}

?>
							</select>
							</br></label>
						</div>
					</fieldset>
				</div>
				<p><input type="submit" name="del_group" value="Delete group" /></p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / Admin / User groups';
require PUN_ROOT.'header.php';

generate_admin_menu('groups');

?>
	<div class="blockform">
		<h2><span>Add/setup groups</span></h2>
		<div class="box">
			<form id="groups" method="post" action="admin_groups.php?action=foo">
				<div class="inform">
					<fieldset>
						<legend>Add new group</legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row">Base new group on<div><input type="submit" name="add_group" value=" Add " tabindex="2" /></div></th>
									<td>
										<select id="base_group" name="base_group" tabindex="1">
<?php

$result = $db->query('SELECT g_id, g_title FROM '.$db->prefix.'groups WHERE g_id>'.PUN_GUEST.' ORDER BY g_title') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());

while ($cur_group = $db->fetch_assoc($result))
{
	if ($cur_group['g_id'] == $pun_config['o_default_user_group'])
		echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
	else
		echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
}

?>
										</select>
										<span>Select a user group from which the new group will inherit it's permission settings. The next page will let you fine-tune said settings.</span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend>Set default group</legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row">Default group<div><input type="submit" name="set_default_group" value=" Save " tabindex="4" /></div></th>
									<td>
										<select id="default_group" name="default_group" tabindex="3">
<?php

$result = $db->query('SELECT g_id, g_title FROM '.$db->prefix.'groups WHERE g_id>'.PUN_GUEST.' ORDER BY g_title') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());

while ($cur_group = $db->fetch_assoc($result))
{
	if ($cur_group['g_id'] == $pun_config['o_default_user_group'])
		echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
	else
		echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
}

?>
										</select>
										<span>This is the default user group, e.g. the group users are placed in when they register. For security reasons, users can't be placed in either the moderator or administrator user groups by default.</span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
			</form>
		</div>

		<h2 class="block2"><span>Existing groups</span></h2>
		<div class="box">
			<div class="fakeform">
				<div class="inform">
					<fieldset>
						<legend>Edit/remove groups</legend>
						<div class="infldset">
							<p>The pre-defined groups Guests, Administrators, Moderators and Members cannot be removed. They can however be edited. Please note though, that in some groups, some options are unavailable (e.g. the <em>edit posts</em> permission for guests). Administrators always have full permissions.</p>
							<table cellspacing="0">
<?php

$result = $db->query('SELECT g_id, g_title FROM '.$db->prefix.'groups ORDER BY g_id') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());

while ($cur_group = $db->fetch_assoc($result))
	echo "\t\t\t\t\t\t\t\t".'<tr><th scope="row"><a href="admin_groups.php?edit_group='.$cur_group['g_id'].'">Edit</a>'.(($cur_group['g_id'] > PUN_MEMBER) ? ' - <a href="admin_groups.php?del_group='.$cur_group['g_id'].'">Remove</a>' : '').'</th><td>'.pun_htmlspecialchars($cur_group['g_title']).'</td></tr>'."\n";

?>
							</table>
						</div>
					</fieldset>
				</div>
			</div>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

require PUN_ROOT.'footer.php';
