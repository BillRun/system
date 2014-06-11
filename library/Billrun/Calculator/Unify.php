<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing calculator class that will unifiy several cdrs to s single cdr if possible.
 * The class is basic rate that can evaluate record rate by different factors
 * 
 * @package  calculator
 * @since    0.5
 *
 */
class Billrun_Calculator_Unify extends Billrun_Calculator {
	protected $unifiedLines = array();
	protected $unificationFields = array('ggsn' => array(
											'required' => array(
													'fields' => array('sid','aid','ggsn_address','arate','urt'),
													'match' => array('ggsn_addres' => '/^(?=62\.90\.|37\.26\.)/') 
												),
											'date_seperation' => 'Ymd',
											'stamp' => array('sgsn_address','ggsn_address','sid','aid','arate','imsi','plan','rating_group'),
											'fields' => array(
																'$set' => array('process_time'),
																'$setOnInsert' => array('urt','imsi','usagesb','usaget','aid','sid','ggsn_address','sgsn_address','rating_group','arate'),
																'$inc' => array('usagev' , 'aprice','apr','fbc_downlink_volume','fbc_uplink_volume','duration'),
											),
					) );
	protected $archivedLines = array();
	protected $unifiedToRawLines = array();
	protected $dateSeperation = "Ymd";
	protected $acceptArchivedLines = false;
	
	public function __construct($options = array()) {
		parent::__construct($options);
		if(isset($option['date_seperation'])) {
			$this->dateSeperation = $option['date_seperation'];
		}
		
		if(isset($options['unification_fields'])) {
			$this->unificationFields = $options['unification_fields'];
		}
		
		if(isset($options['accept_archived_lines'])) {
			$this->acceptArchivedLines = $options['accept_archived_lines'];
		}
		
	}	

	/**
	 * 
	 * @param type $row
	 * @return type
	 */
	public function updateRow($rawRow) {
		$newRow = $rawRow instanceof Mongodloid_Entity? $rawRow->getRawData() : $rawRaw;
		$updatedRowStamp = $this->getLineUnifiedLineStamp($newRow);
		
		if( $this->isLinesLocked($updatedRowStamp, array($newRow['stamp'])) || 
			(!$this->acceptArchivedLines && $this->isLinesArchived(array($newRow['stamp']))) ) {
				Billrun_Factory::log("Line {$newRow['stamp']} was already applied to unified line $updatedRowStamp",Zend_Log::NOTICE);
				return true;
		}
		
		$updatedRow = $this->getUnifiedRowForSingleRow($updatedRowStamp, $newRow);
		foreach($this->unificationFields[$newRow['type']]['fields'] as $key => $fields) {
			foreach ($fields as $field) {
				if($key == '$inc' && isset($updatedRow[$field])) { 
					$updatedRow[$field] += $newRow[$field];
				} else if($key == '$set' && isset($newRow[$field])) {
					$updatedRow[$field] = $newRow[$field];
				}
			}
		}
		
		$updatedRow['lcount'] += 1;
		$this->unifiedLines[$updatedRowStamp] = $updatedRow;
		$this->unifiedToRawLines[$updatedRowStamp][] = $newRow['stamp'];
		
		$newRow['u_s'] = $updatedRowStamp;
		$this->archivedLines[$newRow['stamp']] = $newRow;
		
		
		return true;
	}
	
	/**
	 * 
	 */
	public function saveLinesToArchive() {
		$failedArchived = array();
		$linesArchivedStamps = array();
		$linesColl = Billrun_Factory::db(array('name' => 'archive'))->linesCollection()->setReadPreference('RP_PRIMARY_PREFERRED');
		$localLines = Billrun_Factory::db()->linesCollection()->setReadPreference('RP_PRIMARY_PREFERRED');
		
		Billrun_Factory::log('Saving '.count($this->archivedLines).' to archive.',Zend_Log::INFO);
		foreach($this->archivedLines as $line) {
			try {			
				$linesColl->insert($line,array('w'=>1));
				$linesArchivedStamps[] = $line['stamp'];
				unset($this->data[$line['stamp']]);
			} catch (\Exception $e) {
				if($e->getCode() == '11000') {
					Billrun_Factory::log("got duplicate line when trying to save line {$line['stamp']} to archive.",Zend_Log::ALERT);
					$linesArchivedStamps[] = $line['stamp'];
					unset($this->data[$line['stamp']]);
				} else {
					Billrun_Factory::log("Failed when trying to save a line {$line['stamp']} to the archive failed with: " . $e->getCode() . " : ".$e->getMessage() ,Zend_Log::ALERT);
					$failedArchived[]= $line;				
				}
			}
		}
		Billrun_Factory::log('Removing Lines from the lines collection....',Zend_Log::INFO);		
		$localLines->remove(array('stamp'=>array('$in' => $linesArchivedStamps)));
		
		return $failedArchived;
	}
	
	/**
	 * 
	 */
	public function updateUnifiedLines() {
		Billrun_Factory::log('Updateing '.count($this->unifiedLines).' unified lines...',Zend_Log::INFO);
		$updateFailedLines = array();
		foreach($this->unifiedLines as $key => $row) {				
			$query = array('stamp'=> $key,'type' => $row['type'],'tx' => array ('$nin' => $this->unifiedToRawLines[$key]));
			$update = array_merge(array(
				'$setOnInsert' => array(
					'stamp' => $key,
					'source' => 'unify',					
					'type' => $row['type'],
					'billrun' => Billrun_Util::getBillrunKey(time())
				),			
			), $this->getlockLinesUpdate($key,$this->unifiedToRawLines[$key]));	
			foreach ($this->unificationFields[$row['type']]['fields'] as $fkey => $fields) {
				foreach ($fields as $field) {
					$update[$fkey][$field] = $row[$field];
				}				
			}
			$update['$inc']['lcount'] = $row['lcount'];
			
			$ret = Billrun_Factory::db()->linesCollection()->setReadPreference('RP_PRIMARY_PREFERRED')->update($query,$update,array('w'=>1,'upsert'=>true));
			if (!($ret && $ret['ok'] && $ret['n'] != 0)) {
				//$this->releaseLines($key,$this->unifiedToRawLines[$key]);
				$updateFailedLines[$key] = array( 'unified' => $row, 'lines' => $this->unifiedToRawLines[$key]);
				foreach ($this->unifiedToRawLines[$key] as $lstamp) {
					unset($this->archivedLines[$lstamp]);
				}
				
				Billrun_Factory::log("Updating unified line $key failed.",Zend_Log::ERR);
			}
		}
		return $updateFailedLines;
	}


	public function write() {
		// update db.lines don't update the queue if  a given line failed.
		foreach( $this->updateUnifiedLines() as $failedLine) {
			foreach($failedLine['lines'] as $stamp) {
				unset($this->lines[$stamp]);			
			}
		}
		
		//add lines to archive 
		$this->saveLinesToArchive();
		
		parent::write();		
	}
	
	/**
	 * Get or create a unified row from a given signle row
	 * @param type $updatedRowStamp the unified stamp that the returned row should have.
	 * @param type $newRow the single row.
	 * @return array containing  a new or existing unified row.
	 */
	protected function getUnifiedRowForSingleRow($updatedRowStamp,$newRow) {
		$type = $newRow['type'];
		if(isset($this->unifiedLines[$updatedRowStamp]))  {
			$existingRow =  $this->unifiedLines[$updatedRowStamp];			
		} else {			
			//Billrun_Factory::log(print_r($newRow,1),Zend_Log::ERR);
			$existingRow = array('lcount' => 0,'type'=> $type);
			foreach($this->unificationFields[$type]['fields'] as $key => $fields) {
				foreach ($fields as $field) {					
					if($key == '$inc') { 					
						$existingRow[$field] = 0;
					} else if(isset($newRow[$field])) {
						$existingRow[$field] = $newRow[$field];
						
					}
				}
			}
		}
		return $existingRow;
	}
	
	/**
	 * Get the unified row stamp for a given single line.
	 * @param type $newRow the single line to extract the unified row stamp from.
	 * @return a string  with the unified row stamp.
	 */
	protected function getLineUnifiedLineStamp($newRow) {
		$str = '';
		$typeData = $this->unificationFields[$newRow['type']];
		foreach($typeData['stamp'] as $key => $field) {			
			$str .= serialize($newRow[$field]);
		}
		return md5( $str . date(( isset($typeData['date_seperation']) ? $typeData['date_seperation'] : $this->dateSeperation),  $newRow['urt']->sec));
	}
	
	public function isLineLegitimate($line) {
		$matched = $line['source'] != 'unify' && isset($this->unificationFields[$line['type']]);
		
		if($matched) {
			$requirements = $this->unificationFields[$line['type']]['required'];
			foreach($requirements['match'] as $field => $regex) {
				if(!preg_match($regex,$line[$field])) {
					$matched = false;
				}
			}
		}
		return 	$matched &&
				//verify that all the required field exists in the line
			   (count(array_intersect(array_keys($line->getRawData()), $requirements['fields'])) == count($requirements['fields'])) ;
	}
	
	/**
	 * 
	 * @param type $unifiedStamp
	 * @param type $lineStamps
	 * @return type
	 */
	protected function isLinesLocked($unifiedStamp, $lineStamps) {
		$query = array('stamp'=> $unifiedStamp,'tx'=>array('$in'=>$lineStamps));
		return !Billrun_Factory::db()->linesCollection()->setReadPreference('RP_PRIMARY_PREFERRED')->query($query)->cursor()->limit(1)->current()->isEmpty() ;		
	}
	
	/**
	 * Check if certain lines already inserted to the archive.
	 * @param type $lineStamps an array containing the line stamps to check
	 * @return boolean true if the line all ready exist in the archive false otherwise.
	 */
	protected function isLinesArchived($lineStamps) {		
		return !Billrun_Factory::db(array('name' => 'archive'))->linesCollection()->setReadPreference('RP_PRIMARY_PREFERRED')->query(array('stamp'=> array('$in'=>$lineStamps)))->cursor()->limit(1)->current()->isEmpty() ;		
	}
	
	/**
	 * Get the update argument/query to lock lines for a unfied line in a the DB.
	 * @param type $unifiedStamp the unifed line to lock the lines in.
	 * @param type $lineStamps the stamps of the lines to lock.
	 * @return array update query to pass on to an update  action.
	 */
	protected function getlockLinesUpdate($unifiedStamp, $lineStamps) {
		$query = array('stamp'=> $unifiedStamp);
		$txarr = array();
		foreach ($lineStamps as $value) {
			$txarr[$value] = true;
		}
		$update = array('$pushAll'=> array('tx' => $lineStamps));
		return $update;
	}
	
	/**
	 * Release lock for given lines in a unified line in the DB.
	 * @param type $unifiedStamp the unified line stamp to release the single line on.
	 * @param type $lineStamps the stamp of the single lines to release from lock.
	 */
	protected function releaseLines($unifiedStamp, $lineStamps) {
		$query = array('stamp'=> $unifiedStamp);
		
		$update = array('$pullAll'=> array('tx' => $lineStamps));
		Billrun_Factory::db()->linesCollection()->setReadPreference('RP_PRIMARY_PREFERRED')->update($query,$update);
	}
	
	/**
	 * 
	 */
	public function releaseAllLines() {
		Billrun_Factory::log('Removing locks from  '.count($this->unifiedToRawLines).' unified lines...',Zend_Log::INFO);
		foreach ($this->unifiedToRawLines as $key => $value) {
			$this->releaseLines($key, $value);
		}
	}

	/**
	 * 
	 * @return type
	 */
	protected function getLines() {
		$types = array_keys($this->unificationFields);
		return $this->getQueuedLines( array('type' => array('$in' => $types)) );
	}
	
	/**
	 * 
	 * @return string
	 */
	public function getCalculatorQueueType() {
		return 'unify';
	}
	
	
	public function removeFromQueue() {
		parent::removeFromQueue();
		$this->releaseAllLines();
	}
			
}
