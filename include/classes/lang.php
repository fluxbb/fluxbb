<?php

require_once PUN_ROOT.'modules/gettext/gettext.php';
require_once PUN_ROOT.'modules/cache/cache.php';

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
	protected $langDir = 'lang/';

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
	 * Set the directory where language packs are located
	 *
	 * @param string $dir
	 * @return void
	 */
	public function setLanguageDirectory($dir)
	{
		// Remove any trailing slashes
		$this->langDir = rtrim($dir, '/');
	}

	/**
	 * Set the default language
	 *
	 * @param array $lang
	 * @return void
	 */
	public function setDefaultLanguage($lang)
	{
		if (file_exists($this->langDir.'/'.$lang))
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
		if (file_exists($this->langDir.'/'.$lang))
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
		$default_filename = $this->langDir.'/'.$this->defaultLang.'/'.$resource.'.mo';
		$filename = $this->langDir.'/'.$this->lang.'/'.$resource.'.mo';

		$cache = Cache::load('file', array('dir' => FORUM_CACHE_DIR), 'varexport');
		// TODO: Handle Cache config globally. How?

		// TODO: Slash allowed? - I'd rather use that than an underscore
		$trans_cache = $cache->get($this->lang.'_'.$resource);
		if ($trans_cache === Cache::NOT_FOUND)
		{
			$trans_cache = Gettext::parse($filename);

			// If this is not the default language, load that, too
			if ($this->defaultLang != $this->lang)
			{
				$def_trans_cache = $cache->get($this->defaultLang.'_'.$resource);
				if ($def_trans_cache === Cache::NOT_FOUND)
				{
					$def_trans_cache = Gettext::parse($default_filename);

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
		return isset($this->translationTable[$str]) ? $this->translationTable[$str][0] : $str;
	}
}
