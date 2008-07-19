<?php
/***********************************************************************

  Copyright (C) 2008  FluxBB.org

  Based on code copyright (C) 2002-2008  PunBB.org

  This file is part of FluxBB.

  FluxBB is free software; you can redistribute it and/or modify it
  under the terms of the GNU General Public License as published
  by the Free Software Foundation; either version 2 of the License,
  or (at your option) any later version.

  FluxBB is distributed in the hope that it will be useful, but
  WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston,
  MA  02111-1307  USA

************************************************************************/


if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', '../');
require FORUM_ROOT.'include/common.php';
require FORUM_ROOT.'include/common_admin.php';

($hook = get_hook('agr_start')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

if ($forum_user['g_id'] != FORUM_ADMIN)
	message($lang_common['No permission']);

// Load the admin.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin_common.php';
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin_groups.php';


// Add/edit a group (stage 1)
if (isset($_POST['add_group']) || isset($_GET['edit_group']))
{
	if (isset($_POST['add_group']))
	{
		($hook = get_hook('agr_add_group_form_submitted')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

		$base_group = intval($_POST['base_group']);

		$query = array(
			'SELECT'	=> 'g.*',
			'FROM'		=> 'groups AS g',
			'WHERE'		=> 'g.g_id='.$base_group
		);

		($hook = get_hook('agr_qr_get_base_group')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		$group = $forum_db->fetch_assoc($result);

		$mode = 'add';
	}
	else	// We are editing a group
	{
		($hook = get_hook('agr_edit_group_form_submitted')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

		$group_id = intval($_GET['edit_group']);
		if ($group_id < 1)
			message($lang_common['Bad request']);

		$query = array(
			'SELECT'	=> 'g.*',
			'FROM'		=> 'groups AS g',
			'WHERE'		=> 'g.g_id='.$group_id
		);

		($hook = get_hook('agr_qr_get_group')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		if (!$forum_db->num_rows($result))
			message($lang_common['Bad request']);

		$group = $forum_db->fetch_assoc($result);

		$mode = 'edit';
	}

	// Setup the form
	$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
		array($lang_admin_common['Groups'], forum_link($forum_url['admin_groups'])),
		$mode == 'edit' ? $lang_admin_groups['Edit group heading'] : $lang_admin_groups['Add group heading']
	);

	($hook = get_hook('agr_add_edit_group_pre_header_load')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

	define('FORUM_PAGE_SECTION', 'users');
	define('FORUM_PAGE', 'admin-groups');
	define('FORUM_PAGE_TYPE', 'sectioned');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

	($hook = get_hook('agr_add_edit_group_output_start')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
	<div class="main-subhead">
		<h2><span><?php echo $lang_admin_groups['Group settings heading'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<div id="req-msg" class="frm-warn">
			<p class="important"><?php printf($lang_common['Required warn'], '<em class="req-text">'.$lang_common['Reqmark'].'</em>') ?></p>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_groups']) ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_groups'])) ?>" />
				<input type="hidden" name="mode" value="<?php echo $mode ?>" />
<?php if ($mode == 'edit'): ?>				<input type="hidden" name="group_id" value="<?php echo $group_id ?>" />
<?php endif; if ($mode == 'add'): ?>				<input type="hidden" name="base_group" value="<?php echo $base_group ?>" />
<?php endif; ?>			</div>
<?php ($hook = get_hook('agr_add_edit_group_pre_title_part')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
			<div class="content-head">
				<h3 class="hn"><span><?php echo $lang_admin_groups['Group title head'] ?></span></h3>
			</div>
			<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><span><?php echo $lang_admin_groups['Group title legend'] ?></span></legend>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text required">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><em class="req-text"><?php echo $lang_common['Reqmark'] ?></em> <?php echo $lang_admin_groups['Group title label'] ?></span></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_title" size="25" maxlength="50" value="<?php if ($mode == 'edit') echo forum_htmlencode($group['g_title']); ?>" /></span>
					</div>
				</div>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text required">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_groups['User title label'] ?></span> <small><?php echo $lang_admin_groups['User title help'] ?></small></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="user_title" size="25" maxlength="50" value="<?php echo forum_htmlencode($group['g_user_title']) ?>" /></span>
					</div>
				</div>
<?php ($hook = get_hook('agr_add_edit_group_title_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
			</fieldset>
<?php

	// The rest of the form is for non-admin groups only
	if ($group['g_id'] != FORUM_ADMIN)
	{
		// Reset fieldset counter
		$forum_page['group_count'] = $forum_page['item_count'] = 0;

?>
<?php ($hook = get_hook('agr_add_edit_group_pre_permissions_part')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
			<div class="content-head">
				<h3 class="hn"><span><?php echo $lang_admin_groups['Group perms head'] ?></span></h3>
			</div>
<?php if ($mode == 'edit' && $forum_config['o_default_user_group'] == $group['g_id']): ?>				<div class="ct-box">
					<p class="warn"><?php echo $lang_admin_groups['Moderator default group'] ?></p>
				</div>
<?php endif; ?>							<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
					<legend class="group-legend"><strong><?php echo $lang_admin_groups['Permissions'] ?></strong></legend>
<?php if ($group['g_id'] != FORUM_GUEST): if ($mode != 'edit' || $forum_config['o_default_user_group'] != $group['g_id']): ?>				<fieldset class="mf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<legend><span><?php echo $lang_admin_groups['Mod permissions'] ?></span></legend>
						<div class="mf-box">
							<div class="mf-item"><span class="fld-input">
								<input type="checkbox" id="fld<?php echo $forum_page['fld_count'] ?>" name="moderator" value="1"<?php if ($group['g_moderator'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_groups['Allow moderate label'] ?> <?php echo $lang_admin_groups['Allow moderate help'] ?></label>
							</div>
							<div class="mf-item"><span class="fld-input">
								<input type="checkbox" id="fld<?php echo $forum_page['fld_count'] ?>" name="mod_edit_users" value="1"<?php if ($group['g_mod_edit_users'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_groups['Allow mod edit profiles label'] ?></label>
							</div>
							<div class="mf-item"><span class="fld-input">
								<input type="checkbox" id="fld<?php echo $forum_page['fld_count'] ?>" name="mod_rename_users" value="1"<?php if ($group['g_mod_rename_users'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_groups['Allow mod edit username label'] ?></label>
							</div>
							<div class="mf-item"><span class="fld-input">
								<input type="checkbox" id="fld<?php echo $forum_page['fld_count'] ?>" name="mod_change_passwords" value="1"<?php if ($group['g_mod_change_passwords'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_groups['Allow mod change pass label'] ?></label>
							</div>
							<div class="mf-item"><span class="fld-input">
								<input type="checkbox" id="fld<?php echo $forum_page['fld_count'] ?>" name="mod_ban_users" value="1"<?php if ($group['g_mod_ban_users'] == '1') echo ' checked="checked"' ?> />
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_groups['Allow mod bans label'] ?></label>
							</div>
						</div>
					</fieldset>
<?php endif; endif; ?>					<fieldset class="mf-set group-item<?php echo ++$forum_page['item_count'] ?>">
						<legend><span><?php echo $lang_admin_groups['User permissions'] ?></span></legend>
						<div class="mf-box">
							<div class="mf-item">
								<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="read_board" value="1"<?php if ($group['g_read_board'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_groups['Allow read board label'] ?> <?php echo $lang_admin_groups['Allow read board help'] ?></label>
							</div>
							<div class="mf-item">
								<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="view_users" value="1"<?php if ($group['g_view_users'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_groups['Allow view users label'] ?></label>
							</div>
							<div class="mf-item">
								<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="post_replies" value="1"<?php if ($group['g_post_replies'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_groups['Allow post replies label'] ?></label>
							</div>
							<div class="mf-item">
								<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="post_topics" value="1"<?php if ($group['g_post_topics'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_groups['Allow post topics label'] ?></label>
							</div>
<?php if ($group['g_id'] != FORUM_GUEST): ?>							<div class="mf-item">
								<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="edit_posts" value="1"<?php if ($group['g_edit_posts'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_groups['Allow edit posts label'] ?></label>
							</div>
							<div class="mf-item">
								<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="delete_posts" value="1"<?php if ($group['g_delete_posts'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_groups['Allow delete posts label'] ?></label>
							</div>
							<div class="mf-item">
								<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="delete_topics" value="1"<?php if ($group['g_delete_topics'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_groups['Allow delete topics label'] ?></label>
							</div>
							<div class="mf-item">
								<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="set_title" value="1"<?php if ($group['g_set_title'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_groups['Allow set user title label'] ?></label>
							</div>
<?php endif; ?>							<div class="mf-item">
								<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="search" value="1"<?php if ($group['g_search'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_groups['Allow use search label'] ?></label>
							</div>
							<div class="mf-item">
								<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="search_users" value="1"<?php if ($group['g_search_users'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_groups['Allow search users label'] ?></label>
							</div>
<?php if ($group['g_id'] != FORUM_GUEST): ?>							<div class="mf-item">
								<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="send_email" value="1"<?php if ($group['g_send_email'] == '1') echo ' checked="checked"' ?> /></span>
								<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_admin_groups['Allow send email label'] ?></label>
							</div>
<?php endif; ?>					</div>
					</fieldset>
<?php ($hook = get_hook('agr_add_edit_group_permissions_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
				</fieldset>
<?php

		// Reset counter
		$forum_page['group_count'] = $forum_page['item_count'] = 0;

		// The rest of the form is for non-guest groups only
		if ($group['g_id'] != FORUM_GUEST)
		{

?>
<?php ($hook = get_hook('agr_add_edit_group_pre_flood_part')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
			<div class="content-head">
				<h3 class="hn"><span><?php echo $lang_admin_groups['Group flood head'] ?></span></h3>
			</div>
			<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><span><?php echo $lang_admin_groups['Restrictions'] ?></span></legend>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_groups['Flood interval label'] ?></span> <small><?php echo $lang_admin_groups['Flood interval help'] ?></small></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="post_flood" size="5" maxlength="4" value="<?php echo $group['g_post_flood'] ?>" /></span>
					</div>
				</div>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_groups['Search interval label'] ?></span> <small><?php echo $lang_admin_groups['Search interval help'] ?></small></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="search_flood" size="5" maxlength="4" value="<?php echo $group['g_search_flood'] ?>" /></span>
					</div>
				</div>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_groups['Email flood interval label'] ?></span> <small><?php echo $lang_admin_groups['Email flood interval help'] ?></small></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="email_flood" size="5" maxlength="4" value="<?php echo $group['g_email_flood'] ?>" /></span>
					</div>
				</div>
<?php ($hook = get_hook('agr_add_edit_group_flood_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
			</fieldset>
<?php

		}
	}

?>
				<div class="frm-buttons">
					<span class="submit"><input type="submit" class="button" name="add_edit_group" value=" <?php echo $lang_admin_groups['Update group'] ?> " /></span>
				</div>
			</div>
		</form>
	</div>
<?php

	$tpl_temp = forum_trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}


// Add/edit a group (stage 2)
else if (isset($_POST['add_edit_group']))
{
	// Is this the admin group? (special rules apply)
	$is_admin_group = (isset($_POST['group_id']) && $_POST['group_id'] == FORUM_ADMIN) ? true : false;

	$title = forum_trim($_POST['req_title']);
	$user_title = forum_trim($_POST['user_title']);
	$moderator = isset($_POST['moderator']) && $_POST['moderator'] == '1' ? '1' : '0';
	$mod_edit_users = $moderator == '1' && isset($_POST['mod_edit_users']) && $_POST['mod_edit_users'] == '1' ? '1' : '0';
	$mod_rename_users = $moderator == '1' && isset($_POST['mod_rename_users']) && $_POST['mod_rename_users'] == '1' ? '1' : '0';
	$mod_change_passwords = $moderator == '1' && isset($_POST['mod_change_passwords']) && $_POST['mod_change_passwords'] == '1' ? '1' : '0';
	$mod_ban_users = $moderator == '1' && isset($_POST['mod_ban_users']) && $_POST['mod_ban_users'] == '1' ? '1' : '0';
	$read_board = (isset($_POST['read_board']) && $_POST['read_board'] == '1') || $is_admin_group ? '1' : '0';
	$view_users = (isset($_POST['view_users']) && $_POST['view_users'] == '1') || $is_admin_group ? '1' : '0';
	$post_replies = (isset($_POST['post_replies']) && $_POST['post_replies'] == '1') || $is_admin_group ? '1' : '0';
	$post_topics = (isset($_POST['post_topics']) && $_POST['post_topics'] == '1') || $is_admin_group ? '1' : '0';
	$edit_posts = (isset($_POST['edit_posts']) && $_POST['edit_posts'] == '1') || $is_admin_group ? '1' : '0';
	$delete_posts = (isset($_POST['delete_posts']) && $_POST['delete_posts'] == '1') || $is_admin_group ? '1' : '0';
	$delete_topics = (isset($_POST['delete_topics']) && $_POST['delete_topics'] == '1') || $is_admin_group ? '1' : '0';
	$set_title = (isset($_POST['set_title']) && $_POST['set_title'] == '1') || $is_admin_group ? '1' : '0';
	$search = (isset($_POST['search']) && $_POST['search'] == '1') || $is_admin_group ? '1' : '0';
	$search_users = (isset($_POST['search_users']) && $_POST['search_users'] == '1') || $is_admin_group ? '1' : '0';
	$send_email = (isset($_POST['send_email']) && $_POST['send_email'] == '1') || $is_admin_group ? '1' : '0';
	$post_flood = isset($_POST['post_flood']) ? intval($_POST['post_flood']) : '0';
	$search_flood = isset($_POST['search_flood']) ? intval($_POST['search_flood']) : '0';
	$email_flood = isset($_POST['email_flood']) ? intval($_POST['email_flood']) : '0';

	if ($title == '')
		message($lang_admin_groups['Must enter group message']);

	$user_title = ($user_title != '') ? '\''.$forum_db->escape($user_title).'\'' : 'NULL';

	($hook = get_hook('agr_add_edit_end_validation')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;


	if ($_POST['mode'] == 'add')
	{
		($hook = get_hook('agr_add_group_form_submitted2')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

		$query = array(
			'SELECT'	=> '1',
			'FROM'		=> 'groups AS g',
			'WHERE'		=> 'g_title=\''.$forum_db->escape($title).'\''
		);

		($hook = get_hook('agr_qr_check_group_title_collision')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		if ($forum_db->num_rows($result))
			message(sprintf($lang_admin_groups['Already a group message'], forum_htmlencode($title)));

		// Insert the new group
		$query = array(
			'INSERT'	=> 'g_title, g_user_title, g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_send_email, g_post_flood, g_search_flood, g_email_flood',
			'INTO'		=> 'groups',
			'VALUES'	=> '\''.$forum_db->escape($title).'\', '.$user_title.', '.$moderator.', '.$mod_edit_users.', '.$mod_rename_users.', '.$mod_change_passwords.', '.$mod_ban_users.', '.$read_board.', '.$view_users.', '.$post_replies.', '.$post_topics.', '.$edit_posts.', '.$delete_posts.', '.$delete_topics.', '.$set_title.', '.$search.', '.$search_users.', '.$send_email.', '.$post_flood.', '.$search_flood.', '.$email_flood
		);

		($hook = get_hook('agr_qy_add_group')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);
		$new_group_id = $forum_db->insert_id();

		// Now lets copy the forum specific permissions from the group which this group is based on
		$query = array(
			'SELECT'	=> 'fp.forum_id, fp.read_forum, fp.post_replies, fp.post_topics',
			'FROM'		=> 'forum_perms AS fp',
			'WHERE'		=> 'group_id='.intval($_POST['base_group'])
		);

		($hook = get_hook('agr_qr_get_group_forum_perms')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		while ($cur_forum_perm = $forum_db->fetch_assoc($result))
		{
			$query = array(
				'INSERT'	=> 'group_id, forum_id, read_forum, post_replies, post_topics',
				'INTO'		=> 'forum_perms',
				'VALUES'	=> $new_group_id.', '.$cur_forum_perm['forum_id'].', '.$cur_forum_perm['read_forum'].', '.$cur_forum_perm['post_replies'].', '.$cur_forum_perm['post_topics']
			);

			($hook = get_hook('agr_qy_add_group_forum_perms')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);
		}
	}
	else
	{
		$group_id = intval($_POST['group_id']);

		($hook = get_hook('agr_edit_group_form_submitted2')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

		// Make sure admins and guests don't get moderator privileges
		if ($group_id == FORUM_ADMIN || $group_id == FORUM_GUEST)
			$moderator = '0';

		// Make sure the default group isn't assigned moderator privileges
		if ($moderator == '1' && $forum_config['o_default_user_group'] == $group_id)
			message($lang_admin_groups['Moderator default group']);

		$query = array(
			'SELECT'	=> '1',
			'FROM'		=> 'groups AS g',
			'WHERE'		=> 'g_title=\''.$forum_db->escape($title).'\' AND g_id!='.$group_id
		);

		($hook = get_hook('agr_qr_check_group_title_collision2')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		if ($forum_db->num_rows($result))
			message(sprintf($lang_admin_groups['Already a group message'], forum_htmlencode($title)));

		// Save changes
		$query = array(
			'UPDATE'	=> 'groups',
			'SET'		=> 'g_title=\''.$forum_db->escape($title).'\', g_user_title='.$user_title.', g_moderator='.$moderator.', g_mod_edit_users='.$mod_edit_users.', g_mod_rename_users='.$mod_rename_users.', g_mod_change_passwords='.$mod_change_passwords.', g_mod_ban_users='.$mod_ban_users.', g_read_board='.$read_board.', g_view_users='.$view_users.', g_post_replies='.$post_replies.', g_post_topics='.$post_topics.', g_edit_posts='.$edit_posts.', g_delete_posts='.$delete_posts.', g_delete_topics='.$delete_topics.', g_set_title='.$set_title.', g_search='.$search.', g_search_users='.$search_users.', g_send_email='.$send_email.', g_post_flood='.$post_flood.', g_search_flood='.$search_flood.', g_email_flood='.$email_flood,
			'WHERE'		=> 'g_id='.$group_id
		);

		($hook = get_hook('agr_qy_update_group')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// If the group doesn't have moderator privileges (it might have had before), remove its users from the moderator list in all forums
		if (!$moderator)
			clean_forum_moderators();
	}

	// Regenerate the quickjump cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';

	generate_quickjump_cache();

	redirect(forum_link($forum_url['admin_groups']), (($_POST['mode'] == 'edit') ? $lang_admin_groups['Group edited'] : $lang_admin_groups['Group added']).' '.$lang_admin_groups['Redirect']);
}


// Set default group
else if (isset($_POST['set_default_group']))
{
	$group_id = intval($_POST['default_group']);

	($hook = get_hook('agr_set_default_group_form_submitted')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

	// Make sure it's not the admin or guest groups
	if ($group_id == FORUM_ADMIN || $group_id == FORUM_GUEST)
		message($lang_common['Bad request']);

	// Make sure it's not a moderator group
	$query = array(
		'SELECT'	=> 'g.g_id',
		'FROM'		=> 'groups AS g',
		'WHERE'		=> 'g.g_id='.$group_id.' AND g.g_moderator=0',
		'LIMIT'		=> '1'
	);

	($hook = get_hook('agr_qr_get_group_moderation_status')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	if (!$forum_db->num_rows($result))
		message($lang_common['Bad request']);

	$query = array(
		'UPDATE'	=> 'config',
		'SET'		=> 'conf_value='.$group_id,
		'WHERE'		=> 'conf_name=\'o_default_user_group\''
	);

	($hook = get_hook('agr_qy_set_default_group')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	// Regenerate the config cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';

	generate_config_cache();

	redirect(forum_link($forum_url['admin_groups']), $lang_admin_groups['Default group set'].' '.$lang_admin_common['Redirect']);
}


// Remove a group
else if (isset($_GET['del_group']))
{
	$group_id = intval($_GET['del_group']);
	if ($group_id <= FORUM_GUEST)
		message($lang_common['Bad request']);

	// User pressed the cancel button
	if (isset($_POST['del_group_cancel']))
		redirect(forum_link($forum_url['admin_groups']), $lang_admin_common['Cancel redirect']);

	// Make sure we don't remove the default group
	if ($group_id == $forum_config['o_default_user_group'])
		message($lang_admin_groups['Cannot remove default group']);

	($hook = get_hook('agr_del_group_selected')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;


	// Check if this group has any members
	$query = array(
		'SELECT'	=> 'g.g_title, COUNT(u.id)',
		'FROM'		=> 'groups AS g',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'users AS u',
				'ON'			=> 'g.g_id=u.group_id'
			)
		),
		'WHERE'		=> 'g.g_id='.$group_id,
		'GROUP BY'	=> 'g.g_id, g.g_title'
	);

	($hook = get_hook('agr_qr_get_group_member_count')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	// If the group doesn't have any members or if we've already selected a group to move the members to
	if (!$forum_db->num_rows($result) || isset($_POST['del_group']))
	{
		($hook = get_hook('agr_del_group_form_submitted')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

		if (isset($_POST['del_group']))	// Move users
		{
			$query = array(
				'UPDATE'	=> 'users',
				'SET'		=> 'group_id='.intval($_POST['move_to_group']),
				'WHERE'		=> 'group_id='.$group_id
			);

			($hook = get_hook('agr_qy_move_users')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);
		}

		// Delete the group and any forum specific permissions
		$query = array(
			'DELETE'	=> 'groups',
			'WHERE'		=> 'g_id='.$group_id
		);

		($hook = get_hook('agr_qy_delete_group')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		$query = array(
			'DELETE'	=> 'forum_perms',
			'WHERE'		=> 'group_id='.$group_id
		);

		($hook = get_hook('agr_qy_delete_group_forum_perms')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		clean_forum_moderators();

		// Regenerate the quickjump cache
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			require FORUM_ROOT.'include/cache.php';

		generate_quickjump_cache();

		redirect(forum_link($forum_url['admin_groups']), $lang_admin_groups['Group removed'].' '.$lang_admin_common['Redirect']);
	}

	list($group_title, $num_members) = $forum_db->fetch_row($result);


	// Setup the form
	$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
		array($lang_admin_common['Groups'], forum_link($forum_url['admin_groups'])),
		$lang_admin_groups['Remove group']
	);

	($hook = get_hook('agr_del_group_pre_header_load')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

	define('FORUM_PAGE_SECTION', 'users');
	define('FORUM_PAGE', 'admin-groups');
	define('FORUM_PAGE_TYPE', 'sectioned');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

	($hook = get_hook('agr_del_group_output_start')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php printf($lang_admin_groups['Remove group head'], forum_htmlencode($group_title), $num_members) ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_groups']) ?>?del_group=<?php echo $group_id ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_groups']).'?del_group='.$group_id) ?>" />
			</div>
			<fieldset class="frm-group group-item<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><span><?php echo $lang_admin_groups['Remove group legend'] ?></span></legend>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box select">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_groups['Move users to'] ?></span> <small><?php echo $lang_admin_groups['Remove group help'] ?></small></label><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="move_to_group">
<?php

	$query = array(
		'SELECT'	=> 'g.g_id, g.g_title',
		'FROM'		=> 'groups AS g',
		'WHERE'		=> 'g.g_id!='.FORUM_GUEST.' AND g.g_id!='.$group_id,
		'ORDER BY'	=> 'g.g_title'
	);

	($hook = get_hook('agr_qr_get_groups')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	while ($cur_group = $forum_db->fetch_assoc($result))
	{
		if ($cur_group['g_id'] == $forum_config['o_default_user_group'])	// Pre-select the default Members group
			echo "\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.forum_htmlencode($cur_group['g_title']).'</option>'."\n";
		else
			echo "\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.forum_htmlencode($cur_group['g_title']).'</option>'."\n";
	}

?>

						</select></span>
					</div>
				</div>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="del_group" value="<?php echo $lang_admin_groups['Remove group'] ?>" /></span>
				<span class="cancel"><input type="submit" name="del_group_cancel" value="<?php echo $lang_admin_common['Cancel'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

	$tpl_temp = forum_trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}


// Setup the form
$forum_page['item_count'] = $forum_page['fld_count'] = $forum_page['group_count'] = 0;

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
	$lang_admin_common['Groups']
);

($hook = get_hook('agr_pre_header_load')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

define('FORUM_PAGE_SECTION', 'users');
define('FORUM_PAGE', 'admin-groups');
define('FORUM_PAGE_TYPE', 'sectioned');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('agr_main_output_start')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_admin_groups['Add group heading'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_groups']) ?>?action=foo">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_groups']).'?action=foo') ?>" />
			</div>
			<fieldset class="frm-group group-item<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><span><?php echo $lang_admin_groups['Add group legend'] ?></span></legend>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box select">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_groups['Base new group label'] ?></span></label><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="base_group">
<?php

$query = array(
	'SELECT'	=> 'g.g_id, g.g_title',
	'FROM'		=> 'groups AS g',
	'WHERE'		=> 'g_id>'.FORUM_GUEST,
	'ORDER BY'	=> 'g.g_title'
);

($hook = get_hook('agr_qr_get_groups2')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
while ($cur_group = $forum_db->fetch_assoc($result))
{
	if ($cur_group['g_id'] == $forum_config['o_default_user_group'])
		echo "\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.forum_htmlencode($cur_group['g_title']).'</option>'."\n";
	else
		echo "\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.forum_htmlencode($cur_group['g_title']).'</option>'."\n";
}

?>
						</select></span>
					</div>
				</div>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="add_group" value="<?php echo $lang_admin_groups['Add group'] ?> " /></span>
			</div>
		</form>
	</div>
<?php

	// Reset fieldset counter
	$forum_page['group_count'] = $forum_page['item_count'] = 0;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_admin_groups['Default group heading'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_groups']) ?>?action=foo">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_groups']).'?action=foo') ?>" />
			</div>
			<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><span><?php echo $lang_admin_groups['Default group legend'] ?></span></legend>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box select">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_groups['Default group label'] ?></span></label><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="default_group">
<?php

$query = array(
	'SELECT'	=> 'g.g_id, g.g_title',
	'FROM'		=> 'groups AS g',
	'WHERE'		=> 'g_id>'.FORUM_GUEST.' AND g_moderator=0',
	'ORDER BY'	=> 'g.g_title'
);

($hook = get_hook('agr_qr_get_groups3')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
while ($cur_group = $forum_db->fetch_assoc($result))
{
	if ($cur_group['g_id'] == $forum_config['o_default_user_group'])
		echo "\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.forum_htmlencode($cur_group['g_title']).'</option>'."\n";
	else
		echo "\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.forum_htmlencode($cur_group['g_title']).'</option>'."\n";
}

?>
						</select></span>
					</div>
				</div>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" class="button" name="set_default_group" value="<?php echo $lang_admin_groups['Set default'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

	// Reset counter
	$forum_page['group_count'] = $forum_page['item_count'] = 0;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_admin_groups['Existing groups heading'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<div class="ct-box">
			<p><?php echo $lang_admin_groups['Existing groups intro'] ?></p>
		</div>
		<div class="ct-group frm-item<?php echo ++$forum_page['group_count'] ?>">
<?php

$query = array(
	'SELECT'	=> 'g.g_id, g.g_title',
	'FROM'		=> 'groups AS g',
	'ORDER BY'	=> 'g.g_title'
);

($hook = get_hook('agr_qr_get_groups4')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
$forum_page['item_num'] = 0;
while ($cur_group = $forum_db->fetch_assoc($result))
{

	$forum_page['group_options'] = array();
	$forum_page['group_options']['edit'] = '<span'.((empty($forum_page['group_options'])) ? ' class="item1"' : '').'><a href="'.forum_link($forum_url['admin_groups']).'?edit_group='.$cur_group['g_id'].'">'.$lang_admin_groups['Edit group'].'</a></span>';

	if ($cur_group['g_id'] > FORUM_GUEST)
	{
		if ($cur_group['g_id'] != $forum_config['o_default_user_group'])
			$forum_page['group_options']['remove'] = '<span'.((empty($forum_page['group_options'])) ? ' class="item1"' : '').'><a href="'.forum_link($forum_url['admin_groups']).'?del_group='.$cur_group['g_id'].'">'.$lang_admin_groups['Remove group'].'</a></span>';
		else
			$forum_page['group_options']['remove'] = '<span'.((empty($forum_page['group_options'])) ? ' class="item1"' : '').'>'.$lang_admin_groups['Cannot remove default'].'</span>';
	}
	else
		$forum_page['group_options']['remove'] = '<span'.((empty($forum_page['group_options'])) ? ' class="item1"' : '').'>'.$lang_admin_groups['Cannot remove group'].'</span>';

?>
			<div class="ct-set group-item<?php echo ++$forum_page['item_count'] ?>">
				<div class="ct-box">
					<h3 class="set-legend hn"><span><?php echo forum_htmlencode($cur_group['g_title']) ?> <?php echo ($cur_group['g_id'] == $forum_config['o_default_user_group']) ? $lang_admin_groups['default'] : '' ?></span></h3>
					<p class="options"><?php echo implode(' ', $forum_page['group_options']) ?></p>
				</div>
			</div>
<?php

}

?>
		</div>
	</div>
<?php

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
