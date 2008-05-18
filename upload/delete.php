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

($hook = get_hook('dl_start')) ? eval($hook) : null;

if ($forum_user['g_read_board'] == '0')
	message($lang_common['No view']);

// Load the delete.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/delete.php';


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

($hook = get_hook('dl_qr_get_post_info')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
if (!$forum_db->num_rows($result))
	message($lang_common['Bad request']);

$cur_post = $forum_db->fetch_assoc($result);

// Sort out who the moderators are and if we are currently a moderator (or an admin)
$mods_array = ($cur_post['moderators'] != '') ? unserialize($cur_post['moderators']) : array();
$forum_page['is_admmod'] = ($forum_user['g_id'] == FORUM_ADMIN || ($forum_user['g_moderator'] == '1' && array_key_exists($forum_user['username'], $mods_array))) ? true : false;

$cur_post['is_topic'] = ($id == $cur_post['first_post_id']) ? true : false;

// Do we have permission to delete this post?
if (($forum_user['g_delete_posts'] == '0' ||
	($forum_user['g_delete_topics'] == '0' && $cur_post['is_topic']) ||
	$cur_post['poster_id'] != $forum_user['id'] ||
	$cur_post['closed'] == '1') &&
	!$forum_page['is_admmod'])
	message($lang_common['No permission']);


($hook = get_hook('dl_post_selected')) ? eval($hook) : null;

// User pressed the cancel button
if (isset($_POST['cancel']))
	redirect(forum_link($forum_url['post'], $id), $lang_common['Cancel redirect']);

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

			redirect(forum_link($forum_url['forum'], array($cur_post['fid'], sef_friendly($cur_post['forum_name']))), $lang_delete['Topic del redirect']);
		}
		else
		{
			// Delete just this one post
			delete_post($id, $cur_post['tid'], $cur_post['fid']);

			redirect(forum_link($forum_url['topic'], array($cur_post['tid'], sef_friendly($cur_post['subject']))), $lang_delete['Post del redirect']);
		}
	}
	else
		redirect(forum_link($forum_url['post'], $id), $lang_common['No confirm redirect']);
}

// Run the post through the parser
if (!defined('FORUM_PARSER_LOADED'))
	require FORUM_ROOT.'include/parser.php';
$cur_post['message'] = parse_message($cur_post['message'], $cur_post['hide_smilies']);

// Setup form
$forum_page['set_count'] = $forum_page['fld_count'] = 0;
$forum_page['form_action'] = forum_link($forum_url['delete'], $id);

$forum_page['hidden_fields']['form_sent'] = '<input type="hidden" name="form_sent" value="1" />';
if ($forum_user['is_admmod'])
	$forum_page['hidden_fields']['csrf_token'] = '<input type="hidden" name="csrf_token" value="'.generate_form_token($forum_page['form_action']).'" />';

// Setup form information
$forum_page['frm_info'] = array(
	'<li><span><strong>'.$lang_common['Forum'].':</strong> '.forum_htmlencode($cur_post['forum_name']).'</span></li>',
	'<li><span><strong>'.$lang_common['Topic'].':</strong> '.forum_htmlencode($cur_post['subject']).'</span></li>',
	'<li><span>'.sprintf((($cur_post['is_topic']) ? $lang_delete['Delete topic info'] : $lang_delete['Delete post info']), $cur_post['poster'], format_time($cur_post['posted'])).'</span></li>'
);

// Setup main heading
$forum_page['main_head'] = sprintf(($cur_post['is_topic']) ? $lang_delete['Delete topic head'] : $lang_delete['Delete post head'], $cur_post['poster'], format_time($cur_post['posted']));

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($cur_post['forum_name'], forum_link($forum_url['forum'], array($cur_post['fid'], sef_friendly($cur_post['forum_name'])))),
	array($cur_post['subject'], forum_link($forum_url['topic'], array($cur_post['tid'], sef_friendly($cur_post['subject'])))),
	(($cur_post['is_topic']) ? $lang_delete['Delete topic'] : $lang_delete['Delete post'])
);

($hook = get_hook('dl_pre_header_load')) ? eval($hook) : null;

define ('FORUM_PAGE', 'postdelete');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('dl_main_output_start')) ? eval($hook) : null;

?>
<div id="brd-main" class="main">

	<h1><span><?php echo end($forum_page['crumbs']) ?></span></h1>

	<div class="main-head">
		<h2><span><?php echo $forum_page['main_head'] ?></span></h2>
	</div>
	<div class="main-content frm">
		<div class="frm-info">
			<ul>
				<?php echo implode("\n\t\t\t\t", $forum_page['frm_info'])."\n" ?>
			</ul>
		</div>
		<div class="post-entry">
			<div class="entry-content">
				<?php echo $cur_post['message']."\n" ?>
			</div>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
			</div>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_delete['Delete post'] ?></strong></legend>
				<div class="checkbox radbox">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span class="fld-label"><?php echo $lang_common['Please confirm'] ?></span><br /><input type="checkbox" id="fld<?php echo $forum_page['fld_count'] ?>" name="req_confirm" value="1" checked="checked" /> <?php printf(((($cur_post['is_topic'])) ? $lang_delete['Delete topic head'] : $lang_delete['Delete post head']), $cur_post['poster'], format_time($cur_post['posted'])) ?>.</label>
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

$tpl_temp = trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
