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
class ImportMapController extends Yaf_Controller_Abstract {

	public function indexAction() {
		$parser = Billrun_Parser::getInstance(array(
			'type' => 'separator',
			'separator' => ",",
		));
		
		$parser->setSeparator(",");
		$import = Billrun_Processor::getInstance(array(
			'type' => 'importintnetworkmappings', 
			'parser' => $parser, 
			'path' => '/home/shani/Documents/S.D.O.C/BillRun/backups/mobile_network.csv'
		));
		
		if ($import === FALSE) {
			exit('cannot load import processor');
		}
		
		$import->setBackupPath(array()); // no backup
		$importData = $import->process();
		
		
		
		$this->getView()->title = "BillRun | The best open source billing system";
		$this->getView()->content = "Data import count: " . count($importData)
			. "<br />" . PHP_EOL
			. "Data import count: " . count($importData) . "<br />";

		
	}

}
