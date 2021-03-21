<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Translator type
 *
 * @package  Api
 * @since    5.2
 */
abstract class Api_Translator_TypeModel {

	/**
	 * The date field name
	 * @var string
	 */
	protected $fieldName;

	/**
	 * Extra options for the translator.
	 * @var array
	 */
	protected $options;

	/**
	 * Known string conversions to do on the input before validating
	 * @var array
	 */
	protected $preConversions;

	/**
	 * Known string conversions to do on the input after validating
	 * @var array
	 */
	protected $postConversions;
	
	protected $queryFieldTranslate = false;

	/**
	 * Create a new instance of the Date type translator object
	 * @param string $fieldName - Field name of the data to translate.
	 * @param string $operator - Operator for the field, null if none.
	 * @param array $options - Array of extra options for the translator
	 */
	public function __construct($fieldName, array $options = array(), $preConversions = array(), $postConversions = array()) {
		$this->fieldName = $fieldName;
		$this->options = $options;
		$this->preConversions = $preConversions;
		$this->postConversions = $postConversions;
	}

	/**
	 * Translate an array
	 * @param array $input - Input array
	 * @return array Translated array.
	 */
	public function translate(array $input) {
		$fieldName = $this->fieldName();

		if (!array_key_exists($fieldName, $input)) {
			$invalidField = $this->handleMandatory();
			Billrun_Factory::log("Invalid field: " . print_r($invalidField, 1));
			return $invalidField;
		}

		// Translate the value.
		$translated = $this->translateField($input[$fieldName]);

		if (!$this->valid($translated)) {
			$invalidField = new Billrun_DataTypes_InvalidField($fieldName, 3);
			return $invalidField;
		}
		$output = $input;	
		if ($this->queryFieldTranslate) {
			unset($output[$fieldName]);
			$output = array_merge($output, $translated);
		} else {
			$output[$fieldName] = $translated;
		}
		return $output;
	}

	/**
	 * Handle a missing field, if the field is mandatory, return a missing field error.
	 * If the field is not mandatory, returns an empty field error.
	 * @return \Billrun_DataTypes_InvalidField The error
	 */
	protected function handleMandatory() {
		// Default error is mandatory field missing
		$errorCode = 2;

		$level = $this->mandatoryLevel();

		// If the field is not mandatory, the error is just 'empty field'
		// TODO: Move the magic numbers to constants
		if (!$level) {
			$errorCode = 10;
			// TODO: Move these magic constants
		} elseif ($level == 2) {
			$errorCode = 3;
		}

		return new Billrun_DataTypes_InvalidField($this->fieldName, $errorCode);
	}

	/**
	 * Translate an array
	 * @param mixed $data - Input data
	 * @return mixed Translated value.
	 */
	public function translateField($data) {
		$translated = $this->internalTranslateField($data);

		return $translated;
	}

	/**
	 * Translate an array
	 * @param mixed $data - Input data
	 * @return mixed Translated value.
	 */
	public abstract function internalTranslateField($data);

	/**
	 * Get the field name for the value to translate.
	 * @return string The field name to translate.
	 */
	public function fieldName() {
		return $this->fieldName;
	}

	/**
	 * 
	 * @param array $input
	 */
	public function preConvert(array $input) {
		$fieldName = $this->fieldName();
		foreach (Billrun_Util::getFieldVal($this->preConversions, array()) as $conversion) {
			$input[$fieldName] = $this->convert($input[$fieldName], $conversion);
		}
		return $input;
	}

	public function postConvert(array $input) {
		$fieldName = $this->fieldName();
		foreach (Billrun_Util::getFieldVal($this->postConversions, array()) as $conversion) {
			$input[$fieldName] = $this->convert($input[$fieldName], $conversion);
		}
		return $input;
	}

	/**
	 * Actually perform the conversion
	 * @param mixed $value
	 * @param string $conversion
	 */
	protected function convert($value, $conversion) {
		switch ($conversion) {
			case 'json_decode':
				$value = json_decode($value, 1);
				break;
			case 'password_hash':
				$value = password_hash($value, PASSWORD_DEFAULT);
				break;

			default:
				break;
		}
		return $value;
	}
	
	/**
	 * method to validate the trasnlated value.
	 * 
	 * @param mixed $data the data to check
	 * @return boolean true if valid else false
	 */
	protected function valid($data) {
		return $data !== FALSE;
	}

}
