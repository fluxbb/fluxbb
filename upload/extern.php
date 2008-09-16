<?php
/**
 * External syndication script
 *
 * Allows forum content to be syndicated outside of the site in various formats
 * (ie: RSS, Atom, XML, HTML).
 *
 * @copyright Copyright (C) 2008 FluxBB.org, based on code copyright (C) 2002-2008 PunBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package FluxBB
 */

/***********************************************************************

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
  do), show (how many items to display), fid (the ID or ID's of
  the forum(s) to poll for topics), nfid (the ID or ID's of forums
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

    fid:    One or more forum ID's (comma-separated). If ignored,
            topics from all readable forums will be pulled.

    nfid:   One or more forum ID's (comma-separated) that are to be
            excluded. E.g. the ID of a a test forum.

    tid:    A topic ID from which to show posts. If a tid is supplied,
            fid and nfid are ignored.

    show:   Any integer value between 1 and 50. The default is 15.

    order:  last_post - show topics ordered by when they were last
                        posted in, giving information about the reply.
            posted - show topics ordered by when they were first
                     posted, giving information about the original post.


/***********************************************************************/

define('FORUM_QUIET_VISIT', 1);

if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', './');
require FORUM_ROOT.'include/common.php';

($hook = get_hook('ex_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

// The length at which topic subjects will be truncated (for HTML output)
if (!defined('FORUM_EXTERN_MAX_SUBJECT_LENGTH'))
    define('FORUM_EXTERN_MAX_SUBJECT_LENGTH', 30);

// If we're a guest and we've sent a username/pass, we can try to authenticate using those details
if ($forum_user['is_guest'] && isset($_SERVER['PHP_AUTH_USER']))
	authenticate_user($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);

if ($forum_user['g_read_board'] == '0')
{
	http_authenticate_user();
	exit($lang_common['No view']);
}

$action = isset($_GET['action']) ? $_GET['action'] : 'feed';

//
// Sends the proper headers for Basic HTTP Authentication
//
function http_authenticate_user()
{
	global $forum_config, $forum_user;

	if (!$forum_user['is_guest'])
		return;

	header('WWW-Authenticate: Basic realm="'.$forum_config['o_board_title'].' External Syndication"');
	header('HTTP/1.0 401 Unauthorized');
}

//
// Output $feed as RSS 2.0
//
function output_rss($feed)
{
	global $lang_common, $forum_config;

	// Send XML/no cache headers
	header('Content-Type: text/xml; charset=utf-8');
	header('Expires: '.gmdate('D, d M Y H:i:s').' GMT');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');

	echo '<?xml version="1.0" encoding="utf-8"?>'."\r\n";
	echo '<rss version="2.0">'."\r\n";
	echo "\t".'<channel>'."\r\n";
	echo "\t\t".'<title><![CDATA['.escape_cdata($feed['title']).']]></title>'."\r\n";
	echo "\t\t".'<link>'.$feed['link'].'</link>'."\r\n";
	echo "\t\t".'<description><![CDATA['.escape_cdata($feed['description']).']]></description>'."\r\n";
	echo "\t\t".'<lastBuildDate>'.gmdate('r', count($feed['items']) ? $feed['items'][0]['pubdate'] : time()).'</lastBuildDate>'."\r\n";

	if ($forum_config['o_show_version'] == '1')
		echo "\t\t".'<generator>FluxBB '.$forum_config['o_cur_version'].'</generator>'."\r\n";
	else
		echo "\t\t".'<generator>FluxBB</generator>'."\r\n";

	($hook = get_hook('ex_add_new_rss_info')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	foreach ($feed['items'] as $item)
	{
		echo "\t\t".'<item>'."\r\n";
		echo "\t\t\t".'<title><![CDATA['.escape_cdata($item['title']).']]></title>'."\r\n";
		echo "\t\t\t".'<link>'.$item['link'].'</link>'."\r\n";
		echo "\t\t\t".'<description><![CDATA['.escape_cdata($item['description']).']]></description>'."\r\n";
		echo "\t\t\t".'<author><![CDATA[dummy@example.com ('.escape_cdata($item['author']).')]]></author>'."\r\n";
		echo "\t\t\t".'<pubDate>'.gmdate('r', $item['pubdate']).'</pubDate>'."\r\n";
		echo "\t\t\t".'<guid>'.$item['link'].'</guid>'."\r\n";

		($hook = get_hook('ex_add_new_rss_item_info')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		echo "\t\t".'</item>'."\r\n";
	}

	echo "\t".'</channel>'."\r\n";
	echo '</rss>'."\r\n";
}


//
// Output $feed as Atom 1.0
//
function output_atom($feed)
{
	global $lang_common, $forum_config;

	// Send XML/no cache headers
	header('Content-Type: text/xml; charset=utf-8');
	header('Expires: '.gmdate('D, d M Y H:i:s').' GMT');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');

	echo '<?xml version="1.0" encoding="utf-8"?>'."\r\n";
	echo '<feed xmlns="http://www.w3.org/2005/Atom">'."\r\n";

	echo "\t".'<title type="html"><![CDATA['.escape_cdata($feed['title']).']]></title>'."\r\n";
	echo "\t".'<link rel="self" href="'.forum_htmlencode(get_current_url()).'"/>'."\r\n";
	echo "\t".'<updated>'.gmdate('Y-m-d\TH:i:s\Z', count($feed['items']) ? $feed['items'][0]['pubdate'] : time()).'</updated>'."\r\n";

	if ($forum_config['o_show_version'] == '1')
		echo "\t".'<generator version="'.$forum_config['o_cur_version'].'">FluxBB</generator>'."\r\n";
	else
		echo "\t".'<generator>FluxBB</generator>'."\r\n";

	($hook = get_hook('ex_add_new_atom_info')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	echo "\t".'<id>'.$feed['link'].'</id>'."\r\n";

	$content_tag = ($feed['type'] == 'posts') ? 'content' : 'summary';

	foreach ($feed['items'] as $item)
	{
		echo "\t\t".'<entry>'."\r\n";
		echo "\t\t\t".'<title type="html"><![CDATA['.escape_cdata($item['title']).']]></title>'."\r\n";
		echo "\t\t\t".'<link rel="alternate" href="'.$item['link'].'"/>'."\r\n";
		echo "\t\t\t".'<'.$content_tag.' type="html"><![CDATA['.escape_cdata($item['description']).']]></'.$content_tag.'>'."\r\n";
		echo "\t\t\t".'<author>'."\r\n";
		echo "\t\t\t\t".'<name><![CDATA['.escape_cdata($item['author']).']]></name>'."\r\n";
		echo "\t\t\t".'</author>'."\r\n";
		echo "\t\t\t".'<updated>'.gmdate('Y-m-d\TH:i:s\Z', $item['pubdate']).'</updated>'."\r\n";

		($hook = get_hook('ex_add_new_atom_item_info')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		echo "\t\t\t".'<id>'.$item['link'].'</id>'."\r\n";
		echo "\t\t".'</entry>'."\r\n";
	}

	echo '</feed>'."\r\n";
}


//
// Output $feed as XML
//
function output_xml($feed)
{
	global $lang_common, $forum_config;

	// Send XML/no cache headers
	header('Content-Type: text/xml; charset=utf-8');
	header('Expires: '.gmdate('D, d M Y H:i:s').' GMT');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');

	echo '<?xml version="1.0" encoding="utf-8"?>'."\r\n";
	echo '<source>'."\r\n";
	echo "\t".'<url>'.$feed['link'].'</url>'."\r\n";

	($hook = get_hook('ex_add_new_xml_info')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	$forum_tag = ($feed['type'] == 'posts') ? 'post' : 'topic';

	foreach ($feed['items'] as $item)
	{
		echo "\t".'<'.$forum_tag.' id="'.$item['id'].'">'."\r\n";

		echo "\t\t".'<title><![CDATA['.escape_cdata($item['title']).']]></title>'."\r\n";
		echo "\t\t".'<link>'.$item['link'].'</link>'."\r\n";
		echo "\t\t".'<content><![CDATA['.escape_cdata($item['description']).']]></content>'."\r\n";
		echo "\t\t".'<author><![CDATA['.escape_cdata($item['author']).']]></author>'."\r\n";
		echo "\t\t".'<posted>'.gmdate('r', $item['pubdate']).'</posted>'."\r\n";

		($hook = get_hook('ex_add_new_xml_item_info')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		echo "\t".'</'.$forum_tag.'>'."\r\n";
	}

	echo '</source>'."\r\n";
}


//
// Output $feed as HTML (using <li> tags)
//
function output_html($feed)
{

	// Send the Content-type header in case the web server is setup to send something else
	header('Content-type: text/html; charset=utf-8');

	foreach ($feed['items'] as $item)
	{
		if (utf8_strlen($item['title']) > FORUM_EXTERN_MAX_SUBJECT_LENGTH)
			$subject_truncated = forum_htmlencode(forum_trim(utf8_substr($item['title'], 0, (FORUM_EXTERN_MAX_SUBJECT_LENGTH - 5)))).' …';
		else
			$subject_truncated = forum_htmlencode($item['title']);

		echo '<li><a href="'.$item['link'].'" title="'.forum_htmlencode($item['title']).'">'.$subject_truncated.'</a></li>'."\n";
	}
}

// Show recent discussions
if ($action == 'feed')
{
	// Determine what type of feed to output
	$type = isset($_GET['type']) && in_array($_GET['type'], array('html', 'rss', 'atom', 'xml')) ? $_GET['type'] : 'html';

	$show = isset($_GET['show']) ? intval($_GET['show']) : 15;
	if ($show < 1 || $show > 50)
		$show = 15;

	($hook = get_hook('ex_set_syndication_type')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	// Was a topic ID supplied?
	if (isset($_GET['tid']))
	{
		$tid = intval($_GET['tid']);

		// Fetch topic subject
		$query = array(
			'SELECT'	=> 't.subject, t.num_replies, t.first_post_id',
			'FROM'		=> 'topics AS t',
			'JOINS'		=> array(
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=t.forum_id AND fp.group_id='.$forum_user['g_id'].')'
				)
			),
			'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND t.moved_to IS NULL and t.id='.$tid
		);

		($hook = get_hook('ex_qr_get_topic_data')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		if (!$forum_db->num_rows($result))
		{
			http_authenticate_user();
			exit($lang_common['Bad request']);
		}

		$cur_topic = $forum_db->fetch_assoc($result);

		if ($forum_config['o_censoring'] == '1')
			$cur_topic['subject'] = censor_words($cur_topic['subject']);

		// Setup the feed
		$feed = array(
			'title' 		=>	$forum_config['o_board_title'].' - '.$cur_topic['subject'],
			'link'			=>	forum_link($forum_url['topic'], array($tid, sef_friendly($cur_topic['subject']))),
			'description'		=>	sprintf($lang_common['RSS description topic'], $cur_topic['subject']),
			'items'			=>	array(),
			'type'			=>	'posts'
		);

		// Fetch $show posts
		$query = array(
			'SELECT'	=> 'p.id, p.poster, p.message, p.posted',
			'FROM'		=> 'posts AS p',
			'WHERE'		=> 'p.topic_id='.$tid,
			'ORDER BY'	=> 'p.posted DESC',
			'LIMIT'		=> $show
		);

		($hook = get_hook('ex_qr_get_posts')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		while ($cur_post = $forum_db->fetch_assoc($result))
		{
			if ($forum_config['o_censoring'] == '1')
				$cur_post['subject'] = censor_words($cur_post['subject']);
			
			$feed['items'][] = array(
				'id'			=>	$cur_post['id'],
				'title'			=>	$cur_topic['first_post_id'] == $cur_post['id'] ? $cur_topic['subject'] : $lang_common['RSS reply'].$cur_topic['subject'],
				'link'			=>	forum_link($forum_url['post'], $cur_post['id']),
				'description'		=>	($forum_config['o_censoring'] == '1') ? censor_words($cur_post['message']) : $cur_post['message'],
				'author'		=>	$cur_post['poster'],
				'pubdate'		=>	$cur_post['posted']
			);

			($hook = get_hook('ex_modify_cur_post_item')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		}

		($hook = get_hook('ex_pre_topic_output')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		$output_func = 'output_'.$type;
		$output_func($feed);
	}
	else
	{
		$order_posted = isset($_GET['order']) && $_GET['order'] == 'posted';
		
		// Were any forum ID's supplied?
		if (isset($_GET['fid']) && is_scalar($_GET['fid']) && $_GET['fid'] != '')
		{
			$fids = explode(',', forum_trim($_GET['fid']));
			$fids = array_map('intval', $fids);

			if (!empty($fids))
				$forum_sql = ' AND f.id IN('.implode(',', $fids).')';
		}

		// Any forum ID's to exclude?
		if (isset($_GET['nfid']) && is_scalar($_GET['nfid']) && $_GET['nfid'] != '')
		{
			$nfids = explode(',', forum_trim($_GET['nfid']));
			$nfids = array_map('intval', $nfids);

			if (!empty($nfids))
				$forum_sql = ' AND f.id NOT IN('.implode(',', $nfids).')';
		}

		// Setup the feed
		$feed = array(
			'title' 		=>	$forum_config['o_board_title'],
			'link'			=>	forum_link($forum_url['index']),
			'description'		=>	sprintf($lang_common['RSS description'], $forum_config['o_board_title']),
			'items'			=>	array(),
			'type'			=>	'topics'
		);

		// Fetch $show topics
		$query = array(
			'SELECT'	=> 't.id, t.poster, t.subject, t.posted, t.last_post, t.last_poster, f.id AS fid, f.forum_name',
			'FROM'		=> 'topics AS t',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'forums AS f',
					'ON'			=> 'f.id=t.forum_id'
				),
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
				)
			),
			'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND t.moved_to IS NULL',
			'ORDER BY'	=> ($order_posted ? 't.posted' : 't.last_post').' DESC',
			'LIMIT'		=> $show
		);

		if (isset($forum_sql))
			$query['WHERE'] .= $forum_sql;

		($hook = get_hook('ex_qr_get_topics')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		while ($cur_topic = $forum_db->fetch_assoc($result))
		{
			if ($forum_config['o_censoring'] == '1')
				$cur_topic['subject'] = censor_words($cur_topic['subject']);

			$feed['items'][] = array(
				'id'			=>	$cur_topic['id'],
				'title'			=>	$cur_topic['subject'],
				'link'			=>	$order_posted ? forum_link($forum_url['topic'], array($cur_topic['id'], sef_friendly($cur_topic['subject']))) : forum_link($forum_url['topic_new_posts'], array($cur_topic['id'], sef_friendly($cur_topic['subject']))),
				'description'	=>	$lang_common['Forum'].': <a href="'.forum_link($forum_url['forum'], array($cur_topic['fid'], sef_friendly($cur_topic['forum_name']))).'">'.$cur_topic['forum_name'].'</a>',
				'author'		=>	$order_posted ? $cur_topic['poster'] : $cur_topic['last_poster'],
				'pubdate'		=>	$order_posted ? $cur_topic['posted'] : $cur_topic['last_post']
			);

			($hook = get_hook('ex_modify_cur_topic_item')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		}

		($hook = get_hook('ex_pre_forum_output')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		$output_func = 'output_'.$type;
		$output_func($feed);
	}

	exit;
}

// Show users online
else if ($action == 'online' || $action == 'online_full')
{
	// Load the index.php language file
	require FORUM_ROOT.'lang/'.$forum_config['o_default_lang'].'/index.php';

	// Fetch users online info and generate strings for output
	$num_guests = $num_users = 0;
	$users = array();

	$query = array(
		'SELECT'	=> 'o.user_id, o.ident',
		'FROM'		=> 'online AS o',
		'WHERE'		=> 'o.idle=0',
		'ORDER BY'	=> 'o.ident'
	);

	($hook = get_hook('ex_qr_get_users_online')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	while ($forum_user_online = $forum_db->fetch_assoc($result))
	{
		if ($forum_user_online['user_id'] > 1)
		{
			$users[] = '<a href="'.forum_link($forum_url['user'], $forum_user_online['user_id']).'">'.forum_htmlencode($forum_user_online['ident']).'</a>';
			++$num_users;
		}
		else
			++$num_guests;
	}

	($hook = get_hook('ex_pre_online_output')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	echo $lang_index['Guests online'].': '.forum_number_format($num_guests).'<br />'."\n";

	if ($_GET['action'] == 'online_full')
		echo $lang_index['Users online'].': '.implode(', ', $users).'<br />'."\n";
	else
		echo $lang_index['Users online'].': '.forum_number_format($num_users).'<br />'."\n";

	exit;
}

// Show board statistics
else if ($action == 'stats')
{
	// Load the index.php language file
	require FORUM_ROOT.'lang/'.$forum_config['o_default_lang'].'/index.php';

	// Collect some statistics from the database
	$query = array(
		'SELECT'	=> 'COUNT(u.id)-1',
		'FROM'		=> 'users AS u'
	);

	($hook = get_hook('ex_qr_get_user_count')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$stats['total_users'] = $forum_db->result($result);

	$query = array(
		'SELECT'	=> 'u.id, u.username',
		'FROM'		=> 'users AS u',
		'ORDER BY'	=> 'u.registered DESC',
		'LIMIT'		=> '1'
	);

	($hook = get_hook('ex_qr_get_newest_user')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$stats['last_user'] = $forum_db->fetch_assoc($result);

	$query = array(
		'SELECT'	=> 'SUM(f.num_topics), SUM(f.num_posts)',
		'FROM'		=> 'forums AS f'
	);

	($hook = get_hook('ex_qr_get_post_stats')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	list($stats['total_topics'], $stats['total_posts']) = $forum_db->fetch_row($result);

	($hook = get_hook('ex_pre_stats_output')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	echo $lang_index['No of users'].': '.forum_number_format($stats['total_users']).'<br />'."\n";
	echo $lang_index['Newest user'].': <a href="'.forum_link($forum_url['user'], $stats['last_user']['id']).'">'.forum_htmlencode($stats['last_user']['username']).'</a><br />'."\n";
	echo $lang_index['No of topics'].': '.forum_number_format($stats['total_topics']).'<br />'."\n";
	echo $lang_index['No of posts'].': '.forum_number_format($stats['total_posts']).'<br />'."\n";
	
	exit;
}


($hook = get_hook('ex_new_action')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

// If we end up here, the script was called with some wacky parameters
exit($lang_common['Bad request']);
