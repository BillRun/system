<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of tap3
 *
 * @author eran
 */
class tap3Plugin  extends Billrun_Plugin_BillrunPluginBase
					implements	Billrun_Plugin_Interface_IParser,  
								Billrun_Plugin_Interface_IProcessor {

	protected $name = 'tap3';
	
	protected $nsnConfig = false;
	
	const HEADER_LENGTH = 125;
	const TRAILER_LENGTH = 84;
	const MAX_CHUNKLENGTH_LENGTH = 16384;
	const FILE_READ_AHEAD_LENGTH = 32768;

	public function __construct($options = array()) {
		parent::__construct($options);
		
		$this->nsnConfig = parse_ini_file(Billrun_Factory::config()->getConfigValue('tap3.config_path'), true);
	}
	
	/**
	 * @see Billrun_Plugin_Interface_IParser::parseHeader
	 */	
	public function parseHeader($type, $data, \Billrun_Parser &$parser) {
		if($this->getName() != $type) { return FALSE; }
		$parsedData = Asn_Base::parseASNString($data);
		$parser->setLastParseLength($parsedData->getDataLength()+4);
		foreach($this->nsnConfig['header'] as $key => $val) {
			$header[$key] = $this->parseASNData(explode(',', $val), Asn_Base::getDataArray( $parsedData,true));
		}
		Billrun_Factory::log()->log(print_r($header,1),  Zend_Log::DEBUG);
		Billrun_Factory::log()->log(print_r(Asn_Base::getDataArray($parsedData,true),1),  Zend_Log::DEBUG);
	
		
	}

	/**
	 * @see Billrun_Plugin_Interface_IParser::parseData
	 */	
	public function parseData($type, $data, \Billrun_Parser &$parser) {
		if($this->getName() != $type) { return FALSE; }
		$parsedData = Asn_Base::parseASNString($data);
		$parser->setLastParseLength($parsedData->getDataLength()+4);
		Billrun_Factory::log()->log($parsedData->getType() . ' : ' .  print_r(Asn_Base::getDataArray($parsedData,true),1),  Zend_Log::DEBUG);
		
		//die();
	}
	
	/**
	 * @see Billrun_Plugin_Interface_IParser::parseSingleField
	 */
	public function parseSingleField($type, $data, array $fieldDesc, \Billrun_Parser &$parser) {
		if($this->getName() != $type) { return FALSE; }
		$parsedData = Asn_Base::parseASNString($data);
		//	Billrun_Factory::log()->log(print_r(Asn_Base::getDataArray($parsedData),1),  Zend_Log::DEBUG);
	}
	
	/**
	 * @see Billrun_Plugin_Interface_IParser::parseTrailer
	 */
	public function parseTrailer($type, $data, \Billrun_Parser &$parser) {
		if($this->getName() != $type) { return FALSE; }
		$parsedData = Asn_Base::parseASNString($data);
		$parser->setLastParseLength($parsedData->getDataLength());
		Billrun_Factory::log()->log(print_r(Asn_Base::getDataArray($parsedData,true),1),  Zend_Log::DEBUG);
	}
	
	/**
	 * @see Billrun_Plugin_Interface_IProcessor::processData
	 */
	public function processData($type, $fileHandle, \Billrun_Processor &$processor) {
		if($this->getName() != $type) { return FALSE; }

		$processorData = &$processor->getData();
		$bytes = fread($fileHandle, self::HEADER_LENGTH);
		$processorData['header'] = $processor->buildHeader($bytes);
		$bytes = substr($bytes, $processor->getParser()->getLastParseLength());
		
		do {
			if ( !feof($fileHandle) && !isset($bytes[self::MAX_CHUNKLENGTH_LENGTH]) ) {
				$bytes .= fread($fileHandle, self::FILE_READ_AHEAD_LENGTH);
			}
			$row = $processor->buildDataRow($bytes);
			if ($row) {
				$processorData['data'][] = $row;
			}
			$bytes = substr($bytes, $processor->getParser()->getLastParseLength());
		} while ( !feof($fileHandle) || isset($bytes[self::TRAILER_LENGTH]));

		$processorData['trailer'] = $processor->buildTrailer($bytes);

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
	 * convert the actual data we got from the ASN record to a readable information
	 * @param $asnData the parsed ASN.1 recrod.
	 * @return Array conatining the fields in the ASN record converted to readableformat and keyed by they're use.
	 */
	protected function parseASNData($struct, $asnData) {
		if(  preg_match("/\[(\w+)\]/",$struct[0],$matches) || !is_array($asnData)) {
			$ret = $this->parseField( $asnData, $this->nsnConfig['fields'][$matches[1]]);
			return $ret;
		}
		foreach ($struct as $val) {

			if (isset($asnData[$val])) {
					//$this->log->log(" digging into : $key", Zend_Log::DEBUG);
					$newStruct = $struct;
					array_shift($newStruct);
					return $this->parseASNData($newStruct, $asnData[$val]);
				} 
		}

		return false;
	}
	
	
	/**
	 * parse a field from raw data based on a field description
	 * @param string $data the raw data to be parsed.
	 * @param array $fileDesc the field description
	 * @return mixed the parsed value from the field.
	 */
	protected function parseField($data, $fileDesc) {
		$type = $fileDesc; 
		$length = 0;//$fileDesc[$type];
		$retValue = '';
		print_r($fileDesc);
		switch($type) {
			case 'decimal' :
					$retValue = 0;
					for($i=$length-1; $i >= 0 ; --$i) {
						$retValue = ord($data[$i]) + ($retValue << 8);
					}
				break;
				
			case 'phone_number' :
					$val = '';
					for($i=0; $i < $length ; ++$i) {
						$byteVal = ord($data[$i]);
						$left = $byteVal & 0xF;
						$right = $byteVal >> 4;
						$digit =  $left == 0xA ? "*" : 
									($left == 0xB ? "#" :
									($left > 0xC ? dechex($left-2) :
									 $left));
						$digitRight =  $right == 0xA ? "*" : 
									($right == 0xB ? "#" :
									($right > 0xC ? dechex($right-2) :
									 $right));
						$val .=  $digit . $digitRight;
					}
					$retValue = str_replace('d','',$val);
				break;
				
			case 'long':
					$retValue = 0;
					for($i=$length-1; $i >= 0 ; --$i) {
						$retValue = bcadd(bcmul($retValue , 256 ), ord($data[$i]));
					}
				break;
				
			case 'hex' :
					$retValue ='';
					for($i=$length-1; $i >= 0  ; --$i) {
						$retValue .= dechex(ord($data[$i]));
					}
				break;
				
			case 'bcd_encode' :
					$retValue = '';
					for($i=$length-1; $i >= 0 ;--$i) {
						$byteVal = ord($data[$i]);
						$retValue .=  ((($byteVal >> 4) < 10) ? ($byteVal >> 4) : '' ) . ((($byteVal & 0xF) < 10) ? ($byteVal & 0xF) : '') ;
					}
					if($type == 'bcd_number') {
						$retValue = intval($retValue,10);
					}
					break;	
					
			case 'format_ver' :
					$retValue =$data[0]. $data[1].ord($data[2]).'.'.ord($data[3]).'-'.ord($data[4]);
				break;
			
			case 'ascii':
					$retValue = $data;
				break;
			case 'ascii_number':
					$retValue = intval($data,10);
				break;
			
			case 'integer':
				$retValue = unpack("I",$data);
			break;
			case 'debug':
					$retValue = unpack("C*",$data);
				break;
		}
		
		return $retValue;		
	}
}

?>
