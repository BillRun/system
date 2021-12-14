<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * BillRun CollectionsSteps class
 *
 * @package  Billrun
 * @since    5.0
 */
abstract class Billrun_CollectionSteps extends Billrun_Base {

	/**
	 * Type of object
	 *
	 * @var string
	 */
	protected static $type = 'collection_steps';

	abstract public function createCollectionSteps($aid);

	abstract public function removeCollectionSteps($aid);

	abstract protected function triggerStep($step);

	/**
	 * method to run the setup in the lower layer
	 * 
	 * @param array $step collection step details
	 * 
	 * @return mixed array response details if success, else false
	 */
	protected function runStep($step) {
		Billrun_Factory::dispatcher()->trigger('beforeCollectionStepRun', [$step]);
		$ret = $this->triggerStep($step);
		Billrun_Factory::dispatcher()->trigger('afterCollectionStepRun', [$step, &$ret]);
		return $ret;
	}

}
