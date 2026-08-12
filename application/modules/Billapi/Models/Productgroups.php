<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2026 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi model for products groups entity
 *
 * @package  Billapi
 * @since    5.15
 */
class Models_Productgroups extends Models_Entity {

	protected function init($params) {
		parent::init($params);
		if (in_array($this->action, array('create', 'update', 'closeandnew', 'permanentchange'))) {
			$this->validateUsageTypes();
			$this->validatePropertyTypeUnchanged();
			$this->validateUniqueRates();
		}
	}

	/**
	 * Validate the group's activity (usage) types and align its property type:
	 * all activity types must share the property type of the first chosen one.
	 * A group without activity types has no property type.
	 *
	 * @throws Billrun_Exceptions_Api
	 */
	protected function validateUsageTypes() {
		if (!array_key_exists('usage_types', $this->update)) {
			if ($this->action === 'create') {
				$this->update['usage_types'] = array();
			}
			return;
		}
		$usageTypes = array_values(array_unique((array) $this->update['usage_types']));
		$this->update['usage_types'] = $usageTypes;
		$propertyType = '';
		foreach ($usageTypes as $usageType) {
			$usagePropertyType = Billrun_Utils_Units::getPropertyTypeByUsaget($usageType);
			if ($usagePropertyType === false) {
				throw new Billrun_Exceptions_Api(0, array(), "Unknown activity type: {$usageType}");
			}
			if ($propertyType === '') {
				$propertyType = $usagePropertyType;
				$this->update['property_type'] = $propertyType;
			} else if ($usagePropertyType !== $propertyType) {
				throw new Billrun_Exceptions_Api(0, array(), 'All activity types of a products group must share the same property type');
			}
		}
	}

	/**
	 * The property type of a products group cannot be changed after it was saved.
	 *
	 * @throws Billrun_Exceptions_Api
	 */
	protected function validatePropertyTypeUnchanged() {
		if (is_null($this->before) || $this->before->isEmpty()) {
			return;
		}
		if (!array_key_exists('usage_types', $this->update)) {
			return;
		}
		$oldPropertyType = isset($this->before['property_type']) ? $this->before['property_type'] : '';
		$newPropertyType = isset($this->update['property_type']) ? $this->update['property_type'] : '';
		if ($newPropertyType !== $oldPropertyType) {
			throw new Billrun_Exceptions_Api(1, array(), 'The property type of a products group cannot be changed after it was saved');
		}
	}

	/**
	 * Abort the save when another products group contains exactly the same products.
	 *
	 * @throws Billrun_Exceptions_Api
	 */
	protected function validateUniqueRates() {
		if (!array_key_exists('rates', $this->update)) {
			return;
		}
		$rates = array_values(array_unique((array) $this->update['rates']));
		$this->update['rates'] = $rates;
		if (empty($rates)) {
			return;
		}
		$key = isset($this->update['key']) ? $this->update['key'] : (isset($this->before['key']) ? $this->before['key'] : '');
		$from = isset($this->update['from']) ? $this->update['from'] : (isset($this->before['from']) ? $this->before['from'] : new Mongodloid_Date());
		$to = isset($this->update['to']) ? $this->update['to'] : (isset($this->before['to']) ? $this->before['to'] : new Mongodloid_Date(strtotime(self::UNLIMITED_DATE)));
		$query = array(
			'key' => array('$ne' => $key),
			'rates' => array('$all' => $rates, '$size' => count($rates)),
			'from' => array('$lt' => $to),
			'to' => array('$gt' => $from),
		);
		$existing = $this->collection->query($query)->cursor()->limit(1)->current();
		if (!$existing->isEmpty()) {
			throw new Billrun_Exceptions_Api(2, array(), 'Products group "' . $existing['key'] . '" already contains exactly the same products');
		}
	}

	/**
	 * Return the key field of the entity
	 *
	 * @return String
	 */
	protected function getKeyField() {
		return 'key';
	}

}
