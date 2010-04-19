<?php

/**
 * Copyright (C) 2008-2010 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Tell header.php to use the admin template
define('PUN_ADMIN_CONSOLE', 1);

define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/common_admin.php';


if ($pun_user['g_id'] != PUN_ADMIN)
	message($lang_common['No permission']);

// Load the admin_categories.php language file
require PUN_ROOT.'lang/'.$admin_language.'/admin_categories.php';

// Add a new category
if (isset($_POST['add_cat']))
{
	confirm_referrer('admin_categories.php');

	$new_cat_name = pun_trim($_POST['new_cat_name']);
	if ($new_cat_name == '')
		message($lang_admin_categories['Must enter name message']);

	$db->query('INSERT INTO '.$db->prefix.'categories (cat_name) VALUES(\''.$db->escape($new_cat_name).'\')') or error('Unable to create category', __FILE__, __LINE__, $db->error());

	redirect('admin_categories.php', $lang_admin_categories['Category added redirect']);
}

// Delete a category
else if (isset($_POST['del_cat']) || isset($_POST['del_cat_comply']))
{
	confirm_referrer('admin_categories.php');

	$cat_to_delete = intval($_POST['cat_to_delete']);
	if ($cat_to_delete < 1)
		message($lang_common['Bad request']);

	if (isset($_POST['del_cat_comply'])) // Delete a category with all forums and posts
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

		// Regenerate the quick jump cache
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			require PUN_ROOT.'include/cache.php';

		generate_quickjump_cache();

		redirect('admin_categories.php', $lang_admin_categories['Category deleted redirect']);
	}
	else // If the user hasn't comfirmed the delete
	{
		$result = $db->query('SELECT cat_name FROM '.$db->prefix.'categories WHERE id='.$cat_to_delete) or error('Unable to fetch category info', __FILE__, __LINE__, $db->error());
		$cat_name = $db->result($result);

		$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_common['Admin'], $lang_admin_common['Categories']);
		define('PUN_ACTIVE_PAGE', 'admin');
		require PUN_ROOT.'header.php';

		generate_admin_menu('categories');

?>
	<div class="blockform">
		<h2><span><?php echo $lang_admin_categories['Delete category head'] ?></span></h2>
		<div class="box">
			<form method="post" action="admin_categories.php">
				<div class="inform">
				<input type="hidden" name="cat_to_delete" value="<?php echo $cat_to_delete ?>" />
					<fieldset>
						<legend><?php echo $lang_admin_categories['Confirm delete subhead'] ?></legend>
						<div class="infldset">
							<p><?php printf($lang_admin_categories['Confirm delete info'], pun_htmlspecialchars($cat_name)) ?></p>
							<p class="warntext"><?php echo $lang_admin_categories['Delete category warn'] ?></p>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="del_cat_comply" value="<?php echo $lang_admin_common['Delete'] ?>" /><a href="javascript:history.go(-1)"><?php echo $lang_admin_common['Go back'] ?></a></p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

		require PUN_ROOT.'footer.php';
	}
}

else if (isset($_POST['update'])) // Change position and name of the categories
{
	confirm_referrer('admin_categories.php');

	$cat_order = array_map('trim', $_POST['cat_order']);
	$cat_name = array_map('pun_trim', $_POST['cat_name']);

	$result = $db->query('SELECT id, disp_position FROM '.$db->prefix.'categories ORDER BY disp_position') or error('Unable to fetch category list', __FILE__, __LINE__, $db->error());
	$num_cats = $db->num_rows($result);

	for ($i = 0; $i < $num_cats; ++$i)
	{
		if ($cat_name[$i] == '')
			message($lang_admin_categories['Must enter name message']);

		if ($cat_order[$i] == '' || preg_match('/[^0-9]/', $cat_order[$i]))
			message($lang_admin_categories['Must enter integer message']);

		list($cat_id, $position) = $db->fetch_row($result);

		$db->query('UPDATE '.$db->prefix.'categories SET cat_name=\''.$db->escape($cat_name[$i]).'\', disp_position='.$cat_order[$i].' WHERE id='.$cat_id) or error('Unable to update category', __FILE__, __LINE__, $db->error());
	}

	// Regenerate the quick jump cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';

	generate_quickjump_cache();

	redirect('admin_categories.php', $lang_admin_categories['Categories updated redirect']);
}

// Generate an array with all categories
$result = $db->query('SELECT id, cat_name, disp_position FROM '.$db->prefix.'categories ORDER BY disp_position') or error('Unable to fetch category list', __FILE__, __LINE__, $db->error());
$num_cats = $db->num_rows($result);

for ($i = 0; $i < $num_cats; ++$i)
	$cat_list[] = $db->fetch_row($result);

$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_common['Admin'], $lang_admin_common['Categories']);
define('PUN_ACTIVE_PAGE', 'admin');
require PUN_ROOT.'header.php';

generate_admin_menu('categories');

?>
	<div class="blockform">
		<h2><span><?php echo $lang_admin_categories['Add categories head'] ?></span></h2>
		<div class="box">
			<form method="post" action="admin_categories.php?action=foo">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_categories['Add categories subhead'] ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang_admin_categories['Add category label'] ?><div><input type="submit" name="add_cat" value="<?php echo $lang_admin_categories['Add new submit'] ?>" tabindex="2" /></div></th>
									<td>
										<input type="text" name="new_cat_name" size="35" maxlength="80" tabindex="1" />
										<span><?php printf($lang_admin_categories['Add category help'], '<a href="admin_forums.php">'.$lang_admin_common['Forums'].'</a>') ?></span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
			</form>
		</div>

<?php if ($num_cats): ?>		<h2 class="block2"><span><?php echo $lang_admin_categories['Delete categories head'] ?></span></h2>
		<div class="box">
			<form method="post" action="admin_categories.php?action=foo">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_categories['Delete categories subhead'] ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang_admin_categories['Delete category label'] ?><div><input type="submit" name="del_cat" value="<?php echo $lang_admin_common['Delete'] ?>" tabindex="4" /></div></th>
									<td>
										<select name="cat_to_delete" tabindex="3">
<?php

	foreach ($cat_list as $category)
		echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$category[0].'">'.pun_htmlspecialchars($category[1]).'</option>'."\n";

?>
										</select>
										<span><?php echo $lang_admin_categories['Delete category help'] ?></span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
			</form>
		</div>
<?php endif; ?>

<?php if ($num_cats): ?>		<h2 class="block2"><span><?php echo $lang_admin_categories['Edit categories head'] ?></span></h2>
		<div class="box">
			<form method="post" action="admin_categories.php?action=foo">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_categories['Edit categories subhead'] ?></legend>
						<div class="infldset">
							<table id="categoryedit" cellspacing="0" >
							<thead>
								<tr>
									<th class="tcl" scope="col"><?php echo $lang_admin_categories['Category name label'] ?></th>
									<th scope="col"><?php echo $lang_admin_categories['Category position label'] ?></th>
								</tr>
							</thead>
							<tbody>
<?php

	foreach ($cat_list as $i => $category)
	{

?>
								<tr>
									<td class="tcl"><input type="text" name="cat_name[<?php echo $i ?>]" value="<?php echo pun_htmlspecialchars($category[1]) ?>" size="35" maxlength="80" /></td>
									<td><input type="text" name="cat_order[<?php echo $i ?>]" value="<?php echo $category[2] ?>" size="3" maxlength="3" /></td>
								</tr>
<?php

	}

?>
							</tbody>
							</table>
							<div class="fsetsubmit"><input type="submit" name="update" value="<?php echo $lang_admin_common['Update'] ?>" /></div>
						</div>
					</fieldset>
				</div>
			</form>
		</div>
<?php endif; ?>	</div>
	<div class="clearer"></div>
</div>
<?php

require PUN_ROOT.'footer.php';
