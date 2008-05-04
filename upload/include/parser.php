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


// Here you can add additional smilies if you like (please note that you must escape singlequote and backslash)
$smiley_text = array(':)', '=)', ':|', '=|', ':(', '=(', ':D', '=D', ':o', ':O', ';)', ':/', ':P', ':lol:', ':mad:', ':rolleyes:', ':cool:');
$smiley_img = array('smile.png', 'smile.png', 'neutral.png', 'neutral.png', 'sad.png', 'sad.png', 'big_smile.png', 'big_smile.png', 'yikes.png', 'yikes.png', 'wink.png', 'hmm.png', 'tongue.png', 'lol.png', 'mad.png', 'roll.png', 'cool.png');

// Uncomment the next row if you add smilies that contain any of the characters &"'<>
//$smiley_text = array_map('forum_htmlspecialchars', $smiley_text);


//
// Make sure all BBCodes are lower case and do a little cleanup
//
function preparse_bbcode($text, &$errors, $is_signature = false)
{
	$return = ($hook = get_hook('ps_preparse_bbcode_start')) ? eval($hook) : null;
	if ($return != null)
		return $return;

	if ($is_signature)
	{
		global $lang_profile;

		if (preg_match('#\[quote=(&quot;|"|\'|)(.*)\\1\]|\[quote\]|\[/quote\]|\[code\]|\[/code\]#i', $text))
			$errors[] = $lang_profile['Signature quote/code'];
	}
	
	$temp_text = preparse_tags($text, $errors, $is_signature);
	if ($temp_text !== false)
		$text = $temp_text;
		
	$return = ($hook = get_hook('ps_preparse_bbcode_end')) ? eval($hook) : null;
	if ($return != null)
		return $return;
		
	return trim($text);
}

function preparse_tags($text, &$errors, $is_signature = false)
{
	global $lang_common;
	
    // Start off by making some arrays of bbcode tags and what we need to do with each one
    
    // List of all the tags
    $tags = array('quote','code','b','i','u','color','colour','url','email','img');
    // List of tags that we need to check are open (You could not put b,i,u in here then illegal nesting like [b][i][/b][/i] would be allowed)
    $tags_opened = $tags;
    // and tags we need to check are closed (the same as above, added it just in case)
    $tags_closed = $tags;
    // Tags we can nest and the depth they can be nested to (only quotes )
    $tags_nested = array('quote');
    $tags_nested_depth = array('quote' => 3);
    // Tags to ignore the contents of completely (just code)
    $tags_ignore = array('code');
    // Block tags, block tags can only go within another block tag, they cannot be in a normal tag
    $tags_block = array('quote','code');
	// Tags we trim interior whitespace
	$tags_trim = array('url','email','image');
	// Tags we remove quotes from the argument
	$tags_quotes = array('url','email','image');
	
	$return = ($hook = get_hook('ps_preparse_tags_start')) ? eval($hook) : null;
	if ($return != null)
		return $return;

    $split_text = preg_split("/(\[.*?\])/", $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    
    $open_tags = array('post');
    $opened_tag = 0;
    $new_text = '';
    $current_ignore = '';
    $current_nest = '';
    $current_depth = array();
    
    foreach($split_text as $current)
    {
        if (strpos($current, '[') === false && strpos($current, ']') === false)
        {
            // Its not a bbcode tag so we put it on the end and continue
            if (!$current_nest)
				if (in_array($open_tags[$opened_tag],$tags_trim))
					$new_text .= trim($current);
				else
					$new_text .= $current;
            continue;
        }
        
		if (strpos($current, '/') === 1)
		{
			$current_tag = substr($current, 2, -1);
		}
		else if (strpos($current, '=') === false)
		{
			$current_tag = substr($current, 1, -1);
		}
		else
		{
			$current_tag = substr($current,1,strpos($current, '=')-1);
			$current_arg = substr($current,strpos($current, '=')+1,-1);
		}
		
		$current_tag = strtolower($current_tag);

        if (!in_array($current_tag, $tags))
        {
            // Its not a bbcode tag so we put it on the end and continue
            if (!$current_nest)
                $new_text .= $current;
            continue;
        }
		
		$current = strtolower($current);
        
        // We definitely have a bbcode tag.
        
        if ($current_ignore)
        {
            //This is if we are currently in a tag which escapes other bbcode such as code
            if ($current_ignore == $current_tag && $current == '[/'.$current_ignore.']') 
                $current_ignore = '';
            $new_text .= $current;
				continue;
        }

        if ($current_nest)
        {
            // We are currently too deeply nested so lets see if we are closing the tag or not.
            if ($current_tag != $current_nest)
                continue;
                
            if (substr($current, 1, 1) == '/')
                $current_depth[$current_nest]--;
            else
                $current_depth[$current_nest]++;
            
            if ($current_depth[$current_nest] <= $tags_nested_depth[$current_nest])
                $current_nest = '';

            continue;
        }

        if (substr($current, 1, 1) == '/')
        {
            //This is if we are closing a tag
            if ($opened_tag == 0 || !in_array($current_tag,$open_tags))
            {
                //We tried to close a tag which is not open 
                if (in_array($current_tag, $tags_opened))
                {
                    $errors[] = sprintf($lang_common['BBCode error 1'], $current_tag);
                    return false;
                }
            }
            else
            {
                while (true)
                {
                    if ($open_tags[$opened_tag] == $current_tag)
                    {
                        array_pop($open_tags);
                        $opened_tag--;
                        break;
                    }
                    
                    if (in_array($open_tags[$opened_tag],$tags_closed) && in_array($current_tag,$tags_closed))
                    {
                        $errors[] = sprintf($lang_common['BBCode error 2'], $current_tag, $open_tags[$opened_tag]);
                        return false;
                    }
                    elseif (in_array($open_tags[$opened_tag],$tags_closed))
                        break;
                    else
                    {
                        array_pop($open_tags);
                        $opened_tag--;
                    }
                }
            }
            if (in_array($current_tag,$tags_nested))
            {
                if (isset($current_depth[$current_tag]))
                    $current_depth[$current_tag]--;
            }
            $new_text .= $current;
            continue;
        }
        else
        {
            // We are opening a tag
            if (in_array($current_tag,$tags_block) && !in_array($open_tags[$opened_tag],$tags_block) && $opened_tag != 0)
            {
                // We tried to open a block tag within a non-block tag
                $errors[] = sprintf($lang_common['BBCode error 3'], $current_tag, $open_tags[$opened_tag]);
                return false;
            }
            if (in_array($current_tag,$tags_ignore))
            {
                // Its an ignore tag so we don't need to worry about whats inside it,
                $current_ignore = $current_tag;
                $new_text .= $current;
                continue;
            }
            if (in_array($current_tag,$open_tags) && !in_array($current_tag,$tags_nested))
            {
                // We tried to open a tag within itself that shouldn't be allowed.
                $errors[] = sprintf($lang_common['BBCode error 4'], $current_tag);
                return false;
            }
            if (in_array($current_tag,$tags_nested))
            {
                if (isset($current_depth[$current_tag]))
                    $current_depth[$current_tag]++;
                else
                    $current_depth[$current_tag] = 1;
                    
                if ($current_depth[$current_tag] > $tags_nested_depth[$current_tag])
                {
                    $current_nest = $current_tag;
                    continue;
                }
            }
			if (strpos($current,'=') !== false && in_array($current_tag,$tags_quotes)) {
				$current = preg_replace('#\['.$current_tag.'=("|\'|)(.*?)\\1\]\s*#i', '['.$current_tag.'=$2]', $current);
			}

            $open_tags[] = $current_tag;
            $opened_tag++;
            $new_text .= $current;
            continue;
        }
    }
    // Check we closed all the tags we needed to
    foreach($tags_closed as $check)
    {
        if (in_array($check,$open_tags))
        {
            // We left an important tag open
            $errors[] = sprintf($lang_common['BBCode error 5'], $check);
            return false;
        }
    }
    if ($current_ignore)
    {
        // We left an ignore tag open
		$errors[] = sprintf($lang_common['BBCode error 5'], $current_ignore);
        return false;
    }

	$return = ($hook = get_hook('ps_preparse_tags_end')) ? eval($hook) : null;
	if ($return != null)
		return $return;
	
    return $new_text;
}

//
// Split text into chunks ($inside contains all text inside $start and $end, and $outside contains all text outside)
//
function split_text($text, $start, $end, $retab = true)
{
	global $forum_config;

	$tokens = explode($start, $text);

	$outside[] = $tokens[0];

	$num_tokens = count($tokens);
	for ($i = 1; $i < $num_tokens; ++$i)
	{
		$temp = explode($end, $tokens[$i]);
		$inside[] = $temp[0];
		$outside[] = $temp[1];
	}

	if ($forum_config['o_indent_num_spaces'] != 8 && $retab)
	{
		$spaces = str_repeat(' ', $forum_config['o_indent_num_spaces']);
		$inside = str_replace("\t", $spaces, $inside);
	}

	return array($inside, $outside);
}


//
// Truncate URL if longer than 55 characters (add http:// or ftp:// if missing)
//
function handle_url_tag($url, $link = '')
{
	global $forum_user;

	$full_url = str_replace(array(' ', '\'', '`', '"'), array('%20', '', '', ''), $url);
	if (strpos($url, 'www.') === 0)			// If it starts with www, we add http://
		$full_url = 'http://'.$full_url;
	else if (strpos($url, 'ftp.') === 0)	// Else if it starts with ftp, we add ftp://
		$full_url = 'ftp://'.$full_url;
	else if (!preg_match('#^([a-z0-9]{3,6})://#', $url, $bah)) 	// Else if it doesn't start with abcdef://, we add http://
		$full_url = 'http://'.$full_url;

	// Ok, not very pretty :-)
	$link = ($link == '' || $link == $url) ? ((strlen($url) > 55) ? substr($url, 0 , 39).' &#133; '.substr($url, -10) : $url) : stripslashes($link);

	return '<a href="'.$full_url.'">'.$link.'</a>';
}


//
// Turns an URL from the [img] tag into an <img> tag or a <a href...> tag
//
function handle_img_tag($url, $is_signature = false, $alt=null)
{
	global $lang_common, $forum_config, $forum_user;

	if ($alt == null)
		$alt = $url;
	
	$img_tag = '<a href="'.$url.'">&lt;'.$lang_common['Image link'].'&gt;</a>';

	if ($is_signature && $forum_user['show_img_sig'] != '0')
		$img_tag = '<img class="sigimage" src="'.$url.'" alt="'.forum_htmlencode($alt).'" />';
	else if (!$is_signature && $forum_user['show_img'] != '0')
		$img_tag = '<span class="postimg"><img src="'.$url.'" alt="'.forum_htmlencode($alt).'" /></span>';

	return $img_tag;
}


//
// Convert BBCodes to their HTML equivalent
//
function do_bbcode($text, $is_signature = false)
{
	global $lang_common, $forum_user, $forum_config;
	
	$return = ($hook = get_hook('ps_do_bbcode_start')) ? eval($hook) : null;
	if ($return != null)
		return $return;

	if (strpos($text, 'quote') !== false)
	{
		$text = str_replace('[quote]', '</p><div class="quotebox"><blockquote><p>', $text);
		$text = preg_replace('#\[quote=(&quot;|"|\'|)(.*)\\1\]#seU', '"</p><div class=\"quotebox\"><cite>".str_replace(array(\'[\', \'\\"\'), array(\'&#91;\', \'"\'), \'$2\')." ".$lang_common[\'wrote\'].":</cite><blockquote><p>"', $text);
		$text = preg_replace('#\[\/quote\]\s*#', '</p></blockquote></div><p>', $text);
	}

	$pattern = array('#\[b\](.*?)\[/b\]#s',
					 '#\[i\](.*?)\[/i\]#s',
					 '#\[u\](.*?)\[/u\]#s',
					 '#\[url\]([^\[]*?)\[/url\]#e',
					 '#\[url=([^\[]*?)\](.*?)\[/url\]#e',
					 '#\[email\]([^\[]*?)\[/email\]#',
					 '#\[email=([^\[]*?)\](.*?)\[/email\]#',
					 '#\[colou?r=([a-zA-Z]{3,20}|\#?[0-9a-fA-F]{6})](.*?)\[/colou?r\]#s');

	$replace = array('<strong>$1</strong>',
					 '<em>$1</em>',
					 '<em class="bbuline">$1</em>',
					 'handle_url_tag(\'$1\')',
					 'handle_url_tag(\'$1\', \'$2\')',
					 '<a href="mailto:$1">$1</a>',
					 '<a href="mailto:$1">$2</a>',
					 '<span style="color: $1">$2</span>');

	if ($forum_config['p_message_img_tag'] == '1')
	{
		$pattern[] = '#\[img\]((ht|f)tps?://)([^\s<"]*?)\[/img\]#e';
		$pattern[] = '#\[img=([^\[]*?)\]((ht|f)tps?://)([^\s<"]*?)\[/img\]#e';
		if ($is_signature)
		{
			$replace[] = 'handle_img_tag(\'$1$3\', true)';
			$replace[] = 'handle_img_tag(\'$2$4\', true, \'$1\')';
		}
		else
		{
			$replace[] = 'handle_img_tag(\'$1$3\', false)';
			$replace[] = 'handle_img_tag(\'$2$4\', false, \'$1\')';
		}
	}

	$return = ($hook = get_hook('ps_do_bbcode_replace')) ? eval($hook) : null;
	if ($return != null)
		return $return;
		
	// This thing takes a while! :)
	$text = preg_replace($pattern, $replace, $text);
	
	$return = ($hook = get_hook('ps_do_bbcode_end')) ? eval($hook) : null;
	if ($return != null)
		return $return;
		
	return $text;
}


//
// Make hyperlinks clickable
//
function do_clickable($text)
{
	global $forum_user;

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
	global $forum_config, $base_url, $smiley_text, $smiley_img;

	$text = ' '.$text.' ';

	$num_smilies = count($smiley_text);
	for ($i = 0; $i < $num_smilies; ++$i)
		$text = preg_replace("#(?<=.\W|\W.|^\W)".preg_quote($smiley_text[$i], '#')."(?=.\W|\W.|\W$)#m", '$1<img src="'.$base_url.'/img/smilies/'.$smiley_img[$i].'" width="15" height="15" alt="'.substr($smiley_img[$i], 0, strrpos($smiley_img[$i], '.')).'" />$2', $text);

	return substr($text, 1, -1);
}


//
// Parse message text
//
function parse_message($text, $hide_smilies)
{
	global $forum_config, $lang_common, $forum_user;

	$return = ($hook = get_hook('ps_parse_message_start')) ? eval($hook) : null;
	if ($return != null)
		return $return;
	
	if ($forum_config['o_censoring'] == '1')
		$text = censor_words($text);
		
	$return = ($hook = get_hook('ps_parse_message_post_censor')) ? eval($hook) : null;
	if ($return != null)
		return $return;

	// Convert applicable characters to HTML entities
	$text = forum_htmlencode($text);
	
	$return = ($hook = get_hook('ps_parse_message_pre_split')) ? eval($hook) : null;
	if ($return != null)
		return $return;

	// If the message contains a code tag we have to split it up (text within [code][/code] shouldn't be touched)
	if (strpos($text, '[code]') !== false && strpos($text, '[/code]') !== false)
	{
		list($inside, $outside) = split_text($text, '[code]', '[/code]');
		$outside = array_map('ltrim', $outside);
		$text = implode('[%]', $outside);
	}
	
	$return = ($hook = get_hook('ps_parse_message_post_split')) ? eval($hook) : null;
	if ($return != null)
		return $return;

	if ($forum_config['o_make_links'] == '1')
		$text = do_clickable($text);

	if ($forum_config['o_smilies'] == '1' && $forum_user['show_smilies'] == '1' && $hide_smilies == '0')
		$text = do_smilies($text);

	if ($forum_config['p_message_bbcode'] == '1' && strpos($text, '[') !== false && strpos($text, ']') !== false)
	{
		$text = do_bbcode($text);
	}

	$return = ($hook = get_hook('ps_parse_message_bbcode')) ? eval($hook) : null;
	if ($return != null)
		return $return;

	// Deal with newlines, tabs and multiple spaces
	$pattern = array("\n", "\t", '  ', '  ');
	$replace = array('<br />', '&nbsp; &nbsp; ', '&nbsp; ', ' &nbsp;');
	$text = str_replace($pattern, $replace, $text);

	$return = ($hook = get_hook('ps_parse_message_pre_merge')) ? eval($hook) : null;
	if ($return != null)
		return $return;
	
	// If we split up the message before we have to concatenate it together again (code tags)
	if (isset($inside))
	{
		$outside = explode('[%]', $text);
		$text = '';

		$num_tokens = count($outside);

		for ($i = 0; $i < $num_tokens; ++$i)
		{
			$text .= $outside[$i];
			if (isset($inside[$i]))
				$text .= '</p><div class="codebox"><strong>'.$lang_common['Code'].':</strong><pre><code>'.$inside[$i].'</code></pre></div><p>';
		}
	}
	
	$return = ($hook = get_hook('ps_parse_message_post_merge')) ? eval($hook) : null;
	if ($return != null)
		return $return;

	// Add paragraph tag around post, but make sure there are no empty paragraphs
	$text = preg_replace('#<br />\s*?<br />(?!\s*<br />)#i', "</p><p>", $text);
	$text = str_replace('<p></p>', '', '<p>'.$text.'</p>');

	$return = ($hook = get_hook('ps_parse_message_end')) ? eval($hook) : null;
	if ($return != null)
		return $return;
	
	return $text;
}


//
// Parse signature text
//
function parse_signature($text)
{
	global $forum_config, $lang_common, $forum_user;

	if ($forum_config['o_censoring'] == '1')
		$text = censor_words($text);

	$text = forum_htmlencode($text);

	if ($forum_config['o_make_links'] == '1')
		$text = do_clickable($text);

	if ($forum_config['o_smilies_sig'] == '1' && $forum_user['show_smilies'] != '0')
		$text = do_smilies($text);

	if ($forum_config['p_sig_bbcode'] == '1' && strpos($text, '[') !== false && strpos($text, ']') !== false)
		$text = do_bbcode($text, true);

	// Deal with newlines, tabs and multiple spaces
	$pattern = array("\n", "\t", '  ', '  ');
	$replace = array('<br />', '&nbsp; &nbsp; ', '&nbsp; ', ' &nbsp;');
	$text = str_replace($pattern, $replace, $text);

	return $text;
}
