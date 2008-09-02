<?php
/**
 * Search index rebuilding script
 *
 * Allows administrators to rebuild the index used to search the posts and topics.
 *
 * @copyright Copyright (C) 2008 FluxBB.org, based on code copyright (C) 2002-2008 PunBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package FluxBB
 */


if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', '../');

// Tell common.php that we don't want output buffering
define('FORUM_DISABLE_BUFFERING', 1);

require FORUM_ROOT.'include/common.php';
require FORUM_ROOT.'include/common_admin.php';

($hook = get_hook('ari_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

if ($forum_user['g_id'] != FORUM_ADMIN)
	message($lang_common['No permission']);

// Load the admin.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin_common.php';
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin_reindex.php';


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

	($hook = get_hook('ari_cycle_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	@set_time_limit(0);

	// If this is the first cycle of posts we empty the search index before we proceed
	if (isset($_GET['i_empty_index']))
	{
		$query = array(
			'DELETE'	=> 'search_matches'
		);

		($hook = get_hook('ari_cycle_qr_empty_search_matches')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		$query = array(
			'DELETE'	=> 'search_words'
		);

		($hook = get_hook('ari_cycle_qr_empty_search_words')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// Reset the sequence for the search words (not needed for SQLite)
		switch ($db_type)
		{
			case 'mysql':
			case 'mysqli':
			case 'mysql_innodb':
			case 'mysqli_innodb':
				$result = $forum_db->query('ALTER TABLE '.$forum_db->prefix.'search_words auto_increment=1') or error(__FILE__, __LINE__);
				break;

			case 'pgsql';
				$result = $forum_db->query('SELECT setval(\''.$forum_db->prefix.'search_words_id_seq\', 1, false)') or error(__FILE__, __LINE__);
		}
	}

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		$lang_admin_reindex['Rebuilding index title']
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

<p><?php echo $lang_admin_reindex['Rebuilding index'] ?></p>

<?php

	if (!defined('FORUM_SEARCH_IDX_FUNCTIONS_LOADED'))
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

	($hook = get_hook('ari_cycle_qr_fetch_posts')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$post_id = 0;
	echo '<p>';
	while ($cur_post = $forum_db->fetch_row($result))
	{
		echo sprintf($lang_admin_reindex['Processing post'], $cur_post[0], $cur_post[2]).'<br />'."\n";

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

	($hook = get_hook('ari_cycle_qr_find_next_post')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$query_str = ($forum_db->num_rows($result)) ? '?i_per_page='.$per_page.'&i_start_at='.$forum_db->result($result).'&csrf_token='.generate_form_token('reindex'.$forum_user['id']) : '';

	($hook = get_hook('ari_cycle_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	$forum_db->end_transaction();
	$forum_db->close();

	exit('<script type="text/javascript">window.location="'.forum_link($forum_url['admin_reindex']).$query_str.'"</script><br />'.$lang_admin_reindex['Javascript redirect'].' <a href="'.forum_link($forum_url['admin_reindex']).$query_str.'">'.$lang_admin_reindex['Click to continue'].'</a>.');
}


// Get the first post ID from the db
$query = array(
	'SELECT'	=> 'p.id',
	'FROM'		=> 'posts AS p',
	'ORDER BY'	=> 'p.id',
	'LIMIT'		=> '1'
);

($hook = get_hook('ari_qr_find_lowest_post_id')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
if ($forum_db->num_rows($result))
	$first_id = $forum_db->result($result);

// Setup form
$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
	$lang_admin_common['Rebuild index']
);

($hook = get_hook('ari_pre_header_load')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

define('FORUM_PAGE_SECTION', 'management');
define('FORUM_PAGE', 'admin-reindex');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('ari_main_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
	<div class="frm-head">
		<h2><span><?php echo $lang_admin_reindex['Reindex heading'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<div class="ct-box">
			<p><?php echo $lang_admin_reindex['Reindex info'] ?></p>
		</div>
		<form class="frm-form" method="get" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_reindex']) ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token('reindex'.$forum_user['id']) ?>" />
			</div>
<?php ($hook = get_hook('ari_pre_rebuild_fieldset')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			<fieldset class="frm-group group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><span><?php echo $lang_admin_reindex['Rebuild index legend'] ?></span></legend>
<?php ($hook = get_hook('ari_pre_rebuild_per_page')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_reindex['Posts per cycle'] ?></span> <small><?php echo $lang_admin_reindex['Posts per cycle info'] ?></small></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="i_per_page" size="7" maxlength="7" value="100" /></span>
					</div>
				</div>
<?php ($hook = get_hook('ari_pre_rebuild_start_post')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span class="fld-label"><?php echo $lang_admin_reindex['Starting post'] ?></span> <small><?php echo $lang_admin_reindex['Starting post info'] ?></small></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="i_start_at" size="7" maxlength="7" value="<?php echo (isset($first_id)) ? $first_id : 0 ?>" /></span>
					</div>
				</div>
<?php ($hook = get_hook('ari_pre_rebuild_empty_index')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box checkbox">
						<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="i_empty_index" value="1" checked="checked" /></span>
						<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_reindex['Empty index'] ?></span> <?php echo $lang_admin_reindex['Empty index info'] ?></label>
					</div>
				</div>
<?php ($hook = get_hook('ari_pre_rebuild_fieldset_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			</fieldset>
<?php ($hook = get_hook('ari_rebuild_fieldset_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			<div class="ct-box warn-box">
				<p class="important"><?php echo $lang_admin_reindex['Reindex warning'] ?></p>
				<p class="warn"><?php echo $lang_admin_reindex['Empty index warning'] ?></p>
			</div>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="rebuild_index" value="<?php echo $lang_admin_reindex['Rebuild index'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

($hook = get_hook('ari_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
