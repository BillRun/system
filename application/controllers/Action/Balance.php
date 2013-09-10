<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Balance action class
 *
 * @package  Action
 * @since    0.5
 */
class BalanceAction extends Action_Base {

	public function execute() {
		$request = $this->getRequest();
		$account_id = $request->get("account_id");
		$subscribers = $request->get("subscribers");
		if (!is_numeric($account_id)) {
			die();
		}
		if (is_string($subscribers)) {
			$subscribers = explode(",", $subscribers);
		} else {
			$subscribers = array();
		}

		$options = array(
			'type' => 'balance',
			'account_id' => $account_id,
			'subscribers' => $subscribers,
		);
		$generator = Billrun_Generator::getInstance($options);

		if ($generator) {
			$generator->load();
			$balance = $generator->generate();
//			$this->getView()->balance = $balance->asXML();
			header('Content-type: text/xml');
			$this->getController()->setOutput(array($balance->asXML(), true));
		} else {
			$this->_controller->addOutput("Generator cannot be loaded");
		}
	}

}