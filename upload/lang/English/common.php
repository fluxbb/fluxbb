<?php

// Language definitions for frequently used strings
$lang_common = array(

// Text orientation and encoding
'lang_direction'		=>	'ltr',	// ltr (Left-To-Right) or rtl (Right-To-Left)
'lang_identifier'		=>	'en',

// Notices
'Bad request'			=>	'Bad request. The link you followed is incorrect or outdated.',
'No view'				=>	'You do not have permission to view these forums.',
'No permission'			=>	'You do not have permission to access this page.',
'CSRF token mismatch'	=>	'Unable to confirm security token. A likely cause for this is that some time passed between when you first entered the page and when you submitted a form or clicked a link. If that is the case and you would like to continue with your action, please click the Confirm button. Otherwise, you should click the Cancel button to return to where you were.',
'No cookie'				=>	'You appear to have logged in successfully, however a cookie has not been set. Please check your settings and if applicable, enable cookies for this website.',

// Topic/forum indicators
'Closed'				=>	'[Closed]',
'Redirected forum'		=>	'Redirected forum',

// Miscellaneous
'Forum index'			=>	'Forum index',
'Submit'				=>	'Submit',	// "name" of submit buttons
'Cancel'				=>	'Cancel', // "name" of cancel buttons
'Submit title'			=>	'Accesskey: s', // "title" for submit buttons
'Preview'				=>	'Preview',	// submit button to preview message
'Preview title'			=>	'Accesskey: p', // "title" for preview buttons
'Delete'				=>	'Delete',
'Ban message'			=>	'You are banned from this forum.',
'Ban message 2'			=>	'The ban expires at the end of %s.',
'Ban message 3'			=>	'The administrator or moderator that banned you left the following message:',
'Ban message 4'			=>	'Please direct any inquiries to the forum administrator at %s.',
'Unknown'				=>	'Unknown',
'Never'					=>	'Never',
'Today'					=>	'Today',
'Yesterday'				=>	'Yesterday',
'Info'					=>	'Info',		// a common message box header
'Forum message'			=>	'Forum message',
'Maintenance'			=>	'Maintenance',
'Redirecting'			=>	'Redirecting',
'Forwarding info'		=>	'You should automatically be forwarded to a new page in %s %s.',
'second'				=>	'second',	// singular
'seconds'				=>	'seconds',	// plural
'Click redirect'		=>	'Click here if you do not want to wait any longer (or if your browser does not automatically forward you)',
'Invalid e-mail'		=>	'The e-mail address you entered is invalid.',
'Last post'				=>	'Last post',
'By user'				=>	'by&#160;%s',
'by'					=>	'by',
'Posted by'				=>	'Posted by',
'New posts'				=>	'New posts',	// the link that leads to the first new post
'New posts info'		=>	'Go to the first new post in this topic.',	// the popup text for new posts links
'Username'				=>	'Username',
'Password'				=>	'Password',
'E-mail'				=>	'E-mail',
'E-mail address'		=>	'E-mail address',
'Send e-mail'			=>	'Send&#160;e-mail',
'Send forum e-mail'		=>	'Send e-mail via forum',
'Registered'			=>	'Registered',
'Subject'				=>	'Subject',
'Message'				=>	'Message',
'Write message'			=>	'Write message:',
'Topic'					=>	'Topic',
'Topics'				=>	'Topics',
'Forum'					=>	'Forum',
'Posts'					=>	'Posts',
'Replies'				=>	'Replies',
'Author'				=>	'Author',
'Pages'					=>	'Pages:',
'Page'					=>	'Page',
'BBCode'				=>	'BBCode',	// You probably shouldn't change this
'Smilies'				=>	'Smilies',
'Images'				=>	'Images',
'You may use'			=>	'You may use: %s',
'and'					=>	'and',
'Image link'			=>	'image',	// This is displayed (i.e. <image>) instead of images when "Show images" is disabled in the profile
'wrote'					=>	'wrote',	// For [quote]'s (e.g., User wrote:)
'Code'					=>	'Code',		// For [code]'s
'Forum mailer'			=>	'%s Mailer',	// As in "MyForums Mailer" in the signature of outgoing e-mails
'Write message legend'	=>	'Compose your post',
'Required information'	=>	'Required information',
'Required'				=>	'(Required)',
'Required warn'			=>	'<strong>Important!</strong> All fields marked %s must be completed before submitting this form.',
'No upload warn'		=>	'<strong>Important!</strong> You must choose a file to <em>upload</em> before submitting this form.',
'You are here'			=>	'You are here: ',
'Crumb separator'		=>	' &#187;&#160;', // The character or text that separates links in breadcrumbs
'Title separator'		=>	' - ',
'Page separator'		=>	'&#160;', //The character or text that separates page numbers
'Paging separator'		=>	'&#160;', //The character or text that separates page numbers for page navigation generally
'Previous'				=>	'Previous',
'Next'					=>	'Next',
'Cancel redirect'		=>	'Operation cancelled. Redirecting …',
'No confirm redirect'	=>	'No confirmation provided. Operation cancelled. Redirecting …',
'Please confirm'		=>	'Please confirm:',
'Help page'				=>	'Help with: %s',
'Re'					=>	'Re:',
'Forum rules'			=>	'Forum rules',

// CSRF confirmation form
'Confirm'				=>	'Confirm',	// Button
'Confirm action'		=>	'Confirm action',
'Confirm action head'	=>	'Please confirm or cancel your last action',

// Title
'Title'					=>	'Title',
'Member'				=>	'Member',	// Default title
'Moderator'				=>	'Moderator',
'Administrator'			=>	'Administrator',
'Banned'				=>	'Banned',
'Guest'					=>	'Guest',

// Stuff for include/parser.php
'BBCode error 1'		=>	'[/%1$s] was found without a matching [%1$s]',
'BBCode error 2'		=>	'[/%1$s] found after [%2$s], make sure [%2$s] is properly closed',
'BBCode error 3'		=>	'[%1$s] was opened within [%2$s], this is not allowed',
'BBCode error 4'		=>	'[%1$s] was opened within itself, this is not allowed',
'BBCode error 5'		=>	'[%1$s] was found without a matching [/%1$s]',

// Stuff for the navigator (top of every page)
'Navigation'			=>	'Forum navigation',
'Index'					=>	'Index',
'User list'				=>	'User list',
'Rules'					=>  'Rules',
'Search'				=>  'Search',
'Register'				=>  'Register',
'Login'					=>  'Login',
'Not logged in'			=>  'You are not logged in.',
'Profile'				=>	'Profile',
'Logout'				=>	'Logout',
'Logged in as'			=>	'Logged in as %s.',
'Admin'					=>	'Administration',
'Last visit'			=>	'Last visit: %s',
'Mark all as read'		=>	'Mark all topics as read',
'Login nag'				=>	'Please login or register.',
'New reports'			=>	'New reports',
'Attention'				=>	'Attention!',

// Alerts
'Alert notice'			=>	'<strong>ATTENTION!</strong> There are important %s that you should view immediately.',
'Admin alerts'			=>	'Administrator Alerts',
'Maintenance mode'		=>	'Maintenance mode is enabled!',
'Maintenance alert'		=>	'<strong>WARNING!</strong> This board is in maintenance mode. <em>DO NOT</em> logout, if you do you will not be able to login.',
'Updates'				=>	'FluxBB updates:',
'Updates failed'		=>	'The latest attempt at checking for updates against the FluxBB.org updates service failed. This probably just means that the service is temporarily overloaded or out of order. However, if this alert does not disappear within a day or two, you should disable the automatic check for updates and check for updates manually in the future.',
'Updates version n hf'	=>	'A newer version of FluxBB, version %s, is available for download at <a href="http://FluxBB.org/">FluxBB.org</a>. Furthermore, one or more hotfix extensions are available for install on the Extensions tab of the admin interface.',
'Updates version'		=>	'A newer version of FluxBB, version %s, is available for download at <a href="http://FluxBB.org/">FluxBB.org</a>.',
'Updates hf'			=>	'One or more hotfix extensions are available for install on the Extensions tab of the admin interface.',
'Install script'		=>	'Install script uploaded:',
'Install script alert'	=>	'FluxBB\'s installation script (install.php) is currently uploaded to the forum root. Since FluxBB is already installed, the file should be deleted or moved out of the forum root for security reasons.',
'Update script'			=>	'Database update script uploaded:',
'Update script alert'	=>	'FluxBB\'s database update script (db_update.php) is currently uploaded to the forum root. Once it has been used to update the forum, it should be deleted or moved out of the forum root for security reasons.',
'Maint script'			=>	'Maintenance mode disabling script uploaded:',
'Maint script alert'	=>	'FluxBB\'s maintenance mode disabling script (turn_off_maintenance_mode.php) is currently uploaded to the forum root. It should be deleted or moved out of the forum root if it is not being used.',

// Stuff for Search links and jump menu
'New posts info'		=>	'Lists topics that have new posts since your last visit',
'Recent posts'			=>	'Recent posts',
'Unanswered topics'		=>	'Unanswered topics',
'Your posts'			=>	'Your posts',
'Your topics'			=>	'Your topics',
'Your subscriptions'	=>	'Your subscriptions',
'Page info'				=>	'%s [ %s ]',
'Page number'			=>	'Page [ %s of %s ]',
'Paged info'			=>	'%s [ %s to %s of %s ]',
'Jump to'				=>	'Go to selected forum',
'Quick jump legend'		=>	'Forum quick jump menu',
'Go'					=>	'Go',		// submit button in forum jump
'Debug table'			=>	'Debug information',

// For extern.php RSS feed
'ATOM Feed'				=>	'Atom',
'RSS Feed'				=>	'RSS',
'RSS description'		=>	'The most recent topics at %s.',
'RSS description topic'	=>	'The most recent posts in %s.',
'RSS reply'				=>	'Re: ',	// The topic subject will be appended to this string (to signify a reply)

// Accessibility
'Skip to content'		=>	'Skip to forum content',
'New window warn'		=>	'Opens in a new window if javascript is enabled.',
'Back to'				=>	'Back to:',

);
