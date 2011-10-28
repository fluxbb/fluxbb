<?php

/**
 * Copyright (C) 2008-2011 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

/*-----------------------------------------------------------------------------

INSTRUCTIONS

This script is used to include information about your board from
pages outside the forums and to syndicate news about recent
discussions via RSS/Atom/XML. The script can display a list of
recent discussions, a list of active users or a collection of
general board statistics. The script can be called directly via
an URL, from a PHP include command or through the use of Server
Side Includes (SSI).

The scripts behaviour is controlled via variables supplied in the
URL to the script. The different variables are: action (what to
do), show (how many items to display), fid (the ID or IDs of
the forum(s) to poll for topics), nfid (the ID or IDs of forums
that should be excluded), tid (the ID of the topic from which to
display posts) and type (output as HTML or RSS). The only
mandatory variable is action. Possible/default values are:

	action: feed - show most recent topics/posts (HTML or RSS)
			online - show users online (HTML)
			online_full - as above, but includes a full list (HTML)
			stats - show board statistics (HTML)

	type:   rss - output as RSS 2.0
			atom - output as Atom 1.0
			xml - output as XML
			html - output as HTML (<li>'s)

	fid:    One or more forum IDs (comma-separated). If ignored,
			topics from all readable forums will be pulled.

	nfid:   One or more forum IDs (comma-separated) that are to be
			excluded. E.g. the ID of a a test forum.

	tid:    A topic ID from which to show posts. If a tid is supplied,
			fid and nfid are ignored.

	show:   Any integer value between 1 and 50. The default is 15.

	order:  last_post - show topics ordered by when they were last
						posted in, giving information about the reply.
			posted - show topics ordered by when they were first
					posted, giving information about the original post.

-----------------------------------------------------------------------------*/

define('PUN_QUIET_VISIT', 1);

if (!defined('PUN_ROOT'))
	define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';

// The length at which topic subjects will be truncated (for HTML output)
if (!defined('FORUM_EXTERN_MAX_SUBJECT_LENGTH'))
	define('FORUM_EXTERN_MAX_SUBJECT_LENGTH', 30);

// If we're a guest and we've sent a username/pass, we can try to authenticate using those details
if ($pun_user['is_guest'] && isset($_SERVER['PHP_AUTH_USER']))
	authenticate_user($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);

if ($pun_user['g_read_board'] == '0')
{
	http_authenticate_user();
	exit($lang->t('No view'));
}

$action = isset($_GET['action']) ? strtolower($_GET['action']) : 'feed';

// Handle a couple old formats, from FluxBB 1.2
switch ($action)
{
	case 'active':
		$action = 'feed';
		$_GET['order'] = 'last_post';
		break;

	case 'new':
		$action = 'feed';
		$_GET['order'] = 'posted';
		break;
}

//
// Sends the proper headers for Basic HTTP Authentication
//
function http_authenticate_user()
{
	global $pun_config, $pun_user;

	if (!$pun_user['is_guest'])
		return;

	header('WWW-Authenticate: Basic realm="'.$pun_config['o_board_title'].' External Syndication"');
	header('HTTP/1.0 401 Unauthorized');
}


//
// Output $feed as RSS 2.0
//
function output_rss($feed)
{
	global $lang, $pun_config;

	// Send XML/no cache headers
	header('Content-Type: application/xml; charset=utf-8');
	header('Expires: '.gmdate('D, d M Y H:i:s').' GMT');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');

	echo '<?xml version="1.0" encoding="utf-8"?>'."\n";
	echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">'."\n";
	echo "\t".'<channel>'."\n";
	echo "\t\t".'<atom:link href="'.pun_htmlspecialchars(get_current_url()).'" rel="self" type="application/rss+xml" />'."\n";
	echo "\t\t".'<title><![CDATA['.escape_cdata($feed['title']).']]></title>'."\n";
	echo "\t\t".'<link>'.pun_htmlspecialchars($feed['link']).'</link>'."\n";
	echo "\t\t".'<description><![CDATA['.escape_cdata($feed['description']).']]></description>'."\n";
	echo "\t\t".'<lastBuildDate>'.gmdate('r', count($feed['items']) ? $feed['items'][0]['pubdate'] : time()).'</lastBuildDate>'."\n";

	if ($pun_config['o_show_version'] == '1')
		echo "\t\t".'<generator>FluxBB '.$pun_config['o_cur_version'].'</generator>'."\n";
	else
		echo "\t\t".'<generator>FluxBB</generator>'."\n";

	foreach ($feed['items'] as $item)
	{
		echo "\t\t".'<item>'."\n";
		echo "\t\t\t".'<title><![CDATA['.escape_cdata($item['title']).']]></title>'."\n";
		echo "\t\t\t".'<link>'.pun_htmlspecialchars($item['link']).'</link>'."\n";
		echo "\t\t\t".'<description><![CDATA['.escape_cdata($item['description']).']]></description>'."\n";
		echo "\t\t\t".'<author><![CDATA['.(isset($item['author']['email']) ? escape_cdata($item['author']['email']) : 'dummy@example.com').' ('.escape_cdata($item['author']['name']).')]]></author>'."\n";
		echo "\t\t\t".'<pubDate>'.gmdate('r', $item['pubdate']).'</pubDate>'."\n";
		echo "\t\t\t".'<guid>'.pun_htmlspecialchars($item['link']).'</guid>'."\n";

		echo "\t\t".'</item>'."\n";
	}

	echo "\t".'</channel>'."\n";
	echo '</rss>'."\n";
}


//
// Output $feed as Atom 1.0
//
function output_atom($feed)
{
	global $lang, $pun_config;

	// Send XML/no cache headers
	header('Content-Type: application/atom+xml; charset=utf-8');
	header('Expires: '.gmdate('D, d M Y H:i:s').' GMT');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');

	echo '<?xml version="1.0" encoding="utf-8"?>'."\n";
	echo '<feed xmlns="http://www.w3.org/2005/Atom">'."\n";

	echo "\t".'<title type="html"><![CDATA['.escape_cdata($feed['title']).']]></title>'."\n";
	echo "\t".'<link rel="self" href="'.pun_htmlspecialchars(get_current_url()).'"/>'."\n";
	echo "\t".'<link href="'.pun_htmlspecialchars($feed['link']).'"/>'."\n";
	echo "\t".'<updated>'.gmdate('Y-m-d\TH:i:s\Z', count($feed['items']) ? $feed['items'][0]['pubdate'] : time()).'</updated>'."\n";

	if ($pun_config['o_show_version'] == '1')
		echo "\t".'<generator version="'.$pun_config['o_cur_version'].'">FluxBB</generator>'."\n";
	else
		echo "\t".'<generator>FluxBB</generator>'."\n";

	echo "\t".'<id>'.pun_htmlspecialchars($feed['link']).'</id>'."\n";

	$content_tag = ($feed['type'] == 'posts') ? 'content' : 'summary';

	foreach ($feed['items'] as $item)
	{
		echo "\t".'<entry>'."\n";
		echo "\t\t".'<title type="html"><![CDATA['.escape_cdata($item['title']).']]></title>'."\n";
		echo "\t\t".'<link rel="alternate" href="'.pun_htmlspecialchars($item['link']).'"/>'."\n";
		echo "\t\t".'<'.$content_tag.' type="html"><![CDATA['.escape_cdata($item['description']).']]></'.$content_tag.'>'."\n";
		echo "\t\t".'<author>'."\n";
		echo "\t\t\t".'<name><![CDATA['.escape_cdata($item['author']['name']).']]></name>'."\n";

		if (isset($item['author']['email']))
			echo "\t\t\t".'<email><![CDATA['.escape_cdata($item['author']['email']).']]></email>'."\n";

		if (isset($item['author']['uri']))
			echo "\t\t\t".'<uri>'.pun_htmlspecialchars($item['author']['uri']).'</uri>'."\n";

		echo "\t\t".'</author>'."\n";
		echo "\t\t".'<updated>'.gmdate('Y-m-d\TH:i:s\Z', $item['pubdate']).'</updated>'."\n";

		echo "\t\t".'<id>'.pun_htmlspecialchars($item['link']).'</id>'."\n";
		echo "\t".'</entry>'."\n";
	}

	echo '</feed>'."\n";
}


//
// Output $feed as XML
//
function output_xml($feed)
{
	global $lang, $pun_config;

	// Send XML/no cache headers
	header('Content-Type: application/xml; charset=utf-8');
	header('Expires: '.gmdate('D, d M Y H:i:s').' GMT');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');

	echo '<?xml version="1.0" encoding="utf-8"?>'."\n";
	echo '<source>'."\n";
	echo "\t".'<url>'.pun_htmlspecialchars($feed['link']).'</url>'."\n";

	$forum_tag = ($feed['type'] == 'posts') ? 'post' : 'topic';

	foreach ($feed['items'] as $item)
	{
		echo "\t".'<'.$forum_tag.' id="'.$item['id'].'">'."\n";

		echo "\t\t".'<title><![CDATA['.escape_cdata($item['title']).']]></title>'."\n";
		echo "\t\t".'<link>'.pun_htmlspecialchars($item['link']).'</link>'."\n";
		echo "\t\t".'<content><![CDATA['.escape_cdata($item['description']).']]></content>'."\n";
		echo "\t\t".'<author>'."\n";
		echo "\t\t\t".'<name><![CDATA['.escape_cdata($item['author']['name']).']]></name>'."\n";

		if (isset($item['author']['email']))
			echo "\t\t\t".'<email><![CDATA['.escape_cdata($item['author']['email']).']]></email>'."\n";

		if (isset($item['author']['uri']))
			echo "\t\t\t".'<uri>'.pun_htmlspecialchars($item['author']['uri']).'</uri>'."\n";

		echo "\t\t".'</author>'."\n";
		echo "\t\t".'<posted>'.gmdate('r', $item['pubdate']).'</posted>'."\n";

		echo "\t".'</'.$forum_tag.'>'."\n";
	}

	echo '</source>'."\n";
}


//
// Output $feed as HTML (using <li> tags)
//
function output_html($feed)
{

	// Send the Content-type header in case the web server is setup to send something else
	header('Content-type: text/html; charset=utf-8');
	header('Expires: '.gmdate('D, d M Y H:i:s').' GMT');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');

	foreach ($feed['items'] as $item)
	{
		if (utf8_strlen($item['title']) > FORUM_EXTERN_MAX_SUBJECT_LENGTH)
			$subject_truncated = pun_htmlspecialchars(pun_trim(utf8_substr($item['title'], 0, (FORUM_EXTERN_MAX_SUBJECT_LENGTH - 5)))).' …';
		else
			$subject_truncated = pun_htmlspecialchars($item['title']);

		echo '<li><a href="'.pun_htmlspecialchars($item['link']).'" title="'.pun_htmlspecialchars($item['title']).'">'.$subject_truncated.'</a></li>'."\n";
	}
}

// Show recent discussions
if ($action == 'feed')
{
	require PUN_ROOT.'include/parser.php';

	// Determine what type of feed to output
	$type = isset($_GET['type']) ? strtolower($_GET['type']) : 'html';
	if (!in_array($type, array('html', 'rss', 'atom', 'xml')))
		$type = 'html';

	$show = isset($_GET['show']) ? intval($_GET['show']) : 15;
	if ($show < 1 || $show > 50)
		$show = 15;

	// Was a topic ID supplied?
	if (isset($_GET['tid']))
	{
		$tid = intval($_GET['tid']);

		// Fetch topic subject
		$query = $db->select(array('subject' => 't.subject', 'first_post_id' => 't.first_post_id'), 'topics AS t');

		$query->LeftJoin('fp', 'forum_perms AS fp', 'fp.forum_id = t.forum_id AND fp.group_id = :group_id');

		$query->where = '(fp.read_forum IS NULL OR fp.read_forum = 1) AND t.moved_to IS NULL AND t.id = :topic_id';

		$params = array(':group_id' => $pun_user['g_id'], ':topic_id' => $tid);

		$result = $query->run($params);
		if (empty($result))
		{
			http_authenticate_user();
			exit($lang->t('Bad request'));
		}

		$cur_topic = $result[0];
		unset ($result, $query, $params);

		if ($pun_config['o_censoring'] == '1')
			$cur_topic['subject'] = censor_words($cur_topic['subject']);

		// Setup the feed
		$feed = array(
			'title' 		=>	$pun_config['o_board_title'].$lang->t('Title separator').$cur_topic['subject'],
			'link'			=>	get_base_url(true).'/viewtopic.php?id='.$tid,
			'description'		=>	$lang->t('RSS description topic', $cur_topic['subject']),
			'items'			=>	array(),
			'type'			=>	'posts'
		);

		// Fetch $show posts
		$query = $db->select(array('pid' => 'p.id', 'poster' => 'p.poster', 'message' => 'p.message', 'hide_smilies' => 'p.hide_smilies', 'posted' => 'p.posted', 'posted_id' => 'p.poster_id', 'email_setting' => 'u.email_setting', 'email' => 'u.email', 'poster_email' => 'p.poster_email'), 'posts AS p');

		$query->InnerJoin('u', 'users AS u', 'u.id = p.poster_id');

		$query->where = 'p.topic_id = :topic_id';
		$query->order = array('posted' => 'p.posted DESC');
		$query->limit = $show;

		$params = array(':topic_id' => $tid);

		$result = $query->run($params);
		foreach ($result as $cur_post)
		{
			$cur_post['message'] = parse_message($cur_post['message'], $cur_post['hide_smilies']);

			$item = array(
				'id'			=>	$cur_post['id'],
				'title'			=>	$cur_topic['first_post_id'] == $cur_post['id'] ? $cur_topic['subject'] : $lang->t('RSS reply').$cur_topic['subject'],
				'link'			=>	get_base_url(true).'/viewtopic.php?pid='.$cur_post['id'].'#p'.$cur_post['id'],
				'description'		=>	$cur_post['message'],
				'author'		=>	array(
					'name'	=> $cur_post['poster'],
				),
				'pubdate'		=>	$cur_post['posted']
			);

			if ($cur_post['poster_id'] > 1)
			{
				if ($cur_post['email_setting'] == '0' && !$pun_user['is_guest'])
					$item['author']['email'] = $cur_post['email'];

				$item['author']['uri'] = get_base_url(true).'/profile.php?id='.$cur_post['poster_id'];
			}
			else if ($cur_post['poster_email'] != '' && !$pun_user['is_guest'])
				$item['author']['email'] = $cur_post['poster_email'];

			$feed['items'][] = $item;
		}

		unset ($result, $query, $params);

		$output_func = 'output_'.$type;
		$output_func($feed);
	}
	else
	{
		$order_posted = isset($_GET['order']) && strtolower($_GET['order']) == 'posted';
		$forum_name = '';

		$post_query = $db->select(array('t.id, t.poster, t.subject, t.posted, t.last_post, t.last_poster, p.message, p.hide_smilies, u.email_setting, u.email, p.poster_id, p.poster_email'), 'topics AS t');

		$post_query->InnerJoin('p', 'posts AS p', 'p.id = '.($order_posted ? 't.first_post_id' : 't.last_post_id');

		$post_query->joins['u'] = new InnerJoin('users AS u');
		$post_query->joins['u']->on = 'u.id = p.poster_id');

		$post_query->LeftJoin('fp', 'forum_perms AS fp', 'fp.forum_id = t.forum_id AND fp.group_id = :group_id');

		$post_query->where = '(fp.read_forum IS NULL OR fp.read_forum = 1) AND t.moved_to IS NULL';
		$post_query->order = array('sort' => ($order_posted ? 't.posted' : 't.last_post').' DESC');
		$post_query->limit = 50;

		$post_params = array(':group_id' => $pun_user['g_id']);

		// Were any forum IDs supplied?
		if (isset($_GET['fid']) && is_scalar($_GET['fid']) && $_GET['fid'] != '')
		{
			$fids = explode(',', pun_trim($_GET['fid']));
			$fids = array_map('intval', $fids);

			if (!empty($fids))
			{
				$post_query->where .= ' AND t.forum_id IN :fids';
				$post_params[':fids'] = $fids;
			}

			if (count($fids) == 1)
			{
				// Fetch forum name
				$query = $db->select(array('forum_name' => 'f.forum_name'), 'forums AS f');

				$query->LeftJoin('fp', 'forum_perms AS fp', 'fp.forum_id = f.id AND fp.group_id = :group_id');

				$query->where = '(fp.read_forum IS NULL OR fp.read_forum = 1) AND f.id = :forum_id';

				$params = array(':group_id' => $pun_user['g_id'], ':forum_id' => $fids[0]);

				$result = $query->run($params);
				if (!empty($result))
					$forum_name = $lang->t('Title separator').$result[0]['forum_name'];

				unset ($result, $query, $params);
			}
		}

		// Any forum IDs to exclude?
		if (isset($_GET['nfid']) && is_scalar($_GET['nfid']) && $_GET['nfid'] != '')
		{
			$nfids = explode(',', pun_trim($_GET['nfid']));
			$nfids = array_map('intval', $nfids);

			if (!empty($nfids))
			{
				$post_query->where .= ' AND t.forum_id NOT IN :nfids';
				$post_params[':nfids'] = $nfids;
			}
		}

		// Only attempt to cache if caching is enabled and we have all or a single forum
		if ($pun_config['o_feed_ttl'] > 0 && ($forum_sql == '' || ($forum_name != '' && !isset($_GET['nfid']))))
		{
			$cache_id = 'feed.'.$pun_user['g_id'].'.'.$lang->t('lang_identifier').'.'.($order_posted ? '1' : '0').($forum_name == '' ? '' : '.'.$fids[0]);
			$feed = $cache->get($cache_id);
		}

		$now = time();
		if (!isset($feed) || $feed === Cache::NOT_FOUND)
		{
			// Setup the feed
			$feed = array(
				'title' 		=>	$pun_config['o_board_title'].$forum_name,
				'link'			=>	'/index.php',
				'description'	=>	$lang->t('RSS description', $pun_config['o_board_title']),
				'items'			=>	array(),
				'type'			=>	'topics'
			);

			// Fetch topics
			$result = $post_query->run($post_params);
			foreach ($result as $cur_topic)
			{
				if ($pun_config['o_censoring'] == '1')
					$cur_topic['subject'] = censor_words($cur_topic['subject']);

				$cur_topic['message'] = parse_message($cur_topic['message'], $cur_topic['hide_smilies']);

				$item = array(
					'id'			=>	$cur_topic['id'],
					'title'			=>	$cur_topic['subject'],
					'link'			=>	'/viewtopic.php?id='.$cur_topic['id'].($order_posted ? '' : '&action=new'),
					'description'	=>	$cur_topic['message'],
					'author'		=>	array(
						'name'	=> $order_posted ? $cur_topic['poster'] : $cur_topic['last_poster']
					),
					'pubdate'		=>	$order_posted ? $cur_topic['posted'] : $cur_topic['last_post']
				);

				if ($cur_topic['poster_id'] > 1)
				{
					if ($cur_topic['email_setting'] == '0' && !$pun_user['is_guest'])
						$item['author']['email'] = $cur_topic['email'];

					$item['author']['uri'] = '/profile.php?id='.$cur_topic['poster_id'];
				}
				else if ($cur_topic['poster_email'] != '' && !$pun_user['is_guest'])
					$item['author']['email'] = $cur_topic['poster_email'];

				$feed['items'][] = $item;
			}

			unset ($result, $post_query, $post_params);

			// Output feed as PHP code
			if (isset($cache_id))
				$cache->set($cache_id, $feed, $pun_config['o_feed_ttl'] * 60);
		}

		// If we only want to show a few items but due to caching we have too many
		if (count($feed['items']) > $show)
			$feed['items'] = array_slice($feed['items'], 0, $show);

		// Prepend the current base URL onto some links. Done after caching to handle http/https correctly
		$feed['link'] = get_base_url(true).$feed['link'];

		foreach ($feed['items'] as $key => $item)
		{
			$feed['items'][$key]['link'] = get_base_url(true).$item['link'];

			if (isset($item['author']['uri']))
				$feed['items'][$key]['author']['uri'] = get_base_url(true).$item['author']['uri'];
		}

		$output_func = 'output_'.$type;
		$output_func($feed);
	}

	exit;
}

// Show users online
else if ($action == 'online' || $action == 'online_full')
{
	// Load the index.php language file
	$lang->load('index');

	// Fetch users online info and generate strings for output
	$num_guests = $num_users = 0;
	$users = array();

	$query = $db->select(array('user_id' => 'o.user_id', 'ident' => 'o.ident'), 'online AS o');
	$query->where = 'o.idle = 0';
	$query->order = array('ident' => 'o.ident ASC');

	$params = array();

	$result = $query->run($params);
	foreach ($result as $pun_user_online)
	{
		if ($pun_user_online['user_id'] > 1)
		{
			$users[] = ($pun_user['g_view_users'] == '1') ? '<a href="'.pun_htmlspecialchars(get_base_url(true)).'/profile.php?id='.$pun_user_online['user_id'].'">'.pun_htmlspecialchars($pun_user_online['ident']).'</a>' : pun_htmlspecialchars($pun_user_online['ident']);
			++$num_users;
		}
		else
			++$num_guests;
	}

	unset ($result, $query, $params);

	// Send the Content-type header in case the web server is setup to send something else
	header('Content-type: text/html; charset=utf-8');
	header('Expires: '.gmdate('D, d M Y H:i:s').' GMT');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');

	echo $lang->t('Guests online', forum_number_format($num_guests)).'<br />'."\n";

	if ($action == 'online_full' && !empty($users))
		echo $lang->t('Users online', implode(', ', $users)).'<br />'."\n";
	else
		echo $lang->t('Users online', forum_number_format($num_users)).'<br />'."\n";

	exit;
}

// Show board statistics
else if ($action == 'stats')
{
	// Load the index.php language file
	$lang->load('index');

	// Collect some board statistics
	$stats = fetch_board_stats();

	$query = $db->select(array('total_topics' => 'SUM(f.num_topics) AS total_topics', 'total_posts' => 'SUM(num_posts) AS total_posts'), 'forums AS f');
	$params = array();

	$stats = array_merge($stats, current($query->run($params)));
	unset ($query, $params);

	// Send the Content-type header in case the web server is setup to send something else
	header('Content-type: text/html; charset=utf-8');
	header('Expires: '.gmdate('D, d M Y H:i:s').' GMT');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');

	echo $lang->t('No of users', forum_number_format($stats['total_users'])).'<br />'."\n";
	echo $lang->t('Newest user', (($pun_user['g_view_users'] == '1') ? '<a href="'.pun_htmlspecialchars(get_base_url(true)).'/profile.php?id='.$stats['last_user']['id'].'">'.pun_htmlspecialchars($stats['last_user']['username']).'</a>' : pun_htmlspecialchars($stats['last_user']['username']))).'<br />'."\n";
	echo $lang->t('No of topics', forum_number_format($stats['total_topics'])).'<br />'."\n";
	echo $lang->t('No of posts', forum_number_format($stats['total_posts'])).'<br />'."\n";

	exit;
}

// If we end up here, the script was called with some wacky parameters
exit($lang->t('Bad request'));
