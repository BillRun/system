<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing Remote Files responder class
 *
 * @package  Billing
 * @since    0.5
 */
abstract class Billrun_Responder_Base_Ilds extends Billrun_Responder_Base_LocalDir {

	// A  count of the line that contain some kind of error in the proceesed file.
	protected $linesErrors = 0;
	
	// The line count that were proceesed in the proceesed file.
	protected $linesCount = 0;
	
	// The total charge amount in the processed file.
	protected $totalChargeAmount = 0;
	
	/**
	 * Process a given file and create a temporary response file to it.  
	 * @param type $filePath the location of the file that need to be proceesed
	 * @param type $logLine the log line that associated with the file to process.
	 * @return boolean|string	return the temporary file path if the file should be responded to.
	 *							or false if the file wasn't processed into the DB yet.
	 */
	protected function processFileForResponse($filePath, $logLine) {
		$logLine = $logLine->getRawData();
		$this->linesCount = $this->linesErrors = $this->totalChargeAmount = 0;
		
		$linesCollection = Billrun_Factory::db()->linesCollection();
		$dbLines = $linesCollection->query()->equals('file', $logLine['file']);

		//run only after the lines were processed by the billrun.
		if ($dbLines->count() == 0 || /* TODO fix this db query  find a way to query the $dbLines results insted */ 
			$linesCollection->query()->equals('file', $logLine['file'])->exists('billrun')->count() == 0) {
			return false;
		}

		//save file to a temporary location
		$responsePath = $this->workspace . rand();
		$srcFile = fopen($filePath, "r+");
		$file = fopen($responsePath, "w");
		
		$lines = "";
		foreach ($dbLines as $dbLine) {
			//alter data line
			$line = $this->updateLine($dbLine->getRawData(), $logLine);
			if ($line) {
				$this->linesCount++;
				$this->totalChargeAmount += floatval($dbLine->get('call_charge'));
				$lines .= $line . "\n";
			}
		}
		
		//alter lines
		fputs($file, $this->updateHeader(fgets($srcFile), $logLine) . "\n");
		
		fputs($file, $lines);
		
		//alter trailer
		fputs($file, $this->updateTrailer($logLine) . "\n");

		fclose($file);

		return $responsePath;
	}
	
	/**
	 * 
	 * @param type $line
	 * @param type $logLine
	 * @return type
	 */
	protected function updateHeader($line, $logLine) {
		$line = trim($line);
		return $line;
	}
	
	/**
	 * Create and update a data line for response
	 * @param Array $dbLine the data line from the DB to respond to.
	 * @param Array $logLine thelogline of the file the data line is linked to.
	 * @return string a record data line that holds the data from the proccesed db line. 
	 */
	protected function updateLine($dbLine, $logLine) {
		$line = "";
		
		$dbLine = $this->processLineErrors($dbLine);
		
		if (!$dbLine || (isset($dbLine['record_status']) && intval($dbLine['record_status']) != 0 )) {
			$this->linesErrors++;
			if (!$dbLine) {
				return false;
			}
		}
		foreach ($this->data_structure as $key => $val) {
			$data = (isset($dbLine[$key]) ? $dbLine[$key] : "");
			$line .= sprintf($val, mb_convert_encoding($data, 'ISO-8859-8', 'UTF-8'));
		}

		return $line;
	}
	
	/**
	 * Create  and update the trailer of the response file.
	 * @param array $logLine the logline of the processed file
	 * @return string the response trailer line(s) .
	 */
	protected function updateTrailer($logLine) {
		$line = "";
		foreach ($this->trailer_structure as $key => $val) {
			$data = (isset($logLine[$key]) ? $logLine[$key] : "");
			$line .= sprintf($val,$data);
		}

		return $line;
	}
	
	/**
	 * switch between two strings in a line
	 * (done a lot..)
	 * @param type $name1
	 * @param type $name2
	 * @param type $line
	 * @return type
	 */
	protected function switchNamesInLine($name1, $name2, $line) {
			$ourSign = base64_encode($name1.$name2);
			return str_replace($ourSign, $name1,
									str_replace($name1, $name2, 
										str_replace($name2, $ourSign, $line)
									)
								);
	}
	
	/**
	 * Process record data line structure and check for errors or issues with it
	 * Change it  accordingly and return the updated line.
	 * @param $dbLine A structure the holds a single data line from the DB.
	 * @return An updated line data structure. 
	 */
	abstract protected function processLineErrors($dbLine);
}
