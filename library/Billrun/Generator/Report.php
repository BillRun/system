<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract generator ilds class
 * require to generate xml for each account
 * require to generate csv contain how much to credit each account
 *
 * @package  Billing
 * @since    0.5
 */
abstract class Billrun_Generator_Report extends Billrun_Generator {

	/**
	 * Generate the  report  files.
	 * @param type $resultFiles an array containing all the file names  and thier  corresponding  reports 
	 * @param type $outputDir the directory to place the reports in.
	 */
	protected function generateFiles($resultFiles, $outputDir = GenerateAction::GENERATOR_OUTPUT_DIR) {
		foreach ($resultFiles as $name => $report) {
			$fname = date('Ymd') . "_" . $name ;
			Billrun_Factory::log("Generating file $fname");
			$fd = fopen($outputDir . DIRECTORY_SEPARATOR . $fname, "w+");
			$this->writeToFile($fd, $report);
			fclose($fd);
		}
	}
	
	/**
	 * Write  the report to a file.
	 * @param  $fd  the  file handler to write to.
	 * @param $report  the report to data  to write to the file.
	 */
	abstract function writeToFile( &$fd, &$report );

}
