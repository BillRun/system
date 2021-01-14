<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
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
	protected $filter_by_plan;

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
		if (isset($params['filter_by_plan'])) {
			$this->filter_by_plan = $params['filter_by_plan'];
		} else {
			$this->filter_by_plan = array();
		}
	}

	/**
	 * method to convert plans ref into their name
	 * triggered before present the rate entity for edit
	 * 
	 * @param Mongodloid collection $collection
	 * @param array $entity
	 * @deprecated since version 5.0
	 * 
	 * @return type
	 */
//	public function getItem($id) {
//
//		$entity = parent::getItem($id);
//
//		if (isset($entity['rates'])) {
//			$this->processEntityRatesOnGet($entity);
//		}
//
//		return $entity;
//	}

	/**
	 * Process the internal rates values of an entity.
	 * @param Mongodloid_Entity $entity
	 * @deprecated since 5.0 The rates field in the rate record holds the plan
	 * data instead of a reference.
	 */
	protected function processEntityRatesOnGet(&$entity) {
		$raw_data = $entity->getRawData();
		foreach ($raw_data['rates'] as &$rate) {
			if (!isset($rate['plans'])) {
				continue;
			}
			
			// TODO: The internal logic of this loop is very ambigious, it will
			// be great help if someone from the core team can replace this TODO
			// with a proper comment describing this logic.
			foreach ($rate['plans'] as &$plan) {
				$data = $this->collection->getRef($plan);
				if ($data instanceof Mongodloid_Entity) {
					$plan = $data->get('name');
				}
			}
		}
		$entity->setRawData($raw_data);
	}
	
	/**
	 * method to convert plans names into their refs
	 * triggered before save the rate entity for edit
	 * 
	 * @param Mongodloid collection $collection
	 * @param array $data
	 * 
	 * @return void
	 */
	public function update($data) {
		if (isset($data['rates'])) {
			$this->processRatesOnUpdate($data);
		}

		return parent::update($data);
	}

	/**
	 * Process the internal rates values of input data to update.
	 * @param array $data - Data to update
	 */
	protected function processRatesOnUpdate(&$data) {
		$plansColl = Billrun_Factory::db()->plansCollection();
		$planQuery = Billrun_Utils_Mongo::getDateBoundQuery();
		$rates = $data['rates'];
		//convert plans
		foreach ($rates as &$rate) {
			if (!isset($rate['plans'])) {
				continue;
			}
			$this->processSingleRateOnUpdate($rate, $plansColl, $planQuery);
		}
		$data['rates'] = $rates;
	}
	
	/**
	 * Processing a single rate from the input rates in the data received to update
	 * @param array $rate - reference to the single rate object
	 * @param Mongodloid_Collection $plansColl - The plans collection
	 * @param array $planQuery - The query to use in the plan collection.
	 */
	protected function processSingleRateOnUpdate(&$rate, $plansColl, $planQuery) {
		$sourcePlans = (array) $rate['plans']; // this is array of strings (retreive from client)
		$newRefPlans = array(); // this will be the new array of DBRefs
		unset($rate['plans']);

		// TODO: The internal logic of this loop is very ambigious, it will
		// be great help if someone from the core team can replace this TODO
		// with a proper comment describing this logic.
		foreach ($sourcePlans as &$plan) {
			if (Mongodloid_Ref::isRef($plan)) {
				$newRefPlans[] = $plan;
			} else {
				$planQuery['name'] = $plan;
				$planEntity = $plansColl->query($planQuery)->cursor()->current();
				$newRefPlans[] = $plansColl->createRefByEntity($planEntity);
			}
		}
		$rate['plans'] = $newRefPlans;
	}
	
	public function getTableColumns() {
		if ($this->showprefix) {
			$columns = array(
				'key' => 'Key',
				'prefix' => 'Prefix',
				'from' => 'From',
				'to' => 'To'
			);
		} else {
			$columns = array(
				'key' => 'Key',
				't' => 'Type',
				'tprice' => 'Price',
				'tduration' => 'Interval',
				'tunit' => 'Unit',
				'taccess' => 'Access',
				'from' => 'From',
				'to' => 'To'
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
			'prefix' => 'Prefix',
		);
		return array_merge($sort_fields, parent::getSortFields());
	}

	public function getFilterFields() {
		$names = Billrun_Factory::db()->plansCollection()->query(array('type' => 'customer'))->cursor()->sort(array('name' => 1));
		$planNames = array();
		$planNames['BASE'] = 'BASE';
		foreach ($names as $name) {
			$planNames[$name['name']] = $name['name'];
		}
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
				'default' => '',
				'case_type' => 'upper',
			),
			'prefix' => array(
				'key' => 'prefix',
				'db_key' => 'params.prefix',
				'input_type' => 'text',
				'comparison' => 'contains',
				'display' => 'Prefix',
				'default' => '',
			),
			'plan' => array(
				'key' => 'plan',
				'db_key' => array('rates.call', 'rates.sms', 'rates.data', 'rates.video_call' ,'rates.roaming_incoming_call',
					'rates.roaming_call', 'rates.roaming_callback', 'rates.roaming_callback_short'),
				'input_type' => 'multiselect',
				'comparison' => '$exists',
				'display' => 'Plan',
				'values' => $planNames,
				'default' => array('BASE'),
			),
			'showprefix' => array(
				'key' => 'showprefix',
				'db_key' => 'nofilter',
				'input_type' => 'boolean',
				'display' => 'Show prefix',
				'default' => $this->showprefix ? 'on' : 'off',
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
					'width' => 4,
				),
				'prefix' => array(
					'width' => 4,
				),
				'plan' => array(
					'width' => 2,
				),
			)
		);
		
		$prefix = array(
				'showprefix' => array(
				'width' => 2,
			)
		);
		$parentOrder = parent::getFilterFieldsOrder();
		$parentOrder[0] = array_merge($parentOrder[0], $prefix);
		
		return array_merge($filter_field_order, $parentOrder);
	}

	public function applyFilter($filter_field, $value) {
		if ($filter_field['comparison'] == '$exists') {
			if (!is_null($value) && $value != $filter_field['default'] && is_array($value)) {
				$ret = array('$or' => array());
				foreach ($value as $val) {
					$or = array('$or' => array());
					foreach ($filter_field['db_key'] as $key) {
						$or['$or'][] = array("$key.$val" => array('$exists' => true));
					}
					$ret['$or'][] = $or;
				}
				return $ret;
			}
		} else {
			return parent::applyFilter($filter_field, $value);
		}
	}

	public function setFilteredPlans($plans = array()) {
		$this->filter_by_plan = $plans;
	}

	/**
	 * Get the data resource
	 * 
	 * @return Mongo Cursor
	 */
	public function getData($filter_query = array(), $fields = false) {
		if ($this->showprefix) {
			$aggregate = array(
				array(
					'$unwind' => '$params.prefix'
				),
				array(
					'$match' => $filter_query
				),
				array(
					'$project' => array(
						'key' => '$key',
						'prefix' => '$params.prefix',
						'from' => '$from',
						'to' => '$to',
					),
				),
			);
			if ($this->sort) {
				$aggregate[] = array(
					'$sort' => $this->sort,
				);
			}
			// aggregate2 used for checking general count for pagination
			$aggregate2 = $aggregate;
			if (($offset = $this->offset())) {
				$aggregate[] = array(
					'$skip' => $offset,
				);
			}

			if ($this->size) {
				$aggregate[] = array(
					'$limit' => $this->size,
				);
			}

			$results = $this->collection->aggregate($aggregate);
			$ret = iterator_to_array($results);
			if (count($ret) < $this->size && $offset == 0) {
				$this->_count = count($ret);
			} else {
				$results2 = $this->collection->aggregate($aggregate2);
				$this->_count = count(iterator_to_array($results2));
			}
			return $ret;
		}
		$cursor = $this->getRates($filter_query);
		$this->_count = $cursor->count();
		$resource = $cursor->sort($this->sort)->skip($this->offset())->limit($this->size);
		$ret = array();
		foreach ($resource as $item) {
			if ($fields) {
				foreach ($fields as $field) {
					$row[$field] = $item->get($field);
				}
				if (isset($row['rates'])) {
					// convert plan ref to plan name
					foreach ($row['rates'] as &$rate) {
						if (isset($rate['plans'])) {
							$plans = array();
							foreach ($rate['plans'] as $plan) {
								$plan_id = $plan['$id'];
								$plans[] = Billrun_Factory::plan(array('id' => $plan_id))->getName();
							}
							$rate['plans'] = $plans;
						}
					}
				}
				$ret[] = $row;
			} else if (!$this->isEmptyRatesObject($item->get('rates')) && !$this->showprefix) {
				foreach ($item->get('rates') as $key => $rate) {
					foreach ($this->filter_by_plan as $filteredPlan) {
						if (is_array($rate) && isset($rate[$filteredPlan])) {
							$added_columns = array(
								't' => $key,
								'tprice' => $rate[$filteredPlan]['rate'][0]['price'],
								'taccess' => isset($rate[$filteredPlan][0]['access']) ? $rate[$filteredPlan][0]['access'] : 0,
								'tunit' => $rate[$filteredPlan]['unit'],
								'tinterconnect' => isset($rate[$filteredPlan]['interconnect']) ? $rate[$filteredPlan]['interconnect'] : null,
							);
							if (strpos($key, 'call') !== FALSE) {
								$added_columns['tduration'] = Billrun_Util::durationFormat($rate[$filteredPlan]['rate'][0]['interval']);
							} else if ($key == 'data') {
								$added_columns['tduration'] = Billrun_Util::byteFormat($rate[$filteredPlan]['rate'][0]['interval'], '', 0, true);
							} else {
								$added_columns['tduration'] = $rate[$filteredPlan]['rate'][0]['interval'];
							}
							$raw = $item->getRawData();
							$raw['key'] .= " [" . $filteredPlan . "]";
							$ret[] = new Mongodloid_Entity(array_merge($raw, $added_columns, $rate));
						}
					}
				}
			}
			/* else if ($this->showprefix && (isset($filter_query['$and'][0]['key']) ||
			  isset($filter_query['$and'][0]['params.prefix']))
			  && !empty($item->get('params.prefix'))) { */ else if ($this->showprefix && !empty($item->get('params.prefix'))) { // deprecated
				foreach ($item->get('params.prefix') as $prefix) {
					$item_raw_data = $item->getRawData();
					unset($item_raw_data['params']['prefix']); // to prevent high memory usage
					$entity = new Mongodloid_Entity(array_merge($item_raw_data, array('prefix' => $prefix)));
					$ret[] = $entity;
				}
			} else {
				$ret[] = $item;
			}
		}
		return $ret;
	}
	
	/**
	 * Checks if a rate object is empty, 
	 * in order to display it when filtering by plan
	 * (to handle the case of UI saving empty rates)
	 * 
	 * @param type $rates
	 * @return boolean
	 */
	protected function isEmptyRatesObject($rates) {
		if (!$rates) {
			return true;
		}
		
		foreach ($rates as $key => $rate) {
			if (!empty($rate)) {
				return false;
			}
		}
		return true;
	}

	public function getRates($filter_query) {
		return $this->collection->query($filter_query)->cursor();
	}

	public function getFutureRateKeys($by_keys = array()) {
		$base_match = array(
			'$match' => array(
				'from' => array(
					'$gt' => new Mongodloid_Date(),
				),
			),
		);
		if ($by_keys) {
			$base_match['$match']['key']['$in'] = $by_keys;
		}

		$group = array(
			'$group' => array(
				'_id' => '$key',
			),
		);
		$project = array(
			'$project' => array(
				'_id' => 0,
				'key' => '$_id',
			),
		);
		$future_rates = $this->collection->aggregate($base_match, $group, $project);
		$future_keys = array();
		foreach ($future_rates as $rate) {
			$future_keys[] = $rate['key'];
		}
		return $future_keys;
	}

	public function getActiveRates($by_keys = array()) {
		$base_match = array(
			'$match' => array(
				'from' => array(
					'$lt' => new Mongodloid_Date(),
				),
				'to' => array(
					'$gt' => new Mongodloid_Date(),
				),
			),
		);
		if ($by_keys) {
			$base_match['$match']['key']['$in'] = $by_keys;
		}

		$group = array(
			'$group' => array(
				'_id' => '$key',
				'count' => array(
					'$sum' => 1,
				),
				'oid' => array(
					'$first' => '$_id',
				)
			),
		);
		$project = array(
			'$project' => array(
				'_id' => 0,
				'count' => 1,
				'oid' => 1,
			),
		);
		$having = array(
			'$match' => array(
				'count' => 1,
			),
		);
		$active_rates = $this->collection->aggregate($base_match, $group, $project, $having);
		if (!$active_rates) {
			return $active_rates;
		}
		foreach ($active_rates as $rate) {
			$active_oids[] = $rate['oid'];
		}
		$query = array(
			'_id' => array(
				'$in' => $active_oids,
			),
		);
		$rates = $this->collection->query($query);
		return $rates;
	}

	/**
	 * 
	 * @param string $usage_type
	 * @return string
	 */
	public function getUnit($usage_type) {
		switch ($usage_type) {
			case 'call':
			case 'incoming_call':
				$unit = 'seconds';
				break;
			case 'data':
				$unit = 'bytes';
				break;
			case 'sms':
			case 'mms':
				$unit = 'counter';
				break;
			default:
				$unit = 'seconds';
				break;
		}
		return $unit;
	}

	/**
	 * 
	 * @param array $rules
	 */
	public function getRateArrayByRules($rules) {
		ksort($rules);
		$rate_arr = array();
		$rule_end = 0;
		foreach ($rules as $rule) {
			$rate['price'] = floatval($rule['price']);
			$rate['interval'] = intval($rule['interval']);
			$rate['to'] = $rule_end = intval($rule['times'] == 0 ? pow(2, 31) - 1 : $rule_end + $rule['times'] * $rule['interval']);
			$rate_arr[] = $rate;
		}
		return $rate_arr;
	}

	/**
	 * Get rules array by db rate
	 * @param Mongodloid_Entity $rate
	 * @return array
	 */
	public function getRulesByRate($rate, $showprefix = false, $plans = array()) {
		$first_rule = true;
		$rule['key'] = $rate['key'];
		$rule['from_date'] = date('Y-m-d H:i:s', $rate['from']->sec);
		foreach ($rate['rates'] as $usage_type => $usage_type_rate) {
			$rule['usage_type'] = $usage_type;
			$rule['category'] = $usage_type_rate['category'];
			$rule_counter = 1;
			foreach ($usage_type_rate as $plan => $rate_plan) {
				$rule['plan'] = $plan;
				$rule['interconnect'] = isset($rate_plan['interconnect']) ? $rate_plan['interconnect'] : 'NA';
				$rule['access_price'] = isset($rate_plan['access']) ? $rate_plan['access'] : 0;
				foreach ($rate_plan['rate'] as $rate_rule) {
					$rule['rule'] = $rule_counter;
					$rule['interval'] = $rate_rule['interval'];
					$rule['price'] = $rate_rule['price'];
					$rule['times'] = intval($rate_rule['to'] / $rate_rule['interval']);
					$rule_counter++;
					if ($showprefix) {
						if ($first_rule) {
							$rule['prefix'] = '"' . implode(',', $rate['params']['prefix']) . '"';
							$first_rule = false;
						} else {
							$rule['prefix'] = '';
						}
					}
					$rules[] = $rule;
				}
			}
		}
		return $rules;
		//sort by header?
	}

	/**
	 * 
	 * @return aray
	 */
	public function getPricesListFileHeader($showprefix = false) {
		if ($showprefix) {
			return array('key', 'usage_type', 'category', 'plan', 'interconnect', 'rule', 'access_price', 'interval', 'price', 'times', 'from_date', 'prefix');
		} else {
			return array('key', 'usage_type', 'category', 'plan', 'interconnect', 'rule', 'access_price', 'interval', 'price', 'times', 'from_date');
		}
	}

	public function getRateByVLR($vlr) {
		$prefixes = Billrun_Util::getPrefixes($vlr);
		$match = array('$match' => array(
				'params.serving_networks' => array(
					'$exists' => true,
				),
				'kt_prefixes' => array(
					'$in' => $prefixes,
				),
			),);
		$unwind = array(
			'$unwind' => '$kt_prefixes',
		);
		$sort = array(
			'$sort' => array(
				'kt_prefixes' => -1,
			),
		);
		$limit = array(
			'$limit' => 1,
		);
		$rate = $this->collection->aggregate(array($match, $unwind, $match, $sort, $limit));
		if ($rate) {
			return $rate[0];
		} else {
			return NULL;
		}
	}

	/**
	 * method to fetch plan reference by plan name
	 * 
	 * @param string $plan
	 * @param Mongodloid_Date $currentDate the affective date
	 * 
	 * @return Mongodloid_Ref
	 */
	public function getPlan($plan, $currentDate = null) {
		if (is_null($currentDate)) {
			$currentDate = new Mongodloid_Date();
		}
		$plansColl = Billrun_Factory::db()->plansCollection();
		$planEntity = $plansColl->query('name', $plan)
				->lessEq('from', $currentDate)
				->greaterEq('to', $currentDate)
				->cursor()->current();
		return $plansColl->createRefByEntity($planEntity);
	}
	
}
