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

($hook = get_hook('acg_start')) ? eval($hook) : null;

if ($forum_user['g_id'] != FORUM_ADMIN)
	message($lang_common['No permission']);

// Load the admin.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin.php';


// Add a new category
if (isset($_POST['add_cat']))
{
	$new_cat_name = trim($_POST['new_cat_name']);
	if ($new_cat_name == '')
		message($lang_admin['Must name category']);

	$new_cat_pos = intval($_POST['position']);

	($hook = get_hook('acg_add_cat_form_submitted')) ? eval($hook) : null;

	$query = array(
		'INSERT'	=> 'cat_name, disp_position',
		'INTO'		=> 'categories',
		'VALUES'	=> '\''.$forum_db->escape($new_cat_name).'\', '.$new_cat_pos
	);

	($hook = get_hook('acg_qr_add_category')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	redirect(forum_link($forum_url['admin_categories']), $lang_admin['Category added'].' '.$lang_admin['Redirect']);
}


// Delete a category
else if (isset($_POST['del_cat']) || isset($_POST['del_cat_comply']))
{
	$cat_to_delete = intval($_POST['cat_to_delete']);
	if ($cat_to_delete < 1)
		message($lang_common['Bad request']);

	// User pressed the cancel button
	if (isset($_POST['del_cat_cancel']))
		redirect(forum_link($forum_url['admin_categories']), $lang_admin['Cancel redirect']);

	($hook = get_hook('acg_del_cat_form_submitted')) ? eval($hook) : null;

	if (isset($_POST['del_cat_comply']))	// Delete a category with all forums and posts
	{
		@set_time_limit(0);

		$query = array(
			'SELECT'	=> 'f.id',
			'FROM'		=> 'forums AS f',
			'WHERE'		=> 'cat_id='.$cat_to_delete
		);

		($hook = get_hook('acg_qr_get_forums_to_delete')) ? eval($hook) : null;
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

			($hook = get_hook('acg_qr_delete_forum')) ? eval($hook) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);
		}

		delete_orphans();

		// Delete the category
		$query = array(
			'DELETE'	=> 'categories',
			'WHERE'		=> 'id='.$cat_to_delete
		);

		($hook = get_hook('acg_qr_delete_category')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// Regenerate the quickjump cache
		require_once FORUM_ROOT.'include/cache.php';
		generate_quickjump_cache();

		redirect(forum_link($forum_url['admin_categories']), $lang_admin['Category deleted'].' '.$lang_admin['Redirect']);
	}
	else	// If the user hasn't comfirmed the delete
	{
		$query = array(
			'SELECT'	=> 'c.cat_name',
			'FROM'		=> 'categories AS c',
			'WHERE'		=> 'c.id='.$cat_to_delete
		);

		($hook = get_hook('acg_qr_get_category_name')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		$cat_name = $forum_db->result($result);


		// Setup breadcrumbs
		$forum_page['crumbs'] = array(
			array($forum_config['o_board_title'], forum_link($forum_url['index'])),
			array($lang_admin['Forum administration'], forum_link($forum_url['admin_index'])),
			array($lang_admin['Categories'], forum_link($forum_url['admin_categories'])),
			$lang_admin['Delete category']
		);

		($hook = get_hook('acg_del_cat_pre_header_load')) ? eval($hook) : null;

		define('FORUM_PAGE_SECTION', 'start');
		define('FORUM_PAGE', 'admin-categories');
		require FORUM_ROOT.'header.php';

?>
<div id="brd-main" class="main sectioned admin">


<?php echo generate_admin_menu(); ?>

	<div class="main-head">
		<h1><span>{ <?php echo end($forum_page['crumbs']) ?> }</span></h1>
	</div>

	<div class="main-content frm">
		<div class="frm-head">
			<h2><span><?php printf($lang_admin['Confirm delete cat'], forum_htmlencode($cat_name)) ?></span></h2>
		</div>
		<div class="frm-info">
			<p class="warn"><?php echo $lang_admin['Delete category warning'] ?></p>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_categories']) ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_categories'])) ?>" />
				<input type="hidden" name="cat_to_delete" value="<?php echo $cat_to_delete ?>" />
			</div>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="del_cat_comply" value="<?php echo $lang_admin['Delete category'] ?>" /></span>
				<span class="cancel"><input type="submit" name="del_cat_cancel" value="<?php echo $lang_admin['Cancel'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

		require FORUM_ROOT.'footer.php';
	}
}


else if (isset($_POST['update']))	// Change position and name of the categories
{
	$cat_order = array_map('intval', $_POST['cat_order']);
	$cat_name = array_map('trim', $_POST['cat_name']);

	($hook = get_hook('acg_update_cats_form_submitted')) ? eval($hook) : null;

	$query = array(
		'SELECT'	=> 'c.id, c.cat_name, c.disp_position',
		'FROM'		=> 'categories AS c',
		'ORDER BY'	=> 'c.id'
	);

	($hook = get_hook('acg_qr_get_categories')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	while ($cur_cat = $forum_db->fetch_assoc($result))
	{
		// If these aren't set, we're looking at a category that was added after
		// the admin started editing: we don't want to mess with it
		if (isset($cat_name[$cur_cat['id']]) && isset($cat_order[$cur_cat['id']]))
		{
			if ($cat_name[$cur_cat['id']] == '')
				message($lang_admin['Must enter category']);

			if ($cat_order[$cur_cat['id']] < 0)
				message($lang_admin['Must be integer']);

			// We only want to update if we changed anything
			if ($cur_cat['cat_name'] != $cat_name[$cur_cat['id']] || $cur_cat['disp_position'] != $cat_order[$cur_cat['id']])
			{
				$query = array(
					'UPDATE'	=> 'categories',
					'SET'		=> 'cat_name=\''.$forum_db->escape($cat_name[$cur_cat['id']]).'\', disp_position='.$cat_order[$cur_cat['id']],
					'WHERE'		=> 'id='.$cur_cat['id']
				);

				($hook = get_hook('acg_qr_update_category')) ? eval($hook) : null;
				$forum_db->query_build($query) or error(__FILE__, __LINE__);
			}
		}
	}

	// Regenerate the quickjump cache
	require_once FORUM_ROOT.'include/cache.php';
	generate_quickjump_cache();

	redirect(forum_link($forum_url['admin_categories']), $lang_admin['Categories updated'].' '.$lang_admin['Redirect']);
}


// Generate an array with all categories
$query = array(
	'SELECT'	=> 'c.id, c.cat_name, c.disp_position',
	'FROM'		=> 'categories AS c',
	'ORDER BY'	=> 'c.disp_position'
);

($hook = get_hook('acg_qr_get_categories2')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
$num_cats = $forum_db->num_rows($result);

for ($i = 0; $i < $num_cats; ++$i)
	$cat_list[] = $forum_db->fetch_row($result);

// Setup the form
$forum_page['fld_count'] = $forum_page['set_count'] = $forum_page['part_count'] = 0;


// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($lang_admin['Forum administration'], forum_link($forum_url['admin_index'])),
	$lang_admin['Categories']
);

($hook = get_hook('acg_cat_header_load')) ? eval($hook) : null;

define('FORUM_PAGE_SECTION', 'start');
define('FORUM_PAGE', 'admin-categories');
require FORUM_ROOT.'header.php';

?>
<div id="brd-main" class="main sectioned admin">


<?php echo generate_admin_menu(); ?>

	<div class="main-head">
		<h1><span>{ <?php echo end($forum_page['crumbs']) ?> }</span></h1>
	</div>

	<div class="main-content frm">
		<div class="frm-head">
			<h2><span><?php echo $lang_admin['Add category head'] ?></span></h2>
		</div>
		<div class="frm-info">
			<p><?php printf($lang_admin['Add category info'], '<a href="'.forum_link($forum_url['admin_forums']).'">'.$lang_admin['Add category info link text'].'</a>') ?></p>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_categories']) ?>?action=foo">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_categories']).'?action=foo') ?>" />
			</div>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_admin['Add category'] ?></strong></legend>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['New category name'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="new_cat_name" size="35" maxlength="80" /></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Position'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="position" size="3" maxlength="3" /></span>
						<span class="fld-extra"><?php echo $lang_admin['Category position help'] ?></span>
					</label>
				</div>
<?php ($hook = get_hook('acg_add_cat_fieldset_end')) ? eval($hook) : null; ?>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="add_cat" value="<?php echo $lang_admin['Add category'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

($hook = get_hook('acg_new_form')) ? eval($hook) : null;

// Reset fieldset counter
$forum_page['set_count'] = 0;

if ($num_cats)
{

?>
	<div class="main-content frm">
		<div class="frm-head">
			<h2><span><?php echo $lang_admin['Del category head'] ?></span></h2>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_categories']) ?>?action=foo">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_categories']).'?action=foo') ?>" />
			</div>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_admin['Delete category'] ?></strong></legend>
				<div class="frm-fld select">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Select category'] ?></span><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="cat_to_delete">
<?php

	while (list(, list($cat_id, $cat_name, ,)) = @each($cat_list))
		echo "\t\t\t\t\t\t\t".'<option value="'.$cat_id.'">'.forum_htmlencode($cat_name).'</option>'."\n";

?>
						</select></span>
						<span class="fld-help"><?php echo $lang_admin['Delete help'] ?></span>
					</label>
				</div>
<?php ($hook = get_hook('acg_del_cat_fieldset_end')) ? eval($hook) : null; ?>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="del_cat" value="<?php echo $lang_admin['Delete category'] ?>" /></span>
			</div>
		</form>
	</div>

	<div class="main-content frm">
		<div class="frm-head">
			<h2><span><?php echo $lang_admin['Edit categories head'] ?></span></h2>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_categories']) ?>?action=foo">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_categories']).'?action=foo') ?>" />
			</div>

<?php

	@reset($cat_list);
	for ($i = 0; $i < $num_cats; ++$i)
	{
		list(, list($cat_id, $cat_name, $position)) = @each($cat_list);
		// Reset fieldset counter
		$forum_page['set_count'] = 0;

?>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo forum_htmlencode($cat_name) ?></strong></legend>
				<div class="frm-fld text twin">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>" class="twin1">
						<span class="fld-label"><?php echo $lang_admin['Edit category'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="cat_name[<?php echo $cat_id ?>]" value="<?php echo forum_htmlencode($cat_name) ?>" size="35" maxlength="80" /></span>
					</label><br />
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>" class="twin2">
						<span class="fld-label"><?php echo $lang_admin['Position'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="cat_order[<?php echo $cat_id ?>]" value="<?php echo $position ?>" size="3" maxlength="3" /></span>
					</label>
				</div>
<?php ($hook = get_hook('acg_edit_cat_fieldset_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php

	}

?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" class="button" name="update" value="<?php echo $lang_admin['Update all'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

	($hook = get_hook('acg_has_cats_new_form')) ? eval($hook) : null;
}

?>

</div>
<?php

require FORUM_ROOT.'footer.php';
