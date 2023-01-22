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
	const DEF_RATE_KEY_DB_FIELD = 'arate_key';
	const DEF_APR_DB_FIELD = 'apr';
	

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'rate';

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

	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['calculator']['rate_mapping'])) {
			$this->rateMapping = $options['calculator']['rate_mapping'];
			//Billrun_Factory::log()->log("receive options : ".print_r($this->rateMapping,1),  Zend_Log::DEBUG);
		}
	}

	/**
	 * Get a CDR line volume (duration/count/bytes used)
	 * @param $row the line to get  the volume for.
	 * @param the line usage type
	 */
	abstract protected function getLineVolume($row, $usage_type);

	/**
	 * Get the line usage type (SMS/Call/Data/etc..)
	 * @param $row the CDR line  to get the usage for.
	 */
	abstract protected function getLineUsageType($row);

	/**
	 * Get the associate rate object for a given CDR line.
	 * @param $row the CDR line to get the for.
	 * @param $usage_type the CDR line  usage type (SMS/Call/etc..)
	 * @return the Rate object that was loaded  from the DB  or false if the line shouldn't be rated.
	 */
	abstract protected function getLineRate($row, $usage_type);

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
		$saveProperties = array($this->ratingField,$this->ratingKeyField, 'usaget', 'usagev', $this->pricingField, $this->aprField);
		$saveProperties = array_merge($saveProperties,$this->getAdditionalProperties());
		foreach ($saveProperties as $p) {
			if (!is_null($val = $line->get($p, true))) {
				$save['$set'][$p] = $val;
			}
		}
		$where = array('stamp' => $line['stamp']);
		Billrun_Factory::db()->linesCollection()->update($where, $save);
		Billrun_Factory::db()->queueCollection()->update($where,$save);
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteLine', array('data' => $line, 'calculator' => $this));
		if (!isset($line['usagev']) || $line['usagev'] === 0) {
			$this->garbageQueueLines[] = $line['stamp'];
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
				$configOptions = Billrun_Factory::config()->getConfigValue('Rate_' . ucfirst($type));
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
		$usage_type = $this->getLineUsageType($row);
		$volume = $this->getLineVolume($row, $usage_type);
		$rate = $this->getLineRate($row, $usage_type);
		if (isset($row['roaming']) && ($row['roaming'] == true) && (isset($rate['alpha3']))){
			$current['alpha3'] = $rate['alpha3'];
		}
		$additional_values = $this->getLineAdditionalValues($row);
		if (isset($rate['key']) && $rate['key'] == "UNRATED") {
			return false;
		}
		$added_values = array(
			'usaget' => $usage_type,
			'usagev' => $volume,
			$this->ratingField => $rate ? $rate->createRef() : $rate,
		);
		if(isset($rate['key'])) {
			$added_values[$this->ratingKeyField] = $rate['key'];
		}
		
		if ($rate) {
			$added_values[$this->aprField] = Billrun_Calculator_CustomerPricing::getPriceByRate($rate, $usage_type, $volume);
		}
		if(!empty($additional_values)) {
			$added_values = array_merge($added_values,$additional_values);
		}
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorUpdateRow', array($row, $this));
		return $row;
	}
	
	/**
	 * method to get rate record by name, from and to period
	 * 
	 * @param string $rate_key name of the rate
	 * @param mixed $from numeric unix timestamp or string date (ISO)
	 * @param type $to numeric unix timestamp or string date (ISO)
	 * 
	 * @return Entity rate
	 */
	static public function getRateByName($rate_key, $from = null, $to = null) {
		if (is_null($from)) {
			$from = time();
		}
		if (is_null($to)) {
			$to = time();
		}
		
		if (is_string($from) && !is_numeric($from)) {
			$from = strtotime($from);
		}
		if (is_string($to) && !is_numeric($to)) {
			$to = strtotime($to);
		}
		
		$query = array(
			'key' => $rate_key,
			'from' => array(
				'$lt' => new MongoDate($from),
			),
			'to' => array(
				'$gt' => new MongoDate($to),
			)
		);
		$rates = Billrun_Factory::db()->ratesCollection();
		return $rates->query($query)->cursor()->current();
	}
	
	protected function getLineAdditionalValues($row){
		return array();
	}
	
	protected function getAdditionalProperties() {
		return array();
	}

}
