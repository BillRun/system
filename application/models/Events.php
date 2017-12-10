<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Events model class
 *
 * @package  Models
 * @subpackage Table
 * @since    0.5
 */
class EventsModel extends TableModel {

	public function __construct(array $params = array()) {
		if (isset($params['collection'])) {
			unset($params['collection']);
		}
		parent::__construct($params);
		$this->collection = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('fraud.db'))->eventsCollection();
		$this->collection_name = 'events';
		$this->search_key = "stamp";
	}

	public function getTableColumns() {
		$columns = array(
			'creation_time' => 'Creation time',
			'event_type' => 'Event type',
			'imsi' => 'IMSI',
			'msisdn' => 'MSISDN',
			'source' => 'Source',
			'threshold' => 'Threshold',
			'units' => 'Units',
			'value' => 'Value',
			'notify_time' => 'Notify time',
			'priority' => 'Priority',
//			'_id' => 'Id',
		);
		if (!empty($this->extra_columns)) {
			$extra_columns = array_intersect_key($this->getExtraColumns(), array_fill_keys($this->extra_columns, ""));
			$columns = array_merge($columns, $extra_columns);
		}
		return $columns;
	}

	public function getFilterFields() {

		$search_by_values = array(
			'event_type' => 'Event type',
			'source' => 'Source',
		);

		$operators = array(
			'equals' => 'equals',
			'like' => 'contains',
			'ne' => 'not equals',
			'starts_with' => 'starts with',
			'ends_with' => 'ends with',
		);


		$filter_fields = array(
			'aid' => array(
				'key' => 'aid',
				'db_key' => array('aid', 'returned_value.account_id'),
				'input_type' => 'number',
				'comparison' => 'equals',
				'display' => 'Account id',
				'default' => '',
			),
			'sid' => array(
				'key' => 'sid',
				'db_key' => array('sid', 'returned_value.subscriber_id'),
				'input_type' => 'number',
				'comparison' => 'equals',
				'display' => 'Subscriber id',
				'default' => '',
			),
			'search_by' => array(
				'key' => 'manual_key',
				'db_key' => 'nofilter',
				'input_type' => 'multiselect',
				'display' => 'Search by',
				'values' => $search_by_values,
				'singleselect' => 1,
				'default' => array(),
			),
			'usage_filter' => array(
				'key' => 'manual_operator',
				'db_key' => 'nofilter',
				'input_type' => 'multiselect',
				'display' => '',
				'values' => $operators,
				'singleselect' => 1,
				'default' => array(),
			),
			'usage_value' => array(
				'key' => 'manual_value',
				'db_key' => 'nofilter',
				'input_type' => 'text',
				'display' => '',
				'default' => '',
			),
		);

		return array_merge($filter_fields, parent::getFilterFields());
	}

	public function getFilterFieldsOrder() {
		$filter_field_order = array(
			0 => array(
				'aid' => array(
					'width' => 2,
				),
				'sid' => array(
					'width' => 2,
				),
			),
			1 => array(
				'search_by' => array(
					'width' => 2,
				),
				'usage_filter' => array(
					'width' => 1,
				),
				'usage_value' => array(
					'width' => 1,
				),
			),
		);
		return $filter_field_order;
	}

	public function getSortFields() {
		$sort_fields = array(
			'creation_time' => 'Creation time',
			'event_type' => 'Event type',
			'imsi' => 'IMSI',
			'msisdn' => 'MSISDN',
			'source' => 'Source',
			'threshold' => 'Threshold',
			'units' => 'Units',
			'value' => 'Value',
			'notify_time' => 'Notify time',
			'priority' => 'Priority',
		);
		return $sort_fields;
	}

	public function getData($filter_query = array()) {
		$resource = parent::getData($filter_query);
		$this->_count = $resource->count(false);
		return $resource;
	}

}
