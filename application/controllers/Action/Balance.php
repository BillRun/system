<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Balance action class
 *
 * @package  Action
 * @since    0.5
 */
class BalanceAction extends ApiAction {

	public function execute() {
		$request = $this->getRequest();
		$aid = $request->get("aid");
		$stamp = Billrun_Util::getBillrunKey(time());
		$subscribers = $request->get("subscribers");
		if (!is_numeric($aid)) {
			die();
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
		echo $output;
		$this->getController()->setOutput(array(false, true)); // hack
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

}
