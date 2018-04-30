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
class Models_Action_Import_Rates extends Models_Action {

	protected function runQuery() {
		
		$output = array();
		foreach ($this->update as $key => $item) {
			/**
			 * On Create action check for:
			 * 1. Create Rate  - OK
			 * 2. Create rate revision
			 * 3. Update PLAN price
			 *  or on Update actin check for:
			 * 1. Update rate revision
			 * 2. Update Plan revision
			 * 
			 */
			$params = array(
				'collection' => $this->getCollectionName(),
				'request' => array(
					'action' => 'create',
					'update' => json_encode($item),
				),
			);
			try {
				$entityModel = new Models_Entity($params);
				$entityModel->create();
				$output[$key] = true;
			} catch (Exception $exc) {
				$output[$key] = $exc->getMessage();
			}
		}
		return $output;
	}

	public function execute() {
		if (!empty($this->request['update'])) {
			$this->update = (array) json_decode($this->request['update'], true);
		}
		return $this->runQuery();
	}

}
