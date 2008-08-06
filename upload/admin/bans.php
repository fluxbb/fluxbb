<?php
/**
 * Ban management page
 *
 * Allows administrators and moderators to create, modify, and delete bans.
 *
 * @copyright Copyright (C) 2008 FluxBB.org, based on code copyright (C) 2002-2008 PunBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package FluxBB
 */
 

if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', '../');
require FORUM_ROOT.'include/common.php';
require FORUM_ROOT.'include/common_admin.php';

($hook = get_hook('aba_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

if ($forum_user['g_id'] != FORUM_ADMIN && ($forum_user['g_moderator'] != '1' || $forum_user['g_mod_ban_users'] == '0'))
	message($lang_common['No permission']);

// Load the admin.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin_common.php';
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin_bans.php';


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

			($hook = get_hook('aba_add_ban_selected')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

			$query = array(
				'SELECT'	=> 'u.group_id, u.username, u.email, u.registration_ip',
				'FROM'		=> 'users AS u',
				'WHERE'		=> 'u.id='.$user_id
			);

			($hook = get_hook('aba_add_ban_qr_get_user_by_id')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
			$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
			if (!$forum_db->num_rows($result))
				message($lang_admin_bans['No user id message']);

			list($group_id, $ban_user, $ban_email, $ban_ip) = $forum_db->fetch_row($result);
		}
		else	// Otherwise the username is in POST
		{
			$ban_user = trim($_POST['new_ban_user']);

			($hook = get_hook('aba_add_ban_form_submitted')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

			if ($ban_user != '')
			{
				$query = array(
					'SELECT'	=> 'u.id, u.group_id, u.username, u.email, u.registration_ip',
					'FROM'		=> 'users AS u',
					'WHERE'		=> 'u.username=\''.$forum_db->escape($ban_user).'\' AND u.id>1'
				);

				($hook = get_hook('aba_add_ban_qr_get_user_by_username')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
				$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
				if (!$forum_db->num_rows($result))
					message($lang_admin_bans['No user username message']);

				list($user_id, $group_id, $ban_user, $ban_email, $ban_ip) = $forum_db->fetch_row($result);
			}
		}

		// Make sure we're not banning an admin
		if (isset($group_id) && $group_id == FORUM_ADMIN)
			message($lang_admin_bans['User is admin message']);

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

			($hook = get_hook('aba_add_ban_qr_get_last_known_ip')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
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

		($hook = get_hook('aba_edit_ban_selected')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		$query = array(
			'SELECT'	=> 'b.username, b.ip, b.email, b.message, b.expire',
			'FROM'		=> 'bans AS b',
			'WHERE'		=> 'b.id='.$ban_id
		);

		($hook = get_hook('aba_edit_ban_qr_get_ban_data')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
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
	$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
		array($lang_admin_common['Bans'], forum_link($forum_url['admin_bans'])),
		$lang_admin_bans['Ban advanced']
	);

	($hook = get_hook('aba_add_edit_ban_pre_header_load')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	define('FORUM_PAGE_SECTION', 'users');
	define('FORUM_PAGE', 'admin-bans');
	define('FORUM_PAGE_TYPE', 'sectioned');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

	($hook = get_hook('aba_add_edit_ban_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_admin_bans['Ban advanced heading'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<div class="ct-box">
			<p class="warn"><?php echo $lang_admin_bans['Ban IP warning'] ?></p>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_bans']) ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_bans'])) ?>" />
				<input type="hidden" name="mode" value="<?php echo $mode ?>" />
<?php if ($mode == 'edit'): ?>				<input type="hidden" name="ban_id" value="<?php echo $ban_id ?>" />
<?php endif; ?>			</div>
<?php ($hook = get_hook('aba_add_edit_ban_pre_criteria_fieldset')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			<fieldset class="frm-group group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><span><?php echo $lang_admin_bans['Ban criteria legend'] ?></span></legend>
<?php ($hook = get_hook('aba_add_edit_ban_pre_username')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_bans['Username to ban label'] ?></span></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="ban_user" size="25" maxlength="25" value="<?php if (isset($ban_user)) echo forum_htmlencode($ban_user); ?>" /></span>
					</div>
				</div>
<?php ($hook = get_hook('aba_add_edit_ban_pre_email')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_bans['E-mail/domain to ban label'] ?></span> <small><?php echo $lang_admin_bans['E-mail/domain help'] ?></small></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="ban_email" size="40" maxlength="80" value="<?php if (isset($ban_email)) echo forum_htmlencode(strtolower($ban_email)); ?>" /></span>
					</div>
				</div>
<?php ($hook = get_hook('aba_add_edit_ban_pre_ip')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_bans['IP-addresses to ban label'] ?></span> <small><?php echo $lang_admin_bans['IP-addresses help']; if ($ban_user != '' && isset($user_id)) echo ' '.$lang_admin_bans['IP-addresses help stats'].'<a href="'.forum_link($forum_url['admin_users']).'?ip_stats='.$user_id.'">'.$lang_admin_bans['IP-addresses help link'].'</a>' ?></small></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="ban_ip" size="45" maxlength="255" value="<?php if (isset($ban_ip)) echo $ban_ip; ?>" /></span>
					</div>
				</div>
<?php ($hook = get_hook('aba_add_edit_ban_pre_message')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_bans['Ban message label'] ?></span> <small><?php echo $lang_admin_bans['Ban message help'] ?></small></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="ban_message" size="50" maxlength="255" value="<?php if (isset($ban_message)) echo forum_htmlencode($ban_message); ?>" /></span>
					</div>
				</div>
<?php ($hook = get_hook('aba_add_edit_ban_pre_expire')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_bans['Expire date label'] ?></span> <small><?php echo $lang_admin_bans['Expire date help'] ?></small></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="ban_expire" size="17" maxlength="10" value="<?php if (isset($ban_expire)) echo $ban_expire; ?>" /></span>
					</div>
				</div>
<?php ($hook = get_hook('aba_add_edit_ban_criteria_pre_fieldset_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			</fieldset>
<?php ($hook = get_hook('aba_add_edit_ban_criteria_fieldset_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" class="button" name="add_edit_ban" value=" <?php echo $lang_admin_bans['Save ban'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

	($hook = get_hook('aba_add_edit_ban_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	$tpl_temp = trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

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
		message($lang_admin_bans['Must enter message']);
	else if (strtolower($ban_user) == 'guest')
		message($lang_admin_bans['Can\'t ban guest user']);

	($hook = get_hook('aba_add_edit_ban_form_submitted')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	// Validate IP/IP range (it's overkill, I know)
	if ($ban_ip != '')
	{
		$ban_ip = preg_replace('/[\s]{2,}/', ' ', $ban_ip);
		$addresses = explode(' ', $ban_ip);
		$addresses = array_map('trim', $addresses);

		for ($i = 0; $i < count($addresses); ++$i)
		{
			if (strpos($addresses[$i], ':') !== false)
			{
				$octets = explode(':', $addresses[$i]);

				for ($c = 0; $c < count($octets); ++$c)
				{
					$octets[$c] = ltrim($octets[$c], "0");

					if ($c > 7 || (!empty($octets[$c]) && !ctype_xdigit($octets[$c])) || intval($octets[$c], 16) > 65535)
						message($lang_admin_bans['Invalid IP message']);
				}

				$cur_address = implode(':', $octets);
				$addresses[$i] = $cur_address;
			}
			else
			{
				$octets = explode('.', $addresses[$i]);

				for ($c = 0; $c < count($octets); ++$c)
				{
					$octets[$c] = (strlen($octets[$c]) > 1) ? ltrim($octets[$c], "0") : $octets[$c];

					if ($c > 3 || !ctype_digit($octets[$c]) || intval($octets[$c]) > 255)
						message($lang_admin_bans['Invalid IP message']);
				}

				$cur_address = implode('.', $octets);
				$addresses[$i] = $cur_address;
			}
		}

		$ban_ip = implode(' ', $addresses);
	}

	if (!defined('FORUM_EMAIL_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/email.php';

	if ($ban_email != '' && !is_valid_email($ban_email))
	{
		if (!preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/', $ban_email))
			message($lang_admin_bans['Invalid e-mail message']);
	}

	if ($ban_expire != '' && $ban_expire != 'Never')
	{
		$ban_expire = strtotime($ban_expire);

		if ($ban_expire == -1 || $ban_expire <= time())
			message($lang_admin_bans['Invalid expire message']);
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

		($hook = get_hook('aba_add_edit_ban_qr_add_ban')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);
	}
	else
	{
		$query = array(
			'UPDATE'	=> 'bans',
			'SET'		=> 'username='.$ban_user.', ip='.$ban_ip.', email='.$ban_email.', message='.$ban_message.', expire='.$ban_expire,
			'WHERE'		=> 'id='.intval($_POST['ban_id'])
		);

		($hook = get_hook('aba_add_edit_ban_qr_update_ban')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);
	}

	// Regenerate the bans cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';

	generate_bans_cache();

	($hook = get_hook('aba_add_edit_ban_pre_redirect')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	redirect(forum_link($forum_url['admin_bans']), (($_POST['mode'] == 'edit') ? $lang_admin_bans['Ban edited'] : $lang_admin_bans['Ban added']).' '.$lang_admin_common['Redirect']);
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

	($hook = get_hook('aba_del_ban_form_submitted')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	$query = array(
		'DELETE'	=> 'bans',
		'WHERE'		=> 'id='.$ban_id
	);

	($hook = get_hook('aba_del_ban_qr_delete_ban')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	// Regenerate the bans cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';

	generate_bans_cache();

	($hook = get_hook('aba_del_ban_pre_redirect')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	redirect(forum_link($forum_url['admin_bans']), $lang_admin_bans['Ban removed'].' '. $lang_admin_common['Redirect']);
}


// Setup the form
$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;
$forum_page['form_action'] = forum_link($forum_url['admin_bans']).'?action=more';

$forum_page['hidden_fields'] = array(
	'csrf_token'	=> '<input type="hidden" name="csrf_token" value="'.generate_form_token($forum_page['form_action']).'" />'
);

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
	$lang_admin_common['Bans']
);

($hook = get_hook('aba_pre_header_load')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

define('FORUM_PAGE_SECTION', 'users');
define('FORUM_PAGE', 'admin-bans');
define('FORUM_PAGE_TYPE', 'sectioned');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('aba_main_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_admin_bans['New ban heading'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<div class="ct-box">
			<p><?php echo $lang_admin_bans['Advanced ban info'] ?></p>
		</div>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>">
			<div class="hidden">
				<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
			</div>
			<fieldset class="frm-group group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_admin_bans['New ban legend'] ?></strong></legend>
				<div class="sf-set group-item<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_bans['Username to ban label'] ?></span></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="new_ban_user" size="25" maxlength="25" /></span>
					</div>
				</div>
			</fieldset>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" class="button" name="add_ban" value=" <?php echo $lang_admin_bans['Add ban'] ?> " /></span>
			</div>
		</form>
	</div>
<?php

// Reset counters
$forum_page['group_count'] = $forum_page['item_count'] = 0;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_admin_bans['Existing bans heading'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
<?php

if (!empty($forum_bans))
{

?>
		<div class="ct-group">
<?php

	$forum_page['item_num'] = 0;
	foreach ($forum_bans as $ban_key => $cur_ban)
	{
		$forum_page['ban_info'] = array();
		$forum_page['ban_creator'] = ($cur_ban['ban_creator_username'] != '') ? '<a href="'.forum_link($forum_url['user'], $cur_ban['ban_creator']).'">'.forum_htmlencode($cur_ban['ban_creator_username']).'</a>' : $lang_admin_common['Unknown'];

		if ($cur_ban['username'] != '')
			$forum_page['ban_info']['username'] = '<li><span>'.$lang_admin_bans['Username'].'</span> <strong>'.forum_htmlencode($cur_ban['username']).'</strong></li>';

		if ($cur_ban['email'] != '')
			$forum_page['ban_info']['email'] = '<li><span>'.$lang_admin_bans['E-mail'].'</span> <strong>'.forum_htmlencode($cur_ban['email']).'</strong></li>';

		if ($cur_ban['ip'] != '')
			$forum_page['ban_info']['ip'] = '<li><span>'.$lang_admin_bans['IP-ranges'].'</span> <strong>'.$cur_ban['ip'].'</strong></li>';

		if ($cur_ban['expire'] != '')
			$forum_page['ban_info']['expire'] = '<li><span>'.$lang_admin_bans['Expires'].'</span> <strong>'.format_time($cur_ban['expire'], 1).'</strong></li>';

		if ($cur_ban['message'] != '')
			$forum_page['ban_info']['message'] ='<li><span>'.$lang_admin_bans['Message'].'</span> <strong>'.forum_htmlencode($cur_ban['message']).'</strong></li>';

		($hook = get_hook('aba_view_ban_pre_display')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
			<div class="ct-set group-item<?php echo ++$forum_page['item_num'] ?>">
				<div class="ct-box">
					<div class="ct-legend">
						<h3 class=""><span><?php printf($lang_admin_bans['Current ban head'], $forum_page['ban_creator']) ?></span></h3>
						<p><?php printf($lang_admin_bans['Edit or remove'], '<a href="'.forum_link($forum_url['admin_bans']).'?edit_ban='.$cur_ban['id'].'">'.$lang_admin_bans['Edit ban'].'</a>', '<a href="'.forum_link($forum_url['admin_bans']).'?del_ban='.$cur_ban['id'].'&amp;csrf_token='.generate_form_token('del_ban'.$cur_ban['id']).'">'.$lang_admin_bans['Remove ban'].'</a>') ?></p>
					</div>
<?php if (!empty($forum_page['ban_info'])): ?>				<ul>
					<?php echo implode("\n", $forum_page['ban_info'])."\n" ?>
					</ul>
<?php endif; ?>				</div>
			</div>
<?php

	}

?>
		</div>
<?php

}
else
{

?>
		<div class="ct-box">
			<p><?php echo $lang_admin_bans['No bans'] ?></p>
		</div>
<?php

}

?>
	</div>
<?php

($hook = get_hook('aba_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

$tpl_temp = trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
