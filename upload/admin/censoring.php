<?php
/***********************************************************************

  Copyright (C) 2002-2008  PunBB.org

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


if (!defined('PUN_ROOT'))
	define('PUN_ROOT', '../');
require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/common_admin.php';

($hook = get_hook('acs_start')) ? eval($hook) : null;

if (!$pun_user['is_admmod'])
	message($lang_common['No permission']);

// Load the admin.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/admin.php';


// Add a censor word
if (isset($_POST['add_word']))
{
	$search_for = trim($_POST['new_search_for']);
	$replace_with = trim($_POST['new_replace_with']);

	if ($search_for == '' || $replace_with == '')
		message($lang_admin['Must enter text message']);

	($hook = get_hook('acs_add_word_form_submitted')) ? eval($hook) : null;

	$query = array(
		'INSERT'	=> 'search_for, replace_with',
		'INTO'		=> 'censoring',
		'VALUES'	=> '\''.$pun_db->escape($search_for).'\', \''.$pun_db->escape($replace_with).'\''
	);

	($hook = get_hook('acs_qr_add_censor')) ? eval($hook) : null;
	$pun_db->query_build($query) or error(__FILE__, __LINE__);

	// Regenerate the censor cache
	require_once PUN_ROOT.'include/cache.php';
	generate_censors_cache();

	redirect(pun_link($pun_url['admin_censoring']), $lang_admin['Censor word added'].' '.$lang_admin['Redirect']);
}


// Update a censor word
else if (isset($_POST['update']))
{
	$id = intval(key($_POST['update']));

	$search_for = trim($_POST['search_for'][$id]);
	$replace_with = trim($_POST['replace_with'][$id]);

	if ($search_for == '' || $replace_with == '')
		message($lang_admin['Must enter text message']);

	($hook = get_hook('acs_update_form_submitted')) ? eval($hook) : null;

	$query = array(
		'UPDATE'	=> 'censoring',
		'SET'		=> 'search_for=\''.$pun_db->escape($search_for).'\', replace_with=\''.$pun_db->escape($replace_with).'\'',
		'WHERE'		=> 'id='.$id
	);

	($hook = get_hook('acs_qr_update_censor')) ? eval($hook) : null;
	$pun_db->query_build($query) or error(__FILE__, __LINE__);

	// Regenerate the censor cache
	require_once PUN_ROOT.'include/cache.php';
	generate_censors_cache();

	redirect(pun_link($pun_url['admin_censoring']), $lang_admin['Censor word updated'].' '.$lang_admin['Redirect']);
}


// Remove a censor word
else if (isset($_POST['remove']))
{
	$id = intval(key($_POST['remove']));

	($hook = get_hook('acs_remove_form_submitted')) ? eval($hook) : null;

	$query = array(
		'DELETE'	=> 'censoring',
		'WHERE'		=> 'id='.$id
	);

	($hook = get_hook('acs_qr_delete_censor')) ? eval($hook) : null;
	$pun_db->query_build($query) or error(__FILE__, __LINE__);

	// Regenerate the censor cache
	require_once PUN_ROOT.'include/cache.php';
	generate_censors_cache();

	redirect(pun_link($pun_url['admin_censoring']), $lang_admin['Censor word removed'].' '.$lang_admin['Redirect']);
}


// Load the cached censors
if (file_exists(PUN_CACHE_DIR.'cache_censors.php'))
	include PUN_CACHE_DIR.'cache_censors.php';

if (!defined('PUN_CENSORS_LOADED'))
{
	require_once PUN_ROOT.'include/cache.php';
	generate_censors_cache();
	require PUN_CACHE_DIR.'cache_censors.php';
}


// Setup the form
$pun_page['part_count'] = $pun_page['fld_count'] = $pun_page['set_count'] = 0;

// Setup breadcrumbs
$pun_page['crumbs'] = array(
	array($pun_config['o_board_title'], pun_link($pun_url['index'])),
	array($lang_admin['Forum administration'], pun_link($pun_url['admin_index'])),
	$lang_admin['Censoring']
);

($hook = get_hook('acs_pre_header_load')) ? eval($hook) : null;

define('PUN_PAGE_SECTION', 'options');
define('PUN_PAGE', 'admin-censoring');
require PUN_ROOT.'header.php';

?>
<div id="pun-main" class="main sectioned admin">

<?php echo generate_admin_menu(); ?>

	<div class="main-head">
		<h1><span>{ <?php echo end($pun_page['crumbs']) ?> }</span></h1>
	</div>

	<div class="main-content frm">
		<div class="frm-head">
			<h2><span><?php echo $lang_admin['Add censored word head'] ?></span></h2>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo pun_link($pun_url['admin_censoring']) ?>?action=foo">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(pun_link($pun_url['admin_censoring']).'?action=foo') ?>" />
			</div>
			<div class="frm-info">
				<p><?php echo $lang_admin['Add censored word intro']; if ($pun_user['g_id'] == PUN_ADMIN) printf(' '.$lang_admin['Add censored word extra'], '<strong><a href="'.pun_link($pun_url['admin_options_features']).'">'.$lang_admin['Settings'].' - '.$lang_admin['Features'].'</a></strong>') ?></p>
			</div>
			<fieldset class="frm-set set<?php echo ++$pun_page['set_count'] ?>">
				<legend class="frm-legend"><span><?php echo $lang_admin['Add censored word legend'] ?></span></legend>
<?php ($hook = get_hook('acs_add_word_pre_search_for')) ? eval($hook) : null; ?>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Censored word'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $pun_page['fld_count'] ?>" name="new_search_for" size="24" maxlength="60" /></span>
					</label>
				</div>
<?php ($hook = get_hook('acs_add_word_pre_replace_with')) ? eval($hook) : null; ?>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Censored replacement text'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $pun_page['fld_count'] ?>" name="new_replace_with" size="24" maxlength="60" /></span>
					</label>
				</div>
<?php ($hook = get_hook('acs_add_word_end')) ? eval($hook) : null; ?>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" class="button" name="add_word" value=" <?php echo $lang_admin['Add'] ?> " /></span>
			</div>
		</form>
	</div>
<?php

if (!empty($pun_censors))
{
	// Reset fieldset counter
	$pun_page['set_count'] = 0;

?>
	<div class="main-content frm">
		<div class="frm-head">
			<h2><span><?php echo $lang_admin['Edit censored word legend'] ?></span></h2>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo pun_link($pun_url['admin_censoring']) ?>?action=foo">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(pun_link($pun_url['admin_censoring']).'?action=foo') ?>" />
			</div>
<?php

	foreach ($pun_censors as $censor_key => $cur_word)
	{

	?>
			<fieldset class="frm-set set<?php echo ++$pun_page['set_count'] ?>">
				<legend class="frm-legend"><span><?php echo $lang_admin['Edit censored word legend'] ?></span></legend>
<?php ($hook = get_hook('acs_edit_word_pre_search_for')) ? eval($hook) : null; ?>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Censored word'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $pun_page['fld_count'] ?>" name="search_for[<?php echo $cur_word['id'] ?>]" value="<?php echo pun_htmlencode($cur_word['search_for']) ?>" size="24" maxlength="60" /></span>
					</label>
				</div>
<?php ($hook = get_hook('acs_edit_word_pre_replace_with')) ? eval($hook) : null; ?>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Censored replacement text'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $pun_page['fld_count'] ?>" name="replace_with[<?php echo $cur_word['id'] ?>]" value="<?php echo pun_htmlencode($cur_word['replace_with']) ?>" size="24" maxlength="60" /></span>
					</label>
					<span class="submit"><input type="submit" name="update[<?php echo $cur_word['id'] ?>]" value="<?php echo $lang_admin['Update'] ?>" /> <input type="submit" name="remove[<?php echo $cur_word['id'] ?>]" value="<?php echo $lang_admin['Remove'] ?>" /></span>
				</div>
<?php ($hook = get_hook('acs_edit_word_end')) ? eval($hook) : null; ?>
			</fieldset>
	<?php

	}

?>
		</form>
	</div>
<?php

}
else
{

?>
	<div class="main-content frm">
		<div class="frm-head">
			<h2><span><?php echo $lang_admin['Edit censored word legend'] ?></span></h2>
		</div>
		<div class="frm-form">
			<div class="frm-info">
				<p><?php echo $lang_admin['No censored words'] ?></p>
			</div>
		</div>
	</div>
<?php

}

?>
</div>
<?php

require PUN_ROOT.'footer.php';
