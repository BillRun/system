<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
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
		$cursor = $this->collection->query($filter_query)->cursor();
		$this->_count = $cursor->count();
		return $cursor->current();
	}

	public function getProtectedKeys($entity, $type) {
		$parent_protected = parent::getProtectedKeys($entity, $type);
		if ($type == 'logDetails') {
			$added_fields = array("source", "type", "path", "file_name", "stamp", "received_time", "retrieved_from", "process_time");
			return array_merge($parent_protected, $added_fields);
		}
		return $parent_protected;
	}

	public function getFilterFields() {
		$filter_fields = array(
			'type' => array(
				'key' => 'source',
				'db_key' => 'source',
				'input_type' => 'multiselect',
				'comparison' => '$in',
				'display' => 'Type',
				'values' => Billrun_Factory::config()->getConfigValue('admin_panel.log.source'),
				'default' => array(),
			),
		);
		return array_merge($filter_fields, parent::getFilterFields());
	}

	public function getFilterFieldsOrder() {
		$filter_field_order = array(
			0 => array(
				'type' => array(
					'width' => 1,
				),
			),
		);
		return $filter_field_order;
	}

}
