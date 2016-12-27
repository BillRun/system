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
		$action = Models_Action::getInstance($this->params);
		if (!$action) {
			throw new Billrun_Exceptions_Api(999999, array(), 'Action cannot be found');
		}
		return $action->execute();
	}

}
