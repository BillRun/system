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

	public function __construct(array $params = array()) {
		$params['collection'] = Billrun_Factory::db()->rates;
		parent::__construct($params);
		$this->search_key = "key";
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

		if (!isset($entity['rates'])) {
			return;
		}
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
								->cursor()->current();
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
		$columns = array(
			'key' => 'Key',
			'from' => 'From',
			'to' => 'To',
			'_id' => 'Id',
		);
		return $columns;
	}

}

