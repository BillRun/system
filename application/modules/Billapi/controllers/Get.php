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

	protected function verifyTranslated($translated) {
		
	}

	public function init() {
		parent::init();
		$request = $this->getRequest();
		$this->params['sort'] = json_decode($request->get('sort'), TRUE);
		$this->params['page'] = $request->get('page', 0);
		$this->params['size'] = $request->get('size', 10);
		if (!is_null($this->params['sort'])) {
			$this->validateSort($this->params['sort']);
		}
	}

}
