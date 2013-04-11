<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * This a plguin to provide TAP3 CDRs support to the billing system.
 *
 * @author eran
 */
class tap3Plugin  extends Billrun_Plugin_BillrunPluginBase
					implements	Billrun_Plugin_Interface_IParser,  
								Billrun_Plugin_Interface_IProcessor {
	
	use Billrun_Traits_AsnParsing;
	use Billrun_Traits_FileSequenceChecking;

	protected $name = 'tap3';
	
	protected $nsnConfig = false;
	
	const FILE_READ_AHEAD_LENGTH = 65535;

	public function __construct($options = array()) {
		$this->nsnConfig = (new Yaf_Config_Ini(Billrun_Factory::config()->getConfigValue('tap3.config_path')))->toArray();		
		$this->initParsing();
		$this->addParsingMethods();
	}
	
	/**
	 * back up retrived files that were processed to a third patry path.
	 * @param \Billrun_Processor $processor the processor instace contain the current processed file data. 
	 */
	public function afterProcessorStore(\Billrun_Processor $processor) {
		if($processor->getType() != $this->getName()) { return; } 
		$path = Billrun_Factory::config()->getConfigValue($this->getName().'.thirdparty.backup_path',false,'string');
		if(!$path) return;
		if( $processor->retreivedHostname ) {
			$path = $path . DIRECTORY_SEPARATOR . $processor->retreivedHostname;
		}
		Billrun_Factory::log()->log("Saving file to third party at : $path" , Zend_Log::DEBUG);
		if(!$processor->backupToPath($path ,true) ) {
			Billrun_Factory::log()->log("Couldn't  save file to third patry path at : $path" , Zend_Log::ERR);
		}
	}
	
	/////////////////////////////////////////////// Reciver //////////////////////////////////////
	
	/**
	 * Setup the sequence checker.
	 * @param type $receiver
	 * @param type $hostname
	 * @return type
	 */
	public function beforeFTPReceive($receiver,  $hostname) {
		if($receiver->getType() != $this->getName()) { return; } 
		$this->setFilesSequenceCheckForHost($hostname);
	}
	
	/**
	 * Check recieved file sequences
	 * @param type $receiver
	 * @param type $filepaths
	 * @param type $hostname
	 * @return type
	 */
	public function afterFTPReceived($receiver,  $filepaths , $hostname ) {
		if($receiver->getType() != $this->getName()) { return; }
		$this->checkFilesSeq($filepaths, $hostname);
	}
	
	///////////////////////////////////////////////// Parser //////////////////////////////////
	
	/**
	 * @see Billrun_Plugin_Interface_IParser::parseHeader
	 */	
	public function parseHeader($type, $data, \Billrun_Parser &$parser) {
		if($this->getName() != $type) { return FALSE; }
		$header = $this->parseASNDataRecur( $this->nsnConfig['header'], Asn_Base::getDataArray( $data ,true ), $this->nsnConfig['fields'] );	
	
		return $header;
	}

	/**
	 * @see Billrun_Plugin_Interface_IParser::parseData
	 */	
	public function parseData($type, $data, \Billrun_Parser &$parser) {
		if($this->getName() != $type) { return FALSE; }

		$type = $data->getType();
		$cdrLine = false;
		
		if(isset($this->nsnConfig[$type])) {
			$cdrLine =  $this->parseASNDataRecur( $this->nsnConfig[$type], Asn_Base::getDataArray( $data ,true ), $this->nsnConfig['fields'] );			
			if($cdrLine) {
				$cdrLine['record_type'] = $type;
			}
		} 
		//else { FOR DEBUG
			//Billrun_Factory::log()->log("couldn't find  definition for {$type}",  Zend_Log::DEBUG);
		//}
		//Billrun_Factory::log()->log($data->getType() . " : " . print_r($cdrLine,1) ,  Zend_Log::DEBUG);
		return $cdrLine;
	}
	
	/**
	 * @see Billrun_Plugin_Interface_IParser::parseSingleField
	 */
	public function parseSingleField($type, $data, array $fieldDesc, \Billrun_Parser &$parser) {
		if($this->getName() != $type) { return FALSE; }
		$parsedData = Asn_Base::parseASNString($data);
		//	Billrun_Factory::log()->log(print_r(Asn_Base::getDataArray($parsedData),1),  Zend_Log::DEBUG);
		return $this->parseField($parsedData, $fieldDesc);
	}
	
	/**
	 * @see Billrun_Plugin_Interface_IParser::parseTrailer
	 */
	public function parseTrailer($type, $data, \Billrun_Parser &$parser) {
		if($this->getName() != $type) { return FALSE; }	
		
		$trailer= $this->parseASNDataRecur( $this->nsnConfig['trailer'], Asn_Base::getDataArray( $data ,true ), $this->nsnConfig['fields']);		
		//Billrun_Factory::log()->log(print_r($trailer,1),  Zend_Log::DEBUG);
		
		return $trailer;
	}
	
	/**
	 * add GGSN specific parsing methods.
	 */
	protected function addParsingMethods() {
		$newParsingMethods = array(
				 'raw_data'  => function($data)	{
						return $this->utf8encodeArr($data);
					},
				);
					
		$this->parsingMethods  = array_merge( $this->parsingMethods, $newParsingMethods );
	}

	
	/////////////////////////////////////////// Processor /////////////////////////////////////////
	
	/**
	 * @see Billrun_Plugin_Interface_IProcessor::processData
	 */
	public function processData($type, $fileHandle, \Billrun_Processor &$processor) {
		if($this->getName() != $type) { return FALSE; }

		$processorData = &$processor->getData();
		$bytes= '';
		do {
			$bytes .= fread($fileHandle, self::FILE_READ_AHEAD_LENGTH);
		} while ( !feof($fileHandle));
		$parsedData = Asn_Base::parseASNString($bytes);
		$processorData['header'] = $processor->buildHeader($parsedData);
		//$bytes = substr($bytes, $processor->getParser()->getLastParseLength());

		foreach($parsedData->getData() as  $record ) {			
			if(in_array($record->getType(),$this->nsnConfig['config']['data_records'])) {
				foreach($record->getData() as $key => $data ) {			
					$row = $processor->buildDataRow($data);
					if ($row) {
						$processorData['data'][] = $row;
					}									
				}
			} else {
					//Billrun_Factory::log()->log(print_r($record,1) ,  Zend_Log::DEBUG);
			}
		}

		$processorData['trailer'] = $processor->buildTrailer($parsedData);
		
		return true;
	}
	
	/**
	 * @see Billrun_Plugin_Interface_IProcessor::isProcessingFinished
	 */	
	public function isProcessingFinished($type, $fileHandle, \Billrun_Processor &$processor) {
		if($this->getName() != $type) { return FALSE; }
		return feof($fileHandle);
	}

	/**
	 * Retrive the sequence data  for a ggsn file
	 * @param type $type the type of the file being processed
	 * @param type $filename the file name of the file being processed
	 * @param type $processor the processor instace that triggered the fuction
	 * @return boolean
	 */
	public function getFilenameData($type, $filename, &$processor) {
		if($this->getName() != $type) { return FALSE; }
		return $this->getFileSequenceData($filename);
	}
	
}

?>
