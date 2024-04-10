<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Clear cached object that relay on external information
 *
 * @package  Action
 *
 * @since    2.6
 */
class ClearcacheAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;

	protected $queryFieldsToOp = [
		'clearCacheForSubscriber' => ['sid'],
		'clearCacheForAccount' => ['aid'],
	];

	public function execute() {
		$this->allowed();

		$query = $this->getRequest()->getRequest();
		$ret = false;

		foreach($this->queryFieldsToOp as   $op => $queryKeys) {
			//if all the required fields are found in the  query call to approprite function to clear the cache (subscriber or account)
			if(count($queryKeys) == count(array_intersect($queryKeys, array_keys($query)))) {
				$ret = $this->{$op}($query);
				break;
			}
		}
		return $this->setReponse($ret);
	}

	protected function clearCacheForSubscriber($query) {
		$subscriber = Billrun_Factory::subscriber();
		if($subscriber->getType() == 'external') {
			$id = $query[$subscriber->getCachingEntityIdKey()];

			return $subscriber->cleanExternalCache($id);
		}
		return FALSE;
	}


	protected function clearCacheForAccount($query) {
		$account = Billrun_Factory::account();
		if($account->getType() == 'external') {
			$id = $query[$account->getCachingEntityIdKey()];
			return $account->cleanExternalCache($id);
		}
		return FALSE;
	}

	protected function setReponse($retValue) {
		if($retValue) {
			$this->getController()->setOutput([
					[
						'status' => $retValue,
						'desc' => 'success',
						'input' => $this->getRequest()->getRequest(),
						'details' => [],
					]
				]);
		} else {
			$this->setError('missing fields sid/aid or incomplete data', $this->getRequest()->getRequest());
		}
		return $retValue;
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
	}
}
