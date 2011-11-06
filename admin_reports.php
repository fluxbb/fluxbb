<?php

/**
 * Copyright (C) 2008-2011 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Tell header.php to use the admin template
define('PUN_ADMIN_CONSOLE', 1);

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/common_admin.php';


if (!$pun_user['is_admmod'])
	message($lang->t('No permission'));

// Load the admin_reports.php language file
$lang->load('admin_reports');

// Zap a report
if (isset($_POST['zap_id']))
{
	confirm_referrer('admin_reports.php');

	$zap_id = intval(key($_POST['zap_id']));

	$query = $db->update(array('zapped' => ':now', 'zapped_by' => ':user_id'), 'reports');
	$query->where = 'zapped IS NULL AND :zid';

	$params = array(':now' => time(), ':user_id' => $pun_user['id'], ':zid' => $zap_id);

	$query->run($params);
	unset ($query, $params);

	// Delete old reports (which cannot be viewed anyway)
	$query = $db->delete('reports AS r');
	$query->where = 'r.zapped IS NOT NULL';
	$query->order = array('zapped' => 'r.zapped DESC');
	$query->offset = 10;

	$params = array();

	$query->run($params);
	unset ($query, $params);

	$cache->delete('num_reports');

	redirect('admin_reports.php', $lang->t('Report zapped redirect'));
}


$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Admin'), $lang->t('Reports'));
define('PUN_ACTIVE_PAGE', 'admin');
require PUN_ROOT.'header.php';

generate_admin_menu('reports');

?>
	<div class="blockform">
		<h2><span><?php echo $lang->t('New reports head') ?></span></h2>
		<div class="box">
			<form method="post" action="admin_reports.php?action=zap">
<?php

$query = $db->select(array('rid' => 'r.id', 'topic_id' => 'r.topic_id', 'forum_id' => 'r.forum_id', 'reported_by' => 'r.reported_by', 'created' => 'r.created', 'message' => 'r.message', 'pid' => 'p.id AS pid', 'subject' => 't.subject', 'forum_name' => 'f.forum_name', 'reporter' => 'u.username AS reporter'), 'reports AS r');

$query->leftJoin('f', 'forums AS f', 'r.forum_id = f.id');

$query->leftJoin('p', 'posts AS p', 'r.post_id = p.id');

$query->leftJoin('t', 'topics AS t', 'r.topic_id = t.id');

$query->leftJoin('u', 'users AS u', 'r.reported_by = u.id');

$query->where = 'r.zapped IS NULL';
$query->order = array('created' => 'r.created DESC');

$params = array();

$result = $query->run($params);
unset ($query, $params);

if (!empty($result))
{
	foreach ($result as $cur_report)
	{
		$reporter = ($cur_report['reporter'] != '') ? '<a href="profile.php?id='.$cur_report['reported_by'].'">'.pun_htmlspecialchars($cur_report['reporter']).'</a>' : $lang->t('Deleted user');
		$forum = ($cur_report['forum_name'] != '') ? '<span><a href="viewforum.php?id='.$cur_report['forum_id'].'">'.pun_htmlspecialchars($cur_report['forum_name']).'</a></span>' : '<span>'.$lang->t('Deleted').'</span>';
		$topic = ($cur_report['subject'] != '') ? '<span>»&#160;<a href="viewtopic.php?id='.$cur_report['topic_id'].'">'.pun_htmlspecialchars($cur_report['subject']).'</a></span>' : '<span>»&#160;'.$lang->t('Deleted').'</span>';
		$post = str_replace("\n", '<br />', pun_htmlspecialchars($cur_report['message']));
		$post_id = ($cur_report['pid'] != '') ? '<span>»&#160;<a href="viewtopic.php?pid='.$cur_report['pid'].'#p'.$cur_report['pid'].'">'.$lang->t('Post ID', $cur_report['pid']).'</a></span>' : '<span>»&#160;'.$lang->t('Deleted').'</span>';
		$report_location = array($forum, $topic, $post_id);

?>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Report subhead', format_time($cur_report['created'])) ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang->t('Reported by', $reporter) ?></th>
									<td class="location"><?php echo implode(' ', $report_location) ?></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Reason') ?><div><input type="submit" name="zap_id[<?php echo $cur_report['id'] ?>]" value="<?php echo $lang->t('Zap') ?>" /></div></th>
									<td><?php echo $post ?></td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
<?php

	}
}
else
{

?>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('None') ?></legend>
						<div class="infldset">
							<p><?php echo $lang->t('No new reports') ?></p>
						</div>
					</fieldset>
				</div>
<?php

}

unset ($result);

?>
			</form>
		</div>
	</div>

	<div class="blockform block2">
		<h2><span><?php echo $lang->t('Last 10 head') ?></span></h2>
		<div class="box">
			<div class="fakeform">
<?php

$query = $db->select(array('rid' => 'r.id', 'topic_id' => 'r.topic_id', 'forum_id' => 'r.forum_id', 'reported_by' => 'r.reported_by', 'message' => 'r.message', 'zapped' => 'r.zapped', 'zapped_by_id' => 'r.zapped_by AS zapped_by_id', 'pid' => 'p.id AS pid', 'subject' => 't.subject', 'forum_name' => 'f.forum_name', 'reporter' => 'u.username AS reporter', 'zapped_by' => 'u2.username AS zapped_by'), 'reports AS r');

$query->leftJoin('p', 'posts AS p', 'r.post_id = p.id');

$query->leftJoin('t', 'topics AS t', 'r.topic_id = t.id');

$query->leftJoin('f', 'forums AS f', 'r.forum_id = f.id');

$query->leftJoin('u', 'users AS u', 'r.reported_by = u.id');

$query->leftJoin('u2', 'users AS u2', 'r.zapped_by = u2.id');

$query->where = 'r.zapped IS NOT NULL';
$query->order = array('zapped' => 'r.zapped DESC');
$query->limit = 10;

$params = array();

$result = $query->run($params);
unset ($query, $params);

if (!empty($result))
{
	foreach ($result as $cur_report)
	{
		$reporter = ($cur_report['reporter'] != '') ? '<a href="profile.php?id='.$cur_report['reported_by'].'">'.pun_htmlspecialchars($cur_report['reporter']).'</a>' : $lang->t('Deleted user');
		$forum = ($cur_report['forum_name'] != '') ? '<span><a href="viewforum.php?id='.$cur_report['forum_id'].'">'.pun_htmlspecialchars($cur_report['forum_name']).'</a></span>' : '<span>'.$lang->t('Deleted').'</span>';
		$topic = ($cur_report['subject'] != '') ? '<span>»&#160;<a href="viewtopic.php?id='.$cur_report['topic_id'].'">'.pun_htmlspecialchars($cur_report['subject']).'</a></span>' : '<span>»&#160;'.$lang->t('Deleted').'</span>';
		$post = str_replace("\n", '<br />', pun_htmlspecialchars($cur_report['message']));
		$post_id = ($cur_report['pid'] != '') ? '<span>»&#160;<a href="viewtopic.php?pid='.$cur_report['pid'].'#p'.$cur_report['pid'].'">'.$lang->t('Post ID', $cur_report['pid']).'</a></span>' : '<span>»&#160;'.$lang->t('Deleted').'</span>';
		$zapped_by = ($cur_report['zapped_by'] != '') ? '<a href="profile.php?id='.$cur_report['zapped_by_id'].'">'.pun_htmlspecialchars($cur_report['zapped_by']).'</a>' : $lang->t('NA');
		$zapped_by = ($cur_report['zapped_by'] != '') ? '<strong>'.pun_htmlspecialchars($cur_report['zapped_by']).'</strong>' : $lang->t('NA');
		$report_location = array($forum, $topic, $post_id);

?>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('Zapped subhead', format_time($cur_report['zapped']), $zapped_by) ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang->t('Reported by', $reporter) ?></th>
									<td class="location"><?php echo implode(' ', $report_location) ?></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang->t('Reason') ?></th>
									<td><?php echo $post ?></td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
<?php

	}
}
else
{

?>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang->t('None') ?></legend>
						<div class="infldset">
							<p><?php echo $lang->t('No zapped reports') ?></p>
						</div>
					</fieldset>
				</div>
<?php

}

unset ($result);

?>
			</div>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

require PUN_ROOT.'footer.php';
