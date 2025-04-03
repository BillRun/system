<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * BillRun Collection class
 *
 * @package  Billrun
 * @since    5.8
 */
class Billrun_Collection extends Billrun_Base {

	public function collect($aids = array(), $collectDir = '') {
		$account = Billrun_Factory::account();
		Billrun_Factory::log()->log("Pulling accounts that are subject to collection", Zend_Log::DEBUG);
		$processes = Billrun_Factory::config()->getConfigValue('collection.processes', array());
		$minDebt = $this->getMinDebtOfAllProcesses($processes);
		Billrun_Factory::log()->log("Processing accounts that are actually in collection", Zend_Log::DEBUG);
		$debtByAids = Billrun_Bill::getContractorsInCollection($aids, $minDebt);
		$markedAsInCollection = [];
		$reallyInCollection = [];
		foreach ($processes as $process){
			$conditions = $process['conditions']['account']['fields'] ?? [];
			$processMinDebt = floatval($process['settings']['min_debt'] ?? '10');
			$accountsInConditions = $account->loadAccountsForQuery($conditions);
			foreach ($accountsInConditions as $accountInConditions){
				$aid = $accountInConditions->get('aid');
				if($accountInConditions['in_collection'] == true ){
					$includeAids = $account->getIncludedInCollection([$aid ]);
					if(in_array($aid, $includeAids)){
						$markedAsInCollection[$aid] = $accountInConditions;
					}
				}
				if(isset($debtByAids[$aid]) && $debtByAids[$aid]['total'] >= $processMinDebt){
					$reallyInCollection[$aid] = $debtByAids[$aid];
				}
			}
			if ($collectDir == 'enter_collection' || empty($collectDir)) {
				$updateCollectionStateChanged['in_collection'] = array_diff_key($reallyInCollection, $markedAsInCollection);
			}	
			if ($collectDir == 'exit_collection' || empty($collectDir)) {
				$updateCollectionStateChanged['out_of_collection'] = array_diff_key($markedAsInCollection, $reallyInCollection);
			}
			Billrun_Factory::log()->log("Updating crm if needed", Zend_Log::DEBUG);
			$result[$process['name']] = $account->updateCrmInCollection($updateCollectionStateChanged, $process);
		}
		return $result;
	}
	protected function getMinDebtOfAllProcesses($processes){
		$minDebt = floatval($process[0]['settings']['min_debt'] ?? '10');
		if(!isset($minDebt)){
			return $minDebt;
		}
		foreach ($processes as $process){
			$minDebt = min(floatval($process['settings']['min_debt'] ?? '10'), $minDebt); 
		}
		return $minDebt;
	}
	
	public static function getInstance() {
		return new Billrun_Collection();
	}

}
