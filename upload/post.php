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

************************************************************************/


define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';


if ($pun_user['g_read_board'] == '0')
	message($lang_common['No view']);


$tid = isset($_GET['tid']) ? intval($_GET['tid']) : 0;
$fid = isset($_GET['fid']) ? intval($_GET['fid']) : 0;
if ($tid < 1 && $fid < 1 || $tid > 0 && $fid > 0)
	message($lang_common['Bad request']);

// Fetch some info about the topic and/or the forum
if ($tid)
	$result = $db->query('SELECT f.id, f.forum_name, f.moderators, f.redirect_url, fp.post_replies, fp.post_topics, t.subject, t.closed FROM '.$db->prefix.'topics AS t INNER JOIN '.$db->prefix.'forums AS f ON f.id=t.forum_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND t.id='.$tid) or error('Unable to fetch forum info', __FILE__, __LINE__, $db->error());
else
	$result = $db->query('SELECT f.id, f.forum_name, f.moderators, f.redirect_url, fp.post_replies, fp.post_topics FROM '.$db->prefix.'forums AS f LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND f.id='.$fid) or error('Unable to fetch forum info', __FILE__, __LINE__, $db->error());

if (!$db->num_rows($result))
	message($lang_common['Bad request']);

$cur_posting = $db->fetch_assoc($result);

// Is someone trying to post into a redirect forum?
if ($cur_posting['redirect_url'] != '')
	message($lang_common['Bad request']);

// Sort out who the moderators are and if we are currently a moderator (or an admin)
$mods_array = ($cur_posting['moderators'] != '') ? unserialize($cur_posting['moderators']) : array();
$is_admmod = ($pun_user['g_id'] == PUN_ADMIN || ($pun_user['g_id'] == PUN_MOD && array_key_exists($pun_user['username'], $mods_array))) ? true : false;

// Do we have permission to post?
if ((($tid && (($cur_posting['post_replies'] == '' && $pun_user['g_post_replies'] == '0') || $cur_posting['post_replies'] == '0')) ||
	($fid && (($cur_posting['post_topics'] == '' && $pun_user['g_post_topics'] == '0') || $cur_posting['post_topics'] == '0')) ||
	(isset($cur_posting['closed']) && $cur_posting['closed'] == '1')) &&
	!$is_admmod)
	message($lang_common['No permission']);

// Load the post.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/post.php';

// Start with a clean slate
$errors = array();


// Did someone just hit "Submit" or "Preview"?
if (isset($_POST['form_sent']))
{
	// Make sure form_user is correct
	if (($pun_user['is_guest'] && $_POST['form_user'] != 'Guest') || (!$pun_user['is_guest'] && $_POST['form_user'] != $pun_user['username']))
		message($lang_common['Bad request']);

	// Flood protection
	if (!$pun_user['is_guest'] && !isset($_POST['preview']) && $pun_user['last_post'] != '' && (time() - $pun_user['last_post']) < $pun_user['g_post_flood'])
		$errors[] = $lang_post['Flood start'].' '.$pun_user['g_post_flood'].' '.$lang_post['flood end'];

	// If it's a new topic
	if ($fid)
	{
		$subject = pun_trim($_POST['req_subject']);

		if ($subject == '')
			$errors[] = $lang_post['No subject'];
		else if (pun_strlen($subject) > 70)
			$errors[] = $lang_post['Too long subject'];
		else if ($pun_config['p_subject_all_caps'] == '0' && strtoupper($subject) == $subject && $pun_user['g_id'] > PUN_MOD)
			$subject = ucwords(strtolower($subject));
	}

	// If the user is logged in we get the username and e-mail from $pun_user
	if (!$pun_user['is_guest'])
	{
		$username = $pun_user['username'];
		$email = $pun_user['email'];
	}
	// Otherwise it should be in $_POST
	else
	{
		$username = trim($_POST['req_username']);
		$email = strtolower(trim(($pun_config['p_force_guest_email'] == '1') ? $_POST['req_email'] : $_POST['email']));

		// Load the register.php/profile.php language files
		require PUN_ROOT.'lang/'.$pun_user['language'].'/prof_reg.php';
		require PUN_ROOT.'lang/'.$pun_user['language'].'/register.php';

		// It's a guest, so we have to validate the username
		if (strlen($username) < 2)
			$errors[] = $lang_prof_reg['Username too short'];
		else if (!strcasecmp($username, 'Guest') || !strcasecmp($username, $lang_common['Guest']))
			$errors[] = $lang_prof_reg['Username guest'];
		else if (preg_match('/[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}/', $username))
			$errors[] = $lang_prof_reg['Username IP'];

		if ((strpos($username, '[') !== false || strpos($username, ']') !== false) && strpos($username, '\'') !== false && strpos($username, '"') !== false)
			$errors[] = $lang_prof_reg['Username reserved chars'];
		if (preg_match('#\[b\]|\[/b\]|\[u\]|\[/u\]|\[i\]|\[/i\]|\[color|\[/color\]|\[quote\]|\[quote=|\[/quote\]|\[code\]|\[/code\]|\[img\]|\[/img\]|\[url|\[/url\]|\[email|\[/email\]#i', $username))
			$errors[] = $lang_prof_reg['Username BBCode'];

		// Check username for any censored words
		$temp = censor_words($username);
		if ($temp != $username)
			$errors[] = $lang_register['Username censor'];

		// Check that the username (or a too similar username) is not already registered
		$result = $db->query('SELECT username FROM '.$db->prefix.'users WHERE (username=\''.$db->escape($username).'\' OR username=\''.$db->escape(preg_replace('/[^\w]/', '', $username)).'\') AND id>1') or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());
		if ($db->num_rows($result))
		{
			$busy = $db->result($result);
			$errors[] = $lang_register['Username dupe 1'].' '.pun_htmlspecialchars($busy).'. '.$lang_register['Username dupe 2'];
		}

		if ($pun_config['p_force_guest_email'] == '1' || $email != '')
		{
			require PUN_ROOT.'include/email.php';
			if (!is_valid_email($email))
				$errors[] = $lang_common['Invalid e-mail'];
		}
	}

	// Clean up message from POST
	$message = pun_linebreaks(pun_trim($_POST['req_message']));

	if ($message == '')
		$errors[] = $lang_post['No message'];
	else if (strlen($message) > 65535)
		$errors[] = $lang_post['Too long message'];
	else if ($pun_config['p_message_all_caps'] == '0' && strtoupper($message) == $message && $pun_user['g_id'] > PUN_MOD)
		$message = ucwords(strtolower($message));

	// Validate BBCode syntax
	if ($pun_config['p_message_bbcode'] == '1' && strpos($message, '[') !== false && strpos($message, ']') !== false)
	{
		require PUN_ROOT.'include/parser.php';
		$message = preparse_bbcode($message, $errors);
	}


	require PUN_ROOT.'include/search_idx.php';

	$hide_smilies = isset($_POST['hide_smilies']) ? 1 : 0;
	$subscribe = isset($_POST['subscribe']) ? 1 : 0;

	$now = time();

	// Did everything go according to plan?
	if (empty($errors) && !isset($_POST['preview']))
	{
		// If it's a reply
		if ($tid)
		{
			if (!$pun_user['is_guest'])
			{
				// Insert the new post
				$db->query('INSERT INTO '.$db->prefix.'posts (poster, poster_id, poster_ip, message, hide_smilies, posted, topic_id) VALUES(\''.$db->escape($username).'\', '.$pun_user['id'].', \''.get_remote_address().'\', \''.$db->escape($message).'\', \''.$hide_smilies.'\', '.$now.', '.$tid.')') or error('Unable to create post', __FILE__, __LINE__, $db->error());
				$new_pid = $db->insert_id();

				// To subscribe or not to subscribe, that ...
				if ($pun_config['o_subscriptions'] == '1' && $subscribe)
				{
					$result = $db->query('SELECT 1 FROM '.$db->prefix.'subscriptions WHERE user_id='.$pun_user['id'].' AND topic_id='.$tid) or error('Unable to fetch subscription info', __FILE__, __LINE__, $db->error());
					if (!$db->num_rows($result))
						$db->query('INSERT INTO '.$db->prefix.'subscriptions (user_id, topic_id) VALUES('.$pun_user['id'].' ,'.$tid.')') or error('Unable to add subscription', __FILE__, __LINE__, $db->error());
				}
			}
			else
			{
				// It's a guest. Insert the new post
				$email_sql = ($pun_config['p_force_guest_email'] == '1' || $email != '') ? '\''.$email.'\'' : 'NULL';
				$db->query('INSERT INTO '.$db->prefix.'posts (poster, poster_ip, poster_email, message, hide_smilies, posted, topic_id) VALUES(\''.$db->escape($username).'\', \''.get_remote_address().'\', '.$email_sql.', \''.$db->escape($message).'\', \''.$hide_smilies.'\', '.$now.', '.$tid.')') or error('Unable to create post', __FILE__, __LINE__, $db->error());
				$new_pid = $db->insert_id();
			}

			// Count number of replies in the topic
			$result = $db->query('SELECT COUNT(id) FROM '.$db->prefix.'posts WHERE topic_id='.$tid) or error('Unable to fetch post count for topic', __FILE__, __LINE__, $db->error());
			$num_replies = $db->result($result, 0) - 1;

			// Update topic
			$db->query('UPDATE '.$db->prefix.'topics SET num_replies='.$num_replies.', last_post='.$now.', last_post_id='.$new_pid.', last_poster=\''.$db->escape($username).'\' WHERE id='.$tid) or error('Unable to update topic', __FILE__, __LINE__, $db->error());

			update_search_index('post', $new_pid, $message);

			update_forum($cur_posting['id']);

			// Should we send out notifications?
			if ($pun_config['o_subscriptions'] == '1')
			{
				// Get the post time for the previous post in this topic
				$result = $db->query('SELECT posted FROM '.$db->prefix.'posts WHERE topic_id='.$tid.' ORDER BY id DESC LIMIT 1, 1') or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
				$previous_post_time = $db->result($result);

				// Get any subscribed users that should be notified (banned users are excluded)
				$result = $db->query('SELECT u.id, u.email, u.notify_with_post, u.language FROM '.$db->prefix.'users AS u INNER JOIN '.$db->prefix.'subscriptions AS s ON u.id=s.user_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id='.$cur_posting['id'].' AND fp.group_id=u.group_id) LEFT JOIN '.$db->prefix.'online AS o ON u.id=o.user_id LEFT JOIN '.$db->prefix.'bans AS b ON u.username=b.username WHERE b.username IS NULL AND COALESCE(o.logged, u.last_visit)>'.$previous_post_time.' AND (fp.read_forum IS NULL OR fp.read_forum=1) AND s.topic_id='.$tid.' AND u.id!='.intval($pun_user['id'])) or error('Unable to fetch subscription info', __FILE__, __LINE__, $db->error());
				if ($db->num_rows($result))
				{
					require_once PUN_ROOT.'include/email.php';

					$notification_emails = array();

					// Loop through subscribed users and send e-mails
					while ($cur_subscriber = $db->fetch_assoc($result))
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

								$mail_subject = str_replace('<topic_subject>', '\''.$cur_posting['subject'].'\'', $mail_subject);
								$mail_message = str_replace('<topic_subject>', '\''.$cur_posting['subject'].'\'', $mail_message);
								$mail_message = str_replace('<replier>', $username, $mail_message);
								$mail_message = str_replace('<post_url>', $pun_config['o_base_url'].'/viewtopic.php?pid='.$new_pid.'#p'.$new_pid, $mail_message);
								$mail_message = str_replace('<unsubscribe_url>', $pun_config['o_base_url'].'/misc.php?unsubscribe='.$tid, $mail_message);
								$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'].' '.$lang_common['Mailer'], $mail_message);

								$mail_subject_full = str_replace('<topic_subject>', '\''.$cur_posting['subject'].'\'', $mail_subject_full);
								$mail_message_full = str_replace('<topic_subject>', '\''.$cur_posting['subject'].'\'', $mail_message_full);
								$mail_message_full = str_replace('<replier>', $username, $mail_message_full);
								$mail_message_full = str_replace('<message>', $message, $mail_message_full);
								$mail_message_full = str_replace('<post_url>', $pun_config['o_base_url'].'/viewtopic.php?pid='.$new_pid.'#p'.$new_pid, $mail_message_full);
								$mail_message_full = str_replace('<unsubscribe_url>', $pun_config['o_base_url'].'/misc.php?unsubscribe='.$tid, $mail_message_full);
								$mail_message_full = str_replace('<board_mailer>', $pun_config['o_board_title'].' '.$lang_common['Mailer'], $mail_message_full);

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
							if ($cur_subscriber['notify_with_post'] == '0')
								pun_mail($cur_subscriber['email'], $notification_emails[$cur_subscriber['language']][0], $notification_emails[$cur_subscriber['language']][1]);
							else
								pun_mail($cur_subscriber['email'], $notification_emails[$cur_subscriber['language']][2], $notification_emails[$cur_subscriber['language']][3]);
						}
					}
				}
			}
		}
		// If it's a new topic
		else if ($fid)
		{
			// Create the topic
			$db->query('INSERT INTO '.$db->prefix.'topics (poster, subject, posted, last_post, last_poster, forum_id) VALUES(\''.$db->escape($username).'\', \''.$db->escape($subject).'\', '.$now.', '.$now.', \''.$db->escape($username).'\', '.$fid.')') or error('Unable to create topic', __FILE__, __LINE__, $db->error());
			$new_tid = $db->insert_id();

			if (!$pun_user['is_guest'])
			{
				// To subscribe or not to subscribe, that ...
				if ($pun_config['o_subscriptions'] == '1' && (isset($_POST['subscribe']) && $_POST['subscribe'] == '1'))
					$db->query('INSERT INTO '.$db->prefix.'subscriptions (user_id, topic_id) VALUES('.$pun_user['id'].' ,'.$new_tid.')') or error('Unable to add subscription', __FILE__, __LINE__, $db->error());

				// Create the post ("topic post")
				$db->query('INSERT INTO '.$db->prefix.'posts (poster, poster_id, poster_ip, message, hide_smilies, posted, topic_id) VALUES(\''.$db->escape($username).'\', '.$pun_user['id'].', \''.get_remote_address().'\', \''.$db->escape($message).'\', \''.$hide_smilies.'\', '.$now.', '.$new_tid.')') or error('Unable to create post', __FILE__, __LINE__, $db->error());
			}
			else
			{
				// Create the post ("topic post")
				$email_sql = ($pun_config['p_force_guest_email'] == '1' || $email != '') ? '\''.$email.'\'' : 'NULL';
				$db->query('INSERT INTO '.$db->prefix.'posts (poster, poster_ip, poster_email, message, hide_smilies, posted, topic_id) VALUES(\''.$db->escape($username).'\', \''.get_remote_address().'\', '.$email_sql.', \''.$db->escape($message).'\', \''.$hide_smilies.'\', '.$now.', '.$new_tid.')') or error('Unable to create post', __FILE__, __LINE__, $db->error());
			}
			$new_pid = $db->insert_id();

			// Update the topic with last_post_id
			$db->query('UPDATE '.$db->prefix.'topics SET last_post_id='.$new_pid.' WHERE id='.$new_tid) or error('Unable to update topic', __FILE__, __LINE__, $db->error());

			update_search_index('post', $new_pid, $message, $subject);

			update_forum($fid);
		}

		// If the posting user is logged in, increment his/her post count
		if (!$pun_user['is_guest'])
		{
			$low_prio = ($db_type == 'mysql') ? 'LOW_PRIORITY ' : '';
			$db->query('UPDATE '.$low_prio.$db->prefix.'users SET num_posts=num_posts+1, last_post='.$now.' WHERE id='.$pun_user['id']) or error('Unable to update user', __FILE__, __LINE__, $db->error());
		}

		redirect('viewtopic.php?pid='.$new_pid.'#p'.$new_pid, $lang_post['Post redirect']);
	}
}


// If a topic id was specified in the url (it's a reply).
if ($tid)
{
	$action = $lang_post['Post a reply'];
	$form = '<form id="post" method="post" action="post.php?action=post&amp;tid='.$tid.'" onsubmit="this.submit.disabled=true;if(process_form(this)){return true;}else{this.submit.disabled=false;return false;}">';

	// If a quote-id was specified in the url.
	if (isset($_GET['qid']))
	{
		$qid = intval($_GET['qid']);
		if ($qid < 1)
			message($lang_common['Bad request']);

		$result = $db->query('SELECT poster, message FROM '.$db->prefix.'posts WHERE id='.$qid.' AND topic_id='.$tid) or error('Unable to fetch quote info', __FILE__, __LINE__, $db->error());
		if (!$db->num_rows($result))
			message($lang_common['Bad request']);

		list($q_poster, $q_message) = $db->fetch_row($result);

		$q_message = str_replace('[img]', '[url]', $q_message);
		$q_message = str_replace('[/img]', '[/url]', $q_message);
		$q_message = pun_htmlspecialchars($q_message);

		if ($pun_config['p_message_bbcode'] == '1')
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

			$quote = '[quote='.$q_poster.']'.$q_message.'[/quote]'."\n";
		}
		else
			$quote = '> '.$q_poster.' '.$lang_common['wrote'].':'."\n\n".'> '.$q_message."\n";
	}

	$forum_name = '<a href="viewforum.php?id='.$cur_posting['id'].'">'.pun_htmlspecialchars($cur_posting['forum_name']).'</a>';
}
// If a forum_id was specified in the url (new topic).
else if ($fid)
{
	$action = $lang_post['Post new topic'];
	$form = '<form id="post" method="post" action="post.php?action=post&amp;fid='.$fid.'" onsubmit="return process_form(this)">';

	$forum_name = pun_htmlspecialchars($cur_posting['forum_name']);
}
else
	message($lang_common['Bad request']);


$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / '.$action;
$required_fields = array('req_email' => $lang_common['E-mail'], 'req_subject' => $lang_common['Subject'], 'req_message' => $lang_common['Message']);
$focus_element = array('post');

if (!$pun_user['is_guest'])
	$focus_element[] = ($fid) ? 'req_subject' : 'req_message';
else
{
	$required_fields['req_username'] = $lang_post['Guest name'];
	$focus_element[] = 'req_username';
}

require PUN_ROOT.'header.php';

?>
<div class="linkst">
	<div class="inbox">
		<ul><li><a href="index.php"><?php echo $lang_common['Index'] ?></a></li><li>&nbsp;&raquo;&nbsp;<?php echo $forum_name ?><?php if (isset($cur_posting['subject'])) echo '</li><li>&nbsp;&raquo;&nbsp;'.pun_htmlspecialchars($cur_posting['subject']) ?></li></ul>
	</div>
</div>

<?php

// If there are errors, we display them
if (!empty($errors))
{

?>
<div id="posterror" class="block">
	<h2><span><?php echo $lang_post['Post errors'] ?></span></h2>
	<div class="box">
		<div class="inbox">
			<p><?php echo $lang_post['Post errors info'] ?></p>
			<ul>
<?php

	while (list(, $cur_error) = each($errors))
		echo "\t\t\t\t".'<li><strong>'.$cur_error.'</strong></li>'."\n";
?>
			</ul>
		</div>
	</div>
</div>

<?php

}
else if (isset($_POST['preview']))
{
	require_once PUN_ROOT.'include/parser.php';
	$preview_message = parse_message($message, $hide_smilies);

?>
<div id="postpreview" class="blockpost">
	<h2><span><?php echo $lang_post['Post preview'] ?></span></h2>
	<div class="box">
		<div class="inbox">
			<div class="postright">
				<div class="postmsg">
					<?php echo $preview_message."\n" ?>
				</div>
			</div>
		</div>
	</div>
</div>

<?php

}


$cur_index = 1;

?>
<div class="blockform">
	<h2><span><?php echo $action ?></span></h2>
	<div class="box">
		<?php echo $form."\n" ?>
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_common['Write message legend'] ?></legend>
					<div class="infldset txtarea">
						<input type="hidden" name="form_sent" value="1" />
						<input type="hidden" name="form_user" value="<?php echo (!$pun_user['is_guest']) ? pun_htmlspecialchars($pun_user['username']) : 'Guest'; ?>" />
<?php

if ($pun_user['is_guest'])
{
	$email_label = ($pun_config['p_force_guest_email'] == '1') ? '<strong>'.$lang_common['E-mail'].'</strong>' : $lang_common['E-mail'];
	$email_form_name = ($pun_config['p_force_guest_email'] == '1') ? 'req_email' : 'email';

?>						<label class="conl"><strong><?php echo $lang_post['Guest name'] ?></strong><br /><input type="text" name="req_username" value="<?php if (isset($_POST['req_username'])) echo pun_htmlspecialchars($username); ?>" size="25" maxlength="25" tabindex="<?php echo $cur_index++ ?>" /><br /></label>
						<label class="conl"><?php echo $email_label ?><br /><input type="text" name="<?php echo $email_form_name ?>" value="<?php if (isset($_POST[$email_form_name])) echo pun_htmlspecialchars($email); ?>" size="50" maxlength="50" tabindex="<?php echo $cur_index++ ?>" /><br /></label>
						<div class="clearer"></div>
<?php

}

if ($fid): ?>
						<label><strong><?php echo $lang_common['Subject'] ?></strong><br /><input class="longinput" type="text" name="req_subject" value="<?php if (isset($_POST['req_subject'])) echo pun_htmlspecialchars($subject); ?>" size="80" maxlength="70" tabindex="<?php echo $cur_index++ ?>" /><br /></label>
<?php endif; ?>						<label><strong><?php echo $lang_common['Message'] ?></strong><br />
						<textarea name="req_message" rows="20" cols="95" tabindex="<?php echo $cur_index++ ?>"><?php echo isset($_POST['req_message']) ? pun_htmlspecialchars($message) : (isset($quote) ? $quote : ''); ?></textarea><br /></label>
						<ul class="bblinks">
							<li><a href="help.php#bbcode" onclick="window.open(this.href); return false;"><?php echo $lang_common['BBCode'] ?></a>: <?php echo ($pun_config['p_message_bbcode'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></li>
							<li><a href="help.php#img" onclick="window.open(this.href); return false;"><?php echo $lang_common['img tag'] ?></a>: <?php echo ($pun_config['p_message_img_tag'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></li>
							<li><a href="help.php#smilies" onclick="window.open(this.href); return false;"><?php echo $lang_common['Smilies'] ?></a>: <?php echo ($pun_config['o_smilies'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></li>
						</ul>
					</div>
				</fieldset>
<?php

$checkboxes = array();
if (!$pun_user['is_guest'])
{
	if ($pun_config['o_smilies'] == '1')
		$checkboxes[] = '<label><input type="checkbox" name="hide_smilies" value="1" tabindex="'.($cur_index++).'"'.(isset($_POST['hide_smilies']) ? ' checked="checked"' : '').' />'.$lang_post['Hide smilies'];

	if ($pun_config['o_subscriptions'] == '1')
		$checkboxes[] = '<label><input type="checkbox" name="subscribe" value="1" tabindex="'.($cur_index++).'"'.(isset($_POST['subscribe']) ? ' checked="checked"' : '').' />'.$lang_post['Subscribe'];
}
else if ($pun_config['o_smilies'] == '1')
	$checkboxes[] = '<label><input type="checkbox" name="hide_smilies" value="1" tabindex="'.($cur_index++).'"'.(isset($_POST['hide_smilies']) ? ' checked="checked"' : '').' />'.$lang_post['Hide smilies'];

if (!empty($checkboxes))
{

?>
			</div>
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_common['Options'] ?></legend>
					<div class="infldset">
						<div class="rbox">
							<?php echo implode('<br /></label>'."\n\t\t\t\t", $checkboxes).'<br /></label>'."\n" ?>
						</div>
					</div>
				</fieldset>
<?php

}

?>
			</div>
			<p><input type="submit" name="submit" value="<?php echo $lang_common['Submit'] ?>" tabindex="<?php echo $cur_index++ ?>" accesskey="s" /><input type="submit" name="preview" value="<?php echo $lang_post['Preview'] ?>" tabindex="<?php echo $cur_index++ ?>" accesskey="p" /><a href="javascript:history.go(-1)"><?php echo $lang_common['Go back'] ?></a></p>
		</form>
	</div>
</div>

<?php

// Check to see if the topic review is to be displayed.
if ($tid && $pun_config['o_topic_review'] != '0')
{
	require_once PUN_ROOT.'include/parser.php';

	$result = $db->query('SELECT poster, message, hide_smilies, posted FROM '.$db->prefix.'posts WHERE topic_id='.$tid.' ORDER BY id DESC LIMIT '.$pun_config['o_topic_review']) or error('Unable to fetch topic review', __FILE__, __LINE__, $db->error());

?>

<div id="postreview" class="blockpost">
	<h2><span><?php echo $lang_post['Topic review'] ?></span></h2>
<?php

	//Set background switching on
	$bg_switch = true;
	$post_count = 0;

	while ($cur_post = $db->fetch_assoc($result))
	{
		// Switch the background color for every message.
		$bg_switch = ($bg_switch) ? $bg_switch = false : $bg_switch = true;
		$vtbg = ($bg_switch) ? ' roweven' : ' rowodd';
		$post_count++;

		$cur_post['message'] = parse_message($cur_post['message'], $cur_post['hide_smilies']);

?>
	<div class="box<?php echo $vtbg ?>">
		<div class="inbox">
			<div class="postleft">
				<dl>
					<dt><strong><?php echo pun_htmlspecialchars($cur_post['poster']) ?></strong></dt>
					<dd><?php echo format_time($cur_post['posted']) ?></dd>
				</dl>
			</div>
			<div class="postright">
				<div class="postmsg">
					<?php echo $cur_post['message'] ?>
				</div>
			</div>
			<div class="clearer"></div>
		</div>
	</div>
<?php

	}

?>
</div>
<?php

}

require PUN_ROOT.'footer.php';