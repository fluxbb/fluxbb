<?php

/**
 * Copyright (C) 2008-2011 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Tell header.php to use the admin template
define('PUN_ADMIN_CONSOLE', 1);
// Tell common.php that we don't want output buffering
define('PUN_DISABLE_BUFFERING', 1);

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/common_admin.php';


if ($pun_user['g_id'] != PUN_ADMIN)
	message($lang_common['No permission']);

// Load the admin_maintenance.php language file
require PUN_ROOT.'lang/'.$admin_language.'/admin_maintenance.php';

$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';

if ($action == 'rebuild')
{
	$per_page = isset($_GET['i_per_page']) ? intval($_GET['i_per_page']) : 0;
	$start_at = isset($_GET['i_start_at']) ? intval($_GET['i_start_at']) : 0;

	// Check per page is > 0
	if ($per_page < 1)
		message($lang_admin_maintenance['Posts must be integer message']);

	@set_time_limit(0);

	// If this is the first cycle of posts we empty the search index before we proceed
	if (isset($_GET['i_empty_index']))
	{
		// This is the only potentially "dangerous" thing we can do here, so we check the referer
		confirm_referrer('admin_maintenance.php');

		$query = new TruncateQuery('search_matches');
		$params = array();

		$db->query($query, $params);
		unset ($query, $params);

		$query = new TruncateQuery('search_words');
		$params = array();

		$db->query($query, $params);
		unset ($query, $params);
	}

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_maintenance['Rebuilding search index']);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo generate_page_title($page_title) ?></title>
<style type="text/css">
body {
	font: 12px Verdana, Arial, Helvetica, sans-serif;
	color: #333333;
	background-color: #FFFFFF
}

h1 {
	font-size: 16px;
	font-weight: normal;
}
</style>
</head>
<body>

<h1><?php echo $lang_admin_maintenance['Rebuilding index info'] ?></h1>
<hr />

<?php

	$query_str = '';

	require PUN_ROOT.'include/search_idx.php';

	// Fetch posts to process this cycle
	$query = new SelectQuery(array('id' => 'p.id', 'message' => 'p.message', 'subject' => 't.subject', 'first_post_id' => 't.first_post_id'), 'posts AS p');

	$query->joins['t'] = new InnerJoin('topics AS t');
	$query->joins['t']->on = 't.id = p.topic_id';

	$query->where = 'p.id >= :start_at';
	$query->order = array('pid' => 'p.id ASC');
	$query->limit = $per_page;

	$params = array(':start_at' => $start_at);

	$result = $db->query($query, $params);

	$end_at = 0;
	foreach ($result as $cur_item)
	{
		echo '<p><span>'.sprintf($lang_admin_maintenance['Processing post'], $cur_item['id']).'</span></p>'."\n";

		if ($cur_item['id'] == $cur_item['first_post_id'])
			update_search_index('post', $cur_item['id'], $cur_item['message'], $cur_item['subject']);
		else
			update_search_index('post', $cur_item['id'], $cur_item['message']);

		$end_at = $cur_item['id'];
	}

	unset ($result, $query, $params);

	// Check if there is more work to do
	if ($end_at > 0)
	{
		$query = new SelectQuery(array('id' => 'p.id'), 'posts AS p');
		$query->where = 'p.id > :end_at';
		$query->order = array('pid' => 'p.id ASC');
		$query->limit = 1;

		$params = array(':end_at' => $end_at);

		$result = $db->query($query, $params);
		if (!empty($result))
			$query_str = '?action=rebuild&i_per_page='.$per_page.'&i_start_at='.$result[0]['id'];
	}

	$db->commit_transaction();
	unset ($db);

	exit('<script type="text/javascript">window.location="admin_maintenance.php'.$query_str.'"</script><hr /><p>'.sprintf($lang_admin_maintenance['Javascript redirect failed'], '<a href="admin_maintenance.php'.$query_str.'">'.$lang_admin_maintenance['Click here'].'</a>').'</p>');
}

if ($action == 'prune')
{
	$prune_from = trim($_POST['prune_from']);
	$prune_sticky = intval($_POST['prune_sticky']);

	if (isset($_POST['prune_comply']))
	{
		confirm_referrer('admin_maintenance.php');

		$prune_days = intval($_POST['prune_days']);
		$prune_date = ($prune_days) ? time() - ($prune_days * 86400) : -1;

		@set_time_limit(0);

		if ($prune_from == 'all')
		{
			$query = new SelectQuery(array('id' => 'f.id'), 'forums AS f');
			$params = array();

			$result = $db->query($query, $params);
			foreach ($result as $cur_forum)
			{
				prune($cur_forum['id'], $prune_sticky, $prune_date);
				update_forum($cur_forum['id']);
			}

			unset ($result, $query, $params);
		}
		else
		{
			$prune_from = intval($prune_from);
			prune($prune_from, $prune_sticky, $prune_date);
			update_forum($prune_from);
		}

		// Locate any "orphaned redirect topics" and delete them
		$query = new SelectQuery(array('id' => 't1.id'), 'topics AS t1');

		$query->joins['t2'] = new LeftJoin('topics AS t2');
		$query->joins['t2']->on = 't1.moved_to = t2.id';

		$query->where = 't2.id IS NULL AND t1.moved_to IS NOT NULL';

		$params = array();

		$result = $db->query($query, $params);
		unset ($query, $params);

		if (!empty($result))
		{
			$orphans = array();
			foreach ($result as $cur_orphan)
				$orphans[] = $cur_orphan['id'];

			$query = new DeleteQuery('topics');
			$query->where = 'id IN :tids';

			$params = array(':tids' => $orphans);

			$db->query($query, $params);
			unset ($query, $params);
		}

		unset ($result);

		redirect('admin_maintenance.php', $lang_admin_maintenance['Posts pruned redirect']);
	}

	$prune_days = trim($_POST['req_prune_days']);
	if ($prune_days == '' || preg_match('/[^0-9]/', $prune_days))
		message($lang_admin_maintenance['Days must be integer message']);

	$prune_date = time() - ($prune_days * 86400);

	// Concatenate together the query for counting number of topics to prune
	$query = new SelectQuery(array('num_topics' => 'COUNT(t.id) AS num_topics'), 'topics AS t');
	$query->where = 't.last_post < :prune_date AND t.moved_to IS NULL';

	$params = array(':prune_date' => $prune_date);

	if ($prune_sticky == '0')
		$query->where .= ' AND sticky = 0';

	if ($prune_from != 'all')
	{
		$prune_from = intval($prune_from);

		$query->where .= ' AND forum_id = :prune_from';
		$params[':prune_from'] = $prune_from;

		// Fetch the forum name (just for cosmetic reasons)
		$name_query = new SelectQuery(array('forum_name' => 'f.forum_name'), 'forums AS f');
		$name_query->where = 'f.id = :forum_id';

		$name_params = array(':forum_id' => $prune_from);

		$result = $db->query($name_query, $name_params);
		$forum = '"'.pun_htmlspecialchars($result[0]['forum_name']).'"';
		unset ($result, $name_query, $name_params);
	}
	else
		$forum = $lang_admin_maintenance['All forums'];

	$result = $db->query($query, $params);
	$num_topics = $result[0]['num_topics'];
	unset ($result, $query, $params);

	if (!$num_topics)
		message(sprintf($lang_admin_maintenance['No old topics message'], $prune_days));


	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_common['Admin'], $lang_admin_common['Prune']);
	define('PUN_ACTIVE_PAGE', 'admin');
	require PUN_ROOT.'header.php';

	generate_admin_menu('maintenance');

?>
	<div class="blockform">
		<h2><span><?php echo $lang_admin_maintenance['Prune head'] ?></span></h2>
		<div class="box">
			<form method="post" action="admin_maintenance.php">
				<div class="inform">
					<input type="hidden" name="action" value="prune" />
					<input type="hidden" name="prune_days" value="<?php echo $prune_days ?>" />
					<input type="hidden" name="prune_sticky" value="<?php echo $prune_sticky ?>" />
					<input type="hidden" name="prune_from" value="<?php echo $prune_from ?>" />
					<fieldset>
						<legend><?php echo $lang_admin_maintenance['Confirm prune subhead'] ?></legend>
						<div class="infldset">
							<p><?php printf($lang_admin_maintenance['Confirm prune info'], $prune_days, $forum, forum_number_format($num_topics)) ?></p>
							<p class="warntext"><?php echo $lang_admin_maintenance['Confirm prune warn'] ?></p>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="prune_comply" value="<?php echo $lang_admin_common['Prune'] ?>" /><a href="javascript:history.go(-1)"><?php echo $lang_admin_common['Go back'] ?></a></p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

	require PUN_ROOT.'footer.php';
	exit;
}


// Get the first post ID from the db
$query = new SelectQuery(array('id' => 'p.id'), 'posts AS p');
$query->order = array('id' => 'p.id ASC');
$query->limit = 1;

$params = array();

$result = $db->query($query, $params);
if (!empty($result))
	$first_id = $result[0]['id'];

unset ($result, $query, $params);

$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_common['Admin'], $lang_admin_common['Maintenance']);
define('PUN_ACTIVE_PAGE', 'admin');
require PUN_ROOT.'header.php';

generate_admin_menu('maintenance');

?>
	<div class="blockform">
		<h2><span><?php echo $lang_admin_maintenance['Maintenance head'] ?></span></h2>
		<div class="box">
			<form method="get" action="admin_maintenance.php">
				<div class="inform">
					<input type="hidden" name="action" value="rebuild" />
					<fieldset>
						<legend><?php echo $lang_admin_maintenance['Rebuild index subhead'] ?></legend>
						<div class="infldset">
							<p><?php printf($lang_admin_maintenance['Rebuild index info'], '<a href="admin_options.php#maintenance">'.$lang_admin_common['Maintenance mode'].'</a>') ?></p>
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang_admin_maintenance['Posts per cycle label'] ?></th>
									<td>
										<input type="text" name="i_per_page" size="7" maxlength="7" value="300" tabindex="1" />
										<span><?php echo $lang_admin_maintenance['Posts per cycle help'] ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_maintenance['Starting post label'] ?></th>
									<td>
										<input type="text" name="i_start_at" size="7" maxlength="7" value="<?php echo (isset($first_id)) ? $first_id : 0 ?>" tabindex="2" />
										<span><?php echo $lang_admin_maintenance['Starting post help'] ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_maintenance['Empty index label'] ?></th>
									<td class="inputadmin">
										<span><input type="checkbox" name="i_empty_index" value="1" tabindex="3" checked="checked" />&#160;&#160;<?php echo $lang_admin_maintenance['Empty index help'] ?></span>
									</td>
								</tr>
							</table>
							<p class="topspace"><?php echo $lang_admin_maintenance['Rebuild completed info'] ?></p>
							<div class="fsetsubmit"><input type="submit" name="rebuild_index" value="<?php echo $lang_admin_maintenance['Rebuild index'] ?>" tabindex="4" /></div>
						</div>
					</fieldset>
				</div>
			</form>

			<form method="post" action="admin_maintenance.php" onsubmit="return process_form(this)">
				<div class="inform">
					<input type="hidden" name="action" value="prune" />
					<fieldset>
						<legend><?php echo $lang_admin_maintenance['Prune subhead'] ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang_admin_maintenance['Days old label'] ?></th>
									<td>
										<input type="text" name="req_prune_days" size="3" maxlength="3" tabindex="5" />
										<span><?php echo $lang_admin_maintenance['Days old help'] ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_maintenance['Prune sticky label'] ?></th>
									<td>
										<input type="radio" name="prune_sticky" value="1" tabindex="6" checked="checked" />&#160;<strong><?php echo $lang_admin_common['Yes'] ?></strong>&#160;&#160;&#160;<input type="radio" name="prune_sticky" value="0" />&#160;<strong><?php echo $lang_admin_common['No'] ?></strong>
										<span><?php echo $lang_admin_maintenance['Prune sticky help'] ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_maintenance['Prune from label'] ?></th>
									<td>
										<select name="prune_from" tabindex="7">
											<option value="all"><?php echo $lang_admin_maintenance['All forums'] ?></option>
<?php

	$query = new SelectQuery(array('cid' => 'c.id AS cid', 'cat_name' => 'c.cat_name', 'fid' => 'f.id AS fid', 'forum_name' => 'f.forum_name'), 'categories AS c');

	$query->joins['f'] = new InnerJoin('forums AS f');
	$query->joins['f']->on = 'c.id = f.cat_id';

	$query->where = 'f.redirect_url IS NULL';
	$query->order = array('cposition' => 'c.disp_position ASC', 'cid' => 'c.id ASC', 'fposition' => 'f.disp_position');

	$params = array();

	$result = $db->query($query, $params);

	$cur_category = 0;
	foreach ($result as $forum);
	{
		if ($forum['cid'] != $cur_category) // Are we still in the same category?
		{
			if ($cur_category)
				echo "\t\t\t\t\t\t\t\t\t\t\t".'</optgroup>'."\n";

			echo "\t\t\t\t\t\t\t\t\t\t\t".'<optgroup label="'.pun_htmlspecialchars($forum['cat_name']).'">'."\n";
			$cur_category = $forum['cid'];
		}

		echo "\t\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$forum['fid'].'">'.pun_htmlspecialchars($forum['forum_name']).'</option>'."\n";
	}

	unset ($result, $query, $params);

?>
											</optgroup>
										</select>
										<span><?php echo $lang_admin_maintenance['Prune from help'] ?></span>
									</td>
								</tr>
							</table>
							<p class="topspace"><?php printf($lang_admin_maintenance['Prune info'], '<a href="admin_options.php#maintenance">'.$lang_admin_common['Maintenance mode'].'</a>') ?></p>
							<div class="fsetsubmit"><input type="submit" name="prune" value="<?php echo $lang_admin_common['Prune'] ?>" tabindex="8" /></div>
						</div>
					</fieldset>
				</div>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

require PUN_ROOT.'footer.php';
