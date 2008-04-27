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

($hook = get_hook('afo_start')) ? eval($hook) : null;

if ($forum_user['g_id'] != FORUM_ADMIN)
	message($lang_common['No permission']);

// Load the admin.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin.php';


// Add a "default" forum
if (isset($_POST['add_forum']))
{
	$add_to_cat = intval($_POST['add_to_cat']);
	if ($add_to_cat < 1)
		message($lang_common['Bad request']);

	$forum_name = trim($_POST['forum_name']);
	$position = intval($_POST['position']);

	($hook = get_hook('afo_add_forum_form_submitted')) ? eval($hook) : null;

	if ($forum_name == '')
		message($lang_admin['Must enter forum message']);

	$query = array(
		'INSERT'	=> 'forum_name, disp_position, cat_id',
		'INTO'		=> 'forums',
		'VALUES'	=> '\''.$forum_db->escape($forum_name).'\', '.$position.', '.$add_to_cat
	);

	($hook = get_hook('afo_qr_add_forum')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	// Regenerate the quickjump cache
	require_once FORUM_ROOT.'include/cache.php';
	generate_quickjump_cache();

	redirect(forum_link($forum_url['admin_forums']), $lang_admin['Forum added'].' '.$lang_admin['Redirect']);
}


// Delete a forum
else if (isset($_GET['del_forum']))
{
	$forum_to_delete = intval($_GET['del_forum']);
	if ($forum_to_delete < 1)
		message($lang_common['Bad request']);

	// User pressed the cancel button
	if (isset($_POST['del_forum_cancel']))
		redirect(forum_link($forum_url['admin_forums']), $lang_admin['Cancel redirect']);

	($hook = get_hook('afo_del_forum_form_submitted')) ? eval($hook) : null;

	if (isset($_POST['del_forum_comply']))	// Delete a forum with all posts
	{
		@set_time_limit(0);

		// Prune all posts and topics
		prune($forum_to_delete, 1, -1);

		delete_orphans();

		// Delete the forum and any forum specific group permissions
		$query = array(
			'DELETE'	=> 'forums',
			'WHERE'		=> 'id='.$forum_to_delete
		);

		($hook = get_hook('afo_qr_delete_forum')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		$query = array(
			'DELETE'	=> 'forum_perms',
			'WHERE'		=> 'forum_id='.$forum_to_delete
		);

		($hook = get_hook('afo_qr_delete_forum_perms')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// Regenerate the quickjump cache
		require_once FORUM_ROOT.'include/cache.php';
		generate_quickjump_cache();

		redirect(forum_link($forum_url['admin_forums']), $lang_admin['Forum deleted'].' '.$lang_admin['Redirect']);
	}
	else	// If the user hasn't confirmed the delete
	{
		$query = array(
			'SELECT'	=> 'f.forum_name',
			'FROM'		=> 'forums AS f',
			'WHERE'		=> 'f.id='.$forum_to_delete
		);

		($hook = get_hook('afo_qr_get_forum_name')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		$forum_name = $forum_db->result($result);


		// Setup breadcrumbs
		$forum_page['crumbs'] = array(
			array($forum_config['o_board_title'], forum_link($forum_url['index'])),
			array($lang_admin['Forum administration'], forum_link($forum_url['admin_index'])),
			array($lang_admin['Forums'], forum_link($forum_url['admin_forums'])),
			$lang_admin['Delete forum']
		);

		($hook = get_hook('afo_del_forum_pre_header_load')) ? eval($hook) : null;

		define('FORUM_PAGE_SECTION', 'start');
		define('FORUM_PAGE', 'admin-forums');
		require FORUM_ROOT.'header.php';

?>
<div id="brd-main" class="main sectioned admin">

<?php echo generate_admin_menu(); ?>

	<div class="main-head">
		<h1><span>{ <?php echo end($forum_page['crumbs']) ?> }</span></h1>
	</div>

	<div class="main-content frm">
		<div class="frm-head">
			<h2><span><?php printf($lang_admin['Confirm delete forum'], forum_htmlencode($forum_name)) ?></span></h2>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_forums']) ?>?del_forum=<?php echo $forum_to_delete ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_forums']).'?del_forum='.$forum_to_delete) ?>" />
			</div>
			<div class="frm-info">
				<p class="warn"><?php echo $lang_admin['Delete forum warning'] ?></p>
			</div>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="del_forum_comply" value="<?php echo $lang_admin['Delete'] ?>" /></span>
				<span class="cancel"><input type="submit" name="del_forum_cancel" value="<?php echo $lang_admin['Cancel'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

		require FORUM_ROOT.'footer.php';
	}
}


// Update forum positions
else if (isset($_POST['update_positions']))
{
	$positions = array_map('intval', $_POST['position']);

	($hook = get_hook('afo_update_positions_form_submitted')) ? eval($hook) : null;

	$query = array(
		'SELECT'	=> 'f.id, f.disp_position',
		'FROM'		=> 'categories AS c',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'forums AS f',
				'ON'			=> 'c.id=f.cat_id'
			)
		),
		'ORDER BY'	=> 'c.disp_position, c.id, f.disp_position'
	);

	($hook = get_hook('afo_qr_get_forums')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	while ($cur_forum = $forum_db->fetch_assoc($result))
	{
		// If these aren't set, we're looking at a forum that was added after
		// the admin started editing: we don't want to mess with it
		if (isset($positions[$cur_forum['id']]))
		{
			$new_disp_position = $positions[$cur_forum['id']];

			if ($new_disp_position < 0)
				message($lang_admin['Must be integer']);

			// We only want to update if we changed the position
			if ($cur_forum['disp_position'] != $new_disp_position)
			{
				$query = array(
					'UPDATE'	=> 'forums',
					'SET'		=> 'disp_position='.$new_disp_position,
					'WHERE'		=> 'id='.$cur_forum['id']
				);

				($hook = get_hook('afo_qr_update_forum_position')) ? eval($hook) : null;
				$forum_db->query_build($query) or error(__FILE__, __LINE__);
			}
		}
	}

	// Regenerate the quickjump cache
	require_once FORUM_ROOT.'include/cache.php';
	generate_quickjump_cache();

	redirect(forum_link($forum_url['admin_forums']), $lang_admin['Forums updated'].' '.$lang_admin['Redirect']);
}


else if (isset($_GET['edit_forum']))
{
	$forum_id = intval($_GET['edit_forum']);
	if ($forum_id < 1)
		message($lang_common['Bad request']);

	// Fetch forum info
	$query = array(
		'SELECT'	=> 'f.id, f.forum_name, f.forum_desc, f.redirect_url, f.num_topics, f.sort_by, f.cat_id',
		'FROM'		=> 'forums AS f',
		'WHERE'		=> 'id='.$forum_id
	);

	($hook = get_hook('afo_qr_get_forum_details')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	if (!$forum_db->num_rows($result))
		message($lang_common['Bad request']);

	$cur_forum = $forum_db->fetch_assoc($result);

	// Update group permissions for $forum_id
	if (isset($_POST['save']))
	{
		($hook = get_hook('afo_save_forum_form_submitted')) ? eval($hook) : null;

		// Start with the forum details
		$forum_name = trim($_POST['forum_name']);
		$forum_desc = forum_linebreaks(trim($_POST['forum_desc']));
		$cat_id = intval($_POST['cat_id']);
		$sort_by = intval($_POST['sort_by']);
		$redirect_url = isset($_POST['redirect_url']) && $cur_forum['num_topics'] == 0 ? trim($_POST['redirect_url']) : null;

		if ($forum_name == '')
			message($lang_admin['Must enter forum message']);

		if ($cat_id < 1)
			message($lang_common['Bad request']);

		$forum_desc = ($forum_desc != '') ? '\''.$forum_db->escape($forum_desc).'\'' : 'NULL';
		$redirect_url = ($redirect_url != '') ? '\''.$forum_db->escape($redirect_url).'\'' : 'NULL';

		$query = array(
			'UPDATE'	=> 'forums',
			'SET'		=> 'forum_name=\''.$forum_db->escape($forum_name).'\', forum_desc='.$forum_desc.', redirect_url='.$redirect_url.', sort_by='.$sort_by.', cat_id='.$cat_id,
			'WHERE'		=> 'id='.$forum_id
		);

		($hook = get_hook('afo_qr_update_forum')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// Now let's deal with the permissions
		if (isset($_POST['read_forum_old']))
		{
			$query = array(
				'SELECT'	=> 'g.g_id, g.g_read_board, g.g_post_replies, g.g_post_topics',
				'FROM'		=> 'groups AS g',
				'WHERE'		=> 'g_id!='.FORUM_ADMIN
			);

			($hook = get_hook('afo_qr_get_groups')) ? eval($hook) : null;
			$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
			while ($cur_group = $forum_db->fetch_assoc($result))
			{
				// The default permissions for this group
				$perms_default = array(
					'read_forum'	=>	$cur_group['g_read_board'],
					'post_replies'	=>	$cur_group['g_post_replies'],
					'post_topics'	=>	$cur_group['g_post_topics']
				);

				// The old permissions for this group
				$perms_old = array(
					'read_forum'	=>	$_POST['read_forum_old'][$cur_group['g_id']],
					'post_replies'	=>	$_POST['post_replies_old'][$cur_group['g_id']],
					'post_topics'	=>	$_POST['post_topics_old'][$cur_group['g_id']]
				);

				// The new permissions for this group
				$perms_new = array(
					'read_forum'	=>	($cur_group['g_read_board'] == '1') ? isset($_POST['read_forum_new'][$cur_group['g_id']]) ? '1' : '0' : intval($_POST['read_forum_old'][$cur_group['g_id']]),
					'post_replies'	=>	isset($_POST['post_replies_new'][$cur_group['g_id']]) ? '1' : '0',
					'post_topics'	=>	isset($_POST['post_topics_new'][$cur_group['g_id']]) ? '1' : '0'
				);

				($hook = get_hook('afo_pre_perms_compare')) ? eval($hook) : null;

				// Force all permissions values to integers
				$perms_default = array_map('intval', $perms_default);
				$perms_old = array_map('intval', $perms_old);
				$perms_new = array_map('intval', $perms_new);

				// Check if the new permissions differ from the old
				if ($perms_new !== $perms_old)
				{
					// If the new permissions are identical to the default permissions for this group, delete its row in forum_perms
					if ($perms_new === $perms_default)
					{
						$query = array(
							'DELETE'	=> 'forum_perms',
							'WHERE'		=> 'group_id='.$cur_group['g_id'].' AND forum_id='.$forum_id
						);

						($hook = get_hook('afo_qr_delete_group_forum_perms')) ? eval($hook) : null;
						$forum_db->query_build($query) or error(__FILE__, __LINE__);
					}
					else
					{
						// Run an UPDATE and see if it affected a row, if not, INSERT
						$query = array(
							'UPDATE'	=> 'forum_perms',
							'WHERE'		=> 'group_id='.$cur_group['g_id'].' AND forum_id='.$forum_id
						);

						$temp = array();
						while (list($key, $value) = @each($perms_new))
							$temp[] = $key.'='.$value;

						$query['SET'] = implode(', ', $temp);

						($hook = get_hook('afo_qr_update_forum_perms')) ? eval($hook) : null;
						$forum_db->query_build($query) or error(__FILE__, __LINE__);
						if (!$forum_db->affected_rows())
						{
							$query = array(
								'INSERT'	=> 'group_id, forum_id',
								'INTO'		=> 'forum_perms',
								'VALUES'	=> $cur_group['g_id'].', '.$forum_id
							);

							$query['INSERT'] .= ', '.implode(', ', array_keys($perms_new));
							$query['VALUES'] .= ', '.implode(', ', $perms_new);

							($hook = get_hook('afo_qr_add_forum_perms')) ? eval($hook) : null;
							$forum_db->query_build($query) or error(__FILE__, __LINE__);
						}
					}
				}
			}
		}

		// Regenerate the quickjump cache
		require_once FORUM_ROOT.'include/cache.php';
		generate_quickjump_cache();

		redirect(forum_link($forum_url['admin_forums']), $lang_admin['Forum updated'].' '.$lang_admin['Redirect']);
	}
	else if (isset($_POST['revert_perms']))
	{
		($hook = get_hook('afo_revert_perms_form_submitted')) ? eval($hook) : null;

		$query = array(
			'DELETE'	=> 'forum_perms',
			'WHERE'		=> 'forum_id='.$forum_id
		);

		($hook = get_hook('afo_qr_revert_forum_perms')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// Regenerate the quickjump cache
		require_once FORUM_ROOT.'include/cache.php';
		generate_quickjump_cache();

		redirect(forum_link($forum_url['admin_forums']).'?edit_forum='.$forum_id, $lang_admin['Permissions reverted'].' '.$lang_admin['Redirect']);
	}

	$forum_page['form_info'] = array();
	if ($cur_forum['redirect_url'])
		$forum_page['form_info'][] = '<li><span>'.$lang_admin['Forum perms info 2'].'</span></li>';

	$forum_page['form_info'][] = '<li><span>'.$lang_admin['Forum perms info 1'].'</span></li>';
	$forum_page['form_info'][] = '<li><span>'.$lang_admin['Forum perms info 3'].'</span></li>';
	$forum_page['form_info'][] = '<li><span>'. sprintf($lang_admin['Group key'], '<a href="'.forum_link($forum_url['admin_groups']).'">'.$lang_admin['User groups'].'</a>').'</span></li>';

	// Setup the form
	$forum_page['part_count'] = $forum_page['set_count'] = $forum_page['fld_count'] = 0;

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin['Forum administration'], forum_link($forum_url['admin_index'])),
		array($lang_admin['Forums'], forum_link($forum_url['admin_forums'])),
		$lang_admin['Edit forum']
	);

	($hook = get_hook('afo_edit_forum_pre_header_load')) ? eval($hook) : null;

	define('FORUM_PAGE_SECTION', 'start');
	define('FORUM_PAGE', 'admin-forums');
	require FORUM_ROOT.'header.php';

?>
<div id="brd-main" class="main sectioned admin">

<?php echo generate_admin_menu(); ?>

	<div class="main-head">
		<h1><span>{ <?php echo end($forum_page['crumbs']) ?> }</span></h1>
	</div>

	<form method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_forums']) ?>?edit_forum=<?php echo $forum_id ?>">

	<div class="main-content frm parted">
		<div class="hidden">
			<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_forums']).'?edit_forum='.$forum_id) ?>" />
		</div>
		<div class="frm-head">
			<h2><span><?php echo $lang_admin['Edit forum head'] ?></span></h2>
		</div>
		<div class="frm-form">
<?php ($hook = get_hook('afo_edit_forum_pre_details_part')) ? eval($hook) : null; ?>
			<div class="frm-part part<?php echo ++ $forum_page['part_count'] ?>">
				<h3><span><?php printf($lang_admin['Edit details head'], $forum_page['part_count']) ?></span></h3>
				<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
					<legend class="frm-legend"><strong><?php echo $lang_admin['Edit forum details legend'] ?></strong></legend>
					<div class="frm-fld text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
							<span class="fld-label"><?php echo $lang_admin['Forum name'] ?></span><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="forum_name" size="35" maxlength="80" value="<?php echo forum_htmlencode($cur_forum['forum_name']) ?>" /></span>
						</label>
					</div>
					<div class="frm-fld text textarea">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
							<span class="fld-label"><?php echo $lang_admin['Forum description'] ?></span><br />
							<span class="fld-input"><textarea id="fld<?php echo $forum_page['fld_count'] ?>" name="forum_desc" rows="3" cols="50"><?php echo forum_htmlencode($cur_forum['forum_desc']) ?></textarea></span>
							<span class="fld-help"><?php echo $lang_admin['Forum description help'] ?></span>
						</label>
					</div>
					<div class="frm-fld select">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
							<span class="fld-label"><?php echo $lang_admin['Category assignment'] ?></span><br />
							<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="cat_id">
<?php

	$query = array(
		'SELECT'	=> 'c.id, c.cat_name',
		'FROM'		=> 'categories AS c',
		'ORDER BY'	=> 'c.disp_position'
	);

	($hook = get_hook('afo_qr_get_categories')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	while ($cur_cat = $forum_db->fetch_assoc($result))
	{
		$selected = ($cur_cat['id'] == $cur_forum['cat_id']) ? ' selected="selected"' : '';
		echo "\t\t\t\t\t\t\t\t".'<option value="'.$cur_cat['id'].'"'.$selected.'>'.forum_htmlencode($cur_cat['cat_name']).'</option>'."\n";
	}

?>
								</select></span>
						</label>
					</div>
					<div class="frm-fld select">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
							<span class="fld-label"><?php echo $lang_admin['Sort topics by'] ?></span><br />
							<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="sort_by">
									<option value="0"<?php if ($cur_forum['sort_by'] == '0') echo ' selected="selected"' ?>><?php echo $lang_admin['Sort last post'] ?></option>
									<option value="1"<?php if ($cur_forum['sort_by'] == '1') echo ' selected="selected"' ?>><?php echo $lang_admin['Sort topic start'] ?></option>
							</select></span>
						</label>
					</div>
					<div class="frm-fld text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
							<span class="fld-label"><?php echo $lang_admin['Redirect URL'] ?></span><br />
							<span class="fld-input"><?php echo ($cur_forum['num_topics']) ? '<input type="text" id="fld'.$forum_page['fld_count'].'" name="redirect_url" size="45" maxlength="100" value="'.$lang_admin['Only for empty forums'].'" disabled="disabled" />' : '<input type="text" id="fld'.$forum_page['fld_count'].'" name="redirect_url" size="45" maxlength="100" value="'.forum_htmlencode($cur_forum['redirect_url']).'" />'; ?></span>
						</label>
					</div>
<?php ($hook = get_hook('afo_edit_forum_details_end')) ? eval($hook) : null; ?>
				</fieldset>
			</div>
<?php

// Reset fieldset counter
$forum_page['set_count'] = 0;

($hook = get_hook('afo_edit_forum_pre_permissions_part')) ? eval($hook) : null;

?>
			<div class="frm-part part<?php echo ++ $forum_page['part_count'] ?>">
				<h3><span><?php printf($lang_admin['Edit permissions head'], $forum_page['part_count']) ?></span></h3>
				<div class="frm-info">
					<ul>
						<?php echo implode("\n\t\t\t\t\t", $forum_page['form_info'])."\n" ?>
					</ul>
				</div>
				<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
					<legend class="frm-legend"><strong><?php echo $lang_admin['Edit forum perms legend'] ?></strong></legend>
<?php

	$i = 2;

	$query = array(
		'SELECT'	=> 'g.g_id, g.g_title, g.g_read_board, g.g_post_replies, g.g_post_topics, fp.read_forum, fp.post_replies, fp.post_topics',
		'FROM'		=> 'groups AS g',
		'JOINS'		=> array(
			array(
				'LEFT JOIN'		=> 'forum_perms AS fp',
				'ON'			=> 'g.g_id=fp.group_id AND fp.forum_id='.$forum_id
			)
		),
		'WHERE'		=> 'g.g_id!='.FORUM_ADMIN,
		'ORDER BY'	=> 'g.g_id'
	);

	($hook = get_hook('afo_qr_get_forum_perms')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	while ($cur_perm = $forum_db->fetch_assoc($result))
	{
		$read_forum = ($cur_perm['read_forum'] != '0') ? true : false;
		$post_replies = (($cur_perm['g_post_replies'] == '0' && $cur_perm['post_replies'] == '1') || ($cur_perm['g_post_replies'] == '1' && $cur_perm['post_replies'] != '0')) ? true : false;
		$post_topics = (($cur_perm['g_post_topics'] == '0' && $cur_perm['post_topics'] == '1') || ($cur_perm['g_post_topics'] == '1' && $cur_perm['post_topics'] != '0')) ? true : false;

		// Determine if the current sittings differ from the default or not
		$read_forum_def = ($cur_perm['read_forum'] == '0') ? false : true;
		$post_replies_def = (($post_replies && $cur_perm['g_post_replies'] == '0') || (!$post_replies && ($cur_perm['g_post_replies'] == '' || $cur_perm['g_post_replies'] == '1'))) ? false : true;
		$post_topics_def = (($post_topics && $cur_perm['g_post_topics'] == '0') || (!$post_topics && ($cur_perm['g_post_topics'] == '' || $cur_perm['g_post_topics'] == '1'))) ? false : true;

?>
					<fieldset class="frm-group">
						<legend><span><?php echo forum_htmlencode($cur_perm['g_title']) ?></span></legend>
						<div class="radbox frm-choice">
							<input type="hidden" name="read_forum_old[<?php echo $cur_perm['g_id'] ?>]" value="<?php echo ($read_forum) ? '1' : '0'; ?>" />
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"<?php if (!$read_forum_def) echo ' class="warn"' ?>><input type="checkbox" id="fld<?php echo $forum_page['fld_count'] ?>" name="read_forum_new[<?php echo $cur_perm['g_id'] ?>]" value="1"<?php if ($read_forum) echo ' checked="checked"'; echo ($cur_perm['g_read_board'] == '0') ? ' disabled="disabled"' : ''; ?> /> <?php echo $lang_admin['Read forum'] ?> <?php if (!$read_forum_def) echo $lang_admin['Not default']  ?></label>
							<input type="hidden" name="post_replies_old[<?php echo $cur_perm['g_id'] ?>]" value="<?php echo ($post_replies) ? '1' : '0'; ?>" />
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"<?php if (!$post_replies_def) echo ' class="warn"'; ?>><input type="checkbox" id="fld<?php echo $forum_page['fld_count'] ?>" name="post_replies_new[<?php echo $cur_perm['g_id'] ?>]" value="1"<?php if ($post_replies) echo ' checked="checked"'; echo ($cur_forum['redirect_url'] != '') ? ' disabled="disabled"' : ''; ?> /> <?php echo $lang_admin['Post replies'] ?> <?php if (!$post_replies_def) echo $lang_admin['Not default'] ?></label>
							<input type="hidden" name="post_topics_old[<?php echo $cur_perm['g_id'] ?>]" value="<?php echo ($post_topics) ? '1' : '0'; ?>" />
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"<?php if (!$post_topics_def) echo ' class="warn"'; ?>><input type="checkbox" id="fld<?php echo $forum_page['fld_count'] ?>" name="post_topics_new[<?php echo $cur_perm['g_id'] ?>]" value="1"<?php if ($post_topics) echo ' checked="checked"'; echo ($cur_forum['redirect_url'] != '') ? ' disabled="disabled"' : ''; ?> /> <?php echo $lang_admin['Post topics'] ?> <?php if (!$post_topics_def) echo $lang_admin['Not default'] ?></label>
<?php ($hook = get_hook('afo_edit_forum_new_permission')) ? eval($hook) : null; ?>
						</div>
					</fieldset>
<?php

		++$i;
	}

?>
					<p class="frm-fld link"><span class="fld-label"><?php echo $lang_admin['Administrators'] ?></span> <span class="fld-input"><?php echo $lang_admin['Admin full perms'] ?></span></p>
<?php ($hook = get_hook('afo_edit_forum_permissions_end')) ? eval($hook) : null; ?>
				</fieldset>
			</div>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="save" value="<?php echo $lang_admin['Save changes'] ?>" /></span>
				<span class="submit"><input type="submit" name="revert_perms" value="<?php echo $lang_admin['Restore defaults'] ?>" /></span>
			</div>
		</div>
	</div>
	</form>

</div>

<?php

	require FORUM_ROOT.'footer.php';
}

// Setup the form
$forum_page['fld_count'] = $forum_page['set_count'] = 0;

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($lang_admin['Forum administration'], forum_link($forum_url['admin_index'])),
	$lang_admin['Forums']
);

($hook = get_hook('afo_pre_header_load')) ? eval($hook) : null;

define('FORUM_PAGE_SECTION', 'start');
define('FORUM_PAGE', 'admin-forums');
require FORUM_ROOT.'header.php';

?>
<div id="brd-main" class="main sectioned admin">

<?php echo generate_admin_menu(); ?>

	<div class="main-head">
		<h1><span>{ <?php echo end($forum_page['crumbs']) ?> }</span></h1>
	</div>

	<div class="main-content frm">
		<div class="frm-head">
			<h2><span><?php echo $lang_admin['Add forum head'] ?></span></h2>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_forums']) ?>?action=adddel">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_forums']).'?action=adddel') ?>" />
			</div>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_admin['Add forum legend'] ?></strong></legend>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Forum name'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="forum_name" size="35" maxlength="80" /></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Position'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="position" size="3" maxlength="3" /></span>
						<span class="fld-extra"><?php echo $lang_admin['Forum position help'] ?></span>
					</label>
				</div>
				<div class="frm-fld select">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Add to category'] ?></span><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="add_to_cat">
<?php

	$query = array(
		'SELECT'	=> 'c.id, c.cat_name',
		'FROM'		=> 'categories AS c',
		'ORDER BY'	=> 'c.disp_position'
	);

	($hook = get_hook('afo_qr_get_categories2')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	while ($cur_cat = $forum_db->fetch_assoc($result))
		echo "\t\t\t\t\t\t\t".'<option value="'.$cur_cat['id'].'">'.forum_htmlencode($cur_cat['cat_name']).'</option>'."\n";

?>
						</select></span>
					</label>
				</div>
<?php ($hook = get_hook('afo_add_forum_fieldset_end')) ? eval($hook) : null; ?>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" class="button" name="add_forum" value=" <?php echo $lang_admin['Add forum'] ?> " /></span>
			</div>
		</form>
	</div>

<?php

// Display all the categories and forums
$query = array(
	'SELECT'	=> 'c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.disp_position',
	'FROM'		=> 'categories AS c',
	'JOINS'		=> array(
		array(
			'INNER JOIN'	=> 'forums AS f',
			'ON'			=> 'c.id=f.cat_id'
		)
	),
	'ORDER BY'	=> 'c.disp_position, c.id, f.disp_position'
);

($hook = get_hook('afo_qr_get_cats_and_forums')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

if ($forum_db->num_rows($result))
{
	// Reset fieldset counter
	$forum_page['set_count'] = 0;

?>
	<div class="main-content frm">
		<div class="frm-head">
			<h2><span><?php echo $lang_admin['Edit forums head'] ?></span></h2>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_forums']) ?>?action=edit">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_forums']).'?action=edit') ?>" />
			</div>

<?php

	$cur_category = 0;
	$i = 2;

	while ($cur_forum = $forum_db->fetch_assoc($result))
	{
		if ($cur_forum['cid'] != $cur_category)	// A new category since last iteration?
		{
			if ($i > 2) echo "\t\t\t".'</fieldset>'."\n";

?>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo forum_htmlencode($cur_forum['cat_name']) ?></strong></legend>
				<h3 class="frm-fld link">
					<span class="fld-label"><?php echo $lang_admin['Category'] ?></span>
					<span class="fld-input">[ <?php echo forum_htmlencode($cur_forum['cat_name']) ?> ]</span>
				</h3>
<?php

			$cur_category = $cur_forum['cid'];
		}

?>
				<div class="frm-fld text twin">
					<span class="fld-label"><a href="<?php echo forum_link($forum_url['admin_forums']) ?>?edit_forum=<?php echo $cur_forum['fid'] ?>"><span><?php echo $lang_admin['Edit'].'<span> '.forum_htmlencode($cur_forum['forum_name']).' </span></span>' ?></a><br /> <a href="<?php echo forum_link($forum_url['admin_forums']) ?>?del_forum=<?php echo $cur_forum['fid'] ?>"><span><?php echo $lang_admin['Delete'].'<span> '.forum_htmlencode($cur_forum['forum_name']).'</span></span>' ?></a></span><br />
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>" class="twin2">
						<span class="fld-label"><?php echo $lang_admin['Position'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="position[<?php echo $cur_forum['fid'] ?>]" size="3" maxlength="3" value="<?php echo $cur_forum['disp_position'] ?>" /></span>
						<span class="fld-extra"><?php echo forum_htmlencode($cur_forum['forum_name']) ?></span>
					</label>
				</div>
<?php

		++$i;
	}

?>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" class="button" name="update_positions" value="<?php echo $lang_admin['Update positions'] ?>" /></span>
			</div>
		</form>
<?php

}

?>
	</div>

</div>
<?php

require FORUM_ROOT.'footer.php';
