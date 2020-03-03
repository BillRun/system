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
	protected $billrunExtraFields = array('kosher' => 1, 'credits' => 1, 'sub_services' => 0, 'plans' => 1); //true to save in billrun, false not to save
	protected $billrun_key;
	protected $freeze_start = null;
	protected $freeze_end = null;
	protected $services = array();
	protected $refundCredit = array(
		'credit_type' => 'refund',
		'promotion' => true,
		'reason' => 'eXSWI_CREDIT_REASON_OPERATOR_GIFT',
		'service_name' => 'CRM-REFUND_OFFER_id-BILLRUN_stamp',
		'vatable' => 1,
	);



	/**
	 * calculator for manual charges on billable
	 * 
	 * @var Billrun Calculator
	 */
	protected $creditCalc = null;
	protected $pricingCalc = null;
	protected $serviceCalc = null;
	protected $billing_method = null;

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
		$this->billing_method = Billrun_Factory::config()->getConfigValue('golan.flat_charging', "postpaid");
		$creditCalcOptions = array_merge(array('type' => 'Rate_Credit', 'autoload' => false), Billrun_Factory::config()->getConfigValue('Rate_Credit.calculator', array()));
		$this->creditCalc = Billrun_Calculator::getInstance($creditCalcOptions);
		$pricingCalcOptions = array_merge(array('type' => 'customerPricing', 'autoload' => false), Billrun_Factory::config()->getConfigValue('customerPricing.calculator', array()));
		$this->pricingCalc = Billrun_Calculator::getInstance($pricingCalcOptions);
		$serviceCalcOptions = array_merge(array('type' => 'Rate_Service', 'autoload' => false), Billrun_Factory::config()->getConfigValue('Rate_Service.calculator', array()));
		$this->serviceCalc = Billrun_Calculator::getInstance($serviceCalcOptions);

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
		Billrun_Factory::log("Subscriber API chosen host: " . $host, Zend_Log::INFO);
		$url = Billrun_Factory::config()->getConfigValue('provider.rpc.url', '');

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
	protected function requestAccounts($params, $saveToFile = false) {
		$host = self::getRpcServer();
		Billrun_Factory::log("Subscriber API chosen host: " . $host, Zend_Log::INFO);
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
							$uniquePlanId = null;
							$concat = array(
								'time' => $time,
								'data' => array(
									'aid' => intval($aid),
									'sid' => $sid,
								),
							);
							$subServices = empty($subscriber['services']) ? array() : $subscriber['services'];
							$freezeService = array_filter($subServices, function($service) {
								return $service['service_name'] == 'FREEZE_FLAT_RATE';
							});
							if (count($freezeService) > 1) {
								Billrun_Factory::log()->log("There can only be one freeze service for subscriber", Zend_Log::ALERT);
							}
							$subscriber['freeze'] = current($freezeService);
							
							$concat['data']['activation_start'] = isset($subscriber['activation']) ? Billrun_Util::convertToBillrunDate($subscriber['activation']) : null;
							$concat['data']['activation_end'] = isset($subscriber['deactivate']) ? Billrun_Util::convertToBillrunDate($subscriber['deactivate']) : null;
							$concat['data']['freeze_start_date'] = isset($subscriber['freeze']['from_date']) ? Billrun_Util::convertToBillrunDate($subscriber['freeze']['from_date']) : null;
							$this->freeze_start = $concat['data']['freeze_start_date'];
							$concat['data']['freeze_end_date'] = isset($subscriber['freeze']['to_date']) ? Billrun_Util::convertToBillrunDate($subscriber['freeze']['to_date']) : null;
							if ((!is_null($concat['data']['activation_end'])) && (!is_null($concat['data']['freeze_end_date'])) && ($concat['data']['activation_end'] < $concat['data']['freeze_end_date'])){
								$concat['data']['freeze_end_date'] = $concat['data']['activation_end'];
							}
							$this->freeze_end = $concat['data']['freeze_end_date'];
							$concat['data']['fraction'] = $this->calcFractionOfMonth($concat['data']['activation_start'], $concat['data']['activation_end'], $sid);
				
							$subscriberOffers = !is_null($subscriber['offers']) ? $subscriber['offers'] : array();
							$disconnectedPlans = !empty($subscriber['plan_before_disc']) ? $subscriber['plan_before_disc'] : array();
							$offers = array();
							$offerCredits = array();
							if (!empty($subscriberOffers) && !empty($disconnectedPlans)) {
								Billrun_Factory::log()->log("There's a disconnectd plan and offers, there can be only one set", Zend_Log::ALERT);
							}
							if (!empty($subscriberOffers) || !empty($disconnectedPlans)) {
								foreach ($disconnectedPlans as $key => $disconnectedPlan) {
									$disconnectedPlan['disconnected_plan'] = true;
									$disconnectedPlans[$key] = $disconnectedPlan;
								}
								$subscriberOffers = !empty($disconnectedPlans) && empty($subscriberOffers) ? $disconnectedPlans : $subscriberOffers;
								foreach ($subscriberOffers as $subscriberOffer) {
									$offer = array();
									if (empty($uniquePlanId)) {
										$uniquePlanId = self::generatePlanUniqueId($subscriberOffer['offer_id'], $subscriberOffer['start_date']);
									}
									$offer['id'] = $subscriberOffer['offer_id'];
									$offer['plan'] = $subscriberOffer['plan'];
									$offer['start_date'] = $subscriberOffer['start_date'];
									$offer['end_date'] = $subscriberOffer['end_date'];
									$offer['unique_plan_id'] = self::generatePlanUniqueId($subscriberOffer['offer_id'], $subscriberOffer['start_date']);
									$offer['fraction'] = $this->calcServiceFractionIncludingFreeze($offer, $sid);	
									$offer['count'] = 1;
									$offer['offer_amount'] = $subscriberOffer['offer_amount'];
									$offer['amount_without_vat'] = $subscriberOffer['amount_without_vat'];
									if (!empty($subscriberOffer['disconnected_plan'])) {
										$offer['disconnected_plan'] = $subscriberOffer['disconnected_plan'];
									}
									// As agreed - this was commented out because this fallback no longer neccessary
//									if (!isset($subscriberOffer['offer_amount']) && isset($subscriberOffer['amount_without_vat']) && $subscriberOffer['amount_without_vat'] > 0) {
//										$offer['amount_without_vat'] = $subscriberOffer['amount_without_vat'];
//										$offerCredit = $this->refundCredit;
//										$replacedStampOfferService = preg_replace('/stamp/', $this->billrun_key, $offerCredit['service_name']);
//										$offerCredit['service_name'] = preg_replace('/id/', $offer['id'], $replacedStampOfferService);
//										$offerCredit['offer_id'] = $subscriberOffer['offer_id'];
//										$offerCredit['unique_plan_id'] = self::generatePlanUniqueId($subscriberOffer['offer_id'], $subscriberOffer['start_date']);
//										$offerCredit['aid'] = $offerCredit['account_id'] = $concat['data']['aid'];
//										$offerCredit['sid'] = $offerCredit['subscriber_id'] = $concat['data']['sid'];
//										$offerCredit['activation'] = $concat['data']['activation_start'];
//										$offerCredit['deactivation'] = $concat['data']['activation_end'];
//										$offerCredit['fraction'] = $offer['fraction'];
//										$offerCredit['credit_time'] = Billrun_Util::getEndTime($this->billrun_key);
//										if ($sid) {
//											$offerCredit['plan'] = $subscriberOffer['plan'];
//										} else {
//											$offerCredit['subscriber_id'] = $sid;
//											$offerCredit['plan'] = 'ACCOUNT';
//										}
//										$offerCredit['amount_without_vat'] = $offer['amount_without_vat'];
//										$offerCredit['source_amount_without_vat'] = $offer['amount_without_vat'];
//										$offerCredit['amount_without_vat'] = $this->isFractionNeeded($offerCredit) ? ($offerCredit['amount_without_vat'] * $offerCredit['fraction']) : $offerCredit['amount_without_vat'];
//										$offerCredits[] = $offerCredit;									
//									}
									if ((!is_null($concat['data']['activation_end'])) && ($concat['data']['activation_end'] < $offer['end_date'])){
										$offer['end_date'] = $concat['data']['activation_end'];
									}

									$offer['aid'] = $concat['data']['aid'];
									$offer['sid'] = $concat['data']['sid'];
									$stamp = Billrun_Util::generateArrayStamp($offer);
									if (isset($offers[$stamp])) {
										$offers[$stamp]['count'] ++;
										continue;
									}
									$offers[$stamp] = $offer;
								}
								$offers = array_values($offers);
								$concat['data']['plans'] = $offers;
							}											
							if ($sid) {
								$concat['data']['plan'] = !is_null($subscriber['offers']) ? @reset($this->getPlanNames($concat['data']['plans'])) : null;
							} else {
								$concat['data']['plan'] = 'ACCOUNT';
							}

							if (isset($subscriber['occ']) && is_array($subscriber['occ'])) {
								$credits = array();
								foreach ($subscriber['occ'] as $credit) {
									if (!isset($credit['account_id'])) {
										Billrun_Factory::log("No account id for credit line. Parent account id is " . $aid, Zend_log::ALERT);
										continue;
									}
									if ($aid != $credit['account_id']) {
										Billrun_Factory::log("Credit account id " . $credit['account_id'] . " is different from parent account id " . $aid, Zend_log::WARN);
										continue;
									}
									$credit['aid'] = $concat['data']['aid'];
									$credit['sid'] = $concat['data']['sid'];
									$credit['activation'] = $concat['data']['activation_start'];
									$credit['deactivation'] = $concat['data']['activation_end'];
									$credit['fraction'] = $concat['data']['fraction'];
									if (!empty($uniquePlanId)) {
										$credit['unique_plan_id'] = $uniquePlanId;
									} else {
										Billrun_Factory::log("Credit for sid " . $sid . " is missing unique_plan_id field", Zend_log::WARN);
									}
									if ($sid) {
										$credit['plan'] = empty($concat['data']['plan']) ? "ACCOUNT" : $concat['data']['plan'];
									} else {
										$credit['subscriber_id'] = $sid;
										$credit['plan'] = 'ACCOUNT';
									}
									$credit['source_amount_without_vat'] = $credit['amount_without_vat'];
									$credit['amount_without_vat'] = $this->isFractionNeeded($credit) ? ($credit['amount_without_vat'] * $concat['data']['fraction']) : $credit['amount_without_vat'];
									$credits[] = $credit;
								}
								if (!empty($offerCredits)) {
									$credits = array_merge($credits, $offerCredits);
								}
								$concat['data']['credits'] = $credits;
							} else {
								if (!empty($offerCredits)) {
									$concat['data']['credits'] = $offerCredits;
								}
							}
							
							if (isset($subscriber['services']) && is_array($subscriber['services'])) {
								$services = array();
								foreach ($subscriber['services'] as $serviceDetails) {
									$service = array();
									$service['id'] = $serviceDetails['id'];
									$service['service_name'] = $serviceDetails['service_name'];
									$service['usaget'] = $serviceDetails['type'];
									$service['from_date'] = $serviceDetails['from_date'];
									$service['to_date'] = $serviceDetails['to_date'];
									if ((!is_null($concat['data']['activation_end'])) && ($concat['data']['activation_end'] < $service['to_date'])){
										$service['to_date'] = $concat['data']['activation_end'];
									}
									$service['fraction'] = $this->calcServiceFraction($service['from_date'], $service['to_date'], $sid);
									if (!empty($uniquePlanId)) {
										$service['unique_plan_id'] = $uniquePlanId;
									} else {
										Billrun_Factory::log("Service for sid " . $sid . " is missing unique_plan_id field", Zend_log::WARN);
									}
									$service['count'] = 1;
									$service['aid'] = $concat['data']['aid'];
									$service['sid'] = $concat['data']['sid'];
									if ($sid) {
										$service['plan'] = $concat['data']['plan'];
									} else {
										$service['plan'] = 'ACCOUNT';
									}
									if (is_null($service['plan'])) {
										continue;
									}
									$stamp = Billrun_Util::generateArrayStamp($service);
									if (isset($services[$stamp])) {
										$services[$stamp]['count'] ++;
										continue;
									}
									$services[$stamp] = $service;
								}
								$services = array_values($services);
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
			$usaget = $service['usaget'];
			$parsedRow = Billrun_Util::parseServiceRow($service, $billrun_key);
			$parsedRow['billrun'] = $billrun_key;

			// add rate
			if (($ratedRow = $this->serviceCalc->updateRow(new Mongodloid_Entity($parsedRow))) === FALSE) {
				Billrun_Factory::log("service cannot be rated for subscriber " . $service['sid'] . " for billrun " . $billrun_key . " service details: " . print_R($service, 1), Zend_log::ALERT);
				continue;
			}
			
			$ratedRow['usaget'] = $usaget;
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

	public function getPlans() {
		return $this->plans;
	}
	
	public function getLastOffer() {
		$offers = $this->plans;
		if (empty($offers)) {
			return array();
		}
		$lastOffer = array();
		foreach ($offers as $offer) {
			if (empty($lastOffer)) {
				$lastOffer = $offer;
			}
			if ($offer['end_date'] > $lastOffer['end_date']) {
				$lastOffer = $offer;
			}
		}
		return $lastOffer;
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
		if (is_null($this->plan) || (!is_null($this->plan) && ($this->data['plan'] != $this->plan->getName()))) {
			$params = array(
				'name' => $this->data['plan'],
				'time' => $this->time,
			);
			$this->plan = new Billrun_Plan($params);
		}
		return $this->plan;
	}
	
	public function setPlanName($planName) {
		$this->data['plan'] = $planName;
	}

	public function setPlanId($id) {
		$this->data['offer_id'] = $id;
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
	
	
	public function isFractionNeeded($credit){
		if (((isset($credit['promotion'])) && ($credit['promotion'] == true)) || ($credit['sid'] == 0)) {
			return true;
		}
		return false;
	}
	
	
	public function getFlatPrice($fraction = 1) {
		if ($this->billing_method == 'prepaid') {
			return ($this->getNextPlan()->getPrice() * $fraction);
		}
		return ($this->getPlan()->getPrice() * $fraction);
	}

	/**
	 * for each subscriber calculates the relative part of the month the subsriber was in the plan.
	 * @param $start_date the date the subscriber started the plan
	 * @param $end_date the date the subscriber was no longer in the plan
	 * @return fraction from the whole month
	 */
	public function calcFractionOfMonth($start_date, $end_date, $sid) {
		$billing_start_date = Billrun_Util::getStartTime($this->billrun_key);
		$days_in_month = (int) date('t', $billing_start_date);
		$freeze_days = $this->isFreezeExists() ?  $this->getNumberOfDays($this->getFreezeStartDay(), $this->getFreezeEndDay()) : 0;
		$plan_active_days = $this->getNumberOfDays($start_date, $end_date) - $freeze_days;
		$fraction = $plan_active_days / $days_in_month;
		if ($this->isIllegalFraction($fraction)){
			Billrun_Log::getInstance()->log("Fraction " . $fraction . " is illegal value for fraction, subscriber_id: " . $sid , Zend_log::ALERT);
			$fraction = 0;
		}
		
		return $fraction;
	}

	public function getActivationStartDay() {
		if (isset($this->data['activation_start'])){
			return $this->data['activation_start'];
		}
		return null;
	}

	public function getActivationEndDay() {
		if (isset($this->data['activation_end'])){
			return $this->data['activation_end'];
		}
		return null;
	}

	public function getFreezeStartDay() {
		if (is_null($this->freeze_start)) {
			if (isset($this->data['freeze_start_date']) && !is_null($this->data['freeze_start_date'])) {
				return $this->data['freeze_start_date'];
			}
			return null;
		}
		return $this->freeze_start;
	}

	public function getFreezeEndDay() {
		if (is_null($this->freeze_end)) {
			return $this->data['freeze_end_date'];
		}
		return $this->freeze_end;
	}

	public function calcServiceFraction($service_start, $service_end, $sid) {
		$billing_start_date = Billrun_Util::getStartTime($this->billrun_key);
		$days_in_month = (int) date('t', $billing_start_date);
		$service_days = $this->getNumberOfDays($service_start, $service_end);
		$fraction = $service_days / $days_in_month;
		if ($this->isIllegalFraction($fraction)){
			Billrun_Log::getInstance()->log("Fraction " . $fraction . " is illegal value for fraction, subscriber_id: " . $sid , Zend_log::ALERT);
			$fraction = 0;
		}
		
		return $fraction;
	}

	public function isFreezeExists(){
		$freeze_start = $this->getFreezeStartDay();
		 if (is_null($freeze_start)){
			 return false;
		 }
		 return true;
	}

	
	public function getNumberOfDays($start_date, $end_date){
		$billing_start_date = Billrun_Util::getStartTime($this->billrun_key);
		$billing_end_date = Billrun_Util::getEndTime($this->billrun_key);
		$days_in_month = (int) date('t', $billing_start_date);
		$temp_start = strtotime($start_date);
		$temp_end = is_null($end_date) ? PHP_INT_MAX : strtotime($end_date);
		$start = $billing_start_date > $temp_start ? $billing_start_date : $temp_start;
		$end = $billing_end_date < $temp_end ? $billing_end_date : $temp_end;
		if ($end < $start) {
			return 0;
		}
		$start_day = date('j', $start);
		$end_day = date('j', $end);
		$start_month = date('F', $start);
		$end_month = date('F', $end);

		if ($start_month == $end_month) {
			$days_in_plan = (int) $end_day - (int) $start_day + 1;
		} else {
			$days_in_previous_month = $days_in_month - (int) $start_day + 1;
			$days_in_current_month = (int) $end_day;
			$days_in_plan = $days_in_previous_month + $days_in_current_month;
		}
		return $days_in_plan;
	}
	

	public function chargeByPlan(){
		if ($this->billing_method == 'prepaid'){
			return $this->getNextPlanName();
		}
		
		return $this->getCurrentPlanName();
	}
	
	public function setBillrunKey($billrun_key){
		$this->billrun_key = $billrun_key;
	}

	/**
	 * 
	 * @param string $billrun_key
	 * @return array
	 */
	public function getFlatEntry($billrun_key, $retEntity = false, $offer = false) { // TODO: take all flat properties from offer if offer not empty.
		$billrun_end_time = Billrun_Util::getEndTime($billrun_key);
		if (empty($offer) || !isset($offer['fraction'])) {
			$fraction = $this->calcFractionOfMonth($this->getActivationStartDay(), $this->getActivationEndDay(), $this->sid);
		} else {
			$fraction = $offer['fraction'];
		}
		if ($this->billing_method == 'prepaid'){
			$plan = $this->getNextPlan();
		}
		else {
			$plan = $this->getPlan();
		}

		$flat_entry = array(
			'aid' => $this->aid,
			'sid' => $this->sid,
			'activation' => $this->getActivationStartDay(),
			'deactivation' => $this->getActivationEndDay(),
			'fraction' => $fraction,
			'source' => 'billrun',
			'billrun' => $billrun_key,
			'type' => 'flat',
			'usaget' => 'flat',
			'urt' => new MongoDate($billrun_end_time),
			'aprice' => $offer['offer_amount'] * $fraction,
			'aprice_no_vat' => isset($offer['amount_without_vat']) ? $offer['amount_without_vat'] * $fraction : 0,
			'plan' => $plan->getName(),
			'plan_ref' => $plan->createRef(),
			'process_time' => date(Billrun_Base::base_dateformat),
			'offer_id' => $this->offer_id,
		);
		if (!empty($offer) && isset($offer['start_date']) && isset($offer['end_date'])) {
			$flat_entry['start_date'] = $offer['start_date'];
			$flat_entry['end_date'] = $offer['end_date'];
		}
		if (!empty($offer) && isset($offer['start_date']) && isset($offer['id'])) {
			$flat_entry['unique_plan_id'] = self::generatePlanUniqueId($offer['id'], $offer['start_date']);
		}
		$flat_entry['total_aprice'] = $flat_entry['aprice_no_vat'] + $flat_entry['aprice'];
		$stamp = md5($flat_entry['aid'] . $flat_entry['sid'] . $flat_entry['type'] . $billrun_end_time . $flat_entry['plan'] . $flat_entry['fraction']);
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
				if (isset($item['IRP']) && $item['IRP'] && !empty($item['prepaid'])) {
					unset($item['prepaid']);
				}
				if (is_array($item)) {
					foreach ($availableFields as $key => $field) {
						if (isset($item[$field])) {
							$temp = $item[$field];
							unset($item[$field]);
							$item[$key] = $temp;
						}
					}

					if(!empty($item['addons'])) {
						$balanceMonthKey = Billrun_Util::getBillrunKey(strtotime($item['DATETIME']));
						$balanceStartDate = Billrun_Util::getStartTime($balanceMonthKey);
						$balanceEndDate = Billrun_Util::getEndTime($balanceMonthKey);
						foreach($item['addons']  as &$package) {
								if(in_array($package['service_name'], Billrun_Factory::config()->getConfigValue('subscriber.monthly_bound_services',["IRP_VF_10_DAYS"])) ) {
									$package['balance_from_date'] = max(strtotime($package['from_date']),$balanceStartDate);
									$package['balance_to_date'] = min(strtotime($package['to_date']),$balanceEndDate);
								}
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
		Billrun_Factory::log("Subscriber API chosen host: " . $host, Zend_Log::INFO);
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
		
		foreach ($arr as $key => $line) {
			if (isset($line['offer_id']) && isset($line['offer_start_date'])) {
				$billrunKey = Billrun_Util::getBillrunKey(strtotime($line['time']));
				$billrunStart = Billrun_Util::getStartTime($billrunKey);
				$billrunEnd = Billrun_Util::getEndTime($billrunKey);
				if (strtotime($line['offer_start_date']) < $billrunStart) {
					$arr[$key]['offer_start_date'] = date(Billrun_Base::base_dateformat, $billrunStart);
				}
				if (strtotime($line['offer_end_date']) > $billrunEnd) {
					$arr[$key]['offer_end_date'] = date(Billrun_Base::base_dateformat, $billrunEnd);
				}
				$arr[$key]['unique_plan_id'] = self::generatePlanUniqueId($line['offer_id'], $arr[$key]['offer_start_date']);
			}
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
	
	
	protected function isIllegalFraction($fraction){
		if ($fraction < 0 || $fraction > 1){
			return true;
		}
		return false;
	}
	
	public function isPrepaidAccount() {
		return (isset($this->data['prepaid']) && $this->data['prepaid']); 
	}

	protected function getPlanNames($plans) {
		foreach ($plans as $plan) {
			$planNames[] = $plan['plan'];
		}
		return $planNames;
	}
	
	protected function calcServiceFractionIncludingFreeze($offer, $sid) {
		$billingStartDate = Billrun_Util::getStartTime($this->billrun_key);
		$daysInMonth = (int) date('t', $billingStartDate);
		if ($this->isFreezeExists()) {
			$offerStartDate = (new DateTime())->setTimestamp(strtotime($offer['start_date']));
			$offerEndDate = (new DateTime())->setTimestamp(strtotime($offer['end_date']));
			$freezeStartDate = (new DateTime())->setTimestamp(strtotime($this->getFreezeStartDay()));
			$freezeEndDate = (new DateTime())->setTimestamp(strtotime($this->getFreezeEndDay()));
			$overlappingFreezeDays = $this->getNumberOfOverlappingDays($offerStartDate, $offerEndDate, $freezeStartDate, $freezeEndDate);
		} else {
			$overlappingFreezeDays = 0;
		}
		$serviceDays = $this->getNumberOfDays($offer['start_date'], $offer['end_date']);
		$serviceActiveDays = $serviceDays - $overlappingFreezeDays;
		$fraction = $serviceActiveDays / $daysInMonth;
		if ($this->isIllegalFraction($fraction)){
			Billrun_Log::getInstance()->log("Fraction " . $fraction . " is illegal value for fraction, subscriber_id: " . $sid , Zend_log::ALERT);
			$fraction = 0;
		}
		
		return $fraction;
	}
	
	protected function getNumberOfOverlappingDays($startOne, $endOne, $startTwo, $endTwo) {
		if($startOne <= $endTwo && $endOne >= $startTwo) {
			return min($endOne, $endTwo)->diff(max($startTwo, $startOne))->days + 1;
		}
		
		return 0;
	}
	
	protected static function generatePlanUniqueId($offerId, $offerStartDate) {
		return (int)($offerId . strtotime($offerStartDate));
	}

}
