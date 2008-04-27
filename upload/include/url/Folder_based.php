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

// These are the simple folder based SEF URLs
$forum_url = array(
	'change_email'					=>	'change/email/$1/',
	'change_email_key'				=>	'change/email/$1/$2/',
	'change_password'				=>	'change/password/$1/',
	'change_password_key'			=>	'change/password/$1/$2/',
	'delete'						=>	'delete/$1/',
	'delete_avatar'					=>	'delete/avatar/$1/$2/',
	'delete_user'					=>	'delete/user/$1/',
	'edit'							=>	'edit/$1/',
	'email'							=>	'email/$1/',
	'forum'							=>	'forum/$1/',
	'forum_rss'						=>	'forum/$1/rss/',
	'forum_atom'					=>	'forum/$1/atom/',
	'help'							=>	'help/$1/',
	'index'							=>	'',
	'login'							=>	'login/',
	'logout'						=>	'logout/$1/$2/',
	'mark_read'						=>	'mark/read/$1/',
	'mark_forum_read'				=>	'mark/forum/$1/read/$2/',
	'new_topic'						=>	'new/topic/$1/',
	'new_reply'						=>	'new/reply/$1/',
	'post'							=>	'post/$1/#p$1',
	'profile_about'					=>	'user/$1/about/',
	'profile_identity'				=>	'user/$1/identity/',
	'profile_settings'				=>	'user/$1/settings/',
	'profile_avatar'				=>	'user/$1/avatar/',
	'profile_signature'				=>	'user/$1/signature/',
	'profile_admin'					=>	'user/$1/admin/',
	'quote'							=>	'new/reply/$1/quote/$2/',
	'register'						=>	'register/',
	'report'						=>	'report/$1/',
	'request_password'				=>	'request/password/',
	'rules'							=>	'rules/',
	'search'						=>	'search/',
	'search_resultft'				=>	'search/k$1/$2/a$3/$4/$5/$6/$7/',
	'search_results'				=>	'search/$1/',
	'search_new'					=>	'search/new/',
	'search_24h'					=>	'search/recent/',
	'search_unanswered'				=>	'search/unanswered/',
	'search_subscriptions'			=>	'search/subscriptions/',
	'search_user_posts'				=>	'search/posts/user/$1/',
	'search_user_topics'			=>	'search/topics/user/$1/',
	'subscribe'						=>	'subscribe/$1/$2/',
	'topic'							=>	'topic/$1/',
	'topic_rss'						=>	'topic/$1/rss/',
	'topic_atom'					=>	'topic/$1/atom/',
	'topic_new_posts'				=>	'topic/$1/new/posts/',
	'topic_last_post'				=>	'topic/$1/last/post/',
	'unsubscribe'					=>	'unsubscribe/$1/$2/',
	'upload_avatar'					=>	'upload/avatar/$1/',
	'user'							=>	'user/$1/',
	'users'							=>	'users/',
	'users_browse'					=>	'users/$4/$1/$2/$3/',
	'page'							=>	'page/$1/',
	'moderate'						=>	'moderate/',
	'moderate_forum'				=>	'moderate/$1/',
	'get_host'						=>	'get_host/$1/',
	'move'							=>	'move_topics/$1/$2/',
	'open'							=>	'open/$1/$2/$3/',
	'close'							=>	'close/$1/$2/$3/',
	'stick'							=>	'stick/$1/$2/$3/',
	'unstick'						=>	'unstick/$1/$2/$3/',
	'delete_multiple'				=>	'moderate/$1/$2/',
	'admin_index'					=>	'admin/index.php',
	'admin_bans'					=>	'admin/bans.php',
	'admin_categories'				=>	'admin/categories.php',
	'admin_censoring'				=>	'admin/censoring.php',
	'admin_extensions_manage'		=>	'admin/extensions.php?section=manage',
	'admin_extensions_install'		=>	'admin/extensions.php?section=install',
	'admin_forums'					=>	'admin/forums.php',
	'admin_groups'					=>	'admin/groups.php',
	'admin_loader'					=>	'admin/loader.php',
	'admin_reindex'					=>	'admin/reindex.php',
	'admin_options_setup'			=>	'admin/options.php?section=setup',
	'admin_options_features'		=>	'admin/options.php?section=features',
	'admin_options_content'			=>	'admin/options.php?section=content',
	'admin_options_email'			=>	'admin/options.php?section=email',
	'admin_options_announcements'	=>	'admin/options.php?section=announcements',
	'admin_options_registration'	=>	'admin/options.php?section=registration',
	'admin_options_communications'	=>	'admin/options.php?section=communications',
	'admin_options_maintenance'		=>	'admin/options.php?section=maintenance',
	'admin_prune'					=>	'admin/prune.php',
	'admin_ranks'					=>	'admin/ranks.php',
	'admin_reports'					=>	'admin/reports.php',
	'admin_users'					=>	'admin/users.php'
);
