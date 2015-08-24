<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing realtime processor class
 *
 * @package  Billing
 * @since    4.0
 */
class Billrun_Processor_Realtime extends Billrun_Processor {

	static protected $type = 'realtime';

	/**
	 * override abstract method
	 * @return true
	 */
	public function parse() {
		// real-time have only one event (currently)
		$row = &$this->data['data'][0];
		$row['usaget'] = $this->getLineUsageType($row);
		$row['usagev'] = $this->getLineVolume($row);
		return true;
	}

	/**
	 * process the data
	 * @return boolean
	 */
	public function processData() {
		parent::processData();
		foreach ($this->data['data'] as &$row) {
			$row['urt'] = new MongoDate($row['urt']['sec']);
		}
		return true;
	}

	public function process() {
		Billrun_Factory::dispatcher()->trigger('beforeProcessorParsing', array($this));

		if ($this->parse() === FALSE) {
			Billrun_Factory::log("Billrun_Processor: cannot parse " . $this->filePath, Zend_Log::ERR);
			return FALSE;
		}

		Billrun_Factory::dispatcher()->trigger('afterProcessorParsing', array($this));
		$this->prepareQueue();
		Billrun_Factory::dispatcher()->trigger('beforeProcessorStore', array($this));

		if ($this->store() === FALSE) {
			Billrun_Factory::log("Billrun_Processor: cannot store the parser lines " . $this->filePath, Zend_Log::ERR);
			return FALSE;
		}

		Billrun_Factory::dispatcher()->trigger('afterProcessorStore', array($this));

		//$this->removefromWorkspace($this->getFileStamp());
		Billrun_Factory::dispatcher()->trigger('afterProcessorRemove', array($this));
		return count($this->data['data']);
	}
	
	protected function getLineVolume($row) {
		switch ($row['usaget']) {
			case ('data'):
				return $row['MSCC']['used'];
			case ('call'):
				return 1;
		}
		return 0;
	}

	/**
	 * Get the line usage type (SMS/Call/Data/etc..)
	 * @param $row the CDR line  to get the usage for.
	 */
	protected function getLineUsageType($row) {
		if (isset($row['MSCC']['used'])) {
			return 'data';
		}
		if (isset($row['call_reference'])) {
			return 'call';
		}
		return '';
	}


}
