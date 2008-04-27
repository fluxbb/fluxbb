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


if (!defined('PUN_ROOT'))
	define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';

($hook = get_hook('se_start')) ? eval($hook) : null;

// Load the search.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/search.php';

if ($pun_user['g_read_board'] == '0')
	message($lang_common['No view']);
else if ($pun_user['g_search'] == '0')
	message($lang_search['No search permission']);


// Figure out what to do :-)
if (isset($_GET['action']) || isset($_GET['search_id']))
{
	// Grab and validate all the submitted data
	$action = (isset($_GET['action'])) ? $_GET['action'] : null;
	$value = (isset($_GET['value'])) ? intval($_GET['value']) : 86400;
	$forum = (isset($_GET['forum'])) ? intval($_GET['forum']) : -1;
	$sort_dir = (isset($_GET['sort_dir'])) ? (($_GET['sort_dir'] == 'DESC') ? 'DESC' : 'ASC') : 'DESC';
	if (isset($search_id)) unset($search_id);

	// If a search_id was supplied
	if (isset($_GET['search_id']))
	{
		$search_id = intval($_GET['search_id']);
		if ($db_type == 'mysql' || $db_type == 'mysqli')
			message($lang_common['Bad request']);
		if ($search_id < 1)
			message($lang_common['Bad request']);
	}

	// A list of valid actions (extensions can add their own actions to the array)
	$valid_actions = array('search', 'show_new', 'show_recent', 'show_user_posts', 'show_user_topics', 'show_subscriptions', 'show_unanswered');

	($hook = get_hook('se_search_selected')) ? eval($hook) : null;

	// Validate action
	if ($action && !in_array($action, $valid_actions))
		message($lang_common['Bad request']);

	// If it's a regular search (keywords and/or author)
	if ($action == 'search')
	{
		$keywords = (isset($_GET['keywords'])) ? strtolower(trim($_GET['keywords'])) : null;
		$author = (isset($_GET['author'])) ? strtolower(trim($_GET['author'])) : null;

		if (preg_match('#^[\*%]+$#', $keywords) || (pun_strlen(str_replace(array('*', '%'), '', $keywords)) < 3 && $db_type != 'mysql' && $db_type != 'mysqli'))
			$keywords = '';

		if (preg_match('#^[\*%]+$#', $author) || pun_strlen(str_replace(array('*', '%'), '', $author)) < 2)
			$author = '';

		if (!$keywords && !$author)
			message($lang_search['No terms']);

		$show_as = (isset($_GET['show_as'])) ? $_GET['show_as'] : 'posts';
		$sort_by = (isset($_GET['sort_by'])) ? intval($_GET['sort_by']) : null;
		$search_in = (!isset($_GET['search_in']) || $_GET['search_in'] == 'all') ? 0 : (($_GET['search_in'] == 'message') ? 1 : -1);

	}
	// If it's a user search (by id), make sure we have a user_id
	else if ($action == 'show_user_posts' || $action == 'show_user_topics')
	{
		$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
		if ($user_id < 2)
			message($lang_common['Bad request']);
	}

	// Flood protection
	if (!$pun_user['is_guest'] && $pun_user['last_search'] != '' && (time() - $pun_user['last_search']) < $pun_user['g_search_flood'] && (time() - $pun_user['last_search']) >= 0)
		message(sprintf($lang_search['Search flood'], $pun_user['g_search_flood']));

	// First of all lets find out if we need to cache the results, we only need to do this for detailed queries, not quicksearches or for mysql(i)
	if ($db_type != 'mysql' && $db_type != 'mysqli' && (isset($keywords) || isset($author)))
	{
		// We need to grab results, insert them into the cache and reload with a search id before showing them
		$keyword_results = $author_results = array();

		// If it's a search for keywords
		if ($keywords)
		{
			$stopwords = (array)@file(PUN_ROOT.'lang/'.$pun_user['language'].'/stopwords.txt');
			$stopwords = array_map('trim', $stopwords);

			// Filter out non-alphabetical chars
			$noise_match = array('^', '$', '&', '(', ')', '<', '>', '`', '\'', '"', '|', ',', '@', '_', '?', '%', '~', '[', ']', '{', '}', ':', '\\', '/', '=', '#', '\'', ';', '!', '¤');
			$noise_replace = array(' ', ' ', ' ', ' ', ' ', ' ', ' ', '',  '',   ' ', ' ', ' ', ' ', '',  ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', '' ,  ' ', ' ', ' ', ' ',  ' ', ' ', ' ');
			$keywords = str_replace($noise_match, $noise_replace, $keywords);

			// Strip out excessive whitespace
			$keywords = trim(preg_replace('#\s+#', ' ', $keywords));

			// Fill an array with all the words
			$keywords_array = explode(' ', $keywords);
			if (empty($keywords_array))
				message($lang_search['No hits']);

			while (list($i, $word) = @each($keywords_array))
			{
				$num_chars = pun_strlen($word);

				if ($word !== 'or' && ($num_chars < 3 || $num_chars > 20 || in_array($word, $stopwords)))
					unset($keywords_array[$i]);
			}

			$word_count = 0;
			$match_type = 'and';
			$result_list = array();
			@reset($keywords_array);
			while (list(, $cur_word) = @each($keywords_array))
			{
				switch ($cur_word)
				{
					case 'and':
					case 'or':
					case 'not':
						$match_type = $cur_word;
						break;

					default:
					{
						$cur_word = $pun_db->escape(str_replace('*', '%', $cur_word));

						$query = array(
							'SELECT'	=> 'm.post_id',
							'FROM'		=> 'search_words AS w',
							'JOINS'		=> array(
								array(
									'INNER JOIN'	=> 'search_matches AS m',
									'ON'			=> 'm.word_id=w.id'
								)
							),
							'WHERE'		=> 'w.word LIKE \''.$cur_word.'\''
						);

						// Search in what?
						if ($search_in)
							$query['WHERE'] .= ($search_in > 0 ? ' AND m.subject_match=0' : ' AND m.subject_match=1');

						($hook = get_hook('se_qr_get_keyword_hits')) ? eval($hook) : null;
						$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);

						$row = array();
						while ($temp = $pun_db->fetch_row($result))
						{
							$row[$temp[0]] = 1;

							if (!$word_count)
								$result_list[$temp[0]] = 1;
							else if ($match_type == 'or')
								$result_list[$temp[0]] = 1;
							else if ($match_type == 'not')
								$result_list[$temp[0]] = 0;
						}

						if ($match_type == 'and' && $word_count)
						{
							@reset($result_list);
							while (list($post_id,) = @each($result_list))
							{
								if (!isset($row[$post_id]))
									$result_list[$post_id] = 0;
							}
						}

						++$word_count;
						$pun_db->free_result($result);

						break;
					}
				}
			}

			@reset($result_list);
			while (list($post_id, $matches) = @each($result_list))
			{
				if ($matches)
					$keyword_results[] = $post_id;
			}

			unset($result_list);
		}

		// If it's a search for author name (and that author name isn't Guest)
		if ($author && strtolower($author) != 'guest' && strtolower($author) != strtolower($lang_common['Guest']))
		{
			$query = array(
				'SELECT'	=> 'u.id',
				'FROM'		=> 'users AS u',
				'WHERE'		=> 'u.username '.($db_type == 'pgsql' ? 'ILIKE' : 'LIKE').' \''.$pun_db->escape(str_replace('*', '%', $author)).'\''
			);

			($hook = get_hook('se_qr_get_author')) ? eval($hook) : null;
			$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);

			if ($pun_db->num_rows($result))
			{
				$user_ids = '';
				while ($row = $pun_db->fetch_row($result))
					$user_ids .= (($user_ids != '') ? ',' : '').$row[0];

				$query = array(
					'SELECT'	=> 'p.id',
					'FROM'		=> 'posts AS p',
					'WHERE'		=> 'p.poster_id IN('.$user_ids.')'
				);

				($hook = get_hook('se_qr_get_author_hits')) ? eval($hook) : null;
				$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);

				$search_ids = array();
				while ($row = $pun_db->fetch_row($result))
					$author_results[] = $row[0];

				$pun_db->free_result($result);
			}
		}

		if ($author && $keywords)
		{
			// If we searched for both keywords and author name we want the intersection between the results
			$search_ids = array_intersect($keyword_results, $author_results);
			unset($keyword_results, $author_results);
		}
		else if ($keywords)
			$search_ids = $keyword_results;
		else
			$search_ids = $author_results;

		if (count($search_ids) == 0)
			message($lang_search['No hits']);


		// Setup the default show_as topics search
		$query = array(
			'SELECT'	=> 't.id',
			'FROM'		=> 'posts AS p',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'topics AS t',
					'ON'			=> 't.id=p.topic_id'
				),
				array(
					'LEFT JOIN'		=> 'forum_perms AS fp',
					'ON'			=> '(fp.forum_id=t.forum_id AND fp.group_id='.$pun_user['g_id'].')'
				)
			),
			'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND p.id IN('.implode(',', $search_ids).')',
			'GROUP BY'	=> 't.id'
		);

		// Search a specific forum?
		if ($forum != -1 || ($forum == -1 && $pun_config['o_search_all_forums'] == '0' && !$pun_user['is_admmod']))
			$query['WHERE'] .= ' AND t.forum_id = '.$forum;

		// Adjust the query if show_as posts
		if ($show_as == 'posts')
		{
			$query['SELECT'] = 'p.id';
			unset($query['GROUP BY']);
		}

		($hook = get_hook('se_qr_get_hits')) ? eval($hook) : null;
		$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);

		$search_ids = array();
		while ($row = $pun_db->fetch_row($result))
			$search_ids[] = $row[0];


		// Prune "old" search results
		$query = array(
			'SELECT'	=> 'o.ident',
			'FROM'		=> 'online AS o'
		);

		($hook = get_hook('se_qr_get_online_idents')) ? eval($hook) : null;
		$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
		if ($pun_db->num_rows($result))
		{
			$online_idents = array();
			while ($row = $pun_db->fetch_row($result))
				$online_idents[] = '\''.$pun_db->escape($row[0]).'\'';

			$query = array(
				'DELETE'	=> 'search_cache',
				'WHERE'		=> 'ident NOT IN('.implode(',', $online_idents).')'
			);

			($hook = get_hook('se_qr_delete_old_cached_searches')) ? eval($hook) : null;
			$pun_db->query_build($query) or error(__FILE__, __LINE__);
		}

		// Final search results
		$search_results = implode(',', $search_ids);

		// Fill an array with our results and search properties
		$temp['search_results'] = $search_results;
		$temp['sort_by'] = $sort_by;
		$temp['sort_dir'] = $sort_dir;
		$temp['show_as'] = $show_as;
		$temp = serialize($temp);
		$search_id = mt_rand(1, 2147483647);

		$ident = ($pun_user['is_guest']) ? get_remote_address() : $pun_user['username'];

		$query = array(
			'INSERT'	=> 'id, ident, search_data',
			'INTO'		=> 'search_cache',
			'VALUES'	=> $search_id.', \''.$pun_db->escape($ident).'\', \''.$pun_db->escape($temp).'\''
		);

		($hook = get_hook('se_qr_cache_search')) ? eval($hook) : null;
		$pun_db->query_build($query) or error(__FILE__, __LINE__);

		$pun_db->end_transaction();
		$pun_db->close();

		// Redirect the user to the cached result page
		header('Location: '.str_replace('&amp;', '&', pun_link($pun_url['search_results'], $search_id)));
		exit;
	}

	// If we're still running we don't need to cache results but we still need to get them, either from the cache or from their respective sources.

	// We're doing a fulltext search!
	else if (($db_type == 'mysql' || $db_type == 'mysqli') && (isset($keywords) || isset($author)))
	{
		// Are we limiting the results to a specific forum?
		if ($forum != -1 || ($forum == -1 && $pun_config['o_search_all_forums'] == '0' && !$pun_user['is_admmod']))
			$forum_where = ' AND f.id = '.$forum;
		else
			$forum_where = '';

		// Sort out how to order the results
		switch ($sort_by)
		{
			case 1:
				$sort_by_sql = ($show_as == 'topics') ? 'poster' : 'p.poster';
				break;

			case 2:
				$sort_by_sql = 'subject';
				break;

			case 3:
				$sort_by_sql = 'forum_id';
				break;

			case 4:
				if ($show_as == 'posts')
					$sort_by_sql = 'MATCH(p.message) AGAINST(\''.$pun_db->escape($keywords).'\')';
				else
					$sort_by_sql = 'total_relevance';
				break;

			default:
				$sort_by_sql = ($show_as == 'topics') ? 'posted' : 'p.posted';
				break;
		}

		// Generate the query to give us our results
		if ($show_as == 'posts')
			$query = '
				SELECT
					p.id AS pid, p.poster AS pposter, p.posted AS pposted, p.poster_id, SUBSTRING(p.message, 1, 1000) AS message, t.id AS tid, t.poster, t.subject, t.first_post_id, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_replies, t.forum_id, f.forum_name
				FROM '.$pun_db->prefix.'posts AS p LEFT JOIN '.$pun_db->prefix.'topics AS t ON t.id=p.topic_id LEFT JOIN '.$pun_db->prefix.'forums AS f ON f.id=t.forum_id LEFT JOIN '.$pun_db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].')
				WHERE
					(fp.read_forum IS NULL OR fp.read_forum=1)
					'.($author ? 'AND p.poster LIKE \''.$pun_db->escape(str_replace('*', '%', $author)).'\'' : '').'
					'.($keywords ? 'AND MATCH(p.message) AGAINST(\''.$pun_db->escape($keywords).'\' IN BOOLEAN MODE)' : '').'
					'.$forum_where.'
				ORDER BY '.$sort_by_sql.' '.$sort_dir;
		else
		{
			$query = '
				SELECT
					*, SUM(relevance) AS total_relevance FROM
				(
					SELECT
						t.id AS tid, t.poster, t.subject, t.first_post_id, t.last_post, t.last_post_id, t.last_poster, t.num_replies, t.closed, t.forum_id, t.posted, f.forum_name, MATCH(t.subject) AGAINST(\''.$pun_db->escape($keywords).'\') AS relevance
					FROM '.$pun_db->prefix.'topics AS t LEFT JOIN '.$pun_db->prefix.'forums AS f ON f.id=t.forum_id LEFT JOIN '.$pun_db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].')
					WHERE
						(fp.read_forum IS NULL OR fp.read_forum=1)
						'.($author ? 'AND t.poster LIKE \''.$pun_db->escape(str_replace('*', '%', $author)).'\'' : '').'
						'.($keywords ? 'AND MATCH(t.subject) AGAINST(\''.$pun_db->escape($keywords).'\' IN BOOLEAN MODE)' : '').'
						'.$forum_where.'
					UNION
					SELECT
						t.id AS tid, t.poster, t.subject, t.first_post_id, t.last_post, t.last_post_id, t.last_poster, t.num_replies, t.closed, t.forum_id, t.posted, f.forum_name, MATCH(p.message) AGAINST(\''.$pun_db->escape($keywords).'\') AS relevance
					FROM '.$pun_db->prefix.'posts AS p INNER JOIN '.$pun_db->prefix.'topics AS t ON p.topic_id = t.id LEFT JOIN '.$pun_db->prefix.'forums AS f ON f.id=t.forum_id LEFT JOIN '.$pun_db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].')
					WHERE
						(fp.read_forum IS NULL OR fp.read_forum=1)
						'.($author ? 'AND p.poster LIKE \''.$pun_db->escape(str_replace('*', '%', $author)).'\'' : '').'
						'.($keywords ? 'AND MATCH(p.message) AGAINST(\''.$pun_db->escape($keywords).'\' IN BOOLEAN MODE)' : '').'
						'.$forum_where.'
				) AS tmp
				GROUP BY tid
				ORDER BY '.$sort_by_sql.' '.$sort_dir;
		}

		$url_type = $pun_url['search_resultft'];

		$search_id = array(rawurlencode($keywords), $forum, rawurlencode($author), ($search_in == 0 ) ? 'all' : (($search_in == 1) ? 'message' : 'subject'), $sort_by, $sort_dir, $show_as);
	}
	// We aren't doing a fulltext but we are getting results, if a valid search_id was supplied we attempt to fetch the search results from the cache
	else if (isset($search_id))
	{
		$ident = ($pun_user['is_guest']) ? get_remote_address() : $pun_user['username'];

		$query = array(
			'SELECT'	=> 'sc.search_data',
			'FROM'		=> 'search_cache AS sc',
			'WHERE'		=> 'sc.id='.$search_id.' AND sc.ident=\''.$pun_db->escape($ident).'\''
		);

		($hook = get_hook('se_qr_get_cached_search_data')) ? eval($hook) : null;
		$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
		if ($row = $pun_db->fetch_assoc($result))
		{
			$temp = unserialize($row['search_data']);

			$search_results = $temp['search_results'];
			$sort_by = $temp['sort_by'];
			$sort_dir = $temp['sort_dir'];
			$show_as = $temp['show_as'];

			unset($temp);
		}
		else
			message($lang_search['No hits']);

		switch ($sort_by)
		{
			case 1:
				$sort_by_sql = ($show_as == 'topics') ? 't.poster' : 'p.poster';
				break;

			case 2:
				$sort_by_sql = 't.subject';
				break;

			case 3:
				$sort_by_sql = 't.forum_id';
				break;

			default:
				$sort_by_sql = ($show_as == 'topics') ? 't.posted' : 'p.posted';
				break;
		}

		if ($show_as == 'posts')
		{
			$query = array(
				'SELECT'	=> 'p.id AS pid, p.poster AS pposter, p.posted AS pposted, p.poster_id, '.(($db_type != 'sqlite') ? 'SUBSTRING' : 'SUBSTR').'(p.message, 1, 1000) AS message, t.id AS tid, t.poster, t.subject, t.first_post_id, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_replies, t.forum_id, f.forum_name',
				'FROM'		=> 'posts AS p',
				'JOINS'		=> array(
					array(
						'INNER JOIN'	=> 'topics AS t',
						'ON'			=> 't.id=p.topic_id'
					),
					array(
						'INNER JOIN'	=> 'forums AS f',
						'ON'			=> 'f.id=t.forum_id'
					)
				),
				'WHERE'		=> 'p.id IN('.$search_results.')',
				'ORDER BY'	=> $sort_by_sql
			);

			($hook = get_hook('se_qr_get_cached_hits_as_posts')) ? eval($hook) : null;
		}
		else
		{
			$query = array(
				'SELECT'	=> 't.id AS tid, t.poster, t.subject, t.first_post_id, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_replies, t.closed, t.forum_id, f.forum_name',
				'FROM'		=> 'topics AS t',
				'JOINS'		=> array(
					array(
						'INNER JOIN'	=> 'forums AS f',
						'ON'			=> 'f.id=t.forum_id'
					)
				),
				'WHERE'		=> 't.id IN('.$search_results.')',
				'ORDER BY'	=> $sort_by_sql
			);

			($hook = get_hook('se_qr_get_cached_hits_as_topics')) ? eval($hook) : null;
		}

		$url_type = $pun_url['search_results'];
	}
	else if (in_array($action, $valid_actions))
	{
		$search_id = '';
		$show_as = 'topics';
		switch ($action)
		{
			case 'show_new':
				if ($pun_user['is_guest'])
					message($lang_common['No permission']);

				$query = array(
					'SELECT'	=> 't.id AS tid, t.poster, t.subject, t.last_post, t.last_post_id, t.last_poster, t.num_replies, t.closed, t.forum_id, f.forum_name',
					'FROM'		=> 'topics AS t',
					'JOINS'		=> array(
						array(
							'INNER JOIN'	=> 'forums AS f',
							'ON'			=> 'f.id=t.forum_id'
						),
						array(
							'LEFT JOIN'		=> 'forum_perms AS fp',
							'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].')'
						)
					),
					'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND t.last_post>'.$pun_user['last_visit'].' AND t.moved_to IS NULL',
					'ORDER BY'	=> 't.last_post DESC'
				);

				($hook = get_hook('se_qr_get_new')) ? eval($hook) : null;

				$url_type = $pun_url['search_new'];
				break;

			case 'show_recent':
				$query = array(
					'SELECT'	=> 't.id AS tid, t.poster, t.subject, t.last_post, t.last_post_id, t.last_poster, t.num_replies, t.closed, t.forum_id, f.forum_name',
					'FROM'		=> 'topics AS t',
					'JOINS'		=> array(
						array(
							'INNER JOIN'	=> 'forums AS f',
							'ON'			=> 'f.id=t.forum_id'
						),
						array(
							'LEFT JOIN'		=> 'forum_perms AS fp',
							'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].')'
						)
					),
					'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND t.last_post>'.(time() - $value).' AND t.moved_to IS NULL',
					'GROUP BY'	=> 't.id',
					'ORDER BY'	=> 't.last_post DESC'
				);

				($hook = get_hook('se_qr_get_recent')) ? eval($hook) : null;

				$url_type = $pun_url['search_24h'];
				break;

			case 'show_user_posts':
				$query = array(
					'SELECT'	=> 'p.id AS pid, p.poster AS pposter, p.posted AS pposted, p.poster_id, '.(($db_type != 'sqlite') ? 'SUBSTRING' : 'SUBSTR').'(p.message, 1, 1000) AS message, t.id AS tid, t.poster, t.subject, t.first_post_id, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_replies, t.forum_id, f.forum_name',
					'FROM'		=> 'posts AS p',
					'JOINS'		=> array(
						array(
							'INNER JOIN'	=> 'topics AS t',
							'ON'			=> 't.id=p.topic_id'
						),
						array(
							'INNER JOIN'	=> 'forums AS f',
							'ON'			=> 'f.id=t.forum_id'
						),
						array(
							'LEFT JOIN'		=> 'forum_perms AS fp',
							'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].')'
						)
					),
					'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND p.poster_id='.$user_id,
					'ORDER BY'	=> 'pposted DESC'
				);

				($hook = get_hook('se_qr_get_user_posts')) ? eval($hook) : null;

				$url_type = $pun_url['search_user_posts'];
				$search_id = $user_id;
				$show_as = 'posts';
				break;

			case 'show_user_topics':
				$query = array(
					'SELECT'	=> 't.id AS tid, t.poster, t.subject, t.last_post, t.last_post_id, t.last_poster, t.num_replies, t.closed, t.forum_id, f.forum_name',
					'FROM'		=> 'topics AS t',
					'JOINS'		=> array(
						array(
							'INNER JOIN'	=> 'posts AS p',
							'ON'			=> 't.first_post_id=p.id'
						),
						array(
							'INNER JOIN'	=> 'forums AS f',
							'ON'			=> 'f.id=t.forum_id'
						),
						array(
							'LEFT JOIN'		=> 'forum_perms AS fp',
							'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].')'
						)
					),
					'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND p.poster_id='.$user_id,
					'ORDER BY'	=> 't.last_post DESC'
				);

				($hook = get_hook('se_qr_get_user_topics')) ? eval($hook) : null;

				$url_type = $pun_url['search_user_topics'];
				$search_id = $user_id;
				break;

			case 'show_subscriptions':
				if ($pun_user['is_guest'])
					message($lang_common['Bad request']);

				$query = array(
					'SELECT'	=> 't.id AS tid, t.poster, t.subject, t.last_post, t.last_post_id, t.last_poster, t.num_replies, t.closed, t.forum_id, f.forum_name',
					'FROM'		=> 'topics AS t',
					'JOINS'		=> array(
						array(
							'INNER JOIN'	=> 'subscriptions AS s',
							'ON'			=> '(t.id=s.topic_id AND s.user_id='.$pun_user['id'].')'
						),
						array(
							'INNER JOIN'	=> 'forums AS f',
							'ON'			=> 'f.id=t.forum_id'
						),
						array(
							'LEFT JOIN'		=> 'forum_perms AS fp',
							'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].')'
						)
					),
					'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1)',
					'ORDER BY'	=> 't.last_post DESC'
				);

				($hook = get_hook('se_qr_get_subscriptions')) ? eval($hook) : null;

				$url_type = $pun_url['search_subscriptions'];
				break;

			case 'show_unanswered':
				$query = array(
					'SELECT'	=> 't.id AS tid, t.poster, t.subject, t.last_post, t.last_post_id, t.last_poster, t.num_replies, t.closed, t.forum_id, f.forum_name',
					'FROM'		=> 'topics AS t',
					'JOINS'		=> array(
						array(
							'INNER JOIN'	=> 'forums AS f',
							'ON'			=> 'f.id=t.forum_id'
						),
						array(
							'LEFT JOIN'		=> 'forum_perms AS fp',
							'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].')'
						)
					),
					'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND t.num_replies=0 AND t.moved_to IS NULL',
					'GROUP BY'	=> 't.id',
					'ORDER BY'	=> 't.last_post DESC'
				);

				($hook = get_hook('se_qr_get_unanswered')) ? eval($hook) : null;

				$url_type = $pun_url['search_unanswered'];
				break;

			default:
				// A good place for an extension to add a new search type (action must be added to $valid_actions first)
				($hook = get_hook('se_new_action')) ? eval($hook) : null;
				break;
		}
	}
	else
		message($lang_common['Bad request']);

	// We now have a query that will give us our results in $query, lets get the data!
	if (is_array($query))
		$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);
	else
		$result = $pun_db->query($query) or error(__FILE__, __LINE__);

	if (!$pun_user['is_guest'])
	{
		// Set the user's last_search time
		$query = array(
			'UPDATE'	=> 'users',
			'SET'		=> 'last_search='.time(),
			'WHERE'		=> 'id='.$pun_user['id'],
			'PARAMS'	=> array(
				'LOW_PRIORITY'	=> 1	// MySQL only
			)
		);

		($hook = get_hook('se_qr_update_last_search_time')) ? eval($hook) : null;
		$pun_db->query_build($query) or error(__FILE__, __LINE__);
	}

	// Make sure we actually have some results
	$num_hits = $pun_db->num_rows($result);
	if ($num_hits == 0)
	{
		$pun_page['search_again'] = '<a href="'.pun_link($pun_url['search']).'">'.$lang_search['Perform new search'].'</a>';

		switch ($action)
		{
			case 'show_new':
				message($lang_search['No new posts'], $pun_page['search_again']);

			case 'show_recent':
				message($lang_search['No recent posts'], $pun_page['search_again']);

			case 'show_user_posts':
				message($lang_search['No user posts'], $pun_page['search_again']);

			case 'show_user_topics':
				message($lang_search['No user topics'], $pun_page['search_again']);

			case 'show_subscriptions':
				message($lang_search['No subscriptions'], $pun_page['search_again']);

			case 'show_unanswered':
				message($lang_search['No unanswered'], $pun_page['search_again']);

			default:
				($hook = get_hook('se_new_action_no_hits')) ? eval($hook) : null;
				message($lang_search['No hits'], $pun_page['search_again']);
		}
	}

	// Get topic/forum tracking data
	if (!$pun_user['is_guest'])
		$tracked_topics = get_tracked_topics();

	// Determine the topic or post offset (based on $_GET['p'])
	$pun_page['per_page'] = ($show_as == 'posts') ? $pun_user['disp_posts'] : $pun_user['disp_topics'];
	$pun_page['num_pages'] = ceil($num_hits / $pun_page['per_page']);

	$pun_page['page'] = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $pun_page['num_pages']) ? 1 : $_GET['p'];
	$pun_page['start_from'] = $pun_page['per_page'] * ($pun_page['page'] - 1);
	$pun_page['finish_at'] = min(($pun_page['start_from'] + $pun_page['per_page']), $num_hits);

	// Generate paging links
	$pun_page['page_post'] = '<p class="paging"><span class="pages">'.$lang_common['Pages'].'</span> '.paginate($pun_page['num_pages'], $pun_page['page'], $url_type, $lang_common['Paging separator'], $search_id).'</p>';


	// Fill $search_set with out search hits
	$search_set = array();
	$row_num = 0;
	while ($row = $pun_db->fetch_assoc($result))
	{
		if ($pun_page['start_from'] <= $row_num && $pun_page['finish_at'] > $row_num)
			$search_set[] = $row;
		++$row_num;
	}

	$pun_db->free_result($result);

	($hook = get_hook('se_post_results_fetched')) ? eval($hook) : null;


	// Navigation links for header and page numbering for title/meta description
	if ($pun_page['page'] < $pun_page['num_pages'])
	{
		$pun_page['nav'][] = '<link rel="last" href="'.pun_sublink($url_type, $pun_url['page'], $pun_page['num_pages'], $search_id).'" title="'.$lang_common['Page'].' '.$pun_page['num_pages'].'" />';
		$pun_page['nav'][] = '<link rel="next" href="'.pun_sublink($url_type, $pun_url['page'], ($pun_page['page'] + 1), $search_id).'" title="'.$lang_common['Page'].' '.($pun_page['page'] + 1).'" />';
	}
	if ($pun_page['page'] > 1)
	{
		$pun_page['nav'][] = '<link rel="prev" href="'.pun_sublink($url_type, $pun_url['page'], ($pun_page['page'] - 1), $search_id).'" title="'.$lang_common['Page'].' '.($pun_page['page'] - 1).'" />';
		$pun_page['nav'][] = '<link rel="first" href="'.pun_link($url_type, $search_id).'" title="'.$lang_common['Page'].' 1" />';
	}

	// Setup breadcrumbs and results header and footer
	$pun_page['main_foot_options'][] = '<a class="user-option" href="'.pun_link($pun_url['search']).'">'.$lang_search['Perform new search'].'</a>';
	$pun_page['crumbs'][] = array($pun_config['o_board_title'], pun_link($pun_url['index']));

	switch ($action)
	{
		case 'show_new':
			$pun_page['crumbs'][] = $lang_common['New posts'];
			$pun_page['main_info'] = (($pun_page['num_pages'] == 1) ? sprintf($lang_common['Page info'], $lang_search['Topics with new'], $num_hits) : '<span>'.sprintf($lang_common['Page number'], $pun_page['page'], $pun_page['num_pages']).' </span>'.sprintf($lang_common['Paged info'], $lang_search['Topics with new'], $pun_page['start_from'] + 1, $pun_page['finish_at'], $num_hits));
			$pun_page['main_foot_options'][] = '<a class="user-option" href="'.pun_link($pun_url['mark_read'], generate_form_token('markread'.$pun_user['id'])).'">'.$lang_common['Mark all as read'].'</a>';
			break;

		case 'show_recent':
			$pun_page['crumbs'][] = $lang_common['Recent posts'];
			$pun_page['main_info'] = (($pun_page['num_pages'] == 1) ? sprintf($lang_common['Page info'], $lang_search['Topics with recent'], $num_hits) : '<span>'.sprintf($lang_common['Page number'], $pun_page['page'], $pun_page['num_pages']).' </span>'.sprintf($lang_common['Paged info'], $lang_search['Topics with recent'], $pun_page['start_from'] + 1, $pun_page['finish_at'], $num_hits));
			break;

		case 'show_unanswered':
			$pun_page['crumbs'][] = $lang_common['Unanswered topics'];
			$pun_page['main_info'] = (($pun_page['num_pages'] == 1) ? sprintf($lang_common['Page info'], $lang_common['Unanswered topics'], $num_hits) : '<span>'.sprintf($lang_common['Page number'], $pun_page['page'], $pun_page['num_pages']).' </span>'.sprintf($lang_common['Paged info'], $lang_common['Unanswered topics'], $pun_page['start_from'] + 1, $pun_page['finish_at'], $num_hits));
			break;

		case 'show_user_posts':
			$pun_page['crumbs'][] = sprintf($lang_search['Posts by'], $search_set[0]['pposter']);
			$pun_page['main_info'] = (($pun_page['num_pages'] == 1) ? sprintf($lang_common['Page info'], sprintf($lang_search['Posts by'], $search_set[0]['pposter']), $num_hits) : '<span>'.sprintf($lang_common['Page number'], $pun_page['page'], $pun_page['num_pages']).' </span>'.sprintf($lang_common['Paged info'], sprintf($lang_search['Posts by'], $search_set[0]['pposter']), $pun_page['start_from'] + 1, $pun_page['finish_at'], $num_hits));
			$pun_page['main_foot_options'][] = '<a class="user-option" href="'.pun_link($pun_url['search_user_topics'], $search_id).'">'.sprintf($lang_search['Topics by'], $search_set[0]['pposter']).'</a>';
			break;

		case 'show_user_topics':
			$pun_page['crumbs'][] = sprintf($lang_search['Topics by'], $search_set[0]['poster']);
			$pun_page['main_info'] = (($pun_page['num_pages'] == 1) ? sprintf($lang_common['Page info'], sprintf($lang_search['Topics by'], $search_set[0]['poster']), $num_hits) : '<span>'.sprintf($lang_common['Page number'], $pun_page['page'], $pun_page['num_pages']).' </span>'.sprintf($lang_common['Paged info'], sprintf($lang_search['Topics by'], $search_set[0]['poster']), $pun_page['start_from'] + 1, $pun_page['finish_at'], $num_hits));
			$pun_page['main_foot_options'][] = '<a class="user-option" href="'.pun_link($pun_url['search_user_posts'], $search_id).'">'.sprintf($lang_search['Posts by'], $search_set[0]['poster']).'</a>';
			break;

		case 'show_subscriptions':
			$pun_page['crumbs'][] = $lang_common['Your subscriptions'];
			$pun_page['main_info'] = (($pun_page['num_pages'] == 1) ? sprintf($lang_common['Page info'], $lang_common['Your subscriptions'], $num_hits) : '<span>'.sprintf($lang_common['Page number'], $pun_page['page'], $pun_page['num_pages']).' </span>'.sprintf($lang_common['Paged info'], $lang_common['Your subscriptions'], $pun_page['start_from'] + 1, $pun_page['finish_at'], $num_hits));
			break;

		default:
			$pun_page['crumbs'][] = $lang_search['Search results'];
			$pun_page['main_info'] = (($pun_page['num_pages'] == 1) ? sprintf($lang_common['Page info'], (($show_as=='topics') ? $lang_common['Topics'] : $lang_common['Posts']), $num_hits) : '<span>'.sprintf($lang_common['Page number'], $pun_page['page'], $pun_page['num_pages']).' </span>'.sprintf($lang_common['Paged info'], (($show_as=='topics') ? $lang_common['Topics'] : $lang_common['Posts']), $pun_page['start_from'] + 1, $pun_page['finish_at'], $num_hits));
			break;
	}

	($hook = get_hook('se_results_pre_header_load')) ? eval($hook) : null;

	define('PUN_PAGE', $show_as == 'topics' ? 'searchtopics' : 'searchposts');
	require PUN_ROOT.'header.php';

	if ($show_as == 'topics')
	{
		// Load the forum.php language file
		require PUN_ROOT.'lang/'.$pun_user['language'].'/forum.php';

?>
<div id="pun-main" class="main paged">

	<h1><span><?php echo end($pun_page['crumbs']) ?></span></h1>

	<div class="paged-head">
		<?php echo $pun_page['page_post']."\n" ?>
	</div>

	<div class="main-head">
		<h2><span><?php echo $pun_page['main_info'] ?></span></h2>
	</div>

	<div class="main-content forum">
		<table cellspacing="0" summary="<?php echo $lang_search['Table summary'] ?>">
			<thead>
				<tr>
					<th class="tcl" scope="col"><?php echo $lang_common['Topic']; ?></th>
					<th class="tc2" scope="col"><?php echo $lang_common['Forum'] ?></th>
					<th class="tc3" scope="col"><?php echo $lang_common['Replies'] ?></th>
					<th class="tcr" scope="col"><?php echo $lang_common['Last post'] ?></th>
				</tr>
			</thead>
			<tbody class="statused">
<?php

	}
	else
	{
		// Load the topic.php language file
		require PUN_ROOT.'lang/'.$pun_user['language'].'/topic.php';

?>
<div id="pun-main" class="main paged">

	<h1><span><?php echo end($pun_page['crumbs']) ?></span></h1>

	<div class="paged-head">
		<?php echo $pun_page['page_post']."\n" ?>
	</div>

	<div class="main-head">
		<h2><span><?php echo $pun_page['main_info'] ?></span></h2>
	</div>

	<div class="main-content topic">
<?php

	}

	$pun_page['item_count'] = 0;

	// Finally, lets loop through the results and output them
	for ($i = 0; $i < count($search_set); ++$i)
	{
		++$pun_page['item_count'];

		if ($pun_config['o_censoring'] == '1')
			$search_set[$i]['subject'] = censor_words($search_set[$i]['subject']);

		if ($show_as == 'posts')
		{
			// Generate the post heading
			$pun_page['item_head'] = array(
				'num'	=> '<strong>'.($pun_page['start_from'] + $pun_page['item_count']).'</strong>',
				'user'	=> '<cite>'.(($search_set[$i]['pid'] == $search_set[$i]['first_post_id']) ? sprintf($lang_topic['Topic by'], pun_htmlencode($search_set[$i]['pposter'])) : sprintf($lang_topic['Reply by'], pun_htmlencode($search_set[$i]['pposter']))).'</cite>',
				'date'	=> '<span>'.format_time($search_set[$i]['pposted']).'</span>'
			);

			$pun_page['item_head'] = '<a class="permalink" rel="bookmark" title="'.$lang_topic['Permalink post'].'" href="'.pun_link($pun_url['post'], $search_set[$i]['pid']).'">'.implode(' ', $pun_page['item_head']).'</a>';

			// Generate author identification
			$pun_page['user_ident'] = ($search_set[$i]['poster_id'] > 1 && $pun_user['g_view_users'] == '1') ? '<strong class="username"><a title="'.sprintf($lang_search['Go to profile'], pun_htmlencode($search_set[$i]['pposter'])).'" href="'.pun_link($pun_url['user'], $search_set[$i]['poster_id']).'">'.pun_htmlencode($search_set[$i]['pposter']).'</a></strong>' : '<strong class="username">'.pun_htmlencode($search_set[$i]['pposter']).'</strong>';

			// Generate the post options links
			$pun_page['post_options'] = array();

			$pun_page['post_options'][] = '<a href="'.pun_link($pun_url['forum'], array($search_set[$i]['forum_id'], sef_friendly($search_set[$i]['forum_name']))).'"><span>'.$lang_search['Go to forum'].'<span>: '.pun_htmlencode($search_set[$i]['forum_name']).'</span></span></a>';

			if ($search_set[$i]['pid'] != $search_set[$i]['first_post_id'])
				$pun_page['post_options'][] = '<a class="permalink" rel="bookmark" title="'.$lang_topic['Permalink topic'].'" href="'.pun_link($pun_url['topic'], array($search_set[$i]['tid'], sef_friendly($search_set[$i]['subject']))).'"><span>'.$lang_search['Go to topic'].'<span>: '.pun_htmlencode($search_set[$i]['subject']).'</span></span></a>';

			$pun_page['post_options'][] = '<a class="permalink" rel="bookmark" title="'.$lang_topic['Permalink post'].'" href="'.pun_link($pun_url['post'], $search_set[$i]['pid']).'"><span>'.$lang_search['Go to post'].' <span>'.($pun_page['start_from'] + $pun_page['item_count']).'</span></span></a>';

			// Generate the post title
			$pun_page['item_subject'] = array();
			if ($search_set[$i]['pid'] == $search_set[$i]['first_post_id'])
				$pun_page['item_subject'][] = '<strong>'.$lang_common['Topic'].': '.pun_htmlencode($search_set[$i]['subject']).'</strong>';
			else
				$pun_page['item_subject'][] = '<strong>'.$lang_common['Re'].' '.pun_htmlencode($search_set[$i]['subject']).'</strong>';

			$pun_page['item_subject'][] = sprintf($lang_search['Topic info'], pun_htmlencode($search_set[$i]['poster']), pun_htmlencode($search_set[$i]['forum_name']), $search_set[$i]['num_replies']);

			// Generate the post message
			if ($pun_config['o_censoring'] == '1')
				$search_set[$i]['message'] = censor_words($search_set[$i]['message']);

			$pun_page['message'] = str_replace("\n", '<br />', pun_htmlencode($search_set[$i]['message']));

			if (pun_strlen($pun_page['message']) >= 1000)
				$pun_page['message'] .= '&#160;&#8230;';

			// Give the post some class
			$pun_page['item_status'] = array(
				'post',
				(($pun_page['item_count'] % 2 == 0) ? 'odd' : 'even' )
			);

			if ($pun_page['item_count'] == 1)
				$pun_page['item_status'][] = 'firstpost';

			if (($pun_page['start_from'] + $pun_page['item_count']) == $pun_page['finish_at'])
				$pun_page['item_status'][] = 'lastpost';

			if ($search_set[$i]['pid'] == $search_set[$i]['first_post_id'])
				$pun_page['item_status'][] = 'topicpost';

			($hook = get_hook('se_results_posts_row_pre_display')) ? eval($hook) : null;

?>
		<div class="<?php echo implode(' ', $pun_page['item_status']) ?>">
			<div class="postmain">
				<div class="posthead">
					<h3><?php echo $pun_page['item_head'] ?></h3>
				</div>
				<div class="postbody">
					<div class="user">
						<h4 class="user-ident"><?php echo $pun_page['user_ident'] ?></h4>
					</div>
					<div class="post-entry">
						<h4 class="entry-title"><?php echo implode(' ', $pun_page['item_subject']) ?></h4>
						<div class="entry-content">
							<p><?php echo $pun_page['message'] ?></p>
						</div>
					</div>
				</div>
				<div class="postfoot">
					<div class="post-options"><?php echo implode(' ', $pun_page['post_options']) ?></div>
				</div>
			</div>
		</div>
<?php

		}
		else
		{
			++$pun_page['item_count'];

			// Start from scratch
			$pun_page['item_subject'] = $pun_page['item_status'] = $pun_page['item_last_post'] = $pun_page['item_nav'] = array();
			$pun_page['item_indicator'] = '';
			$pun_page['item_alt_message'] = $lang_common['Topic'].' '.($pun_page['start_from'] + $pun_page['item_count']);

			if ($search_set[$i]['closed'] != '0')
			{
				$pun_page['item_subject'][] = $lang_common['Closed'];
				$pun_page['item_status'][] = 'closed';
			}

			$pun_page['item_subject'][] = '<a href="'.pun_link($pun_url['topic'], array($search_set[$i]['tid'], sef_friendly($search_set[$i]['subject']))).'">'.pun_htmlencode($search_set[$i]['subject']).'</a>';

			$pun_page['item_pages'] = ceil(($search_set[$i]['num_replies'] + 1) / $pun_user['disp_posts']);

			if ($pun_page['item_pages'] > 1)
				$pun_page['item_nav'][] = paginate($pun_page['item_pages'], -1, $pun_url['topic'], $lang_common['Page separator'], array($search_set[$i]['tid'], sef_friendly($search_set[$i]['subject'])));

			// Does this topic contain posts we haven't read? If so, tag it accordingly.
			if (!$pun_user['is_guest'] && $search_set[$i]['last_post'] > $pun_user['last_visit'] && (!isset($tracked_topics['topics'][$search_set[$i]['tid']]) || $tracked_topics['topics'][$search_set[$i]['tid']] < $search_set[$i]['last_post']) && (!isset($tracked_topics['forums'][$search_set[$i]['forum_id']]) || $tracked_topics['forums'][$search_set[$i]['forum_id']] < $search_set[$i]['last_post']))
			{
				$pun_page['item_nav'][] = '<a href="'.pun_link($pun_url['topic_new_posts'], array($search_set[$i]['tid'], sef_friendly($search_set[$i]['subject']))).'" title="'.$lang_forum['New posts info'].'">'.$lang_common['New posts'].'</a>';
				$pun_page['item_status'][] = 'new';
			}

			if (!empty($pun_page['item_nav']))
				$pun_page['item_subject'][] = '<span class="topic-nav">[&#160;'.implode('&#160;&#160;', $pun_page['item_nav']).'&#160;]</span>';

			$pun_page['item_subject'][] = '<span class="byuser">'.sprintf($lang_common['By user'], pun_htmlencode($search_set[$i]['poster'])).'</span>';
			$pun_page['item_last_post'][] = '<a href="'.pun_link($pun_url['post'], $search_set[$i]['last_post_id']).'">'.format_time($search_set[$i]['last_post']).'</a>';
			$pun_page['item_last_post'][] = '<span class="byuser">'.sprintf($lang_common['By user'], pun_htmlencode($search_set[$i]['last_poster'])).'</span>';
			$pun_page['item_indicator'] = '<span class="status '.implode(' ', $pun_page['item_status']).'" title="'.$pun_page['item_alt_message'].'"><img src="'.$base_url.'/style/'.$pun_user['style'].'/status.png" alt="'.$pun_page['item_alt_message'].'" />'.$pun_page['item_indicator'].'</span>';

			($hook = get_hook('se_results_topics_row_pre_display')) ? eval($hook) : null;

?>
				<tr class="<?php echo ($pun_page['item_count'] % 2 != 0) ? 'odd' : 'even' ?>">
					<td class="tcl"><?php echo $pun_page['item_indicator'].' '.implode(' ', $pun_page['item_subject']) ?></td>
					<td class="tc2"><?php echo pun_htmlencode($search_set[$i]['forum_name']) ?></td>
					<td class="tc3"><?php echo $search_set[$i]['num_replies'] ?></td>
					<td class="tcr"><?php echo implode(' ', $pun_page['item_last_post']) ?></td>
				</tr>
<?php

		}
	}

	if ($show_as == 'topics')
	{

?>
			</tbody>
		</table>
<?php

	}

?>
	</div>

	<div class="main-foot">
		<p class="h2"><strong><?php echo $pun_page['main_info'] ?></strong></p>
<?php if (!empty($pun_page['main_foot_options'])): ?>			<p class="main-options"><?php echo implode(' ', $pun_page['main_foot_options']) ?></p>
<?php endif; ?>	</div>

	<div class="paged-foot">
		<?php echo $pun_page['page_post']."\n" ?>
	</div>

</div>

<div id="pun-crumbs-foot">
	<p class="crumbs"><?php echo generate_crumbs(false) ?></p>
</div>
<?php

	require PUN_ROOT.'footer.php';
}

// Setup form
$pun_page['set_count'] = $pun_page['fld_count'] = 0;

// Setup form information
$pun_page['frm-info'] = array('<li><span>'.$lang_search['Search info'].'</span></li>');
if ($db_type == 'mysql' || $db_type == 'mysqli')
{
	$pun_page['frm-info'][] = '<li><span>'.$lang_search['Refine info fulltext'].'</span></li>';
	$pun_page['frm-info'][] = '<li><span>'.$lang_search['Wildcard info fulltext'].'</span></li>';
}
else
{
	$pun_page['frm-info'][] = '<li><span>'.$lang_search['Refine info'].'</span></li>';
	$pun_page['frm-info'][] = '<li><span>'.$lang_search['Wildcard info'].'<span></li>';
}

// Setup predefined search (pds) links
$pun_page['pd_searches'] = array(
	'<a href="'.pun_link($pun_url['search_24h']).'">'.$lang_common['Recent posts'].'</a>',
	'<a href="'.pun_link($pun_url['search_unanswered']).'">'.$lang_common['Unanswered topics'].'</a>'
);

if (!$pun_user['is_guest'])
{
	array_push(
		$pun_page['pd_searches'],
		'<a href="'.pun_link($pun_url['search_new']).'" title="'.$lang_common['New posts info'].'">'.$lang_common['New posts'].'</a>',
		'<a href="'.pun_link($pun_url['search_user_posts'], $pun_user['id']).'">'.$lang_common['Your posts'].'</a>',
		'<a href="'.pun_link($pun_url['search_user_topics'], $pun_user['id']).'">'.$lang_common['Your topics'].'</a>'
	);

	if ($pun_config['o_subscriptions'] == '1')
		$pun_page['pd_searches'][] = '<a href="'.pun_link($pun_url['search_subscriptions']).'">'.$lang_common['Your subscriptions'].'</a>';
}

// Setup breadcrumbs
$pun_page['crumbs'] = array(
	array($pun_config['o_board_title'], pun_link($pun_url['index'])),
	$lang_common['Search']
);

($hook = get_hook('se_pre_header_load')) ? eval($hook) : null;

define('PUN_PAGE', 'search');
require PUN_ROOT.'header.php';

?>
<div id="pun-main" class="main">

	<h1><span><?php echo $lang_common['Search'] ?></span></h1>

	<div class="main-head">
		<h2><span><?php echo $lang_search['Search heading'] ?></span></h2>
	</div>
	<div class="main-content frm">
		<div class="frm-info">
			<h3><?php echo $lang_search['Predefined searches'] ?></h3>
			<p class="actions"><?php echo implode(' ', $pun_page['pd_searches']) ?></p>
			<h3><?php echo $lang_search['Using criteria'] ?></h3>
			<ul>
				<?php echo implode("\n\t\t\t\t", $pun_page['frm-info'])."\n" ?>
			</ul>
		</div>
		<form id="afocus" class="frm-form" method="get" accept-charset="utf-8" action="<?php echo pun_link($pun_url['search']) ?>">
			<div class="hidden">
				<input type="hidden" name="action" value="search" />
			</div>
<?php ($hook = get_hook('se_pre_criteria_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set<?php echo ++$pun_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_search['Search legend'] ?></strong></legend>
<?php ($hook = get_hook('se_criteria_start')) ? eval($hook) : null; ?>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_search['Keyword search'] ?></span><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $pun_page['fld_count'] ?>" name="keywords" size="40" maxlength="100" /></span><br />
						<span class="fld-help"><?php echo $lang_search['Keyword info'] ?></span>
					</label>
				</div>
<?php ($hook = get_hook('se_criteria_pre_author_field')) ? eval($hook) : null; ?>
				<div class="frm-fld text">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_search['Author search'] ?></span><br />
						<span class="fld-input"><input id="fld<?php echo $pun_page['fld_count'] ?>" type="text" name="author" size="25" maxlength="25" /></span><br />
						<span class="fld-help"><?php echo $lang_search['Author info'] ?></span>
					</label>
				</div>
<?php ($hook = get_hook('se_criteria_pre_forum_field')) ? eval($hook) : null; ?>
				<div class="frm-fld select">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_search['Forum search'] ?></span><br />
						<span class="fld-input"><select id="fld<?php echo $pun_page['fld_count'] ?>" name="forum">
<?php

if ($pun_config['o_search_all_forums'] == '1' || $pun_user['is_admmod'])
	echo "	\t\t\t\t\t".'<option value="-1">'.$lang_search['All forums'].'</option>'."\n";

// Get the list of categories and forums
$query = array(
	'SELECT'	=> 'c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.redirect_url',
	'FROM'		=> 'categories AS c',
	'JOINS'		=> array(
		array(
			'INNER JOIN'	=> 'forums AS f',
			'ON'			=> 'c.id=f.cat_id'
		),
		array(
			'LEFT JOIN'		=> 'forum_perms AS fp',
			'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].')'
		)
	),
	'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND f.redirect_url IS NULL',
	'ORDER BY'	=> 'c.disp_position, c.id, f.disp_position'
);

($hook = get_hook('se_qr_get_cats_and_forums')) ? eval($hook) : null;
$result = $pun_db->query_build($query) or error(__FILE__, __LINE__);

$cur_category = 0;
while ($cur_forum = $pun_db->fetch_assoc($result))
{
	if ($cur_forum['cid'] != $cur_category)	// A new category since last iteration?
	{
		if ($cur_category)
			echo "\t\t\t\t\t\t".'</optgroup>'."\n";

		echo "\t\t\t\t\t\t".'<optgroup label="'.pun_htmlencode($cur_forum['cat_name']).'">'."\n";
		$cur_category = $cur_forum['cid'];
	}

	echo "\t\t\t\t\t\t".'<option value="'.$cur_forum['fid'].'">'.pun_htmlencode($cur_forum['forum_name']).'</option>'."\n";
}

?>
						</optgroup>
						</select></span><br />
					</label>
				</div>
<?php ($hook = get_hook('se_criteria_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php ($hook = get_hook('se_pre_results_fieldset')) ? eval($hook) : null; ?>
			<fieldset class="frm-set set<?php echo ++$pun_page['set_count'] ?>">
				<legend class="frm-legend"><strong><?php echo $lang_search['Results legend'] ?></strong></legend>
<?php ($hook = get_hook('se_results_start')) ? eval($hook) : null; ?>
				<div class="frm-fld select">
					<label for="fld<?php echo ++$pun_page['fld_count'] ?>">
						<span class="fld-label"><?php echo $lang_search['Sort by'] ?></span><br />
						<span class="fld-input"><select id="fld<?php echo $pun_page['fld_count'] ?>" name="sort_by">
<?php if ($db_type == 'mysql' || $db_type == 'mysqli'):?>
						<option value="4"><?php echo $lang_search['Sort by relevance'] ?></option>
<?php endif; ?>						<option value="0"><?php echo $lang_search['Sort by post time'] ?></option>
						<option value="1"><?php echo $lang_search['Sort by author'] ?></option>
						<option value="2"><?php echo $lang_search['Sort by subject'] ?></option>
						<option value="3"><?php echo $lang_search['Sort by forum'] ?></option>
						</select></span><br />
					</label>
				</div>
<?php ($hook = get_hook('se_results_pre_sort_choices')) ? eval($hook) : null; ?>
				<fieldset class="frm-group">
					<legend><span><?php echo $lang_search['Sort order'] ?></span></legend>
					<div class="radbox frm-yesno"><label for="fld<?php echo ++$pun_page['fld_count'] ?>"><input type="radio" id="fld<?php echo $pun_page['fld_count'] ?>" name="sort_dir" value="ASC" /> <?php echo $lang_search['Ascending'] ?></label> <label for="fld<?php echo ++$pun_page['fld_count'] ?>"><input type="radio" id="fld<?php echo $pun_page['fld_count'] ?>" name="sort_dir" value="DESC" checked="checked" /> <?php echo $lang_search['Descending'] ?></label></div>
				</fieldset>
<?php ($hook = get_hook('se_results_pre_display_choices')) ? eval($hook) : null; ?>
				<fieldset class="frm-group">
					<legend><span><?php echo $lang_search['Display results'] ?></span></legend>
					<div class="radbox frm-yesno"><label for="fld<?php echo ++$pun_page['fld_count'] ?>"><input type="radio" id="fld<?php echo $pun_page['fld_count'] ?>" name="show_as" value="topics" checked="checked" /> <?php echo $lang_search['Show as topics'] ?></label> <label for="fld<?php echo ++$pun_page['fld_count'] ?>"><input type="radio" id="fld<?php echo $pun_page['fld_count'] ?>" name="show_as" value="posts" /> <?php echo $lang_search['Show as posts'] ?></label></div>
				</fieldset>
<?php ($hook = get_hook('se_results_end')) ? eval($hook) : null; ?>
			</fieldset>
<?php ($hook = get_hook('se_pre_buttons')) ? eval($hook) : null; ?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="search" value="<?php echo $lang_search['Submit search'] ?>" accesskey="s" title="<?php echo $lang_common['Submit title'] ?>" /></span>
			</div>
		</form>
	</div>

</div>
<?php

($hook = get_hook('se_end')) ? eval($hook) : null;

require PUN_ROOT.'footer.php';
