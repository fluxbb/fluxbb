<?php

// Language definitions used in all admin files
$lang_admin_reindex = array(

'Reindex heading'			=>	'Rebuild search index to restore search performance',
'Rebuild index legend'		=>	'Rebuild search index',
'Reindex info'				=>	'If you have added, edited or removed posts manually in the database or if you are having problems searching, you should rebuild the search index. For best performance you should put the forum in maintenance mode during rebuilding. Once the process has completed you will be redirected back to this page. It is highly recommended that you have JavaScript enabled in your browser during rebuilding (for automatic redirect when a cycle has completed).',
'Reindex warning'			=>	'<strong>IMPORTANT!</strong> Rebuilding the search index can take a long time and will increase server load during the rebuild process. If you are forced to abort the rebuild process, make a note of the last processed post ID and enter that ID+1 in "Starting post ID" when/if you want to continue.',
'Empty index warning'		=>	'<strong>WARNING!</strong> If you want to resume an aborted rebuild, do not select "empty index".',
'Posts per cycle'			=>	'Posts per cycle',
'Posts per cycle info'		=>	'The number of posts to process per pageview. E.g. if you were to enter 100, one hundred posts would be processed and then the page would refresh. This is to prevent the script from timing out during the rebuild process.',
'Starting post'				=>	'Starting Post ID',
'Starting post info'		=>	'The post ID to start rebuilding at. The default value is the first available ID in the database. Normally you would not want to change this.',
'Empty index'				=>	'Empty index',
'Empty index info'			=>	'Empty search index before rebuilding (see below).',
'Rebuilding index title'	=>	'Rebuilding search index &#8230;',
'Rebuilding index'			=>	'Rebuilding index &#8230; This might be a good time to put on some coffee :-)',
'Processing post'			=>	'Processing post <strong>%s</strong> in topic <strong>%s</strong>.',
'Javascript redirect'		=>	'JavaScript redirect unsuccessful.',
'Click to continue'			=>	'Click here to continue',
'Rebuild index'				=>	'Rebuild index',

);