<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2014 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Configmodel class
 *
 * @package  Models
 * @since    2.1
 */
class WholesaleModel {

	/**
	 * the collection the config run on
	 * 
	 * @var Mongodloid Collection
	 */
	protected $collection;

	public function __construct() {
		// load the config data from db
		$this->collection = Billrun_Factory::db(array('name' => 'billrun'))->wholesaleCollection();
	}

	public function getStats($group_field) {
		return array(
			'incoming_call' => $this->getCall('TG', $group_field),
			'outgoing_call' => $this->getCall('FG', $group_field),
		);
	}
	
	public function getCgr() {
		return iterator_to_array(Billrun_Factory::db(array('name' => 'billrun'))->cgrCollection()->query()->cursor());
	}
	
	/**
	 * 
	 * @param string $direction FG
	 * @param string $network nr or empty
	 * 
	 * @return array of results
	 */
	protected function getCall($direction, $group_field, $network = 'all', $daysCount = 60) {
		
		$match = array(
			'$match' => array(
				'dayofmonth' => array('$gte' => date('Y-m-d', strtotime($daysCount . ' days ago'))),
				'direction' => $direction,
				'network' => $network,
			),
		);
		$group = array(
			'$group' => array(
				'_id' => array(
					'group_by' => '$' . $group_field,
					'usaget' => '$usaget',
				),
				'duration' => array(
					'$sum' => '$duration',
				),
			)
		);
		$project = array(
			'$project' => array(
				'_id' => 0,
				'group_by' => '$_id.group_by',
				'usaget' => '$_id.usaget',
				'duration' => 1,
			)
		);
		
		$sort = array(
			'$sort' => array(
				'group_by' => 1,
				'usaget' => 1,
			),
		);
		
//		$usaget_group = array(
//			'$group' => array(
//				
//			),
//		);
		$callData = $this->collection->aggregate($match, $group, $project, $sort);
		
		return $callData;

	}
	
	public function getGroupFields() {
		$group_fields = array(
			'group_by' => array(
				'key' => 'group_by',
				'input_type' => 'select',
				'display' => 'Group by',
				'values' => array('dayofmonth' => 'Day of month', 'carrier' => 'Carrier'),
				'default' => 'dayofmonth',
			),
		);
		return $group_fields;
	}
	
}
