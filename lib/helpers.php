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

//include __DIR__.'/helpers/composers.php';
//include __DIR__.'/helpers/filters.php';
//include __DIR__.'/helpers/html.php';
//include __DIR__.'/helpers/validators.php';


function fluxbb($key = null)
{
	if (isset($key))
	{
		return $GLOBALS['__fluxbb.app'][$key];
	}
	
	return $GLOBALS['__fluxbb.app'];
}

function set_fluxbb($app)
{
	$GLOBALS['__fluxbb.app'] = $app;
}

function url($route, $parameters = array())
{
	$app = fluxbb();

	return $app['url.generator']->generateUrl($route, $parameters);
}

function t($id, $parameters = array(), $domain = 'messages', $locale = null)
{
	$app = fluxbb();

	if (isset($app['translator']))
	{
		return $app['translator']->trans($id, $parameters, $domain, $locale);
	}
	else
	{
		throw new RuntimeException('Application translator not set.');
	}
}
