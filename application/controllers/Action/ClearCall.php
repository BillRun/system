<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * ClearCall action class - find open (pre-paid) calls without available balance, 
 * and trigger clearCall event if necessary
 *
 * @package  Action
 * 
 * @since    4.0
 */
class ClearCallAction extends ApiAction {

	/**
	 * Find all open (pre-paid) calls without available balance, 
	 * and trigger clearCall event if necessary
	 */
	public function execute() {
		$this->getController()->addOutput("Running ClearCall API...");
		$openCalls = self::getOpenCalls();
		foreach ($openCalls as $call) {
			$balance = $this->getBalance($call);
			if (is_null($balance) || !count($balance)) { // if the subscriber does not have available balance
				$this->handleNoAvailableBalance($call);
			}
		}
	}

	/**
	 * Finds all calls that have not yet terminated.
	 * 
	 * @return type
	 */
	protected static function getOpenCalls() {

		$additionalDataToLoad = array('calling_number', 'call_id', 'sid', 'aid', 'connection_type', 'usaget', 'time_date', 'time_zone', 'granted_return_code');
		$query = self::getOpenCallsQuery($additionalDataToLoad);
		return Billrun_Factory::db()->linesCollection()->aggregate($query);
	}

	/**
	 * Gets the balance of the subscriber related to the call
	 * 
	 * @param type $call
	 * @return The balance of the subscribers of the call
	 */
	protected function getBalance($call) {
		$options = array(
			'sid' => $call['sid'],
			'aid' => $call['aid'],
			'urt' => $call['urt'],
			'connection_type' => $call['connection_type'],
			'usaget' => $call['usaget'],
		);
		return Billrun_Factory::balance($options)->get('balance');
	}

	/**
	 * Handle the case that subscriber has open calls and o available balance
	 * 
	 * @param type $call
	 */
	protected function handleNoAvailableBalance($call) {
		$row = $call;
		$row['call_reference'] = $row['_id'];
		unset($row['_id']);
		Billrun_Factory::dispatcher()->trigger('afterSubscriberBalanceNotFound', array($call));
	}

	/**
	 * Gets a query that finds all open calls, in a configed period of time
	 * 
	 * @param array $additionalDataToLoad Additional fields to load for call group
	 * @return string
	 */
	protected static function getOpenCallsQuery($additionalDataToLoad = array()) {
		$urtRange = Billrun_Factory::config()->getConfigValue('cli.clearcall.urtRange');
		$startTime = date(strtotime($urtRange['start'], time()));
		$endTime = date(strtotime($urtRange['end'], time()));
		$query = array(
			array(
				'$group' => array(
					'_id' => '$call_reference',
					'statuses' => array(
						'$addToSet' => '$record_type'
					),
					'urt' => array(
						'$min' => '$urt'
					)
				)
			),
			array(
				'$match' => array(
					'statuses' => array(
						'$nin' => array('release_call')
					),
					'urt' => array(
						'$gte' => new Mongodloid_Date($startTime),
						'$lte' => new Mongodloid_Date($endTime),
					)
				)
			)
		);

		// Add additional data (assuming that it is the same for reference call group)
		foreach ($additionalDataToLoad as $field) {
			$query[0]['$group'][$field] = array(
				'$first' => '$' . $field
			);
		}

		return $query;
	}

}
