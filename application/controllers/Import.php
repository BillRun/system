<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing index controller class
 *
 * @package  Controller
 * @since    1.0
 */
class ImportController extends Yaf_Controller_Abstract {

	public function indexAction() {
		die(); // don't enter this by mistake
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
		die(); // don't enter this by mistake
		$path = ''; // TODO: setup by config cli input
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

	public function convertTap3RatesAction() {
		$path = '/home/shani/Documents/S.D.O.C/BillRun/Files/Docs/CSVs/countries.csv';
		$change_date = '2014-09-01 00:00:00';
//		$change_date = '2014-01-01 00:00:00';
		$new_from_date = new MongoDate(strtotime($change_date));
		$old_to_date = new MongoDate(strtotime($change_date) - 1);
		$now = new MongoDate();
		if (!file_exists($path) || is_dir($path)) {
			Billrun_Factory::log("file not exists or path is directory");
			return FALSE;
		}
		$plmns = $this->getPLMNsArr($path);
		$kt_rates = Billrun_Factory::db()->ratesCollection()->query(array('key' => array('$regex' => '^KT'), 'from' => array('$lte' => $now), 'to' => array('$gte' => $now)));
		$old_tap3_rates = Billrun_Factory::db()->ratesCollection()->query(array('key' => array('$regex' => '^AC_ROAM'), 'from' => array('$lte' => $now), 'to' => array('$gte' => $now)));
		$new_rates = array();
		$prefixes_by_country = array();
		foreach ($kt_rates as $kt_rate) {
			$country = $this->getUnifiedCountry($kt_rate['key']);
			if (isset($prefixes_by_country[$country])) {
				$prefixes_by_country[$country] = array_merge($prefixes_by_country[$country], $kt_rate['params']['prefix']);
			} else {
				$prefixes_by_country[$country] = $kt_rate['params']['prefix'];
			}
		}
		foreach ($old_tap3_rates as $rate) {
			foreach ($rate['params']['serving_networks'] as $plmn) {
				if (!isset($plmns[$plmn]['country'])) {
					die('PLMN ' . $plmn . ' of rate ' . $rate['key'] . ' not found in csv');
				}
				$country_label = $plmns[$plmn]['country'];
				$alpha3 = $plmns[$plmn]['alpha3'];
				$unified_country = $plmns[$plmn]['unified_country'];
				$new_rate_key = 'AC_' . $unified_country;
				if (!isset($new_rates[$new_rate_key])) {
					$new_rate = $rate->getRawData();
					unset($new_rate['_id']);
					unset($new_rate['params']['serving_networks']);
					$new_rate['key'] = $new_rate_key;
					$new_rate['from'] = $new_from_date;
					$new_rate['country'] = $country_label;
					$new_rate['alpha3'] = $alpha3;
					if (isset($new_rate['rates']['incoming_call']) && preg_match('/CALLBACK/', $rate['key'])) {
						$new_rate['rates']['callback'] = $new_rate['rates']['incoming_call'];
						unset($new_rate['rates']['incoming_call']);
					}
					if (!isset($prefixes_by_country[$unified_country])) {
						die('No KT prefixes found for ' . $country_label);
					} else {
						$new_rate['kt_prefixes'] = $prefixes_by_country[$unified_country];
					}
				} else {
					$new_rate = $new_rates[$new_rate_key];
					foreach ($rate['rates'] as $usg => $usg_element) {
						if (preg_match('/CALLBACK/', $rate['key']) && $usg == 'incoming_call') {
							$usg = 'callback';
						}
						$new_rate['rates'][$usg] = $usg_element;
					}
				}
				$new_rate['params']['serving_networks'][] = $plmn;
				$new_rates[$new_rate_key] = $new_rate;
			}
		}

		foreach ($new_rates as &$new_rate) {
			$new_rate['params']['serving_networks'] = array_unique($new_rate['params']['serving_networks']);
		}
//		die('Finished preparations successfuly. ' . count($new_rates) . ' rates will be added.');

		if ($new_rates) {
			$ratesCollection = Billrun_Factory::db()->ratesCollection();
			// insert new rates
			$ratesCollection->batchInsert($new_rates);
			// update old rates "to" field
			// TODO: Check the return value of update?
			$ratesCollection->update(array('key' => array('$regex' => '^AC_ROAM'), 'from' => array('$lte' => $now), 'to' => array('$gte' => $now)), array('$set' => array('to' => $old_to_date)), array('multiple' => TRUE));
		}

		die('Ended successfuly.');
	}

	protected function getUnifiedCountry($kt_rate_key) {
		return str_replace('USA_NEW', 'USA', strtoupper(str_replace(array('_MOBILE', '_FIX', 'KT_', ' '), '', $kt_rate_key)));
	}

	protected function getPLMNsArr($path) {
		if (($handle = fopen($path, "r")) === FALSE) {
			die("error opening file");
		}
		$delimiter = "\t";
		$limit = 0;
		$row = 0;
		$plmns = array();
		while (($data = fgetcsv($handle, $limit, $delimiter)) !== FALSE) {
			$row++;
			if ($row == 1) {
				continue;
			}
			$plmns[$data[0]]['unified_country'] = $this->getUnifiedCountry(empty($data[4]) ? $data[3] : $data[4]);
			$plmns[$data[0]]['country'] = $data[3];
			$plmns[$data[0]]['alpha3'] = $data[1];
		}
		return $plmns;
	}

	public function checkCountryNamesAction() {
		$path = '/home/shani/Documents/S.D.O.C/BillRun/Files/Docs/CSVs/countries.csv';
		$plmn_countries = $this->getPLMNsArr($path);
		$unified_countries = array();
		foreach ($plmn_countries as &$country) {
			$unified_countries[] = $country['unified_country'];
		}
		$unified_countries = array_unique($unified_countries);

		$kt_countries = array();
		$kt_rates = Billrun_Factory::db()->ratesCollection()->query(array('key' => array('$regex' => '^KT_')));
		foreach ($kt_rates as $rate) {
			$kt_countries[] = $this->getUnifiedCountry($rate['key']);
		}
		$kt_countries = array_unique($kt_countries);
		die('Exist only in csv ( ' . count(array_diff($unified_countries, $kt_countries)) . '):<br>' . implode('<br>', array_diff($unified_countries, $kt_countries)) . '<br><br>' .
				'Exist only in kt rates ( ' . count(array_diff($kt_countries, $unified_countries)) . '):<br>' . implode('<br>', array_diff($kt_countries, $unified_countries)));
	}

}
