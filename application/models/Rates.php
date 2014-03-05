<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Rates model class to pull data from database for plan collection
 *
 * @package  Models
 * @subpackage Table
 * @since    0.5
 */
class RatesModel extends TabledateModel {

	protected $showprefix;

	public function __construct(array $params = array()) {
		$params['collection'] = Billrun_Factory::db()->rates;
		parent::__construct($params);
		$this->search_key = "key";
		if (isset($params['showprefix'])) {
			$this->showprefix = $params['showprefix'];
			if ($this->size > 50 && $this->showprefix) {
				$this->size = 50;
			}
		} else {
			$this->showprefix = false;
		}
	}

	/**
	 * method to convert plans ref into their name
	 * triggered before present the rate entity for edit
	 * 
	 * @param Mongodloid collection $collection
	 * @param array $entity
	 * 
	 * @return type
	 * @todo move to model
	 */
	public function getItem($id) {

		$entity = parent::getItem($id);

		if (isset($entity['rates'])) {
			$raw_data = $entity->getRawData();
			foreach ($raw_data['rates'] as &$rate) {
				if (isset($rate['plans'])) {
					foreach ($rate['plans'] as &$plan) {
						$data = $this->collection->getRef($plan);
						if ($data instanceof Mongodloid_Entity) {
							$plan = $data->get('name');
						}
					}
				}
			}
			$entity->setRawData($raw_data);
		}

		return $entity;
	}

	/**
	 * method to convert plans names into their refs
	 * triggered before save the rate entity for edit
	 * 
	 * @param Mongodloid collection $collection
	 * @param array $data
	 * 
	 * @return void
	 * @todo move to model
	 */
	public function update($data) {
		if (isset($data['rates'])) {
			$plansColl = Billrun_Factory::db()->plansCollection();
			$currentDate = new MongoDate();
			$rates = $data['rates'];
			//convert plans
			foreach ($rates as &$rate) {
				if (isset($rate['plans'])) {
					$sourcePlans = (array) $rate['plans']; // this is array of strings (retreive from client)
					$newRefPlans = array(); // this will be the new array of DBRefs
					unset($rate['plans']);
					foreach ($sourcePlans as &$plan) {
						$planEntity = $plansColl->query('name', $plan)
										->lessEq('from', $currentDate)
										->greaterEq('to', $currentDate)
										->cursor()->setReadPreference(MongoClient::RP_SECONDARY_PREFERRED)->current();
						$newRefPlans[] = $planEntity->createRef($plansColl);
					}
					$rate['plans'] = $newRefPlans;
				}
			}
			$data['rates'] = $rates;
		}

		return parent::update($data);
	}

	public function getTableColumns() {
		if ($this->showprefix) {
			$columns = array(
				'key' => 'Key',
				'prefix' => 'Prefix',
				'from' => 'From',
				'to' => 'To',
				'_id' => 'Id',
			);
		} else {
			$columns = array(
				'key' => 'Key',
				't' => 'Type',
				'tprice' => 'Price',
				'tduration' => 'Interval',
				'taccess' => 'Access',
				'from' => 'From',
				'to' => 'To',
				'_id' => 'Id',
			);
		}
		if (!empty($this->extra_columns)) {
			$extra_columns = array_intersect_key($this->getExtraColumns(), array_fill_keys($this->extra_columns, ""));
			$columns = array_merge($columns, $extra_columns);
		}
		return $columns;
	}

	public function getSortFields() {
		$sort_fields = array(
			'key' => 'Key',
		);
		return array_merge($sort_fields, parent::getSortFields());
	}

	public function getFilterFields() {
		$filter_fields = array(
//			'usage' => array(
//				'key' => 'rates.$',
//				'db_key' => 'rates.$',
//				'input_type' => 'multiselect',
//				'comparison' => 'exists',
//				'display' => 'Usage',
//				'values' => array('All', 'Call', 'SMS', 'Data'),
//				'default' => array('All'),
//			),
			'key' => array(
				'key' => 'key',
				'db_key' => 'key',
				'input_type' => 'text',
				'comparison' => 'contains',
				'display' => 'Key',
//				'values' => array('All', 'Call', 'SMS', 'Data'),
				'default' => '',
			),
			'prefix' => array(
				'key' => 'prefix',
				'db_key' => 'params.prefix',
				'input_type' => 'text',
				'comparison' => 'contains',
				'display' => 'Prefix',
//				'values' => array('All', 'Call', 'SMS', 'Data'),
				'default' => '',
			),
			'showprefix' => array(
				'key' => 'showprefix',
				'db_key' => 'nofilter',
				'input_type' => 'boolean',
				'display' => 'Show prefix',
				'default' => $this->showprefix ? 'on' : '',
			),
		);
		return array_merge($filter_fields, parent::getFilterFields());
	}

	public function getFilterFieldsOrder() {
		$filter_field_order = array(
//			array(
//				'usage' => array(
//					'width' => 2,
//				),
//			),
			array(
				'key' => array(
					'width' => 2,
				),
			),
			array(
				'prefix' => array(
					'width' => 2,
				),
			),
		);
		$post_filter_field = array(
			array(
				'showprefix' => array(
					'width' => 2,
				),
			),
		);
		return array_merge($filter_field_order, parent::getFilterFieldsOrder(), $post_filter_field);
	}

	/**
	 * Get the data resource
	 * 
	 * @return Mongo Cursor
	 */
	public function getData($filter_query = array()) {
//		print_R($filter_query);die;
		$cursor = $this->collection->query($filter_query)->cursor()->setReadPreference(MongoClient::RP_SECONDARY_PREFERRED);
		$this->_count = $cursor->count();
		$resource = $cursor->sort($this->sort)->skip($this->offset())->limit($this->size);
		$ret = array();
		foreach ($resource as $item) {
			if ($item->get('rates') && !$this->showprefix) {
				foreach ($item->get('rates') as $key => $rate) {
					$added_columns = array(
						't' => $key,
						'tprice' => $rate['rate'][0]['price'],
						'taccess' => isset($rate['access']) ? $rate['access'] : 0,
					);
					if (strpos($key, 'call') !== FALSE) {
						$added_columns['tduration'] = Billrun_Util::durationFormat($rate['rate'][0]['interval']);
					} else if ($key == 'data') {
						$added_columns['tduration'] = Billrun_Util::byteFormat($rate['rate'][0]['interval'], '', 0, true);
					} else {
						$added_columns['tduration'] = $rate['rate'][0]['interval'];
					}
					$ret[] = new Mongodloid_Entity(array_merge($item->getRawData(), $added_columns, $rate));
				}
			} else if ($this->showprefix && (isset($filter_query['$and'][0]['key']) || isset($filter_query['$and'][0]['params.prefix']))) {
				foreach ($item->get('params.prefix') as $prefix) {
					$ret[] = new Mongodloid_Entity(array_merge($item->getRawData(), array('prefix' => $prefix)));
				}
			} else {
				$ret[] = $item;
			}
		}
		return $ret;
	}

}
