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


// Make sure no one attempts to run this script "directly"
if (!defined('FORUM'))
	exit;


//
// Generate the config cache PHP script
//
function generate_config_cache()
{
	global $forum_db;

	$return = ($hook = get_hook('ch_fn_generate_config_cache_start')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
	if ($return != null)
		return;

	// Get the forum config from the DB
	$query = array(
		'SELECT'	=> 'c.*',
		'FROM'		=> 'config AS c'
	);

	($hook = get_hook('ch_fn_generate_config_cache_qr_get_config')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$output = array();
	while ($cur_config_item = $forum_db->fetch_row($result))
		$output[$cur_config_item[0]] = $cur_config_item[1];

	// Output config as PHP code
	$fh = @fopen(FORUM_CACHE_DIR.'cache_config.php', 'wb');
	if (!$fh)
		error('Unable to write configuration cache file to cache directory. Please make sure PHP has write access to the directory \'cache\'.', __FILE__, __LINE__);

	fwrite($fh, '<?php'."\n\n".'define(\'FORUM_CONFIG_LOADED\', 1);'."\n\n".'$forum_config = '.var_export($output, true).';'."\n\n".'?>');

	fclose($fh);
}


//
// Generate the bans cache PHP script
//
function generate_bans_cache()
{
	global $forum_db;

	$return = ($hook = get_hook('ch_fn_generate_bans_cache_start')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
	if ($return != null)
		return;

	// Get the ban list from the DB
	$query = array(
		'SELECT'	=> 'b.*, u.username AS ban_creator_username',
		'FROM'		=> 'bans AS b',
		'JOINS'		=> array(
			array(
				'LEFT JOIN'		=> 'users AS u',
				'ON'			=> 'u.id=b.ban_creator'
			)
		),
		'ORDER BY'	=> 'b.id'
	);

	($hook = get_hook('ch_fn_generate_bans_cache_qr_get_bans')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$output = array();
	while ($cur_ban = $forum_db->fetch_assoc($result))
		$output[] = $cur_ban;

	// Output ban list as PHP code
	$fh = @fopen(FORUM_CACHE_DIR.'cache_bans.php', 'wb');
	if (!$fh)
		error('Unable to write bans cache file to cache directory. Please make sure PHP has write access to the directory \'cache\'.', __FILE__, __LINE__);

	fwrite($fh, '<?php'."\n\n".'define(\'FORUM_BANS_LOADED\', 1);'."\n\n".'$forum_bans = '.var_export($output, true).';'."\n\n".'?>');

	fclose($fh);
}


//
// Generate the ranks cache PHP script
//
function generate_ranks_cache()
{
	global $forum_db;

	$return = ($hook = get_hook('ch_fn_generate_ranks_cache_start')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
	if ($return != null)
		return;

	// Get the rank list from the DB
	$query = array(
		'SELECT'	=> 'r.*',
		'FROM'		=> 'ranks AS r',
		'ORDER BY'	=> 'r.min_posts'
	);

	($hook = get_hook('ch_fn_generate_ranks_cache_qr_get_ranks')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$output = array();
	while ($cur_rank = $forum_db->fetch_assoc($result))
		$output[] = $cur_rank;

	// Output ranks list as PHP code
	$fh = @fopen(FORUM_CACHE_DIR.'cache_ranks.php', 'wb');
	if (!$fh)
		error('Unable to write ranks cache file to cache directory. Please make sure PHP has write access to the directory \'cache\'.', __FILE__, __LINE__);

	fwrite($fh, '<?php'."\n\n".'define(\'FORUM_RANKS_LOADED\', 1);'."\n\n".'$forum_ranks = '.var_export($output, true).';'."\n\n".'?>');

	fclose($fh);
}


//
// Generate the censor cache PHP script
//
function generate_censors_cache()
{
	global $forum_db;

	$return = ($hook = get_hook('ch_fn_generate_censors_cache_start')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
	if ($return != null)
		return;

	// Get the censor list from the DB
	$query = array(
		'SELECT'	=> 'c.*',
		'FROM'		=> 'censoring AS c',
		'ORDER BY'	=> 'c.search_for'
	);

	($hook = get_hook('ch_fn_generate_censors_cache_qr_get_censored_words')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$output = array();
	while ($cur_censor = $forum_db->fetch_assoc($result))
		$output[] = $cur_censor;

	// Output censors list as PHP code
	$fh = @fopen(FORUM_CACHE_DIR.'cache_censors.php', 'wb');
	if (!$fh)
		error('Unable to write censor cache file to cache directory. Please make sure PHP has write access to the directory \'cache\'.', __FILE__, __LINE__);

	fwrite($fh, '<?php'."\n\n".'define(\'FORUM_CENSORS_LOADED\', 1);'."\n\n".'$forum_censors = '.var_export($output, true).';'."\n\n".'?>');

	fclose($fh);
}


//
// Generate quickjump cache PHP scripts
//
function generate_quickjump_cache($group_id = false)
{
	global $forum_db, $lang_common, $forum_url, $forum_config, $forum_user, $base_url;

	$return = ($hook = get_hook('ch_fn_generate_quickjump_cache_start')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
	if ($return != null)
		return;

	// If a group_id was supplied, we generate the quickjump cache for that group only
	if ($group_id !== false)
		$groups[0] = $group_id;
	else
	{
		// A group_id was not supplied, so we generate the quickjump cache for all groups
		$query = array(
			'SELECT'	=> 'g.g_id',
			'FROM'		=> 'groups AS g'
		);

		($hook = get_hook('ch_fn_generate_quickjump_cache_qr_get_groups')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		$num_groups = $forum_db->num_rows($result);

		for ($i = 0; $i < $num_groups; ++$i)
			$groups[] = $forum_db->result($result, $i);
	}

	// Loop through the groups in $groups and output the cache for each of them
	while (list(, $group_id) = @each($groups))
	{
		// Output quickjump as PHP code
		$fh = @fopen(FORUM_CACHE_DIR.'cache_quickjump_'.$group_id.'.php', 'wb');
		if (!$fh)
			error('Unable to write quickjump cache file to cache directory. Please make sure PHP has write access to the directory \'cache\'.', __FILE__, __LINE__);

		$output = '<?php'."\n\n".'if (!defined(\'FORUM\')) exit;'."\n".'define(\'FORUM_QJ_LOADED\', 1);'."\n".'$forum_id = isset($forum_id) ? $forum_id : 0;'."\n\n".'?>';
		$output .= '<form id="qjump" method="get" accept-charset="utf-8" action="'.$base_url.'/viewforum.php">'."\n\t".'<div class="frm-fld frm-select">'."\n\t\t".'<label for="qjump-select"><span><?php echo $lang_common[\'Jump to\'] ?>'.'</span></label><br />'."\n\t\t".'<span class="frm-input"><select id="qjump-select" name="id">'."\n";

		// Get the list of categories and forums from the DB
		$query = array(
			'SELECT'	=> 'c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.redirect_url',
			'FROM'		=> 'categories AS c',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'forums AS f',
					'ON'			=> 'c.id=f.cat_id'
				),
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$group_id.')'
				)
			),
			'WHERE'		=> 'fp.read_forum IS NULL OR fp.read_forum=1',
			'ORDER BY'	=> 'c.disp_position, c.id, f.disp_position'
		);

		($hook = get_hook('ch_fn_generate_quickjump_cache_qr_get_cats_and_forums')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		$cur_category = 0;
		$forum_count = 0;
		$sef_friendly_names = array();
		while ($cur_forum = $forum_db->fetch_assoc($result))
		{
			($hook = get_hook('ch_fn_generate_quickjump_cache_forum_loop_start')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

			if ($cur_forum['cid'] != $cur_category)	// A new category since last iteration?
			{
				if ($cur_category)
					$output .= "\t\t\t".'</optgroup>'."\n";

				$output .= "\t\t\t".'<optgroup label="'.forum_htmlencode($cur_forum['cat_name']).'">'."\n";
				$cur_category = $cur_forum['cid'];
			}

			$sef_friendly_names[$cur_forum['fid']] = sef_friendly($cur_forum['forum_name']);
			$redirect_tag = ($cur_forum['redirect_url'] != '') ? ' &gt;&gt;&gt;' : '';
			$output .= "\t\t\t\t".'<option value="'.$cur_forum['fid'].'"<?php echo ($forum_id == '.$cur_forum['fid'].') ? \' selected="selected"\' : \'\' ?>>'.forum_htmlencode($cur_forum['forum_name']).$redirect_tag.'</option>'."\n";
			$forum_count++;
		}

		$output .= "\t\t\t".'</optgroup>'."\n\t\t".'</select>'."\n\t\t".'<input type="submit" value="<?php echo $lang_common[\'Go\'] ?>" onclick="return Forum.doQuickjumpRedirect(forum_quickjump_url, sef_friendly_url_array);" /></span>'."\n\t".'</div>'."\n".'</form>'."\n";
		$output .= '<script type="text/javascript">'."\n\t\t".'var forum_quickjump_url = "'.forum_link($forum_url['forum']).'";'."\n\t\t".'var sef_friendly_url_array = new Array('.$forum_db->num_rows($result).');';

		foreach ($sef_friendly_names as $forum_id => $forum_name)
			$output .= "\n\t".'sef_friendly_url_array['.$forum_id.'] = "'.forum_htmlencode($forum_name).'";';

		$output .= "\n".'</script>'."\n";

		if ($forum_count < 2)
			$output = '<?php'."\n\n".'if (!defined(\'FORUM\')) exit;'."\n".'define(\'FORUM_QJ_LOADED\', 1);';

		fwrite($fh, $output);

		fclose($fh);
	}
}


//
// Generate the hooks cache PHP script
//
function generate_hooks_cache()
{
	global $forum_db, $forum_config, $base_url;

	$return = ($hook = get_hook('ch_fn_generate_hooks_cache_start')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
	if ($return != null)
		return;

	// Get the hooks from the DB
	$query = array(
		'SELECT'	=> 'eh.id, eh.code, eh.extension_id, e.dependencies',
		'FROM'		=> 'extension_hooks AS eh',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'extensions AS e',
				'ON'			=> 'e.id=eh.extension_id'
			)
		),
		'WHERE'		=> 'e.disabled=0',
		'ORDER BY'	=> 'eh.priority, eh.installed'
	);

	($hook = get_hook('ch_fn_generate_hooks_cache_qr_get_hooks')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$output = array();
	while ($cur_hook = $forum_db->fetch_assoc($result))
	{
		$load_ext_info = '$ext_info_stack[] = array('."\n".
			'\'id\'				=> \''.$cur_hook['extension_id'].'\','."\n".
			'\'path\'			=> FORUM_ROOT.\'extensions/'.$cur_hook['extension_id'].'\','."\n".
			'\'url\'			=> $GLOBALS[\'base_url\'].\'/extensions/'.$cur_hook['extension_id'].'\','."\n".
			'\'dependencies\'	=> array ('."\n";

		$dependencies = explode('|', substr($cur_hook['dependencies'], 1, -1));
		foreach ($dependencies as $cur_dependency)
		{
			// This happens if there are no dependencies because explode ends up returning an array with one empty element
			if (empty($cur_dependency))
				continue;

			$load_ext_info .= '\''.$cur_dependency.'\'	=> array('."\n".
				'\'id\'				=> \''.$cur_dependency.'\','."\n".
				'\'path\'			=> FORUM_ROOT.\'extensions/'.$cur_dependency.'\','."\n".
				'\'url\'			=> $GLOBALS[\'base_url\'].\'/extensions/'.$cur_dependency.'\'),'."\n";
		}

		$load_ext_info .= ')'."\n".');'."\n".'$ext_info = $ext_info_stack[count($ext_info_stack) - 1];';
		$unload_ext_info = 'array_pop($ext_info_stack);'."\n".'$ext_info = empty($ext_info_stack) ? array() : $ext_info_stack[count($ext_info_stack) - 1];';

		$output[$cur_hook['id']][] = $load_ext_info."\n\n".$cur_hook['code']."\n\n".$unload_ext_info."\n";
	}

	// First, remove all existing cache_hook_hookname.php files
	$d = dir(FORUM_CACHE_DIR);
	while (($entry = $d->read()) !== false)
	{
		if (substr($entry, 0, 11) == 'cache_hook_' && substr($entry, strlen($entry) - 4) == '.php')
			@unlink(FORUM_CACHE_DIR.$entry);
	}
	$d->close();

	// Now, output the new cache_hook_hookname.php files
	foreach ($output as $cur_hook => $hooks)
	{
		// Output include hook cache
		$fh = @fopen(FORUM_CACHE_DIR.'cache_hook_'.$cur_hook.'.php', 'wb');
		if (!$fh)
			error('Unable to write hooks cache file to cache directory. Please make sure PHP has write access to the directory \'cache\'.', __FILE__, __LINE__);

		fwrite($fh, '<?php'."\n\n".'if (!defined(\'FORUM\'))'."\n\t".'exit;'."\n\n".implode("\n", $hooks)."\n\n".'return null;');

		fclose($fh);
	}

	// Output hooks as PHP code
	$fh = @fopen(FORUM_CACHE_DIR.'cache_hooks.php', 'wb');
	if (!$fh)
		error('Unable to write hooks cache file to cache directory. Please make sure PHP has write access to the directory \'cache\'.', __FILE__, __LINE__);

	fwrite($fh, '<?php'."\n\n".'define(\'FORUM_HOOKS_LOADED\', 1);'."\n\n".'$forum_hooks = '.var_export($output, true).';'."\n\n".'?>');

	fclose($fh);
}


//
// Generate the updates cache PHP script
//
function generate_updates_cache()
{
	global $forum_db, $forum_config;

	$return = ($hook = get_hook('ch_fn_generate_updates_cache_start')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
	if ($return != null)
		return;

	// Get a list of installed hotfix extensions
	$query = array(
		'SELECT'	=> 'e.id',
		'FROM'		=> 'extensions AS e',
		'WHERE'		=> 'e.id LIKE \'hotfix_%\''
	);

	($hook = get_hook('ch_fn_generate_updates_cache_qr_get_hotfixes')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$num_hotfixes = $forum_db->num_rows($result);

	$hotfixes = array();
	for ($i = 0; $i < $num_hotfixes; ++$i)
		$hotfixes[] = urlencode($forum_db->result($result, $i));

	// Contact the fluxbb.org updates service
	$result = get_remote_file('http://fluxbb.org/update/?type=xml&version='.urlencode($forum_config['o_cur_version']).'&hotfixes='.implode(',', $hotfixes), 8);

	// Make sure we got everything we need
	if ($result != null && strpos($result['content'], '</updates>') !== false)
	{
		if (!defined('FORUM_XML_FUNCTIONS_LOADED'))
			require FORUM_ROOT.'include/xml.php';

		$output = xml_to_array($result['content']);
		$output = current($output);
		$output['cached'] = time();
		$output['fail'] = false;
	}
	else	// If the update check failed, set the fail flag
		$output = array('cached' => time(), 'fail' => true);

	// This hook could potentially (and responsibly) be used by an extension to do its own little update check
	($hook = get_hook('ch_fn_generate_updates_cache_write')) ? (!defined('FORUM_USE_EVAL') ? include $hook : eval($hook)) : null;

	// Output update status as PHP code
	$fh = @fopen(FORUM_CACHE_DIR.'cache_updates.php', 'wb');
	if (!$fh)
		error('Unable to write updates cache file to cache directory. Please make sure PHP has write access to the directory \'cache\'.', __FILE__, __LINE__);

	fwrite($fh, '<?php'."\n\n".'if (!defined(\'FORUM_UPDATES_LOADED\')) define(\'FORUM_UPDATES_LOADED\', 1);'."\n\n".'$forum_updates = '.var_export($output, true).';'."\n\n".'?>');

	fclose($fh);
}

define('FORUM_CACHE_FUNCTIONS_LOADED', 1);
