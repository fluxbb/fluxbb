<?php

/**
 * Copyright (C) 2008-2012 FluxBB
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

// Load the admin_groups.php language file
$lang->load('admin_groups');

// Add/edit a group (stage 1)
if (isset($_POST['add_group']) || isset($_GET['edit_group']))
{
	if (isset($_POST['add_group']))
	{
		$base_group = intval($_POST['base_group']);
		if ($base_group < 1)
			message($lang->t('Bad request'));

		$query = $db->select(array('groups' => 'g.*'), 'groups AS g');
		$query->where = 'g.g_id = :group_id';

		$params = array(':group_id' => $base_group);

		$result = $query->run($params);
		if (empty($result))
			message($lang->t('Bad request'));

		$group = $result[0];
		unset ($result, $query, $params);

		$mode = 'add';
	}
	else // We are editing a group
	{
		$group_id = intval($_GET['edit_group']);
		if ($group_id < 1)
			message($lang->t('Bad request'));

		$query = $db->select(array('groups' => 'g.*'), 'groups AS g');
		$query->where = 'g.g_id = :group_id';

		$params = array(':group_id' => $group_id);

		$result = $query->run($params);
		if (empty($result))
			message($lang->t('Bad request'));

		$group = $result[0];
		unset ($result, $query, $params);

		$mode = 'edit';
	}


	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Admin'), $lang->t('User groups'));
	$required_fields = array('req_title' => $lang->t('Group title label'));
	$focus_element = array('groups2', 'req_title');
	define('PUN_ACTIVE_PAGE', 'admin');
	require PUN_ROOT.'header.php';

	generate_admin_menu('groups');

?>
	<div class="blockform">
		<h2><span><?php echo $lang->t('Group settings head') ?></span></h2>
		<div class="box">
			<form id="groups2" method="post" action="admin_groups.php" onsubmit="return process_form(this)">
				<p class="submittop"><input type="submit" name="add_edit_group" value="<?php echo $lang->t('Save') ?>" /></p>
				<div class="inform">
					<input type="hidden" name="mode" value="<?php echo $mode ?>" />
<?php if ($mode == 'edit'): ?>					<input type="hidden" name="group_id" value="<?php echo $group_id ?>" />
<?php endif; ?><?php if ($mode == 'add'): ?>					<input type="hidden" name="base_group" value="<?php echo $base_group ?>" />
<?php endif; ?>					<fieldset>
						<legend><?php echo $lang->t('Group settings subhead') ?></legend>
						<div class="infldset">
							<p><?php echo $lang->t('Group settings info') ?></p>
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang->t('Group title label') ?></th>
									<td>
										<input type="text" name="req_title" size="25" maxlength="50" value="<?php if ($mode == 'edit') echo pun_htmlspecialchars($group['g_title']); ?>" tabindex="1" />
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('User title label') ?></th>
									<td>
										<input type="text" name="user_title" size="25" maxlength="50" value="<?php echo pun_htmlspecialchars($group['g_user_title']) ?>" tabindex="2" />
										<span><?php echo $lang->t('User title help') ?></span>
									</td>
								</tr>
<?php if ($group['g_id'] != PUN_ADMIN): if ($group['g_id'] != PUN_GUEST): if ($mode != 'edit' || $pun_config['o_default_user_group'] != $group['g_id']): ?>								<tr>
									<th scope="row"> <?php echo $lang->t('Mod privileges label') ?></th>
									<td>
										<input type="radio" name="moderator" value="1"<?php if ($group['g_moderator'] == '1') echo ' checked="checked"' ?> tabindex="3" />&#160;<strong><?php echo $lang->t('Yes') ?></strong>&#160;&#160;&#160;<input type="radio" name="moderator" value="0"<?php if ($group['g_moderator'] == '0') echo ' checked="checked"' ?> tabindex="4" />&#160;<strong><?php echo $lang->t('No') ?></strong>
										<span><?php echo $lang->t('Mod privileges help') ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Edit profile label') ?></th>
									<td>
										<input type="radio" name="mod_edit_users" value="1"<?php if ($group['g_mod_edit_users'] == '1') echo ' checked="checked"' ?> tabindex="5" />&#160;<strong><?php echo $lang->t('Yes') ?></strong>&#160;&#160;&#160;<input type="radio" name="mod_edit_users" value="0"<?php if ($group['g_mod_edit_users'] == '0') echo ' checked="checked"' ?> tabindex="6" />&#160;<strong><?php echo $lang->t('No') ?></strong>
										<span><?php echo $lang->t('Edit profile help') ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Rename users label') ?></th>
									<td>
										<input type="radio" name="mod_rename_users" value="1"<?php if ($group['g_mod_rename_users'] == '1') echo ' checked="checked"' ?> tabindex="7" />&#160;<strong><?php echo $lang->t('Yes') ?></strong>&#160;&#160;&#160;<input type="radio" name="mod_rename_users" value="0"<?php if ($group['g_mod_rename_users'] == '0') echo ' checked="checked"' ?> tabindex="8" />&#160;<strong><?php echo $lang->t('No') ?></strong>
										<span><?php echo $lang->t('Rename users help') ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Change passwords label') ?></th>
									<td>
										<input type="radio" name="mod_change_passwords" value="1"<?php if ($group['g_mod_change_passwords'] == '1') echo ' checked="checked"' ?> tabindex="9" />&#160;<strong><?php echo $lang->t('Yes') ?></strong>&#160;&#160;&#160;<input type="radio" name="mod_change_passwords" value="0"<?php if ($group['g_mod_change_passwords'] == '0') echo ' checked="checked"' ?> tabindex="10" />&#160;<strong><?php echo $lang->t('No') ?></strong>
										<span><?php echo $lang->t('Change passwords help') ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Ban users label') ?></th>
									<td>
										<input type="radio" name="mod_ban_users" value="1"<?php if ($group['g_mod_ban_users'] == '1') echo ' checked="checked"' ?> tabindex="11" />&#160;<strong><?php echo $lang->t('Yes') ?></strong>&#160;&#160;&#160;<input type="radio" name="mod_ban_users" value="0"<?php if ($group['g_mod_ban_users'] == '0') echo ' checked="checked"' ?> tabindex="12" />&#160;<strong><?php echo $lang->t('No') ?></strong>
										<span><?php echo $lang->t('Ban users help') ?></span>
									</td>
								</tr>
<?php endif; endif; ?>								<tr>
									<th scope="row"><?php echo $lang->t('Read board label') ?></th>
									<td>
										<input type="radio" name="read_board" value="1"<?php if ($group['g_read_board'] == '1') echo ' checked="checked"' ?> tabindex="13" />&#160;<strong><?php echo $lang->t('Yes') ?></strong>&#160;&#160;&#160;<input type="radio" name="read_board" value="0"<?php if ($group['g_read_board'] == '0') echo ' checked="checked"' ?> tabindex="14" />&#160;<strong><?php echo $lang->t('No') ?></strong>
										<span><?php echo $lang->t('Read board help') ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('View user info label') ?></th>
									<td>
										<input type="radio" name="view_users" value="1"<?php if ($group['g_view_users'] == '1') echo ' checked="checked"' ?> tabindex="15" />&#160;<strong><?php echo $lang->t('Yes') ?></strong>&#160;&#160;&#160;<input type="radio" name="view_users" value="0"<?php if ($group['g_view_users'] == '0') echo ' checked="checked"' ?> tabindex="16" />&#160;<strong><?php echo $lang->t('No') ?></strong>
										<span><?php echo $lang->t('View user info help') ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Post replies label') ?></th>
									<td>
										<input type="radio" name="post_replies" value="1"<?php if ($group['g_post_replies'] == '1') echo ' checked="checked"' ?> tabindex="17" />&#160;<strong><?php echo $lang->t('Yes') ?></strong>&#160;&#160;&#160;<input type="radio" name="post_replies" value="0"<?php if ($group['g_post_replies'] == '0') echo ' checked="checked"' ?> tabindex="18" />&#160;<strong><?php echo $lang->t('No') ?></strong>
										<span><?php echo $lang->t('Post replies help') ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Post topics label') ?></th>
									<td>
										<input type="radio" name="post_topics" value="1"<?php if ($group['g_post_topics'] == '1') echo ' checked="checked"' ?> tabindex="19" />&#160;<strong><?php echo $lang->t('Yes') ?></strong>&#160;&#160;&#160;<input type="radio" name="post_topics" value="0"<?php if ($group['g_post_topics'] == '0') echo ' checked="checked"' ?> tabindex="20" />&#160;<strong><?php echo $lang->t('No') ?></strong>
										<span><?php echo $lang->t('Post topics help') ?></span>
									</td>
								</tr>
<?php if ($group['g_id'] != PUN_GUEST): ?>								<tr>
									<th scope="row"><?php echo $lang->t('Edit posts label') ?></th>
									<td>
										<input type="radio" name="edit_posts" value="1"<?php if ($group['g_edit_posts'] == '1') echo ' checked="checked"' ?> tabindex="21" />&#160;<strong><?php echo $lang->t('Yes') ?></strong>&#160;&#160;&#160;<input type="radio" name="edit_posts" value="0"<?php if ($group['g_edit_posts'] == '0') echo ' checked="checked"' ?> tabindex="22" />&#160;<strong><?php echo $lang->t('No') ?></strong>
										<span><?php echo $lang->t('Edit posts help') ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Delete posts label') ?></th>
									<td>
										<input type="radio" name="delete_posts" value="1"<?php if ($group['g_delete_posts'] == '1') echo ' checked="checked"' ?> tabindex="23" />&#160;<strong><?php echo $lang->t('Yes') ?></strong>&#160;&#160;&#160;<input type="radio" name="delete_posts" value="0"<?php if ($group['g_delete_posts'] == '0') echo ' checked="checked"' ?> tabindex="24" />&#160;<strong><?php echo $lang->t('No') ?></strong>
										<span><?php echo $lang->t('Delete posts help') ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Delete topics label') ?></th>
									<td>
										<input type="radio" name="delete_topics" value="1"<?php if ($group['g_delete_topics'] == '1') echo ' checked="checked"' ?> tabindex="25" />&#160;<strong><?php echo $lang->t('Yes') ?></strong>&#160;&#160;&#160;<input type="radio" name="delete_topics" value="0"<?php if ($group['g_delete_topics'] == '0') echo ' checked="checked"' ?> tabindex="26" />&#160;<strong><?php echo $lang->t('No') ?></strong>
										<span><?php echo $lang->t('Delete topics help') ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Set own title label') ?></th>
									<td>
										<input type="radio" name="set_title" value="1"<?php if ($group['g_set_title'] == '1') echo ' checked="checked"' ?> tabindex="27" />&#160;<strong><?php echo $lang->t('Yes') ?></strong>&#160;&#160;&#160;<input type="radio" name="set_title" value="0"<?php if ($group['g_set_title'] == '0') echo ' checked="checked"' ?> tabindex="28" />&#160;<strong><?php echo $lang->t('No') ?></strong>
										<span><?php echo $lang->t('Set own title help') ?></span>
									</td>
								</tr>
<?php endif; ?>								<tr>
									<th scope="row"><?php echo $lang->t('User search label') ?></th>
									<td>
										<input type="radio" name="search" value="1"<?php if ($group['g_search'] == '1') echo ' checked="checked"' ?> tabindex="29" />&#160;<strong><?php echo $lang->t('Yes') ?></strong>&#160;&#160;&#160;<input type="radio" name="search" value="0"<?php if ($group['g_search'] == '0') echo ' checked="checked"' ?> tabindex="30" />&#160;<strong><?php echo $lang->t('No') ?></strong>
										<span><?php echo $lang->t('User search help') ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('User list search label') ?></th>
									<td>
										<input type="radio" name="search_users" value="1"<?php if ($group['g_search_users'] == '1') echo ' checked="checked"' ?> tabindex="31" />&#160;<strong><?php echo $lang->t('Yes') ?></strong>&#160;&#160;&#160;<input type="radio" name="search_users" value="0"<?php if ($group['g_search_users'] == '0') echo ' checked="checked"' ?> tabindex="32" />&#160;<strong><?php echo $lang->t('No') ?></strong>
										<span><?php echo $lang->t('User list search help') ?></span>
									</td>
								</tr>
<?php if ($group['g_id'] != PUN_GUEST): ?>								<tr>
									<th scope="row"><?php echo $lang->t('Send emails label') ?></th>
									<td>
										<input type="radio" name="send_email" value="1"<?php if ($group['g_send_email'] == '1') echo ' checked="checked"' ?> tabindex="33" />&#160;<strong><?php echo $lang->t('Yes') ?></strong>&#160;&#160;&#160;<input type="radio" name="send_email" value="0"<?php if ($group['g_send_email'] == '0') echo ' checked="checked"' ?> tabindex="34" />&#160;<strong><?php echo $lang->t('No') ?></strong>
										<span><?php echo $lang->t('Send emails help') ?></span>
									</td>
								</tr>
<?php endif; ?>								<tr>
									<th scope="row"><?php echo $lang->t('Post flood label') ?></th>
									<td>
										<input type="text" name="post_flood" size="5" maxlength="4" value="<?php echo $group['g_post_flood'] ?>" tabindex="35" />
										<span><?php echo $lang->t('Post flood help') ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Search flood label') ?></th>
									<td>
										<input type="text" name="search_flood" size="5" maxlength="4" value="<?php echo $group['g_search_flood'] ?>" tabindex="36" />
										<span><?php echo $lang->t('Search flood help') ?></span>
									</td>
								</tr>
<?php if ($group['g_id'] != PUN_GUEST): ?>								<tr>
									<th scope="row"><?php echo $lang->t('Email flood label') ?></th>
									<td>
										<input type="text" name="email_flood" size="5" maxlength="4" value="<?php echo $group['g_email_flood'] ?>" tabindex="37" />
										<span><?php echo $lang->t('Email flood help') ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Report flood label') ?></th>
									<td>
										<input type="text" name="report_flood" size="5" maxlength="4" value="<?php echo $group['g_report_flood'] ?>" tabindex="38" />
										<span><?php echo $lang->t('Report flood help') ?></span>
									</td>
								</tr>
<?php endif; endif; ?>							</table>
<?php if ($group['g_moderator'] == '1' ): ?>							<p class="warntext"><?php echo $lang->t('Moderator info') ?></p>
<?php endif; ?>						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="add_edit_group" value="<?php echo $lang->t('Save') ?>" tabindex="39" /></p>
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

	$title = pun_trim($_POST['req_title']);
	$user_title = pun_trim($_POST['user_title']);
	$moderator = isset($_POST['moderator']) && $_POST['moderator'] == '1' ? '1' : '0';
	$mod_edit_users = $moderator == '1' && isset($_POST['mod_edit_users']) && $_POST['mod_edit_users'] == '1' ? '1' : '0';
	$mod_rename_users = $moderator == '1' && isset($_POST['mod_rename_users']) && $_POST['mod_rename_users'] == '1' ? '1' : '0';
	$mod_change_passwords = $moderator == '1' && isset($_POST['mod_change_passwords']) && $_POST['mod_change_passwords'] == '1' ? '1' : '0';
	$mod_ban_users = $moderator == '1' && isset($_POST['mod_ban_users']) && $_POST['mod_ban_users'] == '1' ? '1' : '0';
	$read_board = isset($_POST['read_board']) ? intval($_POST['read_board']) : '1';
	$view_users = (isset($_POST['view_users']) && $_POST['view_users'] == '1') || $is_admin_group ? '1' : '0';
	$post_replies = isset($_POST['post_replies']) ? intval($_POST['post_replies']) : '1';
	$post_topics = isset($_POST['post_topics']) ? intval($_POST['post_topics']) : '1';
	$edit_posts = isset($_POST['edit_posts']) ? intval($_POST['edit_posts']) : ($is_admin_group) ? '1' : '0';
	$delete_posts = isset($_POST['delete_posts']) ? intval($_POST['delete_posts']) : ($is_admin_group) ? '1' : '0';
	$delete_topics = isset($_POST['delete_topics']) ? intval($_POST['delete_topics']) : ($is_admin_group) ? '1' : '0';
	$set_title = isset($_POST['set_title']) ? intval($_POST['set_title']) : ($is_admin_group) ? '1' : '0';
	$search = isset($_POST['search']) ? intval($_POST['search']) : '1';
	$search_users = isset($_POST['search_users']) ? intval($_POST['search_users']) : '1';
	$send_email = (isset($_POST['send_email']) && $_POST['send_email'] == '1') || $is_admin_group ? '1' : '0';
	$post_flood = isset($_POST['post_flood']) ? intval($_POST['post_flood']) : '0';
	$search_flood = isset($_POST['search_flood']) ? intval($_POST['search_flood']) : '0';
	$email_flood = isset($_POST['email_flood']) ? intval($_POST['email_flood']) : '0';
	$base_group = isset($_POST['base_group']) ? intval($_POST['base_group']) : $pun_config['o_default_user_group'];
	$report_flood = isset($_POST['report_flood']) ? intval($_POST['report_flood']) : '0';

	if ($title == '')
		message($lang->t('Must enter title message'));

	$query_fields = array('g_title' => ':title', 'g_user_title' => ':user_title', 'g_moderator' => ':moderator', 'g_mod_edit_users' => ':mod_edit_users', 'g_mod_rename_users' => ':mod_rename_users', 'g_mod_change_passwords' => ':mod_change_passwords', 'g_mod_ban_users' => ':mod_ban_users', 'g_read_board' => ':read_board', 'g_view_users' => ':view_users', 'g_post_replies' => ':post_replies', 'g_post_topics' => ':post_topics', 'g_edit_posts' => ':edit_posts', 'g_delete_posts' => ':delete_posts', 'g_delete_topics' => ':delete_topics', 'g_set_title' => ':set_title', 'g_search' => ':search', 'g_search_users' => ':search_users', 'g_send_email' => ':send_email', 'g_post_flood' => ':post_flood', 'g_search_flood' => ':search_flood', 'g_email_flood' => ':email_flood', 'g_report_flood' => ':report_flood');
	$query_params = array(':title' => empty($title) ? null : $title, ':user_title' => $user_title, ':moderator' => $moderator, ':mod_edit_users' => $mod_edit_users, ':mod_rename_users' => $mod_rename_users, ':mod_change_passwords' => $mod_change_passwords, ':mod_ban_users' => $mod_ban_users, ':read_board' => $read_board, ':view_users' => $view_users, ':post_replies' => $post_replies, ':post_topics' => $post_topics, ':edit_posts' => $edit_posts, ':delete_posts' => $delete_posts, ':delete_topics' => $delete_topics, ':set_title' => $set_title, ':search' => $search, ':search_users' => $search_users, ':send_email' => $send_email, ':post_flood' => $post_flood, ':search_flood' => $search_flood, ':email_flood' => $email_flood, ':report_flood' => $report_flood);

	if ($_POST['mode'] == 'add')
	{
		$query = $db->select(array('one' => '1'), 'groups AS g');
		$query->where = 'g.g_title = :group_title';

		$params = array(':group_title' => $title);

		$result = $query->run($params);
		if (!empty($result))
			message($lang->t('Title already exists message', pun_htmlspecialchars($title)));

		unset ($result, $query, $params);

		$query = $db->insert($query_fields, 'groups');
		$params = $query_params;

		$query->run($params);
		$new_group_id = $db->insertId();
		unset ($query, $params);

		// Now lets copy the forum specific permissions from the group which this group is based on
		$query = $db->select(array('forum_id' => 'fp.forum_id', 'read_forum' => 'fp.read_forum', 'post_replies' => 'fp.post_replies', 'post_topics' => 'fp.post_topics'), 'forum_perms AS fp');
		$query->where = 'fp.group_id = :group_id';

		$params = array(':group_id' => $base_group);

		$result = $query->run($params);
		unset ($query, $params);

		$insert_query = $db->insert(array('group_id' => ':group_id', 'forum_id' => ':forum_id', 'read_forum' => ':read_forum', 'post_replies' => ':post_replies', 'post_topics' => ':post_topics'), 'forum_perms');

		foreach ($result as $cur_forum_perm)
		{
			$params = array(':group_id' => $new_group_id, ':forum_id' => $cur_forum_perm['forum_id'], ':read_forum' => $cur_forum_perm['read_forum'], ':post_replies' => $cur_forum_perm['post_replies'], ':post_topics' => $cur_forum_perm['post_topics']);

			$insert_query->run($params);
			unset ($params);
		}

		unset ($result, $insert_query);
	}
	else
	{
		$query = $db->select(array('one' => '1'), 'groups AS g');
		$query->where = 'g.g_title = :title AND g.g_id != :group_id';

		$params = array(':title' => $title, ':group_id' => intval($_POST['group_id']));

		$result = $query->run($params);
		if (!empty($result))
			message($lang->t('Title already exists message', pun_htmlspecialchars($title)));

		unset ($result, $query, $params);

		$query = $db->update($query_fields, 'groups');
		$query->where = 'g_id = :group_id';

		$params = $query_params;
		$params[':group_id'] = intval($_POST['group_id']);

		$query->run($params);
		unset ($query, $params);
	}

	unset ($query_fields, $query_params);

	// Regenerate the quick jump cache
	$cache->delete('quickjump');

	if ($_POST['mode'] == 'edit')
		redirect('admin_groups.php', $lang->t('Group edited redirect'));
	else
		redirect('admin_groups.php', $lang->t('Group added redirect'));
}


// Set default group
else if (isset($_POST['set_default_group']))
{
	confirm_referrer('admin_groups.php');

	$group_id = intval($_POST['default_group']);

	// Make sure it's not the admin or guest groups
	if ($group_id == PUN_ADMIN || $group_id == PUN_GUEST)
		message($lang->t('Bad request'));

	// Make sure it's not a moderator group
	$query = $db->select(array('one' => '1'), 'groups AS g');
	$query->where = 'g.g_id = :group_id AND g.g_moderator = 0';

	$params = array(':group_id' => $group_id);

	$result = $query->run($params);
	if (empty($result))
		message($lang->t('Bad request'));

	unset ($result, $query, $params);

	$query = $db->update(array('conf_value' => ':group_id'), 'config');
	$query->where = 'conf_name = \'o_default_user_group\'';

	$params = array(':group_id' => $group_id);

	$query->run($params);
	unset ($query, $params);

	// Regenerate the config cache
	$cache->delete('config');

	redirect('admin_groups.php', $lang->t('Default group redirect'));
}


// Remove a group
else if (isset($_GET['del_group']))
{
	confirm_referrer('admin_groups.php');

	$group_id = isset($_POST['group_to_delete']) ? intval($_POST['group_to_delete']) : intval($_GET['del_group']);
	if ($group_id < 5)
		message($lang->t('Bad request'));

	// Make sure we don't remove the default group
	if ($group_id == $pun_config['o_default_user_group'])
		message($lang->t('Cannot remove default message'));

	// Check if this group has any members
	$query = $db->select(array('g_title' => 'g.g_title', 'num_members' => 'COUNT(u.id) AS num_members'), 'groups AS g');

	$query->innerJoin('u', 'users AS u', 'g.g_id = u.group_id');

	$query->where = 'g.g_id = :group_id';
	$query->group = array('g_id' => 'g.g_id', 'g_title' => 'g.g_title');

	$params = array(':group_id' => $group_id);

	$result = $query->run($params);
	unset ($query, $params);

	// If the group doesn't have any members or if we've already selected a group to move the members to
	if (empty($result) || isset($_POST['del_group']))
	{
		if (isset($_POST['del_group_comply']) || isset($_POST['del_group']))
		{
			if (isset($_POST['del_group']))
			{
				$move_to_group = intval($_POST['move_to_group']);

				$query = $db->update(array('group_id' => ':new_group_id'), 'users');
				$query->where = 'group_id = :old_group_id';

				$params = array(':new_group_id' => $move_to_group, ':old_group_id' => $group_id);

				$query->run($params);
				unset ($query, $params);
			}

			// Delete the group
			$query = $db->delete('groups');
			$query->where = 'g_id = :group_id';

			$params = array(':group_id' => $group_id);

			$query->run($params);
			unset ($query, $params);

			// Delete any forum specific permissions
			$query = $db->delete('forum_perms');
			$query->where = 'group_id = :group_id';

			$params = array(':group_id' => $group_id);

			$query->run($params);
			unset ($query, $params);

			redirect('admin_groups.php', $lang->t('Group removed redirect'));
		}
		else
		{
			$query = $db->select(array('g_title' => 'g.g_title'), 'groups AS g');
			$query->where = 'g.g_id = :group_id';

			$params = array(':group_id' => $group_id);

			$result = $query->run($params);
			$group_title = $result[0]['g_title'];
			unset ($result, $query, $params);

			$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Admin'), $lang->t('User groups'));
			define('PUN_ACTIVE_PAGE', 'admin');
			require PUN_ROOT.'header.php';

			generate_admin_menu('groups');

?>
	<div class="blockform">
		<h2><span><?php echo $lang->t('Group delete head') ?></span></h2>
		<div class="box">
			<form method="post" action="admin_groups.php?del_group=<?php echo $group_id ?>">
				<div class="inform">
				<input type="hidden" name="group_to_delete" value="<?php echo $group_id ?>" />
					<fieldset>
						<legend><?php echo $lang->t('Confirm delete group subhead') ?></legend>
						<div class="infldset">
							<p><?php echo $lang->t('Confirm delete group info', pun_htmlspecialchars($group_title)) ?></p>
							<p class="warntext"><?php echo $lang->t('Confirm delete warn') ?></p>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="del_group_comply" value="<?php echo $lang->t('Delete') ?>" tabindex="1" /><a href="javascript:history.go(-1)" tabindex="2"><?php echo $lang->t('Go back') ?></a></p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

			require PUN_ROOT.'footer.php';
		}
	}

	$group_title = $result[0]['g_title'];
	$group_members = $result[0]['num_members'];

	unset ($result);

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Admin'), $lang->t('User groups'));
	define('PUN_ACTIVE_PAGE', 'admin');
	require PUN_ROOT.'header.php';

	generate_admin_menu('groups');

?>
	<div class="blockform">
		<h2><span><?php echo $lang->t('Delete group head') ?></span></h2>
		<div class="box">
			<form id="groups" method="post" action="admin_groups.php?del_group=<?php echo $group_id ?>">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Move users subhead') ?></legend>
						<div class="infldset">
							<p><?php echo $lang->t('Move users info', pun_htmlspecialchars($group_title), forum_number_format($group_members)) ?></p>
							<label><?php echo $lang->t('Move users label') ?>
							<select name="move_to_group">
<?php

	$query = $db->select(array('g_id' => 'g.g_id', 'g_title' => 'g.g_title'), 'groups AS g');
	$query->where = 'g.g_id != :group_guest AND g.g_id != :group_id';
	$query->order = array('g_title' => 'g.g_title ASC');

	$params = array(':group_guest' => PUN_GUEST, ':group_id' => $group_id);

	$result = $query->run($params);
	foreach ($result as $cur_group)
	{
		if ($cur_group['g_id'] == PUN_MEMBER) // Pre-select the pre-defined Members group
			echo "\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
		else
			echo "\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
	}

	unset ($result, $query, $params);

?>
							</select>
							<br /></label>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="del_group" value="<?php echo $lang->t('Delete group') ?>" /><a href="javascript:history.go(-1)"><?php echo $lang->t('Go back') ?></a></p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Admin'), $lang->t('User groups'));
define('PUN_ACTIVE_PAGE', 'admin');
require PUN_ROOT.'header.php';

generate_admin_menu('groups');

?>
	<div class="blockform">
		<h2><span><?php echo $lang->t('Add groups head') ?></span></h2>
		<div class="box">
			<form id="groups" method="post" action="admin_groups.php">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Add group subhead') ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang->t('New group label') ?><div><input type="submit" name="add_group" value="<?php echo $lang->t('Add') ?>" tabindex="2" /></div></th>
									<td>
										<select id="base_group" name="base_group" tabindex="1">
<?php

$query = $db->select(array('g_id' => 'g.g_id', 'g_title' => 'g.g_title'), 'groups AS g');
$query->where = 'g.g_id != :group_admin AND g.g_id != :group_guest';
$query->order = array('g_title' => 'g.g_title ASC');

$params = array(':group_guest' => PUN_GUEST, ':group_admin' => PUN_ADMIN);

$result = $query->run($params);
foreach ($result as $cur_group)
{
	if ($cur_group['g_id'] == $pun_config['o_default_user_group'])
		echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
	else
		echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
}

unset ($result, $query, $params);

?>
										</select>
										<span><?php echo $lang->t('New group help') ?></span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Default group subhead') ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang->t('Default group label') ?><div><input type="submit" name="set_default_group" value="<?php echo $lang->t('Save') ?>" tabindex="4" /></div></th>
									<td>
										<select id="default_group" name="default_group" tabindex="3">
<?php

$query = $db->select(array('g_id' => 'g.g_id', 'g_title' => 'g.g_title'), 'groups AS g');
$query->where = 'g.g_id > :group_guest AND g.g_moderator = 0';
$query->order = array('g_title' => 'g.g_title ASC');

$params = array(':group_guest' => PUN_GUEST);

$result = $query->run($params);
foreach ($result as $cur_group)
{
	if ($cur_group['g_id'] == $pun_config['o_default_user_group'])
		echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
	else
		echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
}

unset ($result, $query, $params);

?>
										</select>
										<span><?php echo $lang->t('Default group help') ?></span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
			</form>
		</div>

		<h2 class="block2"><span><?php echo $lang->t('Existing groups head') ?></span></h2>
		<div class="box">
			<div class="fakeform">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Edit groups subhead') ?></legend>
						<div class="infldset">
							<p><?php echo $lang->t('Edit groups info') ?></p>
							<table cellspacing="0">
<?php

$query = $db->select(array('g_id' => 'g.g_id', 'g_title' => 'g.g_title'), 'groups AS g');
$query->order = array('g_id' => 'g.g_id ASC');

$params = array();

$result = $query->run($params);

$cur_index = 5;
foreach ($result as $cur_group)
	echo "\t\t\t\t\t\t\t\t".'<tr><th scope="row"><a href="admin_groups.php?edit_group='.$cur_group['g_id'].'" tabindex="'.$cur_index++.'">'.$lang->t('Edit link').'</a>'.(($cur_group['g_id'] > PUN_MEMBER) ? ' | <a href="admin_groups.php?del_group='.$cur_group['g_id'].'" tabindex="'.$cur_index++.'">'.$lang->t('Delete link').'</a>' : '').'</th><td>'.pun_htmlspecialchars($cur_group['g_title']).'</td></tr>'."\n";

unset ($result, $query, $params);

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
