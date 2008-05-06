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

($hook = get_hook('ain_start')) ? eval($hook) : null;

if (!$forum_user['is_admmod'])
	message($lang_common['No permission']);

// Load the admin.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin.php';


// Show phpinfo() output
if (isset($_GET['action']) && $_GET['action'] == 'phpinfo' && $forum_user['g_id'] == FORUM_ADMIN)
{
	($hook = get_hook('ain_phpinfo_selected')) ? eval($hook) : null;

	// Is phpinfo() a disabled function?
	if (strpos(strtolower((string)@ini_get('disable_functions')), 'phpinfo') !== false)
		message($lang_admin['phpinfo disabled']);

	phpinfo();
	exit;
}


// Generate check for updates text block
if ($forum_user['g_id'] == FORUM_ADMIN)
{
	if ($forum_config['o_check_for_updates'] == '1')
		$fluxbb_updates = $lang_admin['Check for updates enabled'];
	else
	{
		// Get a list of installed hotfix extensions
		$query = array(
			'SELECT'	=> 'e.id',
			'FROM'		=> 'extensions AS e',
			'WHERE'		=> 'e.id LIKE \'hotfix_%\''
		);

		($hook = get_hook('ain_qr_get_hotfixes')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		$num_hotfixes = $forum_db->num_rows($result);

		$hotfixes = array();
		for ($i = 0; $i < $num_hotfixes; ++$i)
			$hotfixes[] = urlencode($forum_db->result($result, $i));

		$fluxbb_updates = '<a href="http://fluxbb.org/update/?version='.urlencode($forum_config['o_cur_version']).'&amp;hotfixes='.implode(',', $hotfixes).'">'.$lang_admin['Check for updates manual'].'</a>';
	}
}


// Get the server load averages (if possible)
if (function_exists('sys_getloadavg') && is_array(sys_getloadavg()))
{
	$load_averages = sys_getloadavg();
	array_walk($load_averages, create_function('&$v', '$v = round($v, 3);'));
	$server_load = $load_averages[0].' '.$load_averages[1].' '.$load_averages[2];
}
else if (@file_exists('/proc/loadavg') && is_readable('/proc/loadavg'))
{
	// We use @ just in case
	$fh = @fopen('/proc/loadavg', 'r');
	$load_averages = @fread($fh, 64);
	@fclose($fh);

	$load_averages = @explode(' ', $load_averages);
	$server_load = isset($load_averages[2]) ? $load_averages[0].' '.$load_averages[1].' '.$load_averages[2] : 'Not available';
}
else if (!in_array(PHP_OS, array('WINNT', 'WIN32')) && preg_match('/averages?: ([0-9\.]+),[\s]+([0-9\.]+),[\s]+([0-9\.]+)/i', @exec('uptime'), $load_averages))
	$server_load = $load_averages[1].' '.$load_averages[2].' '.$load_averages[3];
else
	$server_load = $lang_admin['Not available'];


// Get number of current visitors
$query = array(
	'SELECT'	=> 'COUNT(o.user_id)',
	'FROM'		=> 'online AS o',
	'WHERE'		=> 'o.idle=0'
);

($hook = get_hook('ain_qr_get_users_online')) ? eval($hook) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
$num_online = $forum_db->result($result);


// Get the database system version
switch ($db_type)
{
	case 'sqlite':
		$db_version = 'SQLite '.sqlite_libversion();
		break;

	default:
		$result = $forum_db->query('SELECT VERSION()') or error(__FILE__, __LINE__);
		$db_version = $forum_db->result($result);
		break;
}


// Collect some additional info about MySQL
if ($db_type == 'mysql' || $db_type == 'mysqli')
{
	$db_version = 'MySQL '.$db_version;

	// Calculate total db size/row count
	$result = $forum_db->query('SHOW TABLE STATUS FROM `'.$db_name.'` LIKE \''.$db_prefix.'%\'') or error(__FILE__, __LINE__);

	$total_records = $total_size = 0;
	while ($status = $forum_db->fetch_assoc($result))
	{
		$total_records += $status['Rows'];
		$total_size += $status['Data_length'] + $status['Index_length'];
	}

	$total_size = $total_size / 1024;

	if ($total_size > 1024)
		$total_size = round($total_size / 1024, 2).' MB';
	else
		$total_size = round($total_size, 2).' KB';
}


// Check for the existance of various PHP opcode caches/optimizers
if (function_exists('mmcache'))
	$php_accelerator = '<a href="http://turck-mmcache.sourceforge.net/">Turck MMCache</a>';
else if (isset($_PHPA))
	$php_accelerator = '<a href="http://www.php-accelerator.co.uk/">ionCube PHP Accelerator</a>';
else if (ini_get('apc.enabled'))
	$php_accelerator ='<a href="http://www.php.net/apc/">Alternative PHP Cache (APC)</a>';
else if (ini_get('zend_optimizer.optimization_level'))
	$php_accelerator = '<a href="http://www.zend.com/products/zend_optimizer/">Zend Optimizer</a>';
else if (ini_get('eaccelerator.enable'))
	$php_accelerator = '<a href="http://eaccelerator.net/">eAccelerator</a>';
else if (ini_get('xcache.cacher'))
	$php_accelerator = '<a href="http://trac.lighttpd.net/xcache/">XCache</a>';
else
	$php_accelerator = $lang_admin['Not applicable'];

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($lang_admin['Forum administration'], forum_link($forum_url['admin_index'])),
	$lang_admin['Information']
);

($hook = get_hook('ain_pre_header_load')) ? eval($hook) : null;

define('FORUM_PAGE_SECTION', 'start');
define('FORUM_PAGE', 'admin-information');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

?>
<div id="brd-main" class="main sectioned admin">

<?php echo generate_admin_menu(); ?>

	<div class="main-head">
		<h1><span>{ <?php echo end($forum_page['crumbs']) ?> }</span></h1>
	</div>

	<div class="main-content frm">
		<div class="frm-head">
			<h2><span><?php echo $lang_admin['Information head'] ?></span></h2>
		</div>
<?php if (!empty($alert_items)): ?>		<div class="datagrid">
			<div id="admin-alerts" class="idx-item databox db1">
				<h3 class="legend warn"><span><?php echo $lang_admin['Alerts'] ?></span></h3>
				<div class="data">
					<?php echo implode(' ',$alert_items) ?>
				</div>
			</div>
		</div>
<?php endif; ?>		<div class="datagrid">
<?php ($hook = get_hook('ain_pre_version')) ? eval($hook) : null; ?>
			<div class="idx-item databox db1">
				<h3 class="legend"><span><?php echo $lang_admin['FluxBB version'] ?></span></h3>
				<ul class="data">
					<li><span>FluxBB <?php echo $forum_config['o_cur_version'] ?></span></li>
					<li><span>&copy; Copyright 2008 <a href="http://fluxbb.org/">FluxBB.org</a></span></li>
<?php if (isset($fluxbb_updates)): ?>					<li><span><?php echo $fluxbb_updates ?></span></li>
<?php endif; ?>				</ul>
			</div>
<?php ($hook = get_hook('ain_pre_server_load')) ? eval($hook) : null; ?>
			<div class="idx-item databox">
				<h3 class="legend"><span><?php echo $lang_admin['Server load'] ?></span></h3>
				<p class="data"><?php echo $server_load ?> (<?php echo $num_online.' '.$lang_admin['users online']?>)</p>
			</div>
<?php ($hook = get_hook('ain_pre_environment')) ? eval($hook) : null; if ($forum_user['g_id'] == FORUM_ADMIN): ?>					<div class="idx-item databox">
				<h3 class="legend"><span><?php echo $lang_admin['Environment'] ?></span></h3>
				<ul class="data">
					<li><span><?php echo $lang_admin['Operating system'] ?>: <?php echo PHP_OS ?></span></li>
					<li><span>PHP: <?php echo PHP_VERSION ?> - <a href="<?php echo forum_link($forum_url['admin_index']) ?>?action=phpinfo"><?php echo $lang_admin['Show info'] ?></a></span></li>
					<li><span><?php echo $lang_admin['Accelerator'] ?>: <?php echo $php_accelerator ?></span></li>
				</ul>
			</div>
<?php ($hook = get_hook('ain_pre_database')) ? eval($hook) : null; ?>
			<div class="idx-item databox">
				<h3 class="legend"><span><?php echo $lang_admin['Database'] ?></span></h3>
				<ul class="data">
					<li><span><?php echo $db_version ?></span></li>
<?php if (isset($total_records) && isset($total_size)): ?>						<li><span><?php echo $lang_admin['Rows'] ?>: <?php echo $total_records ?></span></li>
					<li><span><?php echo $lang_admin['Size'] ?>: <?php echo $total_size ?></span></li>
				</ul>
			</div>
<?php endif; endif; ($hook = get_hook('ain_items_end')) ? eval($hook) : null; ?>			</div>
	</div>

</div>
<?php

$tpl_temp = trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
