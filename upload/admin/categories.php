<?php
/**
 * Category management page
 *
 * Allows administrators to create, reposition, and remove categories.
 *
 * @copyright Copyright (C) 2008 FluxBB.org, based on code copyright (C) 2002-2008 PunBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package FluxBB
 */


if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', '../');
require FORUM_ROOT.'include/common.php';
require FORUM_ROOT.'include/common_admin.php';

($hook = get_hook('acg_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

if ($forum_user['g_id'] != FORUM_ADMIN)
	message($lang_common['No permission']);

// Load the admin.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin_common.php';
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin_categories.php';


// Add a new category
if (isset($_POST['add_cat']))
{
	$new_cat_name = forum_trim($_POST['new_cat_name']);
	if ($new_cat_name == '')
		message($lang_admin_categories['Must name category']);

	$new_cat_pos = intval($_POST['position']);

	($hook = get_hook('acg_add_cat_form_submitted')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	$query = array(
		'INSERT'	=> 'cat_name, disp_position',
		'INTO'		=> 'categories',
		'VALUES'	=> '\''.$forum_db->escape($new_cat_name).'\', '.$new_cat_pos
	);

	($hook = get_hook('acg_add_cat_qr_add_category')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	($hook = get_hook('acg_add_cat_pre_redirect')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	redirect(forum_link($forum_url['admin_categories']), $lang_admin_categories['Category added'].' '.$lang_admin_common['Redirect']);
}


// Delete a category
else if (isset($_POST['del_cat']) || isset($_POST['del_cat_comply']))
{
	$cat_to_delete = intval($_POST['cat_to_delete']);
	if ($cat_to_delete < 1)
		message($lang_common['Bad request']);

	// User pressed the cancel button
	if (isset($_POST['del_cat_cancel']))
		redirect(forum_link($forum_url['admin_categories']), $lang_admin_common['Cancel redirect']);

	($hook = get_hook('acg_del_cat_form_submitted')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	if (isset($_POST['del_cat_comply']))	// Delete a category with all forums and posts
	{
		@set_time_limit(0);

		$query = array(
			'SELECT'	=> 'f.id',
			'FROM'		=> 'forums AS f',
			'WHERE'		=> 'cat_id='.$cat_to_delete
		);

		($hook = get_hook('acg_del_cat_qr_get_forums_to_delete')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		$num_forums = $forum_db->num_rows($result);

		for ($i = 0; $i < $num_forums; ++$i)
		{
			$cur_forum = $forum_db->result($result, $i);

			// Prune all posts and topics
			prune($cur_forum, 1, -1);

			// Delete the forum
			$query = array(
				'DELETE'	=> 'forums',
				'WHERE'		=> 'id='.$cur_forum
			);

			($hook = get_hook('acg_del_cat_qr_delete_forum')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);
		}

		delete_orphans();

		// Delete the category
		$query = array(
			'DELETE'	=> 'categories',
			'WHERE'		=> 'id='.$cat_to_delete
		);

		($hook = get_hook('acg_del_cat_qr_delete_category')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// Regenerate the quickjump cache
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			require FORUM_ROOT.'include/cache.php';

		generate_quickjump_cache();

		($hook = get_hook('acg_del_cat_pre_redirect')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		redirect(forum_link($forum_url['admin_categories']), $lang_admin_categories['Category deleted'].' '.$lang_admin_common['Redirect']);
	}
	else	// If the user hasn't comfirmed the delete
	{
		$query = array(
			'SELECT'	=> 'c.cat_name',
			'FROM'		=> 'categories AS c',
			'WHERE'		=> 'c.id='.$cat_to_delete
		);

		($hook = get_hook('acg_del_cat_qr_get_category_name')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		if (!$forum_db->num_rows($result))
			message($lang_common['Bad request']);

		$cat_name = $forum_db->result($result);


		// Setup the form
		$forum_page['form_action'] = forum_link($forum_url['admin_categories']);

		$forum_page['hidden_fields'] = array(
			'csrf_token'	=> '<input type="hidden" name="csrf_token" value="'.generate_form_token($forum_page['form_action']).'" />',
			'cat_to_delete'	=> '<input type="hidden" name="cat_to_delete" value="'.$cat_to_delete.'" />'
		);

		// Setup breadcrumbs
		$forum_page['crumbs'] = array(
			array($forum_config['o_board_title'], forum_link($forum_url['index'])),
			array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
			array($lang_admin_common['Categories'], forum_link($forum_url['admin_categories'])),
			$lang_admin_categories['Delete category']
		);

		($hook = get_hook('acg_del_cat_pre_header_load')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		define('FORUM_PAGE_SECTION', 'start');
		define('FORUM_PAGE', 'admin-categories');
		require FORUM_ROOT.'header.php';

		// START SUBST - <!-- forum_main -->
		ob_start();

		($hook = get_hook('acg_del_cat_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php printf($lang_admin_categories['Confirm delete cat'], forum_htmlencode($cat_name)) ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<div class="ct-box warn-box">
			<p class="warn"><?php echo $lang_admin_categories['Delete category warning'] ?></p>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
			</div>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="del_cat_comply" value="<?php echo $lang_admin_categories['Delete category'] ?>" /></span>
				<span class="cancel"><input type="submit" name="del_cat_cancel" value="<?php echo $lang_admin_common['Cancel'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

		($hook = get_hook('acg_del_cat_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		$tpl_temp = forum_trim(ob_get_contents());
		$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
		ob_end_clean();
		// END SUBST - <!-- forum_main -->

		require FORUM_ROOT.'footer.php';
	}
}


else if (isset($_POST['update']))	// Change position and name of the categories
{
	$cat_order = array_map('intval', $_POST['cat_order']);
	$cat_name = array_map('trim', $_POST['cat_name']);

	($hook = get_hook('acg_update_cats_form_submitted')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	$query = array(
		'SELECT'	=> 'c.id, c.cat_name, c.disp_position',
		'FROM'		=> 'categories AS c',
		'ORDER BY'	=> 'c.id'
	);

	($hook = get_hook('acg_update_cats_qr_get_categories')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	while ($cur_cat = $forum_db->fetch_assoc($result))
	{
		// If these aren't set, we're looking at a category that was added after
		// the admin started editing: we don't want to mess with it
		if (isset($cat_name[$cur_cat['id']]) && isset($cat_order[$cur_cat['id']]))
		{
			if ($cat_name[$cur_cat['id']] == '')
				message($lang_admin_categories['Must enter category']);

			if ($cat_order[$cur_cat['id']] < 0)
				message($lang_admin_categories['Must be integer']);

			// We only want to update if we changed anything
			if ($cur_cat['cat_name'] != $cat_name[$cur_cat['id']] || $cur_cat['disp_position'] != $cat_order[$cur_cat['id']])
			{
				$query = array(
					'UPDATE'	=> 'categories',
					'SET'		=> 'cat_name=\''.$forum_db->escape($cat_name[$cur_cat['id']]).'\', disp_position='.$cat_order[$cur_cat['id']],
					'WHERE'		=> 'id='.$cur_cat['id']
				);

				($hook = get_hook('acg_update_cats_qr_update_category')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
				$forum_db->query_build($query) or error(__FILE__, __LINE__);
			}
		}
	}

	// Regenerate the quickjump cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';

	generate_quickjump_cache();

	($hook = get_hook('acg_update_cats_pre_redirect')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	redirect(forum_link($forum_url['admin_categories']), $lang_admin_categories['Categories updated'].' '.$lang_admin_common['Redirect']);
}


// Generate an array with all categories
$query = array(
	'SELECT'	=> 'c.id, c.cat_name, c.disp_position',
	'FROM'		=> 'categories AS c',
	'ORDER BY'	=> 'c.disp_position'
);

($hook = get_hook('acg_qr_get_categories')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

for ($cat_list = array();$cat_list[] = $forum_db->fetch_assoc($result););

// Setup the form
$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;
$forum_page['form_action'] = forum_link($forum_url['admin_categories']).'?action=foo';

$forum_page['hidden_fields'] = array(
	'csrf_token'	=> '<input type="hidden" name="csrf_token" value="'.generate_form_token($forum_page['form_action']).'" />'
);

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
	$lang_admin_common['Categories']
);

($hook = get_hook('acg_pre_header_load')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

define('FORUM_PAGE_SECTION', 'start');
define('FORUM_PAGE', 'admin-categories');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('acg_main_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_admin_categories['Add category head'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
			</div>
<?php ($hook = get_hook('acg_pre_add_cat_fieldset')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			<fieldset class="frm-group group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><span><?php echo $lang_admin_categories['Add category legend'] ?></span></legend>
				<div class="ct-box set<?php echo ++$forum_page['item_count'] ?>">
					<p><?php printf($lang_admin_categories['Add category info'], '<a href="'.forum_link($forum_url['admin_forums']).'">'.$lang_admin_categories['Add category info link text'].'</a>') ?></p>
				</div>
<?php ($hook = get_hook('acg_pre_new_category_name')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_categories['New category label'] ?></span></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="new_cat_name" size="35" maxlength="80" /></span>
					</div>
				</div>
<?php ($hook = get_hook('acg_pre_new_category_position')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_categories['Position label'] ?></span></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="position" size="3" maxlength="3" /></span>
					</div>
				</div>
<?php ($hook = get_hook('acg_pre_add_cat_fieldset_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			</fieldset>
<?php ($hook = get_hook('acg_add_cat_fieldset_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="add_cat" value="<?php echo $lang_admin_categories['Add category'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

($hook = get_hook('acg_post_add_cat_form')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

// Reset counter
$forum_page['group_count'] = $forum_page['item_count'] = 0;

if (!empty($cat_list))
{

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_admin_categories['Del category head'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
			</div>
<?php ($hook = get_hook('acg_pre_del_cat_fieldset')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			<fieldset class="frm-group group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_admin_categories['Delete category'] ?></strong></legend>
<?php ($hook = get_hook('acg_pre_del_category_select')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box select">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_categories['Select category label'] ?></span> <small><?php echo $lang_admin_common['Delete help'] ?></small></label><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="cat_to_delete">
<?php

	foreach ($cat_list as $cur_category)
		echo "\t\t\t\t\t\t\t".'<option value="'.$cur_category['id'].'">'.forum_htmlencode($cur_category['cat_name']).'</option>'."\n";

?>
						</select></span>
					</div>
				</div>
<?php ($hook = get_hook('acg_pre_del_cat_fieldset_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			</fieldset>
<?php ($hook = get_hook('acg_del_cat_fieldset_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="del_cat" value="<?php echo $lang_admin_categories['Delete category'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

($hook = get_hook('acg_post_del_cat_form')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

// Reset counter
$forum_page['group_count'] = $forum_page['item_count'] = 0;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_admin_categories['Edit categories head'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
			</div>
<?php

	($hook = get_hook('acg_edit_cat_fieldsets_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	foreach ($cat_list as $cur_category)
	{
		$forum_page['item_count'] = 0;

		($hook = get_hook('acg_pre_edit_cur_cat_fieldset')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
			<fieldset class="frm-group group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><span><?php printf($lang_admin_categories['Edit category legend'],  '<span class="hideme"> ('.forum_htmlencode($cur_category['cat_name']).')</span>') ?></span></legend>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
<?php ($hook = get_hook('acg_pre_edit_cat_name')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_categories['Category name label'] ?></span></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="cat_name[<?php echo $cur_category['id'] ?>]" value="<?php echo forum_htmlencode($cur_category['cat_name']) ?>" size="35" maxlength="80" /></span>
					</div>
<?php ($hook = get_hook('acg_pre_edit_cat_position')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_categories['Position label'] ?></span></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="cat_order[<?php echo $cur_category['id'] ?>]" value="<?php echo $cur_category['disp_position'] ?>" size="3" maxlength="3" /></span>
					</div>
				</div>
<?php ($hook = get_hook('acg_pre_edit_cur_cat_fieldset_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			</fieldset>
<?php

		($hook = get_hook('acg_edit_cur_cat_fieldset_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	}

	($hook = get_hook('acg_edit_cat_fieldsets_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" class="button" name="update" value="<?php echo $lang_admin_categories['Update all categories'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

	($hook = get_hook('acg_post_edit_cat_form')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
}

($hook = get_hook('acg_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
