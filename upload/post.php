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

($hook = get_hook('po_start')) ? eval($hook) : null;

if ($forum_user['g_read_board'] == '0')
	message($lang_common['No view']);

// Load the post.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/post.php';


$tid = isset($_GET['tid']) ? intval($_GET['tid']) : 0;
$fid = isset($_GET['fid']) ? intval($_GET['fid']) : 0;
if ($tid < 1 && $fid < 1 || $tid > 0 && $fid > 0)
	message($lang_common['Bad request']);


// Fetch some info about the topic and/or the forum
if ($tid)
{
	$query = array(
		'SELECT'	=> 'f.id, f.forum_name, f.moderators, f.redirect_url, fp.post_replies, fp.post_topics, t.subject, t.closed, s.user_id AS is_subscribed',
		'FROM'		=> 'topics AS t',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'forums AS f',
				'ON'			=> 'f.id=t.forum_id'
			),
			array(
				'LEFT JOIN'		=> 'forum_perms AS fp',
				'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
			),
			array(
				'LEFT JOIN'		=> 'subscriptions AS s',
				'ON'			=> '(t.id=s.topic_id AND s.user_id='.$forum_user['id'].')'
			)
		),
		'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND t.id='.$tid
	);
}
else
{
	$query = array(
		'SELECT'	=> 'f.id, f.forum_name, f.moderators, f.redirect_url, fp.post_replies, fp.post_topics',
		'FROM'		=> 'forums AS f',
		'JOINS'		=> array(
			array(
				'LEFT JOIN'		=> 'forum_perms AS fp',
				'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
			)
		),
		'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND f.id='.$fid
	);
}

($hook = get_hook('po_qr_get_forum_info')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

if (!$forum_db->num_rows($result))
	message($lang_common['Bad request']);

$cur_posting = $forum_db->fetch_assoc($result);
$is_subscribed = $tid && $cur_posting['is_subscribed'];


// Is someone trying to post into a redirect forum?
if ($cur_posting['redirect_url'] != '')
	message($lang_common['Bad request']);

// Sort out who the moderators are and if we are currently a moderator (or an admin)
$mods_array = ($cur_posting['moderators'] != '') ? unserialize($cur_posting['moderators']) : array();
$forum_page['is_admmod'] = ($forum_user['g_id'] == FORUM_ADMIN || ($forum_user['g_moderator'] == '1' && array_key_exists($forum_user['username'], $mods_array))) ? true : false;

// Do we have permission to post?
if ((($tid && (($cur_posting['post_replies'] == '' && $forum_user['g_post_replies'] == '0') || $cur_posting['post_replies'] == '0')) ||
	($fid && (($cur_posting['post_topics'] == '' && $forum_user['g_post_topics'] == '0') || $cur_posting['post_topics'] == '0')) ||
	(isset($cur_posting['closed']) && $cur_posting['closed'] == '1')) &&
	!$forum_page['is_admmod'])
	message($lang_common['No permission']);


// Start with a clean slate
$errors = array();

// Did someone just hit "Submit" or "Preview"?
if (isset($_POST['form_sent']))
{
	($hook = get_hook('po_form_submitted')) ? eval($hook) : null;

	// Make sure form_user is correct
	if (($forum_user['is_guest'] && $_POST['form_user'] != 'Guest') || (!$forum_user['is_guest'] && $_POST['form_user'] != $forum_user['username']))
		message($lang_common['Bad request']);

	// Flood protection
	if (!$forum_user['is_guest'] && !isset($_POST['preview']) && $forum_user['last_post'] != '' && (time() - $forum_user['last_post']) < $forum_user['g_post_flood'] && (time() - $forum_user['last_post']) >= 0)
		$errors[] = sprintf($lang_post['Flood'], $forum_user['g_post_flood']);

	// If it's a new topic
	if ($fid)
	{
		$subject = trim($_POST['req_subject']);

		if ($subject == '')
			$errors[] = $lang_post['No subject'];
		else if (forum_strlen($subject) > 70)
			$errors[] = $lang_post['Too long subject'];
		else if ($forum_config['p_subject_all_caps'] == '0' && strtoupper($subject) == $subject && !$forum_page['is_admmod'])
			$subject = ucwords(strtolower($subject));
	}

	// If the user is logged in we get the username and e-mail from $forum_user
	if (!$forum_user['is_guest'])
	{
		$username = $forum_user['username'];
		$email = $forum_user['email'];
	}
	// Otherwise it should be in $_POST
	else
	{
		$username = trim($_POST['req_username']);
		$email = strtolower(trim(($forum_config['p_force_guest_email'] == '1') ? $_POST['req_email'] : $_POST['email']));

		// Load the profile.php language file
		require FORUM_ROOT.'lang/'.$forum_user['language'].'/profile.php';

		// It's a guest, so we have to validate the username
		$errors = array_merge($errors, validate_username($username));

		if ($forum_config['p_force_guest_email'] == '1' || $email != '')
		{
			require FORUM_ROOT.'include/email.php';
			if (!is_valid_email($email))
				$errors[] = $lang_common['Invalid e-mail'];
		}
	}

	// If we're an administrator or moderator, make sure the CSRF token in $_POST is valid
	if ($forum_user['is_admmod'] && (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== generate_form_token(get_current_url())))
		$errors[] = $lang_post['CSRF token mismatch'];

	// Clean up message from POST
	$message = forum_linebreaks(trim($_POST['req_message']));

	if ($message == '')
		$errors[] = $lang_post['No message'];
	else if (strlen($message) > FORUM_MAX_POSTSIZE)
		$errors[] = $lang_post['Too long message'];
	else if ($forum_config['p_message_all_caps'] == '0' && strtoupper($message) == $message && !$forum_page['is_admmod'])
		$message = ucwords(strtolower($message));

	// Validate BBCode syntax
	if ($forum_config['p_message_bbcode'] == '1' || $forum_config['o_make_links'] == '1')
	{
		require FORUM_ROOT.'include/parser.php';
		$message = preparse_bbcode($message, $errors);
	}

	$hide_smilies = isset($_POST['hide_smilies']) ? 1 : 0;
	$subscribe = isset($_POST['subscribe']) ? 1 : 0;

	($hook = get_hook('po_end_validation')) ? eval($hook) : null;

	$now = time();

	// Did everything go according to plan?
	if (empty($errors) && !isset($_POST['preview']))
	{
		// If it's a reply
		if ($tid)
		{
			$post_info = array(
				'is_guest'		=>	$forum_user['is_guest'],
				'poster'		=>	$username,
				'poster_id'		=>	$forum_user['id'],	// Always 1 for guest posts
				'poster_email'	=>	($forum_user['is_guest'] && $email != '') ? $email : null,	// Always null for non-guest posts
				'subject'		=>	$cur_posting['subject'],
				'message'		=>	$message,
				'hide_smilies'	=>	$hide_smilies,
				'posted'		=>	$now,
				'subscr_action'	=>	($forum_config['o_subscriptions'] == '1' && $subscribe && !$is_subscribed) ? 1 : (($forum_config['o_subscriptions'] == '1' && !$subscribe && $is_subscribed) ? 2 : 0),
				'topic_id'		=>	$tid,
				'forum_id'		=>	$cur_posting['id']
			);

			($hook = get_hook('po_pre_add_post')) ? eval($hook) : null;
			add_post($post_info, $new_pid);
		}
		// If it's a new topic
		else if ($fid)
		{
			$post_info = array(
				'is_guest'		=>	$forum_user['is_guest'],
				'poster'		=>	$username,
				'poster_id'		=>	$forum_user['id'],	// Always 1 for guest posts
				'poster_email'	=>	($forum_user['is_guest'] && $email != '') ? $email : null,	// Always null for non-guest posts
				'subject'		=>	$subject,
				'message'		=>	$message,
				'hide_smilies'	=>	$hide_smilies,
				'posted'		=>	$now,
				'subscribe'		=>	($forum_config['o_subscriptions'] == '1' && (isset($_POST['subscribe']) && $_POST['subscribe'] == '1')),
				'forum_id'		=>	$fid
			);

			($hook = get_hook('po_pre_add_topic')) ? eval($hook) : null;
			add_topic($post_info, $new_tid, $new_pid);
		}

		if (!$forum_user['is_guest'])
		{
			// If the posting user is logged in, increment his/her post count
			$query = array(
				'UPDATE'	=> 'users',
				'SET'		=> 'num_posts=num_posts+1, last_post='.$now,
				'WHERE'		=> 'id='.$forum_user['id'],
			);

			($hook = get_hook('po_qr_increment_num_posts')) ? eval($hook) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);

			// Add/update the topic in our list of tracked topics
			$tracked_topics = get_tracked_topics();
			$tracked_topics['topics'][$tid ? $tid : $new_tid] = time();
			set_tracked_topics($tracked_topics);
		}

		redirect(forum_link($forum_url['post'], $new_pid), $lang_post['Post redirect']);
	}
}


// Are we quoting someone?
if ($tid && isset($_GET['qid']))
{
	$qid = intval($_GET['qid']);
	if ($qid < 1)
		message($lang_common['Bad request']);

	// Get the quote and quote poster
	$query = array(
		'SELECT'	=> 'p.poster, p.message',
		'FROM'		=> 'posts AS p',
		'WHERE'		=> 'id='.$qid.' AND topic_id='.$tid
	);

	($hook = get_hook('po_qr_get_quote')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	if (!$forum_db->num_rows($result))
		message($lang_common['Bad request']);

	list($q_poster, $q_message) = $forum_db->fetch_row($result);

	$q_message = str_replace('[img]', '[url]', $q_message);
	$q_message = str_replace('[/img]', '[/url]', $q_message);
	$q_message = forum_htmlencode($q_message);

	if ($forum_config['p_message_bbcode'] == '1')
	{
		// If username contains a square bracket, we add "" or '' around it (so we know when it starts and ends)
		if (strpos($q_poster, '[') !== false || strpos($q_poster, ']') !== false)
		{
			if (strpos($q_poster, '\'') !== false)
				$q_poster = '"'.$q_poster.'"';
			else
				$q_poster = '\''.$q_poster.'\'';
		}
		else
		{
			// Get the characters at the start and end of $q_poster
			$ends = substr($q_poster, 0, 1).substr($q_poster, -1, 1);

			// Deal with quoting "Username" or 'Username' (becomes '"Username"' or "'Username'")
			if ($ends == '\'\'')
				$q_poster = '"'.$q_poster.'"';
			else if ($ends == '""')
				$q_poster = '\''.$q_poster.'\'';
		}

		$forum_page['quote'] = '[quote='.$q_poster.']'.$q_message.'[/quote]'."\n";
	}
	else
		$forum_page['quote'] = '> '.$q_poster.' '.$lang_common['wrote'].':'."\n\n".'> '.$q_message."\n";
}


// Setup form
$forum_page['set_count'] = $forum_page['fld_count'] = 0;
$forum_page['form_action'] = ($tid ? forum_link($forum_url['new_reply'], $tid) : forum_link($forum_url['new_topic'], $fid));
$forum_page['form_attributes'] = array();

$forum_page['hidden_fields'] = array(
	'<input type="hidden" name="form_sent" value="1" />',
	'<input type="hidden" name="form_user" value="'.((!$forum_user['is_guest']) ? forum_htmlencode($forum_user['username']) : 'Guest').'" />'
);
if ($forum_user['is_admmod'])
	$forum_page['hidden_fields']['csrf_token'] = '<input type="hidden" name="csrf_token" value="'.generate_form_token($forum_page['form_action']).'" />';

// Setup help
$forum_page['main_head_options'] = array();
if ($forum_config['p_message_bbcode'] == '1')
	$forum_page['main_head_options']['bbcode'] = '<a class="exthelp" href="'.forum_link($forum_url['help'], 'bbcode').'" title="'.sprintf($lang_common['Help page'], $lang_common['BBCode']).'"><span>'.$lang_common['BBCode'].'</span></a>';
if ($forum_config['p_message_img_tag'] == '1')
	$forum_page['main_head_options']['img'] = '<a class="exthelp" href="'.forum_link($forum_url['help'], 'img').'" title="'.sprintf($lang_common['Help page'], $lang_common['Images']).'"><span>'.$lang_common['Images'].'</span></a>';
if ($forum_config['o_smilies'] == '1')
	$forum_page['main_head_options']['smilies'] = '<a class="exthelp" href="'.forum_link($forum_url['help'], 'smilies').'" title="'.sprintf($lang_common['Help page'], $lang_common['Smilies']).'"><span>'.$lang_common['Smilies'].'</span></a>';

// Setup breadcrumbs
$forum_page['crumbs'][] = array($forum_config['o_board_title'], forum_link($forum_url['index']));
$forum_page['crumbs'][] = array($cur_posting['forum_name'], forum_link($forum_url['forum'], array($cur_posting['id'], sef_friendly($cur_posting['forum_name']))));
if ($tid) $forum_page['crumbs'][] = array($cur_posting['subject'], forum_link($forum_url['topic'], array($tid, sef_friendly($cur_posting['subject']))));
$forum_page['crumbs'][] = $tid ? $lang_post['Post reply'] : $lang_post['Post new topic'];

($hook = get_hook('po_pre_header_load')) ? eval($hook) : null;

define('FORUM_PAGE', 'post');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

?>
<div id="brd-main" class="main">

	<h1><span><?php echo end($forum_page['crumbs']) ?></span></h1>
<?php

// If preview selected and there are no errors
if (isset($_POST['preview']) && empty($errors))
{
	require_once FORUM_ROOT.'include/parser.php';
	$forum_page['preview_message'] = parse_message(trim($message), $hide_smilies);

?>
	<div class="main-head">
		<h2><span><?php echo $tid ? $lang_post['Preview reply'] : $lang_post['Preview new topic']; ?></span></h2>
	</div>

	<div id="post-preview" class="main-content topic">
		<div class="post firstpost">
			<div class="postmain">
				<div class="posthead">
					<h3><?php echo $lang_post['Preview info'] ?></h3>
				</div>
				<div class="postbody">
					<div class="user">
						<h4 class="user-ident"><strong class="username"><?php echo $forum_user['username'] ?></strong></h4>
					</div>
					<div class="post-entry">
						<div class="entry-content">
						<?php echo $forum_page['preview_message']."\n" ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
<?php

}

?>
	<div class="main-head">
		<h2><span><?php echo $lang_post['Compose your'].' '.($tid ? $lang_post['New reply'] : $lang_post['New topic']) ?></span></h2>
<?php if (!empty($forum_page['main_head_options'])): ?>		<p class="main-options"><?php printf($lang_common['You may use'], implode(' ', $forum_page['main_head_options'])) ?></p>
<?php endif; ?>	</div>

	<div class="main-content frm">
<?php

	// If there were any errors, show them
	if (!empty($errors))
	{
		$forum_page['errors'] = array();
		while (list(, $cur_error) = each($errors))
			$forum_page['errors'][] = '<li class="warn"><span>'.$cur_error.'</span></li>';

		($hook = get_hook('po_pre_post_errors')) ? eval($hook) : null;

?>
		<div class="frm-error">
			<h3 class="warn"><?php echo $lang_post['Post errors'] ?></h3>
			<ul>
				<?php echo implode("\n\t\t\t\t\t", $forum_page['errors'])."\n" ?>
			</ul>
		</div>
<?php

	}

?>
		<div id="req-msg" class="frm-warn">
			<p class="important"><?php printf($lang_common['Required warn'], '<em class="req-text">'.$lang_common['Required'].'</em>') ?></p>
		</div>

		<form id="afocus" class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>"<?php if (!empty($forum_page['form_attributes'])) echo ' '.implode(' ', $forum_page['form_attributes']) ?>>
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
			</div>
<?php

($hook = get_hook('po_pre_guest_info_fieldset')) ? eval($hook) : null;

if ($forum_user['is_guest'])
{
	$forum_page['email_form_name'] = ($forum_config['p_force_guest_email'] == '1') ? 'req_email' : 'email';

?>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_post['Guest post legend'] ?></strong></legend>
<?php ($hook = get_hook('po_guest_info_start')) ? eval($hook) : null; ?>
				<div class="frm-fld text required">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_post['Guest name'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_username" value="<?php if (isset($_POST['req_username'])) echo forum_htmlencode($username); ?>" size="35" maxlength="25" /></span>
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
					</label>
				</div>
<?php ($hook = get_hook('po_post_guest_name_div')) ? eval($hook) : null; ?>
				<div class="frm-fld text<?php if ($forum_config['p_force_guest_email'] == '1') echo ' required' ?>">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_post['Guest e-mail'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="<?php echo $forum_page['email_form_name'] ?>" value="<?php if (isset($_POST[$forum_page['email_form_name']])) echo forum_htmlencode($email); ?>" size="35" maxlength="80" /></span>
						<?php if ($forum_config['p_force_guest_email'] == '1') echo '<em class="req-text">'.$lang_common['Required'].'</em>' ?>
					</label>
				</div>
<?php ($hook = get_hook('po_guest_info_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php

}

($hook = get_hook('po_pre_req_info_fieldset')) ? eval($hook) : null;

?>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_common['Required information'] ?></strong></legend>
<?php

($hook = get_hook('po_req_info_fieldset_start')) ? eval($hook) : null;

if ($fid)
{

?>
				<div class="frm-fld text required longtext">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_post['Topic subject'] ?></span><br />
						<span class="fld-input"><input id="fld<?php echo $forum_page['fld_count'] ?>" type="text" name="req_subject" value="<?php if (isset($_POST['req_subject'])) echo forum_htmlencode($subject); ?>" size="80" maxlength="70" /></span>
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
					</label>
				</div>
<?php

}

($hook = get_hook('po_pre_post_contents')) ? eval($hook) : null;

?>
				<div class="frm-fld text textarea required">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_post['Write message'] ?></span><br />
						<span class="fld-input"><textarea id="fld<?php echo $forum_page['fld_count'] ?>" name="req_message" rows="14" cols="95"><?php echo isset($_POST['req_message']) ? forum_htmlencode($message) : (isset($forum_page['quote']) ? $forum_page['quote'] : ''); ?></textarea></span><br />
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
					</label>
				</div>
			</fieldset>
<?php

$forum_page['checkboxes'] = array();
if ($forum_config['o_smilies'] == '1')
	$forum_page['checkboxes']['hide_smilies'] = '<div class="radbox"><label for="fld'.(++$forum_page['fld_count']).'"><input type="checkbox" id="fld'.$forum_page['fld_count'].'" name="hide_smilies" value="1"'.(isset($_POST['hide_smilies']) ? ' checked="checked"' : '').' /> '.$lang_post['Hide smilies'].'</label></div>';

// Check/uncheck the checkbox for subscriptions depending on scenario
if (!$forum_user['is_guest'] && $forum_config['o_subscriptions'] == '1')
{
	$subscr_checked = false;

	// If it's a preview
	if (isset($_POST['preview']))
		$subscr_checked = isset($_POST['subscribe']) ? true : false;
	// If auto subscribed
	else if ($forum_user['auto_notify'])
		$subscr_checked = true;
	// If already subscribed to the topic
	else if ($is_subscribed)
		$subscr_checked = true;

	$forum_page['checkboxes']['subscribe'] = '<div class="radbox"><label for="fld'.(++$forum_page['fld_count']).'"><input type="checkbox" id="fld'.$forum_page['fld_count'].'" name="subscribe" value="1"'.($subscr_checked ? ' checked="checked"' : '').' /> '.($is_subscribed ? $lang_post['Stay subscribed'] : $lang_post['Subscribe']).'</label></div>';
}

($hook = get_hook('po_pre_optional_fieldset')) ? eval($hook) : null;

if (!empty($forum_page['checkboxes']))
{

?>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_post['Optional legend'] ?></strong></legend>
				<fieldset class="frm-group">
					<legend><span><?php echo $lang_post['Post settings'] ?></span></legend>
					<?php echo implode("\n\t\t\t\t\t\t", $forum_page['checkboxes'])."\n"; ?>
				</fieldset>
			</fieldset>
<?php

}

($hook = get_hook('po_post_optional_fieldset')) ? eval($hook) : null;

?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="submit" value="<?php echo $lang_common['Submit'] ?>" accesskey="s" title="<?php echo $lang_common['Submit title'] ?>" /></span>
				<span class="submit"><input type="submit" name="preview" value="<?php echo $lang_common['Preview'] ?>" accesskey="p" title="<?php echo $lang_common['Preview title'] ?>" /></span>
			</div>
		</form>
	</div>

<?php


// Check if the topic review is to be displayed
if ($tid && $forum_config['o_topic_review'] != '0')
{
	require_once FORUM_ROOT.'include/parser.php';

	// Get posts to display in topic review
	$query = array(
		'SELECT'	=> 'p.id, p.poster, p.message, p.hide_smilies, p.posted',
		'FROM'		=> 'posts AS p',
		'WHERE'		=> 'topic_id='.$tid,
		'ORDER BY'	=>	'id DESC',
		'LIMIT'		=>	$forum_config['o_topic_review']
	);

	($hook = get_hook('po_qr_get_topic_review_posts')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

?>

	<div class="main-head">
		<h2><span><?php echo $lang_post['Topic review'] ?></span></h2>
	</div>

	<div class="main-content topic">
<?php

	$forum_page['item_count'] = 0;

	while ($cur_post = $forum_db->fetch_assoc($result))
	{
		++$forum_page['item_count'];

		$forum_page['item_head'] = array(
			'<strong>'.$forum_page['item_count'].'</strong>',
			'<cite class="author">'.$lang_common['Posted by'].' '.forum_htmlencode($cur_post['poster']).'</cite>',
			'<a class="permalink" rel="bookmark" title="'.$lang_post['Permalink post'].'" href="'.forum_link($forum_url['post'], $cur_post['id']).'">'.format_time($cur_post['posted']).'</a>'
		);

		$forum_page['message'] = parse_message($cur_post['message'], $cur_post['hide_smilies']);

		($hook = get_hook('po_topic_review_row_pre_display')) ? eval($hook) : null;

?>
		<div class="post<?php echo ($forum_page['item_count'] == 1) ? ' firstpost' : '' ?>">
			<div class="postmain">
				<div class="posthead">
					<h3><?php echo implode(' ', $forum_page['item_head']) ?></h3>
				</div>
				<div class="postbody">
					<div class="user">
						<h4 class="user-ident"><strong class="username"><?php echo $cur_post['poster'] ?></strong></h4>
					</div>
					<div class="post-entry">
						<div class="entry-content">
						<?php echo $forum_page['message']."\n" ?>
						</div>
					</div>
				</div>
			</div>
		</div>
<?php

	}

?>
	</div>
<?php

}

?>
</div>
<?php

$forum_id = $cur_posting['id'];

($hook = get_hook('po_end')) ? eval($hook) : null;

$tpl_temp = trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
