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


if (isset($_GET['action']))
	define('PUN_QUIET_VISIT', 1);

if (!defined('PUN_ROOT'))
	define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';

($hook = get_hook('mi_start')) ? eval($hook) : null;

// Load the misc.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/misc.php';


$action = isset($_GET['action']) ? $_GET['action'] : null;
$errors = array();

// Show the forum rules?
if ($action == 'rules')
{
	if ($pun_config['o_rules'] == '0' || ($pun_user['is_guest'] && $pun_user['g_read_board'] == '0' && $pun_config['o_regs_allow'] == '0'))
		message($lang_common['Bad request']);

	// Setup breadcrumbs
	$pun_page['crumbs'] = array(
		array($pun_config['o_board_title'], pun_link($pun_url['index'])),
		$lang_common['Rules']
	);

	($hook = get_hook('mi_rules_pre_header_load')) ? eval($hook) : null;

	define('PUN_PAGE', 'rules');
	require PUN_ROOT.'header.php';

?>
<div id="pun-main" class="main">

	<h1><span><?php echo end($pun_page['crumbs']) ?></span></h1>

	<div class="main-head">
		<h2><span><?php echo $lang_common['Forum rules'] ?></span></h2>
	</div>

	<div class="main-content frm">
		<div class="userbox">
			<?php echo $pun_config['o_rules_message']."\n" ?>
		</div>
	</div>

</div>
<?php

	require PUN_ROOT.'footer.php';
}


// Mark all topics/posts as read?
else if ($action == 'markread')
{
	if ($pun_user['is_guest'])
		message($lang_common['No permission']);

	// We validate the CSRF token. If it's set in POST and we're at this point, the token is valid.
	// If it's in GET, we need to make sure it's valid.
	if (!isset($_POST['csrf_token']) && (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== generate_form_token('markread'.$pun_user['id'])))
		csrf_confirm_form();

	($hook = get_hook('mi_markread_selected')) ? eval($hook) : null;

	$query = array(
		'UPDATE'	=> 'users',
		'SET'		=> 'last_visit='.$pun_user['logged'],
		'WHERE'		=> 'id='.$pun_user['id']
	);

	($hook = get_hook('mi_qr_update_last_visit')) ? eval($hook) : null;
	$pun_db->query_build($query) or error(__FILE__, __LINE__);

	// Reset tracked topics
	set_tracked_topics(null);

	redirect($pun_user['prev_url'], $lang_misc['Mark read redirect']);
}


// Mark the topics/posts in a forum as read?
else if ($action == 'markforumread')
{
	if ($pun_user['is_guest'])
		message($lang_common['No permission']);

	// We validate the CSRF token. If it's set in POST and we're at this point, the token is valid.
	// If it's in GET, we need to make sure it's valid.
	if (!isset($_POST['csrf_token']) && (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== generate_form_token('markforumread'.intval($_GET['fid']).$pun_user['id'])))
		csrf_confirm_form();

	($hook = get_hook('mi_markforumread_selected')) ? eval($hook) : null;

	$tracked_topics = get_tracked_topics();
	$tracked_topics['forums'][intval($_GET['fid'])] = time();
	set_tracked_topics($tracked_topics);

	redirect($pun_user['prev_url'], $lang_misc['Mark forum read redirect']);
}


// Send form e-mail?
else if (isset($_GET['email']))
{
	if ($pun_user['is_guest'] || $pun_user['g_send_email'] == '0')
		message($lang_common['No permission']);

	($hook = get_hook('mi_email_selected')) ? eval($hook) : null;

	// User pressed the cancel button
	if (isset($_POST['cancel']))
		redirect(pun_htmlencode($_POST['redirect_url']), $lang_common['Cancel redirect']);

	$recipient_id = intval($_GET['email']);
	if ($recipient_id < 2)
		message($lang_common['Bad request']);

	$query = array(
		'SELECT'	=> 'u.username, u.email, u.email_setting',
		'FROM'		=> 'users AS u',
		'WHERE'		=> 'u.id='.$recipient_id
	);

	($hook = get_hook('mi_qr_get_form_email_data')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	if (!$pun_db->num_rows($result))
		message($lang_common['Bad request']);

	list($recipient, $recipient_email, $email_setting) = $pun_db->fetch_row($result);

	if ($email_setting == 2 && !$pun_user['is_admmod'])
		message($lang_misc['Form e-mail disabled']);


	if (isset($_POST['form_sent']))
	{
		($hook = get_hook('mi_email_form_submitted')) ? eval($hook) : null;

		// Clean up message and subject from POST
		$subject = trim($_POST['req_subject']);
		$message = trim($_POST['req_message']);

		if ($subject == '')
			$errors[] = $lang_misc['No e-mail subject'];
		if ($message == '')
			$errors[] = $lang_misc['No e-mail message'];
		else if (strlen($message) > PUN_MAX_POSTSIZE)
			$errors[] = $lang_misc['Too long e-mail message'];
		if ($pun_user['last_email_sent'] != '' && (time() - $pun_user['last_email_sent']) < $pun_user['g_email_flood'] && (time() - $pun_user['last_email_sent']) >= 0)
			$errors[] = sprintf($lang_misc['Email flood'], $pun_user['g_email_flood']);

		// Did everything go according to plan?
		if (empty($errors))
		{
			// Load the "form e-mail" template
			$mail_tpl = trim(file_get_contents(PUN_ROOT.'lang/'.$pun_user['language'].'/mail_templates/form_email.tpl'));

			// The first row contains the subject
			$first_crlf = strpos($mail_tpl, "\n");
			$mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
			$mail_message = trim(substr($mail_tpl, $first_crlf));

			$mail_subject = str_replace('<mail_subject>', $subject, $mail_subject);
			$mail_message = str_replace('<sender>', $pun_user['username'], $mail_message);
			$mail_message = str_replace('<board_title>', $pun_config['o_board_title'], $mail_message);
			$mail_message = str_replace('<mail_message>', $message, $mail_message);
			$mail_message = str_replace('<board_mailer>', sprintf($lang_common['Forum mailer'], $pun_config['o_board_title']), $mail_message);

			require_once PUN_ROOT.'include/email.php';

			pun_mail($recipient_email, $mail_subject, $mail_message, '"'.str_replace('"', '', $pun_user['username']).'" <'.$pun_user['email'].'>');

			// Set the user's last_email_sent time
			$query = array(
				'UPDATE'	=> 'users',
				'SET'		=> 'last_email_sent='.time(),
				'WHERE'		=> 'id='.$pun_user['id'],
				'PARAMS'	=> array(
					'LOW_PRIORITY'	=> 1	// MySQL only
				)
			);

			($hook = get_hook('mi_qr_update_last_email_sent')) ? eval($hook) : null;
			$pun_db->query_build($query) or error(__FILE__, __LINE__);

			redirect(pun_htmlencode($_POST['redirect_url']), $lang_misc['E-mail sent redirect']);
		}
	}

	// Setup form
	$pun_page['set_count'] = $pun_page['fld_count'] = 0;
	$pun_page['form_action'] = pun_link($pun_url['email'], $recipient_id);

	$pun_page['hidden_fields'] = array(
		'<input type="hidden" name="form_sent" value="1" />',
		'<input type="hidden" name="redirect_url" value="'.pun_htmlencode($pun_user['prev_url']).'" />'
	);
	if ($pun_user['is_admmod'])
		$pun_page['hidden_fields'][] = '<input type="hidden" name="csrf_token" value="'.generate_form_token($pun_page['form_action']).'" />';

	// Setup main heading
	$pun_page['main_head'] = sprintf($lang_misc['Send forum e-mail'], pun_htmlencode($recipient));

	// Setup breadcrumbs
	$pun_page['crumbs'] = array(
		array($pun_config['o_board_title'], pun_link($pun_url['index'])),
		$lang_common['Send forum e-mail']
	);

	($hook = get_hook('mi_email_pre_header_load')) ? eval($hook) : null;

	define('PUN_PAGE', 'formemail');
	require PUN_ROOT.'header.php';

?>
<div id="pun-main" class="main">

	<h1><span><?php echo end($pun_page['crumbs']) ?></span></h1>

	<div class="main-head">
		<h2><span><?php echo $pun_page['main_head'] ?></span></h2>
	</div>

	<div class="main-content frm">
		<div class="frm-info">
			<p class="important"><?php echo $lang_misc['E-mail disclosure note'] ?></p>
		</div>
<?php

	// If there were any errors, show them
	if (!empty($errors))
	{
		$pun_page['errors'] = array();
		while (list(, $cur_error) = each($errors))
			$pun_page['errors'][] = '<li class="warn"><span>'.$cur_error.'</span></li>';

		($hook = get_hook('mi_pre_email_errors')) ? eval($hook) : null;

?>
		<div class="frm-error">
			<h3 class="warn"><?php echo $lang_misc['Form e-mail errors'] ?></h3>
			<ul>
				<?php echo implode("\n\t\t\t\t\t", $pun_page['errors'])."\n" ?>
			</ul>
		</div>
<?php

	}

?>
		<div id="req-msg" class="frm-warn">
			<p class="important"><?php printf($lang_common['Required warn'], '<em class="req-text">'.$lang_common['Required'].'</em>') ?></p>
		</div>
		<form id="afocus" class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $pun_page['form_action'] ?>">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $pun_page['hidden_fields'])."\n" ?>
			</div>
<?php ($hook = get_hook('mi_email_pre_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set<?php echo ++$pun_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_misc['Write e-mail'] ?></strong></legend>
				<div class="frm-fld text required longtext">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_misc['E-mail subject'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $pun_page['fld_count'] ?>" name="req_subject" value="<?php echo(isset($_POST['req_subject']) ? pun_htmlencode($_POST['req_subject']) : '') ?>" size="75" maxlength="70" /></span>
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
					</label>
				</div>
<?php ($hook = get_hook('mi_email_pre_message_contents')) ? eval($hook) : null; ?>
				<div class="frm-fld text textarea required">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_misc['E-mail message'] ?></span><br />
						<span class="fld-input"><textarea id="fld<?php echo $pun_page['fld_count'] ?>" name="req_message" rows="10" cols="95"><?php echo(isset($_POST['req_message']) ? pun_htmlencode($_POST['req_message']) : '') ?></textarea></span>
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
					</label>
				</div>
			</fieldset>
<?php ($hook = get_hook('mi_email_post_fieldset')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="submit" value="<?php echo $lang_common['Submit'] ?>" accesskey="s" title="<?php echo $lang_common['Submit title'] ?>" /></span>
				<span class="cancel"><input type="submit" name="cancel" value="<?php echo $lang_common['Cancel'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

	require PUN_ROOT.'footer.php';
}


// Report a post?
else if (isset($_GET['report']))
{
	if ($pun_user['is_guest'])
		message($lang_common['No permission']);

	($hook = get_hook('mi_report_selected')) ? eval($hook) : null;

	$post_id = intval($_GET['report']);
	if ($post_id < 1)
		message($lang_common['Bad request']);

	// User pressed the cancel button
	if (isset($_POST['cancel']))
		redirect(pun_link($pun_url['post'], $post_id), $lang_common['Cancel redirect']);


	if (isset($_POST['form_sent']))
	{
		($hook = get_hook('mi_report_form_submitted')) ? eval($hook) : null;

		// Clean up reason from POST
		$reason = pun_linebreaks(trim($_POST['req_reason']));
		if ($reason == '')
			message($lang_misc['No reason']);

		// Get some info about the topic we're reporting
		$query = array(
			'SELECT'	=> 't.id, t.subject, t.forum_id',
			'FROM'		=> 'posts AS p',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'topics AS t',
					'ON'			=> 't.id=p.topic_id'
				)
			),
			'WHERE'		=> 'p.id='.$post_id
		);

		($hook = get_hook('mi_qr_get_report_topic_data')) ? eval($hook) : null;
		$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
		if (!$pun_db->num_rows($result))
			message($lang_common['Bad request']);

		list($topic_id, $subject, $forum_id) = $pun_db->fetch_row($result);

		($hook = get_hook('mi_report_pre_reports_sent')) ? eval($hook) : null;

		// Should we use the internal report handling?
		if ($pun_config['o_report_method'] == 0 || $pun_config['o_report_method'] == 2)
		{
			$query = array(
				'INSERT'	=> 'post_id, topic_id, forum_id, reported_by, created, message',
				'INTO'		=> 'reports',
				'VALUES'	=> $post_id.', '.$topic_id.', '.$forum_id.', '.$pun_user['id'].', '.time().', \''.$pun_db->escape($reason).'\''
			);

			($hook = get_hook('mi_add_report')) ? eval($hook) : null;
			$pun_db->query_build($query) or error(__FILE__, __LINE__);
		}

		// Should we e-mail the report?
		if ($pun_config['o_report_method'] == 1 || $pun_config['o_report_method'] == 2)
		{
			// We send it to the complete mailing-list in one swoop
			if ($pun_config['o_mailing_list'] != '')
			{
				$mail_subject = 'Report('.$forum_id.') - \''.$subject.'\'';
				$mail_message = 'User \''.$pun_user['username'].'\' has reported the following message:'."\n".pun_link($pun_url['post'], $post_id)."\n\n".'Reason:'."\n".$reason;

				require PUN_ROOT.'include/email.php';

				pun_mail($pun_config['o_mailing_list'], $mail_subject, $mail_message);
			}
		}

		redirect(pun_link($pun_url['post'], $post_id), $lang_misc['Report redirect']);
	}

	// Setup form
	$pun_page['set_count'] = $pun_page['fld_count'] = 0;
	$pun_page['form_action'] = pun_link($pun_url['report'], $post_id);

	$pun_page['hidden_fields'][] = '<input type="hidden" name="form_sent" value="1" />';
	if ($pun_user['is_admmod'])
		$pun_page['hidden_fields'][] = '<input type="hidden" name="csrf_token" value="'.generate_form_token($pun_page['form_action']).'" />';

	// Setup breadcrumbs
	$pun_page['crumbs'] = array(
		array($pun_config['o_board_title'], pun_link($pun_url['index'])),
		$lang_misc['Report post']
	);

	($hook = get_hook('mi_report_pre_header_load')) ? eval($hook) : null;

	define('PUN_PAGE', 'report');
	require PUN_ROOT.'header.php';

?>
<div id="pun-main" class="main">

	<h1><span><?php echo end($pun_page['crumbs']) ?></span></h1>

	<div class="main-head">
		<h2><span><?php echo $lang_misc['Send report'] ?></span></h2>
	</div>

	<div class="main-content frm">
		<div id="req-msg" class="frm-warn">
			<p class="important"><?php printf($lang_common['Required warn'], '<em class="req-text">'.$lang_common['Required'].'</em>') ?></p>
		</div>
		<form id="afocus" class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $pun_page['form_action'] ?>">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $pun_page['hidden_fields'])."\n" ?>
			</div>
<?php ($hook = get_hook('mi_report_pre_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set<?php echo ++$pun_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_common['Required information'] ?></strong></legend>
				<div class="frm-fld text textarea required">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_misc['Reason'] ?></span><br />
						<span class="fld-input"><textarea id="fld<?php echo $pun_page['fld_count'] ?>" name="req_reason" rows="5" cols="60"></textarea></span><br />
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
						<span class="fld-help"><?php echo $lang_misc['Reason help'] ?></span>
					</label>
				</div>
			</fieldset>
<?php ($hook = get_hook('mi_report_post_fieldset')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="submit" value="<?php echo $lang_common['Submit'] ?>" accesskey="s" title="<?php echo $lang_common['Submit title'] ?>" /></span>
 				<span class="cancel"><input type="submit" name="cancel" value="<?php echo $lang_common['Cancel'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

	require PUN_ROOT.'footer.php';
}


// Subscribe to a topic?
else if (isset($_GET['subscribe']))
{
	if ($pun_user['is_guest'] || $pun_config['o_subscriptions'] != '1')
		message($lang_common['No permission']);

	// We validate the CSRF token. If it's set in POST and we're at this point, the token is valid.
	// If it's in GET, we need to make sure it's valid.
	if (!isset($_POST['csrf_token']) && (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== generate_form_token('subscribe'.intval($_GET['subscribe']).$pun_user['id'])))
		csrf_confirm_form();

	($hook = get_hook('mi_subscribe_selected')) ? eval($hook) : null;

	$topic_id = intval($_GET['subscribe']);
	if ($topic_id < 1)
		message($lang_common['Bad request']);

	// Make sure the user can view the topic
	$query = array(
		'SELECT'	=> 'subject',
		'FROM'		=> 'topics AS t',
		'JOINS'		=> array(
			array(
				'LEFT JOIN'		=> 'forum_perms AS fp',
				'ON'			=> '(fp.forum_id=t.forum_id AND fp.group_id='.$pun_user['g_id'].')'
			)
		),
		'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND t.id='.$topic_id.' AND t.moved_to IS NULL'
	);
	($hook = get_hook('mi_qr_subscribe_topic_exists')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	if (!$pun_db->num_rows($result))
		message($lang_common['Bad request']);

	$subject = $pun_db->result($result);

	$query = array(
		'SELECT'	=> '1',
		'FROM'		=> 'subscriptions AS s',
		'WHERE'		=> 'user_id='.$pun_user['id'].' AND topic_id='.$topic_id
	);

	($hook = get_hook('mi_qr_check_subscribed')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	if ($pun_db->num_rows($result))
		message($lang_misc['Already subscribed']);

	$query = array(
		'INSERT'	=> 'user_id, topic_id',
		'INTO'		=> 'subscriptions',
		'VALUES'	=> $pun_user['id'].' ,'.$topic_id
	);

	($hook = get_hook('mi_add_subscription')) ? eval($hook) : null;
	$pun_db->query_build($query) or error(__FILE__, __LINE__);

	redirect(pun_link($pun_url['topic'], array($topic_id, sef_friendly($subject))), $lang_misc['Subscribe redirect']);
}


// Unsubscribe from a topic?
else if (isset($_GET['unsubscribe']))
{
	if ($pun_user['is_guest'] || $pun_config['o_subscriptions'] != '1')
		message($lang_common['No permission']);

	// We validate the CSRF token. If it's set in POST and we're at this point, the token is valid.
	// If it's in GET, we need to make sure it's valid.
	if (!isset($_POST['csrf_token']) && (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== generate_form_token('unsubscribe'.intval($_GET['unsubscribe']).$pun_user['id'])))
		csrf_confirm_form();

	($hook = get_hook('mi_unsubscribe_selected')) ? eval($hook) : null;

	$topic_id = intval($_GET['unsubscribe']);
	if ($topic_id < 1)
		message($lang_common['Bad request']);

	$query = array(
		'SELECT'	=> 't.subject',
		'FROM'		=> 'topics AS t',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'subscriptions AS s',
				'ON'			=> 's.user_id='.$pun_user['id'].' AND s.topic_id=t.id'
			)
		),
		'WHERE'		=> 't.id='.$topic_id
	);

	($hook = get_hook('mi_qr_check_subscribed2')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	if (!$pun_db->num_rows($result))
		message($lang_misc['Not subscribed']);

	$subject = $pun_db->result($result);

	$query = array(
		'DELETE'	=> 'subscriptions',
		'WHERE'		=> 'user_id='.$pun_user['id'].' AND topic_id='.$topic_id
	);

	($hook = get_hook('mi_qr_delete_subscription')) ? eval($hook) : null;
	$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);

	redirect(pun_link($pun_url['topic'], array($topic_id, sef_friendly($subject))), $lang_misc['Unsubscribe redirect']);
}


($hook = get_hook('mi_new_action')) ? eval($hook) : null;

message($lang_common['Bad request']);
