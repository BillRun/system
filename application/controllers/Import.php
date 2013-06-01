<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing index controller class
 *
 * @package  Controller
 * @since    1.0
 */
class ImportController extends Yaf_Controller_Abstract {

	public function indexAction() {
		$parser = Billrun_Parser::getInstance(array(
			'type' => 'separator',
			'separator' => ",",
		));
		
		$parser->setSeparator(",");
		$import = Billrun_Processor::getInstance(array(
			'type' => 'importzones', 
			'parser' => $parser, 
			'path' => '/home/shani/Documents/S.D.O.C/BillRun/backups/zone.csv'
		));
		
		if ($import === FALSE) {
			exit('cannot load import processor');
		}
		
		$import->setBackupPath(array()); // no backup
		$importData = $import->process();
		
		$merge = Billrun_Processor::getInstance(array(
			'type' => 'mergerates', 
			'parser' => $parser, 
			'path' => '/home/shani/Documents/S.D.O.C/BillRun/backups/tariff_v2_filtered.csv'
		));

		if ($merge === FALSE) {
			exit('cannot load merge processor');
		}
		
		$merge->setBackupPath(array()); // no backup
		$mergeData = $merge->process();		

		$mergePackage = Billrun_Processor::getInstance(array(
			'type' => 'mergezonepackage', 
			'parser' => $parser, 
			'path' => '/home/shani/Documents/S.D.O.C/BillRun/backups/zone_group_element.csv'
		));

		if ($mergePackage === FALSE) {
			exit('cannot load merge processor');
		}
		
		$mergePackage->setBackupPath(array()); // no backup
		$mergePackageData = $mergePackage->process();
		
		$this->getView()->title = "BillRun | The best open source billing system";
		$this->getView()->content = "Data import count: " . count($importData)
			. "<br />" . PHP_EOL
			. "Data merge count: " . count($mergeData) . "<br />"
			. "Data merge package count: " . count($mergePackageData)
		;

		
	}

}
