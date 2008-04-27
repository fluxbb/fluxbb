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


$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id < 1)
	message($lang_common['Bad request']);

// Fetch some info about the post, the topic and the forum
$result = $db->query('SELECT f.id AS fid, f.forum_name, f.moderators, f.redirect_url, fp.post_replies, fp.post_topics, t.id AS tid, t.subject, t.posted, t.closed, p.poster, p.poster_id, p.message, p.hide_smilies FROM '.$db->prefix.'posts AS p INNER JOIN '.$db->prefix.'topics AS t ON t.id=p.topic_id INNER JOIN '.$db->prefix.'forums AS f ON f.id=t.forum_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND p.id='.$id) or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
if (!$db->num_rows($result))
	message($lang_common['Bad request']);

$cur_post = $db->fetch_assoc($result);

// Sort out who the moderators are and if we are currently a moderator (or an admin)
$mods_array = ($cur_post['moderators'] != '') ? unserialize($cur_post['moderators']) : array();
$is_admmod = ($pun_user['g_id'] == PUN_ADMIN || ($pun_user['g_id'] == PUN_MOD && array_key_exists($pun_user['username'], $mods_array))) ? true : false;

// Determine whether this post is the "topic post" or not
$result = $db->query('SELECT id FROM '.$db->prefix.'posts WHERE topic_id='.$cur_post['tid'].' ORDER BY posted LIMIT 1') or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
$topic_post_id = $db->result($result);

$can_edit_subject = ($id == $topic_post_id && (($pun_user['g_edit_subjects_interval'] == '0' || (time() - $cur_post['posted']) < $pun_user['g_edit_subjects_interval']) || $is_admmod)) ? true : false;

// Do we have permission to edit this post?
if (($pun_user['g_edit_posts'] == '0' ||
	$cur_post['poster_id'] != $pun_user['id'] ||
	$cur_post['closed'] == '1') &&
	!$is_admmod)
	message($lang_common['No permission']);

// Load the post.php/edit.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/post.php';

// Start with a clean slate
$errors = array();


if (isset($_POST['form_sent']))
{
	if ($is_admmod)
		confirm_referrer('edit.php');

	// If it is a topic it must contain a subject
	if ($can_edit_subject)
	{
		$subject = pun_trim($_POST['req_subject']);

		if ($subject == '')
			$errors[] = $lang_post['No subject'];
		else if (pun_strlen($subject) > 70)
			$errors[] = $lang_post['Too long subject'];
		else if ($pun_config['p_subject_all_caps'] == '0' && strtoupper($subject) == $subject && $pun_user['g_id'] > PUN_MOD)
			$subject = ucwords(strtolower($subject));
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


	$hide_smilies = isset($_POST['hide_smilies']) ? intval($_POST['hide_smilies']) : 0;
	if ($hide_smilies != '1') $hide_smilies = '0';

	// Did everything go according to plan?
	if (empty($errors) && !isset($_POST['preview']))
	{
		$edited_sql = (!isset($_POST['silent']) || !$is_admmod) ? $edited_sql = ', edited='.time().', edited_by=\''.$db->escape($pun_user['username']).'\'' : '';

		require PUN_ROOT.'include/search_idx.php';

		if ($can_edit_subject)
		{
			// Update the topic and any redirect topics
			$db->query('UPDATE '.$db->prefix.'topics SET subject=\''.$db->escape($subject).'\' WHERE id='.$cur_post['tid'].' OR moved_to='.$cur_post['tid']) or error('Unable to update topic', __FILE__, __LINE__, $db->error());

			// We changed the subject, so we need to take that into account when we update the search words
			update_search_index('edit', $id, $message, $subject);
		}
		else
			update_search_index('edit', $id, $message);

		// Update the post
		$db->query('UPDATE '.$db->prefix.'posts SET message=\''.$db->escape($message).'\', hide_smilies=\''.$hide_smilies.'\''.$edited_sql.' WHERE id='.$id) or error('Unable to update post', __FILE__, __LINE__, $db->error());

		redirect('viewtopic.php?pid='.$id.'#p'.$id, $lang_post['Edit redirect']);
	}
}



$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / '.$lang_post['Edit post'];
$required_fields = array('req_subject' => $lang_common['Subject'], 'req_message' => $lang_common['Message']);
$focus_element = array('edit', 'req_message');
require PUN_ROOT.'header.php';

$cur_index = 1;

?>
<div class="linkst">
	<div class="inbox">
		<ul><li><a href="index.php"><?php echo $lang_common['Index'] ?></a></li><li>&nbsp;&raquo;&nbsp;<a href="viewforum.php?id=<?php echo $cur_post['fid'] ?>"><?php echo pun_htmlspecialchars($cur_post['forum_name']) ?></a></li><li>&nbsp;&raquo;&nbsp;<?php echo pun_htmlspecialchars($cur_post['subject']) ?></li></ul>
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

?>
<div class="blockform">
	<h2><span><?php echo $lang_post['Edit post'] ?></span></h2>
	<div class="box">
		<form id="edit" method="post" action="edit.php?id=<?php echo $id ?>&amp;action=edit" onsubmit="return process_form(this)">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_post['Edit post legend'] ?></legend>
					<input type="hidden" name="form_sent" value="1" />
					<div class="infldset txtarea">
<?php if ($can_edit_subject): ?>						<label><?php echo $lang_common['Subject'] ?><br />
						<input class="longinput" type="text" name="req_subject" size="80" maxlength="70" tabindex="<?php echo $cur_index++ ?>" value="<?php echo pun_htmlspecialchars(isset($_POST['req_subject']) ? $_POST['req_subject'] : $cur_post['subject']) ?>" /><br /></label>
<?php endif; ?>						<label><?php echo $lang_common['Message'] ?><br />
						<textarea name="req_message" rows="20" cols="95" tabindex="<?php echo $cur_index++ ?>"><?php echo pun_htmlspecialchars(isset($_POST['req_message']) ? $message : $cur_post['message']) ?></textarea><br /></label>
						<ul class="bblinks">
							<li><a href="help.php#bbcode" onclick="window.open(this.href); return false;"><?php echo $lang_common['BBCode'] ?></a>: <?php echo ($pun_config['p_message_bbcode'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></li>
							<li><a href="help.php#img" onclick="window.open(this.href); return false;"><?php echo $lang_common['img tag'] ?></a>: <?php echo ($pun_config['p_message_img_tag'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></li>
							<li><a href="help.php#smilies" onclick="window.open(this.href); return false;"><?php echo $lang_common['Smilies'] ?></a>: <?php echo ($pun_config['o_smilies'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></li>
						</ul>
					</div>
				</fieldset>
<?php

$checkboxes = array();
if ($pun_config['o_smilies'] == '1')
{
	if (isset($_POST['hide_smilies']) || $cur_post['hide_smilies'] == '1')
		$checkboxes[] = '<label><input type="checkbox" name="hide_smilies" value="1" checked="checked" tabindex="'.($cur_index++).'" />&nbsp;'.$lang_post['Hide smilies'];
	else
		$checkboxes[] = '<label><input type="checkbox" name="hide_smilies" value="1" tabindex="'.($cur_index++).'" />&nbsp;'.$lang_post['Hide smilies'];
}

if ($is_admmod)
{
	if ((isset($_POST['form_sent']) && isset($_POST['silent'])) || !isset($_POST['form_sent']))
		$checkboxes[] = '<label><input type="checkbox" name="silent" value="1" tabindex="'.($cur_index++).'" checked="checked" />&nbsp;'.$lang_post['Silent edit'];
	else
		$checkboxes[] = '<label><input type="checkbox" name="silent" value="1" tabindex="'.($cur_index++).'" />&nbsp;'.$lang_post['Silent edit'];
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
							<?php echo implode('</label>'."\n\t\t\t\t\t\t\t", $checkboxes).'</label>'."\n" ?>
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

require PUN_ROOT.'footer.php';
