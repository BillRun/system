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
 * @since    5.3
 */
class Models_Action_Import_Subscribers extends Models_Action {

	protected function runQuery() {
		$output = array();
		foreach ($this->update as $key => $item) {
			if(empty($item['__LINKER__'])) {
				$output[$key] = 'Mandatory update parameter linker missing';
			} else {
				$accountQuery = array(
					"type" => "account",
					$item['__LINKER__']['field'] => $item['__LINKER__']['value'],
				);			
				$account = Billrun_Factory::db()->subscribersCollection()->query($accountQuery)->cursor()->current();
				if(!$account || $account->isEmpty()) {
					$output[$key] = "Account with for subscriber not exist";
				} else {
					unset($item['__LINKER__']);
					$item['aid'] = $account->get('aid');			
					$params = array(
						'collection' => 'subscribers',
						'request' => array(
							'action' => 'create',
							'update' => json_encode($item),
						),
					);
					try {
						$entityModel = new Models_Subscribers($params);
						$entityModel->create();
						$output[$key] = true;
					} catch (Exception $exc) {
						$output[$key] = $exc->getMessage();
					}
				}
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
