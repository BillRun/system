<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi model for balance update
 *
 * @package  Billapi
 * @since    5.3
 */
abstract class Models_Action_Update_Balance_Abstract {
	
	/**
	 * the update method type
	 * @var string
	 */
	protected $updateType = 'Abstract';

	public function __construct(array $params = array()) {
		// load balance update
	}
	
	/**
	 * get the update method type
	 * @return string
	 */
	public function getUpdateType() {
		return $this->updateType;
	}
	
	public function execute() {
		if ($this->preValidate() === false) {
			return false;
		}
		
		$this->update();
		
		if ($this->postValidate() === false) {
			return false;
		}

		$this->createTrackingLines();
		
		return true;
	}

	abstract protected function update();

	/**
	 * create row to track the balance update
	 */
	abstract function createTrackingLines();

	/**
	 * method to load the before state
	 */
	abstract protected function preload();

	public function preValidate() {
		$ret = true;
		Billrun_Factory::dispatcher()->trigger('BillApiBalancePreValidate', array($this, &$ret));
		return $ret;
	}

	public function postValidate() {
		$ret = true;
		Billrun_Factory::dispatcher()->trigger('BillApiBalancePostValidate', array($this, &$ret));
		return $ret;
	}

}
