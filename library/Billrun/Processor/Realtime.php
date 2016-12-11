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
		$row['usagev'] = $this->getLineVolume($row, $config);
		if (!isset($row['urt'])) {
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
		if (isset($config['realtime']['default_values'][$row['record_type']])) {
			return $config['realtime']['default_values'][$row['record_type']];
		}
		
		if (isset($config['realtime']['default_values']['default'])) {
			return $config['realtime']['default_values']['default'];
		}
		
		if ($row['request_type'] == intval(Billrun_Factory::config()->getConfigValue('realtimeevent.requestType.FINAL_REQUEST'))) {
			return 0;
		}
		return Billrun_Factory::config()->getConfigValue('realtimeevent.' . $row['request_type'] .'.defaultValue', Billrun_Factory::config()->getConfigValue('realtimeevent.defaultValue', 0));
	}

	protected function processLines() {
	}

}
