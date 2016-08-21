<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
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
		if (!empty($params['duplicate_rates'])) {
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
	
	public function validate($data, $type) {
		$validationMethods = array('validateName', 'validateMandatoryFields', 'validateTypeOfFields', 'validatePrice', 'validateRecurrence', 'validateYearlyPeriodicity');
		foreach ($validationMethods as $validationMethod) {
			if (($res = $this->{$validationMethod}($data, $type)) !== true) {
				return $this->validationResponse(false, $res);
			}
		}
		return $this->validationResponse(true);
	}
	
	protected function validateName($data) {	
		if(!isset($data['name'])) {
			return false;
		}
		$name = strtolower($data['name']);
		return !in_array($name, array('base', 'groups'));
	}	
	
	protected function validatePrice($data) {		
		foreach ($data['price'] as $price) {
			if (!isset($price['price']) || !isset($price['from'])|| !isset($price['to'])) {
				return "Illegal price structure";
			}
			
			$typeFields = array(
				'price' => 'float',
				'from' => 'date',
				'to' => 'date',
			);
			$validateTypes = $this->validateTypes($price, $typeFields);
			if ($validateTypes !== true) {
				return $validateTypes;
			}
		}
		
		return true;
	}
	
	protected function validateRecurrence($data) {
		if (!isset($data['recurrence']['periodicity']) || !isset($data['recurrence']['unit'])) {
			return 'Illegal "recurrence" stracture';
		}
		
		$typeFields = array(
			'unit' => 'integer',
			'periodicity' => array('type' => 'in_array', 'params' => array('month', 'year')),
		);
		$validateTypes = $this->validateTypes($data['recurrence'], $typeFields);
		if ($validateTypes !== true) {
			return $validateTypes;
		}
		
		if ($data['recurrence']['unit'] !== 1) {
			return 'Temporarily, recurrence "unit" must be 1';
		}
		
		return true;
	}
	
	protected function validateYearlyPeriodicity($data) {
		if ($data['recurrence']['periodicity'] === 'year' && !$data['upfron']) {
			return 'Plans with a yearly periodicity must be paid upfront';
		}
		return true;
	}
	
	public static function isPlanExists($planName) {
		$query = array_merge(Billrun_Util::getDateBoundQuery(), array('name' => $planName));
		return $planName === 'BASE' || (Billrun_Factory::db()->plansCollection()->query($query)->cursor()->count() > 0);
	}

}
