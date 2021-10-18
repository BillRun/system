<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */


/**
 * Configmodel class
 *
 * @package  Models
 * @since    2.1
 */
class ConfigModel {

	/**
	 * the collection the config run on
	 * 
	 * @var Mongodloid Collection
	 */
	protected $collection;

	/**
	 * the config values
	 * @var array
	 */
	protected $data;
	
	/**
	 * options of config
	 * @var array
	 */
	protected $options;
	protected $fileClassesOrder = array('file_type', 'parser', 'processor', 'customer_identification_fields', 'rate_calculators', 'pricing', 'receiver');
	protected $ratingAlgorithms = array('match', 'longestPrefix', 'equalFalse', 'range');
        
	/**
	 * reserved names of File Types.
	 * @var array
	 */
	protected $reservedFileTypeName = array('service', 'flat', 'credit', 'conditional_discount', 'discount', 'all');
	
	/**
	 * Valid file type names regex
	 * @var string
	 */
	protected $fileTypesRegex = '/^[a-zA-Z0-9_]+$/';
	
	/**
	 * Max custom header/footer template size
	 * @var number - bytes
	 */
	protected $invoice_custom_template_max_size = 1 * 1024 * 1024;

	public function __construct() {
		// load the config data from db
		$this->collection = Billrun_Factory::db()->configCollection();
		$this->options = array('receive', 'process', 'calculate', 'export');
		$this->loadConfig();
		br_yaf_register_autoload('Models', APPLICATION_PATH . '/application/modules/Billapi');
	}

	public function getOptions() {
		return $this->options;
	}

	protected function loadConfig() {
		$ret = $this->collection->query()
			->cursor()
			->sort(array('_id' => -1))
			->limit(1)
			->current()
			->getRawData();
		$this->data = $ret;
	}

	public function getConfig() {
		return $this->data;
	}

	/**
	 * 
	 * @param int $data
	 * @return type
	 * @deprecated since version Now
	 * @todo Remove this function?
	 */
	public function setConfig($data) {
		$updatedData = array_merge($this->getConfig(), $data);
		unset($updatedData['_id']);
		foreach ($this->options as $option) {
			if (!isset($data[$option])) {
				$data[$option] = 0;
			}
		}
		return $this->collection->insert($updatedData);
	}

	public function getFromConfig($category, $data) {
		$currentConfig = $this->getConfig();

		// TODO: Create a config class to handle just file_types.
		if ($category == 'file_types') {
			if (!is_array($data)) {
				Billrun_Factory::log("Invalid data for file types.");
				return 0;
			}
			if (empty($data['file_type'])) {
				return $currentConfig['file_types'];
			}
			if ($fileSettings = $this->getFileTypeSettings($currentConfig, $data['file_type'])) {
				return $fileSettings;
			}
			throw new Exception('Unknown file type ' . $data['file_type']);
		} else if ($category == 'subscribers') {
			return $currentConfig['subscribers'];
		} else if ($category == 'payment_gateways') {
 			if (!is_array($data)) {
 				Billrun_Factory::log("Invalid data for payment_gateways.");
 				return 0;
 			}
 			if (empty($data['name'])) {
 				return $this->getSecurePaymentGateways($this->_getFromConfig($currentConfig, $category, $data));
 			}
 			if ($pgSettings = $this->getPaymentGatewaySettings($currentConfig, $data['name'])) {
 				return $this->getSecurePaymentGateway($pgSettings);
			}
			throw new Exception('Unknown payment gateway ' . $data['name']);
		} else if ($category == 'export_generators') {
			 if (!is_array($data)) {
 				Billrun_Factory::log("Invalid data for export_generators.");
 				return 0;
 			}
 			if (empty($data['name'])) {
 				return $currentConfig['export_generators'];
 			}
 			if ($exportGenSettings = $this->getExportGeneratorSettings($currentConfig, $data['name'])) {
 				return $exportGenSettings;
 			}
 			throw new Exception('Unknown export_generator ' . $data['name']);
		} else if ($category == 'template_token'){
			$tokens = Billrun_Factory::templateTokens()->getTokens();
			$tokens = array_merge_recursive($this->_getFromConfig($currentConfig, $category, array()), $tokens);
			return $tokens;
		} else if ($category == 'minimum_entity_start_date'){
			return Models_Entity::getMinimumUpdateDate();
		} else if ($category === 'plugin_actions') {
			if (!empty($data['actions']) && is_array($data['actions'])) {
				$dispatcherChain = Billrun_Dispatcher::getInstance(array('type' => 'chain'));
				foreach ($data['actions'] as $methodName) {
					$plugins[$methodName] = $dispatcherChain->getImplementors($methodName);
				}
			}
			return $plugins;
		} else if ($category === 'plugins') {
			$plugins = $this->_getFromConfig($currentConfig, $category, $data);
			// Add configuration fields array to plugins
			$configuration = Billrun_Factory::dispatcher()->trigger('getConfigurationDefinitions');
			foreach ($plugins as $key => $plugin) {
				if (!is_string($plugin) && !empty($configuration[$plugin['name']])) {
					$plugins[$key]['configuration']['fields'] = $configuration[$plugin['name']];
				}
			}
			return $plugins;
		}
		
		return $this->_getFromConfig($currentConfig, $category, $data);
	}

	/**
	 * Internal getFromConfig function, recursively extracting values and handling
	 * any complex values.
	 * @param type $currentConfig
	 * @param type $category
	 * @param array $data
	 * @return mixed value
	 * @throws Exception
	 */
	protected function _getFromConfig($currentConfig, $category, $data) {
		if (is_array($data) && !empty($data)) {
			$dataKeys = array_keys($data);
			foreach ($dataKeys as $key) {
				$result[] = $this->_getFromConfig($currentConfig, $category . "." . $key, null);
			}
			return $result;
		}

		$valueInCategory = Billrun_Utils_Mongo::getValueByMongoIndex($currentConfig, $category);

		if (!empty($category) && $valueInCategory === null) {
			$result = $this->handleGetNewCategory($category, $data, $currentConfig);
			return $result;
		}
		
		$translated = Billrun_Config::translateComplex($valueInCategory);
		return $translated;
	}

	protected function extractComplexFromArray($array) {
		$returnData = array();
		// Check for complex objects.
		foreach ($array as $key => $value) {
			if (Billrun_Config::isComplex($value)) {
				// Get the complex object.
				$returnData[$key] = Billrun_Config::getComplexValue($value);
			} else {
				$returnData[$key] = $value;
			}
		}

		return $returnData;
	}

	/**
	 * Update a config category with data
	 * @param string $category
	 * @param mixed $data
	 * @return mixed
	 */
	public function updateConfig($category, $data) {
		$updatedData = $this->getConfig();
		unset($updatedData['_id']);

		if(empty($category)) {
			if (!$this->updateRoot($updatedData, $data)) {
				return 0;
			}
		}
		// TODO: Create a config class to handle just file_types.
		else if ($category === 'file_types') {
			if (!is_array($data)) {
				Billrun_Factory::log("Invalid data for file types.");
				return 0;
			}
			if (empty($data['file_type'])) {
				throw new Exception('Couldn\'t find file type name');
			}
			$this->setSettingsArrayElement($updatedData, $data, 'file_types', 'file_type');
			$fileSettings = $this->validateFileSettings($updatedData, $data['file_type'], FALSE);
		} else if ($category === 'payment_gateways') {	
			if (!is_array($data)) {
				Billrun_Factory::log("Invalid data for payment gateways.");
				return 0;
			}
			if (empty($data['name'])) {
				throw new Exception('Couldn\'t find payment gateway name');
			}
			$paymentGateway = Billrun_Factory::paymentGateway($data['name']);
			if (!is_null($paymentGateway)){
				$supported = true;
			}
			else{
				$supported = false;
			}
			if (is_null($supported) || !$supported) {
				throw new Exception('Payment gateway is not supported');
			}
			$secretFields = $paymentGateway->getSecretFields();
			$fakePassword = Billrun_Factory::config()->getConfigValue('billrun.fake_password', 'password');
			$defaultParameters = $paymentGateway->getDefaultParameters();
			$releventParameters = array_intersect_key($defaultParameters, $data['params']); 
			$neededParameters = array_keys($releventParameters);
			$rawPgSettings = $this->getPaymentGatewaySettings($updatedData, $data['name']);
			foreach ($data['params'] as $key => $value) {
				if (!in_array($key, $neededParameters)){
					unset($data['params'][$key]);
				} elseif (in_array($key, $secretFields) && $value === $fakePassword && isset ($rawPgSettings['params'][$key])){
					$data['params'][$key] = $rawPgSettings['params'][$key];
				}
			}
			if ($rawPgSettings) {
				$pgSettings = array_merge($rawPgSettings, $data);
			} else {
				$pgSettings = $data;
			}
			$this->setPaymentGatewaySettings($updatedData, $pgSettings);
 			$pgSettings = $this->validatePaymentGatewaySettings($updatedData, $data, $paymentGateway);
 			if (!$pgSettings){
 				return 0;
 			}
		} else if ($category === 'export_generators') {
			if (!is_array($data)) {
				Billrun_Factory::log("Invalid data for export generator.");
				return 0;
			}
			if (empty($data['name'])) {
				throw new Exception('Couldn\'t find export generator name');
			}
//			if (empty($data['file_type'])) {
//				throw new Exception('Export generator must be associated to input processor');
//			}
//			if (empty($data['segments']) || !is_array($data['segments'])){
//				throw new Exception('Segments must be an array and contain at least one value');
//			}
			
			$rawExportGenSettings = $this->getExportGeneratorSettings($updatedData, $data['name']);
			if ($rawExportGenSettings) {
				$generatorSettings = array_merge($rawExportGenSettings, $data);
			} else {
				$generatorSettings = $data;
			}
			$this->setExportGeneratorSettings($updatedData, $generatorSettings);
			$generatorSettings = $this->validateExportGeneratorSettings($updatedData, $data);
			if (!$generatorSettings) {
				return 0;
			}
		} else if ($category === 'shared_secret') {
			if (!is_array($data)) {
				Billrun_Factory::log("Invalid data for shared secret.");
				return 0;
			}
			if (empty($data['name'])) {
				throw new Exception('Missing name');
			}
			if (empty($data['from']) || empty($data['to'])) {
				throw new Exception('Missing creation/expiration dates');
			}
			if (!isset($data['key'])) {
				$secret = Billrun_Utils_Security::generateSecretKey();
				$data = array_merge($data, $secret);
			}
			$data['from'] = new Mongodloid_Date(strtotime($data['from']));
			$data['to'] = new Mongodloid_Date(strtotime($data['to']));
			$this->setSharedSecretSettings($updatedData, $data);
			$sharedSettings = $this->validateSharedSecretSettings($updatedData, $data);
			if (!$sharedSettings) {
				return 0;
			}
		} else if ($category === 'usage_types' && !$this->validateUsageType($data)) {
			throw new Exception($data . ' is an illegal activity type');
		} else if ($category === 'invoice_export' && !$this->validateStringLength($data['header'], $this->invoice_custom_template_max_size)) {
			$max = Billrun_Util::byteFormat($this->invoice_custom_template_max_size, "MB", 0, true);
			throw new Exception("Custom header template is too long, maximum size is {$max}.");
		} else if ($category === 'invoice_export' && !$this->validateStringLength($data['footer'], $this->invoice_custom_template_max_size)) {
			$max = Billrun_Util::byteFormat($this->invoice_custom_template_max_size, "MB", 2, true);
			throw new Exception("Custom footer template is too long, maximum size is ${$max}.");
		} else if (strpos($category, 'events.') === 0 && !$this->validateEvents($category, $data)) {
			throw new Exception("Error saving events");
		} else if (strpos($category, 'event.') === 0) {
			$eventType = explode('.', $category)[1];
			if ($this->validateEvent($eventType, $data)) {
				$updatedData['events'][$eventType][] = $data;
			}
		} else if ($category === 'plugins') {
			throw new Exception('Only one plugin can be saved');
		} else if ($category === 'plugin') {
			if (empty($data['name'])) {
				throw new Exception('Missing plugin name');
			}
			// Search for plugin in old structure - as array of classNames
			$old_strucrute_plugin_index = array_search($data['name'], $updatedData['plugins']);
			if ($old_strucrute_plugin_index !== FALSE) {
				// allow only in this case to set all parameters to convert class_name to new plugin structure
				$updatedData['plugins'][$old_strucrute_plugin_index] = $data;
			} else {
				$plugins_names = array_map(function($plugin) {
					return is_string($plugin) ? $plugin : $plugin['name'];
				}, $updatedData['plugins']);
				$plugin_index = array_search($data['name'], $plugins_names);
				if ($plugin_index === FALSE) {
					throw new Exception("Plugin {$data['name']} not found");
				}
				// Allow to update only 'enabled' flag and configuration values
				if (isset($data['enabled'])) {
					$updatedData['plugins'][$plugin_index]['enabled'] = $data['enabled'];
				}
				if (isset( $data['configuration']['values'])) {
					$updatedData['plugins'][$plugin_index]['configuration']['values'] = $data['configuration']['values'];
				}
			}
		} else {
			if (!$this->_updateConfig($updatedData, $category, $data)) {
				return 0;
			}
		}

		$ret = $this->collection->insert($updatedData);
		$saveResult = !empty($ret['ok']);
		if ($saveResult) {
			// Reload timezone.
			Billrun_Config::getInstance()->refresh();
			if ($category === 'shared_secret') {
				// remove previous defined clientof the same secret (in case of multiple saves or name change)
				Billrun_Factory::oauth2()->getStorage('access_token')->unsetClientDetails(null, $data['key']);
				// save into oauth_clients
				Billrun_Factory::oauth2()->getStorage('access_token')->setClientDetails($data['name'], $data['key'], Billrun_Util::getForkUrl());
			}
		}

		return $saveResult;
	}
		
	/**
	 * runs before update of configuration, validates that data is correct
	 * 
	 * @param string category
	 * @param array $data
	 * @param array $prevData
	 * @return true on success, throws error in case of an error
	 * @todo re-factor after fields move from subscribers.account.fields => accounts.fields
	 */
	protected function preUpdateConfig($category, &$data, $prevData) {
		if ($this->isCustomFieldsConfig($category, $data)) {
			$this->validateCustomFields($category, $data, $prevData);
		} else if ($category === 'usage_types') {
			foreach ($data as $usagetData) {
				if (!$this->validateUsageType($usagetData['usage_type'])) {
					$message = $usagetData['usage_type'] == '' ? 'Empty string' : $usagetData['usage_type'];
					throw new Exception($message . ' is an illegal activity type');
				}
				if (!$this->validatePropertyType($usagetData['property_type'])) {
					throw new Exception('Must select a property type');
				}
			}
		} else if ($category === 'plays') {
			$this->validatePlays($category, $data, $prevData);
		}
		return true;
	}
	
	/**
	 * validates that fields attributes (mandatory/unique) really are set as defined for existing entities
	 * 
	 * @param string $category
	 * @param array $data
	 * @param array $prevData
	 * @return true on validation success
	 * @throws Exception on validation failure
	 */
	protected function validateCustomFields($category, &$data, $prevData) {
		$params = array(
			'no_init' => true,
			'collection' => $this->getCollectionName($category),
		);
		$entityModel = Models_Entity::getInstance($params);
		
		$mandatoryFields = array();
		$uniqueFields = array();
		foreach ($data as &$field) {
			$fieldName = $field['field_name'];
			$plays = Billrun_Util::getIn($field, 'plays', []);
			$prevField = false;
			foreach ($prevData as $f) {
				if ($f['field_name'] === $fieldName) {
					$prevField = $f;
					break;
				}
			}
			
			if (isset($field['unique']) && $field['unique']) {
				$field['mandatory'] = true;
			}

			if ($this->isFieldNewlySet('mandatory', $field, $prevField)) {
				$mandatoryFields[] = [
					'name' => $fieldName,
					'plays' => $plays,
				];
			}
			
			if ($this->isFieldNewlySet('unique', $field, $prevField)) {
				$uniqueFields[] = [
					'name' => $fieldName,
					'plays' => $plays,
				];
			}
		}

		if (!$this->validateMandatoryFields($entityModel, $mandatoryFields)) {
			throw new Exception('cannot make field\s [' . implode(', ', array_column($mandatoryFields, 'name')) .'] mandatory because there is an entity missing one of those fields');
		}

		if (!$this->validateUniqueFields($entityModel, $uniqueFields)) {
			throw new Exception('cannot make field\s [' . implode(', ', array_column($uniqueFields, 'name')) .'] unique because for one of those fields there is more than one entity with the same value');
		}
		
		return true;
	}
	
	/**
	 * validates that plays configuration is valid
	 * 
	 * @param string $category
	 * @param array $data
	 * @param array $prevData
	 * @return true on validation success
	 * @throws Exception on validation failure
	 */
	protected function validatePlays($category, &$data, $prevData) {
		foreach ($data as $play) {
			$playName = $play['name'];
			if (empty($playName)) {
				throw new Exception('Play name cannot be empty');
			}
			$playsWithSameName = array_filter($data, function($play) use ($playName) { return $play['name'] == $playName; });
			if (count($playsWithSameName) > 1) {
				throw new Exception("Play with name \"{$playName}\" already exists");
			}
		}
		
		$removedOrDisabled = array();
		foreach ($prevData as $prevPlay) {
			$index = array_search($prevPlay['name'], array_column($data, 'name'));
			if ($index === false || ($prevPlay['enabled'] && !$data[$index]['enabled'])) {
				$removedOrDisabled[] = $prevPlay['name'];
			}
		}
		
		if (!empty($removedOrDisabled) && $this->isPlaysInUse($removedOrDisabled)) {
			throw new Exception("Plays in use cannot be removed/disabled");
		}
		
		return true;
	}
	
	protected function isPlaysInUse($plays) {
		$query = Billrun_Utils_Mongo::getDateBoundQuery();
		$query['play'] = array(
			'$in' => $plays,
		);
		
		$checkInEntities = ['plans', 'services', 'rates', 'subscribers'];
		foreach ($checkInEntities as $entity) {
			$inUseInEntity = !Billrun_Factory::db()->{$entity . 'Collection'}()->query($query)->cursor()->current()->isEmpty();
			if ($inUseInEntity) {
				return true;
			}
		}
		return false;
		
	}
	
	/**
	 * checks if the field is now set to true, but previously was not set or was set to false
	 * 
	 * @param string $fieldName
	 * @param array $field
	 * @param array $prevField
	 * @return boolean
	 */
	protected function isFieldNewlySet($fieldName, $field, $prevField) {
		if (!$prevField) {
			return (isset($field[$fieldName]) && $field[$fieldName]);
		}
		return (isset($field[$fieldName]) && $field[$fieldName]) &&
			(!isset($prevField[$fieldName]) || !$prevField[$fieldName]);
	}
	
	/**
	 * checks that all existing entities of type model have all fields marked as mandatories
	 * 
	 * @param Models_Entity $entityModel
	 * @param array $mandatoryFields
	 * @return boolean
	 */
	protected function validateMandatoryFields(Models_Entity $entityModel, $mandatoryFields) {
		if (empty($mandatoryFields)) {
			return true;
		}
		
		$mandatoryQuery = array_merge(Billrun_Utils_Mongo::getDateBoundQuery(time(), true), $entityModel->getMatchSubQuery());
		$mandatoryQuery['$or'] = array();
		foreach ($mandatoryFields as $field) {
			if (Billrun_Utils_Plays::isPlaysInUse() && !empty($field['plays'])) {
				$mandatoryQuery['$or'][] = array(
					'play' => array('$in' => $field['plays']),
					$field['name'] => ''
				);
				$mandatoryQuery['$or'][] = array(
					'play' => array('$in' => $field['plays']),
					$field['name'] => array('$exists' => false)
				);
			} else {
				$mandatoryQuery['$or'][] = array($field['name'] => '');
				$mandatoryQuery['$or'][] = array($field['name'] => array('$exists' => false));
			}
		}
		
		return $entityModel->getCollection()->query($mandatoryQuery)->count() === 0;
	}
	
	/**
	 * checks that all existing entities of type model have all fields marked as unique different from each other
	 * 
	 * @param Models_Entity $entityModel
	 * @param array $uniqueFields
	 * @return boolean
	 */
	protected function validateUniqueFields(Models_Entity $entityModel, $uniqueFields) {
		if (empty($uniqueFields)) {
			return true;
		}
		
		$basicMatch = array_merge(Billrun_Utils_Mongo::getDateBoundQuery(time(), true), $entityModel->getMatchSubQuery());
		$sort = array('t.from' => 1);
		$match2 = array(
			's' => array('$gt' => 1),
		);
		
		foreach ($uniqueFields as $field) {
			$matchFields = array(
				$field['name'] => array('$exists' => true),
			);
			if (Billrun_Utils_Plays::isPlaysInUse() && !empty($field['plays'])) {
				$matchFields['play'] = ['$in' => $field['plays']];
			}
			$match = array_merge($basicMatch, $matchFields);
			$unwind = '$' . $field['name'];
			$project = array(
				$field['name'] => 1,
				't.from' => '$from',
				't.to' => '$to',
			);
			
			$group = array(
				'_id' => '$' . $field['name'],
				'ts' => array('$push' => '$t'),
				's' => array('$sum' => 1),
			);
			
			$results = $entityModel->getCollection()->aggregate(
				array('$match' => $match),
				array('$unwind' => $unwind),
				array('$project' => $project),
				array('$sort' => $sort),
				array('$group' => $group),
				array('$match' => $match2)
			);
			
			foreach ($results as $result) {
				$prevRange = null;
				foreach ($result['ts'] as $range) {
					if ($prevRange && $prevRange['to']->sec >= $range['from']->sec) {
						return false;
					}
					
					$prevRange = $range;
				}
			}
		}
		
		return true;
	}
	
	protected function getCustomFields() {
		return array(
			'subscribers.subscriber.fields' => 'subscribers',
			'subscribers.account.fields' => 'accounts',
			'rates.fields' => 'rates',
			'plans.fields' => 'plans',
			'services.fields' => 'services',
		);
	}
	
	protected function getCollectionName($category) {
		return Billrun_Util::getIn($this->getCustomFields(), $category, '');
	}
	
	/**
	 * checks if the updated category is of  a custom field
	 * 
	 * @param string $category
	 * @param array $data
	 * @return boolean
	 */
	protected function isCustomFieldsConfig($category, $data) {
		return array_key_exists($category, $this->getCustomFields());
	}
	
	public function validateConfig($category, $data) {
		$updatedData = $this->getConfig();
		if ($category === 'file_types') {
			if (empty($data['file_type'])) {
				throw new Exception('Couldn\'t find file type name');
			}
			$this->setSettingsArrayElement($updatedData, $data, 'file_types', 'file_type');
			return $this->validateFileSettings($updatedData, $data['file_type']);
		}
	}

	/**
	 * Load the config template.
	 * @return array The array representing the config template
	 */
	protected function loadTemplate() {
		// Load the config template.
		// TODO: Move the file path to a constant
		$templateFileName = APPLICATION_PATH . "/conf/config/template.json";
		$string = file_get_contents($templateFileName);
		$json_a = json_decode($string, true);
		return $json_a;
	}
	
	/**
	 * Update the config root category.
	 * @param array $currentConfig - The current configuration, passed by reference.
	 * @param array $data - The data to set. Treated as an hierchical JSON structure.
	 * (See _updateCofig).
	 * @return int
	 */
	protected function updateRoot(&$currentConfig, $data) {
		foreach ($data as $key => $value) {
			foreach ($value as $k => $v) {
//				Billrun_Factory::log("Data: " . print_r($data,1));
//				Billrun_Factory::log("Value: " . print_r($value,1));
				if (!$this->_updateConfig($currentConfig, $k, $v)) {
					return 0;
				}
			}
		}
		return 1;
	}
	
	/**
	 * Internal update process, used to update primitive and complex config values.
	 * @param array $currentConfig - The current configuratuin, passed by reference.
	 * @param string $category - Name of the category in the config.
	 * @param array $data - The data to set to the config. This array is treated
	 * as a complete JSON hierchical structure, and can update multiple values at
	 * once, as long as none of the values to update are arrays.
	 * @return int
	 * @throws Billrun_Exceptions_InvalidFields
	 */
	protected function _updateConfig(&$currentConfig, $category, $data) {
		
		if ($category === 'taxation') {
			$this->updateTaxationSettings($currentConfig, $data);
		}
		
		$valueInCategory = Billrun_Utils_Mongo::getValueByMongoIndex($currentConfig, $category);

		if ($valueInCategory === null) {
			$result = $this->handleSetNewCategory($category, $data, $currentConfig);
			return $result;
		}

		// Check if complex object.
		if (Billrun_Config::isComplex($valueInCategory)) {
			// TODO: Do we allow setting?
			return $this->updateComplex($currentConfig, $category, $data, $valueInCategory);
		}
		
		// TODO: if it's possible to receive a non-associative array of associative arrays, we need to also check isMultidimentionalArray
		if (Billrun_Util::isAssoc($data) && !empty($data)) {
			foreach ($data as $key => $value) {
				if (!$this->_updateConfig($currentConfig, $category . "." . $key, $value)) {
					return 0;
				}
			}
			return 1;
		}
		
		$this->preUpdateConfig($category, $data, $valueInCategory);
		return Billrun_Utils_Mongo::setValueByMongoIndex($data, $currentConfig, $category);
	}
	
	protected function updateComplex(&$currentConfig, $category, $data, $valueInCategory) {
		// Set the value for the complex object,
		$valueInCategory['v'] = $data;

		// Validate the complex object.
		if (!Billrun_Config::isComplexValid($valueInCategory)) {
			Billrun_Factory::log("Invalid complex object " . print_r($valueInCategory, 1), Zend_Log::NOTICE);
			$invalidFields[] = Billrun_Utils_Mongo::mongoArrayToInvalidFieldsArray($category, ".");
			throw new Billrun_Exceptions_InvalidFields($invalidFields);
		}

		// Update the config.
		if (!Billrun_Utils_Mongo::setValueByMongoIndex($valueInCategory, $currentConfig, $category)) {
			return 0;
		}

		return 1;
	}
	
	/**
	 * Handle the scenario of a category that doesn't exist in the database
	 * @param string $category - The current category.
	 * @param array $data - Data to set.
	 * @param array $currenConfig - Current configuration data.
	 */
	protected function handleNewCategory($category, $data, &$currentConfig) {
		$splitCategory = explode('.', $category);

		$template = $this->loadTemplate();
		Billrun_Factory::log("Template: " . print_r($template,1), Zend_Log::DEBUG);
		$found = true;
		$ptrTemplate = &$template;
		$newConfig = $currentConfig;
		$newValueIndex = &$newConfig;
		
		// Go through the keys
		foreach ($splitCategory as $key) {
			// If the value doesn't exist check if it has a default value in the template ini
			if(!isset($newValueIndex[$key])) {
				$overrideValue = Billrun_Util::getFieldVal($ptrTemplate[$key], array());
				$newValueIndex[$key] = $overrideValue;
			}
			$newValueIndex = &$newValueIndex[$key];
			if(!isset($ptrTemplate[$key])) {
				$found = false;
				break;
			}
			$ptrTemplate = &$ptrTemplate[$key];
		}
		
		// Check if the value exists in the settings template ini.
		if(!$found) {
			Billrun_Factory::log("Unknown category", Zend_Log::NOTICE);
			return 0;
		}
		
		return $newConfig;
	}
	
	/**
	 * Handle the scenario of a category that doesn't exist in the database
	 * @param string $category - The current category.
	 * @param array $data - Data to set.
	 * @param array $currenConfig - Current configuration data.
	 */
	protected function handleGetNewCategory($category, $data, &$currentConfig) {
		// Set the data
		$newConfig = $this->handleNewCategory($category, $data, $currentConfig);
		if(!$newConfig) {
			throw new Exception("Category not found " . $category);
		}
		$currentConfig = $newConfig;
		
		$result = Billrun_Utils_Mongo::getValueByMongoIndex($currentConfig, $category);
		if(Billrun_Config::isComplex($result)) {
			return Billrun_Config::getComplexValue($result);
		}
		return $result;
	}
	
	protected function handleSetNewCategory($category, $data, &$currentConfig) {
		// Set the data
		$newConfig = $this->handleNewCategory($category, $data, $currentConfig);
		if(!$newConfig) {
			throw new Exception("Category not found " . $category);
		}
		$currentConfig = $newConfig;
		$value = Billrun_Utils_Mongo::getValueByMongoIndex($currentConfig, $category);
		if(Billrun_Config::isComplex($value)) {
			return $this->updateComplex($currentConfig, $category, $data, $value);
		}
		
		$result = Billrun_Utils_Mongo::setValueByMongoIndex($data, $currentConfig, $category);
		return $result;
	}
	
	protected function setConfigValue(&$config, $category, $toSet) {
		// Check if complex object.
		if (Billrun_Config::isComplex($toSet)) {
			return $this->setComplexValue($toSet);
		}

		if (is_array($toSet)) {
			return $this->setConfigArrayValue($toSet);
		}

		return Billrun_Utils_Mongo::setValueByMongoIndex($toSet, $config, $category);
	}

	protected function setConfigArrayValue($toSet) {
		
	}

	protected function setComplexValue($toSet) {
		// Check if complex object.
		if (!Billrun_Config::isComplex($valueInCategory)) {
			// TODO: Do we allow setting?
			Billrun_Factory::log("Encountered a problem", Zend_Log::NOTICE);
			return 0;
		}
		// Set the value for the complex object,
		$valueInCategory['v'] = $data;

		// Validate the complex object.
		if (!Billrun_Config::isComplexValid($valueInCategory)) {
			Billrun_Factory::log("Invalid complex object " . print_r($valueInCategory, 1), Zend_Log::NOTICE);
			$invalidFields = Billrun_Utils_Mongo::mongoArrayToInvalidFieldsArray($category, ".", false);
			throw new Billrun_Exceptions_InvalidFields($invalidFields);
		}

		// Update the config.
		if (!Billrun_Utils_Mongo::setValueByMongoIndex($valueInCategory, $currentConfig, $category)) {
			return 0;
		}

		if (Billrun_Config::isComplex($toSet)) {
			// Get the complex object.
			return Billrun_Config::getComplexValue($toSet);
		}

		if (is_array($toSet)) {
			return $this->extractComplexFromArray($toSet);
		}

		return $toSet;
	}

	public function unsetFromConfig($category, $data) {
		$updatedData = $this->getConfig();
		unset($updatedData['_id']);
		if ($category === 'file_types') {
			if (isset($data['file_type'])) {
				$this->unsetFileTypeSettings($updatedData, $data['file_type']);
			}
		}
		else if ($category === 'export_generators') {
			if (isset($data['name'])) {
				$this->unsetExportGeneratorSettings($updatedData, $data['name']);
			}
		}
		else if ($category === 'payment_gateways') {
 			if (isset($data['name'])) {
 				if (count($data) == 1) {
 					$this->unsetPaymentGatewaySettings($updatedData, $data['name']);
 				} else {
 					if (!$pgSettings = $this->getPaymentGatewaySettings($updatedData, $data['name'])) {
 						throw new Exception('Unkown payment gateway ' . $data['name']);
 					}
 					foreach (array_keys($data) as $key) {
 						if ($key != 'name') {
 							unset($pgSettings[$key]);
 						}
 					}
 					$this->setPaymentGatewaySettings($updatedData, $pgSettings);
 				}
 			}
 		}
		if ($category === 'shared_secret') {
 			if (isset($data['key'])) {
 				if (count($data) != 1) {
					throw new Exception('Can remove only one secret at a time');
 				} else {
					$this->unsetSharedSecretSettings($updatedData, $data['key']);
 				}
 			}
 		}
 
		$ret = $this->collection->insert($updatedData);
		
		if ($category === 'shared_secret') {
			// remove into oauth_clients
			Billrun_Factory::oauth2()->getStorage('access_token')->unsetClientDetails(null, $data['key']);
		}
		return !empty($ret['ok']);
	}
	
	public function setEnabled($category, $data, $enabled) {
		$updatedData = $this->getConfig();
		unset($updatedData['_id']);
		if ($category === 'file_types') {
			foreach ($updatedData['file_types'] as &$someFtSettings) {
				if ($someFtSettings['file_type'] == $data['file_type']) {
					$someFtSettings['enabled'] = $enabled;
					break;
				}
			}
		} else if ($category === 'export_generators') {
			foreach ($updatedData['export_generators'] as &$someFtSettings) {
				if ($someFtSettings['name'] == $data['name']) {
					$someFtSettings['enabled'] = $enabled;
					break;
				}
			}
		}
 
		$ret = $this->collection->insert($updatedData);
		return !empty($ret['ok']);
	}

	protected function getFileTypeSettings($config, $fileType, $enabledOnly = false) {
		if ($filtered = array_filter($config['file_types'], function($fileSettings) use ($fileType, $enabledOnly) {
			return $fileSettings['file_type'] === $fileType && 
				(!$enabledOnly || Billrun_Config::isFileTypeConfigEnabled($fileSettings));
		})) {
			return current($filtered);
		}
		return FALSE;
	}
	
	protected function getPaymentGatewaySettings($config, $pg) {
 		if ($filtered = array_filter($config['payment_gateways'], function($pgSettings) use ($pg) {
 			return $pgSettings['name'] === $pg;
 		})) {
 			return current($filtered);
 		}
 		return FALSE;
 	}
	
	protected function getExportGeneratorSettings($config, $name) {
 		if ($filtered = array_filter($config['export_generators'], function($exportGenSettings) use ($name) {
 			return $exportGenSettings['name'] === $name;
 		})) {
 			return current($filtered);
 		}
 		return FALSE;
 	}
 
	protected function setSettingsArrayElement(&$config, $element, $settingsKey, $elementKey) {
		$fileType = $element[$elementKey];
		$nestedVal = Billrun_Util::getIn($config, $settingsKey, array());
		$foundElement = FALSE;
		foreach ($nestedVal as &$someFileSettings) {
			if ($someFileSettings[$elementKey] == $fileType) {
				$foundElement = TRUE;
				$someFileSettings = $element;
				break;
			}
		}
		if (!$foundElement) {
			$nestedVal[] = $element;
		}
		Billrun_Util::setIn($config, $settingsKey, $nestedVal);
	}

	protected function unsetSettingsArrayElement(&$config, $settingsKey, $elementsKey, $elementId) {
		Billrun_Util::setIn($config, $settingsKey, array_filter(Billrun_Util::getIn($config, $settingsKey, []), function($fileSettings) use ($elementsKey, $elementId) {
			return $fileSettings[$elementsKey] !== $elementId;
		}));
	}
	
	protected function getSettingsArrayElement($config, $settingsKey, $elementsKey, $elementId) {
		return array_filter(Billrun_Util::getIn($config, $settingsKey, []), function($fileSettings) use ($elementId, $elementsKey) {
			return $fileSettings[$elementsKey] === $elementId;
		});
	}
	
	protected function setFileTypeSettings(&$config, $fileSettings) {
		$fileType = $fileSettings['file_type'];
		foreach ($config['file_types'] as &$someFileSettings) {
			if ($someFileSettings['file_type'] == $fileType) {
				$someFileSettings = $fileSettings;
				return;
			}
		}
		$config['file_types'] = array_merge($config['file_types'], array($fileSettings));
	}
	
	
	protected function setPaymentGatewaySettings(&$config, $pgSettings) {
 		$paymentGatewayName = $pgSettings['name'];
 		foreach ($config['payment_gateways'] as &$somePgSettings) {
 			if ($somePgSettings['name'] == $paymentGatewayName) {
				if (!empty($pgSettings['transactions']['receiver'])) {
					foreach ($pgSettings['transactions']['receiver']['connections'] as $key => $connection) {
						$pgSettings['transactions']['receiver']['connections'][$key]['receiver_type'] = $paymentGatewayName;
					}
				}
				if (!empty($pgSettings['denials']['receiver'])) {
					foreach ($pgSettings['denials']['receiver']['connections'] as $key => $connection) {
						$pgSettings['denials']['receiver']['connections'][$key]['receiver_type'] = $paymentGatewayName;
					}
				}	
 				$somePgSettings = $pgSettings;
 				return;
 			}
 		}
		$config['payment_gateways'] = array_merge($config['payment_gateways'], array($pgSettings));
	}

	protected function setSharedSecretSettings(&$config, $sharedSecretData) {
		$key = $sharedSecretData['key'];
		foreach ($config['shared_secret'] as &$secret) {
			if ($secret['key'] == $key) {
				$secret = $sharedSecretData;
				return;
			}
		}
		$config['shared_secret'] = array_merge($config['shared_secret'], array($sharedSecretData));
	}

	protected function validateSharedSecretSettings(&$config, $secret) {
		if ($secret['from'] > $secret['to']) {
			throw new Exception('Illegal dates');
		}
		return true;
	}

	protected function setExportGeneratorSettings(&$config, $egSettings) {
		$exportGenerator = $egSettings['name'];
		foreach ($config['export_generators'] as &$someEgSettings) {
 			if ($someEgSettings['name'] == $exportGenerator) {
 				$someEgSettings = $egSettings;
 				return;
 			}
 		}
        if (!$config['export_generators']) {
            $config['export_generators'] = array($egSettings);
        } else {
            $config['export_generators'] = array_merge($config['export_generators'], array($egSettings));
        }
 	}
 

	/**
	 * TODO change to unsetSettingsArrayElement
	 */
	protected function unsetFileTypeSettings(&$config, $fileType) {
		$config['file_types'] = array_values(array_filter($config['file_types'], function($fileSettings) use ($fileType) {
			return $fileSettings['file_type'] !== $fileType;
		}));
	}
	
	/**
	 * TODO change to unsetSettingsArrayElement
	 */
	protected function unsetPaymentGatewaySettings(&$config, $pg) {
 		$config['payment_gateways'] = array_values(array_filter($config['payment_gateways'], function($pgSettings) use ($pg) {
 			return $pgSettings['name'] !== $pg;
 		}));
 	}
	
	protected function unsetExportGeneratorSettings(&$config, $name) {
		$config['export_generators'] = array_values(array_filter($config['export_generators'], function($egSettings) use ($name) {
 			return $egSettings['name'] !== $name;
 		}));	
	}
	
	protected function unsetSharedSecretSettings(&$config, $secret) {
 		$config['shared_secret'] = array_values(array_filter($config['shared_secret'], function($secretSettings) use ($secret) {
 			return $secretSettings['key'] !== $secret;
 		}));
 	}
 
	protected function validateFileSettings(&$config, $fileType, $allowPartial = TRUE) {
		$completeFileSettings = FALSE;
		$fileSettings = $this->getFileTypeSettings($config, $fileType);
		if (!$this->isLegalFileTypeName($fileType)) {
			throw new Exception('"' . $fileType . '" is an illegal file type name. You may use only alphabets, numbers and underscores');
		}
		if ($this->isReservedFileTypeName($fileType)) {
			throw new Exception($fileType . ' is a reserved BillRun file type');
		}
		if (!$this->isLegalFileSettingsKeys(array_keys($fileSettings))) {
			throw new Exception('Incorrect file settings keys.');
		}
		$updatedFileSettings = array();
		$updatedFileSettings['file_type'] = $fileSettings['file_type'];
		if (isset($fileSettings['type']) && $this->validateType($fileSettings['type'])) {
			$updatedFileSettings['type'] = $fileSettings['type'];
		}
		if (isset($fileSettings['parser'])) {
			$updatedFileSettings['parser'] = $this->validateParserConfiguration($fileSettings['parser']);
			if (isset($fileSettings['processor'])) {
				$updatedFileSettings['processor'] = $this->validateProcessorConfiguration($fileSettings['processor']);
				if (isset($fileSettings['customer_identification_fields'])) {
					$updatedFileSettings['customer_identification_fields'] = $this->validateCustomerIdentificationConfiguration($fileSettings['customer_identification_fields']);
					if (isset($fileSettings['rate_calculators'])) {
						$updatedFileSettings['rate_calculators'] = $this->validateRateCalculatorsConfiguration($fileSettings['rate_calculators'], $config);
						if (isset($fileSettings['pricing'])) {
							$updatedFileSettings['pricing'] = $fileSettings['pricing'];
							if (isset($fileSettings['receiver'])) {
								$updatedFileSettings['receiver'] = $this->validateReceiverConfiguration($fileSettings['receiver']);
								$completeFileSettings = TRUE;
							} else if (isset($fileSettings['realtime'], $fileSettings['response'])) {
								$updatedFileSettings['realtime'] = $this->validateRealtimeConfiguration($fileSettings['realtime']);
								$updatedFileSettings['response'] = $this->validateResponseConfiguration($fileSettings['response']);
								$completeFileSettings = TRUE;
							}

							if (isset($fileSettings['unify'])) {
								$updatedFileSettings['unify'] = $this->getUnifyConfig($updatedFileSettings, $fileSettings['unify']);
							}
							
							if (isset($fileSettings['filters'])) {
								$updatedFileSettings['filters'] = $fileSettings['filters'];
							}
						}
					}
				}
			}
		}
		if (isset($fileSettings['enabled'])) {
			$updatedFileSettings['enabled'] = $fileSettings['enabled'];
		}
		if (!$allowPartial && !$completeFileSettings) {
			throw new Exception('File settings is not complete.');
		}
		$this->setSettingsArrayElement($config, $updatedFileSettings, 'file_types', 'file_type');
		return $this->checkForConflics($config, $fileType);
	}
	
	/**
	 * 
	 * @todo Insert validations
	 * @param string $category
	 * @param type $data
	 * @return boolean
	 */
	protected function validateEvents($category, $events) {
		$eventType = explode('.', $category)[1];
		foreach ($events as $event) {
			$this->validateEvent($type, $event);
		}
		
		return TRUE;
	}
	
	protected function validateEvent($eventType, $event) {
		switch ($eventType) {
			case 'fraud':
				return $this->validateFraudEvent($event);
			case 'balance':
				return $this->validateBalanceEvent($event);
			case 'settings':
			default:
				return true;
		}
		return true;
	}


	protected function validateBalanceEvent($event) {
		if (!isset($event['event_code'])) {
			throw new Exception('Event code is missing');
		}
		return true;
	}

	protected function validateFraudEvent($event) {
		if (!isset($event['event_code'])) {
			throw new Exception('Event code is missing');
		}
		$recurrenceBaseUnits = $event['recurrence']['value'] * ($event['recurrence']['type'] == 'hourly' ? 60 : 1);
		$dateRangeBaseUnits = $event['date_range']['value'] * ($event['date_range']['type'] == 'hourly' ? 60 : 1);
		if ($dateRangeBaseUnits < $recurrenceBaseUnits) {
			throw new Exception('Event recurrence must be less than or equal to date range');
		}
		return true;
	}

	protected function validateType($type) {
		$allowedTypes = array('realtime');
		return in_array($type, $allowedTypes);
	}
	
	protected function validateUsageType($usageType) {
		$reservedUsageTypes = array('cost', 'balance', '');
		return !in_array($usageType, $reservedUsageTypes);
	}
	
	protected function validatePropertyType($propertyType) {
		$reservedUsageTypes = array('');
		return !in_array($propertyType, $reservedUsageTypes);
	}

	protected function validateStringLength($str, $size) {
		return strlen($str) <= $size;
	}
	
	protected function validatePaymentGatewaySettings(&$config, $pg, $paymentGateway) {
 		$connectionParameters = array_keys($pg['params']);
 		$name = $pg['name'];	
		$defaultParameters = $paymentGateway->getDefaultParameters();
		$defaultParametersKeys = array_keys($defaultParameters);
		$diff = array_diff($defaultParametersKeys, $connectionParameters);
		if (!empty($diff)) {
			Billrun_Factory::log("Wrong parameters for connection to " . $name, Zend_Log::NOTICE);
			return false;
		}
		$isAuth = $paymentGateway->authenticateCredentials($pg['params']);
		if (!$isAuth){
			throw new Exception('Wrong credentials for connection to ' . $name, Zend_Log::NOTICE); 
		}	
		
 		return true;
 	}
 
	protected function validateExportGeneratorSettings(&$config, $eg) {
//		$fileTypeSettings = $this->getFileTypeSettings($config, $eg['file_type']);
//		if (empty($fileTypeSettings)){
//			Billrun_Factory::log("There's no matching file type "  . $eg['file_type']);
//			return false;
//		}
//		$parserSettings = $fileTypeSettings['parser'];
//		$inputProcessorFields = $parserSettings['structure'];
//		foreach ($eg['segments'] as $segment){
//			if (!in_array($segment['field'], $inputProcessorFields)){
//				Billrun_Factory::log("There's no matching field in the name of "  . $segment['field'] . "in input processor: ", $eg['file_type']);
//				return false;
//			}
//		}
		
		return true;
 	}
	
	/**
	 * gets all field names (distinct) used for volume in the input processor received
	 * 
	 * @param array $fileSettings
	 * @return array field names
	 */
	protected function getVolumeFields($fileSettings) {
		$volumeFields = array();
		if (isset($fileSettings['processor']['usaget_mapping'])) {
			foreach ($fileSettings['processor']['usaget_mapping'] as $mapping) {
				if ($mapping['volume_type'] === 'field') {
					$volumeSrc = is_array($mapping['volume_src']) ? $mapping['volume_src'] : array($mapping['volume_src']);
					$volumeFields = array_merge($volumeFields , $volumeSrc);
				}
			}
		}
		
		if (isset($fileSettings['processor']['default_volume_type']) && $fileSettings['processor']['default_volume_type'] === 'field') {
			$volumeSrc = is_array($fileSettings['processor']['default_volume_src']) ? $fileSettings['processor']['default_volume_src'] : array($fileSettings['processor']['default_volume_src']);
			$volumeFields = array_merge($volumeFields , $volumeSrc);
		}
		
		return array_unique($volumeFields);
	}

	protected function checkForConflics($config, $fileType) {
		$fileSettings = $this->getFileTypeSettings($config, $fileType);
		if (isset($fileSettings['processor'])) {
			$customFields = $fileSettings['parser']['custom_keys'];
			$calculatedFields = array_map(function($mapping) {
				return $mapping['target_field'];
			}, $fileSettings['processor']['calculated_fields'] ?? []);
			$uniqueFields[] = $dateField = $fileSettings['processor']['date_field'];
			$volumeFields = $this->getVolumeFields($fileSettings);
			$uniqueFields = array_merge($uniqueFields,  $volumeFields);
			if (!isset($fileSettings['processor']['usaget_mapping'])) {
				$fileSettings['processor']['usaget_mapping'] = array();
			}
			$useFromStructure = $uniqueFields;
			$usagetMappingSource = array_map(function($mapping) {
				return $mapping['src_field'];
			}, array_filter($fileSettings['processor']['usaget_mapping'], function($mapping) {
					return isset($mapping['src_field']);
				}));
			if (array_diff($usagetMappingSource, $customFields)) {
				throw new Exception('Unknown fields used for usage type mapping: ' . implode(', ', $usagetMappingSource));
			}
			$usagetTypes = array_map(function($mapping) {
				return $mapping['usaget'];
			}, $fileSettings['processor']['usaget_mapping']);
			if (isset($fileSettings['processor']['default_usaget'])) {
				$usagetTypes[] = $fileSettings['processor']['default_usaget'];
				$usagetTypes = array_unique($usagetTypes);
			}
			if (isset($fileSettings['customer_identification_fields'])) {
				$subscriberMappingUsageTypes = array_keys($fileSettings['customer_identification_fields']);
				if ($unknownUsageTypes = array_diff($subscriberMappingUsageTypes, $usagetTypes)) {
					throw new Exception('Unknown usage type(s) in subscriber identification: ' . implode(',', $unknownUsageTypes));
				}
				if ($usageTypesMissingSubscriberIdentification = array_diff($usagetTypes, $subscriberMappingUsageTypes)) {
					throw new Exception('Missing subscriber identification rules for usage types(s): ' . implode(',', $usageTypesMissingSubscriberIdentification));
				}
				foreach ($fileSettings['customer_identification_fields'] as $usaget => $customerIdentification) {
					$customerMappingSource = array_map(function($mapping) {
						return $mapping['src_key'];
					}, $customerIdentification);
					$useFromStructure = $uniqueFields = array_merge($uniqueFields, array_unique($customerMappingSource));
					$customerMappingTarget = array_map(function($mapping) {
						return $mapping['target_key'];
					}, $customerIdentification);
					$subscriberFields = array_map(function($field) {
						return $field['field_name'];
					}, $config['subscribers']['subscriber']['fields']);
					if ($subscriberDiff = array_unique(array_diff($customerMappingTarget, $subscriberFields))) {
						throw new Exception('Unknown subscriber fields ' . implode(',', $subscriberDiff));
					}
				}
				if (isset($fileSettings['rate_calculators'])) {
					$ratingUsageTypes = array();
					foreach ($fileSettings['rate_calculators'] as $category => $rates) {
						$ratingUsageTypes = array_merge($ratingUsageTypes, array_keys($rates));
					}
					$ratingUsageTypes = array_unique($ratingUsageTypes);
					$ratingLineKeys = array();
					foreach ($fileSettings['rate_calculators'] as $category => $rates) {
						foreach ($rates as $rules) {
							foreach ($rules['priorities'] as $usageRules) {
								foreach ($usageRules['filters'] as $rule) {
									$ratingLineKeys[] = $rule['line_key'];
								}
							}
						}
					}
					$useFromStructure = array_merge($useFromStructure, $ratingLineKeys);
					if ($unknownUsageTypes = array_diff($ratingUsageTypes, $usagetTypes)) {
						throw new Exception('Unknown usage type(s) in rating: ' . implode(',', $unknownUsageTypes));
					}
					if ($usageTypesMissingRating = array_diff($usagetTypes, $ratingUsageTypes)) {
						throw new Exception('Missing rating rules for usage type(s): ' . implode(',', $usageTypesMissingRating));
					}
				}
			}
//			if ($uniqueFields != array_unique($uniqueFields)) {
//				throw new Exception('Cannot use same field for different configurations');
//			}
			$billrunFields = array('type', 'usaget', 'file', 'connection_type', 'urt');
			$customFields = array_merge($customFields, array_map(function($field) {
				return 'uf.' . $field;
			}, $customFields));
			$calculatedFields = array_merge($calculatedFields, array_map(function($field) {
				return 'cf.' . $field;
			}, $calculatedFields));
			$additionalFields = array('computed');
			if ($diff = array_diff($useFromStructure, array_merge($customFields, $billrunFields, $additionalFields, $calculatedFields))) {
				throw new Exception('Unknown source field(s) ' . implode(',', array_unique($diff)));
			}
		}
		return true;
	}

	protected function validateParserConfiguration($parserSettings) {
		if (empty($parserSettings['type'])) {
			throw new Exception('No parser type selected');
		}
		$allowedParsers = array('separator', 'fixed', 'json', 'ggsn', 'tap3', 'nsn');
		if (!in_array($parserSettings['type'], $allowedParsers)) {
			throw new Exception('Parser must be one of: ' . implode(',', $allowedParsers));
		}
		if (empty($parserSettings['structure']) || !is_array($parserSettings['structure'])) {
			throw new Exception('No file structure supplied');
		}
		if (array_column($parserSettings['structure'], 'name') != array_unique(array_column($parserSettings['structure'], 'name'))) {
			 throw new Exception('Duplicate field names found');
		}
		if ($parserSettings['type'] == 'json') {
			$customKeys =  $this->getCustomKeys($parserSettings['structure']);
		} else if ($parserSettings['type'] == 'separator') {
			$customKeys =  $this->getCustomKeys($parserSettings['structure']);
			if (empty($parserSettings['separator'])) {
				throw new Exception('Missing CSV separator');
			}
			if (!(is_scalar($parserSettings['separator']) && !is_bool($parserSettings['separator']))) {
				throw new Exception('Illegal seprator ' . $parserSettings['separator']);
			}
		} else {
			$customKeys =  $this->getCustomKeys($parserSettings['structure']);
			$customLengths = array_column($parserSettings['structure'], 'width');
			if ($customLengths != array_filter($customLengths, function($length) {
					return Billrun_Util::IsIntegerValue($length);
				})) {
				throw new Exception('Duplicate field names found');
			}
		}
		$parserSettings['custom_keys'] = $customKeys;
		if ($customKeys != array_unique($customKeys)) {
			throw new Exception('Duplicate field names found');
		}
		if ($customKeys != array_filter($customKeys, array('Billrun_Util', 'isValidCustomLineKey'))) {
			throw new Exception('Illegal field names');
		}
		foreach (array('H', 'D', 'T') as $rowKey) {
			if (empty($parserSettings['line_types'][$rowKey])) {
				$parserSettings['line_types'][$rowKey] = $rowKey == 'D' ? '//' : '/^none$/';
			} else if (!Billrun_Util::isValidRegex($parserSettings['line_types'][$rowKey])) {
				throw new Exception('Invalid regex ' . $parserSettings['line_types'][$rowKey]);
			}
		}
		return $parserSettings;
	}

	protected function validateProcessorConfiguration($processorSettings) {
		if (empty($processorSettings['type'])) {
			$processorSettings['type'] = 'Usage';
		}
		if (!in_array($processorSettings['type'], array('Usage', 'Realtime'))) {
			throw new Exception('Invalid processor type');
		}
		if (isset($processorSettings['date_format'])) {
			if (isset($processorSettings['time_field']) && !isset($processorSettings['time_format'])) {
				throw new Exception('Missing processor time format (in case date format is set, and timedate are in separated fields)');
			}
			else if (empty($processorSettings['time_field']) && !empty($processorSettings['time_format'])) {
				throw new Exception('Please select time field');
			}
			// TODO validate date format
		}
		if (!isset($processorSettings['date_field'])) {
			throw new Exception('Missing processor date field');
		}
		if (!(isset($processorSettings['usaget_mapping']) || isset($processorSettings['default_usaget']))) {
			throw new Exception('Missing processor usage type mapping rules');
		}
		if (!(isset($processorSettings['usaget_mapping']) || isset($processorSettings['default_volume_src']))) {
			throw new Exception('Missing processor volume field');
		}
		if (isset($processorSettings['usaget_mapping'])) {
			if (!$processorSettings['usaget_mapping'] || !is_array($processorSettings['usaget_mapping'])) {
				throw new Exception('Missing mandatory processor configuration');
			}
			$processorSettings['usaget_mapping'] = array_values($processorSettings['usaget_mapping']);
			foreach ($processorSettings['usaget_mapping'] as $index => $mapping) {
				if (isset($mapping['src_field']) && !isset($mapping['pattern']) || empty($mapping['usaget']) 
					|| empty($mapping['volume_type']) || empty($mapping['volume_src'])) {
					throw new Exception('Illegal usage type mapping at index ' . $index);
				}
			}
		}
		if (!isset($processorSettings['orphan_files_time'])) {
			$processorSettings['orphan_files_time'] = '6 hours';
		}
		return $processorSettings;
	}

	protected function validateCustomerIdentificationConfiguration($usagetCustomerIdentificationSettings) {
		if (!is_array($usagetCustomerIdentificationSettings) || !$usagetCustomerIdentificationSettings) {
			throw new Exception('Illegal customer identification settings');
		}
		foreach ($usagetCustomerIdentificationSettings as $usaget => $customerIdentificationSettings) {
			$customerIdentificationSettings = array_values($customerIdentificationSettings);
			foreach ($customerIdentificationSettings as $index => $settings) {
				if (!isset($settings['src_key'], $settings['target_key'])) {
					throw new Exception('Illegal customer identification settings at index ' . $index);
				}
				if (array_key_exists('conditions', $settings) && (!is_array($settings['conditions']) || !$settings['conditions'] || !($settings['conditions'] == array_filter($settings['conditions'], function ($condition) {
						return isset($condition['field'], $condition['regex']) && Billrun_Util::isValidRegex($condition['regex']);
					})))) {
					throw new Exception('Illegal customer identification conditions field at index ' . $index);
				}
				if (isset($settings['clear_regex']) && !Billrun_Util::isValidRegex($settings['clear_regex'])) {
					throw new Exception('Invalid customer identification clear regex at index ' . $index);
				}
			}
		}
		return $usagetCustomerIdentificationSettings;
	}

	protected function validateRateCalculatorsConfiguration($rateCalculatorsSettings, &$config) {
		if (!is_array($rateCalculatorsSettings)) {
			throw new Exception('Rate calculators settings is not an array');
		}
		$longestPrefixParams = array();
		foreach ($rateCalculatorsSettings as $category => $usagetRates) {
			foreach ($usagetRates as $usaget => $rates) {
				foreach ($rates['priorities'] as $rateRules) {
					foreach ($rateRules['filters'] as $rule) {
						if (!isset($rule['type'], $rule['rate_key'], $rule['line_key'])) {
							throw new Exception('Illegal rating rules for usaget ' . $usaget);
						}
						if (!in_array($rule['type'], $this->ratingAlgorithms)) {
							throw new Exception('Illegal rating algorithm for usaget ' . $usaget);
						}
						if ($rule['type'] === 'longestPrefix') {
							$longestPrefixParams[] = $rule['rate_key'];
						}
						foreach (Billrun_Util::getIn($rule, ['computed', 'line_keys'], array()) as $lineKey) {
							if (!empty($lineKey['regex']) && !Billrun_Util::isValidRegex($lineKey['regex'])) {
								throw new Exception('Illegal regex ' . $lineKey['regex']);
							}
						}
					}
				}
			}
		}
		$this->validateLongestPrefixRateConfiguration($config, $longestPrefixParams);
		return $rateCalculatorsSettings;
	}
	
	protected function validateLongestPrefixRateConfiguration(&$config, $longestPrefixParams) {
		foreach ($config['rates']['fields'] as &$field) {
			if (in_array($field['field_name'], $longestPrefixParams)) {
				$field['multiple'] = true;
			}
		}
	}

	protected function validateReceiverConfiguration($receiverSettings) {
		if (!is_array($receiverSettings)) {
			throw new Exception('Receiver settings is not an array');
		}
		if (!array_key_exists('connections', $receiverSettings) || !is_array($receiverSettings['connections']) || !$receiverSettings['connections']) {
			throw new Exception('Receiver \'connections\' does not exist or is empty');
		}
		
		if (isset($receiverSettings['limit'])) {
			if (!Billrun_Util::IsIntegerValue($receiverSettings['limit']) || $receiverSettings['limit'] < 1) {
				throw new Exception('Illegal receiver limit value ' . $receiverSettings['limit']);
			}
			$receiverSettings['limit'] = intval($receiverSettings['limit']);
		} else {
			$receiverSettings['limit'] = 3;
		}
		if (in_array($receiverSettings['type'], array('ftp', 'ssh'))) {
			foreach ($receiverSettings['connections'] as $index => $connection) {
				if (!isset($connection['name'], $connection['host'], $connection['user'], $connection['remote_directory'], $connection['passive'], $connection['delete_received']) || (!isset($connection['password']) && !isset($connection['key']))) {
					throw new Exception('Missing receiver\'s connection field at index ' . $index);
				}
				if (!Billrun_Util::isValidIPOrHost($connection['host'])) {
					throw new Exception($connection['host'] . ' is not a valid host');
				}
				$connection['passive'] = $connection['passive'] ? 1 : 0;
				$connection['delete_received'] = $connection['delete_received'] ? 1 : 0;
			}
		}
		return $receiverSettings;
	}

	protected function isLegalFileSettingsKeys($keys) {
		$hole = FALSE;
		foreach ($this->fileClassesOrder as $class) {
			if (!in_array($class, $keys)) {
				$hole = TRUE;
			} else if ($hole) {
				return FALSE;
			}
		}
		return TRUE;
	}
	
	protected function validateRealtimeConfiguration($realtimeSettings) {
		if (!is_array($realtimeSettings)) {
			throw new Exception('Realtime settings is not an array');
		}
		
		if (isset($realtimeSettings['postpay_charge']) && $realtimeSettings['postpay_charge']) {
			return $realtimeSettings;
		}
		
		$mandatoryFields = Billrun_Factory::config()->getConfigValue('configuration.realtime.mandatory_fields', array());
		$missingFields = array();
		foreach ($mandatoryFields as $mandatoryField) {
			if (!isset($realtimeSettings[$mandatoryField])) {
				$missingFields[] = $mandatoryField;
			}
		}
		if (!empty($missingFields)) {
			throw new Exception('Realtime settings missing mandatory fields: ' . implode(', ', $missingFields));
		}
		
		return $realtimeSettings;
	}
	
	protected function validateResponseConfiguration($responseSettings) {
		if (!is_array($responseSettings)) {
			throw new Exception('Response settings is not an array');
		}
		
		if (!isset($responseSettings['encode']) || !in_array($responseSettings['encode'], array('json'))) {
			throw new Exception('Invalid response encode type');
		}

		if (!isset($responseSettings['fields'])) {
			throw new Exception('Missing response fields');
		}
		
		foreach ($responseSettings['fields'] as $responseField) {
			if (empty($responseField['response_field_name']) || empty($responseField['row_field_name'])) {
				throw new Exception('Invalid response fields structure');
			}
		}
		
		return $responseSettings;
	}

	public function save($items) {
		$data = $this->getConfig();
		$saveData = array_merge($data, $items);
		$this->setConfig($saveData);
	}
        
	protected function isReservedFileTypeName($name) {
		$lowCaseName = strtolower($name);
		return in_array($lowCaseName, $this->reservedFileTypeName);
	}
        
	protected function isLegalFileTypeName($name) {
		return preg_match($this->fileTypesRegex, $name);
	}
	
	protected function getModelsWithTaxation() {
		return array('plans', 'services', 'rates');
	}
	
	protected function getModelName($model) {
		if ($model === 'rates') {
			return 'Products';
		}
		
		return ucfirst($model);
	}
	
	protected function getTaxationFields() {
		$ret = array(
			'taxation.service_code' => array('title' => 'Taxation service code' ,'mandatory' => true),
			'taxation.product_code' => array('title' => 'Taxation product code' ,'mandatory' => true),
			'taxation.safe_harbor_override_pct' => array('title' => 'Safe Horbor override string' ,'mandatory' => false),
		);
		return $ret;
	}
		
	protected function updateTaxationSettings(&$config, $data) {
		$mandatory = ($data['tax_type'] === 'CSI');
		$modelsWithTaxation = $this->getModelsWithTaxation();
		$mandatoryTaxationFields = $this->getTaxationFields();
		foreach ($modelsWithTaxation as $model) {
		   foreach ($mandatoryTaxationFields as $field => $fieldData) {
			   $this->setModelField($config, $model, $field, $fieldData['title'], $mandatory && $fieldData['mandatory'], $mandatory );
		   }
		}
	}
	
	protected function setModelField(&$config, $model, $fieldName, $title, $mandatory = true, $display = true) {
		foreach ($config[$model]['fields'] as &$field) {
			if ($field['field_name'] === $fieldName) {
				$field['title'] = $title;
				$field['display'] = $display;
				$field['editable'] = $display;
				$field['mandatory'] = $mandatory;
				return;
			}
		}
		
		$config[$model]['fields'][] = array(
			'field_name' => $fieldName,
			'title' => $title,
			'display' => $mandatory,
			'editable' => $mandatory,
			'mandatory' => $mandatory,
		);
	}
	
	/**
	 * Get warnings of configuration if exists
	 * 
	 * @param type $category
	 * @param type $data
	 */
	public function getWarnings($category, $data) {
		$warnings = array();
		
		if (Billrun_Util::isAssoc($data)) {
			$data = array($data);
		}
		
		foreach ($data as $config) {
			if (isset($config['taxation']) && $config['taxation']['tax_type'] === 'CSI') {
				$modelsWithTaxation = $this->getModelsWithTaxation();
				$mandatoryTaxationFields = array_keys(array_filter($this->getTaxationFields(),function($a){return $a['mandatory'];}));
				foreach ($modelsWithTaxation as $model) {
					if ($this->hasEntitiesWithoutMandatoryFields($model, $mandatoryTaxationFields )) {
						$warnings[] = 'There are valid entities of type "' . $this->getModelName($model) . '" without mandatory fields: ' . implode(', ', $mandatoryTaxationFields);
					}
				}
			}
		}
		
		return $warnings;
	}
	
	protected function hasEntitiesWithoutMandatoryFields($model, $mandatoryFields) {
		if (empty($mandatoryFields)) {
			return false;
		}
		$query = Billrun_Utils_Mongo::getDateBoundQuery();
		foreach ($mandatoryFields as $mandatoryField) {
			$query['$or'][] = array($mandatoryField => '');
			$query['$or'][] = array($mandatoryField => array('$exists' => false));
		}
		return !Billrun_Factory::db()->getCollection($model)->query($query)->cursor()->current()->isEmpty();
	}
	
	protected function getCustomKeys($parserStructure) {
		return array_column(array_filter($parserStructure, function($field) {
				return isset($field['checked']) && $field['checked'] === true;
			}),'name');
	}
	
	/**
	 * Get final unify configuration 
	 * 
	 * @param array $config - current configuration
	 * @param array $unifyConfig - unify configuration received
	 * @return array
	 */
	protected function getUnifyConfig($config, $unifyConfig) {
		if (empty($unifyConfig) && !empty($config['realtime']) && empty($config['realtime']['postpay_charge'])) { // prepaid request
			$unifyConfig = $this->getPrepaidUnifyConfig();
		}
		
		return $unifyConfig;
	}
	
	/**
	 * Get's unify configuration for prepaid input processors (taken from global unify configuration)
	 * 
	 * @return array
	 */
	protected function getPrepaidUnifyConfig() {
		return Billrun_Factory::config()->getConfigValue('unify', []);
	}

	/**
	 * Function to check if the system in multi day cycle mode
	 * @return default invoicing day, if multi day cycle mode, else returns false
	 */
	public function isMultiDayCycleMode() {
		return Billrun_Factory::config()->isMultiDayCycle();
	}

	protected function getSecurePaymentGateway($paymentGatewaySetting){
		$fakePassword = Billrun_Factory::config()->getConfigValue('billrun.fake_password', 'password');
		$securePaymentGateway = $paymentGatewaySetting; 
		$name  = Billrun_Util::getIn($paymentGatewaySetting, 'name');
		if(!Billrun_Util::getIn($paymentGatewaySetting, 'custom', false)){
			$paymentGateway = Billrun_Factory::paymentGateway($name);
			if(is_null($paymentGateway)){
				throw new Exception('Unsupported payment gateway ' . $name);
			}
			$secretFields = $paymentGateway->getSecretFields(); 
			foreach ($secretFields as $secretField){
				if(isset($securePaymentGateway['params'][$secretField])){
					$securePaymentGateway['params'][$secretField] = $fakePassword;
				}
			}
		}
		return $securePaymentGateway;
	}

	protected function getSecurePaymentGateways($paymentGateways){
		$securePaymentGateways = [];
		foreach ($paymentGateways as $paymentGateway){
			$securePaymentGateways[] = $this->getSecurePaymentGateway($paymentGateway);
		}
		return $securePaymentGateways;
	}
}
