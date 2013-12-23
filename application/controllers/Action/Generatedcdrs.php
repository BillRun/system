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
		Billrun_Factory::log()->log("Execute Generated CDRs Action", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		$data = $this->parseData($request);
		switch($request['action']) {
			 case 'sync_calls':
					$loadedLines = $this->loadLocalLines($data);
					$savedLines = $this->saveLinesToRemoteDB($data['remote_db'], $loadedLines);
					$stamps = array_map(function($obj) {return $obj['stamp'];} , $savedLines);					
					$removedLines =  $this->removeLocalLines($stamps);
					$this->getController()->setOutput($removedLines);
				break;

			case 'get_calls':
					$loadedLines = $this->loadLocalLines($data);
					$this->getController()->setOutput($localLines);
				break;
			
			case 'remove_calls':
					$this->getController()->setOutput($this->getController()->setOutput( $this->removeLocalLines($data['calls_to_remove']) ));
					

				break;
			
		}
		Billrun_Factory::log()->log("Finished Executing Generated CDRs Action", Zend_Log::INFO);
		return true;

	}
	
	/**
	 * Parse the json data from the request and add need values to it.
	 * @param type $request
	 * @return \MongoDate
	 */
	protected function parseData($request) {
		$data = json_decode($request['data'],true);

		return $data;
	}
	
	public function loadLocalLines($data) {
		$localLines =  Billrun_Factory::db()->linesCollection()->query(array('type'=> 'generated_call',
																			'$or' => array(
																					array('urt' => array(
																						'$gt' => new MongoDate($data['from']),
																						'$lte' => new MongoDate(min($data['to'], time() - static::CALL_CLOSER_PADDING)),
																					)),
																					array('urt' => array('$gt' => new MongoDate($data['from'])),'stage' => 'call_done'),
																				)
																	))->cursor();
		return $localLines;
	}

	public function saveLinesToRemoteDB($dbCreds,$calls	) {
		$savedCalls = array();
		$linesColl = Billrun_Factory::db($dbCreds)->linesCollection();
		foreach ($calls as $call) {

			unset($call['_id']);			
			try {
				if( $linesColl->save($call)) {
					$savedCalls[] = $call;
				}				
			} catch( Exception $e) {
				if($e->getCode() == "11000") {
					$loadedCall = $linesColl->query(array('stamp' => $call['stamp']))->cursor()->current();
					$loadedCall->setRawData( array_merge($call,$loadedCall->getRawData()) );
					$loadedCall->save( $linesColl );					
				} else {
					Billrun_Factory::log()->log("Failed  when tryig to save call with stamp : {$call['stamp']}", Zend_Log::INFO);
				}
			}
		}
		return $savedCalls;
	}

	
	public function removeLocalLines($linesStampsToRemove) {
		$removedLines = Billrun_Factory::db()->linesCollection()->remove(array('type'=> 'generated_call',
																		'stamp' => array( '$in' => $linesStampsToRemove)
																	));
		return $removedLines;
	}
}
