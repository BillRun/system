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

		$count = $i = 0;
		fread($this->fileHandler, 54);
		while (!feof($this->fileHandler)) {
			$bytes .= fread($this->fileHandler, 512);
			$asnObject = ASN::parseASNString($bytes);
			echo PHP_EOL . ASN::$parsedLength . PHP_EOL;
			$bytes = substr($bytes,ASN::$parsedLength+3);
			//ASN::printASN($asnObject);
			//print_r($asnObject[0]);
			$this->parseASNData($asnObject[0]);
			sleep(1);
		}
		echo PHP_EOL . ASN::$parsedLength . PHP_EOL;
		die("!!!!!!!!!!!!!!!!!!!!!!!!!!!!" . PHP_EOL);
	}

	protected function parseASNData($asnData) {
		foreach($this->data_structure as $key => $val) {
			$tempData = $asnData;
			foreach($val as $type => $pos) {
				foreach($pos as $depth) {
					if(isset($tempData->asnData[$depth])) {
						$tempData = $tempData->asnData[$depth];
					}
				}
				switch($type) {
					case 'string':
							$tempData = $tempData->asnData;
						break;
					case 'imsi' :
						$tempData = implode(unpack("L*",$tempData->asnData));
						break;
					case 'ip' :
						$tempData = implode(".",unpack("C*",$tempData->asnData));
						break;
					case 'ttr' :
						print_r($tempData->asnData);
						$tempData = implode(".",unpack("C*",$tempData->asnData));

						break;

					default:
						$tempData = implode("",unpack($type,$tempData->asnData));
				}
//print_r($tempData);
			}

			print("$key : {$tempData}\n");
		}

	}

}