<?php
/***********************************************************************

  Copyright (C) 2002-2005  Rickard Andersson (rickard@punbb.org)

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


// Tell header.php to use the admin template
define('PUN_ADMIN_CONSOLE', 1);
// Tell common.php that we don't want output buffering
define('PUN_DISABLE_BUFFERING', 1);

define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/common_admin.php';


if ($pun_user['g_id'] > PUN_ADMIN)
	message($lang_common['No permission']);


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
				$result = $db->query('ALTER TABLE '.$db->prefix.'search_words auto_increment=1') or error('Unable to update table auto_increment', __FILE__, __LINE__, $db->error());
				break;

			case 'pgsql';
				$result = $db->query('SELECT setval(\''.$db->prefix.'search_words_id_seq\', 1, false)') or error('Unable to update sequence', __FILE__, __LINE__, $db->error());
		}
	}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title><?php echo pun_htmlspecialchars($pun_config['o_board_title']) ?> / Rebuilding search index &hellip;</title>
<style type="text/css">
body {
	font: 10px Verdana, Arial, Helvetica, sans-serif;
	color: #333333;
	background-color: #FFFFFF
}
</style>
</head>
<body>

Rebuilding index &hellip; This might be a good time to put on some coffee :-)<br /><br />

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
		if ($cur_post[0] <> $cur_topic)
		{
			// Fetch subject and ID of first post in topic
			$result2 = $db->query('SELECT p.id, t.subject, MIN(p.posted) AS first FROM '.$db->prefix.'posts AS p INNER JOIN '.$db->prefix.'topics AS t ON t.id=p.topic_id WHERE t.id='.$cur_post[0].' GROUP BY p.id, t.subject ORDER BY first LIMIT 1') or error('Unable to fetch topic info', __FILE__, __LINE__, $db->error());
			list($first_post, $subject) = $db->fetch_row($result2);

			$cur_topic = $cur_post[0];
		}

		echo 'Processing post <strong>'.$cur_post[1].'</strong> in topic <strong>'.$cur_post[0].'</strong><br />'."\n";

		if ($cur_post[1] == $first_post)	// This is the "topic post" so we have to index the subject as well
			update_search_index('post', $cur_post[1], $cur_post[2], $subject);
		else
			update_search_index('post', $cur_post[1], $cur_post[2]);
	}

	// Check if there is more work to do
	$result = $db->query('SELECT id FROM '.$db->prefix.'topics WHERE id>'.$cur_topic.' ORDER BY id ASC LIMIT 1') or error('Unable to fetch topic info', __FILE__, __LINE__, $db->error());

	$query_str = ($db->num_rows($result)) ? '?i_per_page='.$per_page.'&i_start_at='.$db->result($result) : '';

	$db->end_transaction();
	$db->close();

	exit('<script type="text/javascript">window.location="admin_maintenance.php'.$query_str.'"</script><br />JavaScript redirect unsuccessful. Click <a href="admin_maintenance.php'.$query_str.'">here</a> to continue.');
}


// Get the first post ID from the db
$result = $db->query('SELECT id FROM '.$db->prefix.'topics ORDER BY id LIMIT 1') or error('Unable to fetch topic info', __FILE__, __LINE__, $db->error());
if ($db->num_rows($result))
	$first_id = $db->result($result);

$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / Admin / Maintenance';
require PUN_ROOT.'header.php';

generate_admin_menu('maintenance');

?>
	<div class="blockform">
		<h2><span>Forum Maintenance</span></h2>
		<div class="box">
			<form method="get" action="admin_maintenance.php">
				<div class="inform">
					<fieldset>
						<legend>Rebuild search index</legend>
						<div class="infldset">
							<p>If you've added, edited or removed posts manually in the database or if you're having problems searching, you should rebuild the search index. For best performance you should put the forum in maintenance mode during rebuilding. <strong>Rebuilding the search index can take a long time and will increase server load during the rebuild process!</strong></p>
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row">Topics per cycle</th>
									<td>
										<input type="text" name="i_per_page" size="7" maxlength="7" value="100" tabindex="1" />
										<span>The number of topics to process per pageview. E.g. if you were to enter 100, one hundred topics would be processed and then the page would refresh. This is to prevent the script from timing out during the rebuild process.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Starting Topic ID</th>
									<td>
										<input type="text" name="i_start_at" size="7" maxlength="7" value="<?php echo (isset($first_id)) ? $first_id : 0 ?>" tabindex="2" />
										<span>The topic ID to start rebuilding at. It's default value is the first available ID in the database. Normally you wouldn't want to change this.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Empty index</th>
									<td class="inputadmin">
										<span><input type="checkbox" name="i_empty_index" value="1" tabindex="3" checked="checked" />&nbsp;&nbsp;Select this if you want the search index to be emptied before rebuilding (see below).</span>
									</td>
								</tr>
							</table>
							<p class="topspace">Once the process has completed you will be redirected back to this page. It is highly recommended that you have JavaScript enabled in your browser during rebuilding (for automatic redirect when a cycle has completed). If you are forced to abort the rebuild process, make a note of the last processed topic ID and enter that ID+1 in "Topic ID to start at" when/if you want to continue ("Empty index" must not be selected).</p>
							<div class="fsetsubmit"><input type="submit" name="rebuild_index" value="Rebuild index" tabindex="4" /></div>
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
