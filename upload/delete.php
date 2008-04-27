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

($hook = get_hook('dl_start')) ? eval($hook) : null;

if ($pun_user['g_read_board'] == '0')
	message($lang_common['No view']);

// Load the delete.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/delete.php';


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

($hook = get_hook('dl_qr_get_post_info')) ? eval($hook) : null;
$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
if (!$pun_db->num_rows($result))
	message($lang_common['Bad request']);

$cur_post = $pun_db->fetch_assoc($result);

// Sort out who the moderators are and if we are currently a moderator (or an admin)
$mods_array = ($cur_post['moderators'] != '') ? unserialize($cur_post['moderators']) : array();
$pun_page['is_admmod'] = ($pun_user['g_id'] == PUN_ADMIN || ($pun_user['g_moderator'] == '1' && array_key_exists($pun_user['username'], $mods_array))) ? true : false;

$cur_post['is_topic'] = ($id == $cur_post['first_post_id']) ? true : false;

// Do we have permission to delete this post?
if (($pun_user['g_delete_posts'] == '0' ||
	($pun_user['g_delete_topics'] == '0' && $cur_post['is_topic']) ||
	$cur_post['poster_id'] != $pun_user['id'] ||
	$cur_post['closed'] == '1') &&
	!$pun_page['is_admmod'])
	message($lang_common['No permission']);


// User pressed the cancel button
if (isset($_POST['cancel']))
	redirect(pun_link($pun_url['post'], $id), $lang_common['Cancel redirect']);

// User pressed the delete button
else if (isset($_POST['delete']))
{
	($hook = get_hook('dl_form_submitted')) ? eval($hook) : null;

	if (isset($_POST['req_confirm']))
	{
		if ($cur_post['is_topic'])
		{
			// Delete the topic and all of it's posts
			delete_topic($cur_post['tid'], $cur_post['fid']);

			redirect(pun_link($pun_url['forum'], array($cur_post['fid'], sef_friendly($cur_post['forum_name']))), $lang_delete['Topic del redirect']);
		}
		else
		{
			// Delete just this one post
			delete_post($id, $cur_post['tid'], $cur_post['fid']);

			redirect(pun_link($pun_url['topic'], array($cur_post['tid'], sef_friendly($cur_post['subject']))), $lang_delete['Post del redirect']);
		}
	}
	else
		redirect(pun_link($pun_url['post'], $id), $lang_common['No confirm redirect']);
}

// Run the post through the parser
require PUN_ROOT.'include/parser.php';
$cur_post['message'] = parse_message($cur_post['message'], $cur_post['hide_smilies']);

// Setup form
$pun_page['set_count'] = $pun_page['fld_count'] = 0;
$pun_page['form_action'] = pun_link($pun_url['delete'], $id);

$pun_page['hidden_fields'][] = '<input type="hidden" name="form_sent" value="1" />';
if ($pun_user['is_admmod'])
	$pun_page['hidden_fields'][] = '<input type="hidden" name="csrf_token" value="'.generate_form_token($pun_page['form_action']).'" />';

// Setup form information
$pun_page['frm_info'] = array(
	'<li><span><strong>'.$lang_common['Forum'].':</strong> '.pun_htmlencode($cur_post['forum_name']).'</span></li>',
	'<li><span><strong>'.$lang_common['Topic'].':</strong> '.pun_htmlencode($cur_post['subject']).'</span></li>',
	'<li><span>'.sprintf((($cur_post['is_topic']) ? $lang_delete['Delete topic info'] : $lang_delete['Delete post info']), $cur_post['poster'], format_time($cur_post['posted'])).'</span></li>'
);

// Setup main heading
$pun_page['main_head'] = sprintf(($cur_post['is_topic']) ? $lang_delete['Delete topic head'] : $lang_delete['Delete post head'], $cur_post['poster'], format_time($cur_post['posted']));

// Setup breadcrumbs
$pun_page['crumbs'] = array(
	array($pun_config['o_board_title'], pun_link($pun_url['index'])),
	array($cur_post['forum_name'], pun_link($pun_url['forum'], array($cur_post['fid'], sef_friendly($cur_post['forum_name'])))),
	array($cur_post['subject'], pun_link($pun_url['topic'], array($cur_post['tid'], sef_friendly($cur_post['subject'])))),
	(($cur_post['is_topic']) ? $lang_delete['Delete topic'] : $lang_delete['Delete post'])
);

($hook = get_hook('dl_pre_header_load')) ? eval($hook) : null;

define ('PUN_PAGE', 'postdelete');
require PUN_ROOT.'header.php';

?>
<div id="pun-main" class="main">

	<h1><span><?php echo end($pun_page['crumbs']) ?></span></h1>

	<div class="main-head">
		<h2><span><?php echo $pun_page['main_head'] ?></span></h2>
	</div>
	<div class="main-content frm">
		<div class="frm-info">
			<ul>
				<?php echo implode("\n\t\t\t\t", $pun_page['frm_info'])."\n" ?>
			</ul>
		</div>
		<div class="post-entry">
			<div class="entry-content">
				<?php echo $cur_post['message']."\n" ?>
			</div>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $pun_page['form_action'] ?>">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $pun_page['hidden_fields'])."\n" ?>
			</div>
			<fieldset class="frm-set set<?php echo ++$pun_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_delete['Delete post'] ?></strong></legend>
				<div class="checkbox radbox">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>"><span class="fld-label"><?php echo $lang_common['Please confirm'] ?></span><br /><input type="checkbox" id="fld<?php echo $pun_page['fld_count'] ?>" name="req_confirm" value="1" checked="checked" /> <?php printf(((($cur_post['is_topic'])) ? $lang_delete['Delete topic head'] : $lang_delete['Delete post head']), $cur_post['poster'], format_time($cur_post['posted'])) ?>.</label>
				</div>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="delete" value="<?php echo $lang_delete['Delete'] ?>" /></span>
				<span class="cancel"><input type="submit" name="cancel" value="<?php echo $lang_common['Cancel'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

$forum_id = $cur_post['fid'];

($hook = get_hook('dl_end')) ? eval($hook) : null;

require PUN_ROOT.'footer.php';
