<?php

/**
* This is the dynamic loader for the library. It checks whether you have
* the mbstring extension available and includes relevant files
* on that basis, falling back to the native (as in written in PHP) version
* if mbstring is unavailabe.
*
* It's probably easiest to use this, if you don't want to understand
* the dependencies involved, in conjunction with PHP versions etc. At
* the same time, you might get better performance by managing loading
* yourself. The smartest way to do this, bearing in mind performance,
* is probably to "load on demand" - i.e. just before you use these
* functions in your code, load the version you need.
*
* It makes sure the the following functions are available;
* utf8_strlen, utf8_strpos, utf8_strrpos, utf8_substr,
* utf8_strtolower, utf8_strtoupper
* Other functions in the ./native directory depend on these
* six functions being available
* @package utf8
*/

// Check whether PCRE has been compiled with UTF-8 support
$UTF8_ar = array();
if (preg_match('/^.{1}$/u', "ñ", $UTF8_ar) != 1)
	trigger_error('PCRE is not compiled with UTF-8 support', E_USER_ERROR);

unset($UTF8_ar);

// Put the current directory in this constant
if (!defined('UTF8'))
	define('UTF8', dirname(__FILE__));

if (extension_loaded('mbstring') && !defined('UTF8_USE_MBSTRING') && !defined('UTF8_USE_NATIVE'))
	define('UTF8_USE_MBSTRING', true);
else if (!defined('UTF8_USE_NATIVE'))
	define('UTF8_USE_NATIVE', true);

// utf8_strpos() and utf8_strrpos() need utf8_bad_strip() to strip invalid
// characters. Mbstring doesn't do this while the Native implementation does.
require UTF8.'/utils/bad.php';

if (defined('UTF8_USE_MBSTRING'))
{
	/**
	* If string overloading is active, it will break many of the
	* native implementations. mbstring.func_overload must be set
	* to 0, 1 or 4 in php.ini (string overloading disabled).
	* Also need to check we have the correct internal mbstring
	* encoding
	*/
	if (ini_get('mbstring.func_overload') & MB_OVERLOAD_STRING)
		trigger_error('String functions are overloaded by mbstring', E_USER_ERROR);

	mb_language('uni');
	mb_internal_encoding('UTF-8');

	if (!defined('UTF8_CORE'))
		require UTF8.'/mbstring/core.php';
}
elseif (defined('UTF8_USE_NATIVE'))
{
	if (!defined('UTF8_CORE'))
	{
		require UTF8.'/utils/unicode.php';
		require UTF8.'/native/core.php';
	}
}

// Load the native implementation of utf8_trim
require UTF8.'/trim.php';
