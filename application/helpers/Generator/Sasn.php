<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
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
	static $ONE_GB = 1073741824;
	protected $data = null;
	protected $startEndWindow = 12800;

	public function __construct($options) {
		parent::__construct($options);
		$this->startEndWindow = Billrun_Factory::config()->getConfigValue(static::$type . '.generator.start_end_window', $this->startEndWindow);
	}

	public function generate() {
		$fileData = $this->getNextFileData();
		$this->writeRows();
		$this->logDB($fileData);
	}

	public function getNextFileData() {
		$seq = $this->getNextSequenceData(static::$type);

		return array('seq' => $seq, 'filename' => 'SASN_PREP_' . sprintf('%05.5d', $seq) . '_' . date('YmdHis',$this->startTime), 'source' => static::$type);
	}

	//--------------------------------------------  Protected ------------------------------------------------

	protected function writeRows() {
		foreach ($this->data as $line) {
			if ($line['data_volume_gprs_downlink'] > static::$ONE_GB) {
				while ($line['data_volume_gprs_downlink'] > 0) {
					$brokenLine = $line->getRawData();
					$brokenLine['orig_data_volume_gprs_downlink'] = $brokenLine['orig_data_volume_gprs_downlink'] > 0 
												? ($line['orig_data_volume_gprs_downlink'] > static::$ONE_GB ? static::$ONE_GB  :  $line['orig_data_volume_gprs_downlink']) 
												: 0;
					$brokenLine['data_volume_gprs_downlink'] = $line['data_volume_gprs_downlink'] > static::$ONE_GB ? static::$ONE_GB : $line['data_volume_gprs_downlink'];
					$this->writeRowToFile($this->translateCdrFields($brokenLine, $this->translations), $this->fieldDefinitions);
					$line['record_opening_time'] = new Mongodloid_Date($line['record_opening_time']->sec + 1);
					$line['data_volume_gprs_downlink'] -= static::$ONE_GB;
					$line['orig_data_volume_gprs_downlink'] -= static::$ONE_GB;
				}
			} else {
				$this->writeRowToFile($this->translateCdrFields($line, $this->translations), $this->fieldDefinitions);
			}
			//$this->markLines($line['stamps']);
		}
		$this->markFileAsDone();
	}

	protected function getReportCandiateMatchQuery() {
		return array('$and' => array(
				array('$or' => array(
						array('urt' => array('$gt' => new Mongodloid_Date($this->getLastRunDate(static::$type)->sec - $this->startEndWindow)), 'record_type' => array('$ne' => 'final_request')),
						array('urt' => array('$gt' => $this->getLastRunDate(static::$type)))
					))
			)
		);
	}

	protected function getReportFilterMatchQuery() {
		return array('change_date_time' => array('$lt' => new Mongodloid_Date($this->startTime), '$gte' => $this->getLastRunDate(static::$type)));
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
