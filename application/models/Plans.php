<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
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
			'name' => 'Name',
			'from' => 'From',
			'to' => 'To',
			'_id' => 'Id',
			'price' => 'Price',
		);
		return $columns;
	}

	public function getSortFields() {
		$sort_fields = array(
			'name' => 'Name',
			'price' => 'Price',
		);
		return array_merge($sort_fields, parent::getSortFields());
	}

	public function update($params) {
		$source_id = $params['source_id'];
		unset($params['source_id']); // we don't save because admin ref issues
		$duplicate = $params['duplicate_rates'];
		unset($params['duplicate_rates']);
		$oldPlanName = Billrun_Factory::db()->plansCollection()->query(array('_id' => new mongoId($source_id)))->cursor()->current()['name'];
		$newPlanName = $params['name'];
		if ($newPlanName != $oldPlanName && isset($params['include']['groups'][$oldPlanName])) {
			$params['include']['groups'][$newPlanName] = $params['include']['groups'][$oldPlanName];
			unset($params['include']['groups'][$oldPlanName]);
		}
		$entity = parent::update($params);
		if ($duplicate) {
			if ($newPlanName != $oldPlanName) {
				self::duplicate_rates($oldPlanName, $newPlanName);
			}
		}
		return $entity;
	}

	/**
	 * for every rate who has ref to original plan add ref to new plan
	 * @param type $oldPlanName
	 * @param type $newPlanName
	 */
	public function duplicate_rates($oldPlanName, $newPlanName) {
		$rates_col = Billrun_Factory::db()->ratesCollection();
		$usage_types = Billrun_Factory::config()->getConfigValue('admin_panel.line_usages');
		foreach ($usage_types as $type => $string) {
			$attribute = "rates." . $type . ".groups";
			$query = array($attribute => $oldPlanName);
			$update = array('$push' => array($attribute => array('$each' => array($newPlanName), '$position' => 0)));
			$params = array("multiple" => 1);
			$rates_col->update($query, $update, $params);
		}
	}

}
