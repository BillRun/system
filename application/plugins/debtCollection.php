<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * .
 *
 * @package  Application
 * @subpackage Plugins
 * @since    5.8
 */
class debtCollectionPlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'debtCollection';
	protected $immediateEnter = false;
	protected $immediateExit = false;
	protected $cronFrequency = 'daily';
	protected $stepsPeriodicity = 'daily';
	
	public function afterChargeSuccess($aids) {
		if ($this->immediateExit) {
			CollectAction::collect($aids);
		}
	}

	public function afterRefundSuccessOrRejection($aids) {
		if ($this->immediateEnter) {
			CollectAction::collect($aids);
		}
	}
	
	public function cronHour() {
		if ($this->cronFrequency == 'hourly') {
			CollectAction::collect();
		}
		if ($this->stepsPeriodicity == 'daily') {
			Run_collect_stepAction::runCollectStep();
		}
	}

	public function cronDay() {
		if ($this->cronFrequency == 'daily') {
			CollectAction::collect();
		}
		if ($this->stepsPeriodicity == 'daily') {
			Run_collect_stepAction::runCollectStep();
		}
	}
	
}
