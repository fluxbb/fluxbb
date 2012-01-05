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
$lang->load('misc');

$action = isset($_GET['action']) ? $_GET['action'] : null;


if ($action == 'rules')
{
	if ($pun_config['o_rules'] == '0' || ($pun_user['is_guest'] && $pun_user['g_read_board'] == '0' && $pun_config['o_regs_allow'] == '0'))
		message($lang->t('Bad request'));

	// Load the register.php language file
	$lang->load('register');

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Forum rules'));
	define('PUN_ACTIVE_PAGE', 'rules');
	require PUN_ROOT.'header.php';

?>
<div id="rules" class="block">
	<div class="hd"><h2><span><?php echo $lang->t('Forum rules') ?></span></h2></div>
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
		message($lang->t('No permission'));

	$query = $db->update(array('last_visit' => ':logged'), 'users');
	$query->where = 'id = :user_id';

	$params = array(':logged' => $pun_user['logged'], ':user_id' => $pun_user['id']);

	$query->run($params);
	unset ($query, $params);

	// Reset tracked topics
	set_tracked_topics(null);

	redirect('index.php', $lang->t('Mark read redirect'));
}


// Mark the topics/posts in a forum as read?
else if ($action == 'markforumread')
{
	if ($pun_user['is_guest'])
		message($lang->t('No permission'));

	$fid = isset($_GET['fid']) ? intval($_GET['fid']) : 0;
	if ($fid < 1)
		message($lang->t('Bad request'));

	$tracked_topics = get_tracked_topics();
	$tracked_topics['forums'][$fid] = time();
	set_tracked_topics($tracked_topics);

	redirect('viewforum.php?id='.$fid, $lang->t('Mark forum read redirect'));
}


else if (isset($_GET['email']))
{
	if ($pun_user['is_guest'] || $pun_user['g_send_email'] == '0')
		message($lang->t('No permission'));

	$recipient_id = intval($_GET['email']);
	if ($recipient_id < 2)
		message($lang->t('Bad request'));

	$query = $db->select(array('username' => 'u.username', 'email' => 'u.email', 'email_setting' => 'u.email_setting'), 'users AS u');
	$query->where = 'u.id = :recipient_id';

	$params = array(':recipient_id' => $recipient_id);

	$result = $query->run($params);
	if (empty($result))
		message($lang->t('Bad request'));

	$recipient = $result[0];
	unset ($result, $query, $params);

	if ($recipient['email_setting'] == 2 && !$pun_user['is_admmod'])
		message($lang->t('Form email disabled'));


	if (isset($_POST['form_sent']))
	{
		// Clean up message and subject from POST
		$subject = pun_trim($_POST['req_subject']);
		$message = pun_trim($_POST['req_message']);

		if ($subject == '')
			message($lang->t('No email subject'));
		else if ($message == '')
			message($lang->t('No email message'));
		else if (pun_strlen($message) > PUN_MAX_POSTSIZE)
			message($lang->t('Too long email message'));

		if ($pun_user['last_email_sent'] != '' && (time() - $pun_user['last_email_sent']) < $pun_user['g_email_flood'] && (time() - $pun_user['last_email_sent']) >= 0)
			message($lang->t('Email flood', $pun_user['g_email_flood']));

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

		pun_mail($recipient['email'], $mail_subject, $mail_message, $pun_user['email'], $pun_user['username']);

		$query = $db->update(array('last_email_sent' => ':now'), 'users');
		$query->where = 'id = :user_id';

		$params = array(':now' => time(), ':user_id' => $pun_user['id']);

		$query->run($params);
		unset ($query, $params);

		redirect(htmlspecialchars($_POST['redirect_url']), $lang->t('Email sent redirect'));
	}


	// Try to determine if the data in HTTP_REFERER is valid (if not, we redirect to the users profile after the email is sent)
	if (!empty($_SERVER['HTTP_REFERER']))
	{
		$referrer = parse_url($_SERVER['HTTP_REFERER']);
		// Remove www subdomain if it exists
		if (strpos($referrer['host'], 'www.') === 0)
			$referrer['host'] = substr($referrer['host'], 4);

		$valid = parse_url(get_base_url());
		// Remove www subdomain if it exists
		if (strpos($valid['host'], 'www.') === 0)
			$valid['host'] = substr($valid['host'], 4);

		if ($referrer['host'] == $valid['host'] && preg_match('%^'.preg_quote($valid['path'], '%').'/(.*?)\.php%i', $referrer['path']))
			$redirect_url = $_SERVER['HTTP_REFERER'];
	}

	if (!isset($redirect_url))
		$redirect_url = 'profile.php?id='.$recipient_id;

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Send email to').' '.pun_htmlspecialchars($recipient['username']));
	$required_fields = array('req_subject' => $lang->t('Email subject'), 'req_message' => $lang->t('Email message'));
	$focus_element = array('email', 'req_subject');
	define('PUN_ACTIVE_PAGE', 'index');
	require PUN_ROOT.'header.php';

?>
<div id="emailform" class="blockform">
	<h2><span><?php echo $lang->t('Send email to') ?> <?php echo pun_htmlspecialchars($recipient['username']) ?></span></h2>
	<div class="box">
		<form id="email" method="post" action="misc.php?email=<?php echo $recipient_id ?>" onsubmit="this.submit.disabled=true;if(process_form(this)){return true;}else{this.submit.disabled=false;return false;}">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang->t('Write email') ?></legend>
					<div class="infldset txtarea">
						<input type="hidden" name="form_sent" value="1" />
						<input type="hidden" name="redirect_url" value="<?php echo pun_htmlspecialchars($redirect_url) ?>" />
						<label class="required"><strong><?php echo $lang->t('Email subject') ?> <span><?php echo $lang->t('Required') ?></span></strong><br />
						<input class="longinput" type="text" name="req_subject" size="75" maxlength="70" tabindex="1" /><br /></label>
						<label class="required"><strong><?php echo $lang->t('Email message') ?> <span><?php echo $lang->t('Required') ?></span></strong><br />
						<textarea name="req_message" rows="10" cols="75" tabindex="2"></textarea><br /></label>
						<p><?php echo $lang->t('Email disclosure note') ?></p>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="submit" value="<?php echo $lang->t('Submit') ?>" tabindex="3" accesskey="s" /> <a href="javascript:history.go(-1)"><?php echo $lang->t('Go back') ?></a></p>
		</form>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


else if (isset($_GET['report']))
{
	if ($pun_user['is_guest'])
		message($lang->t('No permission'));

	$post_id = intval($_GET['report']);
	if ($post_id < 1)
		message($lang->t('Bad request'));

	if (isset($_POST['form_sent']))
	{
		// Clean up reason from POST
		$reason = pun_linebreaks(pun_trim($_POST['req_reason']));
		if ($reason == '')
			message($lang->t('No reason'));
		else if (strlen($reason) > 65535) // TEXT field can only hold 65535 bytes
			message($lang->t('Reason too long'));

		if ($pun_user['last_report_sent'] != '' && (time() - $pun_user['last_report_sent']) < $pun_user['g_report_flood'] && (time() - $pun_user['last_report_sent']) >= 0)
			message($lang->t('Report flood', $pun_user['g_report_flood']));

		// Get the topic ID
		$query = $db->select(array('topic_id' => 'p.topic_id'), 'posts AS p');
		$query->where = 'p.id = :post_id';

		$params = array(':post_id' => $post_id);

		$result = $query->run($params);
		if (empty($result))
			message($lang->t('Bad request'));

		$topic_id = $result[0]['topic_id'];
		unset ($result, $query, $params);

		// Get the subject and forum ID
		$query = $db->select(array('subject' => 't.subject', 'forum_id' => 't.forum_id'), 'topics AS t');
		$query->where = 't.id = :topic_id';

		$params = array(':topic_id' => $topic_id);

		$result = $query->run($params);
		if (empty($result))
			message($lang->t('Bad request'));

		$cur_post = $result[0];
		unset ($result, $query, $params);

		// Should we use the internal report handling?
		if ($pun_config['o_report_method'] == '0' || $pun_config['o_report_method'] == '2')
		{
			$query = $db->insert(array('post_id' => ':post_id', 'topic_id' => ':topic_id', 'forum_id' => ':forum_id', 'reported_by' => ':user_id', 'created' => ':now', 'message' => ':reason'), 'reports');
			$params = array(':post_id' => $post_id, ':topic_id' => $topic_id, ':forum_id' => $cur_post['forum_id'], ':user_id' => $pun_user['id'], ':now' => time(), ':reason' => $reason);

			$query->run($params);
			unset ($query, $params);
		}

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

				$mail_subject = str_replace('<forum_id>', $cur_post['forum_id'], $mail_subject);
				$mail_subject = str_replace('<topic_subject>', $cur_post['subject'], $mail_subject);
				$mail_message = str_replace('<username>', $pun_user['username'], $mail_message);
				$mail_message = str_replace('<post_url>', get_base_url().'/viewtopic.php?pid='.$post_id.'#p'.$post_id, $mail_message);
				$mail_message = str_replace('<reason>', $reason, $mail_message);
				$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message);

				require PUN_ROOT.'include/email.php';

				pun_mail($pun_config['o_mailing_list'], $mail_subject, $mail_message);
			}
		}

		$query = $db->update(array('last_report_sent' => ':now'), 'users');
		$query->where = 'id = :user_id';

		$params = array(':now' => time(), ':user_id' => $pun_user['id']);

		$query->run($params);
		unset ($query, $params);

		$cache->delete('num_reports');

		redirect('viewtopic.php?pid='.$post_id.'#p'.$post_id, $lang->t('Report redirect'));
	}

	// Fetch some info about the post, the topic and the forum
	$query = $db->select(array('fid' => 'f.id AS fid', 'forum_name' => 'f.forum_name', 'tid' => 't.id AS tid', 'subject' => 't.subject'), 'posts AS p');

	$query->innerJoin('t', 'topics AS t', 't.id = p.topic_id');

	$query->innerJoin('f', 'forums AS f', 'f.id = t.forum_id');

	$query->leftJoin('fp', 'forum_perms AS fp', 'fp.forum_id = f.id AND fp.group_id = :group_id');

	$query->where = '(fp.read_forum IS NULL OR fp.read_forum=1) AND p.id = :post_id';

	$params = array(':group_id' => $pun_user['g_id'], ':post_id' => $post_id);

	$result = $query->run($params);
	if (empty($result))
		message($lang->t('Bad request'));

	$cur_post = $result[0];
	unset ($result, $query, $params);

	if ($pun_config['o_censoring'] == '1')
		$cur_post['subject'] = censor_words($cur_post['subject']);

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Report post'));
	$required_fields = array('req_reason' => $lang->t('Reason'));
	$focus_element = array('report', 'req_reason');
	define('PUN_ACTIVE_PAGE', 'index');
	require PUN_ROOT.'header.php';

?>
<div class="linkst">
	<div class="inbox">
		<ul class="crumbs">
			<li><a href="index.php"><?php echo $lang->t('Index') ?></a></li>
			<li><span>»&#160;</span><a href="viewforum.php?id=<?php echo $cur_post['fid'] ?>"><?php echo pun_htmlspecialchars($cur_post['forum_name']) ?></a></li>
			<li><span>»&#160;</span><a href="viewtopic.php?pid=<?php echo $post_id ?>#p<?php echo $post_id ?>"><?php echo pun_htmlspecialchars($cur_post['subject']) ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang->t('Report post') ?></strong></li>
		</ul>
	</div>
</div>

<div id="reportform" class="blockform">
	<h2><span><?php echo $lang->t('Report post') ?></span></h2>
	<div class="box">
		<form id="report" method="post" action="misc.php?report=<?php echo $post_id ?>" onsubmit="this.submit.disabled=true;if(process_form(this)){return true;}else{this.submit.disabled=false;return false;}">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang->t('Reason desc') ?></legend>
					<div class="infldset txtarea">
						<input type="hidden" name="form_sent" value="1" />
						<label class="required"><strong><?php echo $lang->t('Reason') ?> <span><?php echo $lang->t('Required') ?></span></strong><br /><textarea name="req_reason" rows="5" cols="60"></textarea><br /></label>
					</div>
				</fieldset>
			</div>
			<p class="buttons"><input type="submit" name="submit" value="<?php echo $lang->t('Submit') ?>" accesskey="s" /> <a href="javascript:history.go(-1)"><?php echo $lang->t('Go back') ?></a></p>
		</form>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


else if ($action == 'subscribe')
{
	if ($pun_user['is_guest'])
		message($lang->t('No permission'));

	$topic_id = isset($_GET['tid']) ? intval($_GET['tid']) : 0;
	$forum_id = isset($_GET['fid']) ? intval($_GET['fid']) : 0;
	if ($topic_id < 1 && $forum_id < 1)
		message($lang->t('Bad request'));

	if ($topic_id)
	{
		if ($pun_config['o_topic_subscriptions'] != '1')
			message($lang->t('No permission'));

		// Make sure the user can view the topic
		$query = $db->select(array('one' => '1'), 'topics AS t');

		$query->leftJoin('fp', 'forum_perms AS fp', 'fp.forum_id = t.forum_id AND fp.group_id = :group_id');

		$query->where = '(fp.read_forum IS NULL OR fp.read_forum = 1) AND t.id = :topic_id AND t.moved_to IS NULL';

		$params = array(':group_id' => $pun_user['g_id'], ':topic_id' => $topic_id);

		$result = $query->run($params);
		if (empty($result))
			message($lang->t('Bad request'));

		unset ($result, $query, $params);

		// Make sure the user isn't already subscribed
		$query = $db->select(array('one' => '1'), 'topic_subscriptions AS ts');
		$query->where = 'ts.user_id = :user_id AND ts.topic_id = :topic_id';

		$params = array(':user_id' => $pun_user['id'], ':topic_id' => $topic_id);

		$result = $query->run($params);
		if (!empty($result))
			message($lang->t('Already subscribed topic'));

		unset ($result, $query, $params);

		$query = $db->insert(array('user_id' => ':user_id', 'topic_id' => ':topic_id'), 'topic_subscriptions');
		$params = array(':user_id' => $pun_user['id'], ':topic_id' => $topic_id);

		$query->run($params);
		unset ($query, $params);

		redirect('viewtopic.php?id='.$topic_id, $lang->t('Subscribe redirect'));
	}

	if ($forum_id)
	{
		if ($pun_config['o_forum_subscriptions'] != '1')
			message($lang->t('No permission'));

		// Make sure the user can view the forum
		$query = $db->select(array('one' => '1'), 'forums AS f');

		$query->leftJoin('fp', 'forum_perms AS fp', 'fp.forum_id = f.id AND fp.group_id = :group_id');

		$query->where = '(fp.read_forum IS NULL OR fp.read_forum = 1) AND f.id = :forum_id';

		$params = array(':group_id' => $pun_user['g_id'], ':forum_id' => $forum_id);

		$result = $query->run($params);
		if (empty($result))
			message($lang->t('Bad request'));

		unset ($result, $query, $params);

		// Make sure the user isn't already subscribed
		$query = $db->select(array('one' => '1'), 'forum_subscriptions AS fs');
		$query->where = 'fs.user_id = :user_id AND fs.forum_id = :forum_id';

		$params = array(':user_id' => $pun_user['id'], ':forum_id' => $forum_id);

		$result = $query->run($params);
		if (!empty($result))
			message($lang->t('Already subscribed forum'));

		unset ($result, $query, $params);

		$query = $db->insert(array('user_id' => ':user_id', 'forum_id' => ':forum_id'), 'forum_subscriptions');
		$params = array(':user_id' => $pun_user['id'], ':forum_id' => $forum_id);

		$query->run($params);
		unset ($query, $params);

		redirect('viewforum.php?id='.$forum_id, $lang->t('Subscribe redirect'));
	}
}


else if ($action == 'unsubscribe')
{
	if ($pun_user['is_guest'])
		message($lang->t('No permission'));

	$topic_id = isset($_GET['tid']) ? intval($_GET['tid']) : 0;
	$forum_id = isset($_GET['fid']) ? intval($_GET['fid']) : 0;
	if ($topic_id < 1 && $forum_id < 1)
		message($lang->t('Bad request'));

	if ($topic_id)
	{
		if ($pun_config['o_topic_subscriptions'] != '1')
			message($lang->t('No permission'));

		// Make sure the user is already subscribed
		$query = $db->select(array('one' => '1'), 'topic_subscriptions AS ts');
		$query->where = 'ts.user_id = :user_id AND ts.topic_id = :topic_id';

		$params = array(':user_id' => $pun_user['id'], ':topic_id' => $topic_id);

		$result = $query->run($params);
		if (empty($result))
			message($lang->t('Not subscribed topic'));

		unset ($result, $query, $params);

		$query = $db->delete('topic_subscriptions');
		$query->where = 'user_id = :user_id AND topic_id = :topic_id';

		$params = array(':user_id' => $pun_user['id'], ':topic_id' => $topic_id);

		$query->run($params);
		unset ($query, $params);

		redirect('viewtopic.php?id='.$topic_id, $lang->t('Unsubscribe redirect'));
	}

	if ($forum_id)
	{
		if ($pun_config['o_forum_subscriptions'] != '1')
			message($lang->t('No permission'));

		$query = $db->select(array('one' => '1'), 'forum_subscriptions AS fs');
		$query->where = 'fs.user_id = :user_id AND fs.forum_id = :forum_id';

		$params = array(':user_id' => $pun_user['id'], ':forum_id' => $forum_id);

		$result = $query->run($params);
		if (empty($result))
			message($lang->t('Not subscribed forum'));

		unset ($result, $query, $params);

		$query = $db->delete('forum_subscriptions');
		$query->where = 'user_id = :user_id AND forum_id = :forum_id';

		$params = array(':user_id' => $pun_user['id'], ':forum_id' => $forum_id);

		$query->run($params);
		unset ($query, $params);

		redirect('viewforum.php?id='.$forum_id, $lang->t('Unsubscribe redirect'));
	}
}


else
	message($lang->t('Bad request'));
