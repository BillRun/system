<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */



/**
 * Billing  processor binary class
 *
 * @package  Billing
 * @since    1.0
 */
abstract class processor_binary extends processor {

	protected function parse() {

		// run all over the file with the parser helper
		if (!is_resource($this->fileHandler)) {
			echo "Resource is not configured well" . PHP_EOL;
			return false;
		}

		$count =0;
		$header['data'] = utf8_encode(fread($this->fileHandler, 54));
		$header['type'] = $this->type;
		$header['file'] = basename($this->filePath);
		$header['process_time'] = date('Y-m-d h:i:s');
		$header['stamp'] = md5(serialize($header));
		$this->data['header'] = $header;
		$bytes = null;
		do {
			if(!feof($this->fileHandler) ) {
				$bytes .= fread($this->fileHandler, 8192);
			}
			$this->parser->setLine($bytes);
			$row = $this->parser->parse();
			//print_r($row);
			$bytes = substr($bytes,$this->parser->getLastParseLength());
			$row['type'] = $this->type;
			$row['header_stamp'] = $this->data['header']['stamp'];
			$row['file'] = basename($this->filePath);
			$row['process_time'] = date('Y-m-d h:i:s');
			$this->data['data'][] = $row;
			$count++;
		} while ( strlen($bytes) > 54);

		echo PHP_EOL .$count . PHP_EOL;

		$trailer['type'] = $this->type;
		$trailer['header_stamp'] = $this->data['header']['stamp'];
		$trailer['file'] = basename($this->filePath);
		$trailer['process_time'] = date('Y-m-d h:i:s');
		$trailer['data'] = "";//$bytes;
		$this->data['trailer'] = $trailer;

		return true;
	}


}