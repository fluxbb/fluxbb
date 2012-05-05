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
	message($lang_common['No permission'], false, '403 Forbidden');

// Load the admin_censoring.php language file
require PUN_ROOT.'lang/'.$admin_language.'/admin_groups.php';


// Fetch all groups
$result = $db->query('SELECT * FROM '.$db->prefix.'groups ORDER BY g_id') or error('Unable to fetch user groups', __FILE__, __LINE__, $db->error());
$groups = array();
while ($cur_group = $db->fetch_assoc($result))
	$groups[$cur_group['g_id']] = $cur_group;

// Add/edit a group (stage 1)
if (isset($_POST['add_group']) || isset($_GET['edit_group']))
{
	if (isset($_POST['add_group']))
	{
		$base_group = intval($_POST['base_group']);
		$group = $groups[$base_group];

		$mode = 'add';
	}
	else // We are editing a group
	{
		$group_id = intval($_GET['edit_group']);
		if ($group_id < 1 || !isset($groups[$group_id]))
			message($lang_common['Bad request']);

		$group = $groups[$group_id];

		$mode = 'edit';
	}


	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_common['Admin'], $lang_admin_common['User groups']);
	$required_fields = array('req_title' => $lang_admin_groups['Group title label']);
	$focus_element = array('groups2', 'req_title');
	define('PUN_ACTIVE_PAGE', 'admin');
	require PUN_ROOT.'header.php';

	generate_admin_menu('groups');

?>
	<div class="blockform">
		<h2><span><?php echo $lang_admin_groups['Group settings head'] ?></span></h2>
		<div class="box">
			<form id="groups2" method="post" action="admin_groups.php" onsubmit="return process_form(this)">
				<p class="submittop"><input type="submit" name="add_edit_group" value="<?php echo $lang_admin_common['Save'] ?>" /></p>
				<div class="inform">
					<input type="hidden" name="mode" value="<?php echo $mode ?>" />
<?php if ($mode == 'edit'): ?>					<input type="hidden" name="group_id" value="<?php echo $group_id ?>" />
<?php endif; ?><?php if ($mode == 'add'): ?>					<input type="hidden" name="base_group" value="<?php echo $base_group ?>" />
<?php endif; ?>					<fieldset>
						<legend><?php echo $lang_admin_groups['Group settings subhead'] ?></legend>
						<div class="infldset">
							<p><?php echo $lang_admin_groups['Group settings info'] ?></p>
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang_admin_groups['Group title label'] ?></th>
									<td>
										<input type="text" name="req_title" size="25" maxlength="50" value="<?php if ($mode == 'edit') echo pun_htmlspecialchars($group['g_title']); ?>" tabindex="1" />
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_groups['User title label'] ?></th>
									<td>
										<input type="text" name="user_title" size="25" maxlength="50" value="<?php echo pun_htmlspecialchars($group['g_user_title']) ?>" tabindex="2" />
										<span><?php printf($lang_admin_groups['User title help'], $lang_common['Member']) ?></span>
									</td>
								</tr>
<?php if ($group['g_id'] != PUN_ADMIN): if ($group['g_id'] != PUN_GUEST): ?>								<tr>
									<th scope="row"><?php echo $lang_admin_groups['Promote users label'] ?></th>
									<td>
										<select name="promote_next_group" tabindex="3">
											<option value="0"><?php echo $lang_admin_groups['Disable promotion'] ?></option>
<?php

foreach ($groups as $cur_group)
{
	if (($cur_group['g_id'] != $group['g_id'] || $mode == 'add') && $cur_group['g_id'] != PUN_ADMIN && $cur_group['g_id'] != PUN_GUEST)
	{
		if ($cur_group['g_id'] == $group['g_promote_next_group'])
			echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
		else
			echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
	}
}

?>
										</select>
										<input type="text" name="promote_min_posts" size="5" maxlength="10" value="<?php echo pun_htmlspecialchars($group['g_promote_min_posts']) ?>" tabindex="4" />
										<span><?php printf($lang_admin_groups['Promote users help'], $lang_admin_groups['Disable promotion']) ?></span>
									</td>
								</tr>
<?php if ($mode != 'edit' || $pun_config['o_default_user_group'] != $group['g_id']): ?>								<tr>
									<th scope="row"> <?php echo $lang_admin_groups['Mod privileges label'] ?></th>
									<td>
										<input type="radio" name="moderator" value="1"<?php if ($group['g_moderator'] == '1') echo ' checked="checked"' ?> tabindex="5" />&#160;<strong><?php echo $lang_admin_common['Yes'] ?></strong>&#160;&#160;&#160;<input type="radio" name="moderator" value="0"<?php if ($group['g_moderator'] == '0') echo ' checked="checked"' ?> tabindex="6" />&#160;<strong><?php echo $lang_admin_common['No'] ?></strong>
										<span><?php echo $lang_admin_groups['Mod privileges help'] ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_groups['Edit profile label'] ?></th>
									<td>
										<input type="radio" name="mod_edit_users" value="1"<?php if ($group['g_mod_edit_users'] == '1') echo ' checked="checked"' ?> tabindex="7" />&#160;<strong><?php echo $lang_admin_common['Yes'] ?></strong>&#160;&#160;&#160;<input type="radio" name="mod_edit_users" value="0"<?php if ($group['g_mod_edit_users'] == '0') echo ' checked="checked"' ?> tabindex="8" />&#160;<strong><?php echo $lang_admin_common['No'] ?></strong>
										<span><?php echo $lang_admin_groups['Edit profile help'] ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_groups['Rename users label'] ?></th>
									<td>
										<input type="radio" name="mod_rename_users" value="1"<?php if ($group['g_mod_rename_users'] == '1') echo ' checked="checked"' ?> tabindex="9" />&#160;<strong><?php echo $lang_admin_common['Yes'] ?></strong>&#160;&#160;&#160;<input type="radio" name="mod_rename_users" value="0"<?php if ($group['g_mod_rename_users'] == '0') echo ' checked="checked"' ?> tabindex="10" />&#160;<strong><?php echo $lang_admin_common['No'] ?></strong>
										<span><?php echo $lang_admin_groups['Rename users help'] ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_groups['Change passwords label'] ?></th>
									<td>
										<input type="radio" name="mod_change_passwords" value="1"<?php if ($group['g_mod_change_passwords'] == '1') echo ' checked="checked"' ?> tabindex="11" />&#160;<strong><?php echo $lang_admin_common['Yes'] ?></strong>&#160;&#160;&#160;<input type="radio" name="mod_change_passwords" value="0"<?php if ($group['g_mod_change_passwords'] == '0') echo ' checked="checked"' ?> tabindex="12" />&#160;<strong><?php echo $lang_admin_common['No'] ?></strong>
										<span><?php echo $lang_admin_groups['Change passwords help'] ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_groups['Ban users label'] ?></th>
									<td>
										<input type="radio" name="mod_ban_users" value="1"<?php if ($group['g_mod_ban_users'] == '1') echo ' checked="checked"' ?> tabindex="13" />&#160;<strong><?php echo $lang_admin_common['Yes'] ?></strong>&#160;&#160;&#160;<input type="radio" name="mod_ban_users" value="0"<?php if ($group['g_mod_ban_users'] == '0') echo ' checked="checked"' ?> tabindex="14" />&#160;<strong><?php echo $lang_admin_common['No'] ?></strong>
										<span><?php echo $lang_admin_groups['Ban users help'] ?></span>
									</td>
								</tr>
<?php endif; endif; ?>								<tr>
									<th scope="row"><?php echo $lang_admin_groups['Read board label'] ?></th>
									<td>
										<input type="radio" name="read_board" value="1"<?php if ($group['g_read_board'] == '1') echo ' checked="checked"' ?> tabindex="15" />&#160;<strong><?php echo $lang_admin_common['Yes'] ?></strong>&#160;&#160;&#160;<input type="radio" name="read_board" value="0"<?php if ($group['g_read_board'] == '0') echo ' checked="checked"' ?> tabindex="16" />&#160;<strong><?php echo $lang_admin_common['No'] ?></strong>
										<span><?php echo $lang_admin_groups['Read board help'] ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_groups['View user info label'] ?></th>
									<td>
										<input type="radio" name="view_users" value="1"<?php if ($group['g_view_users'] == '1') echo ' checked="checked"' ?> tabindex="17" />&#160;<strong><?php echo $lang_admin_common['Yes'] ?></strong>&#160;&#160;&#160;<input type="radio" name="view_users" value="0"<?php if ($group['g_view_users'] == '0') echo ' checked="checked"' ?> tabindex="18" />&#160;<strong><?php echo $lang_admin_common['No'] ?></strong>
										<span><?php echo $lang_admin_groups['View user info help'] ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_groups['Post replies label'] ?></th>
									<td>
										<input type="radio" name="post_replies" value="1"<?php if ($group['g_post_replies'] == '1') echo ' checked="checked"' ?> tabindex="19" />&#160;<strong><?php echo $lang_admin_common['Yes'] ?></strong>&#160;&#160;&#160;<input type="radio" name="post_replies" value="0"<?php if ($group['g_post_replies'] == '0') echo ' checked="checked"' ?> tabindex="20" />&#160;<strong><?php echo $lang_admin_common['No'] ?></strong>
										<span><?php echo $lang_admin_groups['Post replies help'] ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_groups['Post topics label'] ?></th>
									<td>
										<input type="radio" name="post_topics" value="1"<?php if ($group['g_post_topics'] == '1') echo ' checked="checked"' ?> tabindex="21" />&#160;<strong><?php echo $lang_admin_common['Yes'] ?></strong>&#160;&#160;&#160;<input type="radio" name="post_topics" value="0"<?php if ($group['g_post_topics'] == '0') echo ' checked="checked"' ?> tabindex="22" />&#160;<strong><?php echo $lang_admin_common['No'] ?></strong>
										<span><?php echo $lang_admin_groups['Post topics help'] ?></span>
									</td>
								</tr>
<?php if ($group['g_id'] != PUN_GUEST): ?>								<tr>
									<th scope="row"><?php echo $lang_admin_groups['Edit posts label'] ?></th>
									<td>
										<input type="radio" name="edit_posts" value="1"<?php if ($group['g_edit_posts'] == '1') echo ' checked="checked"' ?> tabindex="23" />&#160;<strong><?php echo $lang_admin_common['Yes'] ?></strong>&#160;&#160;&#160;<input type="radio" name="edit_posts" value="0"<?php if ($group['g_edit_posts'] == '0') echo ' checked="checked"' ?> tabindex="24" />&#160;<strong><?php echo $lang_admin_common['No'] ?></strong>
										<span><?php echo $lang_admin_groups['Edit posts help'] ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_groups['Delete posts label'] ?></th>
									<td>
										<input type="radio" name="delete_posts" value="1"<?php if ($group['g_delete_posts'] == '1') echo ' checked="checked"' ?> tabindex="25" />&#160;<strong><?php echo $lang_admin_common['Yes'] ?></strong>&#160;&#160;&#160;<input type="radio" name="delete_posts" value="0"<?php if ($group['g_delete_posts'] == '0') echo ' checked="checked"' ?> tabindex="26" />&#160;<strong><?php echo $lang_admin_common['No'] ?></strong>
										<span><?php echo $lang_admin_groups['Delete posts help'] ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_groups['Delete topics label'] ?></th>
									<td>
										<input type="radio" name="delete_topics" value="1"<?php if ($group['g_delete_topics'] == '1') echo ' checked="checked"' ?> tabindex="27" />&#160;<strong><?php echo $lang_admin_common['Yes'] ?></strong>&#160;&#160;&#160;<input type="radio" name="delete_topics" value="0"<?php if ($group['g_delete_topics'] == '0') echo ' checked="checked"' ?> tabindex="28" />&#160;<strong><?php echo $lang_admin_common['No'] ?></strong>
										<span><?php echo $lang_admin_groups['Delete topics help'] ?></span>
									</td>
								</tr>
<?php endif; ?>								<tr>
									<th scope="row"><?php echo $lang_admin_groups['Post links label'] ?></th>
									<td>
										<input type="radio" name="post_links" value="1"<?php if ($group['g_post_links'] == '1') echo ' checked="checked"' ?> tabindex="29" />&#160;<strong><?php echo $lang_admin_common['Yes'] ?></strong>&#160;&#160;&#160;<input type="radio" name="post_links" value="0"<?php if ($group['g_post_links'] == '0') echo ' checked="checked"' ?> tabindex="30" />&#160;<strong><?php echo $lang_admin_common['No'] ?></strong>
										<span><?php echo $lang_admin_groups['Post links help'] ?></span>
									</td>
								</tr>
<?php if ($group['g_id'] != PUN_GUEST): ?>								<tr>
									<th scope="row"><?php echo $lang_admin_groups['Set own title label'] ?></th>
									<td>
										<input type="radio" name="set_title" value="1"<?php if ($group['g_set_title'] == '1') echo ' checked="checked"' ?> tabindex="31" />&#160;<strong><?php echo $lang_admin_common['Yes'] ?></strong>&#160;&#160;&#160;<input type="radio" name="set_title" value="0"<?php if ($group['g_set_title'] == '0') echo ' checked="checked"' ?> tabindex="32" />&#160;<strong><?php echo $lang_admin_common['No'] ?></strong>
										<span><?php echo $lang_admin_groups['Set own title help'] ?></span>
									</td>
								</tr>
<?php endif; ?>								<tr>
									<th scope="row"><?php echo $lang_admin_groups['User search label'] ?></th>
									<td>
										<input type="radio" name="search" value="1"<?php if ($group['g_search'] == '1') echo ' checked="checked"' ?> tabindex="33" />&#160;<strong><?php echo $lang_admin_common['Yes'] ?></strong>&#160;&#160;&#160;<input type="radio" name="search" value="0"<?php if ($group['g_search'] == '0') echo ' checked="checked"' ?> tabindex="34" />&#160;<strong><?php echo $lang_admin_common['No'] ?></strong>
										<span><?php echo $lang_admin_groups['User search help'] ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_groups['User list search label'] ?></th>
									<td>
										<input type="radio" name="search_users" value="1"<?php if ($group['g_search_users'] == '1') echo ' checked="checked"' ?> tabindex="35" />&#160;<strong><?php echo $lang_admin_common['Yes'] ?></strong>&#160;&#160;&#160;<input type="radio" name="search_users" value="0"<?php if ($group['g_search_users'] == '0') echo ' checked="checked"' ?> tabindex="36" />&#160;<strong><?php echo $lang_admin_common['No'] ?></strong>
										<span><?php echo $lang_admin_groups['User list search help'] ?></span>
									</td>
								</tr>
<?php if ($group['g_id'] != PUN_GUEST): ?>								<tr>
									<th scope="row"><?php echo $lang_admin_groups['Send e-mails label'] ?></th>
									<td>
										<input type="radio" name="send_email" value="1"<?php if ($group['g_send_email'] == '1') echo ' checked="checked"' ?> tabindex="37" />&#160;<strong><?php echo $lang_admin_common['Yes'] ?></strong>&#160;&#160;&#160;<input type="radio" name="send_email" value="0"<?php if ($group['g_send_email'] == '0') echo ' checked="checked"' ?> tabindex="38" />&#160;<strong><?php echo $lang_admin_common['No'] ?></strong>
										<span><?php echo $lang_admin_groups['Send e-mails help'] ?></span>
									</td>
								</tr>
<?php endif; ?>								<tr>
									<th scope="row"><?php echo $lang_admin_groups['Post flood label'] ?></th>
									<td>
										<input type="text" name="post_flood" size="5" maxlength="4" value="<?php echo $group['g_post_flood'] ?>" tabindex="39" />
										<span><?php echo $lang_admin_groups['Post flood help'] ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_groups['Search flood label'] ?></th>
									<td>
										<input type="text" name="search_flood" size="5" maxlength="4" value="<?php echo $group['g_search_flood'] ?>" tabindex="40" />
										<span><?php echo $lang_admin_groups['Search flood help'] ?></span>
									</td>
								</tr>
<?php if ($group['g_id'] != PUN_GUEST): ?>								<tr>
									<th scope="row"><?php echo $lang_admin_groups['E-mail flood label'] ?></th>
									<td>
										<input type="text" name="email_flood" size="5" maxlength="4" value="<?php echo $group['g_email_flood'] ?>" tabindex="41" />
										<span><?php echo $lang_admin_groups['E-mail flood help'] ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_groups['Report flood label'] ?></th>
									<td>
										<input type="text" name="report_flood" size="5" maxlength="4" value="<?php echo $group['g_report_flood'] ?>" tabindex="42" />
										<span><?php echo $lang_admin_groups['Report flood help'] ?></span>
									</td>
								</tr>
<?php endif; endif; ?>							</table>
<?php if ($group['g_moderator'] == '1' ): ?>							<p class="warntext"><?php echo $lang_admin_groups['Moderator info'] ?></p>
<?php endif; ?>						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="add_edit_group" value="<?php echo $lang_admin_common['Save'] ?>" tabindex="43" /></p>
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

	$promote_min_posts = isset($_POST['promote_min_posts']) ? intval($_POST['promote_min_posts']) : '0';
	if (isset($_POST['promote_next_group']) &&
			isset($groups[$_POST['promote_next_group']]) &&
			!in_array($_POST['promote_next_group'], array(PUN_ADMIN, PUN_GUEST)) &&
			(!isset($_POST['group_id']) || $_POST['promote_next_group'] != $_POST['group_id']))
		$promote_next_group = $_POST['promote_next_group'];
	else
		$promote_next_group = '0';

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
	$post_links = isset($_POST['post_links']) ? intval($_POST['post_links']) : '1';
	$set_title = isset($_POST['set_title']) ? intval($_POST['set_title']) : ($is_admin_group) ? '1' : '0';
	$search = isset($_POST['search']) ? intval($_POST['search']) : '1';
	$search_users = isset($_POST['search_users']) ? intval($_POST['search_users']) : '1';
	$send_email = (isset($_POST['send_email']) && $_POST['send_email'] == '1') || $is_admin_group ? '1' : '0';
	$post_flood = (isset($_POST['post_flood']) && $_POST['post_flood'] >= 0) ? intval($_POST['post_flood']) : '0';
	$search_flood = (isset($_POST['search_flood']) && $_POST['search_flood'] >= 0) ? intval($_POST['search_flood']) : '0';
	$email_flood = (isset($_POST['email_flood']) && $_POST['email_flood'] >= 0) ? intval($_POST['email_flood']) : '0';
	$report_flood = (isset($_POST['report_flood']) && $_POST['report_flood'] >= 0) ? intval($_POST['report_flood']) : '0';

	if ($title == '')
		message($lang_admin_groups['Must enter title message']);

	$user_title = ($user_title != '') ? '\''.$db->escape($user_title).'\'' : 'NULL';

	if ($_POST['mode'] == 'add')
	{
		$result = $db->query('SELECT 1 FROM '.$db->prefix.'groups WHERE g_title=\''.$db->escape($title).'\'') or error('Unable to check group title collision', __FILE__, __LINE__, $db->error());
		if ($db->num_rows($result))
			message(sprintf($lang_admin_groups['Title already exists message'], pun_htmlspecialchars($title)));

		$db->query('INSERT INTO '.$db->prefix.'groups (g_title, g_user_title, g_promote_min_posts, g_promote_next_group, g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_post_links, g_set_title, g_search, g_search_users, g_send_email, g_post_flood, g_search_flood, g_email_flood, g_report_flood) VALUES(\''.$db->escape($title).'\', '.$user_title.', '.$promote_min_posts.', '.$promote_next_group.', '.$moderator.', '.$mod_edit_users.', '.$mod_rename_users.', '.$mod_change_passwords.', '.$mod_ban_users.', '.$read_board.', '.$view_users.', '.$post_replies.', '.$post_topics.', '.$edit_posts.', '.$delete_posts.', '.$delete_topics.', '.$post_links.', '.$set_title.', '.$search.', '.$search_users.', '.$send_email.', '.$post_flood.', '.$search_flood.', '.$email_flood.', '.$report_flood.')') or error('Unable to add group', __FILE__, __LINE__, $db->error());
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
			message(sprintf($lang_admin_groups['Title already exists message'], pun_htmlspecialchars($title)));

		$db->query('UPDATE '.$db->prefix.'groups SET g_title=\''.$db->escape($title).'\', g_user_title='.$user_title.', g_promote_min_posts='.$promote_min_posts.', g_promote_next_group='.$promote_next_group.', g_moderator='.$moderator.', g_mod_edit_users='.$mod_edit_users.', g_mod_rename_users='.$mod_rename_users.', g_mod_change_passwords='.$mod_change_passwords.', g_mod_ban_users='.$mod_ban_users.', g_read_board='.$read_board.', g_view_users='.$view_users.', g_post_replies='.$post_replies.', g_post_topics='.$post_topics.', g_edit_posts='.$edit_posts.', g_delete_posts='.$delete_posts.', g_delete_topics='.$delete_topics.', g_post_links='.$post_links.', g_set_title='.$set_title.', g_search='.$search.', g_search_users='.$search_users.', g_send_email='.$send_email.', g_post_flood='.$post_flood.', g_search_flood='.$search_flood.', g_email_flood='.$email_flood.', g_report_flood='.$report_flood.' WHERE g_id='.intval($_POST['group_id'])) or error('Unable to update group', __FILE__, __LINE__, $db->error());
	}

	// Regenerate the quick jump cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';

	$group_id = $_POST['mode'] == 'add' ? $new_group_id : intval($_POST['group_id']);
	generate_quickjump_cache($group_id);

	if ($_POST['mode'] == 'edit')
		redirect('admin_groups.php', $lang_admin_groups['Group edited redirect']);
	else
		redirect('admin_groups.php', $lang_admin_groups['Group added redirect']);
}


// Set default group
else if (isset($_POST['set_default_group']))
{
	confirm_referrer('admin_groups.php');

	$group_id = intval($_POST['default_group']);

	// Make sure it's not the admin or guest groups
	if ($group_id == PUN_ADMIN || $group_id == PUN_GUEST)
		message($lang_common['Bad request']);

	// Make sure it's not a moderator group
	if ($groups[$group_id]['g_moderator'] != 0)
		message($lang_common['Bad request']);

	$db->query('UPDATE '.$db->prefix.'config SET conf_value='.$group_id.' WHERE conf_name=\'o_default_user_group\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());

	// Regenerate the config cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';

	generate_config_cache();

	redirect('admin_groups.php', $lang_admin_groups['Default group redirect']);
}


// Remove a group
else if (isset($_GET['del_group']))
{
	confirm_referrer('admin_groups.php');

	$group_id = isset($_POST['group_to_delete']) ? intval($_POST['group_to_delete']) : intval($_GET['del_group']);
	if ($group_id < 5)
		message($lang_common['Bad request']);

	// Make sure we don't remove the default group
	if ($group_id == $pun_config['o_default_user_group'])
		message($lang_admin_groups['Cannot remove default message']);

	// Check if this group has any members
	$result = $db->query('SELECT g.g_title, COUNT(u.id) FROM '.$db->prefix.'groups AS g INNER JOIN '.$db->prefix.'users AS u ON g.g_id=u.group_id WHERE g.g_id='.$group_id.' GROUP BY g.g_id, g_title') or error('Unable to fetch group info', __FILE__, __LINE__, $db->error());

	// If the group doesn't have any members or if we've already selected a group to move the members to
	if (!$db->num_rows($result) || isset($_POST['del_group']))
	{
		if (isset($_POST['del_group_comply']) || isset($_POST['del_group']))
		{
			if (isset($_POST['del_group']))
			{
				$move_to_group = intval($_POST['move_to_group']);
				$db->query('UPDATE '.$db->prefix.'users SET group_id='.$move_to_group.' WHERE group_id='.$group_id) or error('Unable to move users into group', __FILE__, __LINE__, $db->error());
			}

			// Delete the group and any forum specific permissions
			$db->query('DELETE FROM '.$db->prefix.'groups WHERE g_id='.$group_id) or error('Unable to delete group', __FILE__, __LINE__, $db->error());
			$db->query('DELETE FROM '.$db->prefix.'forum_perms WHERE group_id='.$group_id) or error('Unable to delete group forum permissions', __FILE__, __LINE__, $db->error());

			// Don't let users be promoted to this group
			$db->query('UPDATE '.$db->prefix.'groups SET g_promote_next_group=0 WHERE g_promote_next_group='.$group_id) or error('Unable to remove group as promotion target', __FILE__, __LINE__, $db->error());

			redirect('admin_groups.php', $lang_admin_groups['Group removed redirect']);
		}
		else
		{
			$result = $db->query('SELECT g_title FROM '.$db->prefix.'groups WHERE g_id='.$group_id) or error('Unable to fetch group title', __FILE__, __LINE__, $db->error());
			$group_title = $db->result($result);

			$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_common['Admin'], $lang_admin_common['User groups']);
			define('PUN_ACTIVE_PAGE', 'admin');
			require PUN_ROOT.'header.php';

			generate_admin_menu('groups');

?>
	<div class="blockform">
		<h2><span><?php echo $lang_admin_groups['Group delete head'] ?></span></h2>
		<div class="box">
			<form method="post" action="admin_groups.php?del_group=<?php echo $group_id ?>">
				<div class="inform">
				<input type="hidden" name="group_to_delete" value="<?php echo $group_id ?>" />
					<fieldset>
						<legend><?php echo $lang_admin_groups['Confirm delete subhead'] ?></legend>
						<div class="infldset">
							<p><?php printf($lang_admin_groups['Confirm delete info'], pun_htmlspecialchars($group_title)) ?></p>
							<p class="warntext"><?php echo $lang_admin_groups['Confirm delete warn'] ?></p>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="del_group_comply" value="<?php echo $lang_admin_common['Delete'] ?>" tabindex="1" /><a href="javascript:history.go(-1)" tabindex="2"><?php echo $lang_admin_common['Go back'] ?></a></p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

			require PUN_ROOT.'footer.php';
		}
	}

	list($group_title, $group_members) = $db->fetch_row($result);

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_common['Admin'], $lang_admin_common['User groups']);
	define('PUN_ACTIVE_PAGE', 'admin');
	require PUN_ROOT.'header.php';

	generate_admin_menu('groups');

?>
	<div class="blockform">
		<h2><span><?php echo $lang_admin_groups['Delete group head'] ?></span></h2>
		<div class="box">
			<form id="groups" method="post" action="admin_groups.php?del_group=<?php echo $group_id ?>">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_groups['Move users subhead'] ?></legend>
						<div class="infldset">
							<p><?php printf($lang_admin_groups['Move users info'], pun_htmlspecialchars($group_title), forum_number_format($group_members)) ?></p>
							<label><?php echo $lang_admin_groups['Move users label'] ?>
							<select name="move_to_group">
<?php

	$result = $db->query('SELECT g_id, g_title FROM '.$db->prefix.'groups WHERE g_id!='.PUN_GUEST.' AND g_id!='.$group_id.' ORDER BY g_title') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());

	while ($cur_group = $db->fetch_assoc($result))
	{
		if ($cur_group['g_id'] == PUN_MEMBER) // Pre-select the pre-defined Members group
			echo "\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
		else
			echo "\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
	}

?>
							</select>
							<br /></label>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="del_group" value="<?php echo $lang_admin_groups['Delete group'] ?>" /><a href="javascript:history.go(-1)"><?php echo $lang_admin_common['Go back'] ?></a></p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_common['Admin'], $lang_admin_common['User groups']);
define('PUN_ACTIVE_PAGE', 'admin');
require PUN_ROOT.'header.php';

generate_admin_menu('groups');

?>
	<div class="blockform">
		<h2><span><?php echo $lang_admin_groups['Add groups head'] ?></span></h2>
		<div class="box">
			<form id="groups" method="post" action="admin_groups.php">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_groups['Add group subhead'] ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang_admin_groups['New group label'] ?><div><input type="submit" name="add_group" value="<?php echo $lang_admin_common['Add'] ?>" tabindex="2" /></div></th>
									<td>
										<select id="base_group" name="base_group" tabindex="1">
<?php

foreach ($groups as $cur_group)
{
	if ($cur_group['g_id'] != PUN_ADMIN && $cur_group['g_id'] != PUN_GUEST)
	{
		if ($cur_group['g_id'] == $pun_config['o_default_user_group'])
			echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
		else
			echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
	}
}

?>
										</select>
										<span><?php echo $lang_admin_groups['New group help'] ?></span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_groups['Default group subhead'] ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang_admin_groups['Default group label'] ?><div><input type="submit" name="set_default_group" value="<?php echo $lang_admin_common['Save'] ?>" tabindex="4" /></div></th>
									<td>
										<select id="default_group" name="default_group" tabindex="3">
<?php

foreach ($groups as $cur_group)
{
	if ($cur_group['g_id'] > PUN_GUEST && $cur_group['g_moderator'] == 0)
	{
		if ($cur_group['g_id'] == $pun_config['o_default_user_group'])
			echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
		else
			echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.pun_htmlspecialchars($cur_group['g_title']).'</option>'."\n";
	}
}

?>
										</select>
										<span><?php echo $lang_admin_groups['Default group help'] ?></span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
			</form>
		</div>

		<h2 class="block2"><span><?php echo $lang_admin_groups['Existing groups head'] ?></span></h2>
		<div class="box">
			<div class="fakeform">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_groups['Edit groups subhead'] ?></legend>
						<div class="infldset">
							<p><?php echo $lang_admin_groups['Edit groups info'] ?></p>
							<table cellspacing="0">
<?php

$cur_index = 5;

foreach ($groups as $cur_group)
	echo "\t\t\t\t\t\t\t\t".'<tr><th scope="row"><a href="admin_groups.php?edit_group='.$cur_group['g_id'].'" tabindex="'.$cur_index++.'">'.$lang_admin_groups['Edit link'].'</a>'.(($cur_group['g_id'] > PUN_MEMBER) ? ' | <a href="admin_groups.php?del_group='.$cur_group['g_id'].'" tabindex="'.$cur_index++.'">'.$lang_admin_groups['Delete link'].'</a>' : '').'</th><td>'.pun_htmlspecialchars($cur_group['g_title']).'</td></tr>'."\n";

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
