<?php

// Language definitions used in admin_bans.php
$lang_admin_bans = array(

'No user message'				=>	'No user by that username registered. If you want to add a ban not tied to a specific username just leave the username blank.',
'User is admin message'			=>	'The user %s is an administrator and can\'t be banned. If you want to ban an administrator, you must first demote him/her to moderator or user.',
'Must enter message'			=>	'You must enter either a username, an IP address or an email address (at least).',
'Cannot ban guest message'		=>	'The guest user cannot be banned.',
'Invalid IP message'			=>	'You entered an invalid IP/IP-range.',
'Invalid e-mail message'		=>	'The email address (e.g. user@domain.com) or partial email address domain (e.g. domain.com) you entered is invalid.',
'Invalid date message'			=>	'You entered an invalid expire date. The format should be YYYY-MM-DD and the date must be at least one day in the future.',
'Ban added redirect'			=>	'Ban added. Redirecting …' ,
'Ban edited redirect'			=>	'Ban edited. Redirecting …',
'Ban removed redirect'			=>	'Ban removed. Redirecting …',

'New ban head'					=>	'New ban',
'Add ban subhead'				=>	'Add ban',
'Username label'				=>	'Username',
'Username help'					=>	'The username to ban (case-insensitive). The next page will let you enter a custom IP and email. If you just want to ban a specific IP/IP-range or email just leave it blank.',

'Existing bans head'			=>	'Existing bans',
'Existing ban subhead'			=>	'Ban expires: %s',
'E-mail label'					=>	'Email',
'E-mail help'					=>	'The email or email domain you wish to ban (e.g. someone@somewhere.com or somewhere.com). See "Allow banned email addresses" in Permissions for more info.',
'IP label'						=>	'IP address/IP-ranges',
'IP help'						=>	'The IP address or IP-ranges you wish to ban (e.g. 150.11.110.1 or 150.11.110). Separate addresses with spaces. If an IP is entered already it is the last known IP of this user in the database.',
'IP help link'					=>	'Click %s to see IP statistics for this user.',
'Reason label'					=>	'Reason',
'Banned by label'				=>	'Banned by',
'Ban advanced head'				=>	'Ban advanced settings',
'Ban advanced subhead'			=>	'Supplement ban with IP and email',
'Ban message label'				=>	'Ban message',
'Ban message help'				=>	'A message that will be displayed to the banned user when he/she visits the board.',
'Message expiry subhead'		=>	'Ban message and expiry',
'Ban IP range info'				=>	'You should be very careful when banning an IP-range because of the possibility of multiple users matching the same partial IP.',
'Expire date label'				=>	'Expire date',
'Expire date help'				=>	'The date when this ban should be automatically removed (format: yyyy-mm-dd). Leave blank to remove manually.',
'Ban expires'					=>	'Ban expires',
'No bans in list'				=>	'No bans in list.',

);