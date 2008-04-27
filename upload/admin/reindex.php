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

// Tell common.php that we don't want output buffering
define('PUN_DISABLE_BUFFERING', 1);

require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/common_admin.php';

($hook = get_hook('ari_start')) ? eval($hook) : null;

if ($pun_user['g_id'] != PUN_ADMIN)
	message($lang_common['No permission']);

// Load the admin.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/admin.php';

if (isset($_GET['i_per_page']) && isset($_GET['i_start_at']))
{
	$per_page = intval($_GET['i_per_page']);
	$start_at = intval($_GET['i_start_at']);
	if ($per_page < 1 || $start_at < 1)
		message($lang_common['Bad request']);

	// We validate the CSRF token. If it's set in POST and we're at this point, the token is valid.
	// If it's in GET, we need to make sure it's valid.
	if (!isset($_POST['csrf_token']) && (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== generate_form_token('reindex'.$pun_user['id'])))
		csrf_confirm_form();

	($hook = get_hook('ari_cycle_start')) ? eval($hook) : null;

	@set_time_limit(0);

	// If this is the first cycle of posts we empty the search index before we proceed
	if (isset($_GET['i_empty_index']))
	{
		$query = array(
			'DELETE'	=> 'search_matches'
		);

		($hook = get_hook('ari_qr_empty_search_matches')) ? eval($hook) : null;
		$pun_db->query_build($query) or error(__FILE__, __LINE__);

		$query = array(
			'DELETE'	=> 'search_words'
		);

		($hook = get_hook('ari_qr_empty_search_words')) ? eval($hook) : null;
		$pun_db->query_build($query) or error(__FILE__, __LINE__);

		// Reset the sequence for the search words (not needed for SQLite)
		switch ($db_type)
		{
			case 'mysql':
			case 'mysqli':
				$result = $pun_db->query('ALTER TABLE '.$pun_db->prefix.'search_words auto_increment=1') or error(__FILE__, __LINE__);
				break;

			case 'pgsql';
				$result = $pun_db->query('SELECT setval(\''.$pun_db->prefix.'search_words_id_seq\', 1, false)') or error(__FILE__, __LINE__);
		}
	}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html lang="<?php $lang_common['lang_identifier'] ?>" dir="<?php echo $lang_common['lang_direction'] ?>">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<title><?php echo pun_htmlencode($pun_config['o_board_title']) ?> - Rebuilding search index …</title>
<style type="text/css">
body {
	font: 68.75% Verdana, Arial, Helvetica, sans-serif;
	color: #333333;
	background-color: #FFFFFF
}
</style>
</head>
<body>

<p><?php echo $lang_admin['Rebuilding index'] ?></p>

<?php

	require PUN_ROOT.'include/search_idx.php';

	// Fetch posts to process
	$query = array(
		'SELECT'	=> 'p.id, p.message, t.id, t.subject, t.first_post_id',
		'FROM'		=> 'posts AS p',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'topics AS t',
				'ON'			=> 't.id=p.topic_id'
			)
		),
		'WHERE'		=> 'p.id>='.$start_at,
		'ORDER BY'	=> 'p.id',
		'LIMIT'		=> $per_page
	);

	($hook = get_hook('ari_qr_fetch_posts')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);

	$post_id = 0;
	echo '<p>';
	while ($cur_post = $pun_db->fetch_row($result))
	{
		printf($lang_admin['Processing post'], $cur_post[0], $cur_post[2]).'<br />'."\n";

		if ($cur_post[0] == $cur_post[4])	// This is the "topic post" so we have to index the subject as well
			update_search_index('post', $cur_post[0], $cur_post[1], $cur_post[3]);
		else
			update_search_index('post', $cur_post[0], $cur_post[1]);

		$post_id = $cur_post[0];
	}
	echo '</p>';

	// Check if there is more work to do
	$query = array(
		'SELECT'	=> 'p.id',
		'FROM'		=> 'posts AS p',
		'WHERE'		=> 'p.id>'.$post_id,
		'ORDER BY'	=> 'p.id',
		'LIMIT'		=> '1'
	);

	($hook = get_hook('ari_qr_find_next_post')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);

	$query_str = ($pun_db->num_rows($result)) ? '?i_per_page='.$per_page.'&i_start_at='.$pun_db->result($result).'&csrf_token='.generate_form_token('reindex'.$pun_user['id']) : '';

	($hook = get_hook('ari_cycle_end')) ? eval($hook) : null;

	$pun_db->end_transaction();
	$pun_db->close();

	exit('<script type="text/javascript">window.location="'.pun_link($pun_url['admin_reindex']).$query_str.'"</script><br />'.$lang_admin['Javascript redirect'].' <a href="'.pun_link($pun_url['admin_reindex']).$query_str.'">'.$lang_admin['Click to continue'].'</a>.');
}


// Get the first post ID from the db
$query = array(
	'SELECT'	=> 'p.id',
	'FROM'		=> 'posts AS p',
	'ORDER BY'	=> 'p.id',
	'LIMIT'		=> '1'
);

($hook = get_hook('ari_qr_find_lowest_post_id')) ? eval($hook) : null;
$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
if ($pun_db->num_rows($result))
	$first_id = $pun_db->result($result);

// Setup form
$pun_page['set_count'] = $pun_page['fld_count'] = 0;

// Setup breadcrumbs
$pun_page['crumbs'] = array(
	array($pun_config['o_board_title'], pun_link($pun_url['index'])),
	array($lang_admin['Forum administration'], pun_link($pun_url['admin_index'])),
	$lang_admin['Rebuild index']
);

define('PUN_PAGE_SECTION', 'management');
define('PUN_PAGE', 'admin-reindex');
require PUN_ROOT.'header.php';

?>
<div id="pun-main" class="main sectioned admin">

<?php echo generate_admin_menu(); ?>

	<div class="main-head">
		<h1><span>{ <?php echo end($pun_page['crumbs']) ?> }</span></h1>
	</div>

	<div class="main-content frm">
		<div class="frm-head">
			<h2><span><?php echo $lang_admin['Reindex heading'] ?></span></h2>
		</div>
		<div class="frm-info">
			<p><?php echo $lang_admin['Reindex info'] ?></p>
		</div>
		<form class="frm-form" method="get" accept-charset="utf-8" action="<?php echo pun_link($pun_url['admin_reindex']) ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token('reindex'.$pun_user['id']) ?>" />
			</div>
<?php ($hook = get_hook('ari_pre_rebuild_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set<?php echo ++$pun_page['set_count'] ?>">
				<legend class="frm-legend"><span><?php echo $lang_admin['Rebuild index legend'] ?></span></legend>
<?php ($hook = get_hook('ari_pre_per_page')) ? eval($hook) : null; ?>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Posts per cycle'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $pun_page['fld_count'] ?>" name="i_per_page" size="7" maxlength="7" value="100" /></span>
						<span class="fld-help"><?php echo $lang_admin['Posts per cycle info'] ?></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Starting post'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $pun_page['fld_count'] ?>" name="i_start_at" size="7" maxlength="7" value="<?php echo (isset($first_id)) ? $first_id : 0 ?>" /></span>
						<span class="fld-help"><?php echo $lang_admin['Starting post info'] ?></span>
					</label>
				</div>
				<div class="radbox checkbox">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>"><span class="fld-label"><?php echo $lang_admin['Empty index'] ?></span><br /><input type="checkbox" id="fld<?php echo $pun_page['fld_count'] ?>" name="i_empty_index" value="1" checked="checked" /> <?php echo $lang_admin['Empty index info'] ?></label>
				</div>
<?php ($hook = get_hook('ari_rebuild_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php ($hook = get_hook('ari_pre_infobox')) ? eval($hook) : null; ?>
			<div class="frm-info">
				<p class="important"><?php echo $lang_admin['Reindex warning'] ?></p>
				<p class="warn"><?php echo $lang_admin['Empty index warning'] ?></p>
			</div>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="rebuild_index" value="<?php echo $lang_admin['Rebuild index'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

require PUN_ROOT.'footer.php';
