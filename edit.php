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


$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id < 1)
	message($lang_common['Bad request'], false, '404 Not Found');

// Fetch some info about the post, the topic and the forum
$result = $db->query('SELECT f.id AS fid, f.forum_name, f.moderators, f.redirect_url, fp.post_replies, fp.post_topics, t.id AS tid, t.subject, t.posted, t.first_post_id, t.sticky, t.closed, p.poster, p.poster_id, p.message, p.hide_smilies FROM '.$db->prefix.'posts AS p INNER JOIN '.$db->prefix.'topics AS t ON t.id=p.topic_id INNER JOIN '.$db->prefix.'forums AS f ON f.id=t.forum_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND p.id='.$id) or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
if (!$db->num_rows($result))
	message($lang_common['Bad request'], false, '404 Not Found');

$cur_post = $db->fetch_assoc($result);

// Sort out who the moderators are and if we are currently a moderator (or an admin)
$mods_array = ($cur_post['moderators'] != '') ? unserialize($cur_post['moderators']) : array();
$is_admmod = ($pun_user['g_id'] == PUN_ADMIN || ($pun_user['g_moderator'] == '1' && array_key_exists($pun_user['username'], $mods_array))) ? true : false;

$can_edit_subject = $id == $cur_post['first_post_id'];

if ($pun_config['o_censoring'] == '1')
{
	$cur_post['subject'] = censor_words($cur_post['subject']);
	$cur_post['message'] = censor_words($cur_post['message']);
}

// Do we have permission to edit this post?
if (($pun_user['g_edit_posts'] == '0' ||
	$cur_post['poster_id'] != $pun_user['id'] ||
	$cur_post['closed'] == '1') &&
	!$is_admmod)
	message($lang_common['No permission'], false, '403 Forbidden');

if ($is_admmod && $pun_user['g_id'] != PUN_ADMIN && in_array($cur_post['poster_id'], get_admin_ids()))
	message($lang_common['No permission'], false, '403 Forbidden');

// Load the post.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/post.php';

// Start with a clean slate
$errors = array();


if (isset($_POST['form_sent']))
{
	// Make sure they got here from the site
	confirm_referrer('edit.php');

	// If it's a topic it must contain a subject
	if ($can_edit_subject)
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
		else if ($pun_user['g_post_links'] != '1' && preg_match('%(?:h\s*t|f)\s*t\s*p\s*(?:s\s*)?:\s*/\s*/%', $subject))
			$errors[] = $lang_common['BBCode error tag url not allowed'];
	}

	// Clean up message from POST
	$message = pun_linebreaks(pun_trim($_POST['req_message']));

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
	$stick_topic = isset($_POST['stick_topic']) ? '1' : '0';
	if (!$is_admmod)
		$stick_topic = $cur_post['sticky'];

	// Replace four-byte characters (MySQL cannot handle them)
	$message = strip_bad_multibyte_chars($message);

	// Did everything go according to plan?
	if (empty($errors) && !isset($_POST['preview']))
	{
		$edited_sql = (!isset($_POST['silent']) || !$is_admmod) ? ', edited='.time().', edited_by=\''.$db->escape($pun_user['username']).'\'' : '';

		require PUN_ROOT.'include/search_idx.php';

		if ($can_edit_subject)
		{
			// Update the topic and any redirect topics
			$db->query('UPDATE '.$db->prefix.'topics SET subject=\''.$db->escape($subject).'\', sticky='.$stick_topic.' WHERE id='.$cur_post['tid'].' OR moved_to='.$cur_post['tid']) or error('Unable to update topic', __FILE__, __LINE__, $db->error());

			// We changed the subject, so we need to take that into account when we update the search words
			update_search_index('edit', $id, $message, $subject);
		}
		else
			update_search_index('edit', $id, $message);

		// Update the post
		$db->query('UPDATE '.$db->prefix.'posts SET message=\''.$db->escape($message).'\', hide_smilies='.$hide_smilies.$edited_sql.' WHERE id='.$id) or error('Unable to update post', __FILE__, __LINE__, $db->error());

		redirect('viewtopic.php?pid='.$id.'#p'.$id, $lang_post['Edit redirect']);
	}
}



$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_post['Edit post']);
$required_fields = array('req_subject' => $lang_common['Subject'], 'req_message' => $lang_common['Message']);
$focus_element = array('edit', 'req_message');
define('PUN_ACTIVE_PAGE', 'index');
require PUN_ROOT.'header.php';

$cur_index = 1;

?>
<div class="linkst">
	<div class="inbox">
		<ul class="crumbs">
			<li><a href="index.php"><?php echo $lang_common['Index'] ?></a></li>
			<li><span>»&#160;</span><a href="viewforum.php?id=<?php echo $cur_post['fid'] ?>"><?php echo pun_htmlspecialchars($cur_post['forum_name']) ?></a></li>
			<li><span>»&#160;</span><a href="viewtopic.php?id=<?php echo $cur_post['tid'] ?>"><?php echo pun_htmlspecialchars($cur_post['subject']) ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang_post['Edit post'] ?></strong></li>
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

?>
<div id="editform" class="blockform">
	<h2><span><?php echo $lang_post['Edit post'] ?></span></h2>
	<div class="box">
		<form id="edit" method="post" action="edit.php?id=<?php echo $id ?>&amp;action=edit" onsubmit="return process_form(this)">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_post['Edit post legend'] ?></legend>
					<input type="hidden" name="form_sent" value="1" />
					<div class="infldset txtarea">
<?php if ($can_edit_subject): ?>						<label class="required"><strong><?php echo $lang_common['Subject'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br />
						<input class="longinput" type="text" name="req_subject" size="80" maxlength="70" tabindex="<?php echo $cur_index++ ?>" value="<?php echo pun_htmlspecialchars(isset($_POST['req_subject']) ? $_POST['req_subject'] : $cur_post['subject']) ?>" /><br /></label>
<?php endif; ?>						<label class="required"><strong><?php echo $lang_common['Message'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br />
						<textarea name="req_message" rows="20" cols="95" tabindex="<?php echo $cur_index++ ?>"><?php echo pun_htmlspecialchars(isset($_POST['req_message']) ? $message : $cur_post['message']) ?></textarea><br /></label>
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
if ($can_edit_subject && $is_admmod)
{
	if (isset($_POST['stick_topic']) || !isset($_POST['form_sent']) && $cur_post['sticky'] == '1')
		$checkboxes[] = '<label><input type="checkbox" name="stick_topic" value="1" checked="checked" tabindex="'.($cur_index++).'" />'.$lang_common['Stick topic'].'<br /></label>';
	else
		$checkboxes[] = '<label><input type="checkbox" name="stick_topic" value="1" tabindex="'.($cur_index++).'" />'.$lang_common['Stick topic'].'<br /></label>';
}

if ($pun_config['o_smilies'] == '1')
{
	if (isset($_POST['hide_smilies']) || !isset($_POST['form_sent']) && $cur_post['hide_smilies'] == '1')
		$checkboxes[] = '<label><input type="checkbox" name="hide_smilies" value="1" checked="checked" tabindex="'.($cur_index++).'" />'.$lang_post['Hide smilies'].'<br /></label>';
	else
		$checkboxes[] = '<label><input type="checkbox" name="hide_smilies" value="1" tabindex="'.($cur_index++).'" />'.$lang_post['Hide smilies'].'<br /></label>';
}

if ($is_admmod)
{
	if (isset($_POST['silent']) || !isset($_POST['form_sent']))
		$checkboxes[] = '<label><input type="checkbox" name="silent" value="1" tabindex="'.($cur_index++).'" checked="checked" />'.$lang_post['Silent edit'].'<br /></label>';
	else
		$checkboxes[] = '<label><input type="checkbox" name="silent" value="1" tabindex="'.($cur_index++).'" />'.$lang_post['Silent edit'].'<br /></label>';
}

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

require PUN_ROOT.'footer.php';
