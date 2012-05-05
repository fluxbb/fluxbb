<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

if (isset($_GET['action']))
	define('PUN_QUIET_VISIT', 1);

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';


// Load the misc.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/misc.php';

$action = isset($_GET['action']) ? $_GET['action'] : null;


if ($action == 'rules')
{
	if ($pun_config['o_rules'] == '0' || ($pun_user['is_guest'] && $pun_user['g_read_board'] == '0' && $pun_config['o_regs_allow'] == '0'))
		message($lang_common['Bad request']);

	// Load the register.php language file
	require PUN_ROOT.'lang/'.$pun_user['language'].'/register.php';

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_register['Forum rules']);
	define('PUN_ACTIVE_PAGE', 'rules');
	require PUN_ROOT.'header.php';

?>
<div id="rules" class="block">
	<div class="hd"><h2><span><?php echo $lang_register['Forum rules'] ?></span></h2></div>
	<div class="box">
		<div id="rules-block" class="inbox">
			<div class="usercontent"><?php echo $pun_config['o_rules_message'] ?></div>
		</div>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


else if ($action == 'markread')
{
	if ($pun_user['is_guest'])
		message($lang_common['No permission'], false, '403 Forbidden');

	$db->query('UPDATE '.$db->prefix.'users SET last_visit='.$pun_user['logged'].' WHERE id='.$pun_user['id']) or error('Unable to update user last visit data', __FILE__, __LINE__, $db->error());

	// Reset tracked topics
	set_tracked_topics(null);

	redirect('index.php', $lang_misc['Mark read redirect']);
}


// Mark the topics/posts in a forum as read?
else if ($action == 'markforumread')
{
	if ($pun_user['is_guest'])
		message($lang_common['No permission'], false, '403 Forbidden');

	$fid = isset($_GET['fid']) ? intval($_GET['fid']) : 0;
	if ($fid < 1)
		message($lang_common['Bad request']);

	$tracked_topics = get_tracked_topics();
	$tracked_topics['forums'][$fid] = time();
	set_tracked_topics($tracked_topics);

	redirect('viewforum.php?id='.$fid, $lang_misc['Mark forum read redirect']);
}


else if (isset($_GET['email']))
{
	if ($pun_user['is_guest'] || $pun_user['g_send_email'] == '0')
		message($lang_common['No permission'], false, '403 Forbidden');

	$recipient_id = intval($_GET['email']);
	if ($recipient_id < 2)
		message($lang_common['Bad request']);

	$result = $db->query('SELECT username, email, email_setting FROM '.$db->prefix.'users WHERE id='.$recipient_id) or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());
	if (!$db->num_rows($result))
		message($lang_common['Bad request']);

	list($recipient, $recipient_email, $email_setting) = $db->fetch_row($result);

	if ($email_setting == 2 && !$pun_user['is_admmod'])
		message($lang_misc['Form email disabled']);


	if (isset($_POST['form_sent']))
	{
		// Clean up message and subject from POST
		$subject = pun_trim($_POST['req_subject']);
		$message = pun_trim($_POST['req_message']);

		if ($subject == '')
			message($lang_misc['No email subject']);
		else if ($message == '')
			message($lang_misc['No email message']);
		else if (pun_strlen($message) > PUN_MAX_POSTSIZE)
			message($lang_misc['Too long email message']);

		if ($pun_user['last_email_sent'] != '' && (time() - $pun_user['last_email_sent']) < $pun_user['g_email_flood'] && (time() - $pun_user['last_email_sent']) >= 0)
			message(sprintf($lang_misc['Email flood'], $pun_user['g_email_flood']));

		// Load the "form email" template
		$mail_tpl = trim(file_get_contents(PUN_ROOT.'lang/'.$pun_user['language'].'/mail_templates/form_email.tpl'));

		// The first row contains the subject
		$first_crlf = strpos($mail_tpl, "\n");
		$mail_subject = pun_trim(substr($mail_tpl, 8, $first_crlf-8));
		$mail_message = pun_trim(substr($mail_tpl, $first_crlf));

		$mail_subject = str_replace('<mail_subject>', $subject, $mail_subject);
		$mail_message = str_replace('<sender>', $pun_user['username'], $mail_message);
		$mail_message = str_replace('<board_title>', $pun_config['o_board_title'], $mail_message);
		$mail_message = str_replace('<mail_message>', $message, $mail_message);
		$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message);

		require_once PUN_ROOT.'include/email.php';

		pun_mail($recipient_email, $mail_subject, $mail_message, $pun_user['email'], $pun_user['username']);

		$db->query('UPDATE '.$db->prefix.'users SET last_email_sent='.time().' WHERE id='.$pun_user['id']) or error('Unable to update user', __FILE__, __LINE__, $db->error());

		redirect(htmlspecialchars($_POST['redirect_url']), $lang_misc['Email sent redirect']);
	}


	// Try to determine if the data in HTTP_REFERER is valid (if not, we redirect to the users profile after the email is sent)
	if (!empty($_SERVER['HTTP_REFERER']))
	{
		$referrer = parse_url($_SERVER['HTTP_REFERER']);
		// Remove www subdomain if it exists
		if (strpos($referrer['host'], 'www.') === 0)
			$referrer['host'] = substr($referrer['host'], 4);

		// Make sure the path component exists
		if (!isset($referrer['path']))
			$referrer['path'] = '';

		$valid = parse_url(get_base_url());
		// Remove www subdomain if it exists
		if (strpos($valid['host'], 'www.') === 0)
			$valid['host'] = substr($valid['host'], 4);

		// Make sure the path component exists
		if (!isset($valid['path']))
			$valid['path'] = '';

		if ($referrer['host'] == $valid['host'] && preg_match('%^'.preg_quote($valid['path'], '%').'/(.*?)\.php%i', $referrer['path']))
			$redirect_url = $_SERVER['HTTP_REFERER'];
	}

	if (!isset($redirect_url))
		$redirect_url = 'profile.php?id='.$recipient_id;

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_misc['Send email to'].' '.pun_htmlspecialchars($recipient));
	$required_fields = array('req_subject' => $lang_misc['Email subject'], 'req_message' => $lang_misc['Email message']);
	$focus_element = array('email', 'req_subject');
	define('PUN_ACTIVE_PAGE', 'index');
	require PUN_ROOT.'header.php';

?>
<div id="emailform" class="blockform">
	<h2><span><?php echo $lang_misc['Send email to'] ?> <?php echo pun_htmlspecialchars($recipient) ?></span></h2>
	<div class="box">
		<form id="email" method="post" action="misc.php?email=<?php echo $recipient_id ?>" onsubmit="this.submit.disabled=true;if(process_form(this)){return true;}else{this.submit.disabled=false;return false;}">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_misc['Write email'] ?></legend>
					<div class="infldset txtarea">
						<input type="hidden" name="form_sent" value="1" />
						<input type="hidden" name="redirect_url" value="<?php echo pun_htmlspecialchars($redirect_url) ?>" />
						<label class="required"><strong><?php echo $lang_misc['Email subject'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br />
						<input class="longinput" type="text" name="req_subject" size="75" maxlength="70" tabindex="1" /><br /></label>
						<label class="required"><strong><?php echo $lang_misc['Email message'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br />
						<textarea name="req_message" rows="10" cols="75" tabindex="2"></textarea><br /></label>
						<p><?php echo $lang_misc['Email disclosure note'] ?></p>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="submit" value="<?php echo $lang_common['Submit'] ?>" tabindex="3" accesskey="s" /> <a href="javascript:history.go(-1)"><?php echo $lang_common['Go back'] ?></a></p>
		</form>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


else if (isset($_GET['report']))
{
	if ($pun_user['is_guest'])
		message($lang_common['No permission']);

	$post_id = intval($_GET['report']);
	if ($post_id < 1)
		message($lang_common['Bad request']);

	if (isset($_POST['form_sent']))
	{
		// Clean up reason from POST
		$reason = pun_linebreaks(pun_trim($_POST['req_reason']));
		if ($reason == '')
			message($lang_misc['No reason']);
		else if (strlen($reason) > 65535) // TEXT field can only hold 65535 bytes
			message($lang_misc['Reason too long']);

		if ($pun_user['last_report_sent'] != '' && (time() - $pun_user['last_report_sent']) < $pun_user['g_report_flood'] && (time() - $pun_user['last_report_sent']) >= 0)
			message(sprintf($lang_misc['Report flood'], $pun_user['g_report_flood']));

		// Get the topic ID
		$result = $db->query('SELECT topic_id FROM '.$db->prefix.'posts WHERE id='.$post_id) or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
		if (!$db->num_rows($result))
			message($lang_common['Bad request']);

		$topic_id = $db->result($result);

		// Get the subject and forum ID
		$result = $db->query('SELECT subject, forum_id FROM '.$db->prefix.'topics WHERE id='.$topic_id) or error('Unable to fetch topic info', __FILE__, __LINE__, $db->error());
		if (!$db->num_rows($result))
			message($lang_common['Bad request']);

		list($subject, $forum_id) = $db->fetch_row($result);

		// Should we use the internal report handling?
		if ($pun_config['o_report_method'] == '0' || $pun_config['o_report_method'] == '2')
			$db->query('INSERT INTO '.$db->prefix.'reports (post_id, topic_id, forum_id, reported_by, created, message) VALUES('.$post_id.', '.$topic_id.', '.$forum_id.', '.$pun_user['id'].', '.time().', \''.$db->escape($reason).'\')' ) or error('Unable to create report', __FILE__, __LINE__, $db->error());

		// Should we email the report?
		if ($pun_config['o_report_method'] == '1' || $pun_config['o_report_method'] == '2')
		{
			// We send it to the complete mailing-list in one swoop
			if ($pun_config['o_mailing_list'] != '')
			{
				// Load the "new report" template
				$mail_tpl = trim(file_get_contents(PUN_ROOT.'lang/'.$pun_user['language'].'/mail_templates/new_report.tpl'));

				// The first row contains the subject
				$first_crlf = strpos($mail_tpl, "\n");
				$mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
				$mail_message = trim(substr($mail_tpl, $first_crlf));

				$mail_subject = str_replace('<forum_id>', $forum_id, $mail_subject);
				$mail_subject = str_replace('<topic_subject>', $subject, $mail_subject);
				$mail_message = str_replace('<username>', $pun_user['username'], $mail_message);
				$mail_message = str_replace('<post_url>', get_base_url().'/viewtopic.php?pid='.$post_id.'#p'.$post_id, $mail_message);
				$mail_message = str_replace('<reason>', $reason, $mail_message);
				$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message);

				require PUN_ROOT.'include/email.php';

				pun_mail($pun_config['o_mailing_list'], $mail_subject, $mail_message);
			}
		}

		$db->query('UPDATE '.$db->prefix.'users SET last_report_sent='.time().' WHERE id='.$pun_user['id']) or error('Unable to update user', __FILE__, __LINE__, $db->error());

		redirect('viewtopic.php?pid='.$post_id.'#p'.$post_id, $lang_misc['Report redirect']);
	}

	// Fetch some info about the post, the topic and the forum
	$result = $db->query('SELECT f.id AS fid, f.forum_name, t.id AS tid, t.subject FROM '.$db->prefix.'posts AS p INNER JOIN '.$db->prefix.'topics AS t ON t.id=p.topic_id INNER JOIN '.$db->prefix.'forums AS f ON f.id=t.forum_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND p.id='.$post_id) or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
	if (!$db->num_rows($result))
		message($lang_common['Bad request']);

	$cur_post = $db->fetch_assoc($result);

	if ($pun_config['o_censoring'] == '1')
		$cur_post['subject'] = censor_words($cur_post['subject']);

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_misc['Report post']);
	$required_fields = array('req_reason' => $lang_misc['Reason']);
	$focus_element = array('report', 'req_reason');
	define('PUN_ACTIVE_PAGE', 'index');
	require PUN_ROOT.'header.php';

?>
<div class="linkst">
	<div class="inbox">
		<ul class="crumbs">
			<li><a href="index.php"><?php echo $lang_common['Index'] ?></a></li>
			<li><span>»&#160;</span><a href="viewforum.php?id=<?php echo $cur_post['fid'] ?>"><?php echo pun_htmlspecialchars($cur_post['forum_name']) ?></a></li>
			<li><span>»&#160;</span><a href="viewtopic.php?pid=<?php echo $post_id ?>#p<?php echo $post_id ?>"><?php echo pun_htmlspecialchars($cur_post['subject']) ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang_misc['Report post'] ?></strong></li>
		</ul>
	</div>
</div>

<div id="reportform" class="blockform">
	<h2><span><?php echo $lang_misc['Report post'] ?></span></h2>
	<div class="box">
		<form id="report" method="post" action="misc.php?report=<?php echo $post_id ?>" onsubmit="this.submit.disabled=true;if(process_form(this)){return true;}else{this.submit.disabled=false;return false;}">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_misc['Reason desc'] ?></legend>
					<div class="infldset txtarea">
						<input type="hidden" name="form_sent" value="1" />
						<label class="required"><strong><?php echo $lang_misc['Reason'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br /><textarea name="req_reason" rows="5" cols="60"></textarea><br /></label>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="submit" value="<?php echo $lang_common['Submit'] ?>" accesskey="s" /> <a href="javascript:history.go(-1)"><?php echo $lang_common['Go back'] ?></a></p>
		</form>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


else if ($action == 'subscribe')
{
	if ($pun_user['is_guest'])
		message($lang_common['No permission']);

	$topic_id = isset($_GET['tid']) ? intval($_GET['tid']) : 0;
	$forum_id = isset($_GET['fid']) ? intval($_GET['fid']) : 0;
	if ($topic_id < 1 && $forum_id < 1)
		message($lang_common['Bad request']);

	if ($topic_id)
	{
		if ($pun_config['o_topic_subscriptions'] != '1')
			message($lang_common['No permission']);

		// Make sure the user can view the topic
		$result = $db->query('SELECT 1 FROM '.$db->prefix.'topics AS t LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=t.forum_id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND t.id='.$topic_id.' AND t.moved_to IS NULL') or error('Unable to fetch topic info', __FILE__, __LINE__, $db->error());
		if (!$db->num_rows($result))
			message($lang_common['Bad request']);

		$result = $db->query('SELECT 1 FROM '.$db->prefix.'topic_subscriptions WHERE user_id='.$pun_user['id'].' AND topic_id='.$topic_id) or error('Unable to fetch subscription info', __FILE__, __LINE__, $db->error());
		if ($db->num_rows($result))
			message($lang_misc['Already subscribed topic']);

		$db->query('INSERT INTO '.$db->prefix.'topic_subscriptions (user_id, topic_id) VALUES('.$pun_user['id'].' ,'.$topic_id.')') or error('Unable to add subscription', __FILE__, __LINE__, $db->error());

		redirect('viewtopic.php?id='.$topic_id, $lang_misc['Subscribe redirect']);
	}

	if ($forum_id)
	{
		if ($pun_config['o_forum_subscriptions'] != '1')
			message($lang_common['No permission']);

		// Make sure the user can view the forum
		$result = $db->query('SELECT 1 FROM '.$db->prefix.'forums AS f LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND f.id='.$forum_id) or error('Unable to fetch forum info', __FILE__, __LINE__, $db->error());
		if (!$db->num_rows($result))
			message($lang_common['Bad request']);

		$result = $db->query('SELECT 1 FROM '.$db->prefix.'forum_subscriptions WHERE user_id='.$pun_user['id'].' AND forum_id='.$forum_id) or error('Unable to fetch subscription info', __FILE__, __LINE__, $db->error());
		if ($db->num_rows($result))
			message($lang_misc['Already subscribed forum']);

		$db->query('INSERT INTO '.$db->prefix.'forum_subscriptions (user_id, forum_id) VALUES('.$pun_user['id'].' ,'.$forum_id.')') or error('Unable to add subscription', __FILE__, __LINE__, $db->error());

		redirect('viewforum.php?id='.$forum_id, $lang_misc['Subscribe redirect']);
	}
}


else if ($action == 'unsubscribe')
{
	if ($pun_user['is_guest'])
		message($lang_common['No permission']);

	$topic_id = isset($_GET['tid']) ? intval($_GET['tid']) : 0;
	$forum_id = isset($_GET['fid']) ? intval($_GET['fid']) : 0;
	if ($topic_id < 1 && $forum_id < 1)
		message($lang_common['Bad request']);

	if ($topic_id)
	{
		if ($pun_config['o_topic_subscriptions'] != '1')
			message($lang_common['No permission']);

		$result = $db->query('SELECT 1 FROM '.$db->prefix.'topic_subscriptions WHERE user_id='.$pun_user['id'].' AND topic_id='.$topic_id) or error('Unable to fetch subscription info', __FILE__, __LINE__, $db->error());
		if (!$db->num_rows($result))
			message($lang_misc['Not subscribed topic']);

		$db->query('DELETE FROM '.$db->prefix.'topic_subscriptions WHERE user_id='.$pun_user['id'].' AND topic_id='.$topic_id) or error('Unable to remove subscription', __FILE__, __LINE__, $db->error());

		redirect('viewtopic.php?id='.$topic_id, $lang_misc['Unsubscribe redirect']);
	}

	if ($forum_id)
	{
		if ($pun_config['o_forum_subscriptions'] != '1')
			message($lang_common['No permission']);

		$result = $db->query('SELECT 1 FROM '.$db->prefix.'forum_subscriptions WHERE user_id='.$pun_user['id'].' AND forum_id='.$forum_id) or error('Unable to fetch subscription info', __FILE__, __LINE__, $db->error());
		if (!$db->num_rows($result))
			message($lang_misc['Not subscribed forum']);

		$db->query('DELETE FROM '.$db->prefix.'forum_subscriptions WHERE user_id='.$pun_user['id'].' AND forum_id='.$forum_id) or error('Unable to remove subscription', __FILE__, __LINE__, $db->error());

		redirect('viewforum.php?id='.$forum_id, $lang_misc['Unsubscribe redirect']);
	}
}


else
	message($lang_common['Bad request']);
