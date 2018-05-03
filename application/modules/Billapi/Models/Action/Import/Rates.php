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
class Models_Action_Import_Rates extends Models_Action_Import {

	protected function importEntity($entity) {
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
				'update' => json_encode($entity),
			),
		);
		try {
			$entityModel = new Models_Entity($params);
			$entityModel->create();
			return true;
		} catch (Exception $exc) {
			return $exc->getMessage();
		}
	}
}
