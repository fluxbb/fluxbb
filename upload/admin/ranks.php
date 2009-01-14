<?php
/**
 * Rank management page
 *
 * Allows administrators to control the tags given to posters based on their post count
 *
 * @copyright Copyright (C) 2008 FluxBB.org, based on code copyright (C) 2002-2008 PunBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package FluxBB
 */


if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', '../');
require FORUM_ROOT.'include/common.php';
require FORUM_ROOT.'include/common_admin.php';

($hook = get_hook('ark_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

if ($forum_user['g_id'] != FORUM_ADMIN)
	message($lang_common['No permission']);

// Load the admin.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin_common.php';
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin_ranks.php';


// Add a rank
if (isset($_POST['add_rank']))
{
	$rank = forum_trim($_POST['new_rank']);
	$min_posts = intval($_POST['new_min_posts']);

	if ($rank == '')
		message($lang_admin_ranks['Title message']);

	if ($min_posts < 0)
		message($lang_admin_ranks['Min posts message']);

	($hook = get_hook('ark_add_rank_form_submitted')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	// Make sure there isn't already a rank with the same min_posts value
	$query = array(
		'SELECT'	=> '1',
		'FROM'		=> 'ranks AS r',
		'WHERE'		=> 'min_posts='.$min_posts
	);

	($hook = get_hook('ark_add_rank_qr_check_rank_collision')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	if ($forum_db->num_rows($result))
		message(sprintf($lang_admin_ranks['Min posts occupied message'], $min_posts));

	$query = array(
		'INSERT'	=> 'rank, min_posts',
		'INTO'		=> 'ranks',
		'VALUES'	=> '\''.$forum_db->escape($rank).'\', '.$min_posts
	);

	($hook = get_hook('ark_add_rank_qr_add_rank')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	// Regenerate the ranks cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';

	generate_ranks_cache();

	($hook = get_hook('ark_add_rank_pre_redirect')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	redirect(forum_link($forum_url['admin_ranks']), $lang_admin_ranks['Rank added'].' '.$lang_admin_common['Redirect']);
}


// Update a rank
else if (isset($_POST['update']))
{
	$id = intval(key($_POST['update']));

	$rank = forum_trim($_POST['rank'][$id]);
	$min_posts = intval($_POST['min_posts'][$id]);

	if ($rank == '')
		message($lang_admin_ranks['Title message']);

	if ($min_posts < 0)
		message($lang_admin_ranks['Min posts message']);

	($hook = get_hook('ark_update_form_submitted')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	// Make sure there isn't already a rank with the same min_posts value
	$query = array(
		'SELECT'	=> '1',
		'FROM'		=> 'ranks AS r',
		'WHERE'		=> 'id!='.$id.' AND min_posts='.$min_posts
	);

	($hook = get_hook('ark_update_qr_check_rank_collision')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	if ($forum_db->num_rows($result))
		message(sprintf($lang_admin_ranks['Min posts occupied message'], $min_posts));

	$query = array(
		'UPDATE'	=> 'ranks',
		'SET'		=> 'rank=\''.$forum_db->escape($rank).'\', min_posts='.$min_posts,
		'WHERE'		=> 'id='.$id
	);

	($hook = get_hook('ark_update_qr_update_rank')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	// Regenerate the ranks cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';

	generate_ranks_cache();

	($hook = get_hook('ark_update_pre_redirect')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	redirect(forum_link($forum_url['admin_ranks']), $lang_admin_ranks['Rank updated'].' '.$lang_admin_common['Redirect']);
}


// Remove a rank
else if (isset($_POST['remove']))
{
	$id = intval(key($_POST['remove']));

	($hook = get_hook('ark_remove_form_submitted')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	$query = array(
		'DELETE'	=> 'ranks',
		'WHERE'		=> 'id='.$id
	);

	($hook = get_hook('ark_remove_qr_delete_rank')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	// Regenerate the ranks cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';

	generate_ranks_cache();

	($hook = get_hook('ark_remove_pre_redirect')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	redirect(forum_link($forum_url['admin_ranks']), $lang_admin_ranks['Rank removed'].' '.$lang_admin_common['Redirect']);
}


// Load the cached ranks
if (file_exists(FORUM_CACHE_DIR.'cache_ranks.php'))
	include FORUM_CACHE_DIR.'cache_ranks.php';

if (!defined('FORUM_RANKS_LOADED'))
{
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';

	generate_ranks_cache();
	require FORUM_CACHE_DIR.'cache_ranks.php';
}


// Setup the form
$forum_page['fld_count'] = $forum_page['item_count'] = $forum_page['group_count'] = 0;

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
	$lang_admin_common['Ranks']
);

($hook = get_hook('ark_pre_header_load')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

define('FORUM_PAGE_SECTION', 'users');
define('FORUM_PAGE', 'admin-ranks');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('ark_main_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_admin_ranks['Rank head'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<div class="ct-box">
			<p><?php printf($lang_admin_ranks['Add rank intro'], '<strong><a href="'.forum_link($forum_url['admin_settings_features']).'">'.$lang_admin_common['Settings'].' - '.$lang_admin_common['Features'].'</a></strong>') ?></p>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_ranks']) ?>?action=foo">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_ranks']).'?action=foo') ?>" />
			</div>
			<fieldset class="frm-group frm-hdgroup group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_admin_ranks['Add rank legend'] ?></strong></legend>
<?php ($hook = get_hook('ark_pre_add_rank_fieldset')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				<fieldset class="mf-set set<?php echo ++$forum_page['item_count'] ?><?php echo ($forum_page['item_count'] == 1) ? ' mf-head' : ' mf-extra' ?>">
					<legend><span><?php echo $lang_admin_ranks['New rank'] ?></span></legend>
					<div class="mf-box">
<?php ($hook = get_hook('ark_pre_add_rank_title')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
						<div class="mf-field mf-field1 text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span class="fld-label"><?php echo $lang_admin_ranks['Rank title label'] ?></span></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="new_rank" size="24" maxlength="50" /></span>
						</div>
<?php ($hook = get_hook('ark_pre_add_rank_min_posts')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
						<div class="mf-field text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span class="fld-label"><?php echo $lang_admin_ranks['Min posts label'] ?></span></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="new_min_posts" size="7" maxlength="7" /></span>
						</div>
<?php ($hook = get_hook('ark_pre_add_rank_submit')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
						<div class="mf-field text">
							<span class="fld-input"><input type="submit" name="add_rank" value="<?php echo $lang_admin_ranks['Add rank'] ?>" /></span>
						</div>
					</div>
<?php ($hook = get_hook('ark_pre_add_rank_fieldset_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				</fieldset>
<?php ($hook = get_hook('ark_add_rank_fieldset_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			</fieldset>
		</form>
<?php

if (!empty($forum_ranks))
{
	// Reset fieldset counter
	$forum_page['group_count'] = $forum_page['item_count'] = 0;

?>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_ranks']) ?>?action=foo">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_ranks']).'?action=foo') ?>" />
			</div>
			<fieldset class="frm-group group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><span><?php echo $lang_admin_ranks['Existing ranks legend'] ?></span></legend>
<?php

	foreach ($forum_ranks as $rank_key => $cur_rank)
	{

	?>
<?php ($hook = get_hook('ark_pre_edit_cur_rank_fieldset')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				<fieldset class="mf-set mf-extra set<?php echo ++$forum_page['item_count'] ?>">
					<legend><span><?php echo $lang_admin_ranks['Existing ranks'] ?></span></legend>
					<div class="mf-box">
<?php ($hook = get_hook('ark_pre_edit_cur_rank_title')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
						<div class="mf-field text mf-field1">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_ranks['Rank title label'] ?></span></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="rank[<?php echo $cur_rank['id'] ?>]" value="<?php echo forum_htmlencode($cur_rank['rank']) ?>" size="24" maxlength="50" /></span>
						</div>
<?php ($hook = get_hook('ark_pre_edit_cur_rank_min_posts')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
						<div class="mf-field text">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span class="fld-label"><?php echo $lang_admin_ranks['Min posts label'] ?></span></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="min_posts[<?php echo $cur_rank['id'] ?>]" value="<?php echo $cur_rank['min_posts'] ?>" size="7" maxlength="7" /></span>
						</div>
<?php ($hook = get_hook('ark_pre_edit_cur_rank_submit')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
						<div class="mf-field text">
							<span class="fld-input"><input type="submit" name="update[<?php echo $cur_rank['id'] ?>]" value="<?php echo $lang_admin_ranks['Update'] ?>" /> <input type="submit" name="remove[<?php echo $cur_rank['id'] ?>]" value="<?php echo $lang_admin_ranks['Remove'] ?>" /></span>
						</div>
					</div>
<?php ($hook = get_hook('ark_pre_edit_cur_rank_fieldset_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				</fieldset>
	<?php

		($hook = get_hook('ark_edit_cur_rank_fieldset_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	}

?>
			</fieldset>
		</form>
<?php

}

?>
	</div>
<?php

($hook = get_hook('ark_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
