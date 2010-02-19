<?php

/*---

	Copyright (C) 2008-2010 FluxBB.org
	based on code copyright (C) 2002-2005 Rickard Andersson
	License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher

---*/

// The contents of this file are very much inspired by the file functions_search.php
// from the phpBB Group forum software phpBB2 (http://www.phpbb.com)


// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;


//
// "Cleans up" a text string and returns an array of unique words
// This function depends on the current locale setting
//
function split_words($text, $allow_keywords)
{
	// Remove BBCode
	$text = preg_replace('/\[\/?(b|u|i|h|colou?r|quote|code|img|url|email|list)(?:\=[^\]]*)?\]/', ' ', $text);
	// Remove any apostrophes which aren't part of words
	$text = substr(preg_replace('((?<=\W)\'|\'(?=\W))', '', ' '.$text.' '), 1, -1);
	// Remove symbols and multiple whitespace
	$text = preg_replace('/[\^\$&\(\)<>`"“”\|,@_\?%~\+\[\]{}:=\/#\\\\;!\*\.…\s]+/', ' ', $text);
	// Replace multiple dashes with just one
	$text = preg_replace('/([\-])+/', '$1', $text);

	// Fill an array with all the words
	$words = array_unique(explode(' ', $text));

	// Remove any words that should not be indexed
	$words = array_filter($words, 'validate_search_word');

	// If we aren't allowed keywords, remove them
	if (!$allow_keywords)
		$words = array_filter($words, 'not_is_keyword');

	return $words;
}


//
// Checks if a word is a valid searchable word
//
function validate_search_word($word)
{
	global $pun_user;
	static $stopwords;

	// We must allow keywords through for now! Note the double negative...
	if (!not_is_keyword($word))
		return true;

	if (!isset($stopwords))
	{
		if (file_exists(PUN_ROOT.'lang/'.$pun_user['language'].'/stopwords.txt'))
		{
			$stopwords = file(PUN_ROOT.'lang/'.$pun_user['language'].'/stopwords.txt');
			$stopwords = array_map('pun_trim', $stopwords);
			$stopwords = array_filter($stopwords);
		}
		else
			$stopwords = array();

	}

	$num_chars = pun_strlen($word);
	return $num_chars >= PUN_SEARCH_MIN_WORD && $num_chars <= PUN_SEARCH_MAX_WORD && !in_array($word, $stopwords);
}


//
// Check a given word is not a search keyword.
// The logic is backwards so we can easily use it in array_filter to remove keywords.
//
function not_is_keyword($word)
{
	return $word != 'and' && $word != 'or' && $word != 'not';
}

//
// Updates the search index with the contents of $post_id (and $subject)
//
function update_search_index($mode, $post_id, $message, $subject = null)
{
	global $db_type, $db;

	$message = utf8_strtolower($message);
	$subject = utf8_strtolower($subject);

	// Split old and new post/subject to obtain array of 'words'
	$words_message = split_words($message, false);
	$words_subject = ($subject) ? split_words($subject, false) : array();

	if ($mode == 'edit')
	{
		$result = $db->query('SELECT w.id, w.word, m.subject_match FROM '.$db->prefix.'search_words AS w INNER JOIN '.$db->prefix.'search_matches AS m ON w.id=m.word_id WHERE m.post_id='.$post_id, true) or error('Unable to fetch search index words', __FILE__, __LINE__, $db->error());

		// Declare here to stop array_keys() and array_diff() from complaining if not set
		$cur_words['post'] = array();
		$cur_words['subject'] = array();

		while ($row = $db->fetch_row($result))
		{
			$match_in = ($row[2]) ? 'subject' : 'post';
			$cur_words[$match_in][$row[1]] = $row[0];
		}

		$db->free_result($result);

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

	if (!empty($unique_words))
	{
		$result = $db->query('SELECT id, word FROM '.$db->prefix.'search_words WHERE word IN(\''.implode('\',\'', array_map(array($db, 'escape'), $unique_words)).'\')', true) or error('Unable to fetch search index words', __FILE__, __LINE__, $db->error());

		$word_ids = array();
		while ($row = $db->fetch_row($result))
			$word_ids[$row[1]] = $row[0];

		$db->free_result($result);

		$new_words = array_diff($unique_words, array_keys($word_ids));
		unset($unique_words);

		if (!empty($new_words))
		{
			switch ($db_type)
			{
				case 'mysql':
				case 'mysqli':
				case 'mysql_innodb':
				case 'mysqli_innodb':
					$db->query('INSERT INTO '.$db->prefix.'search_words (word) VALUES(\''.implode('\'),(\'', array_map(array($db, 'escape'), $new_words)).'\')') or error('Unable to insert search index words', __FILE__, __LINE__, $db->error());
					break;

				default:
					foreach ($new_words as $word)
						$db->query('INSERT INTO '.$db->prefix.'search_words (word) VALUES(\''.$word.'\')') or error('Unable to insert search index words', __FILE__, __LINE__, $db->error());
					break;
			}
		}

		unset($new_words);
	}

	// Delete matches (only if editing a post)
	foreach ($words['del'] as $match_in => $wordlist)
	{
		$subject_match = ($match_in == 'subject') ? 1 : 0;

		if (!empty($wordlist))
		{
			$sql = '';
			foreach ($wordlist as $word)
				$sql .= (($sql != '') ? ',' : '').$cur_words[$match_in][$word];

			$db->query('DELETE FROM '.$db->prefix.'search_matches WHERE word_id IN('.$sql.') AND post_id='.$post_id.' AND subject_match='.$subject_match) or error('Unable to delete search index word matches', __FILE__, __LINE__, $db->error());
		}
	}

	// Add new matches
	foreach ($words['add'] as $match_in => $wordlist)
	{
		$subject_match = ($match_in == 'subject') ? 1 : 0;

		if (!empty($wordlist))
			$db->query('INSERT INTO '.$db->prefix.'search_matches (post_id, word_id, subject_match) SELECT '.$post_id.', id, '.$subject_match.' FROM '.$db->prefix.'search_words WHERE word IN(\''.implode('\',\'', array_map(array($db, 'escape'), $wordlist)).'\')') or error('Unable to insert search index word matches', __FILE__, __LINE__, $db->error());
	}

	unset($words);
}


//
// Strip search index of indexed words in $post_ids
//
function strip_search_index($post_ids)
{
	global $db_type, $db;

	switch ($db_type)
	{
		case 'mysql':
		case 'mysqli':
		case 'mysql_innodb':
		case 'mysqli_innodb':
		{
			$result = $db->query('SELECT word_id FROM '.$db->prefix.'search_matches WHERE post_id IN('.$post_ids.') GROUP BY word_id') or error('Unable to fetch search index word match', __FILE__, __LINE__, $db->error());

			if ($db->num_rows($result))
			{
				$word_ids = '';
				while ($row = $db->fetch_row($result))
					$word_ids .= ($word_ids != '') ? ','.$row[0] : $row[0];

				$result = $db->query('SELECT word_id FROM '.$db->prefix.'search_matches WHERE word_id IN('.$word_ids.') GROUP BY word_id HAVING COUNT(word_id)=1') or error('Unable to fetch search index word match', __FILE__, __LINE__, $db->error());

				if ($db->num_rows($result))
				{
					$word_ids = '';
					while ($row = $db->fetch_row($result))
						$word_ids .= ($word_ids != '') ? ','.$row[0] : $row[0];

					$db->query('DELETE FROM '.$db->prefix.'search_words WHERE id IN('.$word_ids.')') or error('Unable to delete search index word', __FILE__, __LINE__, $db->error());
				}
			}

			break;
		}

		default:
			$db->query('DELETE FROM '.$db->prefix.'search_words WHERE id IN(SELECT word_id FROM '.$db->prefix.'search_matches WHERE word_id IN(SELECT word_id FROM '.$db->prefix.'search_matches WHERE post_id IN('.$post_ids.') GROUP BY word_id) GROUP BY word_id HAVING COUNT(word_id)=1)') or error('Unable to delete from search index', __FILE__, __LINE__, $db->error());
			break;
	}

	$db->query('DELETE FROM '.$db->prefix.'search_matches WHERE post_id IN('.$post_ids.')') or error('Unable to delete search index word match', __FILE__, __LINE__, $db->error());
}
