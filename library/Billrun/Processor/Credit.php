<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing credit processor class
 *
 * @package  Billing
 * @since    2.0
 */
class Billrun_Processor_Credit extends Billrun_Processor {

	static protected $type = 'credit';
	
	protected $queueCalculators = null;

	/**
	 * override abstract method
	 * @return true
	 */
	public function parse() {
		reset($this->data['data']);
		foreach ($this->data['data'] as $rowKey => &$row) {
			$row['type'] = 'credit';
			if (isset($row['credit_time'])) {
				$row['urt'] = new MongoDate($row['credit_time']);
			} else {
				$row['urt'] = new MongoDate();
			}
		}

		return true;
	}

	public function process() {
		if ($this->parse() === FALSE) {
			Billrun_Factory::log("Billrun_Processor: cannot parse " . $this->filePath, Zend_Log::ERR);
			return FALSE;
		}

		$this->prepareQueue();
		$data = &$this->getData();
		$options = array(
			'autoload' => 0,
			'realtime' => true,
			'credit' => true,
		);
		$this->queueCalculators = new Billrun_Helpers_QueueCalculators($options);
		if (!$this->queueCalculators->run($this, $data)) {
			Billrun_Factory::log("Billrun_Processor: error occured while running queue calculators.", Zend_Log::ERR);
			return FALSE;
		}

		if ($this->store() === FALSE) {
			Billrun_Factory::log("Billrun_Processor: cannot store the parser lines " . $this->filePath, Zend_Log::ERR);
			return FALSE;
		}
		$this->afterProcessorStore();
		return count($this->data['data']);
	}
	
	public function afterProcessorStore() {
		if ($this->queueCalculators) {
			$this->queueCalculators->release();
		}	
	}

	protected function processLines() {
	}

}
