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

	public function beforeProcessorStore($processor) {
		$data = &$processor->getData();
		$queue_data = $processor->getQueueData();

		foreach ($data['data'] as $key => &$line) {
			if ($this->isIncomingRoaming($line)) {
				$this->$incomingRoamingLines[$line['stamp']] = $line;
			}

			if ($this->shouldRemoveLine($line)) {
				$processor->unsetQueueRow($line['stamp']);
				unset($queue_data[$line['stamp']]);
//				unset($data['data'][$key]);
			}
		}
	}

	protected function isIncomingRoaming($row) {
		switch ($row['type']) {
			case 'nsn':
				$roamingIncomingRecordTypes = array('02', '09');
				$roamingRecordTypes = array('01', '02', '08', '09');
				$recordType = Billrun_Util::getIn($row, 'record_type', '');
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
