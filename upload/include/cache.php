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


// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;


//
// If we are running pre PHP 4.2.0, we add our own implementation of var_export
//
if (!function_exists('var_export'))
{
	function var_export()
	{
		$args = func_get_args();
		$indent = (isset($args[2])) ? $args[2] : '';

		if (is_array($args[0]))
		{
			$output = 'array ('."\n";

			foreach ($args[0] as $k => $v)
			{
				if (is_numeric($k))
					$output .= $indent.'  '.$k.' => ';
				else
					$output .= $indent.'  \''.str_replace('\'', '\\\'', str_replace('\\', '\\\\', $k)).'\' => ';

				if (is_array($v))
					$output .= var_export($v, true, $indent.'  ');
				else
				{
					if (gettype($v) != 'string' && !empty($v))
						$output .= $v.','."\n";
					else
						$output .= '\''.str_replace('\'', '\\\'', str_replace('\\', '\\\\', $v)).'\','."\n";
				}
			}

			$output .= ($indent != '') ? $indent.'),'."\n" : ')';
		}
		else
			$output = $args[0];

		if ($args[1] == true)
			return $output;
		else
			echo $output;
	}
}


//
// Generate the config cache PHP script
//
function generate_config_cache()
{
	global $db;

	// Get the forum config from the DB
	$result = $db->query('SELECT * FROM '.$db->prefix.'config', true) or error('Unable to fetch forum config', __FILE__, __LINE__, $db->error());
	while ($cur_config_item = $db->fetch_row($result))
		$output[$cur_config_item[0]] = $cur_config_item[1];

	// Output config as PHP code
	$fh = @fopen(PUN_ROOT.'cache/cache_config.php', 'wb');
	if (!$fh)
		error('Unable to write configuration cache file to cache directory. Please make sure PHP has write access to the directory \'cache\'', __FILE__, __LINE__);

	fwrite($fh, '<?php'."\n\n".'define(\'PUN_CONFIG_LOADED\', 1);'."\n\n".'$pun_config = '.var_export($output, true).';'."\n\n".'?>');

	fclose($fh);
}


//
// Generate the bans cache PHP script
//
function generate_bans_cache()
{
	global $db;

	// Get the ban list from the DB
	$result = $db->query('SELECT * FROM '.$db->prefix.'bans', true) or error('Unable to fetch ban list', __FILE__, __LINE__, $db->error());

	$output = array();
	while ($cur_ban = $db->fetch_assoc($result))
		$output[] = $cur_ban;

	// Output ban list as PHP code
	$fh = @fopen(PUN_ROOT.'cache/cache_bans.php', 'wb');
	if (!$fh)
		error('Unable to write bans cache file to cache directory. Please make sure PHP has write access to the directory \'cache\'', __FILE__, __LINE__);

	fwrite($fh, '<?php'."\n\n".'define(\'PUN_BANS_LOADED\', 1);'."\n\n".'$pun_bans = '.var_export($output, true).';'."\n\n".'?>');

	fclose($fh);
}


//
// Generate the ranks cache PHP script
//
function generate_ranks_cache()
{
	global $db;

	// Get the rank list from the DB
	$result = $db->query('SELECT * FROM '.$db->prefix.'ranks ORDER BY min_posts', true) or error('Unable to fetch rank list', __FILE__, __LINE__, $db->error());

	$output = array();
	while ($cur_rank = $db->fetch_assoc($result))
		$output[] = $cur_rank;

	// Output ranks list as PHP code
	$fh = @fopen(PUN_ROOT.'cache/cache_ranks.php', 'wb');
	if (!$fh)
		error('Unable to write ranks cache file to cache directory. Please make sure PHP has write access to the directory \'cache\'', __FILE__, __LINE__);

	fwrite($fh, '<?php'."\n\n".'define(\'PUN_RANKS_LOADED\', 1);'."\n\n".'$pun_ranks = '.var_export($output, true).';'."\n\n".'?>');

	fclose($fh);
}


//
// Generate quickjump cache PHP scripts
//
function generate_quickjump_cache($group_id = false)
{
	global $db, $lang_common, $pun_user;

	// If a group_id was supplied, we generate the quickjump cache for that group only
	if ($group_id !== false)
		$groups[0] = $group_id;
	else
	{
		// A group_id was now supplied, so we generate the quickjump cache for all groups
		$result = $db->query('SELECT g_id FROM '.$db->prefix.'groups') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());
		$num_groups = $db->num_rows($result);

		for ($i = 0; $i < $num_groups; ++$i)
			$groups[] = $db->result($result, $i);
	}

	// Loop through the groups in $groups and output the cache for each of them
	while (list(, $group_id) = @each($groups))
	{
		// Output quickjump as PHP code
		$fh = @fopen(PUN_ROOT.'cache/cache_quickjump_'.$group_id.'.php', 'wb');
		if (!$fh)
			error('Unable to write quickjump cache file to cache directory. Please make sure PHP has write access to the directory \'cache\'', __FILE__, __LINE__);

		$output = '<?php'."\n\n".'if (!defined(\'PUN\')) exit;'."\n".'define(\'PUN_QJ_LOADED\', 1);'."\n\n".'?>';
		$output .= "\t\t\t\t".'<form id="qjump" method="get" action="viewforum.php">'."\n\t\t\t\t\t".'<div><label><?php echo $lang_common[\'Jump to\'] ?>'."\n\n\t\t\t\t\t".'<br /><select name="id" onchange="window.location=(\'viewforum.php?id=\'+this.options[this.selectedIndex].value)">'."\n";


		$result = $db->query('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.redirect_url FROM '.$db->prefix.'categories AS c INNER JOIN '.$db->prefix.'forums AS f ON c.id=f.cat_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$group_id.') WHERE fp.read_forum IS NULL OR fp.read_forum=1 ORDER BY c.disp_position, c.id, f.disp_position', true) or error('Unable to fetch category/forum list', __FILE__, __LINE__, $db->error());

		$cur_category = 0;
		while ($cur_forum = $db->fetch_assoc($result))
		{
			if ($cur_forum['cid'] != $cur_category)	// A new category since last iteration?
			{
				if ($cur_category)
					$output .= "\t\t\t\t\t\t".'</optgroup>'."\n";

				$output .= "\t\t\t\t\t\t".'<optgroup label="'.pun_htmlspecialchars($cur_forum['cat_name']).'">'."\n";
				$cur_category = $cur_forum['cid'];
			}

			$redirect_tag = ($cur_forum['redirect_url'] != '') ? ' &gt;&gt;&gt;' : '';
			$output .= "\t\t\t\t\t\t\t".'<option value="'.$cur_forum['fid'].'"<?php echo ($forum_id == '.$cur_forum['fid'].') ? \' selected="selected"\' : \'\' ?>>'.pun_htmlspecialchars($cur_forum['forum_name']).$redirect_tag.'</option>'."\n";
		}

		$output .= "\t\t\t\t\t".'</optgroup>'."\n\t\t\t\t\t".'</select>'."\n\t\t\t\t\t".'<input type="submit" value="<?php echo $lang_common[\'Go\'] ?>" accesskey="g" />'."\n\t\t\t\t\t".'</label></div>'."\n\t\t\t\t".'</form>'."\n";

		fwrite($fh, $output);

		fclose($fh);
	}
}
