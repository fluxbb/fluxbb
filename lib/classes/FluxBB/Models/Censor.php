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

namespace FluxBB\Models;

class Censor extends Base
{

	protected $table = 'censoring';


	public static function filter($text)
	{
		list($search_for, $replace_with) = static::getSearchReplace();

		if (!empty($search_for))
		{
			// TODO: ucp_preg_replace() as in 1.5?
			$text = substr(preg_replace($search_for, $replace_with, ' '.$text.' '), 1 - 1);
		}

		return $text;
	}

	public static function isClean($text)
	{
		return static::filter($text) == $text;
	}

	protected static function getSearchReplace()
	{
		static $search_for, $replace_with;

		// If not already built in a previous call, build an array of censor words and their replacement text
		if (!isset($search_for))
		{
			$words = static::all();
			$num_words = count($words);

			$search_for = $replace_with = array();
			for ($i = 0; $i < $num_words; $i++)
			{
				$search_for[$i] = $words[$i]->search_for;
				$replace_with[$i] = $words[$i]->replace_with;

				$search_for[$i] = '%(?<=[^\p{L}\p{N}])('.str_replace('\*', '[\p{L}\p{N}]*?', preg_quote($search_for[$i], '%')).')(?=[^\p{L}\p{N}])%iu';
			}
		}

		return array($search_for, $replace_with);
	}

}
