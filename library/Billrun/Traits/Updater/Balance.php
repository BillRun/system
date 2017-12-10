<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Trait to updating the balance externally
 *
 */
trait Billrun_Traits_Updater_Balance {

	/**
	 * Get the balance updater input.
	 * @return array - Input array for the balance updater.
	 */
	protected function getUpdaterInput($data) {
		$updaterInput['method'] = 'update';
		$updaterInput['sid'] = $data['sid'];

		// Set the recurring flag for the balance update.
		$updaterInput['recurring'] = $data['recurring'];

		// Build the query
		$updaterInputQuery = $this->getInputQuery($data);
		$updaterInputUpdate = $this->getUpdateQuery($data);

		$updaterInput['query'] = json_encode($updaterInputQuery, JSON_FORCE_OBJECT);
		$updaterInput['upsert'] = json_encode($updaterInputUpdate, JSON_FORCE_OBJECT);

		return $updaterInput;
	}

	abstract protected function getInputQuery($data);

	protected function getUpdateQuery($data) {
		$updaterInputUpdate['from'] = $data['from'];
		$updaterInputUpdate['to'] = $data['to'];
		$updaterInputUpdate['operation'] = $data['operation'];

		return $updaterInputUpdate;
	}

	/**
	 * Update a balance according to a auto renew record.
	 * @return boolean
	 */
	protected function updateBalance($data) {
		$updaterInput = $this->getUpdaterInput($data);
		$updater = new Billrun_ActionManagers_Balances_Update();

		// Anonymous object
		$jsonObject = new Billrun_AnObj($updaterInput);
		if (!$updater->parse($jsonObject)) {
			// TODO: What do I do here?
			return false;
		}
		if (!$updater->execute()) {
			// TODO: What do I do here?
			return false;
		}

		return true;
	}

}
