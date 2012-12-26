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
		$header['data'] = fread($this->fileHandler, 54);
		$header['type'] = $this->type;
		$header['file'] = basename($this->filePath);
		$header['process_time'] = date('Y-m-d h:i:s');
		$this->data['header'] = $header;
		while (!feof($this->fileHandler)) {
			$bytes .= fread($this->fileHandler, 512);
			$asnObject = ASN::parseASNString($bytes);
			$bytes = substr($bytes,ASN::$parsedLength+3);
			//print_r($asnObject[0]);
			$row = $this->parseASNData($asnObject[0]);
			$row['type'] = $this->type;
			//$row['header_stamp'] = $this->data['header']['stamp'];
			$row['file'] = basename($this->filePath);
			$row['process_time'] = date('Y-m-d h:i:s');
			$this->data['data'][] = $row;
			//sleep(0.2);
			$count++;
		}
		echo PHP_EOL .$count . PHP_EOL;
		$trailer['type'] = $this->type;
		//$trailer['header_stamp'] = $this->data['header']['stamp'];
		$trailer['file'] = basename($this->filePath);
		$trailer['process_time'] = date('Y-m-d h:i:s');
		$trailer['data'] = $bytes;
		$this->data['trailer'] = $trailer;
		return true;
	}

	protected function parseASNData($asnData) {
		$retArr= array();
		foreach($this->data_structure as $key => $val) {

			foreach($val as $type => $pos) {
				$tempData = $asnData;
				foreach($pos as $depth) {
					if( isset($tempData[$depth])) {
						$tempData = $tempData[$depth];
					}
				}
				if(isset($tempData)) {
					switch($type) {

						case 'string':
							break;
						case 'number':
							$numarr = unpack("C*",$tempData);
							$tempData =0;
							foreach($numarr as $byte) {
								//$tempData = $tempData <<8;
								$tempData =  ($tempData << 8 )+ $byte;
							}
							break;
						case 'BCDencode' :
							$halfBytes = unpack("C*",$tempData);
							$tempData ="";
							foreach($halfBytes as $byte) {
								//$tempData = $tempData <<8;
								$tempData .= ($byte & 0xF) . ((($byte >>4) < 10) ? ($byte >>4) : "" ) ;
							}
							break;
						case 'ip' :
							$tempData = implode(".",unpack("C*",$tempData));
							break;
						case 'datetime' :
							$tempTime = DateTime::createFromFormat("ymdHisT",str_replace("2b","+",implode(unpack("H*",$tempData))) );
							$tempData = is_object($tempTime) ?  $tempTime->format("H:i:s d/m/Y T") : "";
							break;
						case 'json' :
							$tempData = json_encode($tempData);
							break;
						default:
							$tempData = is_array($tempData) ? "" : implode("",unpack($type,$tempData));
					}
				}
			}
			$retArr[$key] = $tempData;
			//print("$key : {$tempData}\n");
		}

	}

}