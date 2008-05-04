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

($hook = get_hook('aus_start')) ? eval($hook) : null;

if (!$forum_user['is_admmod'])
	message($lang_common['No permission']);

// Load the admin.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin.php';


// Show IP statistics for a certain user ID
if (isset($_GET['ip_stats']))
{
	$ip_stats = intval($_GET['ip_stats']);
	if ($ip_stats < 1)
		message($lang_common['Bad request']);

	($hook = get_hook('aus_ip_stats_selected')) ? eval($hook) : null;

	$query = array(
		'SELECT'	=> 'p.poster_ip, MAX(p.posted) AS last_used, COUNT(p.id) AS used_times',
		'FROM'		=> 'posts AS p',
		'WHERE'		=> 'p.poster_id='.$ip_stats,
		'GROUP BY'	=> 'p.poster_ip',
		'ORDER BY'	=> 'last_used DESC'
	);

	($hook = get_hook('aus_qr_get_user_ips')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$forum_page['num_users'] = $forum_db->num_rows($result);

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin['Forum administration'], forum_link($forum_url['admin_index'])),
		array($lang_admin['Searches'], forum_link($forum_url['admin_users'])),
		$lang_admin['User search results']
	);

	($hook = get_hook('aus_ip_stats_pre_header_load')) ? eval($hook) : null;

	define('FORUM_PAGE_SECTION', 'users');
	define('FORUM_PAGE', 'admin-users');
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
			<h2><span><?php printf($lang_admin['IP addresses found'], $forum_page['num_users']) ?></span></h2>
		</div>
		<div class="frm-form">
			<table cellspacing="0">
				<thead>
					<tr>
						<th class="tcl" scope="col"><?php echo $lang_admin['IP address'] ?></th>
						<th class="tc2" scope="col"><?php echo $lang_admin['Last used'] ?></th>
						<th class="tc3" scope="col"><?php echo $lang_admin['Times found'] ?></th>
<?php ($hook = get_hook('aus_ip_stats_table_header_after_used_times')) ? eval($hook) : null; ?>
						<th class="tcr" scope="col"><?php echo $lang_admin['Actions'] ?></th>
<?php ($hook = get_hook('aus_ip_stats_table_header_after_actions')) ? eval($hook) : null; ?>
					</tr>
				</thead>
				<tbody>
<?php

	if ($forum_page['num_users'])
	{
		while ($cur_ip = $forum_db->fetch_assoc($result))
		{
			$forum_page['actions'] = '<a href="'.forum_link($forum_url['admin_users']).'?show_users='.$cur_ip['poster_ip'].'">'.$lang_admin['Find more users'].'</a>';

?>
					<tr>
						<td class="tcl"><a href="<?php echo forum_link($forum_url['get_host'], $cur_ip['poster_ip']) ?>"><?php echo $cur_ip['poster_ip'] ?></a></td>
						<td class="tc2"><?php echo format_time($cur_ip['last_used']) ?></td>
						<td class="tc3"><?php echo $cur_ip['used_times'] ?></td>
<?php ($hook = get_hook('aus_ip_stats_table_contents_after_used_times')) ? eval($hook) : null; ?>
						<td class="tcr actions"><?php echo $forum_page['actions'] ?></td>
<?php ($hook = get_hook('aus_ip_stats_table_contents_after_actions')) ? eval($hook) : null; ?>
					</tr>
<?php

		}
	}
	else
		echo "\t\t\t\t\t\t".'<tr><td class="tcl" colspan="4">'.$lang_admin['No posts by user'].'</td></tr>'."\n";

?>
				</tbody>
			</table>
		</div>
	</div>

</div>
<?php

	$tpl_temp = trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}


// Show users that have at one time posted with the specified IP address
else if (isset($_GET['show_users']))
{
	$ip = $_GET['show_users'];

	if (!@preg_match('/[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}/', $ip) && !@preg_match('/^((([0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}:[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){5}:([0-9A-Fa-f]{1,4}:)?[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){4}:([0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){3}:([0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){2}:([0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(([0-9A-Fa-f]{1,4}:){0,5}:((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(::([0-9A-Fa-f]{1,4}:){0,5}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|([0-9A-Fa-f]{1,4}::([0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})|(::([0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){1,7}:))$/', $ip))
		message($lang_admin['Invalid IP address']);

	($hook = get_hook('aus_show_users_selected')) ? eval($hook) : null;

	// Load the misc.php language file
	require FORUM_ROOT.'lang/'.$forum_user['language'].'/misc.php';

	$query = array(
		'SELECT'	=> 'DISTINCT p.poster_id, p.poster',
		'FROM'		=> 'posts AS p',
		'WHERE'		=> 'p.poster_ip=\''.$forum_db->escape($ip).'\'',
		'ORDER BY'	=> 'p.poster DESC'
	);

	($hook = get_hook('aus_qr_get_users_matching_ip')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$forum_page['num_users'] = $forum_db->num_rows($result);

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin['Forum administration'], forum_link($forum_url['admin_index'])),
		array($lang_admin['Searches'], forum_link($forum_url['admin_users'])),
		$lang_admin['User search results']
	);

	($hook = get_hook('aus_show_users_pre_header_load')) ? eval($hook) : null;

	define('FORUM_PAGE_SECTION', 'users');
	define('FORUM_PAGE', 'admin-users');
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
			<h2><span><?php printf($lang_admin['Users found'], $forum_page['num_users']) ?></span></h2>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_users']) ?>?action=modify_users">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_users']).'?action=modify_users') ?>" />
			</div>
			<table cellspacing="0">
				<thead>
					<tr>
						<th class="tcl" scope="col"><?php echo $lang_admin['Username column'] ?></th>
						<th class="tc2" scope="col"><?php echo $lang_admin['Title column'] ?></th>
						<th class="tc3" scope="col"><?php echo $lang_admin['Posts'] ?></th>
<?php ($hook = get_hook('aus_show_users_table_header_after_num_posts')) ? eval($hook) : null; ?>
						<th class="tcr actions" scope="col"><?php echo $lang_admin['Actions'] ?></th>
<?php ($hook = get_hook('aus_show_users_table_header_after_actions')) ? eval($hook) : null; if ($forum_page['num_users'] > 0): ?>						<th class="tcmod" scope="col"><?php echo $lang_misc['Select'] ?></th>
<?php endif; ?>					</tr>
				</thead>
				<tbody>
<?php

	$num_posts = $forum_db->num_rows($result);
	if ($num_posts)
	{
		// Loop through users and print out some info
		for ($i = 0; $i < $num_posts; ++$i)
		{
			list($poster_id, $poster) = $forum_db->fetch_row($result);

			$query = array(
				'SELECT'	=> 'u.id, u.username, u.email, u.title, u.num_posts, u.admin_note, g.g_id, g.g_user_title',
				'FROM'		=> 'users AS u',
				'JOINS'		=> array(
					array(
						'INNER JOIN'	=> 'groups AS g',
						'ON'			=> 'g.g_id=u.group_id'
					)
				),
				'WHERE'		=> 'u.id>1 AND u.id='.$poster_id
			);

			($hook = get_hook('aus_qr_get_user_details')) ? eval($hook) : null;
			$result2 = $forum_db->query_build($query) or error(__FILE__, __LINE__);
			if ($user_data = $forum_db->fetch_assoc($result2))
			{
				$forum_page['user_title'] = get_title($user_data);
				$forum_page['actions'] = '<span><a href="'.forum_link($forum_url['admin_users']).'?ip_stats='.$user_data['id'].'">'.$lang_admin['View IP stats'].'</a></span> <span><a href="'.forum_link($forum_url['search_user_posts'], $user_data['id']).'">'.$lang_admin['Show posts'].'</a></span>';

?>
					<tr>
						<td class="tcl"><strong><a href="<?php echo forum_link($forum_url['user'], $user_data['id']) ?>"><?php echo forum_htmlencode($user_data['username']) ?></a></strong> <span class="usermail"><a href="mailto:<?php echo $user_data['email'] ?>"><?php echo $user_data['email'] ?></a></span> <?php if ($user_data['admin_note'] != '') echo '<span class="usernote">'.forum_htmlencode($user_data['admin_note']).'</span>' ?></td>
						<td class="tc2"><?php echo $forum_page['user_title'] ?></td>
						<td class="tc3"><?php echo $user_data['num_posts'] ?></td>
<?php ($hook = get_hook('aus_show_users_table_contents_after_num_posts')) ? eval($hook) : null; ?>
						<td class="tcr actions"><?php echo $forum_page['actions'] ?></td>
<?php ($hook = get_hook('aus_show_users_table_contents_after_actions')) ? eval($hook) : null; ?>						<td class="tcmod"><input type="checkbox" name="users[<?php echo $user_data['id'] ?>]" value="1" /></td>
					</tr>
<?php

			}
			else
			{

?>
					<tr>
						<td class="tcl"><?php echo forum_htmlencode($poster) ?></td>
						<td class="tc2"><?php echo $lang_admin['Guest'] ?></td>
						<td class="tc3">&#160;</td>
<?php ($hook = get_hook('aus_show_users_table_contents_after_num_posts_guest')) ? eval($hook) : null; ?>
						<td class="tcr">&#160;</td>
<?php ($hook = get_hook('aus_show_users_table_contents_after_actions_guest')) ? eval($hook) : null; ?>						<td class="tcmod">&#160;</td>
					</tr>
<?php

			}
		}
	}
	else
		echo "\t\t\t\t\t".'<tr><td class="tcl" colspan="4">'.$lang_admin['Cannot find IP'].'</td></tr>'."\n";

?>
				</tbody>
			</table>
<?php

	// Setup control buttons
	$forum_page['main_submit'] = array();

	if ($forum_page['num_users'] > 0)
	{
		if ($forum_user['g_id'] == FORUM_ADMIN || ($forum_user['g_moderator'] == '1' && $forum_user['g_mod_ban_users'] == '1'))
			$forum_page['main_submit']['ban'] = '<span class="submit"><input type="submit" name="ban_users" value="'.$lang_admin['Ban'].'" /></span>';

		if ($forum_user['g_id'] == FORUM_ADMIN)
		{
			$forum_page['main_submit']['delete'] = '<span class="submit"><input type="submit" name="delete_users" value="'.$lang_admin['Delete'].'" /></span>';
			$forum_page['main_submit']['change_group'] = '<span class="submit"><input type="submit" name="change_group" value="'.$lang_admin['Change group'].'" /></span>';
		}
	}

	($hook = get_hook('aus_show_user_pre_moderation_buttons')) ? eval($hook) : null;

	if (!empty($forum_page['main_submit']))
	{

?>
			<p class="submitting">
				<?php echo implode("\n\t\t\t", $forum_page['main_submit'])."\n" ?>
			</p>
<?php

	}

?>
		</form>
	</div>

</div>
<?php

	$tpl_temp = trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}


else if (isset($_POST['delete_users']) || isset($_POST['delete_users_comply']) || isset($_POST['delete_users_cancel']))
{
	// User pressed the cancel button
	if (isset($_POST['delete_users_cancel']))
		redirect(forum_link($forum_url['admin_users']), $lang_common['Cancel redirect']);

	if ($forum_user['g_id'] != FORUM_ADMIN)
		message($lang_common['No permission']);

	if (empty($_POST['users']))
		message($lang_admin['No users selected']);

	($hook = get_hook('aus_delete_users_selected')) ? eval($hook) : null;

	if (!is_array($_POST['users']))
		$users = explode(',', $_POST['users']);
	else
		$users = array_keys($_POST['users']);

	$users = array_map('intval', $users);

	// We check to make sure there are no administrators in this list
	$query = array(
		'SELECT'	=> '1',
		'FROM'		=> 'users AS u',
		'WHERE'		=> 'u.id IN ('.implode(',', $users).') AND u.group_id='.FORUM_ADMIN
	);

	($hook = get_hook('aus_qr_check_for_admins')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	if ($forum_db->num_rows($result) > 0)
		message($lang_admin['Delete admin message']);

	if (isset($_POST['delete_users_comply']))
	{
		($hook = get_hook('aus_delete_users_form_submitted')) ? eval($hook) : null;

		foreach ($users as $id)
		{
			// We don't want to delete the Guest user
			if ($id > 1)
				delete_user($id, true);
		}

		redirect(forum_link($forum_url['admin_users']), $lang_admin['Users deleted'].' '.$lang_admin['Redirect']);
	}

	// Setup form
	$forum_page['set_count'] = $forum_page['fld_count'] = 0;

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin['Forum administration'], forum_link($forum_url['admin_index'])),
		array($lang_admin['Searches'], forum_link($forum_url['admin_users'])),
		$lang_admin['Delete users']
	);

	($hook = get_hook('aus_delete_users_pre_header_load')) ? eval($hook) : null;

	define('FORUM_PAGE_SECTION', 'users');
	define('FORUM_PAGE', 'admin-users');
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
			<h2><span><?php echo $lang_admin['Confirm delete'] ?></span></h2>
		</div>
		<div class="frm-info">
			<p class="warn"><?php echo $lang_admin['Delete warning'] ?></p>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_users']) ?>?action=modify_users">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_users']).'?action=modify_users') ?>" />
				<input type="hidden" name="users" value="<?php echo implode(',', $users) ?>" />
			</div>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><span><?php echo $lang_admin['Delete posts legend'] ?></span></legend>
				<div class="radbox checkbox">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span class="fld-label"><?php echo $lang_admin['Delete posts'] ?></span><br /><input type="checkbox" id="fld<?php echo ++$fld_count ?>" name="delete_posts" value="1" checked="checked" /> <?php echo $lang_admin['Delete posts label'] ?></label>
				</div>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="delete_users_comply" value="<?php echo $lang_admin['Delete'] ?>" /></span>
				<span class="cancel"><input type="submit" name="delete_users_cancel" value="<?php echo $lang_common['Cancel'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

	$tpl_temp = trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}


else if (isset($_POST['ban_users']) || isset($_POST['ban_users_comply']))
{
	if ($forum_user['g_id'] != FORUM_ADMIN && ($forum_user['g_moderator'] != '1' || $forum_user['g_mod_ban_users'] == '0'))
		message($lang_common['No permission']);

	if (empty($_POST['users']))
		message($lang_admin['No users selected']);

	($hook = get_hook('aus_ban_users_selected')) ? eval($hook) : null;

	if (!is_array($_POST['users']))
		$users = explode(',', $_POST['users']);
	else
		$users = array_keys($_POST['users']);

	$users = array_map('intval', $users);

	// We check to make sure there are no administrators in this list
	$query = array(
		'SELECT'	=> '1',
		'FROM'		=> 'users AS u',
		'WHERE'		=> 'u.id IN ('.implode(',', $users).') AND u.group_id='.FORUM_ADMIN
	);

	($hook = get_hook('aus_qr_check_for_admins2')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	if ($forum_db->num_rows($result) > 0)
		message($lang_admin['Ban admin message']);

	if (isset($_POST['ban_users_comply']))
	{
		$ban_message = trim($_POST['ban_message']);
		$ban_expire = trim($_POST['ban_expire']);

		($hook = get_hook('aus_ban_users_form_submitted')) ? eval($hook) : null;

		if ($ban_expire != '' && $ban_expire != 'Never')
		{
			$ban_expire = strtotime($ban_expire);

			if ($ban_expire == -1 || $ban_expire <= time())
				message($lang_admin['Invalid expire message']);
		}
		else
			$ban_expire = 'NULL';

		$ban_message = ($ban_message != '') ? '"'.$forum_db->escape($ban_message).'"' : 'NULL';

		// Get the latest IPs for the posters and store them for a little later
		$query = array(
			'SELECT'	=> 'p.poster_id, p.poster_ip',
			'FROM'		=> 'posts AS p',
			'WHERE'		=> 'p.poster_id IN ('.implode(',', $users).') AND p.poster_id>1',
			'GROUP BY'	=> 'p.poster_id',
			'ORDER BY'	=> 'p.posted DESC'
		);

		($hook = get_hook('aus_qr_get_latest_user_ips')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		$ips = array();
		while ($cur_post = $forum_db->fetch_assoc($result))
			$ips[$cur_post['poster_id']] = $cur_post['poster_ip'];

		// Get the rest of the data for the posters, merge in the IP information, create a ban
		$query = array(
			'SELECT'	=> 'u.id, u.username, u.email, u.registration_ip',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'id IN ('.implode(',', $users).') AND id>1'
		);

		($hook = get_hook('aus_qr_get_users')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		while ($cur_user = $forum_db->fetch_assoc($result))
		{
			$ban_ip = isset($ips[$cur_user['id']]) ? $ips[$cur_user['id']] : $cur_user['registration_ip'];

			$query = array(
				'INSERT'	=> 'username, ip, email, message, expire, ban_creator',
				'INTO'		=> 'bans',
				'VALUES'	=> '\''.$forum_db->escape($cur_user['username']).'\', \''.$ban_ip.'\', \''.$forum_db->escape($cur_user['email']).'\', '.$ban_message.', '.$ban_expire.', '.$forum_user['id']
			);

			($hook = get_hook('aus_qr_add_ban')) ? eval($hook) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);
		}

		// Regenerate the bans cache
		require_once FORUM_ROOT.'include/cache.php';
		generate_bans_cache();

		redirect(forum_link($forum_url['admin_users']), $lang_admin['Users banned'].' '.$lang_admin['Redirect']);
	}

	// Setup form
	$forum_page['set_count'] = $forum_page['fld_count'] = 0;

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin['Forum administration'], forum_link($forum_url['admin_index'])),
		array($lang_admin['Searches'], forum_link($forum_url['admin_users'])),
		$lang_admin['Ban users']
	);

	($hook = get_hook('aus_ban_users_pre_header_load')) ? eval($hook) : null;

	define('FORUM_PAGE_SECTION', 'users');
	define('FORUM_PAGE', 'admin-users');
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
			<h2><span><?php echo $lang_admin['Ban advanced heading'] ?></span></h2>
		</div>
		<div class="frm-info">
			<p><?php echo $lang_admin['Mass ban info'] ?></p>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_users']) ?>?action=modify_users">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_users']).'?action=modify_users') ?>" />
				<input type="hidden" name="users" value="<?php echo implode(',', $users) ?>" />
			</div>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><span><?php echo $lang_admin['Ban settings legend'] ?></span></legend>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Ban message'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="ban_message" size="50" maxlength="255" /></span>
						<span class="fld-help"><?php echo $lang_admin['Ban message info'] ?></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Expire date'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="ban_expire" size="17" maxlength="10" /></span>
						<span class="fld-help"><?php echo $lang_admin['Expire date info'] ?></span>
					</label>
				</div>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" class="button" name="ban_users_comply" value="<?php echo $lang_admin['Ban'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

	$tpl_temp = trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}


else if (isset($_POST['change_group']) || isset($_POST['change_group_comply']) || isset($_POST['change_group_cancel']))
{
	if ($forum_user['g_id'] != FORUM_ADMIN)
		message($lang_common['No permission']);

	// User pressed the cancel button
	if (isset($_POST['change_group_cancel']))
		redirect(forum_link($forum_url['admin_users']), $lang_admin['Cancel redirect']);

	if (empty($_POST['users']))
		message($lang_admin['No users selected']);

	($hook = get_hook('aus_change_group_selected')) ? eval($hook) : null;

	if (!is_array($_POST['users']))
		$users = explode(',', $_POST['users']);
	else
		$users = array_keys($_POST['users']);

	$users = array_map('intval', $users);

	if (isset($_POST['change_group_comply']))
	{
		$move_to_group = intval($_POST['move_to_group']);

		($hook = get_hook('aus_change_group_form_submitted')) ? eval($hook) : null;

		// We need some information on the group
		$query = array(
			'SELECT'	=> 'g.g_moderator',
			'FROM'		=> 'groups AS g',
			'WHERE'		=> 'g.g_id='.$move_to_group
		);

		($hook = get_hook('aus_qr_get_group_moderator_status')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		if ($move_to_group == FORUM_GUEST || !$forum_db->num_rows($result))
			message($lang_common['Bad request']);

		$group_is_mod = $forum_db->result($result);

		// Move users
		$query = array(
			'UPDATE'	=> 'users',
			'SET'		=> 'group_id='.$move_to_group,
			'WHERE'		=> 'id IN ('.implode(',', $users).') AND id>1'
		);

		($hook = get_hook('aus_qr_change_user_group')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		if ($move_to_group != FORUM_ADMIN && $group_is_mod == '0')
			clean_forum_moderators();

		redirect(forum_link($forum_url['admin_users']), $lang_admin['User groups updated'].' '.$lang_admin['Redirect']);
	}

	// Setup form
	$forum_page['set_count'] = $forum_page['fld_count'] = 0;

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin['Forum administration'], forum_link($forum_url['admin_index'])),
		array($lang_admin['Searches'], forum_link($forum_url['admin_users'])),
		$lang_admin['Change group']
	);

	($hook = get_hook('aus_change_group_pre_header_load')) ? eval($hook) : null;

	define('FORUM_PAGE_SECTION', 'users');
	define('FORUM_PAGE', 'admin-users');
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
			<h2><span><?php echo $lang_admin['Change group head'] ?></span></h2>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_users']) ?>?action=modify_users">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_users']).'?action=modify_users') ?>" />
				<input type="hidden" name="users" value="<?php echo implode(',', $users) ?>" />
			</div>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><span><?php echo $lang_admin['Move users legend'] ?></span></legend>
				<div class="frm-fld select">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Move users to'] ?></span><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="move_to_group">
<?php

	$query = array(
		'SELECT'	=> 'g.g_id, g.g_title',
		'FROM'		=> 'groups AS g',
		'WHERE'		=> 'g.g_id!='.FORUM_GUEST,
		'ORDER BY'	=> 'g.g_title'
	);

	($hook = get_hook('aus_qr_get_groups')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	while ($cur_group = $forum_db->fetch_assoc($result))
	{
		if ($cur_group['g_id'] == $forum_config['o_default_user_group'])	// Pre-select the default Members group
			echo "\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.forum_htmlencode($cur_group['g_title']).'</option>'."\n";
		else
			echo "\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.forum_htmlencode($cur_group['g_title']).'</option>'."\n";
	}

?>
						</select></span>
					</label>
				</div>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="change_group_comply" value="<?php echo $lang_admin['Change group'] ?>" /></span>
				<span class="cancel"><input type="submit" name="change_group_cancel" value="<?php echo $lang_admin['Cancel'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

	$tpl_temp = trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}


else if (isset($_POST['find_user']))
{
	$form = $_POST['form'];
	$form['username'] = $_POST['username'];

	($hook = get_hook('aus_find_user_selected')) ? eval($hook) : null;

	// trim() all elements in $form
	$form = array_map('trim', $form);
	$conditions = array();

	$posts_greater = trim($_POST['posts_greater']);
	$posts_less = trim($_POST['posts_less']);
	$last_post_after = trim($_POST['last_post_after']);
	$last_post_before = trim($_POST['last_post_before']);
	$registered_after = trim($_POST['registered_after']);
	$registered_before = trim($_POST['registered_before']);
	$order_by = $_POST['order_by'];
	$direction = $_POST['direction'];
	$user_group = $_POST['user_group'];

	if ((!empty($posts_greater) || !empty($posts_less)) && !ctype_digit($posts_greater.$posts_less))
		message($lang_admin['Non numeric value message']);

	// Try to convert date/time to timestamps
	if ($last_post_after != '')
		$last_post_after = strtotime($last_post_after);
	if ($last_post_before != '')
		$last_post_before = strtotime($last_post_before);
	if ($registered_after != '')
		$registered_after = strtotime($registered_after);
	if ($registered_before != '')
		$registered_before = strtotime($registered_before);

	if ($last_post_after == -1 || $last_post_before == -1 || $registered_after == -1 || $registered_before == -1)
		message($lang_admin['Invalid date/time message']);

	if ($last_post_after != '')
		$conditions[] = 'u.last_post>'.$last_post_after;
	if ($last_post_before != '')
		$conditions[] = 'u.last_post<'.$last_post_before;
	if ($registered_after != '')
		$conditions[] = 'u.registered>'.$registered_after;
	if ($registered_before != '')
		$conditions[] = 'u.registered<'.$registered_before;

	$like_command = ($db_type == 'pgsql') ? 'ILIKE' : 'LIKE';
	while (list($key, $input) = @each($form))
	{
		if ($input != '' && in_array($key, array('username', 'email', 'title', 'realname', 'url', 'jabber', 'icq', 'msn', 'aim', 'yahoo', 'location', 'signature', 'admin_note')))
			$conditions[] = 'u.'.$forum_db->escape($key).' '.$like_command.' \''.$forum_db->escape(str_replace('*', '%', $input)).'\'';
	}

	if ($posts_greater != '')
		$conditions[] = 'u.num_posts>'.$posts_greater;
	if ($posts_less != '')
		$conditions[] = 'u.num_posts<'.$posts_less;

	if ($user_group != 'all')
		$conditions[] = 'u.group_id='.intval($user_group);

	if (empty($conditions))
		message($lang_admin['No search terms message']);


	// Load the misc.php language file
	require FORUM_ROOT.'lang/'.$forum_user['language'].'/misc.php';

	// Find any users matching the conditions
	$query = array(
		'SELECT'	=> 'u.id, u.username, u.email, u.title, u.num_posts, u.admin_note, g.g_id, g.g_user_title',
		'FROM'		=> 'users AS u',
		'JOINS'		=> array(
			array(
				'LEFT JOIN'		=> 'groups AS g',
				'ON'			=> 'g.g_id=u.group_id'
			)
		),
		'WHERE'		=> 'u.id>1 AND '.implode(' AND ', $conditions),
		'ORDER BY'	=> $forum_db->escape($order_by).' '.$forum_db->escape($direction)
	);

	($hook = get_hook('aus_qr_find_users')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$forum_page['num_users'] = $forum_db->num_rows($result);


	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin['Forum administration'], forum_link($forum_url['admin_index'])),
		array($lang_admin['Searches'], forum_link($forum_url['admin_users'])),
		$lang_admin['User search results']
	);

	($hook = get_hook('aus_find_user_pre_header_load')) ? eval($hook) : null;

	define('FORUM_PAGE_SECTION', 'users');
	define('FORUM_PAGE', 'admin-users');
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
			<h2><span><?php printf($lang_admin['Users found'], $forum_page['num_users']) ?></span></h2>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_users']) ?>?action=modify_users">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_users']).'?action=modify_users') ?>" />
			</div>
			<table cellspacing="0">
				<thead>
					<tr>
						<th class="tcl" scope="col"><?php echo $lang_admin['Username column'] ?></th>
						<th class="tc2" scope="col"><?php echo $lang_admin['Title column'] ?></th>
						<th class="tc3" scope="col"><?php echo $lang_admin['Posts'] ?></th>
<?php ($hook = get_hook('aus_find_user_table_header_after_num_posts')) ? eval($hook) : null; ?>
						<th class="tcr actions" scope="col"><?php echo $lang_admin['Actions'] ?></th>
<?php ($hook = get_hook('aus_find_user_table_header_after_actions')) ? eval($hook) : null; if ($forum_page['num_users'] > 0): ?>					<th class="tcmod" scope="col"><?php echo $lang_misc['Select'] ?></th>
<?php endif; ?>					</tr>
				</thead>
				<tbody>
<?php

	if ($forum_page['num_users'])
	{
		while ($user_data = $forum_db->fetch_assoc($result))
		{
			$user_title = get_title($user_data);

			// This script is a special case in that we want to display "Not verified" for non-verified users
			if (($user_data['g_id'] == '' || $user_data['g_id'] == FORUM_UNVERIFIED) && $user_title != $lang_common['Banned'])
				$user_title = '<strong>'.$lang_admin['Not verified'].'</strong>';

			$forum_page['actions'] = '<span><a href="'.forum_link($forum_url['admin_users']).'?ip_stats='.$user_data['id'].'">'.$lang_admin['View IP stats'].'</a></span> <span><a href="'.forum_link($forum_url['search_user_posts'], $user_data['id']).'">'.$lang_admin['Show posts'].'</a></span>';

?>
					<tr>
						<td class="tcl"><?php echo '<strong><a href="'.forum_link($forum_url['user'], $user_data['id']).'">'.forum_htmlencode($user_data['username']).'</a></strong>' ?> <span class="usermail"><a href="mailto:<?php echo $user_data['email'] ?>"><?php echo $user_data['email'] ?></a></span> <?php if ($user_data['admin_note'] != '') echo '<span class="usernote">'.forum_htmlencode($user_data['admin_note']).'</span>' ?></td>
						<td class="tc2"><?php echo $user_title ?></td>
						<td class="tc3"><?php echo $user_data['num_posts'] ?></td>
<?php ($hook = get_hook('aus_find_user_table_contents_after_num_posts')) ? eval($hook) : null; ?>
						<td class="tcr actions"><?php echo $forum_page['actions'] ?></td>
<?php ($hook = get_hook('aus_find_user_table_contents_after_actions')) ? eval($hook) : null; ?>					<td class="tcmod"><input type="checkbox" name="users[<?php echo $user_data['id'] ?>]" value="1" /></td>
					</tr>
<?php

		}
	}
	else
		echo "\t\t\t\t\t".'<tr><td class="tcl" colspan="4">'.$lang_admin['No match'].'</td></tr>'."\n";

?>
				</tbody>
			</table>
<?php

// Setup control buttons
$forum_page['main_submit'] = array();

if ($forum_page['num_users'] > 0)
{
	if ($forum_user['g_id'] == FORUM_ADMIN || ($forum_user['g_moderator'] == '1' && $forum_user['g_mod_ban_users'] == '1'))
		$forum_page['main_submit']['ban'] = '<span class="submit"><input type="submit" name="ban_users" value="'.$lang_admin['Ban'].'" /></span>';

	if ($forum_user['g_id'] == FORUM_ADMIN)
	{
		$forum_page['main_submit']['deLete'] = '<span class="submit"><input type="submit" name="delete_users" value="'.$lang_admin['Delete'].'" /></span>';
		$forum_page['main_submit']['change_group'] = '<span class="submit"><input type="submit" name="change_group" value="'.$lang_admin['Change group'].'" /></span>';
	}
}

($hook = get_hook('aus_find_user_pre_moderation_buttons')) ? eval($hook) : null;

if (!empty($forum_page['main_submit']))
{

?>
			<p class="submitting">
				<?php echo implode("\n\t\t\t\t", $forum_page['main_submit'])."\n" ?>
			</p>
<?php

}

?>
		</form>
	</div>
</div>
<?php

	$tpl_temp = trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}


($hook = get_hook('aus_new_action')) ? eval($hook) : null;


// Setup form
$forum_page['set_count'] = $forum_page['fld_count'] = 0;
$forum_page['form_action'] = '';

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($lang_admin['Forum administration'], forum_link($forum_url['admin_index'])),
	$lang_admin['Searches']
);

($hook = get_hook('aus_search_form_pre_header_load')) ? eval($hook) : null;

define('FORUM_PAGE_SECTION', 'users');
define('FORUM_PAGE', 'admin-users');
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
			<h2><span><?php echo $lang_admin['User search head'] ?></span></h2>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_users']) ?>?action=find_user">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_users']).'?action=find_user') ?>" />
			</div>
<?php ($hook = get_hook('aus_search_pre_user_search_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_admin['User search legend'] ?></strong></legend>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Username'] ?></span><br />
						<span class="input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="username" size="25" maxlength="25" /></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['E-mail address'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[email]" size="30" maxlength="80" /></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Title'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[title]" size="30" maxlength="50" /></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Real name'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[realname]" size="30" maxlength="40" /></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Website'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[url]" size="35" maxlength="100" /></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label">Jabber</span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[jabber]" size="30" maxlength="80" /></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label">ICQ</span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[icq]" size="12" maxlength="12" /></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label">MSN Messenger</span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[msn]" size="30" maxlength="80" /></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label">AOL IM</span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[aim]" size="20" maxlength="20" /></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label">Yahoo! Messenger</span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[yahoo]" size="20" maxlength="20" /></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Location'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[location]" size="30" maxlength="30" /></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Signature'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[signature]" size="35" maxlength="512" /></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Admin note'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[admin_note]" size="30" maxlength="30" /></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['More posts than'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="posts_greater" size="5" maxlength="8" /></span>
						<span class="fld-extra"><?php echo $lang_admin['Number of posts'] ?></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Less posts than'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="posts_less" size="5" maxlength="8" /></span>
						<span class="fld-extra"><?php echo $lang_admin['Number of posts'] ?></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Last post after'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="last_post_after" size="24" maxlength="19" /></span>
 						<span class="fld-extra">(yyyy-mm-dd hh:mm:ss)</span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Last post before'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="last_post_before" size="24" maxlength="19" /></span>
						<span class="fld-extra">(yyyy-mm-dd hh:mm:ss)</span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Registered after'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="registered_after" size="24" maxlength="19" /></span>
						<span class="fld-extra">(yyyy-mm-dd hh:mm:ss)</span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Registered before'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="registered_before" size="24" maxlength="19" /></span>
						<span class="fld-extra">(yyyy-mm-dd hh:mm:ss)</span>
					</label>
				</div>
<?php ($hook = get_hook('aus_search_user_search_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php ($hook = get_hook('aus_search_pre_results_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_admin['User results legend'] ?></strong></legend>
				<div class="frm-fld select">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Order by'] ?></span><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="order_by">
							<option value="username" selected="selected"><?php echo strtolower($lang_admin['Username']) ?></option>
							<option value="email"><?php echo strtolower($lang_admin['E-mail']) ?></option>
							<option value="num_posts"><?php echo strtolower($lang_admin['Posts']) ?></option>
							<option value="last_post"><?php echo $lang_admin['Last post'] ?></option>
							<option value="registered"><?php echo $lang_admin['Registered'] ?></option>
						</select></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Sort order'] ?></span><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="direction">
							<option value="ASC" selected="selected"><?php echo $lang_admin['Ascending'] ?></option>
							<option value="DESC"><?php echo $lang_admin['Descending'] ?></option>
						</select></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['User group'] ?></span><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="user_group">
							<option value="all" selected="selected"><?php echo $lang_admin['All groups'] ?></option>
							<option value="<?php echo FORUM_UNVERIFIED ?>"><?php echo $lang_admin['Unverified users'] ?></option>
<?php

$query = array(
	'SELECT'	=> 'g.g_id, g.g_title',
	'FROM'		=> 'groups AS g',
	'WHERE'		=> 'g.g_id!='.FORUM_GUEST,
	'ORDER BY'	=> 'g.g_title'
);

($hook = get_hook('aus_qr_get_groups2')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
while ($cur_group = $forum_db->fetch_assoc($result))
	echo "\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.forum_htmlencode($cur_group['g_title']).'</option>'."\n";

?>
						</select></span>
					</label>
				</div>
<?php ($hook = get_hook('aus_search_results_fieldset_end')) ? eval($hook) : null; ?>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" class="button" name="find_user" value="<?php echo $lang_admin['Submit search'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

// Reset fieldset counter
$forum_page['set_count'] = 0;

?>

	<div class="main-content frm">
		<div class="frm-head">
			<h2><span><?php echo $lang_admin['IP search head'] ?></span></h2>
		</div>
		<form class="frm-form" method="get" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_users']) ?>">
<?php ($hook = get_hook('aus_search_pre_ip_search_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_admin['IP search legend'] ?></strong></legend>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['IP address'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="show_users" size="18" maxlength="15" /></span>
					</label>
				</div>
<?php ($hook = get_hook('aus_search_ip_search_fieldset_end')) ? eval($hook) : null; ?>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" value=" <?php echo $lang_admin['Submit search'] ?> " /></span>
			</div>
		</form>
	</div>

</div>
<?php

$tpl_temp = trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
