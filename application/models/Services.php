<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Services model class to pull data from database for services collection
 *
 * @package  Models
 * @subpackage Table
 * @since    5.1
 */
class ServicesModel extends PlansModel {

	public function __construct(array $params = array()) {
		$params['collection'] = Billrun_Factory::db()->plans;
		parent::__construct($params);
		$this->search_key = "name";
	}

	public function getTableColumns() {
		$columns = array(
			'name' => 'Service',
			'desc' => "Description",
			'from' => "From",
			'to' => "Expiration"
		);
		return $columns;
	}

	public function getSortFields() {
		$sort_fields = array(
			'name' => 'Service',
			'price' => 'Price'
		);
		return array_merge($sort_fields, parent::getSortFields());
	}

	/**
	 * for every rate who has ref to original plan add ref to new plan
	 * @param type $source_id
	 * @param type $new_id
	 */
	public function duplicate_rates($source_id, $new_id) {
		$rates_col = Billrun_Factory::db()->ratesCollection();
		$source_ref = MongoDBRef::create("services", new mongoId($source_id));
		$dest_ref = MongoDBRef::create("services", $new_id);
		$usage_types = Billrun_Factory::config()->getConfigValue('admin_panel.line_usages');
		foreach ($usage_types as $type => $string) {
			$attribute = "rates." . $type . ".services";
			$query = array($attribute => $source_ref);
			$update = array('$push' => array($attribute => $dest_ref));
			$params = array("multiple" => 1);
			$rates_col->update($query, $update, $params);
		}
	}
}