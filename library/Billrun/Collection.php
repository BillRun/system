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

	/**
	 * the minimum debt an account must have to be in collection, when the process (settings.min_debt) or the
	 * configuration (collection.settings.min_debt) does not set one
	 */
	const DEFAULT_MIN_DEBT = 10;


	protected $reallyInCollectionAids = [];
	public function collect($aids = array(), $collectDir = '') {

		$account = Billrun_Factory::account();
		Billrun_Factory::log()->log("Pulling accounts that are subject to collection", Zend_Log::DEBUG);
		$markedAsInCollection = $account->getInCollection($aids);
		Billrun_Factory::log()->log("Processing accounts that are actually in collection", Zend_Log::DEBUG);
		$processes = Billrun_Factory::config()->getConfigValue('collection.processes', array());
		$minDebt = $this->getMinDebtOfAllProcesses($processes);
		// with change_state_include_debt the state change requests carry the balance of the accounts, so the debt query returns
		// every account with a balance - debt or credit, the credit offsetting the debt - and the minimum debt is applied here
		$collectionStateChangeWithDebt = !empty(array_filter($processes, array(Billrun_Factory::collectionSteps(), 'isChangeStateWithDebt')));
		$balanceByAids = Billrun_Bill::getContractorsInCollection($aids, $minDebt, $collectionStateChangeWithDebt);
		// only the accounts that may be in collection (at least the minimum debt of some process) need their account for the conditions
		$aidsValues = array_keys(array_filter($balanceByAids, function($balanceByAid) use ($minDebt) {
			return $balanceByAid['total'] >= $minDebt;
		}));

		$updateCollectionStateChangedByProcess = [];
		$query['read_preference'] = 'RP_PRIMARY';
		$accountsInConditions = $account->loadAccountsByAidsWithBatches($aidsValues, $query);

		$updateCollectionStateChangedByProcess = $this->getUpdateCollectionStateChangedByProcess(array_merge($accountsInConditions, $markedAsInCollection), $balanceByAids, $collectDir);
		Billrun_Factory::log()->log("Updating crm if needed", Zend_Log::DEBUG);
		$result = [];
		foreach($updateCollectionStateChangedByProcess as $processIndex => $updateCollectionStateChanged){
			$matchProcess = $processes[$processIndex];
			$result[$matchProcess['label']] = $account->updateCrmInCollection($updateCollectionStateChanged, $matchProcess, $balanceByAids);
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
			$processMinDebt = floatval($matchProcess['settings']['min_debt'] ?? self::DEFAULT_MIN_DEBT);

			$aid = intval($accountInConditions['aid']);
			if(isset($accountInConditions['in_collection']) && $accountInConditions['in_collection'] == true ){
				$markedAsInCollectionByProcess[$processIndex][$aid] = $accountInConditions;
			}
			if(isset($debtByAids[$aid]) && $debtByAids[$aid]['total'] >= $processMinDebt){
				$reallyInCollectionByProcess[$processIndex][$aid] = $debtByAids[$aid];
				$this->reallyInCollectionAids[] = $aid;
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
		$minDebt = floatval($processes[0]['settings']['min_debt'] ?? self::DEFAULT_MIN_DEBT);
		if(!isset($minDebt)){
			return $minDebt;
		}
		foreach ($processes as $process){
			$minDebt = min(floatval($process['settings']['min_debt'] ?? self::DEFAULT_MIN_DEBT), $minDebt); 
		}
		return $minDebt;
	}
	
	public static function getInstance() {
		return new Billrun_Collection();
	}

}
