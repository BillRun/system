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
			'separator' => "\t",
		));
		
		$parser->setSeparator("\t");
		$import = Billrun_Processor::getInstance(array(
			'type' => 'importzones', 
			'parser' => $parser, 
			'path' => '/home/ofer/Desktop/billing_data/zone.csv'
		));
		
		if ($import === FALSE) {
			exit('cannot load import processor');
		}
		
		$import->setBackupPath(array()); // no backup
		$importData = $import->process();

		$merge = Billrun_Processor::getInstance(array(
			'type' => 'mergerates', 
			'parser' => $parser, 
			'path' => '/home/ofer/Desktop/billing_data/tariff_v2.csv'
		));

		if ($merge === FALSE) {
			exit('cannot load merge processor');
		}
		
		$merge->setBackupPath(array()); // no backup
		$mergeData = $merge->process();

		$this->getView()->title = "BillRun | The best open source billing system";
		$this->getView()->content = "Data import count: " . count($importData)
			. "<br />" . PHP_EOL
			. "Data import count: " . count($mergeData)
		;

		
	}

}
