<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Lines model class to pull data from database for lines collection
 *
 * @package  Models
 * @subpackage Lines
 * @since    0.5
 */
class LinesModel extends TableModel {

	/**
	 *
	 * @var boolean show garbage lines
	 */
	protected $garbage = false;

	public function __construct(array $params = array()) {
		$params['collection'] = Billrun_Factory::db()->lines;
		parent::__construct($params);
		$this->search_key = "stamp";
	}

	public function getProtectedKeys($entity, $type) {
		$parent_protected = parent::getProtectedKeys($entity, $type);
		if ($type == 'update') {
			return array_merge($parent_protected, array("type", "account_id", "subscriber_id", "billrun_ref", "file", "header_stamp", "imsi", "source", "stamp", "unified_record_time", "usaget", "billrun"));
		}
		return $parent_protected;
	}

	/**
	 * @param Mongodloid collection $collection
	 * @param array $entity
	 * 
	 * @return type
	 * @todo move to model
	 */
	public function getItem($id) {

		$entity = parent::getItem($id);

		if (isset($entity['unified_record_time'])) {
			$entity['unified_record_time'] = (new Zend_Date($entity['unified_record_time']->sec, null, new Zend_Locale('he_IL')))->getIso();
		}
		if (isset($entity['customer_rate'])) {
			$data = $entity->get('customer_rate', false);
			if ($data instanceof Mongodloid_Entity) {
				$entity['customer_rate'] = $data->get('key');
			}
		}
		if (isset($entity['billrun_ref'])) {
			$data = $entity->get('billrun_ref', false);
			if ($data instanceof Mongodloid_Entity) {
				$entity['billrun_ref'] = $data->get('billrun_key');
			}
		}

		return $entity;
	}

	public function getHiddenKeys($entity, $type) {
		$hidden_keys = array_merge(parent::getHiddenKeys($entity, $type), array("plan_ref"));
		return $hidden_keys;
	}

	public function update($data) {
		$currentDate = new MongoDate();
		if (isset($data['customer_rate'])) {
			$ratesColl = Billrun_Factory::db()->ratesCollection();
			$rateEntity = $ratesColl->query('key', $data['customer_rate'])
					->lessEq('from', $currentDate)
					->greaterEq('to', $currentDate)
					->cursor()->current();
			$data['customer_rate'] = $rateEntity->createRef($ratesColl);
		}
		if (isset($data['plan'])) {
			$plansColl = Billrun_Factory::db()->plansCollection();
			$planEntity = $plansColl->query('name', $data['plan'])
					->lessEq('from', $currentDate)
					->greaterEq('to', $currentDate)
					->cursor()->current();
			$data['plan_ref'] = $planEntity->createRef($plansColl);
		}
		parent::update($data);
	}

	public function getData($filter_query) {
		$cursor = $this->collection->query($filter_query)->cursor();
		$this->_count = $cursor->count();
		$resource = $cursor->sort($this->sort)->skip($this->offset())->limit($this->size);
		return $resource;
	}

	public function getTableColumns() {
		$columns = array(
			'type' => 'Type',
			'account_id' => 'Account id',
			'subscriber_id' => 'Subscriber id',
			'usaget' => 'Usage type',
			'usagev' => 'Amount',
			'plan' => 'Plan',
			'price_customer' => 'Price',
			'billrun_key' => 'Billrun',
			'unified_record_time' => 'Time',
			'_id' => 'Id',
		);
		return $columns;
	}
	
	public function toolbar() {
		return 'events';
	}

}

