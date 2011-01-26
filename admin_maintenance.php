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

if (isset($_GET['i_per_page']) && isset($_GET['i_start_at']))
{
	$per_page = intval($_GET['i_per_page']);
	$start_at = intval($_GET['i_start_at']);

	// Check per page is > 0
	if ($per_page < 1)
		message($lang_admin_maintenance['Must be integer message']);

	@set_time_limit(0);

	// If this is the first cycle of posts we empty the search index before we proceed
	if (isset($_GET['i_empty_index']))
	{
		// This is the only potentially "dangerous" thing we can do here, so we check the referer
		confirm_referrer('admin_maintenance.php');

		$db->truncate_table('search_matches') or error('Unable to empty search index match table', __FILE__, __LINE__, $db->error());
		$db->truncate_table('search_words') or error('Unable to empty search index words table', __FILE__, __LINE__, $db->error());

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

	$query_str = '';

	require PUN_ROOT.'include/search_idx.php';

	// Fetch posts to process this cycle
	$result = $db->query('SELECT p.id, p.message, t.subject, t.first_post_id FROM '.$db->prefix.'posts AS p INNER JOIN '.$db->prefix.'topics AS t ON t.id=p.topic_id WHERE p.id >= '.$start_at.' ORDER BY p.id ASC LIMIT '.$per_page) or error('Unable to fetch posts', __FILE__, __LINE__, $db->error());

	$end_at = 0;
	while ($cur_item = $db->fetch_assoc($result))
	{
		echo '<p><span>'.sprintf($lang_admin_maintenance['Processing post'], $cur_item['id']).'</span></p>'."\n";

		if ($cur_item['id'] == $cur_item['first_post_id'])
			update_search_index('post', $cur_item['id'], $cur_item['message'], $cur_item['subject']);
		else
			update_search_index('post', $cur_item['id'], $cur_item['message']);

		$end_at = $cur_item['id'];
	}

	// Check if there is more work to do
	if ($end_at > 0)
	{
		$result = $db->query('SELECT id FROM '.$db->prefix.'posts WHERE id > '.$end_at.' ORDER BY id ASC LIMIT 1') or error('Unable to fetch next ID', __FILE__, __LINE__, $db->error());

		if ($db->num_rows($result) > 0)
			$query_str = '?i_per_page='.$per_page.'&i_start_at='.$db->result($result);
	}

	$db->end_transaction();
	$db->close();

	exit('<script type="text/javascript">window.location="admin_maintenance.php'.$query_str.'"</script><hr /><p>'.sprintf($lang_admin_maintenance['Javascript redirect failed'], '<a href="admin_maintenance.php'.$query_str.'">'.$lang_admin_maintenance['Click here'].'</a>').'</p>');
}


// Get the first post ID from the db
$result = $db->query('SELECT id FROM '.$db->prefix.'posts ORDER BY id ASC LIMIT 1') or error('Unable to fetch topic info', __FILE__, __LINE__, $db->error());
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
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

require PUN_ROOT.'footer.php';
