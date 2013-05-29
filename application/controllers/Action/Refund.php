<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Refund action class
 *
 * @package  Action
 * @since    0.5
 */
class RefundAction extends Action_Base {

	/**
	 * method to execute the refund
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		Billrun_Factory::log()->log("Execute refund", Zend_Log::INFO);

		// @TODO: take to config
		$required_fields = array(
			'credit_time',
			'amount_without_vat',
			'reason',
			'imsi',
		);

		$post = $this->getRequest()->getQuery();
		$keys = array_keys($post);
		$missing_fields = array_diff($required_fields, $keys);

		if (!empty($missing_fields)) {
			$this->getController()->setOutput(array(
				'status' => 0,
				'error' => 'required field(s) missing: ' . implode(', ', $missing_fields),
			));
			return;
		}

		$post['source'] = 'api';
		
		// @TODO: take to config
		$optional_fields = array(
			'type', 
			'charge_type',
		);
		foreach ($optional_fields as $field) {
			if (!isset($post[$field])) {
				$post[$field] = 'refund';
			}
		}
		
		$post['stamp'] = Billrun_Util::generateArrayStamp($post);
		$post['process_time'] = Billrun_Util::generateCurrentTime();
		
		$linesCollection = Billrun_Factory::db()->linesCollection();
		if ($linesCollection->query('stamp', $post['stamp'])->count() > 0) {
			$this->getController()->setOutput(array(
				'status' => 0,
				'error' => 'Transcation already exists in the DB',
				'input' => $post,
			));
			return;
		}
		
		$entity = new Mongodloid_Entity($post);
		if ($entity->save($linesCollection) === false) {
			$this->getController()->setOutput(array(
				'status' => 0,
				'error' => 'failed to store into DB',
				'input' => $post,
			));
		}
		$this->getController()->setOutput(array(
			'status' => 1,
			'desc' => 'success',
			'input' => $post,
		));
	}

}