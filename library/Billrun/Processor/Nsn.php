<?php


class Billrun_Processor_Nsn extends Billrun_Processor_Base_Binary {

	use Billrun_Traits_FileSequenceChecking;
	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'nsn';
	
	/**
	 * parser to processor the file
	 * @var Billrun_parser processor class
	 */
	protected $parser = null;
	
	protected $headerLength = null;
	
	protected $trailerLength = null;
	
	protected $nsn_record_alignment = null;
	
	protected $nsnConfig = null;
	
	protected $fileStats = null;
	

	public function __construct($options = array()) {
		parent::__construct($options);
		//TODO - we shouldn't use specific prossesor for NSN, everything should be in the parser.
		$this->nsnConfig = (new Yaf_Config_Ini(Billrun_Factory::config()->getConfigValue('external_parsers_config.nsn')))->toArray();
		$this->headerLength = intval(Billrun_Util::getIn($this->nsnConfig, 'constants.nsn_header_length', 0));
		$this->trailerLength = intval(Billrun_Util::getIn($this->nsnConfig, 'constants.nsn_trailer_length', 0));
		$this->nsn_record_alignment = intval(Billrun_Util::getIn($this->nsnConfig, 'constants.nsn_record_alignment', 0));
		if (isset($options['parser']) && $options['parser'] != 'none') {
			$this->setParser($options['parser']);
		}
	}

	/**
	 * @see Billrun_Processor::getSequenceData
	 */
	public function getFilenameData($filename) {
		return $this->getFileSequenceData($filename);
	}

	protected function getLineVolume($row) {
		$usage_type = $this->getLineUsageType($row);
		if (in_array($usage_type, array('call', 'incoming_call'))) {
			if (isset($row['duration'])) {
				return $row['duration'];
			}
		}
		if ($usage_type == 'sms') {
			return 1;
		}
		return null;
	}

	public function getLineUsageType($row) {
		switch ($row['record_type']) {
			case '08':
			case '09':
				return 'sms';

			case '02':
			case '12':
				return 'incoming_call';

			case '11':
			case '01':
			case '30':
			default:
				return 'call';
		}
		return 'call';
	}

	/**
	 * method to run over all the files received which did not have been processed
	 */
	public function processLines() {
		if (!is_resource($this->fileHandler)) {
			Billrun_Factory::log('Resource is not configured well', Zend_Log::ERR);
			return FALSE;
		}
		if (!$this->fileStats) {
			$this->fileStats = fstat($this->fileHandler);
		}
		while (!$this->isProcessingFinished()) {
			$bytes = null;

			$headerData = fread($this->fileHandler, $this->headerLength);
			$header = $this->parser->parseHeader($headerData);
			if (isset($header['data_length_in_block']) && !feof($this->fileHandler)) {
				$bytes = fread($this->fileHandler, $header['data_length_in_block'] - $this->headerLength);
			}
			if (in_array($header['format_version'], $this->nsnConfig['block_config']['supported_versions'])) {
				do {
					$row = $this->parseData($bytes, $this->fileHandler);
					if ($row) {
						Billrun_Factory::dispatcher()->trigger('beforeLineMediation', array($this, static::$type, &$parsedRow));
						$row['usaget'] = $this->getLineUsageType($row['uf']);
						$row['usagev'] = $this->getLineVolume($row['uf']);
						$row['urt'] = !empty($row['uf']['urt']) ? $row['uf']['urt'] : new MongoDate();
						Billrun_Factory::dispatcher()->trigger('afterLineMediation', array($this, static::$type, &$row));
						$this->addDataRow($row);
					}
					$bytes = substr($bytes, $this->parser->getLastParseLength());
					Billrun_Factory::log()->log("Last parsed bytes length: " . $this->parser->getLastParseLength(), Zend_log::DEBUG);
				} while (isset($bytes[$this->trailerLength + 1]));
			} else {
				$msg = "Got NSN block with unsupported version :  {$header['format_version']} , block header data : " . print_r($header, 1);
				Billrun_Factory::log()->log($msg, Zend_log::CRIT);
				throw new Exception($msg);
			}

			$trailer = $this->parser->parseTrailer($bytes);
			//align the readhead
			$alignment = $this->nsn_record_alignment * max(1, $header['charging_block_size']);
			if (($alignment - $header['data_length_in_block']) > 0) {
				fread($this->fileHandler, ($alignment - $header['data_length_in_block']));
			}

			//add trailer data
			$processorData = &$this->getData();
			$processorData['trailer'] = !empty($trailer = $this->updateBlockData($trailer, $header, [])) ? $trailer : array('trailer' => TRUE);
			$processedData['header'] = !empty($header) ? $header : array('header' => TRUE);
		}
		return true;
	}

	/**
	 * method to set the parser of the processor
	 * 
	 * @param Billrun_Parser|string|array $parser the parser to use by the processor or its name.
	 *
	 * @return mixed the processor itself (for concatenating methods)
	 */
	public function setParser($parser) {
		if (is_object($parser)) {
			$this->parser = $parser;
		} else {
			$parser = is_array($parser) ? $parser : array('type' => $parser);
			$this->parser = Billrun_Parser::getInstance($parser);
		}
		return $this;
	}

	public function getName(){
		return "nsn";
	}
	
	public function getParser(){
		return $this->parser;
	}
	
		/**
	 * Add block related data from the processor to the log DB collection entry.
	 * @param type $trailer the block header data
	 * @param type $header the block tralier
	 * @param type $logTrailer the log db trailer entry of the paresed file
	 * @return the updated log  trailer entry. 
	 */
	protected function updateBlockData($trailer, $header, $logTrailer) {
		if (Billrun_Factory::config()->getConfigValue('nsn.processor.save_block_header', false)) {
			if (!isset($logTrailer['block_data'])) {
				$logTrailer['block_data'] = array();
			}
			if (!isset($logTrailer['batch'])) {
				$logTrailer['batch'] = array();
			}
			if (!in_array($header['batch_seq_number'], $logTrailer['batch'])) {
				$logTrailer['batch'][] = $header['batch_seq_number'];
			}
			$logTrailer['block_data'][] = array('last_record_number' => $trailer['last_record_number'],
				'first_record_number' => $header['first_record_number'],
				'seq_no' => $header['block_seq_number']);
		}
		return $logTrailer;
	}
	
	/**
	 * @see 
	 */
	public function isProcessingFinished() {
		return feof($this->fileHandler) || ftell($this->fileHandler) + $this->trailerLength >= $this->fileStats['size'];
	}
}