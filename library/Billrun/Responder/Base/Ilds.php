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
	protected $linesCount = 0;
	protected $totalChargeAmount = 0;

	protected function processFileForResponse($filePath,$logLine) {
		$logLine = $logLine->getRawData();

		$dbLines = $this->db->getCollection(self::lines_table)->query()->equals('file',$logLine['file']);

		//run only after the lines were processed by the billrun.
		if($dbLines->count() == 0 || $dbLines->exists('billrun')->count() == 0 ) { return false; }

		//save file to a temporary location
		$responsePath = $this->workPath . rand();
		$srcFile = fopen($filePath,"r+");
		$file = fopen($responsePath,"w");

		//alter lines
		fputs($file,$this->updateHeader(fgets($srcFile),$logLine)."\n");
		foreach($dbLines as $dbLine) {
			//alter data line
			$this->linesCount++;
			$this->totalChargeAmount += intval($dbLine->get('call_charge'));
			$line = $this->updateLine($dbLine->getRawData(),$logLine);
			if($line) {fputs($file,$line."\n");}
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
		if(!isset($dbLine['billrun']) || !$dbLine['billrun']) {
			$dbLine = $this->processErrorLine($dbLine);
		}
		if(!$dbLine || (isset($dbLine['record_status']) && intval($dbLine['record_status']) != 0 ) ) {
				$this->linesErrors++;
				if(!$dbLine) {return false;}
		}
		foreach($this->data_structure as $key => $val) {
			$data = (isset($dbLine[$key]) ? $dbLine[$key] : "");
			$line .= sprintf($val,mb_convert_encoding($data, 'ISO-8859-8', 'UTF-8'));
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

	abstract function processErrorLine($dbLine);

}
