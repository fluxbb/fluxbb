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


// Add a new category
if (isset($_POST['add_cat']))
{
	confirm_referrer('admin_categories.php');

	$new_cat_name = trim($_POST['new_cat_name']);
	if ($new_cat_name == '')
		message('You must enter a name for the category.');

	$db->query('INSERT INTO '.$db->prefix.'categories (cat_name) VALUES(\''.$db->escape($new_cat_name).'\')') or error('Unable to create category', __FILE__, __LINE__, $db->error());

	redirect('admin_categories.php', 'Category added. Redirecting &hellip;');
}


// Delete a category
else if (isset($_POST['del_cat']) || isset($_POST['del_cat_comply']))
{
	confirm_referrer('admin_categories.php');

	$cat_to_delete = intval($_POST['cat_to_delete']);
	if ($cat_to_delete < 1)
		message($lang_common['Bad request']);

	if (isset($_POST['del_cat_comply']))	// Delete a category with all forums and posts
	{
		@set_time_limit(0);

		$result = $db->query('SELECT id FROM '.$db->prefix.'forums WHERE cat_id='.$cat_to_delete) or error('Unable to fetch forum list', __FILE__, __LINE__, $db->error());
		$num_forums = $db->num_rows($result);

		for ($i = 0; $i < $num_forums; ++$i)
		{
			$cur_forum = $db->result($result, $i);

			// Prune all posts and topics
			prune($cur_forum, 1, -1);

			// Delete the forum
			$db->query('DELETE FROM '.$db->prefix.'forums WHERE id='.$cur_forum) or error('Unable to delete forum', __FILE__, __LINE__, $db->error());
		}

		// Locate any "orphaned redirect topics" and delete them
		$result = $db->query('SELECT t1.id FROM '.$db->prefix.'topics AS t1 LEFT JOIN '.$db->prefix.'topics AS t2 ON t1.moved_to=t2.id WHERE t2.id IS NULL AND t1.moved_to IS NOT NULL') or error('Unable to fetch redirect topics', __FILE__, __LINE__, $db->error());
		$num_orphans = $db->num_rows($result);

		if ($num_orphans)
		{
			for ($i = 0; $i < $num_orphans; ++$i)
				$orphans[] = $db->result($result, $i);

			$db->query('DELETE FROM '.$db->prefix.'topics WHERE id IN('.implode(',', $orphans).')') or error('Unable to delete redirect topics', __FILE__, __LINE__, $db->error());
		}

		// Delete the category
		$db->query('DELETE FROM '.$db->prefix.'categories WHERE id='.$cat_to_delete) or error('Unable to delete category', __FILE__, __LINE__, $db->error());

		// Regenerate the quickjump cache
		require_once PUN_ROOT.'include/cache.php';
		generate_quickjump_cache();

		redirect('admin_categories.php', 'Category deleted. Redirecting &hellip;');
	}
	else	// If the user hasn't comfirmed the delete
	{
		$result = $db->query('SELECT cat_name FROM '.$db->prefix.'categories WHERE id='.$cat_to_delete) or error('Unable to fetch category info', __FILE__, __LINE__, $db->error());
		$cat_name = $db->result($result);

		$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / Admin / Categories';
		require PUN_ROOT.'header.php';

		generate_admin_menu('categories');

?>
	<div class="blockform">
		<h2><span>Category delete</span></h2>
		<div class="box">
			<form method="post" action="admin_categories.php">
				<div class="inform">
				<input type="hidden" name="cat_to_delete" value="<?php echo $cat_to_delete ?>" />
					<fieldset>
						<legend>Confirm delete category</legend>
						<div class="infldset">
							<p>Are you sure that you want to delete the category "<?php echo pun_htmlspecialchars($cat_name) ?>"?</p>
							<p>WARNING! Deleting a category will delete all forums and posts (if any) in that category!</p>
						</div>
					</fieldset>
				</div>
				<p><input type="submit" name="del_cat_comply" value="Delete" /><a href="javascript:history.go(-1)">Go back</a></p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

		require PUN_ROOT.'footer.php';
	}
}


else if (isset($_POST['update']))	// Change position and name of the categories
{
	confirm_referrer('admin_categories.php');

	$cat_order = $_POST['cat_order'];
	$cat_name = $_POST['cat_name'];

	$result = $db->query('SELECT id, disp_position FROM '.$db->prefix.'categories ORDER BY disp_position') or error('Unable to fetch category list', __FILE__, __LINE__, $db->error());
	$num_cats = $db->num_rows($result);

	for ($i = 0; $i < $num_cats; ++$i)
	{
		if ($cat_name[$i] == '')
			message('You must enter a category name.');

		if (!@preg_match('#^\d+$#', $cat_order[$i]))
			message('Position must be an integer value.');

		list($cat_id, $position) = $db->fetch_row($result);

		$db->query('UPDATE '.$db->prefix.'categories SET cat_name=\''.$db->escape($cat_name[$i]).'\', disp_position='.$cat_order[$i].' WHERE id='.$cat_id) or error('Unable to update category', __FILE__, __LINE__, $db->error());
	}

	// Regenerate the quickjump cache
	require_once PUN_ROOT.'include/cache.php';
	generate_quickjump_cache();

	redirect('admin_categories.php', 'Categories updated. Redirecting &hellip;');
}


// Generate an array with all categories
$result = $db->query('SELECT id, cat_name, disp_position FROM '.$db->prefix.'categories ORDER BY disp_position') or error('Unable to fetch category list', __FILE__, __LINE__, $db->error());
$num_cats = $db->num_rows($result);

for ($i = 0; $i < $num_cats; ++$i)
	$cat_list[] = $db->fetch_row($result);


$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / Admin / Categories';
require PUN_ROOT.'header.php';

generate_admin_menu('categories');

?>
	<div class="blockform">
		<h2><span>Add/remove/edit categories</span></h2>
		<div class="box">
		<form method="post" action="admin_categories.php?action=foo">
			<div class="inform">
				<fieldset>
					<legend>Add/delete categories</legend>
					<div class="infldset">
						<table class="aligntop" cellspacing="0">
							<tr>
								<th scope="row">Add a new category<div><input type="submit" name="add_cat" value="Add New" tabindex="2" /></div></th>
								<td>
									<input type="text" name="new_cat_name" size="35" maxlength="80" tabindex="1" />
									<span>The name of the new category you want to add. You can edit the name of the category later (see below).Go to <a href="admin_forums.php">Forums</a> to add forums to your new category.</span>
								</td>
							</tr>
<?php if ($num_cats): ?>							<tr>
								<th scope="row">Delete a category<div><input type="submit" name="del_cat" value="Delete" tabindex="4" /></div></th>
								<td>
									<select name="cat_to_delete" tabindex="3">
<?php

	while (list(, list($cat_id, $cat_name, ,)) = @each($cat_list))
		echo "\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cat_id.'">'.pun_htmlspecialchars($cat_name).'</option>'."\n";

?>
									</select>
									<span>Select the name of the category you want to delete. You will be asked to confirm your choice of category for deletion before it is deleted.</span>
								</td>
							</tr>
<?php endif; ?>						</table>
					</div>
				</fieldset>
			</div>
<?php if ($num_cats): ?>			<div class="inform">
				<fieldset>
					<legend>Edit categories</legend>
					<div class="infldset">
						<table id="categoryedit" cellspacing="0" >
						<thead>
							<tr>
								<th class="tcl" scope="col">Name</th>
								<th scope="col">Position</th>
								<th>&nbsp;</th>
							</tr>
						</thead>
						<tbody>
<?php

	@reset($cat_list);
	for ($i = 0; $i < $num_cats; ++$i)
	{
		list(, list($cat_id, $cat_name, $position)) = @each($cat_list);

?>
							<tr><td><input type="text" name="cat_name[<?php echo $i ?>]" value="<?php echo pun_htmlspecialchars($cat_name) ?>" size="35" maxlength="80" /></td><td><input type="text" name="cat_order[<?php echo $i ?>]" value="<?php echo $position ?>" size="3" maxlength="3" /></td><td>&nbsp;</td></tr>
<?php

	}

?>
						</tbody>
						</table>
						<div class="fsetsubmit"><input type="submit" name="update" value="Update" /></div>
					</div>
				</fieldset>
			</div>
<?php endif; ?>		</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

require PUN_ROOT.'footer.php';
