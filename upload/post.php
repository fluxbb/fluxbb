<?php
/**
 * Adds a new post to the specified topic or a new topic to the specified forum.
 *
 * @copyright Copyright (C) 2008 FluxBB.org, based on code copyright (C) 2002-2008 PunBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package FluxBB
 */

define('FORUM_SKIP_CSRF_CONFIRM', 1);

if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', './');
require FORUM_ROOT.'include/common.php';

($hook = get_hook('po_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

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

	($hook = get_hook('po_qr_get_topic_forum_info')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
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

	($hook = get_hook('po_qr_get_forum_info')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
}

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

($hook = get_hook('po_pre_permission_check')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

// Do we have permission to post?
if ((($tid && (($cur_posting['post_replies'] == '' && $forum_user['g_post_replies'] == '0') || $cur_posting['post_replies'] == '0')) ||
	($fid && (($cur_posting['post_topics'] == '' && $forum_user['g_post_topics'] == '0') || $cur_posting['post_topics'] == '0')) ||
	(isset($cur_posting['closed']) && $cur_posting['closed'] == '1')) &&
	!$forum_page['is_admmod'])
	message($lang_common['No permission']);


($hook = get_hook('po_posting_location_selected')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

// Start with a clean slate
$errors = array();

// Did someone just hit "Submit" or "Preview"?
if (isset($_POST['form_sent']))
{
	($hook = get_hook('po_form_submitted')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	// Make sure form_user is correct
	if (($forum_user['is_guest'] && $_POST['form_user'] != 'Guest') || (!$forum_user['is_guest'] && $_POST['form_user'] != $forum_user['username']))
		message($lang_common['Bad request']);

	// Flood protection
	if (!isset($_POST['preview']) && $forum_user['last_post'] != '' && (time() - $forum_user['last_post']) < $forum_user['g_post_flood'] && (time() - $forum_user['last_post']) >= 0)
		$errors[] = sprintf($lang_post['Flood'], $forum_user['g_post_flood']);

	// If it's a new topic
	if ($fid)
	{
		$subject = forum_trim($_POST['req_subject']);

		if ($subject == '')
			$errors[] = $lang_post['No subject'];
		else if (utf8_strlen($subject) > 70)
			$errors[] = $lang_post['Too long subject'];
		else if ($forum_config['p_subject_all_caps'] == '0' && utf8_strtoupper($subject) == $subject && !$forum_page['is_admmod'])
			$errors[] = $lang_post['All caps subject'];
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
		$username = forum_trim($_POST['req_username']);
		$email = strtolower(forum_trim(($forum_config['p_force_guest_email'] == '1') ? $_POST['req_email'] : $_POST['email']));

		// Load the profile.php language file
		require FORUM_ROOT.'lang/'.$forum_user['language'].'/profile.php';

		// It's a guest, so we have to validate the username
		$errors = array_merge($errors, validate_username($username));

		if ($forum_config['p_force_guest_email'] == '1' || $email != '')
		{
			if (!defined('FORUM_EMAIL_FUNCTIONS_LOADED'))
				require FORUM_ROOT.'include/email.php';

			if (!is_valid_email($email))
				$errors[] = $lang_post['Invalid e-mail'];
		}
	}

	// If we're an administrator or moderator, make sure the CSRF token in $_POST is valid
	if ($forum_user['is_admmod'] && (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== generate_form_token(get_current_url())))
		$errors[] = $lang_post['CSRF token mismatch'];

	// Clean up message from POST
	$message = forum_linebreaks(forum_trim($_POST['req_message']));

	if (utf8_strlen($message) > FORUM_MAX_POSTSIZE)
		$errors[] = $lang_post['Too long message'];
	else if ($forum_config['p_message_all_caps'] == '0' && utf8_strtoupper($message) == $message && !$forum_page['is_admmod'])
		$errors[] = $lang_post['All caps message'];

	// Validate BBCode syntax
	if ($forum_config['p_message_bbcode'] == '1' || $forum_config['o_make_links'] == '1')
	{
		if (!defined('FORUM_PARSER_LOADED'))
			require FORUM_ROOT.'include/parser.php';

		$message = preparse_bbcode($message, $errors);
	}

	if ($message == '')
		$errors[] = $lang_post['No message'];

	$hide_smilies = isset($_POST['hide_smilies']) ? 1 : 0;
	$subscribe = isset($_POST['subscribe']) ? 1 : 0;

	$now = time();

	($hook = get_hook('po_end_validation')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	// Did everything go according to plan?
	if (empty($errors) && !isset($_POST['preview']))
	{
		// If it's a reply
		if ($tid)
		{
			$post_info = array(
				'is_guest'		=> $forum_user['is_guest'],
				'poster'		=> $username,
				'poster_id'		=> $forum_user['id'],	// Always 1 for guest posts
				'poster_email'	=> ($forum_user['is_guest'] && $email != '') ? $email : null,	// Always null for non-guest posts
				'subject'		=> $cur_posting['subject'],
				'message'		=> $message,
				'hide_smilies'	=> $hide_smilies,
				'posted'		=> $now,
				'subscr_action'	=> ($forum_config['o_subscriptions'] == '1' && $subscribe && !$is_subscribed) ? 1 : (($forum_config['o_subscriptions'] == '1' && !$subscribe && $is_subscribed) ? 2 : 0),
				'topic_id'		=> $tid,
				'forum_id'		=> $cur_posting['id'],
				'update_user'	=> true,
				'update_unread'	=> true
			);

			($hook = get_hook('po_pre_add_post')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
			add_post($post_info, $new_pid);
		}
		// If it's a new topic
		else if ($fid)
		{
			$post_info = array(
				'is_guest'		=> $forum_user['is_guest'],
				'poster'		=> $username,
				'poster_id'		=> $forum_user['id'],	// Always 1 for guest posts
				'poster_email'	=> ($forum_user['is_guest'] && $email != '') ? $email : null,	// Always null for non-guest posts
				'subject'		=> $subject,
				'message'		=> $message,
				'hide_smilies'	=> $hide_smilies,
				'posted'		=> $now,
				'subscribe'		=> ($forum_config['o_subscriptions'] == '1' && (isset($_POST['subscribe']) && $_POST['subscribe'] == '1')),
				'forum_id'		=> $fid,
				'update_user'	=> true,
				'update_unread'	=> true
			);

			($hook = get_hook('po_pre_add_topic')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
			add_topic($post_info, $new_tid, $new_pid);
		}

		($hook = get_hook('po_pre_redirect')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

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

	($hook = get_hook('po_qr_get_quote')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	if (!$forum_db->num_rows($result))
		message($lang_common['Bad request']);

	list($q_poster, $q_message) = $forum_db->fetch_row($result);

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
			$ends = utf8_substr($q_poster, 0, 1).utf8_substr($q_poster, -1, 1);

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
$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;
$forum_page['form_action'] = ($tid ? forum_link($forum_url['new_reply'], $tid) : forum_link($forum_url['new_topic'], $fid));
$forum_page['form_attributes'] = array();

$forum_page['hidden_fields'] = array(
	'form_sent'		=> '<input type="hidden" name="form_sent" value="1" />',
	'form_user'		=> '<input type="hidden" name="form_user" value="'.((!$forum_user['is_guest']) ? forum_htmlencode($forum_user['username']) : 'Guest').'" />',
	'csrf_token'	=> '<input type="hidden" name="csrf_token" value="'.generate_form_token($forum_page['form_action']).'" />'
);

// Setup help
$forum_page['text_options'] = array();
if ($forum_config['p_message_bbcode'] == '1')
	$forum_page['text_options']['bbcode'] = '<span'.(empty($forum_page['text_options']) ? ' class="item1"' : '').'><a class="exthelp" href="'.forum_link($forum_url['help'], 'bbcode').'" title="'.sprintf($lang_common['Help page'], $lang_common['BBCode']).'">'.$lang_common['BBCode'].'</a></span>';
if ($forum_config['p_message_img_tag'] == '1')
	$forum_page['text_options']['img'] = '<span'.(empty($forum_page['text_options']) ? ' class="item1"' : '').'><a class="exthelp" href="'.forum_link($forum_url['help'], 'img').'" title="'.sprintf($lang_common['Help page'], $lang_common['Images']).'">'.$lang_common['Images'].'</a></span>';
if ($forum_config['o_smilies'] == '1')
	$forum_page['text_options']['smilies'] = '<span'.(empty($forum_page['text_options']) ? ' class="item1"' : '').'><a class="exthelp" href="'.forum_link($forum_url['help'], 'smilies').'" title="'.sprintf($lang_common['Help page'], $lang_common['Smilies']).'">'.$lang_common['Smilies'].'</a></span>';

// Setup breadcrumbs
$forum_page['crumbs'][] = array($forum_config['o_board_title'], forum_link($forum_url['index']));
$forum_page['crumbs'][] = array($cur_posting['forum_name'], forum_link($forum_url['forum'], array($cur_posting['id'], sef_friendly($cur_posting['forum_name']))));
if ($tid)
	$forum_page['crumbs'][] = array($cur_posting['subject'], forum_link($forum_url['topic'], array($tid, sef_friendly($cur_posting['subject']))));
$forum_page['crumbs'][] = $tid ? $lang_post['Post reply'] : $lang_post['Post new topic'];

($hook = get_hook('po_pre_header_load')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

define('FORUM_PAGE', 'post');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('po_main_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

// If preview selected and there are no errors
if (isset($_POST['preview']) && empty($errors))
{
	if (!defined('FORUM_PARSER_LOADED'))
		require FORUM_ROOT.'include/parser.php';

	$forum_page['preview_message'] = parse_message(forum_trim($message), $hide_smilies);

	// Generate the post heading
	$forum_page['post_ident'] = array();
	$forum_page['post_ident']['num'] = '<span class="post-num">#</span>';
	$forum_page['post_ident']['byline'] = '<span class="post-byline">'.sprintf((($tid) ? $lang_post['Reply byline'] : $lang_post['Topic byline']), '<strong>'.forum_htmlencode($forum_user['username']).'</strong>').'</span>';
	$forum_page['post_ident']['link'] = '<span class="post-link">'.format_time(time()).'</span>';

	($hook = get_hook('po_preview_pre_display')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $tid ? $lang_post['Preview reply'] : $lang_post['Preview new topic']; ?></span></h2>
	</div>
	<div id="post-preview" class="main-content main-frm">
		<div class="post singlepost">
			<div class="posthead">
				<h3 class="hn"><?php echo implode(' ', $forum_page['post_ident']) ?></h3>
<?php ($hook = get_hook('po_preview_new_post_head_option')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			</div>
			<div class="postbody">
				<div class="post-entry">
					<div class="entry-content">
						<?php echo $forum_page['preview_message']."\n" ?>
					</div>
<?php ($hook = get_hook('po_preview_new_post_entry_data')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				</div>
			</div>
		</div>
	</div>
<?php

}

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo ($tid) ? $lang_post['Compose your reply'] : $lang_post['Compose your topic'] ?></span></h2>
	</div>
	<div id="post-form" class="main-content main-frm">
<?php

	if (!empty($forum_page['text_options']))
		echo "\t\t".'<p class="ct-options options">'.sprintf($lang_common['You may use'], implode(' ', $forum_page['text_options'])).'</p>'."\n";

	// If there were any errors, show them
	if (!empty($errors))
	{
		$forum_page['errors'] = array();
		foreach($errors as $cur_error)
			$forum_page['errors'][] = '<li class="warn"><span>'.$cur_error.'</span></li>';

		($hook = get_hook('po_pre_post_errors')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
		<div class="ct-box error-box">
			<h3 class="warn"><?php echo $lang_post['Post errors'] ?></h3>
			<ul class="error-list">
				<?php echo implode("\n\t\t\t\t", $forum_page['errors'])."\n" ?>
			</ul>
		</div>
<?php

	}

?>
		<div id="req-msg" class="req-warn ct-box error-box">
			<p><?php printf($lang_common['Required warn'], '<em>'.$lang_common['Required'].'</em>') ?></p>
		</div>
		<form id="afocus" class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>"<?php if (!empty($forum_page['form_attributes'])) echo ' '.implode(' ', $forum_page['form_attributes']) ?>>
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
			</div>
<?php

if ($forum_user['is_guest'])
{
	$forum_page['email_form_name'] = ($forum_config['p_force_guest_email'] == '1') ? 'req_email' : 'email';

	($hook = get_hook('po_pre_guest_info_fieldset')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
			<fieldset class="frm-group group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_post['Guest post legend'] ?></strong></legend>
<?php ($hook = get_hook('po_pre_guest_username')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text required">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><em><?php echo $lang_common['Required'] ?></em> <?php echo $lang_post['Guest name'] ?></span></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_username" value="<?php if (isset($_POST['req_username'])) echo forum_htmlencode($username); ?>" size="35" maxlength="25" /></span>
					</div>
				</div>
<?php ($hook = get_hook('po_pre_guest_email')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text<?php if ($forum_config['p_force_guest_email'] == '1') echo ' required' ?>">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_post['Guest e-mail'] ?><?php if ($forum_config['p_force_guest_email'] == '1') echo ' <em>'.$lang_common['Required'].'</em>' ?></span></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="<?php echo $forum_page['email_form_name'] ?>" value="<?php if (isset($_POST[$forum_page['email_form_name']])) echo forum_htmlencode($email); ?>" size="35" maxlength="80" /></span>
					</div>
				</div>
<?php ($hook = get_hook('po_pre_guest_info_fieldset_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			</fieldset>
<?php

	($hook = get_hook('po_guest_info_fieldset_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	// Reset counters
	$forum_page['group_count'] = $forum_page['item_count'] = 0;
}

($hook = get_hook('po_pre_req_info_fieldset')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
			<fieldset class="frm-group group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_common['Required information'] ?></strong></legend>
<?php

if ($fid)
{
	($hook = get_hook('po_pre_req_subject')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text required longtext">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_post['Topic subject'] ?> <?php if ($forum_config['p_force_guest_email'] == '1') echo '<em>'.$lang_common['Required'].'</em>' ?></span></label><br />
						<span class="fld-input"><input id="fld<?php echo $forum_page['fld_count'] ?>" type="text" name="req_subject" value="<?php if (isset($_POST['req_subject'])) echo forum_htmlencode($subject); ?>" size="80" maxlength="70" /></span>
					</div>
				</div>
<?php

}

($hook = get_hook('po_pre_post_contents')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
				<div class="txt-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="txt-box textarea required">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_post['Write message'] ?> <?php if ($forum_config['p_force_guest_email'] == '1') echo '<em>'.$lang_common['Required'].'</em>' ?></span></label>
						<div class="txt-input"><span class="fld-input"><textarea id="fld<?php echo $forum_page['fld_count'] ?>" name="req_message" rows="14" cols="95"><?php echo isset($_POST['req_message']) ? forum_htmlencode($message) : (isset($forum_page['quote']) ? forum_htmlencode($forum_page['quote']) : ''); ?></textarea></span></div>
					</div>
				</div>
<?php

$forum_page['checkboxes'] = array();
if ($forum_config['o_smilies'] == '1')
	$forum_page['checkboxes']['hide_smilies'] = '<div class="mf-item"><span class="fld-input"><input type="checkbox" id="fld'.(++$forum_page['fld_count']).'" name="hide_smilies" value="1"'.(isset($_POST['hide_smilies']) ? ' checked="checked"' : '').' /></span> <label for="fld'.$forum_page['fld_count'].'">'.$lang_post['Hide smilies'].'</label></div>';

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

	$forum_page['checkboxes']['subscribe'] = '<div class="mf-item"><span class="fld-input"><input type="checkbox" id="fld'.(++$forum_page['fld_count']).'" name="subscribe" value="1"'.($subscr_checked ? ' checked="checked"' : '').' /></span> <label for="fld'.$forum_page['fld_count'].'">'.($is_subscribed ? $lang_post['Stay subscribed'] : $lang_post['Subscribe']).'</label></div>';
}

($hook = get_hook('po_pre_optional_fieldset')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

if (!empty($forum_page['checkboxes']))
{

?>
				<fieldset class="mf-set set<?php echo ++$forum_page['item_count'] ?>">
					<legend><span><?php echo $lang_post['Post settings'] ?></span></legend>
					<div class="mf-box checkbox">
						<?php echo implode("\n\t\t\t\t\t", $forum_page['checkboxes'])."\n"; ?>
					</div>
<?php ($hook = get_hook('po_pre_checkbox_fieldset_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				</fieldset>
<?php

}

($hook = get_hook('po_pre_req_info_fieldset_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
			</fieldset>
<?php

($hook = get_hook('po_req_info_fieldset_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="submit" value="<?php echo ($tid) ? $lang_post['Submit reply'] : $lang_post['Submit topic'] ?>" /></span>
				<span class="submit"><input type="submit" name="preview" value="<?php echo ($tid) ? $lang_post['Preview reply'] : $lang_post['Preview topic'] ?>" /></span>
			</div>
		</form>
	</div>
<?php


// Check if the topic review is to be displayed
if ($tid && $forum_config['o_topic_review'] != '0')
{
	if (!defined('FORUM_PARSER_LOADED'))
		require FORUM_ROOT.'include/parser.php';

	// Get posts to display in topic review
	$query = array(
		'SELECT'	=> 'p.id, p.poster, p.message, p.hide_smilies, p.posted',
		'FROM'		=> 'posts AS p',
		'WHERE'		=> 'topic_id='.$tid,
		'ORDER BY'	=>	'id DESC',
		'LIMIT'		=>	$forum_config['o_topic_review']
	);

	($hook = get_hook('po_topic_review_qr_get_topic_review_posts')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_post['Topic review'] ?></span></h2>
	</div>
	<div  id="topic-review" class="main-content main-frm">
<?php

	$forum_page['item_count'] = 0;
	$forum_page['item_total'] = $forum_db->num_rows($result);
	$forum_page['author_title'] = '';

	while ($cur_post = $forum_db->fetch_assoc($result))
	{
		++$forum_page['item_count'];

		// Generate the post heading
		$forum_page['post_ident'] = array(
			'num'	=> '<span class="post-num">'.forum_number_format($forum_page['item_count']).'</span>',
			'link'	=> '<span class="post-link">'.sprintf($lang_post['Post posted'], '<a class="permalink" rel="bookmark" title="'.$lang_post['Permalink post'].'" href="'.forum_link($forum_url['post'], $cur_post['id']).'">'.format_time($cur_post['posted']).'</a>').'</span>'
		);

		($hook = get_hook('po_topic_review_pre_item_indent_merge')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		$forum_page['message'] = parse_message($cur_post['message'], $cur_post['hide_smilies']);

		// Generate the post heading
		$forum_page['post_ident'] = array();
		$forum_page['post_ident']['num'] = '<span class="post-num">'.forum_number_format($forum_page['item_count']).'</span>';
		$forum_page['post_ident']['byline'] = '<span class="post-byline">'.sprintf($lang_post['Post byline'], '<strong>'.forum_htmlencode($cur_post['poster']).'</strong>').'</span>';
		$forum_page['post_ident']['link'] = '<span class="post-link"><a class="permalink" rel="bookmark" title="'.$lang_post['Permalink post'].'" href="'.forum_link($forum_url['post'], $cur_post['id']).'">'.format_time($cur_post['posted']).'</a></span>';

		($hook = get_hook('po_topic_review_row_pre_display')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
		<div class="post<?php echo ($forum_page['item_count'] == 1) ? ' firstpost' : '' ?><?php echo ($forum_page['item_total'] == $forum_page['item_count']) ? ' lastpost' : '' ?>">
			<div class="posthead">
				<h3 class="hn post-ident"><?php echo implode(' ', $forum_page['post_ident']) ?></h3>
<?php ($hook = get_hook('po_topic_review_new_post_head_option')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			</div>
			<div class="postbody">
				<div class="post-entry">
					<div class="entry-content">
						<?php echo $forum_page['message']."\n" ?>
<?php ($hook = get_hook('po_topic_review_new_post_entry_data')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
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

$forum_id = $cur_posting['id'];

($hook = get_hook('po_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
