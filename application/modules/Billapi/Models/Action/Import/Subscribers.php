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
class Models_Action_Import_Subscribers extends Models_Action_Import {

	protected function getCollectionName() {
		return 'subscribers';
	}
	
	protected function getEntityModel($params) {
		return new Models_Subscribers($params);
	}
	
	protected function getEntityData($entity) {
		if(empty($entity['__LINKER__'])) {
			throw new Exception('Missing mandatory update parameter linker');
		}
		$accountQuery = array(
			"type" => "account",
			$entity['__LINKER__']['field'] => $entity['__LINKER__']['value'],
		);			
		$account = Billrun_Factory::db()->subscribersCollection()->query($accountQuery)->cursor()->current();
		if(!$account || $account->isEmpty()) {
			throw new Exception('Customer for subscriber does not exist');
		}
		$entity['aid'] = $account->get('aid');
		return parent::getEntityData($entity);
	}
}
