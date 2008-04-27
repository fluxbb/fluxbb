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

require PUN_ROOT.'include/search_functions.php';

($hook = get_hook('se_start')) ? eval($hook) : null;

if ($pun_user['g_read_board'] == '0')
	message($lang_common['No view']);
else if ($pun_user['g_search'] == '0')
	message($lang_search['No search permission']);


// If a search_id was supplied
if (isset($_GET['search_id']))
{
	$search_id = intval($_GET['search_id']);
	if ($search_id < 1)
		message($lang_common['Bad request']);

	// Generate the query to grab the cached results
	$query = generate_cached_search_query($search_id, $show_as);

	$url_type = $pun_url['search_results'];
}
// We aren't just grabbing a cached search
elseif (isset($_GET['action']))
{
	$action = (isset($_GET['action'])) ? $_GET['action'] : null;

	// Validate action
	if (!validate_search_action($action))
		message($lang_common['Bad request']);

	// If it's a regular search (keywords and/or author)
	if ($action == 'search')
	{
		$keywords = (isset($_GET['keywords'])) ? strtolower(trim($_GET['keywords'])) : null;
		$author = (isset($_GET['author'])) ? strtolower(trim($_GET['author'])) : null;
		$sort_dir = (isset($_GET['sort_dir'])) ? (($_GET['sort_dir'] == 'DESC') ? 'DESC' : 'ASC') : 'DESC';
		$show_as = (isset($_GET['show_as'])) ? $_GET['show_as'] : 'posts';
		$sort_by = (isset($_GET['sort_by'])) ? intval($_GET['sort_by']) : null;
		$search_in = (!isset($_GET['search_in']) || $_GET['search_in'] == 'all') ? 0 : (($_GET['search_in'] == 'message') ? 1 : -1);
		$forum = (isset($_GET['forum'])) ? intval($_GET['forum']) : -1;
		
		if (preg_match('#^[\*%]+$#', $keywords) || (pun_strlen(str_replace(array('*', '%'), '', $keywords)) < 3))
			$keywords = '';

		if (preg_match('#^[\*%]+$#', $author) || pun_strlen(str_replace(array('*', '%'), '', $author)) < 2)
			$author = '';

		if (!$keywords && !$author)
			message($lang_search['No terms']);

		// Create a cache of the results and redirect the user to the results
		create_search_cache($keywords, $author, $search_in, $forum, $show_as, $sort_by, $sort_dir);
	}
	// Its not a regular search, so its a quicksearch
	else
	{
		$value = null;
		// Get any additional variables for quicksearches
		if ($action == 'show_user_posts' || $action == 'show_user_topics')
		{
			$value = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
			if ($value < 2)
				message($lang_common['Bad request']);
		}
		else if ($action == 'show_recent')
			$value = (isset($_GET['value'])) ? intval($_GET['value']) : 86400;

		$search_id = '';
		$show_as = 'topics';

		// Generate the query for the search
		$query = generate_action_search_query($action, $value, $search_id, $url_type, $show_as);
	}
}

($hook = get_hook('se_pre_search_query')) ? eval($hook) : null;

// We have the query to get the results, lets get them!
if (isset($query))
{
	// No results?
	if (!$query)
		no_search_results();	
	
	// Work out the settings for pagination
	$pun_page['per_page'] = ($show_as == 'posts') ? $pun_user['disp_posts'] : $pun_user['disp_topics'];
	$pun_page['page'] = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $pun_page['num_pages']) ? 1 : $_GET['p'];
	
	// We now have a query that will give us our results in $query, lets get the data!
	$num_hits = get_search_results($query, $search_set, $pun_page);
	
	($hook = get_hook('se_post_results_fetched')) ? eval($hook) : null;

	// No search results?
	if ($num_hits == 0)
		no_search_results($action);
	
	//
	// Output the search results
	//
	
	// Generate paging links
	$pun_page['page_post'] = '<p class="paging"><span class="pages">'.$lang_common['Pages'].'</span> '.paginate($pun_page['num_pages'], $pun_page['page'], $url_type, $lang_common['Paging separator'], $search_id).'</p>';
	
	// Get topic/forum tracking data
	if (!$pun_user['is_guest'])
		$tracked_topics = get_tracked_topics();

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

	$action = (isset($action)) ? $action : null;
	generate_search_crumbs($action);
	
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

//
// Display the search forum
//

// Setup form
$pun_page['set_count'] = $pun_page['fld_count'] = 0;

// Setup form information
$pun_page['frm-info'] = array('<li><span>'.$lang_search['Search info'].'</span></li>');
$pun_page['frm-info'][] = '<li><span>'.$lang_search['Refine info'].'</span></li>';
$pun_page['frm-info'][] = '<li><span>'.$lang_search['Wildcard info'].'<span></li>';

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
						<option value="0"><?php echo $lang_search['Sort by post time'] ?></option>
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
