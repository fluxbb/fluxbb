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
	message($lang->t('No permission'));

// Load the admin_censoring.php language file
$lang->load('admin_censoring');

// Add a censor word
if (isset($_POST['add_word']))
{
	confirm_referrer('admin_censoring.php');

	$search_for = pun_trim($_POST['new_search_for']);
	$replace_with = pun_trim($_POST['new_replace_with']);

	if ($search_for == '')
		message($lang->t('Must enter word message'));

	$query = $db->insert(array('search_for' => ':search_for', 'replace_with' => ':replace_with'), 'censoring');
	
	$params = array(':search_for' => $search_for, ':replace_with' => $replace_with);
	
	$query->run($params);
	unset($query, $params);

	// Regenerate the censoring cache
	$cache->delete('censors');

	redirect('admin_censoring.php', $lang->t('Word added redirect'));
}

// Update a censor word
else if (isset($_POST['update']))
{
	confirm_referrer('admin_censoring.php');

	$id = intval(key($_POST['update']));

	$search_for = pun_trim($_POST['search_for'][$id]);
	$replace_with = pun_trim($_POST['replace_with'][$id]);

	if ($search_for == '')
		message($lang->t('Must enter word message'));

	$query = $db->update(array('search_for' => ':search_for', 'replace_with' => ':replace_with'), 'censoring');
	$query->where = 'id = :id';
	
	$params = array(':search_for' => $search_for, ':replace_with' => $replace_with, ':id' => $id);
	
	$query->run($params);
	unset($query, $params);

	// Regenerate the censoring cache
	$cache->delete('censors');

	redirect('admin_censoring.php', $lang->t('Word updated redirect'));
}

// Remove a censor word
else if (isset($_POST['remove']))
{
	confirm_referrer('admin_censoring.php');

	$id = intval(key($_POST['remove']));

	$query = $db->delete('censoring');
	$query->where = 'id = :id';
	
	$params = array(':id' => $id);
	
	$query->run($params);
	unset($query, $params);

	// Regenerate the censoring cache
	$cache->delete('censors');

	redirect('admin_censoring.php',  $lang->t('Word removed redirect'));
}

$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Admin'), $lang->t('Censoring'));
$focus_element = array('censoring', 'new_search_for');
define('PUN_ACTIVE_PAGE', 'admin');
require PUN_ROOT.'header.php';

generate_admin_menu('censoring');

?>
	<div class="blockform">
		<h2><span><?php echo $lang->t('Censoring head') ?></span></h2>
		<div class="box">
			<form id="censoring" method="post" action="admin_censoring.php">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Add word subhead') ?></legend>
						<div class="infldset">
							<p><?php echo $lang->t('Add word info').' '.($pun_config['o_censoring'] == '1' ? $lang->t('Censoring enabled', '<a href="admin_options.php#censoring">'.$lang->t('Options').'</a>') : $lang->t('Censoring disabled', '<a href="admin_options.php#censoring">'.$lang->t('Options').'</a>')) ?></p>
							<table cellspacing="0">
							<thead>
								<tr>
									<th class="tcl" scope="col"><?php echo $lang->t('Censored word label') ?></th>
									<th class="tc2" scope="col"><?php echo $lang->t('Replacement label') ?></th>
									<th class="hidehead" scope="col"><?php echo $lang->t('Action label') ?></th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td class="tcl"><input type="text" name="new_search_for" size="24" maxlength="60" tabindex="1" /></td>
									<td class="tc2"><input type="text" name="new_replace_with" size="24" maxlength="60" tabindex="2" /></td>
									<td><input type="submit" name="add_word" value="<?php echo $lang->t('Add') ?>" tabindex="3" /></td>
								</tr>
							</tbody>
							</table>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Edit remove subhead') ?></legend>
						<div class="infldset">
<?php

$query = $db->select(array('id' => 'c.id', 'search_for' => 'c.search_for', 'replace_with' => 'c.replace_with'), 'censoring AS c');
$query->order = array('id' => 'c.id ASC');

$result = $db->query($query);
unset($query);

if (!empty($result))
{

?>
							<table cellspacing="0" >
							<thead>
								<tr>
									<th class="tcl" scope="col"><?php echo $lang->t('Censored word label') ?></th>
									<th class="tc2" scope="col"><?php echo $lang->t('Replacement label') ?></th>
									<th class="hidehead" scope="col"><?php echo $lang->t('Action label') ?></th>
								</tr>
							</thead>
							<tbody>
<?php

	foreach ($result as $cur_word)
		echo "\t\t\t\t\t\t\t\t".'<tr><td class="tcl"><input type="text" name="search_for['.$cur_word['id'].']" value="'.pun_htmlspecialchars($cur_word['search_for']).'" size="24" maxlength="60" /></td><td class="tc2"><input type="text" name="replace_with['.$cur_word['id'].']" value="'.pun_htmlspecialchars($cur_word['replace_with']).'" size="24" maxlength="60" /></td><td><input type="submit" name="update['.$cur_word['id'].']" value="'.$lang->t('Update').'" />&#160;<input type="submit" name="remove['.$cur_word['id'].']" value="'.$lang->t('Remove').'" /></td></tr>'."\n";

?>
							</tbody>
							</table>
<?php

}
else
	echo "\t\t\t\t\t\t\t".'<p>'.$lang->t('No words in list').'</p>'."\n";

unset($result);

?>
						</div>
					</fieldset>
				</div>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

require PUN_ROOT.'footer.php';
