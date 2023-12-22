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
class Billrun_Processor_Csv extends Billrun_Processor_Base_SeparatorFieldLines {

	static protected $type = 'csv';

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


		return parent::parse();

	}

	/**
	 * @see Billrun_Processor_Base_FixedFieldsLines::isValidDataRecord($dataLine)
	 */
	protected function isValidDataRecord($dataLine) {
		return count(array_intersect_key( array_keys($dataLine),  array_keys($this->data_structure) )) >= count($this->data_structure);
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
		if( !empty($this->structConfig['config']['date_format']) && !empty($this->structConfig['config']['date_field'])
			&& !empty($row[$this->structConfig['config']['date_field']]) &&empty($row['urt']) ) {
			    $datetime = DateTime::createFromFormat($this->structConfig['config']['date_format'], $row[ $this->structConfig['config']['date_field']]);
				$row['urt'] =  new MongoDate( $datetime ?  $datetime->format('U') : strtotime( $row[ $this->structConfig['config']['date_field']] ));
		}

		return $row;
	}
}

?>
