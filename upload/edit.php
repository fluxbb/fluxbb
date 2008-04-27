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


if (!defined('PUN_ROOT'))
	define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';

($hook = get_hook('ed_start')) ? eval($hook) : null;

if ($pun_user['g_read_board'] == '0')
	message($lang_common['No view']);

// Load the post.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/post.php';


$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id < 1)
	message($lang_common['Bad request']);


// Fetch some info about the post, the topic and the forum
$query = array(
	'SELECT'	=> 'f.id AS fid, f.forum_name, f.moderators, f.redirect_url, fp.post_replies, fp.post_topics, t.id AS tid, t.subject, t.posted, t.first_post_id, t.closed, p.poster, p.poster_id, p.message, p.hide_smilies',
	'FROM'		=> 'posts AS p',
	'JOINS'		=> array(
		array(
			'INNER JOIN'	=> 'topics AS t',
			'ON'			=> 't.id=p.topic_id'
		),
		array(
			'INNER JOIN'	=> 'forums AS f',
			'ON'			=> 'f.id=t.forum_id'
		),
		array(
			'LEFT JOIN'		=> 'forum_perms AS fp',
			'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].')'
		)
	),
	'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND p.id='.$id
);

($hook = get_hook('ed_qr_get_post_info')) ? eval($hook) : null;
$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
if (!$pun_db->num_rows($result))
	message($lang_common['Bad request']);

$cur_post = $pun_db->fetch_assoc($result);

// Sort out who the moderators are and if we are currently a moderator (or an admin)
$mods_array = ($cur_post['moderators'] != '') ? unserialize($cur_post['moderators']) : array();
$pun_page['is_admmod'] = ($pun_user['g_id'] == PUN_ADMIN || ($pun_user['g_moderator'] == '1' && array_key_exists($pun_user['username'], $mods_array))) ? true : false;

// Do we have permission to edit this post?
if (($pun_user['g_edit_posts'] == '0' ||
	$cur_post['poster_id'] != $pun_user['id'] ||
	$cur_post['closed'] == '1') &&
	!$pun_page['is_admmod'])
	message($lang_common['No permission']);


// Start with a clean slate
$errors = array();

$can_edit_subject = ($id == $cur_post['first_post_id'] && (($pun_user['g_edit_subjects_interval'] == '0' || (time() - $cur_post['posted']) < $pun_user['g_edit_subjects_interval']) || $pun_page['is_admmod'])) ? true : false;

if (isset($_POST['form_sent']))
{
	($hook = get_hook('ed_form_submitted')) ? eval($hook) : null;

	// If it is a topic it must contain a subject
	if ($can_edit_subject)
	{
		$subject = trim($_POST['req_subject']);

		if ($subject == '')
			$errors[] = $lang_post['No subject'];
		else if (pun_strlen($subject) > 70)
			$errors[] = $lang_post['Too long subject'];
		else if ($pun_config['p_subject_all_caps'] == '0' && strtoupper($subject) == $subject && !$pun_page['is_admmod'])
			$subject = ucwords(strtolower($subject));
	}

	// Clean up message from POST
	$message = pun_linebreaks(trim($_POST['req_message']));

	if ($message == '')
		$errors[] = $lang_post['No message'];
	else if (strlen($message) > PUN_MAX_POSTSIZE)
		$errors[] = $lang_post['Too long message'];
	else if ($pun_config['p_message_all_caps'] == '0' && strtoupper($message) == $message && !$pun_page['is_admmod'])
		$message = ucwords(strtolower($message));

	// Validate BBCode syntax
	if ($pun_config['p_message_bbcode'] == '1' && strpos($message, '[') !== false && strpos($message, ']') !== false)
	{
		require PUN_ROOT.'include/parser.php';
		$message = preparse_bbcode($message, $errors);
	}

	$hide_smilies = isset($_POST['hide_smilies']) ? 1 : 0;

	($hook = get_hook('ed_end_validation')) ? eval($hook) : null;

	// Did everything go according to plan?
	if (empty($errors) && !isset($_POST['preview']))
	{
		($hook = get_hook('ed_pre_post_edited')) ? eval($hook) : null;

		if ($db_type != 'mysql' && $db_type != 'mysqli')
			require PUN_ROOT.'include/search_idx.php';

		if ($can_edit_subject)
		{
			// Update the topic and any redirect topics
			$query = array(
				'UPDATE'	=> 'topics',
				'SET'		=> 'subject=\''.$pun_db->escape($subject).'\'',
				'WHERE'		=> 'id='.$cur_post['tid'].' OR moved_to='.$cur_post['tid']
			);

			($hook = get_hook('ed_qr_update_subject')) ? eval($hook) : null;
			$pun_db->query_build($query) or error(__FILE__, __LINE__);

			// We changed the subject, so we need to take that into account when we update the search words
			if ($db_type != 'mysql' && $db_type != 'mysqli')
				update_search_index('edit', $id, $message, $subject);
		}
		else if ($db_type != 'mysql' && $db_type != 'mysqli')
			update_search_index('edit', $id, $message);

		// Update the post
		$query = array(
			'UPDATE'	=> 'posts',
			'SET'		=> 'message=\''.$pun_db->escape($message).'\', hide_smilies=\''.$hide_smilies.'\'',
			'WHERE'		=> 'id='.$id
		);

		if (!isset($_POST['silent']) || !$pun_page['is_admmod'])
			$query['SET'] .= ', edited='.time().', edited_by=\''.$pun_db->escape($pun_user['username']).'\'';

		($hook = get_hook('ed_qr_update_post')) ? eval($hook) : null;
		$pun_db->query_build($query) or error(__FILE__, __LINE__);

		redirect(pun_link($pun_url['post'], $id), $lang_post['Edit redirect']);
	}
}

// Setup error messages
if (!empty($errors))
{
	$pun_page['errors'] = array();

	while (list(, $cur_error) = each($errors))
		$pun_page['errors'][] = '<li><span>'.$cur_error.'</span></li>';
}

// Setup form
$pun_page['set_count'] = $pun_page['fld_count'] = 0;
$pun_page['form_action'] = pun_link($pun_url['edit'], $id);
$pun_page['form_attributes'] = array();

$pun_page['hidden_fields'][] = '<input type="hidden" name="form_sent" value="1" />';
if ($pun_user['is_admmod'])
	$pun_page['hidden_fields'][] = '<input type="hidden" name="csrf_token" value="'.generate_form_token($pun_page['form_action']).'" />';

// Setup help
$pun_page['main_head_options'] = array();
if ($pun_config['p_message_bbcode'] == '1')
	$pun_page['main_head_options'][] = '<a class="exthelp" href="'.pun_link($pun_url['help'], 'bbcode').'" title="'.sprintf($lang_common['Help page'], $lang_common['BBCode']).'">'.$lang_common['BBCode'].'</a>';
if ($pun_config['p_message_img_tag'] == '1')
	$pun_page['main_head_options'][] = '<a class="exthelp" href="'.pun_link($pun_url['help'], 'img').'" title="'.sprintf($lang_common['Help page'], $lang_common['Images']).'">'.$lang_common['Images'].'</a>';
if ($pun_config['o_smilies'] == '1')
	$pun_page['main_head_options'][] = '<a class="exthelp" href="'.pun_link($pun_url['help'], 'smilies').'" title="'.sprintf($lang_common['Help page'], $lang_common['Smilies']).'">'.$lang_common['Smilies'].'</a>';

// Setup main heading
$pun_page['main_head'] = sprintf($lang_post['Edit this'], (($id == $cur_post['first_post_id']) ? $lang_post['Topic'] : $lang_post['Reply']), $cur_post['poster']);

// Setup breadcrumbs
$pun_page['crumbs'] = array(
	array($pun_config['o_board_title'], pun_link($pun_url['index'])),
	array($cur_post['forum_name'], pun_link($pun_url['forum'], array($cur_post['fid'], sef_friendly($cur_post['forum_name'])))),
	array($cur_post['subject'], pun_link($pun_url['topic'], array($cur_post['tid'], sef_friendly($cur_post['subject'])))),
	$lang_post['Edit post']
);

($hook = get_hook('ed_pre_header_load')) ? eval($hook) : null;

define('PUN_PAGE', 'postedit');
require PUN_ROOT.'header.php';

?>
<div id="pun-main" class="main">

	<h1><span><?php echo end($pun_page['crumbs']) ?></span></h1>
<?php

// If preview selected and there are no errors
if (isset($_POST['preview']) && empty($pun_page['errors']))
{
	require_once PUN_ROOT.'include/parser.php';
	$pun_page['preview_message'] = parse_message(trim($_POST['req_message']), $hide_smilies);

?>
	<div class="main-head">
		<h2><span><?php echo $lang_post['Preview reply'] ?></span></h2>
	</div>

	<div id="post-preview" class="main-content topic">
		<div class="post firstpost">
			<div class="postmain">
				<div class="posthead">
					<h3><?php echo $lang_post['Preview info'] ?></h3>
				</div>
				<div class="postbody">
					<div class="user">
						<h4 class="user-ident"><strong class="username"><?php echo $cur_post['poster'] ?></strong></h4>
					</div>
					<div class="post-entry">
						<div class="entry-content">
						<?php echo $pun_page['preview_message']."\n" ?>
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
		<h2><span><?php echo $pun_page['main_head'] ?></span></h2>
<?php if (!empty($pun_page['main_head_options'])): ?>		<p class="main-options"><?php printf($lang_common['You may use'], implode(' ', $pun_page['main_head_options'])) ?></p>
<?php endif; ?>	</div>

	<div class="main-content frm">
<?php

// If there were any errors, show them
if (isset($pun_page['errors']))
{

?>
		<div class="frm-error">
			<h3 class="warn"><?php echo $lang_post['Post errors'] ?></h3>
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
		<form id="afocus" class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $pun_page['form_action'] ?>"<?php if (!empty($pun_page['form_attributes'])) echo ' '.implode(' ', $pun_page['form_attributes']) ?>>
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $pun_page['hidden_fields'])."\n" ?>
			</div>
<?php ($hook = get_hook('ed_pre_main_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set<?php echo ++$pun_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_post['Edit post legend'] ?></strong></legend>
<?php if ($can_edit_subject): ?>				<div class="frm-fld text longtext required">
					<label for="fld<?php echo ++ $pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_post['Topic subject'] ?></span><br />
						<span class="fld-input"><input id="fld<?php echo $pun_page['fld_count'] ?>" type="text" name="req_subject" size="80" maxlength="70" value="<?php echo pun_htmlencode(isset($_POST['req_subject']) ? $_POST['req_subject'] : $cur_post['subject']) ?>" /></span>
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
					</label>
				</div>
<?php endif; ($hook = get_hook('ed_pre_message_box')) ? eval($hook) : null; ?>				<div class="frm-fld text textarea required">
					<label for="fld<?php echo ++ $pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_post['Write message'] ?></span><br />
						<span class="fld-input"><textarea id="fld<?php echo $pun_page['fld_count'] ?>" name="req_message" rows="14" cols="95"><?php echo pun_htmlencode(isset($_POST['req_message']) ? $message : $cur_post['message']) ?></textarea></span>
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
					</label>
				</div>
			</fieldset>
<?php

$pun_page['checkboxes'] = array();
if ($pun_config['o_smilies'] == '1')
{
	if (isset($_POST['hide_smilies']) || $cur_post['hide_smilies'] == '1')
		$pun_page['checkboxes'][] = '<div class="radbox"><label for="fld'.(++$pun_page['fld_count']).'"><input type="checkbox" id="fld'.$pun_page['fld_count'].'" name="hide_smilies" value="1" checked="checked" /> '.$lang_post['Hide smilies'].'</label></div>';
	else
		$pun_page['checkboxes'][] = '<div class="radbox"><label for="fld'.(++$pun_page['fld_count']).'"><input type="checkbox" id="fld'.$pun_page['fld_count'].'" name="hide_smilies" value="1" /> '.$lang_post['Hide smilies'].'</label></div>';
}

if ($pun_page['is_admmod'])
{
	if ((isset($_POST['form_sent']) && isset($_POST['silent'])) || !isset($_POST['form_sent']))
		$pun_page['checkboxes'][] = '<div class="radbox"><label for="fld'.(++$pun_page['fld_count']).'"><input type="checkbox" id="fld'.$pun_page['fld_count'].'" name="silent" value="1" checked="checked" /> '.$lang_post['Silent edit'].'</label></div>';
	else
		$pun_page['checkboxes'][] = '<div class="radbox"><label for="fld'.(++$pun_page['fld_count']).'"><input type="checkbox" id="fld'.$pun_page['fld_count'].'" name="silent" value="1" /> '.$lang_post['Silent edit'].'</label></div>';
}

($hook = get_hook('ed_pre_checkbox_display')) ? eval($hook) : null;

if (!empty($pun_page['checkboxes']))
{

?>
			<fieldset class="frm-set set<?php echo ++$pun_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_post['Optional legend'] ?></strong></legend>
				<fieldset class="frm-group">
					<legend><span><?php echo $lang_post['Post settings'] ?></span></legend>
					<?php echo implode("\n\t\t\t\t\t\t", $pun_page['checkboxes'])."\n"; ?>
				</fieldset>
			</fieldset>

<?php

}

($hook = get_hook('ed_post_checkbox_display')) ? eval($hook) : null;

?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="submit" value="<?php echo $lang_common['Submit'] ?>" accesskey="s" title="<?php echo $lang_common['Submit title'] ?>" /></span>
				<span class="submit"><input type="submit" name="preview" value="<?php echo $lang_common['Preview'] ?>" accesskey="p" title="<?php echo $lang_common['Preview title'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

$forum_id = $cur_post['fid'];

($hook = get_hook('ed_end')) ? eval($hook) : null;

require PUN_ROOT.'footer.php';
