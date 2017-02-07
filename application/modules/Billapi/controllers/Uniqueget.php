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
		$this->params['field'] = $this->collection == 'rates' ? 'key' : 'name';
	}
	
}
