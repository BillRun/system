<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
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
		return $columns;
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

}
