<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Golan subscriber class
 *
 * @package  Bootstrap
 * @since    1.0
 * @todo refactoring to general subscriber http class
 */
class Subscriber_Golan extends Billrun_Subscriber {

	/**
	 * method to load subsbscriber details
	 * 
	 * @param array $params the params to load by
	 * 
	 * @return Subscriber_Golan self object for chaining calls and use magic method for properties
	 */
	public function load($params) {
		
		if (!isset($params['imsi']) && !isset($params['IMSI']) && !isset($params['NDC_SN'])) {
			Billrun_Factory::log()->log('Cannot identified Golan subscriber. Require phone or imsi to load. Current parameters: ' . print_R($params), Zend_Log::ALERT);
			return $this;
		}

		if (!isset($params['time'])) {
			$params['DATETIME'] = date(Billrun_Base::base_dateformat);
		} else {
			$params['DATETIME'] = $params['time'];
		}

		$data = $this->request($params);
		if (is_array($data)) {
			$this->data = $data;
		} else {
			Billrun_Factory::log()->log('Failed to load Golan subscriber data', Zend_Log::ALERT);
		}
		return $this;
	}

	/**
	 * method to save subsbscriber details
	 */
	public function save() {
		return $this;
	}

	/**
	 * method to delete subsbscriber entity
	 */
	public function delete() {
		return TRUE;
	}

	/**
	 * method to send request to Golan rpc
	 * 
	 * @param string $phone the phone number of the client
	 * @param string $time the time that phone requested
	 * 
	 * @return array subscriber details
	 */
	protected function request($params) {

		$host = Billrun_Factory::config()->getConfigValue('provider.rpc.server', '');
		$url = Billrun_Factory::config()->getConfigValue('provider.rpc.url', '');
		$datetime_format = Billrun_Base::base_dateformat; // 'Y-m-d H:i:s';

		$path = 'http://' . $host . '/' . $url . '?' . http_build_query($params);
		// @TODO: use Zend_Http_Client
		$json = $this->send($path);

		if (!$json) {
			return false;
		}

		$object = @json_decode($json);

		if (!$object || !is_object($object)) {
			return false;
		}

		return (array) $object;
	}

	/**
	 * method to verify phone number is in NDC_SN format (with leading zero)
	 * 
	 * @param string $phone phone number
	 * 
	 * @return type string
	 */
	protected function NDC_SN($phone) {
		if (substr($phone, 0, 1) == '0') {
			return substr($phone, 1, strlen($phone) - 1);
		}
		return $phone;
	}

	/**
	 * method to send http request via curl
	 * 
	 * @param string $url the url to send
	 * 
	 * @return string the request output
	 * 
	 * @todo use Zend_Http_Client
	 */
	protected function send($url) {
		// create a new cURL resource
		$ch = curl_init();

		// set URL and other appropriate options
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_USERPWD, 'eranu:free');

		// grab URL and pass it to the browser
		$output = curl_exec($ch);

		// close cURL resource, and free up system resources
		curl_close($ch);

		return $output;
	}
	/**
	 * check if the returned subscriber data is a valid data set.
	 * @return boolean true  is the data is valid  false otherwise.
	 */
	public function isValid() {
		$validFields = true;
		foreach($this->getAvailableFields() as $field) {
			if(!isset($this->data[$field]) || is_null($this->data[$field])) {
				$validFields = false;
				break;
			}				
		}
		
		return (!isset($this->data['success']) || $this->data['success'] != FALSE ) && $validFields;
	}

}