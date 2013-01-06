<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing Remote Files responder class
 *
 * @package  Billing
 * @since    1.0
 */
abstract class Billrun_Responder_Base_Ilds extends Billrun_Responder_Base_LocalDir {

	protected $linesErrors = 0;

	protected function processFileForResponse($filePath,$logLine) {
		$logLine = $logLine->getRawData();
		//save file to a temporary location
		$responsePath = $this->workPath . rand();

		$dbLines = $this->db->getCollection(self::lines_table)->query()->equals('file',$logLine['file']);

		$srcFile = fopen($filePath,"r+");
		$file = fopen($responsePath,"w");

		//alter lines
		fputs($file,$this->updateHeader(fgets($srcFile),$logLine)."\n");
		foreach($dbLines as $dbLine) {
			//alter data line
			fputs($file,$this->updateLine($dbLine->getRawData(),$logLine)."\n");
		}
		//alter trailer
		fputs($file,$this->updateTrailer($logLine)."\n");

		fclose($file);

		return $responsePath;
	}

	protected function updateHeader($line,$logLine) {
		$line = trim($line);

		return $line;

	}

	protected function updateLine($dbLine,$logLine) {
		$line="";
		foreach($this->data_structure as $key => $val) {
			$data = (isset($dbLine[$key]) ? $dbLine[$key] : "");
			$line .= sprintf($val,mb_convert_encoding($data, 'ISO-8859-8', 'UTF-8'));
			if($key == 'record_status' && intval($data) != 0 ) {
				$this->linesErrors++;
			}
		}

		return $line;
	}

	protected function updateTrailer($logLine) {
		$line ="";
		foreach($this->trailer_structure as $key => $val) {
			$data = (isset($dbLine[$key]) ? $logLine[$key] : "");
			$line .= sprintf($val,$logLine[$key]);
		}

		return $line;
	}

}
