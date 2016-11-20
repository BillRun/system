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
 */
class realtimePlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'realtime';

	/**
	 * method to rebalace line if required and set usagev offset
	 * 
	 * @param array $row the row we are running on
	 * @param Billrun_Calculator $calculator the calculator trigger the event
	 * 
	 * @return void
	 */
	public function beforeCalculatorUpdateRow(&$row, Billrun_Calculator $calculator) {
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
			if (!in_array($row['type'], array('datart'))) {
				return 0;
			}
			$lines_coll = Billrun_Factory::db()->linesCollection();
			$query = $this->getRowCurrentUsagevQuery($row);
			$line = current($lines_coll->aggregate($query));
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
		return ($row['type'] == 'datart' && in_array($row['record_type'], array('final_request', 'update_request')));
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
		if ($row['type'] == 'datart') {
			$findQuery = array(
				"sid" => $row['sid'],
				"session_id" => $row['session_id'],
				"request_num" => array('$lt' => $row['request_num']),
			);
			$sort = array(
				'sid' => 1,
				'session_id' => 1,
				'request_num' => -1, 
				'_id' => -1,
			);
			$line = $lines_archive_coll->query($findQuery)->cursor()->sort($sort)->limit(1);
			return $line;
		}
		return false;
	}

	/**
	 * Gets the real usagev of the user (known only on the next API call)
	 * Given in 10th of a second
	 * 
	 * @return type
	 */
	protected function getRealUsagev($row) {
		if ($row['type'] == 'datart') {
			$sum = 0;
			foreach ($row['mscc_data'] as $msccData) {
				if (isset($msccData['used_units'])) {
					$sum += intval($msccData['used_units']);
				}
			}
			return $sum;
		}
		return 0;
	}

	/**
	 * Gets the amount of usagev that was charged
	 * 
	 * @return type
	 */
	protected function getChargedUsagev($row, $lineToRebalance) {
		if ($row['type'] == 'datart') {
			return $lineToRebalance['usagev'];
		}
		return null;
	}
	
	protected function getRebalanceApr($lineToRebalance, $realUsagev, $rate) {
		if ((isset($lineToRebalance['free_line']) && $lineToRebalance['free_line']) ||
			($lineToRebalance['type'] === 'datart' && $lineToRebalance['in_data_slowness'])) {
			return 0;
		}
		$offsetCost = Billrun_Calculator_CustomerPricing::getPriceByRate($rate, $lineToRebalance['usaget'], $lineToRebalance['usagev_offset'], 0, $lineToRebalance);
		$currentCost = Billrun_Calculator_CustomerPricing::getPriceByRate($rate, $lineToRebalance['usaget'], $lineToRebalance['usagev_offset'] + $realUsagev, 0, $lineToRebalance);
		$realCost = $currentCost - $offsetCost;
		$chargedCost = $lineToRebalance['apr'];
		return $realCost - $chargedCost;
	}
	
	protected function getRebalancePricingData($lineToRebalance, $realUsagev, $rate, $balance, $plan) {
		$customerPricingCalc = Billrun_Calculator::getInstance(array('type' => 'customerPricing', 'autoload' => false));
		return $customerPricingCalc->getLinePricingData($realUsagev, $lineToRebalance['usaget'], $rate, $balance, $plan, $lineToRebalance);
	}

	protected function getRebalanceUsagevc($rate, $currentUsagevc, $usaget, $usagev, $offset, $planName = null) {
		$tariff = Billrun_Rate::getTariff($rate, $usaget, $planName);
		$rates_arr = $tariff['rate'];
		$offsetCeil = Billrun_Calculator_Rate::getAccumulatedVolumeByRate($rates_arr, $offset, 'rounded');
		$usagevWithOffsetCeil = Billrun_Calculator_Rate::getAccumulatedVolumeByRate($rates_arr, $offset + $usagev, 'rounded');
		$realUsagevc = $usagevWithOffsetCeil - $offsetCeil;
		return $realUsagevc - $currentUsagevc;
	}
	
	protected function getRebalanceData($lineToRebalance, $rate, $rebalanceUsagev, $realUsagev, $usaget, $rebalancePricingData) {
		$rebalanceData = array(
			'usagev' => $rebalanceUsagev,
			'apr' => $this->getRebalanceApr($lineToRebalance, $realUsagev, $rate),
			'usagevc' => $this->getRebalanceUsagevc($rate, $lineToRebalance['usagevc'], $usaget, $realUsagev, $lineToRebalance['usagev_offset'], $lineToRebalance['plan']),
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
			$balance->collection($balances_coll);
			$plan = Billrun_Factory::plan(array('name' => $originalRow['plan'], 'time' => $originalRow['urt']->sec, 'disableCache' => true));
			$balance_totals_key = $plan->getBalanceTotalsKey($usaget, $rate);
			$balance['balance.totals.' . $balance_totals_key . '.usagev'] += $rebalanceUsagev;
			
			if (isset($lineToRebalance['arategroup'])) { // handle groups
				$group = $lineToRebalance['arategroup'];
				$balance['balance.groups.' . $group . '.' . $balance_totals_key . '.usagev'] += $rebalanceUsagev;
				$this->handleRebalanceOfZones($balance, $plan, $group, 'usagev', $rebalanceUsagev);
			}
		}

		$rebalancePricingData = $this->getRebalancePricingData($lineToRebalance, $realUsagev, $rate, $balance, $plan);
		
		// Update balance cost
		if ($balance) {
			$balance['balance.totals.' . $balance_totals_key . '.cost'] -= $rebalancePricingData['aprice'];
			if (isset($lineToRebalance['arategroup'])) { // handle groups
				$group = $lineToRebalance['arategroup'];
				$balance['balance.groups.' . $group . '.' . $balance_totals_key . '.cost'] -= $rebalancePricingData['aprice'];
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
		$sessionIdFields = Billrun_Factory::config()->getConfigValue('realtimeevent.session_id_field', array());
		$sessionQuery = array_intersect_key($lineToRebalance->getRawData(), array_flip($sessionIdFields[$lineToRebalance['type']]));
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
		return ($row['type'] === 'datart' && $row['record_type'] === 'final_request');
	}

	/**
	 * Gets the update query to update subscriber's Line
	 * 
	 * @param type $rebalanceData
	 * @todo We need to update usagevc, in_plan, out_plan, in_group, usagesb
	 */
	protected function getUpdateLineUpdateQuery($rebalanceData) {
		$ret = array('$inc' => $rebalanceData);
		foreach ($rebalanceData as $rebalanceKey => $rebalanceValue) {
			$ret['$inc']['rebalance_' . $rebalanceKey] = $rebalanceValue;
		}
		return $ret;
	}

}
