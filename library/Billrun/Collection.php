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
		Billrun_Factory::log()->log("Pulling accounts that are subject to collection", Zend_Log::DEBUG);
		$markedAsInCollection = $account->getInCollection($aids);
		Billrun_Factory::log()->log("Processing accounts that are actually in collection", Zend_Log::DEBUG);
		$processes = Billrun_Factory::config()->getConfigValue('collection.processes', array());
		$minDebt = $this->getMinDebtOfAllProcesses($processes);
		$debtByAids = Billrun_Bill::getContractorsInCollection($aids, $minDebt);
		$debtCondition = [
			'field' => 'aid',
			'op' => 'in',
			'value' => array_values(array_map(function($debtByAid) {
				return $debtByAid->getRawData()['aid'];
			}, $debtByAids))
		];
		$query = Billrun_Account::convertConditionsToAccountQuery([$debtCondition]);
		$query['read_preference'] = 'RP_PRIMARY'; 
		$updateCollectionStateChangedByProcess = [];

		$gad_batch_limit = Billrun_Factory::config()->getConfigValue('subscribers.account.gad_limit', false, "int");
		if ($gad_batch_limit) {
			Billrun_Factory::log("Found gad batch limit of size " . $gad_batch_limit, Zend_Log::DEBUG);
		} else {
			Billrun_Factory::log("Couldn't find gad batch limit", Zend_Log::DEBUG);
		}
		$aids_batches = array_chunk($customersAids, $gad_batch_limit);
		Billrun_Factory::log("Got " . count($aids_batches) . " aids chunks" , Zend_Log::DEBUG);
		
		$accountsInConditions = $account->loadAccountsForQuery($query);
		
		$updateCollectionStateChangedByProcess = array_merge_recursive($this->getUpdateCollectionStateChangedByProcess(array_merge($accountsInConditions, $markedAsInCollection), $debtByAids), $updateCollectionStateChangedByProcess);

		Billrun_Factory::log()->log("Updating crm if needed", Zend_Log::DEBUG);
		$result = [];
		foreach($updateCollectionStateChangedByProcess as $processIndex => $updateCollectionStateChanged){
			$matchProcess = $processes[$processIndex];
			$result[$matchProcess['label']] = $account->updateCrmInCollection($updateCollectionStateChanged, $matchProcess);
		}
		return $result;
	}

	protected function getUpdateCollectionStateChangedByProcess ($accountsInConditions, $debtByAids){
		$processes = Billrun_Factory::config()->getConfigValue('collection.processes', array());
		$aidsAlreadyProcess = [];
		$updateCollectionStateChangedByProcess = [];
		$markedAsInCollectionByProcess = [];
		$reallyInCollectionByProcess= [];
		$matchProcess = null;
		foreach ($accountsInConditions as $accountInConditions){
			if (in_array($accountInConditions['aid'], $aidsAlreadyProcess)){
				continue;
			}
			foreach ($processes as $processIndex => $process){
				$conditions = $process['conditions'][0]['account']['fields'] ?? [];
				if ($accountInConditions instanceof Mongodloid_Entity) {
					$accountInConditions = $accountInConditions->getRawData();
				}
				if($this->isConditionsMeet($accountInConditions, $conditions)){
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
				$markedAsInCollectionByProcess[$processIndex][$aid] = $accountInConditions;
			}
			if(isset($debtByAids[$aid]) && $debtByAids[$aid]['total'] >= $processMinDebt){
				$reallyInCollectionByProcess[$processIndex][$aid] = $debtByAids[$aid];
			}
			$aidsAlreadyProcess[] = $aid;
			if ($collectDir == 'enter_collection' || empty($collectDir)) {
				$updateCollectionStateChangedByProcess[$processIndex]['in_collection'] = array_diff_key($reallyInCollectionByProcess[$processIndex] ?? [], $markedAsInCollectionByProcess[$processIndex] ?? []);
			}	
			if ($collectDir == 'exit_collection' || empty($collectDir)) {
				$updateCollectionStateChangedByProcess[$processIndex]['out_of_collection'] = array_diff_key($markedAsInCollectionByProcess[$processIndex] ?? [], $reallyInCollectionByProcess[$processIndex] ?? []);
			}
		}
		return $updateCollectionStateChangedByProcess;
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
