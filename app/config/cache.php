<?php

return array(

	/*
	|--------------------------------------------------------------------------
	| Default Cache Driver
	|--------------------------------------------------------------------------
	|
	| This option controls the default cache "driver" that will be used when
	| using the Caching library. Of course, you may use other drivers any
	| time you wish. This is the default when another is not specified.
	|
	| Supported: "file", "apc", "memcached", "redis", "memory"
	|
	*/

	'driver' => 'file',

	/*
	|--------------------------------------------------------------------------
	| File Cache Location
	|--------------------------------------------------------------------------
	|
	| When using the "file" cache driver, we need a location where the cache
	| files may be stored. A sensible default has been specified, but you
	| are free to change it to any other place on disk that you desire.
	|
	*/

	'path' => __DIR__.'/../storage/cache',

	/*
	|--------------------------------------------------------------------------
	| Memcached Servers
	|--------------------------------------------------------------------------
	|
	| Now you may specify an array of your Memcached servers that should be
	| used when utilizing the Memcached cache driver. All of the servers
	| should contain a value for "host", "port", and "weight" options.
	|
	*/

	'memcached' => array(

		array('host' => '127.0.0.1', 'port' => 11211, 'weight' => 100),

	),

	/*
	|--------------------------------------------------------------------------
	| Cache Key Prefix
	|--------------------------------------------------------------------------
	|
	| When utilizing a RAM based store such as APC or Memcached, there might
	| be other applications utilizing the asme cache. So, we'll specify a
	| value to get prefixed to all our keys so we can avoid collisions.
	|
	*/

	'prefix' => 'laravel',

);
