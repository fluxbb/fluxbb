<?php
/**
 * Displays a list of the categories/forums that the current user can see, along
 * with some statistics.
 *
 * @copyright Copyright (C) 2008 FluxBB.org, based on code copyright (C) 2002-2008 PunBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package FluxBB
 */


if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', './');
require FORUM_ROOT.'include/common.php';

($hook = get_hook('in_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

if ($forum_user['g_read_board'] == '0')
	message($lang_common['No view']);

// Load the index.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/index.php';


// Get list of forums and topics with new posts since last visit
if (!$forum_user['is_guest'])
{
	$query = array(
		'SELECT'	=> 't.forum_id, t.id, t.last_post',
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
		'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND t.last_post>'.$forum_user['last_visit'].' AND t.moved_to IS NULL'
	);

	($hook = get_hook('in_qr_get_new_topics')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$new_topics = array();
	while ($cur_topic = $forum_db->fetch_assoc($result))
		$new_topics[$cur_topic['forum_id']][$cur_topic['id']] = $cur_topic['last_post'];

	$tracked_topics = get_tracked_topics();
}

// Setup main heading
$forum_page['main_head'] = forum_htmlencode($forum_config['o_board_title']);

// Setup main options
$forum_page['main_options_head'] = $lang_index['Board options'];
$forum_page['main_options'] = array();
$forum_page['main_options']['feed'] = '<span class="feed'.(empty($forum_page['main_options']) ? ' item1' : '').'"><a class="feed" href="'.forum_link($forum_url['index_rss']).'">'.$lang_index['RSS active feed'].'</a></span>';
if (!$forum_user['is_guest'])
	$forum_page['main_options']['markread'] = '<span'.(empty($forum_page['main_options']) ? ' class="item1"' : '').'><a href="'.forum_link($forum_url['mark_read'], generate_form_token('markread'.$forum_user['id'])).'">'.$lang_index['Mark all as read'].'</a></span>';

($hook = get_hook('in_pre_header_load')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

define('FORUM_ALLOW_INDEX', 1);
define('FORUM_PAGE', 'index');
define('FORUM_PAGE_TYPE', 'index');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('in_main_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

// Print the categories and forums
$query = array(
	'SELECT'	=> 'c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.forum_desc, f.redirect_url, f.moderators, f.num_topics, f.num_posts, f.last_post, f.last_post_id, f.last_poster',
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
	'WHERE'		=> 'fp.read_forum IS NULL OR fp.read_forum=1',
	'ORDER BY'	=> 'c.disp_position, c.id, f.disp_position'
);

($hook = get_hook('in_qr_get_cats_and_forums')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

$forum_page['cur_category'] = $forum_page['cat_count'] = $forum_page['item_count'] = 0;

while ($cur_forum = $forum_db->fetch_assoc($result))
{
	($hook = get_hook('in_forum_loop_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

	$forum_page['item_mods'] = '';
	++$forum_page['item_count'];

	if ($cur_forum['cid'] != $forum_page['cur_category'])	// A new category since last iteration?
	{
		if ($forum_page['cur_category'] != 0)
			echo "\t".'</div>'."\n";

		++$forum_page['cat_count'];
		$forum_page['item_count'] = 1;

		$forum_page['item_header'] = array();
		$forum_page['item_header']['subject']['title'] = '<strong class="subject-title">'.$lang_index['Forums'].'</strong>';
		$forum_page['item_header']['info']['topics'] = '<strong class="info-topics">'.$lang_index['topics'].'</strong>';
		$forum_page['item_header']['info']['post'] = '<strong class="info-posts">'.$lang_index['posts'].'</strong>';
		$forum_page['item_header']['info']['lastpost'] = '<strong class="info-lastpost">'.$lang_index['last post'].'</strong>';

		($hook = get_hook('in_forum_pre_cat_head')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo forum_htmlencode($cur_forum['cat_name']) ?></span></h2>
		<p class="item-summary"><span><?php printf($lang_index['Category subtitle'], implode(' ', $forum_page['item_header']['subject']), implode(', ', $forum_page['item_header']['info'])) ?></span></p>
	</div>
	<div id="category<?php echo $forum_page['cat_count'] ?>" class="main-content main-category">
<?php

		$forum_page['cur_category'] = $cur_forum['cid'];
	}

	// Reset arrays and globals for each forum
	$forum_page['item_status'] = $forum_page['item_subject'] = $forum_page['item_body'] = $forum_page['item_title'] = array();
	$forum_page['item_indicator'] = '';

	// Is this a redirect forum?
	if ($cur_forum['redirect_url'] != '')
	{
		$forum_page['item_body']['subject']['title'] = '<h3 class="hn"><a class="external" href="'.forum_htmlencode($cur_forum['redirect_url']).'" title="'.sprintf($lang_index['Link to'], forum_htmlencode($cur_forum['redirect_url'])).'"><span>'.forum_htmlencode($cur_forum['forum_name']).'</span></a></h3>';
		$forum_page['item_status']['redirect'] = 'redirect';

		if ($cur_forum['forum_desc'] != '')
			$forum_page['item_subject']['desc'] = $cur_forum['forum_desc'];

		$forum_page['item_subject']['redirect'] = '<span>'.$lang_index['External forum'].'</span>';

		($hook = get_hook('in_redirect_row_pre_item_subject_merge')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		if (!empty($forum_page['item_subject']))
			$forum_page['item_body']['subject']['desc'] = '<p>'.implode(' ', $forum_page['item_subject']).'</p>';

		// Forum topic and post count
		$forum_page['item_body']['info']['topics'] = '<li class="info-topics"><span class="label">'.$lang_index['No topic info'].'</span></li>';
		$forum_page['item_body']['info']['posts'] = '<li class="info-posts"><span class="label">'.$lang_index['No post info'].'</span></li>';
		$forum_page['item_body']['info']['lastpost'] = '<li class="info-lastpost"><span class="label">'.$lang_index['No lastpost info'].'</span></li>';

		($hook = get_hook('in_redirect_row_pre_display')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	}
	else
	{
		// Setup the title and link to the forum
		$forum_page['item_title']['title'] = '<a href="'.forum_link($forum_url['forum'], array($cur_forum['fid'], sef_friendly($cur_forum['forum_name']))).'"><span>'.forum_htmlencode($cur_forum['forum_name']).'</span></a>';

		// Are there new posts since our last visit?
		if (!$forum_user['is_guest'] && $cur_forum['last_post'] > $forum_user['last_visit'] && (empty($tracked_topics['forums'][$cur_forum['fid']]) || $cur_forum['last_post'] > $tracked_topics['forums'][$cur_forum['fid']]))
		{
			// There are new posts in this forum, but have we read all of them already?
			while (list($check_topic_id, $check_last_post) = @each($new_topics[$cur_forum['fid']]))
			{
				if ((empty($tracked_topics['topics'][$check_topic_id]) || $tracked_topics['topics'][$check_topic_id] < $check_last_post) && (empty($tracked_topics['forums'][$cur_forum['fid']]) || $tracked_topics['forums'][$cur_forum['fid']] < $check_last_post))
				{
					$forum_page['item_status']['new'] = 'new';
					$forum_page['item_title']['status'] = '<small>'.sprintf($lang_index['Forum has new'], '<a href="'.forum_link($forum_url['search_new_results'], $cur_forum['fid']).'" title="'.$lang_index['New posts title'].'">'.$lang_index['Forum new posts'].'</a>').'</small>';

					break;
				}
			}
		}

		($hook = get_hook('in_normal_row_pre_item_title_merge')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		$forum_page['item_body']['subject']['title'] = '<h3 class="hn">'.implode(' ', $forum_page['item_title']).'</h3>';


		// Setup the forum description and mod list
		if ($cur_forum['forum_desc'] != '')
			$forum_page['item_subject']['desc'] = $cur_forum['forum_desc'];

		if ($cur_forum['moderators'] != '')
		{
			$forum_page['mods_array'] = unserialize($cur_forum['moderators']);
			$forum_page['item_mods'] = array();

			while (list($mod_username, $mod_id) = @each($forum_page['mods_array']))
				$forum_page['item_mods'][] = ($forum_user['g_view_users'] == '1') ? '<a href="'.forum_link($forum_url['user'], $mod_id).'">'.forum_htmlencode($mod_username).'</a>' : forum_htmlencode($mod_username);

			($hook = get_hook('in_row_modify_modlist')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

			$forum_page['item_subject']['modlist'] = '<span class="modlist">('.sprintf($lang_index['Moderated by'], implode(', ', $forum_page['item_mods'])).')</span>';
		}

		($hook = get_hook('in_normal_row_pre_item_subject_merge')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		if (!empty($forum_page['item_subject']))
			$forum_page['item_body']['subject']['desc'] = '<p>'.implode(' ', $forum_page['item_subject']).'</p>';


		// Setup forum topics, post count and last post
		$forum_page['item_body']['info']['topics'] = '<li class="info-topics"><strong>'.forum_number_format($cur_forum['num_topics']).'</strong> <span class="label">'.(($cur_forum['num_topics'] == 1) ? $lang_index['topic'] : $lang_index['topics']).'</span></li>';
		$forum_page['item_body']['info']['posts'] = '<li class="info-posts"><strong>'.forum_number_format($cur_forum['num_posts']).'</strong> <span class="label">'.(($cur_forum['num_posts'] == 1) ? $lang_index['post'] : $lang_index['posts']).'</span></li>';

		if ($cur_forum['last_post'] != '')
			$forum_page['item_body']['info']['lastpost'] = '<li class="info-lastpost"><span class="label">'.$lang_index['Last post'].'</span> <strong><a href="'.forum_link($forum_url['post'], $cur_forum['last_post_id']).'">'.format_time($cur_forum['last_post']).'</a></strong> <cite>'.sprintf($lang_index['Last poster'], forum_htmlencode($cur_forum['last_poster'])).'</cite></li>';
		else
			$forum_page['item_body']['info']['lastpost'] = '<li class="info-lastpost"><strong>'.$lang_index['Forum is empty'].'</strong> <span><a href="'.forum_link($forum_url['new_topic'], $cur_forum['fid']).'">'.$lang_index['First post nag'].'</a></span></li>';

		($hook = get_hook('in_normal_row_pre_display')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	}

	// Generate classes for this forum depending on its status
	$forum_page['item_style'] = (($forum_page['item_count'] % 2 != 0) ? ' odd' : ' even').(($forum_page['item_count'] == 1) ? ' main-item1' : '').((!empty($forum_page['item_status'])) ? ' '.implode(' ', $forum_page['item_status']) : '');

	($hook = get_hook('in_row_pre_display')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
		<div id="forum<?php echo $cur_forum['fid'] ?>" class="main-item<?php echo $forum_page['item_style'] ?>">
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
// Did we output any categories and forums?
if ($forum_page['cur_category'] > 0)
	echo  "\t".'</div>'."\n";
else
{

?>
	<div class="main-content message">
		<p><?php echo $lang_index['Empty board'] ?></p>
	</div>
<?php

}

($hook = get_hook('in_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

$tpl_temp = trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->


// START SUBST - <!-- forum_info -->
ob_start();

($hook = get_hook('in_info_output_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

// Collect some statistics from the database
$query = array(
	'SELECT'	=> 'COUNT(u.id)-1',
	'FROM'		=> 'users AS u'
);

($hook = get_hook('in_stats_qr_get_user_count')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
$stats['total_users'] = $forum_db->result($result);

$query = array(
	'SELECT'	=> 'u.id, u.username',
	'FROM'		=> 'users AS u',
	'WHERE'		=> 'u.group_id != '.FORUM_UNVERIFIED,
	'ORDER BY'	=> 'u.registered DESC',
	'LIMIT'		=> '1'
);

($hook = get_hook('in_stats_qr_get_newest_user')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
$stats['last_user'] = $forum_db->fetch_assoc($result);

$query = array(
	'SELECT'	=> 'SUM(f.num_topics), SUM(f.num_posts)',
	'FROM'		=> 'forums AS f'
);

($hook = get_hook('in_stats_qr_get_post_stats')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
list($stats['total_topics'], $stats['total_posts']) = $forum_db->fetch_row($result);

$stats_list['no_of_users'] = '<li class="st-users"><span>'.$lang_index['No of users'].':</span> <strong>'.forum_number_format($stats['total_users']).'</strong></li>';
$stats_list['newest_user'] = '<li class="st-users"><span>'.$lang_index['Newest user'].':</span> <strong>'.($forum_user['g_view_users'] == '1' ? '<a href="'.forum_link($forum_url['user'], $stats['last_user']['id']).'">'.forum_htmlencode($stats['last_user']['username']).'</a>' : forum_htmlencode($stats['last_user']['username'])).'</strong></li>';
$stats_list['no_of_topics'] = '<li class="st-activity"><span>'.$lang_index['No of topics'].':</span> <strong>'.forum_number_format($stats['total_topics']).'</strong></li>';
$stats_list['no_of_posts'] = '<li class="st-activity"><span>'.$lang_index['No of posts'].':</span> <strong>'.forum_number_format($stats['total_posts']).'</strong></li>';

($hook = get_hook('in_stats_pre_info_output')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

?>
<div id="brd-stats" class="gen-content">
	<h2 class="hn"><span><?php echo $lang_index['Statistics'] ?></span></h2>
	<ul>
		<?php echo implode("\n\t\t", $stats_list)."\n" ?>
	</ul>
</div>
<?php

($hook = get_hook('in_stats_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
($hook = get_hook('in_users_online_start')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

if ($forum_config['o_users_online'] == '1')
{
	// Fetch users online info and generate strings for output
	$query = array(
		'SELECT'	=> 'o.user_id, o.ident',
		'FROM'		=> 'online AS o',
		'WHERE'		=> 'o.idle=0',
		'ORDER BY'	=> 'o.ident'
	);

	($hook = get_hook('in_users_online_qr_get_online_info')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$num_guests = 0;
	$users = array();

	while ($forum_user_online = $forum_db->fetch_assoc($result))
	{
		($hook = get_hook('in_users_online_add_online_user_loop')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

		if ($forum_user_online['user_id'] > 1)
			$users[] = ($forum_user['g_view_users'] == '1') ? '<a href="'.forum_link($forum_url['user'], $forum_user_online['user_id']).'">'.forum_htmlencode($forum_user_online['ident']).'</a>' : forum_htmlencode($forum_user_online['ident']);
		else
			++$num_guests;
	}

	($hook = get_hook('in_users_online_pre_online_info_output')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
?>
<div id="brd-online" class="gen-content">
	<h3 class="hn"><span><?php echo $lang_index['Currently online'] ?></span></h3>
	<p><?php (($num_guests != 1) ? printf($lang_index['Guests plural'], forum_number_format($num_guests)) : printf($lang_index['Guest single'], $num_guests)) ?> <?php ((count($users) > 1) ? printf($lang_index['Users plural'], forum_number_format(count($users))) : printf(((count($users) == 0) ? $lang_index['Users none'] : $lang_index['User single']), count($users))) ?> <?php echo ((count($users) > 0) ? implode(', ', $users) : '') ?></p>
<?php ($hook = get_hook('in_new_online_data')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null; ?>
</div>
<?php

	($hook = get_hook('in_users_online_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;
}

($hook = get_hook('in_info_end')) ? (defined('FORUM_USE_INCLUDE') ? include $hook : eval($hook)) : null;

$tpl_temp = trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_info -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_info -->

require FORUM_ROOT.'footer.php';
