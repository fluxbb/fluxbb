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
function generate_admin_menu($submenu)
{
	global $forum_config, $forum_url, $forum_user, $lang_admin_common, $db_type;

	if ($submenu)
	{
		$forum_page['admin_submenu'] = array();

		if ($forum_user['g_id'] != FORUM_ADMIN)
		{
			$forum_page['admin_submenu']['index'] = '<li class="'.((FORUM_PAGE == 'admin-information') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_index']).'">'.$lang_admin_common['Information'].'</span></a></li>';
			$forum_page['admin_submenu']['users'] = '<li class="'.((FORUM_PAGE == 'admin-users') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_users']).'">'.$lang_admin_common['User search'].'</a></li>';

			if ($forum_config['o_censoring'] == '1')
				$forum_page['admin_submenu']['censoring'] = '<li class="'.((FORUM_PAGE == 'admin-censoring') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_censoring']).'">'.$lang_admin_common['Censoring'].'</a></li>';

			$form_page['admin_submenu']['reports'] = '<li class="'.((FORUM_PAGE == 'admin-reports') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_reports']).'">'.$lang_admin_common['Reports'].'</a></li>';

			if ($forum_user['g_mod_ban_users'] == '1')
				$forum_page['admin_submenu']['bans'] = '<li class="'.((FORUM_PAGE == 'admin-bans') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_bans']).'">'.$lang_admin_common['Bans'].'</a></li>';
		}
		else
		{
			if (FORUM_PAGE_SECTION == 'start')
			{
				$forum_page['admin_submenu']['index'] = '<li class="'.((FORUM_PAGE == 'admin-information') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_index']).'">'.$lang_admin_common['Information'].'</a></li>';
				$forum_page['admin_submenu']['categories'] = '<li class="'.((FORUM_PAGE == 'admin-categories') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_categories']).'">'.$lang_admin_common['Categories'].'</a></li>';
				$forum_page['admin_submenu']['forums'] = '<li class="'.((FORUM_PAGE == 'admin-forums') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_forums']).'">'.$lang_admin_common['Forums'].'</a></li>';
			}
			else if (FORUM_PAGE_SECTION == 'users')
			{
				$forum_page['admin_submenu']['users'] = '<li class="'.((FORUM_PAGE == 'admin-users') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_users']).'">'.$lang_admin_common['Searches'].'</a></li>';
				$forum_page['admin_submenu']['groups'] = '<li class="'.((FORUM_PAGE == 'admin-groups') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_groups']).'">'.$lang_admin_common['Groups'].'</a></li>';
				$forum_page['admin_submenu']['ranks'] = '<li class="'.((FORUM_PAGE == 'admin-ranks') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_ranks']).'">'.$lang_admin_common['Ranks'].'</a></li>';
				$forum_page['admin_submenu']['bans'] = '<li class="'.((FORUM_PAGE == 'admin-bans') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_bans']).'">'.$lang_admin_common['Bans'].'</a></li>';
			}
			else if (FORUM_PAGE_SECTION == 'settings')
			{
				$forum_page['admin_submenu']['settings_setup'] = '<li class="'.((FORUM_PAGE == 'admin-settings-setup') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_settings_setup']).'">'.$lang_admin_common['Setup'].'</a></li>';
				$forum_page['admin_submenu']['settings_features'] = '<li class="'.((FORUM_PAGE == 'admin-settings-features') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_settings_features']).'">'.$lang_admin_common['Features'].'</a></li>';
				$forum_page['admin_submenu']['settings-announcements'] = '<li class="'.((FORUM_PAGE == 'admin-settings-announcements') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_settings_announcements']).'">'.$lang_admin_common['Announcements'].'</a></li>';
				$forum_page['admin_submenu']['settings-email'] = '<li class="'.((FORUM_PAGE == 'admin-settings-email') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_settings_email']).'">'.$lang_admin_common['E-mail'].'</a></li>';
				$forum_page['admin_submenu']['settings-registration'] = '<li class="'.((FORUM_PAGE == 'admin-settings-registration') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_settings_registration']).'">'.$lang_admin_common['Registration'].'</a></li>';
				$forum_page['admin_submenu']['censoring'] = '<li class="'.((FORUM_PAGE == 'admin-settings-censoring') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_censoring']).'">'.$lang_admin_common['Censoring'].'</a></li>';
			}
			else if (FORUM_PAGE_SECTION == 'management')
			{
				$forum_page['admin_submenu']['reports'] = '<li class="'.((FORUM_PAGE == 'admin-reports') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_reports']).'">'.$lang_admin_common['Reports'].'</a></li>';
				$forum_page['admin_submenu']['prune'] = '<li class="'.((FORUM_PAGE == 'admin-prune') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_prune']).'">'.$lang_admin_common['Prune topics'].'</a></li>';
				$forum_page['admin_submenu']['reindex'] = '<li class="'.((FORUM_PAGE == 'admin-reindex') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_reindex']).'">'.$lang_admin_common['Rebuild index'].'</a></li>';
				$forum_page['admin_submenu']['options-maintenance'] = '<li class="'.((FORUM_PAGE == 'admin-maintenace') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_settings_maintenance']).'">'.$lang_admin_common['Maintenance mode'].'</a></li>';
			}
			else if (FORUM_PAGE_SECTION == 'extensions')
			{
				$forum_page['admin_submenu']['extensions-manage'] = '<li class="'.((FORUM_PAGE == 'admin-extensions-manage') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_extensions_manage']).'">'.$lang_admin_common['Manage extensions'].'</a></li>';
				$forum_page['admin_submenu']['extensions-install'] = '<li class="'.((FORUM_PAGE == 'admin-extensions-install') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_extensions_install']).'">'.$lang_admin_common['Install extensions'].'</a></li>';
			}
		}

		($hook = get_hook('ca_admin_menu_new_sublink')) ? eval($hook) : null;

		return (!empty($forum_page['admin_submenu'])) ? implode("\n\t\t", $forum_page['admin_submenu']) : '';
	}
	else
	{
		if ($forum_user['g_id'] != FORUM_ADMIN)
			$forum_page['admin_menu']['index'] = '<li class="active item1"><a href="'.forum_link($forum_url['admin_index']).'"><span>'.$lang_admin_common['Moderate'].'</span></a>'.$adnav_submenu."\n\t\t\t".'</li>';
		else
		{
			$forum_page['admin_menu']['index'] = '<li class="'.((FORUM_PAGE_SECTION == 'start') ? 'active' : 'normal').((empty($forum_page['admin_menu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_index']).'"><span>'.$lang_admin_common['Start'].'</span></a></li>';
			$forum_page['admin_menu']['settings_setup'] = '<li class="'.((FORUM_PAGE_SECTION == 'settings') ? 'active' : 'normal').((empty($forum_page['admin_menu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_settings_setup']).'"><span>'.$lang_admin_common['Settings'].'</span></a></li>';
			$forum_page['admin_menu']['users'] = '<li class="'.((FORUM_PAGE_SECTION == 'users') ? 'active' : 'normal').((empty($forum_page['admin_menu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_users']).'"><span>'.$lang_admin_common['Users'].'</span></a></li>';
			$forum_page['admin_menu']['reports'] = '<li class="'.((FORUM_PAGE_SECTION == 'management') ? 'active' : 'normal').((empty($forum_page['admin_menu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_reports']).'"><span>'.$lang_admin_common['Management'].'</span></a></li>';
			$forum_page['admin_menu']['extensions_manage'] = '<li class="'.((FORUM_PAGE_SECTION == 'extensions') ? 'active' : 'normal').((empty($forum_page['admin_menu'])) ? ' item1' : '').'"><a href="'.forum_link($forum_url['admin_extensions_manage']).'"><span>'.$lang_admin_common['Extensions'].'</span></a></li>';
		}

		($hook = get_hook('ca_admin_menu_new_link')) ? eval($hook) : null;

		return implode("\n\t\t", $forum_page['admin_menu']);
	}
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
		if (!defined('FORUM_SEARCH_IDX_FUNCTIONS_LOADED'))
			require FORUM_ROOT.'include/search_idx.php';

		strip_search_index($post_ids);
	}
}

($hook = get_hook('ca_new_function')) ? eval($hook) : null;
