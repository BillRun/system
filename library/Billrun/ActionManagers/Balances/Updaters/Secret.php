<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Holds the logic for updating balances using the secret card number.
 *
 * @author tom
 */
class Billrun_ActionManagers_Balances_Updaters_Secret extends Billrun_ActionManagers_Balances_Updaters_ChargingPlan {

	/**
	 * Update the balances, based on the plans table
	 * @param type $query - Query to find row to update.
	 * @param type $recordToSet - Values to update.
	 * @param type $subscriberId - Id for the subscriber to update.
	 * @return The updated record, false if failed.
	 */
	public function update($query, $recordToSet, $subscriberId) {
		$cardsColl = Billrun_Factory::db()->cardsCollection();
		// Get the record.
		$dateQuery = array('to' => array('$gt', new MongoDate()));
		$finalQuery = array_merge($dateQuery, $query);
		$cardRecord = $cardsColl->query($finalQuery)->cursor()->current();
		
		// Build the plan query from the card plan field.
		$planQuery = array('charging_plan_name' => $cardRecord['charging_plan']);
		
		return parent::update($planQuery, $recordToSet, $subscriberId);
	}
}
