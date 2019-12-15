<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing realtime processor class
 *
 * @package  Billing
 * @since    4.0
 */
class Billrun_Processor_Realtime extends Billrun_Processor_Usage {

	static protected $type = 'realtime';
	
	public function __construct($options) {
		parent::__construct($options);
		if (!empty($options['default_usaget'])) {
			$this->defaultUsaget = $options['default_usaget'];
		}
		if (!empty($options['usaget_mapping'])) {
			$this->usagetMapping = $options['usaget_mapping'];
		}
	}

	/**
	 * override abstract method
	 * @return true
	 */
	public function parse($config) {
		// real-time have only one event (currently)
		reset($this->data['data']);
		$rowKey = key($this->data['data']);
		$row = &$this->data['data'][$rowKey];
		$row['usaget'] = $this->getLineUsageType($row);
		if ($row['usaget'] === false) {
			Billrun_Factory::log("Billrun_Processor: cannot get line usage type. details: " . print_R($row, 1), Zend_Log::ERR);
			return false;
		}
		$row['stamp'] = md5(serialize(!empty($this->stampFields) ? $this->stampFields : $row));
		$usagev = $this->getLineVolume($row, $config);
		if ($usagev === false) {
			Billrun_Factory::log("Billrun_Processor: cannot get line usage volume. details: " . print_R($row, 1), Zend_Log::ERR);
			return false;
		}
		$row['usagev_unit'] = $this->usagevUnit;
		$row['usagev'] = $usagev;
		if ($this->isLinePrepriced($row['usaget'])) {
			$row['prepriced'] = true;
		}
		$row['process_time'] = new MongoDate();
		$datetime = $this->getRowDateTime($row);
		if (!$datetime) {
			$row['urt'] = new MongoDate();
		} else {
			$row['timezone'] = $datetime->getOffset();
			$row['urt'] = new MongoDate($datetime->format('U'));
		}
		$row['eurt'] = $row['urt'];

		return true;
	}

	/**
	 * process the data
	 * @return boolean
	 */
	public function processData() {
		parent::processData();
		foreach ($this->data['data'] as &$row) {
			if (!isset($row['urt'])) {
				$row['urt'] = new MongoDate();
			}
		}
		return true;
	}

	public function process($config) {
		Billrun_Factory::dispatcher()->trigger('beforeProcessorParsing', array($this));

		if ($this->parse($config) === FALSE) {
			Billrun_Factory::log("Billrun_Processor: cannot parse " . $this->filePath, Zend_Log::ERR);
			return FALSE;
		}

		Billrun_Factory::dispatcher()->trigger('afterProcessorParsing', array($this));
		$this->filterLines();
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

	protected function getLineVolume($row, $config) {
		if ($row['request_type'] == Billrun_Factory::config()->getConfigValue('realtimeevent.requestType.POSTPAY_CHARGE_REQUEST')) {
			return $this->getLineUsageVolume($row['uf'], $row['usaget'], true);
		}
		if (isset($config['realtime']['default_values'][$row['record_type']])) {
			return floatval($config['realtime']['default_values'][$row['record_type']]);
		}
		
		if ($row['request_type'] == intval(Billrun_Factory::config()->getConfigValue('realtimeevent.requestType.FINAL_REQUEST'))) {
			return 0;
		}
		
		if (isset($config['realtime']['default_values']['default'])) {
			return floatval($config['realtime']['default_values']['default']);
		}
		
		return floatval(Billrun_Factory::config()->getConfigValue('realtimeevent.' . $row['request_type'] .'.defaultValue', Billrun_Factory::config()->getConfigValue('realtimeevent.defaultValue', 0)));
	}

	protected function processLines() {
	}
	
	public function process_files() {
		return 0;
	}
	
	public function addDataRow($row) {
		if (!isset($this->data['data'])) {
			$this->data['data'] = array();
		}
		$this->data['data'][] = $row;
		return true;
	}
	
	public function unsetRow($stamp) {
		foreach ($this->data['data'] as $i => $row) {
			if ($row['stamp'] === $stamp) {
				$this->doNotSaveLines[$stamp] = $this->data['data'][$i];
				unset($this->data['data'][$i]);
				return true;
			}
		}
		
		return false;
	}

}