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

($hook = get_hook('acg_start')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

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

	($hook = get_hook('acg_add_cat_form_submitted')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

	$query = array(
		'INSERT'	=> 'cat_name, disp_position',
		'INTO'		=> 'categories',
		'VALUES'	=> '\''.$forum_db->escape($new_cat_name).'\', '.$new_cat_pos
	);

	($hook = get_hook('acg_qr_add_category')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

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

	($hook = get_hook('acg_del_cat_form_submitted')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

	if (isset($_POST['del_cat_comply']))	// Delete a category with all forums and posts
	{
		@set_time_limit(0);

		$query = array(
			'SELECT'	=> 'f.id',
			'FROM'		=> 'forums AS f',
			'WHERE'		=> 'cat_id='.$cat_to_delete
		);

		($hook = get_hook('acg_qr_get_forums_to_delete')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
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

			($hook = get_hook('acg_qr_delete_forum')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);
		}

		delete_orphans();

		// Delete the category
		$query = array(
			'DELETE'	=> 'categories',
			'WHERE'		=> 'id='.$cat_to_delete
		);

		($hook = get_hook('acg_qr_delete_category')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// Regenerate the quickjump cache
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			require FORUM_ROOT.'include/cache.php';

		generate_quickjump_cache();

		redirect(forum_link($forum_url['admin_categories']), $lang_admin_categories['Category deleted'].' '.$lang_admin_common['Redirect']);
	}
	else	// If the user hasn't comfirmed the delete
	{
		$query = array(
			'SELECT'	=> 'c.cat_name',
			'FROM'		=> 'categories AS c',
			'WHERE'		=> 'c.id='.$cat_to_delete
		);

		($hook = get_hook('acg_qr_get_category_name')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		if (!$forum_db->num_rows($result))
			message($lang_common['Bad request']);

		$cat_name = $forum_db->result($result);


		// Setup breadcrumbs
		$forum_page['crumbs'] = array(
			array($forum_config['o_board_title'], forum_link($forum_url['index'])),
			array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
			array($lang_admin_common['Categories'], forum_link($forum_url['admin_categories'])),
			$lang_admin_categories['Delete category']
		);

		($hook = get_hook('acg_del_cat_pre_header_load')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

		define('FORUM_PAGE_SECTION', 'start');
		define('FORUM_PAGE', 'admin-categories');
		define('FORUM_PAGE_TYPE', 'sectioned');
		require FORUM_ROOT.'header.php';

		// START SUBST - <!-- forum_main -->
		ob_start();

		($hook = get_hook('acg_delete_cat_output_start')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php printf($lang_admin_categories['Confirm delete cat'], forum_htmlencode($cat_name)) ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<div class="ct-box warn-box">
			<p class="warn"><?php echo $lang_admin_categories['Delete category warning'] ?></p>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_categories']) ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_categories'])) ?>" />
				<input type="hidden" name="cat_to_delete" value="<?php echo $cat_to_delete ?>" />
			</div>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="del_cat_comply" value="<?php echo $lang_admin_categories['Delete category'] ?>" /></span>
				<span class="cancel"><input type="submit" name="del_cat_cancel" value="<?php echo $lang_admin_common['Cancel'] ?>" /></span>
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
}


else if (isset($_POST['update']))	// Change position and name of the categories
{
	$cat_order = array_map('intval', $_POST['cat_order']);
	$cat_name = array_map('trim', $_POST['cat_name']);

	($hook = get_hook('acg_update_cats_form_submitted')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

	$query = array(
		'SELECT'	=> 'c.id, c.cat_name, c.disp_position',
		'FROM'		=> 'categories AS c',
		'ORDER BY'	=> 'c.id'
	);

	($hook = get_hook('acg_qr_get_categories')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
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

				($hook = get_hook('acg_qr_update_category')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
				$forum_db->query_build($query) or error(__FILE__, __LINE__);
			}
		}
	}

	// Regenerate the quickjump cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';

	generate_quickjump_cache();

	redirect(forum_link($forum_url['admin_categories']), $lang_admin_categories['Categories updated'].' '.$lang_admin_common['Redirect']);
}


// Generate an array with all categories
$query = array(
	'SELECT'	=> 'c.id, c.cat_name, c.disp_position',
	'FROM'		=> 'categories AS c',
	'ORDER BY'	=> 'c.disp_position'
);

($hook = get_hook('acg_qr_get_categories2')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
$num_cats = $forum_db->num_rows($result);

for ($i = 0; $i < $num_cats; ++$i)
	$cat_list[] = $forum_db->fetch_row($result);

// Setup the form
$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;


// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
	$lang_admin_common['Categories']
);

($hook = get_hook('acg_cat_header_load')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

define('FORUM_PAGE_SECTION', 'start');
define('FORUM_PAGE', 'admin-categories');
define('FORUM_PAGE_TYPE', 'sectioned');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('acg_main_output_start')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_admin_categories['Add category head'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<div class="ct-box">
			<p><?php printf($lang_admin_categories['Add category info'], '<a href="'.forum_link($forum_url['admin_forums']).'">'.$lang_admin_categories['Add category info link text'].'</a>') ?></p>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_categories']) ?>?action=foo">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_categories']).'?action=foo') ?>" />
			</div>
			<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><span><?php echo $lang_admin_categories['Add category legend'] ?></span></legend>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_categories['New category label'] ?></span></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="new_cat_name" size="35" maxlength="80" /></span>
					</div>
				</div>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_categories['Position label'] ?></span></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="position" size="3" maxlength="3" /></span>
					</div>
				</div>
			</fieldset>
<?php ($hook = get_hook('acg_add_cat_fieldset_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="add_cat" value="<?php echo $lang_admin_categories['Add category'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

($hook = get_hook('acg_new_form')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

// Reset counter
$forum_page['group_count'] = $forum_page['item_count'] = 0;

if ($num_cats)
{

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_admin_categories['Del category head'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_categories']) ?>?action=foo">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_categories']).'?action=foo') ?>" />
			</div>
			<fieldset class="frm-group frm-item<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_admin_categories['Delete category'] ?></strong></legend>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box select">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_categories['Select category label'] ?></span> <small><?php echo $lang_admin_common['Delete help'] ?></small></label><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="cat_to_delete">
<?php

	while (list(, list($cat_id, $cat_name, ,)) = @each($cat_list))
		echo "\t\t\t\t\t\t\t".'<option value="'.$cat_id.'">'.forum_htmlencode($cat_name).'</option>'."\n";

?>
						</select></span>
					</div>
				</div>
<?php ($hook = get_hook('acg_del_cat_fieldset_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="del_cat" value="<?php echo $lang_admin_categories['Delete category'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

// Reset counter
$forum_page['group_count'] = $forum_page['item_count'] = 0;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_admin_categories['Edit categories head'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_categories']) ?>?action=foo">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_categories']).'?action=foo') ?>" />
			</div>
			<fieldset class="frm-group frm-hdgroup frm-item<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_admin_categories['Edit categories legend'] ?></strong></legend>

<?php

	@reset($cat_list);
	for ($i = 0; $i < $num_cats; ++$i)
	{
		list(, list($cat_id, $cat_name, $position)) = @each($cat_list);

?>
				<fieldset class="mf-set group-item<?php echo ++$forum_page['item_count'] ?><?php echo ($forum_page['item_count'] == 1) ? ' mf-head' : ' mf-extra' ?>">
					<legend><span><?php printf($lang_admin_categories['Edit category legend'],  '<span class="hideme"> ('.forum_htmlencode($cat_name).')</span>') ?></span></legend>
					<div class="mf-box">
						<div class="mf-field mf-field1 text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_categories['Category name label'] ?></span></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="cat_name[<?php echo $cat_id ?>]" value="<?php echo forum_htmlencode($cat_name) ?>" size="35" maxlength="80" /></span>
						</div>
						<div class="mf-field text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_categories['Position label'] ?></span></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="cat_order[<?php echo $cat_id ?>]" value="<?php echo $position ?>" size="3" maxlength="3" /></span>
						</div>
					</div>
				</fieldset>
<?php ($hook = get_hook('acg_edit_cat_fieldset_end')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null; ?>
<?php

	}

?>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" class="button" name="update" value="<?php echo $lang_admin_categories['Update all categories'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

	($hook = get_hook('acg_has_cats_new_form')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
}

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
