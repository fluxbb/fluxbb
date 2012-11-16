<?php

// Language definitions for frequently used strings
return array(

// Text orientation and encoding
'lang_direction'					=>	'ltr', // ltr (Left-To-Right) or rtl (Right-To-Left)
'lang_identifier'					=>	'en',

// Number formatting
'lang_decimal_point'				=>	'.',
'lang_thousands_sep'				=>	',',

// Notices
'bad_request'						=>	'Bad request. The link you followed is incorrect or outdated.',
'no_view'							=>	'You do not have permission to view these forums.',
'no_permission'						=>	'You do not have permission to access this page.',
'bad_referrer'						=>	'Bad HTTP_REFERER. You were referred to this page from an unauthorized source. If the problem persists please make sure that \'Base URL\' is correctly set in Admin/Options and that you are visiting the forum by navigating to that URL. More information regarding the referrer check can be found in the FluxBB documentation.',
'no_cookie'							=>	'You appear to have logged in successfully, however a cookie has not been set. Please check your settings and if applicable, enable cookies for this website.',
'pun_include_error'					=>	'Unable to process user include %s from template %s. There is no such file in neither the template directory nor in the user include directory.',

// Miscellaneous
'announcement'						=>	'Announcement',
'options'							=>	'Options',
'submit'							=>	'Submit', // "Name" of submit buttons
'ban_message'						=>	'You are banned from this forum.',
'ban_message_2'						=>	'The ban expires at the end of',
'ban_message_3'						=>	'The administrator or moderator that banned you left the following message:',
'ban_message_4'						=>	'Please direct any inquiries to the forum administrator at',
'never'								=>	'Never',
'today'								=>	'Today',
'yesterday'							=>	'Yesterday',
'info'								=>	'Info', // A common table header
'go_back'							=>	'Go back',
'maintenance'						=>	'Maintenance',
'redirecting'						=>	'Redirecting',
'click_redirect'					=>	'Click here if you do not want to wait any longer (or if your browser does not automatically forward you)',
'on'								=>	'on', // As in "BBCode is on"
'off'								=>	'off',
'invalid_email'						=>	'The email address you entered is invalid.',
'required'							=>	'(Required)',
'required_field'					=>	'is a required field in this form.', // For javascript form validation
'last_post'							=>	'Last post',
'by'								=>	'by :author', // As in last post by some user
'new_posts'							=>	'New posts', // The link that leads to the first new post
'new_posts_info'					=>	'Go to the first new post in this topic.', // The popup text for new posts links
'username'							=>	'Username',
'password'							=>	'Password',
'email'								=>	'Email',
'send_email'						=>	'Send email',
'moderated_by'						=>	'Moderated by',
'registered'						=>	'Registered',
'subject'							=>	'Subject',
'message'							=>	'Message',
'topic'								=>	'Topic',
'forum'								=>	'Forum',
'posts'								=>	'Posts',
'replies'							=>	'Replies',
'pages'								=>	'Pages:',
'page'								=>	'Page %s',
'bbcode'							=>	'BBCode:', // You probably shouldn't change this
'url_tag'							=>	'[url] tag:',
'img_tag'							=>	'[img] tag:',
'smilies'							=>	'Smilies:',
'and'								=>	'and',
'image_link'						=>	'image', // This is displayed (i.e. <image>) instead of images when "Show images" is disabled in the profile
'wrote'								=>	'wrote:', // For [quote]'s
'mailer'							=>	'%s Mailer', // As in "MyForums Mailer" in the signature of outgoing emails
'important'							=>	'Important information',
'write_message_legend'				=>	'Write your message and submit',
'previous'							=>	'Previous',
'next'								=>	'Next',
'spacer'							=>	'…', // Ellipsis for paginate

// Title
'title'								=>	'Title',
'member'							=>	'Member', // Default title
'moderator'							=>	'Moderator',
'administrator'						=>	'Administrator',
'banned'							=>	'Banned',
'guest'								=>	'Guest',

// Stuff for include/parser.php
'bbcode_error_no_opening_tag'		=>	'[/%1$s] was found without a matching [%1$s]',
'bbcode_error_invalid_nesting'		=>	'[%1$s] was opened within [%2$s], this is not allowed',
'bbcode_error_invalid_self-nesting'	=>	'[%s] was opened within itself, this is not allowed',
'bbcode_error_no_closing_tag'		=>	'[%1$s] was found without a matching [/%1$s]',
'bbcode_error_empty_attribute'		=>	'[%s] tag had an empty attribute section',
'bbcode_error_tag_not_allowed'		=>	'You are not allowed to use [%s] tags',
'bbcode_error_tag_url_not_allowed'	=>	'You are not allowed to post links',
'bbcode_code_problem'				=>	'There is a problem with your [code] tags',
'bbcode_list_size_error'			=>	'Your list was too long to parse, please make it smaller!',

// Stuff for the navigator (top of every page)
'index'								=>	'Index',
'user_list'							=>	'User list',
'rules'								=>	'Rules',
'search'							=>	'Search',
'register'							=>	'Register',
'login'								=>	'Login',
'not_logged_in'						=>	'You are not logged in.',
'profile'							=>	'Profile',
'logout'							=>	'Logout',
'logged_in_as'						=>	'Logged in as',
'admin'								=>	'Administration',
'last_visit'						=>	'Last visit: %s',
'topic_searches'					=>	'Topics:',
'new_posts_header'					=>	'New',
'active_topics'						=>	'Active',
'unanswered_topics'					=>	'Unanswered',
'posted_topics'						=>	'Posted',
'show_new_posts'					=>	'Find topics with new posts since your last visit.',
'show_active_topics'				=>	'Find topics with recent posts.',
'show_unanswered_topics'			=>	'Find topics with no replies.',
'show_posted_topics'				=>	'Find topics you have posted to.',
'mark_all_as_read'					=>	'Mark all topics as read',
'mark_forum_read'					=>	'Mark this forum as read',
'title_separator'					=>	' / ',

// Stuff for the page footer
'board_footer'						=>	'Board footer',
'jump_to'							=>	'Jump to',
'go'								=>	' Go ', // Submit button in forum jump
'moderate_topic'					=>	'Moderate topic',
'move_topic'						=>	'Move topic',
'open_topic'						=>	'Open topic',
'close_topic'						=>	'Close topic',
'unstick_topic'						=>	'Unstick topic',
'stick_topic'						=>	'Stick topic',
'moderate_forum'					=>	'Moderate forum',
'powered_by'						=>	'Powered by :link',

// Debug information
'debug_table'						=>	'Debug information',
'querytime'							=>	'Generated in %1$s seconds, %2$s queries executed',
'memory_usage'						=>	'Memory usage: %1$s',
'peak_usage'						=>	'(Peak: %1$s)',
'query_times'						=>	'Time (s)',
'query'								=>	'Query',
'total_query_time'					=>	'Total query time: %s',

// For extern.php RSS feed
'rss_description'					=>	'The most recent topics at %s.',
'rss_description_topic'				=>	'The most recent posts in %s.',
'rss_reply'							=>	'Re: ', // The topic subject will be appended to this string (to signify a reply)
'rss_active_topics_feed'			=>	'RSS active topics feed',
'atom_active_topics_feed'			=>	'Atom active topics feed',
'rss_forum_feed'					=>	'RSS forum feed',
'atom_forum_feed'					=>	'Atom forum feed',
'rss_topic_feed'					=>	'RSS topic feed',
'atom_topic_feed'					=>	'Atom topic feed',

// Admin related stuff in the header
'new_reports'						=>	'There are new reports',
'maintenance_mode_enabled'			=>	'Maintenance mode is enabled!',

// Units for file sizes
'size_unit_b'						=>	'%s B',
'size_unit_kib'						=>	'%s KiB',
'size_unit_mib'						=>	'%s MiB',
'size_unit_gib'						=>	'%s GiB',
'size_unit_tib'						=>	'%s TiB',
'size_unit_pib'						=>	'%s PiB',
'size_unit_eib'						=>	'%s EiB',

);
