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
	 * Get the record plan according to the input query.
	 * @param type $query
	 * @param type $plansCollection
	 * @return type
	 */
	protected function getPlanRecord($query, $plansCollection) {
		$cardsColl = Billrun_Factory::db()->cardsCollection();
		// Get the record.
		// TODO: Not checking TO or FROM!!!!!!!!!
		$cardRecord = $cardsColl->query($query)->cursor()->current();
		
		// Build the plan query from the card plan field.
		$planQuery = array('charging_plan_name' => $cardRecord['charging_plan']);
		
		return parent::getPlanRecord($planQuery, $plansCollection);
	}
}
