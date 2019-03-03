<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Balance action class
 *
 * @package  Action
 * @since    0.5
 */
class BalanceAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;
	
	public function execute() {
		$options = [
			'fake_cycle' => true,
			'generate_pdf' => false,
			'output' => 'invoice_meta_data',
		];
		$this->forward('generateExpected', $options);
		return false;
		$this->allowed();
		$request = $this->getRequest();
		$aid = $request->get("aid");
		Billrun_Factory::log("Execute balance api call to " . $aid, Zend_Log::INFO);
		$stamp = Billrun_Billingcycle::getBillrunKeyByTimestamp();
		$subscribers = $request->get("subscribers");
		if (!is_numeric($aid)) {
			return $this->setError("aid is not numeric", $request);
		} else {
			settype($aid, 'int');
		}
		if (is_string($subscribers)) {
			$subscribers = explode(",", $subscribers);
		} else {
			$subscribers = array();
		}

		$cacheParams = array(
			'fetchParams' => array(
				'aid' => $aid,
				'subscribers' => $subscribers,
				'stamp' => $stamp,
			),
		);

		$output = $this->cache($cacheParams);
		header('Content-type: text/xml');
		$this->getController()->setOutput(array($output, true)); // hack
	}

	protected function fetchData($params) {
		$options = array(
			'type' => 'balance',
			'aid' => $params['aid'],
			'subscribers' => $params['subscribers'],
			'stamp' => $params['stamp'],
			'buffer' => true,
		);
		$generator = Billrun_Generator::getInstance($options);
		$generator->load();
		$output = $generator->generate();
		return $output;
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
	}

}
