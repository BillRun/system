<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
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
class SubscribersModel extends TabledateModel{
	
	protected $subscribers_coll;
	
	/**
	 * constructor
	 * 
	 * @param array $params of parameters to preset the object
	 */
	public function __construct(array $params = array()) {
		$params['collection'] = Billrun_Factory::db()->subscribers;
		parent::__construct($params);
		$this->subscribers_coll = Billrun_Factory::db()->subscribersCollection();
		$this->search_key = "sid";
	}
	
	public function getTableColumns() {
		$columns = array(
			'sid' => 'SID',
			'msisdn' => 'MSISDN',
			'plan' => 'Plan',
			'language' => 'Language',
			'service_provider' => 'Service Provider',
			'from' => 'From',
			'to' => 'To',
			'_id' => 'Id',
		);
		return $columns;
	}

	public function getSortFields() {
		$sort_fields = array(
			'sid' => 'SID',
			'msisdn' => 'MSISDN',
			'plan' => 'Plan',
			'language' => 'Language',
			'service_provider' => 'Service Provider'
		);
		return array_merge($sort_fields, parent::getSortFields());
	}
	
	public function getFilterFields() {
		$months = 12;
		$billruns = array();
		$timestamp = time();
		for ($i = 0; $i < $months; $i++) {
			$billrun_key = Billrun_Util::getBillrunKey($timestamp);
			if ($billrun_key >= '201401') {
				$billruns[$billrun_key] = $billrun_key;
			}
			else {
				break;
			}
			$timestamp = strtotime("1 month ago", $timestamp);
		}
		arsort($billruns);

		$filter_fields = array(
			'msisdn' => array(
				'key' => 'msisdn',
				'db_key' => 'msisdn',
				'input_type' => 'text',
				'comparison' => 'equals',
				'display' => 'MSISDN',
				'default' => '',
			),
			'from' => array(
				'key' => 'from',
				'db_key' => 'from',
				'input_type' => 'date',
				'comparison' => '$gte',
				'display' => 'From',
				'default' => (new Zend_Date(strtotime('2 months ago'), null, new Zend_Locale('he_IL')))->toString('YYYY-MM-dd HH:mm:ss'),
			),
			'to' => array(
				'key' => 'to',
				'db_key' => 'to',
				'input_type' => 'date',
				'comparison' => '$lte',
				'display' => 'To',
				'default' => (new Zend_Date(strtotime("next month"), null, new Zend_Locale('he_IL')))->toString('YYYY-MM-dd HH:mm:ss'),
			),
		);
		return array_merge($filter_fields, parent::getFilterFields());
	}

	public function getFilterFieldsOrder() {
		$filter_field_order = array(
			0 => array(
				'msisdn' => array(
					'width' => 2,
				),
				'sid' => array(
					'width' => 2,
				),
				'from' => array(
					'width' => 2,
				),
				'to' => array(
					'width' => 2,
				),
			)
		);
		return $filter_field_order;
	}
	
	public function getProtectedKeys($entity, $type) {
		$parentKeys = parent::getProtectedKeys($entity, $type);
		return array_merge($parentKeys, 
						   array("imsi", 
							     "msisdn", 
							     "aid",
							     "sid",
							     "plan",
							     "language",
							     "service_provider",
							     "charging_type"));
	}
}
