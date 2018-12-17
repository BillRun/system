<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Smsc
 *
 * @author eran
 */
class Billrun_Processor_Sms extends Billrun_Processor_Base_SeparatorFieldLines {

	static protected $type = 'sms';

	/**
	 * Hold the structure configuration data.
	 */
	protected $structConfig = false;

	public function __construct($options = array()) {
		parent::__construct($options);

		$this->loadConfig(Billrun_Factory::config()->getConfigValue($this->getType() . '.config_path'));
	}

	protected function parse() {
		$this->parser->setSeparator($this->structConfig['config']['separator']);
		if (isset($this->structConfig['config']) && isset($this->structConfig['config']['add_filename_data_to_header']) &&
				$this->structConfig['config']['add_filename_data_to_header']) {
			$this->data['header'] = array_merge($this->buildHeader(''), array_merge((isset($this->data['header']) ? $this->data['header'] : array()), $this->getFilenameData(basename($this->filePath))));
		}

		// Billrun_Factory::log()->log("sms : ". print_r($this->data,1),Zend_Log::DEBUG);

		return parent::parse();
	}

	/**
	 * @see Billrun_Processor_Base_FixedFieldsLines::isValidDataRecord($dataLine)
	 */
	protected function isValidDataRecord($dataLine) {
		return true; //preg_match( $this->structConfig['config']['valid_data_line'], );
	}

	/**
	 * Find the line type  by checking  if the line match  a configuraed regex.
	 * @param type $line the line to check.
	 * @param type $length the lengthh of the line,
	 * @return string H/T/D  depending on the type of the line.
	 */
	protected function getLineType($line, $length = 1) {
		foreach ($this->structConfig['config']['line_types'] as $key => $val) {
			if (preg_match($val, $line)) {
				//	Billrun_Factory::log()->log("line type key : $key",Zend_Log::DEBUG);
				return $key;
			}
		}
		return parent::getLineType($line, $length);
	}

	/**
	 * the structure configuration
	 * @param type $path
	 */
	protected function loadConfig($path) {
		$this->structConfig = (new Yaf_Config_Ini($path))->toArray();

		$this->header_structure = $this->structConfig['header'];
		$this->data_structure = $this->structConfig['data'];
		$this->trailer_structure = $this->structConfig['trailer'];
	}

	protected function buildData($line, $line_number = null) {
		$row = parent::buildData($line, $line_number);
		if (isset($row[$this->structConfig['config']['date_field']])) {
			if ($row['type'] == 'mmsc') {
				$datetime = DateTime::createFromFormat($this->structConfig['config']['date_format'], preg_replace('/^(\d+)\+(\d)$/', '$1+0$2:00', $row[$this->structConfig['config']['date_field']]));
				$matches = array();
				if (preg_match("/^\+*(\d+)\//", $row['recipent_addr'], $matches)) {
					$row['called_number'] = $matches[1];
				}
				$row['usaget'] = 'mms';
			} else {
				if (isset($row[$this->structConfig['config']['date_field']])) {
					$offset = (isset($this->structConfig['config']['date_offset']) && isset($row[$this->structConfig['config']['date_offset']]) ?
									($row[$this->structConfig['config']['date_offset']] > 0 ? "+" : "" ) . $row[$this->structConfig['config']['date_offset']] : "00" ) . ':00';
					$datetime = DateTime::createFromFormat($this->structConfig['config']['date_format'], $row[$this->structConfig['config']['date_field']] . $offset);
				}
				switch ($row['record_type']) {
					case '1' :
					case '09' :
						$row['usaget'] = 'incoming_sms';
						break;
					case '2' : $row['usaget'] = 'sms';
								break;
					default: 
								$row['usaget'] = 'sms';
				}
				$calling_msc = $row['calling_msc'];
				if (preg_match('/^(?!0+$)/', $calling_msc) && !preg_match('/^0*972/', $calling_msc) && $row['record_type'] == "2"){
					$row['roaming'] = true;
				}
			}
			if (isset($row[$this->structConfig['config']['calling_number_field']]) && !preg_match('/^00*$/', $row[$this->structConfig['config']['calling_number_field']]) && preg_match('/^[0-9]*$/', $row[$this->structConfig['config']['calling_number_field']])) { 
				$row[$this->structConfig['config']['calling_number_field']] = Billrun_Util::msisdn($row[$this->structConfig['config']['calling_number_field']]);
			}
			if (isset($row[$this->structConfig['config']['called_number_field']])) {
				$row[$this->structConfig['config']['called_number_field']] = Billrun_Util::msisdn($row[$this->structConfig['config']['called_number_field']]);
			}
			$row['urt'] = new MongoDate($datetime->format('U'));
		}
		return $row;
	}

}

?>
