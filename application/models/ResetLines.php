<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Reset model class to reset subscribers balance & lines for a billrun month
 *
 * @package  Models
 * @subpackage Table
 * @since    0.5
 */
class ResetLinesModel {

	/**
	 *
	 * @var array
	 */
	protected $aids;

	/**
	 *
	 * @var string
	 */
	protected $billrun_key;

	/**
	 * Don't get newly stuck lines because they might have not been inserted yet to the queue
	 * @var string
	 */
	protected $process_time_offset;

	/**
	 * Usage to substract from extended balance in rebalance.
	 * @var array
	 */
	protected $extendedBalanceSubstract;

	/**
	 * Usage to substract from default balance in rebalance.
	 * @var array
	 */
	protected $balanceSubstract = [];
	
	/**
	 * used for rebalance multiple balances affected by the same line
	 * @var type 
	 */
	protected $alreadyUpdated = [];
	
	protected $balances;
	
	/**
	 * Conditions to add to the query when rebalancing.
	 * @var array
	 */
	protected $conditions;

	public function __construct($aids, $billrun_key, $conditions) {
		$this->initBalances($aids, $billrun_key);
		$this->aids = $aids;
		$this->billrun_key = strval($billrun_key);
		$this->process_time_offset = Billrun_Config::getInstance()->getConfigValue('resetlines.process_time_offset', '15 minutes');
		$this->conditions = $conditions;
	}

	public function reset() {
		Billrun_Factory::log('Reset subscriber activated', Zend_Log::INFO);
		$ret = $this->resetLines();
		return $ret;
	}

	/**
	 * Removes the balance doc for each of the subscribers
	 */
	public function resetBalances($aids) {
		$ret = true;
		$balances_coll = Billrun_Factory::db()->balancesCollection()->setReadPreference('RP_PRIMARY');
		if (!empty($this->aids) && !empty($this->billrun_key)) {
			$ret = $this->resetDefaultBalances($aids, $balances_coll);
			$this->resetExtendedBalances($aids, $balances_coll);
		}
		return $ret;
	}

	/**
	 * Get the reset lines query.
	 * @param array $update_aids - Array of aid's to reset.
	 * @return array Query to run in the collection for reset lines.
	 */
	protected function getResetLinesQuery($update_aids) {
		return array(
			'$or' => array(
				array(
					'billrun' => $this->billrun_key
				),
				array(
					'billrun' => array(
						'$exists' => FALSE,
					),
					'urt' => array(// resets non-billable lines such as ggsn with rate INTERNET_VF
						'$gte' => new MongoDate(Billrun_Billingcycle::getStartTime($this->billrun_key)),
						'$lt' => new MongoDate(Billrun_Billingcycle::getEndTime($this->billrun_key)),
					)
				),
			),
			'aid' => array(
				'$in' => $update_aids,
			),
			'type' => array(
				'$nin' => array('credit', 'flat', 'service'),
			),
			'process_time' => array(
				'$lt' => new MongoDate(strtotime($this->process_time_offset . ' ago')),
			),
		);
	}

	/**
	 * Reset lines for subscribers based on input array of AID's
	 * @param array $update_aids - Array of account ID's to reset.
	 * @param array $advancedProperties - Array of advanced properties.
	 * @param Mongodloid_Collection $lines_coll - The lines collection.
	 * @param Mongodloid_Collection $queue_coll - The queue colection.
	 * @return boolean true if successful false otherwise.
	 */
	protected function resetLinesForAccounts($update_aids, $advancedProperties, $lines_coll, $queue_coll) {
		$conditionsQuery = $this->buildConditionsQuery($update_aids);
		$basicQuery = $this->getResetLinesQuery($update_aids);
		if (!empty($conditionsQuery)) {
			$query = array_merge($basicQuery, array('$and' => array($conditionsQuery)));
		} else {
			$query = $basicQuery;
		}
		$lines = $lines_coll->query($query);
		$rebalanceTime = new MongoDate();
		$stamps = array();
		$queue_lines = array();

		// Go through the collection's lines and fill the queue lines.
		foreach ($lines as $line) {
                        $queue_line = array(
                                'calc_name' => false,
                                'calc_time' => false,
                                'skip_fraud' => true,
                        );
			Billrun_Factory::dispatcher()->trigger('beforeRebalancingLines', array(&$line));
			$this->aggregateLineUsage($line);
			$queue_line['rebalance'] = array();
			$stamps[] = $line['stamp'];
			if (!empty($line['rebalance'])) {
				$queue_line['rebalance'] = $line['rebalance'];
			}
			$queue_line['rebalance'][] = $rebalanceTime;
			$this->buildQueueLine($queue_line, $line, $advancedProperties);
			$queue_lines[] = $queue_line;
		}

		// If there are stamps to handle.
		if ($stamps) {
			// Handle the stamps.
			if (!$this->handleStamps($stamps, $queue_coll, $queue_lines, $lines_coll, $update_aids, $rebalanceTime)) {
				return false;
			}
		}
	}

	/**
	 * Removes lines from queue, reset added fields off lines and re-insert to queue first stage
	 * @todo support update/removal of credit lines
	 */
	protected function resetLines() {
		$lines_coll = Billrun_Factory::db()->linesCollection()->setReadPreference('RP_PRIMARY');
		$queue_coll = Billrun_Factory::db()->queueCollection()->setReadPreference('RP_PRIMARY');
		if (empty($this->aids) || empty($this->billrun_key)) {
			// TODO: Why return true?
			return true;
		}

		$offset = 0;
		$configFields = array('imsi', 'msisdn', 'called_number', 'calling_number');
		$advancedProperties = Billrun_Factory::config()->getConfigValue("queue.advancedProperties", $configFields);

		while ($update_count = count($update_aids = array_slice($this->aids, $offset, 10))) {
			Billrun_Factory::log('Resetting lines of accounts ' . implode(',', $update_aids), Zend_Log::INFO);
			$this->resetLinesForAccounts($update_aids, $advancedProperties, $lines_coll, $queue_coll);
			$offset += 10;
		}

		return TRUE;
	}

	/**
	 * Construct the queue line based on the input line from the collection.
	 * @param array $queue_line - Line to construct.
	 * @param array $line - Input line from the collection.
	 * @param array $advancedProperties - Advanced config properties.
	 */
	protected function buildQueueLine(&$queue_line, $line, $advancedProperties) {
		$queue_line['stamp'] = $line['stamp'];
		$queue_line['type'] = $line['type'];
		$queue_line['urt'] = $line['urt'];

		foreach ($advancedProperties as $property) {
			if (isset($line[$property]) && !isset($queue_line[$property])) {
				$queue_line[$property] = $line[$property];
			}
		}
	}

	/**
	 * Get the query to update the lines collection with.
	 * @return array - Query to use to update lines collection.
	 */
	protected function getUpdateQuery($rebalanceTime) {
		$updateQuery = array(
			'$unset' => array(
				'aid' => 1,
				'sid' => 1,
				'subscriber' => 1,
				'apr' => 1,
				'aprice' => 1,
				'arate' => 1,
				'arate_key' => 1,
				'arategroups' => 1,
				'firstname' => 1,
				'lastname' => 1,
				'billrun' => 1,
				'in_arate' => 1,
				'in_group' => 1,
				'in_plan' => 1,
				'out_plan' => 1,
				'over_arate' => 1,
				'over_group' => 1,
				'over_plan' => 1,
				'out_group' => 1,
				'plan' => 1,
				'plan_ref' => 1,
				'services' => 1,
				'usagesb' => 1,
//				'usagev' => 1,
				'balance_ref' => 1,
				'tax_data' => 1,
				'final_charge' => 1,
				'rates' => 1,
				'services_data' => 1,
				'foreign' => 1, // this should be replaced by querying lines.fields and resetting all custom fields found there
			),
			'$set' => array(
				'in_queue' => true,
			),
			'$push' => array(
				'rebalance' => $rebalanceTime,
			),
		);

		Billrun_Factory::dispatcher()->trigger('beforeUpdateRebalanceLines', array(&$updateQuery));
		
		return $updateQuery;
	}

	/**
	 * Get the query to return all lines including the collected stamps.
	 * @param $stamps - Array of stamps to query for.
	 * @return array Query to run for the lines collection.
	 */
	protected function getStampsQuery($stamps) {
		return array(
			'stamp' => array(
				'$in' => $stamps,
			),
		);
	}

	/**
	 * Handle stamps for reset lines.
	 * @param array $stamps
	 * @param type $queue_coll
	 * @param type $queue_lines
	 * @param type $lines_coll
	 * @param type $update_aids
	 * @return boolean
	 */
	protected function handleStamps($stamps, $queue_coll, $queue_lines, $lines_coll, $update_aids, $rebalanceTime) {
		$update = $this->getUpdateQuery($rebalanceTime);
		$stamps_query = $this->getStampsQuery($stamps);
		
		Billrun_Factory::log('Removing ' . count($stamps) . ' records from queue', Zend_Log::DEBUG);
		$ret = $queue_coll->remove($stamps_query); // ok == 1, err null
		if (isset($ret['err']) && !is_null($ret['err'])) {
			return FALSE;
		}
		Billrun_Factory::log('Starting to reset balances', Zend_Log::DEBUG);
		$ret = $this->resetBalances($update_aids); // err null
		if (isset($ret['err']) && !is_null($ret['err'])) {
			return FALSE;
		}
		if (Billrun_Factory::db()->compareServerVersion('2.6', '>=') === true) {
			try{
				$ret = $queue_coll->batchInsert($queue_lines); // ok==true, nInserted==0 if w was 0
				if (isset($ret['err']) && !is_null($ret['err'])) {
					Billrun_Factory::log('Rebalance: batch insertion to queue failed, Insert Error: ' .$ret['err'], Zend_Log::ALERT);
					throw new Exception();
				}
			} catch (Exception $e) {
				Billrun_Factory::log("Rebalance: Batch insert failed during insertion to queue, inserting line by line, Error: " .  $e->getMessage(), Zend_Log::ERR);
				foreach ($queue_lines as $qline) {
					$ret = $queue_coll->insert($qline); // ok==1, err null
					if (isset($ret['err']) && !is_null($ret['err'])) {
						Billrun_Factory::log('Rebalance: line insertion to queue failed, Insert Error: ' .$ret['err'] . ', failed_line ' . print_r($qline, 1), Zend_Log::ALERT);
						continue;
					}
				}
			}
		} else {
			foreach ($queue_lines as $qline) {
				$ret = $queue_coll->insert($qline); // ok==1, err null
				if (isset($ret['err']) && !is_null($ret['err'])) {
					return FALSE;
				}
			}
		}		
		Billrun_Factory::log('Resetting ' . count($stamps) . ' lines', Zend_Log::DEBUG);
		$ret = $lines_coll->update($stamps_query, $update, array('multiple' => true)); // err null
		if (isset($ret['err']) && !is_null($ret['err'])) {
			return FALSE;
		}

		return true;
	}

	/**
	 * method to calculate the usage need to be subtracted from the balance.
	 * 
	 * @param type $line
	 * 
	 */
	protected function aggregateLineUsage($line) {
		if (!isset($line['usagev']) || !isset($line['aprice'])) {
			return;
		}
		$billrunKey = Billrun_Billingcycle::getBillrunKeyByTimestamp($line['urt']->sec);
		$arategroups = isset($line['arategroups']) ? $line['arategroups'] : array();
		foreach ($arategroups as $arategroup) {
			$balanceId = $arategroup['balance_ref']['$id']->{'$id'};
			$group = $arategroup['name'];
			$arategroupValue = isset($arategroup['usagev']) ? $arategroup['usagev'] : $arategroup['cost'];
			$aggregatedUsage = isset($this->extendedBalanceUsageSubtract[$line['aid']][$balanceId][$group][$line['usaget']]['usage']) ? $this->extendedBalanceUsageSubtract[$line['aid']][$balanceId][$group][$line['usaget']]['usage'] : 0;
			$this->extendedBalanceUsageSubtract[$line['aid']][$balanceId][$group][$line['usaget']]['usage'] = $aggregatedUsage + $arategroupValue;
			@$this->extendedBalanceUsageSubtract[$line['aid']][$balanceId][$group][$line['usaget']]['count'] += 1;
			$groupUsage = isset($this->balanceSubstract[$line['aid']][$line['sid']][$billrunKey]['groups'][$group][$line['usaget']]['usage']) ? $this->balanceSubstract[$line['aid']][$line['sid']][$billrunKey]['groups'][$group][$line['usaget']]['usage'] : 0;
			$this->balanceSubstract[$line['aid']][$line['sid']][$billrunKey]['groups'][$group][$line['usaget']]['usage'] = $groupUsage + $arategroupValue;
			@$this->balanceSubstract[$line['aid']][$line['sid']][$billrunKey]['groups'][$group][$line['usaget']]['count'] += 1;
		}

		if ($this->affectsMainBalance($line)) {
			if (!empty(($line['over_group']))) {
				Billrun_Util::increaseIn($this->balanceSubstract, [$line['aid'], $line['sid'], $billrunKey, 'totals', $line['usaget'], 'over_group', 'usage'], $line['over_group']);
			}
			
			if (!empty(($line['out_group']))) {
				Billrun_Util::increaseIn($this->balanceSubstract, [$line['aid'], $line['sid'], $billrunKey, 'totals', $line['usaget'], 'out_group', 'usage'], $line['out_group']);
			}

			Billrun_Util::increaseIn($this->balanceSubstract, [$line['aid'], $line['sid'], $billrunKey, 'totals', $line['usaget'], 'usage'], $this->getMainBalanceUsage($line));
			Billrun_Util::increaseIn($this->balanceSubstract, [$line['aid'], $line['sid'], $billrunKey, 'totals', $line['usaget'], 'cost'], $line['aprice']);
			Billrun_Util::increaseIn($this->balanceSubstract, [$line['aid'], $line['sid'], $billrunKey, 'totals', $line['usaget'], 'count'], 1);
			Billrun_Util::increaseIn($this->balanceSubstract, [$line['aid'], $line['sid'], $billrunKey, 'cost'], $line['aprice']);
		}
	}

	protected function affectsMainBalance($line) {
		$arategroups = $line['arategroups'] ?? [];
		return empty($arategroups) ||
			$this->isInMainBalance($arategroups) ||
			(isset($line['over_group']) && $line['over_group'] > 0 && isset($line['in_group']) && $line['in_group'] > 0);
	}

	protected function getMainBalanceUsage($line) {
		$arategroups = $line['arategroups'] ?? [];
		if (empty($arategroups)) {
			return $line['usagev'];
		}

		$ret = 0;
		if (!empty($line['over_group'])) {
			$ret += $line['over_group'];
		} else if (!empty($line['out_group'])) {
			$ret += $line['out_group'];
		}

		foreach ($arategroups as $arategroup) {
			$balanceId = $arategroup['balance_ref']['$id']->{'$id'};
			if ($this->isMainBalance($balanceId)) {
				$ret += $arategroup['usagev'] ?? 0;
			}
		}

		return $ret;
	}

	protected function getRelevantBalances($balances, $balanceId, $params = array()) {
		$this->alreadyUpdated = [];
		$ret = [];
		foreach ($balances as $balance) {
			$rawData = $balance->getRawData();
			if (isset($rawData['_id']) && !empty($balanceId) && $rawData['_id']->{'$id'} == $balanceId) {
				return [$rawData];
			}

			if (empty($balanceId) && !empty($params)) {
				$startTime = Billrun_Billingcycle::getStartTime($params['billrun_key']);
				$endTime = Billrun_Billingcycle::getEndTime($params['billrun_key']);
				if ($params['aid'] == $rawData['aid'] && in_array($rawData['sid'], [$params['sid'], 0]) && $startTime == $rawData['from']->sec && $endTime == $rawData['to']->sec) {
					$ret[] = $rawData;
				}
			}
		}
		return !empty($ret) ? $ret : false;
	}

	protected function buildUpdateBalance($balance, $volumeToSubstract, $totalsUsage = array(), $balanceCost = 0) {
		$isMainBlalance = isset($balance['balance']['totals']);
		$update = array();
		foreach ($volumeToSubstract as $group => $usaget) {
			foreach ($usaget as $usageType => $usagev) {
				if (isset($balance['balance']['groups'][$group])) {
					$usedUsage = isset($balance['balance']['groups'][$group]['usagev']) ? $balance['balance']['groups'][$group]['usagev'] : $balance['balance']['groups'][$group]['cost'];
					$usage = min($usagev['usage'] + ($this->alreadyUpdated[$group]['usage'] ?? 0), $usedUsage);
					$count = min($usagev['count'] + ($this->alreadyUpdated[$group]['count'] ?? 0), $balance['balance']['groups'][$group]['count']);
					$update['$set']['balance.groups.' . $group . '.left'] = $balance['balance']['groups'][$group]['left'] + $usage;
					$usageToSet = $usedUsage - $usage;
					if (isset($balance['balance']['groups'][$group]['usagev'])) {
						$update['$set']['balance.groups.' . $group . '.usagev'] = $usageToSet;
					} else if (isset($balance['balance']['groups'][$group]['cost'])) {
						$update['$set']['balance.groups.' . $group . '.cost'] = $usageToSet;
					}
					$update['$set']['balance.groups.' . $group . '.count'] = $balance['balance']['groups'][$group]['count'] - $count;
					$this->alreadyUpdated[$group]['usage'] = $usage;
					$this->alreadyUpdated[$group]['count'] = $count;
				}
			}
		}

		if ($isMainBlalance) {
			foreach ($totalsUsage as $usageType => $usage) {
				if (isset($usage['usage'])) {
					$update['$set']['balance.totals.' . $usageType . '.usagev'] = $balance['balance']['totals'][$usageType]['usagev'] - $usage['usage'];
				}
				if (isset($usage['cost'])) {
					$update['$set']['balance.totals.' . $usageType . '.cost'] = $balance['balance']['totals'][$usageType]['cost'] - $usage['cost'];
				}
				if (isset($usage['count'])) {
					$update['$set']['balance.totals.' . $usageType . '.count'] = $balance['balance']['totals'][$usageType]['count'] - $usage['count'];
				}
				$update['$set']['balance.cost'] = $balance['balance']['cost'] - $balanceCost;
				if (isset($usage['out_group'])) {
					$update['$set']['balance.totals.' . $usageType . '.out_group.usagev'] = $balance['balance']['totals'][$usageType]['out_group']['usagev'] - $usage['out_group']['usage'];
				}
				if (isset($usage['over_group'])) {
					$update['$set']['balance.totals.' . $usageType . '.over_group.usagev'] = $balance['balance']['totals'][$usageType]['over_group']['usagev'] - $usage['over_group']['usage'];
				}
			}
		}

		return $update;
	}

	protected function resetExtendedBalances($aids, $balancesColl) {
		if (empty($this->extendedBalanceUsageSubtract)) {
			return;
		}
		$verifiedArray = Billrun_Util::verify_array($aids, 'int');
		$aidsAsKeys = array_flip($verifiedArray);
		$balancesToUpdate = array_intersect_key($this->extendedBalanceUsageSubtract, $aidsAsKeys);
		$queryBalances = array(
			'aid' => array('$in' => $aids),
			'period' => array('$ne' => 'default')
		);
		$balances = $balancesColl->query($queryBalances)->cursor();
		foreach ($balancesToUpdate as $aid => $packageUsage) {
			foreach ($packageUsage as $balanceId => $usageByUsaget) {
				$relevantBalances = $this->getRelevantBalances($balances, $balanceId);
				if (empty($relevantBalances)) {
					continue;
				}
				foreach ($relevantBalances as $balanceToUpdate) {
					if (empty($balanceToUpdate)) {
						continue;
					}
					$updateData = $this->buildUpdateBalance($balanceToUpdate, $usageByUsaget);
					$query = array(
						'_id' => new MongoId($balanceId),
					);
					Billrun_Factory::log('Resetting extended balance for aid: ' .  $aid . ', balance_id: ' . $balanceId, Zend_Log::DEBUG);
					$balancesColl->update($query, $updateData);
				}
			}
		}

		$this->extendedBalanceUsageSubtract = array();
	}

	protected function resetDefaultBalances($aids, $balancesColl) {
		if (empty($this->balanceSubstract)) {
			return;
		}
		$queryBalances = array(
			'aid' => array('$in' => $aids),
			'period' => 'default'
		);

		$balances = $balancesColl->query($queryBalances)->cursor();
		foreach ($this->balanceSubstract as $aid => $usageBySid) {
			foreach ($usageBySid as $sid => $usageByMonth) {
				foreach ($usageByMonth as $billrunKey => $usage) {
					$relevantBalances = $this->getRelevantBalances($balances, '', array('aid' => $aid, 'sid' => $sid, 'billrun_key' => $billrunKey));
					foreach ($relevantBalances as $balanceToUpdate) {
						if (empty($balanceToUpdate)) {
							continue;
						}
						$groups = !empty($usage['groups']) ? $usage['groups'] : array();
						$totals = !empty($usage['totals']) ? $usage['totals'] : array();
						$cost = !empty($usage['cost']) ? $usage['cost'] : 0;
						$updateData = $this->buildUpdateBalance($balanceToUpdate, $groups, $totals, $cost);
						if (empty($updateData)) {
							continue;
						}
						
						$query = array(
							'_id' => $balanceToUpdate['_id'],
						);
						Billrun_Factory::log('Resetting default balance for sid: ' .  $sid . ', billrun: ' . $billrunKey, Zend_Log::DEBUG);
						$ret = $balancesColl->update($query, $updateData);
					}
				}
			}
		}
		$this->balanceSubstract = array();
		return $ret;
	}

	protected function initBalances($aids) {
		$queryBalances = array(
			'aid' => array('$in' => $aids),
		);

		$balances = Billrun_Factory::db()->balancesCollection()->query($queryBalances)->cursor();
		foreach ($balances as $balance) {
			$balanceId = $balance->getRawData()['_id']->{'$id'};
			$this->balances[$balanceId] = $balance;
		}
	}

	protected function isInExtendedBalance($arategroups) {
		$arategroupBalances = array_column($arategroups, 'balance_ref');
		foreach ($arategroupBalances as $balanceRef) {
			$balanceId = $balanceRef['$id']->{'$id'};
			if (isset($this->balances[$balanceId]) && $this->balances[$balanceId]['period'] != 'default') {
				return true;
			}
		}

		return false;
	}

	protected function isInMainBalance($arategroups) {
		$arategroupBalances = array_column($arategroups, 'balance_ref');
		foreach ($arategroupBalances as $balanceRef) {
			$balanceId = $balanceRef['$id']->{'$id'};
			if ($this->isMainBalance($balanceId)) {
				return true;
			}
		}

		return false;
	}

	protected function isMainBalance($balanceId) {
		return isset($this->balances[$balanceId]) &&
			$this->balances[$balanceId]['period'] == 'default' &&
			!empty($this->balances[$balanceId]['balance']['totals']);
	}
	
	protected function buildConditionsQuery($updateAids) {
		if (empty($this->conditions)) {
			return array();
		}
		$conditionsQuery = array();
		$groupedAids = array();
		$conditionsHashArray = array();
		foreach ($this->conditions as $aid => $conditions) {
			if (!in_array($aid, $updateAids)) {
				continue;
			}
			$conditionsHashArray[key($conditions)] = current($conditions);
			$groupedAids[key($conditions)][] = $aid;		
		}
		foreach ($groupedAids as $conditionHash => $aids) {
			$translatedCondition = $this->translateConditionArrayToQuery($conditionsHashArray[$conditionHash]);
			$conditionsQuery['$or'][] = array_merge(
				array('aid' => array('$in' => $aids)),
				$translatedCondition
			);
		}
		
		return $conditionsQuery;
	}
	
	protected function translateConditionArrayToQuery($conditionArray) {
		foreach ($conditionArray as $orCondValue) {
			$andStructure = array();
			foreach ($orCondValue as $andCondValue) {
				$andStructure[] = $this->translateCondition($andCondValue);
			}
			$orStructure[] = array('$and' => $andStructure);
		}
		
		return array('$or' => $orStructure);
	}
	
	protected function translateCondition($condition) {
		$op = $condition['op'];
		return array($condition['field_name'] => array("$op" => $condition['value']));
	}

}
