<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Importer test class
 *
 * @package  Billrun
 * @since    4.0
 */
class Billrun_Importer_Test extends Billrun_Importer_Abstract {
	
	public function import() {
		Billrun_Factory::log("This is test importer");
		Billrun_Factory::log("The path to import is " . $this->path);
	
		if (!file_exists($this->path) || is_dir($this->path)) {
			Billrun_Factory::log("file not exists or path is directory");
			return FALSE;
		}

		if (($handle = fopen($this->path, "r")) !== FALSE) {
			$delimiter = ","; // TODO: setup by config cli input
			$limit = 0; // TODO: setup by config cli input
			$enclosure = '"'; // TODO: setup by config cli input
			$escape = '\\'; // TODO: setup by config cli input
			$uri = 'http://billrun/api/cards'; // TODO: setup by config cli input
			$curl = new Zend_Http_Client_Adapter_Curl();
			$client = new Zend_Http_Client($uri);
			$client->setAdapter($curl);
			$client->setMethod(Zend_Http_Client::POST);
			$send_arr = array();
			$counter = 0;
			while (($data = fgetcsv($handle, $limit, $delimiter)) !== FALSE) {
				if ($counter++ == 0) continue;
				$secret = hash('sha512',$data[2]);
				$send = array(
					'batch_number' => $data[0],
					'serial_number' => $data[0] . $data[1],
					'secret' => $secret,
					'charging_plan_name' => $data[3],
					'service_provider' => $data[4],
					'status' => $data[5],
					'to' => $data[6],
					'creation_time' => date('m/d/Y h:i:s a', time())
				);
				$send_arr[] = $send;
			}
			$params = array(
				'method' => 'create',
				'cards' => json_encode($send_arr)
			);
			$client->setParameterPost($params);
			$response = $client->request();
			print $response->getBody();
			Billrun_Factory::log("API response with: " . print_R($response->getBody(), true) . "<br />");
		}
		die(" end...");
	}
	
}