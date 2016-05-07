<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class VersionsModel {
	protected static $SAVE_PATH = "exports";
	
	public static function getVersionsBasePath() {
		return Billrun_Factory::config()->getConfigValue('saveversion.export_base_url', '') . self::$SAVE_PATH;
	}

	public static function getVersionsPath($collection) {
		return self::getVersionsBasePath() . '/' . $collection . '/';
	}
	
	public static function getDelimiter() {
		return Billrun_Factory::config()->getConfigValue('saveversion.delimiter', '***');
	}
}