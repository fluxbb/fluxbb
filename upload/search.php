<?php
/**
 * Allows users to search the forum based on various criteria.
 *
 * @copyright Copyright (C) 2008 FluxBB.org, based on code copyright (C) 2002-2008 PunBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package FluxBB
 */


if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', './');
require FORUM_ROOT.'include/common.php';

($hook = get_hook('se_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

// Load the search.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/search.php';

// Load the necessary search functions
require FORUM_ROOT.'include/search_functions.php';


if ($forum_user['g_read_board'] == '0')
	message($lang_common['No view']);
else if ($forum_user['g_search'] == '0')
	message($lang_search['No search permission']);


// If a search_id was supplied
if (isset($_GET['search_id']))
{
	$search_id = intval($_GET['search_id']);
	if ($search_id < 1)
		message($lang_common['Bad request']);

	// Generate the query to grab the cached results
	$query = generate_cached_search_query($search_id, $show_as);

	$url_type = $forum_url['search_results'];
}
// We aren't just grabbing a cached search
else if (isset($_GET['action']))
{
	$action = (isset($_GET['action'])) ? $_GET['action'] : null;

	// Validate action
	if (!validate_search_action($action))
		message($lang_common['Bad request']);

	// If it's a regular search (keywords and/or author)
	if ($action == 'search')
	{
		$keywords = (isset($_GET['keywords'])) ? utf8_strtolower(forum_trim($_GET['keywords'])) : null;
		$author = (isset($_GET['author'])) ? utf8_strtolower(forum_trim($_GET['author'])) : null;
		$sort_dir = (isset($_GET['sort_dir'])) ? (($_GET['sort_dir'] == 'DESC') ? 'DESC' : 'ASC') : 'DESC';
		$show_as = (isset($_GET['show_as'])) ? $_GET['show_as'] : 'posts';
		$sort_by = (isset($_GET['sort_by'])) ? intval($_GET['sort_by']) : null;
		$search_in = (!isset($_GET['search_in']) || $_GET['search_in'] == 'all') ? 0 : (($_GET['search_in'] == 'message') ? 1 : -1);
		$forum = (isset($_GET['forum']) && is_array($_GET['forum'])) ? array_map('intval', $_GET['forum']) : array(-1);

		if (preg_match('#^[\*%]+$#', $keywords))
			$keywords = '';

		if (preg_match('#^[\*%]+$#', $author))
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
		if ($action == 'show_user_posts' || $action == 'show_user_topics' || $action == 'show_subscriptions')
		{
			$value = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
			if ($value < 2)
				message($lang_common['Bad request']);
		}
		else if ($action == 'show_recent')
			$value = (isset($_GET['value'])) ? intval($_GET['value']) : 86400;
		else if ($action == 'show_new')
			$value = (isset($_GET['forum'])) ? intval($_GET['forum']) : -1;

		($hook = get_hook('se_additional_quicksearch_variables')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		$search_id = '';
		$show_as = 'topics';

		// Generate the query for the search
		$query = generate_action_search_query($action, $value, $search_id, $url_type, $show_as);
	}
}

($hook = get_hook('se_pre_search_query')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

// We have the query to get the results, lets get them!
if (isset($query))
{
	// No results?
	if (!$query)
		no_search_results();

	// Work out the settings for pagination
	$forum_page['per_page'] = ($show_as == 'posts') ? $forum_user['disp_posts'] : $forum_user['disp_topics'];

	// We now have a query that will give us our results in $query, lets get the data!
	$num_hits = get_search_results($query, $search_set);

	($hook = get_hook('se_post_results_fetched')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	// No search results?
	if ($num_hits == 0)
		no_search_results($action);

	//
	// Output the search results
	//

	// Setup breadcrumbs and results header and footer
	$forum_page['main_foot_options']['new_search'] = '<a class="user-option" href="'.forum_link($forum_url['search']).'">'.$lang_search['Perform new search'].'</a>';
	$forum_page['crumbs'][] = array($forum_config['o_board_title'], forum_link($forum_url['index']));
	$action = (isset($action)) ? $action : null;
	generate_search_crumbs($action);

	// Generate paging links
	$forum_page['page_post']['paging'] = '<p class="paging"><span class="pages">'.$lang_common['Pages'].'</span> '.paginate($forum_page['num_pages'], $forum_page['page'], $url_type, $lang_common['Paging separator'], $search_id).'</p>';

	if (isset($forum_page['posting_info']))
		$forum_page['page_post']['posting'] = $forum_page['posting_info'];

	// Get topic/forum tracking data
	if (!$forum_user['is_guest'])
		$tracked_topics = get_tracked_topics();

	// Navigation links for header and page numbering for title/meta description
	if ($forum_page['page'] < $forum_page['num_pages'])
	{
		$forum_page['nav']['last'] = '<link rel="last" href="'.forum_sublink($url_type, $forum_url['page'], $forum_page['num_pages'], $search_id).'" title="'.$lang_common['Page'].' '.$forum_page['num_pages'].'" />';
		$forum_page['nav']['next'] = '<link rel="next" href="'.forum_sublink($url_type, $forum_url['page'], ($forum_page['page'] + 1), $search_id).'" title="'.$lang_common['Page'].' '.($forum_page['page'] + 1).'" />';
	}
	if ($forum_page['page'] > 1)
	{
		$forum_page['nav']['prev'] = '<link rel="prev" href="'.forum_sublink($url_type, $forum_url['page'], ($forum_page['page'] - 1), $search_id).'" title="'.$lang_common['Page'].' '.($forum_page['page'] - 1).'" />';
		$forum_page['nav']['first'] = '<link rel="first" href="'.forum_link($url_type, $search_id).'" title="'.$lang_common['Page'].' 1" />';
	}

	($hook = get_hook('se_results_pre_header_load')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	define('FORUM_PAGE', $show_as == 'topics' ? 'searchtopics' : 'searchposts');
	define('FORUM_PAGE_TYPE', $show_as == 'topics' ? 'topic' : 'forum' );
	require FORUM_ROOT.'header.php';

	// START SUBST - <!-- forum_main -->
	ob_start();

	($hook = get_hook('se_results_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	if ($show_as == 'topics')
	{
		// Load the forum.php language file
		require FORUM_ROOT.'lang/'.$forum_user['language'].'/forum.php';

		$forum_page['item_header'] = array();
		$forum_page['item_header']['subject']['title'] = '<strong class="subject-title">'.$lang_forum['Topics'].'</strong>';
		$forum_page['item_header']['info']['replies'] = '<strong class="info-replies">'.$lang_forum['replies'].'</strong>';
		$forum_page['item_header']['info']['lastpost'] = '<strong class="info-lastpost">'.$lang_forum['last post'].'</strong>';

?>
	<div class="main-pagehead">
		<h2 class="hn"><span><?php echo $forum_page['results_info'] ?></span></h2>
	</div>
	<div class="main-subhead">
		<p class="item-summary forum-noview"><span><?php printf($lang_forum['Search subtitle'], implode(' ', $forum_page['item_header']['subject']), implode(', ', $forum_page['item_header']['info'])) ?></span></p>
	</div>
	<div class="main-content main-forum forum-noview">
<?php

	}
	else
	{
		// Load the topic.php language file
		require FORUM_ROOT.'lang/'.$forum_user['language'].'/topic.php';

?>
	<div class="main-pagehead">
		<h2 class="hn"><span><?php echo $forum_page['results_info'] ?></span></h2>
	</div>
	<div class="main-content main-topic">
<?php

	}

	$forum_page['item_count'] = 0;

	if ($show_as == 'posts')
	{
		if (!defined('FORUM_PARSER_LOADED'))
			require FORUM_ROOT.'include/parser.php';
	}

	// Finally, lets loop through the results and output them
	for ($i = 0; $i < count($search_set); ++$i)
	{
		($hook = get_hook('se_results_loop_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		++$forum_page['item_count'];

		if ($forum_config['o_censoring'] == '1')
			$search_set[$i]['subject'] = censor_words($search_set[$i]['subject']);

		if ($show_as == 'posts')
		{

			// Generate the post heading
			$forum_page['item_ident'] = array(
				'num'	=> '<strong>'.forum_number_format($forum_page['start_from'] + $forum_page['item_count']).'</strong>',
				'user'	=> '<cite>'.($search_set[$i]['pid'] == $search_set[$i]['first_post_id'] ? sprintf($lang_topic['Topic by'], forum_htmlencode($search_set[$i]['pposter'])) : sprintf($lang_topic['Reply by'], forum_htmlencode($search_set[$i]['pposter']))).'</cite>',
				'date'	=> '<span>'.format_time($search_set[$i]['pposted']).'</span>'
			);

			$forum_page['item_head'] = '<a class="permalink" rel="bookmark" title="'.$lang_topic['Permalink post'].'" href="'.forum_link($forum_url['post'], $search_set[$i]['pid']).'">'.implode(' ', $forum_page['item_ident']).'</a>';

			// Generate the post title
			$forum_page['item_subject'] = '<a class="permalink" rel="bookmark" title="'.$lang_topic['Permalink topic'].'" href="'.forum_link($forum_url['topic'], array($search_set[$i]['tid'], sef_friendly($search_set[$i]['subject']))).'">'.forum_htmlencode($search_set[$i]['subject']).'</a> <span>'.sprintf($lang_topic['Search replies'], forum_number_format($search_set[$i]['num_replies'])).'</span>';

			($hook = get_hook('se_results_posts_row_pre_item_head')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

			// Generate author identification
			$forum_page['user_ident'] = ($search_set[$i]['poster_id'] > 1 && $forum_user['g_view_users'] == '1') ? '<strong class="username"><a title="'.sprintf($lang_search['Go to profile'], forum_htmlencode($search_set[$i]['pposter'])).'" href="'.forum_link($forum_url['user'], $search_set[$i]['poster_id']).'">'.forum_htmlencode($search_set[$i]['pposter']).'</a></strong>' : '<strong class="username">'.forum_htmlencode($search_set[$i]['pposter']).'</strong>';

			// Generate the post actions links
			$forum_page['post_actions'] = array();
			$forum_page['post_actions']['forum'] = '<span><a href="'.forum_link($forum_url['forum'], array($search_set[$i]['forum_id'], sef_friendly($search_set[$i]['forum_name']))).'">'.$lang_search['Go to forum'].'<span>: '.forum_htmlencode($search_set[$i]['forum_name']).'</span></a></span>';

			if ($search_set[$i]['pid'] != $search_set[$i]['first_post_id'])
				$forum_page['post_actions']['topic'] = '<span><a class="permalink" rel="bookmark" title="'.$lang_topic['Permalink topic'].'" href="'.forum_link($forum_url['topic'], array($search_set[$i]['tid'], sef_friendly($search_set[$i]['subject']))).'">'.$lang_search['Go to topic'].'<span>: '.forum_htmlencode($search_set[$i]['subject']).'</span></a></span>';

			$forum_page['post_actions']['post'] = '<span><a class="permalink" rel="bookmark" title="'.$lang_topic['Permalink post'].'" href="'.forum_link($forum_url['post'], $search_set[$i]['pid']).'">'.$lang_search['Go to post'].' <span>'.forum_number_format($forum_page['start_from'] + $forum_page['item_count']).'</span></a></span>';

			$forum_page['message'] = parse_message($search_set[$i]['message'], $search_set[$i]['hide_smilies']);

			// Give the post some class
			$forum_page['item_status'] = array(
				'post',
				(($forum_page['item_count'] % 2 != 0) ? 'odd' : 'even' )
			);

			if ($forum_page['item_count'] == 1)
				$forum_page['item_status']['firstpost'] = 'firstpost';

			if (($forum_page['start_from'] + $forum_page['item_count']) == $forum_page['finish_at'])
				$forum_page['item_status']['lastpost'] = 'lastpost';

			if ($search_set[$i]['pid'] == $search_set[$i]['first_post_id'])
				$forum_page['item_status']['topicpost'] = 'topicpost';


			($hook = get_hook('se_results_posts_row_pre_display')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
	<div class="<?php echo implode(' ', $forum_page['item_status']) ?>">
		<div class="posthead">
			<h4 class="hn"><?php echo $forum_page['item_head'] ?></h4>
		</div>
		<div class="postbody">
			<div class="user">
				<h5 class="user-ident"><?php echo $forum_page['user_ident'] ?></h5>
			</div>
			<h5 class="result-title"><?php echo $forum_page['item_subject'] ?></h5>
			<div class="post-entry">
				<div class="entry-content">
					<?php echo $forum_page['message'] ?>
				</div>
			</div>
		</div>
		<div class="postfoot">
			<div class="post-options">
				<p class="post-actions"><?php echo implode(' ', $forum_page['post_actions']) ?></p>
			</div>
		</div>
	</div>
<?php

		}
		else
		{
			// Start from scratch
			$forum_page['item_subject'] = $forum_page['item_body'] = $forum_page['item_status'] = $forum_page['item_nav'] = $forum_page['item_title'] = $forum_page['item_title_status'] = array();
			$forum_page['item_indicator'] = '';

			// Assemble the Topic heading

			// Should we display the dot or not? :)
			if (!$forum_user['is_guest'] && $forum_config['o_show_dot'] == '1' && $search_set[$i]['has_posted'] > 0)
			{
				$forum_page['item_title']['posted'] = '<span class="posted-mark">'.$lang_forum['You posted indicator'].'</span>';
				$forum_page['item_status']['posted'] = 'posted';
			}

			$forum_page['item_title']['link'] = '<strong><a href="'.forum_link($forum_url['topic'], array($search_set[$i]['tid'], sef_friendly($search_set[$i]['subject']))).'">'.forum_htmlencode($search_set[$i]['subject']).'</a></strong>';

			$forum_page['item_pages'] = ceil(($search_set[$i]['num_replies'] + 1) / $forum_user['disp_posts']);

			if ($forum_page['item_pages'] > 1)
				$forum_page['item_nav']['pages'] = '<span>'.$lang_forum['Pages'].'&#160;</span>'.paginate($forum_page['item_pages'], -1, $forum_url['topic'], $lang_common['Page separator'], array($search_set[$i]['tid'], sef_friendly($search_set[$i]['subject'])));

			// Does this topic contain posts we haven't read? If so, tag it accordingly.
			if (!$forum_user['is_guest'] && $search_set[$i]['last_post'] > $forum_user['last_visit'] && (!isset($tracked_topics['topics'][$search_set[$i]['tid']]) || $tracked_topics['topics'][$search_set[$i]['tid']] < $search_set[$i]['last_post']) && (!isset($tracked_topics['forums'][$search_set[$i]['forum_id']]) || $tracked_topics['forums'][$search_set[$i]['forum_id']] < $search_set[$i]['last_post']))
			{
				$forum_page['item_nav']['new'] = '<em class="item-newposts"><a href="'.forum_link($forum_url['topic_new_posts'], array($search_set[$i]['tid'], sef_friendly($search_set[$i]['subject']))).'" title="'.$lang_forum['New posts info'].'">'.$lang_forum['New posts'].'</a></em>';
				$forum_page['item_status']['new'] = 'new';
			}

			if (!empty($forum_page['item_nav']))
				$forum_page['item_title']['nav'] = '<span class="item-nav">'.sprintf($lang_forum['Topic navigation'], implode('&#160;&#160;', $forum_page['item_nav'])).'</span>';

			$forum_page['item_body']['subject']['title'] = '<h3 class="hn"><span class="item-num">'.forum_number_format($forum_page['start_from'] + $forum_page['item_count']).'</span> '.implode(' ', $forum_page['item_title']).'</h3>';


			if ($search_set[$i]['sticky'] == '1')
			{
				$forum_page['item_subject_status']['sticky'] = $lang_forum['Sticky'];
				$forum_page['item_status']['sticky'] = 'sticky';
			}

			if ($search_set[$i]['closed'] != '0')
			{
				$forum_page['item_subject_status']['closed'] = $lang_forum['Closed'];
				$forum_page['item_status']['closed'] = 'closed';
			}

			if (!empty($forum_page['item_subject_status']))
				$forum_page['item_subject']['status'] = '<span class="item-status">'.sprintf($lang_forum['Item status'], implode(' ', $forum_page['item_subject_status'])).'</span>';

			$forum_page['item_subject']['starter'] = '<span class="item-starter">'.sprintf($lang_forum['Topic starter'], format_time($search_set[$i]['posted'], 1), '<cite>'.sprintf($lang_forum['by poster'], forum_htmlencode($search_set[$i]['poster'])).'</cite>').'</span>';
			$forum_page['item_subject']['location'] = '<span>'.sprintf($lang_search['Location'], '<a href="'.forum_link($forum_url['forum'], array($search_set[$i]['forum_id'], sef_friendly($search_set[$i]['forum_name']))).'">'.forum_htmlencode($search_set[$i]['forum_name']).'</a>').'</span>';
			$forum_page['item_body']['subject']['desc'] = '<p>'.implode(' ', $forum_page['item_subject']).'</p>';

			if (empty($forum_page['item_status']))
				$forum_page['item_status']['normal'] = 'normal';

			($hook = get_hook('se_results_topics_pre_item_merge')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

			$forum_page['item_style'] = (($forum_page['item_count'] % 2 != 0) ? ' odd' : ' even').(($forum_page['item_count'] == 1) ? ' main-item1' : '').((!empty($forum_page['item_status'])) ? ' '.implode(' ', $forum_page['item_status']) : '');

			$forum_page['item_body']['info']['replies'] = '<li class="info-replies"><strong>'.forum_number_format($search_set[$i]['num_replies']).'</strong> <span class="label">'.(($search_set[$i]['num_replies'] == 1) ? $lang_forum['Reply'] : $lang_forum['Replies']).'</span></li>';
			$forum_page['item_body']['info']['lastpost'] = '<li class="info-lastpost"><span class="label">'.$lang_forum['Last post'].'</span> <strong><a href="'.forum_link($forum_url['post'], $search_set[$i]['last_post_id']).'">'.format_time($search_set[$i]['last_post']).'</a></strong> <cite>'.sprintf($lang_forum['by poster'], forum_htmlencode($search_set[$i]['last_poster'])).'</cite></li>';

			($hook = get_hook('se_results_topics_row_pre_display')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
		<div class="main-item<?php echo $forum_page['item_style'] ?>">
			<span class="icon <?php echo implode(' ', $forum_page['item_status']) ?>"><!-- --></span>
			<div class="item-subject">
				<?php echo implode("\n\t\t\t\t", $forum_page['item_body']['subject'])."\n" ?>
			</div>
			<ul class="item-info">
				<?php echo implode("\n\t\t\t\t", $forum_page['item_body']['info'])."\n" ?>
			</ul>
		</div>
<?php

		}
	}

echo "\t".'</div>'."\n";

	$tpl_temp = forum_trim(ob_get_contents());
	$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
	ob_end_clean();
	// END SUBST - <!-- forum_main -->

	require FORUM_ROOT.'footer.php';
}

//
// Display the search form
//

// Setup form information
$forum_page['frm-info'] = array('search' => '<li><span>'.$lang_search['Search info'].'</span></li>');
$forum_page['frm-info']['keywords'] = '<li><span>'.$lang_search['Keywords info'].'</span></li>';
$forum_page['frm-info']['refine'] = '<li><span>'.$lang_search['Refine info'].'</span></li>';
$forum_page['frm-info']['wildcard'] = '<li><span>'.$lang_search['Wildcard info'].'</span></li>';

if ($forum_config['o_search_all_forums'] == '1' || $forum_user['is_admmod'])
	$forum_page['frm-info']['forums'] = '<li><span>'.$lang_search['Forum default info'].'</span></li>';
else
	$forum_page['frm-info']['forums'] = '<li><span>'.$lang_search['Forum require info'].'</span></li>';

// Setup sort by options
$forum_page['frm-sort'] = array();
$forum_page['frm-sort'][0] = '<option value="0">'.$lang_search['Sort by post time'].'</option>';
$forum_page['frm-sort'][1] = '<option value="1">'.$lang_search['Sort by author'].'</option>';
$forum_page['frm-sort'][2] = '<option value="2">'.$lang_search['Sort by subject'].'</option>';
$forum_page['frm-sort'][3] = '<option value="3">'.$lang_search['Sort by forum'].'</option>';

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	$lang_common['Search']
);

// Setup form
$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;

($hook = get_hook('se_pre_header_load')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

define('FORUM_PAGE', 'search');
define('FORUM_PAGE_TYPE', 'basic');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('se_main_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
	<div class="main-content main-frm">
		<div class="ct-box">
			<ul>
				<?php echo implode("\n\t\t\t\t", $forum_page['frm-info'])."\n" ?>
			</ul>
		</div>
		<form id="afocus" class="frm-form" method="get" accept-charset="utf-8" action="<?php echo forum_link($forum_url['search']) ?>">
			<div class="hidden">
				<input type="hidden" name="action" value="search" />
			</div>
<?php ($hook = get_hook('se_pre_criteria_fieldset')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			<fieldset class="frm-group group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_search['Search legend'] ?></strong></legend>
<?php ($hook = get_hook('se_criteria_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_search['Keyword search'] ?></span></label><br />
						<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="keywords" size="40" maxlength="100" /></span>
					</div>
				</div>
<?php ($hook = get_hook('se_criteria_pre_author_field')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box text">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_search['Author search'] ?></span></label><br />
						<span class="fld-input"><input id="fld<?php echo $forum_page['fld_count'] ?>" type="text" name="author" size="25" maxlength="25" /></span>
					</div>
				</div>
<?php ($hook = get_hook('se_criteria_pre_forum_field')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				<fieldset class="mf-set set<?php echo ++$forum_page['item_count'] ?>">
					<legend><span><?php echo $lang_search['Forum search'] ?> <em><?php echo ($forum_config['o_search_all_forums'] == '1' || $forum_user['is_admmod']) ? $lang_search['Forum search default'] : $lang_search['Forum search require'] ?></em></span></legend>
					<div class="mf-box">
						<div class="checklist">
<?php

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
			'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
		)
	),
	'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND f.redirect_url IS NULL',
	'ORDER BY'	=> 'c.disp_position, c.id, f.disp_position'
);

($hook = get_hook('se_qr_get_cats_and_forums')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

$cur_category = 0;
while ($cur_forum = $forum_db->fetch_assoc($result))
{
	($hook = get_hook('se_forum_loop_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	if ($cur_forum['cid'] != $cur_category)	// A new category since last iteration?
	{
		if ($cur_category)
			echo "\t\t\t\t\t\t\t".'</fieldset>'."\n";

		echo "\t\t\t\t\t\t\t".'<fieldset>'."\n\t\t\t\t\t\t\t\t".'<legend><span>'.forum_htmlencode($cur_forum['cat_name']).':</span></legend>'."\n";
		$cur_category = $cur_forum['cid'];
	}

	echo "\t\t\t\t\t\t\t\t".'<div class="checklist-item"><span class="fld-input"><input type="checkbox" id="fld'.(++$forum_page['fld_count']).'"  name="forum[]" value="'.$cur_forum['fid'].'" /></span> <label for="fld'.$forum_page['fld_count'].'">'.forum_htmlencode($cur_forum['forum_name']).'</label></div>'."\n";
}

?>
							</fieldset>
						</div>
					</div>
				</fieldset>
<?php ($hook = get_hook('se_criteria_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			</fieldset>
<?php $forum_page['item_count'] = 0; ?>
<?php ($hook = get_hook('se_pre_results_fieldset')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			<fieldset class="frm-group group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><strong><?php echo $lang_search['Results legend'] ?></strong></legend>
<?php ($hook = get_hook('se_results_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
					<div class="sf-box select">
						<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_search['Sort by'] ?></span></label><br />
						<span class="fld-input"><select id="fld<?php echo $forum_page['fld_count'] ?>" name="sort_by">
						<?php echo implode("\n\t\t\t\t\t\t", $forum_page['frm-sort'])."\n" ?>
						</select></span>
					</div>
				</div>
<?php ($hook = get_hook('se_results_pre_sort_choices')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				<fieldset class="mf-set set<?php echo ++$forum_page['item_count'] ?>">
					<legend><span><?php echo $lang_search['Sort order'] ?></span></legend>
					<div class="mf-box mf-yesno">
						<div class="mf-item">
							<span class="fld-input"><input type="radio" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="sort_dir" value="ASC" /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_search['Ascending'] ?></label>
						</div>
						<div class="mf-item">
							<span class="fld-input"><input type="radio" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="sort_dir" value="DESC" checked="checked" /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_search['Descending'] ?></label>
						</div>
					</div>
				</fieldset>
<?php ($hook = get_hook('se_results_pre_display_choices')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
				<fieldset class="mf-set set<?php echo ++$forum_page['item_count'] ?>">
					<legend><span><?php echo $lang_search['Display results'] ?></span></legend>
					<div class="mf-box mf-yesno">
						<div class="mf-item">
							<span class="fld-input"><input type="radio" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="show_as" value="topics" checked="checked" /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_search['Show as topics'] ?></label>
						</div>
						<div class="mf-item">
							<span class="fld-input"><input type="radio" id="fld<?php echo ++$forum_page['fld_count'] ?>" name="show_as" value="posts" /></span>
							<label for="fld<?php echo $forum_page['fld_count'] ?>"><?php echo $lang_search['Show as posts'] ?></label>
						</div>
					</div>
				</fieldset>
<?php ($hook = get_hook('se_results_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			</fieldset>
<?php ($hook = get_hook('se_pre_buttons')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
			<div class="frm-buttons">
				<span class="submit"><input type="submit" name="search" value="<?php echo $lang_search['Submit search'] ?>" /></span>
			</div>
		</form>
	</div>
<?php

($hook = get_hook('se_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->


require FORUM_ROOT.'footer.php';
