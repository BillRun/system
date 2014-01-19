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
	protected $next_plan = null;
	protected $time = null;

	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['data'])) {
			$this->data = $options['data'];
			if (isset($options['data']['stamp'])) {
				$this->stamp = $options['data']['stamp'];
			}
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

		$host = self::getRpcServer();
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
	static protected function send($url, $post_data = null) {
		// create a new cURL resource
		$ch = curl_init();

		// set URL and other appropriate options
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_USERPWD, 'eranu:free');
		if (isset($post_data)) {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/plain'));
		}
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
		foreach (array_keys($this->getAvailableFields()) as $key) {
			if (!isset($this->data[$key]) || is_null($this->data[$key])) {
				$validFields = false;
				break;
			}
		}
		$validFields = $validFields && ($this->data['plan'] != 'ERROR' );

		return (!isset($this->data['success']) || $this->data['success'] != FALSE ) && $validFields;
	}

	//@TODO change this function
	protected function requestAccounts($params) {
		$host = self::getRpcServer();
		$url = Billrun_Factory::config()->getConfigValue('crm.url', '');

		$path = 'http://' . $host . '/' . $url . '?' . http_build_query($params);
		//Billrun_Factory::log()->log($path, Zend_Log::DEBUG);
		// @TODO: use Zend_Http_Client
//		$path .= "&account_id=4171195"; // Shani
//		$path .= "&account_id=9073496"; // Ofer
//		$path .= "&account_id=5236445";
//		$path .= "&account_id=7236490";
		$json = self::send($path);
		if (!$json) {
			return false;
		}

		$arr = @json_decode($json, true);

		if (!is_array($arr) || empty($arr)) {
			return false;
		}

		return $arr;
	}

	public function getList($page, $size, $time, $acc_id = null) {
		if (is_null($acc_id)) {
			$params = array('msisdn' => '', 'IMSI' => '', 'DATETIME' => $time, 'page' => $page, 'size' => $size);
		} else {
			$params = array('msisdn' => '', 'IMSI' => '', 'DATETIME' => $time, 'page' => $page, 'size' => $size, 'account_id' => $acc_id);
		}
		$accounts = $this->requestAccounts($params);
		return $this->parseActiveSubscribersOutput($accounts, strtotime($time));
	}

	protected function parseActiveSubscribersOutput($output_arr, $time) {
		if (isset($output_arr['success']) && $output_arr['success'] === FALSE) {
			return array();
		} else {
			$subscriber_general_settings = Billrun_Config::getInstance()->getConfigValue('subscriber', array());
			if (is_array($output_arr) && !empty($output_arr)) {
				$ret_data = array();
				foreach ($output_arr as $aid => $account) {
					if (isset($account['subscribers'])) {
						foreach ($account['subscribers'] as $subscriber) {
							$concat = array(
								'time' => strtotime($time),
								'data' => array(
									'aid' => intval($aid),
									'sid' => intval($subscriber['subscriber_id']),
									'plan' => isset($subscriber['curr_plan']) ? $subscriber['curr_plan'] : null,
									'next_plan' => isset($subscriber['next_plan']) ? $subscriber['next_plan'] : null,
								),
							);
							$subscriber_settings = array_merge($subscriber_general_settings, $concat);
							$ret_data[intval($aid)][] = Billrun_Subscriber::getInstance($subscriber_settings);
						}
					}
				}
				ksort($ret_data); // maybe this will help the aid index to stay in memory
				return $ret_data;
			} else {
				return null;
			}
		}
	}

	public function getListFromFile($file_path, $time) {
		$json = @file_get_contents($file_path);
		$arr = @json_decode($json, true);
		if (!is_array($arr) || empty($arr)) {
			return array();
		}
		return $this->parseActiveSubscribersOutput($arr, $time);
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

	public function getNextPlan() {
		if (is_null($this->next_plan)) {
			if (is_null($this->getNextPlanName())) {
				$this->next_plan = null;
			} else {
				$params = array(
					'name' => $this->getNextPlanName(),
					'time' => Billrun_Util::getStartTime(Billrun_Util::getFollowingBillrunKey(Billrun_Util::getBillrunKey($this->time))),
				);
				$this->next_plan = new Billrun_Plan($params);
			}
		}
		return $this->next_plan;
	}

	public function getFlatPrice() {
		return $this->getNextPlan()->getPrice();
	}

	/**
	 * 
	 * @param string $billrun_key
	 * @return array
	 */
	public function getFlatEntry($billrun_key) {
		$billrun_end_time = Billrun_Util::getEndTime($billrun_key);
		$flat_entry = array(
			'aid' => $this->aid,
			'sid' => $this->sid,
			'source' => 'billrun',
			'billrun' => '000000',
			'type' => 'flat',
			'usaget' => 'flat',
			'urt' => new MongoDate($billrun_end_time),
			'aprice' => $this->getFlatPrice(),
			'plan_ref' => $this->getNextPlan()->createRef(),
			'process_time' => date(Billrun_Base::base_dateformat),
		);
		$stamp = md5($flat_entry['aid'] . $flat_entry['sid'] . $billrun_end_time);
		$flat_entry['stamp'] = $stamp;
		return $flat_entry;
	}

	static public function getSubscribersByParams($params_arr, $availableFields) {
		$subscribers = array();
		foreach ($params_arr as $key => &$params) {
			if (!isset($params['imsi']) && !isset($params['IMSI']) && !isset($params['NDC_SN'])) {
				Billrun_Factory::log()->log('Cannot identify Golan subscriber. Require phone or imsi to load. Current parameters: ' . print_R($params, 1), Zend_Log::ALERT);
				unset($params_arr[$key]);
			} else if (!isset($params['time'])) {
				$params['DATETIME'] = date(Billrun_Base::base_dateformat);
			} else {
				$params['DATETIME'] = $params['time'];
			}
		}
		$list = self::requestList($params_arr);

		if (is_array($list) && !empty($list)) {
			$message = 'Customer API responded with ' . count($list) . ' results';
			$subscriberSettings = Billrun_Factory::config()->getConfigValue('subscriber', array());
			foreach ($list as $stamp => $item) {
				if (is_array($item)) {
					foreach ($availableFields as $key => $field) {
						if (isset($item[$field])) {
							$temp = $item[$field];
							unset($item[$field]);
							$item[$key] = $temp;
						}
					}
					$subscribers[$stamp] = new self(array_merge(array('data' => $item), $subscriberSettings));
				} else {
					//TODO what is the output when subscriber was not found?
//				Billrun_Factory::log()->log('Failed to load Golan subscriber data', Zend_Log::ALERT);
				}
			}
		} else {
			$message = 'Customer API responded with no results';
		}
		Billrun_Factory::log()->log($message . ". Proceeding with calculation...", Zend_Log::INFO);
		return $subscribers;
	}

	static public function requestList($params) {
		$host = self::getRpcServer();
		$url = Billrun_Factory::config()->getConfigValue('provider.rpc.bulk_url', '');

		$path = 'http://' . $host . '/' . $url;
		//Billrun_Factory::log()->log($path, Zend_Log::DEBUG);
		// @TODO: use Zend_Http_Client
		Billrun_Factory::log()->log('Querying customer API with ' . count($params) . ' subscribers', Zend_Log::INFO);
		$json = self::send($path, json_encode($params));

		if (!$json) {
			return false;
		}

		$arr = @json_decode($json, true);
		if (!is_array($arr) || empty($arr)) {
			return false;
		}

		return $arr;
	}

	public function getNextPlanName() {
		if (isset($this->data['next_plan'])) {
			return $this->data['next_plan'];
		} else {
			return null;
		}
	}

	/**
	 * Get the rpc server from config
	 * 
	 * @return string the host or ip of the server
	 */
	static protected function getRpcServer() {
		$hosts = Billrun_Factory::config()->getConfigValue('provider.rpc.server', array());
		if (empty($hosts)) {
			return false;
		}

		if (!is_array($hosts)) {
			// probably string
			return $hosts;
		}

		// if it's array rand between servers
		return $hosts[rand(0, count($hosts) - 1)];
	}

}
