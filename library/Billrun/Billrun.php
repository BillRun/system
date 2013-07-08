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

	/**
	 * 
	 * @param type $account_id
	 * @param type $billrun_key
	 * @return \Billrun_Billrun
	 */
	public function load($account_id, $billrun_key) {
		$billrun_coll = Billrun_Factory::db()->billrunCollection();
		$this->data = $billrun_coll->query(array(
					'account_id' => $account_id,
					'billrun_key' => $billrun_key,
				))
				->cursor()->current();
		$this->data->collection($billrun_coll);
		return $this;
	}

	/**
	 * Create a new billrun record for a subscriber
	 * @param type $subscriber
	 */
	public function create($account_id, $billrun_key) {
		$billrun = new Mongodloid_Entity($this->getAccountEmptyBillrunEntry($account_id, $billrun_key));
		Billrun_Factory::log("Adding account " . $account_id . " with billrun key " . $billrun_key . " to billrun collection", Zend_Log::INFO);
		$billrun->collection(Billrun_Factory::db()->billrunCollection());
		$billrun->save();
		return $this->load($account_id, $billrun_key);
	}

	/**
	 * Add a subscriber to the current billrun entry.
	 * @param type $subscriber_id the  subscriber id  to add.
	 * @return \Billrun_Billrun the current instance  of the billrun entry.
	 */
	public function addSubscriber($subscriber_id) {
		$subscribers = $this->data['subs'];
		$subscribers[] = $this->getEmptySubscriberBillrunEntry($subscriber_id);
		$this->data['subs'] = $subscribers;
		Billrun_Factory::log('Adding subscriber ' . $subscriber_id . ' to billrun collection', Zend_Log::INFO);
		$this->data->collection(Billrun_Factory::db()->billrunCollection());
		$this->data->save();
		return $this;
	}

	/**
	 * check if a given subscriber exists in the current billrun.
	 * @param type $subscriber_id the  subscriber id to check.
	 * @return boolean TRUE  is  the subscriber  exists in the current billrun entry FALSE otherwise.
	 */
	public function exists($subscriber_id) {
		return $this->getSubRawData($subscriber_id) != false;
	}

	/**
	 * method to check if the loaded billrun is valid
	 */
	public function isValid() {
		return count($this->data->getRawData()) > 0;
	}

	/**
	 * Check if the current billrun entry  is  open and can be updated.
	 * @return boolean TRUE if can be up date FALSE otherwise.
	 */
	public function isOpen() {
		return $this->isValid() && !($this->data->offsetExists('invoice_id'));
	}

	/**
	 * Get an empty billrun account  entry structure.
	 * @param type $account_id the account id that the enery belongs to.
	 * @return Array tan empty billrun account  structure.
	 */
	public function getAccountEmptyBillrunEntry($account_id, $billrun_key) {
		$vat = Billrun_Util::getVAT(Billrun_Util::getEndTime($billrun_key));
		return array(
			'account_id' => $account_id,
			'subs' => array(
			),
			'vat' => $vat,
			'billrun_key' => $billrun_key,
		);
	}

	/**
	 * Get an empty billrun subscriber entry
	 * @return Array an empty billrun subscriber entry
	 */
	public function getEmptySubscriberBillrunEntry($subscriber_id) {
		return array(
			'sub_id' => $subscriber_id,
			'costs' => array(
				'flat' => $this->getVATTypes(),
				'over_plan' => $this->getVATTypes(),
				'out_plan' => $this->getVATTypes(),
				'manual' => array(
					'charge' => $this->getVATTypes(),
					'refund' => $this->getVATTypes()
				),
			),
			'lines' => array(
				'call' => array(
					'refs' => array(),
				),
				'sms' => array(
					'refs' => array(),
				),
				'data' => array(
					'counters' => array(),
					'refs' => array(),
				),
				'flat' => array(
					'refs' => array(),
				),
				'mms' => array(
					'refs' => array(),
				),
				'manual' => array(
					'refs' => array(),
				),
			),
			'breakdown' => array(
				'in_plan' => $this->getCategories(),
				'over_plan' => $this->getCategories(),
				'out_plan' => $this->getCategories(),
				'manual' => array(
					'charge' => array(),
					'refund' => array(),
				),
			),
		);
	}

	protected function getVATTypes() {
		return array(
			'vatable' => 0,
			'vat_free' => 0,
		);
	}

	protected function getCategories() {
		return array(
			'base' => array(),
			'intl' => array(),
			'special' => array(),
			'roaming' => array(),
		);
	}

	/**
	 * Add pricing and usage counters to the billrun breakdown.
	 * @param type $key
	 * @param type $usage_type
	 * @param type $volume
	 */
	static public function addToBreakdown(&$subscriberRaw, $plan_key, $category_key, $zone_key, $vatable, $counters = array(), $pricingData = array()) {
		$breakdown_raw = $subscriberRaw['breakdown'];
		if ($plan_key != 'manual') {
			if (!isset($breakdown_raw[$plan_key][$category_key][$zone_key])) {
				$breakdown_raw[$plan_key][$category_key][$zone_key] = Billrun_Balance::getEmptyBalance();
			}
			if (!empty($counters)) {
				if (!empty($pricingData) && isset($pricingData['over_plan']) && $pricingData['over_plan'] < current($counters)) {
					$volume_priced = $pricingData['over_plan'];
					if (!isset($breakdown_raw['in_plan'][$category_key][$zone_key])) {
						$breakdown_raw['in_plan'][$category_key][$zone_key] = Billrun_Balance::getEmptyBalance();
					}
					$breakdown_raw['in_plan'][$category_key][$zone_key]['totals'][key($counters)]['usagev']+=current($counters) - $volume_priced; // add partial usage to flat
				} else {
					$volume_priced = current($counters);
				}
				$breakdown_raw[$plan_key][$category_key][$zone_key]['totals'][key($counters)]['usagev']+=$volume_priced;
				$breakdown_raw[$plan_key][$category_key][$zone_key]['totals'][key($counters)]['cost']+=$pricingData['price_customer'];
				if ($plan_key != 'in_plan') {
					$breakdown_raw[$plan_key][$category_key][$zone_key]['cost']+=$pricingData['price_customer'];
				}
			} else if ($zone_key == 'service') {
				$breakdown_raw[$plan_key][$category_key][$zone_key]['cost'] += $pricingData['price_customer'];
			}
			if (!isset($breakdown_raw[$plan_key][$category_key][$zone_key]['vat'])) {
				$breakdown_raw[$plan_key][$category_key][$zone_key]['vat'] = ($vatable ? floatval(Billrun_Factory::config()->getConfigValue('pricing.vat', 0.18)) : 0); //@TODO we assume here that all the lines would be vatable or all vat-free
			}
		} else {
			if (isset($breakdown_raw[$plan_key][$category_key][$zone_key])) {
				$breakdown_raw[$plan_key][$category_key][$zone_key]+=$pricingData['price_customer'];
			}
			else {
				$breakdown_raw[$plan_key][$category_key][$zone_key]=$pricingData['price_customer'];
			}
		}
		$subscriberRaw['breakdown'] = $breakdown_raw;
	}

	/**
	 * Get the key of the current billrun
	 * @return string the billrun key. 
	 */
	public function getBillrunKey() {
		return $this->data->get('billrun_key');
	}

	/**
	 * Update  
	 * @param type $subscriber_id
	 * @param type $counters
	 * @param type $pricingData
	 * @param type $row
	 * @param type $vatable
	 */
	public function update($subscriber_id, $counters, $pricingData, $row, $vatable) {
		$billrun_key = $this->getBillrunKey();
		if (!$this->exists($subscriber_id)) {
			$this->addSubscriber($subscriber_id);
		}

		$subscriberRaw = $this->getSubRawData($subscriber_id);

		// update costs
		$vat_key = ($vatable ? "vatable" : "vat_free");
		if (isset($pricingData['over_plan']) && $pricingData['over_plan']) {
			$subscriberRaw['costs']['over_plan'][$vat_key] += $pricingData['price_customer'];
		} else if (isset($pricingData['out_plan']) && $pricingData['out_plan']) {
			$subscriberRaw['costs']['out_plan'][$vat_key] += $pricingData['price_customer'];
		} else if ($row['type'] == 'flat') {
			$subscriberRaw['costs']['flat'][$vat_key] += $pricingData['price_customer'];
		} else if ($row['type'] == 'credit') {
			$subscriberRaw['costs']['manual'][$row['charge_type']][$vat_key] += $pricingData['price_customer'];
		}

		if ($row['type'] == 'credit') {
			$usage_type = 'manual';
		} else {
			switch ($row['usaget']) {
				case 'call':
				case 'incoming_call':
					$usage_type = 'call';
					break;
				case 'sms':
				case 'incoming_sms':
					$usage_type = 'sms';
					break;
				case 'data':
					$usage_type = 'data';
					break;
				case 'mms':
					$usage_type = 'mms';
					break;
				case 'flat':
					$usage_type = 'flat';
					break;
				default:
					$usage_type = 'call';
					break;
			}
		}

		if ($row['type'] != 'flat') {
			$rate = $row['customer_rate'];
		}

		// update data counters
		if ($usage_type == 'data') {
			$date_key = date("Ymd", $row['unified_record_time']->sec);
			if (isset($subscriberRaw['lines']['data']['counters'][$date_key])) {
				$subscriberRaw['lines']['data']['counters'][$date_key]+=$row['usagev'];
			} else {
				$subscriberRaw['lines']['data']['counters'][$date_key] = $row['usagev'];
			}
		}

		// update lines refs
		$subscriberRaw['lines'][$usage_type]['refs'][] = $row->createRef();

		// update breakdown
		if ($row['type'] == 'credit') {
			$plan_key = 'manual';
			$zone_key = $row['reason'];
		}
		if (!isset($pricingData['over_plan']) && !isset($pricingData['out_plan'])) { // in plan
			$plan_key = 'in_plan';
			if ($row['type'] == 'flat') {
				$zone_key = 'service';
			}
		} else if (isset($pricingData['over_plan']) && $pricingData['over_plan']) { // over plan
			$plan_key = 'over_plan';
		} else { // out plan
			$plan_key = "out_plan";
		}

		if (isset($rate['rates'][$row['usaget']]['category'])) {
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
				case "default":
					$category_key = "base";
					break;
			}
		} else if ($row['type']=='credit') {
			$category_key = $row['reason'];
		} else {
			$category_key = "base";
		}

		if (!isset($zone_key)) {
			$zone_key = $row['customer_rate']['key'];
		}
		self::addToBreakdown($subscriberRaw, $plan_key, $category_key, $zone_key, $vatable, $counters, $pricingData);

		$this->setSubRawData($subscriber_id, $subscriberRaw);
		$this->setStamp($row);
	}

	/**
	 * Update the billing line with stamp to avoid another pricing
	 *
	 * @param Mongodloid_Entity $line the billing line to update
	 *
	 * @return boolean true on success else false
	 */
	protected function setStamp($line) {
		$current = $line->getRawData();
		$added_values = array(
			'billrun' => $this->getBillrunKey(),
			'billrun_ref' => $this->data->createRef(),
		);

		$newData = array_merge($current, $added_values);
		$line->setRawData($newData);
		$line->save(Billrun_Factory::db()->linesCollection());
		return true;
	}

	/** TODO remove ...
	  static public function getBillrun($account_id, $billrun_key) {
	  $billrun = Billrun_Factory::billrun(array(
	  'account_id' => $account_id,
	  'billrun_key' => $billrun_key,
	  ));
	  return $billrun;
	  } */

	/**
	 * Close the current billrun and create an invoice ID. 
	 */
	public function close() {
		$account_id = $this->data->getRawData()['account_id'];
		$billrun_key = $this->getBillrunKey();
		$closeBillrunCmd = "closeBillrun($account_id, '$billrun_key');";
		$ret = Billrun_Factory::db()->execute($closeBillrunCmd);
		if ($ret['ok']) {
			Billrun_Factory::log()->log("Created invoice " . $ret['retval'] . " for account " . $account_id, Zend_Log::INFO);
		} else {
			Billrun_Factory::log()->log("Failed to create invoice for account " . $account_id, Zend_Log::INFO);
		}
//		$ret = Billrun_Factory::db()->execute("db.getLastErrorObj();");
	}

	/**
	 * 
	 * @param type $subscriber_id
	 * @return mixed
	 */
	protected function getSubRawData($subscriber_id) {
		foreach ($this->data->get('subs') as $sub_entry) {
			if ($sub_entry['sub_id'] == $subscriber_id) {
				return $sub_entry;
			}
		}
		return false;
	}

	/**
	 * 
	 * @param int $subscriber_id
	 * @param array $subscriber_raw
	 * @return boolean
	 */
	protected function setSubRawData($subscriber_id, $subscriber_raw) {
		foreach ($this->data->get('subs') as $key => $sub_entry) {
			if ($sub_entry['sub_id'] == $subscriber_id) {
				$this->data->set('subs.' . $key, $subscriber_raw);
				$this->data->save(Billrun_Factory::db()->billrunCollection());
				return true;
			}
		}
		return false;
	}

}
