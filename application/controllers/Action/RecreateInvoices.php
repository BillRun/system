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
class RecreateInvoicesAction extends ApiAction {

	public function execute() {
		Billrun_Factory::log("Execute recreate invoices", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		if (empty($request['account_id'])) {
			return $this->setError('Please supply at least one account id', $request);
		}

		$billrun_key = Billrun_Billrun::getPreviousBillrunKey(Billrun_Billrun::getBillrunKeyByTimestamp(time()));

		// Warning: will convert half numeric strings / floats to integers
		$account_ids = array_unique(array_diff(Billrun_Util::verify_array(explode(',', $request['account_id']), 'int'), array(0)));

		if (!$account_ids) {
			return $this->setError('Illegal aids', $request);
		}
		$options = array(
			'autoload' => 0,
			'stamp' => $billrun_key,
		);
		$customer_aggregator_options = array(
			'override_accounts' => $account_ids,
			'bulk_account_preload' => 0,
		);
		$customerOptions = array(
			'type' => 'customer',
			'aggregator' => $customer_aggregator_options,
		);
		$customerAgg = Billrun_Aggregator::getInstance(array_merge($options, $customerOptions));
		$customerAgg->load();
		$successfulAccounts = $customerAgg->aggregate();

		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'input' => $request,
				'successfulAccounts' => $successfulAccounts,
		)));
		return TRUE;
	}

}
