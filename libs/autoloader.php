<?php
/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */
define('BASEDIR', __DIR__ . DIRECTORY_SEPARATOR ."..");
/**
 * TODO
 */
class Autoloader {
	public static $locations = array('.','libs');
	public static function autoload($class) {
		$classPath = str_replace("_",DIRECTORY_SEPARATOR,$class);
		foreach(Autoloader::$locations as $val) {
			$filepath = BASEDIR . DIRECTORY_SEPARATOR. $val .  DIRECTORY_SEPARATOR . $classPath . ".php";
			if(file_exists($filepath) && is_readable($filepath)) {
				require_once $filepath;
				return;
			}
		}
		throw new Exception("couldn't include class :  $class");
	}
}

spl_autoload_register(__NAMESPACE__ .'\Autoloader::autoload');