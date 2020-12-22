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
		$retroactiveChanges = $this->findAllTheRetroactiveChanges();
		$suggestions = $this->getSuggestions($retroactiveChanges);
		Billrun_Factory::log()->log('finished to search suggestions for ' . $this->getRecalculateType(), Zend_Log::INFO);
		if(!empty($suggestions)){
			$this->addSuggestionsToDb($suggestions);
		}
	}
	
	protected function getSuggestions($retroactiveChanges){
		$suggestions = [];
		$lines = $this->findAllMatchingLines($retroactiveChanges);
		foreach ($lines as $line){
			$suggestionType = $this->getTypeOfSuggestionForLine($line);
			if(!empty($suggestionType)){
				$suggestions[] = $this->buildSuggestion($line, $suggestionType);
			}
		}
		return $suggestions;
	}
	
	protected function findAllTheRetroactiveChanges(){
		Billrun_Factory::log()->log("Searching all the retroactive rate changes", Zend_Log::INFO);
		$query = array(
			'collection' => $this->getCollectionName(),
			'suggest_recalculations' => array('$ne' => true),
			//TODO:: check all the relevant types (update/permanentchange through GUI / rates importer / API) 
			'type' => array('$in' => ['update', 'closeandnew', 'permanentchange']),
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
		$retroactiveChanges = iterator_to_array(Billrun_Factory::db()->auditCollection()->find($query)->sort(array('_id' => 1)));
		Billrun_Factory::db()->auditCollection()->update($query, $update, array('multiple' => true));	
		
		$validRetroactiveChanges = $this->getValidRetroactiveChanges($retroactiveChanges);
		
		Billrun_Factory::log()->log("found " . count($retroactiveChanges) . " retroactive rate changes", Zend_Log::INFO);
		return $validRetroactiveChanges;
	}


	protected function findAllMatchingLines($retroactiveChanges) {
		$matchingLines = array();
		$now = new MongoDate();
		foreach ($retroactiveChanges as $retroactiveChange){
			$filters = array_merge(
				array(
					'urt' => array(
						'$gte' => $retroactiveChange['new']['from'],
						'$lt' => ($retroactiveChange['new']['to'] <  $now ? $retroactiveChange['new']['to'] : $now)
					),
					$this->getFieldNameOfLine() => $retroactiveChange['key'],
					'in_queue' => array('$ne' => true)
				), $this->addFiltersToFindMatchingLines());
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
							'key' => '$' . $this->getFieldNameOfLine()
						),
						'from' => array(
							'$min' => '$urt'
						),
						'to' => array(
							'$max' => '$urt'
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
						'key' => '$_id.key',
						'from' => 1,
						'to' => 1,
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
		$suggestion =  array(
			'recalculationType' => $this->getRecalculateType(),
			'aid' => $line['aid'],
			'sid' => $line['sid'],
			'billrun_key' => $line['billrun'],
			'from' => $line['from'],
			'to' => new MongoDate(strtotime('+1 sec', $line['to']->sec)),
			'usagev' => $line['usagev'],
			'key' => $line['key'],
			'status' => 'open',
		);
		if($suggestionType === 'rebalance'){
			$this->buildRebalanceSuggestion($suggestion);
		}
		if($suggestionType === 'immediate_invoice'){
			//todo:: what to do when amount is zero 
			$this->buildImmediateInvoiceSuggestion($suggestion, $line);
		}
		$suggestion['stamp'] = $this->getSuggestionStamp($suggestion);
		$suggestion['urt'] = new MongoDate();
		return $suggestion;
	}
	
	protected function buildRebalanceSuggestion(&$suggestion) {
		$suggestion['suggestionType'] = 'rebalance';
	}

	protected function buildImmediateInvoiceSuggestion(&$suggestion, $line) {
		$oldPrice = $line['aprice'];
		$newPrice = $this->recalculationPrice($line);
		$amount = $newPrice - $oldPrice;
		$suggestion['suggestionType'] = 'immediate_invoice';
		$suggestion['amount'] = abs($amount);
		$suggestion['type'] = $amount > 0 ? 'debit' : 'credit';
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
	
	protected function addFiltersToFindMatchingLines() {
		return array();
	}
	
	protected function addSuggestionsToDb($suggestions){
		Billrun_Factory::log()->log("Adding suggestions to db", Zend_Log::INFO);
		foreach($suggestions as $suggestion){
			$overlapSuggestion = $this->getOverlap($suggestion);
			if(!$overlapSuggestion->isEmpty()){
				if($this->checkIfTheSameSuggestion($overlapSuggestion, $suggestion)){
					continue;
				}else{
					$this->handleOverlapSuggestion($overlapSuggestion, $suggestion);
				}
			}else{
				Billrun_Factory::db()->suggestionsCollection()->insert($suggestion);
			}
		}
		Billrun_Factory::log()->log("finished adding suggestions to db", Zend_Log::INFO);
	}

	protected function getOverlap($suggestion) {
		$query = array(
			'aid' => $suggestion['aid'],
			'sid' => $suggestion['sid'],
			'billrun_key' => $suggestion['billrun_key'],
			'key' => $suggestion['key'],
			'status' => 'open',
			'recalculationType' => $suggestion['recalculationType']
		);
		return Billrun_Factory::db()->suggestionsCollection()->query($query)->cursor()->limit(1)->current();
	}
	
	protected function checkIfTheSameSuggestion($overlapSuggestion, $suggestion) {
		return $overlapSuggestion['stamp'] === $suggestion['stamp'];
	}
	
	protected function handleOverlapSuggestion($overlapSuggestion, $suggestion) {
		
		$fakeRetroactiveChanges = $this->buildFakeRetroactiveChanges($overlapSuggestion, $suggestion);
		$newSuggestions = $this->getSuggestions($fakeRetroactiveChanges);
		$newSuggestion = $this->unifyOverlapSuggestions($newSuggestions);
		Billrun_Factory::db()->suggestionsCollection()->insert($newSuggestion);
		Billrun_Factory::db()->suggestionsCollection()->remove($overlapSuggestion);

	}

	protected function buildFakeRetroactiveChanges($overlapSuggestion, $suggestion){
		$fakeRetroactiveChanges = array();
		$fakeRetroactiveChange['key'] = $overlapSuggestion['key']; //equal to suggestion['key']
		$oldFrom = min($overlapSuggestion['from'], $suggestion['from']);
		$newFrom = max($overlapSuggestion['from'], $suggestion['from']);
		$oldTo = min($overlapSuggestion['to'], $suggestion['to']);
		$newTo = max($overlapSuggestion['to'], $suggestion['to']);
		if($oldFrom !== $newFrom){
			$fakeRetroactiveChange['new']['from'] = $oldFrom;
			$fakeRetroactiveChange['new']['to'] =  new MongoDate(strtotime('-1 sec', $newFrom->sec));
			$fakeRetroactiveChanges[] = $fakeRetroactiveChange;
		}
		if($oldTo !== $newTo){
			$fakeRetroactiveChange['new']['from'] = $oldTo;
			$fakeRetroactiveChange['new']['to'] = $newTo;
			$fakeRetroactiveChanges[] = $fakeRetroactiveChange;
		}
		if($newFrom !== $oldTo){
			$fakeRetroactiveChange['new']['from'] = $newFrom;
			$fakeRetroactiveChange['new']['to'] =  $oldTo;
			$fakeRetroactiveChanges[] = $fakeRetroactiveChange;
		}
		return $fakeRetroactiveChanges;
	}
	
	protected function unifyOverlapSuggestions($suggestions) {
		$newSuggestion = $suggestions[0];
		$newSuggestion['usagev'] = 0;
		if($newSuggestion['suggestionType'] === 'rebalance'){
			$this->unifyOverlapRebalanceSuggestions($newSuggestion, $suggestions);
		}else{//suggestionType equal to immediate invoice
			$this->unifyOverlapImmediateInvoiceSuggestions($newSuggestion, $suggestions);
		}
		return $newSuggestion; 
	}

	protected function unifyOverlapRebalanceSuggestions(&$newSuggestion, $suggestions) {
		foreach ($suggestions as $suggestion){
			if($suggestion['suggestionType'] !== 'rebalance'){
				throw new Exception("Something went wrong. all the suggestion must to have the same suggestionType");
			}
			$this->unifyOverlapSuggestion($newSuggestion, $suggestion);
		}
	}

	protected function unifyOverlapImmediateInvoiceSuggestions(&$newSuggestion, $suggestions) {
		$aprice = 0;
		foreach ($suggestions as $suggestion){
			if($suggestion['suggestionType'] !== 'immediate_invoice'){
				throw new Exception("Something went wrong. all the suggestion must to have the same suggestionType");
			}
			$aprice += $suggestion['type'] === 'credit' ? (0 - $suggestion['amount']) : $suggestion['amount'];
			$this->unifyOverlapSuggestion($newSuggestion, $suggestion);
		}
		$newSuggestion['type'] = $aprice < 0 ? 'credit' : 'debit';
		$newSuggestion['amount'] = abs($aprice);
	}

	protected function unifyOverlapSuggestion(&$newSuggestion, $suggestion) {
		$newSuggestion['from'] = min($suggestion['from'], $newSuggestion['from']);
		$newSuggestion['to'] = max($suggestion['to'], $newSuggestion['to']);
		$newSuggestion['usagev'] += $suggestion['usagev'];
	}
	
	protected function getSuggestionStamp($suggestion){
		unset($suggestion['urt']);
		unset($suggestion['stamp']);
		return Billrun_Util::generateArrayStamp($suggestion);
	}

	abstract protected function checkIfValidRetroactiveChange($retroactiveChange);
	
	abstract protected function getCollectionName();
	
	abstract protected function getFieldNameOfLine();
	
	abstract protected function recalculationPrice($line);
	
	abstract protected function getRecalculateType();
}