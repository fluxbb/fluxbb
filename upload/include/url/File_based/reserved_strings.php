<?php
/**
 * Loads the reserved strings used to transform problematic strings in URLs.
 * These are matched against the whole string after all other transformations.
 *
 * @copyright Copyright (C) 2008 FluxBB.org, based on code copyright (C) 2002-2008 PunBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package FluxBB
 */


$forum_reserved_strings = array(
	''		=>	'view',
	'rss'		=>	'rss-view',
	'atom'		=>	'atom-view',
	
	'newpost'	=>	'newpost-view',
	'newposts'	=>	'newposts-view',
	'new-post'	=>	'new-post-view',
	'new-posts'	=>	'new-posts-view',
	
	'lastpost'	=>	'lastpost-view',
	'lastposts'	=>	'lastposts-view',
	'last-post'	=>	'last-post-view',
	'last-posts'	=>	'last-posts-view',
);
