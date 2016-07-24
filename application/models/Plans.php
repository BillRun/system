<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Plans model class to pull data from database for plan collection
 *
 * @package  Models
 * @subpackage Table
 * @since    0.5
 */
class PlansModel extends TabledateModel {

	public function __construct(array $params = array()) {
		$params['collection'] = Billrun_Factory::db()->plans;
		parent::__construct($params);
		$this->search_key = "name";
	}

	public function getTableColumns() {
		$columns = array(
			'name' => 'Plan',
			'service_provider' => 'Service Provider'
		);
		if ($this->type === 'charging') {
			$columns['desc'] = "Description";
		}
		$columns['from'] = 'From';
		$columns['to'] = 'Expiration';
		return $columns;
	}

	public function getSortFields() {
		$sort_fields = array(
			'name' => 'Plan',
			'price' => 'Price',
			'service_provider' => 'Service Provider'
		);
		return array_merge($sort_fields, parent::getSortFields());
	}

	public function update($params) {
		$entity = parent::update($params);
		$duplicate = $params['duplicate_rates'];
		if ($duplicate) {
			$source_id = $params['source_id'];
			unset($params['source_id']); // we don't save because admin ref issues
			unset($params['duplicate_rates']);
			$new_id = $entity['_id']->getMongoID();
			self::duplicate_rates($source_id, $new_id);
		}
		return $entity;
	}

	/**
	 * for every rate who has ref to original plan add ref to new plan
	 * @param type $source_id
	 * @param type $new_id
	 */
	public function duplicate_rates($source_id, $new_id) {
		$rates_col = Billrun_Factory::db()->ratesCollection();
		$source_ref = MongoDBRef::create("plans", new mongoId($source_id));
		$dest_ref = MongoDBRef::create("plans", $new_id);
		$usage_types = Billrun_Factory::config()->getConfigValue('admin_panel.line_usages');
		foreach ($usage_types as $type => $string) {
			$attribute = "rates." . $type . ".plans";
			$query = array($attribute => $source_ref);
			$update = array('$push' => array($attribute => $dest_ref));
			$params = array("multiple" => 1);
			$rates_col->update($query, $update, $params);
		}
	}
	
	public function getFilterFields() {
		$names = Billrun_Factory::db()->serviceprovidersCollection()->query()->cursor()->sort(array('name' => 1));
		$serviceProvidersNames = array();
		foreach ($names as $name) {
			$serviceProvidersNames[$name['name']] = $name['name'];
		}
		$filter_fields = array(
			'service_provider' => array(
				'key' => 'service_provider',
				'db_key' => 'service_provider',
				'input_type' => 'multiselect',
				'comparison' => '$in',
				'display' => 'Service Provider',
				'values' => $serviceProvidersNames,
				'default' => array(),
			),
		);
		return array_merge($filter_fields, parent::getFilterFields());
	}
	
	public function getFilterFieldsOrder() {
		$filter_field_order = array(
			array(
				'service_provider' => array(
					'width' => 2,
				),
			),
		);
		return array_merge($filter_field_order, parent::getFilterFieldsOrder());
	}
	
	public function applyFilter($filter_field, $value) {
		if ($filter_field['comparison'] == '$in' && empty($value)) {
			return;
		}
		return parent::applyFilter($filter_field, $value);
	}
	
	public function getOverlappingDatesQuery($entity, $new = true) {
		$additionalQuery = array(
			'service_provider' => $entity['service_provider'],
		);
		return array_merge(parent::getOverlappingDatesQuery($entity, $new), $additionalQuery);
	}
	
	public function validate($data, $type) {
		$validationMethods = array('validateMandatoryFields', 'validateDateFields', 'validatePrice', 'validateRecurring');
		foreach ($validationMethods as $validationMethod) {
			if (($res = $this->{$validationMethod}($data, $type)) !== true) {
				return $this->validationResponse(false, $res);
			}
		}
		return $this->validationResponse(true);
	}
	
	protected function validatePrice($data) {		
		foreach ($data['price'] as $price) {
			if (!isset($price['price']) || !isset($price['duration'])|| !isset($price['duration']['from'])|| !isset($price['duration']['to'])) {
				return "Illegal price structure";
			}
			if (!Billrun_Util::IsFloatValue($price['price'])) {
				return "price must be a number";
			}
			
			if (!Billrun_Util::isDateValue($price['duration']['from'])) {
				return "duration from must be in date format";
			}
			if (!Billrun_Util::isDateValue($price['duration']['to'])) {
				return "duration to must be in date format";
			}
		}
		
		return true;
	}
	
	protected function validateRecurring($data) {
		if (!isset($data['recurring']['duration']) || !isset($data['recurring']['unit'])) {
			return 'Illegal recurring stracture';
		}
		
		$availableUnits = array('day', 'week', 'month', 'year');
		if (!in_array($data['recurring']['unit'], $availableUnits)) {
			return $data['recurring']['unit'] . ' is not a valid value for "unit". Available values are: ' . implode(', ', $availableUnits);
		}
		
		if (!Billrun_Util::IsIntegerValue($data['recurring']['duration'])) {
			return "Recurring duration must be a number";
		}
		
		return true;
	}

}
