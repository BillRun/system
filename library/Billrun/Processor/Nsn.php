<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Nsn
 *
 * @author eran
 */
class Billrun_Processor_Nsn extends Billrun_Processor_Base_BlockedSeperatedBinary {

	static protected $type = 'nsn';
	
	const HEADER_LENGTH = 41;
	const TRAILER_LENGTH = 24;
	const MAX_CHUNKLENGTH_LENGTH = 8196;
	const RECORD_ALIGNMENT = 0x1ff0;
	
	protected $fileStats = null;
	
	public function parse() {
		$bytes= null;
		
		$headerData = fread($this->fileHandler, self::HEADER_LENGTH);
		//$this->data['header'] = $this->buildHeader($headerData);
		$header = $this->parser->parseHeader($headerData);
		if (isset($header['data_length_in_block']) && !feof($this->fileHandler)) {
			$bytes = fread($this->fileHandler, $header['data_length_in_block'] - self::HEADER_LENGTH );
		}
		
		do {			
			$row = $this->buildDataRow($bytes);
			if ($row) {
				$this->data['data'][] = $row;
			}

			$bytes = substr($bytes,  $this->parser->getLastParseLength());
		} while (isset($bytes[self::TRAILER_LENGTH+1]));
		
		//$this->data['trailer'] = $this->buildTrailer($bytes);
		//align the readhead
		if((self::RECORD_ALIGNMENT- $header['data_length_in_block']) > 0) {
			fread($this->fileHandler, (self::RECORD_ALIGNMENT - $header['data_length_in_block']) );
		}
		
		return true;
	}

	protected function processFinished() {
		return feof($this->fileHandler) ||
				ftell($this->fileHandler) + self::TRAILER_LENGTH >= $this->fileStats['size'];
	}
	
	public function loadFile($file_path) {
		parent::loadFile($file_path);
		$this->fileStats = fstat($this->fileHandler);
	}
}
?>
