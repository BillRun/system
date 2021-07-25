<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Calculates a custom period single charge
 *
 * @package  Plans
 * @since    5.2
 */
Trait Billrun_Plans_Charge_Custom  {

	protected $recurrenceConfig = [];

	public function __construct($plan) {
		parent::__construct($plan);;
		$this->recurrenceConfig = $plan['recurrence'];
		$this->updateCycleByConfig($plan);
	}

	protected function updateCycleByConfig($config) {
		$this->cycle = new Billrun_DataTypes_CustomCycleTime($this->cycle->key(),$config['recurrence'],@$config['invoicing_day'],@$config['activation_date']);
	}

	abstract protected function setMonthlyCover();
}
