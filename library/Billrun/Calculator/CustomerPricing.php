<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing calculator for  pricing  billing lines with customer price.
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Calculator_CustomerPricing extends Billrun_Calculator {

	protected $server_id = 1;
	protected $server_count = 1;
	protected $pricingField = 'price_customer';
	static protected $type = "pricing";

	/**
	 *
	 * @var boolean is customer price vatable by default
	 */
	protected $vatable = true;

	/**
	 *
	 * @var string
	 */
	protected $runtime_billrun_key;
	/**
	 *
	 * @var int timestamp
	 */
	protected $billrun_lower_bound_timestamp;

	public function __construct($options = array()) {
		$options['autoload'] = false;
		parent::__construct($options);

		if (isset($options['calculator']['limit'])) {
			$this->limit = $options['calculator']['limit'];
		}
		if (isset($options['calculator']['vatable'])) {
			$this->vatable = $options['calculator']['vatable'];
		}
		if (isset($options['calculator']['months_limit'])) {
			$this->months_limit = $options['calculator']['months_limit'];
		}
		$this->runtime_billrun_key = Billrun_Util::getBillrunKey(time());
		$this->billrun_lower_bound_timestamp = is_null($this->months_limit) ? 0 : strtotime($this->months_limit . " months ago");
		// set months limit
		$this->load();
	}

	protected function getLines() {
//		$queue = Billrun_Factory::db()->queueCollection();
//		$query = self::getBaseQuery();
		$query = array();
		$query['type'] = array('$in' => array('ggsn', 'smpp', 'smsc', 'nsn', 'tap3'));
		$query['$or'][] = array('account_id' => array('$exists' => false));
		$query['$or'][] = array('account_id' => array('$mod' => array($this->server_count, $this->server_id - 1)));
//		$update = self::getBaseUpdate();
//		$options = array('sort' => array('unified_record_time' => 1));
//		$i = 0;
//		$docs = array();
//		while ($i < $this->limit && ($doc = $queue->findAndModify($query, $update, array(), $options)) && !$doc->isEmpty()) {
//			$docs[] = $doc;
//			$i++;
//		}
		return $this->getQueuedLines($query);
	}

	/**
	 * execute the calculation process
	 * @TODO this function mighh  be a duplicate of  @see Billrun_Calculator::calc() do we really  need the diffrence  between Rate/Pricing?
	 */
	public function calc() {
		Billrun_Factory::dispatcher()->trigger('beforePricingData', array('data' => $this->data));
		$lines_coll = Billrun_Factory::db()->linesCollection();
		foreach ($this->lines as $key => $item) {
			$line = $this->pullLine($item);
			if ($line) {
				Billrun_Factory::dispatcher()->trigger('beforePricingDataRow', array('data' => &$line));
				//Billrun_Factory::log()->log("Calcuating row : ".print_r($item,1),  Zend_Log::DEBUG);
				$line->collection($lines_coll);
				if($this->isLineLegitimate($line)) {
					if (!$this->updateRow($line)) {
						unset($this->lines[$key]);
						continue;
					}
					$this->writeLine($line);
					$this->data[] = $line;
				}
				//$this->updateLinePrice($item); //@TODO  this here to prevent divergance  between the priced lines and the subscriber's balance/billrun if the process fails in the middle.
				Billrun_Factory::dispatcher()->trigger('afterPricingDataRow', array('data' => &$line));
			} else {
				unset($this->lines[$key]);
			}
		}
		Billrun_Factory::dispatcher()->trigger('afterPricingData', array('data' => $this->data));
	}

	protected function updateRow($row) {
		$rate = $row->get('customer_rate');
		
		$billrun_key = Billrun_Util::getBillrunKey($row['unified_record_time']->sec);

		//TODO  change this to be configurable.
		$pricingData = array();

		$usage_type = $row['usaget'];
		$volume = $row['usagev'];
		if ($row['type'] == 'tap3') {
			$usage_class_prefix = "intl_roam_";
		} else {
			$usage_class_prefix = "";
		}

		if (isset($volume)) {
			if ($row['type'] == 'credit') {
				$accessPrice = isset($rate['rates'][$usage_type]['access']) ? $rate['rates'][$usage_type]['access'] : 0;
				$pricingData = array($this->pricingField => $accessPrice + $this->getPriceByRates($rate['rates'][$usage_type]['rate'], $volume));
			} else {
				$pricingData = $this->updateSubscriberBalance(array($usage_class_prefix . $usage_type => $volume), $row, $billrun_key, $usage_type, $rate, $volume);
			}
			$vatable = (!(isset($rate['vatable']) && !$rate['vatable']) || (!isset($rate['vatable']) && !$this->vatable));
			if (!$this->updateBillrun($billrun_key, $row['account_id'], $row['subscriber_id'], array($usage_type => $volume), $pricingData, $row, $vatable)) {
				return false;
			}
		} else {
			Billrun_Factory::log()->log("Line with stamp " . $row['stamp'] . " is missing volume information", Zend_Log::ALERT);
			return false;
		}
		$row->setRawData(array_merge($row->getRawData(), $pricingData));
		return true;
	}

	/**
	 * @TODO
	 * @param type $billrun_key
	 * @param type $account_id
	 * @param type $subscriber_id
	 * @param type $counters
	 * @param type $pricingData
	 * @param type $row
	 * @param type $vatable
	 * @return boolean
	 */
	protected function updateBillrun($billrun_key, $account_id, $subscriber_id, $counters, $pricingData, $row, $vatable) {
		$billrun_coll = Billrun_Factory::db()->billrunCollection();
		$usage_type = $this->getGeneralUsageType($row['usaget']);
		$vat_key = ($vatable ? "vatable" : "vat_free");
		$row_ref = $row->createRef();

		$query = array(
			'account_id' => $account_id,
			'billrun_key' => $billrun_key,
			'invoice_id' => array(
				'$exists' => false,
			),
			'subs' => array(
				'$elemMatch' => array(
					'sub_id' => $subscriber_id,
					'lines.' . $usage_type . '.refs' => array(
						'$nin' => array(
							$row_ref
						)
					)
				)
			),
		);

		$update = array();
		$options = array('w' => 1);

		if (isset($pricingData['over_plan']) && $pricingData['over_plan']) {
			$update['$inc']['subs.$.costs.over_plan.' . $vat_key] = $pricingData['price_customer'];
		} else if (isset($pricingData['out_plan']) && $pricingData['out_plan']) {
			$update['$inc']['subs.$.costs.out_plan.' . $vat_key] = $pricingData['price_customer'];
		} else if ($row['type'] == 'flat') {
			$update['$inc']['subs.$.costs.flat.' . $vat_key] = $pricingData['price_customer'];
		} else if ($row['type'] == 'credit') {
			$update['$inc']['subs.$.costs.credit.' . $row['credit_type'] . '.' . $vat_key] = $pricingData['price_customer'];
		}

		if ($row['type'] != 'flat') {
			$rate = $row['customer_rate'];
		}

		// update data counters
		if ($usage_type == 'data') {
			$date_key = date("Ymd", $row['unified_record_time']->sec);
			$update['$inc']['subs.$.lines.data.counters.' . $date_key] = $row['usagev'];
		}

		$update['$push']['subs.$.lines.' . $usage_type . '.refs'] = $row_ref;

		// addToBreakdown
		if ($row['type'] == 'credit') {
			$plan_key = 'credit';
			$zone_key = $row['reason'];
		} else if (!isset($pricingData['over_plan']) && !isset($pricingData['out_plan'])) { // in plan
			$plan_key = 'in_plan';
			if ($row['type'] == 'flat') {
				$zone_key = 'service';
			}
		} else if (isset($pricingData['over_plan']) && $pricingData['over_plan']) { // over plan
			$plan_key = 'over_plan';
		} else { // out plan
			$plan_key = "out_plan";
		}

		if ($row['type'] == 'credit') {
			$category_key = $row['credit_type'];
		} else if (isset($rate['rates'][$row['usaget']]['category'])) {
			$category = $rate['rates'][$row['usaget']]['category'];
			switch ($category) {
				case "roaming":
					$category_key = "roaming";
					$zone_key = $row['serving_network'];
					break;
				case "special":
					$category_key = "special";
					break;
				case "intl":
					$category_key = "intl";
					break;
				default:
					$category_key = "base";
					break;
			}
		} else {
			$category_key = "base";
		}

		if (!isset($zone_key)) {
			$zone_key = $row['customer_rate']['key'];
		}

		if ($plan_key != 'credit') {
			if (!empty($counters)) {
				if (!empty($pricingData) && isset($pricingData['over_plan']) && $pricingData['over_plan'] < current($counters)) { // volume is partially priced (in & over plan)
					$volume_priced = $pricingData['over_plan'];
					$update['$inc']['subs.$.breakdown.in_plan.' . $category_key . '.' . $zone_key . '.totals.' . key($counters) . '.usagev'] = current($counters) - $volume_priced; // add partial usage to flat
				} else {
					$volume_priced = current($counters);
				}
				$update['$inc']['subs.$.breakdown.' . $plan_key . '.' . $category_key . '.' . $zone_key . '.totals.' . key($counters) . '.usagev'] = $volume_priced;
				$update['$inc']['subs.$.breakdown.' . $plan_key . '.' . $category_key . '.' . $zone_key . '.totals.' . key($counters) . '.cost'] = $pricingData['price_customer'];
				if ($plan_key != 'in_plan') {
					$update['$inc']['subs.$.breakdown.' . $plan_key . '.' . $category_key . '.' . $zone_key . '.cost'] = $pricingData['price_customer'];
				}
			} else if ($zone_key == 'service') { // flat
				$update['$inc']['subs.$.breakdown.' . $plan_key . '.' . $category_key . '.' . $zone_key . '.cost'] = $pricingData['price_customer'];
			}
			$update['$set']['subs.$.breakdown.' . $plan_key . '.' . $category_key . '.' . $zone_key . '.vat'] = ($vatable ? floatval(Billrun_Factory::config()->getConfigValue('pricing.vat', 0.18)) : 0); //@TODO we assume here that all the lines would be vatable or all vat-free
		} else {
			$update['$inc']['subs.$.breakdown.' . $plan_key . '.' . $category_key . '.' . $zone_key] = $pricingData['price_customer'];
		}

		$doc = $billrun_coll->update($query, $update, $options);

		// recovery
		if (!$doc['ok'] || ($doc['ok'] && !$doc['updatedExisting'])) { // billrun document was not found
			$billrun = $this->createBillrunIfNotExists($account_id, $billrun_key);
			if ($billrun->isEmpty()) { // means that the billrun was created so we can retry updating it
				return $this->updateBillrun($billrun_key, $account_id, $subscriber_id, $counters, $pricingData, $row, $vatable);
			} else if ($this->addSubscriberIfNotExists($account_id, $subscriber_id, $billrun_key)) {
				return $this->updateBillrun($billrun_key, $account_id, $subscriber_id, $counters, $pricingData, $row, $vatable);
			} else if ($this->lineRefExists($account_id, $subscriber_id, $billrun_key, $usage_type, $row_ref)) {
				Billrun_Factory::log()->log("Line with stamp " . $row['stamp'] . " already exists in billrun " . $billrun_key . " for account " . $account_id, Zend_Log::NOTICE);
				return true;
			} else {
				if ($billrun_key == $this->runtime_billrun_key) {
					Billrun_Factory::log()->log("Current billrun is closed for account " . $account_id . " for billrun " . $billrun_key, Zend_Log::NOTICE);
					return false;
				} else {
					return $this->updateBillrun($this->runtime_billrun_key, $account_id, $subscriber_id, $counters, $pricingData, $row, $vatable);
				}
			}
		}
		return true;
	}
	/**
	 * @TODO
	 * @param type $account_id
	 * @param type $subscriber_id
	 * @param type $billrun_key
	 * @param type $usage_type
	 * @param type $line_ref
	 * @return boolean true if the line reference allready exists.
	 */
	protected function lineRefExists($account_id, $subscriber_id, $billrun_key, $usage_type, $line_ref) {
		$billrun_coll = Billrun_Factory::db()->billrunCollection();
		$query = array(
			'account_id' => $account_id,
			'billrun_key' => $billrun_key,
			'invoice_id' => array(
				'$exists' => false,
			),
			'subs' => array(
				'$elemMatch' => array(
					'sub_id' => $subscriber_id,
					'lines.' . $usage_type . '.refs' => array(
						'$in' => array(
							$line_ref
						)
					)
				)
			),
		);
		return ($billrun_coll->find($query)->count() > 0);
	}
	
	/**
	 * @TODO
	 * @param type $account_id
	 * @param type $billrun_key
	 * @return type
	 */
	protected function createBillrunIfNotExists($account_id, $billrun_key) {
		$billrun_coll = Billrun_Factory::db()->billrunCollection();
		$query = array(
			'account_id' => $account_id,
			'billrun_key' => $billrun_key,
		);
		$update = array(
			'$setOnInsert' => Billrun_Billrun::getAccountEmptyBillrunEntry($account_id, $billrun_key),
		);
		$options = array(
			'upsert' => true,
			'new' => false,
		);
		return $billrun_coll->findAndModify($query, $update, array(), $options);
	}

	protected function addSubscriberIfNotExists($account_id, $subscriber_id, $billrun_key) {
		$billrun_coll = Billrun_Factory::db()->billrunCollection();
		$query = array(
			'account_id' => $account_id,
			'billrun_key' => $billrun_key,
			'$or' => array(
				array(
					'subs.sub_id' => array(
						'$exists' => false,
					),),
				array(
					'subs' => array(
						'$not' => array(
							'$elemMatch' => array(
								'sub_id' => $subscriber_id,
							),
						),
					),
				),
			),
			'invoice_id' => array(
				'$exists' => false,
			),
		);
		$update = array(
			'$push' => array(
				'subs' => Billrun_Billrun::getEmptySubscriberBillrunEntry($subscriber_id),
			),
		);
		$options = array(
//			'new' => false,
			'w' => 1,
		);
//		$output = $billrun_coll->update($query, $update, array(), $options);
		$output = $billrun_coll->update($query, $update, $options);
		return $output['ok'] && $output['updatedExisting'];
	}

	/**
	 * 
	 * @param string $specific_usage_type specific usage type (usually lines' 'usaget' field) such as 'call', 'incoming_call' etc.
	 */
	public static function getGeneralUsageType($specific_usage_type) {
		switch ($specific_usage_type) {
			case 'call':
			case 'incoming_call':
				return 'call';
			case 'sms':
			case 'incoming_sms':
				return 'sms';
			case 'data':
				return 'data';
			case 'mms':
				return 'mms';
			case 'flat':
				return 'flat';
			case 'credit':
				return 'credit';
			default:
				return 'call';
		}
	}
	/**
	 * Get pricing data for a given rate / subcriber.
	 * @param int $volume The usage volume (seconds of call, count of SMS, bytes  of data)
	 * @param string $usageType The type  of the usage (call/sms/data)
	 * @param mixed $rate The rate of associated with the usage.
	 * @param mixed $subr the  subscriber that generated the usage.
	 * @return Array the 
	 */
	protected function getLinePricingData($volumeToPrice, $usageType, $rate, $subr) {
		$typedRates = $rate['rates'][$usageType];
		$accessPrice = isset($typedRates['access']) ? $typedRates['access'] : 0;
		$plan = Billrun_Factory::plan(array('data' => $subr['current_plan']));

		if ($plan->isRateInSubPlan($rate, $subr, $usageType)) {
			$volumeToPrice = $volumeToPrice - $plan->usageLeftInPlan($subr['balance'], $usageType);

			if ($volumeToPrice < 0) {
				$volumeToPrice = 0;
				//@TODO  check  if that actually the action we want once all the usage is in the plan...
				$accessPrice = 0;
			} else if ($volumeToPrice > 0) {
				$ret['over_plan'] = $volumeToPrice;
			}
		} else {
			$ret['out_plan'] = $volumeToPrice;
		}

		$price = $accessPrice + $this->getPriceByRates($typedRates['rate'], $volumeToPrice);
		//Billrun_Factory::log()->log("Rate : ".print_r($typedRates,1),  Zend_Log::DEBUG);
		$ret[$this->pricingField] = $price;

		return $ret;
	}

	protected function getPriceByRates($rates_arr, $volume) {
		$price = 0;
		foreach ($rates_arr as $currRate) {
			if (0 == $volume) { // volume could be negative if it's a refund amount
				break;
			}//break if no volume left to price.
			$volumeToPriceCurrentRating = ($volume - $currRate['to'] < 0) ? $volume : $currRate['to']; // get the volume that needed to be priced for the current rating
			if (isset($currRate['ceil'])) {
				$ceil = $currRate['ceil'];
			} else {
				$ceil = false;
			}
			if ($ceil) {
				$price += floatval(ceil($volumeToPriceCurrentRating / $currRate['interval']) * $currRate['price']); // actually price the usage volume by the current 	
			} else {
				$price += floatval($volumeToPriceCurrentRating / $currRate['interval'] * $currRate['price']); // actually price the usage volume by the current 
			}
			$volume = $volume - $volumeToPriceCurrentRating; //decressed the volume that was priced
		}
		return $price;
	}
	/**
	 * Update the subsciber balance for a given usage.
	 * @param type $sub the subscriber. 
	 * @param type $counters the  counters to update
	 * @param type $charge the changre to add of  the usage.
	 */
	protected function updateSubscriberBalance($counters, $row, $billrun_key, $usage_type, $rate, $volume) {
		$subscriber_balance = Billrun_Factory::balance(array('subscriber_id' => $row['subscriber_id'], 'billrun_key' => $billrun_key));
		if (!$subscriber_balance->isValid()) {
			Billrun_Factory::log()->log("couldn't get balance for : " . print_r(array(
					'subscriber_id' => $row['subscriber_id'],
					'billrun_month' => $billrun_key
					), 1), Zend_Log::ALERT);
			return false;
		}
		$pricingData = $this->getLinePricingData($volume, $usage_type, $rate, $subscriber_balance);

		$balances = Billrun_Factory::db()->balancesCollection();
		$subRaw = $subscriber_balance->getRawData();
		$stamp = strval($row['stamp']);
		if (array_key_exists($stamp, $subRaw['tx'])) { // we're after a crash
			$pricingData = $subRaw['tx'][$stamp]; // restore the pricingData from before the crash
			return $pricingData;
		}
		$query = array('_id' => $subRaw['_id']);
		$update = array();
		$update['$set']['tx'][$stamp] = $pricingData;
		foreach ($counters as $key => $value) {
			$old_usage = $subRaw['balance']['totals'][$key]['usagev'];
			$query['balance.totals.' . $key . '.usagev'] = $old_usage;
			$update['$set']['balance.totals.' . $key . '.usagev'] = $old_usage + $value;
		}
		$update['$set']['balance.cost'] = $subRaw['balance']['cost'] + $pricingData[$this->pricingField];
		$ret = $balances->update($query, $update, array('w' => 1));
		if (!($ret['ok'] && $ret['updatedExisting'])) { // failed because of different totals (could be that another server with another line raised the totals). Need to calculate pricingData from the beginning
			$this->updateSubscriberBalance($counters, $row, $billrun_key, $usage_type, $rate, $volume);
		}
		return $pricingData;
	}

	/**
	 * gets an open billrun for subscriber with respect to the input billrun's billrun key
	 * @param type $billrun
	 * @param type $create
	 * @return Billrun_Billrun returned billrun's billrun key is not less than the input billrun key
	 */
	public function loadOpenBillrun($billrun) {
		$account_id = $billrun->getAccountId();
		$billrun_key = $billrun->getBillrunKey();
		if ($billrun->isValid()) {
			if ($billrun->isOpen()) { // found billing is open
				return $billrun;
			} else {
				return $this->loadOpenBillrun(Billrun_Factory::billrun(array('account_id' => $account_id, 'billrun_key' => Billrun_Util::getFollowingBillrunKey($billrun_key))));
			}
		} else if ($billrun_key >= $this->runtime_billrun_key) {
			Billrun_Factory::log("Adding account " . $account_id . " with billrun key " . $billrun_key . " to billrun collection", Zend_Log::INFO);
			return Billrun_Factory::billrun(array('account_id' => $account_id, 'billrun_key' => $billrun_key, 'autoload' => false))->save();
		} else { // billrun key is old
			return $this->loadOpenBillrun(Billrun_Factory::billrun(array('account_id' => $account_id, 'billrun_key' => Billrun_Util::getFollowingBillrunKey($billrun_key))));
		}
	}

	/**
	 * removes the transactions from the subscriber's balance to save space.
	 * @param type $row
	 */
	protected function removeBalanceTx($row) {
		$balances_coll = Billrun_Factory::db()->balancesCollection();
		$subscriber_id = $row['subscriber_id'];
		$billrun_key = Billrun_Util::getBillrunKey($row['unified_record_time']->sec);
		$query = array(
			'billrun_month' => $billrun_key,
			'subscriber_id' => $subscriber_id,
		);
		$values = array(
			'$set' => array(
				'tx' => array(
				)
			)
		);
		$balances_coll->update($query, $values);
	}

	/**
	 * 
	 */
	static protected function getCalculatorQueueType() {
		return self::$type;
	}

	/**
	 * @see Billrun_Calculator::isLineLegitimate
	 */
	protected function isLineLegitimate($line) {
		return isset($line['customer_rate']) && $line['customer_rate'] !== false && !isset($line['price_customer']) && $line['unified_record_time']->sec >= $this->billrun_lower_bound_timestamp; 
	}

	/**
	 * 
	 */
	protected function setCalculatorTag() {
		parent::setCalculatorTag();
		foreach ($this->data as $item) {
			$this->removeBalanceTx($item); // we can safely remove the transactions after the lines have left the current queue
		}
	}

}

