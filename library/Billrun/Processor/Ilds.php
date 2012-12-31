<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract processor ilds class
 *
 * @package  Billing
 * @since    1.0
 */
class Billrun_Processor_Ilds extends Billrun_Processor {

	/**
	 * method to parse the data
	 */
	protected function parse() {
		if (!is_resource($this->fileHandler)) {
			echo "Resource is not configured well" . PHP_EOL;
			return false;
		}

		while ($line = fgets($this->fileHandler)) {
			$record_type = $this->getLineType($line);

			// @todo: convert each case code snippet to protected method (including triggers)
			switch ($record_type) {
				case 'H': // header
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
					$header['process_time'] = date('Y-m-d h:i:s');
					$this->data['header'] = $header;

					break;
				case 'T': //trailer
					if (isset($this->data['trailer'])) {
						echo "double trailer" . PHP_EOL;
						return false;
					}

					$this->parser->setStructure($this->trailer_structure);
					$this->parser->setLine($line);
					// @todo: trigger after trailer load (including $header, $data, $trailer)
					$trailer = $this->parser->parse();
					// @todo: trigger after trailer parse (including $header, $data, $trailer)
					$trailer['type'] = $this->type;
					$trailer['header_stamp'] = $this->data['header']['stamp'];
					$trailer['file'] = basename($this->filePath);
					$trailer['process_time'] = date('Y-m-d h:i:s');
					$this->data['trailer'] = $trailer;

					break;
				case 'D': //data
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
					$row['process_time'] = date('Y-m-d h:i:s');
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