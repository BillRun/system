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
	
	/**
	 * @var array
	 */
	protected $exists_rates = array();
	
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
	
	/**
	 * Allow to import rates revisiona in CREATE import type
	 */
//	protected function getImportParams($entity) {
//		if($this->isExistingEntity($entity)) {
//			$this->setImportOperation('permanentchange');
//		}
//		return parent::getImportParams($entity);
//	}
	
//	protected function isExistingEntity($entity) {
//		$key = $entity['key'];
//		if (in_array($key, $this->exists_rates)) {
//			return true;
//		} else {
//			$query = array('key' => $key);
//			if(Billrun_Factory::db()->ratesCollection()->query($query)->count() > 0) {
//				$this->exists_rates[] = $key;
//				return true;
//			}
//		}
//	}
}
