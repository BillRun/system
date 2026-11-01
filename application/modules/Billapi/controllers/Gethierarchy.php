<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2026 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/modules/Billapi/controllers/Billapi.php';

/**
 * Billapi controller for the gethierarchy action (BRCD-785).
 * Returns the full subtree under a given aid, in the same shape as billrun.attributes.hierarchy:
 *   { aid: { name, parent_aid }, ... }
 *
 * @package  Billapi
 * @since    5.27
 */
class GethierarchyController extends BillapiController {

	protected $action;

	protected function verifyTranslated($translated) {
	}

	protected function runOperation() {
		$this->action = Models_Action::getInstance($this->params);
		if (!$this->action) {
			throw new Billrun_Exceptions_Api(999999, array(), 'Action cannot be found');
		}
		$this->output->status = 1;
		try {
			$res = $this->action->execute();
			$this->output->details = $res;
			return $res;
		} catch (Exception $ex) {
			$this->output->status = 0;
			$this->output->errorCode = $ex->getCode();
			$this->output->desc = $ex->getMessage();
			Billrun_Factory::log($this->output->errorCode . ': ' . $this->output->desc, Zend_Log::ERR);
		}
	}

}
