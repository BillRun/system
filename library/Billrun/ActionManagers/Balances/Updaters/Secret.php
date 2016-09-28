<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Holds the logic for updating balances using the secret card number.
 *
 */
class Billrun_ActionManagers_Balances_Updaters_Secret extends Billrun_ActionManagers_Balances_Updaters_ChargingPlan {

	protected $type = 'Secret';

	/**
	 * Reference to the card being used.
	 * @var type Reference
	 */
	protected $cardRef;

	/**
	 * Get the card record according to the received query
	 * @param array $query - Received query to get the record by.
	 * @return boolean
	 */
	protected function getCardRecord($query) {
		if (isset($query['secret'])) {
			$query['secret'] = hash('sha512', $query['secret']);
		} else {
			$errorCode =  22;
			$this->reportError($errorCode, Zend_Log::ALERT);
			return false;
		}
		$dateQuery = Billrun_Utils_Mongo::getDateBoundQuery();
		$finalQuery = array_merge($dateQuery, $query);
		$finalQuery['status'] = array('$eq' => 'Active');
		$cardsColl = Billrun_Factory::db()->cardsCollection();
		return $cardsColl->query($finalQuery)->cursor()->current();
	}

	/**
	 * Update the balances, based on the plans table
	 * @param type $query - Query to find row to update.
	 * @param type $recordToSet - Values to update.
	 * @param type $subscriberId - Id for the subscriber to update.
	 * @return The updated record, false if failed.
	 */
	public function update($query, $recordToSet, $subscriberId) {
		// Get the record.
		$cardRecord = $this->getCardRecord($query);

		if ($cardRecord === false) {
			return false;
		}

		if ($cardRecord->isEmpty()) {
			$errorCode =  10;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		$this->setSourceForLineRecord($cardRecord);

		// Build the plan query from the card plan and service provider field.
		$planQuery = array(
			'charging_plan_name' => $cardRecord['charging_plan_name'],
			'service_provider' => $cardRecord['service_provider'],
		);

		$ret = parent::update($planQuery, $recordToSet, $subscriberId);

		if ($ret === FALSE) {
			return false;
		}

		// TODO: To the request of Pelephone, we always singal a card as used after
		// being loaded into a balance EVEN IF NON IS ACTUALLY LOADED INTO THE BALANCE!
		// To prevent the latter from happening, remove the 'true' in the following if 
		// statement. The $ret['updated'] is an indication for a change in the balance 
		// wallet, which means that the card was loaded, even if partially, and should be 
		// signaled as used.  If the $ret['updated'] is false, nothing was actually loaded
		// into the account.
		if (true || $ret['updated']) {
			$this->signalCardAsUsed($cardRecord, $subscriberId);
		}
		unset($ret['updated']);
		return $ret;
	}

	/**
	 * Signal a given card as used after it has been used to charge a balance.
	 * @param mongoEntity $cardRecord - Record to set as canceled in the mongo.
	 */
	protected function signalCardAsUsed($cardRecord, $subscriberId) {
		$query = array(
			'_id' => array(
				'$eq' => $cardRecord['_id']->getMongoID()
			), // next fields added because of the sharding (cluster env)
			'batch_number' => $cardRecord['batch_number'],
			'serial_number' => $cardRecord['serial_number'],
		);
		$update = array(
			'$set' => array(
				'status' => 'Used',
				'sid' => $subscriberId,
				'activation_datetime' => new MongoDate(),
			),
		);
		$options = array(
			'upsert' => false,
		);
		$cardsColl = Billrun_Factory::db()->cardsCollection();
		$cardsColl->findAndModify($query, $update, array(), $options, true);
	}

	/**
	 * Set the 'Source' value to put in the record of the lines collection.
	 * @return object The value to set.
	 */
	protected function setSourceForLineRecord($card) {
		$collection = Billrun_Factory::db()->cardsCollection();
		$this->cardRef = $collection->createRefByEntity($card);
	}

	/**
	 * Get the 'Source' value to put in the record of the lines collection.
	 * @return object The value to set.
	 */
	protected function getSourceForLineRecord($chargingPlanRecord) {
		return $this->cardRef;
	}

}
