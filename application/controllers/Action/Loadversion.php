<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Recreate invoices action class
 *
 * @package  Action
 * @since    0.5
 */
class LoadversionAction extends ApiAction {
	
	protected $SAVE_PATH = "exports";

	public static function getVersions() {
		$basePathUrl = Billrun_Factory::config()->getConfigValue('saveversion.export_base_url', '');
		$path =  $basePathUrl . 'exports' . '/' . 'rates' . '/';
		$files = scandir($path);
		$versions = array_diff($files, array('.', '..'));
		return array_merge(array('None Selected'), $versions);
	}
	public function execute() {
		Billrun_Factory::log("Execute load version", Zend_Log::INFO);
	}

}
