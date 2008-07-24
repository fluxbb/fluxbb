<?php
/**
 * Forum management page
 *
 * Allows administrators to add, modify, and remove forums.
 *
 * @copyright Copyright (C) 2008 FluxBB.org, based on code copyright (C) 2002-2008 PunBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package FluxBB
 */


if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', '../');
require FORUM_ROOT.'include/common.php';
require FORUM_ROOT.'include/common_admin.php';

($hook = get_hook('afo_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

if ($forum_user['g_id'] != FORUM_ADMIN)
	message($lang_common['No permission']);

// Load the admin.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin_common.php';
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin_forums.php';


// Add a "default" forum
if (isset($_POST['add_forum']))
{
	$add_to_cat = isset($_POST['add_to_cat']) ? intval($_POST['add_to_cat']) : 0;
	if ($add_to_cat < 1)
		message($lang_common['Bad request']);

	$forum_name = forum_trim($_POST['forum_name']);
	$position = intval($_POST['position']);

	($hook = get_hook('afo_add_forum_form_submitted')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	if ($forum_name == '')
		message($lang_admin_forums['Must enter forum message']);

	// Make sure the category we're adding to exists
	$query = array(
		'SELECT'	=> '1',
		'FROM'		=> 'categories AS c',
		'WHERE'		=> 'c.id='.$add_to_cat
	);

	($hook = get_hook('afo_qr_validate_category_id')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	if (!$forum_db->num_rows($result))
		message($lang_common['Bad request']);

	$query = array(
		'INSERT'	=> 'forum_name, disp_position, cat_id',
		'INTO'		=> 'forums',
		'VALUES'	=> '\''.$forum_db->escape($forum_name).'\', '.$position.', '.$add_to_cat
	);

	($hook = get_hook('afo_qr_add_forum')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	// Regenerate the quickjump cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';

	generate_quickjump_cache();

	redirect(forum_link($forum_url['admin_forums']), $lang_admin_forums['Forum added'].' '.$lang_admin_common['Redirect']);
}


// Delete a forum
else if (isset($_GET['del_forum']))
{
	$forum_to_delete = intval($_GET['del_forum']);
	if ($forum_to_delete < 1)
		message($lang_common['Bad request']);

	// User pressed the cancel button
	if (isset($_POST['del_forum_cancel']))
		redirect(forum_link($forum_url['admin_forums']), $lang_admin_common['Cancel redirect']);

	($hook = get_hook('afo_del_forum_form_submitted')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

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

		($hook = get_hook('afo_qr_delete_forum')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		$query = array(
			'DELETE'	=> 'forum_perms',
			'WHERE'		=> 'forum_id='.$forum_to_delete
		);

		($hook = get_hook('afo_qr_delete_forum_perms')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// Regenerate the quickjump cache
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			require FORUM_ROOT.'include/cache.php';

		generate_quickjump_cache();

		redirect(forum_link($forum_url['admin_forums']), $lang_admin_forums['Forum deleted'].' '.$lang_admin_common['Redirect']);
	}
	else	// If the user hasn't confirmed the delete
	{
		$query = array(
			'SELECT'	=> 'f.forum_name',
			'FROM'		=> 'forums AS f',
			'WHERE'		=> 'f.id='.$forum_to_delete
		);

		($hook = get_hook('afo_qr_get_forum_name')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		if (!$forum_db->num_rows($result))
			message($lang_common['Bad request']);

		$forum_name = $forum_db->result($result);


		// Setup breadcrumbs
		$forum_page['crumbs'] = array(
			array($forum_config['o_board_title'], forum_link($forum_url['index'])),
			array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
			array($lang_admin_common['Forums'], forum_link($forum_url['admin_forums'])),
			$lang_admin_forums['Delete forum']
		);

		($hook = get_hook('afo_del_forum_pre_header_load')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		define('FORUM_PAGE_SECTION', 'start');
		define('FORUM_PAGE', 'admin-forums');
		define('FORUM_PAGE_TYPE', 'sectioned');
		require FORUM_ROOT.'header.php';

		// START SUBST - <!-- forum_main -->
		ob_start();

		($hook = get_hook('afo_del_forum_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php printf($lang_admin_forums['Confirm delete forum'], forum_htmlencode($forum_name)) ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_forums']) ?>?del_forum=<?php echo $forum_to_delete ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_forums']).'?del_forum='.$forum_to_delete) ?>" />
			</div>
			<div class="ct-box warn-box">
				<p class="warn"><?php echo $lang_admin_forums['Delete forum warning'] ?></p>
			</div>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="del_forum_comply" value="<?php echo $lang_admin_forums['Delete forum'] ?>" /></span>
				<span class="cancel"><input type="submit" name="del_forum_cancel" value="<?php echo $lang_admin_common['Cancel'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

		$tpl_temp = forum_trim(ob_get_contents());
		$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
		ob_end_clean();
		// END SUBST - <!-- forum_main -->

		require FORUM_ROOT.'footer.php';
	}
}


// Update forum positions
else if (isset($_POST['update_positions']))
{
	$positions = array_map('intval', $_POST['position']);

	($hook = get_hook('afo_update_positions_form_submitted')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

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

	($hook = get_hook('afo_qr_get_forums')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	while ($cur_forum = $forum_db->fetch_assoc($result))
	{
		// If these aren't set, we're looking at a forum that was added after
		// the admin started editing: we don't want to mess with it
		if (isset($positions[$cur_forum['id']]))
		{
			$new_disp_position = $positions[$cur_forum['id']];

			if ($new_disp_position < 0)
				message($lang_admin_forums['Must be integer']);

			// We only want to update if we changed the position
			if ($cur_forum['disp_position'] != $new_disp_position)
			{
				$query = array(
					'UPDATE'	=> 'forums',
					'SET'		=> 'disp_position='.$new_disp_position,
					'WHERE'		=> 'id='.$cur_forum['id']
				);

				($hook = get_hook('afo_qr_update_forum_position')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
				$forum_db->query_build($query) or error(__FILE__, __LINE__);
			}
		}
	}

	// Regenerate the quickjump cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';

	generate_quickjump_cache();

	redirect(forum_link($forum_url['admin_forums']), $lang_admin_forums['Forums updated'].' '.$lang_admin_common['Redirect']);
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
		'WHERE'		=> 'f.id='.$forum_id
	);

	($hook = get_hook('afo_qr_get_forum_details')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	if (!$forum_db->num_rows($result))
		message($lang_common['Bad request']);

	$cur_forum = $forum_db->fetch_assoc($result);

	// Update group permissions for $forum_id
	if (isset($_POST['save']))
	{
		($hook = get_hook('afo_save_forum_form_submitted')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		// Start with the forum details
		$forum_name = forum_trim($_POST['forum_name']);
		$forum_desc = forum_linebreaks(forum_trim($_POST['forum_desc']));
		$cat_id = intval($_POST['cat_id']);
		$sort_by = intval($_POST['sort_by']);
		$redirect_url = isset($_POST['redirect_url']) && $cur_forum['num_topics'] == 0 ? forum_trim($_POST['redirect_url']) : null;

		if ($forum_name == '')
			message($lang_admin_forums['Must enter forum message']);

		if ($cat_id < 1)
			message($lang_common['Bad request']);

		$forum_desc = ($forum_desc != '') ? '\''.$forum_db->escape($forum_desc).'\'' : 'NULL';
		$redirect_url = ($redirect_url != '') ? '\''.$forum_db->escape($redirect_url).'\'' : 'NULL';

		$query = array(
			'UPDATE'	=> 'forums',
			'SET'		=> 'forum_name=\''.$forum_db->escape($forum_name).'\', forum_desc='.$forum_desc.', redirect_url='.$redirect_url.', sort_by='.$sort_by.', cat_id='.$cat_id,
			'WHERE'		=> 'id='.$forum_id
		);

		($hook = get_hook('afo_qr_update_forum')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// Now let's deal with the permissions
		if (isset($_POST['read_forum_old']))
		{
			$query = array(
				'SELECT'	=> 'g.g_id, g.g_read_board, g.g_post_replies, g.g_post_topics',
				'FROM'		=> 'groups AS g',
				'WHERE'		=> 'g_id!='.FORUM_ADMIN
			);

			($hook = get_hook('afo_qr_get_groups')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
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

				($hook = get_hook('afo_pre_perms_compare')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

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

						($hook = get_hook('afo_qr_delete_group_forum_perms')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
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

						($hook = get_hook('afo_qr_update_forum_perms')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
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

							($hook = get_hook('afo_qr_add_forum_perms')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
							$forum_db->query_build($query) or error(__FILE__, __LINE__);
						}
					}
				}
			}
		}

		// Regenerate the quickjump cache
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			require FORUM_ROOT.'include/cache.php';

		generate_quickjump_cache();

		redirect(forum_link($forum_url['admin_forums']), $lang_admin_forums['Forum updated'].' '.$lang_admin_common['Redirect']);
	}
	else if (isset($_POST['revert_perms']))
	{
		($hook = get_hook('afo_revert_perms_form_submitted')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		$query = array(
			'DELETE'	=> 'forum_perms',
			'WHERE'		=> 'forum_id='.$forum_id
		);

		($hook = get_hook('afo_qr_revert_forum_perms')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// Regenerate the quickjump cache
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			require FORUM_ROOT.'include/cache.php';

		generate_quickjump_cache();

		redirect(forum_link($forum_url['admin_forums']).'?edit_forum='.$forum_id, $lang_admin_forums['Permissions reverted'].' '.$lang_admin_common['Redirect']);
	}

	$forum_page['form_info'] = array();
	if ($cur_forum['redirect_url'])
		$forum_page['form_info'][] = '<li><span>'.$lang_admin_forums['Forum perms info 2'].'</span></li>';

	$forum_page['form_info']['read'] = '<li><span>'.$lang_admin_forums['Forum perms read info'].'</span></li>';
	$forum_page['form_info']['restore'] = '<li><span>'.$lang_admin_forums['Forum perms restore info'].'</span></li>';
	$forum_page['form_info']['groups'] = '<li><span>'. sprintf($lang_admin_forums['Forum perms groups info'], '<a href="'.forum_link($forum_url['admin_groups']).'">'.$lang_admin_forums['User groups'].'</a>').'</span></li>';
	$forum_page['form_info']['admins'] = '<li><span>'.$lang_admin_forums['Forum perms admins info'].'</span></li>';

	// Setup the form
	$forum_page['item_count'] = $forum_page['group_count'] = $forum_page['fld_count'] = 0;

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
		array($lang_admin_common['Forums'], forum_link($forum_url['admin_forums'])),
		$lang_admin_forums['Edit forum']
	);

	($hook = get_hook('afo_edit_forum_pre_header_load')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	define('FORUM_PAGE_SECTION', 'start');
	define('FORUM_PAGE', 'admin-forums');
	define('FORUM_PAGE_TYPE', 'sectioned');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

	($hook = get_hook('afo_edit_forum_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php printf($lang_admin_forums['Edit forum head'], forum_htmlencode($cur_forum['forum_name'])) ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<form method="post" class="frm-form" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_forums']) ?>?edit_forum=<?php echo $forum_id ?>">
		<div class="hidden">
			<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_forums']).'?edit_forum='.$forum_id) ?>" />
		</div>
<?php ($hook = get_hook('afo_edit_forum_pre_details_part')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			<div class="content-head">
				<h3 class="hn"><span><?php  echo $lang_admin_forums['Edit forum details head'] ?></span></h3>
			</div>
			<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_admin_forums['Edit forum details legend'] ?></strong></legend>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_forums['Forum name'] ?></span></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="forum_name" size="35" maxlength="80" value="<?php echo forum_htmlencode($cur_forum['forum_name']) ?>" /></span>
					</div>
				</div>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box textarea">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_forums['Forum description'] ?></span> <small><?php echo $lang_admin_forums['Forum description help'] ?></small></label><br />
						<span class="fld-input"><textarea id="fld<?php echo $forum_page['fld_count'] ?>" name="forum_desc" rows="3" cols="50"><?php echo forum_htmlencode($cur_forum['forum_desc']) ?></textarea></span>
					</div>
				</div>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box select">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_forums['Category assignment'] ?></span></label><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="cat_id">
<?php

	$query = array(
		'SELECT'	=> 'c.id, c.cat_name',
		'FROM'		=> 'categories AS c',
		'ORDER BY'	=> 'c.disp_position'
	);

	($hook = get_hook('afo_qr_get_categories')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	while ($cur_cat = $forum_db->fetch_assoc($result))
	{
		$selected = ($cur_cat['id'] == $cur_forum['cat_id']) ? ' selected="selected"' : '';
		echo "\t\t\t\t\t\t\t\t".'<option value="'.$cur_cat['id'].'"'.$selected.'>'.forum_htmlencode($cur_cat['cat_name']).'</option>'."\n";
	}

?>
						</select></span>
					</div>
				</div>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box select">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_forums['Sort topics by'] ?></span></label><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="sort_by">
							<option value="0"<?php if ($cur_forum['sort_by'] == '0') echo ' selected="selected"' ?>><?php echo $lang_admin_forums['Sort last post'] ?></option>
							<option value="1"<?php if ($cur_forum['sort_by'] == '1') echo ' selected="selected"' ?>><?php echo $lang_admin_forums['Sort topic start'] ?></option>
<?php ($hook = get_hook('afo_edit_forum_modify_sort_by')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>						</select></span>
					</div>
				</div>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_forums['Redirect URL'] ?></span></label><br />
						<span class="fld-input"><?php echo ($cur_forum['num_topics']) ? '<input type="text" id="fld'.$forum_page['fld_count'].'" name="redirect_url" size="45" maxlength="100" value="'.$lang_admin_forums['Only for empty forums'].'" disabled="disabled" />' : '<input type="text" id="fld'.$forum_page['fld_count'].'" name="redirect_url" size="45" maxlength="100" value="'.forum_htmlencode($cur_forum['redirect_url']).'" />'; ?></span>
					</div>
				</div>
<?php ($hook = get_hook('afo_edit_forum_details_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			</fieldset>
<?php

// Reset fieldset counter
$forum_page['group_count'] = $forum_page['item_count'] = 0;

($hook = get_hook('afo_edit_forum_pre_permissions_part')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
			<div class="content-head">
				<h3 class="hn"><span><?php echo $lang_admin_forums['Edit forum perms head'] ?></span></h3>
			</div>
			<div class="ct-box">
				<ul>
					<?php echo implode("\n\t\t\t\t\t", $forum_page['form_info'])."\n" ?>
				</ul>
			</div>
			<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_admin_forums['Edit forum perms legend'] ?></strong></legend>
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

	($hook = get_hook('afo_qr_get_forum_perms')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
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
				<fieldset class="mf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<legend><span><?php echo forum_htmlencode($cur_perm['g_title']) ?></span></legend>
					<div class="mf-box mf-yesno">
						<div class="mf-item">
							<input type="hidden" name="read_forum_old[<?php echo $cur_perm['g_id'] ?>]" value="<?php echo ($read_forum) ? '1' : '0'; ?>" />
							<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="read_forum_new[<?php echo $cur_perm['g_id'] ?>]" value="1"<?php if ($read_forum) echo ' checked="checked"'; echo ($cur_perm['g_read_board'] == '0') ? ' disabled="disabled"' : ''; ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"<?php if (!$read_forum_def) echo ' class="warn"' ?>><?php echo $lang_admin_forums['Read forum'] ?> <?php if (!$read_forum_def) echo $lang_admin_forums['Not default']  ?></label>
						</div>
						<div class="mf-item">
							<input type="hidden" name="post_replies_old[<?php echo $cur_perm['g_id'] ?>]" value="<?php echo ($post_replies) ? '1' : '0'; ?>" />
							<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="post_replies_new[<?php echo $cur_perm['g_id'] ?>]" value="1"<?php if ($post_replies) echo ' checked="checked"'; echo ($cur_forum['redirect_url'] != '') ? ' disabled="disabled"' : ''; ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"<?php if (!$post_replies_def) echo ' class="warn"'; ?>><?php echo $lang_admin_forums['Post replies'] ?> <?php if (!$post_replies_def) echo $lang_admin_forums['Not default'] ?></label>
						</div>
						<div class="mf-item">
							<input type="hidden" name="post_topics_old[<?php echo $cur_perm['g_id'] ?>]" value="<?php echo ($post_topics) ? '1' : '0'; ?>" />
							<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="post_topics_new[<?php echo $cur_perm['g_id'] ?>]" value="1"<?php if ($post_topics) echo ' checked="checked"'; echo ($cur_forum['redirect_url'] != '') ? ' disabled="disabled"' : ''; ?> /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"<?php if (!$post_topics_def) echo ' class="warn"'; ?>><?php echo $lang_admin_forums['Post topics'] ?> <?php if (!$post_topics_def) echo $lang_admin_forums['Not default'] ?></label>
<?php ($hook = get_hook('afo_edit_forum_new_permission')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
						</div>
					</div>
				</fieldset>
<?php

		++$i;
	}

?>
<?php ($hook = get_hook('afo_edit_forum_permissions_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="save" value="<?php echo $lang_admin_common['Save changes'] ?>" /></span>
				<span class="submit"><input type="submit" name="revert_perms" value="<?php echo $lang_admin_forums['Restore defaults'] ?>" /></span>
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
$forum_page['fld_count'] = $forum_page['group_count'] = $forum_page['item_count'] = 0;

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
	$lang_admin_common['Forums']
);

($hook = get_hook('afo_pre_header_load')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

define('FORUM_PAGE_SECTION', 'start');
define('FORUM_PAGE', 'admin-forums');
define('FORUM_PAGE_TYPE', 'sectioned');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('afo_main_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_admin_forums['Add forum head'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_forums']) ?>?action=adddel">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_forums']).'?action=adddel') ?>" />
			</div>
			<fieldset class="frm-group group-item<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_admin_forums['Add forum legend'] ?></strong></legend>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_forums['Forum name label'] ?></span></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="forum_name" size="35" maxlength="80" /></span>
					</div>
				</div>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_forums['Position label'] ?></span></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="position" size="3" maxlength="3" /></span>
					</div>
				</div>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box select">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_forums['Add to category label'] ?></span></label><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="add_to_cat">
<?php

	$query = array(
		'SELECT'	=> 'c.id, c.cat_name',
		'FROM'		=> 'categories AS c',
		'ORDER BY'	=> 'c.disp_position'
	);

	($hook = get_hook('afo_qr_get_categories2')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	while ($cur_cat = $forum_db->fetch_assoc($result))
		echo "\t\t\t\t\t\t\t".'<option value="'.$cur_cat['id'].'">'.forum_htmlencode($cur_cat['cat_name']).'</option>'."\n";

?>
						</select></span>
					</div>
				</div>
<?php ($hook = get_hook('afo_add_forum_fieldset_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" class="button" name="add_forum" value=" <?php echo $lang_admin_forums['Add forum'] ?> " /></span>
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

($hook = get_hook('afo_qr_get_cats_and_forums')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

if ($forum_db->num_rows($result))
{
	// Reset fieldset counter
	$forum_page['set_count'] = 0;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_admin_forums['Edit forums head'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_forums']) ?>?action=edit">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_forums']).'?action=edit') ?>" />
			</div>

<?php

	$cur_category = 0;
	$i = 2;
	$forum_page['item_count'] = 0;

	while ($cur_forum = $forum_db->fetch_assoc($result))
	{
		if ($cur_forum['cid'] != $cur_category)	// A new category since last iteration?
		{
			if ($i > 2) echo "\t\t\t".'</div>'."\n";

			$forum_page['item_count'] = 0;
?>
			<div class="content-head">
				<h3 class="hn"><span><?php printf($lang_admin_forums['Forums in category'], forum_htmlencode($cur_forum['cat_name'])) ?></span></h3>
			</div>
			<div class="frm-group frm-hdgroup frm-item<?php echo ++$forum_page['group_count'] ?>">

<?php

			$cur_category = $cur_forum['cid'];
		}

?>
				<fieldset class="mf-set group-item<?php echo ++$forum_page['item_count'] ?><?php echo ($forum_page['item_count'] == 1) ? ' mf-head' : ' mf-extra' ?>">
					<legend><span><?php printf($lang_admin_forums['Edit or delete'], '<a href="'.forum_link($forum_url['admin_forums']).'?edit_forum='.$cur_forum['fid'].'">'.$lang_admin_forums['Edit'].'</a>', '<a href="'.forum_link($forum_url['admin_forums']).'?del_forum='.$cur_forum['fid'].'">'.$lang_admin_forums['Delete'].'</a>') ?></span></legend>
					<div class="mf-box">
						<div class="mf-field mf-field1 forum-field">
							<span class="aslabel">Forum name:</span>
							<span class="fld-input"><?php echo forum_htmlencode($cur_forum['forum_name']) ?></span>
						</div>
						<div class="mf-field">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_forums['Position label'] ?></span></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="position[<?php echo $cur_forum['fid'] ?>]" size="3" maxlength="3" value="<?php echo $cur_forum['disp_position'] ?>" /></span>
						</div>
					</div>
				</fieldset>
<?php

		++$i;
	}

?>
			</div>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" class="button" name="update_positions" value="<?php echo $lang_admin_forums['Update positions'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

}


$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
