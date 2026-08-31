<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi services model for services entity
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Services extends Models_Entity {
	
	protected $errorCode = 999999;

	protected function init($params) {
		parent::init($params);
		$this->validatePrice();
		$this->validateTierLimitCycles();
		$this->validateRecurrence();
		if (in_array($this->action, array('create', 'update', 'closeandnew', 'permanentchange'))) {
			$this->validateProductsGroups();
		}
	}

	public function applyCacheChange($new = null, $old = null) {

		$old = $old ?? (!is_null($this->before) ? $this->before->getRawData() : null);
		$new = $new ?? (!is_null($this->after) ? $this->after->getRawData() : null);
		$res = Billrun_Service::applyEntityCacheChange($new, $old);
	}
	
	/**
	 * Verify in case of limit service sycles Recurring Charges cycles has the same sum of cycles.
	 */
	protected function validateTierLimitCycles() {
		$limitCycles = Billrun_Util::getIn($this->update, 'limit_cycles', false);
		if(!$limitCycles){
			return true;
		}
		if(!is_numeric($limitCycles) || $limitCycles <= 0){
			throw new Billrun_Exceptions_Api($this->errorCode, array(), '"No. of Cycles" is not valid');
		}
		$priceIntervals = Billrun_Util::getIn($this->update, 'price', []);
		$totalCycles = 0;
		foreach ($priceIntervals as $price) {
			$totalCycles = max($price['to'], $totalCycles);
		}
		if($limitCycles !== $totalCycles){
			throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Service summary cycles of "Recurring Charges" not match "No. of Cycles"');
		}
		
		return true;
	}

	/**
	 * Verify services has all price parameters required.
	 */
	protected function validatePrice() {
		$priceIntervals = Billrun_Util::getIn($this->update, 'price', []);
		foreach ($priceIntervals as $price) {
			if (!isset($price['from']) || $price['from'] === '' || 
				!isset($price['to']) || $price['to'] === '') {
				throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Service missing cycles parameters');
			}
			if (!isset($price['price']) || $price['price'] === '') {
				throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Service missing price parameter');
			}
		}
		
		return true;
	}
	
	/**
	 * Verify services has all price parameters required.
	 */
	protected function validateRecurrence() {
            $update_parameters = Billrun_Util::getIn($this->config, [$this->action, 'update_parameters'], []);
            $recurrence_field = array_reduce($update_parameters, function ($acc, $field) {
                return $field['name'] == 'recurrence' ? $field : $acc;
            }, null);
            if (!is_null($recurrence_field)) {
							$periodicity = Billrun_Util::getIn($this->update, 'recurrence.periodicity', null);
							if (empty($periodicity)) {
								$frequency = Billrun_Util::getIn($this->update, 'recurrence.frequency', null);
								if (empty($frequency) ) {
									throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Missing Billing Frequency - Type parameter');
								}
								$start = Billrun_Util::getIn($this->update, 'recurrence.start', null);
								if (empty($start)) {
									throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Missing Billing Frequency - Start parameter');
								}
							}
            }
            return true;
	}

	/**
	 * Validate the structure of the service's included products groups
	 * (include.products_groups). Each entry references a products group by key
	 * and is either usage based (value + unit, the group must have a property
	 * type) or monetary based (cost).
	 *
	 * A usage based value is stored in the unit it was entered in - not in the
	 * property type base unit - so a consumer must convert it by the entry unit.
	 *
	 * @throws Billrun_Exceptions_Api
	 */
	protected function validateProductsGroups() {
		if (!isset($this->update['include']) || !array_key_exists('products_groups', $this->update['include'])) {
			return;
		}
		$entries = $this->update['include']['products_groups'];
		if (!is_array($entries)) {
			throw new Billrun_Exceptions_Api($this->errorCode, array(), 'include.products_groups must be a list of products groups');
		}
		$entries = array_values($entries);
		$this->update['include']['products_groups'] = $entries;
		$usedKeys = array();
		foreach ($entries as $entry) {
			if (!is_array($entry) || empty($entry['key']) || !is_string($entry['key'])) {
				throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Each included products group must have a key');
			}
			$key = $entry['key'];
			if (in_array($key, $usedKeys)) {
				throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Products group "' . $key . '" cannot be included more than once');
			}
			$usedKeys[] = $key;
			$productsGroup = $this->getReferencedProductsGroup($key);
			if ($productsGroup->isEmpty()) {
				throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Unknown products group: ' . $key);
			}
			$this->validateProductsGroupQuota($entry, $productsGroup);
			foreach (array('account_shared', 'account_pool', 'quantity_affected') as $flag) {
				if (array_key_exists($flag, $entry) && !is_bool($entry[$flag])) {
					throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Products group "' . $key . '": ' . $flag . ' must be boolean');
				}
			}
		}
		$this->validateProductsGroupsQuotaTypeUnchanged($entries);
	}

	/**
	 * Validate the quota of a single included products group: usage based
	 * entries hold value + unit and require a group with a property type,
	 * monetary based entries hold cost only.
	 *
	 * @param array $entry the include.products_groups entry
	 * @param Mongodloid_Entity $productsGroup the referenced products group
	 * @throws Billrun_Exceptions_Api
	 */
	protected function validateProductsGroupQuota($entry, $productsGroup) {
		$key = $entry['key'];
		$hasValue = array_key_exists('value', $entry);
		$hasCost = array_key_exists('cost', $entry);
		if ($hasValue === $hasCost) {
			throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Products group "' . $key . '" must have either a value (usage based) or a cost (monetary based)');
		}
		if ($hasCost) {
			if (!is_numeric($entry['cost']) || $entry['cost'] <= 0) {
				throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Products group "' . $key . '": cost must be a positive number');
			}
			if (array_key_exists('unit', $entry)) {
				throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Products group "' . $key . '": unit is not allowed for a monetary based quota');
			}
			return;
		}
		$value = $entry['value'];
		if ($value !== Billrun_Service::UNLIMITED_VALUE && (!is_numeric($value) || $value <= 0)) {
			throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Products group "' . $key . '": value must be a positive number or ' . Billrun_Service::UNLIMITED_VALUE);
		}
		$propertyType = isset($productsGroup['property_type']) ? $productsGroup['property_type'] : null;
		if (is_null($propertyType) || $propertyType === '') {
			throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Products group "' . $key . '" has no property type and can only be included as monetary based');
		}
		if (empty($entry['unit']) || !is_string($entry['unit'])) {
			throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Products group "' . $key . '": unit is mandatory for a usage based quota');
		}
		$units = array_column(Billrun_Utils_Units::getUnitsOfMeasureByPropertyType($propertyType), 'name');
		if (!in_array($entry['unit'], $units)) {
			throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Products group "' . $key . '": unknown unit "' . $entry['unit'] . '" for property type "' . $propertyType . '"');
		}
	}

	/**
	 * After a service was saved, the quota of an included products group cannot
	 * be changed from usage based to monetary based or vice versa.
	 *
	 * @param array $entries the new include.products_groups entries
	 * @throws Billrun_Exceptions_Api
	 */
	protected function validateProductsGroupsQuotaTypeUnchanged($entries) {
		if (is_null($this->before) || $this->before->isEmpty()) {
			return;
		}
		$beforeEntries = Billrun_Util::getIn($this->before->getRawData(), 'include.products_groups', array());
		if (!is_array($beforeEntries)) {
			return;
		}
		foreach ($entries as $entry) {
			foreach ($beforeEntries as $beforeEntry) {
				if (!is_array($beforeEntry) || !isset($beforeEntry['key']) || $beforeEntry['key'] !== $entry['key']) {
					continue;
				}
				if (array_key_exists('cost', $entry) !== array_key_exists('cost', $beforeEntry)) {
					throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Products group "' . $entry['key'] . '": the quota cannot be changed from usage based to monetary based or vice versa');
				}
			}
		}
	}

	/**
	 * Get a products group by key, with revisions overlapping the service revision.
	 *
	 * @param string $key the products group key
	 * @return Mongodloid_Entity
	 */
	protected function getReferencedProductsGroup($key) {
		$from = isset($this->update['from']) ? $this->update['from'] : (isset($this->before['from']) ? $this->before['from'] : new Mongodloid_Date());
		$to = isset($this->update['to']) ? $this->update['to'] : (isset($this->before['to']) ? $this->before['to'] : new Mongodloid_Date(strtotime(self::UNLIMITED_DATE)));
		$query = array(
			'key' => $key,
			'from' => array('$lt' => $to),
			'to' => array('$gt' => $from),
		);
		return Billrun_Factory::db()->productgroupsCollection()->query($query)->cursor()->limit(1)->current();
	}

	/**
	 * method to add entity custom fields values from request
	 *
	 * @param array $fields array of field settings
	 */
	protected function getCustomFields($update = array()) {
		$customFields = parent::getCustomFields();
		$plays = Billrun_Util::getIn($update, 'play', Billrun_Util::getIn($this->before, 'play', []));
		return Billrun_Utils_Plays::filterCustomFields($customFields, $plays);
	}

}
