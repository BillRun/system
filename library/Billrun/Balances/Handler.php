<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
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

}
