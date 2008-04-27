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


//
// Display the admin navigation menu
//
function generate_admin_menu()
{
	global $forum_config, $forum_url, $forum_user, $lang_admin, $db_type;

	$adnav_sublinks = array();

	if ($forum_user['g_id'] != FORUM_ADMIN)
	{
		$adnav_sublinks[] = '<li'.((FORUM_PAGE == 'admin-information') ? ' class="li-first isactive">' : ' class="li-first">').'<a href="'.forum_link($forum_url['admin_index']).'">'.$lang_admin['Information'].'</span></a></li>';
		$adnav_sublinks[] = '<li'.((FORUM_PAGE == 'admin-users') ? ' class="isactive"' : '').'><a href="'.forum_link($forum_url['admin_users']).'">'.$lang_admin['User search'].'</a></li>';

		if ($forum_config['o_censoring'] == '1')
			$adnav_sublinks[] = '<li'.((FORUM_PAGE == 'admin-censoring') ? ' class="isactive">' : '>').'<a href="'.forum_link($forum_url['admin_censoring']).'">'.$lang_admin['Censoring'].'</a></li>';

		$adnav_sublinks[] = '<li'.((FORUM_PAGE == 'admin-reports') ? ' class="isactive">' : '>').'<a href="'.forum_link($forum_url['admin_reports']).'">'.$lang_admin['Reports'].'</a></li>';

		if ($forum_user['g_mod_ban_users'] == '1')
			$adnav_sublinks[] = '<li'.((FORUM_PAGE == 'admin-bans') ? ' class="isactive">' : '>').'<a href="'.forum_link($forum_url['admin_bans']).'">'.$lang_admin['Bans'].'</a></li>';
	}
	else
	{
		if (FORUM_PAGE_SECTION == 'start')
		{
			$adnav_sublinks[] = '<li'.((FORUM_PAGE == 'admin-information') ? ' class="isactive"' : '').'><a href="'.forum_link($forum_url['admin_index']).'">'.$lang_admin['Information'].'</a></li>';
			$adnav_sublinks[] = '<li'.((FORUM_PAGE == 'admin-categories') ? ' class="isactive"' : '').'><a href="'.forum_link($forum_url['admin_categories']).'">'.$lang_admin['Categories'].'</a></li>';
			$adnav_sublinks[] = '<li'.((FORUM_PAGE == 'admin-forums') ? ' class="isactive"' : '').'><a href="'.forum_link($forum_url['admin_forums']).'">'.$lang_admin['Forums'].'</a></li>';
		}
		else if (FORUM_PAGE_SECTION == 'users')
		{
			$adnav_sublinks[] = '<li'.((FORUM_PAGE == 'admin-users') ? ' class="isactive"' : '').'><a href="'.forum_link($forum_url['admin_users']).'">'.$lang_admin['Searches'].'</a></li>';
			$adnav_sublinks[] = '<li'.((FORUM_PAGE == 'admin-groups') ? ' class="isactive"' : '').'><a href="'.forum_link($forum_url['admin_groups']).'">'.$lang_admin['Groups'].'</a></li>';
			$adnav_sublinks[] = '<li'.((FORUM_PAGE == 'admin-ranks') ? ' class="isactive"' : '').'><a href="'.forum_link($forum_url['admin_ranks']).'">'.$lang_admin['Ranks'].'</a></li>';
			$adnav_sublinks[] = '<li'.((FORUM_PAGE == 'admin-bans') ? ' class="isactive"' : '').'><a href="'.forum_link($forum_url['admin_bans']).'">'.$lang_admin['Bans'].'</a></li>';
		}
		else if (FORUM_PAGE_SECTION == 'options')
		{
			$adnav_sublinks[] = '<li'.((FORUM_PAGE == 'admin-options-setup') ? ' class="isactive"' : '').'><a href="'.forum_link($forum_url['admin_options_setup']).'">'.$lang_admin['Setup'].'</a></li>';
			$adnav_sublinks[] = '<li'.((FORUM_PAGE == 'admin-options-features') ? ' class="isactive"' : '').'><a href="'.forum_link($forum_url['admin_options_features']).'">'.$lang_admin['Features'].'</a></li>';
			$adnav_sublinks[] = '<li'.((FORUM_PAGE == 'admin-options-announcements') ? ' class="isactive"' : '').'><a href="'.forum_link($forum_url['admin_options_announcements']).'">'.$lang_admin['Announcements'].'</a></li>';
			$adnav_sublinks[] = '<li'.((FORUM_PAGE == 'admin-options-email') ? ' class="isactive"' : '').'><a href="'.forum_link($forum_url['admin_options_email']).'">'.$lang_admin['E-mail'].'</a></li>';
			$adnav_sublinks[] = '<li'.((FORUM_PAGE == 'admin-options-registration') ? ' class="isactive"' : '').'><a href="'.forum_link($forum_url['admin_options_registration']).'">'.$lang_admin['Registration'].'</a></li>';
			$adnav_sublinks[] = '<li'.((FORUM_PAGE == 'admin-censoring') ? ' class="isactive"' : '').'><a href="'.forum_link($forum_url['admin_censoring']).'">'.$lang_admin['Censoring'].'</a></li>';
		}
		else if (FORUM_PAGE_SECTION == 'management')
		{
			$adnav_sublinks[] = '<li'.((FORUM_PAGE == 'admin-reports') ? ' class="isactive"' : '').'><a href="'.forum_link($forum_url['admin_reports']).'">'.$lang_admin['Reports'].'</a></li>';
			$adnav_sublinks[] = '<li'.((FORUM_PAGE == 'admin-prune') ? ' class="isactive"' : '').'><a href="'.forum_link($forum_url['admin_prune']).'">'.$lang_admin['Prune topics'].'</a></li>';

			$adnav_sublinks[] = '<li'.((FORUM_PAGE == 'admin-reindex') ? ' class="isactive"' : '').'><a href="'.forum_link($forum_url['admin_reindex']).'">'.$lang_admin['Rebuild index'].'</a></li>';

			$adnav_sublinks[] = '<li'.((FORUM_PAGE == 'admin-options-maintenance') ? ' class="isactive"' : '').'><a href="'.forum_link($forum_url['admin_options_maintenance']).'">'.$lang_admin['Maintenance mode'].'</a></li>';
		}
		else if (FORUM_PAGE_SECTION == 'extensions')
		{
			$adnav_sublinks[] = '<li'.((FORUM_PAGE == 'admin-extensions-manage') ? ' class="isactive"' : '').'><a href="'.forum_link($forum_url['admin_extensions_manage']).'">'.$lang_admin['Manage extensions'].'</a></li>';
			$adnav_sublinks[] = '<li'.((FORUM_PAGE == 'admin-extensions-install') ? ' class="isactive"' : '').'><a href="'.forum_link($forum_url['admin_extensions_install']).'">'.$lang_admin['Install extensions'].'</a></li>';
		}
	}

	($hook = get_hook('ca_admin_menu_new_sublink')) ? eval($hook) : null;

	if (count($adnav_sublinks) > 1)
		$adnav_submenu = "\n\t\t\t\t".'<div><ul>'."\n\t\t\t\t\t".implode("\n\t\t\t\t\t", $adnav_sublinks)."\n\t\t\t\t".'</ul></div>';
	else
		$adnav_submenu = '';

	if ($forum_user['g_id'] != FORUM_ADMIN)
		$adnav_links[] = '<li class="topactive"><a href="'.forum_link($forum_url['admin_index']).'"><span>'.$lang_admin['Moderate'].'</span></a>'.$adnav_submenu."\n\t\t\t".'</li>';
	else
	{
		$adnav_links[] = '<li'.((FORUM_PAGE_SECTION == 'start') ? ' class="topactive"' : '').'><a href="'.forum_link($forum_url['admin_index']).'"><span>'.$lang_admin['Start'].'</span></a>'.(((FORUM_PAGE_SECTION == 'start') && ($adnav_submenu != '')) ? $adnav_submenu."\n\t\t\t" : '').'</li>';
		$adnav_links[] = '<li'.((FORUM_PAGE_SECTION == 'options') ? ' class="topactive"' : '').'><a href="'.forum_link($forum_url['admin_options_setup']).'"><span>'.$lang_admin['Settings'].'</span></a>'.(((FORUM_PAGE_SECTION == 'options') && ($adnav_submenu != '')) ? $adnav_submenu."\n\t\t\t" : '').'</li>';
		$adnav_links[] = '<li'.((FORUM_PAGE_SECTION == 'users') ? ' class="topactive"' : '').'><a href="'.forum_link($forum_url['admin_users']).'"><span>'.$lang_admin['Users'].'</span></a>'.(((FORUM_PAGE_SECTION == 'users') && ($adnav_submenu != '')) ? $adnav_submenu."\n\t\t\t" : '').'</li>';
		$adnav_links[] = '<li'.((FORUM_PAGE_SECTION == 'management') ? ' class="topactive"' : '').'><a href="'.forum_link($forum_url['admin_reports']).'"><span>'.$lang_admin['Management'].'</span></a>'.(((FORUM_PAGE_SECTION == 'management') && ($adnav_submenu != '')) ? $adnav_submenu."\n\t\t\t" : '').'</li>';
		$adnav_links[] = '<li'.((FORUM_PAGE_SECTION == 'extensions') ? ' class="topactive"' : '').'><a href="'.forum_link($forum_url['admin_extensions_manage']).'"><span>'.$lang_admin['Extensions'].'</span></a>'.(((FORUM_PAGE_SECTION == 'extensions') && ($adnav_submenu != '')) ? $adnav_submenu."\n\t\t\t" : '').'</li>';
	}

	($hook = get_hook('ca_admin_menu_new_link')) ? eval($hook) : null;

?>
	<div class="main-nav<?php if ($adnav_submenu != '') echo ' submenu' ?>">
		<ul>
			<?php echo implode("\n\t\t\t", $adnav_links)."\n" ?>
		</ul>
	</div>
<?php

}


//
// Delete topics from $forum_id that are "older than" $prune_date (if $prune_sticky is 1, sticky topics will also be deleted)
//
function prune($forum_id, $prune_sticky, $prune_date)
{
	global $forum_db, $db_type;

	// Fetch topics to prune
	$query = array(
		'SELECT'	=> 't.id',
		'FROM'		=> 'topics AS t',
		'WHERE'		=> 't.forum_id='.$forum_id
	);

	if ($prune_date != -1)
		$query['WHERE'] .= ' AND last_post<'.$prune_date;
	if (!$prune_sticky)
		$query['WHERE'] .= ' AND sticky=\'0\'';

	($hook = get_hook('ca_qr_get_topics_to_prune')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$topic_ids = '';
	while ($row = $forum_db->fetch_row($result))
		$topic_ids .= (($topic_ids != '') ? ',' : '').$row[0];

	if ($topic_ids != '')
	{
		// Fetch posts to prune (used lated for updating the search index)
		$query = array(
			'SELECT'	=> 'p.id',
			'FROM'		=> 'posts AS p',
			'WHERE'		=> 'p.topic_id IN('.$topic_ids.')'
		);

		($hook = get_hook('ca_qr_get_posts_to_prune')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		$post_ids = '';
		while ($row = $forum_db->fetch_row($result))
			$post_ids .= (($post_ids != '') ? ',' : '').$row[0];

		// Delete topics
		$query = array(
			'DELETE'	=> 'topics',
			'WHERE'		=> 'id IN('.$topic_ids.')'
		);

		($hook = get_hook('ca_qr_prune_topics')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// Delete posts
		$query = array(
			'DELETE'	=> 'posts',
			'WHERE'		=> 'topic_id IN('.$topic_ids.')'
		);

		($hook = get_hook('ca_qr_prune_posts')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// Delete subscriptions
		$query = array(
			'DELETE'	=> 'subscriptions',
			'WHERE'		=> 'topic_id IN('.$topic_ids.')'
		);

		($hook = get_hook('ca_qr_prune_subscriptions')) ? eval($hook) : null;
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		// We removed a bunch of posts, so now we have to update the search index
		require_once FORUM_ROOT.'include/search_idx.php';
		strip_search_index($post_ids);
	}
}

($hook = get_hook('ca_new_function')) ? eval($hook) : null;
