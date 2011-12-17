<?php

require_once PUN_ROOT.'modules/gettext/Gettext.php';
require_once PUN_ROOT.'modules/cache/src/Cache/Cache.php';

class Flux_Lang
{
	/**
	 * An array of translated strings
	 *
	 * @var array
	 */
	protected $translationTable = array();

	/**
	 * The directory where language packs are located
	 *
	 * @var string
	 */
	protected static $langDir = 'lang';

	/**
	 * The default language
	 *
	 * @var string
	 */
	protected $defaultLang = 'en';

	/**
	 * The language to use
	 *
	 * @var string
	 */
	protected $lang = 'en';

	/**
	 * The resources that have been loaded so far
	 *
	 * @var array
	 */
	protected $loadedResources = array();

	/**
	 * Get a list of all available languages
	 *
	 * @return array
	 */
	public static function getLanguageList()
	{
		$return = array();
		foreach (glob(self::$langDir.'/*', GLOB_ONLYDIR) as $dir)
		{
			$dirs = explode('/', $dir);
			$return[] = end($dirs);
		}

		// TODO: Do we need sorting here?
		natcasesort($return);
		return $return;
	}

	/**
	 * Check whether the given language exists
	 *
	 * @param string $lang
	 * @return bool
	 */
	public static function languageExists($lang)
	{
		return in_array($lang, self::getLanguageList());
	}

	/**
	 * Set the directory where language packs are located
	 *
	 * @param string $dir
	 * @return void
	 */
	public static function setLanguageDirectory($dir)
	{
		// Remove any trailing slashes
		self::$langDir = rtrim($dir, '/');
	}

	/**
	 * Set the default language
	 *
	 * @param array $lang
	 * @return void
	 */
	public function setDefaultLanguage($lang)
	{
		if (file_exists(self::$langDir.'/'.$lang))
			$this->defaultLang = $lang;
		else
			throw new Exception('It seems like default language pack "'.$lang.'" does not exist.');
	}

	/**
	 * Set the language to use
	 *
	 * @param array $lang
	 * @return void
	 */
	public function setLanguage($lang)
	{
		if (file_exists(self::$langDir.'/'.$lang))
			$this->lang = $lang;
		else
			throw new Exception('It seems like language pack "'.$lang.'" does not exist.');
	}

	/**
	 * Load a new file and add its translations to our elements
	 *
	 * Incomplete language packs will fall back to the default language
	 * for missing strings.
	 *
	 * This only loads Gettext files if cached files cannot be found.
	 *
	 * @param array $resource
	 * @return void
	 */
	public function load($resource)
	{
		global $cache;

		// Don't load twice
		if (in_array($resource, $this->loadedResources))
			return;

		$this->loadedResources[] = $resource;

		$default_filename = self::$langDir.'/'.$this->defaultLang.'/'.$resource.'.po';
		$filename = self::$langDir.'/'.$this->lang.'/'.$resource.'.po';

		// TODO: Slash allowed? - I'd rather use that than an underscore
		$trans_cache = $cache->get($this->lang.'_'.$resource);
		if ($trans_cache === Flux_Cache::NOT_FOUND)
		{
			$trans_cache = Flux_Gettext::parse($filename);

			// If this is not the default language, load that, too
			if ($this->defaultLang != $this->lang)
			{
				$def_trans_cache = $cache->get($this->defaultLang.'_'.$resource);
				if ($def_trans_cache === Flux_Cache::NOT_FOUND)
				{
					$def_trans_cache = Flux_Gettext::parse($default_filename);

					$cache->set($this->defaultLang.'_'.$resource, $def_trans_cache);
				}

				// TODO: How could we automatically regenerate these cache files when necessary? (E.g. imagine the default language files being replaced during a new release, the custom translations haven't caught up yet. How to handle that? etc.)

				// Now use default language values as fallback
				$trans_cache = array_merge($def_trans_cache, $trans_cache);
			}

			$cache->set($this->lang.'_'.$resource, $trans_cache);
		}

		// Store the loaded values for usage
		$this->translationTable = array_merge($this->translationTable, $trans_cache);
	}

	/**
	 * Return the translation of the given string
	 *
	 * @param string $str
	 * @return string
	 */
	public function t($str)
	{
		if (isset($this->translationTable[$str]))
		{
			$args = func_get_args();
			$args[0] = $this->translationTable[$args[0]];
			return call_user_func_array('sprintf', $args);
		}

		return $str;
	}
}
