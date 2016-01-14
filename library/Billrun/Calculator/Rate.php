<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of RateAndVolume
 *
 * @author eran
 */
abstract class Billrun_Calculator_Rate extends Billrun_Calculator {

	const DEF_CALC_DB_FIELD = 'arate';
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
	protected $pricingField = Billrun_Calculator_CustomerPricing::DEF_CALC_DB_FIELD;
	protected $aprField = self::DEF_APR_DB_FIELD;

	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['calculator']['rate_mapping'])) {
			$this->rateMapping = $options['calculator']['rate_mapping'];
			//Billrun_Factory::log("receive options : ".print_r($this->rateMapping,1),  Zend_Log::DEBUG);
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
		$saveProperties = array($this->ratingField, 'usaget', 'usagev', $this->pricingField, $this->aprField);
		foreach ($saveProperties as $p) {
			if (!is_null($val = $line->get($p, true))) {
				$save['$set'][$p] = $val;
			}
		}
		$where = array('stamp' => $line['stamp']);
		Billrun_Factory::db()->linesCollection()->update($where, $save);
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteLine', array('data' => $line, 'calculator' => $this));
		if (!isset($line['usagev']) || $line['usagev'] === 0) {
			$this->removeLineFromQueue($line);
			unset($this->lines[$line['stamp']]);
		}
	}

	/**
	 * load calculator rate by line type
	 * 
	 * @param array $line the line properties
	 * @param array $options options to load
	 * 
	 * @return Billrun calculator rate class
	 */
	public static function getRateCalculator($line, array $options = array()) {
		$type = $line['type'];
		if (!isset(self::$calcs[$type])) {
			// @TODO: use always the first condition for all types - it will load the config values by default
			if ($type === 'smsc' || $type === 'smpp' || $type === 'tap3') {
				$configOptions = Billrun_Factory::config()->getConfigValue('Rate_' . ucfirst($type), array());
				$options = array_merge($options, $configOptions);
			}
			$class = 'Billrun_Calculator_Rate_' . ucfirst($type);
			self::$calcs[$type] = new $class($options);
		}
		return self::$calcs[$type];
	}

	/**
	 * make the calculation
	 */
	public function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorUpdateRow', array($row, $this));
		$current = $row->getRawData();
		$rate = $this->getLineRate($row);
		if (is_null($rate) || $rate === false) {
			$row['granted_return_code'] = Billrun_Factory::config()->getConfigValue('prepaid.customer.no_rate');
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
		if ($rate) {
			// TODO: push plan to the function to enable market price by plan
			$added_values[$this->aprField] = Billrun_Calculator_CustomerPricing::getPriceByRate($rate, $row['usaget'], $row['usagev'], $row['plan']);
		}
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);

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
		$this->setRowDataForQuery($row);
		return $this->getRateByParams($row);
	}
	
	/**
	 * Set data used in inner function to find the rate of the line
	 * 
	 * @param type $row current line to find it's rate
	 */
	protected function setRowDataForQuery($row) {
		$timeField = Billrun_Config::getInstance()->getConfigValue('rate_pipeline.' . static::$type . '.time_field', 'urt');
		$calledNumberField = Billrun_Config::getInstance()->getConfigValue('rate_pipeline.' . static::$type . '.called_number_field', 'called_number');
		$this->rowDataForQuery = array(
			'line_time' => $row->get($timeField),
			'called_number' => $row->get($calledNumberField),
		);
	}

	/**
	 * Get a matching rate by config params
	 * @return Mongodloid_Entity the matched rate or false if none found
	 */
	protected function getRateByParams($row) {		
		$query = $this->getRateQuery($row);
		$rates_coll= Billrun_Factory::db()->ratesCollection();
		$matchedRate = $rates_coll->aggregate($query)->current();
		
		if ($matchedRate->isEmpty()) {
			return false;
		}
		
		$key = $matchedRate->get('key');
		return $rates_coll->query(array("key" => $key))->cursor()->current();
	}
	
	/**
	 * Builds aggregate query from config
	 * 
	 * @return string mongo query
	 */
	protected function getRateQuery() {
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
							$pipelineValue[$key] = $this->{$value['classMethod']}($row);
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

}
