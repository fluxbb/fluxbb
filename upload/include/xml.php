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
// Parse XML data into an array
//
function xml_to_array($raw_xml)
{
	$xml_parser = xml_parser_create();
	xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, 0);
	xml_parser_set_option($xml_parser, XML_OPTION_SKIP_WHITE, 0);
	xml_parse_into_struct($xml_parser, $raw_xml, $vals);
	xml_parser_free($xml_parser);

	$_tmp = '';
	foreach ($vals as $xml_elem)
	{
		$x_tag = $xml_elem['tag'];
		$x_level = $xml_elem['level'];
		$x_type = $xml_elem['type'];

		if ($x_level != 1 && $x_type == 'close')
		{
			if (isset($multi_key[$x_tag][$x_level]))
				$multi_key[$x_tag][$x_level] = 1;
			else
				$multi_key[$x_tag][$x_level] = 0;
		}

		if ($x_level != 1 && $x_type == 'complete')
		{
			if ($_tmp == $x_tag)
				$multi_key[$x_tag][$x_level] = 1;

			$_tmp = $x_tag;
		}
	}

	foreach ($vals as $xml_elem)
	{
		$x_tag = $xml_elem['tag'];
		$x_level = $xml_elem['level'];
		$x_type = $xml_elem['type'];

		if ($x_type == 'open')
			$level[$x_level] = $x_tag;

		$start_level = 1;
		$php_stmt = '$xml_array';
		if ($x_type == 'close' && $x_level != 1)
			$multi_key[$x_tag][$x_level]++;

		while ($start_level < $x_level)
		{
			$php_stmt .= '[$level['.$start_level.']]';
			if (isset($multi_key[$level[$start_level]][$start_level]) && $multi_key[$level[$start_level]][$start_level])
				$php_stmt .= '['.($multi_key[$level[$start_level]][$start_level]-1).']';

			++$start_level;
		}

		$add = '';
		if (isset($multi_key[$x_tag][$x_level]) && $multi_key[$x_tag][$x_level] && ($x_type == 'open' || $x_type == 'complete'))
		{
			if (!isset($multi_key2[$x_tag][$x_level]))
				$multi_key2[$x_tag][$x_level] = 0;
			else
				$multi_key2[$x_tag][$x_level]++;

			$add = '['.$multi_key2[$x_tag][$x_level].']';
		}

		if (isset($xml_elem['value']) && trim($xml_elem['value']) != '' && !array_key_exists('attributes', $xml_elem))
		{
			if ($x_type == 'open')
				$php_stmt_main = $php_stmt.'[$x_type]'.$add.'[\'content\'] = $xml_elem[\'value\'];';
			else
				$php_stmt_main = $php_stmt.'[$x_tag]'.$add.' = $xml_elem[\'value\'];';

			eval($php_stmt_main);
		}

		if (array_key_exists('attributes', $xml_elem))
		{
			if (isset($xml_elem['value']))
			{
				$php_stmt_main = $php_stmt.'[$x_tag]'.$add.'[\'content\'] = $xml_elem[\'value\'];';
				eval($php_stmt_main);
			}

			foreach ($xml_elem['attributes'] as $key=>$value)
			{
				$php_stmt_att=$php_stmt.'[$x_tag]'.$add.'[\'attributes\'][$key] = $value;';
				eval($php_stmt_att);
			}
		}
	}

	if (isset($xml_array))
	{
		// Make sure there's an array of notes (even if there is only one)
		if (isset($xml_array['extension']['note']))
		{
			if (!is_array(current($xml_array['extension']['note'])))
				$xml_array['extension']['note'] = array($xml_array['extension']['note']);
		}
		else
			$xml_array['extension']['note'] = array();

		// Make sure there's an array of hooks (even if there is only one)
		if (isset($xml_array['extension']['hooks']) && isset($xml_array['extension']['hooks']['hook']))
		{
			if (!is_array(current($xml_array['extension']['hooks']['hook'])))
				$xml_array['extension']['hooks']['hook'] = array($xml_array['extension']['hooks']['hook']);
		}
	}

	return isset($xml_array) ? $xml_array : array();
}


//
// Validate the syntax of an extension manifest file
//
function validate_manifest($xml_array, $folder_name)
{
	global $lang_admin, $forum_config;

	$errors = array();

	if (!isset($xml_array['extension']) || !is_array($xml_array['extension']))
		$errors[] = $lang_admin['extension root error'];
	else
	{
		$ext = $xml_array['extension'];
		if (!isset($ext['attributes']['engine']))
			$errors[] = $lang_admin['extension/engine error'];
		else if ($ext['attributes']['engine'] != '1.0')
			$errors[] = $lang_admin['extension/engine error2'];
		if (!isset($ext['id']) || $ext['id'] == '')
			$errors[] = $lang_admin['extension/id error'];
		if ($ext['id'] != $folder_name)
			$errors[] = $lang_admin['extension/id error2'];
		if (!isset($ext['title']) || $ext['title'] == '')
			$errors[] = $lang_admin['extension/title error'];
		if (!isset($ext['version']) || $ext['version'] == '')
			$errors[] = $lang_admin['extension/version error'];
		if (!isset($ext['description']) || $ext['description'] == '')
			$errors[] = $lang_admin['extension/description error'];
		if (!isset($ext['author']) || $ext['author'] == '')
			$errors[] = $lang_admin['extension/author error'];
		if (!isset($ext['minversion']) || $ext['minversion'] == '')
			$errors[] = $lang_admin['extension/minversion error'];
		if (isset($ext['minversion']) && version_compare(clean_version($forum_config['o_cur_version']), clean_version($ext['minversion']), '<'))
			$errors[] = sprintf($lang_admin['extension/minversion error2'], $ext['minversion']);
		if (!isset($ext['maxtestedon']) || $ext['maxtestedon'] == '')
			$errors[] = $lang_admin['extension/maxtestedon error'];
		if (isset($ext['note']))
		{
			foreach ($ext['note'] as $note)
			{
				if (!isset($note['content']) || $note['content'] == '')
					$errors[] = $lang_admin['extension/note error'];
				if (!isset($note['attributes']['type']) || $note['attributes']['type'] == '')
					$errors[] = $lang_admin['extension/note error2'];
			}
		}
		if (isset($ext['hooks']) && is_array($ext['hooks']))
		{
			if (!isset($ext['hooks']['hook']) || !is_array($ext['hooks']['hook']))
				$errors[] = $lang_admin['extension/hooks/hook error'];
			else
			{
				foreach ($ext['hooks']['hook'] as $hook)
				{
					if (!isset($hook['content']) || $hook['content'] == '')
						$errors[] = $lang_admin['extension/hooks/hook error'];
					if (!isset($hook['attributes']['id']) || $hook['attributes']['id'] == '')
						$errors[] = $lang_admin['extension/hooks/hook error2'];
				}
			}
		}
	}

	return $errors;
}
