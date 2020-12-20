<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
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
			$suggestionType = $this->getTypeOfSuggestionForLine($line);
			if(!empty($suggestionType)){
				$suggestions[] = $this->buildSuggestion($line, $suggestionType);
			}
		}
		Billrun_Factory::log()->log('finished to search suggestions for ' . $this->getRecalculateType(), Zend_Log::INFO);
		if(!empty($suggestions)){
			//insert suggestions to db. (new collection)
			//אם קיימת חפיפה עם הצעה ישנה צריך לעדכן את ההצעה הישנה 
			//לשים לב לסטטוס של ההצעה הישנה  +
		}
	}
	
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
		$matchingLines = array();
		$now = new MongoDate();
		foreach ($retroactiveChanges as $retroactiveChange){
			$filters = array_merge(
				array(
					'urt' => array(
						'$gt' => $retroactiveChange['new']['from'],
						'$lt' => ($retroactiveChange['new']['to'] <  $now ? $retroactiveChange['new']['to'] : $now)
					),
					$this->getFieldNameOfLine() => $retroactiveChange['key'],
					'in_queue' => array('$ne' => true)
				), $this->addFiltersToFindMatchingLines($retroactiveChange));
			$query = array(
				array(
					'$match' => $filters
				),
				array(
					'$group' => array(
						'_id' => array(
							'aid' => '$aid',
							'sid' => '$sid',
							'billrun' => '$billrun',
							$this->getFieldNameOfLine() => '$' . $this->getFieldNameOfLine()
						),
						'urt' => array(
							'$min' => '$urt'
						),
						'aprice' => array(
							'$sum' => '$aprice'
						),
						'usagev' => array(
							'$sum' => '$usagev'
						)
					)
				),
				array(
					'$project' => array(
						'_id' => 0,
						'aid' => '$_id.aid',
						'sid' => '$_id.sid',
						'billrun' => '$_id.billrun',
						$this->getFieldNameOfLine() => '$_id.' . $this->getFieldNameOfLine(),
						'urt' => 1,
						'aprice' => 1,
						'usagev' => 1
					)
				),
			);
			$lines = iterator_to_array(Billrun_Factory::db()->linesCollection()->aggregate($query));
			$matchingLines = array_merge($matchingLines, $lines);
		}
		return $matchingLines;
	}
	
	protected function getTypeOfSuggestionForLine($line){
		$billrun_key = $line['billrun'];
		//lines which have been included already in a confirmed billing cycle - suggest immediate invoice
		if (Billrun_Billingcycle::getCycleStatus($billrun_key) === 'confirmed'){
			return 'immediate_invoice';
		}
		//lines which have been included already in a running/finished billing cycle - do not suggest anything.
		if (Billrun_Billingcycle::getCycleStatus($billrun_key) === 'running'  ||
			Billrun_Billingcycle::getCycleStatus($billrun_key) === 'finished'){
			return;
		}
		//lines which haven't been included yet in a billing cycle - suggest rebalance
		return 'rebalance';
	}
	
	protected function buildSuggestion($line, $suggestionType){
		//params to search the suggestions and params to for creating onetimeinvoice/rebalance.  
		$keyName = $this->getFieldNameOfLine();
		$suggestion =  array(
			'recalculationType' => $this->getRecalculateType(),
			'aid' => $line['aid'],
			'sid' => $line['sid'],
			'billrun_key' => $line['billrun'],
			'urt' => $line['urt'],
			'usagev' => $line['usagev'],
			$keyName => $line[$keyName],
			'status' => 'open'
		);
		if($suggestionType === 'rebalance'){
			return $this->buildRebalanceSuggestion($suggestion);
		}
		if($suggestionType === 'immediate_invoice'){
			//todo:: what to do when amount is zero 
			return $this->buildImmediateInvoiceSuggestion($suggestion, $line);
		}	
	}
	
	protected function buildRebalanceSuggestion(&$suggestion) {
		$suggestion['suggestionType'] = 'rebalance';
		return $suggestion;
	}

	protected function buildImmediateInvoiceSuggestion(&$suggestion, $line) {
		$oldPrice = $line['aprice'];
		$newPrice = $this->recalculationPrice($line);
		$amount = $newPrice - $oldPrice;
		$suggestion['suggestionType'] = 'immediate_invoice';
		$suggestion['amount'] = $amount;
		$suggestion['type'] = $amount > 0 ? 'debit' : 'credit';
		return $suggestion;
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
	
	protected function addFiltersToFindMatchingLines($retroactiveChange) {
		return array();
	}

	abstract protected function checkIfValidRetroactiveChange($retroactiveChange);
	
	abstract protected function getCollectionName();
	
	abstract protected function getFieldNameOfLine();
	
	abstract protected function recalculationPrice($line);
	
	abstract protected function getRecalculateType();
}