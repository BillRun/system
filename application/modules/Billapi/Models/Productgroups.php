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
			$this->validateRatesPropertyType();
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
	 * Every product of the group must be priced for the group's property type.
	 * The products picker offers only products of the chosen activity types, but that
	 * filter is bypassed by clearing the activity types, picking products of another
	 * property type and choosing the original activity types again before saving.
	 * Only revisions of the product overlapping the group revision are considered, so a
	 * product with no revision priced for the property type during the group revision -
	 * including a product that does not exist at all - is reported too.
	 *
	 * @throws Billrun_Exceptions_Api
	 */
	protected function validateRatesPropertyType() {
		if (!array_key_exists('rates', $this->update)) {
			return;
		}
		$rates = array_values(array_unique((array) $this->update['rates']));
		$propertyType = $this->getUpdatedPropertyType();
		if (empty($rates) || empty($propertyType)) {
			// a group without a property type is not limited to products of one property type
			return;
		}
		$usagetConditions = array();
		foreach (Billrun_Utils_Units::getUsagetsByPropertyType($propertyType) as $usaget) {
			$usagetConditions[] = array('rates.' . $usaget => array('$exists' => true));
		}
		if (empty($usagetConditions)) {
			// the property type has no activity types to price a product by
			return;
		}
		list($from, $to) = $this->getRevisionBounds();
		// the products priced for at least one activity type of the group property type,
		// in a revision overlapping the group revision
		$pipeline = array(
			array('$match' => array(
				'key' => array('$in' => $rates),
				'from' => array('$lt' => $to),
				'to' => array('$gt' => $from),
				'$or' => $usagetConditions,
			)),
			array('$group' => array('_id' => '$key')),
		);
		// setRawReturn so the cursor yields plain arrays that array_column can read
		$results = Billrun_Factory::db()->ratesCollection()->aggregate($pipeline)->setRawReturn(true);
		$priced = array_column(iterator_to_array($results, false), '_id');
		$mismatched = array_values(array_diff($rates, $priced));
		if (!empty($mismatched)) {
			$plural = count($mismatched) > 1;
			throw new Billrun_Exceptions_Api(3, array(), ($plural ? 'Products "' : 'Product "') . implode('", "', $mismatched) . ($plural ? '" are' : '" is') . ' not of the products group property type "' . $propertyType . '"');
		}
	}

	/**
	 * Abort the save when another products group with the same property type
	 * contains exactly the same products.
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
		list($from, $to) = $this->getRevisionBounds();
		$query = array(
			'key' => array('$ne' => $key),
			'property_type' => $this->getUpdatedPropertyType(),
			'rates' => array('$all' => $rates, '$size' => count($rates)),
			'from' => array('$lt' => $to),
			'to' => array('$gt' => $from),
		);
		$existing = $this->collection->query($query)->cursor()->limit(1)->current();
		if (!$existing->isEmpty()) {
			$propertyType = $this->getUpdatedPropertyType();
			$propertyTypeLabel = empty($propertyType) ? 'without a property type' : 'of property type "' . $propertyType . '"';
			throw new Billrun_Exceptions_Api(2, array(), 'Products group "' . $existing['key'] . '" ' . $propertyTypeLabel . ' already contains exactly the same products');
		}
	}

	/**
	 * The property type the group will have after the current change.
	 * null for a group without activity types - in a query it matches
	 * documents without the property_type field.
	 *
	 * @return string|null
	 */
	protected function getUpdatedPropertyType() {
		if (array_key_exists('property_type', $this->update)) {
			return $this->update['property_type'];
		}
		if (array_key_exists('usage_types', $this->update)) {
			return null;
		}
		return isset($this->before['property_type']) ? $this->before['property_type'] : null;
	}

	/**
	 * The revision boundaries the current change will have, used to compare
	 * the group only against revisions of other entities overlapping it.
	 *
	 * @return array [$from, $to]
	 */
	protected function getRevisionBounds() {
		$from = isset($this->update['from']) ? $this->update['from'] : (isset($this->before['from']) ? $this->before['from'] : new Mongodloid_Date());
		$to = isset($this->update['to']) ? $this->update['to'] : (isset($this->before['to']) ? $this->before['to'] : new Mongodloid_Date(strtotime(self::UNLIMITED_DATE)));
		return array($from, $to);
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
