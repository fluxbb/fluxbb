<?php

require_once PUN_ROOT.'modules/gettext/src/Gettext.php';

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
	protected static $defaultLang = 'en';

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
	 * Constructor
	 *
	 * @param string $lang
	 */
	public function __construct($lang)
	{
		$this->setLanguage($lang);
	}

	/**
	 * Get a list of all available languages
	 *
	 * @return array
	 */
	public static function getLanguageList()
	{
		static $list = null;

		if (!isset($list))
		{
			$list = array();
			foreach (glob(PUN_ROOT.self::$langDir.'/*', GLOB_ONLYDIR) as $dir)
			{
				$dirs = explode('/', $dir);
				$list[] = end($dirs);
			}
		}

		return $list;
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
	 * Set the default language
	 *
	 * @param array $lang
	 * @return void
	 */
	public static function setDefaultLanguage($lang)
	{
		if (self::languageExists($lang))
			self::$defaultLang = $lang;
		else
			throw new Exception('It seems like default language pack "'.htmlspecialchars($lang).'" does not exist.');
	}

	/**
	 * Set the language to use
	 *
	 * @param array $lang
	 * @return void
	 */
	protected function setLanguage($lang)
	{
		if (self::languageExists($lang))
			$this->lang = $lang;
		else
			throw new Exception('It seems like language pack "'.htmlspecialchars($lang).'" does not exist.');
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

		$default_filename = PUN_ROOT.self::$langDir.'/'.self::$defaultLang.'/'.$resource.'.po';
		$filename = PUN_ROOT.self::$langDir.'/'.$this->lang.'/'.$resource.'.po';

		// TODO: Slash allowed? - I'd rather use that than an underscore
		$trans_cache = $cache->get($this->lang.'_'.$resource);
		if ($trans_cache === \fluxbb\cache\Cache::NOT_FOUND)
		{
			$trans_cache = \fluxbb\gettext\parse($filename);

			// If this is not the default language, load that, too
			if (self::$defaultLang != $this->lang)
			{
				$def_trans_cache = $cache->get(self::$defaultLang.'_'.$resource);
				if ($def_trans_cache === \fluxbb\cache\Cache::NOT_FOUND)
				{
					$def_trans_cache = \fluxbb\gettext\parse($default_filename);

					$cache->set(self::$defaultLang.'_'.$resource, $def_trans_cache);
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
