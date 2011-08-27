<?php

/**
 * Copyright (C) 2008-2011 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Tell header.php to use the admin template
define('PUN_ADMIN_CONSOLE', 1);

define('PUN_ROOT', dirname(__FILE__).'/');
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

	$query = new InsertQuery(array('cat_name' => ':cat_name'), 'categories');
	$params = array(':cat_name' => $new_cat_name);

	$db->query($query, $params);
	unset ($query, $params);

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

		$query = new SelectQuery(array('id' => 'f.id'), 'forums AS f');
		$query->where = 'f.cat_id = :cat_id';
		
		$params = array(':cat_id' => $cat_to_delete);
		
		$result = $db->query($query, $params);
		unset($query, $params);
		
		foreach ($result as $cur_forum)
		{
			// Prune all posts and topics
			prune($cur_forum['id'], 1, -1);

			// Delete the forum
			$query = new DeleteQuery('forums');
			$query->where = 'id = :forum_id';
			
			$params = array(':forum_id' => $cur_forum['id']);
			
			$db->query($query, $params);
			unset($query, $params);
		}
		unset($result);

		// Locate any "orphaned redirect topics" and delete them
		$query = new SelectQuery(array('id' => 't1.id'), 'topics AS t1');
		$query->joins['t1'] = new LeftJoin('topics AS t2');
		$query->joins['t1']->on = 't1.moved_to = t2.id';
		$query->where = 't2.id IS NULL AND t1.moved_to IS NOT NULL';
		
		$result = $db->query($query);
		unset($query);
		
		if (!empty($result))
		{
			$orphans = array();
			foreach ($result as $cur_orphan)
				$orphans[] = $cur_orphan['id'];

			$query = new DeleteQuery('topics');
			$query->where = 'id IN :orphans';
			
			$params = array(':orphans' => $orphans);
			
			$db->query($query, $params);
			unset($query, $params);
		}
		unset($result);

		// Delete the category
		$query = new DeleteQuery('categories');
		$query->where = 'id = :cat_id';
		
		$params = array(':cat_id' => $cat_to_delete);
		
		$db->query($query, $params);
		unset($query, $params);

		// Regenerate the quick jump cache
		$cache->delete('quickjump');

		redirect('admin_categories.php', $lang_admin_categories['Category deleted redirect']);
	}
	else // If the user hasn't comfirmed the delete
	{
		$query = new SelectQuery(array('cat_name' => 'c.cat_name'), 'categories AS c');
		$query->where = 'c.id = :cat_id';
		
		$params = array(':cat_id' => $cat_to_delete);
		
		$result = $db->query($query, $params);
		$cat_name = $result[0]['cat_name'];
		unset($query, $params, $result);

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

	$categories = $_POST['cat'];
	if (empty($categories))
		message($lang_common['Bad request']);

	$query = new UpdateQuery(array('cat_name' => ':name', 'disp_position' => ':position'), 'categories');
	$query->where = 'id = :cid';

	foreach ($categories as $cat_id => $cur_cat)
	{
		$cur_cat['name'] = pun_trim($cur_cat['name']);
		$cur_cat['order'] = trim($cur_cat['order']);

		if ($cur_cat['name'] == '')
			message($lang_admin_categories['Must enter name message']);

		if ($cur_cat['order'] == '' || preg_match('%[^0-9]%', $cur_cat['order']))
			message($lang_admin_categories['Must enter integer message']);

		$params = array(':name' => $cur_cat['name'], ':position' => $cur_cat['order'], ':cid' => $cat_id);

		$db->query($query, $params);
		unset ($params);
	}

	unset ($query);

	// Regenerate the quick jump cache
	$cache->delete('quickjump');

	redirect('admin_categories.php', $lang_admin_categories['Categories updated redirect']);
}

// Generate an array with all categories
$query = new SelectQuery(array('cid' => 'c.id', 'name' => 'c.cat_name', 'cposition' => 'c.disp_position'), 'categories AS c');
$query->order = array('cposition' => 'c.disp_position ASC');

$params = array();

$cat_list = $db->query($query, $params);
unset ($query, $params);

$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_common['Admin'], $lang_admin_common['Categories']);
define('PUN_ACTIVE_PAGE', 'admin');
require PUN_ROOT.'header.php';

generate_admin_menu('categories');

?>
	<div class="blockform">
		<h2><span><?php echo $lang_admin_categories['Add categories head'] ?></span></h2>
		<div class="box">
			<form method="post" action="admin_categories.php">
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

<?php if (!empty($cat_list)): ?>		<h2 class="block2"><span><?php echo $lang_admin_categories['Delete categories head'] ?></span></h2>
		<div class="box">
			<form method="post" action="admin_categories.php">
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

	foreach ($cat_list as $cur_cat)
		echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_cat['id'].'">'.pun_htmlspecialchars($cur_cat['cat_name']).'</option>'."\n";

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

<?php if (!empty($cat_list)): ?>		<h2 class="block2"><span><?php echo $lang_admin_categories['Edit categories head'] ?></span></h2>
		<div class="box">
			<form method="post" action="admin_categories.php">
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

	foreach ($cat_list as $cur_cat)
	{

?>
								<tr>
									<td class="tcl"><input type="text" name="cat[<?php echo $cur_cat['id'] ?>][name]" value="<?php echo pun_htmlspecialchars($cur_cat['cat_name']) ?>" size="35" maxlength="80" /></td>
									<td><input type="text" name="cat[<?php echo $cur_cat['id'] ?>][order]" value="<?php echo $cur_cat['disp_position'] ?>" size="3" maxlength="3" /></td>
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
