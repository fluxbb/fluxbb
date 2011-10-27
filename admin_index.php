<?php

/**
 * Copyright (C) 2008-2011 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Tell header.php to use the admin template
define('PUN_ADMIN_CONSOLE', 1);

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/common_admin.php';


if (!$pun_user['is_admmod'])
	message($lang->t('No permission'));

// Load the admin_index.php language file
$lang->load('admin_index');

$action = isset($_GET['action']) ? $_GET['action'] : null;

// Check for upgrade
if ($action == 'check_upgrade')
{
	if (!ini_get('allow_url_fopen'))
		message($lang->t('fopen disabled message'));

	$latest_version = trim(@file_get_contents('http://fluxbb.org/latest_version'));
	if (empty($latest_version))
		message($lang->t('Upgrade check failed message'));

	if (version_compare($pun_config['o_cur_version'], $latest_version, '>='))
		message($lang->t('Running latest version message'));
	else
		message(sprintf($lang->t('New version available message'), '<a href="http://fluxbb.org/">FluxBB.org</a>'));
}


// Show phpinfo() output
else if ($action == 'phpinfo' && $pun_user['g_id'] == PUN_ADMIN)
{
	// Is phpinfo() a disabled function?
	if (strpos(strtolower((string) ini_get('disable_functions')), 'phpinfo') !== false)
		message($lang->t('PHPinfo disabled message'));

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

	if (($fh = @fopen('/proc/loadavg', 'r')))
	{
		$load_averages = fread($fh, 64);
		fclose($fh);
	}
	else
		$load_averages = '';

	$load_averages = @explode(' ', $load_averages);
	$server_load = isset($load_averages[2]) ? $load_averages[0].' '.$load_averages[1].' '.$load_averages[2] : $lang->t('Not available');
}
else if (!in_array(PHP_OS, array('WINNT', 'WIN32')) && preg_match('%averages?: ([0-9\.]+),?\s+([0-9\.]+),?\s+([0-9\.]+)%i', @exec('uptime'), $load_averages))
	$server_load = $load_averages[1].' '.$load_averages[2].' '.$load_averages[3];
else
	$server_load = $lang->t('Not available');


// Get number of current visitors
$query = $db->select(array('num_users' => 'COUNT(o.user_id) AS num_users'), 'online AS o');
$query->where = 'o.idle = 0';

$params = array();

$result = $query->run($params);
$num_online = $result[0]['num_users'];
unset ($result, $query, $params);


// Check for the existence of various PHP opcode caches/optimizers
if (function_exists('mmcache'))
	$php_accelerator = '<a href="http://'.$lang->t('Turck MMCache link').'">'.$lang->t('Turck MMCache').'</a>';
else if (isset($_PHPA))
	$php_accelerator = '<a href="http://'.$lang->t('ionCube PHP Accelerator link').'">'.$lang->t('ionCube PHP Accelerator').'</a>';
else if (ini_get('apc.enabled'))
	$php_accelerator ='<a href="http://'.$lang->t('Alternative PHP Cache (APC) link').'">'.$lang->t('Alternative PHP Cache (APC)').'</a>';
else if (ini_get('zend_optimizer.optimization_level'))
	$php_accelerator = '<a href="http://'.$lang->t('Zend Optimizer link').'">'.$lang->t('Zend Optimizer').'</a>';
else if (ini_get('eaccelerator.enable'))
	$php_accelerator = '<a href="http://'.$lang->t('eAccelerator link').'">'.$lang->t('eAccelerator').'</a>';
else if (ini_get('xcache.cacher'))
	$php_accelerator = '<a href="http://'.$lang->t('XCache link').'">'.$lang->t('XCache').'</a>';
else
	$php_accelerator = $lang->t('NA');


$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang->t('Admin'), $lang->t('Index'));
define('PUN_ACTIVE_PAGE', 'admin');
require PUN_ROOT.'header.php';

generate_admin_menu('index');

?>
	<div class="block">
		<h2><span><?php echo $lang->t('Forum admin head') ?></span></h2>
		<div id="adintro" class="box">
			<div class="inbox">
				<p><?php echo $lang->t('Welcome to admin') ?></p>
				<ul>
					<li><span><?php echo $lang->t('Welcome 1') ?></span></li>
					<li><span><?php echo $lang->t('Welcome 2') ?></span></li>
					<li><span><?php echo $lang->t('Welcome 3') ?></span></li>
					<li><span><?php echo $lang->t('Welcome 4') ?></span></li>
					<li><span><?php echo $lang->t('Welcome 5') ?></span></li>
					<li><span><?php echo $lang->t('Welcome 6') ?></span></li>
					<li><span><?php echo $lang->t('Welcome 7') ?></span></li>
					<li><span><?php echo $lang->t('Welcome 8') ?></span></li>
					<li><span><?php echo $lang->t('Welcome 9') ?></span></li>
				</ul>
			</div>
		</div>

		<h2 class="block2"><span><?php echo $lang->t('Statistics head') ?></span></h2>
		<div id="adstats" class="box">
			<div class="inbox">
				<dl>
					<dt><?php echo $lang->t('FluxBB version label') ?></dt>
					<dd>
						<?php printf($lang->t('FluxBB version data')."\n", $pun_config['o_cur_version'], '<a href="admin_index.php?action=check_upgrade">'.$lang->t('Check for upgrade').'</a>') ?>
					</dd>
					<dt><?php echo $lang->t('Server load label') ?></dt>
					<dd>
						<?php printf($lang->t('Server load data')."\n", $server_load, $num_online) ?>
					</dd>
<?php if ($pun_user['g_id'] == PUN_ADMIN): ?>					<dt><?php echo $lang->t('Environment label') ?></dt>
					<dd>
						<?php printf($lang->t('Environment data OS'), PHP_OS) ?><br />
						<?php printf($lang->t('Environment data version'), phpversion(), '<a href="admin_index.php?action=phpinfo">'.$lang->t('Show info').'</a>') ?><br />
						<?php printf($lang->t('Environment data acc')."\n", $php_accelerator) ?>
					</dd>
					<dt><?php echo $lang->t('Database label') ?></dt>
					<dd>
						<?php echo $db->getVersion()."\n" ?>
<?php if (isset($total_records) && isset($total_size)): ?>						<br /><?php printf($lang->t('Database data rows')."\n", forum_number_format($total_records)) ?>
						<br /><?php printf($lang->t('Database data size')."\n", $total_size) ?>
<?php endif; ?>					</dd>
<?php endif; ?>
				</dl>
			</div>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

require PUN_ROOT.'footer.php';
