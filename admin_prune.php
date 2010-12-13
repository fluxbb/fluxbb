<?php

/**
 * Copyright (C) 2008-2010 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Tell header.php to use the admin template
define('PUN_ADMIN_CONSOLE', 1);

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/common_admin.php';


if ($pun_user['g_id'] != PUN_ADMIN)
	message($lang_common['No permission']);

// Load the admin_prune.php language file
require PUN_ROOT.'lang/'.$admin_language.'/admin_prune.php';

if (isset($_GET['action']) || isset($_POST['prune']) || isset($_POST['prune_comply']))
{
	$prune_from = trim($_POST['prune_from']);
	$prune_sticky = intval($_POST['prune_sticky']);

	if (isset($_POST['prune_comply']))
	{
		confirm_referrer('admin_prune.php');

		$prune_days = intval($_POST['prune_days']);
		$prune_date = ($prune_days) ? time() - ($prune_days * 86400) : -1;

		@set_time_limit(0);

		if ($prune_from == 'all')
		{
			$result = $db->query('SELECT id FROM '.$db->prefix.'forums') or error('Unable to fetch forum list', __FILE__, __LINE__, $db->error());
			$num_forums = $db->num_rows($result);

			for ($i = 0; $i < $num_forums; ++$i)
			{
				$fid = $db->result($result, $i);

				prune($fid, $prune_sticky, $prune_date);
				update_forum($fid);
			}
		}
		else
		{
			$prune_from = intval($prune_from);
			prune($prune_from, $prune_sticky, $prune_date);
			update_forum($prune_from);
		}

		// Locate any "orphaned redirect topics" and delete them
		$result = $db->query('SELECT t1.id FROM '.$db->prefix.'topics AS t1 LEFT JOIN '.$db->prefix.'topics AS t2 ON t1.moved_to=t2.id WHERE t2.id IS NULL AND t1.moved_to IS NOT NULL') or error('Unable to fetch redirect topics', __FILE__, __LINE__, $db->error());
		$num_orphans = $db->num_rows($result);

		if ($num_orphans)
		{
			for ($i = 0; $i < $num_orphans; ++$i)
				$orphans[] = $db->result($result, $i);

			$db->query('DELETE FROM '.$db->prefix.'topics WHERE id IN('.implode(',', $orphans).')') or error('Unable to delete redirect topics', __FILE__, __LINE__, $db->error());
		}

		redirect('admin_prune.php', $lang_admin_prune['Posts pruned redirect']);
	}

	$prune_days = trim($_POST['req_prune_days']);
	if ($prune_days == '' || preg_match('/[^0-9]/', $prune_days))
		message($lang_admin_prune['Must be integer message']);

	$prune_date = time() - ($prune_days * 86400);

	// Concatenate together the query for counting number of topics to prune
	$sql = 'SELECT COUNT(id) FROM '.$db->prefix.'topics WHERE last_post<'.$prune_date.' AND moved_to IS NULL';

	if ($prune_sticky == '0')
		$sql .= ' AND sticky=\'0\'';

	if ($prune_from != 'all')
	{
		$prune_from = intval($prune_from);
		$sql .= ' AND forum_id='.$prune_from;

		// Fetch the forum name (just for cosmetic reasons)
		$result = $db->query('SELECT forum_name FROM '.$db->prefix.'forums WHERE id='.$prune_from) or error('Unable to fetch forum name', __FILE__, __LINE__, $db->error());
		$forum = '"'.pun_htmlspecialchars($db->result($result)).'"';
	}
	else
		$forum = $lang_admin_prune['All forums'];

	$result = $db->query($sql) or error('Unable to fetch topic prune count', __FILE__, __LINE__, $db->error());
	$num_topics = $db->result($result);

	if (!$num_topics)
		message(sprintf($lang_admin_prune['No old topics message'], $prune_days));


	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_common['Admin'], $lang_admin_common['Prune']);
	define('PUN_ACTIVE_PAGE', 'admin');
	require PUN_ROOT.'header.php';

	generate_admin_menu('prune');

?>
	<div class="blockform">
		<h2><span><?php echo $lang_admin_prune['Prune head'] ?></span></h2>
		<div class="box">
			<form method="post" action="admin_prune.php?action=foo">
				<div class="inform">
					<input type="hidden" name="prune_days" value="<?php echo $prune_days ?>" />
					<input type="hidden" name="prune_sticky" value="<?php echo $prune_sticky ?>" />
					<input type="hidden" name="prune_from" value="<?php echo $prune_from ?>" />
					<fieldset>
						<legend><?php echo $lang_admin_prune['Confirm prune subhead'] ?></legend>
						<div class="infldset">
							<p><?php printf($lang_admin_prune['Confirm prune info'], $prune_days, $forum, forum_number_format($num_topics)) ?></p>
							<p class="warntext"><?php echo $lang_admin_prune['Confirm prune warn'] ?></p>
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
}


else
{
	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_common['Admin'], $lang_admin_common['Prune']);
	$required_fields = array('req_prune_days' => $lang_admin_prune['Days old label']);
	$focus_element = array('prune', 'req_prune_days');
	define('PUN_ACTIVE_PAGE', 'admin');
	require PUN_ROOT.'header.php';

	generate_admin_menu('prune');

?>
	<div class="blockform">
		<h2><span><?php echo $lang_admin_prune['Prune head'] ?></span></h2>
		<div class="box">
			<form id="prune" method="post" action="admin_prune.php?action=foo" onsubmit="return process_form(this)">
				<div class="inform">
					<input type="hidden" name="form_sent" value="1" />
					<fieldset>
						<legend><?php echo $lang_admin_prune['Prune subhead'] ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang_admin_prune['Days old label'] ?></th>
									<td>
										<input type="text" name="req_prune_days" size="3" maxlength="3" tabindex="1" />
										<span><?php echo $lang_admin_prune['Days old help'] ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_prune['Prune sticky label'] ?></th>
									<td>
										<input type="radio" name="prune_sticky" value="1" tabindex="2" checked="checked" />&#160;<strong><?php echo $lang_admin_common['Yes'] ?></strong>&#160;&#160;&#160;<input type="radio" name="prune_sticky" value="0" />&#160;<strong><?php echo $lang_admin_common['No'] ?></strong>
										<span><?php echo $lang_admin_prune['Prune sticky help'] ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_prune['Prune from label'] ?></th>
									<td>
										<select name="prune_from" tabindex="3">
											<option value="all"><?php echo $lang_admin_prune['All forums'] ?></option>
<?php

	$result = $db->query('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name FROM '.$db->prefix.'categories AS c INNER JOIN '.$db->prefix.'forums AS f ON c.id=f.cat_id WHERE f.redirect_url IS NULL ORDER BY c.disp_position, c.id, f.disp_position') or error('Unable to fetch category/forum list', __FILE__, __LINE__, $db->error());

	$cur_category = 0;
	while ($forum = $db->fetch_assoc($result))
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

?>
											</optgroup>
										</select>
										<span><?php echo $lang_admin_prune['Prune from help'] ?></span>
									</td>
								</tr>
							</table>
							<p class="topspace"><?php printf($lang_admin_prune['Prune info'], '<a href="admin_options.php#maintenance">'.$lang_admin_common['Maintenance mode'].'</a>') ?></p>
							<div class="fsetsubmit"><input type="submit" name="prune" value="<?php echo $lang_admin_common['Prune'] ?>" tabindex="5" /></div>
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
}
