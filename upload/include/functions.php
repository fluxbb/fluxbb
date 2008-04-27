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


//
// Return all code blocks that hook into $hook_id
//
function get_hook($hook_id)
{
	global $pun_hooks;

	return !defined('PUN_DISABLE_HOOKS') && isset($pun_hooks[$hook_id]) ? implode("\n", $pun_hooks[$hook_id]) : false;
}


//
// Authenticates the provided username and password against the user database
// $user can be either a user ID (integer) or a username (string)
// $password can be either a plaintext password or a password hash including salt ($password_is_hash must be set accordingly)
//
function authenticate_user($user, $password, $password_is_hash = false)
{
	global $pun_db, $pun_user;

	($hook = get_hook('fn_authenticate_user_start')) ? eval($hook) : null;

	// Check if there's a user matching $user and $password
	$query = array(
		'SELECT'	=> 'u.*, g.*, o.logged, o.idle, o.csrf_token, o.prev_url',
		'FROM'		=> 'users AS u',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'groups AS g',
				'ON'			=> 'g.g_id=u.group_id'
			),
			array(
				'LEFT JOIN'		=> 'online AS o',
				'ON'			=> 'o.user_id=u.id'
			)
		)
	);

	// Are we looking for a user ID or a username?
	$query['WHERE'] = is_int($user) ? 'u.id='.intval($user) : 'u.username=\''.$pun_db->escape($user).'\'';

	($hook = get_hook('fn_qr_get_user')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	$pun_user = $pun_db->fetch_assoc($result);

	if (!isset($pun_user['id']) ||
		($password_is_hash && $password != $pun_user['password']) ||
		(!$password_is_hash && sha1($pun_user['salt'].sha1($password)) != $pun_user['password']))
		set_default_user();

	($hook = get_hook('fn_authenticate_user_end')) ? eval($hook) : null;
}


//
// Attempt to login with the user ID and password hash from the cookie
//
function cookie_login(&$pun_user)
{
	global $pun_db, $db_type, $pun_config, $cookie_name, $cookie_path, $cookie_domain, $cookie_secure, $pun_time_formats, $pun_date_formats;

	($hook = get_hook('fn_cookie_login_start')) ? eval($hook) : null;

	$now = time();
	$expire = $now + 31536000;	// The cookie expires after a year

	// We assume it's a guest
	$cookie = array('user_id' => 1, 'password_hash' => 'Guest');

	// If a cookie is set, we get the user_id and password hash from it
	if (isset($_COOKIE[$cookie_name]))
		@list($cookie['user_id'], $cookie['password_hash']) = @explode('|', base64_decode($_COOKIE[$cookie_name]));

	($hook = get_hook('fn_cookie_login_fetch_cookie')) ? eval($hook) : null;

	if (intval($cookie['user_id']) > 1)
	{
		authenticate_user(intval($cookie['user_id']), $cookie['password_hash'], true);

		// If we got back the default user, the login failed
		if ($pun_user['id'] == '1')
		{
			pun_setcookie($cookie_name, base64_encode('1|'.random_key(8, true)), $expire);
			return;
		}

		// Set a default language if the user selected language no longer exists
		if (!file_exists(PUN_ROOT.'lang/'.$pun_user['language'].'/common.php'))
			$pun_user['language'] = $pun_config['o_default_lang'];

		// Set a default style if the user selected style no longer exists
		if (!file_exists(PUN_ROOT.'style/'.$pun_user['style'].'/'.$pun_user['style'].'.php'))
			$pun_user['style'] = $pun_config['o_default_style'];

		if (!$pun_user['disp_topics'])
			$pun_user['disp_topics'] = $pun_config['o_disp_topics_default'];
		if (!$pun_user['disp_posts'])
			$pun_user['disp_posts'] = $pun_config['o_disp_posts_default'];

		if ($pun_user['save_pass'] == '0')
			$expire = 0;

		// Check user has a valid date and time format
		if (!isset($pun_time_formats[$pun_user['time_format']]))
			$pun_user['time_format'] = 0;
		if (!isset($pun_date_formats[$pun_user['date_format']]))
			$pun_user['date_format'] = 0;

		// Define this if you want this visit to affect the online list and the users last visit data
		if (!defined('PUN_QUIET_VISIT'))
		{
			// Update the online list
			if (!$pun_user['logged'])
			{
				$pun_user['logged'] = $now;
				$pun_user['csrf_token'] = random_key(40, false, true);
				$pun_user['prev_url'] = get_current_url();

				// REPLACE INTO avoids a user having two rows in the online table
				$query = array(
					'REPLACE'	=> 'user_id, ident, logged, csrf_token, prev_url',
					'INTO'		=> 'online',
					'VALUES'	=> $pun_user['id'].', \''.$pun_db->escape($pun_user['username']).'\', '.$pun_user['logged'].', \''.$pun_user['csrf_token'].'\', \''.$pun_db->escape($pun_user['prev_url']).'\'',
					'UNIQUE'	=> 'user_id='.$pun_user['id']
				);
				($hook = get_hook('fn_qr_add_online_user')) ? eval($hook) : null;
				$pun_db->query_build($query) or error(__FILE__, __LINE__);

				// Reset tracked topics
				set_tracked_topics(null);
			}
			else
			{
				// Special case: We've timed out, but no other user has browsed the forums since we timed out
				if ($pun_user['logged'] < ($now-$pun_config['o_timeout_visit']))
				{
					$query = array(
						'UPDATE'	=> 'users',
						'SET'		=> 'last_visit='.$pun_user['logged'],
						'WHERE'		=> 'id='.$pun_user['id']
					);

					($hook = get_hook('fn_qr_update_user_visit')) ? eval($hook) : null;
					$pun_db->query_build($query) or error(__FILE__, __LINE__);

					$pun_user['last_visit'] = $pun_user['logged'];
				}

				// Now update the logged time and save the current URL in the online list
				$query = array(
					'UPDATE'	=> 'online',
					'SET'		=> 'logged='.$now.', prev_url=\''.$pun_db->escape(get_current_url()).'\'',
					'WHERE'		=> 'user_id='.$pun_user['id']
				);

				if ($pun_user['idle'] == '1')
					$query['SET'] .= ', idle=0';

				($hook = get_hook('fn_qr_update_online_user')) ? eval($hook) : null;
				$pun_db->query_build($query) or error(__FILE__, __LINE__);

				// Update tracked topics with the current expire time
				if (isset($_COOKIE[$cookie_name.'_track']))
					pun_setcookie($cookie_name.'_track', $_COOKIE[$cookie_name.'_track'], time() + $pun_config['o_timeout_visit']);
			}
		}

		$pun_user['is_guest'] = false;
		$pun_user['is_admmod'] = $pun_user['g_id'] == PUN_ADMIN || $pun_user['g_moderator'] == '1';
	}
	else
		set_default_user();

	($hook = get_hook('fn_cookie_login_end')) ? eval($hook) : null;
}


//
// Fill $pun_user with default values (for guests)
//
function set_default_user()
{
	global $pun_db, $db_type, $pun_user, $pun_config;

	($hook = get_hook('fn_set_default_user_start')) ? eval($hook) : null;

	$remote_addr = get_remote_address();

	// Fetch guest user
	$query = array(
		'SELECT'	=> 'u.*, g.*, o.logged, o.csrf_token, o.prev_url',
		'FROM'		=> 'users AS u',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'groups AS g',
				'ON'			=> 'g.g_id=u.group_id'
			),
			array(
				'LEFT JOIN'		=> 'online AS o',
				'ON'			=> 'o.ident=\''.$remote_addr.'\''
			)
		),
		'WHERE'		=> 'u.id=1'
	);

	($hook = get_hook('fn_qr_get_default_user')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	if (!$pun_db->num_rows($result))
		exit('Unable to fetch guest information. The table \''.$pun_db->prefix.'users\' must contain an entry with id = 1 that represents anonymous users.');

	$pun_user = $pun_db->fetch_assoc($result);

	// Update online list
	if (!$pun_user['logged'])
	{
		$pun_user['logged'] = time();
		$pun_user['csrf_token'] = random_key(40, false, true);
		$pun_user['prev_url'] = get_current_url();

		// REPLACE INTO avoids a user having two rows in the online table
		$query = array(
			'REPLACE'	=> 'user_id, ident, logged, csrf_token, prev_url',
			'INTO'		=> 'online',
			'VALUES'	=> '1, \''.$pun_db->escape($remote_addr).'\', '.$pun_user['logged'].', \''.$pun_user['csrf_token'].'\', \''.$pun_db->escape($pun_user['prev_url']).'\'',
			'UNIQUE'	=> 'user_id=1 AND ident=\''.$pun_db->escape($remote_addr).'\''
		);
		($hook = get_hook('fn_qr_add_online_guest_user')) ? eval($hook) : null;
		$pun_db->query_build($query) or error(__FILE__, __LINE__);
	}
	else
	{
		$query = array(
			'UPDATE'	=> 'online',
			'SET'		=> 'logged='.time().', prev_url=\''.$pun_db->escape(get_current_url()).'\'',
			'WHERE'		=> 'ident=\''.$pun_db->escape($remote_addr).'\''
		);

		($hook = get_hook('fn_qr_update_online_guest_user')) ? eval($hook) : null;
		$pun_db->query_build($query) or error(__FILE__, __LINE__);
	}

	$pun_user['disp_topics'] = $pun_config['o_disp_topics_default'];
	$pun_user['disp_posts'] = $pun_config['o_disp_posts_default'];
	$pun_user['timezone'] = $pun_config['o_default_timezone'];
	$pun_user['language'] = $pun_config['o_default_lang'];
	$pun_user['style'] = $pun_config['o_default_style'];
	$pun_user['is_guest'] = true;
	$pun_user['is_admmod'] = false;
}


//
// Set a cookie, PunBB style!
//
function pun_setcookie($name, $value, $expire)
{
	global $cookie_path, $cookie_domain, $cookie_secure;

	($hook = get_hook('fn_pun_setcookie_start')) ? eval($hook) : null;

	// Enable sending of a P3P header by removing // from the following line (try this if login is failing in IE6)
//	@header('P3P: CP="CUR ADM"');

	if (version_compare(PHP_VERSION, '5.2.0', '>='))
		setcookie($name, $value, $expire, $cookie_path, $cookie_domain, $cookie_secure, true);
	else
		setcookie($name, $value, $expire, $cookie_path.'; HttpOnly', $cookie_domain, $cookie_secure);
}


//
// Check whether the connecting user is banned (and delete any expired bans while we're at it)
//
function check_bans()
{
	global $pun_db, $pun_config, $lang_common, $pun_user, $pun_bans;

	($hook = get_hook('fn_check_bans_start')) ? eval($hook) : null;

	// Admins aren't affected
	if (defined('PUN_ADMIN') && $pun_user['g_id'] == PUN_ADMIN || !$pun_bans)
		return;

	// Add a dot at the end of the IP address to prevent banned address 192.168.0.5 from matching e.g. 192.168.0.50
	$user_ip = get_remote_address().'.';
	$bans_altered = false;

	foreach ($pun_bans as $cur_ban)
	{
		// Has this ban expired?
		if ($cur_ban['expire'] != '' && $cur_ban['expire'] <= time())
		{
			$query = array(
				'DELETE'	=> 'bans',
				'WHERE'		=> 'id='.$cur_ban['id']
			);

			($hook = get_hook('fn_qr_delete_expired_ban')) ? eval($hook) : null;
			$pun_db->query_build($query) or error(__FILE__, __LINE__);

			$bans_altered = true;
			continue;
		}

		if ($cur_ban['username'] != '' && strtolower($pun_user['username']) == strtolower($cur_ban['username']))
		{
			$query = array(
				'DELETE'	=> 'online',
				'WHERE'		=> 'ident=\''.$pun_db->escape($pun_user['username']).'\''
			);

			($hook = get_hook('fn_qr_delete_online_user')) ? eval($hook) : null;
			$pun_db->query_build($query) or error(__FILE__, __LINE__);

			message($lang_common['Ban message'].(($cur_ban['expire'] != '') ? ' '.sprintf($lang_common['Ban message 2'], strtolower(format_time($cur_ban['expire'], true))) : '').(($cur_ban['message'] != '') ? ' '.$lang_common['Ban message 3'].'</p><p><strong>'.pun_htmlencode($cur_ban['message']).'</strong></p>' : '</p>').'<p>'.sprintf($lang_common['Ban message 4'], '<a href="mailto:'.$pun_config['o_admin_email'].'">'.$pun_config['o_admin_email'].'</a>'));
		}

		if ($cur_ban['ip'] != '')
		{
			$cur_ban_ips = explode(' ', $cur_ban['ip']);

			$num_ips = count($cur_ban_ips);
			for ($i = 0; $i < $num_ips; ++$i)
			{
				$cur_ban_ips[$i] = $cur_ban_ips[$i].'.';

				if (substr($user_ip, 0, strlen($cur_ban_ips[$i])) == $cur_ban_ips[$i])
				{
					$query = array(
						'DELETE'	=> 'online',
						'WHERE'		=> 'ident=\''.$pun_db->escape($pun_user['username']).'\''
					);

					($hook = get_hook('fn_qr_delete_online_user2')) ? eval($hook) : null;
					$pun_db->query_build($query) or error(__FILE__, __LINE__);

					message($lang_common['Ban message'].(($cur_ban['expire'] != '') ? ' '.sprintf($lang_common['Ban message 2'], strtolower(format_time($cur_ban['expire'], true))) : '').(($cur_ban['message'] != '') ? ' '.$lang_common['Ban message 3'].'</p><p><strong>'.pun_htmlencode($cur_ban['message']).'</strong></p>' : '</p>').'<p>'.sprintf($lang_common['Ban message 4'], '<a href="mailto:'.$pun_config['o_admin_email'].'">'.$pun_config['o_admin_email'].'</a>'));
				}
			}
		}
	}

	// If we removed any expired bans during our run-through, we need to regenerate the bans cache
	if ($bans_altered)
	{
		require_once PUN_ROOT.'include/cache.php';
		generate_bans_cache();
	}
}


//
// Update "Users online"
//
function update_users_online()
{
	global $pun_db, $pun_config, $pun_user;

	$now = time();

	($hook = get_hook('fn_update_users_online_start')) ? eval($hook) : null;

	// Fetch all online list entries that are older than "o_timeout_online"
	$query = array(
		'SELECT'	=> 'o.*',
		'FROM'		=> 'online AS o',
		'WHERE'		=> 'o.logged<'.($now-$pun_config['o_timeout_online'])
	);

	($hook = get_hook('fn_qr_get_old_online_users')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	while ($cur_user = $pun_db->fetch_assoc($result))
	{
		// If the entry is a guest, delete it
		if ($cur_user['user_id'] == '1')
		{
			$query = array(
				'DELETE'	=> 'online',
				'WHERE'		=> 'ident=\''.$pun_db->escape($cur_user['ident']).'\''
			);

			($hook = get_hook('fn_qr_delete_online_guest_user')) ? eval($hook) : null;
			$pun_db->query_build($query) or error(__FILE__, __LINE__);
		}
		else
		{
			// If the entry is older than "o_timeout_visit", update last_visit for the user in question, then delete him/her from the online list
			if ($cur_user['logged'] < ($now-$pun_config['o_timeout_visit']))
			{
				$query = array(
					'UPDATE'	=> 'users',
					'SET'		=> 'last_visit='.$cur_user['logged'],
					'WHERE'		=> 'id='.$cur_user['user_id']
				);

				($hook = get_hook('fn_qr_update_user_visit2')) ? eval($hook) : null;
				$pun_db->query_build($query) or error(__FILE__, __LINE__);

				$query = array(
					'DELETE'	=> 'online',
					'WHERE'		=> 'user_id='.$cur_user['user_id']
				);

				($hook = get_hook('fn_qr_delete_online_user3')) ? eval($hook) : null;
				$pun_db->query_build($query) or error(__FILE__, __LINE__);
			}
			else if ($cur_user['idle'] == '0')
			{
				$query = array(
					'UPDATE'	=> 'online',
					'SET'		=> 'idle=1',
					'WHERE'		=> 'user_id='.$cur_user['user_id']
				);

				($hook = get_hook('fn_qr_update_online_user2')) ? eval($hook) : null;
				$pun_db->query_build($query) or error(__FILE__, __LINE__);
			}
		}
	}

	($hook = get_hook('fn_update_users_online_end')) ? eval($hook) : null;
}


//
// Generate the "navigator" that appears at the top of every page
//
function generate_navlinks()
{
	global $pun_config, $lang_common, $pun_url, $pun_user;

	// Index should always be displayed
	$links[] = '<li id="navindex"'.((PUN_PAGE == 'index') ? ' class="isactive"' : '').'><a href="'.pun_link($pun_url['index']).'"><span>'.$lang_common['Index'].'</span></a></li>';

	if ($pun_user['g_read_board'] == '1' && $pun_user['g_view_users'] == '1')
		$links[] = '<li id="navuserlist"'.((PUN_PAGE == 'userlist') ? ' class="isactive"' : '').'><a href="'.pun_link($pun_url['users']).'"><span>'.$lang_common['User list'].'</span></a></li>';

	if ($pun_config['o_rules'] == '1' && (!$pun_user['is_guest'] || $pun_user['g_read_board'] == '1' || $pun_config['o_regs_allow'] == '1'))
		$links[] = '<li id="navrules"'.((PUN_PAGE == 'rules') ? ' class="isactive"' : '').'><a href="'.pun_link($pun_url['rules']).'"><span>'.$lang_common['Rules'].'</span></a></li>';

	if ($pun_user['is_guest'])
	{
		if ($pun_user['g_read_board'] == '1' && $pun_user['g_search'] == '1')
			$links[] = '<li id="navsearch"'.((PUN_PAGE == 'search') ? ' class="isactive"' : '').'><a href="'.pun_link($pun_url['search']).'"><span>'.$lang_common['Search'].'</span></a></li>';

		$links[] = '<li id="navregister"'.((PUN_PAGE == 'register') ? ' class="isactive"' : '').'><a href="'.pun_link($pun_url['register']).'"><span>'.$lang_common['Register'].'</span></a></li>';
		$links[] = '<li id="navlogin"'.((PUN_PAGE == 'login') ? ' class="isactive"' : '').'><a href="'.pun_link($pun_url['login']).'"><span>'.$lang_common['Login'].'</span></a></li>';
	}
	else
	{
		if (!$pun_user['is_admmod'])
		{
			if ($pun_user['g_read_board'] == '1' && $pun_user['g_search'] == '1')
				$links[] = '<li id="navsearch"'.((PUN_PAGE == 'search') ? ' class="isactive"' : '').'><a href="'.pun_link($pun_url['search']).'"><span>'.$lang_common['Search'].'</span></a></li>';

			$links[] = '<li id="navprofile"'.((substr(PUN_PAGE, 0, 7) == 'profile') ? ' class="isactive"' : '').'><a href="'.pun_link($pun_url['user'], $pun_user['id']).'"><span>'.$lang_common['Profile'].'</span></a></li>';
		}
		else
		{
			$links[] = '<li id="navsearch"'.((PUN_PAGE == 'search') ? ' class="isactive"' : '').'><a href="'.pun_link($pun_url['search']).'"><span>'.$lang_common['Search'].'</span></a></li>';
			$links[] = '<li id="navprofile"'.((PUN_PAGE == 'editprofile' || PUN_PAGE == 'viewprofile') ? ' class="isactive"' : '').'><a href="'.pun_link($pun_url['user'], $pun_user['id']).'"><span>'.$lang_common['Profile'].'</span></a></li>';
			$links[] = '<li id="navadmin"'.((substr(PUN_PAGE, 0, 5) == 'admin') ? ' class="isactive"' : '').'><a href="'.pun_link($pun_url['admin_index']).'"><span>'.$lang_common['Admin'].'</span></a></li>';
		}

		$links[] = '<li id="navlogout"><a href="'.pun_link($pun_url['logout'], array($pun_user['id'], generate_form_token('logout'.$pun_user['id']))).'"><span>'.$lang_common['Logout'].'</span></a></li>';
	}

	// Are there any additional navlinks we should insert into the array before imploding it?
	if ($pun_config['o_additional_navlinks'] != '')
	{
		if (preg_match_all('#([0-9]+)\s*=\s*(.*?)\n#s', $pun_config['o_additional_navlinks']."\n", $extra_links))
		{
			// Insert any additional links into the $links array (at the correct index)
			$num_links = count($extra_links[1]);
			for ($i = 0; $i < $num_links; ++$i)
				array_insert($links, $extra_links[1][$i], '<li id="navextra'.($i + 1).'">'.$extra_links[2][$i].'</li>');
		}
	}

	($hook = get_hook('fn_generate_navlinks_end')) ? eval($hook) : null;

	return implode("\n\t\t", $links);
}


//
// Display the profile navigation menu
//
function generate_profile_menu()
{
	global $lang_profile, $pun_url, $pun_config, $pun_user, $id;

	// Setup links for profile menu
	$profilenav_links = array(
		'<li'.((PUN_PAGE == 'profile-about')  ? ' class="topactive">' : '>').'<a href="'.pun_link($pun_url['profile_about'], $id).'"><span>'.$lang_profile['Section about'].'</span></a></li>',
		'<li'.((PUN_PAGE == 'profile-identity')  ? ' class="topactive">' : '>').'<a href="'.pun_link($pun_url['profile_identity'], $id).'"><span>'.$lang_profile['Section identity'].'</span></a></li>',
		'<li'.((PUN_PAGE == 'profile-settings') ? ' class="topactive">' : '>').'<a href="'.pun_link($pun_url['profile_settings'], $id).'"><span>'.$lang_profile['Section settings'].'</span></a></li>',
	);

	if ($pun_config['o_signatures'] == '1')
		$profilenav_links[] = '<li'.((PUN_PAGE == 'profile-signature') ? ' class="topactive">' : '>').'<a href="'.pun_link($pun_url['profile_signature'], $id).'"><span>'.$lang_profile['Section signature'].'</span></a></li>';

	if ($pun_config['o_avatars'] == '1')
		$profilenav_links[] = '<li'.((PUN_PAGE == 'profile-avatar') ? ' class="topactive">' : '>').'<a href="'.pun_link($pun_url['profile_avatar'], $id).'"><span>'.$lang_profile['Section avatar'].'</span></a></li>';

	if ($pun_user['g_id'] == PUN_ADMIN || ($pun_user['g_moderator'] == '1' && $pun_user['g_mod_ban_users'] == '1'))
		$profilenav_links[] = '<li'.((PUN_PAGE == 'profile-admin') ? ' class="topactive">' : '>').'<a href="'.pun_link($pun_url['profile_admin'], $id).'"><span>'.$lang_profile['Section admin'].'</span></a></li>';

	($hook = get_hook('fn_generate_profile_menu_end')) ? eval($hook) : null;

?>
	<div id="profilenav" class="main-nav">
		<ul>
			<?php echo implode("\n\t\t\t", $profilenav_links)."\n"; ?>
		</ul>
	</div>
<?php

}


//
// Generate breadcrumb navigation
//
function generate_crumbs($reverse)
{
	global $lang_common, $pun_url, $pun_config, $pun_page;

	($hook = get_hook('fn_generate_crumbs_start')) ? eval($hook) : null;

	if (empty($pun_page['crumbs']))
		$pun_page['crumbs'][0] = $pun_config['o_board_title'];

	$crumbs = '';
	$num_crumbs = count($pun_page['crumbs']);

	if ($reverse)
	{
		for ($i = ($num_crumbs - 1); $i >= 0; --$i)
			$crumbs .= (is_array($pun_page['crumbs'][$i]) ? pun_htmlencode($pun_page['crumbs'][$i][0]) : pun_htmlencode($pun_page['crumbs'][$i])).((isset($pun_page['page']) && $i == ($num_crumbs - 1)) ? ' ('.$lang_common['Page'].' '.$pun_page['page'].')' : '').($i > 0 ? $lang_common['Title separator'] : '');
	}
	else
	{
		for ($i = 0; $i < $num_crumbs; ++$i)
		{
			if ($i < ($num_crumbs - 1))
				$crumbs .= '<span class="crumb'.(($i == 0) ? ' crumbfirst' : '').'">'.(($i >= 1) ? '<span>'.$lang_common['Crumb separator'].'</span>' : '').(is_array($pun_page['crumbs'][$i]) ? '<a href="'.$pun_page['crumbs'][$i][1].'">'.pun_htmlencode($pun_page['crumbs'][$i][0]).'</a>' : pun_htmlencode($pun_page['crumbs'][$i])).'</span> ';
			else
				$crumbs .= '<span class="crumb crumblast'.(($i == 0) ? ' crumbfirst' : '').'">'.(($i >= 1) ? '<span>'.$lang_common['Crumb separator'].'</span>' : '').(is_array($pun_page['crumbs'][$i]) ? '<a href="'.$pun_page['crumbs'][$i][1].'">'.pun_htmlencode($pun_page['crumbs'][$i][0]).'</a>' : pun_htmlencode($pun_page['crumbs'][$i])).'</span> ';
		}
	}

	($hook = get_hook('fn_generate_crumbs_end')) ? eval($hook) : null;

	return $crumbs;
}


//
// Save array of tracked topics in cookie
//
function set_tracked_topics($tracked_topics)
{
	global $cookie_name, $cookie_path, $cookie_domain, $cookie_secure, $pun_config;

	($hook = get_hook('fn_set_tracked_topics_start')) ? eval($hook) : null;

	$cookie_data = '';
	if (!empty($tracked_topics))
	{
		// Sort the arrays (latest read first)
		arsort($tracked_topics['topics'], SORT_NUMERIC);
		arsort($tracked_topics['forums'], SORT_NUMERIC);

		// Homebrew serialization (to avoid having to run unserialize() on cookie data)
		foreach ($tracked_topics['topics'] as $id => $timestamp)
			$cookie_data .= 't'.$id.'='.$timestamp.';';
		foreach ($tracked_topics['forums'] as $id => $timestamp)
			$cookie_data .= 'f'.$id.'='.$timestamp.';';

		// Enforce a 4048 byte size limit (4096 minus some space for the cookie name)
		if (strlen($cookie_data) > 4048)
		{
			$cookie_data = substr($cookie_data, 0, 4048);
			$cookie_data = substr($cookie_data, 0, strrpos($cookie_data, ';')).';';
		}
	}

	pun_setcookie($cookie_name.'_track', $cookie_data, time() + $pun_config['o_timeout_visit']);
	$_COOKIE[$cookie_name.'_track'] = $cookie_data;	// Set it directly in $_COOKIE as well
}


//
// Extract array of tracked topics from cookie
//
function get_tracked_topics()
{
	global $cookie_name;

	$cookie_data = isset($_COOKIE[$cookie_name.'_track']) ? $_COOKIE[$cookie_name.'_track'] : false;
	if (!$cookie_data)
		return array('topics' => array(), 'forums' => array());

	if (strlen($cookie_data) > 4048)
		return array('topics' => array(), 'forums' => array());

	// Unserialize data from cookie
	$tracked_topics = array('topics' => array(), 'forums' => array());
	$temp = explode(';', $cookie_data);
	foreach ($temp as $t)
	{
		$type = substr($t, 0, 1) == 'f' ? 'forums' : 'topics';
		$id = intval(substr($t, 1));
		$timestamp = intval(@substr($t, strpos($t, '=') + 1));
		if ($id > 0 && $timestamp > 0)
			$tracked_topics[$type][$id] = $timestamp;
	}

	($hook = get_hook('fn_get_tracked_topics_end')) ? eval($hook) : null;

	return $tracked_topics;
}


//
// Update posts, topics, last_post, last_post_id and last_poster for a forum
//
function sync_forum($forum_id)
{
	global $pun_db;

	($hook = get_hook('fn_sync_forum_start')) ? eval($hook) : null;

	// Get topic and post count for forum
	$query = array(
		'SELECT'	=> 'COUNT(t.id), SUM(t.num_replies)',
		'FROM'		=> 'topics AS t',
		'WHERE'		=> 't.forum_id='.$forum_id
	);

	($hook = get_hook('fn_qr_get_forum_stats')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	list($num_topics, $num_posts) = $pun_db->fetch_row($result);

	$num_posts = $num_posts + $num_topics;		// $num_posts is only the sum of all replies (we have to add the topic posts)

	// Get last_post, last_post_id and last_poster for forum (if any)
	$query = array(
		'SELECT'	=> 't.last_post, t.last_post_id, t.last_poster',
		'FROM'		=> 'topics AS t',
		'WHERE'		=> 't.forum_id='.$forum_id.' AND t.moved_to is NULL',
		'ORDER BY'	=> 't.last_post DESC',
		'LIMIT'		=> '1'
	);

	($hook = get_hook('fn_qr_get_forum_last_post_data')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	if ($pun_db->num_rows($result))
	{
		list($last_post, $last_post_id, $last_poster) = $pun_db->fetch_row($result);
		$last_poster = '\''.$pun_db->escape($last_poster).'\'';
	}
	else
		$last_post = $last_post_id = $last_poster = 'NULL';

	// Now update the forum
	$query = array(
		'UPDATE'	=> 'forums',
		'SET'		=> 'num_topics='.$num_topics.', num_posts='.$num_posts.', last_post='.$last_post.', last_post_id='.$last_post_id.', last_poster='.$last_poster,
		'WHERE'		=> 'id='.$forum_id
	);

	($hook = get_hook('fn_qr_update_forum')) ? eval($hook) : null;
	$pun_db->query_build($query) or error(__FILE__, __LINE__);
}


//
// Update replies, last_post, last_post_id and last_poster for a topic
//
function sync_topic($topic_id)
{
	global $pun_db;

	($hook = get_hook('fn_sync_topic_start')) ? eval($hook) : null;

	// Count number of replies in the topic
	$query = array(
		'SELECT'	=> 'COUNT(p.id)',
		'FROM'		=> 'posts AS p',
		'WHERE'		=> 'p.topic_id='.$topic_id
	);

	($hook = get_hook('fn_qr_get_topic_reply_count')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	$num_replies = $pun_db->result($result, 0) - 1;

	// Get last_post, last_post_id and last_poster
	$query = array(
		'SELECT'	=> 'p.posted, p.id, p.poster',
		'FROM'		=> 'posts AS p',
		'WHERE'		=> 'p.topic_id='.$topic_id,
		'ORDER BY'	=> 'p.id DESC',
		'LIMIT'		=> '1'
	);

	($hook = get_hook('fn_qr_get_topic_last_post_data')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	list($last_post, $last_post_id, $last_poster) = $pun_db->fetch_row($result);

	// Now update the topic
	$query = array(
		'UPDATE'	=> 'topics',
		'SET'		=> 'num_replies='.$num_replies.', last_post='.$last_post.', last_post_id='.$last_post_id.', last_poster=\''.$pun_db->escape($last_poster).'\'',
		'WHERE'		=> 'id='.$topic_id
	);

	($hook = get_hook('fn_qr_update_topic')) ? eval($hook) : null;
	$pun_db->query_build($query) or error(__FILE__, __LINE__);
}


//
// Verifies that the provided username is OK for insertion into the database
//
function validate_username($username, $exclude_id = null)
{
	global $lang_common, $lang_register, $lang_profile, $pun_config;

	$errors = array();

	($hook = get_hook('fn_validate_username_start')) ? eval($hook) : null;

	// Convert multiple whitespace characters into one (to prevent people from registering with indistinguishable usernames)
	$username = preg_replace('#\s+#s', ' ', $username);

	// Validate username
	if (pun_strlen($username) < 2)
		$errors[] = $lang_profile['Username too short'];
	else if (pun_strlen($username) > 25)
		$errors[] = $lang_profile['Username too long'];
	else if (strtolower($username) == 'guest' || strtolower($username) == strtolower($lang_common['Guest']))
		$errors[] = $lang_profile['Username guest'];
	else if (preg_match('/[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}/', $username))
		$errors[] = $lang_profile['Username IP'];
	else if ((strpos($username, '[') !== false || strpos($username, ']') !== false) && strpos($username, '\'') !== false && strpos($username, '"') !== false)
		$errors[] = $lang_profile['Username reserved chars'];
	else if (preg_match('#\[b\]|\[/b\]|\[u\]|\[/u\]|\[i\]|\[/i\]|\[color|\[/color\]|\[quote\]|\[quote=|\[/quote\]|\[code\]|\[/code\]|\[img\]|\[/img\]|\[url|\[/url\]|\[email|\[/email\]#i', $username))
		$errors[] = $lang_profile['Username BBCode'];

	// Check username for any censored words
	if ($pun_config['o_censoring'] == '1' && censor_words($username) != $username)
		$errors[] = $lang_profile['Username censor'];

	// Check for username dupe
	$dupe = check_username_dupe($username, $exclude_id);
	if ($dupe !== false)
		$errors[] = sprintf($lang_profile['Username dupe'], pun_htmlencode($dupe));

	return $errors;
}


//
// Adds a new user. The username must be passed through validate_username() first.
//
function add_user($user_info, &$new_uid)
{
	global $pun_db, $base_url, $lang_common, $pun_config, $pun_user, $pun_url;

	($hook = get_hook('fn_add_user_start')) ? eval($hook) : null;

	// Add the user
	$query = array(
		'INSERT'	=> 'username, group_id, password, email, email_setting, save_pass, timezone, dst, language, style, registered, registration_ip, last_visit, salt, activate_key',
		'INTO'		=> 'users',
		'VALUES'	=> '\''.$pun_db->escape($user_info['username']).'\', '.$user_info['group_id'].', \''.$pun_db->escape($user_info['password_hash']).'\', \''.$pun_db->escape($user_info['email']).'\', '.$user_info['email_setting'].', '.$user_info['save_pass'].', '.floatval($user_info['timezone']).', '.$user_info['dst'].', \''.$pun_db->escape($user_info['language']).'\', \''.$pun_db->escape($user_info['style']).'\', '.$user_info['registered'].', \''.$pun_db->escape($user_info['registration_ip']).'\', '.$user_info['registered'].', \''.$pun_db->escape($user_info['salt']).'\', '.$user_info['activate_key'].''
	);

	($hook = get_hook('fn_qr_add_user')) ? eval($hook) : null;
	$pun_db->query_build($query) or error(__FILE__, __LINE__);
	$new_uid = $pun_db->insert_id();

	// Must the user verify the registration?
	if ($user_info['require_verification'])
	{
		// Load the "welcome" template
		$mail_tpl = trim(file_get_contents(PUN_ROOT.'lang/'.$pun_user['language'].'/mail_templates/welcome.tpl'));

		// The first row contains the subject
		$first_crlf = strpos($mail_tpl, "\n");
		$mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
		$mail_message = trim(substr($mail_tpl, $first_crlf));

		$mail_subject = str_replace('<board_title>', $pun_config['o_board_title'], $mail_subject);
		$mail_message = str_replace('<base_url>', $base_url.'/', $mail_message);
		$mail_message = str_replace('<username>', $user_info['username'], $mail_message);
		$mail_message = str_replace('<activation_url>', str_replace('&amp;', '&', pun_link($pun_url['change_password_key'], array($new_uid, substr($user_info['activate_key'], 1, -1)))), $mail_message);
		$mail_message = str_replace('<board_mailer>', sprintf($lang_common['Forum mailer'], $pun_config['o_board_title']), $mail_message);

		($hook = get_hook('fn_add_user_send_verification')) ? eval($hook) : null;

		pun_mail($user_info['email'], $mail_subject, $mail_message);
	}

	// Should we alert people on the admin mailing list that a new user has registered?
	if ($user_info['notify_admins'] && $pun_config['o_mailing_list'] != '')
	{
		$mail_subject = 'Alert - New registration';
		$mail_message = 'User \''.$user_info['username'].'\' registered in the forums at '.$base_url.'/'."\n\n".'User profile: '.pun_link($pun_url['user'], $new_uid)."\n\n".'-- '."\n".'Forum Mailer'."\n".'(Do not reply to this message)';

		pun_mail($pun_config['o_mailing_list'], $mail_subject, $mail_message);
	}

	($hook = get_hook('fn_add_user_end')) ? eval($hook) : null;
}


//
// Delete a user and all information associated with it
//
function delete_user($user_id)
{
	global $pun_db, $db_type, $pun_config;

	($hook = get_hook('fn_delete_user_start')) ? eval($hook) : null;

	// First we need to get some data on the user
	$query = array(
		'SELECT'	=> 'u.username, u.group_id, g.g_moderator',
		'FROM'		=> 'users AS u',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'groups AS g',
				'ON'			=> 'g.g_id=u.group_id'
			)
		),
		'WHERE'		=> 'u.id='.$user_id
	);

	($hook = get_hook('fn_qr_get_user_data')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	$user = $pun_db->fetch_assoc($result);

	// Delete any subscriptions
	$query = array(
		'DELETE'	=> 'subscriptions',
		'WHERE'		=> 'user_id='.$user_id
	);

	($hook = get_hook('fn_qr_delete_subscriptions')) ? eval($hook) : null;
	$pun_db->query_build($query) or error(__FILE__, __LINE__);

	// Remove him/her from the online list (if they happen to be logged in)
	$query = array(
		'DELETE'	=> 'online',
		'WHERE'		=> 'user_id='.$user_id
	);

	($hook = get_hook('fn_qr_delete_user_delete_online')) ? eval($hook) : null;
	$pun_db->query_build($query) or error(__FILE__, __LINE__);

	// Should we delete all posts made by this user?
	if (isset($_POST['delete_posts']))
	{
		@set_time_limit(0);

		// Find all posts made by this user
		$query = array(
			'SELECT'	=> 'p.id, p.topic_id, t.forum_id, t.first_post_id',
			'FROM'		=> 'posts AS p',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'topics AS t',
					'ON'			=> 't.id=p.topic_id'
				)
			),
			'WHERE'		=> 'p.poster_id='.$user_id
		);

		($hook = get_hook('fn_qr_get_user_posts')) ? eval($hook) : null;
		$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
		while ($cur_post = $pun_db->fetch_assoc($result))
		{
			if ($cur_post['first_post_id'] == $cur_post['id'])
				delete_topic($cur_post['topic_id'], $cur_post['forum_id']);
			else
				delete_post($cur_post['id'], $cur_post['topic_id'], $cur_post['forum_id']);
		}
	}
	else
	{
		// Set all his/her posts to guest
		$query = array(
			'UPDATE'	=> 'posts',
			'SET'		=> 'poster_id=1',
			'WHERE'		=> 'poster_id='.$user_id
		);

		($hook = get_hook('fn_qr_reset_user_posts')) ? eval($hook) : null;
		$pun_db->query_build($query) or error(__FILE__, __LINE__);
	}

	// Delete the user
	$query = array(
		'DELETE'	=> 'users',
		'WHERE'		=> 'id='.$user_id
	);

	($hook = get_hook('fn_qr_delete_user')) ? eval($hook) : null;
	$pun_db->query_build($query) or error(__FILE__, __LINE__);

	// Delete user avatar
	if (file_exists($pun_config['o_avatars_dir'].'/'.$user_id.'.gif'))
		@unlink($pun_config['o_avatars_dir'].'/'.$user_id.'.gif');
	if (file_exists($pun_config['o_avatars_dir'].'/'.$user_id.'.jpg'))
		@unlink($pun_config['o_avatars_dir'].'/'.$user_id.'.jpg');
	if (file_exists($pun_config['o_avatars_dir'].'/'.$user_id.'.png'))
		@unlink($pun_config['o_avatars_dir'].'/'.$user_id.'.png');

	// If the user is a moderator or an administrator, we remove him/her from the moderator list in all forums
	// and regenerate the bans cache (in case he/she created any bans)
	if ($user['group_id'] == PUN_ADMIN || $user['g_moderator'] == '1')
	{
		clean_forum_moderators();

		// Regenerate the bans cache
		require_once PUN_ROOT.'include/cache.php';
		generate_bans_cache();
	}

	($hook = get_hook('fn_delete_user_end')) ? eval($hook) : null;
}


//
// Iterates through all forum moderator lists and removes any erroneous entries
//
function clean_forum_moderators()
{
	global $pun_db;

	($hook = get_hook('fn_clean_forum_moderators_start')) ? eval($hook) : null;

	// Get a list of forums and their respective lists of moderators
	$query = array(
		'SELECT'	=> 'f.id, f.moderators',
		'FROM'		=> 'forums AS f',
		'WHERE'		=> 'f.moderators IS NOT NULL'
	);

	($hook = get_hook('fn_qr_get_forum_moderators')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);

	while ($cur_forum = $pun_db->fetch_assoc($result))
	{
		$cur_moderators = unserialize($cur_forum['moderators']);
		$new_moderators = $cur_moderators;

		// Iterate through each user in the list and check if he/she is in a moderator or admin group
		foreach ($cur_moderators as $username => $user_id)
		{
			$query = array(
				'SELECT'	=> '1',
				'FROM'		=> 'users AS u',
				'JOINS'		=> array(
					array(
						'INNER JOIN'	=> 'groups AS g',
						'ON'			=> 'g.g_id=u.group_id'
					)
				),
				'WHERE'		=> '(g.g_moderator=1 OR u.group_id=1) AND u.id='.$user_id
			);

			($hook = get_hook('fn_qr_check_user_in_moderator_group')) ? eval($hook) : null;
			$result2 = $pun_db->query_build($query) or error(__FILE__, __LINE__);

			if (!$pun_db->num_rows($result2))	// If the user isn't in a moderator or admin group, remove him/her from the list
				unset($new_moderators[$username]);
		}

		// If we changed anything, update the forum
		if ($cur_moderators != $new_moderators)
		{
			$new_moderators = (!empty($new_moderators)) ? '\''.$pun_db->escape(serialize($new_moderators)).'\'' : 'NULL';

			$query = array(
				'UPDATE'	=> 'forums',
				'SET'		=> 'moderators='.$new_moderators,
				'WHERE'		=> 'id='.$cur_forum['id']
			);

			($hook = get_hook('fn_qr_set_forum_moderators')) ? eval($hook) : null;
			$pun_db->query_build($query) or error(__FILE__, __LINE__);
		}
	}

	($hook = get_hook('fn_clean_forum_moderators_end')) ? eval($hook) : null;
}


//
// Locate and delete any orphaned redirect topics
//
function delete_orphans()
{
	global $pun_db;

	($hook = get_hook('fn_delete_orphans_start')) ? eval($hook) : null;

	// Locate any orphaned redirect topics
	$query = array(
		'SELECT'	=> 't1.id',
		'FROM'		=> 'topics AS t1',
		'JOINS'		=> array(
			array(
				'LEFT JOIN'		=> 'topics AS t2',
				'ON'			=> 't1.moved_to=t2.id'
			)
		),
		'WHERE'		=> 't2.id IS NULL AND t1.moved_to IS NOT NULL'
	);

	($hook = get_hook('fn_qr_get_orphans')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	$num_orphans = $pun_db->num_rows($result);

	if ($num_orphans)
	{
		for ($i = 0; $i < $num_orphans; ++$i)
			$orphans[] = $pun_db->result($result, $i);

		// Delete the orphan
		$query = array(
			'DELETE'	=> 'topics',
			'WHERE'		=> 'id IN('.implode(',', $orphans).')'
		);

		($hook = get_hook('fn_qr_delete_orphan')) ? eval($hook) : null;
		$pun_db->query_build($query) or error(__FILE__, __LINE__);
	}
}


//
// Delete a topic and all of it's posts
//
function delete_topic($topic_id, $forum_id)
{
	global $pun_db, $db_type;

	($hook = get_hook('fn_delete_topic_start')) ? eval($hook) : null;

	// Delete the topic and any redirect topics
	$query = array(
		'DELETE'	=> 'topics',
		'WHERE'		=> 'id='.$topic_id.' OR moved_to='.$topic_id
	);

	($hook = get_hook('fn_qr_delete_topic')) ? eval($hook) : null;
	$pun_db->query_build($query) or error(__FILE__, __LINE__);

	// Create a list of the post ID's in this topic
	$post_ids = '';
	$query = array(
		'SELECT'	=> 'p.id',
		'FROM'		=> 'posts AS p',
		'WHERE'		=> 'p.topic_id='.$topic_id
	);

	($hook = get_hook('fn_qr_get_posts_to_delete')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	while ($row = $pun_db->fetch_row($result))
		$post_ids .= ($post_ids != '') ? ','.$row[0] : $row[0];

	// Make sure we have a list of post ID's
	if ($post_ids != '')
	{
		// Delete posts in topic
		$query = array(
			'DELETE'	=> 'posts',
			'WHERE'		=> 'topic_id='.$topic_id
		);

		($hook = get_hook('fn_qr_delete_topic_posts')) ? eval($hook) : null;
		$pun_db->query_build($query) or error(__FILE__, __LINE__);

		if ($db_type != 'mysql' && $db_type != 'mysqli')
		{
			require PUN_ROOT.'include/search_idx.php';
			strip_search_index($post_ids);
		}
	}

	// Delete any subscriptions for this topic
	$query = array(
		'DELETE'	=> 'subscriptions',
		'WHERE'		=> 'topic_id='.$topic_id
	);

	($hook = get_hook('fn_qr_delete_topic_subscriptions')) ? eval($hook) : null;
	$pun_db->query_build($query) or error(__FILE__, __LINE__);

	sync_forum($forum_id);

	($hook = get_hook('fn_delete_topic_end')) ? eval($hook) : null;
}


//
// Delete a single post
//
function delete_post($post_id, $topic_id, $forum_id)
{
	global $pun_db, $db_type;

	($hook = get_hook('fn_delete_post_start')) ? eval($hook) : null;

	$query = array(
		'SELECT'	=> 'p.id, p.poster, p.posted',
		'FROM'		=> 'posts AS p',
		'WHERE'		=> 'p.topic_id='.$topic_id,
		'ORDER BY'	=> 'p.id DESC',
		'LIMIT'		=> '2'
	);

	($hook = get_hook('fn_qr_get_topic_lastposts_info')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	list($last_id, ,) = $pun_db->fetch_row($result);
	list($second_last_id, $second_poster, $second_posted) = $pun_db->fetch_row($result);

	// Delete the post
	$query = array(
		'DELETE'	=> 'posts',
		'WHERE'		=> 'id='.$post_id
	);

	($hook = get_hook('fn_qr_delete_post')) ? eval($hook) : null;
	$pun_db->query_build($query) or error(__FILE__, __LINE__);

	if ($db_type != 'mysql' && $db_type != 'mysqli')
	{
		require PUN_ROOT.'include/search_idx.php';
		strip_search_index($post_id);
	}

	// Count number of replies in the topic
	$query = array(
		'SELECT'	=> 'COUNT(p.id)',
		'FROM'		=> 'posts AS p',
		'WHERE'		=> 'p.topic_id='.$topic_id
	);

	($hook = get_hook('fn_qr_get_topic_reply_count2')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	$num_replies = $pun_db->result($result, 0) - 1;

	// Update the topic now that a post has been deleted
	$query = array(
		'UPDATE'	=> 'topics',
		'SET'		=> 'num_replies='.$num_replies,
		'WHERE'		=> 'id='.$topic_id
	);

	// If we deleted the most recent post, we need to sync up last post data as wel
	if ($last_id == $post_id)
		$query['SET'] .= ', last_post='.$second_posted.', last_post_id='.$second_last_id.', last_poster=\''.$pun_db->escape($second_poster).'\'';

	($hook = get_hook('fn_qr_update_topic2')) ? eval($hook) : null;
	$pun_db->query_build($query) or error(__FILE__, __LINE__);

	sync_forum($forum_id);

	($hook = get_hook('fn_delete_post_end')) ? eval($hook) : null;
}


//
// Creates a new topic with its first post
//
function add_topic($post_info, &$new_tid, &$new_pid)
{
	global $pun_db, $db_type, $pun_config, $lang_common;

	($hook = get_hook('fn_add_topic_start')) ? eval($hook) : null;

	// Add the topic
	$query = array(
		'INSERT'	=> 'poster, subject, posted, last_post, last_poster, forum_id',
		'INTO'		=> 'topics',
		'VALUES'	=> '\''.$pun_db->escape($post_info['poster']).'\', \''.$pun_db->escape($post_info['subject']).'\', '.$post_info['posted'].', '.$post_info['posted'].', \''.$pun_db->escape($post_info['poster']).'\', '.$post_info['forum_id']
	);

	($hook = get_hook('fn_qr_add_topic')) ? eval($hook) : null;
	$pun_db->query_build($query) or error(__FILE__, __LINE__);
	$new_tid = $pun_db->insert_id();

	// To subscribe or not to subscribe, that ...
	if (!$post_info['is_guest'] && $post_info['subscribe'])
	{
		$query = array(
			'INSERT'	=> 'user_id, topic_id',
			'INTO'		=> 'subscriptions',
			'VALUES'	=> $post_info['poster_id'].' ,'.$new_tid
		);

		($hook = get_hook('fn_qr_add_subscription')) ? eval($hook) : null;
		$pun_db->query_build($query) or error(__FILE__, __LINE__);
	}

	// Create the post ("topic post")
	$query = array(
		'INSERT'	=> 'poster, poster_id, poster_ip, message, hide_smilies, posted, topic_id',
		'INTO'		=> 'posts',
		'VALUES'	=> '\''.$pun_db->escape($post_info['poster']).'\', '.$post_info['poster_id'].', \''.get_remote_address().'\', \''.$pun_db->escape($post_info['message']).'\', '.$post_info['hide_smilies'].', '.$post_info['posted'].', '.$new_tid
	);

	// If it's a guest post, there might be an e-mail address we need to include
	if ($post_info['is_guest'] && $post_info['poster_email'] != null)
	{
		$query['INSERT'] .= ', poster_email';
		$query['VALUES'] .= ', \''.$post_info['poster_email'].'\'';
	}

	($hook = get_hook('fn_qr_add_topic_post')) ? eval($hook) : null;
	$pun_db->query_build($query) or error(__FILE__, __LINE__);
	$new_pid = $pun_db->insert_id();

	// Update the topic with last_post_id and first_post_id
	$query = array(
		'UPDATE'	=> 'topics',
		'SET'		=> 'last_post_id='.$new_pid.', first_post_id='.$new_pid,
		'WHERE'		=> 'id='.$new_tid
	);

	($hook = get_hook('fn_qr_update_topic3')) ? eval($hook) : null;
	$pun_db->query_build($query) or error(__FILE__, __LINE__);

	if ($db_type != 'mysql' && $db_type != 'mysqli')
	{
		require PUN_ROOT.'include/search_idx.php';
		update_search_index('post', $new_pid, $post_info['message'], $post_info['subject']);
	}

	sync_forum($post_info['forum_id']);

	($hook = get_hook('fn_add_topic_end')) ? eval($hook) : null;
}


//
// Creates a new post
//
function add_post($post_info, &$new_pid)
{
	global $pun_db, $db_type, $pun_config, $lang_common;

	($hook = get_hook('fn_add_post_start')) ? eval($hook) : null;

	// Add the post
	$query = array(
		'INSERT'	=> 'poster, poster_id, poster_ip, message, hide_smilies, posted, topic_id',
		'INTO'		=> 'posts',
		'VALUES'	=> '\''.$pun_db->escape($post_info['poster']).'\', '.$post_info['poster_id'].', \''.get_remote_address().'\', \''.$pun_db->escape($post_info['message']).'\', '.$post_info['hide_smilies'].', '.$post_info['posted'].', '.$post_info['topic_id']
	);

	// If it's a guest post, there might be an e-mail address we need to include
	if ($post_info['is_guest'] && $post_info['poster_email'] != null)
	{
		$query['INSERT'] .= ', poster_email';
		$query['VALUES'] .= ', \''.$post_info['poster_email'].'\'';
	}

	($hook = get_hook('fn_qr_add_post')) ? eval($hook) : null;
	$pun_db->query_build($query) or error(__FILE__, __LINE__);
	$new_pid = $pun_db->insert_id();

	if (!$post_info['is_guest'])
	{
		// Subscribe or unsubscribe?
		if ($post_info['subscr_action'] == 1)
		{
			$query = array(
				'INSERT'	=> 'user_id, topic_id',
				'INTO'		=> 'subscriptions',
				'VALUES'	=> $post_info['poster_id'].' ,'.$post_info['topic_id']
			);

			($hook = get_hook('fn_qr_add_subscription2')) ? eval($hook) : null;
			$pun_db->query_build($query) or error(__FILE__, __LINE__);
		}
		else if ($post_info['subscr_action'] == 2)
		{
			$query = array(
				'DELETE'	=> 'subscriptions',
				'WHERE'		=> 'topic_id='.$post_info['topic_id'].' AND user_id='.$post_info['poster_id']
			);

			($hook = get_hook('fn_qr_delete_subscription')) ? eval($hook) : null;
			$pun_db->query_build($query) or error(__FILE__, __LINE__);
		}
	}

	// Count number of replies in the topic
	$query = array(
		'SELECT'	=> 'COUNT(p.id)',
		'FROM'		=> 'posts AS p',
		'WHERE'		=> 'p.topic_id='.$post_info['topic_id']
	);

	($hook = get_hook('fn_qr_get_topic_reply_count3')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	$num_replies = $pun_db->result($result, 0) - 1;

	// Update topic
	$query = array(
		'UPDATE'	=> 'topics',
		'SET'		=> 'num_replies='.$num_replies.', last_post='.$post_info['posted'].', last_post_id='.$new_pid.', last_poster=\''.$pun_db->escape($post_info['poster']).'\'',
		'WHERE'		=> 'id='.$post_info['topic_id']
	);

	($hook = get_hook('fn_qr_update_topic4')) ? eval($hook) : null;
	$pun_db->query_build($query) or error(__FILE__, __LINE__);

	sync_forum($post_info['forum_id']);

	if ($db_type != 'mysql' && $db_type != 'mysqli')
	{
		require PUN_ROOT.'include/search_idx.php';
		update_search_index('post', $new_pid, $post_info['message']);
	}

	send_subscriptions($post_info, $new_pid);

	($hook = get_hook('fn_add_post_end')) ? eval($hook) : null;
}


//
// Send out subscription emails
//
function send_subscriptions($post_info, $new_pid)
{
	global $pun_config, $pun_db, $pun_url, $lang_common;

	($hook = get_hook('fn_send_subscriptions_start')) ? eval($hook) : null;

	if ($pun_config['o_subscriptions'] != '1')
		return;

	// Get the post time for the previous post in this topic
	$query = array(
		'SELECT'	=> 'p.posted',
		'FROM'		=> 'posts AS p',
		'WHERE'		=> 'p.topic_id='.$post_info['topic_id'],
		'ORDER BY'	=> 'p.id DESC',
		'LIMIT'		=> '1, 1'
	);

	($hook = get_hook('fn_qr_get_previous_post_time')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	$previous_post_time = $pun_db->result($result);

	// Get any subscribed users that should be notified (banned users are excluded)
	$query = array(
		'SELECT'	=> 'u.id, u.email, u.notify_with_post, u.language',
		'FROM'		=> 'users AS u',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'subscriptions AS s',
				'ON'			=> 'u.id=s.user_id'
			),
			array(
				'LEFT JOIN'		=> 'forum_perms AS fp',
				'ON'			=> '(fp.forum_id='.$post_info['forum_id'].' AND fp.group_id=u.group_id)'
			),
			array(
				'LEFT JOIN'		=> 'online AS o',
				'ON'			=> 'u.id=o.user_id'
			),
			array(
				'LEFT JOIN'		=> 'bans AS b',
				'ON'			=> 'u.username=b.username'
			),
		),
		'WHERE'		=> 'b.username IS NULL AND COALESCE(o.logged, u.last_visit)>'.$previous_post_time.' AND (fp.read_forum IS NULL OR fp.read_forum=1) AND s.topic_id='.$post_info['topic_id'].' AND u.id!='.$post_info['poster_id']
	);

	($hook = get_hook('fn_qr_get_users_to_notify')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);

	if ($pun_db->num_rows($result))
	{
		require_once PUN_ROOT.'include/email.php';

		$notification_emails = array();

		// Loop through subscribed users and send e-mails
		while ($cur_subscriber = $pun_db->fetch_assoc($result))
		{
			// Is the subscription e-mail for $cur_subscriber['language'] cached or not?
			if (!isset($notification_emails[$cur_subscriber['language']]))
			{
				if (file_exists(PUN_ROOT.'lang/'.$cur_subscriber['language'].'/mail_templates/new_reply.tpl'))
				{
					// Load the "new reply" template
					$mail_tpl = trim(file_get_contents(PUN_ROOT.'lang/'.$cur_subscriber['language'].'/mail_templates/new_reply.tpl'));

					// Load the "new reply full" template (with post included)
					$mail_tpl_full = trim(file_get_contents(PUN_ROOT.'lang/'.$cur_subscriber['language'].'/mail_templates/new_reply_full.tpl'));

					// The first row contains the subject (it also starts with "Subject:")
					$first_crlf = strpos($mail_tpl, "\n");
					$mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
					$mail_message = trim(substr($mail_tpl, $first_crlf));

					$first_crlf = strpos($mail_tpl_full, "\n");
					$mail_subject_full = trim(substr($mail_tpl_full, 8, $first_crlf-8));
					$mail_message_full = trim(substr($mail_tpl_full, $first_crlf));

					$mail_subject = str_replace('<topic_subject>', '\''.$post_info['subject'].'\'', $mail_subject);
					$mail_message = str_replace('<topic_subject>', '\''.$post_info['subject'].'\'', $mail_message);
					$mail_message = str_replace('<replier>', $post_info['poster'], $mail_message);
					$mail_message = str_replace('<post_url>', pun_link($pun_url['post'], $new_pid), $mail_message);
					$mail_message = str_replace('<unsubscribe_url>', pun_link($pun_url['unsubscribe'], array($post_info['topic_id'], generate_form_token('unsubscribe'.$post_info['topic_id'].$cur_subscriber['id']))), $mail_message);
					$mail_message = str_replace('<board_mailer>', sprintf($lang_common['Forum mailer'], $pun_config['o_board_title']), $mail_message);

					$mail_subject_full = str_replace('<topic_subject>', '\''.$post_info['subject'].'\'', $mail_subject_full);
					$mail_message_full = str_replace('<topic_subject>', '\''.$post_info['subject'].'\'', $mail_message_full);
					$mail_message_full = str_replace('<replier>', $post_info['poster'], $mail_message_full);
					$mail_message_full = str_replace('<message>', $post_info['message'], $mail_message_full);
					$mail_message_full = str_replace('<post_url>', pun_link($pun_url['post'], $new_pid), $mail_message_full);
					$mail_message_full = str_replace('<unsubscribe_url>', pun_link($pun_url['unsubscribe'], array($post_info['topic_id'], generate_form_token('unsubscribe'.$post_info['topic_id'].$cur_subscriber['id']))), $mail_message_full);
					$mail_message_full = str_replace('<board_mailer>', sprintf($lang_common['Forum mailer'], $pun_config['o_board_title']), $mail_message_full);

					$notification_emails[$cur_subscriber['language']][0] = $mail_subject;
					$notification_emails[$cur_subscriber['language']][1] = $mail_message;
					$notification_emails[$cur_subscriber['language']][2] = $mail_subject_full;
					$notification_emails[$cur_subscriber['language']][3] = $mail_message_full;

					$mail_subject = $mail_message = $mail_subject_full = $mail_message_full = null;
				}
			}

			// We have to double check here because the templates could be missing
			if (isset($notification_emails[$cur_subscriber['language']]))
			{
				// Make sure the e-mail address format is valid before sending
				if (is_valid_email($cur_subscriber['email']))
				{
					if ($cur_subscriber['notify_with_post'] == '0')
						pun_mail($cur_subscriber['email'], $notification_emails[$cur_subscriber['language']][0], $notification_emails[$cur_subscriber['language']][1]);
					else
						pun_mail($cur_subscriber['email'], $notification_emails[$cur_subscriber['language']][2], $notification_emails[$cur_subscriber['language']][3]);
				}
			}
		}
	}

	($hook = get_hook('fn_send_subscriptions_end')) ? eval($hook) : null;
}


//
// Make a string safe to use in a URL
//
function sef_friendly($str)
{
	($hook = get_hook('fn_sef_friendly_start')) ? eval($hook) : null;

	$str = strtolower(utf8_decode($str));
	$str = strtr($str,
		"\xc0\xc1\xc2\xc3\xc4\xc5\xe0\xe1\xe2\xe3\xe4\xe5\xd2\xd3\xd4\xd5\xd6\xd8\xf2\xf3\xf4\xf5\xf6\xf8\xc8\xc9\xca\xcb\xe8\xe9\xea\xeb\xc7\xe7\xcc\xcd\xce\xcf\xec\xed\xee\xef\xd9\xda\xdb\xdc\xf9\xfa\xfb\xfc\xff\xd1\xf1",
		'aaaaaaaaaaaaooooooooooooeeeeeeeecciiiiiiiiuuuuuuuuynn'
	);
	$str = preg_replace(array('/[^a-z0-9\s]/', '/[\s]+/'), array('', '-'), $str);

	return $str != '-' ? trim($str, '-') : '';
}


//
// Replace censored words in $text
//
function censor_words($text)
{
	global $pun_db;
	static $search_for, $replace_with;

	($hook = get_hook('fn_censor_words_start')) ? eval($hook) : null;

	// If not already loaded in a previous call, load the cached censors
	if (!defined('PUN_CENSORS_LOADED'))
	{
		if (file_exists(PUN_CACHE_DIR.'cache_censors.php'))
			include PUN_CACHE_DIR.'cache_censors.php';

		if (!defined('PUN_CENSORS_LOADED'))
		{
			require_once PUN_ROOT.'include/cache.php';
			generate_censors_cache();
			require PUN_CACHE_DIR.'cache_censors.php';
		}

		$search_for = array();
		$replace_with = array();

		foreach ($pun_censors as $censor_key => $cur_word)
		{
			$search_for[$censor_key] = '/\b('.str_replace('\*', '\w*?', preg_quote($cur_word['search_for'], '/')).')\b/iu';
			$replace_with[$censor_key] = $cur_word['replace_with'];

			($hook = get_hook('fn_censor_words_setup_regex')) ? eval($hook) : null;
		}
	}

	if (!empty($search_for))
		$text = substr(preg_replace($search_for, $replace_with, ' '.$text.' '), 1, -1);

	return $text;
}


//
// Check if a username is occupied
//
function check_username_dupe($username, $exclude_id = null)
{
	global $pun_db;

	($hook = get_hook('fn_check_username_dupe_start')) ? eval($hook) : null;

	$query = array(
		'SELECT'	=> 'u.username',
		'FROM'		=> 'users AS u',
		'WHERE'		=> '(UPPER(username)=UPPER(\''.$pun_db->escape($username).'\') OR UPPER(username)=UPPER(\''.$pun_db->escape(preg_replace('/[^\w]/u', '', $username)).'\')) AND id>1'
	);

	if ($exclude_id)
		$query['WHERE'] .= ' AND id!='.$exclude_id;

	($hook = get_hook('fn_qr_check_username_dupe')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);

	return $pun_db->num_rows($result) ? $pun_db->result($result) : false;
}


//
// Determines the correct title for $user
// $user must contain the elements 'username', 'title', 'posts', 'g_id' and 'g_user_title'
//
function get_title($user)
{
	global $pun_db, $pun_config, $pun_bans, $lang_common;
	static $ban_list, $pun_ranks;

	($hook = get_hook('fn_get_title_start')) ? eval($hook) : null;

	// If not already built in a previous call, build an array of lowercase banned usernames
	if (empty($ban_list))
	{
		$ban_list = array();

		foreach ($pun_bans as $cur_ban)
			$ban_list[] = strtolower($cur_ban['username']);
	}

	// If not already loaded in a previous call, load the cached ranks
	if ($pun_config['o_ranks'] == '1' && !defined('PUN_RANKS_LOADED'))
	{
		if (file_exists(PUN_CACHE_DIR.'cache_ranks.php'))
			include PUN_CACHE_DIR.'cache_ranks.php';

		if (!defined('PUN_RANKS_LOADED'))
		{
			require_once PUN_ROOT.'include/cache.php';
			generate_ranks_cache();
			require PUN_CACHE_DIR.'cache_ranks.php';
		}
	}

	// If the user has a custom title
	if ($user['title'] != '')
		$user_title = pun_htmlencode($pun_config['o_censoring'] == '1' ? censor_words($user['title']) : $user['title']);
	// If the user is banned
	else if (in_array(strtolower($user['username']), $ban_list))
		$user_title = $lang_common['Banned'];
	// If the user group has a default user title
	else if ($user['g_user_title'] != '')
		$user_title = pun_htmlencode($user['g_user_title']);
	// If the user is a guest
	else if ($user['g_id'] == PUN_GUEST)
		$user_title = $lang_common['Guest'];
	else
	{
		// Are there any ranks?
		if ($pun_config['o_ranks'] == '1' && !empty($pun_ranks))
		{
			@reset($pun_ranks);
			while (list(, $cur_rank) = @each($pun_ranks))
			{
				if (intval($user['num_posts']) >= $cur_rank['min_posts'])
					$user_title = pun_htmlencode($cur_rank['rank']);
			}
		}

		// If the user didn't "reach" any rank (or if ranks are disabled), we assign the default
		if (!isset($user_title))
			$user_title = $lang_common['Member'];
	}

	($hook = get_hook('fn_get_title_end')) ? eval($hook) : null;

	return $user_title;
}


//
// Generate a string with numbered links (for multipage scripts)
//
function paginate($num_pages, $cur_page, $link, $separator, $args = null)
{
	global $pun_url, $lang_common;

	$pages = array();
	$link_to_all = false;

	($hook = get_hook('fn_paginate_start')) ? eval($hook) : null;

	// If $cur_page == -1, we link to all pages (used in viewforum.php)
	if ($cur_page == -1)
	{
		$cur_page = 1;
		$link_to_all = true;
	}

	if ($num_pages <= 1)
		$pages = array('<strong class="item1">1</strong>');
	else
	{
		// Add a previous page link
		if ($num_pages > 1 && $cur_page > 1)
			$pages[] = '<a'.(empty($pages) ? ' class="item1"' : '').' href="'.pun_sublink($link, $pun_url['page'], ($cur_page - 1), $args).'">'.$lang_common['Previous'].'</a>';

		if ($cur_page > 3)
		{
			$pages[] = '<a'.(empty($pages) ? ' class="item1"' : '').' href="'.pun_sublink($link, $pun_url['page'], 1, $args).'">1</a>';

			if ($cur_page > 5)
				$pages[] = '<span>…</span>';
		}

		// Don't ask me how the following works. It just does, OK? :-)
		for ($current = ($cur_page == 5) ? $cur_page - 3 : $cur_page - 2, $stop = ($cur_page + 4 == $num_pages) ? $cur_page + 4 : $cur_page + 3; $current < $stop; ++$current)
		{
			if ($current < 1 || $current > $num_pages)
				continue;
			else if ($current != $cur_page || $link_to_all)
				$pages[] = '<a'.(empty($pages) ? ' class="item1" ' : '').' href="'.pun_sublink($link, $pun_url['page'], $current, $args).'">'.$current.'</a>';
			else
				$pages[] = '<strong'.(empty($pages) ? ' class="item1"' : '').'>'.$current.'</strong>';
		}

		if ($cur_page <= ($num_pages-3))
		{
			if ($cur_page != ($num_pages-3) && $cur_page != ($num_pages-4))
				$pages[] = '<span>…</span>';

			$pages[] = '<a'.(empty($pages) ? ' class="item1" ' : '').' href="'.pun_sublink($link, $pun_url['page'], $num_pages, $args).'">'.$num_pages.'</a>';
		}

		// Add a next page link
		if ($num_pages > 1 && !$link_to_all && $cur_page < $num_pages)
			$pages[] = '<a'.(empty($pages) ? ' class="item1" ' : '').' href="'.pun_sublink($link, $pun_url['page'], ($cur_page + 1), $args).'">'.$lang_common['Next'].'</a>';
	}

	($hook = get_hook('fn_paginate_end')) ? eval($hook) : null;

	return implode($separator, $pages);
}


//
// Clean version string from trailing '.0's
//
function clean_version($version)
{
	return preg_replace('/(\.0)+(?!\.)|(\.0+$)/', '$2', $version);
}


//
// Display a message
//
function message($message, $link = '')
{
	global $pun_db, $pun_url, $lang_common, $pun_config, $base_url, $pun_start, $tpl_main, $pun_user, $pun_page, $pun_updates;

	($hook = get_hook('fn_message_start')) ? eval($hook) : null;

	if (!defined('PUN_HEADER'))
	{
		// Setup breadcrumbs
		$pun_page['crumbs'] = array(
			array($pun_config['o_board_title'], pun_link($pun_url['index'])),
			$lang_common['Info']
		);

		define('PUN_PAGE', 'message');
		require PUN_ROOT.'header.php';
	}

?>
<div id="pun-main" class="main">

	<h1><span><?php echo end($pun_page['crumbs']) ?></span></h1>

	<div class="main-head">
		<h2><span><?php echo $lang_common['Forum message'] ?></span></h2>
	</div>
	<div class="main-content message">
		<p><?php echo $message ?><?php if ($link != '') echo ' <span>'.$link.'</span>' ?></p>
	</div>

</div>
<?php

	require PUN_ROOT.'footer.php';
}


//
// Display a form that the user can use to confirm that they want to undertake an action.
// Used when the CSRF token from the request does not match the token stored in the database.
//
function csrf_confirm_form()
{
	global $pun_db, $pun_url, $lang_common, $pun_config, $base_url, $pun_start, $tpl_main, $pun_user, $pun_page, $pun_updates;

	// User pressed the cancel button
	if (isset($_POST['confirm_cancel']))
		redirect(pun_htmlencode($_POST['prev_url']), $lang_common['Cancel redirect']);

	//
	// A helper function for csrf_confirm_form. It takes a multi-dimensional array and returns it as a
	// single-dimensional array suitable for use in hidden fields.
	//
	function _csrf_confirm_form($key, $values)
	{
		$fields = array();

		if (is_array($values))
		{
			foreach ($values as $cur_key => $cur_values)
				$fields = array_merge($fields, _csrf_confirm_form($key.'['.$cur_key.']', $cur_values));

			return $fields;
		}
		else
			$fields[$key] = $values;

		return $fields;
	}

	($hook = get_hook('fn_csrf_confirm_form_start')) ? eval($hook) : null;

	// Setup breadcrumbs
	$pun_page['crumbs'] = array(
		array($pun_config['o_board_title'], pun_link($pun_url['index'])),
		$lang_common['Confirm action']
	);

	$pun_page['form_action'] = get_current_url();

	$pun_page['hidden_fields'] = array(
		'<input type="hidden" name="csrf_token" value="'.generate_form_token($pun_page['form_action']).'" />',
		'<input type="hidden" name="prev_url" value="'.pun_htmlencode($pun_user['prev_url']).'" />'
	);

	foreach ($_POST as $submitted_key => $submitted_val)
	{
		if ($submitted_key != 'csrf_token' && $submitted_key != 'prev_url')
		{
			$hidden_fields = _csrf_confirm_form($submitted_key, $submitted_val);
			foreach ($hidden_fields as $field_key => $field_val)
				$pun_page['hidden_fields'][] = '<input type="hidden" name="'.pun_htmlencode($field_key).'" value="'.pun_htmlencode($field_val).'" />';
		}
	}

	define('PUN_PAGE', 'dialogue');
	require PUN_ROOT.'header.php';

	($hook = get_hook('fn_csrf_confirm_form_pre_header_load')) ? eval($hook) : null;

?>
<div id="pun-main" class="main">

	<h1><span><?php echo end($pun_page['crumbs']) ?></span></h1>

	<div class="main-head">
		<h2><span><?php echo $lang_common['Confirm action head'] ?></span></h2>
	</div>
	<div class="main-content frm">
		<div class="frm-info">
			<p><?php echo $lang_common['CSRF token mismatch'] ?></p>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $pun_page['form_action'] ?>">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $pun_page['hidden_fields'])."\n" ?>
			</div>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" value="<?php echo $lang_common['Confirm'] ?>" /></span>
				<span class="cancel"><input type="submit" name="confirm_cancel" value="<?php echo $lang_common['Cancel'] ?>" /></span>
			</div>
		</form>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


//
// Generate a hyperlink with parameters and anchor
//
function pun_link($link, $args = null)
{
	global $pun_config, $base_url;

	$gen_link = $link;
	if ($args == null)
		$gen_link = $base_url.'/'.$link;
	else if (!is_array($args))
		$gen_link = $base_url.'/'.str_replace('$1', $args, $link);
	else
	{
		for ($i = 0; isset($args[$i]); ++$i)
			$gen_link = str_replace('$'.($i + 1), $args[$i], $gen_link);
		$gen_link = $base_url.'/'.$gen_link;
	}

	($hook = get_hook('fn_pun_link_end')) ? eval($hook) : null;

	return $gen_link;
}


//
// Generate a hyperlink with parameters and anchor and a subsection such as a subpage
//
function pun_sublink($link, $sublink, $subarg, $args = null)
{
	global $pun_config, $pun_url, $base_url;

	$gen_link = $link;
	if (!is_array($args) && $args != null)
		$gen_link = str_replace('$1', $args, $link);
	else
	{
		for ($i = 0; isset($args[$i]); ++$i)
			$gen_link = str_replace('$'.($i + 1), $args[$i], $gen_link);
	}

	if (isset($pun_url['insertion_find']))
		$gen_link = $base_url.'/'.str_replace($pun_url['insertion_find'], str_replace('$1', str_replace('$1', $subarg, $sublink), $pun_url['insertion_replace']), $gen_link);
	else
		$gen_link = $base_url.'/'.$gen_link.str_replace('$1', $subarg, $sublink);

	($hook = get_hook('fn_pun_sublink_end')) ? eval($hook) : null;

	return $gen_link;
}


//
// Format a time string according to $time_format and timezones
//
function format_time($timestamp, $date_only = false)
{
	global $pun_config, $lang_common, $pun_user, $pun_time_formats, $pun_date_formats;

	($hook = get_hook('fn_format_time_start')) ? eval($hook) : null;

	if ($timestamp == '')
		return $lang_common['Never'];

	$diff = ($pun_user['timezone'] + $pun_user['dst']) * 3600;
	$timestamp += $diff;
	$now = time();

	$date = gmdate($pun_date_formats[$pun_user['date_format']], $timestamp);
	$base = gmdate('Y-m-d', $timestamp);
	$today = gmdate('Y-m-d', $now+$diff);
	$yesterday = gmdate('Y-m-d', $now+$diff-86400);

	if ($base == $today)
		$date = $lang_common['Today'];
	else if ($base == $yesterday)
		$date = $lang_common['Yesterday'];

	if (!$date_only)
		$date .= ' '.gmdate($pun_time_formats[$pun_user['time_format']], $timestamp);

	return $date;
}


//
// Generate a random key of length $len
//
function random_key($len, $readable = false, $hash = false)
{
	$key = '';

	if ($hash)
		$key = substr(sha1(uniqid(rand(), true)), 0, $len);
	else if ($readable)
	{
		$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

		for ($i = 0; $i < $len; ++$i)
			$key .= substr($chars, (mt_rand() % strlen($chars)), 1);
	}
	else
	{
		for ($i = 0; $i < $len; ++$i)
			$key .= chr(mt_rand(33, 126));
	}

	($hook = get_hook('fn_random_key_end')) ? eval($hook) : null;

	return $key;
}


//
// Generates a valid CSRF token for use when submitting a form to $target_url
// $target_url should be an absolute URL and it should be exactly the URL that the user is going to
// Alternately, if the form token is going to be used in GET (which would mean the token is going to be
// a part of the URL itself), $target_url may be a plain string containing information related to the URL.
//
function generate_form_token($target_url)
{
	global $pun_user;

	($hook = get_hook('fn_generate_form_token_start')) ? eval($hook) : null;

	return sha1(str_replace('&amp;', '&', $target_url).$pun_user['csrf_token']);
}

//
// Try to determine the correct remote IP-address
//
function get_remote_address()
{
	($hook = get_hook('fn_get_remote_address_start')) ? eval($hook) : null;

	return $_SERVER['REMOTE_ADDR'];
}


//
// Try to determine the current URL
//
function get_current_url()
{
	global $base_url;

	($hook = get_hook('fn_get_current_url')) ? eval($hook) : null;

	$protocol = (!isset($_SERVER['HTTPS']) || strtolower($_SERVER['HTTPS']) == 'off') ? 'http://' : 'https://';
	$port = (isset($_SERVER['SERVER_PORT']) && (($_SERVER['SERVER_PORT'] != '80' && $protocol == 'http://') || ($_SERVER['SERVER_PORT'] != '443' && $protocol == 'https://')) && strpos($_SERVER['HTTP_HOST'], ':') === false) ? ':'.$_SERVER['SERVER_PORT'] : '';

	return $protocol.$_SERVER['HTTP_HOST'].$port.$_SERVER['REQUEST_URI'];
}


//
// Encodes the contents of $str so that they are safe to output on an (X)HTML page
//
function pun_htmlencode($str)
{
	($hook = get_hook('fn_pun_htmlencode')) ? eval($hook) : null;

	return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}


//
// An UTF-8 aware version of strlen()
//
function pun_strlen($str)
{
	return strlen(utf8_decode($str));
}


//
// Convert \r\n and \r to \n
//
function pun_linebreaks($str)
{
	return str_replace(array("\r\n", "\r"), "\n", $str);
}


//
// Inserts $element into $input at $offset
//
function array_insert(&$input, $offset, $element)
{
	// Out of bounds checks
	if ($offset > count($input))
		$offset = count($input);
	else if ($offset < 0)
		$offset = 0;

	array_splice($input, $offset, 0, 0);
	$input[$offset] = $element;
}


//
// Attempts to fetch the provided URL using any available means
//
function get_remote_file($url, $timeout, $head_only = false)
{
	$result = null;
	$parsed_url = parse_url($url);
	$allow_url_fopen = strtolower(@ini_get('allow_url_fopen'));

	// Quite unlikely that this will be allowed on a shared host, but it can't hurt
	if (function_exists('ini_set'))
		@ini_set('default_socket_timeout', $timeout);

	// If we have cURL, we might as well use it
	if (function_exists('curl_init'))
	{
		// Setup the transfer
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_NOBODY, $head_only);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_USERAGENT, 'PunBB');

		// Grab the page
		$content = @curl_exec($ch);

		// Ignore everything except a 200 response code
		if ($content !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) == '200')
		{
			if ($head_only)
				$result['headers'] = explode("\r\n", trim($content));
			else
			{
				$content_start = strpos($content, "\r\n\r\n");
				if ($content_start !== false)
				{
					$result['headers'] = explode("\r\n", substr($content, 0, $content_start));
					$result['content'] = trim(substr($content, $content_start));
				}
			}
		}

		curl_close($ch);
	}
	// fsockopen() is the second best thing
	else if (function_exists('fsockopen'))
	{
		$remote = @fsockopen($parsed_url['host'], !empty($parsed_url['port']) ? intval($parsed_url['port']) : 80, $errno, $errstr, $timeout);
		if ($remote)
		{
			// Send a standard HTTP 1.0 request for the page
			$method = $head_only ? 'HEAD' : 'GET';
			fwrite($remote, ($head_only ? 'HEAD' : 'GET').' '.(!empty($parsed_url['path']) ? $parsed_url['path'] : '/').(!empty($parsed_url['query']) ? '?'.$parsed_url['query'] : '').' HTTP/1.0'."\r\n");
			fwrite($remote, 'Host: '.$parsed_url['host']."\r\n");
			fwrite($remote, 'User-Agent: PunBB'."\r\n");
			fwrite($remote, 'Connection: Close'."\r\n\r\n");

			stream_set_timeout($remote, $timeout);
			$stream_meta = stream_get_meta_data($remote);

			// Fetch the response 1024 bytes at a time and watch out for a timeout
			$content = false;
			while (!feof($remote) && !$stream_meta['timed_out'])
			{
				$content .= fgets($remote, 1024);
				$stream_meta = stream_get_meta_data($remote);
			}

			fclose($remote);

			// Ignore everything except a 200 response code (we don't support redirects)
			if ($content !== false && preg_match('#^HTTP/1.[01] 200 OK#', $content))
			{
				if ($head_only)
					$result['headers'] = explode("\r\n", trim($content));
				else
				{
					$content_start = strpos($content, "\r\n\r\n");
					if ($content_start !== false)
					{
						$result['headers'] = explode("\r\n", substr($content, 0, $content_start));
						$result['content'] = trim(substr($content, $content_start));
					}
				}
			}
		}
	}
	// Last case scenario, we use file_get_contents provided allow_url_fopen is enabled (any non 200 response results in a failure)
	else if (in_array($allow_url_fopen, array('on', 'true', '1')))
	{
		// PHP5's version of file_get_contents() supports stream options
		if (version_compare(PHP_VERSION, '5.0.0', '>='))
		{
			// Setup a stream context
			$stream_context = stream_context_create(
				array(
					'http' => array(
						'method'		=> $head_only ? 'HEAD' : 'GET',
						'user_agent'	=> 'PunBB',
						'max_redirects'	=> 3,		// PHP >=5.1.0 only
						'timeout'		=> $timeout	// PHP >=5.2.1 only
					)
				)
			);

			$content = @file_get_contents($url, false, $stream_context);
		}
		else
			$content = @file_get_contents($url);

		// Did we get anything?
		if ($content !== false)
		{
			// Gotta love the fact that $http_response_header just appears in the global scope (*cough* hack! *cough*)
			$result['headers'] = $http_response_header;
			if (!$head_only)
				$result['content'] = trim($content);
		}
	}

	return $result;
}


//
// Display a message when board is in maintenance mode
//
function maintenance_message()
{
	global $pun_db, $pun_config, $lang_common, $pun_user, $base_url;

	($hook = get_hook('fn_maintenance_message_start')) ? eval($hook) : null;

	// Deal with newlines, tabs and multiple spaces
	$pattern = array("\t\t", '  ', '  ');
	$replace = array('&#160; &#160; ', '&#160; ', ' &#160;');
	$message = str_replace($pattern, $replace, $pun_config['o_maintenance_message']);

	// Send the Content-type header in case the web server is setup to send something else
	header('Content-type: text/html; charset=utf-8');

	// Send a 503 HTTP response code to prevent search bots from indexing the maintenace message
	header('HTTP/1.1 503 Service Temporarily Unavailable');

	// Load the maintenance template
	$tpl_maint = trim(file_get_contents(PUN_ROOT.'include/template/maintenance.tpl'));

	// START SUBST - <!-- pun_local -->
	$tpl_maint = str_replace('<!-- pun_local -->', 'xml:lang="'.$lang_common['lang_identifier'].'" lang="'.$lang_common['lang_identifier'].'" dir="'.$lang_common['lang_direction'].'"', $tpl_maint);
	// END SUBST - <!-- pun_local -->


	// START SUBST - <!-- pun_head -->
	ob_start();

?>
<title><?php echo $lang_common['Maintenance'].' - '.pun_htmlencode($pun_config['o_board_title']) ?></title>
<link rel="stylesheet" type="text/css" media="screen" href="<?php echo $base_url ?>/style/<?php echo $pun_user['style'] ?>/<?php echo $pun_user['style'].'.css' ?>" />
<!--[if lte IE 6]><link rel="stylesheet" type="text/css" href="<?php echo $base_url ?>/style/<?php echo $pun_user['style'] ?>/<?php echo $pun_user['style'].'_fix.css' ?>" /><![endif]-->
<!--[if IE 7]><link rel="stylesheet" type="text/css" href="<?php echo $base_url ?>/style/<?php echo $pun_user['style'] ?>/<?php echo $pun_user['style'].'_fix7.css' ?>" /><![endif]-->
<?php

	$tpl_temp = trim(ob_get_contents());
	$tpl_maint = str_replace('<!-- pun_head -->', $tpl_temp, $tpl_maint);
	ob_end_clean();
	// END SUBST - <!-- pun_head -->


	// START SUBST - <!-- pun_maint_main -->
	ob_start();

?>
<div id="pun-main" class="main">

	<h1><span><?php echo $lang_common['Maintenance'] ?></span></h1>

	<div class="main-content message">
		<div class="userbox">
			<?php echo $message."\n" ?>
		</div>
	</div>

</div>
<?php

	$tpl_temp = "\t".trim(ob_get_contents());
	$tpl_maint = str_replace('<!-- pun_maint_main -->', $tpl_temp, $tpl_maint);
	ob_end_clean();
	// END SUBST - <!-- pun_maint_main -->


	// End the transaction
	$pun_db->end_transaction();


	// START SUBST - <!-- pun_include "*" -->
	while (preg_match('#<!-- ?pun_include "([^/\\\\]*?)" ?-->#', $tpl_maint, $cur_include))
	{
		if (!file_exists(PUN_ROOT.'include/user/'.$cur_include[1]))
			error('Unable to process user include &lt;!-- pun_include "'.pun_htmlencode($cur_include[1]).'" --&gt; from template maintenance.tpl. There is no such file in folder /include/user/.');

		ob_start();
		include PUN_ROOT.'include/user/'.$cur_include[1];
		$tpl_temp = ob_get_contents();
		$tpl_maint = str_replace($cur_include[0], $tpl_temp, $tpl_maint);
		ob_end_clean();
	}
	// END SUBST - <!-- pun_include "*" -->


	// Close the db connection (and free up any result data)
	$pun_db->close();

	exit($tpl_maint);
}


//
// Display $message and redirect user to $destination_url
//
function redirect($destination_url, $message)
{
	global $pun_db, $pun_config, $lang_common, $pun_user, $base_url;

	($hook = get_hook('fn_redirect_start')) ? eval($hook) : null;

	// Prefix with base_url (unless it's there already)
	if (strpos($destination_url, 'http://') !== 0 && strpos($destination_url, 'https://') !== 0 && strpos($destination_url, '/') !== 0)
		$destination_url = $base_url.'/'.$destination_url;

	// Do a little spring cleaning
	$destination_url = preg_replace('/([\r\n])|(%0[ad])|(;[\s]*data[\s]*:)/i', '', $destination_url);

	// If the delay is 0 seconds, we might as well skip the redirect all together
	if ($pun_config['o_redirect_delay'] == '0')
		header('Location: '.str_replace('&amp;', '&', $destination_url));

	// Send no-cache headers
	header('Expires: Thu, 21 Jul 1977 07:30:00 GMT');	// When yours truly first set eyes on this world! :)
	header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
	header('Cache-Control: post-check=0, pre-check=0', false);
	header('Pragma: no-cache');		// For HTTP/1.0 compability

	// Send the Content-type header in case the web server is setup to send something else
	header('Content-type: text/html; charset=utf-8');

	// Load the redirect template
	$tpl_redir = trim(file_get_contents(PUN_ROOT.'include/template/redirect.tpl'));


	// START SUBST - <!-- pun_local -->
	$tpl_redir = str_replace('<!-- pun_local -->', 'xml:lang="'.$lang_common['lang_identifier'].'" lang="'.$lang_common['lang_identifier'].'" dir="'.$lang_common['lang_direction'].'"', $tpl_redir);
	// END SUBST - <!-- pun_local -->


	// START SUBST - <!-- pun_head -->
	ob_start();

?>
<meta http-equiv="refresh" content="<?php echo $pun_config['o_redirect_delay'] ?>;URL=<?php echo str_replace(array('<', '>', '"'), array('&lt;', '&gt;', '&quot;'), $destination_url) ?>" />
<title><?php echo $lang_common['Redirecting'].' - '.pun_htmlencode($pun_config['o_board_title']) ?></title>
<?php

	// Include the stylesheets
	require PUN_ROOT.'style/'.$pun_user['style'].'/'.$pun_user['style'].'.php';

	$tpl_temp = "\t".trim(ob_get_contents());
	$tpl_redir = str_replace('<!-- pun_head -->', $tpl_temp, $tpl_redir);
	ob_end_clean();
	// END SUBST - <!-- pun_head -->


	// START SUBST - <!-- pun_redir_main -->
	ob_start();

?>
<div id="pun-main" class="main">

	<h1><span><?php echo $lang_common['Redirecting'] ?></span></h1>

	<div class="main-head">
		<h2><span><?php echo $message ?></span></h2>
	</div>
	<div class="main-content message">
		<p><?php printf($lang_common['Forwarding info'], $pun_config['o_redirect_delay'], intval($pun_config['o_redirect_delay']) == 1 ? $lang_common['second'] : $lang_common['seconds']) ?><span> <a href="<?php echo $destination_url ?>"><?php echo $lang_common['Click redirect'] ?></a></span></p>
	</div>

</div>
<?php

	$tpl_temp = "\t".trim(ob_get_contents());
	$tpl_redir = str_replace('<!-- pun_redir_main -->', $tpl_temp, $tpl_redir);
	ob_end_clean();
	// END SUBST - <!-- pun_redir_main -->


	// START SUBST - <!-- pun_debug -->
	if (defined('PUN_SHOW_QUERIES'))
		$tpl_redir = str_replace('<!-- pun_debug -->', get_saved_queries(), $tpl_redir);

	// End the transaction
	$pun_db->end_transaction();
	// END SUBST - <!-- pun_debug -->


	// START SUBST - <!-- pun_include "*" -->
	while (preg_match('#<!-- ?pun_include "([^/\\\\]*?)" ?-->#', $tpl_redir, $cur_include))
	{
		if (!file_exists(PUN_ROOT.'include/user/'.$cur_include[1]))
			error('Unable to process user include &lt;!-- pun_include "'.pun_htmlencode($cur_include[1]).'" --&gt; from template redirect.tpl. There is no such file in folder /include/user/.');

		ob_start();
		include PUN_ROOT.'include/user/'.$cur_include[1];
		$tpl_temp = ob_get_contents();
		$tpl_redir = str_replace($cur_include[0], $tpl_temp, $tpl_redir);
		ob_end_clean();
	}
	// END SUBST - <!-- pun_include "*" -->


	// Close the db connection (and free up any result data)
	$pun_db->close();

	exit($tpl_redir);
}


//
// Display a simple error message
//
function error()
{
	global $pun_config;

	/*
		Parse input parameters. Possible function signatures:
		error('Error message.');
		error(__FILE__, __LINE__);
		error('Error message.', __FILE__, __LINE__);
	*/
	$num_args = func_num_args();
	if ($num_args == 3)
	{
		$message = func_get_arg(0);
		$file = func_get_arg(1);
		$line = func_get_arg(2);
	}
	else if ($num_args == 2)
	{
		$file = func_get_arg(0);
		$line = func_get_arg(1);
	}
	else if ($num_args == 1)
		$message = func_get_arg(0);

	// Set a default title if the script failed before $pun_config could be populated
	if (empty($pun_config))
		$pun_config['o_board_title'] = 'PunBB';

	// Empty all output buffers and stop buffering
	while (@ob_end_clean());

	// "Restart" output buffering if we are using ob_gzhandler (since the gzip header is already sent)
	if (!empty($pun_config['o_gzip']) && extension_loaded('zlib') && (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false || strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'deflate') !== false))
		ob_start('ob_gzhandler');

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
<head>
<title>Error - <?php echo pun_htmlencode($pun_config['o_board_title']) ?></title>
</head>
<body style="margin: 40px; font: 85%/130% verdana, arial, sans-serif; color: #333;">

<h1>An error was encountered</h1>
<hr />
<?php

	if (isset($message))
		echo '<p>'.$message.'</p>'."\n";

	if ($num_args > 1)
	{
		if (defined('PUN_DEBUG'))
		{
			if (isset($file) && isset($line))
				echo '<p><em>The error occurred on line '.$line.' in '.$file.'</em></p>'."\n";

			$db_error = isset($GLOBALS['db']) ? $GLOBALS['db']->error() : array();
			if (!empty($db_error['error_msg']))
			{
				echo '<p><strong>Database reported:</strong> '.pun_htmlencode($db_error['error_msg']).(($db_error['error_no']) ? ' (Errno: '.$db_error['error_no'].')' : '').'.</p>'."\n";

				if ($db_error['error_sql'] != '')
					echo '<p><strong>Failed query:</strong> <code>'.pun_htmlencode($db_error['error_sql']).'</code></p>'."\n";
			}
		}
		else
			echo '<p><strong>Note:</strong> For detailed error information (necessary for troubleshooting), enable "DEBUG mode". To enable "DEBUG mode", open up the file include/essentials.php in a text editor and locate the line "//define(\'PUN_DEBUG\', 1);". It it located at the very top of the file below the software license preamble. Then remove the two slashes in the beginning of the line and save/upload the script. Once you\'ve solved the problem, it is recommended that "DEBUG mode" be turned off again (just add the two slashes back again).</p>'."\n";
	}

?>

</body>
</html>
<?php

	// If a database connection was established (before this error) we close it
	if (isset($GLOBALS['db']))
		$GLOBALS['db']->close();

	exit;
}


//
// Unset any variables instantiated as a result of register_globals being enabled
//
function pun_unregister_globals()
{
	$register_globals = @ini_get('register_globals');
	if ($register_globals === "" || $register_globals === "0" || strtolower($register_globals) === "off")
		return;

	// Prevent script.php?GLOBALS[foo]=bar
	if (isset($_REQUEST['GLOBALS']) || isset($_FILES['GLOBALS']))
		exit('I\'ll have a steak sandwich and... a steak sandwich.');

	// Variables that shouldn't be unset
	$no_unset = array('GLOBALS', '_GET', '_POST', '_COOKIE', '_REQUEST', '_SERVER', '_ENV', '_FILES');

	// Remove elements in $GLOBALS that are present in any of the superglobals
	$input = array_merge($_GET, $_POST, $_COOKIE, $_SERVER, $_ENV, $_FILES, isset($_SESSION) && is_array($_SESSION) ? $_SESSION : array());
	foreach ($input as $k => $v)
	{
		if (!in_array($k, $no_unset) && isset($GLOBALS[$k]))
		{
			unset($GLOBALS[$k]);
			unset($GLOBALS[$k]);	// Double unset to circumvent the zend_hash_del_key_or_index hole in PHP <4.4.3 and <5.1.4
		}
	}
}


// DEBUG FUNCTIONS BELOW

//
// Display executed queries (if enabled)
//
function get_saved_queries()
{
	global $pun_db, $lang_common;

	// Get the queries so that we can print them out
	$saved_queries = $pun_db->get_saved_queries();

	$output = '
<div id="pun-debug" class="main">

	<div class="main-head">
		<h2><span>'.$lang_common['Debug table'].'</span></h2>
	</div>

	<div class="main-content debug">
		<table cellspacing="0" summary="Database query performance information">
			<thead>
				<tr>
					<th class="tcl" scope="col">Time (s)</th>
					<th class="tcr" scope="col">Query</th>
				</tr>
			</thead>
			<tbody>
';

	$query_time_total = 0.0;
	while (list(, $cur_query) = @each($saved_queries))
	{
		$query_time_total += $cur_query[1];

		$output .= '
				<tr>
					<td class="tcl">'.(($cur_query[1] != 0) ? $cur_query[1] : '&#160;').'</td>
					<td class="tcr">'.pun_htmlencode($cur_query[0]).'</td>
				</tr>
';

	}

	$output .= '
				<tr class="totals">
					<td class="tcl"><em>'.$query_time_total.'</em></td>
					<td class="tcr"><em>Total query time</em></td>
				</tr>
			</tbody>
		</table>
	</div>
</div>
';

	return $output;
}


//
// Dump contents of variable(s)
//
function dump()
{
	echo '<pre>';

	$num_args = func_num_args();

	for ($i = 0; $i < $num_args; ++$i)
	{
		print_r(func_get_arg($i));
		echo "\n\n";
	}

	echo '</pre>';
	exit;
}
