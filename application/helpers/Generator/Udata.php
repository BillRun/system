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

class Generator_Udata extends Billrun_Generator {
	
	use Billrun_Traits_FileActions;
	
	static $type = 'udata';
	
	protected $data = null;
	protected $grouping = array();
	protected $match = array();
	protected $translations = array();
	protected $fieldDefinitions =  array();
	protected $preProject = array();
	protected $unwind = array();

	public function __construct($options) {
		parent::__construct($options);
		$config = Billrun_Factory::config()->getConfigValue('udata',array());
		foreach($config['generator']['match'] as $idx => $query) {
			foreach($query as  $key => $val) {
				$this->match['$or'][$idx][$key] = json_decode($val,JSON_OBJECT_AS_ARRAY);
			}
		}
		$this->match['mediated.'.static::$type] = array('$exists' => 0);
		
		$this->grouping = array('_id'=> array());
		$this->grouping['_id'] = array_merge($this->grouping['_id'],$this->translateJSONConfig($config['generator']['grouping']));	
		$this->grouping = array_merge($this->grouping,$this->translateJSONConfig($config['generator']['mapping']));
			
		foreach($config['generator']['helpers'] as  $key => $mapping) {
			$mapArr = json_decode($mapping,JSON_OBJECT_AS_ARRAY);
			if(!empty($mapArr)) {
				$this->grouping[$key] = $mapArr;
			}
		}
		
		$this->fieldDefinitions = $this->translateJSONConfig(Billrun_Factory::config()->getConfigValue('udata.generator.field_definitions', array()));
		$this->translations = $this->translateJSONConfig(Billrun_Factory::config()->getConfigValue('udata.generator.translations', array()));
		$this->preProject = $this->translateJSONConfig(Billrun_Factory::config()->getConfigValue('udata.generator.pre_project', array()));
		$this->unwind = $this->translateJSONConfig(Billrun_Factory::config()->getConfigValue('udata.generator.unwind', ''));
		
		$this->archiveDb = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('archive.db', array()));
	}
	
	public function generate() {
		$fileData = $this->getNextFilename();
		$fh = $this->openFile($fileData['filename']);
		foreach($this->data as $line) {
			$this->writeRowToFile($this->translateCdrFields($line, $this->translations), $this->fieldDefinitions, $fh);
			$this->markLines($line['stamps']);
		}
		$this->closeFile($fh,$fileData);
	}

	public function load() {
		$fields = array();
		$fieldExamples =  $this->archiveDb->archiveCollection()->query( $this->match  )->cursor()->limit(100);
		foreach( $fieldExamples as $doc ) {
			foreach( $doc->getRawData() as $key => $val ) {
				$fields[$key] = 1;
			}
		}
		if(!empty($fields)) {
			
			$this->data = $this->archiveDb->archiveCollection()->aggregateCursor( array('$match' => $this->match ),
																				array('$project'=> array_merge($fields, $this->preProject ) ),
																				//array('$unwind' => $this->unwind),
																				array('$sort'=>array('urt'=> 1)),
																				array('$group'=> $this->grouping)
																			//	array('$match' => array('helper.record_type' => 'final_request'))
																			 );
		} else {
			Billrun_Factory::log(" Generator::Udata - Couldn't find any usage.",Zend_Log::WARN);
			$this->data = array();
		}
	}
	
	public function getNextFilename() {
		$lastFile = Billrun_Factory::db()->logCollection()->query(array('source'=>'udata'))->cursor()->sort(array('seq'=>-1))->limit(1)->current();
		$seq = (empty($lastFile['seq']) ? 0 : $lastFile['seq']);
		$seq++;
		
		return array( 'seq' => $seq , 'filename' =>  'SASN_PREP_'.sprintf('%05d',$seq).'_'.date('YmdHis') , 'source' => static::$type);
	}
	
	
	
	//--------------------------------------------  Protected ------------------------------------------------
	
	protected function translateJSONConfig($config) {		
		$retConfig = $config;
		if(is_array($config)) {
			foreach($config as $key => $mapping) {
				if(is_array($mapping)) {
					$retConfig[$key] = $this->translateJSONConfig($mapping);

				} else {
					$decodedJson = json_decode($mapping,JSON_OBJECT_AS_ARRAY);
					if(!empty($decodedJson)) {
						$retConfig[$key] = $decodedJson;
					} else if($decodedJson !== null) {
						unset($retConfig[$key]);
					}
				}
			}
		}
		return $retConfig;
	}
	
	protected function translateCdrFields($line,$translations) {
		foreach($translations as $key => $trans) {
			switch( $trans['type'] ) {			
				case 'function' :
					if(method_exists($this,$trans['translation']['function'])) {
						$line[$key] = $this->{$trans['translation']['function']}( $line[$key], $trans['translation']['values'] , $line );
					}
					break;
				case 'regex' :
				default :
						$line[$key] = preg_replace(key($trans['translation']), reset($trans['translation']), $line[$key]);
					break;
			}			
		}
		return $line;
	}
	
	protected function writeRowToFile($row, $fieldDefinitions ,$fh ) {
		$str ='';		
		$empty= true;		
		foreach($fieldDefinitions as $field => $definition) {
			$fieldFormat = !empty($definition) ? $definition :  '%s' ;
			$empty &= empty($row[$field]);
			$fieldStr = sprintf($fieldFormat ,  (isset($row[$field]) ? $row[$field] : '') );
			$str .= $fieldStr;
		}
		if(!$empty) {
			$this->writeToFile($fh, $str.PHP_EOL);
		} else {
			Billrun_Factory::log("BIReport got an empty line : ".print_r($row,1),Zend_Log::WARN);
		}
	}

	
	protected function openFile($filename) {
		return fopen($this->export_directory.DIRECTORY_SEPARATOR. $filename, 'w+');
	}
	
	
	protected function writeToFile($fh,$str) {
		Billrun_Factory::log($str);
		fwrite($fh, mb_convert_encoding($str, "UTF-8", "HTML-ENTITIES"));
	}
	
	protected function closeFile($fh,$fileData) {
		fclose($fh);
		$this->logDB($fileData);
	}
	
	
	protected function markLines($stamps) {
		$query = array('stamp'=> array('$in'=> $stamps));
		$update = array('$set' => array( 'mediated.'.static::$type => new MongoDate() ));
		try {
			$result = $this->archiveDb->archiveCollection()->update($query,$update,array('multiple'=>1));
		} catch(Exception $e) {
			#TODO : implement error handling
		}
		
	}
	
	/**
	 * method to log the processing
	 * 
	 * @todo refactoring this method
	 */
	protected function logDB($fileData) {
		Billrun_Factory::dispatcher()->trigger('beforeLogGeneratedFile', array(&$fileData, $this));
		
		$data = array(
			'stamp' => Billrun_Util::generateArrayStamp($fileData),
			'file_name' =>  $fileData['filename'],
			'seq' => $fileData['seq'],
			'source' => $fileData['source'],
			'received_hostname' => Billrun_Util::getHostName(),
			'received_time' => date(self::base_dateformat),
		);

		if (empty($data['stamp'])) {
			Billrun_Factory::log("Billrun_Receiver::logDB - got file with empty stamp :  {$data['stamp']}", Zend_Log::NOTICE);
			return FALSE;
		}

		try {
			$log = Billrun_Factory::db()->logCollection();
			$result = $log->insert(new Mongodloid_Entity($data));

			if ($result['ok'] != 1 ) {
				Billrun_Factory::log("Billrun_Receiver::logDB - Failed when trying to update a file log record " . $data['file_name'] . " with stamp of : {$data['stamp']}", Zend_Log::NOTICE);
			}
		} catch (Exception $e) {
			//TODO : handle exceptions
		}
		Billrun_Factory::log("Billrun_Receiver::logDB - logged the generation of : " . $data['file_name'] , Zend_Log::INFO);
		return $result['ok'] == 1 ;
	}
	
	// ------------------------------------ Helpers -----------------------------------------
	// 
	
	protected function fieldQueries($queries, $line) {
		foreach ($queries as $query) {
			$match = true;
			foreach ($query as $fieldKey => $regex) {
				$match &= preg_match($regex, $line[$fieldKey]);
			}
			if($match) {
				return TRUE;
			}
		}
		return FALSE;
	}




	protected function isLineEligible($param) {
		//// Not Network
		//if ( ( rec.getNodeId().substring( 11,12 ).equals( "u" ) &&
		//rec.getgpp_GGSN_Address() == null)
		//|| ( rec.getNodeId().substring( 11,12 ).equals( "c" ) && rec.getUserName()== null ) )
		//{
		//return false ;
		//}
	}
	
	protected function transalteDuration($value, $parameters, $line) {
		return date($parameters['date_format'],$line[$parameters['end_field']]->sec - $line[$parameters['start_field']]->sec);
	}
	
	protected function transalteUrt($value,$dateFormat) {
		return date($dateFormat,$value->sec);
	}

	protected function transalteRatingGroup($value, $mapping, $line) {
		$retVal = $value;
		if(!empty($mapping)) {
			foreach ($mapping as $possibleRet => $queries) {
				if($this->fieldQueries($queries, $line)) {
					$retVal = $possibleRet;
					break;
				}
			
			}
		}
		return  $retVal;
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
		$retVal = $value;
		if(!empty($mapping)) {
			foreach ($mapping as $possibleRet => $queries) {
				if($this->fieldQueries($queries, $line)) {
					$retVal = $possibleRet;
					break;
				}
			
			}
		}
		return  $retVal;
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
	
	protected function lacTranslation($param) {
		//if ( RecIn.isgppUserLocationInfoSet() )
		// if (RecIn.getgppUserLocationInfo().length() >4 )
		// {
		// Record.setLocationAreaCode( RecIn.getgppUserLocationInfo().substring(0,5 ) ) ;
		// Record.setCellIdentifier(RecIn.getgppUserLocationInfo().substring(0,5 ) ) ;
		// } else {
		// Record.setLocationAreaCode(RecIn.getgppUserLocationInfo() ) ;
		// Record.setCellIdentifier(RecIn.getgppUserLocationInfo() ) 
		// }		
	}
	
}