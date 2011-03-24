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
	message($lang_common['No permission']);

// Load the admin_reports.php language file
require PUN_ROOT.'lang/'.$admin_language.'/admin_reports.php';

// Zap a report
if (isset($_POST['zap_id']))
{
	confirm_referrer('admin_reports.php');

	$zap_id = intval(key($_POST['zap_id']));

	$query = new UpdateQuery(array('zapped' => ':now', 'zapped_by' => ':user_id'), 'reports');
	$query->where = 'zapped IS NULL AND :zid';

	$params = array(':now' => time(), ':user_id' => $pun_user['id'], ':zid' => $zap_id);

	$db->query($query, $params);
	unset ($query, $params);

	// Delete old reports (which cannot be viewed anyway)
	$query = new DeleteQuery('reports AS r');
	$query->where = 'r.zapped IS NOT NULL';
	$query->order_by = array('zapped' => 'r.zapped DESC');
	$query->offset = 10;

	$params = array();

	$db->query($query, $params);
	unset ($query, $params);

	$cache->delete('num_reports');

	redirect('admin_reports.php', $lang_admin_reports['Report zapped redirect']);
}


$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_common['Admin'], $lang_admin_common['Reports']);
define('PUN_ACTIVE_PAGE', 'admin');
require PUN_ROOT.'header.php';

generate_admin_menu('reports');

?>
	<div class="blockform">
		<h2><span><?php echo $lang_admin_reports['New reports head'] ?></span></h2>
		<div class="box">
			<form method="post" action="admin_reports.php?action=zap">
<?php

$query = new SelectQuery(array('rid' => 'r.id', 'topic_id' => 'r.topic_id', 'forum_id' => 'r.forum_id', 'reported_by' => 'r.reported_by', 'created' => 'r.created', 'message' => 'r.message', 'pid' => 'p.id AS pid', 'subject' => 't.subject', 'forum_name' => 'f.forum_name', 'reporter' => 'u.username AS reporter'), 'reports AS r');

$query->joins['f'] = new LeftJoin('forums AS f');
$query->joins['f']->on = 'r.forum_id = f.id';

$query->joins['p'] = new LeftJoin('posts AS p');
$query->joins['p']->on = 'r.post_id = p.id';

$query->joins['t'] = new LeftJoin('topics AS t');
$query->joins['t']->on = 'r.topic_id = t.id';

$query->joins['u'] = new LeftJoin('users AS u');
$query->joins['u']->on = 'r.reported_by = u.id';

$query->where = 'r.zapped IS NULL';
$query->order_by = array('created' => 'r.created DESC');

$params = array();

$result = $db->query($query, $params);
unset ($query, $params);

if (!empty($result))
{
	foreach ($result as $cur_report)
	{
		$reporter = ($cur_report['reporter'] != '') ? '<a href="profile.php?id='.$cur_report['reported_by'].'">'.pun_htmlspecialchars($cur_report['reporter']).'</a>' : $lang_admin_reports['Deleted user'];
		$forum = ($cur_report['forum_name'] != '') ? '<span><a href="viewforum.php?id='.$cur_report['forum_id'].'">'.pun_htmlspecialchars($cur_report['forum_name']).'</a></span>' : '<span>'.$lang_admin_reports['Deleted'].'</span>';
		$topic = ($cur_report['subject'] != '') ? '<span>»&#160;<a href="viewtopic.php?id='.$cur_report['topic_id'].'">'.pun_htmlspecialchars($cur_report['subject']).'</a></span>' : '<span>»&#160;'.$lang_admin_reports['Deleted'].'</span>';
		$post = str_replace("\n", '<br />', pun_htmlspecialchars($cur_report['message']));
		$post_id = ($cur_report['pid'] != '') ? '<span>»&#160;<a href="viewtopic.php?pid='.$cur_report['pid'].'#p'.$cur_report['pid'].'">'.sprintf($lang_admin_reports['Post ID'], $cur_report['pid']).'</a></span>' : '<span>»&#160;'.$lang_admin_reports['Deleted'].'</span>';
		$report_location = array($forum, $topic, $post_id);

?>
				<div class="inform">
					<fieldset>
						<legend><?php printf($lang_admin_reports['Report subhead'], format_time($cur_report['created'])) ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php printf($lang_admin_reports['Reported by'], $reporter) ?></th>
									<td class="location"><?php echo implode(' ', $report_location) ?></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_reports['Reason'] ?><div><input type="submit" name="zap_id[<?php echo $cur_report['id'] ?>]" value="<?php echo $lang_admin_reports['Zap'] ?>" /></div></th>
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
						<legend><?php echo $lang_admin_common['None'] ?></legend>
						<div class="infldset">
							<p><?php echo $lang_admin_reports['No new reports'] ?></p>
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
		<h2><span><?php echo $lang_admin_reports['Last 10 head'] ?></span></h2>
		<div class="box">
			<div class="fakeform">
<?php

$query = new SelectQuery(array('rid' => 'r.id', 'topic_id' => 'r.topic_id', 'forum_id' => 'r.forum_id', 'reported_by' => 'r.reported_by', 'message' => 'r.message', 'zapped' => 'r.zapped', 'zapped_by_id' => 'r.zapped_by AS zapped_by_id', 'pid' => 'p.id AS pid', 'subject' => 't.subject', 'forum_name' => 'f.forum_name', 'reporter' => 'u.username AS reporter', 'zapped_by' => 'u2.username AS zapped_by'), 'reports AS r');

$query->joins['p'] = new LeftJoin('posts AS p');
$query->joins['p']->on = 'r.post_id = p.id';

$query->joins['t'] = new LeftJoin('topics AS t');
$query->joins['t']->on = 'r.topic_id = t.id';

$query->joins['f'] = new LeftJoin('forums AS f');
$query->joins['f']->on = 'r.forum_id = f.id';

$query->joins['u'] = new LeftJoin('users AS u');
$query->joins['u']->on = 'r.reported_by = u.id';

$query->joins['u2'] = new LeftJoin('users AS u2');
$query->joins['u2']->on = 'r.zapped_by = u2.id';

$query->where = 'r.zapped IS NOT NULL';
$query->order_by = array('zapped' => 'r.zapped DESC');
$query->limit = 10;

$params = array();

$result = $db->query($query, $params);
unset ($query, $params);

if (!empty($result))
{
	foreach ($result as $cur_report)
	{
		$reporter = ($cur_report['reporter'] != '') ? '<a href="profile.php?id='.$cur_report['reported_by'].'">'.pun_htmlspecialchars($cur_report['reporter']).'</a>' : $lang_admin_reports['Deleted user'];
		$forum = ($cur_report['forum_name'] != '') ? '<span><a href="viewforum.php?id='.$cur_report['forum_id'].'">'.pun_htmlspecialchars($cur_report['forum_name']).'</a></span>' : '<span>'.$lang_admin_reports['Deleted'].'</span>';
		$topic = ($cur_report['subject'] != '') ? '<span>»&#160;<a href="viewtopic.php?id='.$cur_report['topic_id'].'">'.pun_htmlspecialchars($cur_report['subject']).'</a></span>' : '<span>»&#160;'.$lang_admin_reports['Deleted'].'</span>';
		$post = str_replace("\n", '<br />', pun_htmlspecialchars($cur_report['message']));
		$post_id = ($cur_report['pid'] != '') ? '<span>»&#160;<a href="viewtopic.php?pid='.$cur_report['pid'].'#p'.$cur_report['pid'].'">'.sprintf($lang_admin_reports['Post ID'], $cur_report['pid']).'</a></span>' : '<span>»&#160;'.$lang_admin_reports['Deleted'].'</span>';
		$zapped_by = ($cur_report['zapped_by'] != '') ? '<a href="profile.php?id='.$cur_report['zapped_by_id'].'">'.pun_htmlspecialchars($cur_report['zapped_by']).'</a>' : $lang_admin_reports['NA'];
		$zapped_by = ($cur_report['zapped_by'] != '') ? '<strong>'.pun_htmlspecialchars($cur_report['zapped_by']).'</strong>' : $lang_admin_reports['NA'];
		$report_location = array($forum, $topic, $post_id);

?>
				<div class="inform">
					<fieldset>
						<legend><?php printf($lang_admin_reports['Zapped subhead'], format_time($cur_report['zapped']), $zapped_by) ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php printf($lang_admin_reports['Reported by'], $reporter) ?></th>
									<td class="location"><?php echo implode(' ', $report_location) ?></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_reports['Reason'] ?></th>
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
						<legend><?php echo $lang_admin_common['None'] ?></legend>
						<div class="infldset">
							<p><?php echo $lang_admin_reports['No zapped reports'] ?></p>
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
