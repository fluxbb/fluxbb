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
	$return = ($hook = get_hook('si_fn_split_words_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	if ($return != null)
		return $return;

	// Remove BBCode
	$text = preg_replace('/\[\/?(b|u|i|h|colou?r|quote|code|img|url|email|list)(?:\=[^\]]*)?\]/', ' ', $text);
	// Remove any apostrophes which aren't part of words
	$text = substr(preg_replace('((?<=\W)\'|\'(?=\W))', '', ' '.$text.' '), 1, -1);
	// Remove symbols and multiple whitespace
	$text = preg_replace('/[\^\$&\(\)<>`"\|,@_\?%~\+\[\]{}:=\/#\\\\;!\*\.\s]+/', ' ', $text);

	// Fill an array with all the words
	$words = array_unique(explode(' ', $text));
	// Remove any words that should not be indexed
	$words = array_filter($words, 'validate_search_word');

	$return = ($hook = get_hook('si_fn_split_words_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	if ($return != null)
		return $return;

	return $words;
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
		$cur_words = array(
			'post'		=> array(),
			'subject'	=> array()
		);

		while ($row = $forum_db->fetch_row($result))
			$cur_words[$row[2] ? 'subject' : 'post'][$row[1]] = $row[0];

		$forum_db->free_result($result);

		$words = array(
			'add'	=> array(
				'post'		=> array_diff($words_message, array_keys($cur_words['post'])),
				'subject'	=> array_diff($words_subject, array_keys($cur_words['subject']))
			),
			'del'	=> array(
				'post'		=> array_diff(array_keys($cur_words['post']), $words_message),
				'subject'	=> array_diff(array_keys($cur_words['subject']), $words_subject)
			)
		);
	}
	else
	{
		$words = array(
			'add'	=> array(
				'post'		=> $words_message,
				'subject'	=> $words_subject
			),
			'del'	=> array(
				'post'		=> array(),
				'subject'	=> array()
			)
		);
	}

	// Get unique words from the above arrays
	$unique_words = array_unique(array_merge($words['add']['post'], $words['add']['subject']));

	if (!empty($unique_words))
	{
		$query = array(
			'SELECT'	=> 'id, word',
			'FROM'		=> 'search_words',
			'WHERE'		=> 'word IN(\''.implode('\',\'', array_map(array($forum_db, 'escape'), $unique_words)).'\')'
		);

		($hook = get_hook('si_fn_update_search_index_qr_get_existing_words')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		$existing_words = array();
		while ($row = $forum_db->fetch_row($result))
			$existing_words[] = $row[1];

		$forum_db->free_result($result);

		$new_words = array_diff($unique_words, $existing_words);
		if (!empty($new_words))
		{
			$query = array(
				'INSERT'	=> 'word',
				'INTO'		=> 'search_words',
				'VALUES'	=> preg_replace('#^(.*)$#', '\'\1\'', array_map(array($forum_db, 'escape'), $new_words))
			);

			($hook = get_hook('si_fn_update_search_index_qr_insert_words')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);
		}
	}

	// Delete matches (only if editing a post)
	foreach ($words['del'] as $match_in => $wordlist)
	{
		if (!empty($wordlist))
		{
			$word_ids = array();
			foreach ($wordlist as $word)
				$word_ids[] = $cur_words[$match_in][$word];

			$query = array(
				'DELETE'	=> 'search_matches',
				'WHERE'		=> 'word_id IN('.implode(',', $word_ids).') AND post_id='.$post_id.' AND subject_match='.($match_in == 'subject' ? '1' : '0')
			);

			($hook = get_hook('si_fn_update_search_index_qr_delete_matches')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);
		}
	}

	// Add new matches
	foreach ($words['add'] as $match_in => $wordlist)
	{
		if (!empty($wordlist))
		{
			$subquery = array(
				'SELECT'	=> $post_id.', id, '.($match_in == 'subject' ? '1' : '0'),
				'FROM'		=> 'search_words',
				'WHERE'		=> 'word IN (\''.implode('\',\'', array_map(array($forum_db, 'escape'), $wordlist)).'\')'
			);

			// Really this should use the query builder too, though it doesn't currently support the syntax
			$sql = 'INSERT INTO '.$forum_db->prefix.'search_matches (post_id, word_id, subject_match) '.$forum_db->query_build($subquery, true);

			($hook = get_hook('si_fn_update_search_index_qr_delete_matches')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
			$forum_db->query($sql) or error(__FILE__, __LINE__);
		}
	}
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
		$word_ids = array();
		while ($row = $forum_db->fetch_row($result))
			$word_ids[] = $row[0];

		$query = array(
			'SELECT'	=> 'word_id',
			'FROM'		=> 'search_matches',
			'WHERE'		=> 'word_id IN('.implode(',', $word_ids).')',
			'GROUP BY'	=> 'word_id, subject_match',
			'HAVING'	=> 'COUNT(word_id)=1'
		);

		($hook = get_hook('si_fn_strip_search_index_qr_get_removable_words')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		if ($forum_db->num_rows($result))
		{
			$word_ids = array();
			while ($row = $forum_db->fetch_row($result))
				$word_ids[] = $row[0];

			$query = array(
				'DELETE'	=> 'search_words',
				'WHERE'		=> 'id IN('.implode(',', $word_ids).')'
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
