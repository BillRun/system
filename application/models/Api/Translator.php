<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Array translator
 *
 * @package  Api
 * @since    5.3
 */
class Api_TranslatorModel {
	
	protected $TRANSLATOR_STUMP = "Api_Translator_";
	
	/**
	 * Array of type translators
	 * @var Billrun_Api_Translator_Type 
	 */
	protected $translators;
	
	/**
	 *
	 * @var array
	 */
	protected $activeRange;
	
	/**
	 *
	 * @var array
	 */
	protected $orFields;
	
	/**
	 * Create a new instance of the data translator object.
	 * @param array $options[active_range] -
	 * @param array $options[fields] - Array of fields from the structure: 'name' => VALUE, 'type'=> VALUE, 'options' => ARRAY VALUE
	 * @param array $options[operators] - Array of operators from the structure: 'field_name' => operator, like: 'from'=> 'gt'
	 */
	public function __construct(array $options) {
		$fields = $options['fields'];
		$this->orFields = Billrun_Util::getFieldVal($options['or_fields'], array());
		
		// Go through the input fields.
		foreach ($fields as $current) {
			$translator = $this->createTranslator($current);
			if(!$translator) {
				continue;
			}
			$this->translators[] = $translator;
		}
	}
	
	/**
	 * Create a translator
	 * @param array $translatorOptions - Input options for the translator (name, type, options)
	 * @param array $operators - Input operators for field name.
	 * @todo: Create a class for translator options, to validate that all the data is there
	 * @return Billrun_Api_Translator_Type or null on failure.
	 */
	protected function createTranslator(array $translatorOptions) {
		if(!isset($translatorOptions['name'], $translatorOptions['options'], $translatorOptions['type'])) {
			// TODO: Move the error code to a constant
			throw new Billrun_Exceptions_Api(98);
		}
		
		$type = $translatorOptions['type'];
		
		// Create the new type validator.
		$translatorName = $this->TRANSLATOR_STUMP . ucfirst(strtolower($type)) . 'Model';

		// Check that the class exists.
		if(!class_exists($translatorName)) {
			Billrun_Factory::log("Translator could not be initialized! " . print_r($translatorName,1), Zend_Log::WARN);
			return null;
		}
		
		$name = $translatorOptions['name'];
		$options = $translatorOptions['options'];
		$preConversions = isset($translatorOptions['preConversions']) ? $translatorOptions['preConversions'] : [];
		$postConversions = isset($translatorOptions['postConversions']) ? $translatorOptions['postConversions'] : [];
		
		// Create the validator.
		return new $translatorName($name, $options, $preConversions, $postConversions);
	}
	
	/**
	 * Translate an array
	 * @param string $rootName - The root name of the onject.
	 * @param array $input - Input array
	 * @return array ['success' => true|false, 'data' => Translated array|Invalid fields array].
	 */
	public function translate(array $input) {
		$output = $input;
		$invalidFields = array();
		$translatedCount = 0;
		foreach ($this->translators as $translator) {
			$output = $translator->preConvert($output);
			$result = $translator->translate($output);
			if($result instanceof Billrun_DataTypes_InvalidField) {
				// Check that the error should be registered.
				// TODO: I made it so the first 10 numbers (starting from zero),
				// will be the actual errors, we should move all these magic values
				// to constants
				$invalidFields[] = $result;
				continue;
			}
			$result = $translator->postConvert($result);
			
			$translatedCount++;
			
			$output = $result;
		}
		
		// Throw invalid fields exception
		if(!empty($invalidFields)) {
			return array('success' => false, 'data' => $invalidFields);
		}
		
		return array('success' => true, 'data' => $this->forceOutputByOrFields($output));
	}
	
	/**
	 * Converts the translated parameters to a queried one by the method received
	 * 
	 * @param array $output
	 * @return array
	 */
	protected function forceOutputByOrFields($output) {
		if (empty($this->orFields)) {
			$ret = $output;
		} else {
			foreach ($this->orFields as $field) {
				if (isset($output[$field])) {
					$orFields[$field] = $output[$field];
				}
			}
			$ret['$or'] = array_chunk($orFields, 1, true);
			$ret = array_merge($ret, array_diff_key($output, $orFields));
		}
		
		return $ret;
	}
}