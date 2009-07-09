<?php

/*
// Determine what locale to use
switch (PHP_OS)
{
	case 'WINNT':
	case 'WIN32':
		$locale = 'english';
		break;

	case 'FreeBSD':
	case 'NetBSD':
	case 'OpenBSD':
		$locale = 'en_US.US-ASCII';
		break;

	default:
		$locale = 'en_US';
		break;
}

// Attempt to set the locale
setlocale(LC_CTYPE, $locale);
*/

// Language definitions for frequently used strings
$lang_common = array(

// Text orientation and encoding
'lang_direction'		=>	'ltr',	// ltr (Left-To-Right) or rtl (Right-To-Left)

// Number formatting
'lang_decimal_point'				=>	'.',
'lang_thousands_sep'				=>	',',

// Notices
'Bad request'			=>	'Bad request. The link you followed is incorrect or outdated.',
'No view'				=>	'You do not have permission to view these forums.',
'No permission'			=>	'You do not have permission to access this page.',
'Bad referrer'			=>	'Bad HTTP_REFERER. You were referred to this page from an unauthorized source. If the problem persists please make sure that \'Base URL\' is correctly set in Admin/Options and that you are visiting the forum by navigating to that URL. More information regarding the referrer check can be found in the FluxBB documentation.',

// Topic/forum indicators
'New icon'				=>	'There are new posts',
'Normal icon'			=>	'<!-- -->',
'Closed icon'			=>	'This topic is closed',
'Redirect icon'			=>	'Redirected forum',

// Miscellaneous
'Announcement'			=>	'Announcement',
'Options'				=>	'Options',
'Actions'				=>	'Actions',
'Submit'				=>	'Submit',	// "name" of submit buttons
'Ban message'			=>	'You are banned from this forum.',
'Ban message 2'			=>	'The ban expires at the end of',
'Ban message 3'			=>	'The administrator or moderator that banned you left the following message:',
'Ban message 4'			=>	'Please direct any inquiries to the forum administrator at',
'Never'					=>	'Never',
'Today'					=>	'Today',
'Yesterday'				=>	'Yesterday',
'Info'					=>	'Info',		// a common table header
'Go back'				=>	'Go back',
'Maintenance'			=>	'Maintenance',
'Redirecting'			=>	'Redirecting',
'Click redirect'		=>	'Click here if you do not want to wait any longer (or if your browser does not automatically forward you)',
'on'					=>	'on',		// as in "BBCode is on"
'off'					=>	'off',
'Invalid e-mail'		=>	'The e-mail address you entered is invalid.',
'required field'		=>	'is a required field in this form.',	// for javascript form validation
'Last post'				=>	'Last post',
'by'					=>	'by',	// as in last post by someuser
'New posts'				=>	'New&nbsp;posts',	// the link that leads to the first new post (use &nbsp; for spaces)
'New posts info'		=>	'Go to the first new post in this topic.',	// the popup text for new posts links
'Username'				=>	'Username',
'Password'				=>	'Password',
'E-mail'				=>	'E-mail',
'Send e-mail'			=>	'Send e-mail',
'Moderated by'			=>	'Moderated by',
'Registered'			=>	'Registered',
'Subject'				=>	'Subject',
'Message'				=>	'Message',
'Topic'					=>	'Topic',
'Forum'					=>	'Forum',
'Posts'					=>	'Posts',
'Replies'				=>	'Replies',
'Author'				=>	'Author',
'Pages'					=>	'Pages',
'BBCode'				=>	'BBCode',	// You probably shouldn't change this
'img tag'				=>	'[img] tag',
'Smilies'				=>	'Smilies',
'and'					=>	'and',
'Image link'			=>	'image',	// This is displayed (i.e. <image>) instead of images when "Show images" is disabled in the profile
'wrote'					=>	'wrote',	// For [quote]'s
'Code'					=>	'Code',		// For [code]'s
'Mailer'				=>	'Mailer',	// As in "MyForums Mailer" in the signature of outgoing e-mails
'Important information'	=>	'Important information',
'Write message legend'	=>	'Write your message and submit',

// Title
'Title'					=>	'Title',
'Member'				=>	'Member',	// Default title
'Moderator'				=>	'Moderator',
'Administrator'			=>	'Administrator',
'Banned'				=>	'Banned',
'Guest'					=>	'Guest',

// Stuff for include/parser.php
'BBCode error 1'         =>    '[/%1$s] was found without a matching [%1$s]',
'BBCode error 2'         =>    '[%s] tag is empty',
'BBCode error 3'         =>    '[%1$s] was opened within [%2$s], this is not allowed',
'BBCode error 4'         =>    '[%s] was opened within itself, this is not allowed',
'BBCode error 5'         =>    '[%1$s] was found without a matching [/%1$s]',
'BBCode error 6'         =>    '[%s] tag had an empty attribute section',
'BBCode nested list'     =>    '[list] tags cannot be nested',
'BBCode code problem'    =>    'There is a problem with your [code] tags',

// Stuff for the navigator (top of every page)
'Index'					=>	'Index',
'User list'				=>	'User list',
'Rules'					=>  'Rules',
'Search'				=>  'Search',
'Register'				=>  'Register',
'Login'					=>  'Login',
'Not logged in'			=>  'You are not logged in.',
'Profile'				=>	'Profile',
'Logout'				=>	'Logout',
'Logged in as'			=>	'Logged in as',
'Admin'					=>	'Administration',
'Last visit'			=>	'Last visit',
'Show new posts'		=>	'Show new posts since last visit',
'Mark all as read'		=>	'Mark all topics as read',
'Mark forum read'		=>	'Mark this forum as read',
'Link separator'		=>	'',	// The text that separates links in the navigator

// Stuff for the page footer
'Board footer'			=>	'Board footer',
'Search links'			=>	'Search links',
'Show recent posts'		=>	'Show recent posts',
'Show unanswered posts'	=>	'Show unanswered posts',
'Show your posts'		=>	'Show your posts',
'Show subscriptions'	=>	'Show your subscribed topics',
'Jump to'				=>	'Jump to',
'Go'					=>	' Go ',		// submit button in forum jump
'Moderate topic'		=>	'Moderate topic',
'Move topic'			=>  'Move topic',
'Open topic'			=>  'Open topic',
'Close topic'			=>  'Close topic',
'Unstick topic'			=>  'Unstick topic',
'Stick topic'			=>  'Stick topic',
'Moderate forum'		=>	'Moderate forum',
'Delete posts'			=>	'Delete multiple posts', // Deprecated
'Powered by'			=> 'Powered by %s',

// Debug information
'Debug table'			=>	'Debug information',
'Querytime'			=>    'Generated in %1$s seconds, %2$s queries executed',
'Query times'			=>    'Time (s)',
'Query'				=>    'Query',
'Total query time'			=>    'Total query time: %s',

// Email related notifications
'New user notification'					=>	'Alert - New registration',
'New user message'					=>	'User \'%s\' registered in the forums at %s',
'Banned email notification'				=>	'Alert - Banned e-mail detected',
'Banned email register message'				=>	'User \'%s\' registered with banned e-mail address: %s',
'Banned email change message'				=>	'User \'%s\' changed to banned e-mail address: %s',
'Duplicate email notification'				=>	'Alert - Duplicate e-mail detected',
'Duplicate email register message'			=>	'User \'%s\' registered with an e-mail address that also belongs to: %s',
'Duplicate email change message'			=>	'User \'%s\' changed to an e-mail address that also belongs to: %s',
'Report notification'					=>	'Report(%d) - \'%s\'',
'Report message 1'					=>	'User \'%s\' has reported the following message: %s',
'Report message 2'					=>	'Reason: %s',

'User profile'						=>	'User profile: %s',
'Email signature'					=>	'Forum Mailer'."\n".'(Do not reply to this message)',

// For extern.php RSS feed
'RSS description'					=>	'The most recent topics at %s.',
'RSS description topic'				=>	'The most recent posts in %s.',
'RSS reply'							=>	'Re: '	// The topic subject will be appended to this string (to signify a reply)

);
