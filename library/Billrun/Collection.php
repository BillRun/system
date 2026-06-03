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


	protected $reallyInCollectionAids = [];
	public function collect($aids = array(), $collectDir = '') {

		$account = Billrun_Factory::account();
		Billrun_Factory::log()->log("Pulling accounts that are subject to collection", Zend_Log::DEBUG);
		$markedAsInCollection = $account->getInCollection($aids);
		Billrun_Factory::log()->log("Processing accounts that are actually in collection", Zend_Log::DEBUG);
		$processes = Billrun_Factory::config()->getConfigValue('collection.processes', array());
		$minDebt = $this->getMinDebtOfAllProcesses($processes);
		$debtByAids = Billrun_Bill::getContractorsInCollection($aids, $minDebt);
		$aidsValues = array_values(array_map(function($debtByAid) {
			return $debtByAid->getRawData()['aid'];
		}, $debtByAids));

		$updateCollectionStateChangedByProcess = [];

		$gadBatchLimit = Billrun_Factory::config()->getConfigValue('subscribers.account.gad_limit', false, "int");
		if ($gadBatchLimit) {
			Billrun_Factory::log("Found gad batch limit of size " . $gadBatchLimit, Zend_Log::DEBUG);
		} else {
			Billrun_Factory::log("Couldn't find gad batch limit", Zend_Log::DEBUG);
		}
		$aidsBatches = array_chunk($aidsValues, $gadBatchLimit);
		Billrun_Factory::log("Got " . count($aidsBatches) . " aids chunks" , Zend_Log::DEBUG);
		$accountsInConditions = [];
		for ($i = 0; $i < count($aidsBatches); $i++) {

			$query = ['aid' => array('$in' => $aidsBatches[$i])];
			$query['read_preference'] = 'RP_PRIMARY';
			$accountsInConditions = array_merge($account->loadAccountsForQuery($query), $accountsInConditions);
		}
		$updateCollectionStateChangedByProcess = $this->getUpdateCollectionStateChangedByProcess(array_merge($accountsInConditions, $markedAsInCollection), $debtByAids, $collectDir);
		Billrun_Factory::log()->log("Updating crm if needed", Zend_Log::DEBUG);
		$result = [];
		foreach($updateCollectionStateChangedByProcess as $processIndex => $updateCollectionStateChanged){
			$matchProcess = $processes[$processIndex];
			$result[$matchProcess['label']] = $account->updateCrmInCollection($updateCollectionStateChanged, $matchProcess);
		}
		if(empty($aids)){
			$this->removeAllCollectionStepsOfCustomerNotInCollection();
		}
		return $result;
	}

	protected function removeAllCollectionStepsOfCustomerNotInCollection(){
		Billrun_Factory::log()->log("Removing any future collection steps for a customer not in collection of all processes", Zend_Log::DEBUG);
		$collectionSteps = Billrun_Factory::collectionSteps();
		$res = $collectionSteps->removeCollectionSteps(array('$nin' => $this->reallyInCollectionAids));
	}

	/**
	 * Determines the accounts whose collection state needs to be updated based on matching processes
	 * and current debt status. It checks which accounts should enter or exit collection according to
	 * the defined collection processes.
	 *
	 * @param array $accountsInConditions List of accounts that are either marked as in collection or whose debts exceed
	 *                                    the minimum debt defined in any of the processes.
	 * @param array $debtByAids An associative array of debts indexed by account ID (aid), where each item contains aid debt.
	 * @param string $collectDir Optional direction for collection state change. 
	 *                           Can be 'enter_collection', 'exit_collection', or empty for both directions.
	 *
	 * @return array A multi-dimensional array grouped by process index with keys:
	 *               - 'in_collection': accounts that need to be marked as in collection
	 *               - 'out_of_collection': accounts that need to be marked as out of collection
	 */
	protected function getUpdateCollectionStateChangedByProcess ($accountsInConditions, $debtByAids, $collectDir){
		$processes = Billrun_Factory::config()->getConfigValue('collection.processes', array());
		$aidsAlreadyProcess = [];
		$updateCollectionStateChangedByProcess = [];
		$markedAsInCollectionByProcess = [];
		$reallyInCollectionByProcess= [];
		$matchProcess = null;
		foreach ($accountsInConditions as $accountInConditions){
			if (isset($aidsAlreadyProcess[$accountInConditions['aid']])){
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
			if(isset($accountInConditions['in_collection']) && $accountInConditions['in_collection'] == true ){
				$markedAsInCollectionByProcess[$processIndex][$aid] = $accountInConditions;
			}
			if(isset($debtByAids[$aid]) && $debtByAids[$aid]['total'] >= $processMinDebt){
				$reallyInCollectionByProcess[$processIndex][$aid] = $debtByAids[$aid];
				$this->reallyInCollectionAids[]= $aid;
			}
			$aidsAlreadyProcess[$aid] = true;
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
