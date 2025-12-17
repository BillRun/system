<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Validator class
 *
 */
class Billrun_Validator {

	public $integerPattern = '/^\s*[+-]?\d+\s*$/';
	public $numberPattern = '/^\s*[-+]?[0-9]*\.?[0-9]+([eE][-+]?[0-9]+)?\s*$/';
	public static $validatorsFunctions = array(
		'required' => 'RequiredValidator',
		'filter' => 'FilterValidator',
		'match' => 'RegularExpressionValidator',
		'url' => 'UrlValidator',
		'unique' => 'UniqueValidator',
		'compare' => 'CompareValidator',
		'length' => 'LengthValidator',
		'uniqueWith' => 'UniqueWithValidator',
		'in' => 'RangeValidator',
		'number' => 'NumberValidator',
		'integer' => 'IntegerValidator',
		'default' => 'DefaultValueValidator',
		'boolean' => 'BooleanValidator',
		'date' => 'DateValidator',
	);
	protected $options;
	protected $rules;
	protected $errors;
	protected $params;
	protected $isValid;
	protected $summaryReport;

	public function __construct(array $params = array()) {

		$this->params = $params;

		$this->errors = array("attributes" > array(), "global" => array());
		$this->options = array();
		$this->summaryReport = array();
		$this->isValid = true;
		$this->validations = Billrun_Config::getInstance(new Yaf_Config_Ini(APPLICATION_PATH . '/conf/validation.ini'))->toArray();
	}

	protected function isEmpty($value, $trim = false) {
		return $value === null || $value === array() || $value === '' || $trim && is_scalar($value) && trim($value) === '';
	}

	protected function addError($attribute, $message, $code, $index = -1) {
		$this->valid = false;
		if ($index >= 0) {
			$indexText = sprintf("[%d]", $index + 1);
			$this->errors['attributes'][$attribute][$index][] = array("message" => $message, "error_code" => $code);
		} else {
			$indexText = "";
			$this->errors['attributes'][$attribute][] = array("message" => $message, "error_code" => $code);
		}
		$replacement = $attribute . $indexText;
		$new_message = preg_replace("/" . $attribute . "/", $replacement, $message);
		$this->summaryReport[] = $new_message;
	}

	public function RequiredValidator($attribute, $value, $validationOptions = array(), $index = -1) {
		$code = "required";

		$trim = isset($validationOptions["trim"]) ? $validationOptions["trim"] : false;

		if (isset($validationOptions["message"])) {
			$message = $validationOptions['message'];
		} else {
			$message = $attribute . " is required";
		}

		if ($this->isEmpty($value, $trim)) {
			$this->addError($attribute, $message, $code, $index);
			return false;
		}
		return true;
	}

	public function DefaultValueValidator($attribute, $value, $validationOptions = array(), $index = -1) {
		$checkValue = $validationOptions["checkValue"];
		return $checkValue;
	}

	public function FilterValidator($attribute, $value, $validationOptions = array(), $index = -1) {
		$trim = isset($validationOptions["trim"]) ? $validationOptions["trim"] : false;
		if ($this->isEmpty($value, $trim)) {
			$this->addError($attribute, $message, $code, $index);
			return false;
		}
		return true;
	}

	public function UniqueValidator($attribute, $value, $validationOptions = array(), $index = -1) {

		$code = "unique";


		if (strlen(trim("$value")) == 0 || !isset($validationOptions["collection"])) {
			return true;
		}

		$collection = Billrun_Factory::db()->getCollection($validationOptions["collection"]);
		//Billrun_Factory::log("collection :" .print_r($collection,true) , Zend_Log::DEBUG);

		if (!($collection instanceof Mongodloid_Collection)) {
			$this->addError($attribute, $validationOptions["collection"] . " is not instanceof of $message Mongodloid_Collection", $code, $index);
			return false;
		}


		if (isset($validationOptions["message"])) {
			$message = $validationOptions['message'];
		} else {
			$message = $attribute . " with value : " . $value . " has already been taken";
		}

		$checkUniqueQuery = array($attribute => $value);

		if (isset($validationOptions["objectRef"]["_id"])) {
			$MongoID = $validationOptions["objectRef"]["_id"];
			$checkUniqueQuery = array_merge($checkUniqueQuery, array("_id" => array('$ne' => new Mongodloid_Id((string) $MongoID))));
		}

		$cursor = $collection->find($checkUniqueQuery, array());
		Billrun_Factory::log("$cursor->count() :" . var_export($checkUniqueQuery, true), Zend_Log::DEBUG);
		if ($cursor->count()) {
			$this->addError($attribute, $message, $code, $index);
			return false;
		}

		return true;
	}

	public function UniqueWithValidator($attribute, $value, $validationOptions = array(), $index = -1) {

		$code = "uniqueWith";
		if (!(isset($validationOptions["attributes"]) && is_array($validationOptions["attributes"]))) {
			return true;
		}

		$collection = Billrun_Factory::db()->getCollection($validationOptions["collection"]);
		if (!($collection instanceof Mongodloid_Collection)) {
			return true;
		}

		if (isset($validationOptions["message"])) {
			$message = $validationOptions['message'];
		} else {
			$message = $attribute . " with value : " . $value . " has already been taken";
		}

		$checkUniqueQuery = array();

		foreach ($validationOptions["attributes"] as $attrIndex => $attr) {
			//if(!isset($validationOptions["objectRef"][$attr])){ 
			$checkUniqueQuery[$attr] = $validationOptions["objectRef"][$attr];
			//}
		}

		if (isset($validationOptions["objectRef"]["_id"])) {
			$MongoID = $validationOptions["objectRef"]["_id"];
			$checkUniqueQuery = array_merge($checkUniqueQuery, array("_id" => array('$ne' => new Mongodloid_Id((string) $MongoID))));
		}



		$cursor = $collection->find($checkUniqueQuery, array());
		Billrun_Factory::log("$cursor->count() :" . print_r($cursor, true), Zend_Log::DEBUG);
		if ($cursor->count()) {
			$this->addError($attribute, $message, $code, $index);
			return false;
		}

		return true;
	}

	public function NumberValidator($attribute, $value, $validationOptions = array(), $index = -1) {
		$code = "number";

		if (strlen(trim("$value")) == 0) {
			return true;
		}
		if (isset($validationOptions["message"])) {
			$message = $validationOptions['message'];
		} else {
			$message = $attribute . " must be an number";
		}

		if (!preg_match($this->numberPattern, "$value")) {
			$this->addError($attribute, $message, $code, $index);
			return false;
		}

		$status = true;
		if ($validationOptions["max"] !== null && $value > $validationOptions["max"]) {
			$message = $attribute . " is  greater then the Maximum :" . $validationOptions["max"];
			$this->addError($attribute, $message, $code, $index);
			$status = false;
		}

		if ($validationOptions["min"] !== null && $value < $validationOptions["min"]) {
			$message = $attribute . " is  lower then the Minimum :" . $validationOptions["min"];
			$this->addError($attribute, $message, $code, $index);
			$status = false;
		}

		return $status;
	}

	public function LengthValidator($attribute, $value, $validationOptions = array("min" => null, "max" => null), $index = -1) {
		$length = strlen($value);

		$code = "length";
		$status = true;

		if ($validationOptions["min"] !== null && $length < $validationOptions["min"]) {
			$message = $attribute . " is too short (minimum is " . $validationOptions["min"] . " characters)";
			$this->addError($attribute, $message, $code, $index);
			$status = false;
		}

		if ($validationOptions["max"] !== null && $length > $validationOptions["max"]) {
			$message = $attribute . " is too long (maximum is " . $validationOptions["max"] . " characters)";
			$this->addError($attribute, $message, $code, $index);
			$status = false;
		}
		return $status;
	}

	public function IntegerValidator($attribute, $value, $validationOptions = array(), $index = -1) {

		$code = "integer";
		if (strlen(trim("$value")) == 0)
			return true;

		if (isset($validationOptions["message"])) {
			$message = $validationOptions['message'];
		} else {
			$message = $attribute . " must be an integer";
			if (!isset($validationOptions["message"])) {
				$validationOptions["message"] = $message;
			}
		}

		if (!$this->NumberValidator($attribute, $value, $validationOptions, $index)) {
			return true;
		};
		if (!preg_match($this->integerPattern, "$value")) {
			$this->addError($attribute, $message, $code, $index);
			return false;
		}


		return true;
	}

	public function validate_one_level($object, $collection) {

		/*  get collection rules tree    */
		$val = $this->getKeyVal(array($this->validations, $collection));
		if (!$this->getKeyVal(array($this->validations, $collection))) {

			return $this;
		}
		/* loop over the collection attributes */


		foreach ($object as $attr => $attrValue) {


			/* the attribute validation rules */
			$attrRules = $this->getKeyVal(array($this->validations, $collection, $attr));

			if ($attrRules == null)
				continue;


			$attrType = $this->getKeyVal(array($attrRules, "is"), "scalar");

			if (isset($attrRules["is"])) {
				unset($attrRules["is"]);
			}


			foreach ($attrRules as $check => $checkOptions) {
				//skip undefined validation test

				if (!is_array($checkOptions)) {
					$checkOptions = array("checkValue" => $checkOptions);
				}

				if ($check === "unique" || $check === "uniqueWith") {
					if (!isset($checkOptions["collection"])) {
						$checkOptions = array_merge(array("collection" => $collection), $checkOptions);
					}
				}

				$checkOptions["objectRef"] = $object;
				if (!(isset(self::$validatorsFunctions[$check]))) {
					Billrun_Factory::log("undefined check function  => $check (please implement)", Zend_Log::ERROR);
					continue;
				}


				if ($attrType == "array") {
					$fn = array('self', self::$validatorsFunctions[$check]);
					$valIndex = 0;
					foreach ($attrValue as $scalarVal) {
						call_user_func($fn, $attr, $scalarVal, $checkOptions, $valIndex);
						$valIndex++;
					}
				}

				if ($attrType == "scalar") {
					$fn = array('self', self::$validatorsFunctions[$check]);
					call_user_func($fn, $attr, $attrValue, $checkOptions);
				}
			}
		}
		return $this;
	}

	public function validate($object, $collection) {

		/*  get collection rules tree    */
		Billrun_Factory::log("Validate collection : " . $collection . "\n", Zend_Log::INFO);
		$val = $this->getKeyVal(array($this->validations, $collection));
		if (!$this->getKeyVal(array($this->validations, $collection))) {

			return $this;
		}
		/* loop over the collection attributes */

		$flat = $this->flatTree($object);
		Billrun_Factory::log("Flat : " . var_export($flat, true), Zend_Log::INFO);

		Billrun_Factory::log("collection validations : " . var_export($this->getKeyVal(array($this->validations, $collection)), 1) . "\n", Zend_Log::INFO);


		foreach (array_values($flat) as $attrInfo) {
			$attr = $attrInfo["key"];
			$attrRules = $this->getKeyVal(array($this->validations, $collection, $attr));

			//Billrun_Factory::log("attrRules  for $attr : " . var_export($attrRules,1) ."\n" , Zend_Log::INFO);

			if ($attrRules === null)
				continue;

			$attrType = $this->getKeyVal(array($attrRules, "is"), "scalar");

			if (isset($attrRules["is"])) {
				unset($attrRules["is"]);
			}


			foreach ($attrRules as $check => $checkOptions) {
				if (!is_array($checkOptions)) {
					$checkOptions = array("checkValue" => $checkOptions);
				}

				if ($check === "unique" || $check === "uniqueWith") {
					if (!isset($checkOptions["collection"])) {
						$checkOptions = array_merge(array("collection" => $collection), $checkOptions);
					}
				}

				$checkOptions["objectRef"] = $object;

				if (!(isset(self::$validatorsFunctions[$check]))) {
					Billrun_Factory::log("undefined check function  => $check (please implement)", Zend_Log::INFO);
					continue;
				}


				if ($attrInfo["type"] == "array") {
					$fn = array('self', self::$validatorsFunctions[$check]);
					$valIndex = 0;
					foreach ($attrInfo["value"] as $scalarVal) {
						call_user_func($fn, $attr, $scalarVal, $checkOptions, $valIndex);
						$valIndex++;
					}
				} else {
					$fn = array('self', self::$validatorsFunctions[$check]);
					call_user_func($fn, $attr, $attrInfo["value"], $checkOptions);
				}
			}
		}
		return $this;
	}

	public function getOptions() {
		return $this->options;
	}

	public function getRules() {
		return $this->rules;
	}

	public function getErrors() {
		return array(
			"errors" => $this->errors,
			"summaryReport" => $this->summaryReport,
			"isValid" => $this->isValid()
		);
	}

	public function setReport($report) {
		$this->summaryReport = $report;
		$this->isValid = false;
		return $this->getErrors();
	}

	public function getReport() {
		return $this->summaryReport;
	}

	public function getValidations() {
		return $this->validations;
	}

	public function isValid() {
		if (sizeof($this->summaryReport)) {
			return false;
		}
		return true;
	}

	public function getKeyVal($array = array(), $default = null) {
		$where = array_shift($array);
		$stepinto = $where;

		while ($key = array_shift($array)) {
			if (!isset($stepinto[$key])) {
				return $default;
			} else {
				$stepinto = $stepinto[$key];
			}
		}

		return $stepinto;
	}

	private function is_list($array) {
		foreach ($array as $key => $value) {
			if (is_string($key)) {
				return false;
			};
		}
		return true;
	}

	public function flatTree($params) {


		Billrun_Factory::log("flat params  => " . var_export($params, true), Zend_Log::INFO);
		$iterator = new RecursiveIteratorIterator(
			new RecursiveArrayIterator(array("params" => $params)), RecursiveIteratorIterator::CHILD_FIRST
		);
		$attrs = array();

		for ($iterator; $iterator->valid(); $iterator->next()) {
			$value = $iterator->current();
			$type = gettype($value);

			if ($type === "array" && !self::is_list($value))
				continue;

			array_push($attrs, array("key" => $iterator->key(),
				"type" => $type,
				"depth" => $iterator->getDepth(),
				"value" => $value));
		}

		return $attrs;
	}

}
