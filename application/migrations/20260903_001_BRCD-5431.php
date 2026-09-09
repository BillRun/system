<?php

/**
 * BRCD-5431 - Products groups: migrate the services include groups to products groups.
 *
 * Every non counter group of a service becomes a products group with the same key, so the
 * existing balances, that hold the group name, keep matching. Counter groups are left in
 * the include groups as they are.
 *
 * A service is migrated as a whole, and a group that cannot be migrated aborts the
 * migration. The services migrated up to that point keep working, and as the task is not
 * recorded the migration runs again, skipping them, once the data is fixed.
 */
return new class extends Billrun_Migration_Base {

	public function getTaskCode() {
		return 'BRCD-5431';
	}

	public function run() {
		$migrated = 0;
		$cursor = $this->db->servicesCollection()->query(['include.groups' => ['$exists' => true]])->cursor()->setReadPreference('RP_PRIMARY');
		foreach ($cursor as $service) {
			$migrated += $this->migrateService($service->getRawData()) ? 1 : 0;
		}
		$this->log('BRCD-5431: migrated ' . $migrated . ' services revisions');
	}

	/**
	 * Create the products groups of a service revision include groups, and rewrite its
	 * include: the non counter groups become products groups entries, the counter groups
	 * stay as they are.
	 *
	 * @param array $service the service revision
	 * @return boolean whether the service was migrated
	 */
	protected function migrateService($service) {
		$entries = [];
		$counters = [];
		foreach ($service['include']['groups'] as $groupName => $group) {
			if (!empty($group['counter_only'])) { // counter groups stay in the include groups
				$counters[$groupName] = $group;
				continue;
			}
			if (isset($group['limits'])) {
				$this->abort($service['name'], $groupName, 'the group has limits, which a products group cannot hold');
			}
			$this->createProductsGroup($service['name'], $groupName, $group);
			$entries[] = $this->buildEntry($groupName, $group);
		}
		if (empty($entries)) { // nothing but counters on this service
			return false;
		}
		$update = ['include.products_groups' => $entries];
		$query = ['_id' => $service['_id']];
		if (empty($counters)) {
			$this->db->servicesCollection()->update($query, ['$set' => $update, '$unset' => ['include.groups' => 1]]);
			return true;
		}
		$update['include.groups'] = $counters;
		$this->db->servicesCollection()->update($query, ['$set' => $update]);
		return true;
	}

	/**
	 * Create the products group of a legacy group, unless a products group of that key is
	 * already there - then it must hold the same products and usage types.
	 *
	 * @param string $serviceName
	 * @param string $groupName the group name, which is the products group key
	 * @param array $group the legacy include group
	 */
	protected function createProductsGroup($serviceName, $groupName, $group) {
		$rates = array_values($group['rates']);
		$usageTypes = array_keys(isset($group['usage_types']) ? $group['usage_types'] : []);
		sort($rates); // the products are compared as a set
		sort($usageTypes);
		$existing = $this->db->productgroupsCollection()->query(['key' => $groupName])->cursor()->setReadPreference('RP_PRIMARY')->limit(1)->current();
		if (!$existing->isEmpty()) {
			$existingRates = (array) $existing->get('rates');
			$existingUsageTypes = (array) $existing->get('usage_types');
			sort($existingRates);
			sort($existingUsageTypes);
			if ($existingRates != $rates || $existingUsageTypes != $usageTypes) {
				$this->abort($serviceName, $groupName, 'a products group of the same key already holds different products or usage types');
			}
			return; // the products group is already there and holds the same products
		}
		$document = [
			'key' => $groupName,
			'description' => $groupName,
			'rates' => $rates,
			'usage_types' => $usageTypes,
			'from' => new Mongodloid_Date(0),
			'to' => new Mongodloid_Date(strtotime(Models_Entity::UNLIMITED_DATE)),
		];
		if (!empty($usageTypes)) {
			$document['property_type'] = Billrun_Utils_Units::getPropertyTypeByUsaget(reset($usageTypes));
		}
		$this->db->productgroupsCollection()->insert($document);
	}

	/**
	 * Build the include products groups entry of a legacy group.
	 *
	 * @param string $groupName
	 * @param array $group the legacy include group
	 * @return array the include.products_groups entry
	 */
	protected function buildEntry($groupName, $group) {
		$entry = [
			'key' => $groupName,
			'account_shared' => !empty($group['account_shared']),
			'account_pool' => !empty($group['account_pool']),
			'quantity_affected' => !empty($group['quantity_affected']),
		];
		if (isset($group['cost'])) { // monetary based
			$entry['cost'] = $group['cost'];
			return $entry;
		}
		// the value of both structures is in the base unit, the unit is the one it is displayed in
		$entry['unit'] = $group['usage_types'][key($group['usage_types'])]['unit'];
		$entry['value'] = $group['value'];
		return $entry;
	}

	/**
	 * Alert and stop the migration, so that the task is not recorded as done and it runs
	 * again once the data is fixed.
	 *
	 * @throws Exception
	 */
	protected function abort($serviceName, $groupName, $reason) {
		$message = 'BRCD-5431: cannot migrate group "' . $groupName . '" of service "' . $serviceName . '": ' . $reason;
		Billrun_Factory::log($message, Zend_Log::ALERT);
		$this->log($message);
		throw new Exception($message);
	}

};
