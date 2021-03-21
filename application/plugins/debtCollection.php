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
	protected $immediateExit = true;
	protected $cronFrequency = 'daily';
	protected $stepsPeriodicity = 'hourly'; // shouldn't be configurable
	protected $collection;
	protected $nonWorkingDays = array(0, 6);
	protected $collectionSteps;

	public function __construct($options = array()) {
		$this->collection = Billrun_Factory::collection();
		$this->collectionSteps = Billrun_Factory::collectionSteps();
	}
	
	public function afterChargeSuccess($bill) {
		if ($this->immediateExit) {
			$this->collection->collect(array($bill['aid']), 'exit_collection');
		}
	}
	
	public function afterPaymentAdjusted($oldAmount, $newAmount, $aid) {
		if (($oldAmount - $newAmount < 0) && $this->immediateExit) {
			$this->collection->collect(array($aid), 'exit_collection');
		} else if (($oldAmount - $newAmount) > 0 && $this->immediateEnter) {
			$this->collection->collect(array($aid), 'enter_collection');
		}
	}

	public function afterRefundSuccess($bill) {
		if ($this->immediateEnter) {
			$this->collection->collect(array($bill['aid']), 'enter_collection');
		}
	}
	
	public function afterInvoiceConfirmed($bill) {
		if ($bill['due'] > (0 + Billrun_Bill::precision) && $this->immediateEnter) {
			$this->collection->collect([$bill['aid']], 'enter_collection');
		} else if ($bill['due'] < (0 - Billrun_Bill::precision) && $this->immediateExit) {
			$this->collection->collect([$bill['aid']], 'exit_collection');
		}
	}

	public function afterRejection($bill) {
		if ($this->immediateEnter) {
			$this->collection->collect(array($bill['aid']), 'enter_collection');
		}
	}
	
	public function cronHour() {
		if ($this->cronFrequency == 'hourly') {
			$this->collection->collect();
		}
		if ($this->stepsPeriodicity == 'hourly') {
			$this->collectionSteps->runCollectStep();
		}
	}

	public function cronDay() {
		if ($this->cronFrequency == 'daily') {
			$this->collection->collect();
		}
		if ($this->stepsPeriodicity == 'daily') {
			$this->collectionSteps->runCollectStep();
		}
	}
	
	public function afterDenial($denialParams) {
		if ($denialParams['amount'] < (0 - Billrun_Bill::precision) && $this->immediateEnter) { // When amount of denial in response is negative the due of the created rec will be positive.
			$this->collection->collect([$denialParams['aid']], 'enter_collection');
		} else if ($denialParams['amount'] > (0 + Billrun_Bill::precision) && $this->immediateExit) {
			$this->collection->collect([$denialParams['aid']], 'exit_collection');
		}
	}
}
