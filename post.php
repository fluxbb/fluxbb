<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';


if ($pun_user['g_read_board'] == '0')
	message($lang_common['No view'], false, '403 Forbidden');


$tid = isset($_GET['tid']) ? intval($_GET['tid']) : 0;
$fid = isset($_GET['fid']) ? intval($_GET['fid']) : 0;
if ($tid < 1 && $fid < 1 || $tid > 0 && $fid > 0)
	message($lang_common['Bad request'], false, '404 Not Found');

// Fetch some info about the topic and/or the forum
if ($tid)
	$result = $db->query('SELECT f.id, f.forum_name, f.moderators, f.redirect_url, fp.post_replies, fp.post_topics, t.subject, t.closed, s.user_id AS is_subscribed FROM '.$db->prefix.'topics AS t INNER JOIN '.$db->prefix.'forums AS f ON f.id=t.forum_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') LEFT JOIN '.$db->prefix.'topic_subscriptions AS s ON (t.id=s.topic_id AND s.user_id='.$pun_user['id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND t.id='.$tid) or error('Unable to fetch forum info', __FILE__, __LINE__, $db->error());
else
	$result = $db->query('SELECT f.id, f.forum_name, f.moderators, f.redirect_url, fp.post_replies, fp.post_topics FROM '.$db->prefix.'forums AS f LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND f.id='.$fid) or error('Unable to fetch forum info', __FILE__, __LINE__, $db->error());

if (!$db->num_rows($result))
	message($lang_common['Bad request'], false, '404 Not Found');

$cur_posting = $db->fetch_assoc($result);
$is_subscribed = $tid && $cur_posting['is_subscribed'];

// Is someone trying to post into a redirect forum?
if ($cur_posting['redirect_url'] != '')
	message($lang_common['Bad request'], false, '404 Not Found');

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
	message($lang_common['No permission'], false, '403 Forbidden');

// Load the post.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/post.php';

// Start with a clean slate
$errors = array();


// Did someone just hit "Submit" or "Preview"?
if (isset($_POST['form_sent']))
{
	flux_hook('post_before_validation');

	// Flood protection
	if (!isset($_POST['preview']) && $pun_user['last_post'] != '' && (time() - $pun_user['last_post']) < $pun_user['g_post_flood'])
		$errors[] = sprintf($lang_post['Flood start'], $pun_user['g_post_flood'], $pun_user['g_post_flood'] - (time() - $pun_user['last_post']));

	// Make sure they got here from the site
	confirm_referrer(array('post.php', 'viewtopic.php'));

	// If it's a new topic
	if ($fid)
	{
		$subject = pun_trim($_POST['req_subject']);

		if ($subject == '')
			$errors[] = $lang_post['No subject'];
		else if ($pun_config['o_censoring'] == '1')
		{
			// Censor subject to see if that causes problems
			$subject = pun_trim(censor_words($subject));

			if ($subject == '')
				$errors[] = $lang_post['No subject after censoring'];
		}

		if (empty($errors))
		{
			if (pun_strlen($subject) > 70)
				$errors[] = $lang_post['Too long subject'];
			else if ($pun_config['p_subject_all_caps'] == '0' && is_all_uppercase($subject) && !$pun_user['is_admmod'])
				$errors[] = $lang_post['All caps subject'];
			else if ($pun_user['g_post_links'] != '1' && preg_match('%(?:h\s*t|f)\s*t\s*p\s*(?:s\s*)?:\s*/\s*/%', $subject))
				$errors[] = $lang_common['BBCode error tag url not allowed'];
		}
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
		$email = strtolower(pun_trim(($pun_config['p_force_guest_email'] == '1') ? $_POST['req_email'] : $_POST['email']));
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
			// we should only check guests because members' addresses are already verified
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
			$message = pun_trim(censor_words($message));

			if ($message == '')
				$errors[] = $lang_post['No message after censoring'];
		}
	}

	$hide_smilies = isset($_POST['hide_smilies']) ? '1' : '0';
	$subscribe = isset($_POST['subscribe']) ? '1' : '0';
	$stick_topic = isset($_POST['stick_topic']) && $is_admmod ? '1' : '0';

	// Replace four-byte characters (MySQL cannot handle them)
	$message = strip_bad_multibyte_chars($message);

	$now = time();

	flux_hook('post_after_validation');

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
				$db->query('INSERT INTO '.$db->prefix.'posts (poster, poster_id, poster_ip, message, hide_smilies, posted, topic_id) VALUES(\''.$db->escape($username).'\', '.$pun_user['id'].', \''.$db->escape(get_remote_address()).'\', \''.$db->escape($message).'\', '.$hide_smilies.', '.$now.', '.$tid.')') or error('Unable to create post', __FILE__, __LINE__, $db->error());
				$new_pid = $db->insert_id();

				// To subscribe or not to subscribe, that ...
				if ($pun_config['o_topic_subscriptions'] == '1')
				{
					if ($subscribe && !$is_subscribed)
						$db->query('INSERT INTO '.$db->prefix.'topic_subscriptions (user_id, topic_id) VALUES('.$pun_user['id'].' ,'.$tid.')') or error('Unable to add subscription', __FILE__, __LINE__, $db->error());
					else if (!$subscribe && $is_subscribed)
						$db->query('DELETE FROM '.$db->prefix.'topic_subscriptions WHERE user_id='.$pun_user['id'].' AND topic_id='.$tid) or error('Unable to remove subscription', __FILE__, __LINE__, $db->error());
				}
			}
			else
			{
				// It's a guest. Insert the new post
				$email_sql = ($pun_config['p_force_guest_email'] == '1' || $email != '') ? '\''.$db->escape($email).'\'' : 'NULL';
				$db->query('INSERT INTO '.$db->prefix.'posts (poster, poster_ip, poster_email, message, hide_smilies, posted, topic_id) VALUES(\''.$db->escape($username).'\', \''.$db->escape(get_remote_address()).'\', '.$email_sql.', \''.$db->escape($message).'\', '.$hide_smilies.', '.$now.', '.$tid.')') or error('Unable to create post', __FILE__, __LINE__, $db->error());
				$new_pid = $db->insert_id();
			}

			// Update topic
			$db->query('UPDATE '.$db->prefix.'topics SET num_replies=num_replies+1, last_post='.$now.', last_post_id='.$new_pid.', last_poster=\''.$db->escape($username).'\' WHERE id='.$tid) or error('Unable to update topic', __FILE__, __LINE__, $db->error());

			update_search_index('post', $new_pid, $message);

			update_forum($cur_posting['id']);

			// Should we send out notifications?
			if ($pun_config['o_topic_subscriptions'] == '1')
			{
				// Get the post time for the previous post in this topic
				$result = $db->query('SELECT posted FROM '.$db->prefix.'posts WHERE topic_id='.$tid.' ORDER BY id DESC LIMIT 1, 1') or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
				$previous_post_time = $db->result($result);

				// Get any subscribed users that should be notified (banned users are excluded)
				$result = $db->query('SELECT u.id, u.email, u.notify_with_post, u.language FROM '.$db->prefix.'users AS u INNER JOIN '.$db->prefix.'topic_subscriptions AS s ON u.id=s.user_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id='.$cur_posting['id'].' AND fp.group_id=u.group_id) LEFT JOIN '.$db->prefix.'online AS o ON u.id=o.user_id LEFT JOIN '.$db->prefix.'bans AS b ON u.username=b.username WHERE b.username IS NULL AND COALESCE(o.logged, u.last_visit)>'.$previous_post_time.' AND (fp.read_forum IS NULL OR fp.read_forum=1) AND s.topic_id='.$tid.' AND u.id!='.$pun_user['id']) or error('Unable to fetch subscription info', __FILE__, __LINE__, $db->error());
				if ($db->num_rows($result))
				{
					require_once PUN_ROOT.'include/email.php';

					$notification_emails = array();
					$languages = forum_list_langs();

					$cleaned_message = bbcode2email($message, -1);

					// Loop through subscribed users and send emails
					while ($cur_subscriber = $db->fetch_assoc($result))
					{
						if (!in_array($cur_subscriber['language'], $languages))
							$cur_subscriber['language'] = $pun_config['o_default_lang'];

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
								$mail_message = str_replace('<unsubscribe_url>', get_base_url().'/viewtopic.php?id='.$tid.'#unsubscribe', $mail_message);
								$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message);

								$mail_subject_full = str_replace('<topic_subject>', $cur_posting['subject'], $mail_subject_full);
								$mail_message_full = str_replace('<topic_subject>', $cur_posting['subject'], $mail_message_full);
								$mail_message_full = str_replace('<replier>', $username, $mail_message_full);
								$mail_message_full = str_replace('<message>', $cleaned_message, $mail_message_full);
								$mail_message_full = str_replace('<post_url>', get_base_url().'/viewtopic.php?pid='.$new_pid.'#p'.$new_pid, $mail_message_full);
								$mail_message_full = str_replace('<unsubscribe_url>', get_base_url().'/viewtopic.php?id='.$tid.'#unsubscribe', $mail_message_full);
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
			}
		}
		// If it's a new topic
		else if ($fid)
		{
			// Create the topic
			$db->query('INSERT INTO '.$db->prefix.'topics (poster, subject, posted, last_post, last_poster, sticky, forum_id) VALUES(\''.$db->escape($username).'\', \''.$db->escape($subject).'\', '.$now.', '.$now.', \''.$db->escape($username).'\', '.$stick_topic.', '.$fid.')') or error('Unable to create topic', __FILE__, __LINE__, $db->error());
			$new_tid = $db->insert_id();

			if (!$pun_user['is_guest'])
			{
				// To subscribe or not to subscribe, that ...
				if ($pun_config['o_topic_subscriptions'] == '1' && $subscribe)
					$db->query('INSERT INTO '.$db->prefix.'topic_subscriptions (user_id, topic_id) VALUES('.$pun_user['id'].' ,'.$new_tid.')') or error('Unable to add subscription', __FILE__, __LINE__, $db->error());

				// Create the post ("topic post")
				$db->query('INSERT INTO '.$db->prefix.'posts (poster, poster_id, poster_ip, message, hide_smilies, posted, topic_id) VALUES(\''.$db->escape($username).'\', '.$pun_user['id'].', \''.$db->escape(get_remote_address()).'\', \''.$db->escape($message).'\', '.$hide_smilies.', '.$now.', '.$new_tid.')') or error('Unable to create post', __FILE__, __LINE__, $db->error());
			}
			else
			{
				// Create the post ("topic post")
				$email_sql = ($pun_config['p_force_guest_email'] == '1' || $email != '') ? '\''.$db->escape($email).'\'' : 'NULL';
				$db->query('INSERT INTO '.$db->prefix.'posts (poster, poster_ip, poster_email, message, hide_smilies, posted, topic_id) VALUES(\''.$db->escape($username).'\', \''.$db->escape(get_remote_address()).'\', '.$email_sql.', \''.$db->escape($message).'\', '.$hide_smilies.', '.$now.', '.$new_tid.')') or error('Unable to create post', __FILE__, __LINE__, $db->error());
			}
			$new_pid = $db->insert_id();

			// Update the topic with last_post_id
			$db->query('UPDATE '.$db->prefix.'topics SET last_post_id='.$new_pid.', first_post_id='.$new_pid.' WHERE id='.$new_tid) or error('Unable to update topic', __FILE__, __LINE__, $db->error());

			update_search_index('post', $new_pid, $message, $subject);

			update_forum($fid);

			// Should we send out notifications?
			if ($pun_config['o_forum_subscriptions'] == '1')
			{
				// Get any subscribed users that should be notified (banned users are excluded)
				$result = $db->query('SELECT u.id, u.email, u.notify_with_post, u.language FROM '.$db->prefix.'users AS u INNER JOIN '.$db->prefix.'forum_subscriptions AS s ON u.id=s.user_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id='.$cur_posting['id'].' AND fp.group_id=u.group_id) LEFT JOIN '.$db->prefix.'bans AS b ON u.username=b.username WHERE b.username IS NULL AND (fp.read_forum IS NULL OR fp.read_forum=1) AND s.forum_id='.$cur_posting['id'].' AND u.id!='.$pun_user['id']) or error('Unable to fetch subscription info', __FILE__, __LINE__, $db->error());
				if ($db->num_rows($result))
				{
					require_once PUN_ROOT.'include/email.php';

					$notification_emails = array();
					$languages = forum_list_langs();

					$cleaned_message = bbcode2email($message, -1);

					// Loop through subscribed users and send emails
					while ($cur_subscriber = $db->fetch_assoc($result))
					{
						if (!in_array($cur_subscriber['language'], $languages))
							$cur_subscriber['language'] = $pun_config['o_default_lang'];

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
								$mail_message = str_replace('<topic_subject>', $subject, $mail_message);
								$mail_message = str_replace('<forum_name>', $cur_posting['forum_name'], $mail_message);
								$mail_message = str_replace('<poster>', $username, $mail_message);
								$mail_message = str_replace('<topic_url>', get_base_url().'/viewtopic.php?id='.$new_tid, $mail_message);
								$mail_message = str_replace('<unsubscribe_url>', get_base_url().'/viewforum.php?id='.$cur_posting['id'].'#unsubscribe', $mail_message);
								$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message);

								$mail_subject_full = str_replace('<forum_name>', $cur_posting['forum_name'], $mail_subject_full);
								$mail_message_full = str_replace('<topic_subject>', $subject, $mail_message_full);
								$mail_message_full = str_replace('<forum_name>', $cur_posting['forum_name'], $mail_message_full);
								$mail_message_full = str_replace('<poster>', $username, $mail_message_full);
								$mail_message_full = str_replace('<message>', $cleaned_message, $mail_message_full);
								$mail_message_full = str_replace('<topic_url>', get_base_url().'/viewtopic.php?id='.$new_tid, $mail_message_full);
								$mail_message_full = str_replace('<unsubscribe_url>', get_base_url().'/viewforum.php?id='.$cur_posting['id'].'#unsubscribe', $mail_message_full);
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
			$db->query('UPDATE '.$db->prefix.'users SET num_posts=num_posts+1, last_post='.$now.' WHERE id='.$pun_user['id']) or error('Unable to update user', __FILE__, __LINE__, $db->error());

			// Promote this user to a new group if enabled
			if ($pun_user['g_promote_next_group'] != 0 && $pun_user['num_posts'] + 1 >= $pun_user['g_promote_min_posts'])
			{
				$new_group_id = $pun_user['g_promote_next_group'];
				$db->query('UPDATE '.$db->prefix.'users SET group_id='.$new_group_id.' WHERE id='.$pun_user['id']) or error('Unable to promote user to new group', __FILE__, __LINE__, $db->error());
			}

			// Topic tracking stuff...
			$tracked_topics = get_tracked_topics();
			$tracked_topics['topics'][$new_tid] = time();
			set_tracked_topics($tracked_topics);
		}
		else
		{
			$db->query('UPDATE '.$db->prefix.'online SET last_post='.$now.' WHERE ident=\''.$db->escape(get_remote_address()).'\'' ) or error('Unable to update user', __FILE__, __LINE__, $db->error());
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
			message($lang_common['Bad request'], false, '404 Not Found');

		$result = $db->query('SELECT poster, message FROM '.$db->prefix.'posts WHERE id='.$qid.' AND topic_id='.$tid) or error('Unable to fetch quote info', __FILE__, __LINE__, $db->error());
		if (!$db->num_rows($result))
			message($lang_common['Bad request'], false, '404 Not Found');

		list($q_poster, $q_message) = $db->fetch_row($result);

		// If the message contains a code tag we have to split it up (text within [code][/code] shouldn't be touched)
		if (strpos($q_message, '[code]') !== false && strpos($q_message, '[/code]') !== false)
		{
			list($inside, $outside) = split_text($q_message, '[code]', '[/code]');

			$q_message = implode("\1", $outside);
		}

		// Remove [img] tags from quoted message
		$q_message = preg_replace('%\[img(?:=(?:[^\[]*?))?\]((ht|f)tps?://)([^\s<"]*?)\[/img\]%U', '\1\3', $q_message);

		// If we split up the message before we have to concatenate it together again (code tags)
		if (isset($inside))
		{
			$outside = explode("\1", $q_message);
			$q_message = '';

			$num_tokens = count($outside);
			for ($i = 0; $i < $num_tokens; ++$i)
			{
				$q_message .= $outside[$i];
				if (isset($inside[$i]))
					$q_message .= '[code]'.$inside[$i].'[/code]';
			}

			unset($inside);
		}

		if ($pun_config['o_censoring'] == '1')
			$q_message = censor_words($q_message);

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
			$quote = '> '.$q_poster.' '.$lang_common['wrote']."\n\n".'> '.$q_message."\n";
	}
}
// If a forum ID was specified in the url (new topic)
else if ($fid)
{
	$action = $lang_post['Post new topic'];
	$form = '<form id="post" method="post" action="post.php?action=post&amp;fid='.$fid.'" onsubmit="return process_form(this)">';
}
else
	message($lang_common['Bad request'], false, '404 Not Found');


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

flux_hook('post_before_header');

define('PUN_ACTIVE_PAGE', 'index');
require PUN_ROOT.'header.php';

?>
<div class="linkst">
	<div class="inbox">
		<ul class="crumbs">
			<li><a href="index.php"><?php echo $lang_common['Index'] ?></a></li>
			<li><span>»&#160;</span><a href="viewforum.php?id=<?php echo $cur_posting['id'] ?>"><?php echo pun_htmlspecialchars($cur_posting['forum_name']) ?></a></li>
<?php if (isset($_POST['req_subject'])): ?>			<li><span>»&#160;</span><?php echo pun_htmlspecialchars($_POST['req_subject']) ?></li>
<?php endif; ?>
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
						<label class="required"><strong><?php echo $lang_common['Subject'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br /><input class="longinput" type="text" name="req_subject" value="<?php if (isset($_POST['req_subject'])) echo pun_htmlspecialchars($_POST['req_subject']); ?>" size="80" maxlength="70" tabindex="<?php echo $cur_index++ ?>" /><br /></label>
<?php endif; ?>						<label class="required"><strong><?php echo $lang_common['Message'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br />
						<textarea name="req_message" rows="20" cols="95" tabindex="<?php echo $cur_index++ ?>"><?php echo isset($_POST['req_message']) ? pun_htmlspecialchars($orig_message) : (isset($quote) ? $quote : ''); ?></textarea><br /></label>
						<ul class="bblinks">
							<li><span><a href="help.php#bbcode" onclick="window.open(this.href); return false;"><?php echo $lang_common['BBCode'] ?></a> <?php echo ($pun_config['p_message_bbcode'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
							<li><span><a href="help.php#url" onclick="window.open(this.href); return false;"><?php echo $lang_common['url tag'] ?></a> <?php echo ($pun_config['p_message_bbcode'] == '1' && $pun_user['g_post_links'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
							<li><span><a href="help.php#img" onclick="window.open(this.href); return false;"><?php echo $lang_common['img tag'] ?></a> <?php echo ($pun_config['p_message_bbcode'] == '1' && $pun_config['p_message_img_tag'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
							<li><span><a href="help.php#smilies" onclick="window.open(this.href); return false;"><?php echo $lang_common['Smilies'] ?></a> <?php echo ($pun_config['o_smilies'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
						</ul>
					</div>
				</fieldset>
<?php

$checkboxes = array();
if ($fid && $is_admmod)
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
<?php flux_hook('post_before_submit') ?>
			<p class="buttons"><input type="submit" name="submit" value="<?php echo $lang_common['Submit'] ?>" tabindex="<?php echo $cur_index++ ?>" accesskey="s" /> <input type="submit" name="preview" value="<?php echo $lang_post['Preview'] ?>" tabindex="<?php echo $cur_index++ ?>" accesskey="p" /> <a href="javascript:history.go(-1)"><?php echo $lang_common['Go back'] ?></a></p>
		</form>
	</div>
</div>

<?php

// Check to see if the topic review is to be displayed
if ($tid && $pun_config['o_topic_review'] != '0')
{
	require_once PUN_ROOT.'include/parser.php';

	$result = $db->query('SELECT poster, message, hide_smilies, posted FROM '.$db->prefix.'posts WHERE topic_id='.$tid.' ORDER BY id DESC LIMIT '.$pun_config['o_topic_review']) or error('Unable to fetch topic review', __FILE__, __LINE__, $db->error());

?>

<div id="postreview">
	<h2><span><?php echo $lang_post['Topic review'] ?></span></h2>
<?php

	// Set background switching on
	$post_count = 0;

	while ($cur_post = $db->fetch_assoc($result))
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

?>
</div>
<?php

}

require PUN_ROOT.'footer.php';
