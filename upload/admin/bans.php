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
	define('FORUM_ROOT', '../');
require FORUM_ROOT.'include/common.php';
require FORUM_ROOT.'include/common_admin.php';

($hook = get_hook('aba_start')) ? eval($hook) : null;

if ($forum_user['g_id'] != FORUM_ADMIN && ($forum_user['g_moderator'] != '1' || $forum_user['g_mod_ban_users'] == '0'))
	message($lang_common['No permission']);

// Load the admin.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin.php';


// Add/edit a ban (stage 1)
if (isset($_REQUEST['add_ban']) || isset($_GET['edit_ban']))
{
	if (isset($_GET['add_ban']) || isset($_POST['add_ban']))
	{
		// If the id of the user to ban was provided through GET (a link from profile.php)
		if (isset($_GET['add_ban']))
		{
			$add_ban = intval($_GET['add_ban']);
			if ($add_ban < 2)
				message($lang_common['Bad request']);

			$user_id = $add_ban;

			($hook = get_hook('aba_add_ban_selected')) ? eval($hook) : null;

			$query = array(
				'SELECT'	=> 'u.group_id, u.username, u.email, u.registration_ip',
				'FROM'		=> 'users AS u',
				'WHERE'		=> 'u.id='.$user_id
			);

			($hook = get_hook('aba_qr_get_user_by_id')) ? eval($hook) : null;
			$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
			if (!$forum_db->num_rows($result))
				message($lang_admin['No user id message']);

			list($group_id, $ban_user, $ban_email, $ban_ip) = $forum_db->fetch_row($result);
		}
		else	// Otherwise the username is in POST
		{
			$ban_user = trim($_POST['new_ban_user']);

			($hook = get_hook('aba_add_ban_form_submitted')) ? eval($hook) : null;

			if ($ban_user != '')
			{
				$query = array(
					'SELECT'	=> 'u.id, u.group_id, u.username, u.email, u.registration_ip',
					'FROM'		=> 'users AS u',
					'WHERE'		=> 'u.username=\''.$forum_db->escape($ban_user).'\' AND u.id>1'
				);

				($hook = get_hook('aba_qr_get_user_by_username')) ? eval($hook) : null;
				$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
				if (!$forum_db->num_rows($result))
					message($lang_admin['No user username message']);

				list($user_id, $group_id, $ban_user, $ban_email, $ban_ip) = $forum_db->fetch_row($result);
			}
		}

		// Make sure we're not banning an admin
		if (isset($group_id) && $group_id == FORUM_ADMIN)
			message($lang_admin['User is admin message']);

		// If we have a $user_id, we can try to find the last known IP of that user
		if (isset($user_id))
		{
			$query = array(
				'SELECT'	=> 'p.poster_ip',
				'FROM'		=> 'posts AS p',
				'WHERE'		=> 'p.poster_id='.$user_id,
				'ORDER BY'	=> 'p.posted DESC',
				'LIMIT'		=> '1'
			);

			($hook = get_hook('aba_qr_get_last_known_ip')) ? eval($hook) : null;
			$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

			$ban_ip = ($forum_db->num_rows($result)) ? $forum_db->result($result) : $ban_ip;
		}

		$mode = 'add';
	}
	else	// We are editing a ban
	{
		$ban_id = intval($_GET['edit_ban']);
		if ($ban_id < 1)
			message($lang_common['Bad request']);

		($hook = get_hook('aba_edit_ban_selected')) ? eval($hook) : null;

		$query = array(
			'SELECT'	=> 'b.username, b.ip, b.email, b.message, b.expire',
			'FROM'		=> 'bans AS b',
			'WHERE'		=> 'b.id='.$ban_id
		);

		($hook = get_hook('aba_qr_get_ban_data')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		if ($forum_db->num_rows($result))
			list($ban_user, $ban_ip, $ban_email, $ban_message, $ban_expire) = $forum_db->fetch_row($result);
		else
			message($lang_common['Bad request']);

		// We just use GMT for expire dates, as its a date rather than a day I don't think its worth worrying about
		$ban_expire = ($ban_expire != '') ? gmdate('Y-m-d', $ban_expire) : '';

		$mode = 'edit';
	}


	// Setup the form
	$forum_page['fld_count'] = $forum_page['set_count'] = 0;

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin['Forum administration'], forum_link($forum_url['admin_index'])),
		array($lang_admin['Bans'], forum_link($forum_url['admin_bans'])),
		$lang_admin['Ban advanced']
	);

	($hook = get_hook('aba_add_edit_ban_pre_header_load')) ? eval($hook) : null;

	define('FORUM_PAGE_SECTION', 'users');
	define('FORUM_PAGE', 'admin-bans');
	require FORUM_ROOT.'header.php';

?>
<div id="brd-main" class="main admin sectioned">

<?php echo generate_admin_menu(); ?>

	<div class="main-head">
		<h1><span>{ <?php echo end($forum_page['crumbs']) ?> }</span></h1>
	</div>

	<div class="main-content frm">
		<div class="frm-head">
			<h2><span><?php echo $lang_admin['Ban advanced heading'] ?></span></h2>
		</div>
		<div class="frm-info">
			<p class="warn"><?php echo $lang_admin['Ban IP warning'] ?></p>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_bans']) ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_bans'])) ?>" />
				<input type="hidden" name="mode" value="<?php echo $mode ?>" />
<?php if ($mode == 'edit'): ?>				<input type="hidden" name="ban_id" value="<?php echo $ban_id ?>" />
<?php endif; ?>			</div>
<?php ($hook = get_hook('aba_add_edit_ban_pre_criteria_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><span><?php echo $lang_admin['Ban criteria legend'] ?></span></legend>
<?php ($hook = get_hook('aba_add_edit_ban_pre_username')) ? eval($hook) : null; ?>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Username to ban'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="ban_user" size="25" maxlength="25" value="<?php if (isset($ban_user)) echo forum_htmlencode($ban_user); ?>" /></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['E-mail/domain to ban'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="ban_email" size="40" maxlength="80" value="<?php if (isset($ban_email)) echo strtolower($ban_email); ?>" /></span>
						<span class="fld-help"><?php echo $lang_admin['E-mail/domain info'] ?></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['IP-addresses to ban'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="ban_ip" size="45" maxlength="255" value="<?php if (isset($ban_ip)) echo $ban_ip; ?>" /></span>
						<span class="fld-help"><?php echo $lang_admin['IP-addresses info']; if ($ban_user != '' && isset($user_id)) echo ' '.$lang_admin['IP-addresses info 2'].'<a href="'.forum_link($forum_url['admin_users']).'?ip_stats='.$user_id.'">'.$lang_admin['IP-addresses info link'].'</a>' ?></span>
					</label>
				</div>
<?php ($hook = get_hook('aba_add_edit_ban_criteria_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php ($hook = get_hook('aba_add_edit_ban_pre_settings_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><span><?php echo $lang_admin['Ban settings legend'] ?></span></legend>
<?php ($hook = get_hook('aba_add_edit_ban_pre_message')) ? eval($hook) : null; ?>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Ban message'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="ban_message" size="50" maxlength="255" value="<?php if (isset($ban_message)) echo forum_htmlencode($ban_message); ?>" /></span>
						<span class="fld-help"><?php echo $lang_admin['Ban message info'] ?></span>
					</label>
				</div>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Expire date'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="ban_expire" size="17" maxlength="10" value="<?php if (isset($ban_expire)) echo $ban_expire; ?>" /></span>
						<span class="fld-help"><?php echo $lang_admin['Expire date info'] ?></span>
					</label>
				</div>
<?php ($hook = get_hook('aba_add_edit_ban_settings_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php ($hook = get_hook('aba_add_edit_ban_pre_buttons')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" class="button" name="add_edit_ban" value=" <?php echo $lang_admin['Save'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

	require FORUM_ROOT.'footer.php';
}


// Add/edit a ban (stage 2)
else if (isset($_POST['add_edit_ban']))
{
	$ban_user = trim($_POST['ban_user']);
	$ban_ip = trim($_POST['ban_ip']);
	$ban_email = strtolower(trim($_POST['ban_email']));
	$ban_message = trim($_POST['ban_message']);
	$ban_expire = trim($_POST['ban_expire']);

	if ($ban_user == '' && $ban_ip == '' && $ban_email == '')
		message($lang_admin['Must enter message']);
	else if (strtolower($ban_user) == 'guest')
		message($lang_admin['Can\'t ban guest user']);

	($hook = get_hook('aba_add_edit_ban_form_submitted2')) ? eval($hook) : null;

	// Validate IP/IP range (it's overkill, I know)
	if ($ban_ip != '')
	{
		$ban_ip = preg_replace('/[\s]{2,}/', ' ', $ban_ip);
		$addresses = explode(' ', $ban_ip);
		$addresses = array_map('trim', $addresses);

		for ($i = 0; $i < count($addresses); ++$i)
		{
			$octets = explode('.', $addresses[$i]);

			for ($c = 0; $c < count($octets); ++$c)
			{
				$octets[$c] = (strlen($octets[$c]) > 1) ? ltrim($octets[$c], "0") : $octets[$c];

				if ($c > 3 || !ctype_digit($octets[$c]) || intval($octets[$c]) > 255)
					message($lang_admin['Invalid IP message']);
			}

			$cur_address = implode('.', $octets);
			$addresses[$i] = $cur_address;
		}

		$ban_ip = implode(' ', $addresses);
	}

	require FORUM_ROOT.'include/email.php';
	if ($ban_email != '' && !is_valid_email($ban_email))
	{
		if (!preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/', $ban_email))
			message($lang_admin['Invalid e-mail message']);
	}

	if ($ban_expire != '' && $ban_expire != 'Never')
	{
		$ban_expire = strtotime($ban_expire);

		if ($ban_expire == -1 || $ban_expire <= time())
			message($lang_admin['Invalid expire message']);
	}
	else
		$ban_expire = 'NULL';

	$ban_user = ($ban_user != '') ? '\''.$forum_db->escape($ban_user).'\'' : 'NULL';
	$ban_ip = ($ban_ip != '') ? '\''.$forum_db->escape($ban_ip).'\'' : 'NULL';
	$ban_email = ($ban_email != '') ? '\''.$forum_db->escape($ban_email).'\'' : 'NULL';
	$ban_message = ($ban_message != '') ? '\''.$forum_db->escape($ban_message).'\'' : 'NULL';

	if ($_POST['mode'] == 'add')
	{
		$query = array(
			'INSERT'	=> 'username, ip, email, message, expire, ban_creator',
			'INTO'		=> 'bans',
			'VALUES'	=> $ban_user.', '.$ban_ip.', '.$ban_email.', '.$ban_message.', '.$ban_expire.', '.$forum_user['id']
		);

		($hook = get_hook('aba_qr_add_ban')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);
	}
	else
	{
		$query = array(
			'UPDATE'	=> 'bans',
			'SET'		=> 'username='.$ban_user.', ip='.$ban_ip.', email='.$ban_email.', message='.$ban_message.', expire='.$ban_expire,
			'WHERE'		=> 'id='.intval($_POST['ban_id'])
		);

		($hook = get_hook('aba_qr_update_ban')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);
	}

	// Regenerate the bans cache
	require_once FORUM_ROOT.'include/cache.php';
	generate_bans_cache();

	redirect(forum_link($forum_url['admin_bans']), (($_POST['mode'] == 'edit') ? $lang_admin['Ban edited'] : $lang_admin['Ban added']).' '.$lang_admin['Redirect']);
}


// Remove a ban
else if (isset($_GET['del_ban']))
{
	$ban_id = intval($_GET['del_ban']);
	if ($ban_id < 1)
		message($lang_common['Bad request']);

	// Validate the CSRF token
	if (!isset($_POST['csrf_token']) && (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== generate_form_token('del_ban'.$ban_id)))
		csrf_confirm_form();

	($hook = get_hook('aba_del_ban_form_submitted2')) ? eval($hook) : null;

	$query = array(
		'DELETE'	=> 'bans',
		'WHERE'		=> 'id='.$ban_id
	);

	($hook = get_hook('aba_qr_delete_ban')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	// Regenerate the bans cache
	require_once FORUM_ROOT.'include/cache.php';
	generate_bans_cache();

	redirect(forum_link($forum_url['admin_bans']), $lang_admin['Ban removed'].' '. $lang_admin['Redirect']);
}


// Setup the form
$forum_page['part_count'] = $forum_page['fld_count'] = $forum_page['set_count'] = 0;

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($lang_admin['Forum administration'], forum_link($forum_url['admin_index'])),
	$lang_admin['Bans']
);

($hook = get_hook('aba_pre_header_loaded')) ? eval($hook) : null;

define('FORUM_PAGE_SECTION', 'users');
define('FORUM_PAGE', 'admin-bans');
require FORUM_ROOT.'header.php';

?>
<div id="brd-main" class="main sectioned admin">

<?php echo generate_admin_menu(); ?>

	<div class="main-head">
		<h1><span>{ <?php echo end($forum_page['crumbs']) ?> }</span></h1>
	</div>

	<div class="main-content frm">
		<div class="frm-head">
			<h2><span><?php echo $lang_admin['New ban heading'] ?></span></h2>
		</div>
		<div class="frm-info">
			<p><?php echo $lang_admin['Advanced ban info'] ?></p>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_bans']) ?>?action=more">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_bans']).'?action=more') ?>" />
			</div>
			<fieldset class="frm-set set<?php echo ++$forum_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_admin['New ban legend'] ?></strong></legend>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$forum_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_admin['Username to ban'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="new_ban_user" size="25" maxlength="25" /></span>
					</label>
				</div>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" class="button" name="add_ban" value=" <?php echo $lang_admin['Add'] ?> " /></span>
			</div>
		</form>
	</div>
<?php

// Reset fieldset counter
$forum_page['set_count'] = 0;

?>
	<div class="main-content frm">
		<div class="frm-head">
			<h2><span><?php echo $lang_admin['Existing bans heading'] ?></span></h2>
		</div>
<?php

if (!empty($forum_bans))
{
	$forum_page['item_num'] = 0;
	foreach ($forum_bans as $ban_key => $cur_ban)
	{
		$forum_page['ban_info'] = array();
		$forum_page['ban_creator'] = ($cur_ban['ban_creator_username'] != '') ? '<a href="'.forum_link($forum_url['user'], $cur_ban['ban_creator']).'">'.forum_htmlencode($cur_ban['ban_creator_username']).'</a>' : $lang_admin['Unknown'];

		if ($cur_ban['username'] != '')
			$forum_page['ban_info'][] = '<span>'.$lang_admin['Username'].': '.forum_htmlencode($cur_ban['username']).'</span>';

		if ($cur_ban['email'] != '')
			$forum_page['ban_info'][] = '<span>'.$lang_admin['E-mail'].': '.$cur_ban['email'].'</span>';

		if ($cur_ban['ip'] != '')
			$forum_page['ban_info'][] = '<span>'.$lang_admin['IP-ranges'].': '.$cur_ban['ip'].'</span>';

		if ($cur_ban['expire'] != '')
			$forum_page['ban_info'][] = '<span>'.$lang_admin['Expire date'].': '.format_time($cur_ban['expire'], true).'</span>';

		($hook = get_hook('aba_view_ban_pre_display')) ? eval($hook) : null;

?>
		<div class="ban-item databox db<?php echo ++$forum_page['item_num'] ?>">
			<h3 class="legend"><span><?php printf($lang_admin['Current ban head'], $forum_page['ban_creator']) ?></span></h3>
<?php if (!empty($forum_page['ban_info'])): ?>			<p class="data">
				<?php echo implode('<br />', $forum_page['ban_info'])."\n" ?>
			</p>
<?php endif; if ($cur_ban['message'] != ''): ?>			<p><?php echo $lang_admin['Reason'].': '.forum_htmlencode($cur_ban['message']) ?></p>
<?php endif; ?>		<p class="actions"><a href="<?php echo forum_link($forum_url['admin_bans']).'?edit_ban='.$cur_ban['id'] ?>"><?php echo $lang_admin['Edit'] ?></a> <a href="<?php echo forum_link($forum_url['admin_bans']).'?del_ban='.$cur_ban['id'].'&amp;csrf_token='.generate_form_token('del_ban'.$cur_ban['id']) ?>"><?php echo $lang_admin['Remove'] ?></a></p>
</div>
<?php

	}
}
else
{

?>
		<div class="frm-info">
			<p><?php echo $lang_admin['No bans'] ?></p>
		</div>
<?php

}

?>
	</div>

</div>
<?php

require FORUM_ROOT.'footer.php';
