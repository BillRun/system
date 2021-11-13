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
 * This class is to hold the logic for the subscribers module.
 *
 * @package  Models
 * @subpackage Table
 * @since    4.0
 */
class SubscribersautorenewservicesModel extends TabledateModel {

	protected $subscribers_auto_renew_services_coll;

	/**
	 * constructor
	 * 
	 * @param array $params of parameters to preset the object
	 */
	public function __construct(array $params = array()) {
		$params['collection'] = Billrun_Factory::db()->subscribers_auto_renew_services;
		parent::__construct($params);
		$this->subscribers_auto_renew_services_coll = Billrun_Factory::db()->subscribers_auto_renew_servicesCollection();
		$this->search_key = "sid";
	}

	public function getTableColumns() {
		$columns = array(
			'sid' => 'Subscriber No',
			'aid' => 'BAN',
			'interval' => 'Interval',
			'charging_plan_name' => 'Charging Plan',
			'service_provider' => "Service Provider",
			'done' => 'Done',
			'remain' => 'Remaining',
			'operators' => 'Operation',
			'initial_renew_date' => "Initial Renew Date",
			'last_renew_date' => 'Last Renew Date',
			'next_renew_date' => 'Next Renew Date',
			'from' => 'Start',
			'to' => 'Expiration'
		);
		return $columns;
	}

	public function getSortFields() {
		$sort_fields = array(
			'sid' => 'Subscriber No',
			'aid' => 'BAN',
			'interval' => 'Interval',
			'charging_plan_name' => 'Charging Plan Name',
			'done' => 'Done',
			'remain' => 'Remaining',
			'operators' => 'Operation',
			'initial_renew_date' => 'Initial Renew Date',
			'last_renew_date' => 'Last Renew Date',
			'next_renew_date' => 'Next Renew Date'
		);
		return array_merge($sort_fields, parent::getSortFields());
	}

	public function getFilterFields() {
		$names = Billrun_Factory::db()->plansCollection()->query(array('type' => 'charging'))->cursor()->sort(array('name' => 1));
		$planNames = array();
		foreach ($names as $name) {
			$planNames[$name['name']] = $name['name'];
		}

		$filter_fields = array(
			'sid' => array(
				'key' => 'sid',
				'db_key' => 'sid',
				'input_type' => 'number',
				'comparison' => 'equals',
				'display' => 'Subscriber No',
				'default' => '',
			),
			'aid' => array(
				'key' => 'aid',
				'db_key' => 'aid',
				'input_type' => 'number',
				'comparison' => 'equals',
				'display' => 'BAN',
				'default' => '',
			),
			'charging_plan_name' => array(
				'key' => 'charging_plan_name',
				'db_key' => 'charging_plan_name',
				'input_type' => 'multiselect',
				'comparison' => '$in',
				'ref_coll' => 'plans',
				'ref_key' => 'name',
				'display' => 'Charging Plan',
				'values' => $planNames,
				'default' => array(),
			),
			'service_provider' => array(
				'key' => 'service_provider',
				'db_key' => 'service_provider',
				'input_type' => 'text',
				'comparison' => 'contains',
				'display' => 'Service Provider',
				'default' => '',
			)
		);
		return array_merge($filter_fields, parent::getFilterFields());
	}

	public function getFilterFieldsOrder() {
		$filter_field_order = array(
			0 => array(
				'sid' => array(
					'width' => 2,
				),
				'aid' => array(
					'width' => 2,
				),
				'charging_plan_name' => array(
					'width' => 2,
				),
				'service_provider' => array(
					'width' => 2
				),
			)
		);
		return array_merge($filter_field_order, parent::getFilterFieldsOrder());
	}

	public function getProtectedKeys($entity, $type) {
		$parentKeys = parent::getProtectedKeys($entity, $type);
		return array_merge($parentKeys, array());
	}

	public function update($params) {
		$params['remain'] = Billrun_Utils_Autorenew::countMonths(strtotime($params['from']), strtotime($params['to']));
		if (is_string($params['next_renew_date'])) {
			$params['next_renew_date'] = new Mongodloid_Date(strtotime($params['next_renew_date']));
		} else if (is_array($params['next_renew_date'])) {
			$params['next_renew_date'] = new Mongodloid_Date($params['next_renew_date']['sec']);
		}
		if (is_string($params['last_renew_date'])) {
			$params['last_renew_date'] = new Mongodloid_Date(strtotime($params['last_renew_date']));
		} else if (is_array($params['last_renew_date'])) {
			$params['last_renew_date'] = new Mongodloid_Date($params['last_renew_date']['sec']);
		}
		return parent::update($params);
	}
	
	public function hasEntityWithOverlappingDates($entity, $new = true) {
		return false;
	}

}
