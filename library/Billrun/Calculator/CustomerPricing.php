<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator for  pricing  billing lines with customer price.
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Calculator_CustomerPricing extends Billrun_Calculator {

	/**
	 * constant of calculator db field
	 */
	const DEF_CALC_DB_FIELD = 'aprice';

	/**
	 *
	 * @var type 
	 */
	public $pricingField = self::DEF_CALC_DB_FIELD;

	/**
	 * the name tag of the class
	 * 
	 * @var string
	 */
	static protected $type = "pricing";

	/**
	 * the precision of price comparison
	 * @var double 
	 * @todo move to separated class 
	 */
	static protected $precision = 0.000001;

	/**
	 *
	 * @var boolean is customer price vatable by default
	 */
	protected $vatable = true;

	/**
	 * Save unlimited usages to balances
	 * @var boolean
	 */
	protected $unlimited_to_balances = true;

	/**
	 * plans list
	 * @var array
	 * @deprecated since version 4.0
	 */
	protected $plans = array();

	/**
	 * balances collection
	 * @var Mongodloid_Collection 
	 */
	protected $balances = null;

	/**
	 * timestamp of minimum row time that can be calculated
	 * @var int timestamp
	 */
	protected $billrun_lower_bound_timestamp;

	/**
	 * Minimum possible billrun key for newly calculated lines
	 * @var string 
	 */
	protected $active_billrun;

	/**
	 * End time of the active billrun (unix timestamp)
	 * @var int
	 */
	protected $active_billrun_end_time;

	/**
	 * Second minimum possible billrun key for newly calculated lines
	 * @var string
	 */
	protected $next_active_billrun;

	/**
	 * Array of subscriber ids queued for rebalance in rebalance_queue collection
	 * @var array
	 */
	protected $sidsQueuedForRebalance;

	/**
	 * balance that customer pricing update
	 * 
	 * @param Billrun_Balance
	 */
	protected $balance;

	/**
	 * prepaid minimum balance volume
	 * 
	 * @var float
	 */
	protected $min_balance_volume = null;

	/**
	 * prepaid minimum balance cost
	 * 
	 * @var float
	 */
	protected $min_balance_cost = null;

	/**
	 * call offset
	 * 
	 * @param int
	 */
	protected $call_offset = 0;

	public function __construct($options = array()) {
		if (isset($options['autoload'])) {
			$autoload = $options['autoload'];
		} else {
			$autoload = true;
		}

		if (isset($options['realtime'])) {
			$realtime = $options['realtime'];
		} else {
			$realtime = false;
		}

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
		if (isset($options['calculator']['unlimited_to_balances'])) {
			$this->unlimited_to_balances = (boolean) ($options['calculator']['unlimited_to_balances']);
		}
		$this->billrun_lower_bound_timestamp = is_null($this->months_limit) ? 0 : strtotime($this->months_limit . " months ago");
		// set months limit
		if ($autoload) {
			$this->load();
		}
		//TODO: check how to remove call to loadRates
		$this->balances = Billrun_Factory::db()->balancesCollection()->setReadPreference('RP_PRIMARY');

		$this->active_billrun = Billrun_Billrun::getActiveBillrun();
		$this->active_billrun_end_time = Billrun_Billingcycle::getEndTime($this->active_billrun);
		$this->next_active_billrun = Billrun_Billingcycle::getFollowingBillrunKey($this->active_billrun);

		$this->sidsQueuedForRebalance = array_flip(Billrun_Factory::db()->rebalance_queueCollection()->distinct('sid'));
	}

	protected function getLines() {
		$query = array();
		return $this->getQueuedLines($query);
	}

	public function setCallOffset($val) {
		$this->call_offset = $val;
	}

	public function getCallOffset() {
		return $this->call_offset;
	}

	public function prepareData($lines) {
		
	}

	/**
	 * execute the calculation process
	 * @TODO this function might  be a duplicate of  @see Billrun_Calculator::calc() do we really  need the difference between Rate/Pricing? (they differ in the plugins triggered)
	 */
	public function calc() {
		Billrun_Factory::dispatcher()->trigger('beforePricingData', array('data' => $this->data));
		$lines_coll = Billrun_Factory::db()->linesCollection();

		$lines = $this->pullLines($this->lines);
		foreach ($lines as $key => $line) {
			if ($line) {
				Billrun_Factory::dispatcher()->trigger('beforePricingDataRow', array('data' => &$line));
				//Billrun_Factory::log("Calculating row: ".print_r($item,1),  Zend_Log::DEBUG);
				$line->collection($lines_coll);
				if ($this->isLineLegitimate($line)) {
					if ($this->updateRow($line) === FALSE) {
						unset($this->lines[$line['stamp']]);
						continue;
					}
					$this->data[$line['stamp']] = $line;
				}
				//$this->updateLinePrice($item); //@TODO  this here to prevent divergance  between the priced lines and the subscriber's balance/billrun if the process fails in the middle.
				Billrun_Factory::dispatcher()->trigger('afterPricingDataRow', array('data' => &$line));
			}
		}
		Billrun_Factory::dispatcher()->trigger('afterPricingData', array('data' => $this->data));
	}

	public function updateRow($row) {
		if (isset($this->sidsQueuedForRebalance[$row['sid']])) {
			return false;
		}

		try {
			Billrun_Factory::dispatcher()->trigger('beforeCalculatorUpdateRow', array(&$row, $this));
			$updateRow = Billrun_Calculator_Updaterow::getInstance('Customerpricing', $row, $this, $row['charging_type']);
			$pricingData = $updateRow->update();
			if (is_bool($pricingData)) {
				return $pricingData;
			}
			$row->setRawData(array_merge($row->getRawData(), $pricingData));
			Billrun_Factory::dispatcher()->trigger('afterCalculatorUpdateRow', array(&$row, $this));
		} catch (Exception $e) {
			Billrun_Factory::log('Line with stamp ' . $row['stamp'] . ' crashed when trying to price it. got exception :' . $e->getCode() . ' : ' . $e->getMessage() . "\n trace :" . $e->getTraceAsString(), Zend_Log::ERR);
			return false;
		}
	}

	/**
	 * Gets the subscriber balance. If it does not exist, creates it.
	 * 
	 * @param type $row
	 * 
	 * @return Billrun_Balance
	 */
	public function loadSubscriberBalance($row, $granted_volume = null, $granted_cost = null) {
		// we moved the init of plan_ref to customer calc, we leave it here only for verification and avoid b/c issues
		if (!isset($row['plan_ref'])) {
			$plan = Billrun_Factory::plan(array('name' => $row['plan'], 'time' => $row['urt']->sec, /* 'disableCache' => true */));
			$plan_ref = $plan->createRef();
			if (is_null($plan_ref)) {
				Billrun_Factory::log('No plan found for subscriber ' . $row['sid'], Zend_Log::ALERT);
				$row['usagev'] = 0;
				$row['apr'] = 0;
				return false;
			}
			$row['plan_ref'] = $plan_ref;
		}
		$instanceOptions = array_merge($row->getRawData(), array('granted_usagev' => $granted_volume, 'granted_cost' => $granted_cost));
		$instanceOptions['balance_db_refresh'] = true;
		$loadedBalance = Billrun_Balance::getInstance($instanceOptions);
		if (!$loadedBalance || !$loadedBalance->isValid()) {
			Billrun_Factory::log("couldn't get balance for subscriber: " . $row['sid'], Zend_Log::INFO);
			$row['usagev'] = 0;
			$row['apr'] = 0;
			return false;
		} else {
			Billrun_Factory::log("Found balance for subscriber " . $row['sid'], Zend_Log::DEBUG);
		}
		$this->balance = $loadedBalance;
		return true;
	}

	/**
	 * Determines if a rate should not produce billable lines, but only counts the usage
	 * @param Mongodloid_Entity|array $rate the input rate
	 * @return boolean
	 */
	public function isBillable($rate) {
		return !isset($rate['billable']) || $rate['billable'] === TRUE;
	}

	/**
	 * Override parent calculator to save changes with update (not save)
	 */
	public function writeLine($line, $dataKey) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteLine', array('data' => $line, 'calculator' => $this));
		$save = array();
		$saveProperties = $this->getPossiblyUpdatedFields();
		foreach ($saveProperties as $p) {
			if (!is_null($val = $line->get($p, true))) {
				$save['$set'][$p] = $val;
			}
		}
		$where = array('stamp' => $line['stamp']);
		if ($save) {
			Billrun_Factory::db()->linesCollection()->update($where, $save);
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteLine', array('data' => $line, 'calculator' => $this));
		if (!isset($line['usagev']) || $line['usagev'] === 0) {
			$this->removeLineFromQueue($line);
			unset($this->data[$dataKey]);
		}
	}

	public function getPossiblyUpdatedFields() {
		return array($this->pricingField, 'billrun', 'over_plan', 'in_plan', 'out_plan', 'plan_ref', 'usagesb', 'arategroups', 'over_arate', 'over_group', 'in_group', 'in_arate');
	}

	public function getPricingField() {
		return $this->pricingField;
	}

	/**
	 * removes the transactions from the subscriber's balance to save space.
	 * @param type $row
	 */
	public function removeBalanceTx($row) {
		$query = array(
			'sid' => $row['sid'],
			'from' => array(
				'$lte' => $row['urt'],
			),
			'to' => array(
				'$gt' => $row['urt'],
			),
		);
		$values = array(
			'$unset' => array(
				'tx.' . $row['stamp'] => 1
			)
		);
		$this->balances->update($query, $values);
	}

	/**
	 * @see Billrun_Calculator::getCalculatorQueueType
	 * 
	 * @todo Move to trait because it also use by processor
	 */
	public function getCalculatorQueueType() {
		return self::$type;
	}

	/**
	 * @see Billrun_Calculator::isLineLegitimate
	 */
	public function isLineLegitimate($line) {
		$arate = Billrun_Rates_Util::getRateByRef($line->get('arate', TRUE));
		return !is_null($arate) && (empty($arate['skip_calc']) || !in_array(self::$type, $arate['skip_calc'])) &&
			isset($line['sid']) && $line['sid'] !== false &&
			$line['urt']->sec >= $this->billrun_lower_bound_timestamp;
	}

	/**
	 * set queue calculator tag
	 * 
	 * @todo Move to trait because it also use by processor
	 */
	protected function setCalculatorTag($query = array(), $update = array()) {
		parent::setCalculatorTag($query, $update);
		foreach ($this->data as $item) {
			if ($this->isLineLegitimate($item) && !empty($item['tx_saved'])) {
				$this->removeBalanceTx($item); // we can safely remove the transactions after the lines have left the current queue
			}
		}
	}

	public static function getPrecision() {
		return static::$precision;
	}

	/**
	 * check if row is prepaid
	 * 
	 * @param array $row row handled by the calculator
	 * 
	 * @return boolean true it it's prepaid row
	 * @todo refactoring prepaid to strategy pattern
	 */
	public static function isPrepaid($row) {
		return isset($row['charging_type']) && $row['charging_type'] === 'prepaid';
	}

}
