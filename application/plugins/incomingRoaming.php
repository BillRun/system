<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * incoming roaming plugin
 *
 * @package  Plugins
 */
class incomingRoamingPlugin extends Billrun_Plugin_BillrunPluginBase {

	protected $incomingRoamingLines = [];
	protected $incomingRoamingQueueLines = [];

	public function beforeProcessorStore($processor) {
		$data = &$processor->getData();
		$queue_data = $processor->getQueueData();

		$this->incomingRoamingLines = [];
		$this->incomingRoamingQueueLines = [];
		foreach ($data['data'] as $key => &$line) {
			if ($this->isIncomingRoaming($line)) {
				$queue_data[$line['stamp']]['incoming_roaming'] = $line['incoming_roaming'] = true;
				$this->incomingRoamingLines[$line['stamp']] = $line;
				$this->incomingRoamingQueueLines[$line['stamp']] = $queue_data[$line['stamp']];
			}

			if ($this->shouldRemoveLine($line)) {
				$processor->unsetQueueRow($line['stamp']);
				unset($queue_data[$line['stamp']]);
				unset($data['data'][$key]);
			}
		}
	}
	
	public function afterProcessorStore($processor) {
		Billrun_Factory::log()->log("Moving incoming roaming lines to incoming roaming DB", Zend_Log::INFO);
		try {
			$incomingRoamingDb = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('incoming_roaming.db'));
			$linesCollection = $incomingRoamingDb->linesCollection();
			$queueCollection = $incomingRoamingDb->queueCollection();
			$options = [
				'w' => 1,
			];
			Billrun_Factory::log()->log("About to batch insert " . count($this->incomingRoamingLines) . " incoming roaming lines", Zend_Log::INFO);
			if (!empty($this->incomingRoamingLines)) {
				$linesCollection->batchinsert($this->incomingRoamingLines, $options);
			}
			Billrun_Factory::log()->log("Done inserting incoming roaming lines", Zend_Log::INFO);
			Billrun_Factory::log()->log("About to batch insert " . count($this->incomingRoamingQueueLines) . " incoming roaming queue lines", Zend_Log::INFO);
			if (!empty($this->incomingRoamingQueueLines)) {
				$queueCollection->batchinsert($this->incomingRoamingQueueLines, $options);
			}
			Billrun_Factory::log()->log("Done inserting incoming roaming queue lines", Zend_Log::INFO);
		} catch (Exception $ex) {
			Billrun_Factory::log()->log("Error bacth inserting incoming roaming lines. Exception code: {$ex->getCode()}. Details: {$ex->getMessage()}", Zend_Log::ERR);
		}	
	}
	
	public function afterCalculatorUpdateRow($row, $calculator) {
		if ($calculator->getCalculatorQueueType() == 'rate' && !empty($row['incoming_roaming'])) {
			if (!empty($tadig = $calculator->getTadig($row))) {
				$row['plmn'] = $tadig;
			}
		}
	}

	protected function isIncomingRoaming($row) {
		switch ($row['type']) {
			case 'nsn':
				$roamingIncomingRecordTypes = array('02', '09');
				$roamingRecordTypes = array('01', '02', '08', '09');
				$recordType = isset($row['record_type']) ? $row['record_type'] : '';
				$imsiField = in_array($recordType, $roamingIncomingRecordTypes) ? 'called_imsi' : 'imsi';
				$imsi = isset($row[$imsiField]) ? $row[$imsiField] : '';
				return (!empty($imsi) && preg_match('/^(?!425)/', $imsi)) &&
						(in_array($recordType, $roamingRecordTypes));
			case 'sgsn':
				return !empty($row['imsi']) && preg_match('/^(?!425)/', $row['imsi']);
			default:
				return false;
		}
		
		return false;
	}

	protected function shouldRemoveLine($line) {
		if (!empty($line['incoming_roaming'])) {
			return true;
		}
		
		switch ($line['type']) {
			case 'nsn':
				return isset($line['record_type']) && in_array($line['record_type'], ['08', '09']);
			case 'sgsn':
				return true;
		}

		return false;
	}

}
