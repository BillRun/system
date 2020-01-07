<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * TADIGs model class
 *
 * @package  Models
 * @subpackage Table
 * @since    0.5
 */
class TadigsModel extends TableModel {

	public function __construct(array $params = array()) {
		$params['collection'] = Billrun_Factory::db()->tadigs;
		parent::__construct($params);
		$this->search_key = 'tadig';
	}

	public function getSortFields() {
		$sort_fields = array(
			'tadig' => 'Tadig',
			'launch_date' => 'Launch Date',
		);
		return $sort_fields;
	}

	public function getFilterFields() {
		$filter_fields = array(
			'tadig' => array(
				'key' => 'tadig',
				'db_key' => 'tadig',
				'input_type' => 'text',
				'case_type' => 'upper',
				'comparison' => 'contains',
				'display' => 'Tadig',
				'default' => '',
			),
			'mcc_mnc' => array(
				'key' => 'mcc_mnc',
				'db_key' => 'mcc_mnc',
				'input_type' => 'text',
				'comparison' => '$eq',
				'display' => 'MCC-MNC',
				'default' => '',
			),
			'from_launch_date' => array(
				'key' => 'from_launch_date',
				'db_key' => 'launch_date',
				'input_type' => 'date',
				'comparison' => '$gte',
				'display' => 'From Launch Date',
				'default' => '',
			),
			'to_launch_date' => array(
				'key' => 'to_launch_date',
				'db_key' => 'launch_date',
				'input_type' => 'date',
				'comparison' => '$lte',
				'display' => 'To Launch Date',
				'default' => '',
			),
		);
		return array_merge($filter_fields, parent::getFilterFields());
	}

	public function getFilterFieldsOrder() {
		$filter_field_order = array(
			array(
				'tadig' => array(
					'width' => 2,
				),
				'mcc_mnc' => array(
					'width' => 2,
				),
			),
			array(
				'from_launch_date' => array(
					'width' => 2,
				),
				'to_launch_date' => array(
					'width' => 2,
				),
			),
		);
		return $filter_field_order;
	}

	public function getData($filter_query = array()) {
		$resource = parent::getData($filter_query);
		$ret = array();
		foreach ($resource as $item) {
			$item['mcc_mnc'] = json_encode($item['mcc_mnc']);
			$item['launch_date'] = empty($item['launch_date'])
					? 'TD'
					: (new Zend_Date($item['launch_date']->sec))->toString('YYYY-MM-dd HH:mm:ss');
			$ret[] = $item;
		}
		return $ret;
	}

	public function applyFilter($filter_field, $value) {
		if (!empty($value) && $filter_field['input_type'] == 'text' && $filter_field['comparison'] != 'contains') { // only contains is handled in the parent
			return array(
				$filter_field['db_key'] => array(
					$filter_field['comparison'] => $value
				)
			);
		}

		return parent::applyFilter($filter_field, $value);
	}
	
	public function getEmptyItem() {
		return new Mongodloid_Entity(array(
			'tadig' => '',
			'mcc_mnc' => array(),
			'launch_date' => '',
		));
	}
	
	public function getItem($id) {
		$entity = parent::getItem($id);
		if (!empty($entity['launch_date'])) {
				$entity['launch_date'] = (new Zend_Date($entity['launch_date']->sec))->toString('YYYY-MM-dd HH:mm:ss');
		}
		
		return $entity;
	}
	
	public function update($params) {
		if (isset($params['launch_date']) && !$params['launch_date'] instanceof MongoDate) {
			$params['launch_date'] = new MongoDate(strtotime($params['launch_date']));
		}
		
		if (empty($params['launch_date'])) {
			unset($params['launch_date']);
		}
		
		if (!isset($params['mcc_mnc'])) {
			$params['mcc_mnc'] = array();
		}
		
		if (!is_array($params['mcc_mnc'])) {
			$params['mcc_mnc'] = array($params['mcc_mnc']);
		}
		
		$params['mcc_mnc'] = array_map('strval', $params['mcc_mnc']);

		return parent::update($params);
	}

}
