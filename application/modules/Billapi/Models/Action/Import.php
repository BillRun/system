<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi unique get operation
 * Retrieve list of entities while the key or name field is unique
 * This is accounts unique get
 *
 * @package  Billapi
 * @since    5.5
 */
class Models_Action_Import extends Models_Action {

	/**
	 * Import operation type
	 */
	protected $operation = 'create';

	public function execute() {
		if (!empty($this->request['operation'])) {
			$this->setImportOperation($this->request['operation']);
		}
		if (!empty($this->request['update'])) {
			$this->update = (array) json_decode($this->request['update'], true);
		}
		
		return $this->run();
	}
	
	/**
	 * Read file content to array
	 * @return type
	 */
	protected function getFileRows() {
		$delimiter = Billrun_Util::getIn($this->update, 'delimiter', ',');
		$files = $this->getFiles();
		$file = reset($files);
		$rows = array_map(function($row) use ($delimiter) {
			return str_getcsv($row, $delimiter);
		}, file($file));
		return $rows;
	}
	
	protected function run() {
		$importType = $this->getImportType();
		switch ($importType) {
			case 'manual_mapping':
				$max_imported_rows = Billrun_Factory::config()->getConfigValue("import.max_rows_to_import", 1000000);
				$rows = $this->getFileRows();
				if (count($rows) > $max_imported_rows) {
					throw new Exception("File can not contain more than {$max_imported_rows} rows");
				}
				$entities = $this->getFormatedRows($rows);
				return $this->runManualMappingQuery($entities);
			case 'predefined_mapping':
				return $this->runPredefinedMappingQuery();
			default:
				$funcName = 'import' . ucfirst($this->getCollectionName());
				return $this->runCustomQuery($funcName);
		}
	}
	
	protected function getImportType() {
		return Billrun_Util::getIn($this->update, 'importType', 'manual_mapping');
	}
	
	protected function getFormatedRows($file_rows) {
		$data = [];
		$mapper_prefix = '__csvindex__';
		$map = $this->update['map'];
		$linker = $this->update['linker'];
		$updater = $this->update['updater'];
		$import_fields = $this->update['import_fields'];
		$import_fields = array_column($import_fields, null, 'value');
		$default_values = $this->update['default_values'];
		$predefined_values = $this->update['predefined_values'];
		$multi_field_action = $this->update['multi_field_action'];
		
		// create importable rows with data
		foreach ($file_rows as $idx => $file_row) {
			 // Ignore first (headers) line
			if ($idx === 0) {
				continue;
			}
			
			Billrun_Factory::dispatcher()->trigger('beforeImportRowFormat', array(&$file_row, $this->operation, $this->request['collection'], $this->update));
			
			// Set the row number from file - will need in case of error to point to the row with problem
			$data[$idx]['__CSVROW__'] = $idx + 1;
			
			
			// Set linker for entities with parent<->child relationship
			if (!empty($linker['field']) && !empty($linker['value'])) {
				$csv_index = intval(Billrun_Util::removePrefix($linker['value'], $mapper_prefix));
				$data[$idx]['__LINKER__'] = [
					'field' => $linker['field'],
					'value' => $file_row[$csv_index]
				];
			}
			
			// Set updater for entities with parent<->child relationship
			if (!empty($updater['field']) && !empty($updater['value'])) {
				$csv_index = intval(Billrun_Util::removePrefix($updater['value'], $mapper_prefix));
				$data[$idx]['__UPDATER__'] = [
					'field' => $updater['field'],
					'value' => $file_row[$csv_index]
				];
			}
			
			// Set updater action for multifields
			if (!empty($multi_field_action)) {
				$data[$idx]['__MULTI_FIELD_ACTION__'] = $multi_field_action;
			}
			
			// Set Data -> default values
			if (in_array($this->operation, ['create']) && !empty($default_values)) {
				foreach ($default_values as $field_name => $default_value) {
					$data[$idx][$field_name] = $default_value;
				}
			}
			
			// Set Data -> from Mapper
			foreach ($map as $field_name => $mapper_value) {
				// fixed value
				$column_value = $mapper_value;
				// value from csv
				if (Billrun_Util::startsWith($mapper_value, $mapper_prefix)) {
					$csv_index = intval(Billrun_Util::removePrefix($mapper_value, $mapper_prefix));
					$column_value = $file_row[$csv_index];
				} 
				$field_type = Billrun_Util::getIn($import_fields, [$field_name, 'type'], 'string');
				// convert string to array for field of type Range
				if ($field_type === 'ranges') {
					$ranges = explode(',', $column_value);
					$column_value = array_reduce($ranges, function($acc, $range) {
						$acc[] = [
							'from' => $range[0],
							'to' => $range[1],
						];
						return $acc;
					}, []);
				}
				Billrun_Util::setIn($data[$idx], $field_name, $column_value);
			}
			
			// Set Data -> predefined values
			if (in_array($this->operation, ['create']) && !empty($predefined_values)) {
				foreach ($predefined_values as $field_name => $predefined_value) {
					$data[$idx][$field_name] = $predefined_value;
				}
			}
			Billrun_Factory::dispatcher()->trigger('afterImportRowFormat', array(&$data[$idx], $this->operation, $this->request['collection'], $this->update));
		}

		return $data;
	}

	protected function runManualMappingQuery($entities) {
		$output = array();
		
		$import_fields = $this->update['import_fields'];
		$multi_value_fields = array_column(array_filter($import_fields, function($field) {
			return $field['multiple'] === true;
		}), 'value');
		
		foreach ($entities as $key => $entity) {
			$errors = isset($entity['__ERRORS__']) ? $entity['__ERRORS__'] : [];
			$csv_rows = isset($entity['__CSVROW__']) ? $entity['__CSVROW__'] : [];
			
			foreach ($entity as $field_name => $value) {
				// build multivalues field value
				if (in_array($field_name, $multi_value_fields)) {
					if(!is_array($value)) {
						$values = array_map('trim', array_filter(explode(",", $value), 'strlen'));
						Billrun_Util::setIn($entity, [$field_name], $values);
					} else {
						Billrun_Util::setIn($entity, [$field_name], $value);
					}
				} else if (is_array($value)) {
					foreach ($value as $field_key => $field_val) {
						$full_field_name = "{$field_name}.{$field_key}";
						if (in_array($full_field_name, $multi_value_fields)) {
							$values = array_map('trim', array_filter(explode(",", $field_val), 'strlen'));
							Billrun_Util::setIn($entity, [$field_name, $field_key], $values);
						}
					}
				}
			}
			
			if($this->request['operation'] !== $this->getImportOperation()) {
				$this->setImportOperation($this->request['operation']);
			}
			// If error from FE exist, skip import and return error details for csv_rows
			if(!empty($errors)) {
				// set errro = false for csv_rows without error
				if(!empty($csv_rows)) {
					foreach ($csv_rows as $csv_row) {
						if(!array_key_exists($csv_row,$errors)) {
							$errors[$csv_row] = false;
						}
					}
				}
				// Create Error responce array
				foreach ($errors as $row_index => $row_errors) {
					if(is_array($row_errors)) {
						foreach ($row_errors as $error) {
							$output[$row_index][] = $error;
						}
					} else {
						$output[$row_index] = $row_errors;
					}
				}
			} else {
				$result = $this->importEntity($entity);
				// set import status for all csv_rows that build this entity
				if(!empty($csv_rows)) {
					if (is_array($csv_rows)) {
						foreach ($csv_rows as $row_index) {
							$output[$row_index] = $result;
						}
					} else {
						$output[$csv_rows] = $result;
					}
				} else {
					$output[$key] = $result;
				}
			}
		}
                Billrun_Factory::dispatcher()->trigger('afterRunManualMappingQuery', array(&$output, $this->request['collection'], $this->update));
		return $output;
	}

	protected function importEntity($entity) {
		Billrun_Factory::dispatcher()->trigger('beforeImportEntity', array(&$entity, $this->operation, $this->request['collection'], $this->update));
		try {
			$params = $this->getImportParams($entity);
			$entityModel = $this->getEntityModel($params);
			$action = strtolower($params['request']['action']);
			$entityModel->{$action}();
			return true;
		} catch (Exception $exc) {
			return $exc->getMessage();
		}
	}

	protected function setImportOperation($operation) {
		$operations = $this->settings['operation'];
		if ($operations && in_array($operation, $operations)) {
			$this->operation = $operation;
		} else {
			throw new Exception('Unsupported import operation');
		}
	}
	
	protected function getImportParams($entity) {
		$params = array(
			'collection' => $this->getCollectionName(),
			'request' => array(
				'action' => $this->getImportOperation(),
				'update' => $this->getEntityData($entity),
			)
		);
		if($this->getImportOperation() == 'permanentchange') {
			$query = array(
				'effective_date' => empty($entity['effective_date']) ? $entity['from'] : $entity['effective_date'],
				$entity['__UPDATER__']['field'] => $entity['__UPDATER__']['value']
			);
			$params['request']['query'] = json_encode($query);
		}
		return $params;
	}

	protected function getImportOperation() {
		return $this->operation;
	}

	protected function getEntityData($entity) {
		if (empty($entity['from']) && !empty($entity['effective_date'])) {
			$entity['from'] = $entity['effective_date'];
		}
		unset($entity['__UPDATER__']);
		unset($entity['__LINKER__']);
		unset($entity['__MULTI_FIELD_ACTION__']);
		unset($entity['__ERRORS__']);
		unset($entity['__CSVROW__']);
		return json_encode($entity);
	}

	protected function getEntityModel($params) {
		return Models_Entity::getInstance($params);
	}
	
	protected function getFiles() {
		$files = [];
		foreach ($_FILES['files']['name'] as $i => $fileName) {
			$files[$fileName] = $_FILES['files']['tmp_name'][$i];
		}
		
		return $files;
	}
	
	protected function runPredefinedMappingQuery() {
		$ret = [];
		$mapping = $this->getMapping();
		foreach ($this->getFiles() as $fileName => $filePath) {
			$data = $this->getFileData($filePath);
			foreach ($data as $key => $row) {
				$ret["{$fileName}-{$key}"] = $this->importPredefinedMappingEntity($row, $mapping);
			}
		}
		
		return $ret;
	}
	
	protected function getFileData($filePath) {
		$data = array_map('str_getcsv', file($filePath));
		$header = $this->getHeader($data);
		if (!empty($header)) {
			array_walk($data, function(&$row) use ($header) {
				$row = array_combine($header, $row);
			});
		}

		return $data;
	}
	
	protected function getHeader(&$data, $params = []) {
		$header = $data[0];
		array_shift($data); // remove column header
		return $header;
	}

	protected function getMapping() {
		$collection = $this->getCollectionName();
		$config = Billrun_Factory::config()->getConfigValue("{$collection}.fields", []);
		$fields = Billrun_Factory::config()->getConfigValue("billapi.{$collection}.import.mapper", []);
		$importable_fields = array_replace_recursive(
			array_column($config, null, 'field_name'),
			array_column($fields, null, 'field_name')
		);
		$importable_fields = array_filter($importable_fields, function($field) {
			return Billrun_Util::getIn($field, 'importable', true);
		});
		$mapper = array_reduce($importable_fields, function ($acc, $field) {
			$acc = array_merge($acc, $this->createRecursionMapperKey($field));
			return $acc;
		}, []);
		
		return $mapper;
	}
	
	protected function importPredefinedMappingEntity($row, $mapping) {
		try {
			$entityData = $this->getPredefinedMappingEntityData($row, $mapping);
			$action = $this->getImportOperation();
			$params = [
				'collection' => $this->getCollectionName(),
				'request' => array(
					'action' => $action,
					'query' => json_encode($this->getPredefinedMappingEntityQuery($entityData)),
					'update' => json_encode($entityData),
					'is_import' => true,
				),
			];
		
			$entityModel = $this->getEntityModel($params);
			$entityModel->{$action}();
			return true;
		} catch (Exception $ex) {
			return $ex->getMessage();
		}
	}
	
	protected function getPredefinedMappingEntityData($row, $mapping) {
		$ret = [];
		foreach ($mapping as $fieldParams) {
			$fieldName = $fieldParams['field_name'];
			$value = $this->translateValue($row, $fieldParams);
			if (!empty($value)) {
				Billrun_Util::setIn($ret, $fieldName, $value);
			}
		}
		
		return $ret;
	}
	
	protected function getPredefinedMappingEntityQuery($entityData) {
		if(!$this->getImportOperation() == 'permanentchange') {
			return [];
		}
		
		$uniqueFields = Billrun_Factory::config()->getConfigValue("billapi.{$this->getCollectionName()}.duplicate_check", []);
		$ret = [
			'effective_date' => date('Y-m-d H:i:s'),
		];
		foreach ($uniqueFields as $uniqueField) {
			$ret[$uniqueField] = Billrun_Util::getIn($entityData, $uniqueField, '');
		}
		
		return $ret;
	}
	
	protected function getExportMapper() {
		$collection = $this->getCollectionName();
		$fields = Billrun_Factory::config()->getConfigValue("{$collection}.fields", []);
		$config = Billrun_Factory::config()->getConfigValue("billapi.{$collection}.export.mapper", []);;
		$exportable_fields = array_replace_recursive(
			array_column($config, null, 'field_name'),
			array_column($fields, null, 'field_name')
		);
		$exportable_fields = array_filter($exportable_fields, function($field) {
			return Billrun_Util::getIn($field, 'exportable', true);
		});
		$mapper = array_reduce($exportable_fields, function ($acc, $field) {
			$acc = array_merge($acc, $this->createRecursionMapperKey($field));
			return $acc;
		}, []);
		return $mapper;
	}

	protected function translateValue($row, $params) {
		$export_config = $this->getExportMapper();
		$export_field = $export_config[$params['field_name']];
		$defaultColumnName = Billrun_Util::getIn($export_field, 'title', $export_field['field_name']);
		$rowFieldName = Billrun_Util::getIn($params, 'title', $defaultColumnName);
		$type = Billrun_Util::getIn($params, 'type', 'string');
		$isMultiple = Billrun_Util::getIn($params, 'multiple', false);
		$callback = Billrun_Util::getIn($params, 'callback', false);
		if (!empty($callback) && method_exists($this, $callback)) {
			$value = $this->{$callback}($row, $params);
		} else {
			$value = Billrun_Util::getIn($row, $rowFieldName, Billrun_Util::getIn($params, 'default', ''));
		}
		if ($isMultiple) {
			$type = 'array';
		}
		
		switch ($type) {
			case 'int':
				return $this->fromInt($value);
			case 'float':
				return $this->fromFloat($value);
			case 'date':
			case 'datetime':
				return $this->fromDate($value);
			case 'daterange': //TODO: fix
				$value = $this->fromDate($value);
				return $this->fromRanges($value);
			case 'range':
				return $this->fromRanges($value);
			case 'percentage':
				return $this->fromPercentage($value);
			case 'boolean':
				return $this->fromBoolean($value);
			case 'array':
				return $this->fromArray($value);
			case 'json':
				return $this->fromJson($value);
			default:
				return $value;
		}
	}
	
	protected function fromInt($value) {
		return intval($value);
	}
	
	protected function fromFloat($value) {
		return floatval($value);
	}
	
	protected function fromRanges($ranges) {
		return array_map(function($range) {
			return $this->fromRange($range);
		}, $this->fromArray($ranges));
	}

	protected function fromRange($range) {
		$range = str_replace(' - ', '-', $range);
		$range = explode('-', $range);
		return [
			'from' => $range[0],
			'to' => $range[1],
		];
	}

	protected function fromArray($value) {
		$values = explode(",", $value);
		$values = array_map('trim', $values); // clean
		$values = array_filter($values, 'strlen'); // remove empty values
		$values = array_values($values); // reset keys after filter
		return $values;
	}

	protected function fromDate($value) {
		return date('Y-m-d H:i:s', Billrun_Utils_Time::getTime($value));
	}

	protected function fromBoolean($value) {
		if (empty($value)) {
			return false;
		}
		return in_array($value, ['false', 'FALSE', '0', 'null', 'NULL', 'no']) ? false : true;
	}

	protected function fromJson($value) {	
		return json_decode($value, JSON_OBJECT_AS_ARRAY);
	}

	function fromPercentage($value) {
		$value = str_replace('%', '', $value);
		return ($value / 100);
	}

	protected function runCustomQuery($customFunc) {
		$result = Billrun_Factory::chain()->trigger($customFunc, [$this->getFiles()]);
		$errors = Billrun_Util::getIn($result, 'errors', []);
		if (!empty($errors)) {
			$errorMessage = implode(', ', $errors);
			throw new Exception($errorMessage);			
		}
		
		$details = [];
		$imported_entities = Billrun_Util::getIn($result, 'imported_entities', null);
		if (!is_null($imported_entities)) {
			$details['imported_entities'] = $imported_entities;
		}
		$general_errors = Billrun_Util::getIn($result, 'general_errors', null);
		if (!is_null($general_errors)) {
			$details['general_errors'] = $general_errors;
		}
		$general_warnings = Billrun_Util::getIn($result, 'general_warnings', null);
		if (!is_null($general_warnings)) {
			$details['general_warnings'] = $general_warnings;
		}
		$created = Billrun_Util::getIn($result, 'created', null);
		if (!is_null($created)) {
			$details['created'] = $created;
		}
		$updated = Billrun_Util::getIn($result, 'updated', null);
		if (!is_null($updated)) {
			$details['updated'] = $updated;
		}
		return $details;
	}
	
	protected function createRecursionMapperKey($config, $configs = []) {
		$max_array_count = 1; // TODO:: TEMP need to find a way how to calculate it from DB
		$path = $config['field_name'];
		$title = isset($config['title']) ? $config['title'] : false;
		$matches = [];
		preg_match('/{[0-9]+}/', $path, $matches);
		if (empty($matches)) {
			$configs[$path] = $config;
			return $configs;
		}
		for ($idx = 0; $idx < $max_array_count; $idx++) {
			$config['field_name'] = str_replace($matches[0], $idx, $path);
			if ($title !== false) {
				$config['title'] = str_replace($matches[0], $idx, $title);
			}
			$configs = $this->createRecursionMapperKey($config, $configs);
		}
		return $configs;
	}

}
