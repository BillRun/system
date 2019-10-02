<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Generator_Translations {
	
	protected static $defaultLangPath = 'billrun.invoices.language.default';
	protected static $currentLang;
	protected static $languages = [];
	protected static $translations = [];
	
	public static function load() {
		if (!empty($defaultLang = Billrun_Factory::config()->getConfigValue(static::$defaultLangPath))) {
			static::setLanguage($defaultLang);
		} else {
			Billrun_Factory::log()->log('Generator_Translations: Missing Default invoice language in config', Zend_Log::DEBUG);
		}
	}
	
	public static function setLanguage($lang) {
		if(!static::$languages[$lang]++)  {
			static::setTranslation($lang, ['/conf/translations/'.$lang.'.ini' , '/conf/translations/overrides/'.$lang.'.ini']);
		}
		static::$currentLang = $lang;
	}
	
	protected static function setTranslation($lang, $paths) {
		$tr = [];
		foreach ($paths as $path) {
			$tr = array_merge($tr, parse_ini_file(APPLICATION_PATH . $path));
		}
		static::$translations[$lang] = $tr;
	}
	
	public static function getDefaultLanguage() {
		return Billrun_Factory::config()->getConfigValue(static::$defaultLanguagePath);
	}
	
	public static function tr($slug, $values) {
		call_user_func_array('printf',array_merge(static::$translations[static::$currentLang][$slug] ?: static::$translations[static::getDefaultLanguage()][$slug] ?: $slug, $values));
	}
}