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

($hook = get_hook('apr_start')) ? eval($hook) : null;

if ($pun_user['g_id'] != PUN_ADMIN)
	message($lang_common['No permission']);

// Load the admin.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/admin.php';


if (isset($_GET['action']) || isset($_POST['prune']) || isset($_POST['prune_comply']))
{
	if (isset($_POST['prune_comply']))
	{
		$prune_from = $_POST['prune_from'];
		$prune_days = intval($_POST['prune_days']);
		$prune_date = ($prune_days) ? time() - ($prune_days*86400) : -1;

		($hook = get_hook('apr_prune_comply_form_submitted')) ? eval($hook) : null;

		@set_time_limit(0);

		if ($prune_from == 'all')
		{
			$query = array(
				'SELECT'	=> 'f.id',
				'FROM'		=> 'forums AS f'
			);

			($hook = get_hook('apr_qr_get_all_forums')) ? eval($hook) : null;
			$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
			$num_forums = $pun_db->num_rows($result);

			for ($i = 0; $i < $num_forums; ++$i)
			{
				$fid = $pun_db->result($result, $i);

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

		redirect(pun_link($pun_url['admin_prune']), $lang_admin['Prune done'].' '.$lang_admin['Redirect']);
	}


	$prune_days = intval($_POST['req_prune_days']);
	if ($prune_days < 0)
		message($lang_admin['Days to prune message']);

	$prune_date = time() - ($prune_days*86400);
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

		($hook = get_hook('apr_qr_get_forum_name')) ? eval($hook) : null;
		$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
		$forum = pun_htmlencode($pun_db->result($result));
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

	($hook = get_hook('apr_qr_get_topic_count')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	$num_topics = $pun_db->result($result);

	if (!$num_topics)
		message($lang_admin['No days old message']);


	// Setup breadcrumbs
	$pun_page['crumbs'] = array(
		array($pun_config['o_board_title'], pun_link($pun_url['index'])),
		array($lang_admin['Forum administration'], pun_link($pun_url['admin_index'])),
		array($lang_admin['Prune topics'], pun_link($pun_url['admin_prune'])),
		$lang_admin['Confirm prune heading']
	);

	($hook = get_hook('apr_prune_comply_pre_header_load')) ? eval($hook) : null;

	define('PUN_PAGE_SECTION', 'management');
	define('PUN_PAGE', 'admin-prune');
	require PUN_ROOT.'header.php';

?>
<div id="pun-main" class="main sectioned admin">


<?php echo generate_admin_menu(); ?>

	<div class="main-head">
		<h1><span>{ <?php echo end($pun_page['crumbs']) ?> }</span></h1>
	</div>

	<div class="main-content frm">
		<div class="frm-head">
			<h2><span><?php printf($lang_admin['Prune details head'], ($forum == 'all forums') ? $lang_admin['All forums'] : $forum ) ?></span></h2>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo pun_link($pun_url['admin_prune']) ?>?action=foo">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(pun_link($pun_url['admin_prune']).'?action=foo') ?>" />
				<input type="hidden" name="prune_days" value="<?php echo $prune_days ?>" />
				<input type="hidden" name="prune_sticky" value="<?php echo intval($_POST['prune_sticky']) ?>" />
				<input type="hidden" name="prune_from" value="<?php echo $prune_from ?>" />
			</div>
			<div class="frm-info">
				<p class="warn"><span><?php printf($lang_admin['Prune topics info 1'], $num_topics, isset($_POST['prune_sticky']) ? ' ('.$lang_admin['Include sticky'].')' : '') ?></span></p>
				<p class="warn"><span><?php printf($lang_admin['Prune topics info 2'], $prune_days) ?></span></p>
			</div>
<?php ($hook = get_hook('apr_prune_comply_pre_buttons')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="prune_comply" value="<?php echo $lang_admin['Prune topics'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

	require PUN_ROOT.'footer.php';
}


else
{
	// Setup form
	$pun_page['set_count'] = $pun_page['fld_count'] = 0;

	// Setup breadcrumbs
	$pun_page['crumbs'] = array(
		array($pun_config['o_board_title'], pun_link($pun_url['index'])),
		array($lang_admin['Forum administration'], pun_link($pun_url['admin_index'])),
		$lang_admin['Prune topics']
	);

	($hook = get_hook('apr_pre_header_load')) ? eval($hook) : null;

	define('PUN_PAGE_SECTION', 'management');
	define('PUN_PAGE', 'admin-prune');
	require PUN_ROOT.'header.php';

?>
<div id="pun-main" class="main sectioned admin">

<?php echo generate_admin_menu(); ?>

	<div class="main-head">
		<h1><span>{ <?php echo end($pun_page['crumbs']) ?> }</span></h1>
	</div>

	<div class="main-content frm">
		<div class="frm-head">
			<h2 class="prefix"><span><?php echo $lang_admin['Prune settings head'] ?></span></h2>
		</div>
		<div class="frm-info">
			<p><?php echo $lang_admin['Prune intro'] ?></p>
			<p class="important"><?php echo $lang_admin['Prune caution'] ?></p>
		</div>
		<div id="req-msg" class="frm-warn">
			<p class="important"><?php printf($lang_common['Required warn'], '<em class="req-text">'.$lang_common['Required'].'</em>') ?></p>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo pun_link($pun_url['admin_prune']) ?>?action=foo">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(pun_link($pun_url['admin_prune']).'?action=foo') ?>" />
				<input type="hidden" name="form_sent" value="1" />
			</div>
<?php ($hook = get_hook('apr_pre_prune_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set<?php echo ++$pun_page['set_count'] ?>">
				<legend class="frm-legend"><span><?php echo $lang_admin['Prune legend'] ?></span></legend>
<?php ($hook = get_hook('apr_pre_prune_from')) ? eval($hook) : null; ?>
				<div class="frm-fld select">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Prune from'] ?></span><br />
						<span class="fld-input"><select id="fld<?php echo $pun_page['fld_count'] ?>" name="prune_from">
							<option value="all"><?php echo $lang_admin['All forums'] ?></option>
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

	($hook = get_hook('apr_qr_get_forum_list')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);

	$cur_category = 0;
	while ($forum = $pun_db->fetch_assoc($result))
	{
		if ($forum['cid'] != $cur_category)	// Are we still in the same category?
		{
			if ($cur_category)
				echo "\t\t\t\t\t\t\t\t".'</optgroup>'."\n";

			echo "\t\t\t\t\t\t\t\t".'<optgroup label="'.pun_htmlencode($forum['cat_name']).'">'."\n";
			$cur_category = $forum['cid'];
		}

		echo "\t\t\t\t\t\t\t\t\t".'<option value="'.$forum['fid'].'">'.pun_htmlencode($forum['forum_name']).'</option>'."\n";
	}

?>
						</optgroup>
						</select></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Days old'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $pun_page['fld_count'] ?>" name="req_prune_days" size="4" maxlength="4" /></span>
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
					</label>
				</div>
				<div class="radbox checkbox">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>"><span class="fld-label"><?php echo $lang_admin['Prune sticky'] ?></span><br /><input type="checkbox" id="fld<?php echo $pun_page['fld_count'] ?>" name="prune_sticky" value="1" checked="checked" /> <?php echo $lang_admin['Prune sticky enable'] ?></label>
				</div>
<?php ($hook = get_hook('apr_prune_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php ($hook = get_hook('apr_pre_buttons')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="prune" value="<?php echo $lang_admin['Prune topics'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

	require PUN_ROOT.'footer.php';
}
