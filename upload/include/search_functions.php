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

// Load the search.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/search.php';

//
// Cache the results of a search and redirect the user to the results page
//
function create_search_cache($keywords, $author, $search_in = false, $forum = -1, $show_as = 'topics', $sort_by = null, $sort_dir = 'DESC')
{
	global $forum_db, $forum_user, $forum_config, $forum_url, $lang_search, $lang_common, $db_type;

	$return = ($hook = get_hook('sq_create_search_cache_start')) ? eval($hook) : null;
	if ($return != null)
		return $return;
	
	// Flood protection
	if (!$forum_user['is_guest'] && $forum_user['last_search'] != '' && (time() - $forum_user['last_search']) < $forum_user['g_search_flood'] && (time() - $forum_user['last_search']) >= 0)
		message(sprintf($lang_search['Search flood'], $forum_user['g_search_flood']));
	
	// We need to grab results, insert them into the cache and reload with a search id before showing them
	$keyword_results = $author_results = array();

	// If it's a search for keywords
	if ($keywords)
	{
		$stopwords = (array)@file(FORUM_ROOT.'lang/'.$forum_user['language'].'/stopwords.txt');
		$stopwords = array_map('trim', $stopwords);

		// Filter out non-alphabetical chars
		$noise_match = array('^', '$', '&', '(', ')', '<', '>', '`', '\'', '"', '|', ',', '@', '_', '?', '%', '~', '[', ']', '{', '}', ':', '\\', '/', '=', '#', '\'', ';', '!', '');
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
			$num_chars = forum_strlen($word);

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
					$cur_word = $forum_db->escape(str_replace('*', '%', $cur_word));

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
					$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

					$row = array();
					while ($temp = $forum_db->fetch_row($result))
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
					$forum_db->free_result($result);

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
		
		($hook = get_hook('sq_create_search_cache_end')) ? eval($hook) : null;
	}

	// If it's a search for author name (and that author name isn't Guest)
	if ($author && strtolower($author) != 'guest' && strtolower($author) != strtolower($lang_common['Guest']))
	{
		$query = array(
			'SELECT'	=> 'u.id',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'u.username '.($db_type == 'pgsql' ? 'ILIKE' : 'LIKE').' \''.$forum_db->escape(str_replace('*', '%', $author)).'\''
		);

		($hook = get_hook('se_qr_get_author')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		if ($forum_db->num_rows($result))
		{
			$user_ids = '';
			while ($row = $forum_db->fetch_row($result))
				$user_ids .= (($user_ids != '') ? ',' : '').$row[0];

			$query = array(
				'SELECT'	=> 'p.id',
				'FROM'		=> 'posts AS p',
				'WHERE'		=> 'p.poster_id IN('.$user_ids.')'
			);

			($hook = get_hook('se_qr_get_author_hits')) ? eval($hook) : null;
			$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

			$search_ids = array();
			while ($row = $forum_db->fetch_row($result))
				$author_results[] = $row[0];

			$forum_db->free_result($result);
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
		no_search_results();

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
				'ON'			=> '(fp.forum_id=t.forum_id AND fp.group_id='.$forum_user['g_id'].')'
			)
		),
		'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND p.id IN('.implode(',', $search_ids).')',
		'GROUP BY'	=> 't.id'
	);

	// Search a specific forum?
	if ($forum != -1 || ($forum == -1 && $forum_config['o_search_all_forums'] == '0' && !$forum_user['is_admmod']))
		$query['WHERE'] .= ' AND t.forum_id = '.$forum;

	// Adjust the query if show_as posts
	if ($show_as == 'posts')
	{
		$query['SELECT'] = 'p.id';
		unset($query['GROUP BY']);
	}

	($hook = get_hook('se_qr_get_hits')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$search_ids = array();
	while ($row = $forum_db->fetch_row($result))
		$search_ids[] = $row[0];

	// Prune "old" search results
	$query = array(
		'SELECT'	=> 'o.ident',
		'FROM'		=> 'online AS o'
	);

	($hook = get_hook('se_qr_get_online_idents')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	if ($forum_db->num_rows($result))
	{
		$online_idents = array();
		while ($row = $forum_db->fetch_row($result))
			$online_idents[] = '\''.$forum_db->escape($row[0]).'\'';

		$query = array(
			'DELETE'	=> 'search_cache',
			'WHERE'		=> 'ident NOT IN('.implode(',', $online_idents).')'
		);

		($hook = get_hook('se_qr_delete_old_cached_searches')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);
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
	$ident = ($forum_user['is_guest']) ? get_remote_address() : $forum_user['username'];

	$query = array(
		'INSERT'	=> 'id, ident, search_data',
		'INTO'		=> 'search_cache',
		'VALUES'	=> $search_id.', \''.$forum_db->escape($ident).'\', \''.$forum_db->escape($temp).'\''
	);

	($hook = get_hook('se_qr_cache_search')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	$forum_db->end_transaction();
	$forum_db->close();

	// Redirect the user to the cached result page
	header('Location: '.str_replace('&amp;', '&', forum_link($forum_url['search_results'], $search_id)));
	exit;
}


//
// Generate query to grab the results for a cached search
//
function generate_cached_search_query($search_id, &$show_as)
{
	global $forum_db, $db_type, $forum_user;
	
	$ident = ($forum_user['is_guest']) ? get_remote_address() : $forum_user['username'];

	$query = array(
		'SELECT'	=> 'sc.search_data',
		'FROM'		=> 'search_cache AS sc',
		'WHERE'		=> 'sc.id='.$search_id.' AND sc.ident=\''.$forum_db->escape($ident).'\''
	);

	($hook = get_hook('se_qr_get_cached_search_data')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	if ($row = $forum_db->fetch_assoc($result))
	{
		$temp = unserialize($row['search_data']);

		$search_results = $temp['search_results'];
		$sort_by = $temp['sort_by'];
		$sort_dir = $temp['sort_dir'];
		$show_as = $temp['show_as'];

		unset($temp);
	}
	else
		return false;

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
			($hook = get_hook('se_qr_cached_sort_by')) ? eval($hook) : null;
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
			'ORDER BY'	=> $sort_by_sql . ' ' . $sort_dir
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
			'ORDER BY'	=> $sort_by_sql . ' ' . $sort_dir
		);

		($hook = get_hook('se_qr_get_cached_hits_as_topics')) ? eval($hook) : null;
	}
	
	return $query;
}


//
// Generate query to grab the results for an action search (i.e. quicksearch)
//
function generate_action_search_query($action, $value, &$search_id, &$url_type, &$show_as)
{
	global $forum_db, $forum_user, $lang_common, $forum_url, $db_type;

	switch ($action)
	{
		case 'show_new':
			if ($forum_user['is_guest'])
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
						'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
					)
				),
				'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND t.last_post>'.$forum_user['last_visit'].' AND t.moved_to IS NULL',
				'ORDER BY'	=> 't.last_post DESC'
			);

			($hook = get_hook('se_qr_get_new')) ? eval($hook) : null;

			$url_type = $forum_url['search_new'];
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
						'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
					)
				),
				'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND t.last_post>'.(time() - $value).' AND t.moved_to IS NULL',
				'GROUP BY'	=> 't.id',
				'ORDER BY'	=> 't.last_post DESC'
			);

			($hook = get_hook('se_qr_get_recent')) ? eval($hook) : null;

			$url_type = $forum_url['search_24h'];
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
						'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
					)
				),
				'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND p.poster_id='.$value,
				'ORDER BY'	=> 'pposted DESC'
			);

			($hook = get_hook('se_qr_get_user_posts')) ? eval($hook) : null;

			$url_type = $forum_url['search_user_posts'];
			$search_id = $value;
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
						'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
					)
				),
				'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND p.poster_id='.$value,
				'ORDER BY'	=> 't.last_post DESC'
			);

			($hook = get_hook('se_qr_get_user_topics')) ? eval($hook) : null;

			$url_type = $forum_url['search_user_topics'];
			$search_id = $value;
			break;

		case 'show_subscriptions':
			if ($forum_user['is_guest'])
				message($lang_common['Bad request']);

			$query = array(
				'SELECT'	=> 't.id AS tid, t.poster, t.subject, t.last_post, t.last_post_id, t.last_poster, t.num_replies, t.closed, t.forum_id, f.forum_name',
				'FROM'		=> 'topics AS t',
				'JOINS'		=> array(
					array(
						'INNER JOIN'	=> 'subscriptions AS s',
						'ON'			=> '(t.id=s.topic_id AND s.user_id='.$value.')'
					),
					array(
						'INNER JOIN'	=> 'forums AS f',
						'ON'			=> 'f.id=t.forum_id'
					),
					array(
						'LEFT JOIN'		=> 'forum_perms AS fp',
						'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
					)
				),
				'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1)',
				'ORDER BY'	=> 't.last_post DESC'
			);

			($hook = get_hook('se_qr_get_subscriptions')) ? eval($hook) : null;

			$url_type = $forum_url['search_subscriptions'];
			$search_id = $value;
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
						'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
					)
				),
				'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND t.num_replies=0 AND t.moved_to IS NULL',
				'GROUP BY'	=> 't.id',
				'ORDER BY'	=> 't.last_post DESC'
			);

			($hook = get_hook('se_qr_get_unanswered')) ? eval($hook) : null;

			$url_type = $forum_url['search_unanswered'];
			break;

		default:
			// A good place for an extension to add a new search type (action must be added to $valid_actions first)
			($hook = get_hook('se_new_action')) ? eval($hook) : null;
			break;
	}
	
	return $query;
}


//
// Get search results for a specified query, returns number of results
//
function get_search_results($query, &$search_set, &$forum_page)
{
	global $forum_db, $forum_user, $lang_common;
	
	if (is_array($query))
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	else
		$result = $forum_db->query($query) or error(__FILE__, __LINE__);

	if (!$forum_user['is_guest'])
	{
		// Set the user's last_search time
		$query = array(
			'UPDATE'	=> 'users',
			'SET'		=> 'last_search='.time(),
			'WHERE'		=> 'id='.$forum_user['id'],
		);

		($hook = get_hook('se_qr_update_last_search_time')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);
	}

	// Make sure we actually have some results
	$num_hits = $forum_db->num_rows($result);
	if ($num_hits == 0)
		return 0;

	// Determine the number of pages (based on $forum_page['per_page'])
	$forum_page['num_pages'] = ceil($num_hits / $forum_page['per_page']);
	
	// Determine the topic or post offset (based on $forum_page['page'])
	$forum_page['start_from'] = $forum_page['per_page'] * ($forum_page['page'] - 1);
	$forum_page['finish_at'] = min(($forum_page['start_from'] + $forum_page['per_page']), $num_hits);

	// Fill $search_set with out search hits
	$search_set = array();
	$row_num = 0;
	while ($row = $forum_db->fetch_assoc($result))
	{
		if ($forum_page['start_from'] <= $row_num && $forum_page['finish_at'] > $row_num)
			$search_set[] = $row;
		++$row_num;
	}

	$forum_db->free_result($result);

	return $num_hits;
}


//
// Output a message if there are no results
//
function no_search_results($action = 'search')
{
	global $forum_page, $lang_search, $forum_url;
	
	$forum_page['search_again'] = '<a href="'.forum_link($forum_url['search']).'">'.$lang_search['Perform new search'].'</a>';
	
	switch ($action)
	{
		case 'show_new':
			message($lang_search['No new posts'], $forum_page['search_again']);

		case 'show_recent':
			message($lang_search['No recent posts'], $forum_page['search_again']);

		case 'show_user_posts':
			message($lang_search['No user posts'], $forum_page['search_again']);

		case 'show_user_topics':
			message($lang_search['No user topics'], $forum_page['search_again']);

		case 'show_subscriptions':
			message($lang_search['No subscriptions'], $forum_page['search_again']);

		case 'show_unanswered':
			message($lang_search['No unanswered'], $forum_page['search_again']);

		default:
			($hook = get_hook('se_no_search_results')) ? eval($hook) : null;
			message($lang_search['No hits'], $forum_page['search_again']);
	}
}


//
// Generate search breadcrumbs
function generate_search_crumbs($action = null)
{
	global $forum_page, $lang_common, $lang_search, $forum_url, $forum_user, $num_hits, $search_set, $search_id, $show_as;
	
	switch ($action)
	{
		case 'show_new':
			$forum_page['crumbs'][] = $lang_common['New posts'];
			$forum_page['main_info'] = (($forum_page['num_pages'] == 1) ? sprintf($lang_common['Page info'], $lang_search['Topics with new'], $num_hits) : '<span>'.sprintf($lang_common['Page number'], $forum_page['page'], $forum_page['num_pages']).' </span>'.sprintf($lang_common['Paged info'], $lang_search['Topics with new'], $forum_page['start_from'] + 1, $forum_page['finish_at'], $num_hits));
			$forum_page['main_foot_options']['mark_read'] = '<a class="user-option" href="'.forum_link($forum_url['mark_read'], generate_form_token('markread'.$forum_user['id'])).'">'.$lang_common['Mark all as read'].'</a>';
			break;

		case 'show_recent':
			$forum_page['crumbs'][] = $lang_common['Recent posts'];
			$forum_page['main_info'] = (($forum_page['num_pages'] == 1) ? sprintf($lang_common['Page info'], $lang_search['Topics with recent'], $num_hits) : '<span>'.sprintf($lang_common['Page number'], $forum_page['page'], $forum_page['num_pages']).' </span>'.sprintf($lang_common['Paged info'], $lang_search['Topics with recent'], $forum_page['start_from'] + 1, $forum_page['finish_at'], $num_hits));
			break;

		case 'show_unanswered':
			$forum_page['crumbs'][] = $lang_common['Unanswered topics'];
			$forum_page['main_info'] = (($forum_page['num_pages'] == 1) ? sprintf($lang_common['Page info'], $lang_common['Unanswered topics'], $num_hits) : '<span>'.sprintf($lang_common['Page number'], $forum_page['page'], $forum_page['num_pages']).' </span>'.sprintf($lang_common['Paged info'], $lang_common['Unanswered topics'], $forum_page['start_from'] + 1, $forum_page['finish_at'], $num_hits));
			break;

		case 'show_user_posts':
			$forum_page['crumbs'][] = sprintf($lang_search['Posts by'], $search_set[0]['pposter']);
			$forum_page['main_info'] = (($forum_page['num_pages'] == 1) ? sprintf($lang_common['Page info'], sprintf($lang_search['Posts by'], $search_set[0]['pposter']), $num_hits) : '<span>'.sprintf($lang_common['Page number'], $forum_page['page'], $forum_page['num_pages']).' </span>'.sprintf($lang_common['Paged info'], sprintf($lang_search['Posts by'], $search_set[0]['pposter']), $forum_page['start_from'] + 1, $forum_page['finish_at'], $num_hits));
			$forum_page['main_foot_options']['search_user_topics'] = '<a class="user-option" href="'.forum_link($forum_url['search_user_topics'], $search_id).'">'.sprintf($lang_search['Topics by'], $search_set[0]['pposter']).'</a>';
			break;

		case 'show_user_topics':
			$forum_page['crumbs'][] = sprintf($lang_search['Topics by'], $search_set[0]['poster']);
			$forum_page['main_info'] = (($forum_page['num_pages'] == 1) ? sprintf($lang_common['Page info'], sprintf($lang_search['Topics by'], $search_set[0]['poster']), $num_hits) : '<span>'.sprintf($lang_common['Page number'], $forum_page['page'], $forum_page['num_pages']).' </span>'.sprintf($lang_common['Paged info'], sprintf($lang_search['Topics by'], $search_set[0]['poster']), $forum_page['start_from'] + 1, $forum_page['finish_at'], $num_hits));
			$forum_page['main_foot_options']['search_user_posts'] = '<a class="user-option" href="'.forum_link($forum_url['search_user_posts'], $search_id).'">'.sprintf($lang_search['Posts by'], $search_set[0]['poster']).'</a>';
			break;

		case 'show_subscriptions':
			$forum_page['crumbs'][] = $lang_common['Your subscriptions'];
			$forum_page['main_info'] = (($forum_page['num_pages'] == 1) ? sprintf($lang_common['Page info'], $lang_common['Your subscriptions'], $num_hits) : '<span>'.sprintf($lang_common['Page number'], $forum_page['page'], $forum_page['num_pages']).' </span>'.sprintf($lang_common['Paged info'], $lang_common['Your subscriptions'], $forum_page['start_from'] + 1, $forum_page['finish_at'], $num_hits));
			break;

		default:
			$forum_page['crumbs'][] = $lang_search['Search results'];
			$forum_page['main_info'] = (($forum_page['num_pages'] == 1) ? sprintf($lang_common['Page info'], (($show_as=='topics') ? $lang_common['Topics'] : $lang_common['Posts']), $num_hits) : '<span>'.sprintf($lang_common['Page number'], $forum_page['page'], $forum_page['num_pages']).' </span>'.sprintf($lang_common['Paged info'], (($show_as=='topics') ? $lang_common['Topics'] : $lang_common['Posts']), $forum_page['start_from'] + 1, $forum_page['finish_at'], $num_hits));
			($hook = get_hook('se_generate_crumbs')) ? eval($hook) : null;
			break;
	}
}


//
// Checks to see if an action is valid
//
function validate_search_action($action)
{
	// A list of valid actions (extensions can add their own actions to the array)
	$valid_actions = array('search', 'show_new', 'show_recent', 'show_user_posts', 'show_user_topics', 'show_subscriptions', 'show_unanswered');
	
	($hook = get_hook('se_validate_actions')) ? eval($hook) : null;
	
	if (in_array($action, $valid_actions))
		return true;
	else
		return false;
}