<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a prototype for a Realtime response action.
 *
 */
abstract class Billrun_ActionManagers_Realtime_Responder_Base {

	/**
	 * Db line to create the response from
	 * 
	 * @var type array
	 */
	protected $row;
	
	/**
	 * The name of the api response
	 * 
	 * @var string 
	 */
	protected $responseApiName = 'basic';

	/**
	 * Create an instance of the RealtimeAction type.
	 */
	public function __construct(array $options = array()) {
		$this->row = $options['row'];
		$this->responseApiName = $this->getResponsApiName();
	}
	
	/**
	 * Sets API name
	 */
	public abstract function getResponsApiName();

	/**
	 * Checks if the responder is valid
	 * 
	 * @return boolean
	 */
	public function isValid() {
		return (!is_null($this->row));
	}
	
	/**
	 * Checks if rebalance is needed
	 * 
	 * @return boolean
	 */
	public function isRebalanceRequired() {
		return false;
	}

	/**
	 * Get response message
	 */
	public function getResponse() {
		if ($this->isRebalanceRequired()) {
			$this->rebalance();
		}
		$responseData = $this->getResponseData();
		return $responseData;
	}
	
	/**
	 * Gets the fields to show on response
	 * 
	 * @return type
	 */
	protected function getResponseFields() {
		return Billrun_Factory::config()->getConfigValue('realtimeevent.responseData.basic', array());
	}

	/**
	 * Gets response message data
	 * 
	 * @return array
	 */
	protected function getResponseData() {
		$ret = array();
		$responseFields = $this->getResponseFields();
		foreach ($responseFields as $responseField => $rowField) {
			if (is_array($rowField)) {
				$ret[$responseField] = (isset($rowField['classMethod']) ? $this->{$rowField['classMethod']}() : '');
			} else {
				$ret[$responseField] = (isset($this->row[$rowField]) ? $this->row[$rowField] : '');
			}
		}
		return $ret;
	}
	
	/**
	 * Gets the amount of usagev that was charged
	 * 
	 * @return type
	 */
	protected function getChargedUsagev($lineToRebalance) {
		return $lineToRebalance['usagev'];
	}
	
	/**
	 * Calculate balance leftovers and add it to the current balance (if were taken due to prepaid mechanism)
	 */
	protected function rebalance() {
		$lineToRebalace = $this->getLineToUpdate()->current();
		$realUsagev = $this->getRealUsagev();
		$chargedUsagev = $this->getChargedUsagev($lineToRebalace);
		if ($chargedUsagev !== null) {
			if (($realUsagev - $chargedUsagev) < 0) {
				$this->handleRebalanceRequired($realUsagev-$chargedUsagev, $lineToRebalace);
			}
		}
	}

	/**
	 * In case balance is in over charge (due to prepaid mechanism), 
	 * adds a refund row to the balance.
	 * 
	 * @param type $rebalanceUsagev amount of balance (usagev) to return to the balance
	 */
	protected function handleRebalanceRequired($rebalanceUsagev, $lineToRebalance = null) {
		
		// Update subscribers balance
		$balanceRef = $lineToRebalance->get('balance_ref');
		$balances_coll = Billrun_Factory::db()->balancesCollection();
		$balance = $balances_coll->getRef($balanceRef);
		if (is_array($balance['tx']) && empty($balance['tx'])) {
			$balance['tx'] = new stdClass();
		}
		$balance->collection($balances_coll);
		$usaget = $lineToRebalance['usaget'];
		$rate = Billrun_Factory::db()->ratesCollection()->getRef($lineToRebalance->get('arate', true));
		$rebalanceCost = Billrun_Calculator_CustomerPricing::getPriceByRate($rate, $usaget, $rebalanceUsagev, $lineToRebalance['plan']);
		if (!is_null($balance->get('balance.totals.' . $usaget . '.usagev'))) {
			$balance['balance.totals.' . $usaget . '.usagev'] += $rebalanceUsagev;
		} else {
			if (!is_null($balance->get('balance.totals.' . $usaget . '.cost'))) {
				$balance['balance.totals.' . $usaget . '.cost'] += $rebalanceCost;
			} else {
				$balance['balance.cost'] += $rebalanceCost;
			}
		}

		$updateQuery = $this->getUpdateLineUpdateQuery($rebalanceUsagev, $rebalanceCost);

		Billrun_Factory::dispatcher()->trigger('beforeSubscriberRebalance', array($lineToRebalance, $balance, &$rebalanceUsagev, &$rebalanceCost, &$updateQuery, $this));

		$balance->save();
					
		// Update previous line
		$lines_coll = Billrun_Factory::db()->linesCollection();
		$lines_coll->update(array('_id' => $lineToRebalance->getId()->getMongoId()), $updateQuery);
		Billrun_Factory::dispatcher()->trigger('afterSubscriberRebalance', array($lineToRebalance, $balance, &$rebalanceUsagev, &$rebalanceCost, &$updateQuery, $this));
	}
	
	
	/**
	 * Gets the update query to update subscriber's Line
	 * 
	 * @param type $rebalanceUsagev
	 */
	protected function getUpdateLineUpdateQuery($rebalanceUsagev, $cost) {
		return array(
			'$inc' => array(
				'usagev' => $rebalanceUsagev,
				'aprice' => $cost,
			),
			'$set' => array(
				'rebalance_usagev' => $rebalanceUsagev,
				'rebalance_cost' => $cost,
			)
		);
	}

}
