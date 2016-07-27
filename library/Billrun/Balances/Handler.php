<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing cron controller class
 * Used for is alive checks
 * 
 * @package  Controller
 * @since    4.0
 */
class Billrun_Balances_Handler {

	use Billrun_Traits_Updater_Balance {
		getUpdateQuery as traitGetUpdateQuery;
		getUpdaterInput as traitGetUpdaterInput;
	}

	/**
	 * Close all balances.
	 */
	public function closeBalances() {
		$balancesQuery['to'] = array(
			'$gte' => new MongoDate(strtotime("yesterday midnight")),
			'$lte' => new MongoDate(strtotime("midnight")),
		);
		$balancesColl = Billrun_Factory::db()->balancesCollection();
		$balancesCursor = $balancesColl->query($balancesQuery)->cursor();
		foreach ($balancesCursor as $balance) {
			try {
				$value = $this->getValue($balance);
				if ($value > 0 && !Billrun_Util::isEqual($value, 0, Billrun_Calculator_CustomerPricing::getPrecision())) {
					continue;
				}

				$data = $this->getUpdateData($balance);
				$data['value'] = $value * -1;
				$this->updateBalance($data);
			} catch (Exception $ex) {
				Billrun_Factory::log("Cron exception! " . $ex->getCode() . ": " . $ex->getMessage(), Zend_Log::ERR);
			}
		}
	}

	/**
	 * Get the balance updater input.
	 * @return array - Input array for the balance updater.
	 */
	protected function getUpdaterInput($data) {
		$updaterInput = $this->traitGetUpdaterInput($data);
		$updaterInput['additional'] = $data['additional'];
		return $updaterInput;
	}

	protected function getInputQuery($data) {
		$result = array();
		$result['_id'] = $data['_id'];
		return $result;
	}

	protected function getUpdateQuery($data) {
		$updaterInputUpdate = $this->traitGetUpdateQuery($data);
		$updaterInputUpdate['value'] = $data['value'];
		$updaterInputUpdate['expiration_date'] = $data['to'];
		return $updaterInputUpdate;
	}

	/**
	 * Get the update data data to be used in the query.
	 * @param Mongodloid_Entity $balance
	 * @return array
	 */
	protected function getUpdateData($balance) {
		$data = $balance->getRawData();
		$data['operation'] = "inc";
		$data['recurring'] = isset($balance['recurring']);
		// TODO: Put actual values
		$data['additional'] = json_encode(array("balance_source" => "CRON", "balance_type" => "BAL_EXP"));
		return $data;
	}

	protected function getValue($balance) {
		$chargingBy = $balance['charging_by'];
		$chargingByUsaget = $balance['charging_by_usaget'];
		$value = 0;
		if ($chargingBy === $chargingByUsaget) {
			$value = isset($balance['balance']['total_cost']) ? $balance['balance']['total_cost'] : $balance['balance']['cost'];
		} else {
			$value = $balance['balance']['totals'][$chargingByUsaget][$chargingBy];
		}

		return $value;
	}
	
	public function sendBalanceExpirationdateNotifications() {
		$plansNotifications = $this->getAllPlansWithExpirationDateNotification();
		foreach ($plansNotifications as $planNotification) {
			$subscribersInPlan = $this->getSubscribersInPlan($planNotification['plan_name']);
			foreach ($subscribersInPlan as $subscriber) {
				$balances = $this->getBalancesToNotify($subscriber->get('sid'), $planNotification['notification']);
				if (!$balances) {
					continue;
				}
				
				$this->notifyForBalances($subscriber, $balances);
			}
		}
	}
	
	/**
	 * Notify on all balances per a subscriber
	 * @param array $subscriber - Current subscriber to notify
	 * @param array $balances - Array of balances record to try and notify on
	 */
	protected function notifyForBalances($subscriber, $balances) {
		foreach ($balances as $balance) {
			// Do not notify on an empty balance
			if(Billrun_Balances_Util::getBalanceValue($balance) == 0) {
				continue;
			}
			Billrun_Factory::dispatcher()->trigger('balanceExpirationDate', array($balance, $subscriber->getRawData()));
		}
	}
	
	protected function getBalancesToNotify($subscriberId, $notification) {
		$balancesCollection = Billrun_Factory::db()->balancesCollection();
		$query = array(
			'sid' => $subscriberId,
			'to' => array(
				'$gte' => new MongoDate(strtotime('+' . $notification['value'] . ' days midnight')),
				'$lte' => new MongoDate(strtotime('+' . ($notification['value'] + 1) . ' days midnight')),
			),
			'pp_includes_external_id' => array('$in' => $notification['pp_includes']),
		);
		$balances = $balancesCollection->query($query)->cursor();
		if ($balances->count() == 0) {
			return false;
		}
		return $balances;
	}

	protected function getSubscribersInPlan($planName) {
		$subscribersCollection = Billrun_Factory::db()->subscribersCollection();
		$query = Billrun_Util::getDateBoundQuery();
		$query['plan'] = $planName;
		$subscribers = $subscribersCollection->query($query)->cursor();
		if ($subscribers->count() == 0) {
			return false;
		}
		return $subscribers;
	}

	protected function getAllPlansWithExpirationDateNotification() {
		$match = Billrun_Util::getDateBoundQuery();
		$match["notifications_threshold.expiration_date"] = array('$exists' => 1);
		$unwind = '$notifications_threshold.expiration_date';
		$plansCollection = Billrun_Factory::db()->plansCollection();
		$plans = $plansCollection->aggregate(array('$match' => $match), array('$unwind' => $unwind));
		$plansNotifications = array_map(function($doc) {
			return array('plan_name' => $doc['name'], 'notification' => $doc['notifications_threshold']['expiration_date']);
		}, iterator_to_array($plans));
		return $plansNotifications;
	}

}
