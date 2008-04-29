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

($hook = get_hook('arp_start')) ? eval($hook) : null;

if (!$forum_user['is_admmod'])
	message($lang_common['No permission']);

// Load the admin.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin.php';


// Mark reports as read
if (isset($_POST['mark_as_read']))
{
	if (empty($_POST['reports']))
		message($lang_admin['No reports selected']);

	($hook = get_hook('arp_mark_as_read_form_submitted')) ? eval($hook) : null;

	$reports_to_mark = array_map('intval', array_keys($_POST['reports']));

	$query = array(
		'UPDATE'	=> 'reports',
		'SET'		=> 'zapped='.time().', zapped_by='.$forum_user['id'],
		'WHERE'		=> 'id IN('.implode(',', $reports_to_mark).') AND zapped IS NULL'
	);

	($hook = get_hook('arp_qr_mark_reports_as_read')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	redirect(forum_link($forum_url['admin_reports']), $lang_admin['Reports marked read'].' '.$lang_admin['Redirect']);
}

$forum_page['fld_count'] = $forum_page['set_count'] = 0;

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($lang_admin['Forum administration'], forum_link($forum_url['admin_index'])),
	$lang_admin['Reports']
);

($hook = get_hook('arp_pre_header_load')) ? eval($hook) : null;

define('FORUM_PAGE_SECTION', 'management');
define('FORUM_PAGE', 'admin-reports');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

?>
<div id="brd-main" class="main sectioned admin">

<?php echo generate_admin_menu(); ?>

	<div class="main-head">
		<h1><span>{ <?php echo end($forum_page['crumbs']) ?> }</span></h1>
	</div>

	<div class="main-content frm">
		<div class="frm-head">
			<h2><span><?php echo $lang_admin['New reports heading'] ?></span></h2>
		</div>
<?php

// Fetch any unread reports
$query = array(
	'SELECT'	=> 'r.id, r.topic_id, r.forum_id, r.reported_by, r.created, r.message, p.id AS pid, t.subject, f.forum_name, u.username AS reporter',
	'FROM'		=> 'reports AS r',
	'JOINS'		=> array(
		array(
			'LEFT JOIN'		=> 'posts AS p',
			'ON'			=> 'r.post_id=p.id'
		),
		array(
			'LEFT JOIN'		=> 'topics AS t',
			'ON'			=> 'r.topic_id=t.id'
		),
		array(
			'LEFT JOIN'		=> 'forums AS f',
			'ON'			=> 'r.forum_id=f.id'
		),
		array(
			'LEFT JOIN'		=> 'users AS u',
			'ON'			=> 'r.reported_by=u.id'
		)
	),
	'WHERE'		=> 'r.zapped IS NULL',
	'ORDER BY'	=> 'r.created DESC'
);

($hook = get_hook('arp_qr_get_new_reports')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
if ($forum_db->num_rows($result))
{

?>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_reports']) ?>?action=zap">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_reports']).'?action=zap') ?>" />
			</div>
<?php

	$forum_page['num_items'] = 0;

	while ($cur_report = $forum_db->fetch_assoc($result))
	{
		$reporter = ($cur_report['reporter'] != '') ? '<a href="'.forum_link($forum_url['user'], $cur_report['reported_by']).'">'.forum_htmlencode($cur_report['reporter']).'</a>' : $lang_admin['Deleted user'];
		$forum = ($cur_report['forum_name'] != '') ? '<a href="'.forum_link($forum_url['forum'], array($cur_report['forum_id'], sef_friendly($cur_report['forum_name']))).'">'.forum_htmlencode($cur_report['forum_name']).'</a>' : $lang_admin['Deleted forum'];
		$topic = ($cur_report['subject'] != '') ? '<a href="'.forum_link($forum_url['topic'], array($cur_report['topic_id'], sef_friendly($cur_report['subject']))).'">'.forum_htmlencode($cur_report['subject']).'</a>' : $lang_admin['Deleted topic'];
		$message = str_replace("\n", '<br />', forum_htmlencode($cur_report['message']));
		$post_id = ($cur_report['pid'] != '') ? '<a href="'.forum_link($forum_url['post'], $cur_report['pid']).'">Post #'.$cur_report['pid'].'</a>' : $lang_admin['Deleted post'];

		($hook = get_hook('arp_new_report_pre_display')) ? eval($hook) : null;

?>
			<div class="rep-item databox">
				<h3 class="legend"><span><?php printf($lang_admin['Reported by'], format_time($cur_report['created']), $reporter) ?></span></h3>
				<div class="radbox checkbox item-select"><label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span class="fld-label"><?php echo $lang_admin['Select report'] ?></span><input type="checkbox" id="fld<?php echo $forum_page['fld_count'] ?>" name="reports[<?php echo $cur_report['id'] ?>]" value="1" /> <?php echo ++$forum_page['num_items'] ?></label></div>
				<p><?php echo $forum ?>&#160;»&#160;<?php echo $topic ?>&#160;»&#160;<?php echo $post_id ?></p>
				<p><?php echo $message ?></p>
<?php ($hook = get_hook('arp_new_report_new_block')) ? eval($hook) : null; ?>
			</div>
<?php

	}

?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="mark_as_read" value="<?php echo $lang_admin['Mark read'] ?>" /></span>
			</div>
		</form>
<?php

}
else
{

?>
		<div class="frm-info">
			<p><?php echo $lang_admin['No new reports'] ?></p>
		</div>
<?php

}

?>
	</div>

	<div class="main-content frm">
		<div class="frm-head">
			<h2><span><?php echo $lang_admin['Read reports heading'] ?></span></h2>
		</div>
<?php

// Fetch the last 10 reports marked as read
$query = array(
	'SELECT'	=> 'r.id, r.topic_id, r.forum_id, r.reported_by, r.created, r.message, r.zapped, r.zapped_by AS zapped_by_id, p.id AS pid, t.subject, f.forum_name, u.username AS reporter, u2.username AS zapped_by',
	'FROM'		=> 'reports AS r',
	'JOINS'		=> array(
		array(
			'LEFT JOIN'		=> 'posts AS p',
			'ON'			=> 'r.post_id=p.id'
		),
		array(
			'LEFT JOIN'		=> 'topics AS t',
			'ON'			=> 'r.topic_id=t.id'
		),
		array(
			'LEFT JOIN'		=> 'forums AS f',
			'ON'			=> 'r.forum_id=f.id'
		),
		array(
			'LEFT JOIN'		=> 'users AS u',
			'ON'			=> 'r.reported_by=u.id'
		),
		array(
			'LEFT JOIN'		=> 'users AS u2',
			'ON'			=> 'r.zapped_by=u2.id'
		)
	),
	'WHERE'		=> 'r.zapped IS NOT NULL',
	'ORDER BY'	=> 'r.zapped DESC',
	'LIMIT'		=> '10'
);

($hook = get_hook('arp_qr_get_last_zapped_reports')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
if ($forum_db->num_rows($result))
{
	$i = 1;
	$forum_page['num_items'] = 0;
	while ($cur_report = $forum_db->fetch_assoc($result))
	{
		$reporter = ($cur_report['reporter'] != '') ? '<a href="'.forum_link($forum_url['user'], $cur_report['reported_by']).'">'.forum_htmlencode($cur_report['reporter']).'</a>' : $lang_admin['Deleted user'];
		$forum = ($cur_report['forum_name'] != '') ? '<a href="'.forum_link($forum_url['forum'], array($cur_report['forum_id'], sef_friendly($cur_report['forum_name']))).'">'.forum_htmlencode($cur_report['forum_name']).'</a>' : $lang_admin['Deleted forum'];
		$topic = ($cur_report['subject'] != '') ? '<a href="'.forum_link($forum_url['topic'], array($cur_report['topic_id'], sef_friendly($cur_report['subject']))).'">'.forum_htmlencode($cur_report['subject']).'</a>' : $lang_admin['Deleted topic'];
		$message = str_replace("\n", '<br />', forum_htmlencode($cur_report['message']));
		$post_id = ($cur_report['pid'] != '') ? '<a href="'.forum_link($forum_url['post'], $cur_report['pid']).'">Post #'.$cur_report['pid'].'</a>' : $lang_admin['Deleted post'];
		$zapped_by = ($cur_report['zapped_by'] != '') ? '<a href="'.forum_link($forum_url['user'], $cur_report['zapped_by_id']).'">'.forum_htmlencode($cur_report['zapped_by']).'</a>' : $lang_admin['Deleted user'];

		($hook = get_hook('arp_report_pre_display')) ? eval($hook) : null;

?>
		<div class="rep-item databox">
			<h3 class="legend"><span><strong><?php echo ++$forum_page['num_items'] ?></strong> <?php printf($lang_admin['Reported by'], format_time($cur_report['created']), $reporter) ?></span></h3>
			<p><?php echo $forum ?>&#160;»&#160;<?php echo $topic ?>&#160;»&#160;<?php echo $post_id ?></p>
			<p><?php echo $message ?></p>
			<p><?php printf($lang_admin['Marked read by'], format_time($cur_report['zapped']), $zapped_by) ?></p>
<?php ($hook = get_hook('arp_report_new_block')) ? eval($hook) : null; ?>
		</div>
<?php

	}
}
else
{

?>
		<div class="frm-info">
			<p><?php echo $lang_admin['No read reports'] ?></p>
		</div>
<?php

}

?>
	</div>

</div>
<?php

$tpl_temp = trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
