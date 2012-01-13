<?php

return array(
	'db'		=> array(
		'type'			=> 'mysql',
		'host'			=> 'localhost',
		'dbname'		=> 'fluxbb__2.0',
		'username'		=> 'root',
		'password'		=> '',
		'prefix'		=> 'forum_',
		'p_connect'		=> false,
	),
	'cache'		=> array(
		'type'			=> 'File',
		'dir'			=> PUN_ROOT.'cache/',
		'serializer'	=> 'VarExport',
		'suffix'		=> '.php',
	),
	'cookie'	=> array(
		'name'			=> 'pun_cookie_1',
		'domain'		=> '',
		'path'			=> '/',
		'secure'		=> 0,
		'seed'			=> '123456789abc',
	),
	'base_url'	=> 'http://localhost/fluxbb',
);
