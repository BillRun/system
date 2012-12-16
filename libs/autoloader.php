<?php
/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

define('BASEDIR', __DIR__ . DIRECTORY_SEPARATOR . "..");

/**
 * Autoloader class
 *This loads (includes) class files by it's class name.
 */
class Autoloader {

	/**
	 * Hold all the possible locations to find library files.
	 */
	protected static $locations = array('.','libs');

	/**
	 * Add path or severalpathes to the possible path locations
	 * @param paths string or an array of pathes
	 *		(can also be a standard path string where the paths are
	 *		 seperated with ":"(linux) or ";"(windows))
	 */
	public static function addToPath($paths) {
		if(!is_array($paths)) {
			$paths = explode(PATH_SEPARATOR,$paths);
		}
		Autoloader::$locations = array_merge(Autoloader::$locations,$paths);
	}

	/**
	 * This function actually handles missing Classes autoloading.
	 * oncea class could notbe found thisfunction is called with the class name to try andfix the issue.
	 * @param $class the nae of the missing class.
	 */
	public static function autoload($class) {
		$classPath = str_replace("_",DIRECTORY_SEPARATOR,$class);
		foreach(Autoloader::$locations as $val) {
			$base= ( substr($val,0,1) != "/" ? BASEDIR . DIRECTORY_SEPARATOR : "" );
			$filepath = $base . $val  .  DIRECTORY_SEPARATOR . $classPath . ".php";

			if(file_exists($filepath) && is_readable($filepath)) {
				require_once $filepath;
				return;
			}
		}
		throw new Exception("couldn't find included class : $class , searched in  : ". implode(PATH_SEPARATOR,Autoloader::$locations));
	}
}

//Initialize the autoloader.
spl_autoload_register(__NAMESPACE__ .'\Autoloader::autoload');
