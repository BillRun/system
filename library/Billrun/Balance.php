<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract subscriber class
 *
 * @package  Billing
 * @since    0.5
 */
abstract class Billrun_Balance extends Mongodloid_Entity {

	/**
	 * Type of object
	 *
	 * @var string
	 */
	static protected $type = 'balance';
	static protected $instance = array();

	/**
	 * the row that load the balance
	 * @var array
	 */
	protected $row;

	/**
	 * constant of calculator db field
	 */
	const DEF_CALC_DB_FIELD = 'aprice';

	/**
	 * the pricing field
	 * @var string
	 * @todo take from customer pricing
	 */
	public $pricingField = self::DEF_CALC_DB_FIELD;

	/**
	 * constructor of balance entity
	 * 
	 * @param array $values options to load the balance
	 * 
	 * @return void
	 * 
	 */
	public function __construct($values = null, $collection = null) {
		// Balance require to be used only with primary preferred (specially on real-time)
		$this->collection(Billrun_Factory::db()->balancesCollection()->setReadPreference('RP_PRIMARY'));

		$this->row = $values;

		if (!isset($this->row['sid']) || !isset($this->row['aid'])) {
			Billrun_Factory::log('Error creating balance, no aid or sid', Zend_Log::ALERT);
			return;
		}

		$this->init($values); // this for override behaviour by the inheritance classes

		$balance_values = $this->load();

		$this->setRawData($balance_values);
	}

	/**
	 * abstract class to extend constructor before load the balance from DB after set the row
	 */
	abstract protected function init();

	/**
	 * method to get the instance of the class (singleton)
	 * 
	 * @param type $params
	 * 
	 * @return Billrun_Balance
	 */
	static public function getInstance($params = null) {
		$stamp = Billrun_Util::generateArrayStamp($params);
		if (empty(self::$instance[$stamp])) {
			if (empty($params)) {
				$params = Yaf_Application::app()->getConfig();
			}
			if (isset($params['charging_type'])) {
				$class = 'Billrun_Balance_' . $params['charging_type'];
			} else { // fallback to default postpaid balance
				$class = 'Billrun_Balance_Postpaid';
			}
			self::$instance[$stamp] = new $class($params);
		} else {
			if (isset($params['balance_db_refresh']) && $params['balance_db_refresh']) {
				self::$instance[$stamp]->load();
			}
		}

		return self::$instance[$stamp];
	}

	/**
	 * get balance collection
	 * 
	 * @return collection object
	 * 
	 * @deprecated since version 5.3
	 */
	public static function getCollection() {
		Billrun_Factory::log("Use deprecated method: " . __FUNCTION__, Zend_Log::DEBUG);
		// Balance require to be used only with primary preferred (specially on real-time)
		return Billrun_Factory::db()->balancesCollection()->setReadPreference('RP_PRIMARY');
	}

	/**
	 * Loads the balance for subscriber
	 * @return array subscriber's balance
	 */
	protected function load() {
		Billrun_Factory::log("Trying to load balance for subscriber " . $this->row['sid'] . ". urt: " . $this->row['urt']->sec . ". charging_type: " . $this->charging_type, Zend_Log::DEBUG);
		$query = $this->getBalanceLoadQuery();
		return $this->collection()
				->query($query)
				->cursor()
				->sort($this->loadQuerySort())
				->setReadPreference('RP_PRIMARY')
				->limit(1)
				->current();
	}

	protected function loadQuerySort() {
		return array();
	}

	/**
	 * Gets a query to get the correct balance of the subscriber.
	 * 
	 * @param type $subscriberId
	 * @param type $timeNow - The time now.
	 * @param type $chargingType
	 * @param type $usageType
	 * @return array
	 */
	protected function getBalanceLoadQuery(array $query = array()) {
		$query['sid'] = $this->row['sid'];
		$query['sid'] = array('$lte' => $this->row['urt']);
		$query['to'] = array('$gte' => $this->row['urt']);

		Billrun_Factory::dispatcher()->trigger('getBalanceLoadQuery', array(&$query, $this->row, $this));

		return $query;
	}

	/**
	 * method to check if the loaded balance is valid
	 */
	public function isValid() {
		return count($this->getRawData()) > 0;
	}

//	/**
//	 * method to update subscriber balance to db
//	 * 
//	 * @param Mongodloid_Entity $row the input line
//	 * @param mixed $rate The rate of associated with the usage.
//	 * @param Billrun_Plan $plan the customer plan
//	 * @param string $usage_type The type  of the usage (call/sms/data)
//	 * @param int $volume The usage volume (seconds of call, count of SMS, bytes  of data)
//	 * 
//	 * @return mixed on success update return pricing data array, else false
//	 * 
//	 */
//	public function updateBalanceByRow($row, $rate, $plan, $usage_type, $volume) {
//		if (($crashedPricingData = $this->getTx($row['stamp'])) !== FALSE) {
//			return $crashedPricingData;
//		}
//		$pricingData = $this->getLinePricingData($volume, $usage_type, $rate, $plan, $row);
//		if (isset($row['billrun_pretend']) && $row['billrun_pretend']) {
//			Billrun_Factory::dispatcher()->trigger('afterUpdateSubscriberBalance', array(array_merge($row->getRawData(), $pricingData), $this, &$pricingData, $this));
//			return $pricingData;
//		}
//
//		$balance_id = $this->getId();
//		Billrun_Factory::log("Updating balance " . $balance_id . " of subscriber " . $row['sid'], Zend_Log::DEBUG);
//		list($query, $update) = $this->buildBalanceUpdateQuery($pricingData, $row, $volume);
//
//		Billrun_Factory::dispatcher()->trigger('beforeCommitSubscriberBalance', array(&$row, &$pricingData, &$query, &$update, $rate, $this));
//		$ret = $this->collection()->update($query, $update);
//		if (!($ret['ok'] && $ret['updatedExisting'])) {
//			Billrun_Factory::log('Update subscriber balance failed on updated existing document. Update status: ' . print_r($ret, true), Zend_Log::INFO);
//			return false;
//		}
//		Billrun_Factory::log("Line with stamp " . $row['stamp'] . " was written to balance " . $balance_id . " for subscriber " . $row['sid'], Zend_Log::DEBUG);
//		$row['tx_saved'] = true; // indication for transaction existence in balances. Won't & shouldn't be saved to the db.
//		return $pricingData;
//	}

	/**
	 * trigger update query on the balance collection
	 * 
	 * @param array $query the query of the update command
	 * @param array $update the update command
	 * 
	 * @return array update command results
	 */
	public function update($query, $update) {
		return $this->collection()->update($query, $update, array('w' => 1));
	}

	/**
	 * method to build update query of the balance
	 * 
	 * @param array $pricingData pricing data array
	 * @param Mongodloid_Entity $row the input line
	 * @param int $volume The usage volume (seconds of call, count of SMS, bytes  of data)
	 * 
	 * @return array update query array (mongo style)
	 * 
	 * @todo move to balance object
	 */
	public function buildBalanceUpdateQuery(&$pricingData, $row, $volume) {
		$update = array();
//		$update['$set']['tx.' . $row['stamp']] = $pricingData;
		$balance_totals_key = $this->getBalanceTotalsKey($pricingData);
		$balance_key = 'balance.totals.' . $balance_totals_key . '.usagev';
		$query = array(
			'_id' => $this->getId()->getMongoID(),
			'$or' => array(
				array($balance_key => $this->getCurrentUsage($balance_totals_key)),
				array($balance_key => array('$exists' => 0))
			)
		);

		return array($query, $update);
	}

	/**
	 * get current usage which will the old (after update)
	 * @return int
	 */
	protected function getCurrentUsage($balance_totals_key) {
		if (!isset($this->get('balance')['totals'][$balance_totals_key]['usagev'])) {
			return 0;
		}
		return $this->get('balance')['totals'][$balance_totals_key]['usagev'];
	}

	/**
	 * method to get balance totals key
	 * 
	 * @param array $row
	 * @param array $pricingData rate handle
	 * 
	 * @return string
	 */
	abstract protected function getBalanceTotalsKey($pricingData);

	public function getBalanceChargingTotalsKey($usaget) {
		return $this->chargingTotalsKey = $usaget;
	}

	/**
	 * method to get free row pricing data
	 * 
	 * @return array
	 */
	public function getFreeRowPricingData() {
		return array(
			'in_plan' => 0,
			'over_plan' => 0,
			'out_plan' => 0,
			'in_group' => 0,
			'over_group' => 0,
			$this->pricingField => 0,
		);
	}

}
