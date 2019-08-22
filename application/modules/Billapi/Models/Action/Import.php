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
	
	protected function run() {
		$importType = $this->getImportType();
		switch ($importType) {
			case 'manual_mapping':
				return $this->runQuery();
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

	protected function runQuery() {
		$output = array();
		foreach ($this->update as $key => $entity) {
			$errors = isset($entity['__ERRORS__']) ? $entity['__ERRORS__'] : [];
			$csv_rows = isset($entity['__CSVROW__']) ? $entity['__CSVROW__'] : [];
			
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
					foreach ($csv_rows as $row_index) {
						$output[$row_index] = $result;
					}
				} else {
					$output[$key] = $result;
				}
			}
		}
		return $output;
	}

	protected function importEntity($entity) {
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
		return new Models_Entity($params);
	}
	
	protected function runPredefinedMappingQuery() {
		$ret = [];
		
		foreach ($_FILES as $file) {
			$data = $this->getFileData($file);
			$mapping = $this->getMapping();

			foreach ($data as $key => $row) {
				$ret["{$file['name'][0]}-{$key}"] = $this->importPredefinedMappingEntity($row, $mapping);
			}
		}
		
		return $ret;
	}
	
	protected function getFileData($file) {
		$data = array_map('str_getcsv', file($file['tmp_name'][0]));
		$header = $this->getHeader($data, $params);
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
		return Billrun_Factory::config()->getConfigValue("billapi.{$collection}.import.mapper", []);
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

	protected function translateValue($row, $params) {
		$rowFieldName = Billrun_Util::getIn($params, 'title', $params['field_name']);
		$type = Billrun_Util::getIn($params, 'type', 'string');
		$value = Billrun_Util::getIn($row, $rowFieldName, Billrun_Util::getIn($params, 'default', ''));
		
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
		return array_map(function() {
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
		$value = str_replace(', ', ',', $value);
		return explode(",", $value);
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
		$result = Billrun_Factory::chain()->trigger($customFunc, []);
		$importedEntities = Billrun_Util::getIn($result, 'imported_entities', []);
		$errors = Billrun_Util::getIn($result, 'errors', []);
		if (empty($errors)) {
			return $importedEntities;
		}

		$errorMessage = implode(', ', $errors);
		throw new Exception($errorMessage);
	}

}
