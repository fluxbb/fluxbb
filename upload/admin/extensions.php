<?php
/**
 * Extension and hotfix management page
 *
 * Allows administrators to control the extensions and hotfixes installed in the site.
 *
 * @copyright Copyright (C) 2008 FluxBB.org, based on code copyright (C) 2002-2008 PunBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package FluxBB
 */


if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', '../');
require FORUM_ROOT.'include/common.php';
require FORUM_ROOT.'include/common_admin.php';

if (!defined('FORUM_XML_FUNCTIONS_LOADED'))
	require FORUM_ROOT.'include/xml.php';

($hook = get_hook('aex_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

if ($forum_user['g_id'] != FORUM_ADMIN)
	message($lang_common['No permission']);

// Load the admin.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin_common.php';
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin_ext.php';

// Make sure we have XML support
if (!function_exists('xml_parser_create'))
	message($lang_admin_ext['No XML support']);

$section = isset($_GET['section']) ? $_GET['section'] : null;


// Install an extension
if (isset($_GET['install']) || isset($_GET['install_hotfix']))
{
	($hook = get_hook('aex_install_selected')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	// User pressed the cancel button
	if (isset($_POST['install_cancel']))
		redirect(forum_link($forum_url['admin_extensions_install']), $lang_admin_common['Cancel redirect']);

	$id = preg_replace('/[^0-9a-z_]/', '', isset($_GET['install']) ? $_GET['install'] : $_GET['install_hotfix']);

	// Load manifest (either locally or from fluxbb.org updates service)
	if (isset($_GET['install']))
		$manifest = @file_get_contents(FORUM_ROOT.'extensions/'.$id.'/manifest.xml');
	else
		$manifest = @end(get_remote_file('http://fluxbb.org/update/manifest/'.$id.'.xml', 16));

	// Parse manifest.xml into an array and validate it
	$ext_data = xml_to_array($manifest);
	$errors = validate_manifest($ext_data, $id);

	if (!empty($errors))
		message(isset($_GET['install']) ? $lang_common['Bad request'] : $lang_admin_ext['Hotfix download failed']);

	// Make sure we have an array of dependencies
	if (!isset($ext_data['extension']['dependencies']['dependency']))
		$ext_data['extension']['dependencies'] = array();
	else if (!is_array(current($ext_data['extension']['dependencies'])))
		$ext_data['extension']['dependencies'] = array($ext_data['extension']['dependencies']['dependency']);
	else
		$ext_data['extension']['dependencies'] = $ext_data['extension']['dependencies']['dependency'];

	$query = array(
		'SELECT'	=> 'e.id',
		'FROM'		=> 'extensions AS e',
		'WHERE'		=> 'e.disabled=0'
	);

	($hook = get_hook('aex_install_check_dependencies')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$installed_ext = array();
	while ($row = $forum_db->fetch_assoc($result))
		$installed_ext[] = $row['id'];

	foreach ($ext_data['extension']['dependencies'] as $dependency)
	{
		if (!in_array($dependency, $installed_ext))
			message(sprintf($lang_admin_ext['Missing dependency'], $dependency));
	}

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
		array((strpos($id, 'hotfix_') === 0) ? $lang_admin_common['Manage hotfixes'] : $lang_admin_common['Manage extensions'], (strpos($id, 'hotfix_') === 0) ? forum_link($forum_url['admin_extensions_hotfixes']) : forum_link($forum_url['admin_extensions_manage'])),
		(strpos($id, 'hotfix_') === 0) ? $lang_admin_ext['Install hotfix'] : $lang_admin_ext['Install extension']
	);

	if (isset($_POST['install_comply']))
	{
		($hook = get_hook('aex_install_comply_form_submitted')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		// $ext_info contains some information about the extension being installed
		$ext_info = array(
			'id'			=> $id,
			'path'			=> FORUM_ROOT.'extensions/'.$id,
			'url'			=> $base_url.'/extensions/'.$id,
			'dependencies'	=> array()
		);

		foreach ($ext_data['extension']['dependencies'] as $dependency)
		{
			$ext_info['dependencies'][$dependency] = array(
				'id'	=> $dependency,
				'path'	=> FORUM_ROOT.'extensions/'.$dependency,
				'url'	=> $base_url.'/extensions/'.$dependency,
			);
		}

		// Is there some uninstall code to store in the db?
		$uninstall_code = (isset($ext_data['extension']['uninstall']) && forum_trim($ext_data['extension']['uninstall']) != '') ? '\''.$forum_db->escape(forum_trim($ext_data['extension']['uninstall'])).'\'' : 'NULL';

		// Is there an uninstall note to store in the db?
		$uninstall_note = 'NULL';
		foreach ($ext_data['extension']['note'] as $cur_note)
		{
			if ($cur_note['attributes']['type'] == 'uninstall' && forum_trim($cur_note['content']) != '')
				$uninstall_note = '\''.$forum_db->escape(forum_trim($cur_note['content'])).'\'';
		}

		$notices = array();

		// Is this a fresh install or an upgrade?
		$query = array(
			'SELECT'	=> 'e.version',
			'FROM'		=> 'extensions AS e',
			'WHERE'		=> 'e.id=\''.$forum_db->escape($id).'\''
		);

		($hook = get_hook('aex_install_comply_qr_get_current_ext_version')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		if ($forum_db->num_rows($result))
		{
			// EXT_CUR_VERSION will be available to the extension install routine (to facilitate extension upgrades)
			define('EXT_CUR_VERSION', $forum_db->result($result));

			// Run the author supplied install code
			if (isset($ext_data['extension']['install']) && forum_trim($ext_data['extension']['install']) != '')
				eval($ext_data['extension']['install']);

			// Update the existing extension
			$query = array(
				'UPDATE'	=> 'extensions',
				'SET'		=> 'title=\''.$forum_db->escape($ext_data['extension']['title']).'\', version=\''.$forum_db->escape($ext_data['extension']['version']).'\', description=\''.$forum_db->escape($ext_data['extension']['description']).'\', author=\''.$forum_db->escape($ext_data['extension']['author']).'\', uninstall='.$uninstall_code.', uninstall_note='.$uninstall_note.', dependencies=\'|'.implode('|', $ext_data['extension']['dependencies']).'|\'',
				'WHERE'		=> 'id=\''.$forum_db->escape($id).'\''
			);

			($hook = get_hook('aex_install_comply_qr_update_ext')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);

			// Delete the old hooks
			$query = array(
				'DELETE'	=> 'extension_hooks',
				'WHERE'		=> 'extension_id=\''.$forum_db->escape($id).'\''
			);

			($hook = get_hook('aex_install_comply_qr_update_ext_delete_hooks')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);
		}
		else
		{
			// Run the author supplied install code
			if (isset($ext_data['extension']['install']) && forum_trim($ext_data['extension']['install']) != '')
				eval($ext_data['extension']['install']);

			// Add the new extension
			$query = array(
				'INSERT'	=> 'id, title, version, description, author, uninstall, uninstall_note, dependencies',
				'INTO'		=> 'extensions',
				'VALUES'	=> '\''.$forum_db->escape($ext_data['extension']['id']).'\', \''.$forum_db->escape($ext_data['extension']['title']).'\', \''.$forum_db->escape($ext_data['extension']['version']).'\', \''.$forum_db->escape($ext_data['extension']['description']).'\', \''.$forum_db->escape($ext_data['extension']['author']).'\', '.$uninstall_code.', '.$uninstall_note.', \'|'.implode('|', $ext_data['extension']['dependencies']).'|\'',
			);

			($hook = get_hook('aex_install_comply_qr_add_ext')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);
		}

		// Now insert the hooks
		foreach ($ext_data['extension']['hooks']['hook'] as $ext_hook)
		{
			$cur_hooks = explode(',', $ext_hook['attributes']['id']);
			foreach ($cur_hooks as $cur_hook)
			{
				$query = array(
					'INSERT'	=> 'id, extension_id, code, installed, priority',
					'INTO'		=> 'extension_hooks',
					'VALUES'	=> '\''.$forum_db->escape(forum_trim($cur_hook)).'\', \''.$forum_db->escape($id).'\', \''.$forum_db->escape(forum_trim($ext_hook['content'])).'\', '.time().', '.(isset($ext_hook['attributes']['priority']) ? $ext_hook['attributes']['priority'] : 5)
				);

				($hook = get_hook('aex_install_comply_qr_add_hook')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
				$forum_db->query_build($query) or error(__FILE__, __LINE__);
			}
		}

		// Empty the PHP cache
		forum_clear_cache();

		// Regenerate the hooks cache
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			require FORUM_ROOT.'include/cache.php';

		generate_hooks_cache();

		// Display notices if there are any
		if (!empty($notices))
		{
			($hook = get_hook('aex_install_notices_pre_header_load')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

			define('FORUM_PAGE_SECTION', 'extensions');
			if (strpos($id, 'hotfix_') === 0)
				define('FORUM_PAGE', 'admin-extensions-hotfixes');
			else
				define('FORUM_PAGE', 'admin-extensions-manage');
			define('FORUM_PAGE_TYPE', 'sectioned');
			require FORUM_ROOT.'header.php';

			// START SUBST - <!-- forum_main -->
			ob_start();

			($hook = get_hook('aex_install_notices_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo end($forum_page['crumbs']) ?> "<?php echo forum_htmlencode($ext_data['extension']['title']) ?>"</span></h2>
	</div>
	<div class="main-content main-frm">
		<div class="ct-box info-box">
			<p><?php echo $lang_admin_ext['Extension installed info'] ?></p>
			<ul class="data-list">
<?php

			while (list(, $cur_notice) = each($notices))
				echo "\t\t\t\t".'<li><span>'.$cur_notice.'</span></li>'."\n";

?>
			</ul>
			<p><a href="<?php echo forum_link($forum_url['admin_extensions_manage']) ?>"><?php echo $lang_admin_common['Manage extensions'] ?></a></p>
		</div>
	</div>
<?php

			($hook = get_hook('aex_install_notices_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

			$tpl_temp = forum_trim(ob_get_contents());
			$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
			ob_end_clean();
			// END SUBST - <!-- forum_main -->

			require FORUM_ROOT.'footer.php';
		}
		else
		{
			($hook = get_hook('aex_install_comply_pre_redirect')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

			if (strpos($id, 'hotfix_') === 0)
				redirect(forum_link($forum_url['admin_extensions_hotfixes']), $lang_admin_ext['Hotfix installed'].' '.$lang_admin_common['Redirect']);
			else
				redirect(forum_link($forum_url['admin_extensions_manage']), $lang_admin_ext['Extension installed'].' '.$lang_admin_common['Redirect']);
		}
	}


	($hook = get_hook('aex_install_pre_header_load')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	define('FORUM_PAGE_SECTION', 'extensions');
	if (strpos($id, 'hotfix_') === 0)
		define('FORUM_PAGE', 'admin-extensions-hotfixes');
	else
		define('FORUM_PAGE', 'admin-extensions-manage');
	define('FORUM_PAGE_TYPE', 'sectioned');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

	($hook = get_hook('aex_install_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo end($forum_page['crumbs']) ?> "<?php echo forum_htmlencode($ext_data['extension']['title']) ?>"</span></h2>
	</div>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $base_url.'/admin/extensions.php'.(isset($_GET['install']) ? '?install=' : '?install_hotfix=').$id ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token($base_url.'/admin/extensions.php'.(isset($_GET['install']) ? '?install=' : '?install_hotfix=').$id) ?>" />
			</div>
			<div class="ct-box info-box">
				<h3 class="ct-legend hn"><span><?php echo forum_htmlencode($ext_data['extension']['title']) ?></span></h3>
				<ul class="data-list">
					<li><span><?php printf($lang_admin_ext['Extension by'], forum_htmlencode($ext_data['extension']['author'])) ?></span></li>
					<li><span><?php  echo ((strpos($id, 'hotfix_') !== 0) ? sprintf($lang_admin_ext['Version'], $ext_data['extension']['version']) : $lang_admin_ext['Hotfix']) ?></span></li>
					<li><span><?php echo forum_htmlencode($ext_data['extension']['description']) ?></span></li>
				</ul>
<?php

	// Setup an array of warnings to display in the form
	$form_warnings = array();
	$forum_page['num_items'] = 0;

	foreach ($ext_data['extension']['note'] as $cur_note)
	{
		if ($cur_note['attributes']['type'] == 'install')
			$form_warnings[] = '<p>'.++$forum_page['num_items'].'. '.forum_htmlencode($cur_note['content']).'</p>';
	}

	if (version_compare(clean_version($forum_config['o_cur_version']), clean_version($ext_data['extension']['maxtestedon']), '>'))
		$form_warnings[] = '<p>'.++$forum_page['num_items'].'. '.$lang_admin_ext['Maxtestedon warning'].'</p>';

	if (!empty($form_warnings))
	{

?>
				<h4 class="note"><?php echo $lang_admin_ext['Install note'] ?></h4>
<?php

		echo implode("\n\t\t\t\t\t", $form_warnings)."\n";
	}

?>
			</div>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="install_comply" value="<?php echo ((strpos($id, 'hotfix_') !== 0) ? $lang_admin_ext['Install extension'] : $lang_admin_ext['Install hotfix']) ?>" /></span>
				<span class="cancel"><input type="submit" name="install_cancel" value="<?php echo $lang_admin_common['Cancel'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

	($hook = get_hook('aex_install_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	$tpl_temp = forum_trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}


// Uninstall an extension
else if (isset($_GET['uninstall']))
{
	// User pressed the cancel button
	if (isset($_POST['uninstall_cancel']))
		redirect(forum_link($forum_url['admin_extensions_manage']), $lang_admin_common['Cancel redirect']);

	($hook = get_hook('aex_uninstall_selected')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	$id = preg_replace('/[^0-9a-z_]/', '', $_GET['uninstall']);

	// Fetch info about the extension
	$query = array(
		'SELECT'	=> 'e.title, e.version, e.description, e.author, e.uninstall, e.uninstall_note',
		'FROM'		=> 'extensions AS e',
		'WHERE'		=> 'e.id=\''.$forum_db->escape($id).'\''
	);

	($hook = get_hook('aex_uninstall_qr_get_extension')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	if (!$forum_db->num_rows($result))
		message($lang_common['Bad request']);

	$ext_data = $forum_db->fetch_assoc($result);

	// Check dependancies
	$query = array(
		'SELECT'	=> 'e.id',
		'FROM'		=> 'extensions AS e',
		'WHERE'		=> 'e.dependencies LIKE \'%|'.$forum_db->escape($id).'|%\''
	);

	($hook = get_hook('aex_uninstall_qr_check_dependencies')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	if ($forum_db->num_rows($result) != 0)
	{
		$dependency = $forum_db->fetch_assoc($result);
		message(sprintf($lang_admin_ext['Uninstall dependency'], $dependency['id']));
	}

	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
		array((strpos($id, 'hotfix_') === 0) ? $lang_admin_common['Manage hotfixes'] : $lang_admin_common['Manage extensions'], (strpos($id, 'hotfix_') === 0) ? forum_link($forum_url['admin_extensions_hotfixes']) : forum_link($forum_url['admin_extensions_manage'])),
		(strpos($id, 'hotfix_') === 0) ? $lang_admin_ext['Uninstall hotfix'] : $lang_admin_ext['Uninstall extension']
	);

	// If the user has confirmed the uninstall
	if (isset($_POST['uninstall_comply']))
	{
		($hook = get_hook('aex_uninstall_comply_form_submitted')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		$notices = array();

		// Run uninstall code
		eval($ext_data['uninstall']);

		// Now delete the extension and its hooks from the db
		$query = array(
			'DELETE'	=> 'extension_hooks',
			'WHERE'		=> 'extension_id=\''.$forum_db->escape($id).'\''
		);

		($hook = get_hook('aex_uninstall_comply_qr_uninstall_delete_hooks')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		$query = array(
			'DELETE'	=> 'extensions',
			'WHERE'		=> 'id=\''.$forum_db->escape($id).'\''
		);

		($hook = get_hook('aex_uninstall_comply_qr_delete_extension')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// Empty the PHP cache
		forum_clear_cache();

		// Regenerate the hooks cache
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			require FORUM_ROOT.'include/cache.php';

		generate_hooks_cache();

		// Display notices if there are any
		if (!empty($notices))
		{
			($hook = get_hook('aex_uninstall_notices_pre_header_load')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

			define('FORUM_PAGE_SECTION', 'extensions');
			define('FORUM_PAGE', 'admin-extensions-manage');
			define('FORUM_PAGE_TYPE', 'sectioned');
			require FORUM_ROOT.'header.php';

			// START SUBST - <!-- forum_main -->
			ob_start();

			($hook = get_hook('aex_uninstall_notices_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo end($forum_page['crumbs']) ?> "<?php echo forum_htmlencode($ext_data['title']) ?>"</span></h2>
	</div>
	<div class="main-content main-frm">
		<div class="ct-box info-box">
			<p><?php echo $lang_admin_ext['Extension uninstalled info'] ?></p>
			<ul class="info-list">
<?php

			while (list(, $cur_notice) = each($notices))
				echo "\t\t\t\t".'<li><span>'.$cur_notice.'</span></li>'."\n";

?>
			</ul>
			<p><a href="<?php echo forum_link($forum_url['admin_extensions_manage']) ?>"><?php echo $lang_admin_common['Manage extensions'] ?></a></p>
		</div>
	</div>
<?php

			($hook = get_hook('aex_uninstall_notices_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

			$tpl_temp = forum_trim(ob_get_contents());
			$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
			ob_end_clean();
			// END SUBST - <!-- forum_main -->

			require FORUM_ROOT.'footer.php';
		}
		else
		{
			($hook = get_hook('aex_uninstall_comply_pre_redirect')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

			if (strpos($id, 'hotfix_') === 0)
				redirect(forum_link($forum_url['admin_extensions_hotfixes']), $lang_admin_ext['Hotfix uninstalled'].' '.$lang_admin_common['Redirect']);
			else
				redirect(forum_link($forum_url['admin_extensions_manage']), $lang_admin_ext['Extension uninstalled'].' '.$lang_admin_common['Redirect']);
		}
	}
	else	// If the user hasn't confirmed the uninstall
	{
		($hook = get_hook('aex_uninstall_pre_header_load')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		define('FORUM_PAGE_SECTION', 'extensions');
		if (strpos($id, 'hotfix_') === 0)
			define('FORUM_PAGE', 'admin-extensions-hotfixes');
		else
			define('FORUM_PAGE', 'admin-extensions-manage');
		define('FORUM_PAGE_TYPE', 'sectioned');
		require FORUM_ROOT.'header.php';

		// START SUBST - <!-- forum_main -->
		ob_start();

		($hook = get_hook('aex_uninstall_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo end($forum_page['crumbs']) ?> "<?php echo forum_htmlencode($ext_data['title']) ?>"</span></h2>
	</div>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo $base_url ?>/admin/extensions.php?section=manage&amp;uninstall=<?php echo $id ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token($base_url.'/admin/extensions.php?section=manage&amp;uninstall='.$id) ?>" />
			</div>
			<div class="ct-box info-box">
				<h3 class="ct-legend hn"><span><?php echo forum_htmlencode($ext_data['title']) ?></span></h3>
				<ul class="data-list">
					<li><span><?php printf($lang_admin_ext['Extension by'], forum_htmlencode($ext_data['author'])) ?></span></li>
					<li><span><?php echo ((strpos($id, 'hotfix_') !== 0) ? sprintf($lang_admin_ext['Version'], $ext_data['version']) : $lang_admin_ext['Hotfix']) ?></span></li>
					<li><span><?php echo forum_htmlencode($ext_data['description']) ?></span></li>
<?php if ($ext_data['uninstall_note'] != ''): ?>				<h4><?php echo $lang_admin_ext['Uninstall note'] ?></h4>
				<p><?php echo forum_htmlencode($ext_data['uninstall_note']) ?></p>
<?php endif; ?>			</div>
<?php if (strpos($id, 'hotfix_') !== 0): ?>			<div class="ct-box warn-box">
				<p class="warn"><?php echo $lang_admin_ext['Installed extensions warn'] ?></p>
			</div>
<?php endif; ?>				<div class="frm-buttons">
				<span class="submit"><input type="submit" class="button" name="uninstall_comply" value="<?php echo $lang_admin_ext['Uninstall'] ?>" /></span>
				<span class="cancel"><input type="submit" class="button" name="uninstall_cancel" value="<?php echo $lang_admin_common['Cancel'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

		($hook = get_hook('aex_uninstall_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		$tpl_temp = forum_trim(ob_get_contents());
		$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
		ob_end_clean();
		// END SUBST - <!-- forum_main -->

		require FORUM_ROOT.'footer.php';
	}
}


// Enable or disable an extension
else if (isset($_GET['flip']))
{
	$id = preg_replace('/[^0-9a-z_]/', '', $_GET['flip']);

	// We validate the CSRF token. If it's set in POST and we're at this point, the token is valid.
	// If it's in GET, we need to make sure it's valid.
	if (!isset($_POST['csrf_token']) && (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== generate_form_token('flip'.$id)))
		csrf_confirm_form();

	($hook = get_hook('aex_flip_selected')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	// Fetch the current status of the extension
	$query = array(
		'SELECT'	=> 'e.disabled',
		'FROM'		=> 'extensions AS e',
		'WHERE'		=> 'e.id=\''.$forum_db->escape($id).'\''
	);

	($hook = get_hook('aex_flip_qr_get_disabled_status')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	if (!$forum_db->num_rows($result))
		message($lang_common['Bad request']);

	// Are we disabling or enabling?
	$disable = $forum_db->result($result) == '0';

	// Check dependancies
	if ($disable)
	{
		$query = array(
			'SELECT'	=> 'e.id',
			'FROM'		=> 'extensions AS e',
			'WHERE'		=> 'e.disabled=0 AND e.dependencies LIKE \'%|'.$forum_db->escape($id).'|%\''
		);

		($hook = get_hook('aex_flip_qr_get_disable_dependencies')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		if ($forum_db->num_rows($result) != 0)
		{
			$dependency = $forum_db->fetch_assoc($result);
			message(sprintf($lang_admin_ext['Disable dependency'], $dependency['id']));
		}
	}
	else
	{
		$query = array(
			'SELECT'	=> 'e.dependencies',
			'FROM'		=> 'extensions AS e',
			'WHERE'		=> 'e.id=\''.$forum_db->escape($id).'\''
		);

		($hook = get_hook('aex_flip_qr_get_enable_dependencies')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		$dependencies = $forum_db->fetch_assoc($result);
		$dependencies = explode('|', substr($dependencies['dependencies'], 1, -1));

		$query = array(
			'SELECT'	=> 'e.id',
			'FROM'		=> 'extensions AS e',
			'WHERE'		=> 'e.disabled=0'
		);

		($hook = get_hook('aex_flip_qr_check_dependencies')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		$installed_ext = array();
		while ($row = $forum_db->fetch_assoc($result))
			$installed_ext[] = $row['id'];

		foreach ($dependencies as $dependency)
		{
			if (!empty($dependency) && !in_array($dependency, $installed_ext))
				message(sprintf($lang_admin_ext['Disabled dependency'], $dependency));
		}
	}

	$query = array(
		'UPDATE'	=> 'extensions',
		'SET'		=> 'disabled='.($disable ? '1' : '0'),
		'WHERE'		=> 'id=\''.$forum_db->escape($id).'\''
	);

	($hook = get_hook('aex_flip_qr_update_disabled_status')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	// Regenerate the hooks cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';

	generate_hooks_cache();

	($hook = get_hook('aex_flip_pre_redirect')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	if ($section == 'hotfixes')
		redirect(forum_link($forum_url['admin_extensions_hotfixes']), ($disable ? $lang_admin_ext['Hotfix disabled'] : $lang_admin_ext['Hotfix enabled']).' '.$lang_admin_common['Redirect']);
	else
		redirect(forum_link($forum_url['admin_extensions_manage']), ($disable ? $lang_admin_ext['Extension disabled'] : $lang_admin_ext['Extension enabled']).' '.$lang_admin_common['Redirect']);
}

($hook = get_hook('aex_new_action')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;


// Generate an array of installed extensions
$inst_exts = array();
$query = array(
	'SELECT'	=> 'e.*',
	'FROM'		=> 'extensions AS e',
	'ORDER BY'	=> 'e.title'
);

($hook = get_hook('aex_qr_get_all_extensions')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
while ($cur_ext = $forum_db->fetch_assoc($result))
	$inst_exts[$cur_ext['id']] = $cur_ext;


if ($section == 'hotfixes')
{
	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
		$lang_admin_common['Manage hotfixes']
	);

	($hook = get_hook('aex_section_hotfixes_pre_header_load')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	define('FORUM_PAGE_SECTION', 'extensions');
	define('FORUM_PAGE', 'admin-extensions-hotfixes');
	define('FORUM_PAGE_TYPE', 'sectioned');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

	($hook = get_hook('aex_section_hotfixes_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_admin_ext['Hotfixes available'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
<?php

	$num_exts = 0;
	$num_failed = 0;
	$forum_page['item_num'] = 1;
	$forum_page['ext_item'] = array();
	$forum_page['ext_error'] = array();

	// Loop through any available hotfixes
	if (isset($forum_updates['hotfix']))
	{
		// If there's only one hotfix, add one layer of arrays so we can foreach over it
		if (!is_array(current($forum_updates['hotfix'])))
			$forum_updates['hotfix'] = array($forum_updates['hotfix']);

		foreach ($forum_updates['hotfix'] as $hotfix)
		{
			if (!array_key_exists($hotfix['attributes']['id'], $inst_exts))
			{
				$forum_page['ext_item'][] = '<div class="ct-box info-box">'."\n\t\t\t".'<h3 class="ct-legend hn"><span>'.forum_htmlencode($hotfix['content']).'</span></h3>'."\n\t\t\t".'<ul>'."\n\t\t\t\t".'<li><span>'.sprintf($lang_admin_ext['Extension by'], 'FluxBB').'</span></li>'."\n\t\t\t\t".'<li><span>'.$lang_admin_ext['Hotfix description'].'</span></li>'."\n\t\t\t".'</ul>'."\n\t\t\t\t".'<p class="options"><span class="item1"><a href="'.$base_url.'/admin/extensions.php?install_hotfix='.urlencode($hotfix['attributes']['id']).'">'.$lang_admin_ext['Install hotfix'].'</a></span></p>'."\n\t\t".'</div>';
				++$num_exts;
			}
		}
	}

	($hook = get_hook('aex_section_hotfixes_pre_display_ext_list')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	if ($num_exts)
		echo "\t\t".implode("\n\t\t", $forum_page['ext_item'])."\n";
	else
	{

?>
		<div class="ct-box info-box">
			<p><?php echo $lang_admin_ext['No available hotfixes'] ?></p>
		</div>
<?php

	}

?>
	</div>
<?php

	($hook = get_hook('aex_section_hotfixes_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_admin_ext['Installed hotfixes'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
<?php

	$installed_count = 0;
	while (list($id, $ext) = @each($inst_exts))
	{
		if (strpos($id, 'hotfix_') !== 0)
				continue;

		$forum_page['ext_actions'] = array(
			'flip'		=> '<span class="item1"><a href="'.$base_url.'/admin/extensions.php?section=hotfixes&amp;flip='.$id.'&amp;csrf_token='.generate_form_token('flip'.$id).'">'.($ext['disabled'] != '1' ? $lang_admin_ext['Disable'] : $lang_admin_ext['Enable']).'</a></span>',
			'uninstall'	=> '<span><a href="'.$base_url.'/admin/extensions.php?section=hotfixese&amp;uninstall='.$id.'">'.$lang_admin_ext['Uninstall'].'</a></span>'
		);

		($hook = get_hook('aex_section_hotfixes_pre_ext_actions')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
		<div class="ct-box info-box<?php if ($ext['disabled'] == '1') echo ' extdisabled' ?>">
			<h3 class="ct-legend hn"><span><?php echo forum_htmlencode($ext['title']) ?><?php if ($ext['disabled'] == '1') echo ' ( <span>'.$lang_admin_ext['Extension disabled'].'</span> )' ?></span></h3>
			<ul class="data-list">
				<li><span><?php printf($lang_admin_ext['Extension by'], forum_htmlencode($ext['author'])) ?></span></li>
				<li><span><?php echo ((strpos($id, 'hotfix_') !== 0) ? sprintf($lang_admin_ext['Version'], $ext['version']) : $lang_admin_ext['Hotfix']) ?></span></li>
<?php if ($ext['description'] != ''): ?>				<li><span><?php echo forum_htmlencode($ext['description']) ?></span></li>
<?php endif; ?>			</ul>
			<p class="options"><?php echo implode(' ', $forum_page['ext_actions']) ?></p>
		</div>
<?php
		$installed_count++;
	}

	if ($installed_count == 0)
	{

?>
		<div class="ct-box info-box">
			<p><?php echo $lang_admin_ext['No installed hotfixes'] ?></p>
		</div>
<?php

	}

?>
	</div>
<?php

	($hook = get_hook('aex_section_hotfixes_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	$tpl_temp = forum_trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}
else
{
	// Setup breadcrumbs
	$forum_page['crumbs'] = array(
		array($forum_config['o_board_title'], forum_link($forum_url['index'])),
		array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
		$lang_admin_common['Manage extensions']
	);

	($hook = get_hook('aex_section_manage_pre_header_load')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	define('FORUM_PAGE_SECTION', 'extensions');
	define('FORUM_PAGE', 'admin-extensions-manage');
	define('FORUM_PAGE_TYPE', 'sectioned');
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

	($hook = get_hook('aex_section_install_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_admin_ext['Extensions available'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
<?php

	$num_exts = 0;
	$num_failed = 0;
	$forum_page['item_num'] = 1;
	$forum_page['ext_item'] = array();
	$forum_page['ext_error'] = array();

	$d = dir(FORUM_ROOT.'extensions');
	while (($entry = $d->read()) !== false)
	{
		if ($entry{0} != '.' && is_dir(FORUM_ROOT.'extensions/'.$entry))
		{
			if (preg_match('/[^0-9a-z_]/', $entry))
			{
				$forum_page['ext_error'][] = '<div class="ext-error databox db'.++$forum_page['item_num'].'">'."\n\t\t\t\t".'<h3 class="legend"><span>'.sprintf($lang_admin_ext['Extension loading error'], forum_htmlencode($entry)).'<span></h3>'."\n\t\t\t\t".'<p>'.$lang_admin_ext['Illegal ID'].'</p>'."\n\t\t\t".'</div>';
				++$num_failed;
				continue;
			}
			else if (!file_exists(FORUM_ROOT.'extensions/'.$entry.'/manifest.xml'))
			{
				$forum_page['ext_error'][] = '<div class="ext-error databox db'.++$forum_page['item_num'].'">'."\n\t\t\t\t".'<h3 class="legend"><span>'.sprintf($lang_admin_ext['Extension loading error'], forum_htmlencode($entry)).'<span></h3>'."\n\t\t\t\t".'<p>'.$lang_admin_ext['Missing manifest'].'</p>'."\n\t\t\t".'</div>';
				++$num_failed;
				continue;
			}

			// Parse manifest.xml into an array
			$ext_data = xml_to_array(@file_get_contents(FORUM_ROOT.'extensions/'.$entry.'/manifest.xml'));
			if (empty($ext_data))
			{
				$forum_page['ext_error'][] = '<div class="ext-error databox db'.++$forum_page['item_num'].'">'."\n\t\t\t\t".'<h3 class="legend"><span>'.sprintf($lang_admin_ext['Extension loading error'], forum_htmlencode($entry)).'<span></h3>'."\n\t\t\t\t".'<p>'.$lang_admin_ext['Failed parse manifest'].'</p>'."\n\t\t\t".'</div>';
				++$num_failed;
				continue;
			}

			// Validate manifest
			$errors = validate_manifest($ext_data, $entry);
			if (!empty($errors))
			{
				$forum_page['ext_error'][] = '<div class="ext-error databox db'.++$forum_page['item_num'].'">'."\n\t\t\t\t".'<h3 class="legend"><span>'.sprintf($lang_admin_ext['Extension loading error'], forum_htmlencode($entry)).'</span></h3>'."\n\t\t\t\t".'<p>'.implode(' ', $errors).'</p>'."\n\t\t\t".'</div>';
				++$num_failed;
			}
			else
			{
				if (!array_key_exists($entry, $inst_exts) || version_compare($inst_exts[$entry]['version'], $ext_data['extension']['version'], '!='))
				{
					$forum_page['ext_item'][] = '<div class="ct-box info-box">'."\n\t\t\t".'<h3 class="ct-legend hn"><span>'.forum_htmlencode($ext_data['extension']['title']).'</span></h3>'."\n\t\t\t".'<ul class="data-list">'."\n\t\t\t\t".'<li><span>'.sprintf($lang_admin_ext['Extension by'], forum_htmlencode($ext_data['extension']['author'])).'</span></li>'."\n\t\t\t\t".'<li><span>'.sprintf($lang_admin_ext['Version'], $ext_data['extension']['version']).'</span></li>'.(($ext_data['extension']['description'] != '') ? "\n\t\t\t\t".'<li><span>'.forum_htmlencode($ext_data['extension']['description']).'</span></li>' : '')."\n\t\t\t".'</ul>'."\n\t\t\t".'<p class="options"><span class="item1"><a href="'.$base_url.'/admin/extensions.php?install='.urlencode($entry).'">'.(isset($inst_exts[$entry]['version']) ? $lang_admin_ext['Upgrade extension'] : $lang_admin_ext['Install extension']).'</a></span></p>'."\n\t\t".'</div>';
					++$num_exts;
				}
			}
		}
	}
	$d->close();

	($hook = get_hook('aex_section_install_pre_display_ext_list')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	if ($num_exts)
		echo "\t\t".implode("\n\t\t", $forum_page['ext_item'])."\n";
	else
	{

?>
		<div class="ct-box info-box">
			<p><?php echo $lang_admin_ext['No available extensions'] ?></p>
		</div>
<?php

	}

	// If any of the extensions had errors
	if ($num_failed)
	{

?>
		<div class="ct-box data-box">
			<p class="important"><?php echo $lang_admin_ext['Invalid extensions'] ?></p>
			<?php echo implode("\n\t\t\t", $forum_page['ext_error'])."\n" ?>
		</div>
<?php

	}

?>
	</div>
<?php

	($hook = get_hook('aex_section_manage_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_admin_ext['Installed extensions'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
<?php

	$installed_count = 0;
	$forum_page['ext_item'] = array();
	while (list($id, $ext) = @each($inst_exts))
	{
		if (strpos($id, 'hotfix_') === 0)
			continue;

		$forum_page['ext_actions'] = array(
			'flip'		=> '<span class="item1"><a href="'.$base_url.'/admin/extensions.php?section=manage&amp;flip='.$id.'&amp;csrf_token='.generate_form_token('flip'.$id).'">'.($ext['disabled'] != '1' ? $lang_admin_ext['Disable'] : $lang_admin_ext['Enable']).'</a></span>',
			'uninstall'	=> '<span><a href="'.$base_url.'/admin/extensions.php?section=manage&amp;uninstall='.$id.'">'.$lang_admin_ext['Uninstall'].'</a></span>'
		);

		($hook = get_hook('aex_section_manage_pre_ext_actions')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		if ($ext['disabled'] == '1')
			$forum_page['ext_item'][] = '<div class="ct-box info-box extdisabled">'."\n\t\t".'<h3 class="ct-legend hn"><span>'.forum_htmlencode($ext['title']).' ( <span>'.$lang_admin_ext['Extension disabled'].'</span> )</span></h3>'."\n\t\t".'<ul class="data-list">'."\n\t\t\t".'<li><span>'.sprintf($lang_admin_ext['Extension by'], forum_htmlencode($ext['author'])).'</span></li>'."\n\t\t\t".'<li><span>'.sprintf($lang_admin_ext['Version'], $ext['version']).'</span></li>'."\n\t\t\t".(($ext['description'] != '') ? '<li><span>'.forum_htmlencode($ext['description']).'</span></li>' : '')."\n\t\t\t".'</ul>'."\n\t\t".'<p class="options">'.implode(' ', $forum_page['ext_actions']).'</p>'."\n\t".'</div>';
		else
			$forum_page['ext_item'][] = '<div class="ct-box info-box">'."\n\t\t".'<h3 class="ct-legend hn"><span>'.forum_htmlencode($ext['title']).'</span></h3>'."\n\t\t".'<ul class="data-list">'."\n\t\t\t".'<li><span>'.sprintf($lang_admin_ext['Extension by'], forum_htmlencode($ext['author'])).'</span></li>'."\n\t\t\t".'<li><span>'.sprintf($lang_admin_ext['Version'], $ext['version']).'</span></li>'."\n\t\t\t".(($ext['description'] != '') ? '<li><span>'.forum_htmlencode($ext['description']).'</span></li>' : '')."\n\t\t".'</ul>'."\n\t\t".'<p class="options">'.implode(' ', $forum_page['ext_actions']).'</p>'."\n\t".'</div>';

		$installed_count++;
	}

	if ($installed_count > 0)
	{

?>
		<div class="ct-box warn-box">
			<p class="warn"><?php echo $lang_admin_ext['Installed extensions warn'] ?></p>
		</div>
<?php

		echo "\t".implode("\n\t", $forum_page['ext_item'])."\n";
	}
	else
	{

?>
		<div class="ct-box info-box">
			<p><?php echo $lang_admin_ext['No installed extensions'] ?></p>
		</div>
<?php

	}

?>
	</div>
<?php

	($hook = get_hook('aex_section_manage_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	$tpl_temp = forum_trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}
