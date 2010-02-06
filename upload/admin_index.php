<?php

/*---

	Copyright (C) 2008-2010 FluxBB.org
	based on code copyright (C) 2002-2005 Rickard Andersson
	License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher

---*/

// Tell header.php to use the admin template
define('PUN_ADMIN_CONSOLE', 1);

define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/common_admin.php';


if (!$pun_user['is_admmod'])
	message($lang_common['No permission']);


$action = isset($_GET['action']) ? $_GET['action'] : null;

// Check for upgrade
if ($action == 'check_upgrade')
{
	if (!ini_get('allow_url_fopen'))
		message('Unable to check for upgrade since \'allow_url_fopen\' is disabled on this system.');

	$latest_version = trim(@file_get_contents('http://fluxbb.org/latest_version'));
	if (empty($latest_version))
		message('Check for upgrade failed for unknown reasons.');

	if (version_compare($pun_config['o_cur_version'], $latest_version, '>='))
		message('You are running the latest version of FluxBB.');
	else
		message('A new version of FluxBB has been released. You can download the latest version at <a href="http://fluxbb.org/">FluxBB.org</a>.');
}


// Show phpinfo() output
else if ($action == 'phpinfo' && $pun_user['g_id'] == PUN_ADMIN)
{
	// Is phpinfo() a disabled function?
	if (strpos(strtolower((string) ini_get('disable_functions')), 'phpinfo') !== false)
		message('The PHP function phpinfo() has been disabled on this server.');

	phpinfo();
	exit;
}


// Get the server load averages (if possible)
if (@file_exists('/proc/loadavg') && is_readable('/proc/loadavg'))
{
	// We use @ just in case
	if (($fh = @fopen('/proc/loadavg', 'r')))
	{
		$load_averages = fread($fh, 64);
		fclose($fh);
	}
	else
		$load_averages = '';

	$load_averages = explode(' ', $load_averages);
	$server_load = isset($load_averages[2]) ? $load_averages[0].' '.$load_averages[1].' '.$load_averages[2] : 'Not available';
}
else if (!in_array(PHP_OS, array('WINNT', 'WIN32')) && preg_match('/averages?: ([0-9\.]+),?[\s]+([0-9\.]+),?[\s]+([0-9\.]+)/i', @exec('uptime'), $load_averages))
	$server_load = $load_averages[1].' '.$load_averages[2].' '.$load_averages[3];
else
	$server_load = 'Not available';


// Get number of current visitors
$result = $db->query('SELECT COUNT(user_id) FROM '.$db->prefix.'online WHERE idle=0') or error('Unable to fetch online count', __FILE__, __LINE__, $db->error());
$num_online = $db->result($result);


// Collect some additional info about MySQL
if ($db_type == 'mysql' || $db_type == 'mysqli' || $db_type == 'mysql_innodb' || $db_type == 'mysqli_innodb')
{
	// Calculate total db size/row count
	$result = $db->query('SHOW TABLE STATUS FROM `'.$db_name.'`') or error('Unable to fetch table status', __FILE__, __LINE__, $db->error());

	$total_records = $total_size = 0;
	while ($status = $db->fetch_assoc($result))
	{
		$total_records += $status['Rows'];
		$total_size += $status['Data_length'] + $status['Index_length'];
	}

	$total_size = file_size($total_size);
}


// Check for the existence of various PHP opcode caches/optimizers
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
	$php_accelerator = '<a href="http://xcache.lighttpd.net/">XCache</a>';
else
	$php_accelerator = 'N/A';


$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), 'Admin', 'Index');
require PUN_ROOT.'header.php';

generate_admin_menu('index');

?>
	<div class="block">
		<h2><span>Forum administration</span></h2>
		<div id="adintro" class="box">
			<div class="inbox">
				<p>Welcome to the FluxBB administration control panel. From here you can control vital aspects of the board. Depending on whether you are an administrator or a moderator you can:</p>
				<p>
					&nbsp;- Organize categories and forums.<br />
					&nbsp;- Set forum-wide options and preferences.<br />
					&nbsp;- Control permissions for users and guests.<br />
					&nbsp;- View IP statistics for users.<br />
					&nbsp;- Ban users.<br />
					&nbsp;- Censor words.<br />
					&nbsp;- Set up user ranks.<br />
					&nbsp;- Prune old posts.<br />
					&nbsp;- Handle post reports.
				</p>
			</div>
		</div>

		<h2 class="block2"><span>Statistics</span></h2>
		<div id="adstats" class="box">
			<div class="inbox">
				<dl>
					<dt>FluxBB version</dt>
					<dd>
						FluxBB <?php echo $pun_config['o_cur_version'] ?> - <a href="admin_index.php?action=check_upgrade">Check for upgrade</a><br />
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
						<?php echo implode(' ', $db->get_version())."\n" ?>
<?php if (isset($total_records) && isset($total_size)): ?>						<br />Rows: <?php echo forum_number_format($total_records)."\n" ?>
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
