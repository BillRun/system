<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Realtime plugin
 *
 * @package  Application
 * @subpackage Plugins
 * @since    5.3
 * @deprecated since version 5.5
 */
class realtimePlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'realtime';
	
	/**
	 * current configuration
	 * 
	 * @var type 
	 */
	protected $config = null;

	/**
	 * method to rebalace line if required and set usagev offset
	 * 
	 * @param array $row the row we are running on
	 * @param Billrun_Calculator $calculator the calculator trigger the event
	 * 
	 * @return void
	 */
	public function beforeCalculatorUpdateRow(&$row, Billrun_Calculator $calculator) {
		if (!isset($row['realtime']) || !$row['realtime']) {
			return;
		}
		if (!isset($row['usagev_offset']) && ($calculator->getType() == 'pricing' || stripos(get_class($calculator), 'rate') !== FALSE)) {
			$row['usagev_offset'] = $this->getRowCurrentUsagev($row);
		}
		
		if ($calculator->getType() == 'pricing') {
			if ($this->isRebalanceRequired($row)) {
				$this->rebalance($row);
			}
		}
	}

	protected function getRowCurrentUsagev($row) {
		try {
			if ($this->isPrepayChargeRequest($row)) {
				return 0;
			}
			$lines_coll = Billrun_Factory::db()->linesCollection();
			$query = $this->getRowCurrentUsagevQuery($row);
			$line = current(iterator_to_array($lines_coll->aggregate($query)));
		} catch (Exception $ex) {
			Billrun_Factory::log($ex->getCode() . ': ' . $ex->getMessage());
		}
		return isset($line['sum']) ? $line['sum'] : 0;
	}

	protected function getRowCurrentUsagevQuery($row) {
		$query = array(
			array(
				'$match' => array(
					"sid" => $row['sid'],
					"session_id" => $row['session_id'],
				)
			),
			array(
				'$group' => array(
					'_id' => null,
					'sum' => array('$sum' => '$usagev'),
				)
			)
		);
		return $query;
	}

	protected function isRebalanceRequired($row) {
		if ($this->isPrepayChargeRequest($row)) {
			return false;
		}
		if ($this->isReblanceOnLastRequestOnly($row)) {
			$rebalanceTypes = array('final_request');
		} else {
			$rebalanceTypes = array('final_request', 'update_request');
		}
		return ($row['realtime'] && in_array($row['record_type'], $rebalanceTypes));
	}
	
	protected function isReblanceOnLastRequestOnly($row) {
		$config = $this->getConfig($row);
		return (isset($config['realtime']['rebalance_on_final']) && $config['realtime']['rebalance_on_final']);
	}
	
	protected function isPrepayChargeRequest($row) {
		return $row['request_type'] == Billrun_Factory::config()->getConfigValue('realtimeevent.requestType.POSTPAY_CHARGE_REQUEST');
	}

	/**
	 * Calculate balance leftovers and add it to the current balance (if were taken due to prepaid mechanism)
	 */
	protected function rebalance($row) {
		$lineToRebalance = $this->getLineToUpdate($row)->current();
		$realUsagev = $this->getRealUsagev($row);
		$chargedUsagev = $this->getChargedUsagev($row, $lineToRebalance);
		if ($chargedUsagev !== null) {
			$rebalanceUsagev = $realUsagev - $chargedUsagev;
			if (($rebalanceUsagev) < 0) {
				$this->handleRebalanceRequired($rebalanceUsagev, $realUsagev, $lineToRebalance, $row);
			}
		}
	}

	/**
	 * Gets the Line that needs to be updated (on rebalance)
	 */
	protected function getLineToUpdate($row) {
		$lines_archive_coll = Billrun_Factory::db()->archiveCollection();
		$findQuery = array(
			"sid" => $row['sid'],
			"session_id" => $row['session_id'],
		);
		$sort = array(
			'sid' => 1,
			'session_id' => 1, 
			'_id' => -1,
		);
		$line = $lines_archive_coll->query($findQuery)->cursor()->sort($sort)->limit(1);
		return $line;
	}

	/**
	 * Gets the real usagev of the user (known only on the next API call)
	 * Given in 10th of a second
	 * 
	 * @return type
	 */
	protected function getRealUsagev($row) {
		$config = $this->getConfig($row);
		if (!isset($row['uf'][$config['realtime']['used_usagev_field']])) {
			return 0;
		}
		return $row['uf'][$config['realtime']['used_usagev_field']];
	}
	
	/**
	 * Gets the current configuration values
	 * 
	 * @param $row
	 * @return configuration
	 */
	protected function getConfig($row) {
		if (empty($this->config)) {
			$this->config = Billrun_Factory::config()->getFileTypeSettings($row['type'], true);
		}
		return $this->config;
	}

	/**
	 * Gets the amount of usagev that was charged
	 * 
	 * @return type
	 */
	protected function getChargedUsagev($row, $lineToRebalance) {
		if ($this->isReblanceOnLastRequestOnly($row)) {
			$lines_archive_coll = Billrun_Factory::db()->archiveCollection();
			$query = $this->getRebalanceQuery($row);
			$line = $lines_archive_coll->aggregate($query)->current();
			return $line['sum'];
		}
		return $lineToRebalance['usagev'];
	}
	
	protected function getRebalanceQuery($lineToRebalance) {
		$sessionQuery = $this->getSessionIdQuery($lineToRebalance->getRawData());
		$findQuery = array_merge(array("sid" => $lineToRebalance['sid']), $sessionQuery);
		return array(
			array(
				'$match' => $findQuery
			),
			array(
				'$group' => array(
					'_id' => 'sid',
					'sum' => array('$sum' => '$usagev')
				)
			)
		);
	}
	
	protected function getSessionIdQuery ($row) {
		if (isset($row['session_id'])) {
			return array('session_id' => $row['session_id']);
		}
		return array();
	}
	
	protected function getRebalancePricingData($lineToRebalance, $realUsagev) {
		$row = $lineToRebalance;
		$row['billrun_pretend'] = true;
		$row['usagev'] = $realUsagev;
		$calcRow = Billrun_Calculator_Row::getInstance('Customerpricing', $row, $this, $row['connection_type']);
		return $calcRow->update();
	}
	
	public function getPricingField() {
		return Billrun_Calculator_CustomerPricing::DEF_CALC_DB_FIELD;
	}
	
	protected function getRebalanceData($lineToRebalance, $rate, $rebalanceUsagev, $realUsagev, $usaget, $rebalancePricingData) {
		$rebalanceData = array(
			'usagev' => $rebalanceUsagev,
			'aprice' => $lineToRebalance['aprice'] - $rebalancePricingData['aprice'],
		);
		
		foreach ($rebalancePricingData as $rebalanceKey => $rebalanceVal) {
			if ($rebalanceKey === 'arategroup') {
				continue;
			}
			$rebalanceData[$rebalanceKey] = $rebalanceVal - $lineToRebalance[$rebalanceKey];
		}
		
		return $rebalanceData;
	}
	
	/**
	 * rebalance zones in Balance object
	 * 
	 * @param $balance
	 * @param $plan
	 * @param $group
	 * @param $rebalanceKey - key to rebalance (cost/usagev/count...)
	 * @param $rebalanceValue
	 * @deprecated since version 5.5
	 */
	protected function handleRebalanceOfZones(&$balance, $plan, $group, $rebalanceKey, $rebalanceValue) {
		$optionDetails = $plan->getOptionByKey($group);
		foreach (@Billrun_Util::getFieldVal($optionDetails['zones'],array()) as $zone) {
			$balance['balance.zones.' . $zone . '.' . $rebalanceKey] += $rebalanceValue;
		}
	}

	/**
	 * In case balance is in over charge (due to prepaid mechanism), 
	 * adds a refund row to the balance.
	 * 
	 * @param type $rebalanceUsagev amount of balance (usagev) to return to the balance
	 * @param type $realUsagev
	 * @param type $lineToRebalance
	 * @param type $originalRow
	 */	
	protected function handleRebalanceRequired($rebalanceUsagev, $realUsagev, $lineToRebalance, $originalRow) {
		$usaget = $lineToRebalance['usaget'];
		$rate = Billrun_Factory::db()->ratesCollection()->getRef($lineToRebalance->get('arate', true));
		
		// Update subscribers balance
		$balanceRef = $lineToRebalance->get('balance_ref', true);
		if (!$balanceRef) {
			$balance = null;
		} else {
			// Update balance usagev
			$balances_coll = Billrun_Factory::db()->balancesCollection();
			$balance = $balances_coll->getRef($balanceRef);
			if (is_array($balance['tx']) && empty($balance['tx'])) { //TODO: this is a hack because tx is saved as [] instead of {}
				$balance['tx'] = new stdClass();
			}
                        if (is_array($balance['tx2']) && empty($balance['tx2'])) { //TODO: this is a hack because tx is saved as [] instead of {}
				$balance['tx2'] = new stdClass();
			}
			$balance->collection($balances_coll);
			$plan = Billrun_Factory::plan(array('name' => $originalRow['plan'], 'time' => $originalRow['urt']->sec, 'disableCache' => true));
			$balance_totals_key = $plan->getBalanceTotalsKey($usaget, $rate); //TODO: use $balance->getBalanceTotalsKey in customerpricing
			$balance['balance.totals.' . $balance_totals_key . '.usagev'] += $rebalanceUsagev;
			
			if (isset($lineToRebalance['arategroup'])) { // handle groups
				$group = $lineToRebalance['arategroup'];
				$balance['balance.groups.' . $group . '.' . $balance_totals_key . '.usagev'] += $rebalanceUsagev;
				//$this->handleRebalanceOfZones($balance, $plan, $group, 'usagev', $rebalanceUsagev);
			}
		}

		$rebalancePricingData = $this->getRebalancePricingData($lineToRebalance, $realUsagev);
		
		// Update balance cost
		if ($balance) {
			$rebalanceAprice = ($lineToRebalance['aprice'] - $rebalancePricingData['aprice']);
			$balance['balance.cost'] -= $rebalanceAprice;
			$balance['balance.totals.' . $balance_totals_key . '.cost'] -= $rebalanceAprice;
			if (isset($lineToRebalance['arategroup'])) { // handle groups
				$group = $lineToRebalance['arategroup'];
				$balance['balance.groups.' . $group . '.' . $balance_totals_key . '.cost'] -= $rebalanceAprice;
			}
			$balance->save();
		}
		
		$originalRow['usagev_offset'] += $rebalanceUsagev;
		
		$rebalanceData = $this->getRebalanceData($lineToRebalance, $rate, $rebalanceUsagev, $realUsagev, $usaget, $rebalancePricingData);
		$updateQuery = $this->getUpdateLineUpdateQuery($rebalanceData);
		
		// Update line in archive
		$lines_archive_coll = Billrun_Factory::db()->archiveCollection();
		$lines_archive_coll->update(array('_id' => $lineToRebalance->getId()->getMongoId()), $updateQuery);

		// Update line in Lines collection will be done by Unify calculator
		$sessionQuery = $this->getSessionIdQuery($lineToRebalance->getRawData());
		$findQuery = array_merge(array("sid" => $lineToRebalance['sid']), $sessionQuery);
		$lines_coll = Billrun_Factory::db()->linesCollection();
		$options = array('multiple' => true); // this option is added in case we have sharding key=stamp and the update cannot be done
		$lines_coll->update($findQuery, $updateQuery, $options);
	}
	
	/**
	 * return whether we need to consider intervals when rebalancing usagev balance
	 * 
	 * @param type $row
	 * @return type
	 * @todo move hard-coded values to configuration
	 */
	protected function needToRebalanceUsagev($row) {
		return ($row['realtime'] && $row['record_type'] === 'final_request');
	}

	/**
	 * Gets the update query to update subscriber's Line
	 * 
	 * @param type $rebalanceData
	 * @todo We need to update usagevc, in_plan, out_plan, in_group, usagesb
	 */
	protected function getUpdateLineUpdateQuery($rebalanceData) {
		unset($rebalanceData['billrun']);
		$ret = array('$inc' => $rebalanceData);
		foreach ($rebalanceData as $rebalanceKey => $rebalanceValue) {
			$ret['$inc']['rebalance_' . $rebalanceKey] = $rebalanceValue;
		}
		return $ret;
	}

}
