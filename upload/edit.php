<?php
/***********************************************************************

  Copyright (C) 2008  FluxBB.org

  Based on code copyright (C) 2002-2008  PunBB.org

  This file is part of FluxBB.

  FluxBB is free software; you can redistribute it and/or modify it
  under the terms of the GNU General Public License as published
  by the Free Software Foundation; either version 2 of the License,
  or (at your option) any later version.

  FluxBB is distributed in the hope that it will be useful, but
  WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston,
  MA  02111-1307  USA

************************************************************************/


if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', './');
require FORUM_ROOT.'include/common.php';

($hook = get_hook('ed_start')) ? eval($hook) : null;

if ($forum_user['g_read_board'] == '0')
	message($lang_common['No view']);

// Load the post.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/post.php';


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
			'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
		)
	),
	'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND p.id='.$id
);

($hook = get_hook('ed_qr_get_post_info')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
if (!$forum_db->num_rows($result))
	message($lang_common['Bad request']);

$cur_post = $forum_db->fetch_assoc($result);

// Sort out who the moderators are and if we are currently a moderator (or an admin)
$mods_array = ($cur_post['moderators'] != '') ? unserialize($cur_post['moderators']) : array();
$forum_page['is_admmod'] = ($forum_user['g_id'] == FORUM_ADMIN || ($forum_user['g_moderator'] == '1' && array_key_exists($forum_user['username'], $mods_array))) ? true : false;

// Do we have permission to edit this post?
if (($forum_user['g_edit_posts'] == '0' ||
	$cur_post['poster_id'] != $forum_user['id'] ||
	$cur_post['closed'] == '1') &&
	!$forum_page['is_admmod'])
	message($lang_common['No permission']);


// Start with a clean slate
$errors = array();

$can_edit_subject = ($id == $cur_post['first_post_id'] && (($forum_user['g_edit_subjects_interval'] == '0' || (time() - $cur_post['posted']) < $forum_user['g_edit_subjects_interval']) || $forum_page['is_admmod'])) ? true : false;

if (isset($_POST['form_sent']))
{
	($hook = get_hook('ed_form_submitted')) ? eval($hook) : null;

	// If it is a topic it must contain a subject
	if ($can_edit_subject)
	{
		$subject = trim($_POST['req_subject']);

		if ($subject == '')
			$errors[] = $lang_post['No subject'];
		else if (forum_strlen($subject) > 70)
			$errors[] = $lang_post['Too long subject'];
		else if ($forum_config['p_subject_all_caps'] == '0' && strtoupper($subject) == $subject && !$forum_page['is_admmod'])
			$subject = ucwords(strtolower($subject));
	}

	// Clean up message from POST
	$message = forum_linebreaks(trim($_POST['req_message']));

	if ($message == '')
		$errors[] = $lang_post['No message'];
	else if (strlen($message) > FORUM_MAX_POSTSIZE)
		$errors[] = $lang_post['Too long message'];
	else if ($forum_config['p_message_all_caps'] == '0' && strtoupper($message) == $message && !$forum_page['is_admmod'])
		$message = ucwords(strtolower($message));

	// Validate BBCode syntax
	if ($forum_config['p_message_bbcode'] == '1' && strpos($message, '[') !== false && strpos($message, ']') !== false)
	{
		require FORUM_ROOT.'include/parser.php';
		$message = preparse_bbcode($message, $errors);
	}

	$hide_smilies = isset($_POST['hide_smilies']) ? 1 : 0;

	($hook = get_hook('ed_end_validation')) ? eval($hook) : null;

	// Did everything go according to plan?
	if (empty($errors) && !isset($_POST['preview']))
	{
		($hook = get_hook('ed_pre_post_edited')) ? eval($hook) : null;

		require FORUM_ROOT.'include/search_idx.php';

		if ($can_edit_subject)
		{
			// Update the topic and any redirect topics
			$query = array(
				'UPDATE'	=> 'topics',
				'SET'		=> 'subject=\''.$forum_db->escape($subject).'\'',
				'WHERE'		=> 'id='.$cur_post['tid'].' OR moved_to='.$cur_post['tid']
			);

			($hook = get_hook('ed_qr_update_subject')) ? eval($hook) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);

			// We changed the subject, so we need to take that into account when we update the search words
			update_search_index('edit', $id, $message, $subject);
		}
		else
			update_search_index('edit', $id, $message);

		// Update the post
		$query = array(
			'UPDATE'	=> 'posts',
			'SET'		=> 'message=\''.$forum_db->escape($message).'\', hide_smilies=\''.$hide_smilies.'\'',
			'WHERE'		=> 'id='.$id
		);

		if (!isset($_POST['silent']) || !$forum_page['is_admmod'])
			$query['SET'] .= ', edited='.time().', edited_by=\''.$forum_db->escape($forum_user['username']).'\'';

		($hook = get_hook('ed_qr_update_post')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		redirect(forum_link($forum_url['post'], $id), $lang_post['Edit redirect']);
	}
}

// Setup error messages
if (!empty($errors))
{
	$forum_page['errors'] = array();

	while (list(, $cur_error) = each($errors))
		$forum_page['errors'][] = '<li><span>'.$cur_error.'</span></li>';
}

// Setup form
$forum_page['set_count'] = $forum_page['fld_count'] = 0;
$forum_page['form_action'] = forum_link($forum_url['edit'], $id);
$forum_page['form_attributes'] = array();

$forum_page['hidden_fields'][] = '<input type="hidden" name="form_sent" value="1" />';
if ($forum_user['is_admmod'])
	$forum_page['hidden_fields'][] = '<input type="hidden" name="csrf_token" value="'.generate_form_token($forum_page['form_action']).'" />';

// Setup help
$forum_page['main_head_options'] = array();
if ($forum_config['p_message_bbcode'] == '1')
	$forum_page['main_head_options'][] = '<a class="exthelp" href="'.forum_link($forum_url['help'], 'bbcode').'" title="'.sprintf($lang_common['Help page'], $lang_common['BBCode']).'">'.$lang_common['BBCode'].'</a>';
if ($forum_config['p_message_img_tag'] == '1')
	$forum_page['main_head_options'][] = '<a class="exthelp" href="'.forum_link($forum_url['help'], 'img').'" title="'.sprintf($lang_common['Help page'], $lang_common['Images']).'">'.$lang_common['Images'].'</a>';
if ($forum_config['o_smilies'] == '1')
	$forum_page['main_head_options'][] = '<a class="exthelp" href="'.forum_link($forum_url['help'], 'smilies').'" title="'.sprintf($lang_common['Help page'], $lang_common['Smilies']).'">'.$lang_common['Smilies'].'</a>';

// Setup main heading
$forum_page['main_head'] = sprintf($lang_post['Edit this'], (($id == $cur_post['first_post_id']) ? $lang_post['Topic'] : $lang_post['Reply']), $cur_post['poster']);

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($cur_post['forum_name'], forum_link($forum_url['forum'], array($cur_post['fid'], sef_friendly($cur_post['forum_name'])))),
	array($cur_post['subject'], forum_link($forum_url['topic'], array($cur_post['tid'], sef_friendly($cur_post['subject'])))),
	$lang_post['Edit post']
);

($hook = get_hook('ed_pre_header_load')) ? eval($hook) : null;

define('FORUM_PAGE', 'postedit');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

?>
<div id="brd-main" class="main">

	<h1><span><?php echo end($forum_page['crumbs']) ?></span></h1>
<?php

// If preview selected and there are no errors
if (isset($_POST['preview']) && empty($forum_page['errors']))
{
	require_once FORUM_ROOT.'include/parser.php';
	$forum_page['preview_message'] = parse_message(trim($_POST['req_message']), $hide_smilies);

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
						<?php echo $forum_page['preview_message']."\n" ?>
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
		<h2><span><?php echo $forum_page['main_head'] ?></span></h2>
<?php if (!empty($forum_page['main_head_options'])): ?>		<p class="main-options"><?php printf($lang_common['You may use'], implode(' ', $forum_page['main_head_options'])) ?></p>
<?php endif; ?>	</div>

	<div class="main-content frm">
<?php

// If there were any errors, show them
if (isset($forum_page['errors']))
{

?>
		<div class="frm-error">
			<h3 class="warn"><?php echo $lang_post['Post errors'] ?></h3>
			<ul>
				<?php echo implode("\n\t\t\t\t\t", $forum_page['errors'])."\n" ?>
			</ul>
		</div>
<?php

}

?>
		<div id="req-msg" class="frm-warn">
			<p class="important"><?php printf($lang_common['Required warn'], '<em class="req-text">'.$lang_common['Required'].'</em>') ?></p>
		</div>
		<form id="afocus" class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>"<?php if (!empty($forum_page['form_attributes'])) echo ' '.implode(' ', $forum_page['form_attributes']) ?>>
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
			</div>
<?php ($hook = get_hook('ed_pre_main_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_post['Edit post legend'] ?></strong></legend>
<?php if ($can_edit_subject): ?>				<div class="frm-fld text longtext required">
					<label for="fld<?php echo ++ $forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_post['Topic subject'] ?></span><br />
						<span class="fld-input"><input id="fld<?php echo $forum_page['fld_count'] ?>" type="text" name="req_subject" size="80" maxlength="70" value="<?php echo forum_htmlencode(isset($_POST['req_subject']) ? $_POST['req_subject'] : $cur_post['subject']) ?>" /></span>
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
					</label>
				</div>
<?php endif; ($hook = get_hook('ed_pre_message_box')) ? eval($hook) : null; ?>				<div class="frm-fld text textarea required">
					<label for="fld<?php echo ++ $forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_post['Write message'] ?></span><br />
						<span class="fld-input"><textarea id="fld<?php echo $forum_page['fld_count'] ?>" name="req_message" rows="14" cols="95"><?php echo forum_htmlencode(isset($_POST['req_message']) ? $message : $cur_post['message']) ?></textarea></span>
						<em class="req-text"><?php echo $lang_common['Required'] ?></em>
					</label>
				</div>
			</fieldset>
<?php

$forum_page['checkboxes'] = array();
if ($forum_config['o_smilies'] == '1')
{
	if (isset($_POST['hide_smilies']) || $cur_post['hide_smilies'] == '1')
		$forum_page['checkboxes'][] = '<div class="radbox"><label for="fld'.(++$forum_page['fld_count']).'"><input type="checkbox" id="fld'.$forum_page['fld_count'].'" name="hide_smilies" value="1" checked="checked" /> '.$lang_post['Hide smilies'].'</label></div>';
	else
		$forum_page['checkboxes'][] = '<div class="radbox"><label for="fld'.(++$forum_page['fld_count']).'"><input type="checkbox" id="fld'.$forum_page['fld_count'].'" name="hide_smilies" value="1" /> '.$lang_post['Hide smilies'].'</label></div>';
}

if ($forum_page['is_admmod'])
{
	if ((isset($_POST['form_sent']) && isset($_POST['silent'])) || !isset($_POST['form_sent']))
		$forum_page['checkboxes'][] = '<div class="radbox"><label for="fld'.(++$forum_page['fld_count']).'"><input type="checkbox" id="fld'.$forum_page['fld_count'].'" name="silent" value="1" checked="checked" /> '.$lang_post['Silent edit'].'</label></div>';
	else
		$forum_page['checkboxes'][] = '<div class="radbox"><label for="fld'.(++$forum_page['fld_count']).'"><input type="checkbox" id="fld'.$forum_page['fld_count'].'" name="silent" value="1" /> '.$lang_post['Silent edit'].'</label></div>';
}

($hook = get_hook('ed_pre_checkbox_display')) ? eval($hook) : null;

if (!empty($forum_page['checkboxes']))
{

?>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_post['Optional legend'] ?></strong></legend>
				<fieldset class="frm-group">
					<legend><span><?php echo $lang_post['Post settings'] ?></span></legend>
					<?php echo implode("\n\t\t\t\t\t\t", $forum_page['checkboxes'])."\n"; ?>
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

$tpl_temp = trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
