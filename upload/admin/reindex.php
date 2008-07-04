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

// Tell common.php that we don't want output buffering
define('FORUM_DISABLE_BUFFERING', 1);

require FORUM_ROOT.'include/common.php';
require FORUM_ROOT.'include/common_admin.php';

($hook = get_hook('ari_start')) ? eval($hook) : null;

if ($forum_user['g_id'] != FORUM_ADMIN)
	message($lang_common['No permission']);

// Load the admin.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin.php';

if (isset($_GET['i_per_page']) && isset($_GET['i_start_at']))
{
	$per_page = intval($_GET['i_per_page']);
	$start_at = intval($_GET['i_start_at']);
	if ($per_page < 1 || $start_at < 1)
		message($lang_common['Bad request']);

	// We validate the CSRF token. If it's set in POST and we're at this point, the token is valid.
	// If it's in GET, we need to make sure it's valid.
	if (!isset($_POST['csrf_token']) && (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== generate_form_token('reindex'.$forum_user['id'])))
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
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		$query = array(
			'DELETE'	=> 'search_words'
		);

		($hook = get_hook('ari_qr_empty_search_words')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// Reset the sequence for the search words (not needed for SQLite)
		switch ($db_type)
		{
			case 'mysql':
			case 'mysqli':
				$result = $forum_db->query('ALTER TABLE '.$forum_db->prefix.'search_words auto_increment=1') or error(__FILE__, __LINE__);
				break;

			case 'pgsql';
				$result = $forum_db->query('SELECT setval(\''.$forum_db->prefix.'search_words_id_seq\', 1, false)') or error(__FILE__, __LINE__);
		}
	}

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		$lang_admin['Rebuilding index title']
	);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html lang="<?php $lang_common['lang_identifier'] ?>" dir="<?php echo $lang_common['lang_direction'] ?>">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<title><?php echo generate_crumbs(true) ?></title>
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

	require FORUM_ROOT.'include/search_idx.php';

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
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$post_id = 0;
	echo '<p>';
	while ($cur_post = $forum_db->fetch_row($result))
	{
		echo sprintf($lang_admin['Processing post'], $cur_post[0], $cur_post[2]).'<br />'."\n";

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
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$query_str = ($forum_db->num_rows($result)) ? '?i_per_page='.$per_page.'&i_start_at='.$forum_db->result($result).'&csrf_token='.generate_form_token('reindex'.$forum_user['id']) : '';

	($hook = get_hook('ari_cycle_end')) ? eval($hook) : null;

	$forum_db->end_transaction();
	$forum_db->close();

	exit('<script type="text/javascript">window.location="'.forum_link($forum_url['admin_reindex']).$query_str.'"</script><br />'.$lang_admin['Javascript redirect'].' <a href="'.forum_link($forum_url['admin_reindex']).$query_str.'">'.$lang_admin['Click to continue'].'</a>.');
}


// Get the first post ID from the db
$query = array(
	'SELECT'	=> 'p.id',
	'FROM'		=> 'posts AS p',
	'ORDER BY'	=> 'p.id',
	'LIMIT'		=> '1'
);

($hook = get_hook('ari_qr_find_lowest_post_id')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
if ($forum_db->num_rows($result))
	$first_id = $forum_db->result($result);

// Setup form
$forum_page['set_count'] = $forum_page['fld_count'] = 0;

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($lang_admin['Forum administration'], forum_link($forum_url['admin_index'])),
	$lang_admin['Rebuild index']
);

($hook = get_hook('ari_pre_header_load')) ? eval($hook) : null;

define('FORUM_PAGE_SECTION', 'management');
define('FORUM_PAGE', 'admin-reindex');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('ari_main_output_start')) ? eval($hook) : null;

?>
<div id="brd-main" class="main sectioned admin">

	<div class="main-head">
		<h1><span>{ <?php echo end($forum_page['crumbs']) ?> }</span></h1>
	</div>

	<div class="main-content main-frm">
		<div class="frm-head">
			<h2><span><?php echo $lang_admin['Reindex heading'] ?></span></h2>
		</div>
		<div class="frm-info">
			<p><?php echo $lang_admin['Reindex info'] ?></p>
		</div>
		<form class="frm-form" method="get" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_reindex']) ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token('reindex'.$forum_user['id']) ?>" />
			</div>
<?php ($hook = get_hook('ari_pre_rebuild_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-fset fset<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><span><?php echo $lang_admin['Rebuild index legend'] ?></span></legend>
<?php ($hook = get_hook('ari_pre_per_page')) ? eval($hook) : null; ?>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Posts per cycle'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="i_per_page" size="7" maxlength="7" value="100" /></span>
						<span class="fld-help"><?php echo $lang_admin['Posts per cycle info'] ?></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Starting post'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="i_start_at" size="7" maxlength="7" value="<?php echo (isset($first_id)) ? $first_id : 0 ?>" /></span>
						<span class="fld-help"><?php echo $lang_admin['Starting post info'] ?></span>
					</label>
				</div>
				<div class="radbox checkbox">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span class="fld-label"><?php echo $lang_admin['Empty index'] ?></span><br /><input type="checkbox" id="fld<?php echo $forum_page['fld_count'] ?>" name="i_empty_index" value="1" checked="checked" /> <?php echo $lang_admin['Empty index info'] ?></label>
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

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
