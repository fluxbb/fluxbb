<?php

/**
 * Copyright (C) 2008-2011 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */


//
// Collect some board statistics
//
function fetch_board_stats()
{
	global $cache, $db;

	$stats = $cache->get('boardstats');
	if ($stats === Flux_Cache::NOT_FOUND)
	{
		$stats = array();

		// Count total registered users
		$query = $db->select(array('total_users' => '(COUNT(u.id) - 1) AS total_users'), 'users AS u');
		$query->where = 'u.group_id != :group_id';

		$params = array(':group_id' => PUN_UNVERIFIED);

		$stats = array_merge($stats, current($query->run($params)));
		unset ($query, $params);

		// Fetch last user
		$query = $db->select(array('id' => 'u.id', 'username' => 'u.username'), 'users AS u');
		$query->where = 'group_id != :group_id';
		$query->order = array('registered' => 'u.registered DESC');
		$query->limit = 1;

		$params = array(':group_id' => PUN_UNVERIFIED);

		$stats['last_user'] = current($query->run($params));
		unset ($query, $params);

		$cache->set('boardstats', $stats);
	}

	return $stats;
}


//
// Return current timestamp (with microseconds) as a float
//
function get_microtime()
{
	list($usec, $sec) = explode(' ', microtime());
	return ((float)$usec + (float)$sec);
}

//
// Cookie stuff!
//
function check_cookie(&$pun_user)
{
	global $db, $db_type, $pun_config, $flux_config;

	$now = time();

	// If the cookie is set and it matches the correct pattern, then read the values from it
	if (isset($_COOKIE[$flux_config['cookie']['name']]) && preg_match('%^(\d+)\|([0-9a-fA-F]+)\|(\d+)\|([0-9a-fA-F]+)$%', $_COOKIE[$flux_config['cookie']['name']], $matches))
	{
		$cookie = array(
			'user_id'			=> intval($matches[1]),
			'password_hash' 	=> $matches[2],
			'expiration_time'	=> intval($matches[3]),
			'cookie_hash'		=> $matches[4],
		);
	}

	// If it has a non-guest user, and hasn't expired
	if (isset($cookie) && $cookie['user_id'] > 1 && $cookie['expiration_time'] > $now)
	{
		// If the cookie has been tampered with
		if (forum_hmac($cookie['user_id'].'|'.$cookie['expiration_time'], $flux_config['cookie']['seed'].'_cookie_hash') != $cookie['cookie_hash'])
		{
			$expire = $now + 31536000; // The cookie expires after a year
			pun_setcookie(1, pun_hash(uniqid(rand(), true)), $expire);
			set_default_user();

			return;
		}

		// Check if there's a user with the user ID and password hash from the cookie
		$query = $db->select(array('user' => 'u.*', 'group' => 'g.*', 'logged' => 'o.logged', 'idle' => 'o.idle'), 'users AS u');

		$query->innerJoin('g', 'groups AS g', 'u.group_id = g.g_id');

		$query->leftJoin('o', 'online AS o', 'o.user_id = u.id');

		$query->where = 'u.id = :user_id';

		$params = array(':user_id' => $cookie['user_id']);

		$result = $query->run($params);
		unset ($query, $params);

		// If the password is invalid
		if (empty($result) || forum_hmac($result[0]['password'], $flux_config['cookie']['seed'].'_password_hash') !== $cookie['password_hash'])
		{
			$expire = $now + 31536000; // The cookie expires after a year
			pun_setcookie(1, pun_hash(uniqid(rand(), true)), $expire);
			set_default_user();

			return;
		}

		$pun_user = $result[0];
		unset ($result);

		// Send a new, updated cookie with a new expiration timestamp
		$expire = ($cookie['expiration_time'] > $now + $pun_config['o_timeout_visit']) ? $now + 1209600 : $now + $pun_config['o_timeout_visit'];
		pun_setcookie($pun_user['id'], $pun_user['password'], $expire);

		// Set a default language if the user selected language no longer exists
		if (!file_exists(PUN_ROOT.'lang/'.$pun_user['language']))
			$pun_user['language'] = $pun_config['o_default_lang'];

		// Set a default style if the user selected style no longer exists
		if (!file_exists(PUN_ROOT.'style/'.$pun_user['style'].'.css'))
			$pun_user['style'] = $pun_config['o_default_style'];

		if (!$pun_user['disp_topics'])
			$pun_user['disp_topics'] = $pun_config['o_disp_topics_default'];
		if (!$pun_user['disp_posts'])
			$pun_user['disp_posts'] = $pun_config['o_disp_posts_default'];

		// Define this if you want this visit to affect the online list and the users last visit data
		if (!defined('PUN_QUIET_VISIT'))
		{
			// Update the online list
			if (!$pun_user['logged'])
			{
				$pun_user['logged'] = $now;

				// REPLACE INTO avoids a user having two rows in the online table
				$query = $db->replace(array('user_id' => ':user_id', 'logged' => ':logged'), 'online', array('ident' => ':ident'));
				$params = array(':user_id' => $pun_user['id'], ':ident' => $pun_user['username'], ':logged' => $pun_user['logged']);

				$query->run($params);
				unset ($query, $params);

				// Reset tracked topics
				set_tracked_topics(null);
			}
			else
			{
				// Special case: We've timed out, but no other user has browsed the forums since we timed out
				if ($pun_user['logged'] < ($now-$pun_config['o_timeout_visit']))
				{
					$query = $db->update(array('last_visit' => ':logged'), 'users');
					$query->where = 'id = :user_id';

					$params = array(':logged' => $pun_user['logged'], ':user_id' => $pun_user['id']);

					$query->run($params);
					unset ($query, $params);

					$pun_user['last_visit'] = $pun_user['logged'];
				}

				$query = $db->update(array('logged' => ':now', 'idle' => '0'), 'online');
				$query->where = 'user_id = :user_id';

				$params = array(':now' => $now, ':user_id' => $pun_user['id']);

				$query->run($params);
				unset ($query, $params);

				// Update tracked topics with the current expire time
				if (isset($_COOKIE[$flux_config['cookie']['name'].'_track']))
					forum_setcookie($flux_config['cookie']['name'].'_track', $_COOKIE[$flux_config['cookie']['name'].'_track'], $now + $pun_config['o_timeout_visit']);
			}
		}
		else
		{
			if (!$pun_user['logged'])
				$pun_user['logged'] = $pun_user['last_visit'];
		}

		$pun_user['is_guest'] = false;
		$pun_user['is_admmod'] = $pun_user['g_id'] == PUN_ADMIN || $pun_user['g_moderator'] == '1';
	}
	else
		set_default_user();
}


//
// Converts the CDATA end sequence ]]> into ]]&gt;
//
function escape_cdata($str)
{
	return str_replace(']]>', ']]&gt;', $str);
}


//
// Authenticates the provided username and password against the user database
// $user can be either a user ID (integer) or a username (string)
// $password can be either a plaintext password or a password hash including salt ($password_is_hash must be set accordingly)
//
function authenticate_user($user, $password, $password_is_hash = false)
{
	global $db, $pun_user;

	// Check if there's a user matching $user and $password
	$query = $db->select(array('users' => 'u.*', 'group' => 'g.*', 'logged' => 'o.logged', 'idle' => 'o.idle'), 'users AS u');

	$query->innerJoin('g', 'groups AS g', 'g.g_id = u.group_id');

	$query->leftJoin('o', 'online AS o', 'o.user_id = u.id');

	$params = array();

	if (is_int($user))
	{
		$query->where = 'u.id = :user_id';
		$params[':user_id'] = $user;
	}
	else
	{
		$query->where = 'u.username = :username';
		$params[':username'] = $user;
	}

	$result = $query->run($params);
	if (empty($result))
	{
		set_default_user();
		return;
	}

	$pun_user = $result[0];
	unset ($result, $query, $params);

	if (($password_is_hash && $password != $pun_user['password']) ||
		(!$password_is_hash && pun_hash($password) != $pun_user['password']))
		set_default_user();
	else
		$pun_user['is_guest'] = false;
}


//
// Try to determine the current URL
//
function get_current_url($max_length = 0)
{
	$protocol = get_current_protocol();
	$port = (isset($_SERVER['SERVER_PORT']) && (($_SERVER['SERVER_PORT'] != '80' && $protocol == 'http') || ($_SERVER['SERVER_PORT'] != '443' && $protocol == 'https')) && strpos($_SERVER['HTTP_HOST'], ':') === false) ? ':'.$_SERVER['SERVER_PORT'] : '';

	$url = urldecode($protocol.'://'.$_SERVER['HTTP_HOST'].$port.$_SERVER['REQUEST_URI']);

	if (strlen($url) <= $max_length || $max_length == 0)
		return $url;

	// We can't find a short enough url
	return null;
}


//
// Fetch the current protocol in use - http or https
//
function get_current_protocol()
{
	$protocol = 'http';

	// Check if the server is claiming to using HTTPS
	if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off')
		$protocol = 'https';

	// If we are behind a reverse proxy try to decide which protocol it is using
	if (defined('FORUM_BEHIND_REVERSE_PROXY'))
	{
		// Check if we are behind a Microsoft based reverse proxy
		if (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) != 'off')
			$protocol = 'https';

		// Check if we're behind a "proper" reverse proxy, and what protocol it's using
		if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']))
			$protocol = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']);
	}

	return $protocol;
}

//
// Fetch the base_url, optionally support HTTPS and HTTP
//
function get_base_url($support_https = false)
{
	global $flux_config;
	static $base_url;

	if (!$support_https)
		return $flux_config['base_url'];

	if (!isset($base_url))
	{
		// Make sure we are using the correct protocol
		$base_url = str_replace(array('http://', 'https://'), get_current_protocol().'://', $flux_config['base_url']);
	}

	return $base_url;
}


//
// Fill $pun_user with default values (for guests)
//
function set_default_user()
{
	global $db, $db_type, $pun_user, $pun_config;

	$remote_addr = get_remote_address();

	// Fetch guest user
	$query = $db->select(array('user' => 'u.*', 'group' => 'g.*', 'logged' => 'o.logged', 'last_post' => 'o.last_post', 'last_search' => 'o.last_search'), 'users AS u');

	$query->innerJoin('g', 'groups AS g', 'u.group_id = g.g_id');

	$query->leftJoin('o', 'online AS o', 'o.ident = :ident');

	$query->where = 'u.id = 1';

	$params = array(':ident' => $remote_addr);

	$result = $query->run($params);
	unset ($query, $params);

	if (empty($result))
		exit('Unable to fetch guest information. Your database must contain both a guest user and a guest user group.');

	$pun_user = $result[0];
	unset ($result);

	// Update online list
	if (!$pun_user['logged'])
	{
		$pun_user['logged'] = time();

		// REPLACE INTO avoids a user having two rows in the online table
		$query = $db->replace(array('user_id' => '1', 'logged' => ':logged'), 'online', array('ident' => ':ident'));
		$params = array(':ident' => $remote_addr, ':logged' => $pun_user['logged']);

		$query->run($params);
		unset ($query, $params);
	}
	else {
		$query = $db->update(array('logged' => ':now'), 'online');
		$query->where = 'ident = :ident';

		$params = array(':now' => time(), ':ident' => $remote_addr);

		$query->run($params);
		unset ($query, $params);
	}

	$pun_user['disp_topics'] = $pun_config['o_disp_topics_default'];
	$pun_user['disp_posts'] = $pun_config['o_disp_posts_default'];
	$pun_user['timezone'] = $pun_config['o_default_timezone'];
	$pun_user['dst'] = $pun_config['o_default_dst'];
	$pun_user['language'] = $pun_config['o_default_lang'];
	$pun_user['style'] = $pun_config['o_default_style'];
	$pun_user['is_guest'] = true;
	$pun_user['is_admmod'] = false;
}


//
// SHA1 HMAC with PHP 4 fallback
//
function forum_hmac($data, $key, $raw_output = false)
{
	if (function_exists('hash_hmac'))
		return hash_hmac('sha1', $data, $key, $raw_output);

	// If key size more than blocksize then we hash it once
	if (strlen($key) > 64)
		$key = pack('H*', sha1($key)); // we have to use raw output here to match the standard

	// Ensure we're padded to exactly one block boundary
	$key = str_pad($key, 64, chr(0x00));

	$hmac_opad = str_repeat(chr(0x5C), 64);
	$hmac_ipad = str_repeat(chr(0x36), 64);

	// Do inner and outer padding
	for ($i = 0;$i < 64;$i++) {
		$hmac_opad[$i] = $hmac_opad[$i] ^ $key[$i];
		$hmac_ipad[$i] = $hmac_ipad[$i] ^ $key[$i];
	}

	// Finally, calculate the HMAC
	$hash = sha1($hmac_opad.pack('H*', sha1($hmac_ipad.$data)));

	// If we want raw output then we need to pack the final result
	if ($raw_output)
		$hash = pack('H*', $hash);

	return $hash;
}


//
// Set a cookie, FluxBB style!
// Wrapper for forum_setcookie
//
function pun_setcookie($user_id, $password_hash, $expire)
{
	global $flux_config;

	forum_setcookie($flux_config['cookie']['name'], $user_id.'|'.forum_hmac($password_hash, $flux_config['cookie']['seed'].'_password_hash').'|'.$expire.'|'.forum_hmac($user_id.'|'.$expire, $flux_config['cookie']['seed'].'_cookie_hash'), $expire);
}


//
// Set a cookie, FluxBB style!
//
function forum_setcookie($name, $value, $expire)
{
	global $flux_config;

	// Enable sending of a P3P header
	header('P3P: CP="CUR ADM"');

	if (version_compare(PHP_VERSION, '5.2.0', '>='))
		setcookie($name, $value, $expire, $flux_config['cookie']['path'], $flux_config['cookie']['domain'], $flux_config['cookie']['secure'], true);
	else
		setcookie($name, $value, $expire, $flux_config['cookie']['path'].'; HttpOnly', $flux_config['cookie']['domain'], $flux_config['cookie']['secure']);
}


//
// Check whether the connecting user is banned (and delete any expired bans while we're at it)
//
function check_bans()
{
	global $cache, $db, $pun_config, $lang, $pun_user, $pun_bans;

	// Admins and moderators aren't affected
	if ($pun_user['is_admmod'] || !$pun_bans)
		return;

	// Add a dot or a colon (depending on IPv4/IPv6) at the end of the IP address to prevent banned address
	// 192.168.0.5 from matching e.g. 192.168.0.50
	$user_ip = get_remote_address();
	$user_ip .= (strpos($user_ip, '.') !== false) ? '.' : ':';

	$bans_altered = false;
	$is_banned = false;

	$query = $db->delete('bans AS b');
	$query->where = 'b.id = :ban_id';

	foreach ($pun_bans as $cur_ban)
	{
		// Has this ban expired?
		if ($cur_ban['expire'] != '' && $cur_ban['expire'] <= time())
		{
			$params = array(':ban_id' => $cur_ban['id']);

			$query->run($params);
			unset ($params);

			$bans_altered = true;
			continue;
		}

		if ($cur_ban['username'] != '' && utf8_strtolower($pun_user['username']) == utf8_strtolower($cur_ban['username']))
			$is_banned = true;

		if ($cur_ban['ip'] != '')
		{
			$cur_ban_ips = explode(' ', $cur_ban['ip']);

			$num_ips = count($cur_ban_ips);
			for ($i = 0; $i < $num_ips; ++$i)
			{
				// Add the proper ending to the ban
				if (strpos($user_ip, '.') !== false)
					$cur_ban_ips[$i] = $cur_ban_ips[$i].'.';
				else
					$cur_ban_ips[$i] = $cur_ban_ips[$i].':';

				if (substr($user_ip, 0, strlen($cur_ban_ips[$i])) == $cur_ban_ips[$i])
				{
					$is_banned = true;
					break;
				}
			}
		}

		if ($is_banned)
		{
			$query = $db->delete('online');
			$query->where = 'ident = :username';

			$params = array(':username' => $pun_user['username']);

			$query->run($params);
			unset ($query, $params);

			message($lang->t('Ban message').' '.(($cur_ban['expire'] != '') ? $lang->t('Ban message 2').' '.strtolower(format_time($cur_ban['expire'], true)).'. ' : '').(($cur_ban['message'] != '') ? $lang->t('Ban message 3').'<br /><br /><strong>'.pun_htmlspecialchars($cur_ban['message']).'</strong><br /><br />' : '<br /><br />').$lang->t('Ban message 4').' <a href="mailto:'.$pun_config['o_admin_email'].'">'.$pun_config['o_admin_email'].'</a>.', true);
		}
	}

	unset ($query);

	// If we removed any expired bans during our run-through, we need to regenerate the bans cache
	if ($bans_altered)
		$cache->delete('bans');
}


//
// Check username
//
function check_username($username, $exclude_id = null)
{
	global $db, $pun_config, $errors, $lang, $lang, $pun_bans;

	$lang->load('prof_reg');
	$lang->load('register');

	// Convert multiple whitespace characters into one (to prevent people from registering with indistinguishable usernames)
	$username = preg_replace('%\s+%s', ' ', $username);

	// Validate username
	if (pun_strlen($username) < 2)
		$errors[] = $lang->t('Username too short');
	else if (pun_strlen($username) > 25) // This usually doesn't happen since the form element only accepts 25 characters
		$errors[] = $lang->t('Username too long');
	else if (!strcasecmp($username, 'Guest') || !strcasecmp($username, $lang->t('Guest')))
		$errors[] = $lang->t('Username guest');
	else if (preg_match('%[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}%', $username) || preg_match('%((([0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}:[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){5}:([0-9A-Fa-f]{1,4}:)?[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){4}:([0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){3}:([0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){2}:([0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(([0-9A-Fa-f]{1,4}:){0,5}:((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(::([0-9A-Fa-f]{1,4}:){0,5}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|([0-9A-Fa-f]{1,4}::([0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})|(::([0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){1,7}:))%', $username))
		$errors[] = $lang->t('Username IP');
	else if ((strpos($username, '[') !== false || strpos($username, ']') !== false) && strpos($username, '\'') !== false && strpos($username, '"') !== false)
		$errors[] = $lang->t('Username reserved chars');
	else if (preg_match('%(?:\[/?(?:b|u|s|ins|del|em|i|h|colou?r|quote|code|img|url|email|list|\*|topic|post|forum|user)\]|\[(?:img|url|quote|list)=)%i', $username))
		$errors[] = $lang->t('Username BBCode');

	// Check username for any censored words
	if ($pun_config['o_censoring'] == '1' && censor_words($username) != $username)
		$errors[] = $lang->t('Username censor');

	// Check that the username (or a too similar username) is not already registered
	$query = $db->select(array('username' => 'u.username'), 'users AS u');
	$query->where = '(u.username LIKE :username OR u.username LIKE :clean_username) AND u.id > 1';

	$params = array(':username' => $username, ':clean_username' => ucp_preg_replace('%[^\p{L}\p{N}]%u', '', $username));

	if ($exclude_id)
	{
		$query->where .= ' AND u.id != :exclude_id';
		$params[':exclude_id'] = $exclude_id;
	}

	$result = $query->run($params);
	if (!empty($result))
		$errors[] = $lang->t('Username dupe 1').' '.pun_htmlspecialchars($result[0]['username']).'. '.$lang->t('Username dupe 2');

	unset ($query, $params, $result);

	// Check username for any banned usernames
	foreach ($pun_bans as $cur_ban)
	{
		if ($cur_ban['username'] != '' && utf8_strtolower($username) == utf8_strtolower($cur_ban['username']))
		{
			$errors[] = $lang->t('Banned username');
			break;
		}
	}
}


//
// Update "Users online"
//
function update_users_online()
{
	global $db, $pun_config;

	$now = time();

	// Fetch all online list entries that are older than "o_timeout_online"
	$query = $db->select(array('user_id' => 'o.user_id', 'ident' => 'o.ident', 'logged' => 'o.logged', 'idle' => 'o.idle'), 'online AS o');
	$query->where = 'logged < :logged';

	$params = array(':logged' => $now - $pun_config['o_timeout_online']);

	$result = $query->run($params);
	unset ($query, $params);

	// Query for deleting an online entry
	$query_delete = $db->delete('online');
	$query_delete->where = 'ident = :ident';

	// Query for updating users last visit time
	$query_update_user = $db->update(array('last_visit' => ':logged'), 'users');
	$query_update_user->where = 'id = :user_id';

	// Query for updating online idle
	$query_update_online = $db->update(array('idle' => 1), 'online');
	$query_update_online->where = 'user_id = :user_id';

	foreach ($result as $cur_user)
	{
		// If the entry is a guest, delete it
		if ($cur_user['user_id'] == '1')
		{
			$params = array(':ident' => $cur_user['ident']);

			$query_delete->run($params);
			unset ($params);
		}
		else
		{
			// If the entry is older than "o_timeout_visit", update last_visit for the user in question, then delete him/her from the online list
			if ($cur_user['logged'] < ($now - $pun_config['o_timeout_visit']))
			{
				$params = array(':logged' => $cur_user['logged'], ':user_id' => $cur_user['user_id']);

				$query_update_user->run($params);
				unset ($params);

				$params = array(':ident' => $cur_user['ident']);

				$query_delete->run($params);
				unset ($params);
			}
			else if ($cur_user['idle'] == '0')
			{
				$params = array(':user_id' => $cur_user['user_id']);

				$query_update_online->run($params);
				unset ($params);
			}
		}
	}

	unset ($result, $query_delete, $query_update_user, $query_update_online);
}


//
// Display the profile navigation menu
//
function generate_profile_menu($page = '')
{
	global $lang, $pun_config, $pun_user, $id;

	$lang->load('profile');

?>
<div id="profile" class="block2col">
	<div class="blockmenu">
		<h2><span><?php echo $lang->t('Profile menu') ?></span></h2>
		<div class="box">
			<div class="inbox">
				<ul>
					<li<?php if ($page == 'essentials') echo ' class="isactive"'; ?>><a href="profile.php?section=essentials&amp;id=<?php echo $id ?>"><?php echo $lang->t('Section essentials') ?></a></li>
					<li<?php if ($page == 'personal') echo ' class="isactive"'; ?>><a href="profile.php?section=personal&amp;id=<?php echo $id ?>"><?php echo $lang->t('Section personal') ?></a></li>
					<li<?php if ($page == 'messaging') echo ' class="isactive"'; ?>><a href="profile.php?section=messaging&amp;id=<?php echo $id ?>"><?php echo $lang->t('Section messaging') ?></a></li>
<?php if ($pun_config['o_avatars'] == '1' || $pun_config['o_signatures'] == '1'): ?>					<li<?php if ($page == 'personality') echo ' class="isactive"'; ?>><a href="profile.php?section=personality&amp;id=<?php echo $id ?>"><?php echo $lang->t('Section personality') ?></a></li>
<?php endif; ?>					<li<?php if ($page == 'display') echo ' class="isactive"'; ?>><a href="profile.php?section=display&amp;id=<?php echo $id ?>"><?php echo $lang->t('Section display') ?></a></li>
					<li<?php if ($page == 'privacy') echo ' class="isactive"'; ?>><a href="profile.php?section=privacy&amp;id=<?php echo $id ?>"><?php echo $lang->t('Section privacy') ?></a></li>
<?php if ($pun_user['g_id'] == PUN_ADMIN || ($pun_user['g_moderator'] == '1' && $pun_user['g_mod_ban_users'] == '1')): ?>					<li<?php if ($page == 'admin') echo ' class="isactive"'; ?>><a href="profile.php?section=admin&amp;id=<?php echo $id ?>"><?php echo $lang->t('Section admin') ?></a></li>
<?php endif; ?>				</ul>
			</div>
		</div>
	</div>
<?php

}


//
// Outputs markup to display a user's avatar
//
function generate_avatar_markup($user_id)
{
	global $pun_config;

	$filetypes = array('jpg', 'gif', 'png');
	$avatar_markup = '';

	foreach ($filetypes as $cur_type)
	{
		$path = $pun_config['o_avatars_dir'].'/'.$user_id.'.'.$cur_type;

		if (file_exists(PUN_ROOT.$path) && $img_size = getimagesize(PUN_ROOT.$path))
		{
			$avatar_markup = '<img src="'.pun_htmlspecialchars(get_base_url(true).'/'.$path.'?m='.filemtime(PUN_ROOT.$path)).'" '.$img_size[3].' alt="" />';
			break;
		}
	}

	return $avatar_markup;
}


//
// Generate browser's title
//
function generate_page_title($page_title, $p = null)
{
	global $pun_config, $lang;

	$page_title = array_reverse($page_title);

	if ($p != null)
		$page_title[0] .= ' ('.$lang->t('Page', forum_number_format($p)).')';

	$crumbs = implode($lang->t('Title separator'), $page_title);

	return $crumbs;
}


//
// Save array of tracked topics in cookie
//
function set_tracked_topics($tracked_topics)
{
	global $flux_config, $pun_config;

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

		// Enforce a byte size limit (4096 minus some space for the cookie name - defaults to 4048)
		if (strlen($cookie_data) > FORUM_MAX_COOKIE_SIZE)
		{
			$cookie_data = substr($cookie_data, 0, FORUM_MAX_COOKIE_SIZE);
			$cookie_data = substr($cookie_data, 0, strrpos($cookie_data, ';')).';';
		}
	}

	forum_setcookie($flux_config['cookie']['name'].'_track', $cookie_data, time() + $pun_config['o_timeout_visit']);
	$_COOKIE[$flux_config['cookie']['name'].'_track'] = $cookie_data; // Set it directly in $_COOKIE as well
}


//
// Extract array of tracked topics from cookie
//
function get_tracked_topics()
{
	global $flux_config;

	$cookie_data = isset($_COOKIE[$flux_config['cookie']['name'].'_track']) ? $_COOKIE[$flux_config['cookie']['name'].'_track'] : false;
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
		$timestamp = intval(substr($t, strpos($t, '=') + 1));
		if ($id > 0 && $timestamp > 0)
			$tracked_topics[$type][$id] = $timestamp;
	}

	return $tracked_topics;
}


//
// Update posts, topics, last_post, last_post_id and last_poster for a forum
//
function update_forum($forum_id)
{
	global $db;

	$query = $db->select(array('num_topics' => 'COUNT(t.id) AS num_topics', 'num_replies' => 'SUM(t.num_replies) AS num_replies'), 'topics AS t');
	$query->where = 't.forum_id = :forum_id';

	$params = array(':forum_id' => $forum_id);

	$result = $query->run($params);

	$num_topics = $result[0]['num_topics'];
	$num_posts = $result[0]['num_topics'] + $result[0]['num_replies'];

	unset ($result, $query, $params);

	$query = $db->select(array('last_post' => 't.last_post AS posted', 'last_post_id' => 't.last_post_id AS id', 'last_poster' => 't.last_poster AS poster'), 'topics AS t');
	$query->where = 't.forum_id = :forum_id AND t.moved_to IS NULL';
	$query->order = array('last_post' => 't.last_post DESC');
	$query->limit = 1;

	$params = array(':forum_id' => $forum_id);

	$result = $query->run($params);
	unset ($query, $params);

	$query = $db->update(array('num_topics' => ':num_topics', 'num_posts' => ':num_posts', 'last_post' => ':last_post', 'last_post_id' => ':last_post_id', 'last_poster' => ':last_poster'), 'forums');
	$query->where = 'id = :forum_id';

	// There are topics in the forum
	if (!empty($result))
	{
		$last_post = $result[0];
		unset ($result);

		$params = array(':num_topics' => $num_topics, ':num_posts' => $num_posts, ':last_post' => $last_post['posted'], ':last_post_id' => $last_post['id'], ':last_poster' => $last_post['poster'], ':forum_id' => $forum_id);
	}
	// There are no topics
	else
	{
		unset ($result);

		$params = array(':num_topics' => $num_topics, ':num_posts' => $num_posts, ':last_post' => null, ':last_post_id' => null, ':last_poster' => null, ':forum_id' => $forum_id);
	}

	$query->run($params);
	unset ($query, $params);
}


//
// Deletes any avatars owned by the specified user ID
//
function delete_avatar($user_id)
{
	global $pun_config;

	$filetypes = array('jpg', 'gif', 'png');

	// Delete user avatar
	foreach ($filetypes as $cur_type)
	{
		if (file_exists(PUN_ROOT.$pun_config['o_avatars_dir'].'/'.$user_id.'.'.$cur_type))
			@unlink(PUN_ROOT.$pun_config['o_avatars_dir'].'/'.$user_id.'.'.$cur_type);
	}
}


//
// Delete a topic and all of it's posts
//
function delete_topic($topic_id)
{
	global $db;

	// Delete the topic and any redirect topics
	$query = $db->delete('topics');
	$query->where = 'id = :topic_id OR moved_to = :topic_id';

	$params = array(':topic_id' => $topic_id);

	$query->run($params);
	unset($query, $params);

	// Create a list of the post IDs in this topic
	$query = $db->select(array('id' => 'p.id'), 'posts AS p');
	$query->where = 'p.topic_id = :topic_id';

	$params = array(':topic_id' => $topic_id);

	$result = $query->run($params);

	$post_ids = array();
	foreach ($result as $row)
		$post_ids[] = $row['id'];
	unset($query, $params, $result);

	// Make sure we have a list of post IDs
	if (!empty($post_ids))
	{
		strip_search_index($post_ids);

		// Delete posts in topic
		$query = $db->delete('posts');
		$query->where = 'topic_id = :topic_id';

		$params = array(':topic_id' => $topic_id);

		$query->run($params);
		unset($query, $params);
	}

	// Delete any subscriptions for this topic
	$query = $db->delete('topic_subscriptions');
	$query->where = 'topic_id = :topic_id';

	$params = array(':topic_id' => $topic_id);

	$query->run($params);
	unset($query, $params);
}


//
// Delete a single post
//
function delete_post($post_id, $topic_id)
{
	global $db;

	$query = $db->select(array('id' => 'p.id', 'poster' => 'p.poster', 'posted' => 'p.posted'), 'posts AS p');
	$query->where = 'p.topic_id = :topic_id';
	$query->order = array('p.id DESC');
	$query->limit = 2;

	$params = array(':topic_id' => $topic_id);

	$result = $query->run($params);

	if (count($result) > 0)
		$last_id = $result[0]['id'];

	if (count($result) > 1)
		list($second_last_id, $second_poster, $second_posted) = array($result[1]['id'], $result[1]['poster'], $result[1]['posted']);
	else
		list($second_last_id, $second_poster, $second_posted) = array('', '', '');

	unset($query, $params, $result);

	// Delete the post
	$query = $db->delete('posts');
	$query->where = 'id = :post_id';

	$params = array(':post_id' => $post_id);

	$query->run($params);
	unset($query, $params);

	strip_search_index($post_id);

	// Count number of replies in the topic
	$query = $db->select(array('post_count' => 'COUNT(p.id) AS post_count'), 'posts AS p');
	$query->where = 'topic_id = :topic_id';

	$params = array(':topic_id' => $topic_id);

	$result = $query->run($params);
	$num_replies = $result[0]['post_count'] - 1;
	unset($query, $params, $result);

	// If the message we deleted is the most recent in the topic (at the end of the topic)
	if ($last_id == $post_id)
	{
		// If there is a $second_last_id there is more than 1 reply to the topic
		if (!empty($second_last_id))
		{
			$query = $db->update(array('last_post' => ':second_posted', 'last_post_id' => ':second_last_id', 'last_poster' => ':second_poster', 'num_replies' => ':num_replies'), 'topics');
			$query->where = 'id = :topic_id';

			$params = array(':second_posted' => $second_posted, ':second_last_id' => $second_last_id, ':second_poster' => $second_poster, ':num_replies' => $num_replies, ':topic_id' => $topic_id);

			$query->run($params);
			unset($query, $params);
		}
		else
		{
			// We deleted the only reply, so now last_post/last_post_id/last_poster is posted/id/poster from the topic itself
			$query = $db->update(array('last_post' => 'posted', 'last_post_id' => 'id', 'last_poster' => 'poster', 'num_replies' => ':num_replies'), 'topics');
			$query->where = 'id = :topic_id';

			$params = array(':num_replies' => $num_replies, ':topic_id' => $topic_id);

			$query->run($params);
			unset($query, $params);
		}
	}
	else
	{
		// Otherwise we just decrement the reply counter
		$query = $db->update(array('num_replies' => ':num_replies'), 'topics');
		$query->where = 'id = :topic_id';

		$params = array(':num_replies' => $num_replies, ':topic_id' => $topic_id);

		$query->run($params);
		unset($query, $params);
	}
}


//
// Replace censored words in $text
//
function censor_words($text)
{
	global $cache, $db;
	static $censors;

	// If not already built in a previous call, build an array of censor words and their replacement text
	if (!isset($censors))
	{
		$censors = $cache->get('censors');
		if ($censors === Flux_Cache::NOT_FOUND)
		{
			$censors = array();

			$query = $db->select(array('search_for' => 'c.search_for', 'replace_with' => 'c.replace_with'), 'censoring AS c');
			$params = array();

			$result = $query->run($params);
			foreach ($result as $cur_censor)
			{
				$cur_censor['search_for'] = '/(?<=[^\p{L}\p{N}])('.str_replace('\*', '[\p{L}\p{N}]*?', preg_quote($cur_censor['search_for'], '/')).')(?=[^\p{L}\p{N}])/iu';
				$censors[$cur_censor['search_for']] = $cur_censor['replace_with'];
			}

			unset ($result, $query, $params);
			$cache->set('censors', $censors);
		}
	}

	if (!empty($censors))
		$text = substr(ucp_preg_replace(array_keys($censors), array_values($censors), ' '.$text.' '), 1, -1);

	return $text;
}


//
// Determines the correct title for $user
// $user must contain the elements 'username', 'title', 'posts', 'g_id' and 'g_user_title'
//
function get_title($user)
{
	global $cache, $db, $pun_config, $pun_bans, $lang;
	static $ban_list, $pun_ranks;

	// If not already built in a previous call, build an array of lowercase banned usernames
	if (empty($ban_list))
	{
		$ban_list = array();

		foreach ($pun_bans as $cur_ban)
			$ban_list[] = strtolower($cur_ban['username']);
	}

	// If not already loaded in a previous call, load the cached ranks
	if ($pun_config['o_ranks'] == '1' && !isset($pun_ranks))
	{
		$pun_ranks = $cache->get('ranks');
		if ($pun_ranks === Flux_Cache::NOT_FOUND)
		{
			$pun_ranks = array();

			// Get the rank list from the DB
			$query = $db->select(array('ranks' => 'r.*'), 'ranks AS r');
			$query->order = array('min_posts' => 'r.min_posts ASC');

			$params = array();

			$pun_ranks = $query->run($params);
			unset ($query, $params);

			$cache->set('ranks', $pun_ranks);
		}
	}

	// If the user has a custom title
	if ($user['title'] != '')
		$user_title = pun_htmlspecialchars($user['title']);
	// If the user is banned
	else if (in_array(strtolower($user['username']), $ban_list))
		$user_title = $lang->t('Banned');
	// If the user group has a default user title
	else if ($user['g_user_title'] != '')
		$user_title = pun_htmlspecialchars($user['g_user_title']);
	// If the user is a guest
	else if ($user['g_id'] == PUN_GUEST)
		$user_title = $lang->t('Guest');
	else
	{
		// Are there any ranks?
		if ($pun_config['o_ranks'] == '1' && !empty($pun_ranks))
		{
			foreach ($pun_ranks as $cur_rank)
			{
				if ($user['num_posts'] >= $cur_rank['min_posts'])
					$user_title = pun_htmlspecialchars($cur_rank['rank']);
			}
		}

		// If the user didn't "reach" any rank (or if ranks are disabled), we assign the default
		if (!isset($user_title))
			$user_title = $lang->t('Member');
	}

	return $user_title;
}


//
// Generate a string with numbered links (for multipage scripts)
//
function paginate($num_pages, $cur_page, $link)
{
	global $lang;

	$pages = array();
	$link_to_all = false;

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
			$pages[] = '<a'.(empty($pages) ? ' class="item1"' : '').' href="'.$link.'&amp;p='.($cur_page - 1).'">'.$lang->t('Previous').'</a>';

		if ($cur_page > 3)
		{
			$pages[] = '<a'.(empty($pages) ? ' class="item1"' : '').' href="'.$link.'&amp;p=1">1</a>';

			if ($cur_page > 5)
				$pages[] = '<span class="spacer">'.$lang->t('Spacer').'</span>';
		}

		// Don't ask me how the following works. It just does, OK? :-)
		for ($current = ($cur_page == 5) ? $cur_page - 3 : $cur_page - 2, $stop = ($cur_page + 4 == $num_pages) ? $cur_page + 4 : $cur_page + 3; $current < $stop; ++$current)
		{
			if ($current < 1 || $current > $num_pages)
				continue;
			else if ($current != $cur_page || $link_to_all)
				$pages[] = '<a'.(empty($pages) ? ' class="item1"' : '').' href="'.$link.'&amp;p='.$current.'">'.forum_number_format($current).'</a>';
			else
				$pages[] = '<strong'.(empty($pages) ? ' class="item1"' : '').'>'.forum_number_format($current).'</strong>';
		}

		if ($cur_page <= ($num_pages-3))
		{
			if ($cur_page != ($num_pages-3) && $cur_page != ($num_pages-4))
				$pages[] = '<span class="spacer">'.$lang->t('Spacer').'</span>';

			$pages[] = '<a'.(empty($pages) ? ' class="item1"' : '').' href="'.$link.'&amp;p='.$num_pages.'">'.forum_number_format($num_pages).'</a>';
		}

		// Add a next page link
		if ($num_pages > 1 && !$link_to_all && $cur_page < $num_pages)
			$pages[] = '<a'.(empty($pages) ? ' class="item1"' : '').' href="'.$link.'&amp;p='.($cur_page +1).'">'.$lang->t('Next').'</a>';
	}

	return implode(' ', $pages);
}


//
// Display a message
//
function message($message, $no_back_link = false)
{
	global $db, $cache, $lang, $pun_config, $pun_start, $tpl_main, $pun_user;

	if (!defined('PUN_HEADER'))
	{
		$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Info'));
		define('PUN_ACTIVE_PAGE', 'index');
		require PUN_ROOT.'header.php';
	}

?>

<div id="msg" class="block">
	<h2><span><?php echo $lang->t('Info') ?></span></h2>
	<div class="box">
		<div class="inbox">
			<p><?php echo $message ?></p>
<?php if (!$no_back_link): ?>			<p><a href="javascript: history.go(-1)"><?php echo $lang->t('Go back') ?></a></p>
<?php endif; ?>		</div>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


//
// Format a time string according to $time_format and time zones
//
function format_time($timestamp, $date_only = false, $date_format = null, $time_format = null, $time_only = false, $no_text = false)
{
	global $pun_config, $lang, $pun_user, $forum_date_formats, $forum_time_formats;

	if ($timestamp == '')
		return $lang->t('Never');

	$diff = ($pun_user['timezone'] + $pun_user['dst']) * 3600;
	$timestamp += $diff;
	$now = time();

	if($date_format == null)
		$date_format = $forum_date_formats[$pun_user['date_format']];

	if($time_format == null)
		$time_format = $forum_time_formats[$pun_user['time_format']];

	$date = gmdate($date_format, $timestamp);
	$today = gmdate($date_format, $now+$diff);
	$yesterday = gmdate($date_format, $now+$diff-86400);

	if(!$no_text)
	{
		if ($date == $today)
			$date = $lang->t('Today');
		else if ($date == $yesterday)
			$date = $lang->t('Yesterday');
	}

	if ($date_only)
		return $date;
	else if ($time_only)
		return gmdate($time_format, $timestamp);
	else
		return $date.' '.gmdate($time_format, $timestamp);
}


//
// A wrapper for PHP's number_format function
//
function forum_number_format($number, $decimals = 0)
{
	global $lang;

	return is_numeric($number) ? number_format($number, $decimals, $lang->t('lang_decimal_point'), $lang->t('lang_thousands_sep')) : $number;
}


//
// Generate a random key of length $len
//
function random_key($len, $readable = false, $hash = false)
{
	$key = '';

	if ($hash)
		$key = substr(pun_hash(uniqid(rand(), true)), 0, $len);
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

	return $key;
}


//
// Make sure that HTTP_REFERER matches base_url/script
//
function confirm_referrer($script, $error_msg = false)
{
	global $pun_config, $lang;

	// There is no referrer
	if (empty($_SERVER['HTTP_REFERER']))
		message($error_msg ? $error_msg : $lang->t('Bad referrer'));

	$referrer = parse_url(strtolower($_SERVER['HTTP_REFERER']));
	// Remove www subdomain if it exists
	if (strpos($referrer['host'], 'www.') === 0)
		$referrer['host'] = substr($referrer['host'], 4);

	$valid = parse_url(strtolower(get_base_url().'/'.$script));
	// Remove www subdomain if it exists
	if (strpos($valid['host'], 'www.') === 0)
		$valid['host'] = substr($valid['host'], 4);

	// Check the host and path match. Ignore the scheme, port, etc.
	if ($referrer['host'] != $valid['host'] || $referrer['path'] != $valid['path'])
		message($error_msg ? $error_msg : $lang->t('Bad referrer'));
}


//
// Generate a random password of length $len
// Compatibility wrapper for random_key
//
function random_pass($len)
{
	return random_key($len, true);
}


//
// Compute a hash of $str
//
function pun_hash($str)
{
	return sha1($str);
}


//
// Try to determine the correct remote IP-address
//
function get_remote_address()
{
	$remote_addr = $_SERVER['REMOTE_ADDR'];

	// If we are behind a reverse proxy try to find the real users IP
	if (defined('FORUM_BEHIND_REVERSE_PROXY'))
	{
		if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
		{
			// The general format of the field is:
			// X-Forwarded-For: client1, proxy1, proxy2
			// where the value is a comma+space separated list of IP addresses, the left-most being the farthest downstream client,
			// and each successive proxy that passed the request adding the IP address where it received the request from.
			$forwarded_for = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
			$forwarded_for = trim($forwarded_for[0]);

			if (@preg_match('%^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$%', $forwarded_for) || @preg_match('%^((([0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}:[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){5}:([0-9A-Fa-f]{1,4}:)?[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){4}:([0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){3}:([0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){2}:([0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(([0-9A-Fa-f]{1,4}:){0,5}:((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(::([0-9A-Fa-f]{1,4}:){0,5}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|([0-9A-Fa-f]{1,4}::([0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})|(::([0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){1,7}:))$%', $forwarded_for))
				$remote_addr = $forwarded_for;
		}
	}

	return $remote_addr;
}


//
// Calls htmlspecialchars with a few options already set
//
function pun_htmlspecialchars($str)
{
	return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}


//
// Calls htmlspecialchars_decode with a few options already set
//
function pun_htmlspecialchars_decode($str)
{
	if (function_exists('htmlspecialchars_decode'))
		return htmlspecialchars_decode($str, ENT_QUOTES);

	static $translations;
	if (!isset($translations))
	{
		$translations = get_html_translation_table(HTML_SPECIALCHARS, ENT_QUOTES);
		$translations['&#039;'] = '\''; // get_html_translation_table doesn't include &#039; which is what htmlspecialchars translates ' to, but apparently that is okay?! http://bugs.php.net/bug.php?id=25927
		$translations = array_flip($translations);
	}

	return strtr($str, $translations);
}


//
// A wrapper for utf8_strlen for compatibility
//
function pun_strlen($str)
{
	return utf8_strlen($str);
}


//
// Convert \r\n and \r to \n
//
function pun_linebreaks($str)
{
	return str_replace("\r", "\n", str_replace("\r\n", "\n", $str));
}


//
// A wrapper for utf8_trim for compatibility
//
function pun_trim($str, $charlist = false)
{
	return utf8_trim($str, $charlist);
}

//
// Checks if a string is in all uppercase
//
function is_all_uppercase($string)
{
	return utf8_strtoupper($string) == $string && utf8_strtolower($string) != $string;
}


//
// Inserts $element into $input at $offset
// $offset can be either a numerical offset to insert at (eg: 0 inserts at the beginning of the array)
// or a string, which is the key that the new element should be inserted before
// $key is optional: it's used when inserting a new key/value pair into an associative array
//
function array_insert(&$input, $offset, $element, $key = null)
{
	if ($key == null)
		$key = $offset;

	// Determine the proper offset if we're using a string
	if (!is_int($offset))
		$offset = array_search($offset, array_keys($input), true);

	// Out of bounds checks
	if ($offset > count($input))
		$offset = count($input);
	else if ($offset < 0)
		$offset = 0;

	$input = array_merge(array_slice($input, 0, $offset), array($key => $element), array_slice($input, $offset));
}


//
// Display a message when board is in maintenance mode
//
function maintenance_message()
{
	global $db, $pun_config, $lang, $pun_user;

	// Send no-cache headers
	header('Expires: Thu, 21 Jul 1977 07:30:00 GMT'); // When yours truly first set eyes on this world! :)
	header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
	header('Cache-Control: post-check=0, pre-check=0', false);
	header('Pragma: no-cache'); // For HTTP/1.0 compatibility

	// Send the Content-type header in case the web server is setup to send something else
	header('Content-type: text/html; charset=utf-8');

	// Deal with newlines, tabs and multiple spaces
	$pattern = array("\t", '  ', '  ');
	$replace = array('&#160; &#160; ', '&#160; ', ' &#160;');
	$message = str_replace($pattern, $replace, $pun_config['o_maintenance_message']);

	if (file_exists(PUN_ROOT.'style/'.$pun_user['style'].'/maintenance.tpl'))
	{
		$tpl_file = PUN_ROOT.'style/'.$pun_user['style'].'/maintenance.tpl';
		$tpl_inc_dir = PUN_ROOT.'style/'.$pun_user['style'].'/';
	}
	else
	{
		$tpl_file = PUN_ROOT.'include/template/maintenance.tpl';
		$tpl_inc_dir = PUN_ROOT.'include/user/';
	}

	$tpl_maint = file_get_contents($tpl_file);

	// START SUBST - <pun_include "*">
	preg_match_all('%<pun_include "([^/\\\\]*?)\.(php[45]?|inc|html?|txt)">%i', $tpl_maint, $pun_includes, PREG_SET_ORDER);

	foreach ($pun_includes as $cur_include)
	{
		ob_start();

		// Allow for overriding user includes, too.
		if (file_exists($tpl_inc_dir.$cur_include[1].'.'.$cur_include[2]))
			require $tpl_inc_dir.$cur_include[1].'.'.$cur_include[2];
		else if (file_exists(PUN_ROOT.'include/user/'.$cur_include[1].'.'.$cur_include[2]))
			require PUN_ROOT.'include/user/'.$cur_include[1].'.'.$cur_include[2];
		else
			error($lang->t('Pun include error', htmlspecialchars($cur_include[0]), basename($tpl_file)));

		$tpl_temp = ob_get_contents();
		$tpl_maint = str_replace($cur_include[0], $tpl_temp, $tpl_maint);
		ob_end_clean();
	}
	// END SUBST - <pun_include "*">


	// START SUBST - <pun_language>
	$tpl_maint = str_replace('<pun_language>', $lang->t('lang_identifier'), $tpl_maint);
	// END SUBST - <pun_language>


	// START SUBST - <pun_content_direction>
	$tpl_maint = str_replace('<pun_content_direction>', $lang->t('lang_direction'), $tpl_maint);
	// END SUBST - <pun_content_direction>


	// START SUBST - <pun_head>
	ob_start();

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Maintenance'));

?>
<title><?php echo generate_page_title($page_title) ?></title>
<link rel="stylesheet" type="text/css" href="style/<?php echo $pun_user['style'].'.css' ?>" />
<?php

	$tpl_temp = trim(ob_get_contents());
	$tpl_maint = str_replace('<pun_head>', $tpl_temp, $tpl_maint);
	ob_end_clean();
	// END SUBST - <pun_head>


	// START SUBST - <pun_maint_main>
	ob_start();

?>
<div class="block">
	<h2><?php echo $lang->t('Maintenance') ?></h2>
	<div class="box">
		<div class="inbox">
			<p><?php echo $message ?></p>
		</div>
	</div>
</div>
<?php

	$tpl_temp = trim(ob_get_contents());
	$tpl_maint = str_replace('<pun_maint_main>', $tpl_temp, $tpl_maint);
	ob_end_clean();
	// END SUBST - <pun_maint_main>


	// End the transaction
	$db->end_transaction();


	// Close the db connection (and free up any result data)
	$db->close();

	exit($tpl_maint);
}


//
// Display $message and redirect user to $destination_url
//
function redirect($destination_url, $message)
{
	global $db, $cache, $pun_config, $lang, $pun_user;

	// Prefix with base_url (unless there's already a valid URI)
	if (strpos($destination_url, 'http://') !== 0 && strpos($destination_url, 'https://') !== 0 && strpos($destination_url, '/') !== 0)
		$destination_url = get_base_url(true).'/'.$destination_url;

	// Do a little spring cleaning
	$destination_url = preg_replace('%([\r\n])|(\%0[ad])|(;\s*data\s*:)%i', '', $destination_url);

	// If the delay is 0 seconds, we might as well skip the redirect all together
	if ($pun_config['o_redirect_delay'] == '0')
		header('Location: '.str_replace('&amp;', '&', $destination_url));

	// Send no-cache headers
	header('Expires: Thu, 21 Jul 1977 07:30:00 GMT'); // When yours truly first set eyes on this world! :)
	header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
	header('Cache-Control: post-check=0, pre-check=0', false);
	header('Pragma: no-cache'); // For HTTP/1.0 compatibility

	// Send the Content-type header in case the web server is setup to send something else
	header('Content-type: text/html; charset=utf-8');

	if (file_exists(PUN_ROOT.'style/'.$pun_user['style'].'/redirect.tpl'))
	{
		$tpl_file = PUN_ROOT.'style/'.$pun_user['style'].'/redirect.tpl';
		$tpl_inc_dir = PUN_ROOT.'style/'.$pun_user['style'].'/';
	}
	else
	{
		$tpl_file = PUN_ROOT.'include/template/redirect.tpl';
		$tpl_inc_dir = PUN_ROOT.'include/user/';
	}

	$tpl_redir = file_get_contents($tpl_file);

	// START SUBST - <pun_include "*">
	preg_match_all('%<pun_include "([^/\\\\]*?)\.(php[45]?|inc|html?|txt)">%i', $tpl_redir, $pun_includes, PREG_SET_ORDER);

	foreach ($pun_includes as $cur_include)
	{
		ob_start();

		// Allow for overriding user includes, too.
		if (file_exists($tpl_inc_dir.$cur_include[1].'.'.$cur_include[2]))
			require $tpl_inc_dir.$cur_include[1].'.'.$cur_include[2];
		else if (file_exists(PUN_ROOT.'include/user/'.$cur_include[1].'.'.$cur_include[2]))
			require PUN_ROOT.'include/user/'.$cur_include[1].'.'.$cur_include[2];
		else
			error($lang->t('Pun include error', htmlspecialchars($cur_include[0]), basename($tpl_file)));

		$tpl_temp = ob_get_contents();
		$tpl_redir = str_replace($cur_include[0], $tpl_temp, $tpl_redir);
		ob_end_clean();
	}
	// END SUBST - <pun_include "*">


	// START SUBST - <pun_language>
	$tpl_redir = str_replace('<pun_language>', $lang->t('lang_identifier'), $tpl_redir);
	// END SUBST - <pun_language>


	// START SUBST - <pun_content_direction>
	$tpl_redir = str_replace('<pun_content_direction>', $lang->t('lang_direction'), $tpl_redir);
	// END SUBST - <pun_content_direction>


	// START SUBST - <pun_head>
	ob_start();

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Redirecting'));

?>
<meta http-equiv="refresh" content="<?php echo $pun_config['o_redirect_delay'] ?>;URL=<?php echo str_replace(array('<', '>', '"'), array('&lt;', '&gt;', '&quot;'), $destination_url) ?>" />
<title><?php echo generate_page_title($page_title) ?></title>
<link rel="stylesheet" type="text/css" href="style/<?php echo $pun_user['style'].'.css' ?>" />
<?php

	$tpl_temp = trim(ob_get_contents());
	$tpl_redir = str_replace('<pun_head>', $tpl_temp, $tpl_redir);
	ob_end_clean();
	// END SUBST - <pun_head>


	// START SUBST - <pun_redir_main>
	ob_start();

?>
<div class="block">
	<h2><?php echo $lang->t('Redirecting') ?></h2>
	<div class="box">
		<div class="inbox">
			<p><?php echo $message.'<br /><br /><a href="'.$destination_url.'">'.$lang->t('Click redirect').'</a>' ?></p>
		</div>
	</div>
</div>
<?php

	$tpl_temp = trim(ob_get_contents());
	$tpl_redir = str_replace('<pun_redir_main>', $tpl_temp, $tpl_redir);
	ob_end_clean();
	// END SUBST - <pun_redir_main>


	// START SUBST - <pun_footer>
	ob_start();

	// End the transaction
	$db->commitTransaction();

	// Display executed queries (if enabled)
	if (defined('PUN_SHOW_QUERIES'))
		display_saved_queries();

	$tpl_temp = trim(ob_get_contents());
	$tpl_redir = str_replace('<pun_footer>', $tpl_temp, $tpl_redir);
	ob_end_clean();
	// END SUBST - <pun_footer>


	// Close the db connection (and free up any result data)
	unset ($db);

	exit($tpl_redir);
}


//
// Display a simple error message
//
function error($message, $file = null, $line = null, $db_error = false)
{
	global $pun_config;

	// Set some default settings if the script failed before $pun_config could be populated
	if (empty($pun_config))
	{
		$pun_config = array(
			'o_board_title'	=> 'FluxBB',
			'o_gzip'		=> '0'
		);
	}

	// Empty all output buffers and stop buffering
	while (@ob_end_clean());

	// "Restart" output buffering if we are using ob_gzhandler (since the gzip header is already sent)
	if ($pun_config['o_gzip'] && extension_loaded('zlib'))
		ob_start('ob_gzhandler');

	// Send no-cache headers
	header('Expires: Thu, 21 Jul 1977 07:30:00 GMT'); // When yours truly first set eyes on this world! :)
	header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
	header('Cache-Control: post-check=0, pre-check=0', false);
	header('Pragma: no-cache'); // For HTTP/1.0 compatibility

	// Send the Content-type header in case the web server is setup to send something else
	header('Content-type: text/html; charset=utf-8');

	echo 'Error in file '.$file.', line '.$line.':<br />';
	echo $message;
	if ($db_error != false)
		echo '<br />DB Error: '.$db_error;

	exit;
}


//
// Unset any variables instantiated as a result of register_globals being enabled
//
function forum_unregister_globals()
{
	$register_globals = ini_get('register_globals');
	if ($register_globals === '' || $register_globals === '0' || strtolower($register_globals) === 'off')
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
			unset($GLOBALS[$k]); // Double unset to circumvent the zend_hash_del_key_or_index hole in PHP <4.4.3 and <5.1.4
		}
	}
}


//
// Removes any "bad" characters (characters which mess with the display of a page, are invisible, etc) from user input
//
function forum_remove_bad_characters()
{
	$_GET = remove_bad_characters($_GET);
	$_POST = remove_bad_characters($_POST);
	$_COOKIE = remove_bad_characters($_COOKIE);
	$_REQUEST = remove_bad_characters($_REQUEST);
}

//
// Removes any "bad" characters (characters which mess with the display of a page, are invisible, etc) from the given string
// See: http://kb.mozillazine.org/Network.IDN.blacklist_chars
//
function remove_bad_characters($array)
{
	static $bad_utf8_chars;

	if (!isset($bad_utf8_chars))
	{
		$bad_utf8_chars = array(
			"\xcc\xb7"		=> '',		// COMBINING SHORT SOLIDUS OVERLAY		0337	*
			"\xcc\xb8"		=> '',		// COMBINING LONG SOLIDUS OVERLAY		0338	*
			"\xe1\x85\x9F"	=> '',		// HANGUL CHOSEONG FILLER				115F	*
			"\xe1\x85\xA0"	=> '',		// HANGUL JUNGSEONG FILLER				1160	*
			"\xe2\x80\x8b"	=> '',		// ZERO WIDTH SPACE						200B	*
			"\xe2\x80\x8c"	=> '',		// ZERO WIDTH NON-JOINER				200C
			"\xe2\x80\x8d"	=> '',		// ZERO WIDTH JOINER					200D
			"\xe2\x80\x8e"	=> '',		// LEFT-TO-RIGHT MARK					200E
			"\xe2\x80\x8f"	=> '',		// RIGHT-TO-LEFT MARK					200F
			"\xe2\x80\xaa"	=> '',		// LEFT-TO-RIGHT EMBEDDING				202A
			"\xe2\x80\xab"	=> '',		// RIGHT-TO-LEFT EMBEDDING				202B
			"\xe2\x80\xac"	=> '', 		// POP DIRECTIONAL FORMATTING			202C
			"\xe2\x80\xad"	=> '',		// LEFT-TO-RIGHT OVERRIDE				202D
			"\xe2\x80\xae"	=> '',		// RIGHT-TO-LEFT OVERRIDE				202E
			"\xe2\x80\xaf"	=> '',		// NARROW NO-BREAK SPACE				202F	*
			"\xe2\x81\x9f"	=> '',		// MEDIUM MATHEMATICAL SPACE			205F	*
			"\xe2\x81\xa0"	=> '',		// WORD JOINER							2060
			"\xe3\x85\xa4"	=> '',		// HANGUL FILLER						3164	*
			"\xef\xbb\xbf"	=> '',		// ZERO WIDTH NO-BREAK SPACE			FEFF
			"\xef\xbe\xa0"	=> '',		// HALFWIDTH HANGUL FILLER				FFA0	*
			"\xef\xbf\xb9"	=> '',		// INTERLINEAR ANNOTATION ANCHOR		FFF9	*
			"\xef\xbf\xba"	=> '',		// INTERLINEAR ANNOTATION SEPARATOR		FFFA	*
			"\xef\xbf\xbb"	=> '',		// INTERLINEAR ANNOTATION TERMINATOR	FFFB	*
			"\xef\xbf\xbc"	=> '',		// OBJECT REPLACEMENT CHARACTER			FFFC	*
			"\xef\xbf\xbd"	=> '',		// REPLACEMENT CHARACTER				FFFD	*
			"\xe2\x80\x80"	=> ' ',		// EN QUAD								2000	*
			"\xe2\x80\x81"	=> ' ',		// EM QUAD								2001	*
			"\xe2\x80\x82"	=> ' ',		// EN SPACE								2002	*
			"\xe2\x80\x83"	=> ' ',		// EM SPACE								2003	*
			"\xe2\x80\x84"	=> ' ',		// THREE-PER-EM SPACE					2004	*
			"\xe2\x80\x85"	=> ' ',		// FOUR-PER-EM SPACE					2005	*
			"\xe2\x80\x86"	=> ' ',		// SIX-PER-EM SPACE						2006	*
			"\xe2\x80\x87"	=> ' ',		// FIGURE SPACE							2007	*
			"\xe2\x80\x88"	=> ' ',		// PUNCTUATION SPACE					2008	*
			"\xe2\x80\x89"	=> ' ',		// THIN SPACE							2009	*
			"\xe2\x80\x8a"	=> ' ',		// HAIR SPACE							200A	*
			"\xE3\x80\x80"	=> ' ',		// IDEOGRAPHIC SPACE					3000	*
		);
	}

	if (is_array($array))
		return array_map('remove_bad_characters', $array);

	// Strip out any invalid characters
	$array = utf8_bad_clean($array);

	// Remove control characters
	$array = preg_replace('%[\x{00}-\x{08}\x{0b}-\x{0c}\x{0e}-\x{1f}]%', '', $array);

	// Replace some "bad" characters
	$array = str_replace(array_keys($bad_utf8_chars), array_values($bad_utf8_chars), $array);

	return $array;
}


//
// Converts the file size in bytes to a human readable file size
//
function file_size($size)
{
	global $lang;

	$units = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB');

	for ($i = 0; $size > 1024; $i++)
		$size /= 1024;

	return $lang->t('Size unit '.$units[$i], round($size, 2));;
}


//
// Fetch a list of available styles
//
function forum_list_styles()
{
	$styles = array();

	$d = dir(PUN_ROOT.'style');
	while (($entry = $d->read()) !== false)
	{
		if ($entry{0} == '.')
			continue;

		if (substr($entry, -4) == '.css')
			$styles[] = substr($entry, 0, -4);
	}
	$d->close();

	natcasesort($styles);

	return $styles;
}


//
// Generate a cache ID based on the last modification time for all stopwords files
//
function generate_stopwords_cache_id()
{
	$files = glob(PUN_ROOT.'lang/*/stopwords.txt');
	if ($files === false)
		return 'cache_id_error';

	$hash = array();

	foreach ($files as $file)
	{
		$hash[] = $file;
		$hash[] = filemtime($file);
	}

	return sha1(implode('|', $hash));
}


//
// Split text into chunks ($inside contains all text inside $start and $end, and $outside contains all text outside)
//
function split_text($text, $start, $end, &$errors, $retab = true)
{
	global $pun_config, $lang;

	$result = array(0 => array(), 1 => array()); // 0 = inside, 1 = outside

	// split the text into parts
	$parts = preg_split('%'.preg_quote($start, '%').'(.*)'.preg_quote($end, '%').'%Us', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
	$num_parts = count($parts);

	// preg_split results in outside parts having even indices, inside parts having odd
	for ($i = 0;$i < $num_parts;$i++)
		$result[1 - ($i % 2)][] = $parts[$i];

	if ($pun_config['o_indent_num_spaces'] != 8 && $retab)
	{
		$spaces = str_repeat(' ', $pun_config['o_indent_num_spaces']);
		$result[1] = str_replace("\t", $spaces, $result[1]);
	}

	return $result;
}


//
// Extract blocks from a text with a starting and ending string
// This function always matches the most outer block so nesting is possible
//
function extract_blocks($text, $start, $end, &$errors = array(), $retab = true)
{
	global $pun_config;

	$code = array();
	$start_len = strlen($start);
	$end_len = strlen($end);
	$regex = '%(?:'.preg_quote($start, '%').'|'.preg_quote($end, '%').')%';
	$matches = array();

	if (preg_match_all($regex, $text, $matches))
	{
		$counter = $offset = 0;
		$start_pos = $end_pos = false;

		foreach ($matches[0] as $match)
		{
			if ($match == $start)
			{
				if ($counter == 0)
					$start_pos = strpos($text, $start);
				$counter++;
			}
			elseif ($match == $end)
			{
				$counter--;
				if ($counter == 0)
					$end_pos = strpos($text, $end, $offset + 1);
				$offset = strpos($text, $end, $offset + 1);
			}

			if ($start_pos !== false && $end_pos !== false)
			{
				$code[] = substr($text, $start_pos + $start_len,
					$end_pos - $start_pos - $start_len);
				$text = substr_replace($text, "\1", $start_pos,
					$end_pos - $start_pos + $end_len);
				$start_pos = $end_pos = false;
				$offset = 0;
			}
		}
	}

	if ($pun_config['o_indent_num_spaces'] != 8 && $retab)
	{
		$spaces = str_repeat(' ', $pun_config['o_indent_num_spaces']);
		$text = str_replace("\t", $spaces, $text);
	}

	return array($code, $text);
}


//
// function url_valid($url) {
//
// Return associative array of valid URI components, or FALSE if $url is not
// RFC-3986 compliant. If the passed URL begins with: "www." or "ftp.", then
// "http://" or "ftp://" is prepended and the corrected full-url is stored in
// the return array with a key name "url". This value should be used by the caller.
//
// Return value: FALSE if $url is not valid, otherwise array of URI components:
// e.g.
// Given: "http://www.jmrware.com:80/articles?height=10&width=75#fragone"
// Array(
//	  [scheme] => http
//	  [authority] => www.jmrware.com:80
//	  [userinfo] =>
//	  [host] => www.jmrware.com
//	  [IP_literal] =>
//	  [IPV6address] =>
//	  [ls32] =>
//	  [IPvFuture] =>
//	  [IPv4address] =>
//	  [regname] => www.jmrware.com
//	  [port] => 80
//	  [path_abempty] => /articles
//	  [query] => height=10&width=75
//	  [fragment] => fragone
//	  [url] => http://www.jmrware.com:80/articles?height=10&width=75#fragone
// )
function url_valid($url)
{
	if (strpos($url, 'www.') === 0) $url = 'http://'. $url;
	if (strpos($url, 'ftp.') === 0) $url = 'ftp://'. $url;
	if (!preg_match('/# Valid absolute URI having a non-empty, valid DNS host.
		^
		(?P<scheme>[A-Za-z][A-Za-z0-9+\-.]*):\/\/
		(?P<authority>
		(?:(?P<userinfo>(?:[A-Za-z0-9\-._~!$&\'()*+,;=:]|%[0-9A-Fa-f]{2})*)@)?
		(?P<host>
			(?P<IP_literal>
			\[
			(?:
				(?P<IPV6address>
				(?:												 (?:[0-9A-Fa-f]{1,4}:){6}
				|												   ::(?:[0-9A-Fa-f]{1,4}:){5}
				| (?:							 [0-9A-Fa-f]{1,4})?::(?:[0-9A-Fa-f]{1,4}:){4}
				| (?:(?:[0-9A-Fa-f]{1,4}:){0,1}[0-9A-Fa-f]{1,4})?::(?:[0-9A-Fa-f]{1,4}:){3}
				| (?:(?:[0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})?::(?:[0-9A-Fa-f]{1,4}:){2}
				| (?:(?:[0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})?::	[0-9A-Fa-f]{1,4}:
				| (?:(?:[0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})?::
				)
				(?P<ls32>[0-9A-Fa-f]{1,4}:[0-9A-Fa-f]{1,4}
				| (?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}
					(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)
				)
				|	(?:(?:[0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})?::	[0-9A-Fa-f]{1,4}
				|	(?:(?:[0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})?::
				)
			| (?P<IPvFuture>[Vv][0-9A-Fa-f]+\.[A-Za-z0-9\-._~!$&\'()*+,;=:]+)
			)
			\]
			)
		| (?P<IPv4address>(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}
							(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?))
		| (?P<regname>(?:[A-Za-z0-9\-._~!$&\'()*+,;=]|%[0-9A-Fa-f]{2})+)
		)
		(?::(?P<port>[0-9]*))?
		)
		(?P<path_abempty>(?:\/(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@]|%[0-9A-Fa-f]{2})*)*)
		(?:\?(?P<query>		  (?:[A-Za-z0-9\-._~!$&\'()*+,;=:@\\/?]|%[0-9A-Fa-f]{2})*))?
		(?:\#(?P<fragment>	  (?:[A-Za-z0-9\-._~!$&\'()*+,;=:@\\/?]|%[0-9A-Fa-f]{2})*))?
		$
		/mx', $url, $m)) return FALSE;
	switch ($m['scheme'])
	{
	case 'https':
	case 'http':
		if ($m['userinfo']) return FALSE; // HTTP scheme does not allow userinfo.
		break;
	case 'ftps':
	case 'ftp':
		break;
	default:
		return FALSE;	// Unrecognised URI scheme. Default to FALSE.
	}
	// Validate host name conforms to DNS "dot-separated-parts".
	if ($m{'regname'}) // If host regname specified, check for DNS conformance.
	{
		if (!preg_match('/# HTTP DNS host name.
			^					   # Anchor to beginning of string.
			(?!.{256})			   # Overall host length is less than 256 chars.
			(?:					   # Group dot separated host part alternatives.
			[0-9A-Za-z]\.		   # Either a single alphanum followed by dot
			|					   # or... part has more than one char (63 chars max).
			[0-9A-Za-z]		   # Part first char is alphanum (no dash).
			[\-0-9A-Za-z]{0,61}  # Internal chars are alphanum plus dash.
			[0-9A-Za-z]		   # Part last char is alphanum (no dash).
			\.				   # Each part followed by literal dot.
			)*					   # One or more parts before top level domain.
			(?:					   # Explicitly specify top level domains.
			com|edu|gov|int|mil|net|org|biz|
			info|name|pro|aero|coop|museum|
			asia|cat|jobs|mobi|tel|travel|
			[A-Za-z]{2})		   # Country codes are exqactly two alpha chars.
			$					   # Anchor to end of string.
			/ix', $m['host'])) return FALSE;
	}
	$m['url'] = $url;
	for ($i = 0; isset($m[$i]); ++$i) unset($m[$i]);
	return $m; // return TRUE == array of useful named $matches plus the valid $url.
}

//
// Replace string matching regular expression
//
// This function takes care of possibly disabled unicode properties in PCRE builds
//
function ucp_preg_replace($pattern, $replace, $subject)
{
	$replaced = preg_replace($pattern, $replace, $subject);

	// If preg_replace() returns false, this probably means unicode support is not built-in, so we need to modify the pattern a little
	if ($replaced === false)
	{
		if (is_array($pattern))
		{
			foreach ($pattern as $cur_key => $cur_pattern)
				$pattern[$cur_key] = str_replace('\p{L}\p{N}', '\w', $cur_pattern);

			$replaced = preg_replace($pattern, $replace, $subject);
		}
		else
			$replaced = preg_replace(str_replace('\p{L}\p{N}', '\w', $pattern), $replace, $subject);
	}

	return $replaced;
}

// DEBUG FUNCTIONS BELOW

//
// Display executed queries (if enabled)
//
function display_saved_queries()
{
	global $db, $lang;

	// Get the queries so that we can print them out
	$saved_queries = $db->getExecutedQueries();

?>

<div id="debug" class="blocktable">
	<h2><span><?php echo $lang->t('Debug table') ?></span></h2>
	<div class="box">
		<div class="inbox">
			<table cellspacing="0">
			<thead>
				<tr>
					<th class="tcl" scope="col"><?php echo $lang->t('Query times') ?></th>
					<th class="tcr" scope="col"><?php echo $lang->t('Query') ?></th>
				</tr>
			</thead>
			<tbody>
<?php

	$query_time_total = 0;
	foreach ($saved_queries as $cur_query)
	{
		$query_time_total += $cur_query['duration'];

?>
				<tr>
					<td class="tcl"><?php echo forum_number_format($cur_query['duration'], 5) ?></td>
					<td class="tcr"><?php echo pun_htmlspecialchars($cur_query['sql']) ?></td>
				</tr>
<?php

	}

?>
				<tr>
					<td class="tcl" colspan="2"><?php echo $lang->t('Total query time', forum_number_format($query_time_total, 5).' s') ?></td>
				</tr>
			</tbody>
			</table>
		</div>
	</div>
</div>
<?php

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
