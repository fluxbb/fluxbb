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

($hook = get_hook('arp_start')) ? eval($hook) : null;

if (!$pun_user['is_admmod'])
	message($lang_common['No permission']);

// Load the admin.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/admin.php';


// Mark reports as read
if (isset($_POST['mark_as_read']))
{
	if (empty($_POST['reports']))
		message($lang_admin['No reports selected']);

	($hook = get_hook('arp_mark_as_read_form_submitted')) ? eval($hook) : null;

	$reports_to_mark = array_map('intval', array_keys($_POST['reports']));

	$query = array(
		'UPDATE'	=> 'reports',
		'SET'		=> 'zapped='.time().', zapped_by='.$pun_user['id'],
		'WHERE'		=> 'id IN('.implode(',', $reports_to_mark).') AND zapped IS NULL'
	);

	($hook = get_hook('arp_qr_mark_reports_as_read')) ? eval($hook) : null;
	$pun_db->query_build($query) or error(__FILE__, __LINE__);

	redirect(pun_link($pun_url['admin_reports']), $lang_admin['Reports marked read'].' '.$lang_admin['Redirect']);
}

$pun_page['fld_count'] = $pun_page['set_count'] = 0;

// Setup breadcrumbs
$pun_page['crumbs'] = array(
	array($pun_config['o_board_title'], pun_link($pun_url['index'])),
	array($lang_admin['Forum administration'], pun_link($pun_url['admin_index'])),
	$lang_admin['Reports']
);

($hook = get_hook('arp_pre_header_load')) ? eval($hook) : null;

define('PUN_PAGE_SECTION', 'management');
define('PUN_PAGE', 'admin-reports');
require PUN_ROOT.'header.php';

?>
<div id="pun-main" class="main sectioned admin">

<?php echo generate_admin_menu(); ?>

	<div class="main-head">
		<h1><span>{ <?php echo end($pun_page['crumbs']) ?> }</span></h1>
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
$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
if ($pun_db->num_rows($result))
{

?>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo pun_link($pun_url['admin_reports']) ?>?action=zap">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(pun_link($pun_url['admin_reports']).'?action=zap') ?>" />
			</div>
<?php

	$pun_page['num_items'] = 0;

	while ($cur_report = $pun_db->fetch_assoc($result))
	{
		$reporter = ($cur_report['reporter'] != '') ? '<a href="'.pun_link($pun_url['user'], $cur_report['reported_by']).'">'.pun_htmlencode($cur_report['reporter']).'</a>' : $lang_admin['Deleted user'];
		$forum = ($cur_report['forum_name'] != '') ? '<a href="'.pun_link($pun_url['forum'], array($cur_report['forum_id'], sef_friendly($cur_report['forum_name']))).'">'.pun_htmlencode($cur_report['forum_name']).'</a>' : $lang_admin['Deleted forum'];
		$topic = ($cur_report['subject'] != '') ? '<a href="'.pun_link($pun_url['topic'], array($cur_report['topic_id'], sef_friendly($cur_report['subject']))).'">'.pun_htmlencode($cur_report['subject']).'</a>' : $lang_admin['Deleted topic'];
		$message = str_replace("\n", '<br />', pun_htmlencode($cur_report['message']));
		$post_id = ($cur_report['pid'] != '') ? '<a href="'.pun_link($pun_url['post'], $cur_report['pid']).'">Post #'.$cur_report['pid'].'</a>' : $lang_admin['Deleted post'];

		($hook = get_hook('arp_new_report_pre_display')) ? eval($hook) : null;

?>
			<div class="rep-item databox">
				<h3 class="legend"><span><?php printf($lang_admin['Reported by'], format_time($cur_report['created']), $reporter) ?></span></h3>
				<div class="radbox checkbox item-select"><label for="fld<?php echo ++$pun_page['fld_count'] ?>"><span class="fld-label"><?php echo $lang_admin['Select report'] ?></span><input type="checkbox" id="fld<?php echo $pun_page['fld_count'] ?>" name="reports[<?php echo $cur_report['id'] ?>]" value="1" /> <?php echo ++$pun_page['num_items'] ?></label></div>
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
$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
if ($pun_db->num_rows($result))
{
	$i = 1;
	$pun_page['num_items'] = 0;
	while ($cur_report = $pun_db->fetch_assoc($result))
	{
		$reporter = ($cur_report['reporter'] != '') ? '<a href="'.pun_link($pun_url['user'], $cur_report['reported_by']).'">'.pun_htmlencode($cur_report['reporter']).'</a>' : $lang_admin['Deleted user'];
		$forum = ($cur_report['forum_name'] != '') ? '<a href="'.pun_link($pun_url['forum'], array($cur_report['forum_id'], sef_friendly($cur_report['forum_name']))).'">'.pun_htmlencode($cur_report['forum_name']).'</a>' : $lang_admin['Deleted forum'];
		$topic = ($cur_report['subject'] != '') ? '<a href="'.pun_link($pun_url['topic'], array($cur_report['topic_id'], sef_friendly($cur_report['subject']))).'">'.pun_htmlencode($cur_report['subject']).'</a>' : $lang_admin['Deleted topic'];
		$message = str_replace("\n", '<br />', pun_htmlencode($cur_report['message']));
		$post_id = ($cur_report['pid'] != '') ? '<a href="'.pun_link($pun_url['post'], $cur_report['pid']).'">Post #'.$cur_report['pid'].'</a>' : $lang_admin['Deleted post'];
		$zapped_by = ($cur_report['zapped_by'] != '') ? '<a href="'.pun_link($pun_url['user'], $cur_report['zapped_by_id']).'">'.pun_htmlencode($cur_report['zapped_by']).'</a>' : $lang_admin['Deleted user'];

		($hook = get_hook('arp_report_pre_display')) ? eval($hook) : null;

?>
		<div class="rep-item databox">
			<h3 class="legend"><span><strong><?php echo ++$pun_page['num_items'] ?></strong> <?php printf($lang_admin['Reported by'], format_time($cur_report['created']), $reporter) ?></span></h3>
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

require PUN_ROOT.'footer.php';
