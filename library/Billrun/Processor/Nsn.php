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
	const FILE_READ_AHEAD_LENGTH = 32768;

	
	public function parse() {
		$bytes= null;
		// run all over the file with the parser helper
		if (!is_resource($this->fileHandler)) {
			$this->log->log("Resource is not configured well", Zend_Log::ERR);
			return false;
		}
		$headerData = fread($this->fileHandler, self::HEADER_LENGTH);
		$header = $this->parser->parseHeader($headerData);
		$this->data['header'] = $this->buildHeader($header);
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
		
		$this->data['trailer'] = $this->buildTrailer($this->parser->parseTrailer($bytes));
		if((0x1ff0 - $header['data_length_in_block']) > 0) {
			fread($this->fileHandler, (0x1ff0 - $header['data_length_in_block']) );
		}
		return true;
	}

	protected function processFinished() {
		return feof($this->fileHandler);
	}
}
?>
