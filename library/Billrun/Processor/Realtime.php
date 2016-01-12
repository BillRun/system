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
		if(!isset($row['urt'])) {
			$row['urt'] = new MongoDate();
		}

		return true;
	}

	/**
	 * process the data
	 * @return boolean
	 */
	public function processData() {
		parent::processData();
		foreach ($this->data['data'] as &$row) {
			if(!isset($row['urt'])) {
				$row['urt'] = new MongoDate();
			}
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
		Billrun_Factory::dispatcher()->trigger('beforeProcessorStore', array($this, true));

		if ($this->store() === FALSE) {
			Billrun_Factory::log("Billrun_Processor: cannot store the parser lines " . $this->filePath, Zend_Log::ERR);
			return FALSE;
		}

		Billrun_Factory::dispatcher()->trigger('afterProcessorStore', array($this, true));

		//$this->removefromWorkspace($this->getFileStamp());
		Billrun_Factory::dispatcher()->trigger('afterProcessorRemove', array($this));
		return count($this->data['data']);
	}
	
	protected function getLineVolume($row) {
		switch ($row['usaget']) {
			case ('data'):
/*				$sum = 0;
				$freeOfChargeRatingGroups = Billrun_Factory::config()->getConfigValue('realtimeevent.data.freeOfChargeRatingGroups', array());
				foreach ($row['mscc_data'] as $msccData) {
					if (!in_array($msccData['rating_group'], $freeOfChargeRatingGroups)) {
						$sum += $msccData['requested_units'];
					}
				}
				return $sum;*/
				if ($row['request_type'] == intval(Billrun_Factory::config()->getConfigValue('realtimeevent.data.requestType.FINAL_REQUEST'))) {
					return 1;
				}
				return Billrun_Factory::config()->getConfigValue('realtimeevent.data.quotaDefaultValue', 0);
			case ('call'):
				return Billrun_Factory::config()->getConfigValue('realtimeevent.callReservationTime.default', 180);
			case ('sms'):
				return 1;
			case ('service'):
				return 1;
		}
		return 0;
	}

	/**
	 * Get the line usage type (SMS/Call/Data/etc..)
	 * @param $row the CDR line  to get the usage for.
	 */
	protected function getLineUsageType($row) {
		if (isset($row['mscc_data'])) {
			return 'data';
		}
		if (isset($row['call_reference'])) {
			return 'call';
		}
		if (isset($row['record_type']) && $row['record_type'] === 'sms') {
			return 'sms';
		}
		if (isset($row['record_type']) && $row['record_type'] === 'service') {
			return 'service';
		}
		return '';
	}


}
