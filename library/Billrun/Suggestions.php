<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract suggestions class
 *
 * @package  Billing
 */
abstract class Billrun_Suggestions {
	
	protected $suggestRecalculations;
	
	public function __construct() {
		$this->suggestRecalculations = Billrun_Factory::config()->getConfigValue('billrun.suggest_recalculations', false);
	}
	
	//run this in background
	public function recalculateSuggestions(){
		if(!$this->suggestRecalculations){
			Billrun_Factory::log()->log('suggest recalculations mode is off', Zend_Log::INFO);
			return;
		}
		Billrun_Factory::log()->log('Starting to search suggestions for ' . $this->getRecalculateType(), Zend_Log::INFO);
		$suggestions = [];
		$retroactiveChanges = $this->findAllTheRetroactiveChanges();
		$lines = $this->findAllMatchingLines($retroactiveChanges);
		foreach ($lines as $line){
			//todo:: check if line valid rate not override include in service/plan
			$suggestionType = $this->getTypeOfSuggestionForLine($line);
			if(!empty($suggestionType)){
				$suggestions[] = $this->buildSuggestion($line, $suggestionType);
			}
		}
		Billrun_Factory::log()->log('finished to search suggestions for ' . $this->getRecalculateType(), Zend_Log::INFO);
		//insert suggestions to db. (new collection)
	}
	
	abstract protected function getRecalculateType();
	
	protected function findAllTheRetroactiveChanges(){
		Billrun_Factory::log()->log("Searching all the retroactive rate changes", Zend_Log::INFO);
		$query = array(
			'collection' => $this->getCollectionName(),
			'suggest_recalculations' => array('$ne' => true),
			//TODO:: check all the relevant types (update/permanentchange through GUI / rates importer / API) 
			'type' => array('$in' => ['update', 'closeandnew']),
			//retroactive change
			'new.from' => array(
				'$lt' => new MongoDate()
			)
		);
		$update = array(
			'$set' => array(
				'suggest_recalculations' => true
			)
		);
		//check if can be done in one command. 
		$retroactiveChanges = iterator_to_array(Billrun_Factory::db()->auditCollection()->find($query));
		Billrun_Factory::db()->auditCollection()->update($query, $update, array('multiple' => true));	
		
		$validRetroactiveChanges = $this->getValidRetroactiveChanges($retroactiveChanges);
		
		Billrun_Factory::log()->log("found all the retroactive rate changes", Zend_Log::INFO);
		return $validRetroactiveChanges;
	}


	protected function findAllMatchingLines($retroactiveChanges) {
		
	}
	
	protected function getTypeOfSuggestionForLine($line){
		//immediate invoice or rebalance
	}
	
	protected function buildSuggestion($line, $suggestionType){
		//params to search the suggestions and params to for creating onetimeinvoice/rebalance.  
	}
	
	protected function getValidRetroactiveChanges($retroactiveChanges) {
		$validRetroactiveChanges = [];
		foreach ($retroactiveChanges as $retroactiveChange){
			if($this->checkIfValidRetroactiveChange($retroactiveChange)){
				$validRetroactiveChanges[] = $retroactiveChange;
			}
		}
		return $validRetroactiveChanges;
	}
	
	abstract protected function checkIfValidRetroactiveChange($retroactiveChange);
	
	abstract protected function getCollectionName();
}