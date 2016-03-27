<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 4; see LICENSE.txt
 */

/**
 * Handle the cards cron proccess
 *
 * @author Tom Feigin
 * @todo Maybe move this to a Cron folder
 */
class Billrun_Cards_Handler {
	
	/**
	 * Get the query to return expired records.
	 * @return array - query
	 */
	protected function getExpiredQuery() {
		$query = array();
		
		// TODO: The 'active' value should not be hardcoded.
		$query['status'] = array('$nin' => array("Expired", "Stolen"));
		$query['to'] = array('$lte' => new MongoDate());
			
		return $query;
	}
	
	/**
	 * Get the cards update query.
	 * @return array - Query.
	 */
	protected function getUpdateQuery() {
		$query = array();
		$query['$set'] = array('status' => 'Expired');
		return $query;
	}
	
	public function cardsExpiration() {				
		$query = $this->getExpiredQuery();
		$update = $this->getUpdateQuery();
		$options = array(
			'upsert' => false,
			'new' => false,
			'multiple' =>1,
		);
				
		$collection = Billrun_Factory::db()->cardsCollection();
		return $autoRenewCursor = $collection->update($query, $update, $options);
	}
}
