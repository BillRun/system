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
	 *
	 * @var type 
	 */
	protected $db;

	public function __construct() {
		$db = Billrun_Factory::config()->getConfigValue('wholesale.db');
		$this->db = Zend_Db::factory('Pdo_Mysql', array(
					'host' => $db['host'],
					'username' => $db['username'],
					'password' => $db['password'],
					'dbname' => $db['name']
		));
	}

	public function getStats($group_field, $from_day, $to_day) {
		return array(
			'incoming_call' => $this->getCall('TG', $group_field, $from_day, $to_day),
			'outgoing_call' => $this->getCall('FG', $group_field, $from_day, $to_day),
		);
	}

	/**
	 * 
	 * @param string $direction FG
	 * @param string $network nr or empty
	 * 
	 * @return array of results
	 */
	public function getCall($direction, $group_field, $from_day, $to_day, $carrier = null, $network = 'all') {
		$sub_query = 'SELECT usaget, dayofmonth, longname as carrier, sum(duration) as seconds,'
				. 'CASE WHEN carrier like "N%" and direction like "TG" THEN sum(duration)/60*0.0614842117289702 ELSE sum(duration)/60*0.0614842117289702 END as cost'
				. ' FROM wholesale left join cgr_compressed on wholesale.carrier=cgr_compressed.shortname'
				. ' WHERE direction like "' . $direction . '" AND network like "' . $network . '" AND dayofmonth BETWEEN "' . $from_day . '" AND "' . $to_day . '"'
				. ' GROUP BY dayofmonth,carrier,usaget,direction'
				. ' ORDER BY usaget,dayofmonth,longname';
		
		$query = 'SELECT ' . $group_field . ' AS group_by, usaget ,sum(seconds) as duration, round(sum(cost),2) as cost from (' . $sub_query . ') as sq';

		if ($carrier) {
			$query .= ' WHERE carrier LIKE "' . $carrier . '"';
		}
		
		$query .= ' GROUP BY '. $group_field;
		
		$callData= $this->db->fetchAll($query);
		return $callData;
	}

	public function getGroupFields() {
		$group_fields = array(
			'group_by' => array(
				'key' => 'group_by',
				'input_type' => 'select',
				'display' => 'Group by',
				'values' => array('dayofmonth' => array('display' => 'Day of month', 'popup' => 'carrier'), 'carrier' => array('display' => 'Carrier', 'popup' => 'dayofmonth')),
				'default' => 'dayofmonth',
			),
		);
		return $group_fields;
	}

	public function getFilterFields() {
		$filter_fields = array(
			'from' => array(
				'key' => 'from_day',
				'db_key' => 'dayofmonth',
				'input_type' => 'date',
				'comparison' => '$gte',
				'display' => 'From day',
				'default' => (new Zend_Date(strtotime('60 days ago'), null, new Zend_Locale('he_IL')))->toString('YYYY-MM-dd'),
			),
			'to' => array(
				'key' => 'to_day',
				'db_key' => 'dayofmonth',
				'input_type' => 'date',
				'comparison' => '$lte',
				'display' => 'To day',
				'default' => (new Zend_Date(time(), null, new Zend_Locale('he_IL')))->toString('YYYY-MM-dd'),
			),
		);
		return $filter_fields;
	}

}
