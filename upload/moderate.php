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
	define('FORUM_ROOT', './');
require FORUM_ROOT.'include/common.php';

($hook = get_hook('mr_start')) ? eval($hook) : null;

// Load the misc.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/misc.php';


// This particular function doesn't require forum-based moderator access. It can be used
// by all moderators and admins.
if (isset($_GET['get_host']))
{
	if (!$forum_user['is_admmod'])
		message($lang_common['No permission']);

	($hook = get_hook('mr_view_ip_selected')) ? eval($hook) : null;

	// Is get_host an IP address or a post ID?
	if (@preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $_GET['get_host']) || @preg_match('/^((([0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}:[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){5}:([0-9A-Fa-f]{1,4}:)?[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){4}:([0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){3}:([0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){2}:([0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(([0-9A-Fa-f]{1,4}:){0,5}:((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(::([0-9A-Fa-f]{1,4}:){0,5}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|([0-9A-Fa-f]{1,4}::([0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})|(::([0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){1,7}:))$/', $_GET['get_host']))
		$ip = $_GET['get_host'];
	else
	{
		$get_host = intval($_GET['get_host']);
		if ($get_host < 1)
			message($lang_common['Bad request']);

		$query = array(
			'SELECT'	=> 'p.poster_ip',
			'FROM'		=> 'posts AS p',
			'WHERE'		=> 'p.id='.$get_host
		);

		($hook = get_hook('mr_qr_get_poster_ip')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		if (!$forum_db->num_rows($result))
			message($lang_common['Bad request']);

		$ip = $forum_db->result($result);
	}

	message(sprintf($lang_misc['Hostname lookup'], $ip, @gethostbyaddr($ip), '<a href="'.forum_link($forum_url['admin_users']).'?show_users='.$ip.'">'.$lang_misc['Show more users'].'</a>'));
}


// All other functions require moderator/admin access
$fid = isset($_GET['fid']) ? intval($_GET['fid']) : 0;
if ($fid < 1)
	message($lang_common['Bad request']);

// Get some info about the forum we're moderating
$query = array(
	'SELECT'	=> 'f.forum_name, f.redirect_url, f.num_topics, f.moderators',
	'FROM'		=> 'forums AS f',
	'JOINS'		=> array(
		array(
			'LEFT JOIN'		=> 'forum_perms AS fp',
			'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
		)
	),
	'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND f.id='.$fid
);

($hook = get_hook('mr_qr_get_forum_data')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
if (!$forum_db->num_rows($result))
	message($lang_common['Bad request']);

$cur_forum = $forum_db->fetch_assoc($result);

// Make sure we're not trying to moderate a redirect forum
if ($cur_forum['redirect_url'] != '')
	message($lang_common['Bad request']);

// Setup the array of moderators
$mods_array = ($cur_forum['moderators'] != '') ? unserialize($cur_forum['moderators']) : array();

if ($forum_user['g_id'] != FORUM_ADMIN && ($forum_user['g_moderator'] != '1' || !array_key_exists($forum_user['username'], $mods_array)))
	message($lang_common['No permission']);

// Get topic/forum tracking data
if (!$forum_user['is_guest'])
	$tracked_topics = get_tracked_topics();


// Did someone click a cancel button?
if (isset($_POST['cancel']))
	redirect(forum_link($forum_url['forum'], array($fid, sef_friendly($cur_forum['forum_name']))), $lang_common['Cancel redirect']);


// All other topic moderation features require a topic id in GET
if (isset($_GET['tid']))
{
	($hook = get_hook('mr_post_actions_selected')) ? eval($hook) : null;

	$tid = intval($_GET['tid']);
	if ($tid < 1)
		message($lang_common['Bad request']);

	// Fetch some info about the topic
	$query = array(
		'SELECT'	=> 't.subject, t.poster, t.first_post_id, t.posted, t.num_replies',
		'FROM'		=> 'topics AS t',
		'WHERE'		=> 't.id='.$tid.' AND t.moved_to IS NULL'
	);

	($hook = get_hook('mr_qr_get_topic_info')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	if (!$forum_db->num_rows($result))
		message($lang_common['Bad request']);

	$cur_topic = $forum_db->fetch_assoc($result);

	// User pressed the cancel button
	if (isset($_POST['delete_posts_cancel']))
		redirect(forum_link($forum_url['topic'], array($tid, sef_friendly($cur_topic['subject']))), $lang_common['Cancel redirect']);

	// Delete one or more posts
	if (isset($_POST['delete_posts']) || isset($_POST['delete_posts_comply']))
	{
		($hook = get_hook('mr_delete_posts_form_submitted')) ? eval($hook) : null;

		$posts = $_POST['posts'];
		if (empty($posts))
			message($lang_misc['No posts selected']);

		if (isset($_POST['delete_posts_comply']))
		{
			if (!isset($_POST['req_confirm']))
				redirect(forum_link($forum_url['topic'], array($tid, sef_friendly($cur_topic['subject']))), $lang_common['No confirm redirect']);

			($hook = get_hook('mr_confirm_delete_posts_form_submitted')) ? eval($hook) : null;

			if (@preg_match('/[^0-9,]/', $posts))
				message($lang_common['Bad request']);

			// Verify that the post IDs are valid
			$query = array(
				'SELECT'	=> 'COUNT(p.id)',
				'FROM'		=> 'posts AS p',
				'WHERE'		=> 'p.id IN('.$posts.') AND p.id!='.$cur_topic['first_post_id'].' AND p.topic_id='.$tid
			);

			($hook = get_hook('mr_qr_verify_post_ids')) ? eval($hook) : null;
			$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
			if ($forum_db->result($result) != substr_count($posts, ',') + 1)
				message($lang_common['Bad request']);

			// Delete the posts
			$query = array(
				'DELETE'	=> 'posts',
				'WHERE'		=> 'id IN('.$posts.')'
			);

			($hook = get_hook('mr_qr_delete_posts')) ? eval($hook) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);

			require FORUM_ROOT.'include/search_idx.php';
			strip_search_index($posts);

			// Get last_post, last_post_id, and last_poster for the topic after deletion
			$query = array(
				'SELECT'	=> 'p.id, p.poster, p.posted',
				'FROM'		=> 'posts AS p',
				'WHERE'		=> 'p.topic_id='.$tid,
				'ORDER BY'	=> 'p.id',
				'LIMIT'		=> '1'
			);

			($hook = get_hook('mr_qr_get_topic_last_post_data')) ? eval($hook) : null;
			$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
			$last_post = $forum_db->fetch_assoc($result);

			// How many posts did we just delete?
			$num_posts_deleted = substr_count($posts, ',') + 1;

			// Update the topic
			$query = array(
				'UPDATE'	=> 'topics',
				'SET'		=> 'last_post='.$last_post['posted'].', last_post_id='.$last_post['id'].', last_poster=\''.$forum_db->escape($last_post['poster']).'\', num_replies=num_replies-'.$num_posts_deleted,
				'WHERE'		=> 'id='.$tid
			);

			($hook = get_hook('mr_qr_update_topic')) ? eval($hook) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);

			sync_forum($fid);

			redirect(forum_link($forum_url['topic'], array($tid, sef_friendly($cur_topic['subject']))), $lang_misc['Delete posts redirect']);
		}

		// Setup form
		$forum_page['set_count'] = $forum_page['fld_count'] = 0;
		$forum_page['form_action'] = forum_link($forum_url['delete_multiple'], array($fid, $tid));

		$forum_page['hidden_fields'] = array(
			'<input type="hidden" name="csrf_token" value="'.generate_form_token($forum_page['form_action']).'" />',
			'<input type="hidden" name="posts" value="'.implode(',', array_keys($posts)).'" />'
		);

		// Setup breadcrumbs
		$forum_page['crumbs'] = array(
			array($forum_config['o_board_title'], forum_link($forum_url['index'])),
			array($cur_forum['forum_name'], forum_link($forum_url['forum'], array($fid, sef_friendly($cur_forum['forum_name'])))),
			array($cur_topic['subject'], forum_link($forum_url['topic'], array($tid, sef_friendly($cur_topic['subject'])))),
			$lang_misc['Delete posts']
		);

		//Setup main heading
		$forum_page['main_head'] = end($forum_page['crumbs']);

		($hook = get_hook('mr_confirm_delete_posts_pre_header_load')) ? eval($hook) : null;

		define('FORUM_PAGE', 'dialogue');
		require FORUM_ROOT.'header.php';

		// START SUBST - <!-- forum_main -->
		ob_start();

		($hook = get_hook('mr_confirm_delete_posts_output_start')) ? eval($hook) : null;

?>
<div class="main-content frm">
	<form class="frm-newform" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
		<div class="hidden">
			<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
		</div>
		<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
			<legend class="frm-legend"><strong><?php echo $lang_misc['Delete posts'] ?></strong></legend>
			<div class="frm-radbox"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="req_confirm" value="1" checked="checked" /> <label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_common['Please confirm'] ?></span> <?php echo $lang_misc['Confirm post delete'] ?>.</label></div>
		</fieldset>
		<div class="frm-buttons">
			<span class="submit"><input type="submit" name="delete_posts_comply" value="<?php echo $lang_common['Delete'] ?>" /></span>
			<span class="cancel"><input type="submit" name="cancel" value="<?php echo $lang_common['Cancel'] ?>" /></span>
		</div>
	</form>
</div>
<?php

		$forum_id = $fid;

		$tpl_temp = trim(ob_get_contents());
		$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
		ob_end_clean();
		// END SUBST - <!-- forum_main -->

		require FORUM_ROOT.'footer.php';
	}


	// Show the delete multiple posts view

	// Load the viewtopic.php language file
	require FORUM_ROOT.'lang/'.$forum_user['language'].'/topic.php';

	// Used to disable the Move and Delete buttons if there are no replies to this topic
	$forum_page['button_status'] = ($cur_topic['num_replies'] == 0) ? ' disabled="disabled"' : '';


	// Determine the post offset (based on $_GET['p'])
	$forum_page['num_pages'] = ceil(($cur_topic['num_replies'] + 1) / $forum_user['disp_posts']);
	$forum_page['page'] = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $forum_page['num_pages']) ? 1 : $_GET['p'];
	$forum_page['start_from'] = $forum_user['disp_posts'] * ($forum_page['page'] - 1);
	$forum_page['finish_at'] = min(($forum_page['start_from'] + $forum_user['disp_posts']), ($cur_topic['num_replies'] + 1));

	// Generate paging links
	$forum_page['page_post']['paging'] = '<p class="paging"><span class="pages">'.$lang_common['Pages'].'</span> '.paginate($forum_page['num_pages'], $forum_page['page'], $forum_url['delete_multiple'], $lang_common['Paging separator'], array($fid, $tid)).'</p>';

	// Navigation links for header and page numbering for title/meta description
	if ($forum_page['page'] < $forum_page['num_pages'])
	{
		$forum_page['nav']['last'] = '<link rel="last" href="'.forum_sublink($forum_url['delete_multiple'], $forum_url['page'], $forum_page['num_pages'], array($fid, $tid)).'" title="'.$lang_common['Page'].' '.$forum_page['num_pages'].'" />';
		$forum_page['nav']['next'] = '<link rel="next" href="'.forum_sublink($forum_url['delete_multiple'], $forum_url['page'], ($forum_page['page'] + 1), array($fid, $tid)).'" title="'.$lang_common['Page'].' '.($forum_page['page'] + 1).'" />';
	}
	if ($forum_page['page'] > 1)
	{
		$forum_page['nav']['prev'] = '<link rel="prev" href="'.forum_sublink($forum_url['delete_multiple'], $forum_url['page'], ($forum_page['page'] - 1), array($fid, $tid)).'" title="'.$lang_common['Page'].' '.($forum_page['page'] - 1).'" />';
		$forum_page['nav']['first'] = '<link rel="first" href="'.forum_link($forum_url['delete_multiple'], array($fid, $tid)).'" title="'.$lang_common['Page'].' 1" />';
	}

	if ($forum_config['o_censoring'] == '1')
		$cur_topic['subject'] = censor_words($cur_topic['subject']);

	// Setup form
	$forum_page['form_action'] = forum_link($forum_url['delete_multiple'], array($fid, $tid));

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($cur_forum['forum_name'], forum_link($forum_url['forum'], array($fid, sef_friendly($cur_forum['forum_name'])))),
		array($cur_topic['subject'], forum_link($forum_url['topic'], array($tid, sef_friendly($cur_topic['subject'])))),
		$lang_topic['Delete posts']
	);

	$forum_page['main_head'] = sprintf($lang_misc['Delete posts head'], forum_htmlencode($cur_topic['subject']));

	if ($forum_page['num_pages'] > 1)
		$forum_page['main_head'] .= '<br /><small>'.sprintf($lang_misc['Paged info topics'], $forum_page['start_from'] + 1, $forum_page['finish_at'], $cur_topic['num_replies'] + 1).'</small>';

	($hook = get_hook('mr_post_actions_pre_header_load')) ? eval($hook) : null;

	define('FORUM_PAGE', 'modtopic');
	define('FORUM_PAGE_TYPE','topic');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

	($hook = get_hook('mr_post_actions_output_start')) ? eval($hook) : null;

?>
<form class="newform" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
<div class="hidden">
	<input type="hidden" name="csrf_token" value="<?php echo generate_form_token($forum_page['form_action']) ?>" />
</div>
<div class="main-content topic">
<?php

	if (!defined('FORUM_PARSER_LOADED'))
		require FORUM_ROOT.'include/parser.php';

	$forum_page['item_count'] = 0;	// Keep track of post numbers

	// Retrieve the posts (and their respective poster)
	$query = array(
		'SELECT'	=> 'u.title, u.num_posts, g.g_id, g.g_user_title, p.id, p.poster, p.poster_id, p.message, p.hide_smilies, p.posted, p.edited, p.edited_by',
		'FROM'		=> 'posts AS p',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'users AS u',
				'ON'			=> 'u.id=p.poster_id'
			),
			array(
				'INNER JOIN'	=> 'groups AS g',
				'ON'			=> 'g.g_id=u.group_id'
			)
		),
		'WHERE'		=> 'p.topic_id='.$tid,
		'ORDER BY'	=> 'p.id',
		'LIMIT'		=> $forum_page['start_from'].','.$forum_user['disp_posts']
	);

	($hook = get_hook('mr_qr_get_posts')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	while ($cur_post = $forum_db->fetch_assoc($result))
	{
		++$forum_page['item_count'];

		$forum_page['post_options'] = $forum_page['message'] = array();
		$forum_page['user_ident'] = '';
		$forum_page['user_info'] = '';
		$cur_post['username'] = $cur_post['poster'];

		// Generate the post heading
		$forum_page['item_ident'] = array();
		if ($cur_post['id'] == $cur_topic['first_post_id'])
			$forum_page['item_ident']['num'] = '<strong>'.($forum_page['start_from'] + $forum_page['item_count']).'</strong>';

		$forum_page['item_ident']['user'] = '<cite>'.($cur_topic['posted'] == $cur_post['posted'] ? sprintf($lang_topic['Topic by'], forum_htmlencode($cur_post['username'])) : sprintf($lang_topic['Reply by'], forum_htmlencode($cur_post['username']))).'</cite>';
		$forum_page['item_ident']['date'] = '<span>'.format_time($cur_post['posted']).'</span>';

		$forum_page['item_head'] = '<a class="permalink" rel="bookmark" title="'.$lang_topic['Permalink post'].'" href="'.forum_link($forum_url['post'], $cur_post['id']).'">'.implode(' ', $forum_page['item_ident']).'</a>';

		// Generate the checkbox field
		if ($cur_post['id'] != $cur_topic['first_post_id'])
			$forum_page['item_head'] = '<label for="fld'.$cur_post['id'].'"><span>'.$lang_misc['Select post'].' </span> <input type="checkbox" id="fld'.$cur_post['id'].'" name="posts['.$cur_post['id'].']" value="1" /> <strong>'.($forum_page['start_from'] + $forum_page['item_count']).'</strong></label>'.$forum_page['item_head'];

		// Generate author identification
		$forum_page['user_ident']['username'] = (($cur_post['poster_id'] > 1) ? '<strong class="username"><a title="'.sprintf($lang_topic['Go to profile'], forum_htmlencode($cur_post['username'])).'" href="'.forum_link($forum_url['user'], $cur_post['poster_id']).'">'.forum_htmlencode($cur_post['username']).'</a></strong>' : '<strong class="username">'.forum_htmlencode($cur_post['username']).'</strong>');
		$forum_page['user_ident']['usertitle'] = '<span class="usertitle">'.get_title($cur_post).'</span>';

		// Give the post some class
		$forum_page['item_status'] = array(
			'post',
			($forum_page['item_count'] % 2 == 0) ? 'odd' : 'even'
		);

		if ($forum_page['item_count'] == 1)
			$forum_page['item_status']['firstpost'] = 'firstpost';

		if (($forum_page['start_from'] + $forum_page['item_count']) == $forum_page['finish_at'])
			$forum_page['item_status']['lastpost'] = 'lastpost';

		if ($cur_post['id'] == $cur_topic['first_post_id'])
			$forum_page['item_status']['topicpost'] = 'topicpost';

		if ($cur_post['id'] == $cur_topic['first_post_id'])
			$forum_page['item_subject'] = $lang_common['Topic'].': '.$cur_topic['subject'];
		else
			$forum_page['item_subject'] = $lang_common['Re'].' '.$cur_topic['subject'];

		// Perform the main parsing of the message (BBCode, smilies, censor words etc)
		$forum_page['message']['message'] = parse_message($cur_post['message'], $cur_post['hide_smilies']);

		if ($cur_post['edited'] != '')
			$forum_page['message']['edited'] = '<p class="lastedit"><em>'.sprintf($lang_topic['Last edited'], forum_htmlencode($cur_post['edited_by']), format_time($cur_post['edited'])).'</em></p>';

		($hook = get_hook('mr_post_actions_row_pre_display')) ? eval($hook) : null;

?>
		<div class="<?php echo implode(' ', $forum_page['item_status']) ?>">
			<div id="p<?php echo $cur_post['id'] ?>" class="posthead">
				<h3><?php echo $forum_page['item_head'] ?></h3>
			</div>
			<div class="postbody">
				<div class="user">
					<h4 class="user-ident"><?php echo implode('<br />', $forum_page['user_ident']) ?></h4>
				</div>
				<div class="post-entry">
					<h4 class="entry-title"><?php echo $forum_page['item_subject'] ?></h4>
					<div class="entry-content">
						<?php echo implode("\n\t\t\t\t\t\t\t", $forum_page['message'])."\n" ?>
					</div>
				</div>
			</div>
		</div>
<?php

	}

?>
</div>
<?php

$forum_page['mod_options'] = array();
$forum_page['mod_options']['del_posts'] = '<span class="submit'.(empty($forum_page['mod_options']) ? ' item1' : '').'"><input type="submit" name="delete_posts" value="'.$lang_misc['Delete posts'].'" /></span>';
$forum_page['mod_options']['del_topic'] = '<span'.(empty($forum_page['mod_options']) ? ' class="item1"' : '').'><a href="'.forum_link($forum_url['delete'], $cur_topic['first_post_id']).'">'.$lang_misc['Delete whole topic'].'</a></span>';

($hook = get_hook('mr_post_actions_pre_mod_options')) ? eval($hook) : null;

?>
<div class="main-options mod-options">
	<p class="options"><?php echo implode(' ', $forum_page['mod_options']) ?></p>
</div>
</form>
<?php

	$forum_id = $fid;

	$tpl_temp = trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}


// Move one or more topics
if (isset($_REQUEST['move_topics']) || isset($_POST['move_topics_to']))
{
	if (isset($_POST['move_topics_to']))
	{
		($hook = get_hook('mr_confirm_move_topics_form_submitted')) ? eval($hook) : null;

		if (@preg_match('/[^0-9,]/', $_POST['topics']))
			message($lang_common['Bad request']);

		$topics = explode(',', $_POST['topics']);
		$move_to_forum = isset($_POST['move_to_forum']) ? intval($_POST['move_to_forum']) : 0;
		if (empty($topics) || $move_to_forum < 1)
			message($lang_common['Bad request']);

		// Fetch the forum name for the forum we're moving to
		$query = array(
			'SELECT'	=> 'f.forum_name',
			'FROM'		=> 'forums AS f',
			'WHERE'		=> 'f.id='.$move_to_forum
		);

		($hook = get_hook('mr_qr_get_move_to_forum_name')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		if (!$forum_db->num_rows($result))
			message($lang_common['Bad request']);

		$move_to_forum_name = $forum_db->result($result);

		// Verify that the topic IDs are valid
		$query = array(
			'SELECT'	=> 'COUNT(t.id)',
			'FROM'		=> 'topics AS t',
			'WHERE'		=> 't.id IN('.implode(',', $topics).') AND t.forum_id='.$fid
		);

		($hook = get_hook('mr_qr_verify_topic_ids')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		if ($forum_db->result($result) != count($topics))
			message($lang_common['Bad request']);

		// Delete any redirect topics if there are any (only if we moved/copied the topic back to where it where it was once moved from)
		$query = array(
			'DELETE'	=> 'topics',
			'WHERE'		=> 'forum_id='.$move_to_forum.' AND moved_to IN('.implode(',', $topics).')'
		);

		($hook = get_hook('mr_qr_delete_redirect_topics')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// Move the topic(s)
		$query = array(
			'UPDATE'	=> 'topics',
			'SET'		=> 'forum_id='.$move_to_forum,
			'WHERE'		=> 'id IN('.implode(',', $topics).')'
		);

		($hook = get_hook('mr_qr_move_topics')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// Should we create redirect topics?
		if (isset($_POST['with_redirect']))
		{
			while (list(, $cur_topic) = @each($topics))
			{
				// Fetch info for the redirect topic
				$query = array(
					'SELECT'	=> 't.poster, t.subject, t.posted, t.last_post',
					'FROM'		=> 'topics AS t',
					'WHERE'		=> 't.id='.$cur_topic
				);

				($hook = get_hook('mr_qr_get_redirect_topic_data')) ? eval($hook) : null;
				$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
				$moved_to = $forum_db->fetch_assoc($result);

				// Create the redirect topic
				$query = array(
					'INSERT'	=> 'poster, subject, posted, last_post, moved_to, forum_id',
					'INTO'		=> 'topics',
					'VALUES'	=> '\''.$forum_db->escape($moved_to['poster']).'\', \''.$forum_db->escape($moved_to['subject']).'\', '.$moved_to['posted'].', '.$moved_to['last_post'].', '.$cur_topic.', '.$fid
				);

				($hook = get_hook('mr_qr_add_redirect_topic')) ? eval($hook) : null;
				$forum_db->query_build($query) or error(__FILE__, __LINE__);
			}
		}

		sync_forum($fid);			// Synchronize the forum FROM which the topic was moved
		sync_forum($move_to_forum);	// Synchronize the forum TO which the topic was moved

		$forum_page['redirect_msg'] = (count($topics) > 1) ? $lang_misc['Move topics redirect'] : $lang_misc['Move topic redirect'];
		redirect(forum_link($forum_url['forum'], array($move_to_forum, sef_friendly($move_to_forum_name))), $forum_page['redirect_msg']);
	}

	if (isset($_POST['move_topics']))
	{
		$topics = isset($_POST['topics']) ? $_POST['topics'] : array();
		if (empty($topics))
			message($lang_misc['No topics selected']);

		$topics = implode(',', array_keys($topics));
		$action = 'multi';
	}
	else
	{
		$topics = intval($_GET['move_topics']);
		if ($topics < 1)
			message($lang_common['Bad request']);

		$action = 'single';

		// Fetch the topic subject
		$query = array(
			'SELECT'	=> 't.subject',
			'FROM'		=> 'topics AS t',
			'WHERE'		=> 't.id='.$topics
		);

		($hook = get_hook('mr_qr_get_topic_to_move_subject')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		if (!$forum_db->num_rows($result))
			message($lang_common['Bad request']);

		$subject = $forum_db->result($result);
	}

	// Get forums we can move the post into
	$query = array(
		'SELECT'	=> 'c.id AS cid, c.cat_name, f.id AS fid, f.forum_name',
		'FROM'		=> 'categories AS c',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'forums AS f',
				'ON'			=> 'c.id=f.cat_id'
			),
			array(
				'LEFT JOIN'		=> 'forum_perms AS fp',
				'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
			)
		),
		'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND f.redirect_url IS NULL AND f.id!='.$fid,
		'ORDER BY'	=> 'c.disp_position, c.id, f.disp_position'
	);

	($hook = get_hook('mr_qr_get_target_forums')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$num_forums = $forum_db->num_rows($result);

	if (!$num_forums)
		message($lang_misc['Nowhere to move']);

	$forum_list = array();
	for ($i = 0; $i < $num_forums; ++$i)
		$forum_list[] = $forum_db->fetch_assoc($result);

	// Setup form
	$forum_page['fld_count'] = $forum_page['set_count'] = 0;
	$forum_page['form_action'] = forum_link($forum_url['moderate_forum'], $fid);

	$forum_page['hidden_fields'] = array(
		'<input type="hidden" name="csrf_token" value="'.generate_form_token($forum_page['form_action']).'" />',
		'<input type="hidden" name="topics" value="'.$topics.'" />'
	);

	// Setup breadcrumbs
	$forum_page['crumbs'][] = array($forum_config['o_board_title'], forum_link($forum_url['index']));
	$forum_page['crumbs'][] = array($cur_forum['forum_name'], forum_link($forum_url['forum'], array($fid, sef_friendly($cur_forum['forum_name']))));
	if ($action == 'single')
		$forum_page['crumbs'][] = array($subject, forum_link($forum_url['topic'], array($topics, sef_friendly($subject))));
	else
		$forum_page['crumbs'][] = array($lang_misc['Moderate forum'], forum_link($forum_url['moderate_forum'], $fid));
	$forum_page['crumbs'][] =	($action == 'single') ? $lang_misc['Move topic'] : $lang_misc['Move topics'];

	//Setup main heading
	$forum_page['main_head'] = end($forum_page['crumbs']).' '.$lang_misc['To new forum'];

	($hook = get_hook('mr_move_topics_pre_header_load')) ? eval($hook) : null;

	define('FORUM_PAGE', 'dialogue');
	define('FORUM_PAGE_TYPE', 'basic');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

	($hook = get_hook('mr_move_topics_output_start')) ? eval($hook) : null;

?>
<div class="main-content frm">
	<form class="frm-newform" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
		<div class="hidden">
			<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
		</div>
		<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
			<legend class="frm-legend"><strong><?php echo $lang_misc['Move topic'] ?></strong></legend>
			<div class="frm-select">
				<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
					<span><?php echo $lang_misc['Move to'] ?></span>
				</label><br />
				<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="move_to_forum">
<?php

	$forum_page['cur_category'] = 0;
	foreach ($forum_list as $cur_forum)
	{
		if ($cur_forum['cid'] != $forum_page['cur_category'])	// A new category since last iteration?
		{
			if ($forum_page['cur_category'])
				echo "\t\t\t\t".'</optgroup>'."\n";

			echo "\t\t\t\t".'<optgroup label="'.forum_htmlencode($cur_forum['cat_name']).'">'."\n";
			$forum_page['cur_category'] = $cur_forum['cid'];
		}

		if ($cur_forum['fid'] != $fid)
			echo "\t\t\t\t".'<option value="'.$cur_forum['fid'].'">'.forum_htmlencode($cur_forum['forum_name']).'</option>'."\n";
	}

?>
				</optgroup>
				</select></span>
			</div>
			<div class="frm-radbox"><input type="checkbox" id="fld<?php echo (++$forum_page['fld_count']) ?>" name="with_redirect" value="1"<?php if ($action == 'single') echo ' checked="checked"' ?> /> <label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_misc['Redirect topic'] ?></span> <?php echo ($action == 'single') ? $lang_misc['Leave redirect'] : $lang_misc['Leave redirects'] ?></label></div>
		</fieldset>
		<div class="frm-buttons">
			<span class="submit"><input type="submit" name="move_topics_to" value="<?php echo $lang_misc['Move'] ?>" /></span>
			<span class="cancel"><input type="submit" name="cancel" value="<?php echo $lang_common['Cancel'] ?>" /></span>
		</div>
	</form>
</div>
<?php

	$forum_id = $fid;

	$tpl_temp = trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}


// Delete one or more topics
else if (isset($_REQUEST['delete_topics']) || isset($_POST['delete_topics_comply']))
{
	$topics = isset($_POST['topics']) ? $_POST['topics'] : array();
	if (empty($topics))
		message($lang_misc['No topics selected']);

	if (isset($_POST['delete_topics_comply']))
	{
		if (!isset($_POST['req_confirm']))
			redirect(forum_link($forum_url['forum'], array($fid, sef_friendly($cur_forum['forum_name']))), $lang_common['Cancel redirect']);

		($hook = get_hook('mr_confirm_delete_topics_form_submitted')) ? eval($hook) : null;

		if (@preg_match('/[^0-9,]/', $topics))
			message($lang_common['Bad request']);

		// Verify that the topic IDs are valid
		$query = array(
			'SELECT'	=> 'COUNT(t.id)',
			'FROM'		=> 'topics AS t',
			'WHERE'		=> 't.id IN('.$topics.') AND t.forum_id='.$fid
		);

		($hook = get_hook('mr_qr_verify_topic_ids2')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		if ($forum_db->result($result) != substr_count($topics, ',') + 1)
			message($lang_common['Bad request']);

		// Create an array of forum IDs that need to be synced
		$forum_ids = array($fid);
		$query = array(
			'SELECT'	=> 't.forum_id',
			'FROM'		=> 'topics AS t',
			'WHERE'		=> 't.moved_to IN('.$topics.')'
		);

		($hook = get_hook('mr_qr_get_forums_to_sync')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		while ($row = $forum_db->fetch_row($result))
			$forum_ids[] = $row[0];

		// Delete the topics and any redirect topics
		$query = array(
			'DELETE'	=> 'topics',
			'WHERE'		=> 'id IN('.$topics.') OR moved_to IN('.$topics.')'
		);

		($hook = get_hook('mr_qr_delete_topics')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// Delete any subscriptions
		$query = array(
			'DELETE'	=> 'subscriptions',
			'WHERE'		=> 'topic_id IN('.$topics.')'
		);

		($hook = get_hook('mr_qr_delete_subscriptions')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// Create a list of the post ID's in the deleted topic and strip the search index
		$query = array(
			'SELECT'	=> 'p.id',
			'FROM'		=> 'posts AS p',
			'WHERE'		=> 'p.topic_id IN('.$topics.')'
		);

		($hook = get_hook('mr_qr_get_deleted_posts')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		$post_ids = '';
		while ($row = $forum_db->fetch_row($result))
			$post_ids .= ($post_ids != '') ? ','.$row[0] : $row[0];

		// Strip the search index provided we're not just deleting redirect topics
		if ($post_ids != '')
		{
			require FORUM_ROOT.'include/search_idx.php';
			strip_search_index($post_ids);
		}

		// Delete posts
		$query = array(
			'DELETE'	=> 'posts',
			'WHERE'		=> 'topic_id IN('.$topics.')'
		);

		($hook = get_hook('mr_qr_delete_topic_posts')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		foreach ($forum_ids as $cur_forum_id)
			sync_forum($cur_forum_id);

		redirect(forum_link($forum_url['forum'], array($fid, sef_friendly($cur_forum['forum_name']))), $lang_misc['Delete topics redirect']);
	}


	// Setup form
	$forum_page['fld_count'] = $forum_page['set_count'] = 0;
	$forum_page['form_action'] = forum_link($forum_url['moderate_forum'], $fid);

	$forum_page['hidden_fields'] = array(
		'<input type="hidden" name="csrf_token" value="'.generate_form_token($forum_page['form_action']).'" />',
		'<input type="hidden" name="topics" value="'.implode(',', array_keys($topics)).'" />'
	);

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($cur_forum['forum_name'], forum_link($forum_url['forum'], array($fid, sef_friendly($cur_forum['forum_name'])))),
		array($lang_misc['Moderate forum'], forum_link($forum_url['moderate_forum'], $fid)),
		$lang_misc['Delete topics']
	);

	//Setup main heading
	$forum_page['main_head'] = end($forum_page['crumbs']);

	($hook = get_hook('mr_delete_topics_pre_header_load')) ? eval($hook) : null;

	define('FORUM_PAGE', 'dialogue');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

	($hook = get_hook('mr_delete_topics_output_start')) ? eval($hook) : null;

?>
<div class="main-content frm">
	<form class="frm-newform" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
		<div class="hidden">
			<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
		</div>
		<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
			<legend class="frm-legend"><strong><?php echo $lang_misc['Delete topics'] ?></strong></legend>
			<div class="frm-radbox"><input type="checkbox" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="req_confirm" value="1" checked="checked" /> <label for="fld<?php echo $forum_page['fld_count'] ?>"><span><?php echo $lang_common['Please confirm'] ?></span> <?php echo $lang_misc['Delete topics comply'] ?></label></div>
		</fieldset>
		<div class="frm-buttons">
			<span class="submit"><input type="submit" name="delete_topics_comply" value="<?php echo $lang_common['Delete'] ?>" /></span>
			<span class="cancel"><input type="submit" name="cancel" value="<?php echo $lang_common['Cancel'] ?>" /></span>
		</div>
	</form>
</div>
<?php

	$forum_id = $fid;

	$tpl_temp = trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}


// Open or close one or more topics
else if (isset($_REQUEST['open']) || isset($_REQUEST['close']))
{
	$action = (isset($_REQUEST['open'])) ? 0 : 1;

	($hook = get_hook('mr_open_close_topic_selected')) ? eval($hook) : null;

	// There could be an array of topic ID's in $_POST
	if (isset($_POST['open']) || isset($_POST['close']))
	{
		$topics = isset($_POST['topics']) ? @array_map('intval', @array_keys($_POST['topics'])) : array();
		if (empty($topics))
			message($lang_misc['No topics selected']);

		$query = array(
			'UPDATE'	=> 'topics',
			'SET'		=> 'closed='.$action,
			'WHERE'		=> 'id IN('.implode(',', $topics).') AND forum_id='.$fid
		);

		($hook = get_hook('mr_qr_open_close_topics')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		$forum_page['redirect_msg'] = ($action) ? $lang_misc['Close topics redirect'] : $lang_misc['Open topics redirect'];
		redirect(forum_link($forum_url['moderate_forum'], $fid), $forum_page['redirect_msg']);
	}
	// Or just one in $_GET
	else
	{
		$topic_id = ($action) ? intval($_GET['close']) : intval($_GET['open']);
		if ($topic_id < 1)
			message($lang_common['Bad request']);

		// We validate the CSRF token. If it's set in POST and we're at this point, the token is valid.
		// If it's in GET, we need to make sure it's valid.
		if (!isset($_POST['csrf_token']) && (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== generate_form_token(($action ? 'close' : 'open').$topic_id)))
			csrf_confirm_form();

		// Get the topic subject
		$query = array(
			'SELECT'	=> 't.subject',
			'FROM'		=> 'topics AS t',
			'WHERE'		=> 't.id='.$topic_id.' AND forum_id='.$fid
		);

		($hook = get_hook('mr_qr_get_open_close_topic_subject')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		if (!$forum_db->num_rows($result))
			message($lang_common['Bad request']);

		$subject = $forum_db->result($result);

		$query = array(
			'UPDATE'	=> 'topics',
			'SET'		=> 'closed='.$action,
			'WHERE'		=> 'id='.$topic_id.' AND forum_id='.$fid
		);

		($hook = get_hook('mr_qr_open_close_topic')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		$forum_page['redirect_msg'] = ($action) ? $lang_misc['Close topic redirect'] : $lang_misc['Open topic redirect'];
		redirect(forum_link($forum_url['topic'], array($topic_id, sef_friendly($subject))), $forum_page['redirect_msg']);
	}
}


// Stick a topic
else if (isset($_GET['stick']))
{
	$stick = intval($_GET['stick']);
	if ($stick < 1)
		message($lang_common['Bad request']);

	// We validate the CSRF token. If it's set in POST and we're at this point, the token is valid.
	// If it's in GET, we need to make sure it's valid.
	if (!isset($_POST['csrf_token']) && (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== generate_form_token('stick'.$stick)))
		csrf_confirm_form();

	($hook = get_hook('mr_stick_topic_selected')) ? eval($hook) : null;

	// Get the topic subject
	$query = array(
		'SELECT'	=> 't.subject',
		'FROM'		=> 'topics AS t',
		'WHERE'		=> 't.id='.$stick.' AND forum_id='.$fid
	);

	($hook = get_hook('mr_qr_get_stick_topic_subject')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	if (!$forum_db->num_rows($result))
		message($lang_common['Bad request']);

	$subject = $forum_db->result($result);

	$query = array(
		'UPDATE'	=> 'topics',
		'SET'		=> 'sticky=1',
		'WHERE'		=> 'id='.$stick.' AND forum_id='.$fid
	);

	($hook = get_hook('mr_qr_stick_topic')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	redirect(forum_link($forum_url['topic'], array($stick, sef_friendly($subject))), $lang_misc['Stick topic redirect']);
}


// Unstick a topic
else if (isset($_GET['unstick']))
{
	$unstick = intval($_GET['unstick']);
	if ($unstick < 1)
		message($lang_common['Bad request']);

	// We validate the CSRF token. If it's set in POST and we're at this point, the token is valid.
	// If it's in GET, we need to make sure it's valid.
	if (!isset($_POST['csrf_token']) && (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== generate_form_token('unstick'.$unstick)))
		csrf_confirm_form();

	($hook = get_hook('mr_unstick_topic_selected')) ? eval($hook) : null;

	// Get the topic subject
	$query = array(
		'SELECT'	=> 't.subject',
		'FROM'		=> 'topics AS t',
		'WHERE'		=> 't.id='.$unstick.' AND forum_id='.$fid
	);

	($hook = get_hook('mr_qr_get_unstick_topic_subject')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	if (!$forum_db->num_rows($result))
		message($lang_common['Bad request']);

	$subject = $forum_db->result($result);

	$query = array(
		'UPDATE'	=> 'topics',
		'SET'		=> 'sticky=0',
		'WHERE'		=> 'id='.$unstick.' AND forum_id='.$fid
	);

	($hook = get_hook('mr_qr_unstick_topic')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	redirect(forum_link($forum_url['topic'], array($unstick, sef_friendly($subject))), $lang_misc['Unstick topic redirect']);
}


($hook = get_hook('mr_new_action')) ? eval($hook) : null;


// No specific forum moderation action was specified in the query string, so we'll display the moderate forum view

// Load the viewforum.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/forum.php';

// Determine the topic offset (based on $_GET['p'])
$forum_page['num_pages'] = ceil($cur_forum['num_topics'] / $forum_user['disp_topics']);

$forum_page['page'] = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $forum_page['num_pages']) ? 1 : $_GET['p'];
$forum_page['start_from'] = $forum_user['disp_topics'] * ($forum_page['page'] - 1);
$forum_page['finish_at'] = min(($forum_page['start_from'] + $forum_user['disp_topics']), ($cur_forum['num_topics']));

// Generate paging links
$forum_page['page_post']['paging'] = '<p class="paging"><span class="pages">'.$lang_common['Pages'].'</span> '.paginate($forum_page['num_pages'], $forum_page['page'], $forum_url['moderate_forum'], $lang_common['Paging separator'], $fid).'</p>';

// Navigation links for header and page numbering for title/meta description
if ($forum_page['page'] < $forum_page['num_pages'])
{
	$forum_page['nav']['last'] = '<link rel="last" href="'.forum_sublink($forum_url['moderate_forum'], $forum_url['page'], $forum_page['num_pages'], $fid).'" title="'.$lang_common['Page'].' '.$forum_page['num_pages'].'" />';
	$forum_page['nav']['next'] = '<link rel="next" href="'.forum_sublink($forum_url['moderate_forum'], $forum_url['page'], ($forum_page['page'] + 1), $fid).'" title="'.$lang_common['Page'].' '.($forum_page['page'] + 1).'" />';
}
if ($forum_page['page'] > 1)
{
	$forum_page['nav']['prev'] = '<link rel="prev" href="'.forum_sublink($forum_url['moderate_forum'], $forum_url['page'], ($forum_page['page'] - 1), $fid).'" title="'.$lang_common['Page'].' '.($forum_page['page'] - 1).'" />';
	$forum_page['nav']['first'] = '<link rel="first" href="'.forum_link($forum_url['moderate_forum'], $fid).'" title="'.$lang_common['Page'].' 1" />';
}

// Setup form
$forum_page['fld_count'] = 0;
$forum_page['form_action'] = forum_link($forum_url['moderate_forum'], $fid);

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($cur_forum['forum_name'], forum_link($forum_url['forum'], array($fid, sef_friendly($cur_forum['forum_name'])))),
	$lang_misc['Moderate forum']
);

// Setup main heading
$forum_page['main_head'] = sprintf($lang_misc['Moderate forum head'], forum_htmlencode($cur_forum['forum_name']));

if ($forum_page['num_pages'] > 1)
	$forum_page['main_head'] .= '<br /><small>'.sprintf($lang_misc['Paged info forums'], $forum_page['start_from'] + 1, $forum_page['finish_at'], $cur_forum['num_topics']).'</small>';

($hook = get_hook('mr_topic_actions_pre_header_load')) ? eval($hook) : null;

define('FORUM_PAGE', 'modforum');
define('FORUM_PAGE_TYPE', 'forum');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('mr_topic_actions_output_start')) ? eval($hook) : null;

?>
<form method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
<div id="forum<?php echo $fid ?>" class="main-content forum">
	<div class="content-head">
		<h2 class="hn"><span><?php echo $lang_misc['Select topics'] ?></span></h2>
	</div>
	<div class="hidden">
		<input type="hidden" name="csrf_token" value="<?php echo generate_form_token($forum_page['form_action']) ?>" />
	</div>
	<table cellspacing="0" summary="<?php echo $lang_forum['Table summary mods'].forum_htmlencode($cur_forum['forum_name']) ?>">
		<thead>
			<tr>
<?php ($hook = get_hook('mr_table_header_begin')) ? eval($hook) : null; ?>
				<th class="tcl" scope="col"><?php echo $lang_common['Topic'] ?></th>
				<th class="tc2" scope="col"><?php echo $lang_common['Replies'] ?></th>
<?php if ($forum_config['o_topic_views'] == '1'): ?>				<th class="tc3" scope="col"><?php echo $lang_forum['Views'] ?></th>
<?php endif; ($hook = get_hook('mr_table_header_after_num_views')) ? eval($hook) : null; ?>				<th class="tcr" scope="col"><?php echo $lang_common['Last post'] ?></th>
<?php ($hook = get_hook('mr_table_header_after_last_post')) ? eval($hook) : null; ?>				<th class="tcmod" scope="col"><?php echo $lang_misc['Select'] ?></th>
			</tr>
		</thead>
		<tbody class="statused">
<?php

// Select topics
$query = array(
	'SELECT'	=> 't.id, t.poster, t.subject, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to',
	'FROM'		=> 'topics AS t',
	'WHERE'		=> 'forum_id='.$fid,
	'ORDER BY'	=> 't.sticky DESC, last_post DESC',
	'LIMIT'		=>	$forum_page['start_from'].', '.$forum_user['disp_topics']
);

($hook = get_hook('mr_qr_get_topics')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

// If there are topics in this forum.
if ($forum_db->num_rows($result))
{
	$forum_page['item_count'] = 0;

	while ($cur_topic = $forum_db->fetch_assoc($result))
	{
		($hook = get_hook('mr_topic_actions_row_loop_start')) ? eval($hook) : null;

		++$forum_page['item_count'];

		// Start from scratch
		$forum_page['item_subject'] = $forum_page['item_status'] = $forum_page['item_last_post'] = $forum_page['item_alt_message'] = $forum_page['item_nav'] = array();
		$forum_page['item_indicator'] = '';
		$forum_page['item_alt_message']['topic'] = $lang_common['Topic'].' '.($forum_page['start_from'] + $forum_page['item_count']);

		if ($forum_config['o_censoring'] == '1')
			$cur_topic['subject'] = censor_words($cur_topic['subject']);

		if ($cur_topic['moved_to'] != null)
		{
			$forum_page['item_status']['moved'] = 'moved';
			$forum_page['item_last_post']['moved'] = $forum_page['item_alt_message']['moved'] = $lang_forum['Moved'];
			$forum_page['item_subject']['moved_to'] = '<a href="'.forum_link($forum_url['topic'], array($cur_topic['moved_to'], sef_friendly($cur_topic['subject']))).'">'.forum_htmlencode($cur_topic['subject']).'</a>';
			$forum_page['item_subject']['moved_by'] = '<span class="byuser">'.sprintf($lang_common['By user'], forum_htmlencode($cur_topic['poster'])).'</span>';
			$cur_topic['num_replies'] = $cur_topic['num_views'] = ' - ';
			$forum_page['ghost_topic'] = true;
		}
		else
		{
			$forum_page['ghost_topic'] = false;

			if ($cur_topic['sticky'] == '1')
			{
				$forum_page['item_subject']['sticky'] = $lang_forum['Sticky'];
				$forum_page['item_status']['sticky'] = 'sticky';
			}

			if ($cur_topic['closed'] == '1')
			{
				$forum_page['item_subject']['closed'] = $lang_common['Closed'];
				$forum_page['item_status']['closed'] = 'closed';
			}

			$forum_page['item_subject']['subject'] = '<a href="'.forum_link($forum_url['topic'], array($cur_topic['id'], sef_friendly($cur_topic['subject']))).'">'.forum_htmlencode($cur_topic['subject']).'</a>';

			$forum_page['item_pages'] = ceil(($cur_topic['num_replies'] + 1) / $forum_user['disp_posts']);

			if ($forum_page['item_pages'] > 1)
				$forum_page['item_nav']['pages'] = paginate($forum_page['item_pages'], -1, $forum_url['topic'], $lang_common['Page separator'], array($cur_topic['id'], sef_friendly($cur_topic['subject'])));

			// Does this topic contain posts we haven't read? If so, tag it accordingly.
			if ($cur_topic['last_post'] > $forum_user['last_visit'] && (!isset($tracked_topics['topics'][$cur_topic['id']]) || $tracked_topics['topics'][$cur_topic['id']] < $cur_topic['last_post']) && (!isset($tracked_topics['forums'][$fid]) || $tracked_topics['forums'][$fid] < $cur_topic['last_post']) && !$forum_page['ghost_topic'])
			{
				$forum_page['item_nav']['new'] = '<a href="'.forum_link($forum_url['topic_new_posts'], array($cur_topic['id'], sef_friendly($cur_topic['subject']))).'" title="'.$lang_forum['New posts info'].'">'.$lang_common['New posts'].'</a>';
				$forum_page['item_status']['new'] = 'new';
			}

			if (!empty($forum_page['item_nav']))
				$forum_page['item_subject']['nav'] = '<span class="topic-nav">[&#160;'.implode('&#160;&#160;', $forum_page['item_nav']).'&#160;]</span>';

			$forum_page['item_subject']['poster'] = '<span class="byuser">'.sprintf($lang_common['By user'], forum_htmlencode($cur_topic['poster'])).'</span>';
			$forum_page['item_last_post']['last_post'] = '<a href="'.forum_link($forum_url['post'], $cur_topic['last_post_id']).'">'.format_time($cur_topic['last_post']).'</a>';
			$forum_page['item_last_post']['last_poster'] = '<span class="byuser">'.sprintf($lang_common['By user'], forum_htmlencode($cur_topic['last_poster'])).'</span>';

			if (empty($forum_page['item_status']))
				$forum_page['item_status']['normal'] = 'normal';

			$forum_page['subject_label'] = $cur_topic['subject'];
		}

		($hook = get_hook('mr_topic_actions_row_pre_item_merge')) ? eval($hook) : null;

		$forum_page['item_style'] = (($forum_page['item_count'] % 2 != 0) ? 'odd' : 'even').' '.implode(' ', $forum_page['item_status']);
		$forum_page['item_indicator'] = '<span class="status '.implode(' ', $forum_page['item_status']).'" title="'.implode(' - ', $forum_page['item_alt_message']).'"><img src="'.$base_url.'/style/'.$forum_user['style'].'/status.png" alt="'.implode(' - ', $forum_page['item_alt_message']).'" />'.$forum_page['item_indicator'].'</span>';

		($hook = get_hook('mr_topic_actions_row_pre_display')) ? eval($hook) : null;

?>
			<tr class="<?php echo $forum_page['item_style'] ?>">
<?php ($hook = get_hook('mr_table_contents_begin')) ? eval($hook) : null; ?>
				<td class="tcl"><?php echo $forum_page['item_indicator'].' '.implode(' ', $forum_page['item_subject']) ?></td>
				<td class="tc2"><?php echo (!$forum_page['ghost_topic']) ? $cur_topic['num_replies'] : ' - ' ?></td>
<?php if ($forum_config['o_topic_views'] == '1'): ?>				<td class="tc3"><?php echo (!$forum_page['ghost_topic']) ? $cur_topic['num_views'] : ' - ' ?></td>
<?php endif; ($hook = get_hook('mr_table_contents_after_num_views')) ? eval($hook) : null; ?>				<td class="tcr"><?php echo implode(' ', $forum_page['item_last_post']) ?></td>
<?php ($hook = get_hook('mr_table_contents_after_last_post')) ? eval($hook) : null; ?>				<td class="tcmod"><label for="fld<?php echo ++$forum_page['fld_count'] ?>"><input id="fld<?php echo $forum_page['fld_count'] ?>" type="checkbox" name="topics[<?php echo $cur_topic['id'] ?>]" value="1" /> <span><?php echo $forum_page['subject_label'] ?></span></label></td>
			</tr>
<?php

	}

	// Setup moderator control buttons
	if ($cur_forum['num_topics'] >= 1)
	{
		$forum_page['mod_options'] = array();
		$forum_page['mod_options']['mod_move'] = '<span class="submit'.(empty($forum_page['mod_options']) ? ' item1' : '').'"><input type="submit" name="move_topics" value="'.$lang_misc['Move'].'" /></span>';
		$forum_page['mod_options']['mod_delete'] = '<span class="submit'.(empty($forum_page['mod_options']) ? ' item1' : '').'"><input type="submit" name="delete_topics" value="'.$lang_common['Delete'].'" /></span>';
		$forum_page['mod_options']['mod_open'] = '<span class="submit'.(empty($forum_page['mod_options']) ? ' item1' : '').'"><input type="submit" name="open" value="'.$lang_misc['Open'].'" /></span>';
		$forum_page['mod_options']['mod_close'] = '<span class="submit'.(empty($forum_page['mod_options']) ? ' item1' : '').'"><input type="submit" name="close" value="'.$lang_misc['Close'].'" /></span>';
	}
}
else
{
	$forum_page['item_indicator'] = '<span class="status empty" title="'.$lang_forum['No topics'].'"><img src="'.$base_url.'/style/'.$forum_user['style'].'/status.png" alt="'.$lang_forum['No topics'].'" /></span>';

	($hook = get_hook('mr_topic_actions_forum_empty')) ? eval($hook) : null;

?>
			<tr class="odd empty">
<?php ($hook = get_hook('mr_empty_table_contents_begin')) ? eval($hook) : null; ?>
				<td class="tcl"><?php echo $forum_page['item_indicator'].' '.$lang_forum['First topic nag'] ?></td>
				<td class="tc2"> - </td>
<?php if ($forum_config['o_topic_views'] == '1'): ?>				<td class="tc3"> - </td>
<?php endif; ($hook = get_hook('mr_empty_table_contents_after_num_views')) ? eval($hook) : null; ?>				<td class="tcr"><?php echo $lang_forum['Never'] ?></td>
<?php ($hook = get_hook('mr_empty_table_contents_after_last_post')) ? eval($hook) : null; ?>				<td class="tcmod"> - </td>
			</tr>
<?php

}

?>
		</tbody>
	</table>
<?php

($hook = get_hook('mr_topic_actions_post_topic_list')) ? eval($hook) : null;

?>
</div>
<?php if (!empty($forum_page['mod_options'])): ?><div class="main-options mod-options">
	<p class="options"><?php echo implode(' ', $forum_page['mod_options']) ?></p>
</div>
<?php endif; ?></form>
<?php

$forum_id = $fid;

($hook = get_hook('mr_end')) ? eval($hook) : null;

$tpl_temp = trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
