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
							'api_name' => '/start_call|answer_call|reservation_time|release_call/',
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
								'$setOnInsert' => array('arate', 'usaget', 'calling_number', 'called_number', 'call_reference', 'call_id', 'connected_number', 'plan', 'charging_type', 'service_provider', 'subscriber_lang', 'imsi', 'aid', 'sid', 'pp_includes_name'),
								'$set' => array('process_time'),
								'$inc' => array('usagev', 'duration', 'apr', 'out_balance_usage', 'aprice'),
							),
						),
						array(
							'match' => array(
								'api_name' => '/^answer_call$/',
							),
							'update' => array(
								'$set' => array('urt', 'balance_before'),
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
				),
				'smsrt' => array(
					'required' => array(
						'fields' => array('urt', 'association_number'),
					),
					'date_seperation' => 'Ymd',
					'stamp' => array(
						'value' => array('association_number', 'usaget', 'calling_number', 'called_number'), // no urt intentionally
						'field' => array()
					),
					'fields' => array(
						array(
							'match' => array(
							),
							'update' => array(
								'$setOnInsert' => array('urt', 'balance_before', 'arate', 'usaget', 'calling_number', 'called_number', 'plan', 'charging_type', 'service_provider', 'subscriber_lang', 'aid', 'sid', 'pp_includes_name', 'association_number', 'transaction_id'),
								'$set' => array('process_time'),
								'$inc' => array('usagev', 'apr', 'out_balance_usage', 'aprice'),
							),
						),
						array(
							'match' => array(
							),
							'update' => array(
								'$set' => array('balance_after'),
							),
						),
					),
				),
				'mmsrt' => array(
					'required' => array(
						'fields' => array('urt', 'association_number'),
					),
					'date_seperation' => 'Ymd',
					'stamp' => array(
						'value' => array('association_number', 'usaget', 'calling_number', 'called_number'), // no urt intentionally
						'field' => array()
					),
					'fields' => array(
						array(
							'match' => array(
							),
							'update' => array(
								'$setOnInsert' => array('urt', 'balance_before', 'arate', 'usaget', 'calling_number', 'called_number', 'plan', 'charging_type', 'service_provider', 'subscriber_lang', 'aid', 'sid', 'pp_includes_name', 'association_number', 'transaction_id'),
								'$set' => array('process_time'),
								'$inc' => array('usagev', 'apr', 'out_balance_usage', 'aprice'),
							),
						),
						array(
							'match' => array(
							),
							'update' => array(
								'$set' => array('balance_after'),
							),
						),
					),
				),
				'service' => array(
					'required' => array(
						'fields' => array('urt', 'association_number', 'service_name'),
					),
					'date_seperation' => 'Ymd',
					'stamp' => array(
						'value' => array('association_number', 'usaget', 'calling_number', 'called_number'), // no urt intentionally
						'field' => array()
					),
					'fields' => array(
						array(
							'match' => array(
							),
							'update' => array(
								'$setOnInsert' => array('urt', 'balance_before', 'arate', 'usaget', 'calling_number', 'service_name', 'plan', 'charging_type', 'service_provider', 'subscriber_lang', 'aid', 'sid', 'pp_includes_name', 'association_number', 'transaction_id'),
								'$set' => array('process_time'),
								'$inc' => array('usagev', 'apr', 'out_balance_usage', 'aprice'),
							),
						),
						array(
							'match' => array(
							),
							'update' => array(
								'$set' => array('balance_after'),
							),
						),
					),
				),
				'gy' => array(
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
								'$setOnInsert' => array('arate', 'usaget', 'imsi', 'session_id', 'plan', 'charging_type', 'service_provider', 'subscriber_lang', 'aid', 'sid', 'pp_includes_name', 'balance_before'),
								'$set' => array('process_time'),
								'$inc' => array('usagev', 'duration', 'apr', 'out_balance_usage', 'aprice'),
							),
						),
						array(
							'match' => array(
								'request_type' => '/^3$/',
							),
							'update' => array(
								'$set' => array('balance_after'),
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
		$types = array('callrt','smsrt','mmsrt','service', 'gy');
		return $this->getQueuedLines(array('type' => array('$in' => $types)));
	}
	
	protected function setUnifiedLineDefaults(&$line) {
	}
	
	protected function getDateSeparation($line, $typeData) {
		return FALSE;
	}

}
