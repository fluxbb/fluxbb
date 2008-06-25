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
require FORUM_ROOT.'include/common.php';
require FORUM_ROOT.'include/common_admin.php';

($hook = get_hook('ark_start')) ? eval($hook) : null;

if ($forum_user['g_id'] != FORUM_ADMIN)
	message($lang_common['No permission']);

// Load the admin.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin.php';


// Add a rank
if (isset($_POST['add_rank']))
{
	$rank = forum_trim($_POST['new_rank']);
	$min_posts = intval($_POST['new_min_posts']);

	if ($rank == '')
		message($lang_admin['Title message']);

	if ($min_posts < 0)
		message($lang_admin['Min posts message']);

	($hook = get_hook('ark_add_rank_form_submitted')) ? eval($hook) : null;

	// Make sure there isn't already a rank with the same min_posts value
	$query = array(
		'SELECT'	=> '1',
		'FROM'		=> 'ranks AS r',
		'WHERE'		=> 'min_posts='.$min_posts
	);

	($hook = get_hook('ark_qr_check_rank_collision')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	if ($forum_db->num_rows($result))
		message(sprintf($lang_admin['Min posts occupied message'], $min_posts));

	$query = array(
		'INSERT'	=> 'rank, min_posts',
		'INTO'		=> 'ranks',
		'VALUES'	=> '\''.$forum_db->escape($rank).'\', '.$min_posts
	);

	($hook = get_hook('ark_qr_add_rank')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	// Regenerate the ranks cache
	require_once FORUM_ROOT.'include/cache.php';
	generate_ranks_cache();

	redirect(forum_link($forum_url['admin_ranks']), $lang_admin['Rank added'].' '.$lang_admin['Redirect']);
}


// Update a rank
else if (isset($_POST['update']))
{
	$id = intval(key($_POST['update']));

	$rank = forum_trim($_POST['rank'][$id]);
	$min_posts = intval($_POST['min_posts'][$id]);

	if ($rank == '')
		message($lang_admin['Title message']);

	if ($min_posts < 0)
		message($lang_admin['Min posts message']);

	($hook = get_hook('ark_update_form_submitted')) ? eval($hook) : null;

	// Make sure there isn't already a rank with the same min_posts value
	$query = array(
		'SELECT'	=> '1',
		'FROM'		=> 'ranks AS r',
		'WHERE'		=> 'id!='.$id.' AND min_posts='.$min_posts
	);

	($hook = get_hook('ark_qr_check_rank_collision2')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	if ($forum_db->num_rows($result))
		message(sprintf($lang_admin['Min posts occupied message'], $min_posts));

	$query = array(
		'UPDATE'	=> 'ranks',
		'SET'		=> 'rank=\''.$forum_db->escape($rank).'\', min_posts='.$min_posts,
		'WHERE'		=> 'id='.$id
	);

	($hook = get_hook('ark_qr_update_rank')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	// Regenerate the ranks cache
	require_once FORUM_ROOT.'include/cache.php';
	generate_ranks_cache();

	redirect(forum_link($forum_url['admin_ranks']), $lang_admin['Rank updated'].' '.$lang_admin['Redirect']);
}


// Remove a rank
else if (isset($_POST['remove']))
{
	$id = intval(key($_POST['remove']));

	($hook = get_hook('ark_remove_form_submitted')) ? eval($hook) : null;

	$query = array(
		'DELETE'	=> 'ranks',
		'WHERE'		=> 'id='.$id
	);

	($hook = get_hook('ark_qr_delete_rank')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	// Regenerate the ranks cache
	require_once FORUM_ROOT.'include/cache.php';
	generate_ranks_cache();

	redirect(forum_link($forum_url['admin_ranks']), $lang_admin['Rank removed'].' '.$lang_admin['Redirect']);
}


// Load the cached ranks
if (file_exists(FORUM_CACHE_DIR.'cache_ranks.php'))
	include FORUM_CACHE_DIR.'cache_ranks.php';

if (!defined('FORUM_RANKS_LOADED'))
{
	require_once FORUM_ROOT.'include/cache.php';
	generate_ranks_cache();
	require FORUM_CACHE_DIR.'cache_ranks.php';
}


// Setup the form
$forum_page['fld_count'] = $forum_page['set_count'] = 0;

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($lang_admin['Forum administration'], forum_link($forum_url['admin_index'])),
	$lang_admin['Ranks']
);

($hook = get_hook('ark_pre_header_load')) ? eval($hook) : null;

define('FORUM_PAGE_SECTION', 'users');
define('FORUM_PAGE', 'admin-ranks');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('ark_main_output_start')) ? eval($hook) : null;

?>
<div id="brd-main" class="main sectioned admin">

<?php echo generate_admin_menu(); ?>

	<div class="main-head">
		<h1><span>{ <?php echo end($forum_page['crumbs']) ?> }</span></h1>
	</div>

	<div class="main-content frm">
		<div class="frm-head">
			<h2><span><?php echo $lang_admin['Add new rank'] ?></span></h2>
		</div>
		<div class="frm-info">
			<p><?php printf($lang_admin['Add rank intro'], '<strong><a href="'.forum_link($forum_url['admin_options_features']).'">'.$lang_admin['Settings'].' - '.$lang_admin['Features'].'</a></strong>') ?></p>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_ranks']) ?>?action=foo">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_ranks']).'?action=foo') ?>" />
			</div>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_admin['Add rank legend'] ?></strong></legend>
<?php ($hook = get_hook('ark_add_rank_pre_rank')) ? eval($hook) : null; ?>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
					<span class="fld-label"><?php echo $lang_admin['Rank title'] ?></span><br />
					<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="new_rank" size="24" maxlength="50" /></span>
					</label>
				</div>
<?php ($hook = get_hook('ark_add_rank_pre_min_posts')) ? eval($hook) : null; ?>
				<div class="frm-fld">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Min posts'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="new_min_posts" size="7" maxlength="7" /></span>
					</label>
				</div>
<?php ($hook = get_hook('ark_add_rank_end')) ? eval($hook) : null; ?>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="add_rank" value="<?php echo $lang_admin['Add rank'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

if (!empty($forum_ranks))
{
	// Reset fieldset counter
	$forum_page['set_count'] = 0;

?>
	<div class="main-content frm">
		<div class="frm-head">
			<h2><span><?php echo $lang_admin['Existing ranks intro'] ?></span></h2>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_ranks']) ?>?action=foo">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_ranks']).'?action=foo') ?>" />
			</div>
<?php

	foreach ($forum_ranks as $rank_key => $cur_rank)
	{

	?>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><span><?php echo $lang_admin['Rank'].' '.($rank_key + 1) ?></span></legend>
<?php ($hook = get_hook('ark_edit_rank_pre_rank')) ? eval($hook) : null; ?>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Rank title'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="rank[<?php echo $cur_rank['id'] ?>]" value="<?php echo forum_htmlencode($cur_rank['rank']) ?>" size="24" maxlength="50" /></span>
					</label>
				</div>
<?php ($hook = get_hook('ark_edit_rank_pre_min_posts')) ? eval($hook) : null; ?>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Min posts'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="min_posts[<?php echo $cur_rank['id'] ?>]" value="<?php echo $cur_rank['min_posts'] ?>" size="7" maxlength="7" /></span>
					</label>
					<span class="submit"><input type="submit" name="update[<?php echo $cur_rank['id'] ?>]" value="<?php echo $lang_admin['Update'] ?>" /> <input type="submit" name="remove[<?php echo $cur_rank['id'] ?>]" value="<?php echo $lang_admin['Remove'] ?>" /></span>
				</div>
<?php ($hook = get_hook('ark_edit_rank_end')) ? eval($hook) : null; ?>
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
			<h2><span><?php echo $lang_admin['Existing ranks intro'] ?></span></h2>
		</div>
		<div class="frm-form">
			<div class="frm-info">
				<p><?php echo $lang_admin['No ranks'] ?></p>
			</div>
		</div>
	</div>
<?php

}

?>
</div>
<?php

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
