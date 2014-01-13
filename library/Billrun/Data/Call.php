<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract generator ilds class
 * require to generate xml for each account
 * require to generate csv contain how much to credit each account
 *
 * @package  Billing
 * @since    0.5
 */
class Billrun_Data_Call extends Billrun_Data {
	

	static public function getLastCallMade($limitations= array()) {
		$query = array_merge(array('type'=> 'generated_call'),$limitations);
		return new Billrun_Data_Call(Billrun_Factory::db()->linesCollection()->query($query)->cursor()->sort(array('urt' => -1))->limit(1)->current());
	}
	
	public function isActive() {
		return $this->data['stage'] != 'call_done' && $this->data['caller_end_result'] != 'call_killed';
	}
	
	/**
	 * Save q call made/received to  the DB.
	 * @param Array $calls containing the call recrods of the calls  that where made/received
	 * @return boolean
	 */
	protected function save($action, $call, $isCalling, $stage='call_done') {
		//Billrun_Factory::log("Saving call.");
		$call['execution_end_time'] = new MongoDate(round(microtime(true)));
		$direction = $isCalling ? 'caller' : 'callee';
		$commonRec = array_merge($action, array('test_id' => $this->testId, 'date' => date('Ymd'), 'source' => 'generator', 'type' => 'generated_call'));
		$commonRec['stamp'] = md5(serialize($commonRec));
		$callData = array('stage' => $stage);
		foreach ($call as $key => $value) {
			$callData["{$direction}_{$key}"] = $value;
		}
		if ($isCalling) {
			$callData['urt'] = $call['call_start_time'] ? $call['call_start_time'] : $call['execution_start_time'];
		}
		if( ($ret = $this->safeSave(array('type' => 'generated_call', 'stamp' => $commonRec['stamp']), $callData, array_merge($callData, $commonRec))) ) {
			Billrun_Factory::log('Successfully saved.');
		}
		
		return $ret;
	}
	
	/**
	 * Save  with  findAndModifiy  to safely handle  concurrent db access.
	 * @param type $query the query to find the item to save.
	 * @param type $updateData the  data  to update the item with if  it exists in the DB
	 * @param type $newData the  data to create the  item in the  db  with
	 * @return boolean true  if the  save was successful  false otherwise.
	 */
	protected function safeSave($query, $updateData, $newData) {
		$linesCollec = Billrun_Factory::db()->linesCollection();
		//$varifiyField = array_keys($updateData)[0];
		if (!($ret = $linesCollec->findAndModify(	$query, 
													array('$setOnInsert' => $newData), 
													array(), 
													array('upsert' => true, 'new' => true)) ) || 
			$ret->isEmpty() || count(array_diff( $newData, $ret->getRawData() )) ) {

			if (!($ret = $linesCollec->findAndModify(	$query, 
														array('$set' => $updateData), 
														array(), 
														array('upsert' => false, 'new' => true)) ) || $ret->isEmpty()) {

				Billrun_Factory::log('Failed when trying to save : ' . print_r($updateData, 1));
				return false;
				
			}
		}		
		return true;
	}
}
