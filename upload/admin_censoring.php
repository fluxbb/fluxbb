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


if ($pun_user['g_id'] > PUN_MOD)
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


$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / Admin / Censoring';
$focus_element = array('censoring', 'new_search_for');
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
							<p>Enter a word that you want to censor and the replacement text for this word. Wildcards are accepted (i.e. *some* would match somewhere and lonesome). Censor words also affect usernames. New users will not be able to register with usernames containing any censored words. The search is case insensitive. <strong>Censor words must be enabled in <a href="admin_options.php#censoring">Options</a> for this to have any effect.</strong></p>
							<table  cellspacing="0">
							<thead>
								<tr>
									<th class="tcl" scope="col">Censored&nbsp;word</th>
									<th class="tc2" scope="col">Replacement&nbsp;text</th>
									<th class="hidehead" scope="col">Action</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td><input type="text" name="new_search_for" size="24" maxlength="60" tabindex="1" /></td>
									<td><input type="text" name="new_replace_with" size="24" maxlength="60" tabindex="2" /></td>
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
		echo "\t\t\t\t\t\t\t\t".'<tr><td><input type="text" name="search_for['.$cur_word['id'].']" value="'.pun_htmlspecialchars($cur_word['search_for']).'" size="24" maxlength="60" /></td><td><input type="text" name="replace_with['.$cur_word['id'].']" value="'.pun_htmlspecialchars($cur_word['replace_with']).'" size="24" maxlength="60" /></td><td><input type="submit" name="update['.$cur_word['id'].']" value="Update" />&nbsp;<input type="submit" name="remove['.$cur_word['id'].']" value="Remove" /></td></tr>'."\n";

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
