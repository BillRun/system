<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
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
		die();
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

		$merge_intl_networks = Billrun_Processor::getInstance(array(
					'type' => 'mergeintlnetworks',
					'parser' => $parser,
					'path' => '/home/shani/Documents/S.D.O.C/BillRun/backups/mobile_network.csv'
		));

		if ($merge_intl_networks === FALSE) {
			exit('cannot load import processor');
		}

		$merge_intl_networks->setBackupPath(array()); // no backup
		$importMapData = $merge_intl_networks->process();

		$wholesale = Billrun_Processor::getInstance(array(
					'type' => 'wholesaleoutrates',
					'parser' => $parser,
					'path' => '/home/shani/Documents/S.D.O.C/BillRun/backups/wholesale/wsalein_tariff_out_v2.csv'
		));

		if ($wholesale === FALSE) {
			exit('cannot load import processor' . PHP_EOL);
		}

		$wholesale->setBackupPath(array()); // no backup
		$importWholesaleZones = $wholesale->process();

		$wholesalein = Billrun_Processor::getInstance(array(
					'type' => 'wholesaleinrates',
					'parser' => $parser,
					'path' => '/home/shani/Documents/S.D.O.C/BillRun/backups/wholesale/wsalein_tariff_in_v2.csv'
		));

		if ($wholesalein === FALSE) {
			exit('cannot load import processor' . PHP_EOL);
		}

		$wholesalein->setBackupPath(array()); // no backup
		$importWholesaleIn = $wholesalein->process();

		$this->getView()->title = "BillRun | The best open source billing system";
		$this->getView()->content = "Data import count: " . count($importWholesaleZones)
				. "<br />" . PHP_EOL
				. "Data merge count: " . count($mergeData) . "<br />"
				. "Data merge package count: " . count($mergePackageData) . "<br />"
				. "Data merge package count: " . count($mergePackageData) . "<br />"
				. "Merge intl. networks count: " . $importMapData . "<br />" . PHP_EOL;
		;
	}

	public function csvAction() {
		$path = '/home/shani/Desktop/refunds_201312.csv'; // TODO: setup by config cli input
		if (!file_exists($path) || is_dir($path)) {
			Billrun_Factory::log("file not exists or path is directory");
			return FALSE;
		}

		if (($handle = fopen($path, "r")) !== FALSE) {
			$delimiter = "\t"; // TODO: setup by config cli input
			$limit = 0; // TODO: setup by config cli input
			$enclosure = '"'; // TODO: setup by config cli input
			$escape = '\\'; // TODO: setup by config cli input
			$uri = 'http://billrun/api/bulkcredit'; // TODO: setup by config cli input
			$curl = new Zend_Http_Client_Adapter_Curl();
			$client = new Zend_Http_Client($uri);
			$client->setAdapter($curl);
			$client->setMethod(Zend_Http_Client::POST);
			$send_arr = array();
			while (($data = fgetcsv($handle, $limit, $delimiter)) !== FALSE) {
				$urt = new Zend_Date(strtotime($data[2] . " " . $data[3]), 'he_IL');
				$send = array(
					'account_id' => $data[0],
					'subscriber_id' => $data[1],
					'credit_time' => $urt->getTimestamp(),
					'amount_without_vat' => $data[4],
					'reason' => $data[5],
					'credit_type' => isset($data[6]) ? $data[6] : 'refund',
				);
				$send_arr[] = $send;
			}
			$params = array(
				'operation' => 'credit',
				'credits' => json_encode($send_arr),
			);
			$client->setParameterPost($params);
			$response = $client->request();
			print $response->getBody();
			Billrun_Factory::log("API response with: " . print_R($response->getBody(), true) . "<br />");
		}
		die(" end...");
	}

}
