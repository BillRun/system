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
	 * Get an array of prefixes for a given.
	 * @param string $str the number to get prefixes to.
	 * @return Array the possible prefixes of the number sorted by prefix size in decreasing order.
	 */
	protected function getPrefixes($str) {
		$prefixes = array();
		for ($i = strlen($str); $i > 0; $i--) {
			$prefixes[] = substr($str, 0, $i);
		}
		return $prefixes;
	}

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
			if ($type === 'smsc' || $type === 'smpp') {
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
		if (isset($rate['key']) && $rate['key'] == "UNRATED") {
			return false;
		}
		$added_values = array(
			'usaget' => $usage_type,
			'usagev' => $volume,
			$this->ratingField => $rate ? $rate->createRef() : $rate,
		);
		if ($rate) {
			$added_values[$this->aprField] = Billrun_Calculator_CustomerPricing::getPriceByRate($rate, $usage_type, $volume);
		}
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorUpdateRow', array($row, $this));
		return $row;
	}

}
