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
	 * Array of account ids queued for rebalance in rebalance_queue collection
	 * @var array
	 */
	protected $aidsQueuedForRebalance;

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
		if (isset($options['calculator']['unlimited_to_balances'])) {
			$this->unlimited_to_balances = (boolean) ($options['calculator']['unlimited_to_balances']);
		}
		$this->months_limit = Billrun_Factory::config()->getConfigValue('pricing.months_limit', 0);
		$this->billrun_lower_bound_timestamp = strtotime($this->months_limit . " months ago");
		// set months limit
		if ($autoload) {
			$this->load();
		}
		//TODO: check how to remove call to loadRates
		$this->balances = Billrun_Factory::db()->balancesCollection()->setReadPreference('RP_PRIMARY');

		$this->active_billrun = Billrun_Billrun::getActiveBillrun();
		$this->active_billrun_end_time = Billrun_Billingcycle::getEndTime($this->active_billrun);
		$this->next_active_billrun = Billrun_Billingcycle::getFollowingBillrunKey($this->active_billrun);

		$this->aidsQueuedForRebalance = array_flip(Billrun_Util::verify_array(Billrun_Factory::db()->rebalance_queueCollection()->distinct('aid'), 'int'));
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
		if (isset($this->aidsQueuedForRebalance[$row['aid']])) {
			return false;
		}

		try {
			Billrun_Factory::dispatcher()->trigger('beforeCalculatorUpdateRow', array(&$row, $this));
			$rateField = 'arate';
			$arate = $row->get($rateField, true);
			$totalPricingData = array();
			$rates = (is_null($row->get('rates', true)) ? array(): $row->get('rates', true));
			$foreignFields = array();
			foreach ($rates as &$rate) {
				$row[$rateField] = $rate['rate'];
				$row['retail_rate'] = $this->isRetailRate($rate);
				$calcRow = Billrun_Calculator_Row::getInstance('Customerpricing', $row, $this, $row['connection_type']);
				$calcRow->preUpdate();
				$pricingData = $calcRow->update();
				if (is_bool($pricingData)) {
					return $pricingData;
				}
				$foreignFields = array_merge($foreignFields, $this->getForeignFields( array('balance' => $calcRow->getBalance() ,
																			'services' => $calcRow->getUsedServices(),
																			'plan' => $calcRow->getPlan()) ,
																	 $row->getRawData()));
				$this->updatePricingData($rate, $totalPricingData, $pricingData);
			}
			
			unset($row['retail_rate']);
			if (empty($arate)) {
				unset($row[$rateField]);
			} else {
				$row[$rateField] = $arate;
			}
			
			$row->setRawData(array_merge($row->getRawData(), $totalPricingData, $foreignFields));
			$row->set('rates', $rates);
			$this->afterCustomerPricing($row);
			Billrun_Factory::dispatcher()->trigger('afterCalculatorUpdateRow', array(&$row, $this));
		} catch (Exception $e) {
			Billrun_Factory::log('Line with stamp ' . $row['stamp'] . ' crashed when trying to price it. got exception :' . $e->getCode() . ' : ' . $e->getMessage() . "\n trace :" . $e->getTraceAsString(), Zend_Log::ERR);
			return false;
		}
	}
	
	/**
	 * update rate object and pricing data of entire line based on current rate calculation
	 * 
	 * @param array $rate (by ref) - current rate object
	 * @param array $totalPricingData (by ref) - entire line's pricing data
	 * @param array $pricingData - current rate pricing data
	 */
	protected function updatePricingData(&$rate, &$totalPricingData, $pricingData) {
		$pricingField = $this->getPricingField();
		$rate['pricing'] = $pricingData;
		if (isset($rate['pricing'][$pricingField])) {
			$rate['pricing']['charge'] = $rate['pricing'][$pricingField];
			unset($rate['pricing'][$pricingField]);
		}
		foreach ($pricingData as $key => $val) {
			if ($key === $pricingField && !$this->shouldAddToRetailPrice($rate)) {
				continue;
			}
			if (!isset($totalPricingData[$key]) || $key === 'billrun') {
				$totalPricingData[$key] = $val;
			} else {
				$totalPricingData[$key] += $val;
			}
		}
	}


	/**
	 * returns whether or not the current rate is a retail rate
	 * 
	 * @param array $rate
	 * @return boolean
	 */
	protected function isRetailRate($rate) {
		return $rate['tariff_category'] === 'retail';
	}
	
	/**
	 * whether or not the received rate pricing data should be added to the retail price
	 * 
	 * @param array $rate
	 * @return boolean
	 */
	protected function shouldAddToRetailPrice($rate) {
		return ($this->isRetailRate($rate)) ||
			(isset($rate['add_to_retail']) && $rate['add_to_retail']);
	}
	
	/**
	 * Handles special cases in customer pricing needs to be updated after price calculation
	 * 
	 * @param $row (reference) - will be changed 
	 */
	protected function afterCustomerPricing(&$row) {
		if ($row['type'] == 'credit' && $row['usaget'] === 'refund') { // handle the case of refund by usagev (calculators can only handle positive values)
			$row['aprice'] = -abs($row['aprice']);

		}
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
			Billrun_Factory::db()->queueCollection()->update($where, $save);
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteLine', array('data' => $line, 'calculator' => $this));
	}

	public function getPossiblyUpdatedFields() {
		return array_merge(parent::getPossiblyUpdatedFields(), array($this->pricingField, 'billrun', 'over_plan', 'in_plan', 'out_plan', 'plan_ref', 'usagesb', 'arategroups', 'over_arate', 'over_group', 'in_group', 'in_arate', 'rates', 'out_group'));
	}

	public function getPricingField() {
		return $this->pricingField;
	}

	/**
	 * removes the transactions from the subscriber's balance to save space.
	 * @param type $row
	 */
	public function removeBalanceTx($row) {
		Billrun_Balances_Util::removeTx($row);
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
		if (!empty($line['skip_calc']) && in_array(static::$type, $line['skip_calc'])) {
			return FALSE;
		}
		if (empty($line['rates'])) {
			return false;
		}
		foreach ($line['rates'] as $rate) {
			$arate = Billrun_Rates_Util::getRateByRef($rate['rate']);
			if (is_null($arate) || (!empty($arate['skip_calc']) && in_array(self::$type, $arate['skip_calc']))) {
				return false;
			}
		}
		return isset($line['sid']) && $line['sid'] !== false &&
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
	
	public function getActiveBillrunEndTime() {
		return $this->active_billrun_end_time;
	}
	public function getActiveBillrun() {
		return $this->active_billrun;
	}
	public function getNextActiveBillrun() {
		return $this->next_active_billrun;
	}
	
	protected function getForeignFieldsFromConfig() {
		$foreignFields = parent::getForeignFieldsFromConfig();
		$config = Billrun_Factory::config();
		$runningTimeForeign = [];
		if($config->isMultiDayCycle()) {
			$runningTimeForeign[] = [
				'field_name' => 'foreign.account.invoicing_day',
				'foreign' => [
					'entity' => 'account',
					'field' => 'invoicing_day'
				]
			];
		}
		return !empty(array_diff(array_column($runningTimeForeign, 'field_name'), array_column($foreignFields, 'field_name'))) ? array_merge($foreignFields, $runningTimeForeign) : $foreignFields;
	}
}
