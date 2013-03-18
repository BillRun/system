<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Ggsn
 *
 * @author eran
 */
class Billrun_Processor_Ggsn extends Billrun_Processor_Base_Binary {
	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'ggsn';
	
	const HEADER_LENGTH = 54;
	const MAX_CHUNKLENGTH_LENGTH = 512;
	const FILE_READ_AHEAD_LENGTH = 8196;

	public function __construct($options) {
		parent::__construct($options);
	}

	protected function parse() {

		// run all over the file with the parser helper
		if (!is_resource($this->fileHandler)) {
			Billrun_Factory::log()->log('Resource is not configured well', Zend_Log::ERR);
			return false;
		}

		$this->data['header'] = $this->buildHeader(fread($this->fileHandler, self::HEADER_LENGTH));

		$bytes = null;
		do {
			if ( !feof($this->fileHandler) && !isset($bytes[self::MAX_CHUNKLENGTH_LENGTH]) ) {
				$bytes .= fread($this->fileHandler, self::FILE_READ_AHEAD_LENGTH);
			}

			$row = $this->buildDataRow($bytes);
			if ($row) {
				$this->data['data'][] = $row;
			}

			$bytes = substr($bytes, $this->parser->getLastParseLength());
		} while (isset($bytes[self::HEADER_LENGTH]));
		
		$this->data['trailer'] = $this->buildTrailer($bytes);

		return true;
	}

}

?>
