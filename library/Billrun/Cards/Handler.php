<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 4; see LICENSE.txt
 */

/**
 * Handle the cards cron proccess
 *
 * @todo Maybe move this to a Cron folder
 */
class Billrun_Cards_Handler {

	/**
	 * Get the query to return expired records.
	 * @return array - query
	 */
	protected function getExpiredQuery() {
		$query = array(
			'status' => array('$in' => array("Active", "Idle")), // TODO: What should be in this array? It shouldn't be hard coded.
			'to' => array('$lt' => new Mongodloid_Date()),
		);
		
		return $query;
	}

	/**
	 * Get the cards update query.
	 * @return array - Query.
	 */
	protected function getUpdateQuery() {
		$query = array();
		$query['$set'] = array(
			'status' => 'Expired',
			'expired_on' => new Mongodloid_Date(),
		);
		return $query;
	}

	public function cardsExpiration() {
		$query = $this->getExpiredQuery();
		$update = $this->getUpdateQuery();
		$options = array(
			'upsert' => false,
			'new' => false,
			'multiple' => true,
		);

		$collection = Billrun_Factory::db()->cardsCollection();
		return $autoRenewCursor = $collection->update($query, $update, $options);
	}

}
