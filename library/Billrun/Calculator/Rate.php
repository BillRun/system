<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Rate calculator class for records
 *
 * @package  calculator
 * @since    2.0
 */
abstract class Billrun_Calculator_Rate extends Billrun_Calculator {

	const DEF_CALC_DB_FIELD = 'arate';
	const DEF_RATE_KEY_DB_FIELD = 'arate_key';
	const DEF_APR_DB_FIELD = 'apr';

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'rate';

	/**
	 * Data from line used to find the correct rate.
	 * Used in inner function to find the rate of the line
	 * 
	 * @var array
	 */
	protected $rowDataForQuery = array();

	/**
	 * The mapping of the fileds in the lines to the 
	 * @var array
	 */
	protected $rateMapping = array();
	protected static $calcs = array();

	/**
	 * The rating field to update in the CDR line.
	 * @var string
	 */
	protected $ratingField = self::DEF_CALC_DB_FIELD;
	protected $ratingKeyField = self::DEF_RATE_KEY_DB_FIELD;
	protected $pricingField = Billrun_Calculator_CustomerPricing::DEF_CALC_DB_FIELD;
	protected $aprField = self::DEF_APR_DB_FIELD;

	/**
	 * call offset
	 * 
	 * @param int $balance
	 */
	protected $call_offset = 0;

	/**
	 * Should the rating field be overriden if it already exists?
	 * @var boolean
	 */
	protected $overrideRate = TRUE;

	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['calculator']['rate_mapping'])) {
			$this->rateMapping = $options['calculator']['rate_mapping'];
			//Billrun_Factory::log("receive options : ".print_r($this->rateMapping,1),  Zend_Log::DEBUG);
		}
		if (isset($options['realtime'])) {
			$this->overrideRate = !boolval($options['realtime']);
		}
		if (isset($options['credit']) && boolval($options['credit'])) {
			$this->overrideRate = true;
		}
		if (isset($options['calculator']['override_rate'])) {
			$this->overrideRate = boolval($options['calculator']['override_rate']);
		}
	}

	/**
	 * Get a CDR line volume (duration/count/bytes used)
	 * @param $row the line to get  the volume for.
	 * @param the line usage type
	 * @deprecated since version 2.9
	 */
	abstract protected function getLineVolume($row);

	/**
	 * Get the line usage type (SMS/Call/Data/etc..)
	 * @param $row the CDR line  to get the usage for.
	 * @deprecated since version 2.9
	 */
	abstract protected function getLineUsageType($row);

	/**
	 * method to receive the lines the calculator should take care
	 * 
	 * @return Mongodloid_Cursor Mongo cursor for iteration
	 */
	protected function getLines() {
		return $this->getQueuedLines(array('type' => static::$type));
	}

	/**
	 * @see Billrun_Calculator::getCalculatorQueueType
	 */
	public function getCalculatorQueueType() {
		return self::$type;
	}
	
	public function prepareData($lines) {
		
	}
	/**
	 * @see Billrun_Calculator::isLineLegitimate
	 */
	public function isLineLegitimate($line) {
		return $line['type'] == static::$type;
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
		Billrun_Factory::db()->linesCollection()->update($where, $save);
		Billrun_Factory::db()->queueCollection()->update($where, $save);
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteLine', array('data' => $line, 'calculator' => $this));
	}

	public function getPossiblyUpdatedFields() {
		return array_merge(parent::getPossiblyUpdatedFields(), array($this->ratingField, $this->ratingKeyField, 'usaget', 'usagev', $this->pricingField, $this->aprField, 'rates', 'cf' ));
	}
	
	protected static function getRateCalculatorClassName($type) {
		return 'Billrun_Calculator_Rate_Usage';
	}

	/**
	 * load calculator rate by line type
	 * 
	 * @param array $line the line properties
	 * @param array $options options to load
	 * @todo Create one calculator instance per line type
	 * 
	 * @return Billrun calculator rate class
	 */
	public static function getRateCalculator($line, array $options = array()) {
		$type = $line['type'];
		if (!isset(self::$calcs[$type])) {
			$options = array_merge(
				$options, 
				array(
					'usaget' => $line['usaget'],
					'type' => $line['type'],
					)
			);
			$class = self::getRateCalculatorClassName($type);
			self::$calcs[$type] = new $class($options);
			
			// @TODO: use always the first condition for all types - it will load the config values by default
//			if ($type === 'smsc' || $type === 'smpp' || $type === 'tap3') {
//				$configOptions = Billrun_Factory::config()->getConfigValue('Rate_' . ucfirst($type), array());
//				$options = array_merge($options, $configOptions);
//			}
//
//			if ($type === 'callrt') {
//				$options = array_merge($options, array('usaget' => $line['usaget']));
//			}
//			$class = 'Billrun_Calculator_Rate_' . ucfirst($type);
//			if (!class_exists($class, true)) {
//				Billrun_Factory::log("getRateCalculator '$class' is an invalid class! line:" . print_r($line, true), Zend_Log::ERR);
//				// TODO: How to handle error?
//				return false;
//			}
//			self::$calcs[$type] = new $class($options);
		}
		return self::$calcs[$type];
	}

	public function setCallOffset($val) {
		$this->call_offset = $val;
	}

	public function getCallOffset() {
		return $this->call_offset;
	}

	/**
	 * make the calculation
	 */
	public function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorUpdateRow', array(&$row, $this));
		$current = $row->getRawData();
		$rate = $this->getLineRate($row);
		// TODO: need to refactoring the real-time handling
		if (is_null($rate) || $rate === false) {
			$row['granted_return_code'] = Billrun_Factory::config()->getConfigValue('realtime.granted_code.failed_calculator.rate');
			$row['usagev'] = 0;
			return false;
		}

		// TODO: need to refactoring the real-time handling
		if (isset($row['realtime']) && $row['realtime'] && $this->isRateBlockedByPlan($row, $rate)) {
			$row['granted_return_code'] = Billrun_Factory::config()->getConfigValue('realtime.granted_code.failed_calculator.rate');
			$row['usagev'] = 0;
			return false;
		}

		if (isset($rate['key']) && $rate['key'] == "UNRATED") {
			return false;
		}

		// TODO: Create the ref using the collection, not the entity object.
		$rate->collection(Billrun_Factory::db()->ratesCollection());
		$added_values = array(
			$this->ratingField => $rate ? $rate->createRef() : $rate,
		);

		if (isset($rate['key'])) {
			$added_values[$this->ratingKeyField] = $rate['key'];
		}

		if ($rate) {
			// TODO: push plan to the function to enable market price by plan
			$added_values[$this->aprField] = Billrun_Rates_Util::getTotalCharge($rate, $row['usaget'], $row['usagev'], $row['plan'], array(), 0, $row['urt']->sec);
		}
		$row->setRawData( array_merge($current, $added_values , $this->getForeignFields(array('rating_data' => $added_values),$current)) );

		Billrun_Factory::dispatcher()->trigger('afterCalculatorUpdateRow', array(&$row, $this));
		return $row;
	}

	/**
	 * Get the associate rate object for a given CDR line.
	 * @param $row the CDR line to get the for.
	 * @param $usage_type the CDR line  usage type (SMS/Call/etc..)
	 * @return the Rate object that was loaded  from the DB  or false if the line shouldn't be rated.
	 */
	protected function getLineRate($row) {
		if ($this->overrideRate || !isset($row[$this->getRatingField()])) {
			$this->setRowDataForQuery($row);
			$rate = $this->getRateByParams($row);
		} else {
			$rate = Billrun_Factory::db()->ratesCollection()->getRef($row[$this->getRatingField()]);
		}
		return $rate;
	}

	/**
	 * Set data used in inner function to find the rate of the line
	 * 
	 * @param type $row current line to find it's rate
	 */
	protected function setRowDataForQuery($row) {
		$timeField = Billrun_Config::getInstance()->getConfigValue('rate_pipeline.' . static::$type . '.time_field', 'urt');
		$calledNumberField = Billrun_Config::getInstance()->getConfigValue('rate_pipeline.' . $row['usaget'] . '.called_number_field', Billrun_Config::getInstance()->getConfigValue('rate_pipeline.' . static::$type . '.called_number_field', 'called_number'));
		$countryCodeField = Billrun_Config::getInstance()->getConfigValue('rate_pipeline.' . static::$type . '.country_code_field', 'location_mcc');
		$countryCode = $row->get($countryCodeField);
		if (!$countryCode) {
			$countryCode = Billrun_Factory::config()->getConfigValue('rate.default_mcc', 425);
		}
		$countryCode = substr($countryCode, 0, 3);
		$this->rowDataForQuery = array(
			'line_time' => $row->get($timeField),
			'called_number' => $row->get($calledNumberField),
			'country_code' => $countryCode,
		);
	}

	/**
	 * Get a matching rate by config params
	 * @return Mongodloid_Entity the matched rate or false if none found
	 */
	protected function getRateByParams($row) {
		$query = $this->getRateQuery($row);
		Billrun_Factory::dispatcher()->trigger('extendRateParamsQuery', array(&$query, &$row, &$this));
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$matchedRate = $rates_coll->aggregate($query)->current();

		if ($matchedRate->isEmpty()) {
			return false;
		}

 		$rawData = $matchedRate->getRawData();
 		if (!isset($rawData['key']) || !isset($rawData['_id']['_id']) || !($rawData['_id']['_id'] instanceof MongoId)) {
 			return false;	
 		}
 		$idQuery = array(
 			"key" => $rawData['key'], // this is for sharding purpose
 			"_id" => $rawData['_id']['_id'],
 		);
 		
 		return $rates_coll->query($idQuery)->cursor()->current();
	}

	/**
	 * Builds aggregate query from config
	 * 
	 * @return string mongo query
	 */
	protected function getRateQuery($row) {
		$pipelines = Billrun_Config::getInstance()->getConfigValue('rate_pipeline.' . self::$type, array()) +
			Billrun_Config::getInstance()->getConfigValue('rate_pipeline.' . static::$type, array());
		$query = array();
		foreach ($pipelines as $currPipeline) {
			if (!is_array($currPipeline)) {
				continue;
			}
			foreach ($currPipeline as $pipelineOperator => $pipeline) {
				$pipelineValue = '';
				if (is_array($pipeline)) {
					foreach ($pipeline as $key => $value) {
						$key = str_replace('__', '.', $key);
						if (isset($value['classMethod'])) {
							if (!method_exists($this, $value['classMethod'])) {
								continue;
							}
							$val = $this->{$value['classMethod']}($row, $key);
							if (!is_null($val)) {
								$pipelineValue[$key] = $val;
							}
						} else {
							$pipelineValue[$key] = (is_numeric($value)) ? intval($value) : $value;
						}
					}
				} else {
					$pipelineValue = (is_numeric($pipeline)) ? intval($pipeline) : $pipeline;
				}

				$query[] = array('$' . $pipelineOperator => $pipelineValue);
			}
		}
		
		return $query;
	}

	/**
	 * Assistance function to generate 'from' field query with current row.
	 * 
	 * @return array query for 'from' field
	 */
	protected function getFromTimeQuery() {
		return array('$lte' => $this->rowDataForQuery['line_time']);
	}

	/**
	 * Assistance function to generate 'to' field query with current row.
	 * 
	 * @return array query for 'to' field
	 */
	protected function getToTimeQuery() {
		return array('$gte' => $this->rowDataForQuery['line_time']);
	}

	public function getRatingField() {
		return $this->ratingField;
	}

	protected function isRateBlockedByPlan($row, $rate) {
		$plan = Billrun_Factory::db()->plansCollection()->getRef($row['plan_ref']);
		if (isset($plan['disallowed_rates']) && isset($rate['key']) && in_array($rate['key'], $plan['disallowed_rates'])) {
			Billrun_Factory::log('Plan ' . $plan['name'] . ' is not allowed to use rate ' . $rate['key'], Zend_Log::NOTICE);
			return true;
		}

		if (!isset($rate['rates'][$row['usaget']][$plan['name']]) && !isset($rate['rates'][$row['usaget']]['BASE'])) {
			return true;
		}

		return false;
	}
	
	/**
	 * method to clean specific prefixes from phone number
	 * the prefixes taken from config in the next format:
	 * rate.{static::$type}.trimPrefixes or rate.trimPrefixes
	 * (former takes precedence)
	 *
	 * @param string $number the number to handle
	 *
	 * @return string clean number
	 */
	public function getCleanNumber($number) {
		$minLength = Billrun_Factory::config()->getConfigValue('rate.' . $this->getType() . '.minLengthTrimPrefixes', Billrun_Factory::config()->getConfigValue('rate.minLengthTrimPrefixes', 10));
		if (strlen($number) < $minLength) {
			return $number;
		}
		$configurationTrimPrefixes = Billrun_Factory::config()->getConfigValue('rate.' . $this->getType() . '.trimPrefixes', Billrun_Factory::config()->getConfigValue('rate.trimPrefixes', array()));
		if (!empty($configurationTrimPrefixes)) {
			return preg_replace('/^(' . implode('|', array_map('preg_quote', $configurationTrimPrefixes)) . ')/', '', $number);
		}
		return $number;
	}
	
	protected function getCountryCodeMatchQuery() {
		return array('$in' => Billrun_Util::getPrefixes($this->rowDataForQuery['country_code']));
	}
}
