<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi verification trait model for 
 *
 * @package  Billapi
 * @since    5.3
 */
trait Models_Verification {

	/**
	 * Returns the translated (validated) request
	 * @param array $query the query parameter
	 * @param array $data the update parameter
	 * 
	 * @return array
	 * 
	 * @throws Billrun_Exceptions_Api
	 * @throws Billrun_Exceptions_InvalidFields
	 */
	protected function validateRequest($query, $data, $action, $config, $error, $forceNotEmpty = true, $requestOptions = array(), $duplicateCheck = array(), $customFields = array()) {
		$options = array();
		if (isset($config['unique_query_parameters']) && $config['unique_query_parameters']) {
			$updatedQueryParams = $this->verifyQueryParams($query, $duplicateCheck, $customFields);
			if (!empty($updatedQueryParams)) {
				if (!isset($query['_id']) && !isset($query['effective_date'])) {
					throw new Billrun_Exceptions_Api($error, array(), 'When updating entity not by id need to transfer effective_date field');
				} else if (isset($query['effective_date'])) {
					$updatedQueryParams[] = array(
						 'name' => 'effective_date',
						 'type' => 'datetimeInRange'
					);
				}
				$config['query_parameters'] = $updatedQueryParams;
			}
		}
		if (!empty($config['update_parameters_regex'])) {
			foreach ($config['update_parameters_regex'] as $regexDefinitions) {
				$updateFieldRegex = $regexDefinitions['regex'];
				$updateFieldType = $regexDefinitions['type'];
				foreach ($data as $fieldName => $value) {
					if (preg_match($updateFieldRegex, $fieldName)) {
						$config['update_parameters'][] = array(
							'name' => $fieldName,
							'type' => $updateFieldType,
						);
					}
				}
			}
		}
		foreach (array('query_parameters' => $query, 'update_parameters' => $data) as $type => $params) {
			$options['fields'] = array();
			$translated[$type] = array();
			$configParams = Billrun_Util::getFieldVal($config[$type], array());
			foreach ($configParams as $param) {
				$name = $param['name'];
				$isGenerated = (isset($param['generated']) && $param['generated']);
				if (!isset($params[$name]) || $params[$name] === "") {
					if (isset($param['mandatory']) && $param['mandatory'] && !$isGenerated) {
						throw new Billrun_Exceptions_Api($error, array(), 'Mandatory ' . str_replace('_parameters', '', $type) . ' parameter ' . $name . ' missing');
					}
					if (!$isGenerated) {
						continue;
					}
				}
				if (isset($param['regex'])){
					if(preg_match($param['regex'] , $params[$name]) !== 1) {
						throw new Billrun_Exceptions_Api($error, array(), ucfirst(str_replace('_parameters', '', $type)) . ' parameter ' . $name . ' contain illegal characters');
					}
				}
				$options['fields'][] = array(
					'name' => $name,
					'type' => $param['type'],
					'preConversions' => isset($param['pre_conversion']) ? $param['pre_conversion'] : [],
					'postConversions' => isset($param['post_conversion']) ? $param['post_conversion'] : [],
					'options' => [],
				);
				if (!$isGenerated) {
					if ($param['type'] == 'array' && (is_string($params[$name]) || is_numeric($params[$name]))) {
						$val = array(
							'$in' => array($params[$name])
						);
					} else {
						$val = $params[$name];
					}
					$knownParams[$type][$name] = $val;
				} else { // on generate field the value will be automatically generate
					$knownParams[$type][$name] = null;
				}
				unset($params[$name]);
			}
			if ($options['fields']) {
				$options['or_fields'] = ($type === 'query_parameters' && isset($requestOptions['or_fields']) ? $requestOptions['or_fields'] : array());
				$translatorModel = new Api_TranslatorModel($options);
				$ret = $translatorModel->translate($knownParams[$type]);
				$translated[$type] = $ret['data'];
//				Billrun_Factory::log("Translated result: " . print_r($ret, 1));
				if (!$ret['success']) {
					throw new Billrun_Exceptions_InvalidFields($translated[$type]);
				}
			}
			if (!Billrun_Util::getFieldVal($config[$action]['restrict_query'], 1) && $params) {
				$translated[$type] = array_merge($translated[$type], $params);
			}
		}
		if(isset($requestOptions['push_fields']) || isset($requestOptions['pull_fields'])){
			$ops = array('push_fields' => 'push', 'pull_fields' => 'pull');
			$fields_settings = $config['fields'];
			$fields_names = array_column($fields_settings, 'field_name');
			foreach ($ops as $op => $op_name) {
				if (isset($requestOptions[$op])) {
					foreach ($requestOptions[$op] as $op_field) {
						$index = array_search($op_field['field_name'], $fields_names);
						if($index !== FALSE && isset($fields_settings[$index]['multiple']) && $fields_settings[$index]['multiple'] && isset($fields_settings[$index]['editable']) && $fields_settings[$index]['editable'] !== FALSE) {
							$translated['query_options'][$op][] = $op_field;
						} else {
							throw new Billrun_Exceptions_Api($error, array(), "Can not run {$op_name} on non multiple field '{$op_field['field_name']}'");

						}
					}
				}
				
			}
		}
		if ($forceNotEmpty) {
			$this->verifyTranslated($translated);
		}
		if (isset($translated['update_parameters']['effective_date'])) {
			unset($translated['update_parameters']['effective_date']);
		}
		if (!empty($config['unique_query_parameters']) && !isset($translated['query_parameters']['_id']) && !empty($this->collection)) {
			$translated['query_parameters'] = $this->transformQueryById($translated['query_parameters']);
		}
		if(!isset($translated['query_options'])) {
			$translated['query_options'] = array();
		}
		
		return array($translated['query_parameters'], $translated['update_parameters'], $translated['query_options']);
	}

	/**
	 * Verify the translated query & update
	 * @param array $translated
	 */
	protected function verifyTranslated($translated) {
		if (!$translated['query_parameters'] && !$translated['update_parameters']) {
			throw new Billrun_Exceptions_Api($this->errorBase + 2, array(), 'No query/update was found or entity not supported');
		}
	}
	
	/**
	 * Verify that legal query params are transfered for update and adjust the query if needed.
	 * @param array $queryParams
	 */
	protected function verifyQueryParams($queryParams, $duplicateCheck, $customFields) {
		if (isset($queryParams['_id'])) {
			return $this->buildIdQuery();
		} else if (empty(array_diff_key(array_flip($duplicateCheck), $queryParams))) {
			return $this->buildDuplicateCheckQuery($duplicateCheck);
		} else if (!empty($customFields)) {
			return $this->buildUniqueFieldsQuery($customFields);
		}
		
		return false;
	}
	
	protected function buildIdQuery() {
		return array(
			array(
				'name' => '_id',
				'type' => 'dbid',
				'mandatory' => '1'
		));
	}
	
	protected function buildDuplicateCheckQuery($duplicateCheck) {
		foreach ($duplicateCheck as $type => $fieldName) {
			$query[] = array(
				'name' => $fieldName,
				'type' => $type,
			);
		}
		
		return $query;
	}
	
	protected function buildUniqueFieldsQuery($customFields) {
		$uniqueFields = array_filter($customFields, function($field) {
			return isset($field['unique']) && $field['unique'] && !(isset($field['system']) && $field['system']);
		});
		foreach ($uniqueFields as $field) {
			$query[] = array(
				'name' => $field['field_name'],
				'type' => isset($field['type']) ? $field['type'] : 'string',
			);
		}
		return $query;
	}
	
	protected function transformQueryById($query) {
		$entity = $this->collection->query($query)->cursor();
		if ($entity->count() != 1) {
			throw new Exception('No unique record was found');
		}
		$data = $entity->current()->getRawData();
		if (!isset($data['_id'])) {
			throw new Exception('Missing Id for entity');
		}
		return array('_id' => $data['_id']);
	}

}
