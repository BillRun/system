<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing Billrun class
 *
 * @package  Billrun
 * @since    0.5
 */
class Billrun_Billrun {

	public function __construct($options = array()) {
		if (isset($options['account_id']) && isset($options['billrun_key'])) {
			$this->load($options['account_id'], $options['billrun_key']);
		}
	}

	public function load($account_id, $billrun_key) {
		$this->data = Billrun_Factory::db()->billrunCollection()->query(array(
					'account_id' => strval($account_id),
					'billrun_key' => $billrun_key,
				))
				->cursor()->current();
		return $this;
	}

	/**
	 * Create a new billrun record for a subscriber
	 * @param type $subscriber
	 */
	public function create($account_id, $billrun_key) {
		$billrun = new Mongodloid_Entity($this->getAccountEmptyBillrunEntry($account_id));
		$billrun['billrun_key'] = $billrun_key;
		Billrun_Factory::log("Adding account " . $account_id . " with billrun key " . $billrun_key . " to billrun collection", Zend_Log::INFO);
		$billrun->collection(Billrun_Factory::db()->billrunCollection());
		$billrun->save();
		return $this->load($account_id, $billrun_key);
	}

	public function addSubscriber($subscriber_id) {
		$subscribers = $this->data['subscribers'];
		$subscribers[strval($subscriber_id)] = $this->getEmptySubscriberBillrunEntry();
		$this->data['subscribers'] = $subscribers;
		Billrun_Factory::log('Adding subscriber ' . $subscriber_id . ' to billrun collection', Zend_Log::INFO);
		$this->data->collection(Billrun_Factory::db()->billrunCollection());
		$this->data->save();
		return $this;
	}

	public function exists($subscriber_id) {
		return isset($this->data->getRawData()['subscribers'][strval($subscriber_id)]);
	}

	/**
	 * method to check if the loaded billrun is valid
	 */
	public function isValid() {
		return count($this->data->getRawData()) > 0;
	}

	public function isOpen() {
		return $this->isValid() && !($this->data->offsetExists('invoice_id'));
	}

	public function getAccountEmptyBillrunEntry($account_id) {
		return array(
			'account_id' => strval($account_id),
			'subscribers' => array(
			),
		);
	}

	public function getEmptySubscriberBillrunEntry() {
		return array(
			'costs' => array(
				'flat' => 0,
				'over_plan' => 0,
				'out_plan_vatable' => 0,
				'out_plan_vat_free' => 0,
			),
			'lines' => array(
				'call' => array(
					'refs' => array(
					),
				),
				'sms' => array(
					'refs' => array(
					),
				),
				'data' => array(
					'counters' => array(
					),
					'refs' => array(
					),
				),
			),
			'breakdown' => array(
				'flat' => array(
				),
				'over_plan' => array(
				),
				'intl' => array(
				),
				'special' => array(
				),
				'roaming' => array(
				),
			),
		);
	}

	/**
	 * 
	 * @param type $key
	 * @param type $usage_type
	 * @param type $volume
	 */
	static public function addToBreakdown(&$breakdown_raw, $breakdown_key, $zone_key, $vatable, $counters = array(), $charge = null) {
		if (!isset($breakdown_raw[$breakdown_key][$zone_key])) {
			$breakdown_raw[$breakdown_key][$zone_key] = Billrun_Plan::getEmptyPlanBalance();
		}
		if (!empty($counters)) {
			$breakdown_raw[$breakdown_key][$zone_key]['totals'][key($counters)]['usagev']+=current($counters);
			$breakdown_raw[$breakdown_key][$zone_key]['totals'][key($counters)]['cost']+=$charge;
			if ($breakdown_key != 'flat') {
				$breakdown_raw[$breakdown_key][$zone_key]['cost']+=$charge;
			}
		} else if ($breakdown_key == 'flat') {
			$breakdown_raw[$breakdown_key][$zone_key]['cost'] = $charge;
		}
		if (!isset($breakdown_raw[$breakdown_key][$zone_key]['vat'])) {
			$breakdown_raw[$breakdown_key][$zone_key]['vat'] = ($vatable ? Billrun_Factory::config()->getConfigValue('pricing.vat', '1.18') - 1 : 0); //@TODO we assume here that all the lines would be vatable or all vat-free
		}
	}

	public function getBillrunKey() {
		return $this->data->get('billrun_key');
	}

	public function update($subscriber_id, $counters, $pricingData, $row, $vatable) {
		$billrun_key = $this->getBillrunKey();
		if (!$this->exists($subscriber_id)) {
			$this->addSubscriber($subscriber_id);
		}
		$billRaw = $this->data->getRawData();
		$subscriberRaw = $billRaw['subscribers'][$subscriber_id];

		// update costs
		if (isset($pricingData['over_plan']) && $pricingData['over_plan']) {
			$subscriberRaw['costs']['over_plan'] += $pricingData['price_customer'];
		} else if (isset($pricingData['out_plan']) && $pricingData['out_plan']) {
			if ($vatable) {
				$subscriberRaw['costs']['out_plan_vatable'] += $pricingData['price_customer'];
			} else {
				$subscriberRaw['costs']['out_plan_vat_free'] += $pricingData['price_customer'];
			}
		} else if ($row['type'] == 'flat') {
			$subscriberRaw['costs']['flat'] = $pricingData['price_customer'];
		}

		if (!($row['type'] == 'flat')) {
			$rate = $row['customer_rate'];
			switch ($row['usaget']) {
				case 'call':
				case 'incoming_call':
					$usage_type = 'call';
					break;
				case 'sms':
					$usage_type = 'sms';
					break;
				case 'data':
					$usage_type = 'data';
					break;
				default:
					$usage_type = 'call';
					break;
			}

			// update lines refs
			$subscriberRaw['lines'][$usage_type]['refs'][] = $row->createRef();

			// update data counters
			if ($usage_type == 'data') {
				$date_key = date("Ymd", $row['unified_record_time']->sec);
				if (isset($subscriberRaw['lines']['data']['counters'][$date_key])) {
					$subscriberRaw['lines']['data']['counters'][$date_key]+=$row['usagev'];
				} else {
					$subscriberRaw['lines']['data']['counters'][$date_key] = $row['usagev'];
				}
			}
		}

		// update breakdown
		if (!isset($pricingData['over_plan']) && !isset($pricingData['out_plan'])) { // in plan
			$breakdown_key = 'flat';
			$zone_key = $billrun_key;
		} else if (isset($pricingData['over_plan']) && $pricingData['over_plan']) {
			$breakdown_key = 'over_plan';
		} else {
			$category = $rate['rates'][$row['usaget']]['category'];
			switch ($category) {
				case "roaming":
					$breakdown_key = "roaming";
					$zone_key = $row['serving_network'];
					break;
				case "special":
					$breakdown_key = "special";
					break;
				default:
					$breakdown_key = "intl";
					break;
			}
		}
		if (!isset($zone_key)) {
			$zone_key = $row['customer_rate']['key'];
		}
		self::addToBreakdown($subscriberRaw['breakdown'], $breakdown_key, $zone_key, $vatable, $counters, $pricingData['price_customer']);

		$billRaw['subscribers'][$subscriber_id] = $subscriberRaw;
		$this->data->setRawData($billRaw);
		$this->data->save(Billrun_Factory::db()->billrunCollection());
		$this->setStamp($row);
	}

	/**
	 * update the billing line with stamp to avoid another pricing
	 *
	 * @param Mongodloid_Entity $line the billing line to update
	 *
	 * @return boolean true on success else false
	 */
	protected function setStamp($line) {
		$current = $line->getRawData();
		$added_values = array(
			'billrun' => $this->getBillrunKey(),
		);

		$newData = array_merge($current, $added_values);
		$line->setRawData($newData);
		$line->save(Billrun_Factory::db()->linesCollection());
		return true;
	}
	
	static public function getBillrun($account_id, $billrun_key) {
		$billrun = Billrun_Factory::billrun(array(
			'account_id' => $account_id,
			'billrun_key' => $billrun_key,
		));
		return $billrun;
	}

	public function close() {
		$account_id = $this->data->getRawData()['account_id'];
		$billrun_key = $this->getBillrunKey();
		$closeBillrunCmd = "closeBillrun('$account_id', '$billrun_key');";
		$ret = Billrun_Factory::db()->execute($closeBillrunCmd);
		if ($ret['ok']) {
			Billrun_Factory::log()->log("Created invoice " . $ret['retval'] . " for account " . $account_id, Zend_Log::INFO);
		} else {
			Billrun_Factory::log()->log("Failed to create invoice for account " . $account_id, Zend_Log::INFO);
		}
//		$ret = Billrun_Factory::db()->execute("db.getLastErrorObj();");
	}

}
