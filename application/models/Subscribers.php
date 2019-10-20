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
class SubscribersModel extends TabledateModel {

	protected $subscribers_coll;

	/**
	 * constructor
	 * 
	 * @param array $params of parameters to preset the object
	 */
	public function __construct(array $params = array()) {
		$params['collection'] = Billrun_Factory::db()->subscribers;
		parent::__construct($params);
		$this->subscribers_coll = Billrun_Factory::subscriber();
		$this->search_key = "sid";
	}

	public function getTableColumns() {
		$columns = array(
			'sid' => 'Subscriber No',
			'aid' => 'BAN',
			'msisdn' => 'MSISDN',
			'plan' => 'Plan',
			'from' => 'From',
			'to' => 'Expiration'
		);
		return $columns;
	}

	public function getSortFields() {
		$sort_fields = array(
			'sid' => 'Subscriber No',
			'aid' => 'BAN',
			'msisdn' => 'MSISDN',
			'plan' => 'Plan'
		);
		return array_merge($sort_fields, parent::getSortFields());
	}

	public function getFilterFields() {
		$names = Billrun_Factory::db()->plansCollection()->query(array(
				'$or' => array(
					array(
						'type' => 'customer'
					),
					array(
						'type' => array(
							'$exists' => false
						)
					)
				)
			))->cursor()->sort(array('name' => 1));
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
			'msisdn' => array(
				'key' => 'msisdn',
				'db_key' => 'msisdn',
				'input_type' => 'text',
				'comparison' => 'contains',
				'display' => 'MSISDN',
				'default' => '',
			),
			'imsi' => array(
				'key' => 'imsi',
				'db_key' => 'imsi',
				'input_type' => 'text',
				'comparison' => '$in',
				'display' => 'IMSI',
				'default' => ''
			),
			'plan' => array(
				'key' => 'plan',
				'db_key' => 'plan',
				'input_type' => 'multiselect',
				'comparison' => '$in',
				'display' => 'Plan',
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
			array(
				'sid' => array(
					'width' => 2,
				),
				'aid' => array(
					'width' => 2
				),
				'msisdn' => array(
					'width' => 2,
				)
			),
			array(
				'imsi' => array(
					'width' => 2,
				),
				'plan' => array(
					'width' => 2
				),
				'service_provider' => array(
					'width' => 2
				)
			)
		);
		return array_merge($filter_field_order, parent::getFilterFieldsOrder());
	}

	public function getBySid($sid) {
		$entity = $this->subscribers_coll->loadSubscriber(array('sid' => intval($sid), Billrun_Utils_Mongo::getDateBoundQuery()));
		// convert mongo values into javascript values
		$entity['_id'] = (string) $entity['_id'];
		if ($entity['from'] && isset($entity['from']->sec))
			$entity['from'] = (new Zend_Date($entity['from']->sec))->toString('dd-MM-YYYY HH:mm:ss');
		if ($entity['to'] && isset($entity['to']->sec))
			$entity['to'] = (new Zend_Date($entity['to']->sec))->toString('dd-MM-YYYY HH:mm:ss');
		if ($entity['creation_time'] && isset($entity['creation_time']->sec))
			$entity['creation_time'] = (new Zend_Date($entity['creation_time']->sec))->toString('dd-MM-YYYY HH:mm:ss');
		if ($entity['data_slowness_enter'] && isset($entity['data_slowness_enter']->sec))
			$entity['data_slowness_enter'] = (new Zend_Date($entity['data_slowness_enter']->sec))->toString('dd-MM-YYYY HH:mm:ss');
		if ($entity['data_slowness_exit'] && isset($entity['data_slowness_exit']->sec))
			$entity['data_slowness_exit'] = (new Zend_Date($entity['data_slowness_exit']->sec))->toString('dd-MM-YYYY HH:mm:ss');
		if (is_array($entity['from']) && isset($entity['from']->sec))
			$entity['from'] = (new Zend_Date($entity['from']->sec))->toString('dd-MM-YYYY HH:mm:ss');
		if (is_array($entity['to']) && isset($entity['to']->sec))
			$entity['to'] = (new Zend_Date($entity['to']->sec))->toString('dd-MM-YYYY HH:mm:ss');
		return $entity;
	}

	public function getProtectedKeys($entity, $type) {
		$parentKeys = parent::getProtectedKeys($entity, $type);
		return array_merge($parentKeys, array());
	}
	
	public function hasEntityWithOverlappingDates($entity, $new = true) {
		return false;
	}
	
	public function getItem($id) {
		$entity = parent::getItem($id);
		if ($entity['data_slowness_enter'] && isset($entity['data_slowness_enter']->sec))
			$entity['data_slowness_enter'] = (new Zend_Date($entity['data_slowness_enter']->sec))->toString('dd-MM-YYYY HH:mm:ss');
		if ($entity['data_slowness_exit'] && isset($entity['data_slowness_exit']->sec))
			$entity['data_slowness_exit'] = (new Zend_Date($entity['data_slowness_exit']->sec))->toString('dd-MM-YYYY HH:mm:ss');
		return $entity;
	}

	public function getItemByName($id, $field_name = 'name') {
		$entity = parent::getItemByName($id, $field_name);
		if ($entity['data_slowness_enter'] && isset($entity['data_slowness_enter']->sec))
			$entity['data_slowness_enter'] = (new Zend_Date($entity['data_slowness_enter']->sec))->toString('dd-MM-YYYY HH:mm:ss');
		if ($entity['data_slowness_exit'] && isset($entity['data_slowness_exit']->sec))
			$entity['data_slowness_exit'] = (new Zend_Date($entity['data_slowness_exit']->sec))->toString('dd-MM-YYYY HH:mm:ss');
		return $entity;
	}

}
