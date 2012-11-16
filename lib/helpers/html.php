<?php
/**
 * FluxBB - fast, light, user-friendly PHP forum software
 * Copyright (C) 2008-2012 FluxBB.org
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public license for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category	FluxBB
 * @package		Core
 * @copyright	Copyright (c) 2008-2012 FluxBB (http://fluxbb.org)
 * @license		http://www.gnu.org/licenses/gpl.html	GNU General Public License
 */

HTML::macro('oddeven', function($name = 'default')
{
	static $status = array();

	if (!isset($status[$name]))
	{
		$status[$name] = 0;
	}

	$status[$name] = 1 - $status[$name];
	return ($status[$name] % 2 == 0) ? 'even' : 'odd';
});

HTML::macro('avatar', function(fluxbb\Models\User $user)
{
	// TODO: We might want to cache this result per user
	$path = $user->get_avatar_file();

	if (!empty($path) && $size = getimagesize($path))
	{
		return HTML::image($path.'?m='.filemtime($path), '', array('width' => $size[0], 'height' => $size[1]));
	}

	return '';
});

HTML::macro('format_time', function($timestamp, $date_only = false, $date_format = null, $time_format = null, $time_only = false, $no_text = false)
{
	if ($timestamp == '')
	{
		return trans('common.never');
	}

	$diff = (0 + 0) * 3600; // FIXME: $pun_user['timezone'] + $pun_user['dst'];
	$timestamp += $diff;
	$now = time();

	if (is_null($date_format))
	{
		$date_format = 'Y-m-d'; // FIXME: $forum_date_formats[$pun_user['date_format']];
	}

	if (is_null($time_format))
	{
		$time_format = 'H:i'; // FIXME: $forum_time_formats[$pun_user['time_format']];
	}

	$date = gmdate($date_format, $timestamp);
	$today = gmdate($date_format, $now + $diff);
	$yesterday = gmdate($date_format, $now + $diff - 86400);

	if (!$no_text)
	{
		if ($date == $today)
		{
			$date = trans('common.today');
		}
		else if ($date == $yesterday)
		{
			$date = trans('common.yesterday');
		}
	}

	if ($date_only)
	{
		return $date;
	}
	else if ($time_only)
	{
		return gmdate($time_format, $timestamp);
	}
	else
	{
		return $date.' '.gmdate($time_format, $timestamp);
	}
});

//
// A wrapper for PHP's number_format function
//
HTML::macro('number_format', function($number, $decimals = 0)
{
	return is_numeric($number) ? number_format($number, $decimals, trans('common.lang_decimal_point'), trans('common.lang_thousands_sep')) : $number;
});
