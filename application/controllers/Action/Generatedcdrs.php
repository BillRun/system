<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Get the generatord CDRs  for a given time period.
 *
 * @author eran
 */
class GeneratedcdrsAction extends Action_Base {
	
	const CALL_CLOSER_PADDING = 600;
	
	/**
	 *
	 */
	public function execute() {
		Billrun_Factory::log()->log("Execute Update Config", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		$data = $this->parseData($request);
		switch($request['action']) {
			 case 'sync_lines':
					$loadedLines = $this->loadLocalLines($data);
					$savedLines = $this->saveLinesToRemoteDB($data['remote_db'], $loadedLines);
					$stamps = array_map(function($obj) {return $obj['stamp'];} , $savedLines);					
					$removedLines =  $this->removeLocalLines($stamps);
				break;

			case 'get_calls':
					$loadedLines = $this->loadLocalLines($data);
					$this->getController()->setOutput($localLines);
				break;
			
			case 'remove_calls':
					$this->getController()->setOutput($this->getController()->setOutput( $this->removeLocalLines($data['calls_to_remove']) ));
					

				break;
			
		}
		return true;

	}
	
	public function loadLocalLines($data) {
		$localLines = Billrun_Factory::db()->linesCollection()->query(array('type'=> 'generated_call','urt' => array(
																		'$gt' => new MongoDate(strtotime($data['from'])),
																		'$lte' => new MongoDate(min(strtotime($data['to'], time() - static::CALL_CLOSER_PADDING))),
																	)));
		return $localLines;
	}

	public function saveLinesToRemoteDB($dbCreds,$lines) {
		$savedCalls = array();
		foreach ($calls as $call) {
			unset($call['_id']);
			try {
				if( Billrun_Factory::db($data['remote_db'])->linesCollection()->save($call)) {
					$savedCalls[] = $call;
				}				
			} catch( Exception $e) {
				//TODO  catch duplicate and handle them.
			}
		}
		return $saveCalls;
	}

	
	public function removeLocalLines($linesStampsToRemove) {
		$removedLines = Billrun_Factory::db()->linesCollection()->remove(array('type'=> 'generated_call',
																		'stamp' => array( '$in' => $linesStampsToRemove)
																	));
		return $removedLines;
	}
}
