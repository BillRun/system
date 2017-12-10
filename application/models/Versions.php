<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Table Date model class to pull data from database for collections with from/to fields
 *
 * @package  Models
 * @subpackage Versions
 * @since    4.2
 */
class VersionsModel {

	protected static $SAVE_PATH = "exports";

	public static function getVersionsBasePath() {
		return APPLICATION_PATH . Billrun_Factory::config()->getConfigValue('saveversion.export_base_url', '') . '/' . self::$SAVE_PATH;
	}

	public static function getVersionsPath($collection) {
		return self::getVersionsBasePath() . '/' . $collection . '/';
	}

	public static function getDelimiter() {
		return Billrun_Factory::config()->getConfigValue('saveversion.delimiter', '***');
	}

}
