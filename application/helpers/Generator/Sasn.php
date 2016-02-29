<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Udata Generator class
 *
 * @package  Models
 * @since    2.1
 */

class Generator_Sasn extends Billrun_Generator_ConfigurableCDRAggregationCsv {
	
	static $type = 'sasn';
	
	protected $data = null;
	protected $grouping = array();
	protected $match = array();
	protected $translations = array();
	protected $fieldDefinitions =  array();
	protected $preProject = array();
	protected $unwind = array();
	
	
	public function generate() {
		$fileData = $this->getNextFileData();
		$this->writeRows();
		$this->logDB($fileData);
	}
	
	public function getNextFileData() {
		$seq = $this->getNextSequenceData(static::$type);
		
		return array( 'seq' => $seq , 'filename' =>  'SASN_PREP_'.sprintf('%05.5d',$seq).'_'.date('YmdHis') , 'source' => static::$type);
	}
	
	//--------------------------------------------  Protected ------------------------------------------------

	protected function writeRows() {
		foreach($this->data as $line) {
			$this->writeRowToFile($this->translateCdrFields($line, $this->translations), $this->fieldDefinitions);
			$this->markLines($line['stamps']);
		}
	}
	
	// ------------------------------------ Helpers -----------------------------------------
	// 
	
	
	protected function transalteDuration($value, $parameters, $line) {
		return date($parameters['date_format'], $line[$parameters['end_field']]->sec - $line[$parameters['start_field']]->sec);
	}
	

	protected function transalteRatingGroup($value, $mapping, $line) {
		return $this->cdrQueryTranslations($value, $mapping, $line);
		// convert Rating Group for RAMI /y Phone	
		//if ( rec.isgpp_IMSISet() && IsPeleIMSI(rec.getgpp_IMSI() , array("rami","yPhone") )) { 
		//	rec.setRatingGroup( 92 ) ; 
		//	}
			// GO_GLOBAL Records
		//if ( rec.getContentType() == 9026 )
		//{
		//if ( rec.getRoamingFlag() == 1 ) {
		//// Update group for GO_GLOBAL
		//rec.setRatingGroup( 80 ) ;
		//} else {
		//// Drop Go Global in Israel
		//rec.setRatingGroup( 90 ) ;
		//}
		//} 
		//	if ( (rec.getRatingGroup() == 90 || rec.getRatingGroup() == 99 )
		// && rec.getRoamingFlag() == 1 // OutBound
		// && rec.getContentType() != 9012 // Not MMS
		// && rec.getContentType() != 9013 // Not MMS
		// && rec.getNodeId().substring( 11,12 ).equals( "u" ) ) // From UMTS
		//{
		//// Update Raiting Group For OutBound
		//rec.setRatingGroup( 92 ) ;
		//}
		//if ( rec.getRatingGroup() == 90 || rec.getRatingGroup() == 99 )
		//{
		//return false ;
		//}
		//if ( Const.DropDummyRecords == false )
		//return true ;
		//if ( rec.getDataVolDownlink() + rec.getDataVolUplink() == 0 )
		//{ // Update Drop Falags for droped Volume - Record is Empty
		//return false ;
		//}
		
	}
	
	protected function apnTranslations($value, $mapping, $line) {
		// convert APN for YOUPHONE
		return $this->cdrQueryTranslations($value, $mapping, $line);
		//if ( rec.isgpp_IMSISet() && IsPeleIMSI(rec.getgpp_IMSI() , "yPhone" )) {
		//	if ( rec.getRatingGroup() == 401 ) 
		//		rec.setCalledStationId( "VASYOUPHONE") ;
		//	else if ( rec.getRatingGroup() == 402 ) 
		//		rec.setCalledStationId( "DATAYOUPHONE") ;
		//	else if ( rec.getRatingGroup() == 403 )
		//		rec.setCalledStationId( "MMSYOUPHONE") ;
		//	else
		//		rec.setCalledStationId( "DATAYOUPHONE") ;
		//}		
	}
	

}