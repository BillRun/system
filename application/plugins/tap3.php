<?php
require_once __DIR__ . '/AsnParsing.php';

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
	use AsnParsing;

	protected $name = 'tap3';
	
	protected $nsnConfig = false;
	
	const FILE_READ_AHEAD_LENGTH = 32768;

	public function __construct($options = array()) {
		parent::__construct($options);
		
		$this->nsnConfig = parse_ini_file(Billrun_Factory::config()->getConfigValue('tap3.config_path'), true);
		$this->initParsing();
		$this->addParsingMethods();
	}
	
	/**
	 * @see Billrun_Plugin_Interface_IParser::parseHeader
	 */	
	public function parseHeader($type, $data, \Billrun_Parser &$parser) {
		if($this->getName() != $type) { return FALSE; }
		$header = $this->getASNDataByConfig($data, $this->nsnConfig['header'], $this->nsnConfig['fields'] );	
	
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
			$cdrLine =  $this->getASNDataByConfig($data, $this->nsnConfig[$type], $this->nsnConfig['fields'] );			
			if($cdrLine) {
				$cdrLine['record_type'] = $type;
			}
		} else {
			//Billrun_Factory::log()->log("couldn't find  definition for {$type}",  Zend_Log::DEBUG);
		}
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
		
		$trailer= $this->getASNDataByConfig($data, $this->nsnConfig['trailer'], $this->nsnConfig['fields']);		
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
			Billrun_Factory::log()->log($record->getType() . " : " . count($record->getData()) ,  Zend_Log::DEBUG);
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

}

?>
