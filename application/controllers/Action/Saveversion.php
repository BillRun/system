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
class SaveversionAction extends ApiAction {
	
	protected $SAVE_PATH = "exports";

	public function execute() {
		Billrun_Factory::log("Execute save version", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		$collection = $request['collection'];
		$name = $request['name'];
		Billrun_Factory::log("Exporting " . $collection, Zend_Log::INFO);
		$basePathUrl = Billrun_Factory::config()->get('saveversion.export_base_url', '');
		$path =  $basePathUrl . $this->SAVE_PATH . '/' . $collection . '/' . $name;
		$cmd = 'mongoexport --db billing -c ' . $collection . ' > ' . $path;
		system(`$cmd`);
	}

}
