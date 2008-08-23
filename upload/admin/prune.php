<?php
/**
 * Topic pruning page
 *
 * Allows administrators to delete older topics from the site.
 *
 * @copyright Copyright (C) 2008 FluxBB.org, based on code copyright (C) 2002-2008 PunBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package FluxBB
 */


if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', '../');
require FORUM_ROOT.'include/common.php';
require FORUM_ROOT.'include/common_admin.php';

($hook = get_hook('apr_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

if ($forum_user['g_id'] != FORUM_ADMIN)
	message($lang_common['No permission']);

// Load the admin.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin_common.php';
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin_prune.php';


if (isset($_GET['action']) || isset($_POST['prune']) || isset($_POST['prune_comply']))
{
	if (isset($_POST['prune_comply']))
	{
		$prune_from = $_POST['prune_from'];
		$prune_days = intval($_POST['prune_days']);
		$prune_date = ($prune_days) ? time() - ($prune_days*86400) : -1;

		($hook = get_hook('apr_prune_comply_form_submitted')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		@set_time_limit(0);

		if ($prune_from == 'all')
		{
			$query = array(
				'SELECT'	=> 'f.id',
				'FROM'		=> 'forums AS f'
			);

			($hook = get_hook('apr_prune_comply_qr_get_all_forums')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
			$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
			$num_forums = $forum_db->num_rows($result);

			for ($i = 0; $i < $num_forums; ++$i)
			{
				$fid = $forum_db->result($result, $i);

				prune($fid, $_POST['prune_sticky'], $prune_date);
				sync_forum($fid);
			}
		}
		else
		{
			$prune_from = intval($prune_from);
			prune($prune_from, $_POST['prune_sticky'], $prune_date);
			sync_forum($prune_from);
		}

		delete_orphans();

		($hook = get_hook('apr_prune_pre_redirect')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		redirect(forum_link($forum_url['admin_prune']), $lang_admin_prune['Prune done'].' '.$lang_admin_common['Redirect']);
	}


	$prune_days = intval($_POST['req_prune_days']);
	if ($prune_days < 0)
		message($lang_admin_prune['Days to prune message']);

	$prune_date = time() - ($prune_days * 86400);
	$prune_from = $_POST['prune_from'];

	if ($prune_from != 'all')
	{
		$prune_from = intval($prune_from);

		// Fetch the forum name (just for cosmetic reasons)
		$query = array(
			'SELECT'	=> 'f.forum_name',
			'FROM'		=> 'forums AS f',
			'WHERE'		=> 'f.id='.$prune_from
		);

		($hook = get_hook('apr_prune_comply_qr_get_forum_name')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		$forum = forum_htmlencode($forum_db->result($result));
	}
	else
		$forum = 'all forums';

	// Count the number of topics to prune
	$query = array(
		'SELECT'	=> 'COUNT(t.id)',
		'FROM'		=> 'topics AS t',
		'WHERE'		=> 't.last_post<'.$prune_date.' AND t.moved_to IS NULL'
	);

	if ($prune_from != 'all')
		$query['WHERE'] .= ' AND t.forum_id='.$prune_from;
	if (!isset($_POST['prune_sticky']))
		$query['WHERE'] .= ' AND t.sticky=0';

	($hook = get_hook('apr_prune_comply_qr_get_topic_count')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$num_topics = $forum_db->result($result);

	if (!$num_topics)
		message($lang_admin_prune['No days old message']);


	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
		array($lang_admin_prune['Prune topics'], forum_link($forum_url['admin_prune'])),
		$lang_admin_prune['Confirm prune heading']
	);

	($hook = get_hook('apr_prune_comply_pre_header_load')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	define('FORUM_PAGE_SECTION', 'management');
	define('FORUM_PAGE', 'admin-prune');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

	($hook = get_hook('apr_prune_comply_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php printf($lang_admin_prune['Prune details head'], ($forum == 'all forums') ? $lang_admin_prune['All forums'] : $forum ) ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_prune']) ?>?action=foo">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_prune']).'?action=foo') ?>" />
				<input type="hidden" name="prune_days" value="<?php echo $prune_days ?>" />
				<input type="hidden" name="prune_sticky" value="<?php echo intval($_POST['prune_sticky']) ?>" />
				<input type="hidden" name="prune_from" value="<?php echo $prune_from ?>" />
			</div>
			<div class="ct-box">
				<p class="warn"><span><?php printf($lang_admin_prune['Prune topics info 1'], $num_topics, isset($_POST['prune_sticky']) ? ' ('.$lang_admin_prune['Include sticky'].')' : '') ?></span></p>
				<p class="warn"><span><?php printf($lang_admin_prune['Prune topics info 2'], $prune_days) ?></span></p>
			</div>
<?php ($hook = get_hook('apr_prune_comply_pre_buttons')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="prune_comply" value="<?php echo $lang_admin_prune['Prune topics'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

	($hook = get_hook('apr_prune_comply_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	$tpl_temp = forum_trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}


else
{
	// Setup form
	$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
		$lang_admin_common['Prune topics']
	);

	($hook = get_hook('apr_pre_header_load')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	define('FORUM_PAGE_SECTION', 'management');
	define('FORUM_PAGE', 'admin-prune');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

	($hook = get_hook('apr_main_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_admin_prune['Prune settings head'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<div class="ct-box">
			<p><?php echo $lang_admin_prune['Prune intro'] ?></p>
			<p class="important"><?php echo $lang_admin_prune['Prune caution'] ?></p>
		</div>
		<div id="req-msg" class="frm-warn">
			<p class="important"><?php printf($lang_common['Required warn'], '<em class="req-text">'.$lang_common['Required'].'</em>') ?></p>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_prune']) ?>?action=foo">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_prune']).'?action=foo') ?>" />
				<input type="hidden" name="form_sent" value="1" />
			</div>
<?php ($hook = get_hook('apr_pre_prune_fieldset')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			<fieldset class="frm-group group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><span><?php echo $lang_admin_prune['Prune legend'] ?></span></legend>
<?php ($hook = get_hook('apr_pre_prune_from')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box select">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span class="fld-label"><?php echo $lang_admin_prune['Prune from'] ?></span></label><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="prune_from">
							<option value="all"><?php echo $lang_admin_prune['All forums'] ?></option>
<?php

	$query = array(
		'SELECT'	=> 'c.id AS cid, c.cat_name, f.id AS fid, f.forum_name',
		'FROM'		=> 'categories AS c',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'forums AS f',
				'ON'			=> 'c.id=f.cat_id'
			)
		),
		'WHERE'		=> 'f.redirect_url IS NULL',
		'ORDER BY'	=> 'c.disp_position, c.id, f.disp_position'
	);

	($hook = get_hook('apr_qr_get_forum_list')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$cur_category = 0;
	while ($forum = $forum_db->fetch_assoc($result))
	{
		if ($forum['cid'] != $cur_category)	// Are we still in the same category?
		{
			if ($cur_category)
				echo "\t\t\t\t\t\t\t\t".'</optgroup>'."\n";

			echo "\t\t\t\t\t\t\t\t".'<optgroup label="'.forum_htmlencode($forum['cat_name']).'">'."\n";
			$cur_category = $forum['cid'];
		}

		echo "\t\t\t\t\t\t\t\t\t".'<option value="'.$forum['fid'].'">'.forum_htmlencode($forum['forum_name']).'</option>'."\n";
	}

?>
						</optgroup>
						</select></span>
					</div>
				</div>
<?php ($hook = get_hook('apr_pre_prune_days')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span class="fld-label"><em class="req-text"><?php echo $lang_common['Required'] ?></em> <?php echo $lang_admin_prune['Days old'] ?></span></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_prune_days" size="4" maxlength="4" /></span>
					</div>
				</div>
<?php ($hook = get_hook('apr_pre_prune_sticky')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box checkbox">
						<span class="fld-input"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="prune_sticky" value="1" checked="checked" /></span>
						<label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_admin_prune['Prune sticky'] ?></span> <?php echo $lang_admin_prune['Prune sticky enable'] ?></label>
					</div>
				</div>
<?php ($hook = get_hook('apr_pre_prune_fieldset_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			</fieldset>
<?php ($hook = get_hook('apr_prune_fieldset_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="prune" value="<?php echo $lang_admin_prune['Prune topics'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

	($hook = get_hook('apr_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	$tpl_temp = forum_trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}
