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
	 * Import opperation type
	 * @var string create / update
	 */
	protected $operation = 'create';

	public function execute() {
		if (!empty($this->request['operation'])) {
			$this->operation = $this->request['operation'];
		}
		if (!empty($this->request['update'])) {
			$this->update = (array) json_decode($this->request['update'], true);
		}
		return $this->runQuery();
	}

	protected function runQuery() {
		$output = array();
		foreach ($this->update as $key => $entity) {
			$output[$key] = $this->importEntity($entity);
		}
		return $output;
	}

	public function importEntity($entity) {
		try {
			$params = $this->createEntityImportParams($entity);
			$entityModel = $this->getEntityModel($params);
			$entityModel->create();
			return true;
		} catch (Exception $exc) {
			return $exc->getMessage();
		}
	}

	protected function createEntityImportParams($entity) {
		return array(
			'collection' => $this->getCollectionName(),
			'request' => array(
				'action' => $this->getOperationName(),
				'update' => $this->getEntity($entity),
			)
		);
	}

	protected function getOperationName() {
		$operations = $this->settings['operation'];
		if ($operations && in_array($this->operation, $operations)) {
			return $this->operation;
		}
		throw new Exception('Unsupported import operation');
	}

	protected function getEntity($entity) {
		return json_encode($entity);
	}

	protected function getEntityModel($params) {
		return new Models_Entity($params);
	}

}
