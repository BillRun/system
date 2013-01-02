<?php

/**
 * @category   Billrun
 * @package    Processor
 * @subpackage Nrtrde
 * @copyright  Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

/**
 * Billing processor for NRTRDE
 * see also:
 * http://www.tapeditor.com/OnlineDemo/NRTRDE-ASCII-format.html
 *
 * @package    Billing
 * @subpackage Processor
 * @since      1.0
 */
class Billrun_Processor_Separator extends Billrun_Processor {

	protected $type = 'separator';

	public function __construct($options) {
		parent::__construct($options);
	}

	/**
	 * Get the type of the currently parsed line.
	 * 
	 * @param $line  string containing the parsed line.
	 * 
	 * @return Character representing the line type
	 */
	protected function getLineType($line) {
		return $line[0];
	}

	/**
	 * method to parse the data
	 */
	protected function parse() {
		if (!is_resource($this->fileHandler)) {
			echo "Resource is not configured well" . PHP_EOL;
			return false;
		}

		$separator = $this->parser->getSeparator();
		while ($line = fgetcsv($this->fileHandler, 8092, $separator)) {
			$record_type = $this->getLineType($line, $separator);

			// @todo: convert each case code snippet to protected method (including triggers)
			switch ($record_type) {
				case '10':
				case 'NRTRDE': // header
					if (isset($this->data['header'])) {
						echo "double header" . PHP_EOL;
						return false;
					}

					$this->parser->setStructure($this->header_structure);
					$this->parser->setLine($line);
					// @todo: trigger after header load (including $header)
					$header = $this->parser->parse();
					// @todo: trigger after header parse (including $header)
					$header['type'] = $this->type;
					$header['file'] = basename($this->filePath);
					$header['process_time'] = date(self::base_dateformat);
					$this->data['header'] = $header;

					break;
				case '20':
				case 'MOC':
				case '30':
				case 'MTC': // data
					if (!isset($this->data['header'])) {
						echo "No header found" . PHP_EOL;
						return false;
					}

					$this->parser->setStructure($this->data_structure); // for the next iteration
					$this->parser->setLine($line);
					// @todo: trigger after row load (including $header, $row)
					$row = $this->parser->parse();
					// @todo: trigger after row parse (including $header, $row)
					$row['type'] = $this->type;
					$row['header_stamp'] = $this->data['header']['stamp'];
					$row['file'] = basename($this->filePath);
					$row['process_time'] = date(self::base_dateformat);
					// hot fix cause this field contain iso-8859-8
					if (isset($row['country_desc'])) {
						$row['country_desc'] = mb_convert_encoding($row['country_desc'], 'UTF-8', 'ISO-8859-8');
					}
					$this->data['data'][] = $row;

					break;
				default:
					//raise warning
					break;
			}
		}
		return true;
	}

}