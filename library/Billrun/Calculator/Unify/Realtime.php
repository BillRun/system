<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator class that will unifiy several cdrs to s single cdr if possible.
 * The class is basic rate that can evaluate record rate by different factors
 * 
 * @package  calculator
 * 
 * @since    2.6
 *
 */
class Billrun_Calculator_Unify_Realtime extends Billrun_Calculator_Unify {

	/**
	 * Get the unification fields.
	 * @param array $options - Array of input options.
	 * @return array The unification fields.
	 */
	protected function getUnificationFields($options) {
		if (isset($options['unification_fields'])) {
			return $options['unification_fields'];
		} else {
			$type = $options['type'];
			// all realtime lines should be unified the same
			return array(
				$type => array(
					'required' => array(
						'fields' => array('session_id', 'urt', 'request_num', 'request_type'),
						'match' => array(
							'request_type' => '/1|2|3/',
						),
					),
					'date_seperation' => 'Ymd',
					'stamp' => array(
						'value' => array('session_id', 'usaget', 'imsi'), // no urt intentionally
						'field' => array()
					),
					'fields' => array(
						array(
							'match' => array(
								'request_type' => '/.*/',
							),
							'update' => array(
								'$setOnInsert' => array('arate', 'arate_key', 'usaget', 'imsi', 'session_id', 'urt', 'plan', 'charging_type', 'aid', 'sid', 'msisdn'),
								'$set' => array('process_time', 'granted_return_code'),
								'$inc' => array('usagev', 'duration', 'apr', 'out_balance_usage', 'in_balance_usage', 'aprice'),
							),
						),
					),
				),
			);
		}
	}

	/**
	 * 
	 * @return type
	 */
	protected function getLines() {
		$query = array('realtime' => true);
		return $this->getQueuedLines($query);
	}

	protected function setUnifiedLineDefaults(&$line) {
		
	}

	protected function getDateSeparation($line, $typeData) {
		return FALSE;
	}

}
