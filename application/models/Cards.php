<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * This class is to hold the logic for the Cards module.
 *
 * @package  Models
 * @subpackage Table
 * @since    4.0
 */
class CardsModel extends TableModel {

	protected $cards_coll;

	/**
	 * constructor
	 * 
	 * @param array $params of parameters to preset the object
	 */
	public function __construct(array $params = array()) {
		$params['collection'] = Billrun_Factory::db()->cards;
		parent::__construct($params);
		$this->cards_coll = Billrun_Factory::db()->cardsCollection();
		$this->search_key = "secret";
	}

	public function getTableColumns() {
		$columns = array(
			'batch_number' => 'Batch Number',
			'serial_number' => 'Serial Number',
			'charging_plan_name' => 'Charging Plan',
			'service_provider' => 'Service Provider',
			'status' => 'Status',
			'to' => 'Expiration',
			'sid' => 'Subscriber No',
			'activation_datetime' => 'Activation',
		);
		return $columns;
	}

	public function getSortFields() {
		$sort_fields = array(
			'batch_number' => 'Batch Number',
			'serial_number' => 'Serial Number',
			'charging_plan_name' => 'Charging Plan',
			'status' => 'Status',
			'service_provider' => 'Service Provider',
			'sid' => 'Subscriber No',
			'activation_datetime' => 'Activation'
		);
		return array_merge($sort_fields, parent::getSortFields());
	}

	public function getFilterFields() {
		$names = Billrun_Factory::db()->plansCollection()->query(array('type' => 'charging'))->cursor()->sort(array('name' => 1));
		$planNames = array();
		foreach ($names as $name) {
			$planNames[$name['name']] = $name['name'];
		}
		$names = Billrun_Factory::db()->serviceprovidersCollection()->query()->cursor()->sort(array('name' => 1));
		$serviceProvidersNames = array();
		foreach ($names as $name) {
			$serviceProvidersNames[$name['name']] = $name['name'];
		}

		$statuses = array('Idle' => 'Idle', 'Shipped' => 'Shipped', 'Active' => 'Active', 'Disqualified' => 'Disqualified', 'Stolen' => 'Stolen', 'Expired' => 'Expired', 'Used' => 'Used');
		$filter_fields = array(
			'batch_number' => array(
				'key' => 'batch_number',
				'db_key' => 'batch_number',
				'input_type' => 'number',
				'comparison' => 'equals',
				'display' => 'Batch Number',
				'default' => '',
			),
			'serial_number' => array(
				'key' => 'serial_number',
				'db_key' => 'serial_number',
				'input_type' => 'number',
				'comparison' => 'equals',
				'display' => 'Serial Number',
				'default' => '',
			),
			'charging_plan_name' => array(
				'key' => 'plan',
				'db_key' => 'charging_plan_name',
				'input_type' => 'multiselect',
				'comparison' => '$in',
				'ref_coll' => 'plans',
				'ref_key' => 'name',
				'display' => 'Charging Plan',
				'values' => $planNames,
				'default' => array(),
			),
			'status' => array(
				'key' => 'status',
				'db_key' => 'status',
				'input_type' => 'multiselect',
				'comparison' => '$in',
				'display' => 'Status',
				'values' => $statuses,
				'default' => array(),
			),
			'service_provider' => array(
				'key' => 'service_provider',
				'db_key' => 'service_provider',
				'input_type' => 'multiselect',
				'comparison' => '$in',
				'display' => 'Service Provider',
				'values' => $serviceProvidersNames,
				'default' => array(),
			),
			'sid' => array(
				'key' => 'sid',
				'db_key' => 'sid',
				'input_type' => 'number',
				'comparison' => 'equals',
				'display' => 'Subscriber No',
				'default' => '',
			),
			'date' => array(
				'key' => 'date',
				'db_key' => array('creation_time', 'to'),
				'input_type' => 'date',
				'comparison' => array('$lte', '$gte'),
				'display' => 'Active Date',
//				'default' => (new Zend_Date(null, null, new Zend_Locale('he_IL')))->toString('YYYY-MM-dd HH:mm:ss'),
			),
		);
		if (AdminController::authorized('admin')) {
			$filter_fields['secret'] = array(
				'key' => 'secret',
				'db_key' => 'secret',
				'input_type' => 'text',
				'comparison' => 'hash',
				'display' => 'Secret Code',
				'default' => '',
			);
		}
		return array_merge($filter_fields, parent::getFilterFields());
	}

	public function getFilterFieldsOrder() {
		$filter_field_order = array(
			array(
				'batch_number' => array(
					'width' => 2,
				),
				'serial_number' => array(
					'width' => 2,
				),
				'charging_plan_name' => array(
					'width' => 2
				)
			),
			array(
				'service_provider' => array(
					'width' => 2
				),
				'status' => array(
					'width' => 2
				),
				'sid' => array(
					'width' => 2,
				),
				'date' => array(
					'width' => 2,
				)
			),
		);
		if (AdminController::authorized('admin')) {
			$filter_field_order[0]['secret'] = array(
				'width' => 2,
			);
		}
		return $filter_field_order;
	}

	public function getProtectedKeys($entity, $type) {
		$parentKeys = parent::getProtectedKeys($entity, $type);
		return array_merge(array("_id"), $parentKeys, array()
		);
	}

	public function getHiddenKeys($entity, $type) {
		$parentKeys = parent::getHiddenKeys($entity, $type);
		return array_merge(array("_id"), $parentKeys, array(
			"secret"
			)
		);
	}

	public function applyFilter($filter_field, $value) {
		if ($filter_field['comparison'] == 'hash') {
			$filter_field['comparison'] = 'contains';
			$value = $this->hashValue($value);
		}
		if ($filter_field['input_type'] == 'date' && is_array($filter_field['db_key'])) {
			if (is_string($value)) {
				$value = new Mongodloid_Date((new Zend_Date($value, null, new Zend_Locale('he_IL')))->getTimestamp());
				$ret = array(
					'$and' => array(
						array(
							$filter_field['db_key'][0] => array(
								$filter_field['comparison'][0] => $value
							),
							$filter_field['db_key'][1] => array(
								$filter_field['comparison'][1] => $value
							),
						),
					),
				);
				return $ret;
			}
		} else {
			return parent::applyFilter($filter_field, $value);
		}
	}
	
	protected function hashValue($val) {
		return hash('sha512', $val);
	}

}
