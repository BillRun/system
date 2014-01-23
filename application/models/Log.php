<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Log model class
 *
 * @package  Models
 * @subpackage Table
 * @since    0.5
 */
class LogModel extends TableModel {

	public function __construct(array $params = array()) {
		$params['collection'] = Billrun_Factory::db()->log;
		parent::__construct($params);
		$this->search_key = "stamp";
	}

	public function getTableColumns() {
		$columns = array(
			'source' => 'Source',
			'type' => 'Type',
			'retrieved_from' => 'Retrieved from',
			'file_name' => 'Filename',
			'received_time' => 'Date received',
			'process_time' => 'Date processed',
			'_id' => 'Id',
		);
		return $columns;
	}

	public function toolbar() {
		return 'log';
	}

	public function getSortFields() {
		$sort_fields = array(
			'source' => 'Source',
			'type' => 'Type',
			'retrieved_from' => 'Retrieved from',
			'file_name' => 'Filename',
			'received_time' => 'Date received',
			'process_time' => 'Date processed',
		);
		return $sort_fields;
	}

	public function getDataByStamp($filter_query = array()) {
		$cursor = $this->collection->query($filter_query)->cursor()->setReadPreference(MongoClient::RP_SECONDARY_PREFERRED);
		$this->_count = $cursor->count();
		return $cursor->current();
	}

	public function getProtectedKeys($entity, $type) {
		$parent_protected = parent::getProtectedKeys($entity, $type);
		if ($type == 'logDetails') {
			return array_merge($parent_protected, array("path", "file_name", "stamp", "received_time"));
		}
		return $parent_protected;
	}

}
