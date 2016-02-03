<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
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
			// TODO: put in seperate config dedicate to unify
			return array(
				'callrt' => array(
					'required' => array(
						'fields' => array('urt', 'call_reference', 'api_name'),
						'match' => array(
							'api_name' => '/answer_call|reservation_time|release_call/',
						),
					),
					'date_seperation' => 'Ymd',
					'stamp' => array(
						'value' => array('call_reference', 'usaget', 'imsi', 'calling_number', 'called_number'), // no urt intentionally
						'field' => array()
					),
					'fields' => array(
						array(
							'match' => array(
								'api_name' => '/.*/',
							),
							'update' => array(
								'$setOnInsert' => array('arate', 'usaget', 'calling_number', 'called_number', 'call_reference', 'call_id', 'connected_number', 'plan', 'charging_type', 'service_provider', 'subscriber_lang', 'imsi'),
								'$set' => array('process_time'),
								'$inc' => array('usagev', 'duration', 'apr', 'out_balance_usage', 'aprice'),
							),
						),
						array(
							'match' => array(
								'api_name' => '/^answer_call$/',
							),
							'update' => array(
								'$set' => array('urt', 'aid', 'sid', 'balance_before'),
							),
						),
						array(
							'match' => array(
								'api_name' => '/^release_call$/',
							),
							'update' => array(
								'$set' => array('balance_after'),
							),
						),
					),
					'archive_fallback' => array(
						'api_name' => '/^start_call$/',
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
		$types = array('callrt');
		return $this->getQueuedLines(array('type' => array('$in' => $types)));
	}
	
	protected function setUnifiedLineDefaults(&$line) {
	}
	
	protected function getDateSeparation($line, $typeData) {
		return FALSE;
	}

}
