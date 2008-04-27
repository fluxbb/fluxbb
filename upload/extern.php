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

************************************************************************


  INSTRUCTIONS

  This script is used to include information about your board from
  pages outside the forums and to syndicate news about recent
  discussions via RSS. The script can display a list of recent
  discussions (sorted by post time or last post time), a list of
  active users or a collection of general board statistics. The
  script can be called directly via an URL (for RSS), from a PHP
  include command or through the use of Server Side Includes (SSI).

  The scripts behaviour is controlled via variables supplied in the
  URL to the script. The different variables are: action (what to
  output), show (how many topics to display), fid (the ID or ID's of
  the forum(s) to poll for topics), nfid (the ID or ID's of forums
  that should be excluded) and type (output as HTML or RSS). The
  only mandatory variable is action. Possible/default values are:

    action: active (show most recently active topics) (HTML or RSS)
            new (show newest topics) (HTML or RSS)
            online (show users online) (HTML)
            online_full (as above, but includes a full list) (HTML)
            stats (show board statistics) (HTML)

    show:   Any integer value between 1 and 50. This variables is
            ignored for RSS output. The default is 15.

    fid:    One or more forum ID's (comma-separated). If ignored,
            topics from all guest-readable forums will be polled.

    nfid:   One or more forum ID's (comma-separated) that are to be
            excluded. E.g. the ID of a a test forum.

    type:   RSS. Anything else means HTML output.

  Here are some examples using PHP include().

    Show the 15 most recently active topics from all forums:
    include('http://host.com/forums/extern.php?action=active');

    Show the 10 newest topics from forums with ID 5, 6 and 7:
    include('http://host.com/forums/extern.php?action=new&show=10&fid=5,6,7');

    Show users online:
    include('http://host.com/forums/extern.php?action=online');

    Show users online with full listing of users:
    include('http://host.com/forums/extern.php?action=online_full');

    Show board statistics:
    include('http://host.com/forums/extern.php?action=stats');

  Here are some examples using SSI.

    Show the 5 newest topics from forums with ID 11 and 22:
    <!--#include virtual="forums/extern.php?action=new&show=5&fid=11,22" -->

    Show board statistics:
    <!--#include virtual="forums/extern.php?action=stats" -->

  And finally some examples using extern.php to output an RSS 0.91
  feed.

    Output the 15 most recently active topics:
    http://host.com/extern.php?action=active&type=RSS

    Output the 15 newest topics from forum with ID=2:
    http://host.com/extern.php?action=active&type=RSS&fid=2

  Below you will find some variables you can edit to tailor the
  scripts behaviour to your needs.


/***********************************************************************/

// The maximum number of topics that will be displayed
$show_max_topics = 60;

// The length at which topic subjects will be truncated (for HTML output)
$max_subject_length = 30;

/***********************************************************************/

// DO NOT EDIT ANYTHING BELOW THIS LINE! (unless you know what you are doing)


define('PUN_ROOT', './');
@include PUN_ROOT.'config.php';

// If PUN isn't defined, config.php is missing or corrupt
if (!defined('PUN'))
	exit('The file \'config.php\' doesn\'t exist or is corrupt. Please run install.php to install PunBB first.');


// Make sure PHP reports all errors except E_NOTICE
error_reporting(E_ALL ^ E_NOTICE);

// Turn off magic_quotes_runtime
set_magic_quotes_runtime(0);


// Load the functions script
require PUN_ROOT.'include/functions.php';

// Load DB abstraction layer and try to connect
require PUN_ROOT.'include/dblayer/common_db.php';

// Load cached config
@include PUN_ROOT.'cache/cache_config.php';
if (!defined('PUN_CONFIG_LOADED'))
{
    require PUN_ROOT.'include/cache.php';
    generate_config_cache();
    require PUN_ROOT.'cache/cache_config.php';
}

// Make sure we (guests) have permission to read the forums
$result = $db->query('SELECT g_read_board FROM '.$db->prefix.'groups WHERE g_id=3') or error('Unable to fetch group info', __FILE__, __LINE__, $db->error());
if ($db->result($result) == '0')
	exit('No permission');


// Attempt to load the common language file
@include PUN_ROOT.'lang/'.$pun_config['o_default_lang'].'/common.php';
if (!isset($lang_common))
	exit('There is no valid language pack \''.$pun_config['o_default_lang'].'\' installed. Please reinstall a language of that name.');

// Check if we are to display a maintenance message
if ($pun_config['o_maintenance'] && !defined('PUN_TURN_OFF_MAINT'))
	maintenance_message();

if (!isset($_GET['action']))
	exit('No parameters supplied. See extern.php for instructions.');


//
// Converts the CDATA end sequence ]]> into ]]&gt;
//
function escape_cdata($str)
{
	return str_replace(']]>', ']]&gt;', $str);
}


//
// Show recent discussions
//
if ($_GET['action'] == 'active' || $_GET['action'] == 'new')
{
	$order_by = ($_GET['action'] == 'active') ? 't.last_post' : 't.posted';
	$forum_sql = '';

	// Was any specific forum ID's supplied?
	if (isset($_GET['fid']) && $_GET['fid'] != '')
	{
		$fids = explode(',', trim($_GET['fid']));
		$fids = array_map('intval', $fids);

		if (!empty($fids))
			$forum_sql = ' AND f.id IN('.implode(',', $fids).')';
	}

	// Any forum ID's to exclude?
	if (isset($_GET['nfid']) && $_GET['nfid'] != '')
	{
		$nfids = explode(',', trim($_GET['nfid']));
		$nfids = array_map('intval', $nfids);

		if (!empty($nfids))
			$forum_sql = ' AND f.id NOT IN('.implode(',', $nfids).')';
	}

	// Should we output this as RSS?
	if (isset($_GET['type']) && strtoupper($_GET['type']) == 'RSS')
	{
		$rss_description = ($_GET['action'] == 'active') ? $lang_common['RSS Desc Active'] : $lang_common['RSS Desc New'];
		$url_action = ($_GET['action'] == 'active') ? '&amp;action=new' : '';

		// Send XML/no cache headers
		header('Content-Type: text/xml');
		header('Expires: '.gmdate('D, d M Y H:i:s').' GMT');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');

		// It's time for some syndication!
		echo '<?xml version="1.0" encoding="'.$lang_common['lang_encoding'].'"?>'."\r\n";
		echo '<!DOCTYPE rss PUBLIC "-//Netscape Communications//DTD RSS 0.91//EN" "http://my.netscape.com/publish/formats/rss-0.91.dtd">'."\r\n";
		echo '<rss version="0.91">'."\r\n";
		echo '<channel>'."\r\n";
		echo "\t".'<title>'.pun_htmlspecialchars($pun_config['o_board_title']).'</title>'."\r\n";
		echo "\t".'<link>'.$pun_config['o_base_url'].'/</link>'."\r\n";
		echo "\t".'<description>'.pun_htmlspecialchars($rss_description.' '.$pun_config['o_board_title']).'</description>'."\r\n";
		echo "\t".'<language>en-us</language>'."\r\n";

		// Fetch 15 topics
		$result = $db->query('SELECT t.id, t.poster, t.subject, t.posted, t.last_post, f.id AS fid, f.forum_name FROM '.$db->prefix.'topics AS t INNER JOIN '.$db->prefix.'forums AS f ON f.id=t.forum_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id=3) WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND t.moved_to IS NULL'.$forum_sql.' ORDER BY '.$order_by.' DESC LIMIT 15') or error('Unable to fetch topic list', __FILE__, __LINE__, $db->error());

		while ($cur_topic = $db->fetch_assoc($result))
		{
			if ($pun_config['o_censoring'] == '1')
				$cur_topic['subject'] = censor_words($cur_topic['subject']);

			echo "\t".'<item>'."\r\n";
			echo "\t\t".'<title>'.pun_htmlspecialchars($cur_topic['subject']).'</title>'."\r\n";
			echo "\t\t".'<link>'.$pun_config['o_base_url'].'/viewtopic.php?id='.$cur_topic['id'].$url_action.'</link>'."\r\n";
			echo "\t\t".'<description><![CDATA['.escape_cdata($lang_common['Forum'].': <a href="'.$pun_config['o_base_url'].'/viewforum.php?id='.$cur_topic['fid'].'">'.$cur_topic['forum_name'].'</a><br />'."\r\n".$lang_common['Author'].': '.$cur_topic['poster'].'<br />'."\r\n".$lang_common['Posted'].': '.date('r', $cur_topic['posted']).'<br />'."\r\n".$lang_common['Last post'].': '.date('r', $cur_topic['last_post'])).']]></description>'."\r\n";
			echo "\t".'</item>'."\r\n";
		}

		echo '</channel>'."\r\n";
		echo '</rss>';
	}


	// Output regular HTML
	else
	{
		$show = isset($_GET['show']) ? intval($_GET['show']) : 15;
		if ($show < 1 || $show > 50)
			$show = 15;

		// Fetch $show topics
		$result = $db->query('SELECT t.id, t.subject FROM '.$db->prefix.'topics AS t INNER JOIN '.$db->prefix.'forums AS f ON f.id=t.forum_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id=3) WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND t.moved_to IS NULL'.$forum_sql.' ORDER BY '.$order_by.' DESC LIMIT '.$show) or error('Unable to fetch topic list', __FILE__, __LINE__, $db->error());

		while ($cur_topic = $db->fetch_assoc($result))
		{
			if ($pun_config['o_censoring'] == '1')
				$cur_topic['subject'] = censor_words($cur_topic['subject']);

			if (pun_strlen($cur_topic['subject']) > $max_subject_length)
				$subject_truncated = pun_htmlspecialchars(trim(substr($cur_topic['subject'], 0, ($max_subject_length-5)))).' &hellip;';
			else
				$subject_truncated = pun_htmlspecialchars($cur_topic['subject']);

			echo '<li><a href="'.$pun_config['o_base_url'].'/viewtopic.php?id='.$cur_topic['id'].'&amp;action=new" title="'.pun_htmlspecialchars($cur_topic['subject']).'">'.$subject_truncated.'</a></li>'."\n";
		}
	}

	return;
}


//
// Show users online
//
else if ($_GET['action'] == 'online' || $_GET['action'] == 'online_full')
{
	// Load the index.php language file
	require PUN_ROOT.'lang/'.$pun_config['o_default_lang'].'/index.php';
	
	// Fetch users online info and generate strings for output
	$num_guests = $num_users = 0;
	$users = array();
	$result = $db->query('SELECT user_id, ident FROM '.$db->prefix.'online WHERE idle=0 ORDER BY ident', true) or error('Unable to fetch online list', __FILE__, __LINE__, $db->error());

	while ($pun_user_online = $db->fetch_assoc($result))
	{
		if ($pun_user_online['user_id'] > 1)
		{
			$users[] = '<a href="'.$pun_config['o_base_url'].'/profile.php?id='.$pun_user_online['user_id'].'">'.pun_htmlspecialchars($pun_user_online['ident']).'</a>';
			++$num_users;
		}
		else
			++$num_guests;
	}

	echo $lang_index['Guests online'].': '.$num_guests.'<br />';

	if ($_GET['action'] == 'online_full')
		echo $lang_index['Users online'].': '.implode(', ', $users).'<br />';
	else
		echo $lang_index['Users online'].': '.$num_users.'<br />';

	return;
}


//
// Show board statistics
//
else if ($_GET['action'] == 'stats')
{
	// Load the index.php language file
	require PUN_ROOT.'lang/'.$pun_config['o_default_lang'].'/index.php';

	// Collect some statistics from the database
	$result = $db->query('SELECT COUNT(id)-1 FROM '.$db->prefix.'users') or error('Unable to fetch total user count', __FILE__, __LINE__, $db->error());
	$stats['total_users'] = $db->result($result);

	$result = $db->query('SELECT id, username FROM '.$db->prefix.'users ORDER BY registered DESC LIMIT 1') or error('Unable to fetch newest registered user', __FILE__, __LINE__, $db->error());
	$stats['last_user'] = $db->fetch_assoc($result);

	$result = $db->query('SELECT SUM(num_topics), SUM(num_posts) FROM '.$db->prefix.'forums') or error('Unable to fetch topic/post count', __FILE__, __LINE__, $db->error());
	list($stats['total_topics'], $stats['total_posts']) = $db->fetch_row($result);

	echo $lang_index['No of users'].': '.$stats['total_users'].'<br />';
	echo $lang_index['Newest user'].': <a href="'.$pun_config['o_base_url'].'/profile.php?id='.$stats['last_user']['id'].'">'.pun_htmlspecialchars($stats['last_user']['username']).'</a><br />';
	echo $lang_index['No of topics'].': '.$stats['total_topics'].'<br />';
	echo $lang_index['No of posts'].': '.$stats['total_posts'];

	return;
}


else
	exit('Bad request');
