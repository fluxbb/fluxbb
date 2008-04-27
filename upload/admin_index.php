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


// Tell header.php to use the admin template
define('PUN_ADMIN_CONSOLE', 1);

define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/common_admin.php';


if ($pun_user['g_id'] > PUN_MOD)
	message($lang_common['No permission']);


$action = isset($_GET['action']) ? $_GET['action'] : null;

// Check for upgrade
if ($action == 'check_upgrade')
{
	if (!ini_get('allow_url_fopen'))
		message('Unable to check for upgrade since \'allow_url_fopen\' is disabled on this system.');

	$fp = @fopen('http://www.punbb.org/latest_version', 'r');
	$latest_version = trim(@fread($fp, 16));
	@fclose($fp);

	if ($latest_version == '')
		message('Check for upgrade failed for unknown reasons.');

	$cur_version = str_replace(array('.', 'dev', 'beta', ' '), '', strtolower($pun_config['o_cur_version']));
	$cur_version = (strlen($cur_version) == 2) ? intval($cur_version) * 10 : intval($cur_version);

	$latest_version = str_replace('.', '', strtolower($latest_version));
	$latest_version = (strlen($latest_version) == 2) ? intval($latest_version) * 10 : intval($latest_version);

	if ($cur_version >= $latest_version)
		message('You are running the latest version of PunBB.');
	else
		message('A new version of PunBB has been released. You can download the latest version at <a href="http://www.punbb.org/">PunBB.org</a>.');
}


// Show phpinfo() output
else if ($action == 'phpinfo' && $pun_user['g_id'] == PUN_ADMIN)
{
	// Is phpinfo() a disabled function?
	if (strpos(strtolower((string)@ini_get('disable_functions')), 'phpinfo') !== false)
		message('The PHP function phpinfo() has been disabled on this server.');

	phpinfo();
	exit;
}


// Get the server load averages (if possible)
if (@file_exists('/proc/loadavg') && is_readable('/proc/loadavg'))
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
	$server_load = 'Not available';


// Get number of current visitors
$result = $db->query('SELECT COUNT(user_id) FROM '.$db->prefix.'online WHERE idle=0') or error('Unable to fetch online count', __FILE__, __LINE__, $db->error());
$num_online = $db->result($result);


// Get the database system version
switch ($db_type)
{
	case 'sqlite':
		$db_version = 'SQLite '.sqlite_libversion();
		break;

	default:
		$result = $db->query('SELECT VERSION()') or error('Unable to fetch version info', __FILE__, __LINE__, $db->error());
		$db_version = $db->result($result);
		break;
}


// Collect some additional info about MySQL
if ($db_type == 'mysql' || $db_type == 'mysqli')
{
	$db_version = 'MySQL '.$db_version;

	// Calculate total db size/row count
	$result = $db->query('SHOW TABLE STATUS FROM `'.$db_name.'`') or error('Unable to fetch table status', __FILE__, __LINE__, $db->error());

	$total_records = $total_size = 0;
	while ($status = $db->fetch_assoc($result))
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


// See if MMCache or PHPA is loaded
if (function_exists('mmcache'))
	$php_accelerator = '<a href="http://turck-mmcache.sourceforge.net/">Turck MMCache</a>';
else if (isset($_PHPA))
	$php_accelerator = '<a href="http://www.php-accelerator.co.uk/">ionCube PHP Accelerator</a>';
else
	$php_accelerator = 'N/A';


$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / Admin';
require PUN_ROOT.'header.php';

generate_admin_menu('index');

?>
	<div class="block">
		<h2>Forum administration</h2>
		<div id="adintro" class="box">
			<div class="inbox">
				<p>
					Welcome to the PunBB administration control panel. From here you can control vital aspects of the forum. Depending on whether you are an administrator or a moderator you can<br /><br />
					&nbsp;- organize categories and forums.<br />
					&nbsp;- set forum-wide options and preferences.<br />
					&nbsp;- control permissions for users and guests.<br />
					&nbsp;- view IP statistics for users.<br />
					&nbsp;- ban users.<br />
					&nbsp;- censor words.<br />
					&nbsp;- set up user ranks.<br />
					&nbsp;- prune old posts.<br />
					&nbsp;- handle post reports.
				</p>
			</div>
		</div>

		<h2 class="block2"><span>Statistics</span></h2>
		<div id="adstats" class="box">
			<div class="inbox">
				<dl>
					<dt>PunBB version</dt>
					<dd>
						PunBB <?php echo $pun_config['o_cur_version'] ?> - <a href="admin_index.php?action=check_upgrade">Check for upgrade</a><br />
						&copy; Copyright 2002, 2003, 2004, 2005 Rickard Andersson
					</dd>
					<dt>Server load</dt>
					<dd>
						<?php echo $server_load ?> (<?php echo $num_online ?> users online)
					</dd>
<?php if ($pun_user['g_id'] == PUN_ADMIN): ?>					<dt>Environment</dt>
					<dd>
						Operating system: <?php echo PHP_OS ?><br />
						PHP: <?php echo phpversion() ?> - <a href="admin_index.php?action=phpinfo">Show info</a><br />
						Accelerator: <?php echo $php_accelerator."\n" ?>
					</dd>
					<dt>Database</dt>
					<dd>
						<?php echo $db_version."\n" ?>
<?php if (isset($total_records) && isset($total_size)): ?>						<br />Rows: <?php echo $total_records."\n" ?>
						<br />Size: <?php echo $total_size."\n" ?>
<?php endif; endif; ?>					</dd>
				</dl>
			</div>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

require PUN_ROOT.'footer.php';
