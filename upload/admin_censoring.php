<?php

/*---

	Copyright (C) 2008-2010 FluxBB.org
	based on code copyright (C) 2002-2005 Rickard Andersson
	License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher

---*/

// Tell header.php to use the admin template
define('PUN_ADMIN_CONSOLE', 1);

define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/common_admin.php';


if (!$pun_user['is_admmod'])
	message($lang_common['No permission']);


// Add a censor word
if (isset($_POST['add_word']))
{
	confirm_referrer('admin_censoring.php');

	$search_for = trim($_POST['new_search_for']);
	$replace_with = trim($_POST['new_replace_with']);

	if ($search_for == '' || $replace_with == '')
		message('You must enter both a word to censor and text to replace it with.');

	$db->query('INSERT INTO '.$db->prefix.'censoring (search_for, replace_with) VALUES (\''.$db->escape($search_for).'\', \''.$db->escape($replace_with).'\')') or error('Unable to add censor word', __FILE__, __LINE__, $db->error());

	redirect('admin_censoring.php', 'Censor word added. Redirecting &hellip;');
}

// Update a censor word
else if (isset($_POST['update']))
{
	confirm_referrer('admin_censoring.php');

	$id = intval(key($_POST['update']));

	$search_for = trim($_POST['search_for'][$id]);
	$replace_with = trim($_POST['replace_with'][$id]);

	if ($search_for == '' || $replace_with == '')
		message('You must enter both text to search for and text to replace with.');

	$db->query('UPDATE '.$db->prefix.'censoring SET search_for=\''.$db->escape($search_for).'\', replace_with=\''.$db->escape($replace_with).'\' WHERE id='.$id) or error('Unable to update censor word', __FILE__, __LINE__, $db->error());

	redirect('admin_censoring.php', 'Censor word updated. Redirecting &hellip;');
}

// Remove a censor word
else if (isset($_POST['remove']))
{
	confirm_referrer('admin_censoring.php');

	$id = intval(key($_POST['remove']));

	$db->query('DELETE FROM '.$db->prefix.'censoring WHERE id='.$id) or error('Unable to delete censor word', __FILE__, __LINE__, $db->error());

	redirect('admin_censoring.php', 'Censor word removed. Redirecting &hellip;');
}

$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), 'Admin', 'Censoring');
$focus_element = array('censoring', 'new_search_for');
define('FORUM_ACTIVE_PAGE', 'admin');
require PUN_ROOT.'header.php';

generate_admin_menu('censoring');

?>
	<div class="blockform">
		<h2><span>Censoring</span></h2>
		<div class="box">
			<form id="censoring" method="post" action="admin_censoring.php?action=foo">
				<div class="inform">
					<fieldset>
						<legend>Add word</legend>
						<div class="infldset">
							<p>Enter a word that you want to censor and the replacement text for this word. Wildcards are accepted (i.e. *some* would match somewhere and lonesome). Censor words also affect usernames. New users will not be able to register with usernames containing any censored words. The search is case-insensitive. <strong>Censor words must be enabled in <a href="admin_options.php#censoring">Options</a> for this to have any effect.</strong></p>
							<table cellspacing="0">
							<thead>
								<tr>
									<th class="tcl" scope="col">Censored&nbsp;word</th>
									<th class="tc2" scope="col">Replacement&nbsp;text</th>
									<th class="hidehead" scope="col">Action</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td class="tcl"><input type="text" name="new_search_for" size="24" maxlength="60" tabindex="1" /></td>
									<td class="tc2"><input type="text" name="new_replace_with" size="24" maxlength="60" tabindex="2" /></td>
									<td><input type="submit" name="add_word" value=" Add " tabindex="3" /></td>
								</tr>
							</tbody>
							</table>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend>Edit/remove words</legend>
						<div class="infldset">
<?php

$result = $db->query('SELECT id, search_for, replace_with FROM '.$db->prefix.'censoring ORDER BY id') or error('Unable to fetch censor word list', __FILE__, __LINE__, $db->error());
if ($db->num_rows($result))
{

?>
							<table cellspacing="0" >
							<thead>
								<tr>
									<th class="tcl" scope="col">Censored&nbsp;word</th>
									<th class="tc2" scope="col">Replacement&nbsp;text</th>
									<th class="hidehead" scope="col">Actions</th>
								</tr>
							</thead>
							<tbody>
<?php

	while ($cur_word = $db->fetch_assoc($result))
		echo "\t\t\t\t\t\t\t\t".'<tr><td class="tcl"><input type="text" name="search_for['.$cur_word['id'].']" value="'.pun_htmlspecialchars($cur_word['search_for']).'" size="24" maxlength="60" /></td><td class="tc2"><input type="text" name="replace_with['.$cur_word['id'].']" value="'.pun_htmlspecialchars($cur_word['replace_with']).'" size="24" maxlength="60" /></td><td><input type="submit" name="update['.$cur_word['id'].']" value="Update" />&nbsp;<input type="submit" name="remove['.$cur_word['id'].']" value="Remove" /></td></tr>'."\n";

?>
							</tbody>
							</table>
<?php

}
else
	echo "\t\t\t\t\t\t\t".'<p>No censor words in list.</p>'."\n";

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
