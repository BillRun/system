<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * BillRun Collection class
 *
 * @package  Billrun
 * @since    5.8
 */
class Billrun_Collection extends Billrun_Base {

	public function collect($aids = array(), $collectDir = '') {
		$account = Billrun_Factory::account();
		$markedAsInCollection = $account->getInCollection($aids);
		$reallyInCollection = Billrun_Bill::getContractorsInCollection($aids);
		if ($collectDir == 'enter_collection' || empty($collectDir)) {
			$updateCollectionStateChanged['in_collection'] = array_diff_key($reallyInCollection, $markedAsInCollection);
		}
		if ($collectDir == 'exit_collection' || empty($collectDir)) {
			$updateCollectionStateChanged['out_of_collection'] = array_diff_key($markedAsInCollection, $reallyInCollection);
		}
		$result = $account->updateCrmInCollection($updateCollectionStateChanged);
		return $result;
	}

	public static function getInstance() {
		return new Billrun_Collection();
	}

}
