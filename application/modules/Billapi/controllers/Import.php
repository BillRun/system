<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/modules/Billapi/controllers/Billapi.php';

/**
 * Billapi controller for getting BillRun entities
 *
 * @package  Billapi
 * @since    5.5
 */
class ImportController extends BillapiController {
	
	protected $action;

	protected function runOperation() {
		$this->action = Models_Action::getInstance($this->params);
		if (!$this->action) {
			throw new Billrun_Exceptions_Api(999999, array(), 'Action cannot be found');
		}
		$this->output->status = 1;
		try {
			$results = $this->action->execute();
                        $imported_entities = isset($results['imported_entities']) ? $results['imported_entities'] : $results;
			foreach ($imported_entities as $result) {
				if($result !== true) {
					$this->output->status = 2;
					$this->output->warnings = [
						'Some rows were not imported'
					];
					break; 
				}
			}
			$this->output->details = $results;
		} catch (Exception $ex) {
			$this->output->status = 0;
			$this->output->errorCode = $ex->getCode();
			$this->output->desc = $ex->getMessage();
			Billrun_Factory::log($this->output->errorCode . ': ' . $this->output->desc, Zend_Log::ERR);
		}
	}

}