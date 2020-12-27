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

		$this->reload();
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
	public static function getInstance($params = null) {
		if (isset($params['connection_type'])) {
			$class = 'Billrun_Balance_' . ucfirst($params['connection_type']);
		} else { // fallback to default postpaid balance
			$class = 'Billrun_Balance_Postpaid';
		}
		
		return call_user_func($class .'::getInstance', $params); 
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
		Billrun_Factory::log("Trying to load balance for subscriber " . $this->row['sid'] . ". urt: " . $this->row['urt']->sec . ". connection_type: " . $this->connection_type, Zend_Log::DEBUG);
		$query = $this->getBalanceLoadQuery();
		if ($query === false) {
			return array();
	}
		$retEntity = $this->collection()
				->query($query)
				->cursor()
				->sort($this->loadQuerySort())
				->setReadPreference('RP_PRIMARY')
				->limit(1)
				->current();

		if (!$retEntity instanceof Mongodloid_Entity || $retEntity->isEmpty()) {
			return array();
		}

		return $retEntity->getRawData();
	}

	public function reload() {
		$balance_values = $this->load();
		$this->setRawData($balance_values);
	}

	protected function loadQuerySort() {
		return array();
	}
	
	abstract protected function getBalanceLoadQuery(array $query = array());
	
	/**
	 * on prepaid there is no default balance, return no balance (empty array)
	 * @param array $options settings
	 * @return array
	 */
	protected function getDefaultBalance() {
		return array();
	}


	/**
	 * method to check if the loaded balance is valid
	 */
	public function isValid() {
		return count($this->getRawData()) > 0;
	}

	/**
	 * trigger update query on the balance collection
	 * 
	 * @param array $query the query of the update command
	 * @param array $update the update command
	 * 
	 * @return array update command results
	 */
	public function update($query, $update) {
		$skipEvents = false;
		$options = array(
			'new' => TRUE,
		);
		
		// this is for balances sharding, although this is _id query
		if (!isset($query['sid'])) {
			$query['sid'] = $this->row['sid'];
		}
		if (!isset($query['aid'])) {
			$query['aid'] = $this->row['aid'];
		}
		
		$ret = $this->collection()->findAndModify($query, $update, null, $options);
		if ($ret->isEmpty()) {
			return FALSE;
		}
		$after = $ret->getRawData();
		$additionalEntities = array(
			'subscriber' => isset($this->row['subscriber']) ? $this->row['subscriber'] : null,
		);
		Billrun_Factory::dispatcher()->trigger('beforeTriggerEvents', array(&$skipEvents, $this->row));
		if (!$skipEvents) {
			Billrun_Factory::eventsManager()->trigger(Billrun_EventsManager::EVENT_TYPE_BALANCE, $this->getRawData(), $after, $additionalEntities, array('aid' => $after['aid'], 'sid' => $after['sid'], 'row' => array('usagev' => $this->row['usagev'], 'urt' => $this->row['urt']->sec)));
		}
		Billrun_Factory::dispatcher()->trigger('afterBalanceUpdate', array($this->row, $after));
		$this->setRawData($after);
		return $ret;
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
		$update['$set']['tx.' . $row['stamp']] = $pricingData;
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
	abstract public function getBalanceTotalsKey($pricingData);

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
