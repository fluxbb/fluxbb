<?php

return array(

	'driver'		=> 'cookie',

	'lifetime'		=> 120,
	'lottery'		=> array(2, 100),

	'path'			=> __DIR__.'/../storage/sessions',

	'connection'	=> null,
	'table'			=> 'sessions',

);