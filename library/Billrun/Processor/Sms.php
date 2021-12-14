<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Sms processor based on field lines separator
 *
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

		// Billrun_Factory::log("sms : ". print_r($this->data,1),Zend_Log::DEBUG);

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
	protected function getLineType($line) {
		foreach ($this->structConfig['config']['line_types'] as $key => $val) {
			if (preg_match($val, $line)) {
				//	Billrun_Factory::log("line type key : $key",Zend_Log::DEBUG);
				return $key;
			}
		}
		return parent::getLineType($line);
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
			} else {
				if (isset($row[$this->structConfig['config']['date_field']])) {
					$offset = (isset($this->structConfig['config']['date_offset']) && isset($row[$this->structConfig['config']['date_offset']]) ?
							($row[$this->structConfig['config']['date_offset']] > 0 ? "+" : "" ) . $row[$this->structConfig['config']['date_offset']] : "00" ) . ':00';
					$datetime = DateTime::createFromFormat($this->structConfig['config']['date_format'], $row[$this->structConfig['config']['date_field']] . $offset);
				}
			}
			if (isset($row[$this->structConfig['config']['calling_number_field']])) {
				$row[$this->structConfig['config']['calling_number_field']] = Billrun_Util::msisdn($row[$this->structConfig['config']['calling_number_field']]);
			}
			if (isset($row[$this->structConfig['config']['called_number_field']])) {
				$row[$this->structConfig['config']['called_number_field']] = Billrun_Util::msisdn($row[$this->structConfig['config']['called_number_field']]);
			}
			$row['urt'] = new Mongodloid_Date($datetime->format('U'));
			$row['usaget'] = $this->getLineUsageType($row);
			$row['usagev'] = $this->getLineVolume($row);
		}
		return $row;
	}

	/**
	 * @see Billrun_Processor::getLineVolume
	 */
	protected function getLineVolume($row) {
		return 1;
	}

	/**
	 * @see Billrun_Processor::getLineUsageType
	 */
	protected function getLineUsageType($row) {
		return $row['type'] == 'mmsc' ? 'mms' : 'sms';
	}

}

?>
