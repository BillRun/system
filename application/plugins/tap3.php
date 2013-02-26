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
	
	
	const HEADER_LENGTH = 125;
	const TRAILER_LENGTH = 41;
	const MAX_CHUNKLENGTH_LENGTH = 8196;
	const FILE_READ_AHEAD_LENGTH = 32768;

	public function parseHeader($type, $data, \Billrun_Parser &$parser) {
		if($this->getName() != $type) { return FALSE; }
		$parsedData = Asn_Base::parseASNString($data);
		$parser->setLastParseLength($parsedData->getDataLength()+4);
			Billrun_Factory::log()->log(print_r(Asn_Base::getDataArray($parsedData),1),  Zend_Log::DEBUG);
	}

	
	public function parseData($type, $data, \Billrun_Parser &$parser) {
		if($this->getName() != $type) { return FALSE; }
		$parsedData = Asn_Base::parseASNString($data);
		$parser->setLastParseLength($parsedData->getDataLength()+4);
		Billrun_Factory::log()->log(print_r(($parsedData),1),  Zend_Log::DEBUG);
		
		//die();
	}

	public function parseSingleField($type, $data, array $fieldDesc, \Billrun_Parser &$parser) {
		if($this->getName() != $type) { return FALSE; }
		$parsedData = Asn_Base::parseASNString($data);
		print_r($parsedData);
	}
	
	/**
	 * @see Billrun_Plugin_Interface_IParser::parseTrailer
	 */
	public function parseTrailer($type, $data, \Billrun_Parser &$parser) {
		if($this->getName() != $type) { return FALSE; }
			$parsedData = Asn_Base::parseASNString($data);
		$parser->setLastParseLength($parsedData->getDataLength());
		print_r($parsedData);
	}
	
	/**
	 * @see Billrun_Plugin_Interface_IProcessor::processData
	 */
	public function processData($type, $fileHandle, \Billrun_Processor &$processor) {
		if($this->getName() != $type) { return FALSE; }

		$bytes = fread($fileHandle, self::HEADER_LENGTH);
		$this->data['header'] = $processor->buildHeader($bytes);
		$bytes = substr($bytes, $processor->getParser()->getLastParseLength());
		
		do {
			if ( !feof($fileHandle) && !isset($bytes[self::MAX_CHUNKLENGTH_LENGTH]) ) {
				$bytes .= fread($fileHandle, self::FILE_READ_AHEAD_LENGTH);
			}
			$row = $processor->buildDataRow($bytes);
			if ($row) {
				$this->data['data'][] = $row;
			}
			Billrun_Factory::log()->log("1",Zend_Log::DEBUG);
			$bytes = substr($bytes, $processor->getParser()->getLastParseLength());
		} while (isset($bytes[self::TRAILER_LENGTH]));
		
		$this->data['trailer'] = $processor->buildTrailer($bytes);

		return true;
	}
	
	public function isProcessingFinished($type, $fileHandle, \Billrun_Processor &$processor) {
		if($this->getName() != $type) { return FALSE; }
		return feof($fileHandle);
	}
}

?>
