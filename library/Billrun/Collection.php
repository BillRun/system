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
	use Billrun_Traits_ConditionsCheck;


	public function collect($aids = array(), $collectDir = '') {
		$account = Billrun_Factory::account();
		$processes = Billrun_Factory::config()->getConfigValue('collection.processes', array());
		$minDebt = $this->getMinDebtOfAllProcesses($processes);
		$debtByAids = Billrun_Bill::getContractorsInCollection($aids, $minDebt);
		$values = array_values(array_map(function($debtByAid) {
			return $debtByAid->getRawData()['aid'];
		}, $debtByAids));
		$debtCondition = [
			'field' => 'aid',
			'op' => 'in',
			'value' => $values
		];
		$query = Billrun_Account::convertConditionsToAccountQuery([$debtCondition]);
		$query['read_preference'] = 'RP_PRIMARY'; 
		Billrun_Factory::log()->log("Processing accounts that are actually in collection", Zend_Log::DEBUG);
		$accountsInConditions = $account->loadAccountsForQuery($query);
		$aidsAlreadyProcess = [];
		$updateCollectionStateChangedByProcess = [];
		$markedAsInCollection = [];
		$reallyInCollection = [];
		$matchProcess = null;
		foreach ($accountsInConditions as $accountInConditions){
			if (in_array($accountInConditions['aid'], $aidsAlreadyProcess)){
				continue;
			}
			foreach ($processes as $processIndex => $process){
				$conditions = $process['conditions'][0]['account']['fields'] ?? [];
				if($this->isConditionsMeet($accountInConditions->getRawData(), $conditions)){
					$matchProcess = $process;
					break;
				}
			}
			if(!$matchProcess){
				continue;
			}
			$processMinDebt = floatval($matchProcess['settings']['min_debt'] ?? '10');

			$aid = $accountInConditions['aid'];
			if($accountInConditions['in_collection'] == true ){
				$includeAids = $account->getIncludedInCollection([$aid ]);
				if(in_array($aid, $includeAids)){
					$markedAsInCollection[$aid] = $accountInConditions;
				}
			}
			if(isset($debtByAids[$aid]) && $debtByAids[$aid]['total'] >= $processMinDebt){
				$reallyInCollection[$aid] = $debtByAids[$aid];
			}
			$aidsAlreadyProcess[] = $aid;
			if ($collectDir == 'enter_collection' || empty($collectDir)) {
				$updateCollectionStateChangedByProcess[$processIndex]['in_collection'] = array_diff_key($reallyInCollection, $markedAsInCollection);
			}	
			if ($collectDir == 'exit_collection' || empty($collectDir)) {
				$updateCollectionStateChangedByProcess[$processIndex]['out_of_collection'] = array_diff_key($markedAsInCollection, $reallyInCollection);
			}
		}
		
		Billrun_Factory::log()->log("Updating crm if needed", Zend_Log::DEBUG);
		foreach($updateCollectionStateChangedByProcess as $processIndex => $updateCollectionStateChanged){
			$matchProcess = $processes[$processIndex];
			$result[$matchProcess['label']] = $account->updateCrmInCollection($updateCollectionStateChanged, $matchProcess);
		}
		return $result;
	}

	protected function getMinDebtOfAllProcesses($processes){
		$minDebt = floatval($processes[0]['settings']['min_debt'] ?? '10');
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
