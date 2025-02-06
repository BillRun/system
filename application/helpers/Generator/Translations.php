<?php

/**
 * @category   Application
 * @package    Helpers
 * @subpackage Generator
 * @copyright  Copyright (C) 2023 BillRun Technologies LTD. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

/**
 * Translations generator
 *
 * @package    Generator
 * @subpackage Translations
 * @since      5.0
 */
class Generator_Translations {

	protected static $defaultLangPath = 'billrun.invoices.language.default';
	protected static $defaultLang;
	protected static $currentLang;
	protected static $languages = [];
	protected static $translations = [];

	public static function load() {
		if (!static::$defaultLang) {
			$defaultLang = Billrun_Factory::config()->getConfigValue(static::$defaultLangPath, 'en_GB' );
			static::$defaultLang = $defaultLang;
			static::setLanguage($defaultLang);
		}
	}

	public static function setLanguage($lang = null) {
		if (is_null($lang)) {
			$lang = static::getDefaultLanguage();
		}
		static::$currentLang = $lang;
		$translationsLocations = array(
			'/conf/translations/' . $lang . '.ini',
			'/conf/translations/overrides/' . $lang . '.ini',
			'/conf/translations/tenants/' . Billrun_Factory::config()->getTenant() . '/' . $lang . '.ini',
		);

		if (!static::$languages[$lang]++) {
			static::setTranslation($lang, $translationsLocations);
		}
	}

	protected static function setTranslation($lang, $paths) {
		$tr = [];
		foreach ($paths as $path) {
			if (!file_exists(APPLICATION_PATH . $path)) {
				continue;
			}
			Billrun_Factory::log("Loading translation file " . $path . " of lang " . $lang);
			$slugs = parse_ini_file(APPLICATION_PATH . $path);
			$tr = array_merge($tr, $slugs ?: []);
		}
		static::$translations[$lang] = $tr;
	}

	public static function getDefaultLanguage() {
		return Billrun_Factory::config()->getConfigValue(static::$defaultLangPath, 'en_GB');
	}

	public static function translate($slug, $args = []) {
		echo( static::stranslate($slug, $args) );
	}

	public static function stranslate($slug, $args = [], $slugFallback = null) {
		if (!is_array($args)) {
			$args = [$args];
		}
		$currentLangTranslation = @static::$translations[static::$currentLang][$slug];
		$defaultLangTranslation = @static::$translations[static::getDefaultLanguage()][$slug];
		$translation = $currentLangTranslation ?: $defaultLangTranslation ?: ($slugFallback ?: $slug);
		return call_user_func_array('sprintf',array_merge([$translation], $args));
	}

}
