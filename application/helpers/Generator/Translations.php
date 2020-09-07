<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Generator_Translations {
	
	protected static $defaultLangPath = 'billrun.invoices.language.default';
	protected static $defaultLang;
	protected static $currentLang;
	protected static $languages = [];
	protected static $translations = [];
	
	public static function load() {
		if (!static::$defaultLang) {
			$defaultLang = Billrun_Factory::config()->getConfigValue(static::$defaultLangPath, 'en_GB');
				static::$defaultLang = $defaultLang;
				static::setLanguage($defaultLang);
		}
	}

    public static function setLanguage($lang = null) {
        if (is_null($lang)) {
            static::setLanguage(static::getDefaultLanguage());
        } else {
            if (!static::$languages[$lang]++) {
                static::setTranslation($lang, ['/conf/translations/' . $lang . '.ini', '/conf/translations/overrides/' . $lang . '.ini']);
            }
            static::$currentLang = $lang;
        }
    }

    protected static function setTranslation($lang, $paths) {
		$tr = [];
		foreach ($paths as $path) {
			$slugs = parse_ini_file(APPLICATION_PATH . $path);
			$tr = array_merge($tr, $slugs ?: []);
		}
		static::$translations[$lang] = $tr;
	}
	
	public static function getDefaultLanguage() {
		return Billrun_Factory::config()->getConfigValue(static::$defaultLangPath, 'en_GB');
	}
	
	public static function translate($slug, $args = []) {
		if (!is_array($args)) {
			$args = [$args];
		}
		$currentLangTranslation = static::$translations[static::$currentLang][$slug];
		$defaultLangTranslation = static::$translations[static::getDefaultLanguage()][$slug];
		$translation = $currentLangTranslation ?: $defaultLangTranslation ?: $slug;
		call_user_func_array('printf',array_merge([$translation], $args));
	}
}
