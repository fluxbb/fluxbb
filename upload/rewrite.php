<?php
/***********************************************************************

  Copyright (C) 2002-2008  PunBB.org

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


define('PUN_ROOT', './');
require PUN_ROOT.'include/essentials.php';

// Bring in all the rewrite rules
require PUN_ROOT.'include/rewrite_rules.php';

// Allow extensions to create their own rewrite rules/modify existing rules
($hook = get_hook('re_rewrite_rules')) ? eval($hook) : null;

// We determine the path to the script, since we need to separate the path from the data to be rewritten
$path_to_script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
if (substr($path_to_script, -1) != '/')
	$path_to_script  = $path_to_script.'/';

// We create our own request URI with the path removed and only the parts to rewrite included
$request_uri = substr($_SERVER['REQUEST_URI'], strlen($path_to_script));
if (strpos($request_uri, '?') !== false)
	$request_uri = substr($request_uri, 0, strpos($request_uri, '?'));

$rewritten_url = '';
$url_parts = array();
// We go through every rewrite rule
foreach ($pun_rewrite_rules as $rule => $rewrite_to)
{
	// We have a match!
	if (preg_match($rule, $request_uri))
	{
		$rewritten_url = preg_replace($rule, $rewrite_to, $request_uri);
		$url_parts = explode('?', $rewritten_url);

		// If there is a query string
		if (isset($url_parts[1]))
		{
			$query_string = explode('&', $url_parts[1]);

			// Set $_GET properly for all of the variables
			// We also set $_REQUEST if it's not already set
			foreach ($query_string as $cur_param)
			{
				$param_data = explode('=', $cur_param);

				// Sometimes, parameters don't set a value (eg: script.php?foo), so we set them to null
				$param_data[1] = isset($param_data[1]) ? $param_data[1] : null;

				// We don't want to be overwriting values in $_REQUEST that were set in POST or COOKIE
				if (!isset($_POST[$param_data[0]]) && !isset($_COOKIE[$param_data[0]]))
					$_REQUEST[$param_data[0]] = urldecode($param_data[1]);

				$_GET[$param_data[0]] = urldecode($param_data[1]);
			}
		}
		break;
	}
}

// If we don't know what to rewrite to, we show a bad request messsage
if (empty($rewritten_url))
{
	header('HTTP/1.x 404 Not Found');

	// Allow an extension to override the "Bad request" message with a custom 404 page
	($hook = get_hook('re_page_not_found')) ? eval($hook) : null;

	exit('Bad request');
}

// We change $_SERVER['PHP_SELF'] so that it reflects the file we're actually loading
$_SERVER['PHP_SELF'] = str_replace('rewrite.php', $url_parts[0], $_SERVER['PHP_SELF']);

require PUN_ROOT.$url_parts[0];
