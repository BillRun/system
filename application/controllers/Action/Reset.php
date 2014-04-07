<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Credit action class
 *
 * @package  Action
 * @since    0.5
 */
class ResetAction extends ApiAction {

	public function execute() {
		Billrun_Factory::log()->log("Execute reset", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		$sids = array();
		if (!isset($request['sid'])) {
			return $this->setError('Please supply at least one sid', $request);
		}
		if (!isset($request['billrun']) || !Billrun_Util::isBillrunKey($request['billrun'])) {
			return $this->setError('Please supply a valid billrun key', $request);
		} else {
			$billrun_key = $request['billrun'];
		}

		foreach (explode(',', $request['sid']) as $sid) {
			if (!Zend_Locale_Format::isInteger($sid)) {
				return $this->setError('Illegal sid', $sid);
				continue;
			} else {
				$sids[] = intval($sid);
			}
		}

		if ($sids) {
			$model = new ResetModel(array_unique($sids), $billrun_key);
			$model->reset();
		} else {
			return $this->setError('Please supply at least one sid', $request);
		}
		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'input' => $request,
		)));
		return true;
	}

}