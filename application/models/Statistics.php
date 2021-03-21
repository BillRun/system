<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class is to hold the logic for the Statistics module.
 *
 * @package  Models
 * @subpackage Table
 * @since    4.0
 */
class StatisticsModel extends TableModel {

	protected $statistics_coll;

	/**
	 * constructor
	 * 
	 * @param array $params of parameters to preset the object
	 */
	public function __construct(array $params = array()) {
		$params['collection'] = Billrun_Factory::db()->statistics;
		parent::__construct($params);
		$this->statistics_coll = Billrun_Factory::db()->statisticsCollection();
	}

}
