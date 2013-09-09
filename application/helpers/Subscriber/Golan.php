<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
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

	protected $plan = null;
	protected $time = null;

	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['data'])) {
			$this->data = $options['data'];
		}
		if (isset($options['time'])) {
			$this->time = $options['time'];
		}
		// pay attention that just availableFields array can be access from outside
	}

	/**
	 * method to load subsbscriber details
	 * 
	 * @param array $params the params to load by
	 * 
	 * @return Subscriber_Golan self object for chaining calls and use magic method for properties
	 */
	public function load($params) {

		if (!isset($params['imsi']) && !isset($params['IMSI']) && !isset($params['NDC_SN'])) {
			Billrun_Factory::log()->log('Cannot identify Golan subscriber. Require phone or imsi to load. Current parameters: ' . print_R($params, 1), Zend_Log::ALERT);
			return $this;
		}

		if (!isset($params['time'])) {
			$params['DATETIME'] = date(Billrun_Base::base_dateformat);
		} else {
			$params['DATETIME'] = $params['time'];
		}

		$data = $this->request($params); // @todo uncomment this
//		$data = array("account_id" => 7112968, "subscriber_id" => 116815, "plan" =>"SMALL"); // @todo remove this

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
		//Billrun_Factory::log()->log($path, Zend_Log::DEBUG);
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
		foreach ($this->getAvailableFields() as $field) {
			if (!isset($this->data[$field]) || is_null($this->data[$field])) {
				$validFields = false;
				break;
			}
		}
		$validFields = $validFields && ($this->data['plan'] != 'ERROR' );

		return (!isset($this->data['success']) || $this->data['success'] != FALSE ) && $validFields;
	}

	//@TODO change this function
	protected function requestAccounts($params) {
		$host = Billrun_Factory::config()->getConfigValue('crm.server', '');
		$url = Billrun_Factory::config()->getConfigValue('crm.url', '');

		$path = 'http://' . $host . '/' . $url . '?' . http_build_query($params);
		//Billrun_Factory::log()->log($path, Zend_Log::DEBUG);
		// @TODO: use Zend_Http_Client
//		$json = $this->send($path);
//		$json =  '{"6052392":{"subscribers":[{"subscriber_id":1,"plan":"LARGE"}]}}'; // stub
		if (!$json) {
			return false;
		}

		$arr = @json_decode($json,true);

		if (!is_array($arr) || empty($arr)) {
			return false;
		}

		return $arr;
	}

	public function getList($page, $size, $time, $acc_id = null) {
		if (is_null($acc_id)) {
			$params = array('msisdn' => '', 'IMSI' => '', 'DATETIME' => $time, 'page' => $page, 'size' => $size);
		}
		else {
			$params = array('msisdn' => '', 'IMSI' => '', 'DATETIME' => $time, 'page' => $page, 'size' => $size, 'account_id' => $acc_id);
		}
		$accounts = $this->requestAccounts($params);
		$subscriber_general_settings = Billrun_Config::getInstance()->getConfigValue('subscriber', array());
		if (is_array($accounts) && !empty($accounts)) {
			foreach ($accounts as $account_id => $account) {
				foreach ($account['subscribers'] as $subscriber) {
					$subscriber_settings = array_merge($subscriber_general_settings, array('time' => strtotime($time), 'data' => array('account_id' => intval($account_id), 'subscriber_id' => $subscriber['subscriber_id'], 'plan' => $subscriber['plan'])));
					$ret_data[intval($account_id)][] = Billrun_Subscriber::getInstance($subscriber_settings);
				}
			}
			return $ret_data;
		}
		else {
			return null;
		}
	}

	public function getPlan() {
		if (is_null($this->plan)) {
			$params = array(
				'name' => $this->data['plan'],
				'time' => $this->time,
			);
			$this->plan = new Billrun_Plan($params);
		}
		return $this->plan;
	}

	public function getFlatPrice() {
		return $this->getPlan()->getPrice();
	}

	/**
	 * 
	 * @param string $billrun_key
	 * @return array
	 */
	public function getFlatEntry($billrun_key) {
		$flat_entry = array(
			'account_id' => $this->account_id,
			'subscriber_id' => $this->subscriber_id,
			'source' => 'billrun',
			'type' => 'flat',
			'usaget' => 'flat',
			'unified_record_time' => new MongoDate(),
			'billrun_key' => $billrun_key,
			'price_customer' => $this->getFlatPrice(),
			'plan_ref' => $this->getPlan()->createRef(),
		);
		$stamp = md5($flat_entry['account_id'] . $flat_entry['subscriber_id'] . $flat_entry['billrun_key']);
		$flat_entry['stamp'] = $stamp;
		return $flat_entry;
	}

}