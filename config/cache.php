<?php

return array(

	'driver'		=> 'file',
	'path'			=> __DIR__.'/../cache',

	'connection'	=> null,
	'table'			=> 'cache',

	'memcached'		=> array(
		array('host' => '127.0.0.1', 'port' => 11211, 'weight' => 100),
	),

	'prefix'		=> 'fluxbb',

);
