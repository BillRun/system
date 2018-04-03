<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing receiver for Credit Guard files.
 *
 * @package  Billing
 * @since    5.0
 */
class Billrun_Receiver_CGfeedback extends Billrun_Receiver_Ssh {

	static protected $type = 'CGfeedback';
	protected $gateway;

	public function __construct($options) {
		$this->loadConfig(Billrun_Factory::config()->getConfigValue(self::$type . '.config_path'));
		$options = array_merge($options, $this->getAllReceiverDefinitions());
		parent::__construct($options);
	}

	/**
	 * method to receive files through ssh
	 * 
	 * @return array list of the files received
	 */
	public function receive() {
		return parent::receive();
	}
	
	/**
	 * the structure configuration
	 * @param type $path
	 */
	protected function loadConfig($path) {
		$this->structConfig = (new Yaf_Config_Ini($path))->toArray();
		$this->receiverDefinitions = $this->structConfig['receiver'];
		$this->gateway = Billrun_Factory::paymentGateway('CreditGuard');
	}
	
	protected function getAllReceiverDefinitions() {
		$receiverDefinitions = array();
		foreach ($this->receiverDefinitions  as $key => $value) {
			$receiverIniDefinitions[$key] = $value;
		}
		$dbReceiverDefinitions = $this->gateway->getGatewayReceiver();
		$connections = $dbReceiverDefinitions['connections'];
		foreach ($connections as $key => $connection) {
			if (isset($receiverIniDefinitions['port'])) {
				$connections[$key]['port'] = $receiverIniDefinitions['port'];
				unset($receiverIniDefinitions['port']);
				continue;
			}
			Billrun_Factory::log()->log("There isn't defined port", Zend_Log::NOTICE);
		}
		$dbReceiverDefinitions['connections'] = $connections;
		foreach ($dbReceiverDefinitions as $key => $value) {
			$receiverDefinitions[$key] = $value;
		}

		return array('receiver' => array_merge($receiverDefinitions, $receiverIniDefinitions));
	}

}
