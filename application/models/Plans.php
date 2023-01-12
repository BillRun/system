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
		$source_ref = Mongodloid_Ref::create("plans", new Mongodloid_Id($source_id));
		$dest_ref = Mongodloid_Ref::create("plans", $new_id);
		$usage_types = Billrun_Factory::config()->getConfigValue('admin_panel.line_usages');
		foreach ($usage_types as $type => $string) {
			$attribute = "rates." . $type . ".plans";
			$query = array($attribute => $source_ref);
			$update = array('$push' => array($attribute => $dest_ref));
			$params = array("multiple" => true);
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

}