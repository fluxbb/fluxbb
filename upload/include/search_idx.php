<?php
/**
 * Load various functions used in indexing posts and topics for searching.
 *
 * @copyright Copyright (C) 2008 FluxBB.org, based on code copyright (C) 2002-2008 PunBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package FluxBB
 */


// The contents of this file are very much inspired by the file functions_search.php
// from the phpBB Group forum software phpBB2 (http://www.phpbb.com). 


// Make sure no one attempts to run this script "directly"
if (!defined('FORUM'))
	exit;

if (!defined('FORUM_SEARCH_MIN_WORD'))
	define('FORUM_SEARCH_MIN_WORD', 3);
if (!defined('FORUM_SEARCH_MAX_WORD'))
	define('FORUM_SEARCH_MAX_WORD', 20);

//
// "Cleans up" a text string and returns an array of unique words
// This function depends on the current locale setting
//
function split_words($text)
{
	global $forum_user;
	static $noise_match, $noise_replace, $stopwords;

	$return = ($hook = get_hook('si_fn_split_words_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	if ($return != null)
		return $return;

	if (empty($noise_match))
	{
		$stopwords = (array)@file(FORUM_ROOT.'lang/'.$forum_user['language'].'/stopwords.txt');
		$stopwords = array_map('trim', $stopwords);

		($hook = get_hook('si_fn_split_words_modify_noise_matches')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	}

	// Remove BBCode
	$text = preg_replace('/\[\/?(b|u|i|h|colou?r|quote|code|img|url|email|list)(?:\=[^\]]*)?\]/', ' ', $text);
	// Remove any apostrophes which aren't part of words
	$text = substr(preg_replace('((?<=\W)\'|\'(?=\W))', '', ' '.$text.' '), 1, -1);
	// Remove symbols and multiple whitespace
	$text = preg_replace('/[\^\$&\(\)<>`"\|,@_\?%~\+\[\]{}:=\/#\\\\;!\*\.\s]+/', ' ', $text);

	// Fill an array with all the words
	$words = explode(' ', $text);

	if (!empty($words))
	{
		while (list($i, $word) = @each($words))
		{
			$num_chars = utf8_strlen($word);

			if ($num_chars < FORUM_SEARCH_MIN_WORD || $num_chars > FORUM_SEARCH_MAX_WORD || in_array($words[$i], $stopwords))
				unset($words[$i]);
		}
	}

	$return = ($hook = get_hook('si_fn_split_words_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	if ($return != null)
		return $return;

	return array_unique($words);
}


//
// Updates the search index with the contents of $post_id (and $subject)
//
function update_search_index($mode, $post_id, $message, $subject = null)
{
	global $db_type, $forum_db;

	$return = ($hook = get_hook('si_fn_update_search_index_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	if ($return != null)
		return;

	// Split old and new post/subject to obtain array of 'words'
	$words_message = split_words($message);
	$words_subject = ($subject) ? split_words($subject) : array();

	if ($mode == 'edit')
	{
		$query = array(
			'SELECT'	=> 'w.id, w.word, m.subject_match',
			'FROM'		=> 'search_words AS w',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'search_matches AS m',
					'ON'			=> 'w.id=m.word_id'
				)
			),
			'WHERE'		=> 'm.post_id='.$post_id
		);

		($hook = get_hook('si_fn_update_search_index_qr_get_current_words')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		// Declare here to stop array_keys() and array_diff() from complaining if not set
		$cur_words['post'] = array();
		$cur_words['subject'] = array();

		while ($row = $forum_db->fetch_row($result))
		{
			$match_in = ($row[2]) ? 'subject' : 'post';
			$cur_words[$match_in][$row[1]] = $row[0];
		}

		$forum_db->free_result($result);

		$words['add']['post'] = array_diff($words_message, array_keys($cur_words['post']));
		$words['add']['subject'] = array_diff($words_subject, array_keys($cur_words['subject']));
		$words['del']['post'] = array_diff(array_keys($cur_words['post']), $words_message);
		$words['del']['subject'] = array_diff(array_keys($cur_words['subject']), $words_subject);
	}
	else
	{
		$words['add']['post'] = $words_message;
		$words['add']['subject'] = $words_subject;
		$words['del']['post'] = array();
		$words['del']['subject'] = array();
	}

	unset($words_message);
	unset($words_subject);

	// Get unique words from the above arrays
	$unique_words = array_unique(array_merge($words['add']['post'], $words['add']['subject']));
	$unique_words = array_map(array($forum_db, 'escape'), $unique_words);

	if (!empty($unique_words))
	{
		$query = array(
			'SELECT'	=> 'id, word',
			'FROM'		=> 'search_words',
			'WHERE'		=> 'word IN(\''.implode('\',\'', $unique_words).'\')'
		);

		($hook = get_hook('si_fn_update_search_index_qr_get_existing_words')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		$word_ids = array();
		while ($row = $forum_db->fetch_row($result))
			$word_ids[] = $row[1];

		$forum_db->free_result($result);
		
		$word_ids = array_map(array($forum_db, 'escape'), $word_ids);

		$new_words = array_diff($unique_words, $word_ids);
		unset($unique_words);

		if (!empty($new_words))
		{
			$query = array(
				'INSERT'	=> 'word',
				'INTO'		=> 'search_words',
				'VALUES'	=> preg_replace('#^(.*)$#', '\'\1\'', $new_words)
			);

			($hook = get_hook('si_fn_update_search_index_qr_insert_words')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);
		}
		unset($new_words);
	}

	// Delete matches (only if editing a post)
	while (list($match_in, $wordlist) = @each($words['del']))
	{
		$subject_match = ($match_in == 'subject') ? 1 : 0;

		if (!empty($wordlist))
		{
			$sql = '';
			while (list(, $word) = @each($wordlist))
				$sql .= (($sql != '') ? ',' : '').$forum_db->escape($cur_words[$match_in][$word]);

			$query = array(
				'DELETE'	=> 'search_matches',
				'WHERE'		=> 'word_id IN('.$sql.') AND post_id='.$post_id.' AND subject_match='.$subject_match
			);

			($hook = get_hook('si_fn_update_search_index_qr_delete_matches')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);
		}
	}

	// Add new matches
	while (list($match_in, $wordlist) = @each($words['add']))
	{
		$wordlist = array_map(array($forum_db, 'escape'), $wordlist);
		$subject_match = ($match_in == 'subject') ? 1 : 0;

		if (!empty($wordlist))
		{
			$sql = 'INSERT INTO '.$forum_db->prefix.'search_matches (post_id, word_id, subject_match) SELECT '.$post_id.', id, '.$subject_match.' FROM '.$forum_db->prefix.'search_words WHERE word IN(\''.implode('\',\'', $wordlist).'\')';
			($hook = get_hook('si_fn_update_search_index_qr_delete_matches')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
			$forum_db->query($sql) or error(__FILE__, __LINE__);
		}
	}

	unset($words);
}


//
// Strip search index of indexed words in $post_ids
//
function strip_search_index($post_ids)
{
	global $db_type, $forum_db;

	$return = ($hook = get_hook('si_fn_strip_search_index_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	if ($return != null)
		return;

	$query = array(
		'SELECT'	=> 'word_id',
		'FROM'		=> 'search_matches',
		'WHERE'		=> 'post_id IN('.$post_ids.')',
		'GROUP BY'	=> 'word_id'
	);

	($hook = get_hook('si_fn_strip_search_index_qr_get_post_words')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	if ($forum_db->num_rows($result))
	{
		$word_ids = '';
		while ($row = $forum_db->fetch_row($result))
			$word_ids .= ($word_ids != '') ? ','.$row[0] : $row[0];

		$query = array(
			'SELECT'	=> 'word_id',
			'FROM'		=> 'search_matches',
			'WHERE'		=> 'word_id IN('.$word_ids.')',
			'GROUP BY'	=> 'word_id, subject_match',
			'HAVING'	=> 'COUNT(word_id)=1'
		);

		($hook = get_hook('si_fn_strip_search_index_qr_get_removable_words')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		if ($forum_db->num_rows($result))
		{
			$word_ids = '';
			while ($row = $forum_db->fetch_row($result))
				$word_ids .= ($word_ids != '') ? ','.$row[0] : $row[0];

			$query = array(
				'DELETE'	=> 'search_words',
				'WHERE'		=> 'id IN('.$word_ids.')'
			);

			($hook = get_hook('si_fn_strip_search_index_qr_delete_words')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);
		}
	}

	$query = array(
		'DELETE'	=> 'search_matches',
		'WHERE'		=> 'post_id IN('.$post_ids.')'
	);
	($hook = get_hook('si_fn_strip_search_index_qr_delete_matches')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	($hook = get_hook('si_fn_strip_search_index_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
}

define('FORUM_SEARCH_IDX_FUNCTIONS_LOADED', 1);
