<?php

/**
 * Copyright (C) 2008-2011 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';


if ($pun_user['g_read_board'] == '0')
	message($lang_common['No view']);


$tid = isset($_GET['tid']) ? intval($_GET['tid']) : 0;
$fid = isset($_GET['fid']) ? intval($_GET['fid']) : 0;
if ($tid < 1 && $fid < 1 || $tid > 0 && $fid > 0)
	message($lang_common['Bad request']);

// Fetch some info about the topic and/or the forum
$query = new SelectQuery(array('fid' => 'f.id', 'forum_name' => 'f.forum_name', 'moderators' => 'f.moderators', 'redirect_url' => 'f.redirect_url', 'post_replies' => 'fp.post_replies', 'post_topics' => 'fp.post_topics'), 'forums AS f');

$query->joins['fp'] = new LeftJoin('forum_perms AS fp');
$query->joins['fp']->on = 'fp.forum_id = f.id AND fp.group_id = :group_id';

$query->where = '(fp.read_forum IS NULL OR fp.read_forum = 1) AND '.($tid ? 't.id' : 'f.id').' = :id';

$params = array(':group_id' => $pun_user['g_id']);
$params[':id'] = ($tid ? $tid : $fid);

if ($tid)
{
	$query->fields['subect'] = 't.subject';
	$query->fields['closed'] = 't.closed';
	$query->fields['is_subscribed'] = 's.user_id AS is_subscribed';

	$query->joins['t'] = new InnerJoin('topics AS t');
	$query->joins['t']->on = 't.forum_id = f.id';

	$query->joins['s'] = new LeftJoin('topic_subscriptions AS s');
	$query->joins['s']->on = 't.id = s.topic_id AND s.user_id = :user_id';

	$params[':user_id'] = $pun_user['id'];
}

$result = $db->query($query, $params);
if (empty($result))
	message($lang_common['Bad request']);

$cur_posting = $result[0];
unset ($result, $query, $params);

$is_subscribed = $tid && $cur_posting['is_subscribed'];

// Is someone trying to post into a redirect forum?
if ($cur_posting['redirect_url'] != '')
	message($lang_common['Bad request']);

// Sort out who the moderators are and if we are currently a moderator (or an admin)
$mods_array = ($cur_posting['moderators'] != '') ? unserialize($cur_posting['moderators']) : array();
$is_admmod = ($pun_user['g_id'] == PUN_ADMIN || ($pun_user['g_moderator'] == '1' && array_key_exists($pun_user['username'], $mods_array))) ? true : false;

if ($tid && $pun_config['o_censoring'] == '1')
	$cur_posting['subject'] = censor_words($cur_posting['subject']);

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
	// Flood protection
	if (!isset($_POST['preview']) && $pun_user['last_post'] != '' && (time() - $pun_user['last_post']) < $pun_user['g_post_flood'])
		$errors[] = $lang_post['Flood start'].' '.$pun_user['g_post_flood'].' '.$lang_post['flood end'];

	// If it's a new topic
	if ($fid)
	{
		$subject = pun_trim($_POST['req_subject']);

		if ($pun_config['o_censoring'] == '1')
			$censored_subject = pun_trim(censor_words($subject));

		if ($subject == '')
			$errors[] = $lang_post['No subject'];
		else if ($pun_config['o_censoring'] == '1' && $censored_subject == '')
			$errors[] = $lang_post['No subject after censoring'];
		else if (pun_strlen($subject) > 70)
			$errors[] = $lang_post['Too long subject'];
		else if ($pun_config['p_subject_all_caps'] == '0' && is_all_uppercase($subject) && !$pun_user['is_admmod'])
			$errors[] = $lang_post['All caps subject'];
	}

	// If the user is logged in we get the username and email from $pun_user
	if (!$pun_user['is_guest'])
	{
		$username = $pun_user['username'];
		$email = $pun_user['email'];
	}
	// Otherwise it should be in $_POST
	else
	{
		$username = pun_trim($_POST['req_username']);
		$email = strtolower(trim(($pun_config['p_force_guest_email'] == '1') ? $_POST['req_email'] : $_POST['email']));
		$banned_email = false;

		// Load the register.php/prof_reg.php language files
		require PUN_ROOT.'lang/'.$pun_user['language'].'/prof_reg.php';
		require PUN_ROOT.'lang/'.$pun_user['language'].'/register.php';

		// It's a guest, so we have to validate the username
		check_username($username);

		if ($pun_config['p_force_guest_email'] == '1' || $email != '')
		{
			require PUN_ROOT.'include/email.php';
			if (!is_valid_email($email))
				$errors[] = $lang_common['Invalid email'];

			// Check if it's a banned email address
			// we should only check guests because members addresses are already verified
			if ($pun_user['is_guest'] && is_banned_email($email))
			{
				if ($pun_config['p_allow_banned_email'] == '0')
					$errors[] = $lang_prof_reg['Banned email'];

				$banned_email = true; // Used later when we send an alert email
			}
		}
	}

	// Clean up message from POST
	$orig_message = $message = pun_linebreaks(pun_trim($_POST['req_message']));

	// Here we use strlen() not pun_strlen() as we want to limit the post to PUN_MAX_POSTSIZE bytes, not characters
	if (strlen($message) > PUN_MAX_POSTSIZE)
		$errors[] = sprintf($lang_post['Too long message'], forum_number_format(PUN_MAX_POSTSIZE));
	else if ($pun_config['p_message_all_caps'] == '0' && is_all_uppercase($message) && !$pun_user['is_admmod'])
		$errors[] = $lang_post['All caps message'];

	// Validate BBCode syntax
	if ($pun_config['p_message_bbcode'] == '1')
	{
		require PUN_ROOT.'include/parser.php';
		$message = preparse_bbcode($message, $errors);
	}

	if (empty($errors))
	{
		if ($message == '')
			$errors[] = $lang_post['No message'];
		else if ($pun_config['o_censoring'] == '1')
		{
			// Censor message to see if that causes problems
			$censored_message = pun_trim(censor_words($message));

			if ($censored_message == '')
				$errors[] = $lang_post['No message after censoring'];
		}
	}

	$hide_smilies = isset($_POST['hide_smilies']) ? '1' : '0';
	$subscribe = isset($_POST['subscribe']) ? '1' : '0';
	$stick_topic = isset($_POST['stick_topic']) && $is_admmod ? '1' : '0';

	$now = time();

	// Did everything go according to plan?
	if (empty($errors) && !isset($_POST['preview']))
	{
		require PUN_ROOT.'include/search_idx.php';

		// If it's a reply
		if ($tid)
		{
			if (!$pun_user['is_guest'])
			{
				$new_tid = $tid;

				// Insert the new post
				$query = new InsertQuery(array('poster' => ':poster', 'poster_id' => ':poster_id', 'poster_ip' => ':poster_ip', 'message' => ':message', 'hide_smilies' => ':hide_smilies', 'posted' => ':now', 'topic_id' => ':topic_id'), 'posts');
				$params = array(':poster' => $username, ':poster_id' => $pun_user['id'], ':poster_ip' => get_remote_address(), ':message' => $message, ':hide_smilies' => $hide_smilies, ':now' => $now, ':topic_id' => $tid);

				$db->query($query, $params);
				$new_pid = $db->insert_id();
				unset ($query, $params);

				// To subscribe or not to subscribe, that ...
				if ($pun_config['o_topic_subscriptions'] == '1')
				{
					if ($subscribe && !$is_subscribed)
					{
						$query = new InsertQuery(array('user_id' => ':user_id', 'topic_id' => ':topic_id'), 'topic_subscriptions');
						$params = array(':user_id' => $pun_user['id'], ':topic_id' => $tid);

						$db->query($query, $params);
						unset ($query, $params);
					}
					else if (!$subscribe && $is_subscribed)
					{
						$query = new DeleteQuery('topic_subscriptions');
						$query->where = 'user_id = :user_id AND topic_id = :topic_id';

						$params = array(':user_id' => $pun_user['id'], ':topic_id' => $tid);

						$db->query($query, $params);
						unset ($query, $params);
					}
				}
			}
			else
			{
				// It's a guest. Insert the new post
				$query = new InsertQuery(array('poster' => ':poster', 'poster_ip' => ':poster_ip', 'poster_email' => ':poster_email', 'message' => ':message', 'hide_smilies' => ':hide_smilies', 'posted' => ':now', 'topic_id' => ':topic_id'), 'posts');
				$params = array(':poster' => $username, ':poster_ip' => get_remote_address(), ':poster_email' => empty($email) ? null : $email, ':message' => $message, ':hide_smilies' => $hide_smilies, ':now' => $now, ':topic_id' => $tid);

				$db->query($query, $params);
				$new_pid = $db->insert_id();
				unset ($query, $params);
			}

			// Count number of replies in the topic
			$query = new SelectQuery(array('num_replies' => '(COUNT(p.id) - 1) AS num_replies'), 'posts AS p');
			$query->where = 'p.topic_id = :topic_id';

			$params = array(':topic_id' => $tid);

			$result = $db->query($query, $params);
			$num_replies = $result[0]['num_replies'];
			unset ($result, $query, $params);

			// Update topic
			$query = new UpdateQuery(array('num_replies' => ':num_replies', 'last_post' => ':now', 'last_post_id' => ':last_post_id', 'last_poster' => ':last_poster'), 'topics');
			$query->where = 'id = :topic_id';

			$params = array(':num_replies' => $num_replies, ':now' => $now, ':last_post_id' => $new_pid, ':last_poster' => $username, ':topic_id' => $tid);

			$db->query($query, $params);
			unset ($query, $params);

			update_search_index('post', $new_pid, $message);

			update_forum($cur_posting['id']);

			// Should we send out notifications?
			if ($pun_config['o_topic_subscriptions'] == '1')
			{
				// Get the post time for the previous post in this topic
				$query = new SelectQuery(array('posted' => 'p.posted'), 'posts AS p');
				$query->where = 'p.topic_id = :topic_id';
				$query->order = array('pid' => 'p.id DESC');
				$query->offset = 1;
				$query->limit = 1;

				$params = array(':topic_id' => $tid);

				$result = $db->query($query, $params);
				$previous_post_time = $result[0]['posted'];
				unset ($result, $query, $params);

				// Get any subscribed users that should be notified (banned users are excluded)
				$query = new SelectQuery(array('id' => 'u.id', 'email' => 'u.email', 'notify_with_post' => 'u.notify_with_post', 'language' => 'u.language'), 'users AS u');

				$query->joins['ts'] = new InnerJoin('topic_subscriptions AS ts');
				$query->joins['ts']->on = 'u.id = ts.user_id';

				$query->joins['fp'] = new LeftJoin('forum_perms AS fp');
				$query->joins['fp']->on = 'fp.forum_id = :forum_id AND fp.group_id = u.group_id';

				$query->joins['o'] = new LeftJoin('online AS o');
				$query->joins['o']->on = 'u.id = o.user_id';

				$query->joins['b'] = new LeftJoin('bans AS b');
				$query->joins['b']->on = 'u.username = b.username';

				$query->where = 'b.username IS NULL AND COALESCE(o.logged, u.last_visit) > :last_post AND (fp.read_forum IS NULL OR fp.read_forum = 1) AND ts.topic_id = :topic_id AND u.id != :user_id';

				$params = array(':forum_id' => $cur_posting['id'], ':last_post' => $previous_post_time, ':topic_id' => $tid, ':user_id' => $pun_user['id']);

				$result = $db->query($query, $params);
				if (!empty($result))
				{
					require_once PUN_ROOT.'include/email.php';

					$notification_emails = array();

					if ($pun_config['o_censoring'] == '1')
						$cleaned_message = bbcode2email($censored_message, -1);
					else
						$cleaned_message = bbcode2email($message, -1);

					// Loop through subscribed users and send emails
					foreach ($result as $cur_subscriber)
					{
						// Is the subscription email for $cur_subscriber['language'] cached or not?
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

								$mail_subject = str_replace('<topic_subject>', $cur_posting['subject'], $mail_subject);
								$mail_message = str_replace('<topic_subject>', $cur_posting['subject'], $mail_message);
								$mail_message = str_replace('<replier>', $username, $mail_message);
								$mail_message = str_replace('<post_url>', get_base_url().'/viewtopic.php?pid='.$new_pid.'#p'.$new_pid, $mail_message);
								$mail_message = str_replace('<unsubscribe_url>', get_base_url().'/misc.php?action=unsubscribe&tid='.$tid, $mail_message);
								$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message);

								$mail_subject_full = str_replace('<topic_subject>', $cur_posting['subject'], $mail_subject_full);
								$mail_message_full = str_replace('<topic_subject>', $cur_posting['subject'], $mail_message_full);
								$mail_message_full = str_replace('<replier>', $username, $mail_message_full);
								$mail_message_full = str_replace('<message>', $cleaned_message, $mail_message_full);
								$mail_message_full = str_replace('<post_url>', get_base_url().'/viewtopic.php?pid='.$new_pid.'#p'.$new_pid, $mail_message_full);
								$mail_message_full = str_replace('<unsubscribe_url>', get_base_url().'/misc.php?action=unsubscribe&tid='.$tid, $mail_message_full);
								$mail_message_full = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message_full);

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

					unset($cleaned_message);
				}

				unset ($result, $query, $params);
			}
		}
		// If it's a new topic
		else if ($fid)
		{
			// Create the topic
			$query = new InsertQuery(array('poster' => ':poster', 'subject' => ':subject', 'poster' => ':now', 'last_post' => ':now', 'last_poster' => ':last_poster', 'sticky' => ':sticky', 'forum_id' => ':forum_id'), 'topics');
			$params = array(':poster' => $username, ':subject' => $subject, ':now' => $now, ':last_poster' => $username, ':sticky' => $stick_topic, ':forum_id' => $fid);

			$db->query($query, $params);
			$new_tid = $db->insert_id();
			unset ($query, $params);

			if (!$pun_user['is_guest'])
			{
				// To subscribe or not to subscribe, that ...
				if ($pun_config['o_topic_subscriptions'] == '1' && $subscribe)
				{
					$query = new InsertQuery(array('user_id' => ':user_id', 'topic_id' => ':topic_id'), 'topic_subscriptions');
					$params = array(':user_id' => $pun_user['id'], ':topic_id' => $new_tid);

					$db->query($query, $params);
					unset ($query, $params);
				}

				// Create the post ("topic post")
				$query = new InsertQuery(array('poster' => ':poster', 'poster_id' => ':poster_id', 'poster_ip' => ':poster_ip', 'message' => ':message', 'hide_smilies' => ':hide_smilies', 'posted' => ':now', 'topic_id' => ':topic_id'), 'posts');
				$params = array(':poster' => $username, ':poster_id' => $pun_user['id'], ':poster_ip' => get_remote_address(), ':message' => $message, ':hide_smilies' => $hide_smilies, ':now' => $now, ':topic_id' => $new_tid);

				$db->query($query, $params);
				$new_pid = $db->insert_id();
				unset ($query, $params);
			}
			else
			{
				// Create the post ("topic post")
				$query = new InsertQuery(array('poster' => ':poster', 'poster_ip' => ':poster_ip', 'poster_email' => ':poster_email', 'message' => ':message', 'hide_smilies' => ':hide_smilies', 'posted' => ':posted', 'topic_id' => ':topic_id'), 'posts');
				$params = array(':poster' => $username, ':poster_ip' => get_remote_address(), ':poster_email' => empty($email) ? null : $email, ':message' => $message, ':hide_smilies' => $hide_smilies, ':now' => $now, ':topic_id' => $new_tid);

				$db->query($query, $params);
				$new_pid = $db->insert_id();
				unset ($query, $params);
			}

			// Update the topic with last_post_id
			$query = new UpdateQuery(array('last_post_id' => ':new_pid', 'first_post_id' => ':new_pid'), 'topics');
			$query->where = 'id = :topic_id';

			$params = array(':new_pid' => $new_pid, ':topic_id' => $new_tid);

			$db->query($query, $params);
			unset ($query, $params);

			update_search_index('post', $new_pid, $message, $subject);

			update_forum($fid);

			// Should we send out notifications?
			if ($pun_config['o_forum_subscriptions'] == '1')
			{
				// Get any subscribed users that should be notified (banned users are excluded)
				$query = new SelectQuery(array('id' => 'u.id', 'email' => 'u.email', 'notify_with_post' => 'u.notify_with_post', 'language' => 'u.language'), 'users AS u');

				$query->joins['fs'] = new InnerJoin('forum_subscriptions AS fs');
				$query->joins['fs']->on = 'u.id = fs.user_id';

				$query->joins['fp'] = new LeftJoin('forum_perms AS fp');
				$query->joins['fp']->on = 'fp.forum_id = :forum_id AND fp.group_id = u.group_id';

				$query->joins['b'] = new LeftJoin('bans AS b');
				$query->joins['b']->on = 'u.username = b.username';

				$query->where = 'b.username IS NULL AND (fp.read_forum IS NULL OR fp.read_forum = 1) AND fs.forum_id = :forum_id AND u.id != :user_id';

				$params = array(':forum_id' => $cur_posting['id'], ':user_id' => $pun_user['id']);

				$result = $db->query($query, $params);
				if (!empty($result))
				{
					require_once PUN_ROOT.'include/email.php';

					$notification_emails = array();

					if ($pun_config['o_censoring'] == '1')
						$cleaned_message = bbcode2email($censored_message, -1);
					else
						$cleaned_message = bbcode2email($message, -1);

					// Loop through subscribed users and send emails
					foreach ($result as $cur_subscriber)
					{
						// Is the subscription email for $cur_subscriber['language'] cached or not?
						if (!isset($notification_emails[$cur_subscriber['language']]))
						{
							if (file_exists(PUN_ROOT.'lang/'.$cur_subscriber['language'].'/mail_templates/new_topic.tpl'))
							{
								// Load the "new topic" template
								$mail_tpl = trim(file_get_contents(PUN_ROOT.'lang/'.$cur_subscriber['language'].'/mail_templates/new_topic.tpl'));

								// Load the "new topic full" template (with post included)
								$mail_tpl_full = trim(file_get_contents(PUN_ROOT.'lang/'.$cur_subscriber['language'].'/mail_templates/new_topic_full.tpl'));

								// The first row contains the subject (it also starts with "Subject:")
								$first_crlf = strpos($mail_tpl, "\n");
								$mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
								$mail_message = trim(substr($mail_tpl, $first_crlf));

								$first_crlf = strpos($mail_tpl_full, "\n");
								$mail_subject_full = trim(substr($mail_tpl_full, 8, $first_crlf-8));
								$mail_message_full = trim(substr($mail_tpl_full, $first_crlf));

								$mail_subject = str_replace('<forum_name>', $cur_posting['forum_name'], $mail_subject);
								$mail_message = str_replace('<topic_subject>', $pun_config['o_censoring'] == '1' ? $censored_subject : $subject, $mail_message);
								$mail_message = str_replace('<forum_name>', $cur_posting['forum_name'], $mail_message);
								$mail_message = str_replace('<poster>', $username, $mail_message);
								$mail_message = str_replace('<topic_url>', get_base_url().'/viewtopic.php?id='.$new_tid, $mail_message);
								$mail_message = str_replace('<unsubscribe_url>', get_base_url().'/misc.php?action=unsubscribe&fid='.$cur_posting['id'], $mail_message);
								$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message);

								$mail_subject_full = str_replace('<forum_name>', $cur_posting['forum_name'], $mail_subject_full);
								$mail_message_full = str_replace('<topic_subject>', $pun_config['o_censoring'] == '1' ? $censored_subject : $subject, $mail_message_full);
								$mail_message_full = str_replace('<forum_name>', $cur_posting['forum_name'], $mail_message_full);
								$mail_message_full = str_replace('<poster>', $username, $mail_message_full);
								$mail_message_full = str_replace('<message>', $cleaned_message, $mail_message_full);
								$mail_message_full = str_replace('<topic_url>', get_base_url().'/viewtopic.php?id='.$new_tid, $mail_message_full);
								$mail_message_full = str_replace('<unsubscribe_url>', get_base_url().'/misc.php?action=unsubscribe&fid='.$cur_posting['id'], $mail_message_full);
								$mail_message_full = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message_full);

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

					unset($cleaned_message);
				}

				unset ($result, $query, $params);
			}
		}

		// If we previously found out that the email was banned
		if ($pun_user['is_guest'] && $banned_email && $pun_config['o_mailing_list'] != '')
		{
			// Load the "banned email post" template
			$mail_tpl = trim(file_get_contents(PUN_ROOT.'lang/'.$pun_user['language'].'/mail_templates/banned_email_post.tpl'));

			// The first row contains the subject
			$first_crlf = strpos($mail_tpl, "\n");
			$mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
			$mail_message = trim(substr($mail_tpl, $first_crlf));

			$mail_message = str_replace('<username>', $username, $mail_message);
			$mail_message = str_replace('<email>', $email, $mail_message);
			$mail_message = str_replace('<post_url>', get_base_url().'/viewtopic.php?pid='.$new_pid.'#p'.$new_pid, $mail_message);
			$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message);

			pun_mail($pun_config['o_mailing_list'], $mail_subject, $mail_message);
		}

		// If the posting user is logged in, increment his/her post count
		if (!$pun_user['is_guest'])
		{
			$query = new UpdateQuery(array('num_posts' => 'num_posts + 1', 'last_post' => ':now'), 'users');
			$query->where = 'id = :user_id';

			$params = array(':now' => $now, ':user_id' => $pun_user['id']);

			$db->query($query, $params);
			unset ($query, $params);

			$tracked_topics = get_tracked_topics();
			$tracked_topics['topics'][$new_tid] = time();
			set_tracked_topics($tracked_topics);
		}
		else
		{
			$query = new UpdateQuery(array('last_post' => ':now'), 'online');
			$query->where = 'ident = :ident';

			$params = array(':now' => $now, ':ident' => get_remote_address());

			$db->query($query, $params);
			unset ($query, $params);
		}

		redirect('viewtopic.php?pid='.$new_pid.'#p'.$new_pid, $lang_post['Post redirect']);
	}
}


// If a topic ID was specified in the url (it's a reply)
if ($tid)
{
	$action = $lang_post['Post a reply'];
	$form = '<form id="post" method="post" action="post.php?action=post&amp;tid='.$tid.'" onsubmit="this.submit.disabled=true;if(process_form(this)){return true;}else{this.submit.disabled=false;return false;}">';

	// If a quote ID was specified in the url
	if (isset($_GET['qid']))
	{
		$qid = intval($_GET['qid']);
		if ($qid < 1)
			message($lang_common['Bad request']);

		$query = new SelectQuery(array('poster' => 'p.poster', 'message' => 'p.message'), 'posts AS p');
		$query->where = 'p.id = :qid AND p.topic_id = :tid';

		$params = array(':qid' => $qid, ':tid' => $tid);

		$result = $db->query($query, $params);
		if (empty($result))
			message($lang_common['Bad request']);

		$cur_quote = $result[0];
		unset ($result, $query, $params);

		// If the message contains a code tag we have to split it up (text within [code][/code] shouldn't be touched)
		if (strpos($cur_quote['message'], '[code]') !== false && strpos($cur_quote['message'], '[/code]') !== false)
		{
			$errors = array();
			list($inside, $outside) = split_text($cur_quote['message'], '[code]', '[/code]', $errors);
			if (!empty($errors)) // Technically this shouldn't happen, since $cur_quote['message'] is an existing post it should only exist if it previously passed validation
				message($errors[0]);

			$cur_quote['message'] = implode("\1", $outside);
		}

		// Remove [img] tags from quoted message
		$cur_quote['message'] = preg_replace('%\[img(?:=(?:[^\[]*?))?\]((ht|f)tps?://)([^\s<"]*?)\[/img\]%U', '\1\3', $cur_quote['message']);

		// If we split up the message before we have to concatenate it together again (code tags)
		if (isset($inside))
		{
			$outside = explode("\1", $cur_quote['message']);
			$cur_quote['message'] = '';

			$num_tokens = count($outside);
			for ($i = 0; $i < $num_tokens; ++$i)
			{
				$cur_quote['message'] .= $outside[$i];
				if (isset($inside[$i]))
					$cur_quote['message'] .= '[code]'.$inside[$i].'[/code]';
			}

			unset($inside);
		}

		if ($pun_config['o_censoring'] == '1')
			$cur_quote['message'] = censor_words($cur_quote['message']);

		$cur_quote['message'] = pun_htmlspecialchars($cur_quote['message']);

		if ($pun_config['p_message_bbcode'] == '1')
		{
			// If username contains a square bracket, we add "" or '' around it (so we know when it starts and ends)
			if (strpos($cur_quote['poster'], '[') !== false || strpos($cur_quote['poster'], ']') !== false)
			{
				if (strpos($cur_quote['poster'], '\'') !== false)
					$cur_quote['poster'] = '"'.$cur_quote['poster'].'"';
				else
					$cur_quote['poster'] = '\''.$cur_quote['poster'].'\'';
			}
			else
			{
				// Get the characters at the start and end of $cur_quote['poster']
				$ends = substr($cur_quote['poster'], 0, 1).substr($cur_quote['poster'], -1, 1);

				// Deal with quoting "Username" or 'Username' (becomes '"Username"' or "'Username'")
				if ($ends == '\'\'')
					$cur_quote['poster'] = '"'.$cur_quote['poster'].'"';
				else if ($ends == '""')
					$cur_quote['poster'] = '\''.$cur_quote['poster'].'\'';
			}

			$quote = '[quote='.$cur_quote['poster'].']'.$cur_quote['message'].'[/quote]'."\n";
		}
		else
			$quote = '> '.$cur_quote['poster'].' '.$lang_common['wrote']."\n\n".'> '.$cur_quote['message']."\n";
	}
}
// If a forum ID was specified in the url (new topic)
else if ($fid)
{
	$action = $lang_post['Post new topic'];
	$form = '<form id="post" method="post" action="post.php?action=post&amp;fid='.$fid.'" onsubmit="return process_form(this)">';
}
else
	message($lang_common['Bad request']);


$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $action);
$required_fields = array('req_email' => $lang_common['Email'], 'req_subject' => $lang_common['Subject'], 'req_message' => $lang_common['Message']);
$focus_element = array('post');

if (!$pun_user['is_guest'])
	$focus_element[] = ($fid) ? 'req_subject' : 'req_message';
else
{
	$required_fields['req_username'] = $lang_post['Guest name'];
	$focus_element[] = 'req_username';
}

define('PUN_ACTIVE_PAGE', 'index');
require PUN_ROOT.'header.php';

?>
<div class="linkst">
	<div class="inbox">
		<ul class="crumbs">
			<li><a href="index.php"><?php echo $lang_common['Index'] ?></a></li>
			<li><span>»&#160;</span><a href="viewforum.php?id=<?php echo $cur_posting['id'] ?>"><?php echo pun_htmlspecialchars($cur_posting['forum_name']) ?></a></li>
<?php if (isset($cur_posting['subject'])): ?>			<li><span>»&#160;</span><a href="viewtopic.php?id=<?php echo $tid ?>"><?php echo pun_htmlspecialchars($cur_posting['subject']) ?></a></li>
<?php endif; ?>			<li><span>»&#160;</span><strong><?php echo $action ?></strong></li>
		</ul>
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
		<div class="inbox error-info">
			<p><?php echo $lang_post['Post errors info'] ?></p>
			<ul class="error-list">
<?php

	foreach ($errors as $cur_error)
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
			<div class="postbody">
				<div class="postright">
					<div class="postmsg">
						<?php echo $preview_message."\n" ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<?php

}


$cur_index = 1;

?>
<div id="postform" class="blockform">
	<h2><span><?php echo $action ?></span></h2>
	<div class="box">
		<?php echo $form."\n" ?>
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_common['Write message legend'] ?></legend>
					<div class="infldset txtarea">
						<input type="hidden" name="form_sent" value="1" />
<?php

if ($pun_user['is_guest'])
{
	$email_label = ($pun_config['p_force_guest_email'] == '1') ? '<strong>'.$lang_common['Email'].' <span>'.$lang_common['Required'].'</span></strong>' : $lang_common['Email'];
	$email_form_name = ($pun_config['p_force_guest_email'] == '1') ? 'req_email' : 'email';

?>
						<label class="conl required"><strong><?php echo $lang_post['Guest name'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br /><input type="text" name="req_username" value="<?php if (isset($_POST['req_username'])) echo pun_htmlspecialchars($username); ?>" size="25" maxlength="25" tabindex="<?php echo $cur_index++ ?>" /><br /></label>
						<label class="conl<?php echo ($pun_config['p_force_guest_email'] == '1') ? ' required' : '' ?>"><?php echo $email_label ?><br /><input type="text" name="<?php echo $email_form_name ?>" value="<?php if (isset($_POST[$email_form_name])) echo pun_htmlspecialchars($email); ?>" size="50" maxlength="80" tabindex="<?php echo $cur_index++ ?>" /><br /></label>
						<div class="clearer"></div>
<?php

}

if ($fid): ?>
						<label class="required"><strong><?php echo $lang_common['Subject'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br /><input class="longinput" type="text" name="req_subject" value="<?php if (isset($_POST['req_subject'])) echo pun_htmlspecialchars($subject); ?>" size="80" maxlength="70" tabindex="<?php echo $cur_index++ ?>" /><br /></label>
<?php endif; ?>						<label class="required"><strong><?php echo $lang_common['Message'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br />
						<textarea name="req_message" rows="20" cols="95" tabindex="<?php echo $cur_index++ ?>"><?php echo isset($_POST['req_message']) ? pun_htmlspecialchars($orig_message) : (isset($quote) ? $quote : ''); ?></textarea><br /></label>
						<ul class="bblinks">
							<li><span><a href="help.php#bbcode" onclick="window.open(this.href); return false;"><?php echo $lang_common['BBCode'] ?></a> <?php echo ($pun_config['p_message_bbcode'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
							<li><span><a href="help.php#img" onclick="window.open(this.href); return false;"><?php echo $lang_common['img tag'] ?></a> <?php echo ($pun_config['p_message_bbcode'] == '1' && $pun_config['p_message_img_tag'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
							<li><span><a href="help.php#smilies" onclick="window.open(this.href); return false;"><?php echo $lang_common['Smilies'] ?></a> <?php echo ($pun_config['o_smilies'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
						</ul>
					</div>
				</fieldset>
<?php

$checkboxes = array();
if ($is_admmod)
	$checkboxes[] = '<label><input type="checkbox" name="stick_topic" value="1" tabindex="'.($cur_index++).'"'.(isset($_POST['stick_topic']) ? ' checked="checked"' : '').' />'.$lang_common['Stick topic'].'<br /></label>';

if (!$pun_user['is_guest'])
{
	if ($pun_config['o_smilies'] == '1')
		$checkboxes[] = '<label><input type="checkbox" name="hide_smilies" value="1" tabindex="'.($cur_index++).'"'.(isset($_POST['hide_smilies']) ? ' checked="checked"' : '').' />'.$lang_post['Hide smilies'].'<br /></label>';

	if ($pun_config['o_topic_subscriptions'] == '1')
	{
		$subscr_checked = false;

		// If it's a preview
		if (isset($_POST['preview']))
			$subscr_checked = isset($_POST['subscribe']) ? true : false;
		// If auto subscribed
		else if ($pun_user['auto_notify'])
			$subscr_checked = true;
		// If already subscribed to the topic
		else if ($is_subscribed)
			$subscr_checked = true;

		$checkboxes[] = '<label><input type="checkbox" name="subscribe" value="1" tabindex="'.($cur_index++).'"'.($subscr_checked ? ' checked="checked"' : '').' />'.($is_subscribed ? $lang_post['Stay subscribed'] : $lang_post['Subscribe']).'<br /></label>';
	}
}
else if ($pun_config['o_smilies'] == '1')
	$checkboxes[] = '<label><input type="checkbox" name="hide_smilies" value="1" tabindex="'.($cur_index++).'"'.(isset($_POST['hide_smilies']) ? ' checked="checked"' : '').' />'.$lang_post['Hide smilies'].'<br /></label>';

if (!empty($checkboxes))
{

?>
			</div>
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_common['Options'] ?></legend>
					<div class="infldset">
						<div class="rbox">
							<?php echo implode("\n\t\t\t\t\t\t\t", $checkboxes)."\n" ?>
						</div>
					</div>
				</fieldset>
<?php

}

?>
			</div>
			<p class="buttons"><input type="submit" name="submit" value="<?php echo $lang_common['Submit'] ?>" tabindex="<?php echo $cur_index++ ?>" accesskey="s" /> <input type="submit" name="preview" value="<?php echo $lang_post['Preview'] ?>" tabindex="<?php echo $cur_index++ ?>" accesskey="p" /> <a href="javascript:history.go(-1)"><?php echo $lang_common['Go back'] ?></a></p>
		</form>
	</div>
</div>

<?php

// Check to see if the topic review is to be displayed
if ($tid && $pun_config['o_topic_review'] != '0')
{
	require_once PUN_ROOT.'include/parser.php';

?>

<div id="postreview">
	<h2><span><?php echo $lang_post['Topic review'] ?></span></h2>
<?php

	$query = new SelectQuery(array('poster' => 'p.poster', 'message' => 'p.message', 'hide_smilies' => 'p.hide_smilies', 'posted' => 'p.posted'), 'posts AS p');
	$query->where = 'p.topic_id = :tid';
	$query->order = array('id' => 'p.id DESC');
	$query->limit = $pun_config['o_topic_review'];

	$params = array(':tid' => $tid);

	$result = $db->query($query, $params);

	// Set background switching on
	$post_count = 0;

	foreach ($result as $cur_post)
	{
		$post_count++;

		$cur_post['message'] = parse_message($cur_post['message'], $cur_post['hide_smilies']);

?>
	<div class="blockpost">
		<div class="box<?php echo ($post_count % 2 == 0) ? ' roweven' : ' rowodd' ?>">
			<div class="inbox">
				<div class="postbody">
					<div class="postleft">
						<dl>
							<dt><strong><?php echo pun_htmlspecialchars($cur_post['poster']) ?></strong></dt>
							<dd><span><?php echo format_time($cur_post['posted']) ?></span></dd>
						</dl>
					</div>
					<div class="postright">
						<div class="postmsg">
							<?php echo $cur_post['message']."\n" ?>
						</div>
					</div>
				</div>
				<div class="clearer"></div>
			</div>
		</div>
	</div>
<?php

	}

	unset ($result, $query, $params);

?>
</div>
<?php

}

require PUN_ROOT.'footer.php';
