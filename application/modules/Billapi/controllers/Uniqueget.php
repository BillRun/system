<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/modules/Billapi/controllers/Get.php';

/**
 * Billapi controller for unique getting BillRun entities
 *
 * @package  Billapi
 * @since    5.3
 */
class UniquegetController extends GetController {

	public function init() {
		parent::init();
		$request = $this->getRequest();
		$this->params['field'] = $this->collection == 'rates' ? 'key' : 'name';
	}
	
	protected function runOperation() {
		$res = parent::runOperation();
		$resCount = count($res);
		$pagesize = $this->action->getSize();
		if ($pagesize > 0 && $resCount > $pagesize) { // if we have indication that we have next page
			unset($res[$resCount-1]);
			$this->output->details = $res;
			$this->output->next_page = true;
		} else {
			$this->output->next_page = false;
		}
		return $res;
	}

}
