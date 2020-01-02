<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
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
	protected $save_crm_output = false;
	protected $crm_output_dir = null;
	protected $billrunExtraFields = array('kosher' => 1, 'credits' => 1, 'sub_services' => 0); //true to save in billrun, false not to save

	/**
	 * calculator for manual charges on billable
	 * 
	 * @var Billrun Calculator
	 */
	protected $creditCalc = null;
	protected $pricingCalc = null;
	protected $serviceCalc = null;

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

		if (isset($options['save_crm_output'])) {
			$this->save_crm_output = $options['save_crm_output'];
		}
		if ($this->save_crm_output) {
			$this->crm_output_dir = (isset($options['crm_output_dir']) ? $options['crm_output_dir'] : (getcwd() . '/files/crm_output/billable_subscribers')) . '/' . date('Ym') . '/';
			if (!file_exists($this->crm_output_dir)) {
				mkdir($this->crm_output_dir, 0777, true);
			}

		}
//		$creditCalcOptions = array_merge(array('type' => 'Rate_Credit', 'autoload' => false), Billrun_Factory::config()->getConfigValue('Rate_Credit.calculator', array()));
//		$this->creditCalc = Billrun_Calculator::getInstance($creditCalcOptions);
//		$pricingCalcOptions = array_merge(array('type' => 'customerPricing', 'autoload' => false), Billrun_Factory::config()->getConfigValue('customerPricing.calculator', array()));
//		$this->pricingCalc = Billrun_Calculator::getInstance($pricingCalcOptions);
//		$serviceCalcOptions = array_merge(array('type' => 'Rate_Service', 'autoload' => false), Billrun_Factory::config()->getConfigValue('Rate_Service.calculator', array()));
//		$this->serviceCalc = Billrun_Calculator::getInstance($serviceCalcOptions);

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
			Billrun_Factory::log()->log(json_encode($data),Zend_Log::DEBUG);
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
		$url = Billrun_Factory::config()->getConfigValue('provider.rpc.bulk_url', '');

		$path = 'http://' . $host . '/' . $url;
		Billrun_Factory::log()->log($path, Zend_Log::DEBUG);
		Billrun_Factory::log()->log(json_encode(array($params)), Zend_Log::DEBUG);
		// @TODO: use Zend_Http_Client
		$json = $this->send($path, json_encode(array($params)));

		if (!$json) {
			return false;
		}

		$object = @json_decode($json, true);

		if (!$object || !is_array($object)) {
			return false;
		}

		return (array) $object[0];
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
	protected function requestAccounts($params, $saveToFile = false) {
		$host = self::getRpcServer();
		$url = Billrun_Factory::config()->getConfigValue('crm.url', '');

		$path = 'http://' . $host . '/' . $url . '?' . http_build_query($params);
		//Billrun_Factory::log()->log($path, Zend_Log::DEBUG);
		// @TODO: use Zend_Http_Client
//		$path .= "&account_id=4171195"; // Shani_old
//		$path .= "&account_id=4050951"; // Shani_new
//		$path .= "&account_id=9073496"; // Ofer
//		$path .= "&account_id=9999263";
		if ($saveToFile) {
			$cache_file_path = $this->crm_output_dir . $params['page'] . '_' . $params['size'] . '.json';
			if (!file_exists($cache_file_path) || ($json = file_get_contents($cache_file_path)) === FALSE) {
				$json = self::send($path);
				file_put_contents($cache_file_path, $json);
			}
		} else {
			$json = self::send($path);
			if ($saveToFile) {
				$file_path = $this->crm_output_dir . time() . '_' . md5($path) . '.json';
//				file_put_contents($file_path, $path . PHP_EOL);
//				file_put_contents($file_path, $json, FILE_APPEND);
				file_put_contents($file_path, $json);
			}
		}

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
		$accounts = $this->requestAccounts($params, is_null($acc_id) && $this->save_crm_output);
		return $this->parseActiveSubscribersOutput($accounts, strtotime($time));
	}

	/**
	 * @param array $output_arr
	 * @param int $time
	 * @return array
	 */
	protected function parseActiveSubscribersOutput($output_arr, $time) {
		if (isset($output_arr['success']) && $output_arr['success'] === FALSE) {
			return array();
		} else {
			$subscriber_general_settings = Billrun_Config::getInstance()->getConfigValue('subscriber', array());
			if (is_array($output_arr) && !empty($output_arr)) {
				$ret_data = array();
				$billrun_fields = array_keys(self::getExtraFieldsForBillrun());
				foreach ($output_arr as $aid => $account) {
					if (isset($account['subscribers'])) {
						foreach ($account['subscribers'] as $subscriber) {
							$sid = intval($subscriber['subscriber_id']);
							$concat = array(
								'time' => $time,
								'data' => array(
									'aid' => intval($aid),
									'sid' => $sid,
								),
							);

							if ($sid) {
								$concat['data']['plan'] = isset($subscriber['curr_plan']) ? $subscriber['curr_plan'] : null;
								$concat['data']['next_plan'] = isset($subscriber['next_plan']) ? $subscriber['next_plan'] : null;
							} else {
								$concat['data']['plan'] = 'ACCOUNT';
								$concat['data']['next_plan'] = 'ACCOUNT';
							}

							if (isset($subscriber['occ']) && is_array($subscriber['occ'])) {
								$credits = array();
								foreach ($subscriber['occ'] as $credit) {
									$credit['aid'] = $concat['data']['aid'];
									$credit['sid'] = $concat['data']['sid'];
									if ($sid) {
										$credit['plan'] = $concat['data']['plan'];
									} else {
										$credit['subscriber_id'] = $sid;
										$credit['plan'] = 'ACCOUNT';
									}
									$credits[] = $credit;
								}
								$concat['data']['credits'] = $credits;
							}
							
							if (isset($subscriber['did_premium'])) {
								$count = intval($subscriber['did_premium']);
								 while ($count > 0) {
									 $subscriber['services'][] = 'DID_PREMIUM';
									 $count--;
									
								}
							}

							if (isset($subscriber['services']) && is_array($subscriber['services'])) {
								$reduced = array();
								foreach ($subscriber['services'] as $service_name) {
									if (!isset($reduced[$service_name])) {
										$reduced[$service_name] = 1;
									} else {
										$reduced[$service_name]+=1;
									}
								}
								$services = array();
								foreach ($reduced as $service_name => $service_count) {
									$service = array();
									$service['service_name'] = $service_name;
									$service['count'] = $service_count;
									$service['aid'] = $concat['data']['aid'];
									$service['sid'] = $concat['data']['sid'];
									if ($sid) {
										$service['plan'] = $concat['data']['plan'];
									} else {
										$service['plan'] = 'ACCOUNT';
									}
									$services[] = $service;
								}
								$concat['data']['sub_services'] = $services;
							}

							foreach ($billrun_fields as $field) {
								if (isset($subscriber[$field])) {
									$concat['data'][$field] = $subscriber[$field];
								}
							}
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

	/**
	 * for each credit create a row, parse, rate and price
	 * @param type $billrun_key
	 * @param type $retEntity
	 * @return array of credit rows
	 */
	public function getCredits($billrun_key, $retEntity = false) {
		$ret = array();
		if (!is_array($this->credits) || !count($this->credits)) {
			return $ret;
		}
		foreach ($this->credits as $credit) {
			if (!isset($credit['aid']) || !isset($credit['sid'])) {
				Billrun_Factory::log("Credit cannot be parsed for subscriber. aid or sid or both not exists. credit details: " . print_R($credit, 1), Zend_log::ALERT);
				continue;
			}

			$parsedRow = Billrun_Util::parseCreditRow($credit);
			$parsedRow['billrun'] = $billrun_key; // this will ensure we are on correct billrun even on pricing calculator
			if (empty($parsedRow)) {
				Billrun_Factory::log("Credit cannot be parsed for subscriber " . $credit['sid'] . " for billrun " . $billrun_key . " credit details: " . print_R($credit, 1), Zend_log::ALERT);
				continue;
			}
			// add rate
			if (($ratedRow = $this->creditCalc->updateRow(new Mongodloid_Entity($parsedRow))) === FALSE) {
				Billrun_Factory::log("Credit cannot be rated for subscriber " . $credit['sid'] . " for billrun " . $billrun_key . " credit details: " . print_R($credit, 1), Zend_log::ALERT);
				continue;
			}

			// add billrun, price
			if (($insertRow = $this->pricingCalc->updateRow($ratedRow)) === FALSE) {
				Billrun_Factory::log("Credit cannot be calc pricing for subscriber " . $credit['sid'] . " for billrun " . $billrun_key . " credit details: " . print_R($credit, 1), Zend_log::ALERT);
				continue;
			}

			if ($retEntity && !($insertRow instanceof Mongodloid_Entity)) {
				$ret[$insertRow['stamp']] = new Mongodloid_Entity($insertRow);
			} else if (!$retEntity && ($insertRow instanceof Mongodloid_Entity)) {
				$ret[$insertRow['stamp']] = $insertRow->getRawData();
			} else {
				$ret[$insertRow['stamp']] = $insertRow;
			}
		}
		return $ret;
	}

	/**
	 * for each service type create a row, add required fields for lines collection, rate and price
	 * @param type $billrun_key
	 * @param type $retEntity
	 * @return array of service rows
	 */
	public function getServices($billrun_key, $retEntity = false) {
		$ret = array();
		if (!is_array($this->sub_services) || !count($this->sub_services)) {
			return $ret;
		}
		foreach ($this->sub_services as $service) {
			if (!isset($service['aid']) || !isset($service['sid'])) {
				Billrun_Factory::log("Service cannot be parsed for subscriber. aid or sid or both not exists. service details: " . print_R($service, 1), Zend_log::ALERT);
				continue;
			}
			$parsedRow = Billrun_Util::parseServiceRow($service, $billrun_key);
			$parsedRow['billrun'] = $billrun_key;

			// add rate
			if (($ratedRow = $this->serviceCalc->updateRow(new Mongodloid_Entity($parsedRow))) === FALSE) {
				Billrun_Factory::log("service cannot be rated for subscriber " . $service['sid'] . " for billrun " . $billrun_key . " service details: " . print_R($service, 1), Zend_log::ALERT);
				continue;
			}

			// add billrun, price
			if (($insertRow = $this->pricingCalc->updateRow($ratedRow)) === FALSE) {
				Billrun_Factory::log("service cannot be calc pricing for subscriber " . $service['sid'] . " for billrun " . $billrun_key . " credit details: " . print_R($service, 1), Zend_log::ALERT);
				continue;
			}

			if ($retEntity && !($insertRow instanceof Mongodloid_Entity)) {
				$ret[$insertRow['stamp']] = new Mongodloid_Entity($insertRow);
			} else if (!$retEntity && ($insertRow instanceof Mongodloid_Entity)) {
				$ret[$insertRow['stamp']] = $insertRow->getRawData();
			} else {
				$ret[$insertRow['stamp']] = $insertRow;
			}
		}
		return $ret;
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
	public function getFlatEntry($billrun_key, $retEntity = false) {
		$billrun_end_time = Billrun_Util::getEndTime($billrun_key);
		$flat_entry = array(
			'aid' => $this->aid,
			'sid' => $this->sid,
			'source' => 'billrun',
			'billrun' => $billrun_key,
			'type' => 'flat',
			'usaget' => 'flat',
			'urt' => new MongoDate($billrun_end_time),
			'aprice' => $this->getFlatPrice(),
			'plan_ref' => $this->getNextPlan()->createRef(),
			'process_time' => date(Billrun_Base::base_dateformat),
		);
		$stamp = md5($flat_entry['aid'] . $flat_entry['sid'] . $billrun_end_time);
		$flat_entry['stamp'] = $stamp;
		if ($retEntity) {
			return new Mongodloid_Entity($flat_entry);
		}
		return $flat_entry;
	}

	public function getSubscribersByParams($params_arr, $availableFields) {
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
			Billrun_Factory::log()->log($message . ". Proceeding with calculation...", Zend_Log::INFO);
		} else {
			$message = 'Customer API responded with no results';
			Billrun_Factory::log()->log($message . ". Proceeding with calculation...", count($params_arr) ? Zend_Log::ALERT : Zend_Log::INFO);
		}
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

	public function getCurrentPlanName() {
		if (isset($this->data['plan'])) {
			return $this->data['plan'];
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
