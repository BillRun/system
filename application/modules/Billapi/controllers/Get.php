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
 * @since    5.3
 */
class GetController extends BillapiController {
	
	protected $action;

	protected function verifyTranslated($translated) {
		
	}

	protected function runOperation() {
		$this->params['sort'] = json_decode(@$this->params['request']['sort'], TRUE);
		$this->params['page'] = Billrun_Util::getIn($this->params, 'request.page', 0);
		$this->params['size'] = Billrun_Util::getIn($this->params, 'request.size', 10);
		if (!is_null($this->params['sort'])) {
			$this->validateSort($this->params['sort']);
		}
		
		$this->action = Models_Action::getInstance($this->params);

		if (!$this->action) {
			throw new Billrun_Exceptions_Api(999999, array(), 'Action cannot be found');
		}
		$this->output->status = 1;
		try {
			$res = $this->action->execute();
			$resCount = count($res);
			$pagesize = $this->action->getSize();
			if ($pagesize > 0 && $resCount > $pagesize) { // if we have indication that we have next page
				unset($res[$resCount-1]);
				$this->output->next_page = true;
			} else {
				$this->output->next_page = false;
			}
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
