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


// Here you can add additional smilies if you like (please note that you must escape singlequote and backslash)
$smiley_text = array(':)', '=)', ':|', '=|', ':(', '=(', ':D', '=D', ':o', ':O', ';)', ':/', ':P', ':lol:', ':mad:', ':rolleyes:', ':cool:');
$smiley_img = array('smile.png', 'smile.png', 'neutral.png', 'neutral.png', 'sad.png', 'sad.png', 'big_smile.png', 'big_smile.png', 'yikes.png', 'yikes.png', 'wink.png', 'hmm.png', 'tongue.png', 'lol.png', 'mad.png', 'roll.png', 'cool.png');

// Uncomment the next row if you add smilies that contain any of the characters &"'<>
//$smiley_text = array_map('pun_htmlspecialchars', $smiley_text);


//
// Make sure all BBCodes are lower case and do a little cleanup
//
function preparse_bbcode($text, &$errors, $is_signature = false)
{
	// Change all simple BBCodes to lower case
	$a = array('[B]', '[I]', '[U]', '[/B]', '[/I]', '[/U]');
	$b = array('[b]', '[i]', '[u]', '[/b]', '[/i]', '[/u]');
	$text = str_replace($a, $b, $text);

	// Do the more complex BBCodes (also strip excessive whitespace and useless quotes)
	$a = array( '#\[url=("|\'|)(.*?)\\1\]\s*#i',
				'#\[url\]\s*#i',
				'#\s*\[/url\]#i',
				'#\[email=("|\'|)(.*?)\\1\]\s*#i',
				'#\[email\]\s*#i',
				'#\s*\[/email\]#i',
				'#\[img\]\s*(.*?)\s*\[/img\]#is',
				'#\[colou?r=("|\'|)(.*?)\\1\](.*?)\[/colou?r\]#is');

	$b = array(	'[url=$2]',
				'[url]',
				'[/url]',
				'[email=$2]',
				'[email]',
				'[/email]',
				'[img]$1[/img]',
				'[color=$2]$3[/color]');

	if (!$is_signature)
	{
		// For non-signatures, we have to do the quote and code tags as well
		$a[] = '#\[quote=(&quot;|"|\'|)(.*?)\\1\]\s*#i';
		$a[] = '#\[quote\]\s*#i';
		$a[] = '#\s*\[/quote\]\s*#i';
		$a[] = '#\[code\][\r\n]*(.*?)\s*\[/code\]\s*#is';

		$b[] = '[quote=$1$2$1]';
		$b[] = '[quote]';
		$b[] = '[/quote]'."\n";
		$b[] = '[code]$1[/code]'."\n";
	}

	// Run this baby!
	$text = preg_replace($a, $b, $text);

	if (!$is_signature)
	{
		$overflow = check_tag_order($text, $error);

		if ($error)
			// A BBCode error was spotted in check_tag_order()
			$errors[] = $error;
		else if ($overflow)
			// The quote depth level was too high, so we strip out the inner most quote(s)
			$text = substr($text, 0, $overflow[0]).substr($text, $overflow[1], (strlen($text) - $overflow[0]));
	}
	else
	{
		global $lang_prof_reg;

		if (preg_match('#\[quote=(&quot;|"|\'|)(.*)\\1\]|\[quote\]|\[/quote\]|\[code\]|\[/code\]#i', $text))
			message($lang_prof_reg['Signature quote/code']);
	}

	if (preg_match('#\[url\](.*?)([\[]+?)(.*?)\[/url\]#', $text) || preg_match('#\[url=(.*?)([\[]+?)(.*?)\](.*?)\[/url\]#', $text) || preg_match('#\[url=(.*?)\](.*?)([\[]+?)(.*?)\[/url\]#', $text))
		message('BBCode can not be nested within [url] tags.');
	if (preg_match('#\[email\](.*?)([\[]+?)(.*?)\[/email\]#', $text) || preg_match('#\[email=(.*?)([\[]+?)(.*?)\](.*?)\[/email\]#', $text) || preg_match('#\[email=(.*?)\](.*?)([\[]+?)(.*?)\[/email\]#', $text))
		message('BBCode can not be nested within [email] tags.');

	return trim($text);
}


//
// Parse text and make sure that [code] and [quote] syntax is correct
//
function check_tag_order($text, &$error)
{
	global $lang_common;

	// The maximum allowed quote depth
	$max_depth = 3;

	$cur_index = 0;
	$q_depth = 0;

	while (true)
	{
		// Look for regular code and quote tags
		$c_start = strpos($text, '[code]');
		$c_end = strpos($text, '[/code]');
		$q_start = strpos($text, '[quote]');
		$q_end = strpos($text, '[/quote]');

		// Look for [quote=username] style quote tags
		if (preg_match('#\[quote=(&quot;|"|\'|)(.*)\\1\]#sU', $text, $matches))
			$q2_start = strpos($text, $matches[0]);
		else
			$q2_start = 65536;

		// Deal with strpos() returning false when the string is not found
		// (65536 is one byte longer than the maximum post length)
		if ($c_start === false) $c_start = 65536;
		if ($c_end === false) $c_end = 65536;
		if ($q_start === false) $q_start = 65536;
		if ($q_end === false) $q_end = 65536;

		// If none of the strings were found
		if (min($c_start, $c_end, $q_start, $q_end, $q2_start) == 65536)
			break;

		// We are interested in the first quote (regardless of the type of quote)
		$q3_start = ($q_start < $q2_start) ? $q_start : $q2_start;

		// We found a [quote] or a [quote=username]
		if ($q3_start < min($q_end, $c_start, $c_end))
		{
			$step = ($q_start < $q2_start) ? 7 : strlen($matches[0]);

			$cur_index += $q3_start + $step;

			// Did we reach $max_depth?
			if ($q_depth == $max_depth)
				$overflow_begin = $cur_index - $step;

			++$q_depth;
			$text = substr($text, $q3_start + $step);
		}

		// We found a [/quote]
		else if ($q_end < min($q_start, $c_start, $c_end))
		{
			if ($q_depth == 0)
			{
				$error = $lang_common['BBCode error'].' '.$lang_common['BBCode error 1'];
				return;
			}

			$q_depth--;
			$cur_index += $q_end+8;

			// Did we reach $max_depth?
			if ($q_depth == $max_depth)
				$overflow_end = $cur_index;

			$text = substr($text, $q_end+8);
		}

		// We found a [code]
		else if ($c_start < min($c_end, $q_start, $q_end))
		{
			// Make sure there's a [/code] and that any new [code] doesn't occur before the end tag
			$tmp = strpos($text, '[/code]');
			$tmp2 = strpos(substr($text, $c_start+6), '[code]');
			if ($tmp2 !== false)
				$tmp2 += $c_start+6;

			if ($tmp === false || ($tmp2 !== false && $tmp2 < $tmp))
			{
				$error = $lang_common['BBCode error'].' '.$lang_common['BBCode error 2'];
				return;
			}
			else
				$text = substr($text, $tmp+7);

			$cur_index += $tmp+7;
		}

		// We found a [/code] (this shouldn't happen since we handle both start and end tag in the if clause above)
		else if ($c_end < min($c_start, $q_start, $q_end))
		{
			$error = $lang_common['BBCode error'].' '.$lang_common['BBCode error 3'];
			return;
		}
	}

	// If $q_depth <> 0 something is wrong with the quote syntax
	if ($q_depth)
	{
		$error = $lang_common['BBCode error'].' '.$lang_common['BBCode error 4'];
		return;
	}
	else if ($q_depth < 0)
	{
		$error = $lang_common['BBCode error'].' '.$lang_common['BBCode error 5'];
		return;
	}

	// If the quote depth level was higher than $max_depth we return the index for the
	// beginning and end of the part we should strip out
	if (isset($overflow_begin))
		return array($overflow_begin, $overflow_end);
	else
		return null;
}


//
// Split text into chunks ($inside contains all text inside $start and $end, and $outside contains all text outside)
//
function split_text($text, $start, $end)
{
	global $pun_config;

	$tokens = explode($start, $text);

	$outside[] = $tokens[0];

	$num_tokens = count($tokens);
	for ($i = 1; $i < $num_tokens; ++$i)
	{
		$temp = explode($end, $tokens[$i]);
		$inside[] = $temp[0];
		$outside[] = $temp[1];
	}

	if ($pun_config['o_indent_num_spaces'] != 8 && $start == '[code]')
	{
		$spaces = str_repeat(' ', $pun_config['o_indent_num_spaces']);
		$inside = str_replace("\t", $spaces, $inside);
	}

	return array($inside, $outside);
}


//
// Truncate URL if longer than 55 characters (add http:// or ftp:// if missing)
//
function handle_url_tag($url, $link = '')
{
	global $pun_user;

	$full_url = str_replace(array(' ', '\'', '`', '"'), array('%20', '', '', ''), $url);
	if (strpos($url, 'www.') === 0)			// If it starts with www, we add http://
		$full_url = 'http://'.$full_url;
	else if (strpos($url, 'ftp.') === 0)	// Else if it starts with ftp, we add ftp://
		$full_url = 'ftp://'.$full_url;
	else if (!preg_match('#^([a-z0-9]{3,6})://#', $url, $bah)) 	// Else if it doesn't start with abcdef://, we add http://
		$full_url = 'http://'.$full_url;

	// Ok, not very pretty :-)
	$link = ($link == '' || $link == $url) ? ((strlen($url) > 55) ? substr($url, 0 , 39).' &hellip; '.substr($url, -10) : $url) : stripslashes($link);

	return '<a href="'.$full_url.'">'.$link.'</a>';
}


//
// Turns an URL from the [img] tag into an <img> tag or a <a href...> tag
//
function handle_img_tag($url, $is_signature = false)
{
	global $lang_common, $pun_config, $pun_user;

	$img_tag = '<a href="'.$url.'">&lt;'.$lang_common['Image link'].'&gt;</a>';

	if ($is_signature && $pun_user['show_img_sig'] != '0')
		$img_tag = '<img class="sigimage" src="'.$url.'" alt="'.htmlspecialchars($url).'" />';
	else if (!$is_signature && $pun_user['show_img'] != '0')
		$img_tag = '<img class="postimg" src="'.$url.'" alt="'.htmlspecialchars($url).'" />';

	return $img_tag;
}


//
// Convert BBCodes to their HTML equivalent
//
function do_bbcode($text)
{
	global $lang_common, $pun_user;

	if (strpos($text, 'quote') !== false)
	{
		$text = str_replace('[quote]', '</p><blockquote><div class="incqbox"><p>', $text);
		$text = preg_replace('#\[quote=(&quot;|"|\'|)(.*)\\1\]#seU', '"</p><blockquote><div class=\"incqbox\"><h4>".str_replace(array(\'[\', \'\\"\'), array(\'&#91;\', \'"\'), \'$2\')." ".$lang_common[\'wrote\'].":</h4><p>"', $text);
		$text = preg_replace('#\[\/quote\]\s*#', '</p></div></blockquote><p>', $text);
	}

	$pattern = array('#\[b\](.*?)\[/b\]#s',
					 '#\[i\](.*?)\[/i\]#s',
					 '#\[u\](.*?)\[/u\]#s',
					 '#\[url\]([^\[]*?)\[/url\]#e',
					 '#\[url=([^\[]*?)\](.*?)\[/url\]#e',
					 '#\[email\]([^\[]*?)\[/email\]#',
					 '#\[email=([^\[]*?)\](.*?)\[/email\]#',
					 '#\[color=([a-zA-Z]*|\#?[0-9a-fA-F]{6})](.*?)\[/color\]#s');

	$replace = array('<strong>$1</strong>',
					 '<em>$1</em>',
					 '<span class="bbu">$1</span>',
					 'handle_url_tag(\'$1\')',
					 'handle_url_tag(\'$1\', \'$2\')',
					 '<a href="mailto:$1">$1</a>',
					 '<a href="mailto:$1">$2</a>',
					 '<span style="color: $1">$2</span>');

	// This thing takes a while! :)
	$text = preg_replace($pattern, $replace, $text);

	return $text;
}


//
// Make hyperlinks clickable
//
function do_clickable($text)
{
	global $pun_user;

	$text = ' '.$text;

	$text = preg_replace('#([\s\(\)])(https?|ftp|news){1}://([\w\-]+\.([\w\-]+\.)*[\w]+(:[0-9]+)?(/[^"\s\(\)<\[]*)?)#ie', '\'$1\'.handle_url_tag(\'$2://$3\')', $text);
	$text = preg_replace('#([\s\(\)])(www|ftp)\.(([\w\-]+\.)*[\w]+(:[0-9]+)?(/[^"\s\(\)<\[]*)?)#ie', '\'$1\'.handle_url_tag(\'$2.$3\', \'$2.$3\')', $text);

	return substr($text, 1);
}


//
// Convert a series of smilies to images
//
function do_smilies($text)
{
	global $smiley_text, $smiley_img;

	$text = ' '.$text.' ';

	$num_smilies = count($smiley_text);
	for ($i = 0; $i < $num_smilies; ++$i)
		$text = preg_replace("#(?<=.\W|\W.|^\W)".preg_quote($smiley_text[$i], '#')."(?=.\W|\W.|\W$)#m", '$1<img src="img/smilies/'.$smiley_img[$i].'" width="15" height="15" alt="'.substr($smiley_img[$i], 0, strrpos($smiley_img[$i], '.')).'" />$2', $text);

	return substr($text, 1, -1);
}


//
// Parse message text
//
function parse_message($text, $hide_smilies)
{
	global $pun_config, $lang_common, $pun_user;

	if ($pun_config['o_censoring'] == '1')
		$text = censor_words($text);

	// Convert applicable characters to HTML entities
	$text = pun_htmlspecialchars($text);

	// If the message contains a code tag we have to split it up (text within [code][/code] shouldn't be touched)
	if (strpos($text, '[code]') !== false && strpos($text, '[/code]') !== false)
	{
		list($inside, $outside) = split_text($text, '[code]', '[/code]');
		$outside = array_map('ltrim', $outside);
		$text = implode('<">', $outside);
	}

	if ($pun_config['o_make_links'] == '1')
		$text = do_clickable($text);

	if ($pun_config['o_smilies'] == '1' && $pun_user['show_smilies'] == '1' && $hide_smilies == '0')
		$text = do_smilies($text);

	if ($pun_config['p_message_bbcode'] == '1' && strpos($text, '[') !== false && strpos($text, ']') !== false)
	{
		$text = do_bbcode($text);

		if ($pun_config['p_message_img_tag'] == '1')
		{
//			$text = preg_replace('#\[img\]((ht|f)tps?://)([^\s<"]*?)\.(jpg|jpeg|png|gif)\[/img\]#e', 'handle_img_tag(\'$1$3.$4\')', $text);
			$text = preg_replace('#\[img\]((ht|f)tps?://)([^\s<"]*?)\[/img\]#e', 'handle_img_tag(\'$1$3\')', $text);
		}
	}

	// Deal with newlines, tabs and multiple spaces
	$pattern = array("\n", "\t", '  ', '  ');
	$replace = array('<br />', '&nbsp; &nbsp; ', '&nbsp; ', ' &nbsp;');
	$text = str_replace($pattern, $replace, $text);

	// If we split up the message before we have to concatenate it together again (code tags)
	if (isset($inside))
	{
		$outside = explode('<">', $text);
		$text = '';

		$num_tokens = count($outside);

		for ($i = 0; $i < $num_tokens; ++$i)
		{
			$text .= $outside[$i];
			if (isset($inside[$i]))
			{
				$num_lines = ((substr_count($inside[$i], "\n")) + 3) * 1.5;
				$height_str = ($num_lines > 35) ? '35em' : $num_lines.'em';
				$text .= '</p><div class="codebox"><div class="incqbox"><h4>'.$lang_common['Code'].':</h4><div class="scrollbox" style="height: '.$height_str.'"><pre>'.$inside[$i].'</pre></div></div></div><p>';
			}
		}
	}

	// Add paragraph tag around post, but make sure there are no empty paragraphs
	$text = str_replace('<p></p>', '', '<p>'.$text.'</p>');

	return $text;
}


//
// Parse signature text
//
function parse_signature($text)
{
	global $pun_config, $lang_common, $pun_user;

	if ($pun_config['o_censoring'] == '1')
		$text = censor_words($text);

	$text = pun_htmlspecialchars($text);

	if ($pun_config['o_make_links'] == '1')
		$text = do_clickable($text);

	if ($pun_config['o_smilies_sig'] == '1' && $pun_user['show_smilies'] != '0')
		$text = do_smilies($text);

	if ($pun_config['p_sig_bbcode'] == '1' && strpos($text, '[') !== false && strpos($text, ']') !== false)
	{
		$text = do_bbcode($text);

		if ($pun_config['p_sig_img_tag'] == '1')
		{
//			$text = preg_replace('#\[img\]((ht|f)tps?://)([^\s<"]*?)\.(jpg|jpeg|png|gif)\[/img\]#e', 'handle_img_tag(\'$1$3.$4\', true)', $text);
			$text = preg_replace('#\[img\]((ht|f)tps?://)([^\s<"]*?)\[/img\]#e', 'handle_img_tag(\'$1$3\', true)', $text);
		}
	}

	// Deal with newlines, tabs and multiple spaces
	$pattern = array("\n", "\t", '  ', '  ');
	$replace = array('<br />', '&nbsp; &nbsp; ', '&nbsp; ', ' &nbsp;');
	$text = str_replace($pattern, $replace, $text);

	return $text;
}
