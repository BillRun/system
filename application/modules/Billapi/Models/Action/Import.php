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
		return $this->runQuery();
	}

	protected function runQuery() {
		$output = array();
		foreach ($this->update as $key => $entity) {
			$errors = $entity['__ERRORS__'];
			$csv_rows = $entity['__CSVROW__'];
			
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

}
