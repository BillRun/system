<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Handler for externally closing all balances.
 *
 * @author Tom Feigin
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
			'$gt' => new MongoDate(strtotime("yesterday midnight")),
			'$lte' => new MongoDate(strtotime("midnight")),
		);
		$balancesColl = Billrun_Factory::db()->balancesCollection();
		$balancesCursor = $balancesColl->query($balancesQuery)->cursor();
		foreach ($balancesCursor as $balance) {
			$value = $this->getValue($balance);
			if($value >= 0) {
				continue;
			}
			
			$data = $this->getUpdateData($balance);
			$data['value'] = $value *-1;
			$this->updateBalance($data);
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
		return array('pp_includes_external_id' => $data['pp_includes_external_id']);
	}
	
	protected function getUpdateQuery($data) {
		$updaterInputUpdate = $this->traitGetUpdateQuery($data); 
		$updaterInputUpdate['value'] = $data['value'];
		$updaterInputUpdate['expiration_date'] = $data['to'];
		return $updaterInputUpdate;
	}
	
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
		$chargingByUsegt = $balance['charging_by_usaget'];
		$value = 0;
		if($chargingBy === $chargingByUsegt) {
			$value = isset($balance['balance']['total_cost']) ? $balance['balance']['total_cost'] : $balance['balance']['cost'];
		} else {
			$value = $balance['balance']['totals'][$chargingByUsegt][$chargingBy];
		}
		
		return $value;
	}
}
