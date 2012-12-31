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
	const MAX_CHUNKLENGTH_LENGTH = 512;
	const FILE_READ_AHEAD_LENGTH = 32768;

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
			if(!isset($bytes[self::MAX_CHUNKLENGTH_LENGTH]) && !feof($this->fileHandler)) {
				$bytes .= fread($this->fileHandler, self::FILE_READ_AHEAD_LENGTH );
			}

			$row = $this->buildDataRow($bytes);
			if($row) {
				$this->data['data'][] = $row;
			}

			$bytes = substr($bytes,$this->parser->getLastParseLength());
		} while ( isset($bytes[self::HEADER_LENGTH]) );

		$this->data['trailer'] = $this->buildTrailer($bytes);

		return true;
	}




}