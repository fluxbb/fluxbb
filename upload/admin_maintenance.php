<?php

/*---

	Copyright (C) 2008-2010 FluxBB.org
	based on code copyright (C) 2002-2005 Rickard Andersson
	License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher

---*/

// Tell header.php to use the admin template
define('PUN_ADMIN_CONSOLE', 1);
// Tell common.php that we don't want output buffering
define('PUN_DISABLE_BUFFERING', 1);

define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/common_admin.php';


if ($pun_user['g_id'] != PUN_ADMIN)
	message($lang_common['No permission']);

// Load the admin_maintenance.php language file
require PUN_ROOT.'lang/'.$admin_language.'/admin_maintenance.php';

if (isset($_GET['i_per_page']) && isset($_GET['i_start_at']))
{
	$per_page = intval($_GET['i_per_page']);
	$start_at = intval($_GET['i_start_at']);
	if ($per_page < 1 || $start_at < 1)
		message($lang_common['Bad request']);

	@set_time_limit(0);

	// If this is the first cycle of posts we empty the search index before we proceed
	if (isset($_GET['i_empty_index']))
	{
		// This is the only potentially "dangerous" thing we can do here, so we check the referer
		confirm_referrer('admin_maintenance.php');

		$truncate_sql = ($db_type != 'sqlite' && $db_type != 'pgsql') ? 'TRUNCATE TABLE ' : 'DELETE FROM ';
		$db->query($truncate_sql.$db->prefix.'search_matches') or error('Unable to empty search index match table', __FILE__, __LINE__, $db->error());
		$db->query($truncate_sql.$db->prefix.'search_words') or error('Unable to empty search index words table', __FILE__, __LINE__, $db->error());

		// Reset the sequence for the search words (not needed for SQLite)
		switch ($db_type)
		{
			case 'mysql':
			case 'mysqli':
			case 'mysql_innodb':
			case 'mysqli_innodb':
				$result = $db->query('ALTER TABLE '.$db->prefix.'search_words auto_increment=1') or error('Unable to update table auto_increment', __FILE__, __LINE__, $db->error());
				break;

			case 'pgsql';
				$result = $db->query('SELECT setval(\''.$db->prefix.'search_words_id_seq\', 1, false)') or error('Unable to update sequence', __FILE__, __LINE__, $db->error());
		}
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

	require PUN_ROOT.'include/search_idx.php';

	// Fetch posts to process
	$result = $db->query('SELECT id FROM '.$db->prefix.'topics WHERE id>='.$start_at.' ORDER BY id LIMIT '.$per_page) or error('Unable to fetch topic list', __FILE__, __LINE__, $db->error());
	$topics = array();
	while ($cur_topic = $db->fetch_row($result))
		$topics[] = $cur_topic[0];

	$result = $db->query('SELECT topic_id, id, message FROM '.$db->prefix.'posts WHERE topic_id IN ('.implode(',', $topics).') ORDER BY topic_id') or error('Unable to fetch topic/post info', __FILE__, __LINE__, $db->error());

	$cur_topic = 0;
	while ($cur_post = $db->fetch_row($result))
	{
		if ($cur_post[0] != $cur_topic)
		{
			// Fetch subject and ID of first post in topic
			$result2 = $db->query('SELECT p.id, t.subject, MIN(p.posted) AS first FROM '.$db->prefix.'posts AS p INNER JOIN '.$db->prefix.'topics AS t ON t.id=p.topic_id WHERE t.id='.$cur_post[0].' GROUP BY p.id, t.subject ORDER BY first LIMIT 1') or error('Unable to fetch topic info', __FILE__, __LINE__, $db->error());
			list($first_post, $subject) = $db->fetch_row($result2);

			$cur_topic = $cur_post[0];
		}

		echo '<p><span>'.sprintf($lang_admin_maintenance['Processing post'], $cur_post[1], $cur_post[0]).'</span></p>'."\n";

		if ($cur_post[1] == $first_post) // This is the "topic post" so we have to index the subject as well
			update_search_index('post', $cur_post[1], $cur_post[2], $subject);
		else
			update_search_index('post', $cur_post[1], $cur_post[2]);
	}

	// Check if there is more work to do
	$result = $db->query('SELECT id FROM '.$db->prefix.'topics WHERE id>'.$cur_topic.' ORDER BY id ASC LIMIT 1') or error('Unable to fetch topic info', __FILE__, __LINE__, $db->error());

	$query_str = ($db->num_rows($result)) ? '?i_per_page='.$per_page.'&i_start_at='.$db->result($result) : '';

	$db->end_transaction();
	$db->close();

	exit('<script type="text/javascript">window.location="admin_maintenance.php'.$query_str.'"</script><hr /><p>'.sprintf($lang_admin_maintenance['Javascript redirect failed'], '<a href="admin_maintenance.php'.$query_str.'">'.$lang_admin_maintenance['Click here'].'</a>').'</p>');
}


// Get the first post ID from the db
$result = $db->query('SELECT id FROM '.$db->prefix.'topics ORDER BY id LIMIT 1') or error('Unable to fetch topic info', __FILE__, __LINE__, $db->error());
if ($db->num_rows($result))
	$first_id = $db->result($result);

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
					<fieldset>
						<legend><?php echo $lang_admin_maintenance['Rebuild index subhead'] ?></legend>
						<div class="infldset">
							<p><?php printf($lang_admin_maintenance['Rebuild index info'], '<a href="admin_options.php#maintenance">'.$lang_admin_common['Maintenance mode'].'</a>') ?></p>
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang_admin_maintenance['Topics per cycle label'] ?></th>
									<td>
										<input type="text" name="i_per_page" size="7" maxlength="7" value="100" tabindex="1" />
										<span><?php echo $lang_admin_maintenance['Topics per cycle help'] ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_maintenance['Starting topic label'] ?></th>
									<td>
										<input type="text" name="i_start_at" size="7" maxlength="7" value="<?php echo (isset($first_id)) ? $first_id : 0 ?>" tabindex="2" />
										<span><?php echo $lang_admin_maintenance['Starting topic help'] ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_maintenance['Empty index label'] ?></th>
									<td class="inputadmin">
										<span><input type="checkbox" name="i_empty_index" value="1" tabindex="3" checked="checked" />&nbsp;&nbsp;<?php echo $lang_admin_maintenance['Empty index help'] ?></span>
									</td>
								</tr>
							</table>
							<p class="topspace"><?php echo $lang_admin_maintenance['Rebuild completed info'] ?></p>
							<div class="fsetsubmit"><input type="submit" name="rebuild_index" value="<?php echo $lang_admin_maintenance['Rebuild index'] ?>" tabindex="4" /></div>
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
