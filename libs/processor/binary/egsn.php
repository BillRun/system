<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing processor for 012 class
 *
 * @package  Billing
 * @since    1.0
 */
class processor_binary_egsn extends processor_binary {

	protected $type = 'egsn';
	const HEADER_LENGTH = 54;

	public function __construct($options) {
		parent::__construct($options);

	}

protected function parse() {

		// run all over the file with the parser helper
		if (!is_resource($this->fileHandler)) {
			echo "Resource is not configured well" . PHP_EOL;
			return false;
		}


		$this->data['header'] = $this->buildHeader(fread($this->fileHandler, self::HEADER_LENGTH));
		$bytes = null;
		do {
			if(!feof($this->fileHandler) ) {
				$bytes .= fread($this->fileHandler, 8192);
			}
			$row = $this->buildDataRow($bytes);
			if($row) {
				$this->data['data'][] = $row;
			}

			$bytes = substr($bytes,$this->parser->getLastParseLength());
		} while ( strlen($bytes) > self::HEADER_LENGTH);

		$this->data['trailer'] = $this->buildTrailer($bytes);

		return true;
	}




}