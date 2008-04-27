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


if (!defined('PUN_ROOT'))
	define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';

($hook = get_hook('mr_start')) ? eval($hook) : null;

// Load the misc.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/misc.php';


// This particular function doesn't require forum-based moderator access. It can be used
// by all moderators and admins.
if (isset($_GET['get_host']))
{
	if (!$pun_user['is_admmod'])
		message($lang_common['No permission']);

	($hook = get_hook('mr_view_ip_selected')) ? eval($hook) : null;

	// Is get_host an IP address or a post ID?
	if (@preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $_GET['get_host']))
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
		$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
		if (!$pun_db->num_rows($result))
			message($lang_common['Bad request']);

		$ip = $pun_db->result($result);
	}

	message(sprintf($lang_misc['Hostname lookup'], $ip, @gethostbyaddr($ip), '<a href="'.pun_link($pun_url['admin_users']).'?show_users='.$ip.'">'.$lang_misc['Show more users'].'</a>'));
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
			'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].')'
		)
	),
	'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND f.id='.$fid
);

($hook = get_hook('mr_qr_get_forum_data')) ? eval($hook) : null;
$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
if (!$pun_db->num_rows($result))
	message($lang_common['Bad request']);

$cur_forum = $pun_db->fetch_assoc($result);

// Make sure we're not trying to moderate a redirect forum
if ($cur_forum['redirect_url'] != '')
	message($lang_common['Bad request']);

// Setup the array of moderators
$mods_array = ($cur_forum['moderators'] != '') ? unserialize($cur_forum['moderators']) : array();

if ($pun_user['g_id'] != PUN_ADMIN && ($pun_user['g_moderator'] != '1' || !array_key_exists($pun_user['username'], $mods_array)))
	message($lang_common['No permission']);

// Get topic/forum tracking data
if (!$pun_user['is_guest'])
	$tracked_topics = get_tracked_topics();


// Did someone click a cancel button?
if (isset($_POST['cancel']))
	redirect(pun_link($pun_url['forum'], array($fid, sef_friendly($cur_forum['forum_name']))), $lang_common['Cancel redirect']);


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
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	if (!$pun_db->num_rows($result))
		message($lang_common['Bad request']);

	$cur_topic = $pun_db->fetch_assoc($result);

	// User pressed the cancel button
	if (isset($_POST['delete_posts_cancel']))
		redirect(pun_link($pun_url['topic'], array($tid, sef_friendly($cur_topic['subject']))), $lang_common['Cancel redirect']);

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
				redirect(pun_link($pun_url['topic'], array($tid, sef_friendly($cur_topic['subject']))), $lang_common['No confirm redirect']);

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
			$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
			if ($pun_db->result($result) != substr_count($posts, ',') + 1)
				message($lang_common['Bad request']);

			// Delete the posts
			$query = array(
				'DELETE'	=> 'posts',
				'WHERE'		=> 'id IN('.$posts.')'
			);

			($hook = get_hook('mr_qr_delete_posts')) ? eval($hook) : null;
			$pun_db->query_build($query) or error(__FILE__, __LINE__);

			if ($db_type != 'mysql' && $db_type != 'mysqli')
			{
				require PUN_ROOT.'include/search_idx.php';
				strip_search_index($posts);
			}

			// Get last_post, last_post_id, and last_poster for the topic after deletion
			$query = array(
				'SELECT'	=> 'p.id, p.poster, p.posted',
				'FROM'		=> 'posts AS p',
				'WHERE'		=> 'p.topic_id='.$tid,
				'ORDER BY'	=> 'p.id',
				'LIMIT'		=> '1'
			);

			($hook = get_hook('mr_qr_get_topic_last_post_data')) ? eval($hook) : null;
			$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
			$last_post = $pun_db->fetch_assoc($result);

			// How many posts did we just delete?
			$num_posts_deleted = substr_count($posts, ',') + 1;

			// Update the topic
			$query = array(
				'UPDATE'	=> 'topics',
				'SET'		=> 'last_post='.$last_post['posted'].', last_post_id='.$last_post['id'].', last_poster=\''.$pun_db->escape($last_post['poster']).'\', num_replies=num_replies-'.$num_posts_deleted,
				'WHERE'		=> 'id='.$tid
			);

			($hook = get_hook('mr_qr_update_topic')) ? eval($hook) : null;
			$pun_db->query_build($query) or error(__FILE__, __LINE__);

			sync_forum($fid);

			redirect(pun_link($pun_url['topic'], array($tid, sef_friendly($cur_topic['subject']))), $lang_misc['Delete posts redirect']);
		}

		// Setup form
		$pun_page['set_count'] = $pun_page['fld_count'] = 0;
		$pun_page['form_action'] = pun_link($pun_url['delete_multiple'], array($fid, $tid));

		$pun_page['hidden_fields'] = array(
			'<input type="hidden" name="csrf_token" value="'.generate_form_token($pun_page['form_action']).'" />',
			'<input type="hidden" name="posts" value="'.implode(',', array_keys($posts)).'" />'
		);

		// Setup breadcrumbs
		$pun_page['crumbs'] = array(
			array($pun_config['o_board_title'], pun_link($pun_url['index'])),
			array($cur_forum['forum_name'], pun_link($pun_url['forum'], array($fid, sef_friendly($cur_forum['forum_name'])))),
			array($cur_topic['subject'], pun_link($pun_url['topic'], array($tid, sef_friendly($cur_topic['subject'])))),
			$lang_misc['Delete posts']
		);

		($hook = get_hook('mr_confirm_delete_posts_pre_header_load')) ? eval($hook) : null;

		define('PUN_PAGE', 'dialogue');
		require PUN_ROOT.'header.php';

?>
<div id="pun-main" class="main">

	<h1><span><?php echo end($pun_page['crumbs']) ?></span></h1>

	<div class="main-head">
		<h2><span><?php echo $lang_misc['Confirm post delete'] ?></span></h2>
	</div>

	<div class="main-content frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $pun_page['form_action'] ?>">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $pun_page['hidden_fields'])."\n" ?>
			</div>
			<fieldset class="frm-set set<?php echo ++$pun_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_misc['Delete posts'] ?></strong></legend>
				<div class="checkbox radbox">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>"><span class="fld-label"><?php echo $lang_common['Please confirm'] ?></span><br /><input type="checkbox" id="fld<?php echo $pun_page['fld_count'] ?>" name="req_confirm" value="1" checked="checked" /> <?php echo $lang_misc['Confirm post delete'] ?>.</label>
				</div>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="delete_posts_comply" value="<?php echo $lang_common['Delete'] ?>" /></span>
				<span class="cancel"><input type="submit" name="cancel" value="<?php echo $lang_common['Cancel'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

		$forum_id = $fid;

		require PUN_ROOT.'footer.php';
	}


	// Show the delete multiple posts view

	// Load the viewtopic.php language file
	require PUN_ROOT.'lang/'.$pun_user['language'].'/topic.php';

	// Used to disable the Move and Delete buttons if there are no replies to this topic
	$pun_page['button_status'] = ($cur_topic['num_replies'] == 0) ? ' disabled="disabled"' : '';


	// Determine the post offset (based on $_GET['p'])
	$pun_page['num_pages'] = ceil(($cur_topic['num_replies'] + 1) / $pun_user['disp_posts']);
	$pun_page['page'] = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $pun_page['num_pages']) ? 1 : $_GET['p'];
	$pun_page['start_from'] = $pun_user['disp_posts'] * ($pun_page['page'] - 1);
	$pun_page['finish_at'] = min(($pun_page['start_from'] + $pun_user['disp_posts']), ($cur_topic['num_replies'] + 1));

	// Generate paging links
	$pun_page['page_post'] = '<p class="paging"><span class="pages">'.$lang_common['Pages'].'</span> '.paginate($pun_page['num_pages'], $pun_page['page'], $pun_url['delete_multiple'], $lang_common['Paging separator'], array($fid, $tid)).'</p>';

	// Navigation links for header and page numbering for title/meta description
	if ($pun_page['page'] < $pun_page['num_pages'])
	{
		$pun_page['nav'][] = '<link rel="last" href="'.pun_sublink($pun_url['delete_multiple'], $pun_url['page'], $pun_page['num_pages'], array($fid, $tid)).'" title="'.$lang_common['Page'].' '.$pun_page['num_pages'].'" />';
		$pun_page['nav'][] = '<link rel="next" href="'.pun_sublink($pun_url['delete_multiple'], $pun_url['page'], ($pun_page['page'] + 1), array($fid, $tid)).'" title="'.$lang_common['Page'].' '.($pun_page['page'] + 1).'" />';
	}
	if ($pun_page['page'] > 1)
	{
		$pun_page['nav'][] = '<link rel="prev" href="'.pun_sublink($pun_url['delete_multiple'], $pun_url['page'], ($pun_page['page'] - 1), array($fid, $tid)).'" title="'.$lang_common['Page'].' '.($pun_page['page'] - 1).'" />';
		$pun_page['nav'][] = '<link rel="first" href="'.pun_link($pun_url['delete_multiple'], array($fid, $tid)).'" title="'.$lang_common['Page'].' 1" />';
	}

	// Generate page information
	if ($pun_page['num_pages'] > 1)
		$pun_page['main_info'] = '<span>'.sprintf($lang_common['Page number'], $pun_page['page'], $pun_page['num_pages']).' </span>'.sprintf($lang_common['Paged info'], $lang_common['Posts'], $pun_page['start_from'] + 1, $pun_page['finish_at'], $cur_topic['num_replies'] + 1);
	else
		$pun_page['main_info'] = sprintf($lang_common['Page info'], $lang_common['Posts'], ($cur_topic['num_replies'] + 1));

	if ($pun_config['o_censoring'] == '1')
		$cur_topic['subject'] = censor_words($cur_topic['subject']);

	// Setup form
	$pun_page['form_action'] = pun_link($pun_url['delete_multiple'], array($fid, $tid));

	// Setup breadcrumbs
	$pun_page['crumbs'] = array(
		array($pun_config['o_board_title'], pun_link($pun_url['index'])),
		array($cur_forum['forum_name'], pun_link($pun_url['forum'], array($fid, sef_friendly($cur_forum['forum_name'])))),
		array($cur_topic['subject'], pun_link($pun_url['topic'], array($tid, sef_friendly($cur_topic['subject'])))),
		$lang_topic['Delete posts']
	);

	($hook = get_hook('mr_post_actions_pre_header_load')) ? eval($hook) : null;

	define('PUN_PAGE', 'modtopic');
	require PUN_ROOT.'header.php';

?>
<div id="pun-main" class="main paged">

	<h1><span><?php echo end($pun_page['crumbs']) ?></span></h1>

	<form method="post" accept-charset="utf-8" action="<?php echo $pun_page['form_action'] ?>">

	<div class="hidden">
		<input type="hidden" name="csrf_token" value="<?php echo generate_form_token($pun_page['form_action']) ?>" />
	</div>

	<div class="paged-head">
		<?php echo $pun_page['page_post']."\n" ?>
	</div>

	<div class="main-head">
		<h2><span><?php echo $pun_page['main_info'] ?></span></h2>
		<p class="main-options"><?php echo $lang_misc['Select posts'] ?></p>
	</div>

	<div class="main-content topic">
<?php

	require PUN_ROOT.'include/parser.php';

	$pun_page['item_count'] = 0;	// Keep track of post numbers

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
		'LIMIT'		=> $pun_page['start_from'].','.$pun_user['disp_posts']
	);

	($hook = get_hook('mr_qr_get_posts')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	while ($cur_post = $pun_db->fetch_assoc($result))
	{
		++$pun_page['item_count'];

		$pun_page['post_options'] = $pun_page['message'] = array();
		$pun_page['user_ident'] = '';
		$pun_page['user_info'] = '';
		$cur_post['username'] = $cur_post['poster'];

		// Generate the post heading
		$pun_page['item_ident'] = array(
			'num'	=> '<strong>'.($pun_page['start_from'] + $pun_page['item_count']).'</strong>',
			'user'	=> '<cite>'.($cur_topic['posted'] == $cur_post['posted'] ? sprintf($lang_topic['Topic by'], pun_htmlencode($cur_post['username'])) : sprintf($lang_topic['Reply by'], pun_htmlencode($cur_post['username']))).'</cite>',
			'date'	=> '<span>'.format_time($cur_post['posted']).'</span>'
		);

		$pun_page['item_head'] = '<a class="permalink" rel="bookmark" title="'.$lang_topic['Permalink post'].'" href="'.pun_link($pun_url['post'], $cur_post['id']).'">'.implode(' ', $pun_page['item_ident']).'</a>';

		// Generate the checkbox field
		if ($cur_post['id'] != $cur_topic['first_post_id'])
			$pun_page['item_select'] = '<div class="checkbox radbox item-select"><label for="fld'.$cur_post['id'].'"><span class="fld-label">'.$lang_misc['Select post'].'</span> <input type="checkbox" id="fld'.$cur_post['id'].'" name="posts['.$cur_post['id'].']" value="1" /> '.$pun_page['item_ident']['num'].'</label></div>';

		// Generate author identification
		$pun_page['user_ident'] = (($cur_post['poster_id'] > 1) ? '<strong class="username"><a title="'.sprintf($lang_topic['Go to profile'], pun_htmlencode($cur_post['username'])).'" href="'.pun_link($pun_url['user'], $cur_post['poster_id']).'">'.pun_htmlencode($cur_post['username']).'</a></strong>' : '<strong class="username">'.pun_htmlencode($cur_post['username']).'</strong>');
		$pun_page['user_info'] = '<li class="title"><span><strong>'.$lang_topic['Title'].'</strong> '.get_title($cur_post).'</span></li>';

		// Give the post some class
		$pun_page['item_status'] = array(
			'post',
			($pun_page['item_count'] % 2 == 0) ? 'odd' : 'even'
		);

		if ($pun_page['item_count'] == 1)
			$pun_page['item_status'][] = 'firstpost';

		if (($pun_page['start_from'] + $pun_page['item_count']) == $pun_page['finish_at'])
			$pun_page['item_status'][] = 'lastpost';

		if ($cur_post['id'] == $cur_topic['first_post_id'])
			$pun_page['item_status'][] = 'topicpost';

		if ($cur_post['id'] == $cur_topic['first_post_id'])
			$pun_page['item_subject'] = $lang_common['Topic'].': '.$cur_topic['subject'];
		else
			$pun_page['item_subject'] = $lang_common['Re'].' '.$cur_topic['subject'];

		// Perform the main parsing of the message (BBCode, smilies, censor words etc)
		$pun_page['message'][] = parse_message($cur_post['message'], $cur_post['hide_smilies']);

		if ($cur_post['edited'] != '')
			$pun_page['message'][] = '<p class="lastedit"><em>'.sprintf($lang_topic['Last edited'], pun_htmlencode($cur_post['edited_by']), format_time($cur_post['edited'])).'</em></p>';

		($hook = get_hook('mr_post_actions_row_pre_display')) ? eval($hook) : null;

?>
		<div class="<?php echo implode(' ', $pun_page['item_status']) ?>">
			<div class="postmain">
				<div id="p<?php echo $cur_post['id'] ?>" class="posthead">
					<h3><?php echo $pun_page['item_head'] ?></h3>
				</div>
				<?php if (isset($pun_page['item_select'])) echo $pun_page['item_select']."\n" ?>
				<div class="postbody">
					<div class="user">
						<h4 class="user-ident"><?php echo $pun_page['user_ident'] ?></h4>
						<ul class="user-info">
							<?php echo $pun_page['user_info']."\n" ?>
						</ul>
					</div>
					<div class="post-entry">
						<h4 class="entry-title"><?php echo $pun_page['item_subject'] ?></h4>
						<div class="entry-content">
							<?php echo implode("\n\t\t\t\t\t\t\t", $pun_page['message'])."\n" ?>
						</div>
					</div>
				</div>
			</div>
		</div>
<?php

	}

?>
	</div>

	<div class="main-foot">
		<p class="h2"><strong><?php echo $pun_page['main_info'] ?></strong></p>
	</div>

	<div class="paged-foot">
		<p class="submitting"><span class="submit"><input type="submit" name="delete_posts" value="<?php echo $lang_misc['Delete posts'] ?>"<?php echo $pun_page['button_status'] ?> /></span></p>
		<?php echo $pun_page['page_post']."\n" ?>
	</div>

	</form>

</div>

<div id="pun-crumbs-foot">
	<p class="crumbs"><?php echo generate_crumbs(false) ?></p>
</div>

<?php

	$forum_id = $fid;

	require PUN_ROOT.'footer.php';
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
		$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);

		if (!$pun_db->num_rows($result))
			message($lang_common['Bad request']);

		$move_to_forum_name = $pun_db->result($result);

		// Verify that the topic IDs are valid
		$query = array(
			'SELECT'	=> 'COUNT(t.id)',
			'FROM'		=> 'topics AS t',
			'WHERE'		=> 't.id IN('.implode(',', $topics).') AND t.forum_id='.$fid
		);

		($hook = get_hook('mr_qr_verify_topic_ids')) ? eval($hook) : null;
		$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
		if ($pun_db->result($result) != count($topics))
			message($lang_common['Bad request']);

		// Delete any redirect topics if there are any (only if we moved/copied the topic back to where it where it was once moved from)
		$query = array(
			'DELETE'	=> 'topics',
			'WHERE'		=> 'forum_id='.$move_to_forum.' AND moved_to IN('.implode(',', $topics).')'
		);

		($hook = get_hook('mr_qr_delete_redirect_topics')) ? eval($hook) : null;
		$pun_db->query_build($query) or error(__FILE__, __LINE__);

		// Move the topic(s)
		$query = array(
			'UPDATE'	=> 'topics',
			'SET'		=> 'forum_id='.$move_to_forum,
			'WHERE'		=> 'id IN('.implode(',', $topics).')'
		);

		($hook = get_hook('mr_qr_move_topics')) ? eval($hook) : null;
		$pun_db->query_build($query) or error(__FILE__, __LINE__);

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
				$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
				$moved_to = $pun_db->fetch_assoc($result);

				// Create the redirect topic
				$query = array(
					'INSERT'	=> 'poster, subject, posted, last_post, moved_to, forum_id',
					'INTO'		=> 'topics',
					'VALUES'	=> '\''.$pun_db->escape($moved_to['poster']).'\', \''.$pun_db->escape($moved_to['subject']).'\', '.$moved_to['posted'].', '.$moved_to['last_post'].', '.$cur_topic.', '.$fid
				);

				($hook = get_hook('mr_qr_add_redirect_topic')) ? eval($hook) : null;
				$pun_db->query_build($query) or error(__FILE__, __LINE__);
			}
		}

		sync_forum($fid);			// Synchronize the forum FROM which the topic was moved
		sync_forum($move_to_forum);	// Synchronize the forum TO which the topic was moved

		$pun_page['redirect_msg'] = (count($topics) > 1) ? $lang_misc['Move topics redirect'] : $lang_misc['Move topic redirect'];
		redirect(pun_link($pun_url['forum'], array($move_to_forum, sef_friendly($move_to_forum_name))), $pun_page['redirect_msg']);
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
		$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);

		if (!$pun_db->num_rows($result))
			message($lang_common['Bad request']);

		$subject = $pun_db->result($result);
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
				'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].')'
			)
		),
		'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND f.redirect_url IS NULL AND f.id!='.$fid,
		'ORDER BY'	=> 'c.disp_position, c.id, f.disp_position'
	);

	($hook = get_hook('mr_qr_get_target_forums')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	$num_forums = $pun_db->num_rows($result);

	if (!$num_forums)
		message($lang_misc['Nowhere to move']);

	$forum_list = array();
	for ($i = 0; $i < $num_forums; ++$i)
		$forum_list[] = $pun_db->fetch_assoc($result);

	// Setup form
	$pun_page['fld_count'] = $pun_page['set_count'] = 0;
	$pun_page['form_action'] = pun_link($pun_url['moderate_forum'], $fid);

	$pun_page['hidden_fields'] = array(
		'<input type="hidden" name="csrf_token" value="'.generate_form_token($pun_page['form_action']).'" />',
		'<input type="hidden" name="topics" value="'.$topics.'" />'
	);

	// Setup breadcrumbs
	$pun_page['crumbs'][] = array($pun_config['o_board_title'], pun_link($pun_url['index']));
	$pun_page['crumbs'][] = array($cur_forum['forum_name'], pun_link($pun_url['forum'], array($fid, sef_friendly($cur_forum['forum_name']))));
	if ($action == 'single')
		$pun_page['crumbs'][] = array($subject, pun_link($pun_url['topic'], array($topics, sef_friendly($subject))));
	else
		$pun_page['crumbs'][] = array($lang_misc['Moderate forum'], pun_link($pun_url['moderate_forum'], $fid));
	$pun_page['crumbs'][] =	($action == 'single') ? $lang_misc['Move topic'] : $lang_misc['Move topics'];

	($hook = get_hook('mr_move_topics_pre_header_load')) ? eval($hook) : null;

	define('PUN_PAGE', 'dialogue');
	require PUN_ROOT.'header.php';

?>
<div id="pun-main" class="main">

	<h1><span><?php echo end($pun_page['crumbs']) ?></span></h1>

	<div class="main-head">
		<h2><span><?php echo end($pun_page['crumbs']).' '.$lang_misc['To new forum'] ?></span></h2>
	</div>

	<div class="main-content frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $pun_page['form_action'] ?>">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $pun_page['hidden_fields'])."\n" ?>
			</div>
			<fieldset class="frm-set set<?php echo ++$pun_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_misc['Move topic'] ?></strong></legend>
				<div class="frm-fld select">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_misc['Move to'] ?></span><br />
						<span class="fld-input"><select id="fld<?php echo $pun_page['fld_count'] ?>" name="move_to_forum">
<?php

	$pun_page['cur_category'] = 0;
	foreach ($forum_list as $cur_forum)
	{
		if ($cur_forum['cid'] != $pun_page['cur_category'])	// A new category since last iteration?
		{
			if ($pun_page['cur_category'])
				echo "\t\t\t\t\t\t".'</optgroup>'."\n";

			echo "\t\t\t\t\t\t".'<optgroup label="'.pun_htmlencode($cur_forum['cat_name']).'">'."\n";
			$pun_page['cur_category'] = $cur_forum['cid'];
		}

		if ($cur_forum['fid'] != $fid)
			echo "\t\t\t\t\t\t".'<option value="'.$cur_forum['fid'].'">'.pun_htmlencode($cur_forum['forum_name']).'</option>'."\n";
	}

?>
						</optgroup>
						</select></span>
					</label>
				</div>
				<div class="checkbox radbox">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>"><span class="fld-label"><?php echo $lang_misc['Redirect topic'] ?></span><br /><input type="checkbox" id="fld<?php echo $pun_page['fld_count'] ?>" name="with_redirect" value="1"<?php if ($action == 'single') echo ' checked="checked"' ?> /> <?php echo ($action == 'single') ? $lang_misc['Leave redirect'] : $lang_misc['Leave redirects'] ?></label>
				</div>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="move_topics_to" value="<?php echo $lang_misc['Move'] ?>" /></span>
				<span class="cancel"><input type="submit" name="cancel" value="<?php echo $lang_common['Cancel'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

	$forum_id = $fid;

	require PUN_ROOT.'footer.php';
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
			redirect(pun_link($pun_url['forum'], array($fid, sef_friendly($cur_forum['forum_name']))), $lang_common['Cancel redirect']);

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
		$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
		if ($pun_db->result($result) != substr_count($topics, ',') + 1)
			message($lang_common['Bad request']);

		// Delete the topics and any redirect topics
		$query = array(
			'DELETE'	=> 'topics',
			'WHERE'		=> 'id IN('.$topics.') OR moved_to IN('.$topics.')'
		);

		($hook = get_hook('mr_qr_delete_topics')) ? eval($hook) : null;
		$pun_db->query_build($query) or error(__FILE__, __LINE__);

		// Delete any subscriptions
		$query = array(
			'DELETE'	=> 'subscriptions',
			'WHERE'		=> 'topic_id IN('.$topics.')'
		);

		($hook = get_hook('mr_qr_delete_subscriptions')) ? eval($hook) : null;
		$pun_db->query_build($query) or error(__FILE__, __LINE__);

		if ($db_type != 'mysql' && $db_type != 'mysqli')
		{
			// Create a list of the post ID's in the deleted topic and strip the search index
			$query = array(
				'SELECT'	=> 'p.id',
				'FROM'		=> 'posts AS p',
				'WHERE'		=> 'p.topic_id IN('.$topics.')'
			);

			($hook = get_hook('mr_qr_get_deleted_posts')) ? eval($hook) : null;
			$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);

			$post_ids = '';
			while ($row = $pun_db->fetch_row($result))
				$post_ids .= ($post_ids != '') ? ','.$row[0] : $row[0];

			// Strip the search index provided we're not just deleting redirect topics
			if ($post_ids != '')
			{
				require PUN_ROOT.'include/search_idx.php';
				strip_search_index($post_ids);
			}
		}

		// Delete posts
		$query = array(
			'DELETE'	=> 'posts',
			'WHERE'		=> 'topic_id IN('.$topics.')'
		);

		($hook = get_hook('mr_qr_delete_topic_posts')) ? eval($hook) : null;
		$pun_db->query_build($query) or error(__FILE__, __LINE__);

		sync_forum($fid);

		redirect(pun_link($pun_url['forum'], array($fid, sef_friendly($cur_forum['forum_name']))), $lang_misc['Delete topics redirect']);
	}


	// Setup form
	$pun_page['fld_count'] = $pun_page['set_count'] = 0;
	$pun_page['form_action'] = pun_link($pun_url['moderate_forum'], $fid);

	$pun_page['hidden_fields'] = array(
		'<input type="hidden" name="csrf_token" value="'.generate_form_token($pun_page['form_action']).'" />',
		'<input type="hidden" name="topics" value="'.implode(',', array_keys($topics)).'" />'
	);

	// Setup breadcrumbs
	$pun_page['crumbs'] = array(
		array($pun_config['o_board_title'], pun_link($pun_url['index'])),
		array($cur_forum['forum_name'], pun_link($pun_url['forum'], array($fid, sef_friendly($cur_forum['forum_name'])))),
		array($lang_misc['Moderate forum'], pun_link($pun_url['moderate_forum'], $fid)),
		$lang_misc['Delete topics']
	);

	($hook = get_hook('mr_delete_topics_pre_header_load')) ? eval($hook) : null;

	define('PUN_PAGE', 'dialogue');
	require PUN_ROOT.'header.php';

?>
<div id="pun-main" class="main">

	<h1><span><?php echo end($pun_page['crumbs']) ?></span></h1>

	<div class="main-head">
		<h2><span><?php echo $lang_misc['Confirm topic delete'] ?></span></h2>
	</div>

	<div class="main-content frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $pun_page['form_action'] ?>">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $pun_page['hidden_fields'])."\n" ?>
			</div>
			<fieldset class="frm-set set<?php echo ++$pun_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_misc['Delete topics'] ?></strong></legend>
				<div class="checkbox radbox">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>"><span class="fld-label"><?php echo $lang_common['Please confirm'] ?></span><br /><input type="checkbox" id="fld<?php echo $pun_page['fld_count'] ?>" name="req_confirm" value="1" checked="checked" /> <?php echo $lang_misc['Delete topics comply'] ?></label>
				</div>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="delete_topics_comply" value="<?php echo $lang_common['Delete'] ?>" /></span>
				<span class="cancel"><input type="submit" name="cancel" value="<?php echo $lang_common['Cancel'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

	$forum_id = $fid;

	require PUN_ROOT.'footer.php';
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
		$pun_db->query_build($query) or error(__FILE__, __LINE__);

		$pun_page['redirect_msg'] = ($action) ? $lang_misc['Close topics redirect'] : $lang_misc['Open topics redirect'];
		redirect(pun_link($pun_url['moderate_forum'], $fid), $pun_page['redirect_msg']);
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
		$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);

		if (!$pun_db->num_rows($result))
			message($lang_common['Bad request']);

		$subject = $pun_db->result($result);

		$query = array(
			'UPDATE'	=> 'topics',
			'SET'		=> 'closed='.$action,
			'WHERE'		=> 'id='.$topic_id.' AND forum_id='.$fid
		);

		($hook = get_hook('mr_qr_open_close_topic')) ? eval($hook) : null;
		$pun_db->query_build($query) or error(__FILE__, __LINE__);

		$pun_page['redirect_msg'] = ($action) ? $lang_misc['Close topic redirect'] : $lang_misc['Open topic redirect'];
		redirect(pun_link($pun_url['topic'], array($topic_id, sef_friendly($subject))), $pun_page['redirect_msg']);
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
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);

	if (!$pun_db->num_rows($result))
		message($lang_common['Bad request']);

	$subject = $pun_db->result($result);

	$query = array(
		'UPDATE'	=> 'topics',
		'SET'		=> 'sticky=1',
		'WHERE'		=> 'id='.$stick.' AND forum_id='.$fid
	);

	($hook = get_hook('mr_qr_stick_topic')) ? eval($hook) : null;
	$pun_db->query_build($query) or error(__FILE__, __LINE__);

	redirect(pun_link($pun_url['topic'], array($stick, sef_friendly($subject))), $lang_misc['Stick topic redirect']);
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
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);

	if (!$pun_db->num_rows($result))
		message($lang_common['Bad request']);

	$subject = $pun_db->result($result);

	$query = array(
		'UPDATE'	=> 'topics',
		'SET'		=> 'sticky=0',
		'WHERE'		=> 'id='.$unstick.' AND forum_id='.$fid
	);

	($hook = get_hook('mr_qr_unstick_topic')) ? eval($hook) : null;
	$pun_db->query_build($query) or error(__FILE__, __LINE__);

	redirect(pun_link($pun_url['topic'], array($unstick, sef_friendly($subject))), $lang_misc['Unstick topic redirect']);
}


($hook = get_hook('mr_new_action')) ? eval($hook) : null;


// No specific forum moderation action was specified in the query string, so we'll display the moderate forum view

// Load the viewforum.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/forum.php';

// Determine the topic offset (based on $_GET['p'])
$pun_page['num_pages'] = ceil($cur_forum['num_topics'] / $pun_user['disp_topics']);

$pun_page['page'] = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $pun_page['num_pages']) ? 1 : $_GET['p'];
$pun_page['start_from'] = $pun_user['disp_topics'] * ($pun_page['page'] - 1);
$pun_page['finish_at'] = min(($pun_page['start_from'] + $pun_user['disp_topics']), ($cur_forum['num_topics']));

// Generate paging links
$pun_page['page_post'] = '<p class="paging"><span class="pages">'.$lang_common['Pages'].'</span> '.paginate($pun_page['num_pages'], $pun_page['page'], $pun_url['moderate_forum'], $lang_common['Paging separator'], $fid).'</p>';

// Navigation links for header and page numbering for title/meta description
if ($pun_page['page'] < $pun_page['num_pages'])
{
	$pun_page['nav'][] = '<link rel="last" href="'.pun_sublink($pun_url['moderate_forum'], $pun_url['page'], $pun_page['num_pages'], $fid).'" title="'.$lang_common['Page'].' '.$pun_page['num_pages'].'" />';
	$pun_page['nav'][] = '<link rel="next" href="'.pun_sublink($pun_url['moderate_forum'], $pun_url['page'], ($pun_page['page'] + 1), $fid).'" title="'.$lang_common['Page'].' '.($pun_page['page'] + 1).'" />';
}
if ($pun_page['page'] > 1)
{
	$pun_page['nav'][] = '<link rel="prev" href="'.pun_sublink($pun_url['moderate_forum'], $pun_url['page'], ($pun_page['page'] - 1), $fid).'" title="'.$lang_common['Page'].' '.($pun_page['page'] - 1).'" />';
	$pun_page['nav'][] = '<link rel="first" href="'.pun_link($pun_url['moderate_forum'], $fid).'" title="'.$lang_common['Page'].' 1" />';
}

// Generate page information
if ($pun_page['num_pages'] > 1)
	$pun_page['main_info'] = '<span>'.sprintf($lang_common['Page number'], $pun_page['page'], $pun_page['num_pages']).' </span>'.sprintf($lang_common['Paged info'], $lang_common['Topics'], $pun_page['start_from'] + 1, $pun_page['finish_at'], $cur_forum['num_topics']);
else
	$pun_page['main_info'] = (($pun_db->num_rows($result)) ? sprintf($lang_common['Page info'], $lang_common['Topics'], $cur_forum['num_topics']) : $lang_forum['No topics']);

// Setup form
$pun_page['fld_count'] = 0;
$pun_page['form_action'] = pun_link($pun_url['moderate_forum'], $fid);

// Setup breadcrumbs
$pun_page['crumbs'] = array(
	array($pun_config['o_board_title'], pun_link($pun_url['index'])),
	array($cur_forum['forum_name'], pun_link($pun_url['forum'], array($fid, sef_friendly($cur_forum['forum_name'])))),
	$lang_forum['Moderate forum']
);

($hook = get_hook('mr_topic_actions_pre_header_load')) ? eval($hook) : null;

define('PUN_PAGE', 'modforum');
require PUN_ROOT.'header.php';

?>
<div id="pun-main" class="main paged">

	<h1><span><?php echo end($pun_page['crumbs']) ?></span></h1>

	<form method="post" accept-charset="utf-8" action="<?php echo $pun_page['form_action'] ?>">

	<div class="paged-head">
		<?php echo $pun_page['page_post']."\n" ?>
	</div>

	<div class="main-head">
		<h2><span><?php echo $pun_page['main_info'] ?></span></h2>
		<p class="main-options"><?php echo $lang_misc['Select topics'] ?></p>
	</div>

	<div id="forum<?php echo $fid ?>" class="main-content forum">
		<div class="hidden">
			<input type="hidden" name="csrf_token" value="<?php echo generate_form_token($pun_page['form_action']) ?>" />
		</div>
		<table cellspacing="0" summary="<?php echo $lang_forum['Table summary mods'].pun_htmlencode($cur_forum['forum_name']) ?>">
			<thead>
				<tr>
					<th class="tcl" scope="col"><?php echo $lang_common['Topic'] ?></th>
					<th class="tc2" scope="col"><?php echo $lang_common['Replies'] ?></th>
<?php if ($pun_config['o_topic_views'] == '1'): ?>					<th class="tc3" scope="col"><?php echo $lang_forum['Views'] ?></th>
<?php endif; ?>					<th class="tcr" scope="col"><?php echo $lang_common['Last post'] ?></th>
					<th class="tcmod" scope="col"><?php echo $lang_misc['Select'] ?></th>
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
	'LIMIT'		=>	$pun_page['start_from'].', '.$pun_user['disp_topics']
);

($hook = get_hook('mr_qr_get_topics')) ? eval($hook) : null;
$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);

// If there are topics in this forum.
if ($pun_db->num_rows($result))
{
	$pun_page['button_status'] = '';
	$pun_page['item_count'] = 0;

	while ($cur_topic = $pun_db->fetch_assoc($result))
	{
		++$pun_page['item_count'];

		// Start from scratch
		$pun_page['item_subject'] = $pun_page['item_status'] = $pun_page['item_last_post'] = $pun_page['item_alt_message'] = $pun_page['item_nav'] = array();
		$pun_page['item_indicator'] = '';
		$pun_page['item_alt_message'][] = $lang_common['Topic'].' '.($pun_page['start_from'] + $pun_page['item_count']);

		if ($pun_config['o_censoring'] == '1')
			$cur_topic['subject'] = censor_words($cur_topic['subject']);

		if ($cur_topic['moved_to'] != null)
		{
			$pun_page['item_status'][] = 'moved';
			$pun_page['item_last_post'][] = $pun_page['item_alt_message'][] = $lang_forum['Moved'];
			$pun_page['item_subject'][] = '<a href="'.pun_link($pun_url['topic'], array($cur_topic['moved_to'], sef_friendly($cur_topic['subject']))).'">'.pun_htmlencode($cur_topic['subject']).'</a>';
			$pun_page['item_subject'][] = '<span class="byuser">'.sprintf($lang_common['By user'], pun_htmlencode($cur_topic['poster'])).'</span>';
			$cur_topic['num_replies'] = $cur_topic['num_views'] = ' - ';
			$pun_page['ghost_topic'] = true;
		}
		else
		{
			$pun_page['ghost_topic'] = false;

			if ($cur_topic['sticky'] == '1')
			{
				$pun_page['item_subject'][] = $lang_forum['Sticky'];
				$pun_page['item_status'][] = 'sticky';
			}

			if ($cur_topic['closed'] == '1')
			{
				$pun_page['item_subject'][] = $lang_common['Closed'];
				$pun_page['item_status'][] = 'closed';
			}

			$pun_page['item_subject'][] = '<a href="'.pun_link($pun_url['topic'], array($cur_topic['id'], sef_friendly($cur_topic['subject']))).'">'.pun_htmlencode($cur_topic['subject']).'</a>';

			$pun_page['item_pages'] = ceil(($cur_topic['num_replies'] + 1) / $pun_user['disp_posts']);

			if ($pun_page['item_pages'] > 1)
				$pun_page['item_nav'][] = paginate($pun_page['item_pages'], -1, $pun_url['topic'], $lang_common['Page separator'], array($cur_topic['id'], sef_friendly($cur_topic['subject'])));

			// Does this topic contain posts we haven't read? If so, tag it accordingly.
			if ($cur_topic['last_post'] > $pun_user['last_visit'] && (!isset($tracked_topics['topics'][$cur_topic['id']]) || $tracked_topics['topics'][$cur_topic['id']] < $cur_topic['last_post']) && (!isset($tracked_topics['forums'][$fid]) || $tracked_topics['forums'][$fid] < $cur_topic['last_post']) && !$pun_page['ghost_topic'])
			{
				$pun_page['item_nav'][] = '<a href="'.pun_link($pun_url['topic_new_posts'], array($cur_topic['id'], sef_friendly($cur_topic['subject']))).'" title="'.$lang_forum['New posts info'].'">'.$lang_common['New posts'].'</a>';
				$pun_page['item_status'][] = 'new';
			}

			if (!empty($pun_page['item_nav']))
				$pun_page['item_subject'][] = '<span class="topic-nav">[&#160;'.implode('&#160;&#160;', $pun_page['item_nav']).'&#160;]</span>';

			$pun_page['item_subject'][] = '<span class="byuser">'.sprintf($lang_common['By user'], pun_htmlencode($cur_topic['poster'])).'</span>';
			$pun_page['item_last_post'][] = '<a href="'.pun_link($pun_url['post'], $cur_topic['last_post_id']).'">'.format_time($cur_topic['last_post']).'</a>';
			$pun_page['item_last_post'][] = '<span class="byuser">'.sprintf($lang_common['By user'], pun_htmlencode($cur_topic['last_poster'])).'</span>';

			if (empty($pun_page['item_status']))
				$pun_page['item_status'][] = 'normal';

			$pun_page['subject_label'] = $cur_topic['subject'];
		}

		$pun_page['item_style'] = (($pun_page['item_count'] % 2 != 0) ? 'odd' : 'even').' '.implode(' ', $pun_page['item_status']);
		$pun_page['item_indicator'] = '<span class="status '.implode(' ', $pun_page['item_status']).'" title="'.implode(' - ', $pun_page['item_alt_message']).'"><img src="'.$base_url.'/style/'.$pun_user['style'].'/status.png" alt="'.implode(' - ', $pun_page['item_alt_message']).'" />'.$pun_page['item_indicator'].'</span>';

		($hook = get_hook('mr_topic_actions_row_pre_display')) ? eval($hook) : null;

?>
				<tr class="<?php echo $pun_page['item_style'] ?>">
					<td class="tcl"><?php echo $pun_page['item_indicator'].' '.implode(' ', $pun_page['item_subject']) ?></td>
					<td class="tc2"><?php echo (!$pun_page['ghost_topic']) ? $cur_topic['num_replies'] : ' - ' ?></td>
<?php if ($pun_config['o_topic_views'] == '1'): ?>					<td class="tc3"><?php echo (!$pun_page['ghost_topic']) ? $cur_topic['num_views'] : ' - ' ?></td>
<?php endif; ?>					<td class="tcr"><?php echo implode(' ', $pun_page['item_last_post']) ?></td>
					<td class="tcmod"><label for="fld<?php echo ++$pun_page['fld_count'] ?>"><input id="fld<?php echo $pun_page['fld_count'] ?>" type="checkbox" name="topics[<?php echo $cur_topic['id'] ?>]" value="1" /> <span><?php echo $pun_page['subject_label'] ?></span></label></td>
				</tr>
<?php

	}
}
else
{
	$pun_page['button_status'] = ' disabled="disabled"';
	$pun_page['item_indicator'] = '<span class="status empty" title="'.$lang_forum['No topics'].'"><img src="'.$base_url.'/style/'.$pun_user['style'].'/status.png" alt="'.$lang_forum['No topics'].'" /></span>';

	($hook = get_hook('mr_topic_actions_forum_empty')) ? eval($hook) : null;

?>
				<tr class="odd empty">
					<td class="tcl"><?php echo $pun_page['item_indicator'].' '.$lang_forum['First topic nag'] ?></td>
					<td class="tc2"> - </td>
<?php if ($pun_config['o_topic_views'] == '1'): ?>					<td class="tc3"> - </td>
<?php endif; ?>					<td class="tcr"><?php echo $lang_forum['Never'] ?></td>
					<td class="tcmod"> - </td>
				</tr>
<?php

}

?>
			</tbody>
		</table>
<?php

// Setup moderator control buttons
$pun_page['main_mod_submit'] = array(
	'<span class="submit"><input type="submit" name="move_topics" value="'.$lang_misc['Move'].'"'.$pun_page['button_status'].' /></span>',
	'<span class="submit"><input type="submit" name="delete_topics" value="'.$lang_common['Delete'].'"'.$pun_page['button_status'].' /></span>',
	'<span class="submit"><input type="submit" name="open" value="'.$lang_misc['Open'].'"'.$pun_page['button_status'].' /></span>',
	'<span class="submit"><input type="submit" name="close" value="'.$lang_misc['Close'].'"'.$pun_page['button_status'].' /></span>'
);

($hook = get_hook('mr_topic_actions_post_topic_list')) ? eval($hook) : null;

?>
	</div>

	<div class="main-foot">
		<p class="h2"><strong><?php echo $pun_page['main_info'] ?></strong></p>
	</div>

	<div class="paged-foot">
		<p class="submitting"><?php echo implode("\n\t\t\t", $pun_page['main_mod_submit'])."\n" ?></p>
		<?php echo $pun_page['page_post']."\n" ?>
	</div>

	</form>

</div>

<div id="pun-crumbs-foot">
	<p class="crumbs"><?php echo generate_crumbs(false) ?></p>
</div>
<?php

$forum_id = $fid;

($hook = get_hook('mr_end')) ? eval($hook) : null;

require PUN_ROOT.'footer.php';
