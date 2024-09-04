<?php
/**
 * @package	Billing
 * @copyright	Copyright (C) 2012-2018 BillRun Technologies Ltd. All rights reserved.
 * @license	GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Plugin to handle Dialogic custom behaviour
 *
 * @package  Application
 * @subpackage Plugins
 * @since    5.8
 */
class dialogicPlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * 
	 * @param Billrun_Processor $processor
	 * @param string $type
	 * @param array $line The parsed line. Fields are in the array root.
	 */
	public function beforeLineMediation(Billrun_Processor $processor, $type, &$line) {
		if ($type == 'dialogic') {
			$line['original_egress_call_answer'] = $line['egress_call_answer'];
			if (empty($line['egress_call_answer'])) {
				if (empty($line['call_duration']) && !empty($line['ingress_signal_start_timestamp'])) {
					$line['egress_call_answer'] = $line['ingress_signal_start_timestamp'];
				} else {
					Billrun_Factory::log('Dialogic unknown call start time for row ' . $line['global_call_identifier'], Zend_Log::ALERT);
					return;
				}
			}
			$line['egress_call_answer'] .= ' UTC';
			if ($line['cdr_status'] != 'S' && $line['call_duration'] == '') {
				$line['original_call_duration'] = $line['call_duration'];
				$line['call_duration'] = 0;
			}
		}
	}

	/**
	 * 
	 * @param Billrun_Processor $processor
	 * @param string $type
	 * @param array $line The mediated line. Contains "uf" field.
	 */
	public function afterLineMediation(Billrun_Processor $processor, $type, &$line) {
		if ($type == 'dialogic') {
			$line['uf']['egress_call_answer'] = $line['uf']['original_egress_call_answer'];
			unset($line['uf']['original_egress_call_answer']);
			if (array_key_exists('original_call_duration', $line['uf'])) {
				$line['uf']['call_duration'] = $line['uf']['original_call_duration'];
				unset($line['uf']['original_call_duration']);
			}
		}
	}

}
