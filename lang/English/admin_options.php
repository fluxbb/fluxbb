<?php

// Language definitions used in admin_options.php
$lang_admin_options = array(

'Bad HTTP Referer message'  		=>	'Bad HTTP_REFERER. If you have moved these forums from one location to another or switched domains, you need to update the Base URL manually in the database (look for o_base_url in the config table) and then clear the cache by deleting all .php files in the /cache directory.',
'Must enter title message'			=>	'You must enter a board title.',
'Invalid e-mail message'			=>	'The admin email address you entered is invalid.',
'Invalid webmaster e-mail message'	=>	'The webmaster email address you entered is invalid.',
'SMTP passwords did not match'		=>	'You need to enter the SMTP password twice exactly the same to change it.',
'Enter announcement here'			=>	'Enter your announcement here.',
'Enter rules here'					=>	'Enter your rules here.',
'Default maintenance message'		=>	'The forums are temporarily down for maintenance. Please try again in a few minutes.',
'Timeout error message'				=>	'The value of "Timeout online" must be smaller than the value of "Timeout visit".',
'Options updated redirect'			=>	'Options updated. Redirecting …',
'Options head'						=>	'Options',

// Essentials section
'Essentials subhead'				=>	'Essentials',
'Board title label'					=>	'Board title',
'Board title help'					=>	'The title of this bulletin board (shown at the top of every page). This field may <strong>not</strong> contain HTML.',
'Board desc label'					=>	'Board description',
'Board desc help'					=>	'A short description of this bulletin board (shown at the top of every page). This field may contain HTML.',
'Base URL label'					=>	'Base URL',
'Base URL help'						=>	'The complete URL of the board without trailing slash (i.e. http://www.mydomain.com/forums). This <strong>must</strong> be correct in order for all admin and moderator features to work. If you get "Bad referer" errors, it\'s probably incorrect.',
'Base URL problem'					=>	'Your installation does not support automatic conversion of internationalized domain names. As your base URL contains special characters, you <strong>must</strong> use an online converter in order to avoid "Bad referer" errors.',
'Timezone label'					=>	'Default time zone',
'Timezone help'						=>	'The default time zone for guests and users attempting to register for the board.',
'DST label'							=>	'Adjust for DST',
'DST help'							=>	'Check if daylight savings is in effect (advances times by 1 hour).',
'Language label'					=>	'Default language',
'Language help'						=>	'The default language for guests and users who haven\'t changed from the default in their profile. If you remove a language pack, this must be updated.',
'Default style label'				=>	'Default style',
'Default style help'				=>	'The default style for guests and users who haven\'t changed from the default in their profile.',

// Essentials section timezone options
'UTC-12:00'							=>	'(UTC-12:00) International Date Line West',
'UTC-11:00'							=>	'(UTC-11:00) Niue, Samoa',
'UTC-10:00'							=>	'(UTC-10:00) Hawaii-Aleutian, Cook Island',
'UTC-09:30'							=>	'(UTC-09:30) Marquesas Islands',
'UTC-09:00'							=>	'(UTC-09:00) Alaska, Gambier Island',
'UTC-08:30'							=>	'(UTC-08:30) Pitcairn Islands',
'UTC-08:00'							=>	'(UTC-08:00) Pacific',
'UTC-07:00'							=>	'(UTC-07:00) Mountain',
'UTC-06:00'							=>	'(UTC-06:00) Central',
'UTC-05:00'							=>	'(UTC-05:00) Eastern',
'UTC-04:00'							=>	'(UTC-04:00) Atlantic',
'UTC-03:30'							=>	'(UTC-03:30) Newfoundland',
'UTC-03:00'							=>	'(UTC-03:00) Amazon, Central Greenland',
'UTC-02:00'							=>	'(UTC-02:00) Mid-Atlantic',
'UTC-01:00'							=>	'(UTC-01:00) Azores, Cape Verde, Eastern Greenland',
'UTC'								=>	'(UTC) Western European, Greenwich',
'UTC+01:00'							=>	'(UTC+01:00) Central European, West African',
'UTC+02:00'							=>	'(UTC+02:00) Eastern European, Central African',
'UTC+03:00'							=>	'(UTC+03:00) Eastern African',
'UTC+03:30'							=>	'(UTC+03:30) Iran',
'UTC+04:00'							=>	'(UTC+04:00) Moscow, Gulf, Samara',
'UTC+04:30'							=>	'(UTC+04:30) Afghanistan',
'UTC+05:00'							=>	'(UTC+05:00) Pakistan',
'UTC+05:30'							=>	'(UTC+05:30) India, Sri Lanka',
'UTC+05:45'							=>	'(UTC+05:45) Nepal',
'UTC+06:00'							=>	'(UTC+06:00) Bangladesh, Bhutan, Yekaterinburg',
'UTC+06:30'							=>	'(UTC+06:30) Cocos Islands, Myanmar',
'UTC+07:00'							=>	'(UTC+07:00) Indochina, Novosibirsk',
'UTC+08:00'							=>	'(UTC+08:00) Greater China, Australian Western, Krasnoyarsk',
'UTC+08:45'							=>	'(UTC+08:45) Southeastern Western Australia',
'UTC+09:00'							=>	'(UTC+09:00) Japan, Korea, Chita, Irkutsk',
'UTC+09:30'							=>	'(UTC+09:30) Australian Central',
'UTC+10:00'							=>	'(UTC+10:00) Australian Eastern',
'UTC+10:30'							=>	'(UTC+10:30) Lord Howe',
'UTC+11:00'							=>	'(UTC+11:00) Solomon Island, Vladivostok',
'UTC+11:30'							=>	'(UTC+11:30) Norfolk Island',
'UTC+12:00'							=>	'(UTC+12:00) New Zealand, Fiji, Magadan',
'UTC+12:45'							=>	'(UTC+12:45) Chatham Islands',
'UTC+13:00'							=>	'(UTC+13:00) Tonga, Phoenix Islands, Kamchatka',
'UTC+14:00'							=>	'(UTC+14:00) Line Islands',

// Timeout Section
'Timeouts subhead'					=>	'Time and timeouts',
'Time format label'					=>	'Time format',
'PHP manual'						=>	'PHP manual',
'Time format help'					=>	'[Current format: %s]. See %s for formatting options.',
'Date format label'					=>	'Date format',
'Date format help'					=>	'[Current format: %s]. See %s for formatting options.',
'Visit timeout label'				=>	'Visit timeout',
'Visit timeout help'				=>	'Number of seconds a user must be idle before his/hers last visit data is updated (primarily affects new message indicators).',
'Online timeout label'				=>	'Online timeout',
'Online timeout help'				=>	'Number of seconds a user must be idle before being removed from the online users list.',
'Redirect time label'				=>	'Redirect time',
'Redirect time help'				=>	'Number of seconds to wait when redirecting. If set to 0, no redirect page will be displayed (not recommended).',

// Display Section
'Display subhead'					=>	'Display',
'Version number label'				=>	'Version number',
'Version number help'				=>	'Show FluxBB version number in footer.',
'Info in posts label'				=>	'User info in posts',
'Info in posts help'				=>	'Show information about the poster under the username in topic view. The information affected is location, register date, post count and the contact links (email and URL).',
'Post count label'					=>	'User post count',
'Post count help'					=>	'Show the number of posts a user has made (affects topic view, profile and user list).',
'Smilies label'						=>	'Smilies in posts',
'Smilies help'						=>	'Convert smilies to small graphic icons.',
'Smilies sigs label'				=>	'Smilies in signatures',
'Smilies sigs help'					=>	'Convert smilies to small graphic icons in user signatures.',
'Clickable links label'				=>	'Make clickable links',
'Clickable links help'				=>	'When enabled, FluxBB will automatically detect any URLs in posts and make them clickable hyperlinks.',
'Topic review label'				=>	'Topic review',
'Topic review help'					=>	'Maximum number of posts to display when posting (newest first). Set to 0 to disable.',
'Topics per page label'				=>	'Topics per page',
'Topics per page help'				=>	'The default number of topics to display per page in a forum. Users can personalize this setting.',
'Posts per page label'				=>	'Posts per page',
'Posts per page help'				=>	'The default number of posts to display per page in a topic. Users can personalize this setting.',
'Indent label'						=>	'Indent size',
'Indent help'						=>	'If set to 8, a regular tab will be used when displaying text within the [code][/code] tag. Otherwise this many spaces will be used to indent the text.',
'Quote depth label'					=>	'Maximum [quote] depth',
'Quote depth help'					=>	'The maximum times a [quote] tag can go inside other [quote] tags, any tags deeper than this will be discarded.',

// Features section
'Features subhead'					=>	'Features',
'Quick post label'					=>	'Quick post',
'Quick post help'					=>	'When enabled, FluxBB will add a quick post form at the bottom of topics. This way users can post directly from the topic view.',
'Users online label'				=>	'Users online',
'Users online help'					=>	'Display info on the index page about guests and registered users currently browsing the board.',
'Censor words label'				=>	'Censor words',
'Censor words help'					=>	'Enable this to censor specific words in the board. See %s for more info.',
'Signatures label'					=>	'Signatures',
'Signatures help'					=>	'Allow users to attach a signature to their posts.',
'User has posted label'				=>	'User has posted earlier',
'User has posted help'				=>	'This feature displays a dot in front of topics in viewforum.php in case the currently logged in user has posted in that topic earlier. Disable if you are experiencing high server load.',
'Topic views label'					=>	'Topic views',
'Topic views help'					=>	'Keep track of the number of views a topic has. Disable if you are experiencing high server load in a busy forum.',
'Quick jump label'					=>	'Quick jump',
'Quick jump help'					=>	'Enable the quick jump (jump to forum) drop list.',
'GZip label'						=>	'GZip output',
'GZip help'							=>	'If enabled, FluxBB will gzip the output sent to browsers. This will reduce bandwidth usage, but use a little more CPU. This feature requires that PHP is configured with zlib (--with-zlib). Note: If you already have one of the Apache modules mod_gzip or mod_deflate set up to compress PHP scripts, you should disable this feature.',
'Search all label'					=>	'Search all forums',
'Search all help'					=>	'When disabled, searches will only be allowed in one forum at a time. Disable if server load is high due to excessive searching.',
'Menu items label'					=>	'Additional menu items',
'Menu items help'					=>	'By entering HTML hyperlinks into this textbox, any number of items can be added to the navigation menu at the top of all pages. The format for adding new links is X = &lt;a href="URL"&gt;LINK&lt;/a&gt; where X is the position at which the link should be inserted (e.g. 0 to insert at the beginning and 2 to insert after "User list"). Separate entries with a linebreak.',

// Feeds section
'Feed subhead'						=>	'Syndication',
'Default feed label'				=>	'Default feed type',
'Default feed help'					=>	'Select the type of syndication feed to display. Note: Choosing none will not disable feeds, only hide them by default.',
'None'								=>	'None',
'RSS'								=>	'RSS',
'Atom'								=>	'Atom',
'Feed TTL label'					=>	'Duration to cache feeds',
'Feed TTL help'						=>	'Feeds can be cached to lower the resource usage of feeds.',
'No cache'							=>	'Don\'t cache',
'Minutes'							=>	'%d minutes',

// Reports section
'Reports subhead'					=>	'Reports',
'Reporting method label'			=>	'Reporting method',
'Internal'							=>	'Internal',
'By e-mail'							=>	'Email',
'Both'								=>	'Both',
'Reporting method help'				=>	'Select the method for handling topic/post reports. You can choose whether topic/post reports should be handled by the internal report system, emailed to the addresses on the mailing list (see below) or both.',
'Mailing list label'				=>	'Mailing list',
'Mailing list help'					=>	'A comma separated list of subscribers. The people on this list are the recipients of reports.',

// Avatars section
'Avatars subhead'					=>	'Avatars',
'Use avatars label'					=>	'Use avatars',
'Use avatars help'					=>	'When enabled, users will be able to upload an avatar which will be displayed under their title.',
'Upload directory label'			=>	'Upload directory',
'Upload directory help'				=>	'The upload directory for avatars (relative to the FluxBB root directory). PHP must have write permissions to this directory.',
'Max width label'					=>	'Max width',
'Max width help'					=>	'The maximum allowed width of avatars in pixels (60 is recommended).',
'Max height label'					=>	'Max height',
'Max height help'					=>	'The maximum allowed height of avatars in pixels (60 is recommended).',
'Max size label'					=>	'Max size',
'Max size help'						=>	'The maximum allowed size of avatars in bytes (10240 is recommended).',

// E-mail section
'E-mail subhead'					=>	'Email',
'Admin e-mail label'				=>	'Admin email',
'Admin e-mail help'					=>	'The email address of the board administrator.',
'Webmaster e-mail label'			=>	'Webmaster email',
'Webmaster e-mail help'				=>	'This is the address that all emails sent by the board will be addressed from.',
'Forum subscriptions label'			=>	'Forum subscriptions',
'Forum subscriptions help'			=>	'Enable users to subscribe to forums (receive email when someone creates a new topic).',
'Topic subscriptions label'			=>	'Topic subscriptions',
'Topic subscriptions help'			=>	'Enable users to subscribe to topics (receive email when someone replies).',
'SMTP address label'				=>	'SMTP server address',
'SMTP address help'					=>	'The address of an external SMTP server to send emails with. You can specify a custom port number if the SMTP server doesn\'t run on the default port 25 (example: mail.myhost.com:3580). Leave blank to use the local mail program.',
'SMTP username label'				=>	'SMTP username',
'SMTP username help'				=>	'Username for SMTP server. Only enter a username if it is required by the SMTP server (most servers <strong>do not</strong> require authentication).',
'SMTP password label'				=>	'SMTP password',
'SMTP change password help'			=>	'Check this if you want to change or delete the currently stored password.',
'SMTP password help'				=>	'Password for SMTP server. Only enter a password if it is required by the SMTP server (most servers <strong>do not</strong> require authentication). Please enter your password twice to confirm.',
'SMTP SSL label'					=>	'Encrypt SMTP using SSL',
'SMTP SSL help'						=>	'Encrypts the connection to the SMTP server using SSL. Should only be used if your SMTP server requires it and your version of PHP supports SSL.',

// Registration Section
'Registration subhead'				=>	'Registration',
'Allow new label'					=>	'Allow new registrations',
'Allow new help'					=>	'Controls whether this board accepts new registrations. Disable only under special circumstances.',
'Verify label'						=>	'Verify registrations',
'Verify help'						=>	'When enabled, users are emailed a random password when they register. They can then log in and change the password in their profile if they see fit. This feature also requires users to verify new email addresses if they choose to change from the one they registered with. This is an effective way of avoiding registration abuse and making sure that all users have "correct" email addresses in their profiles.',
'Report new label'					=>	'Report new registrations',
'Report new help'					=>	'If enabled, FluxBB will notify users on the mailing list (see above) when a new user registers in the forums.',
'Use rules label'					=>	'User forum rules',
'Use rules help'					=>	'When enabled, users must agree to a set of rules when registering (enter text below). The rules will always be available through a link in the navigation table at the top of every page.',
'Rules label'						=>	'Enter your rules here',
'Rules help'						=>	'Here you can enter any rules or other information that the user must review and accept when registering. If you enabled rules above you have to enter something here, otherwise it will be disabled. This text will not be parsed like regular posts and thus may contain HTML.',
'E-mail default label'				=>	'Default email setting',
'E-mail default help'				=>	'Choose the default privacy setting for new user registrations.',
'Display e-mail label'				=>	'Display email address to other users.',
'Hide allow form label'				=>	'Hide email address but allow form e-mail.',
'Hide both label'					=>	'Hide email address and disallow form email.',

// Announcement Section
'Announcement subhead'				=>	'Announcements',
'Display announcement label'		=>	'Display announcement',
'Display announcement help'			=>	'Enable this to display the below message in the board.',
'Announcement message label'		=>	'Announcement message',
'Announcement message help'			=>	'This text will not be parsed like regular posts and thus may contain HTML.',

// Maintenance Section
'Maintenance subhead'				=>	'Maintenance',
'Maintenance mode label'			=>	'Maintenance mode',
'Maintenance mode help'				=>	'When enabled, the board will only be available to administrators. This should be used if the board needs to be taken down temporarily for maintenance. <strong>WARNING! Do not log out when the board is in maintenance mode.</strong> You will not be able to login again.',
'Maintenance message label'			=>	'Maintenance message',
'Maintenance message help'			=>	'The message that will be displayed to users when the board is in maintenance mode. If left blank, a default message will be used. This text will not be parsed like regular posts and thus may contain HTML.',

);
